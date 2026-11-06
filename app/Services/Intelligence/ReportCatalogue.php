<?php

namespace App\Services\Intelligence;

use App\Models\Approval;
use App\Models\Award;
use App\Models\Competitor;
use App\Models\Customer;
use App\Models\Kpi;
use App\Models\Opportunity;
use App\Models\OpportunityStageHistory;
use App\Models\Partner;
use App\Models\Renewal;
use App\Models\SupportCase;
use App\Models\Tender;
use App\Models\User;
use App\Services\WorkflowService;
use Carbon\Carbon;

// Standard report catalogue (UX-056). Each report returns [headers, rows] from live data.
class ReportCatalogue
{
    public const REPORTS = [
        1 => 'Executive Performance Summary',
        2 => 'Pipeline Detail & Aging',
        3 => 'Opportunity Movement',
        4 => 'Tender Performance',
        5 => 'Awarded Revenue Detail',
        6 => 'Award → Invoice → Collection',
        7 => 'Customer Engagement',
        8 => 'Partner/OEM Performance',
        9 => 'Competitor Intelligence',
        10 => 'KPI Performance',
        11 => 'CC Operations & SLA',
        12 => 'Approval Aging',
        13 => 'Renewal & Expiry',
    ];

    public const PERIODS = ['quarter' => 'Current Quarter', 'year' => 'Current Year', 'all' => 'All Time'];

    public static function code(int $id): string
    {
        return sprintf('RPT-%03d', $id);
    }

    /** [from, to] for a period key, or null for all time. Business dates drive attribution. */
    public static function range(string $period): ?array
    {
        return match ($period) {
            'quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            default => null,
        };
    }

    private static function within($q, string $col, ?array $r)
    {
        return $r ? $q->whereBetween($col, $r) : $q;
    }

    private static function rm($v): string
    {
        return 'RM '.number_format((float) $v, 2);
    }

    private static function aging(Opportunity $o): int
    {
        $at = $o->history->first()?->created_at ?? $o->created_at;
        return (int) Carbon::parse($at)->diffInDays(now());
    }

