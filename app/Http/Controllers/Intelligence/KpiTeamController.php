<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Kpi;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

// Team KPI (UX-049) and KPI Review & Lock (UX-052). Both are scoped by Kpi::visibleTo().
class KpiTeamController extends Controller
{
    private function filtered(Request $request)
    {
        $period = $request->query('period', Kpi::currentPeriod());
        $status = in_array($request->query('status'), Kpi::STATUSES, true) ? $request->query('status') : null;
        $source = in_array($request->query('source'), Kpi::SOURCES, true) ? $request->query('source') : null;
        $q = Kpi::visibleTo(auth()->user())->with(['user', 'adjustments.user', 'reviewer'])
            ->when($period !== 'all', fn ($q) => $q->where('period', $period))
            ->when((int) $request->query('user_id'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->when($source, fn ($q, $v) => $q->where('source', $v))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('evidence', 'like', "%{$s}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%"))));
        if ($request->query('lock') === 'locked') {
            $q->where('locked', true);
        } elseif ($request->query('lock') === 'open') {
            $q->where('locked', false);
        }
        return [$period, $q->orderBy('user_id')->orderBy('id')];
    }

    private function filterData(): array
    {
        $visible = Kpi::visibleTo(auth()->user());
        return [
            'periods' => (clone $visible)->whereNotNull('period')->distinct()->orderByDesc('period')->pluck('period'),
            'people' => User::whereIn('id', (clone $visible)->select('user_id'))->orderBy('name')->get(),
            'isTeam' => in_array(auth()->user()->role, Kpi::TEAM_ROLES, true),
        ];
    }

    public function team(Request $request)
    {
        [$period, $q] = $this->filtered($request);
        $all = $q->get();
        // "By person" summary stays over the full filtered set; only the detail table is paginated
        $summary = $all->groupBy('user_id')->map(fn ($xs) => [
            'user' => $xs->first()->user,
            'count' => $xs->count(),
            'weight' => $xs->sum('weight'),
            'score' => Kpi::weightedScore($xs),
            'locked' => $xs->where('locked', true)->count(),
            'risk' => $xs->whereIn('status', ['At Risk', 'Behind'])->count(),
        ]);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $kpis = new LengthAwarePaginator($all->forPage($page, config('csa.per_page')), $all->count(), config('csa.per_page'), $page, ['path' => $request->url(), 'query' => $request->query()]);
        return view('intelligence.kpi-team', compact('period', 'kpis', 'summary') + $this->filterData());
    }

    public function review(Request $request)
    {
        [$period, $q] = $this->filtered($request);
        $kpis = $q->paginate(config('csa.per_page'))->withQueryString();
        return view('intelligence.kpi-review', compact('period', 'kpis') + $this->filterData());
    }

    // Review an open KPI (actual, rating, comment); optionally lock it in the same step
    public function saveReview(Request $request, Kpi $kpi, WorkflowService $wf)
    {
        $u = auth()->user();
        abort_unless($u->canLockKpi() && Kpi::visibleTo($u)->whereKey($kpi->id)->exists(), 403);
        $d = $request->validate([
            'actual' => 'required|numeric|min:0',
            'rating' => 'required|integer|min:1|max:10',
            'status' => 'required|in:'.implode(',', Kpi::STATUSES),
            'review_comment' => 'nullable|string|max:1000',
            'lock' => 'nullable|boolean',
        ]);
        if ($kpi->locked) {
            return back()->with('err', 'Locked KPI cannot be directly rewritten — use Adjustment.');
        }
        $kpi->update([
            'actual' => $d['actual'], 'rating' => $d['rating'], 'status' => $d['status'],
            'review_comment' => $d['review_comment'] ?? null, 'reviewed_by' => $u->id, 'reviewed_at' => now(),
        ]);
        AuditLog::record('KPI_REVIEW', 'kpi', $kpi->id, ['actual' => $d['actual'], 'rating' => $d['rating']]);
        if (! empty($d['lock'])) {
            [, $msg] = $wf->lockKpi($kpi, $u);
            return back()->with('ok', 'Review saved. '.$msg);
        }
        return back()->with('ok', 'Review saved.');
    }
}
