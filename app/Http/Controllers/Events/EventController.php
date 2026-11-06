<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\CsaEvent;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    public function index(Request $request)
    {
        CsaEvent::syncApprovals();
        $request->validate(['q' => 'nullable|string|max:100', 'from' => 'nullable|date', 'to' => 'nullable|date']);
        $q = CsaEvent::with(['owner', 'invitations', 'opportunities']);
        if (in_array($s = $request->query('status'), CsaEvent::STATUSES, true)) {
            $q->where('status', $s);
        }
        if ($term = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")->orWhere('venue', 'like', "%{$term}%"));
        }
        if ($request->query('mine')) {
            $q->where('owner_id', auth()->id());
        }
        if ($v = $request->query('from')) $q->whereDate('event_date', '>=', $v);
        if ($v = $request->query('to')) $q->whereDate('event_date', '<=', $v);

        // KPI cards cover the whole filtered set, not just the current page
        $base = (clone $q)->toBase();
        $kpi = (clone $base)->selectRaw("count(*) as n, coalesce(sum(budget), 0) as budget, coalesce(sum(actual), 0) as actual, sum(case when status = 'PENDING_APPROVAL' then 1 else 0 end) as pending")->first();
        $kpi->roi = (float) DB::table('csa_event_opportunity')->join('opportunities', 'opportunities.id', '=', 'csa_event_opportunity.opportunity_id')
            ->whereIn('csa_event_opportunity.csa_event_id', (clone $base)->select('csa_events.id'))->sum('opportunities.value');

        return view('events.index', [
            'events' => $q->orderBy('event_date')->paginate(config('csa.per_page'))->withQueryString(),
            'kpi' => $kpi,
            'statuses' => CsaEvent::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(CsaEvent::canCreate(auth()->user()), 403);
        $d = $request->validate([
            'name' => 'required|string|max:200',
            'event_date' => 'required|date',
            'venue' => 'nullable|string|max:200',
            'objective' => 'nullable|string|max:2000',
            'budget' => 'nullable|numeric|min:0|max:100000000',
        ]);
        $e = CsaEvent::create(array_merge($d, [
            'code' => WorkflowService::nextCode('EVT', 'csa_events'),
            'budget' => $d['budget'] ?? 0,
            'status' => 'DRAFT',
            'owner_id' => auth()->id(),
        ]));
        AuditLog::record('EVENT_CREATED', 'event', $e->id, ['name' => $e->name]);
        return $request->filled('quick_create_page')
            ? redirect()->route('events.index')->with('ok', "Event {$e->code} created as Draft.")
            : ($request->filled('quick_create')
                ? redirect()->route('dashboard')->with('ok', "Event {$e->code} created as Draft.")
                : redirect()->route('events.show', $e)->with('ok', "Event {$e->code} created as Draft."));
    }

    public function show(CsaEvent $event)
    {
        CsaEvent::syncApprovals();
        $event->refresh()->load(['owner', 'approval.approver', 'invitations', 'opportunities.customer']);
        $u = auth()->user();
        return view('events.show', [
            'e' => $event,
            'canManage' => $event->canManage($u),
            'canDecide' => CsaEvent::isApprover($u) && $event->status === 'PENDING_APPROVAL'
                && $event->approval && $event->approval->requester_id !== $u->id,
            'history' => Approval::with('approver')->where('subject_type', 'event')->where('subject_id', $event->id)->latest('id')->get(),
            'opportunities' => Opportunity::with('customer')->whereNotIn('id', $event->opportunities->pluck('id'))->orderBy('code')->get(),
        ]);
    }

    // Plan details and budget can only change while Draft (snapshot is frozen once submitted)
    public function update(Request $request, CsaEvent $event)
    {
        abort_unless($event->canManage(auth()->user()), 403);
        if (! $event->isEditable()) {
            return back()->with('err', 'Only Draft events can be edited; the submitted budget snapshot is immutable.');
        }
        $d = $request->validate([
            'name' => 'required|string|max:200',
            'event_date' => 'required|date',
            'venue' => 'nullable|string|max:200',
            'objective' => 'nullable|string|max:2000',
            'budget' => 'required|numeric|min:0|max:100000000',
        ]);
        $event->update($d);
        AuditLog::record('EVENT_UPDATED', 'event', $event->id, ['budget' => (float) $event->budget]);
        return back()->with('ok', 'Event updated.');
    }

    public function actual(Request $request, CsaEvent $event)
    {
        abort_unless($event->canManage(auth()->user()), 403);
        if (! in_array($event->status, ['APPROVED', 'ACTIVE/HELD', 'POST_MORTEM'], true)) {
            return back()->with('err', 'Actual cost can be recorded only after budget approval and before closure.');
        }
        $d = $request->validate(['actual' => 'required|numeric|min:0|max:100000000']);
        $old = (float) $event->actual;
        $event->update(['actual' => $d['actual']]);
        AuditLog::record('EVENT_ACTUAL_COST', 'event', $event->id, ['old' => $old, 'new' => (float) $d['actual']]);
        return back()->with('ok', $event->overBudget() ? 'Actual cost saved — over approved budget.' : 'Actual cost saved.');
    }

    public function submit(CsaEvent $event)
    {
        $u = auth()->user();
        abort_unless($event->canManage($u), 403);

        return DB::transaction(function () use ($event, $u) {
            $e = CsaEvent::whereKey($event->id)->lockForUpdate()->first();
            if ($e->status !== 'DRAFT') {
                return back()->with('err', "Event in status {$e->status} cannot be submitted.");
            }
            if ((float) $e->budget <= 0) {
                return back()->with('err', 'Enter a budget before submitting for approval.');
            }
            // HOD first, else AHOD; never the requester (no self-approval)
            $approver = User::whereIn('role', CsaEvent::APPROVER_ROLES)->where('active', true)->where('id', '!=', $u->id)
                ->orderByRaw("role = 'HOD' desc")->orderBy('id')->first();
            if (! $approver) {
                return back()->with('err', 'No HOD / AHOD available to approve (requester cannot approve own event).');
            }
            $a = Approval::create([
                'code' => WorkflowService::nextCode('APR', 'approvals'),
                'type' => 'Event/Budget',
                'subject_type' => 'event',
                'subject_id' => $e->id,
                'requester_id' => $u->id,
                'approver_id' => $approver->id,
                'snapshot' => [ // immutable submission snapshot
                    'event' => $e->code,
                    'name' => $e->name,
                    'date' => $e->event_date->format('Y-m-d H:i'),
                    'venue' => $e->venue,
                    'budget' => (float) $e->budget,
                    'objective' => $e->objective,
                    'owner' => $e->owner?->name,
                ],
            ]);
            $e->update(['status' => 'PENDING_APPROVAL', 'approval_id' => $a->id]);
            AuditLog::record('APPROVAL_REQUESTED', 'approval', $a->id, ['type' => $a->type, 'event' => $e->code]);
            return back()->with('ok', "Budget approval {$a->code} sent to {$approver->name}.");
        });
    }

    // HOD / AHOD decision from the event page; same rules as the Approval Inbox
    public function decide(Request $request, CsaEvent $event, WorkflowService $wf)
    {
        $u = auth()->user();
        abort_unless(CsaEvent::isApprover($u), 403);
        $d = $request->validate([
            'decision' => 'required|in:Approved,Rejected,Returned',
            'note' => 'required_unless:decision,Approved|nullable|string|max:500',
        ]);
        if ($event->status !== 'PENDING_APPROVAL' || ! $event->approval) {
            return back()->with('err', 'This event has no pending budget approval.');
        }
        [$ok, $msg] = $wf->decideApproval($event->approval, $u, $d['decision'], $d['note'] ?? null);
        CsaEvent::syncApprovals();
        return back()->with($ok ? 'ok' : 'err', $msg);
    }

    // APPROVED → ACTIVE/HELD → POST_MORTEM → CLOSED
    public function advance(CsaEvent $event)
    {
        abort_unless($event->canManage(auth()->user()), 403);
        $next = ['APPROVED' => 'ACTIVE/HELD', 'ACTIVE/HELD' => 'POST_MORTEM', 'POST_MORTEM' => 'CLOSED'][$event->status] ?? null;
        if (! $next) {
            return back()->with('err', $event->status === 'DRAFT' || $event->status === 'PENDING_APPROVAL'
                ? 'Budget must be approved by HOD / AHOD before the event can proceed.'
                : "No further lifecycle step from {$event->status}.");
        }
        if ($next === 'CLOSED' && blank($event->pm_lessons)) {
            return back()->with('err', 'Close blocked: save the post-mortem (lessons learned) first.');
        }
        $event->update(['status' => $next]);
        AuditLog::record('EVENT_STATUS', 'event', $event->id, ['status' => $next]);
        return back()->with('ok', "Event moved to {$next}.");
    }

    public function linkOpportunity(Request $request, CsaEvent $event)
    {
        abort_unless($event->canManage(auth()->user()), 403);
        if ($event->status === 'CLOSED') {
            return back()->with('err', 'Closed events cannot be changed.');
        }
        $d = $request->validate(['opportunity_id' => 'required|exists:opportunities,id']);
        $event->opportunities()->syncWithoutDetaching([$d['opportunity_id']]);
        AuditLog::record('EVENT_OPPORTUNITY_LINKED', 'event', $event->id, ['opportunity_id' => (int) $d['opportunity_id']]);
        return back()->with('ok', 'Opportunity linked to event.');
    }

    public function unlinkOpportunity(CsaEvent $event, Opportunity $opportunity)
    {
        abort_unless($event->canManage(auth()->user()), 403);
        if ($event->status === 'CLOSED') {
            return back()->with('err', 'Closed events cannot be changed.');
        }
        $event->opportunities()->detach($opportunity->id);
        AuditLog::record('EVENT_OPPORTUNITY_UNLINKED', 'event', $event->id, ['opportunity_id' => $opportunity->id]);
        return back()->with('ok', 'Opportunity unlinked.');
    }
}
