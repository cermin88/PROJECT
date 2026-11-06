<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\Award;
use App\Models\Competitor;
use App\Models\Opportunity;
use App\Models\Partner;
use App\Models\SupportCase;
use App\Models\Tender;
use App\Services\Intelligence\ReportCatalogue;
use Carbon\Carbon;
use Illuminate\Http\Request;

// Analytics Hub (UX-055): funnel, stage aging, tender outcomes, revenue qualification, partner/competitor, CC
class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->query('period', 'year');
        abort_unless(isset(ReportCatalogue::PERIODS[$period]), 404);
        $r = ReportCatalogue::range($period);

        $awards = Award::visibleToProjectOwner(auth()->user())
            ->when($r, fn ($q) => $q->whereBetween('award_date', $r))->get();
        $active = Opportunity::active()->with('history')->get();
        $funnel = collect(Opportunity::STAGES)->map(function ($s) use ($active) {
            $xs = $active->where('stage', $s);
            $age = $xs->map(fn ($o) => (int) Carbon::parse($o->history->first()?->created_at ?? $o->created_at)->diffInDays(now()));
            return ['stage' => $s, 'count' => $xs->count(), 'value' => (float) $xs->sum('value'), 'aging' => $xs->count() ? (int) round($age->avg()) : 0];
        });

        // Win rate uses tenders decided in the period (business date = closing date)
        $decided = Tender::whereIn('status', Tender::DECIDED)->when($r, fn ($q) => $q->whereBetween('closing_at', $r))->get();
        $won = $decided->where('status', 'AWARDED')->count();
        $cases = SupportCase::when($r, fn ($q) => $q->whereBetween('opened_at', $r))->get();
        $csat = $cases->whereNotNull('csat');

        return view('intelligence.analytics', [
            'period' => $period,
            'revenue' => (float) $awards->where('revenue_qualified', true)->sum('value'),
            'excluded' => (float) $awards->where('revenue_qualified', false)->sum('value'),
            'pipeline' => (float) $active->sum('value'),
            'pipelineCount' => $active->count(),
            'winRate' => $decided->count() ? round($won / $decided->count() * 100, 1) : null,
            'won' => $won,
            'lost' => $decided->count() - $won,
            'funnel' => $funnel,
            'closed' => Opportunity::whereIn('stage', Opportunity::CLOSED)->selectRaw('stage, count(*) as n, sum(value) as v')->groupBy('stage')->get(),
            'tenderStatus' => Tender::when($r, fn ($q) => $q->whereBetween('closing_at', $r))->selectRaw('status, count(*) as n')->groupBy('status')->orderByDesc('n')->get(),
            'competitors' => Competitor::orderByDesc('encounters')->limit(6)->get(),
            'partners' => Partner::withCount('products')->orderByDesc('products_count')->limit(6)->get(),
            'csat' => $csat->count() ? round($csat->avg('csat'), 1) : null,
            'csatCount' => $csat->count(),
            'breached' => $cases->filter->isBreached()->count(),
            'casesByPriority' => $cases->groupBy('priority')->map->count(),
        ]);
    }
}
