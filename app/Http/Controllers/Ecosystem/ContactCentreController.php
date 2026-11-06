<?php

namespace App\Http\Controllers\Ecosystem;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SupportCase;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class ContactCentreController extends Controller
{
    public function dashboard()
    {
        $u = auth()->user();
        abort_unless(Access::viewsCc($u), 403);
        $cases = SupportCase::with('assignee')->visibleTo($u)->get();
        $open = $cases->reject->isDone();
        $done = $cases->filter->isDone();
        $csat = $cases->whereNotNull('csat');

        $aging = ['< 1 day' => 0, '1–3 days' => 0, '3–7 days' => 0, '> 7 days' => 0];
        foreach ($open as $c) {
            $h = $c->agingHours();
            $aging[$h < 24 ? '< 1 day' : ($h < 72 ? '1–3 days' : ($h < 168 ? '3–7 days' : '> 7 days'))]++;
        }

        return view('ecosystem.cc.dashboard', [
            'open' => $open,
            'critical' => $open->where('priority', 'Critical')->count(),
            'breached' => $open->filter->isBreached()->count(),
            'paused' => $open->whereNotNull('paused_at')->count(),
            // Open cases not breached (prototype indicator) and resolved-within-SLA rate
            'compliance' => $open->count() ? round(100 - $open->filter->isBreached()->count() / $open->count() * 100) : 100,
            'resolvedMet' => $done->count() ? round($done->filter(fn ($c) => $c->slaState() === 'Met')->count() / $done->count() * 100) : null,
            'csatAvg' => $csat->count() ? round($csat->avg('csat'), 1) : null,
            'csatCount' => $csat->count(),
            'byStatus' => $cases->countBy('status'),
            'byChannel' => $cases->countBy('channel'),
            'aging' => $aging,
            'workload' => $this->workload($u),
            'hot' => $open->whereIn('priority', ['Critical', 'High'])->sortBy(fn ($c) => $c->remainingMinutes()),
        ]);
    }

    public function assignment(Request $request)
    {
        $u = auth()->user();
        abort_unless(Access::viewsCc($u), 403);
        $q = SupportCase::with(['customer', 'assignee'])->visibleTo($u)->whereNotIn('status', SupportCase::DONE);
        if ($request->query('show') === 'unassigned') {
            $q->whereNull('assignee_id');
        } elseif ($a = $request->query('assignee')) {
            $q->where('assignee_id', (int) $a);
        }
        $this->filter($q, $request);
        $cases = $q->get()->sortBy([
            fn ($a, $b) => array_search($b->priority, SupportCase::PRIORITIES) <=> array_search($a->priority, SupportCase::PRIORITIES),
            fn ($a, $b) => $a->remainingMinutes() <=> $b->remainingMinutes(),
        ]);
        return view('ecosystem.cc.assignment', [
            'cases' => $this->paginate($cases->values(), $request),
            'workload' => $this->workload($u),
            'assignees' => User::whereIn('role', SupportCase::ASSIGNEE_ROLES)->where('active', true)->orderBy('name')->get(),
            'canAssign' => $u->canAssignCases(),
        ]);
    }

    // Assign / reassign; a reason is mandatory when moving a case away from someone
    public function assign(Request $request, SupportCase $case, WorkflowService $wf)
    {
        abort_unless(auth()->user()->canAssignCases(), 403);
        $d = $request->validate([
            'assignee_id' => ['required', Rule::exists('users', 'id')->whereIn('role', SupportCase::ASSIGNEE_ROLES)],
            'reason' => [$case->assignee_id ? 'required' : 'nullable', 'string', 'max:200'],
        ], ['reason.required' => 'A reason is required to reassign a case.']);
        if ($case->isDone()) {
            return back()->with('err', "Case {$case->code} is {$case->status}; reopen it before reassigning.");
        }
        if ((int) $d['assignee_id'] === $case->assignee_id) {
            return back()->with('err', 'Case is already assigned to that person.');
        }
        $from = $case->assignee?->name;
        [$ok, $msg] = $wf->assignCase($case, User::findOrFail($d['assignee_id']), auth()->user());
        if ($ok && $from) {
            $case->timeline()->create(['text' => "Reassigned from {$from}: {$d['reason']}", 'user_id' => auth()->id()]);
            AuditLog::record('CASE_REASSIGNED', 'case', $case->id, ['from' => $from, 'reason' => $d['reason']]);
        }
        return back()->with($ok ? 'ok' : 'err', $ok ? "{$case->code}: {$msg}" : $msg);
    }

