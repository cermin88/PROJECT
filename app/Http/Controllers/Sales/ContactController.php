<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $q = Contact::with('customer')->whereNull('archived_at')->whereHas('customer', fn ($c) => $c->active());
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")
                ->orWhere('mobile', 'like', "%{$s}%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")));
        }
        foreach (['customer_id', 'buying_role', 'relationship'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }
        return view('sales.contacts.index', [
            'contacts' => $q->orderBy('name')->paginate(config('csa.per_page'))->withQueryString(),
            'customers' => Customer::active()->orderBy('name')->get(['id', 'name']),
            'canCreate' => SalesRules::canCreate(auth()->user()),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(SalesRules::canCreate(auth()->user()), 403);
        $data = $this->validated($request);
        if ($dup = $this->duplicate($data)) {
            return back()->withInput()->with('err', "Duplicate candidate: {$dup->name} ({$dup->email}{$dup->mobile}) already exists for this customer — review the existing contact.");
        }
        $c = Contact::create($data);
        AuditLog::record('CONTACT_CREATED', 'contact', $c->id, ['customer' => $c->customer_id]);
        return $request->filled('quick_create_page')
            ? redirect()->route('contacts.index')->with('ok', "Contact {$c->name} created.")
            : ($request->filled('quick_create')
                ? redirect()->route('dashboard')->with('ok', "Contact {$c->name} created.")
                : back()->with('ok', "Contact {$c->name} created."));
    }

    public function edit(Contact $contact)
    {
        abort_unless($this->canEdit($contact), 403);
        return view('sales.contacts.edit', [
            'contact' => $contact,
            'customers' => Customer::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Contact $contact)
    {
        abort_unless($this->canEdit($contact), 403);
        $data = $this->validated($request);
        if ($dup = $this->duplicate($data, $contact->id)) {
            return back()->withInput()->with('err', "Duplicate candidate: {$dup->name} already exists for this customer.");
        }
        $contact->update($data);
        AuditLog::record('CONTACT_UPDATED', 'contact', $contact->id, ['changed' => array_keys($contact->getChanges())]);
        return redirect()->route('contacts.index')->with('ok', 'Contact updated.');
    }

    // Owner of the organisation, or HOD / AHOD / Superadmin
    private function canEdit(Contact $contact): bool
    {
        return SalesRules::canEdit(auth()->user(), $contact->customer?->owner_id);
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('archived', 0)],
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|string|max:30',
            'position' => 'nullable|string|max:120',
            'division' => 'nullable|string|max:120',
            'buying_role' => ['nullable', Rule::in(SalesRules::BUYING_ROLES)],
            'relationship' => ['nullable', Rule::in(SalesRules::RELATIONSHIPS)],
        ]);
        $d['relationship'] = $d['relationship'] ?? 'Warm';
        return $d;
    }

    // Prototype rule: same customer + same name + same email or mobile
    private function duplicate(array $d, ?int $exceptId = null): ?Contact
    {
        if (empty($d['email']) && empty($d['mobile'])) {
            return null;
        }
        return Contact::where('customer_id', $d['customer_id'])->whereRaw('lower(name) = ?', [mb_strtolower($d['name'])])
            ->where(fn ($w) => $w->when($d['email'] ?? null, fn ($x, $e) => $x->orWhere('email', $e))
                ->when($d['mobile'] ?? null, fn ($x, $m) => $x->orWhere('mobile', $m)))
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->first();
    }
}
