<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Sales\SalesRules;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\OpportunityStageHistory;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OpportunityController extends Controller
{
    public const TABS = ['Overview', 'Commercial', 'Tenders', 'Competitors', 'History'];
    public const VALUE_TYPE = 'Major Opportunity Value Change';

    public function index(Request $request)
    {
        $q = Opportunity::with(['customer', 'owner', 'history'])->whereNull('archived_at');
        if ($request->query('view') !== 'all') {
            $q->active(); // default list = Active Pipeline (excludes Lost/Award/Cancelled)
        }
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"));
        }
        foreach (['stage', 'owner_id', 'customer_id'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }
        return view('opportunities.index', [
            'opps' => $q->orderByDesc('value')->paginate(config('csa.per_page'))->withQueryString(),
            'customers' => Customer::active()->orderBy('name')->get(['id', 'name']),
            'owners' => $this->owners(),
            'stages' => Opportunity::STAGES,
            'canCreate' => SalesRules::canCreateSales(auth()->user()),
        ]);
    }

    // Opportunity workspace (UX-012)
    public function show(Request $request, Opportunity $opportunity)
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'Overview';
        $opportunity->load(['customer', 'owner', 'competitor', 'tenders', 'history']);
        $user = auth()->user();
        $valueApprovals = Approval::where('subject_type', 'opportunity')->where('subject_id', $opportunity->id)
            ->where('type', self::VALUE_TYPE)->latest('id')->get();
        return view('opportunities.show', [
            'o' => $opportunity,
            'tab' => $tab,
            'tabs' => self::TABS,
            'stages' => Opportunity::STAGES,
            'nextStages' => $this->nextStages($opportunity),
            'canEdit' => SalesRules::canEdit($user, $opportunity->owner_id),
            'canCreateSales' => SalesRules::canCreateSales($user),
            'canCommercial' => SalesRules::canSeeCommercial($user),
            'valueApprovals' => $valueApprovals,
            'applicable' => $valueApprovals->first(fn ($a) => $a->status === 'Approved' && ! $this->applied($a)),
            'audit' => $tab === 'History' ? AuditLog::with('user')->where('entity_type', 'opportunity')->where('entity_id', $opportunity->id)->latest('id')->get() : collect(),
        ]);
    }

    public function pipeline()
    {
        $active = Opportunity::active()->with(['customer', 'history'])->get();
        return view('opportunities.pipeline', [
            'board' => $active->groupBy('stage'),
            'stages' => Opportunity::STAGES,
            'm' => [
                'value' => $active->sum('value'),
                'count' => $active->count(),
                'stale' => $active->filter(fn ($o) => $o->lastStageAt()->lt(now()->subDays(SalesRules::STALE_DAYS)))->count(),
                'overdue' => $active->filter(fn ($o) => $o->next_action_date && $o->next_action_date->lt(today()))->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(SalesRules::canCreateSales(auth()->user()), 403);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('archived', 0)],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->whereIn('role', SalesRules::OWNER_ROLES)],
            'stage' => ['required', Rule::in(Opportunity::STAGES)],
            'next_action' => 'required|string|max:255',
            'next_action_date' => 'required|date',
            'value' => 'nullable|numeric|min:0', // may be blank early
        ]);
        $idx = array_search($data['stage'], Opportunity::STAGES, true);
        if ($idx >= 3 && empty($data['value'])) {
            return back()->withInput()->with('err', 'Estimated Value is mandatory from Budget Approval & Evaluation onwards.');
        }
        $o = Opportunity::create([
            'value' => $data['value'] ?? 0,
            'owner_id' => $data['owner_id'] ?? auth()->id(),
        ] + $data + [
            'code' => WorkflowService::nextCode('OPP', 'opportunities'),
            'probability' => Opportunity::STAGE_PROBABILITY[$idx],
        ]);
        OpportunityStageHistory::create([
            'opportunity_id' => $o->id, 'stage' => $o->stage, 'changed_by' => auth()->id(), 'reason' => 'Created',
        ]);
        AuditLog::record('OPPORTUNITY_CREATED', 'opportunity', $o->id);
        return $request->filled('quick_create_page')
            ? redirect()->route('opportunities.index')->with('ok', "Opportunity {$o->code} created.")
            : ($request->filled('quick_create')
                ? redirect()->route('dashboard')->with('ok', "Opportunity {$o->code} created.")
                : redirect()->route('opportunities.show', $o)->with('ok', "Opportunity {$o->code} created."));
    }

    // Guarded stage transition (prototype confirmOppMove)
    public function stage(Request $request, Opportunity $opportunity)
    {
        abort_unless(SalesRules::canEdit(auth()->user(), $opportunity->owner_id), 403);
        $data = $request->validate([
            'stage' => ['required', Rule::in(array_merge(Opportunity::STAGES, Opportunity::CLOSED))],
            'reason' => 'required_if:stage,Lost,Cancelled|nullable|string|max:255', // reason mandatory when closing out
        ]);
        // 'Award' is reached only through Award qualification, never by dragging a stage
        if ($data['stage'] === 'Award') {
            return back()->withErrors(['stage' => 'Award stage is set automatically when an Award is qualified.']);
        }
        if (in_array($opportunity->stage, Opportunity::CLOSED, true)) {
            return back()->with('err', "Opportunity is already {$opportunity->stage}; ordinary reversal is blocked.");
        }
        $cur = array_search($opportunity->stage, Opportunity::STAGES, true);
        $to = array_search($data['stage'], Opportunity::STAGES, true);
        if ($to !== false) {
            if ($to <= $cur) {
                return back()->with('err', 'Stage transitions move forward only.');
            }
            if ($to >= 3 && (float) $opportunity->value <= 0) {
                return back()->with('err', 'BLOCKED: Estimated Value is mandatory before Budget Approval & Evaluation.');
            }
            if ($to > $cur + 1 && empty($data['reason'])) {
                return back()->with('err', 'Stage skip requires a mandatory reason.');
            }
        }
        $opportunity->update([
            'stage' => $data['stage'],
            'probability' => $to === false ? 0 : Opportunity::STAGE_PROBABILITY[$to],
            'lost_reason' => $data['stage'] === 'Lost' ? $data['reason'] : $opportunity->lost_reason,
        ]);
        OpportunityStageHistory::create([
            'opportunity_id' => $opportunity->id, 'stage' => $data['stage'],
            'changed_by' => auth()->id(), 'reason' => $data['reason'] ?? 'Normal progression',
        ]);
        AuditLog::record('OPPORTUNITY_STAGE', 'opportunity', $opportunity->id, ['stage' => $data['stage']]);
        return back()->with('ok', "Opportunity moved to {$data['stage']}.");
    }

    // Next action is ordinary owner housekeeping
    public function nextAction(Request $request, Opportunity $opportunity)
    {
        abort_unless(SalesRules::canEdit(auth()->user(), $opportunity->owner_id), 403);
        $data = $request->validate(['next_action' => 'required|string|max:255', 'next_action_date' => 'required|date']);
        $opportunity->update($data);
        AuditLog::record('OPPORTUNITY_NEXT_ACTION', 'opportunity', $opportunity->id, $data);
        return back()->with('ok', 'Next action updated.');
    }

    // Changes over 20% need HOD/AHOD approval unless made by HOD/AHOD
    public function value(Request $request, Opportunity $opportunity, WorkflowService $wf)
    {
        $user = auth()->user();
        abort_unless(SalesRules::canEdit($user, $opportunity->owner_id), 403);
        $d = $request->validate(['value' => 'required|numeric|min:0', 'reason' => 'required|string|max:255']);
        $old = (float) $opportunity->value;
        $delta = $old > 0 ? abs($d['value'] - $old) / $old : 1;

        if ($delta > 0.2 && ! SalesRules::canChangeValueDirectly($user)) {
            if (Approval::where('subject_type', 'opportunity')->where('subject_id', $opportunity->id)
                ->where('type', self::VALUE_TYPE)->where('status', 'Pending')->exists()) {
                return back()->with('err', 'A value change is already pending approval.');
            }
            $hod = User::where('role', 'HOD')->first();
            abort_unless($hod, 422, 'No HOD user configured to approve.');
            $a = Approval::create([
                'code' => WorkflowService::nextCode('APR', 'approvals'),
                'type' => self::VALUE_TYPE, 'subject_type' => 'opportunity', 'subject_id' => $opportunity->id,
                'requester_id' => $user->id, 'approver_id' => $hod->id,
                'snapshot' => ['summary' => "{$opportunity->code}: RM " . number_format($old) . ' → RM ' . number_format($d['value']),
                    'old_value' => $old, 'new_value' => (float) $d['value'], 'reason' => $d['reason']],
            ]);
            AuditLog::record('APPROVAL_REQUESTED', 'approval', $a->id, ['type' => $a->type, 'opportunity' => $opportunity->code]);
            return back()->with('ok', "Change exceeds 20% — approval {$a->code} sent to {$hod->name}.");
        }
        $opportunity->update(['value' => $d['value']]);
        AuditLog::record('OPPORTUNITY_VALUE_CHANGED', 'opportunity', $opportunity->id, ['from' => $old, 'to' => (float) $d['value'], 'reason' => $d['reason']]);
        return back()->with('ok', 'Estimated value updated.');
    }

    // Applies an approved value change exactly once
    public function applyValue(Opportunity $opportunity, Approval $approval)
    {
        abort_unless(SalesRules::canEdit(auth()->user(), $opportunity->owner_id), 403);
        abort_unless($approval->subject_type === 'opportunity' && $approval->subject_id == $opportunity->id && $approval->type === self::VALUE_TYPE, 404);
        if ($approval->status !== 'Approved') {
            return back()->with('err', "Approval {$approval->code} is {$approval->status}; value unchanged.");
        }
        if ($this->applied($approval)) {
            return back()->with('ok', 'Approved value was already applied (no duplicate change).');
        }
        $opportunity->update(['value' => $approval->snapshot['new_value']]);
        AuditLog::record('OPPORTUNITY_VALUE_APPLIED', 'opportunity', $opportunity->id, ['approval' => $approval->id, 'to' => $approval->snapshot['new_value']]);
        return back()->with('ok', 'Approved value applied.');
    }

    private function applied(Approval $a): bool
    {
        return AuditLog::where('action', 'OPPORTUNITY_VALUE_APPLIED')->where('entity_id', $a->subject_id)
            ->get()->contains(fn ($l) => ($l->detail['approval'] ?? null) == $a->id);
    }

    private function nextStages(Opportunity $o): array
    {
        $cur = array_search($o->stage, Opportunity::STAGES, true);
        return $cur === false ? [] : array_slice(Opportunity::STAGES, $cur + 1);
    }

    private function owners()
    {
        return User::whereIn('role', SalesRules::OWNER_ROLES)->orderBy('name')->get(['id', 'name']);
    }
}
