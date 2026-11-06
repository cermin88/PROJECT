<?php

namespace App\Http\Controllers\Work;

use App\Models\AuditLog;
use App\Models\Task;
use App\Models\User;
use App\Services\WorkflowService;

// Role rules for the Work module (from the prototype: HOD/AHOD/Management see everyone's work, others their own)
trait WorkAccess
{
    protected function seesAll(User $u): bool
    {
        return in_array($u->role, ['Management', 'HOD', 'AHOD', 'Superadmin'], true);
    }

    // May give a task to someone other than themselves
    protected function canAssignOthers(User $u): bool
    {
        return $this->seesAll($u) || in_array($u->role, ['BD Manager', 'CC Manager', 'CC Team Lead'], true);
    }

    // MoM approval sits with HOD / AHOD (approval matrix)
    protected function canApproveMom(User $u): bool
    {
        return in_array($u->role, ['HOD', 'AHOD'], true);
    }

    protected function canTouchTask(Task $t, User $u): bool
    {
        return $this->seesAll($u) || in_array($u->id, [$t->assignee_id, $t->created_by], true);
    }

    protected function scopeTasks($q, User $u)
    {
        return $this->seesAll($u) ? $q : $q->where(fn ($w) => $w->where('assignee_id', $u->id)->orWhere('created_by', $u->id));
    }

    protected function scopeActivities($q, User $u)
    {
        return $this->seesAll($u) ? $q : $q->where('owner_id', $u->id);
    }

    protected function scopeMinutes($q, User $u)
    {
        return $this->seesAll($u) ? $q : $q->where(fn ($w) => $w->where('author_id', $u->id)
            ->orWhereHas('attendees', fn ($a) => $a->where('user_id', $u->id))
            ->orWhereHas('actionItems', fn ($a) => $a->where('assignee_id', $u->id)));
    }

    protected function spawnTask(array $attrs): Task
    {
        $task = Task::create($attrs + [
            'code' => WorkflowService::nextCode('TSK', 'tasks'),
            'status' => 'Not Started',
            'created_by' => auth()->id(),
        ]);
        AuditLog::record('TASK_CREATED', 'task', $task->id, ['title' => $task->title]);
        return $task;
    }
}
