<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Award;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Kpi;
use App\Models\Opportunity;
use App\Models\SupportCase;
use App\Models\Tender;
use App\Services\Intelligence\ReportCatalogue;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Standard Reports (UX-056) and Management Pack (UX-058)
class ReportController extends Controller
{
    // Export rights (prototype canExport)
    public const EXPORT = ['Management', 'HOD', 'AHOD', 'Superadmin'];

    public static function canExport(): bool
    {
        return in_array(auth()->user()->role, self::EXPORT, true);
    }

    private function period(Request $request): string
    {
        $p = $request->query('period', 'quarter');
        abort_unless(isset(ReportCatalogue::PERIODS[$p]), 404);
        return $p;
    }

    public static function csv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_values((array) $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function index(Request $request)
    {
        return view('intelligence.reports', ['reports' => ReportCatalogue::REPORTS, 'period' => $this->period($request), 'canExport' => self::canExport()]);
    }

    public function show(Request $request, int $report, ReportCatalogue $cat)
    {
        abort_unless(isset(ReportCatalogue::REPORTS[$report]), 404);
        $period = $this->period($request);
        [$headers, $rows] = $cat->build($report, $period, auth()->user());
        // Free-text filter over every cell of the generated rows, then paginate the filtered set
        $rows = collect($rows);
        if (($s = trim((string) $request->query('q'))) !== '') {
            $rows = $rows->filter(fn ($r) => collect((array) $r)->contains(fn ($c) => is_scalar($c) && mb_stripos((string) $c, $s) !== false))->values();
        }
        $page = LengthAwarePaginator::resolveCurrentPage();
        return view('intelligence.report-show', [
            'id' => $report, 'title' => ReportCatalogue::REPORTS[$report], 'period' => $period,
            'headers' => $headers, 'canExport' => self::canExport(),
            'rows' => new LengthAwarePaginator($rows->forPage($page, config('csa.per_page')), $rows->count(), config('csa.per_page'), $page, ['path' => $request->url(), 'query' => $request->query()]),
        ]);
    }

    public function export(Request $request, int $report, ReportCatalogue $cat)
    {
        abort_unless(isset(ReportCatalogue::REPORTS[$report]), 404);
        abort_unless(self::canExport(), 403);
        $period = $this->period($request);
        [$headers, $rows] = $cat->build($report, $period, auth()->user());
        $code = ReportCatalogue::code($report);
        AuditLog::record('EXPORT', 'report', $report, ['report' => $code.' '.ReportCatalogue::REPORTS[$report], 'period' => $period, 'rows' => count($rows)]);
        return self::csv("{$code}-{$period}.csv", $headers, $rows);
    }

    /** Figures for the management pack, all computed from the live tables. */
    private function pack(string $period): array
    {
        $r = ReportCatalogue::range($period);
        $viewer = auth()->user();
        $m = app(WorkflowService::class)->metrics($viewer);
        $unqualifiedAwards = Award::visibleToProjectOwner($viewer)->where('revenue_qualified', false)->count();
        $within = fn ($q, $col) => $r ? $q->whereBetween($col, $r) : $q;
        $rev = (float) $within(Award::visibleToProjectOwner($viewer)->qualified(), 'award_date')->sum('value');
        $tenders = $within(Tender::query(), 'closing_at')->get();
        $won = $tenders->where('status', 'AWARDED')->count();
        $lost = $tenders->where('status', 'LOST')->count();
        $inv = $within(Invoice::visibleToProjectOwner($viewer), 'invoice_date')->get();
        $cases = $within(SupportCase::query(), 'opened_at')->get();
        $stale = Opportunity::active()->where('updated_at', '<', now()->subDays(30))->count();
        $kpis = Kpi::with(['user', 'adjustments'])->where('period', Kpi::currentPeriod())->orderBy('user_id')->get();

        return [
            'Executive Summary' => [
                'Revenue Target (CSA)' => 'RM '.number_format($m['revenue_target'], 2),
                'Awarded Revenue (qualified only)' => 'RM '.number_format($rev, 2),
                'Achievement' => $m['revenue_target'] > 0 ? round($rev / $m['revenue_target'] * 100, 1).'%' : '—',
                'Active Pipeline (excl. Lost/Award/Cancelled)' => 'RM '.number_format($m['pipeline_value'], 2),
            ],
            'Tender Performance' => [
                'Submitted' => $tenders->whereNotNull('submitted_at')->count(),
                'Awarded' => $won,
                'Lost' => $lost,
                'Win Rate — Awarded ÷ (Awarded + Lost)' => $won + $lost ? round($won / ($won + $lost) * 100, 1).'%' : 'N/A',
            ],
            'Commercial Funnel' => [
                'Awarded (qualified)' => 'RM '.number_format($rev, 2),
                'Invoiced' => 'RM '.number_format($inv->sum('amount'), 2),
                'Collected' => 'RM '.number_format($inv->sum('paid'), 2),
                'Outstanding' => 'RM '.number_format($inv->sum(fn ($i) => max(0, $i->outstanding())), 2),
            ],
            'Customer & Engagement' => [
                'Active Customers' => Customer::where('archived', false)->count(),
                'Not engaged in 60 days' => Customer::where('archived', false)->where(fn ($q) => $q->whereNull('last_engagement_at')->orWhere('last_engagement_at', '<', now()->subDays(60)))->count(),
                'Stale Opportunities (no update 30 days)' => $stale,
            ],
            'KPI ('.Kpi::currentPeriod().')' => $kpis->take(8)->mapWithKeys(fn ($k) => [$k->user->name.' · '.$k->name => $k->achievement().'%'.($k->rating ? " · {$k->rating}/10" : '').($k->locked ? ' · Locked' : '')])->all(),
            'Contact Centre' => [
                'Open' => $cases->whereNotIn('status', ['Resolved', 'Closed'])->count(),
                'Critical open' => $cases->where('priority', 'Critical')->whereNotIn('status', ['Resolved', 'Closed'])->count(),
                'SLA breached' => $cases->filter->isBreached()->count(),
                'CSAT responses' => $cases->whereNotNull('csat')->count(),
            ],
            'Risks / Attention' => [
                'Unqualified Awards (RM0 revenue until evidence)' => $unqualifiedAwards,
                'Pending approvals' => $m['pending_approvals'],
                'Open tenders' => $m['tenders_open'],
            ],
        ];
    }

    public function packView(Request $request)
    {
        $period = $this->period($request);
        return view('intelligence.pack', ['period' => $period, 'sections' => $this->pack($period), 'canExport' => self::canExport()]);
    }

    public function packExport(Request $request)
    {
        abort_unless(self::canExport(), 403);
        $period = $this->period($request);
        $rows = [];
        foreach ($this->pack($period) as $section => $items) {
            foreach ($items as $k => $v) {
                $rows[] = [$section, $k, $v];
            }
        }
        AuditLog::record('EXPORT', 'management_pack', null, ['period' => $period, 'rows' => count($rows)]);
        return self::csv("management-pack-{$period}.csv", ['Section', 'Measure', 'Value'], $rows);
    }
}
