<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\SupportCase;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CaseController extends Controller
{
    public function index(Request $request)
    {
        $u = auth()->user();
        $q = SupportCase::with(['customer', 'assignee'])->visibleTo($u); // CC Executive: own cases only
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('code', 'like', "%{$s}%")->orWhere('title', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")));
        }
        foreach (['status' => SupportCase::STATUSES, 'priority' => SupportCase::PRIORITIES, 'channel' => SupportCase::CHANNELS] as $f => $allowed) {
            if (in_array($v = $request->query($f), $allowed, true)) {
                $q->where($f, $v);
            }
        }
        if ($a = $request->query('assignee')) {
            $a === 'none' ? $q->whereNull('assignee_id') : $q->where('assignee_id', (int) $a);
        }
        foreach (['from' => '>=', 'to' => '<='] as $k => $op) {
            if (($d = $request->query($k)) && strtotime($d)) {
                $q->whereDate('opened_at', $op, $d);
            }
        }
        if ($request->query('sla') === 'paused') {
            $q->whereNotNull('paused_at');
        } elseif ($request->query('sla') === 'breached') {
            // SLA state is computed in PHP, so filter the open set
            $ids = (clone $q)->whereNotIn('status', SupportCase::DONE)->get()->filter->isBreached()->pluck('id');
            $q->whereIn('id', $ids);
        }
        return view('cases.index', [
            'cases' => $q->latest('opened_at')->paginate(config('csa.per_page'))->withQueryString(),
            'statuses' => SupportCase::STATUSES,
            'assignees' => User::whereIn('role', SupportCase::ASSIGNEE_ROLES)->orderBy('name')->get(),
            'customers' => $u->canManageCases() ? Customer::where('archived', false)->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->canManageCases(), 403);
        $d = $request->validate([
            'title' => 'required|string|max:255',
            'customer_id' => 'required|exists:customers,id',
            'contact_id' => ['nullable', Rule::exists('contacts', 'id')->where('customer_id', $request->input('customer_id'))],
            'channel' => ['required', Rule::in(SupportCase::CHANNELS)],
            'priority' => ['required', Rule::in(SupportCase::PRIORITIES)],
        ], ['contact_id.exists' => 'Contact must belong to the selected customer.']);
        // Default to the customer's first contact, as the prototype does
        $d['contact_id'] ??= Contact::where('customer_id', $d['customer_id'])->value('id');
        $case = SupportCase::create($d + [
            'code' => WorkflowService::nextCode('CAS', 'cases'),
            'status' => 'Open',
            'opened_at' => now(),
            'sla_hours' => SupportCase::SLA_HOURS[$d['priority']],
        ]);
        $case->timeline()->create(['text' => 'Case opened', 'user_id' => auth()->id()]);
        AuditLog::record('CASE_CREATED', 'case', $case->id, ['priority' => $case->priority]);
        return $request->filled('quick_create')
            ? redirect()->route('dashboard')->with('ok', "Case {$case->code} opened ({$case->sla_hours}h resolution SLA).")
            : redirect()->route('cases.show', $case)->with('ok', "Case {$case->code} opened ({$case->sla_hours}h resolution SLA).");
    }

    public function show(SupportCase $case)
    {
        abort_unless($case->visibleTo(auth()->user()), 403, 'CC Executive may only access assigned cases.');
        $case->load(['customer', 'contact', 'assignee', 'timeline.user']);
        return view('cases.show', [
            'case' => $case,
            'executives' => User::whereIn('role', SupportCase::ASSIGNEE_ROLES)->orderBy('name')->get(),
            'statuses' => array_values(array_diff(SupportCase::STATUSES, ['Closed', 'Reopened'])),
            'methods' => SupportCase::CONFIRM_METHODS,
        ]);
    }

    public function status(Request $request, SupportCase $case, WorkflowService $wf)
    {
        $this->authorizeWork($case);
        $d = $request->validate(['status' => 'required|string']);
        [$ok, $msg] = $wf->changeCaseStatus($case, $d['status'], auth()->user());
        if ($ok) {
            $this->syncResolvedAt($case->fresh());
        }
        return back()->with($ok ? 'ok' : 'err', $msg);
    }

    public function assign(Request $request, SupportCase $case, WorkflowService $wf)
    {
        $d = $request->validate(['assignee_id' => ['required', Rule::exists('users', 'id')->whereIn('role', SupportCase::ASSIGNEE_ROLES)]]);
        [$ok, $msg] = $wf->assignCase($case, User::findOrFail($d['assignee_id']), auth()->user());
        return back()->with($ok ? 'ok' : 'err', $msg);
    }

    public function confirm(Request $request, SupportCase $case, WorkflowService $wf)
    {
        $this->authorizeWork($case);
        $d = $request->validate([
            'method' => ['required', Rule::in(SupportCase::CONFIRM_METHODS)],
            'remarks' => 'required|string|max:500', // remarks / evidence are mandatory before Closed
            'csat' => 'nullable|integer|min:1|max:5',
        ]);
        [$ok, $msg] = $wf->confirmCase($case, $d['method'], $d['remarks'], $d['csat'] ?? null, auth()->user());
        if ($ok && $request->boolean('close')) {
            [$ok, $msg] = $wf->changeCaseStatus($case->fresh(), 'Closed', auth()->user());
            $this->syncResolvedAt($case->fresh());
        }
        return back()->with($ok ? 'ok' : 'err', $msg);
    }

    public function note(Request $request, SupportCase $case)
    {
        $this->authorizeWork($case);
        $d = $request->validate(['text' => 'required|string|max:255']);
        $case->timeline()->create(['text' => $d['text'], 'user_id' => auth()->id()]);
        AuditLog::record('CASE_NOTE', 'case', $case->id);
        return back()->with('ok', 'Note added to timeline.');
    }

    public function reopen(Request $request, SupportCase $case, WorkflowService $wf)
    {
        $this->authorizeWork($case);
        $d = $request->validate(['reason' => 'required|string|max:200']);
        if (! $case->isDone()) {
            return back()->with('err', 'Only Resolved or Closed cases can be reopened.');
        }
        [$ok, $msg] = $wf->changeCaseStatus($case, 'Reopened', auth()->user());
        if ($ok) {
            $case->timeline()->create(['text' => "Reopened: {$d['reason']}", 'user_id' => auth()->id()]);
            AuditLog::record('CASE_REOPENED', 'case', $case->id, ['reason' => $d['reason']]);
            $this->syncResolvedAt($case->fresh());
        }
        return back()->with($ok ? 'ok' : 'err', $msg);
    }

    private function authorizeWork(SupportCase $case): void
    {
        $u = auth()->user();
        abort_unless($u->canManageCases() && $case->visibleTo($u), 403);
    }

    // Resolution clock stops at Resolved/Closed and restarts if the case is worked again
    private function syncResolvedAt(SupportCase $c): void
    {
        if ($c->isDone() && ! $c->resolved_at) {
            $c->update(['resolved_at' => now()]);
        } elseif (! $c->isDone() && $c->resolved_at) {
            // time spent sitting in Resolved does not count against the SLA
            $c->update(['resolved_at' => null, 'paused_minutes' => $c->paused_minutes + (int) $c->resolved_at->diffInMinutes(now())]);
        }
    }
}
