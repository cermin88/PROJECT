<?php

namespace App\Http\Controllers\Ecosystem;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Award;
use App\Models\Opportunity;
use App\Models\Partner;
use App\Models\PartnerAgreement;
use App\Models\PartnerContact;
use App\Models\Product;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartnerController extends Controller
{
    public function index(Request $request)
    {
        $showArchived = $request->boolean('archived') && Access::archivesPartner(auth()->user());
        $q = Partner::with('owner')->withCount('products')->where('archived', $showArchived);
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('reg_no', 'like', "%{$s}%")->orWhere('classification', 'like', "%{$s}%"));
        }
        if ($c = $request->query('classification')) {
            $q->where('classification', 'like', "%{$c}%");
        }
        if ($request->query('preferred') === '1') {
            $q->where('preferred', true);
        }
        return view('ecosystem.partners.index', [
            'partners' => $q->orderBy('name')->paginate(config('csa.per_page'))->withQueryString(),
            'showArchived' => $showArchived,
            'canEdit' => Access::editsEcosystem(auth()->user()),
        ]);
    }

    public function create()
    {
        abort_unless(Access::editsEcosystem(auth()->user()), 403);
        return view('ecosystem.partners.form', ['partner' => new Partner(['rating' => 3, 'status' => 'Active', 'owner_id' => auth()->id()]), 'owners' => $this->owners()]);
    }

    public function store(Request $request)
    {
        abort_unless(Access::editsEcosystem(auth()->user()), 403);
        $data = $this->validated($request);
        if ($dup = $this->duplicateWarning($request, $data)) {
            return back()->withInput()->with('err', $dup);
        }
        $p = Partner::create($data);
        AuditLog::record('PARTNER_CREATED', 'partner', $p->id, ['name' => $p->name]);
        return $request->filled('quick_create_page')
            ? redirect()->route('partners.index')->with('ok', "Partner {$p->ref()} created.")
            : ($request->filled('quick_create')
                ? redirect()->route('dashboard')->with('ok', "Partner {$p->ref()} created.")
                : redirect()->route('partners.show', $p)->with('ok', "Partner {$p->ref()} created."));
    }

    public function show(Partner $partner)
    {
        $partner->load(['owner', 'contacts', 'agreements', 'products', 'opportunities.customer', 'tenders']);
        $commercial = Access::seesCommercial(auth()->user());
        // Association only: qualified awards on tenders this partner is linked to
        $awarded = (float) Award::qualified()->whereIn('tender_id', $partner->tenders->pluck('id'))->sum('value');
        return view('ecosystem.partners.show', [
            'partner' => $partner,
            'commercial' => $commercial,
            'awarded' => $awarded,
            'canEdit' => Access::editsEcosystem(auth()->user()) && ! $partner->archived,
            'canArchive' => Access::archivesPartner(auth()->user()),
            'linkableProducts' => Product::where(fn ($q) => $q->whereNull('partner_id')->orWhere('partner_id', '!=', $partner->id))->orderBy('name')->get(),
            'linkableOpps' => $commercial ? Opportunity::whereNotIn('id', $partner->opportunities->pluck('id'))->orderBy('code')->get(['id', 'code', 'name']) : collect(),
            'linkableTenders' => $commercial ? Tender::whereNotIn('id', $partner->tenders->pluck('id'))->orderBy('code')->get(['id', 'code', 'title']) : collect(),
            'audit' => AuditLog::with('user')->where('entity_type', 'partner')->where('entity_id', $partner->id)->latest('id')->limit(30)->get(),
        ]);
    }

    public function edit(Partner $partner)
    {
        abort_unless(Access::editsEcosystem(auth()->user()) && ! $partner->archived, 403);
        return view('ecosystem.partners.form', ['partner' => $partner, 'owners' => $this->owners()]);
    }

    public function update(Request $request, Partner $partner)
    {
        abort_unless(Access::editsEcosystem(auth()->user()) && ! $partner->archived, 403);
        $data = $this->validated($request);
        if ($dup = $this->duplicateWarning($request, $data, $partner->id)) {
            return back()->withInput()->with('err', $dup);
        }
        $partner->update($data);
        AuditLog::record('PARTNER_UPDATED', 'partner', $partner->id, ['changed' => array_keys($partner->getChanges())]);
        return redirect()->route('partners.show', $partner)->with('ok', 'Partner saved.');
    }

    // Historical links are kept; the partner just leaves the active list
    public function archive(Request $request, Partner $partner)
    {
        abort_unless(Access::archivesPartner(auth()->user()), 403);
        $restore = $request->boolean('restore');
        $partner->update(['archived' => ! $restore, 'status' => $restore ? 'Active' : 'Inactive']);
        AuditLog::record($restore ? 'PARTNER_RESTORED' : 'PARTNER_ARCHIVED', 'partner', $partner->id);
        return redirect()->route($restore ? 'partners.show' : 'partners.index', $restore ? $partner : [])
            ->with('ok', $restore ? 'Partner restored.' : "{$partner->name} archived; historical links retained.");
    }

    public function addContact(Request $request, Partner $partner)
    {
        $this->authorizeEdit($partner);
        $d = $request->validate([
            'name' => 'required|string|max:255', 'position' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255', 'mobile' => 'nullable|string|max:40',
        ]);
        $partner->contacts()->create($d);
        AuditLog::record('PARTNER_CONTACT_ADDED', 'partner', $partner->id, ['name' => $d['name']]);
        return back()->with('ok', 'Contact added.');
    }

    public function removeContact(Partner $partner, PartnerContact $contact)
    {
        $this->authorizeEdit($partner);
        abort_unless($contact->partner_id === $partner->id, 404);
        $contact->delete();
        AuditLog::record('PARTNER_CONTACT_REMOVED', 'partner', $partner->id, ['name' => $contact->name]);
        return back()->with('ok', 'Contact removed.');
    }

    public function addAgreement(Request $request, Partner $partner)
    {
        $this->authorizeEdit($partner);
        $d = $request->validate([
            'type' => ['required', Rule::in(Partner::AGREEMENT_TYPES)],
            'status' => ['required', Rule::in(PartnerAgreement::STATUSES)],
            'expiry_date' => 'nullable|date',
        ]);
        $partner->agreements()->create($d);
        AuditLog::record('PARTNER_AGREEMENT_ADDED', 'partner', $partner->id, $d);
        return back()->with('ok', 'Agreement registered.');
    }

    public function updateAgreement(Request $request, Partner $partner, PartnerAgreement $agreement)
    {
        $this->authorizeEdit($partner);
        abort_unless($agreement->partner_id === $partner->id, 404);
        $d = $request->validate(['status' => ['required', Rule::in(PartnerAgreement::STATUSES)], 'expiry_date' => 'nullable|date']);
        $agreement->update($d);
        AuditLog::record('PARTNER_AGREEMENT_UPDATED', 'partner', $partner->id, ['agreement' => $agreement->id] + $d);
        return back()->with('ok', 'Agreement updated.');
    }

    public function linkProduct(Request $request, Partner $partner)
    {
        $this->authorizeEdit($partner);
        $d = $request->validate(['product_id' => 'required|exists:products,id']);
        $product = Product::findOrFail($d['product_id']);
        $product->update(['partner_id' => $partner->id]);
        AuditLog::record('PARTNER_PRODUCT_LINKED', 'partner', $partner->id, ['product' => $product->ref()]);
        return back()->with('ok', "{$product->name} now represented by {$partner->name}.");
    }

    // Opportunity / Tender association (many partners per record, many records per partner)
    public function link(Request $request, Partner $partner)
    {
        $this->authorizeEdit($partner);
        abort_unless(Access::seesCommercial(auth()->user()), 403);
        $d = $request->validate(['type' => 'required|in:opportunity,tender', 'id' => 'required|integer']);
        $rel = $d['type'] === 'opportunity' ? $partner->opportunities() : $partner->tenders();
        $model = $d['type'] === 'opportunity' ? Opportunity::findOrFail($d['id']) : Tender::findOrFail($d['id']);
        $rel->syncWithoutDetaching([$model->id]); // linking twice is a no-op
        AuditLog::record('PARTNER_LINKED', 'partner', $partner->id, ['type' => $d['type'], 'code' => $model->code]);
        return back()->with('ok', "Linked {$model->code}.");
    }

    public function unlink(Partner $partner, string $type, int $id)
    {
        $this->authorizeEdit($partner);
        abort_unless(in_array($type, ['opportunity', 'tender'], true), 404);
        ($type === 'opportunity' ? $partner->opportunities() : $partner->tenders())->detach($id);
        AuditLog::record('PARTNER_UNLINKED', 'partner', $partner->id, compact('type', 'id'));
        return back()->with('ok', 'Association removed.');
    }

    private function authorizeEdit(Partner $partner): void
    {
        abort_unless(Access::editsEcosystem(auth()->user()) && ! $partner->archived, 403);
    }

    private function owners()
    {
        return User::whereIn('role', ['HOD', 'AHOD', 'BD Manager', 'BD Executive', 'Sales Admin', 'Sales Admin / Inside Sales'])->orderBy('name')->get();
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'name' => 'required|string|max:255',
            'reg_no' => 'nullable|string|max:60',
            'owner_id' => ['required', Rule::exists('users', 'id')],
            'status' => 'required|in:Active,Inactive',
            'website' => 'nullable|url|max:255',
            'rating' => 'required|integer|min:1|max:5',
            'classifications' => 'required|array|min:1',
            'classifications.*' => Rule::in(Partner::CLASSIFICATIONS),
            'notes' => 'nullable|string|max:2000',
        ], ['classifications.required' => 'Select at least one classification.']);
        $d['classification'] = implode(', ', array_unique($d['classifications']));
        $d['preferred'] = $request->boolean('preferred');
        unset($d['classifications']);
        return $d;
    }

    // Blocks a likely duplicate unless the user ticked "save anyway"
    private function duplicateWarning(Request $request, array $d, ?int $exceptId = null): ?string
    {
        if ($request->boolean('confirm_duplicate')) {
            return null;
        }
        $dups = Partner::duplicatesOf($d['name'], $d['reg_no'] ?? null, $exceptId);
        if ($dups->isEmpty()) {
            return null;
        }
        return 'Possible duplicate: ' . $dups->map(fn ($p) => "{$p->ref()} · {$p->name}")->implode(', ')
            . '. Review the existing record, or tick "Save anyway" to continue.';
    }
}