    public function build(int $id, string $period, User $viewer): array
    {
        $r = self::range($period);

        return match ($id) {
            1 => $this->executive($r, $viewer),
            2 => [['ID', 'Opportunity', 'Customer', 'Stage', 'Value', 'Stage Aging (days)'],
                Opportunity::active()->with(['customer', 'history'])->orderByDesc('value')->get()
                    ->map(fn ($o) => [$o->code, $o->name, $o->customer->name, $o->stage, self::rm($o->value), self::aging($o)])->all()],
            3 => [['Opportunity', 'Stage', 'Changed By', 'Reason', 'Date'],
                self::within(OpportunityStageHistory::query(), 'created_at', $r)->latest('id')->limit(500)->get()
                    ->map(fn ($h) => [$this->lookup('opp', $h->opportunity_id), $h->stage, $this->lookup('user', $h->changed_by) ?? '—', $h->reason ?? '', Carbon::parse($h->created_at)->format('d/m/Y')])->all()],
            4 => [['Tender', 'Title', 'Customer', 'Status', 'Closing', 'Price'],
                self::within(Tender::with('customer'), 'closing_at', $r)->orderBy('closing_at')->get()
                    ->map(fn ($t) => [$t->code, $t->title, $t->customer->name, $t->status, $t->closing_at?->format('d/m/Y'), self::rm($t->price)])->all()],
            5 => [['Award', 'Title', 'Customer', 'Award Value', 'Qualified Revenue', 'Business Date', 'Status'],
                self::within(Award::visibleToProjectOwner($viewer)->with('customer'), 'award_date', $r)->orderBy('award_date')->get()
                    ->map(fn ($a) => [$a->code, $a->title, $a->customer->name, self::rm($a->value), self::rm($a->revenueContribution()), $a->award_date->format('d/m/Y'), $a->revenue_qualified ? 'Qualified' : 'Excluded'])->all()],
            6 => [['Award', 'Qualified Revenue', 'Invoiced', 'Collected', 'Outstanding'],
                self::within(Award::visibleToProjectOwner($viewer)->with(['invoices' => fn ($q) => $q->visibleToProjectOwner($viewer)]), 'award_date', $r)->orderBy('award_date')->get()
                    ->map(fn ($a) => [$a->code, self::rm($a->revenueContribution()), self::rm($a->invoices->sum('amount')), self::rm($a->invoices->sum('paid')), self::rm($a->invoices->sum(fn ($i) => max(0, $i->outstanding())))])->all()],
            7 => [['Customer', 'Sector', 'Relationship', 'Owner', 'Last Engagement', 'Days Since'],
                Customer::with('owner')->where('archived', false)->orderBy('last_engagement_at')->get()
                    ->map(fn ($c) => [$c->name, $c->sector, $c->relationship, $c->owner?->name ?? '—', $c->last_engagement_at?->format('d/m/Y') ?? 'Never', $c->last_engagement_at ? (int) $c->last_engagement_at->diffInDays(now()) : ''])->all()],
            8 => [['Partner', 'Classification', 'Preferred', 'Rating', 'Products'],
                Partner::withCount('products')->orderBy('name')->get()
                    ->map(fn ($p) => [$p->name, $p->classification, $p->preferred ? 'Yes' : 'No', $p->rating, $p->products_count])->all()],
            9 => [['Competitor', 'Encounters', 'Won Against', 'Lost Against', 'Lost Opportunities'],
                Competitor::orderByDesc('encounters')->get()
                    ->map(fn ($c) => [$c->name, $c->encounters, $c->won_against, $c->lost_against, Opportunity::where('competitor_id', $c->id)->where('stage', 'Lost')->count()])->all()],
            10 => [['Employee', 'Period', 'KPI', 'Source', 'Target', 'Effective Actual', 'Achievement %', 'Weight', 'Rating', 'Locked'],
                Kpi::visibleTo($viewer)->with(['user', 'adjustments'])->when($period !== 'all', fn ($q) => $q->where('period', Kpi::currentPeriod()))->orderBy('user_id')->get()
                    ->map(fn ($k) => [$k->user->name, $k->period, $k->name, $k->source, $k->target, $k->effectiveActual(), $k->achievement(), $k->weight, $k->rating ?? '', $k->locked ? 'Yes' : 'No'])->all()],
            11 => [['Case', 'Customer', 'Priority', 'Status', 'SLA', 'CSAT'],
                self::within(SupportCase::with('customer'), 'opened_at', $r)->latest('opened_at')->get()
                    ->map(fn ($c) => [$c->code, $c->customer->name, $c->priority, $c->status, $c->paused_at ? 'Paused' : ($c->isBreached() ? 'Breached' : 'Compliant'), $c->csat ?? 'No response'])->all()],
            12 => [['Approval', 'Type', 'Requester', 'Approver', 'Status', 'Age (days)'],
                Approval::with(['requester', 'approver'])->latest('id')->get()
                    ->map(fn ($a) => [$a->code, $a->type, $a->requester?->name, $a->approver?->name, $a->status, (int) $a->created_at->diffInDays($a->decided_at ?? now())])->all()],
            13 => [['Customer', 'Type', 'Expiry', 'Status', 'Owner', 'Renewal Opportunity'],
                Renewal::visibleToProjectOwner($viewer)->with(['customer', 'owner', 'opportunity'])->orderBy('expiry_date')->get()
                    ->map(fn ($x) => [$x->customer->name, $x->type, $x->expiry_date->format('d/m/Y'), $x->status, $x->owner?->name ?? '—', $x->opportunity?->code ?? '—'])->all()],
            default => abort(404),
        };
    }

    private array $maps = [];

    // Small id => label maps so movement rows don't run one query per row
    private function lookup(string $kind, $id): ?string
    {
        $this->maps[$kind] ??= $kind === 'opp' ? Opportunity::pluck('code', 'id')->all() : User::pluck('name', 'id')->all();
        return $this->maps[$kind][$id] ?? null;
    }

    private function executive(?array $r, User $viewer): array
    {
        $m = app(WorkflowService::class)->metrics($viewer);
        $rev = (float) self::within(Award::visibleToProjectOwner($viewer)->qualified(), 'award_date', $r)->sum('value');
        $unqualifiedAwards = Award::visibleToProjectOwner($viewer)->where('revenue_qualified', false)->count();
        return [['Measure', 'Value', 'Rule'], [
            ['Revenue Target (CSA, not individual)', self::rm($m['revenue_target']), 'Reference figure only'],
            ['Awarded Revenue', self::rm($rev), 'Qualified Award value only (PO / LOA / official award)'],
            ['Achievement', $m['revenue_target'] > 0 ? round($rev / $m['revenue_target'] * 100, 1).'%' : '—', 'Awarded ÷ target'],
            ['Active Pipeline', self::rm($m['pipeline_value']), 'Excludes Lost / Award / Cancelled'],
            ['Active Opportunities', $m['pipeline_count'], 'Excludes Lost / Award / Cancelled'],
            ['Tender Win Rate', $m['win_rate'] === null ? 'N/A' : $m['win_rate'].'%', 'Awarded ÷ (Awarded + Lost)'],
            ['Open Cases', $m['open_cases'], 'Not Resolved / Closed'],
            ['Unqualified Awards', $unqualifiedAwards, 'Do not count towards revenue'],
        ]];
    }
}
