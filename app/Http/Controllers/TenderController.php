<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Sales\SalesRules;
use App\Http\Controllers\Sales\TenderBoardController;
use App\Models\AuditLog;
use App\Models\Award;
use App\Models\Opportunity;
use App\Models\Tender;
use App\Models\TenderChecklistItem;
use App\Models\TenderStatusHistory;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TenderController extends Controller
{
    public const CHECKLIST = [
        'Tender document purchased/received', 'Mandatory company documents', 'Technical proposal finalised',
        'Commercial pricing finalised', 'Submission packaging verified',
    ];

    public function index(Request $request)
    {
        $q = Tender::with(['customer', 'owner', 'checklist'])->whereNull('archived_at');
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"));
        }
        foreach (['status', 'risk', 'owner_id'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }
        return view('tenders.index', [
            'tenders' => $q->orderBy('closing_at')->paginate(config('csa.per_page'))->withQueryString(),
            'states' => Tender::STATES,
            'owners' => User::whereIn('role', SalesRules::OWNER_ROLES)->orderBy('name')->get(['id', 'name']),
            'opps' => Opportunity::active()->orderBy('name')->get(['id', 'code', 'name']),
            'canCreate' => SalesRules::canCreateSales(auth()->user()),
        ]);
    }

    // Tender War Room
    public function show(Tender $tender)
    {
        $tender->load(['customer', 'owner', 'checklist', 'history', 'opportunity', 'retenderOf', 'retenders', 'awards']);
        $mandatoryOpen = $tender->checklist->where('mandatory', true)->where('done', false)->count();
        $user = auth()->user();
        return view('tenders.show', [
            'tender' => $tender,
            'mandatoryOpen' => $mandatoryOpen,
            'blockers' => collect(TenderBoardController::blockers($tender)),
            'canWork' => SalesRules::canCreateSales($user),
            'canEdit' => SalesRules::canEdit($user, $tender->owner_id),
            'canDecide' => SalesRules::canDecide($user),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(SalesRules::canCreateSales(auth()->user()), 403);
        $d = $request->validate([
            'opportunity_id' => 'required|exists:opportunities,id',
            'title' => 'required|string|max:255',
            'closing_at' => 'required|date',
        ]);
        $opp = Opportunity::findOrFail($d['opportunity_id']);
        $t = DB::transaction(function () use ($d, $opp) {
            $t = Tender::create($d + [
                'code' => WorkflowService::nextCode('TND', 'tenders'),
                'customer_id' => $opp->customer_id, 'owner_id' => auth()->id(), 'status' => 'DRAFT', 'risk' => 'Low',
            ]);
            foreach (self::CHECKLIST as $label) {
                $t->checklist()->create(['label' => $label, 'mandatory' => true]);
            }
            TenderStatusHistory::create(['tender_id' => $t->id, 'status' => 'DRAFT', 'changed_by' => auth()->id()]);
            return $t;
        });
        AuditLog::record('TENDER_CREATED', 'tender', $t->id, ['opportunity' => $opp->code]);
        return $request->filled('quick_create_page')
            ? redirect()->route('tenders.index')->with('ok', "Draft tender {$t->code} created.")
            : ($request->filled('quick_create')
                ? redirect()->route('dashboard')->with('ok', "Draft tender {$t->code} created.")
                : redirect()->route('tenders.show', $t)->with('ok', "Draft tender {$t->code} created."));
    }

    public function toggleChecklist(Tender $tender, TenderChecklistItem $item)
    {
        abort_unless(SalesRules::canCreateSales(auth()->user()), 403);
        abort_unless($item->tender_id === $tender->id, 404);
        abort_if($tender->status === 'SUBMITTED', 422, 'Submitted tenders are read-only.');
        $item->update(['done' => ! $item->done]);
        AuditLog::record('CHECKLIST_UPDATE', 'tender', $tender->id, ['item' => $item->label, 'done' => $item->done]);
        return back();
    }

    public function savePricing(Request $request, Tender $tender)
    {
        abort_unless(SalesRules::canCreateSales(auth()->user()), 403);
        $data = $request->validate(['cost' => 'required|numeric|min:0', 'price' => 'required|numeric|min:0']);
        // Any price change invalidates a previous approval
        $changed = (float) $tender->cost !== (float) $data['cost'] || (float) $tender->price !== (float) $data['price'];
        $tender->update($data + ($changed ? ['pricing_approved' => false] : []));
        if ($changed) {
            AuditLog::record('TENDER_PRICING_CHANGED', 'tender', $tender->id, $data);
        }
        return back()->with('ok', $changed ? 'Pricing saved; re-approval required.' : 'Pricing unchanged.');
    }

    public function requestApproval(Tender $tender, WorkflowService $wf)
    {
        abort_unless(SalesRules::canCreateSales(auth()->user()), 403);
        [$ok, $msg] = $wf->requestPricingApproval($tender, auth()->user());
        return back()->with($ok ? 'ok' : 'err', $msg);
    }

    public function submit(Tender $tender, WorkflowService $wf)
    {
        abort_unless(SalesRules::canEdit(auth()->user(), $tender->owner_id), 403);
        [$ok, $msg] = $wf->submitTender($tender, auth()->user());
        return back()->with($ok ? 'ok' : 'err', $msg);
    }

    // DRAFT → BID_ASSESSMENT → PREPARATION (Bid) | NO_BID
    public function bid(Request $request, Tender $tender)
    {
        $user = auth()->user();
        $d = $request->validate([
            'decision' => 'required|in:assess,bid,no_bid',
            'reason' => 'required_if:decision,no_bid|nullable|string|max:255',
        ]);
        if ($d['decision'] === 'assess') {
            abort_unless(SalesRules::canEdit($user, $tender->owner_id), 403);
            if ($tender->status !== 'DRAFT') {
                return back()->with('err', 'Only a Draft tender can start bid assessment.');
            }
            return $this->transition($tender, 'BID_ASSESSMENT', 'Bid assessment started.');
        }
        // Bid / No-Bid is an approval-type decision
        abort_unless(SalesRules::canDecide($user), 403);
        if ($tender->status !== 'BID_ASSESSMENT') {
            return back()->with('err', 'Bid / No-Bid can only be decided during Bid Assessment.');
        }
        if ($d['decision'] === 'no_bid') {
            $tender->no_bid_reason = $d['reason'];
            return $this->transition($tender, 'NO_BID', 'No-Bid recorded.', ['reason' => $d['reason']]);
        }
        return $this->transition($tender, 'PREPARATION', 'Bid approved — tender moved to Preparation.');
    }

    // Post-submission outcomes (prototype setTenderOutcome)
    public function outcome(Request $request, Tender $tender)
    {
        abort_unless(SalesRules::canEdit(auth()->user(), $tender->owner_id), 403);
        $d = $request->validate([
            'status' => ['required', Rule::in(['TECHNICAL_EVALUATION', 'COMMERCIAL_EVALUATION', 'SHORTLISTED', 'AWARDED', 'LOST', 'CANCELLED'])],
            'reason' => 'required_if:status,LOST|nullable|string|max:255',
        ]);
        if (in_array($tender->status, Tender::FINAL, true) || $tender->status === 'NO_BID') {
            return back()->with('err', 'Final result is controlled; ordinary reversal is blocked.');
        }
        if (! in_array($tender->status, Tender::POST_SUBMISSION, true) && $d['status'] !== 'CANCELLED') {
            return back()->with('err', 'Post-submission outcome requires the tender to be Submitted first.');
        }
        return DB::transaction(function () use ($tender, $d) {
            if ($d['status'] === 'LOST') {
                $tender->loss_reason = $d['reason'];
            }
            $msg = "{$tender->code} → {$d['status']}.";
            // An Awarded tender generates exactly one pending-evidence Award
            if ($d['status'] === 'AWARDED' && ! Award::where('tender_id', $tender->id)->exists()) {
                $a = Award::create([
                    'code' => WorkflowService::nextCode('AWD', 'awards'), 'title' => "Award — {$tender->title}",
                    'customer_id' => $tender->customer_id, 'opportunity_id' => $tender->opportunity_id, 'tender_id' => $tender->id,
                    'value' => $tender->price, 'award_date' => today(), 'status' => 'PENDING_EVIDENCE', 'revenue_qualified' => false,
                ]);
                AuditLog::record('AUTO_CREATE', 'award', $a->id, ['tender' => $tender->code]);
                $msg .= " Award {$a->code} created (pending evidence, contributes RM0 until qualified).";
            }
            return $this->transition($tender, $d['status'], $msg, ['reason' => $d['reason'] ?? null]);
        });
    }

    public function retender(Tender $tender)
    {
        abort_unless(SalesRules::canEdit(auth()->user(), $tender->owner_id), 403);
        if ($tender->status !== 'CANCELLED') {
            return back()->with('err', 'Only a Cancelled tender can create a Retender.');
        }
        if ($existing = $tender->retenders()->first()) {
            return redirect()->route('tenders.show', $existing)->with('ok', "Retender {$existing->code} already exists (no duplicate created).");
        }
        $n = DB::transaction(function () use ($tender) {
            $n = $tender->replicate(['code', 'status', 'submitted_at', 'pricing_approved', 'loss_reason', 'no_bid_reason']);
            $n->fill([
                'code' => WorkflowService::nextCode('TND', 'tenders'), 'title' => $tender->title . ' — Retender',
                'status' => 'DRAFT', 'pricing_approved' => false, 'retender_of_id' => $tender->id, 'owner_id' => $tender->owner_id ?? auth()->id(),
            ])->save();
            foreach ($tender->checklist as $c) {
                $n->checklist()->create(['label' => $c->label, 'mandatory' => $c->mandatory, 'done' => false]);
            }
            TenderStatusHistory::create(['tender_id' => $n->id, 'status' => 'DRAFT', 'changed_by' => auth()->id()]);
            return $n;
        });
        AuditLog::record('CREATE_RETENDER', 'tender', $n->id, ['source' => $tender->code]);
        return redirect()->route('tenders.show', $n)->with('ok', "Retender {$n->code} created from {$tender->code}.");
    }

    private function transition(Tender $tender, string $status, string $msg, array $detail = [])
    {
        $tender->status = $status;
        $tender->save();
        TenderStatusHistory::create(['tender_id' => $tender->id, 'status' => $status, 'changed_by' => auth()->id()]);
        AuditLog::record('TENDER_STATUS', 'tender', $tender->id, ['status' => $status] + array_filter($detail));
        return back()->with('ok', $msg);
    }
}
