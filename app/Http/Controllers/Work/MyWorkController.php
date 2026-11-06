<?php

namespace App\Http\Controllers\Work;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Approval;
use App\Models\ApprovalDelegation;
use App\Models\MeetingMinute;
use App\Models\Task;
use App\Models\Tender;

class MyWorkController extends Controller
{
    use WorkAccess;

    public function index()
    {
        $u = auth()->user();
        $tasks = $this->scopeTasks(Task::query(), $u);
        $liveTenders = Tender::whereNotIn('status', ['AWARDED', 'LOST', 'CANCELLED', 'NO_BID']);

        return view('work.mywork', [
            'all' => $this->seesAll($u),
            'openTasks' => (clone $tasks)->open()->count(),
            'overdueCount' => (clone $tasks)->overdue()->count(),
            // includes items delegated to me (UX-071), same as the header Approvals button
            'myApprovals' => Approval::whereIn('approver_id', [$u->id, ...ApprovalDelegation::delegatorIdsFor($u->id)])->where('status', 'Pending')->count()
                + ($this->canApproveMom($u) ? MeetingMinute::where('status', 'PENDING_APPROVAL')->where('author_id', '!=', $u->id)->count() : 0),
            'tenderRisks' => (clone $liveTenders)->where('risk', 'High')->count(),
            // Daily agenda: open tasks due today or earlier, plus today's planned activities
            'agenda' => (clone $tasks)->open()->with('assignee')->whereDate('due_date', '<=', today())->orderBy('due_date')->limit(10)->get(),
            'todayActivities' => $this->scopeActivities(Activity::with('customer'), $u)->where('status', 'PLANNED')
                ->whereDate('activity_at', today())->orderBy('activity_at')->get(),
            // Weekly priorities
            'weekTasks' => (clone $tasks)->open()->with('assignee')->whereBetween('due_date', [today()->addDay(), today()->addDays(7)])->orderBy('due_date')->get(),
            'closingTenders' => (clone $liveTenders)->whereBetween('closing_at', [now(), now()->addDays(10)])->orderBy('closing_at')->get(),
            'returnedMoms' => MeetingMinute::where('author_id', $u->id)->where('status', 'RETURNED')->get(),
        ]);
    }
}
