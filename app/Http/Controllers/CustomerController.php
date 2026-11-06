<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Sales\SalesRules;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public const TABS = ['Overview', 'Contacts', 'Opportunities', 'Tenders', 'Commercial', 'Cases', 'Documents', 'History'];

    public function index(Request $request)
    {
        $user = $request->user();
        $canViewAllCustomers = SalesRules::canViewAllCustomers($user);
        $q = Customer::with('owner')->where('archived', false)
            ->when(! $canViewAllCustomers, fn ($query) => $query->where('owner_id', $user->id));
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%")->orWhere('sector', 'like', "%{$s}%"));
        }
        foreach (['type', 'relationship'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }
        if ($canViewAllCustomers && ($ownerId = $request->query('owner_id'))) {
            $q->where('owner_id', $ownerId);
        }
        return view('customers.index', [
            'customers' => $q->orderBy('name')->paginate(config('csa.per_page'))->withQueryString(),
            'owners' => $canViewAllCustomers
                ? User::whereIn('role', SalesRules::OWNER_ROLES)->orderBy('name')->get(['id', 'name'])
                : collect(),
            'canViewAllCustomers' => $canViewAllCustomers,
            'canCreate' => SalesRules::canCreate(auth()->user()),
        ]);
    }

    // Customer 360 with prototype tabs
    public function show(Request $request, Customer $customer)
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'Overview';
        $user = auth()->user();
        abort_unless(SalesRules::canViewAllCustomers($user) || $customer->owner_id === $user->id, 404);
        $customer->load(['owner', 'contacts', 'opportunities.owner', 'tenders', 'awards', 'cases.assignee']);
        if ($tab === 'Commercial') {
            $customer->setRelation('awards', $customer->awards()->visibleToProjectOwner($user)->get());
        }
        return view('customers.show', [
            'customer' => $customer,
            'tab' => $tab,
            'tabs' => self::TABS,
            'canEdit' => SalesRules::canEdit($user, $customer->owner_id),
            'canArchive' => SalesRules::canArchive($user),
            'canCreate' => SalesRules::canCreate($user),
            'canCreateSales' => SalesRules::canCreateSales($user),
            'canCommercial' => SalesRules::canSeeCommercial($user),
            'projects' => $tab === 'Commercial'
                ? Project::visibleTo($user)->whereIn('award_id', $customer->awards->pluck('id'))->get()
                : collect(),
            'documents' => $tab === 'Documents' ? Document::where('context_type', 'customer')->where('context_id', $customer->id)
                ->when(! $user->canSeeRestricted(), fn ($q) => $q->where('restricted', false))->get() : collect(),
            'history' => $tab === 'History' ? AuditLog::with('user')->where('entity_type', 'customer')->where('entity_id', $customer->id)->latest('id')->take(50)->get() : collect(),
            // current owner stays selectable so saving the form never transfers ownership by accident
            'owners' => User::whereIn('role', SalesRules::OWNER_ROLES)->orWhere('id', $customer->owner_id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(SalesRules::canCreate(auth()->user()), 403);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in(SalesRules::CUSTOMER_TYPES)],
            'sector' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
        ]);
        // Duplicate detection on organisation name + type + state (prototype rule)
        $dup = Customer::whereRaw('lower(name) = ?', [mb_strtolower($data['name'])])->where('type', $data['type'])
            ->where('state', $data['state'] ?? null)->first();
        if ($dup) {
            return back()->withInput()->with('err', "Potential duplicate: {$dup->code} {$dup->name}. Review the existing record before proceeding.");
        }
        $c = Customer::create($data + [
            'code' => WorkflowService::nextCode('CUS', 'customers'),
            'owner_id' => auth()->id(),
            'sector' => $data['sector'] ?? 'Unclassified',
            'last_engagement_at' => now(),
        ]);
        AuditLog::record('CUSTOMER_CREATED', 'customer', $c->id);
        return $request->filled('quick_create_page')
            ? redirect()->route('customers.index')->with('ok', 'Customer created.')
            : ($request->filled('quick_create')
                ? redirect()->route('dashboard')->with('ok', 'Customer created.')
                : redirect()->route('customers.show', $c)->with('ok', 'Customer created.'));
    }

    public function update(Request $request, Customer $customer)
    {
        $user = auth()->user();
        abort_unless(SalesRules::canEdit($user, $customer->owner_id), 403);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in(SalesRules::CUSTOMER_TYPES)],
            'sector' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'parent' => 'nullable|string|max:255',
            'relationship' => ['required', Rule::in(SalesRules::RELATIONSHIPS)],
            'owner_id' => 'nullable|exists:users,id',
        ]);
        // Ownership transfer is a privileged edit
        if (! SalesRules::canArchive($user)) {
            unset($data['owner_id']);
        }
        $old = $customer->only(array_keys($data));
        $customer->update($data);
        AuditLog::record('CUSTOMER_UPDATED', 'customer', $customer->id, ['from' => array_intersect_key($old, $customer->getChanges())]);
        return back()->with('ok', 'Customer updated.');
    }

    public function archive(Customer $customer)
    {
        abort_unless(SalesRules::canArchive(auth()->user()), 403);
        // keep the Archive View (UX-063) fields in step with the legacy flag
        $customer->forceFill(['archived' => true, 'archived_at' => now(), 'archived_by' => auth()->id(), 'archive_reason' => 'Archived from Customer 360'])->save();
        AuditLog::record('ARCHIVE', 'customer', $customer->id, ['reason' => 'Archived through controlled action']);
        return redirect()->route('customers.index')->with('ok', "{$customer->name} archived.");
    }
}
