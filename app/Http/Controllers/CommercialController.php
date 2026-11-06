<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Renewal;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Http\Request;

class CommercialController extends Controller
{
    public const INVOICE_STATES = ['outstanding' => 'Outstanding', 'overdue' => 'Overdue', 'paid' => 'Fully paid'];

    public function projects(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100']);
        $user = auth()->user();
        $visibleProjects = Project::visibleTo($user);
        $q = (clone $visibleProjects)->with(['award.customer', 'milestones', 'owner']);
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('code', 'like', "%{$s}%")->orWhere('title', 'like', "%{$s}%")
                ->orWhereHas('award.customer', fn ($c) => $c->where('name', 'like', "%{$s}%")));
        }
        $statuses = (clone $visibleProjects)->distinct()->orderBy('status')->pluck('status')->all();
        if (in_array($v = $request->query('status'), $statuses, true)) {
            $q->where('status', $v);
        }
        if ($v = (int) $request->query('owner_id')) {
            $q->where('owner_id', $v);
        }
        return view('commercial.projects', [
            'projects' => $q->orderBy('code')->paginate(config('csa.per_page'))->withQueryString(),
            'statuses' => $statuses,
            'owners' => User::whereIn('id', (clone $visibleProjects)->whereNotNull('owner_id')->select('owner_id'))
                ->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function milestone(Request $request, ProjectMilestone $milestone)
    {
        abort_unless(Project::visibleTo(auth()->user())->whereKey($milestone->project_id)->exists(), 404);
        $d = $request->validate(['progress' => 'required|integer|min:0|max:100']);
        $milestone->update($d);
        return back()->with('ok', 'Milestone updated.');
    }

    public function invoices(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100', 'from' => 'nullable|date', 'to' => 'nullable|date']);
        $q = Invoice::visibleToProjectOwner(auth()->user())->with(['award.customer']);
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('invoice_no', 'like', "%{$s}%")
                ->orWhereHas('award', fn ($a) => $a->where('code', 'like', "%{$s}%")->orWhere('title', 'like', "%{$s}%"))
                ->orWhereHas('award.customer', fn ($c) => $c->where('name', 'like', "%{$s}%")));
        }
        match ($request->query('state')) {
            'outstanding' => $q->whereColumn('paid', '<', 'amount'),
            'overdue' => $q->whereColumn('paid', '<', 'amount')->whereDate('due_date', '<', today()),
            'paid' => $q->whereColumn('paid', '>=', 'amount'),
            default => null,
        };
        if ($v = $request->query('from')) $q->whereDate('due_date', '>=', $v);
        if ($v = $request->query('to')) $q->whereDate('due_date', '<=', $v);

        // totals over the whole filtered set, not just the current page
        $totals = (clone $q)->toBase()->selectRaw('coalesce(sum(amount), 0) as amount, coalesce(sum(paid), 0) as paid')->first();
        return view('commercial.invoices', [
            'invoices' => $q->orderBy('due_date')->paginate(config('csa.per_page'))->withQueryString(),
            'totalInvoiced' => (float) $totals->amount,
            'totalPaid' => (float) $totals->paid,
            'states' => self::INVOICE_STATES,
        ]);
    }

    public function renewals(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100', 'from' => 'nullable|date', 'to' => 'nullable|date']);
        $visibleRenewals = Renewal::visibleToProjectOwner(auth()->user());
        $q = (clone $visibleRenewals)->with(['customer', 'opportunity']);
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('type', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"))
                ->orWhereHas('opportunity', fn ($o) => $o->where('code', 'like', "%{$s}%")));
        }
        $types = (clone $visibleRenewals)->distinct()->orderBy('type')->pluck('type')->all();
        $statuses = (clone $visibleRenewals)->distinct()->orderBy('status')->pluck('status')->all();
        if (in_array($v = $request->query('type'), $types, true)) $q->where('type', $v);
        if (in_array($v = $request->query('status'), $statuses, true)) $q->where('status', $v);
        if ($v = (int) $request->query('owner_id')) $q->where('owner_id', $v);
        if ($v = $request->query('from')) $q->whereDate('expiry_date', '>=', $v);
        if ($v = $request->query('to')) $q->whereDate('expiry_date', '<=', $v);

        return view('commercial.renewals', [
            'renewals' => $q->orderBy('expiry_date')->paginate(config('csa.per_page'))->withQueryString(),
            'types' => $types,
            'statuses' => $statuses,
            'owners' => User::whereIn('id', (clone $visibleRenewals)->whereNotNull('owner_id')->select('owner_id'))
                ->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function renewalOpportunity(Renewal $renewal, WorkflowService $wf)
    {
        abort_unless(Renewal::visibleToProjectOwner(auth()->user())->whereKey($renewal->id)->exists(), 404);
        [$ok, $msg] = $wf->createRenewalOpportunity($renewal, auth()->user());
        return back()->with($ok ? 'ok' : 'err', $msg);
    }
}
