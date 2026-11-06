<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\ApprovalDelegation;
use App\Services\WorkflowService;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100', 'from' => 'nullable|date', 'to' => 'nullable|date']);
        $u = auth()->user();
        $onBehalf = ApprovalDelegation::delegatorIdsFor($u->id); // approvers who delegated to me today
        $base = Approval::where(fn ($q) => $q->where('approver_id', $u->id)->orWhere('requester_id', $u->id)
            ->orWhere(fn ($d) => $d->whereIn('approver_id', $onBehalf)->where('status', 'Pending')));
        // Filter options come from the rows this user can see, and input must match one of them
        $statuses = (clone $base)->distinct()->orderBy('status')->pluck('status')->all();
        $types = (clone $base)->distinct()->orderBy('type')->pluck('type')->all();
        $q = (clone $base)->with(['requester', 'approver']);
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('code', 'like', "%{$s}%")->orWhere('type', 'like', "%{$s}%")
                ->orWhereHas('requester', fn ($r) => $r->where('name', 'like', "%{$s}%")));
        }
        if (in_array($v = $request->query('status'), $statuses, true)) {
            $q->where('status', $v);
        }
        if (in_array($v = $request->query('type'), $types, true)) {
            $q->where('type', $v);
        }
        if ($v = $request->query('from')) $q->whereDate('created_at', '>=', $v);
        if ($v = $request->query('to')) $q->whereDate('created_at', '<=', $v);
        $inbox = $q->orderByRaw("status = 'Pending' desc")->latest()->paginate(config('csa.per_page'))->withQueryString();
        return view('approvals.index', compact('inbox', 'onBehalf', 'statuses', 'types'));
    }

    public function decide(Request $request, Approval $approval, WorkflowService $wf)
    {
        $data = $request->validate([
            'decision' => 'required|in:Approved,Rejected,Returned',
            'note' => 'required_unless:decision,Approved|nullable|string|max:500',
        ]);
        [$ok, $msg] = $wf->decideApproval($approval, auth()->user(), $data['decision'], $data['note'] ?? null);
        return back()->with($ok ? 'ok' : 'err', $msg);
    }
}
