<?php

namespace App\Http\Controllers\Work;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    use WorkAccess;

    public function index(Request $request)
    {
        $u = auth()->user();
        $q = $this->scopeTasks(Task::with(['assignee', 'customer', 'opportunity', 'tender', 'actionItem.minute']), $u);
        $s = $request->query('status', 'open');
        match ($s) {
            'open' => $q->open(),
            'overdue' => $q->overdue(),
            'all' => null,
            default => $q->where('status', $s),
        };
        if ($v = $request->query('assignee_id')) $q->where('assignee_id', $v);
        if ($request->query('mine')) $q->where('assignee_id', $u->id);

        return view('work.tasks.index', $this->formData($u) + [
            'tasks' => $q->orderBy('due_date')->paginate(config('csa.per_page'))->withQueryString(),
            'status' => $s,
        ]);
    }

    public function store(Request $request)
    {
        $d = $this->validated($request);
        $t = $this->spawnTask($d);
        return $request->filled('quick_create_page')
            ? redirect()->route('tasks.index')->with('ok', "Task {$t->code} created for {$t->assignee->name}.")
            : ($request->filled('quick_create')
                ? redirect()->route('dashboard')->with('ok', "Task {$t->code} created for {$t->assignee->name}.")
                : back()->with('ok', "Task {$t->code} created for {$t->assignee->name}."));
    }

    public function edit(Task $task)
    {
        abort_unless($this->canTouchTask($task, auth()->user()), 403);
        return view('work.tasks.edit', $this->formData(auth()->user()) + ['t' => $task]);
    }

    public function update(Request $request, Task $task)
    {
        abort_unless($this->canTouchTask($task, auth()->user()), 403);
        if (in_array($task->status, Task::CLOSED, true)) {
            return back()->with('err', "Task is {$task->status} and can no longer be edited.");
        }
        $d = $this->validated($request);
        $before = $task->only(['assignee_id', 'due_date']);
        $task->update($d);
        AuditLog::record('TASK_UPDATED', 'task', $task->id, ['before' => ['assignee_id' => $before['assignee_id'], 'due' => $before['due_date']?->toDateString()]]);
        return redirect()->route('tasks.index')->with('ok', "Task {$task->code} updated.");
    }

    // Not Started -> In Progress -> Completed
    public function advance(Task $task)
    {
        abort_unless($this->canTouchTask($task, auth()->user()), 403);
        $next = $task->nextStatus();
        if (! $next) {
            return back()->with('err', "Task is already {$task->status}.");
        }
        $task->update(['status' => $next, 'completed_at' => $next === 'Completed' ? now() : null]);
        AuditLog::record('TASK_STATUS', 'task', $task->id, ['status' => $next]);
        return back()->with('ok', "Task {$task->code} → {$next}.");
    }

    public function cancel(Request $request, Task $task)
    {
        abort_unless($this->canTouchTask($task, auth()->user()), 403);
        $d = $request->validate(['reason' => 'required|string|max:255']);
        if (in_array($task->status, Task::CLOSED, true)) {
            return back()->with('err', "Task is already {$task->status}.");
        }
        $task->update(['status' => 'Cancelled']);
        AuditLog::record('TASK_STATUS', 'task', $task->id, ['status' => 'Cancelled', 'reason' => $d['reason']]);
        return back()->with('ok', "Task {$task->code} cancelled.");
    }

    private function validated(Request $request): array
    {
        $u = auth()->user();
        $d = $request->validate([
            'title' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'assignee_id' => 'nullable|exists:users,id',
            'due_date' => 'required|date',
            'customer_id' => 'nullable|exists:customers,id',
            'opportunity_id' => 'nullable|exists:opportunities,id',
            'tender_id' => 'nullable|exists:tenders,id',
        ]);
        $d['assignee_id'] = (int) ($d['assignee_id'] ?? $u->id);
        // Only managers / leads may hand work to someone else
        abort_unless($d['assignee_id'] === $u->id || $this->canAssignOthers($u), 403);
        return $d;
    }

    private function formData(User $u): array
    {
        return [
            'statuses' => Task::STATUSES,
            'assignees' => $this->canAssignOthers($u) ? User::where('active', true)->orderBy('name')->get() : collect([$u]),
            'customers' => Customer::where('archived', false)->orderBy('name')->get(['id', 'name']),
            'opps' => Opportunity::orderBy('code')->get(['id', 'code', 'name']),
            'tenders' => Tender::orderBy('code')->get(['id', 'code', 'title']),
        ];
    }
}