    public function sla(Request $request)
    {
        $u = auth()->user();
        abort_unless(Access::viewsCc($u), 403);
        $q = SupportCase::with(['customer', 'assignee'])->visibleTo($u);
        $this->filter($q, $request, dates: true);
        $states = ['Running', 'Paused', 'Breached', 'Met', 'Missed'];
        $cases = $q->latest('opened_at')->get();
        if (in_array($s = $request->query('state'), $states, true)) {
            $cases = $cases->filter(fn ($c) => $c->slaState() === $s);
        }
        // KPIs and CSAT are over the full filtered set, not just the current page
        $rated = $cases->whereNotNull('csat');
        $byState = $cases->countBy(fn ($c) => $c->slaState());
        return view('ecosystem.cc.sla', [
            'cases' => $this->paginate($cases->values(), $request),
            'states' => $states,
            'stateCounts' => collect($states)->mapWithKeys(fn ($st) => [$st => $byState->get($st, 0)]),
            'csatAvg' => $rated->count() ? round($rated->avg('csat'), 1) : null,
            'csatDist' => collect(range(5, 1))->mapWithKeys(fn ($n) => [$n => $rated->where('csat', $n)->count()]),
            'canCapture' => $u->canManageCases(),
        ]);
    }

    // CSAT is captured once, after customer confirmation, by the agent from the customer's feedback
    public function csat(Request $request, SupportCase $case)
    {
        $u = auth()->user();
        abort_unless($u->canManageCases() && $case->visibleTo($u), 403);
        $d = $request->validate(['csat' => 'required|integer|min:1|max:5', 'comment' => 'nullable|string|max:150']);
        if (! $case->confirmation_method) {
            return back()->with('err', 'CSAT can only be captured after customer confirmation is recorded.');
        }
        if ($case->csat) {
            return back()->with('err', "CSAT already captured ({$case->csat}/5).");
        }
        $case->update(['csat' => $d['csat']]);
        $case->timeline()->create(['text' => "CSAT {$d['csat']}/5 captured" . (! empty($d['comment']) ? ": {$d['comment']}" : ''), 'user_id' => $u->id]);
        AuditLog::record('CASE_CSAT', 'case', $case->id, ['csat' => $d['csat']]);
        return back()->with('ok', "CSAT recorded for {$case->code}.");
    }

    // Shared list filters: free text over code / title / customer, whitelisted priority, optional opened date range
    private function filter($q, Request $request, bool $dates = false): void
    {
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('code', 'like', "%{$s}%")->orWhere('title', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")));
        }
        if (in_array($p = $request->query('priority'), SupportCase::PRIORITIES, true)) {
            $q->where('priority', $p);
        }
        foreach ($dates ? ['from' => '>=', 'to' => '<='] : [] as $k => $op) {
            if (($d = $request->query($k)) && strtotime($d)) {
                $q->whereDate('opened_at', $op, $d);
            }
        }
    }

    // Rows are ordered / filtered in PHP, so paginate the collection
    private function paginate($items, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));
        return new LengthAwarePaginator($items->forPage($page, config('csa.per_page')), $items->count(), config('csa.per_page'), $page, ['path' => $request->url(), 'query' => $request->query()]);
    }

    // Open-case load per CC user (CC Executives only see their own row)
    private function workload(User $u)
    {
        $users = User::whereIn('role', ['CC Manager', 'CC Team Lead', 'CC Executive'])
            ->when($u->role === 'CC Executive', fn ($q) => $q->whereKey($u->id))->orderBy('role')->orderBy('name')->get();
        $open = SupportCase::whereNotIn('status', SupportCase::DONE)->whereIn('assignee_id', $users->pluck('id'))->get()->groupBy('assignee_id');
        return $users->map(fn ($x) => (object) [
            'user' => $x,
            'open' => ($o = $open->get($x->id, collect()))->count(),
            'critical' => $o->whereIn('priority', ['Critical', 'High'])->count(),
            'breached' => $o->filter->isBreached()->count(),
            'paused' => $o->whereNotNull('paused_at')->count(),
        ]);
    }
}
