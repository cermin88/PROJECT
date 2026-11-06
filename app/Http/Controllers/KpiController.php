<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Kpi;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class KpiController extends Controller
{
    // My KPI (UX-048): own KPIs only. Team views live in Intelligence\KpiTeamController.
    public function index(Request $request)
    {
        $period = $request->query('period', Kpi::currentPeriod());
        $status = in_array($request->query('status'), Kpi::STATUSES, true) ? $request->query('status') : null;
        $source = in_array($request->query('source'), Kpi::SOURCES, true) ? $request->query('source') : null;
        $all = Kpi::with(['adjustments.user', 'reviewer'])->where('user_id', auth()->id())
            ->when($period !== 'all', fn ($q) => $q->where('period', $period))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('evidence', 'like', "%{$s}%")->orWhere('formula', 'like', "%{$s}%")))
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->when($source, fn ($q, $v) => $q->where('source', $v))
            ->orderBy('id')->get();
        $page = LengthAwarePaginator::resolveCurrentPage();
        return view('kpi.index', [
            // count + weighted score stay over the full filtered set, the table is paginated
            'kpis' => new LengthAwarePaginator($all->forPage($page, config('csa.per_page')), $all->count(), config('csa.per_page'), $page, ['path' => $request->url(), 'query' => $request->query()]),
            'total' => $all->count(),
            'period' => $period,
            'periods' => Kpi::where('user_id', auth()->id())->whereNotNull('period')->distinct()->orderByDesc('period')->pluck('period'),
            'score' => Kpi::weightedScore($all),
        ]);
    }

    // Owner adds / updates evidence on an open KPI; locked periods are read-only
    public function evidence(Request $request, Kpi $kpi)
    {
        abort_unless($kpi->user_id === auth()->id(), 403);
        $d = $request->validate(['evidence' => 'required|string|max:255']);
        if ($kpi->locked) {
            return back()->with('err', 'Locked period — evidence cannot be changed. Ask your reviewer for an adjustment.');
        }
        $kpi->update(['evidence' => $d['evidence']]);
        AuditLog::record('KPI_EVIDENCE', 'kpi', $kpi->id, ['evidence' => $d['evidence']]);
        return back()->with('ok', 'Evidence saved for review.');
    }

    public function lock(Kpi $kpi, WorkflowService $wf)
    {
        [$ok, $msg] = $wf->lockKpi($kpi, auth()->user());
        return back()->with($ok ? 'ok' : 'err', $msg);
    }

    public function adjust(Request $request, Kpi $kpi, WorkflowService $wf)
    {
        abort_unless(auth()->user()->canLockKpi(), 403);
        $d = $request->validate(['actual' => 'required|numeric|min:0', 'reason' => 'required|string|max:255']);
        [$ok, $msg] = $wf->adjustKpi($kpi, (float) $d['actual'], $d['reason'], auth()->user());
        return back()->with($ok ? 'ok' : 'err', $msg);
    }
}
