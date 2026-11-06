<?php

namespace App\Services\Intelligence;

use App\Models\Award;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\SupportCase;
use App\Models\Tender;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

// Custom Report Builder (UX-057): module -> fields -> filters -> grouping -> sort -> date range
class CustomReportQuery
{
    public const MAX_ROWS = 5000;

    /** module => [label, model, business date column, status column, owner column, fields] */
    public static function modules(): array
    {
        $d = fn ($v) => $v ? Carbon::parse($v)->format('d/m/Y') : '';
        return [
            'opportunity' => ['Opportunity', Opportunity::class, 'created_at', 'stage', 'owner_id', [
                'code' => ['Business ID', fn ($o) => $o->code],
                'name' => ['Name', fn ($o) => $o->name],
                'customer' => ['Customer', fn ($o) => $o->customer?->name],
                'stage' => ['Stage', fn ($o) => $o->stage],
                'owner' => ['Owner', fn ($o) => $o->owner?->name],
                'value' => ['Value (RM)', fn ($o) => (float) $o->value],
                'probability' => ['Probability %', fn ($o) => $o->probability],
                'next_action_date' => ['Next Action Date', fn ($o) => $d($o->next_action_date)],
            ], Opportunity::STAGES + [99 => 'Award', 100 => 'Lost', 101 => 'Cancelled']],
            'tender' => ['Tender', Tender::class, 'closing_at', 'status', 'owner_id', [
                'code' => ['Business ID', fn ($t) => $t->code],
                'title' => ['Title', fn ($t) => $t->title],
                'customer' => ['Customer', fn ($t) => $t->customer?->name],
                'status' => ['Status', fn ($t) => $t->status],
                'owner' => ['Owner', fn ($t) => $t->owner?->name],
                'risk' => ['Risk', fn ($t) => $t->risk],
                'price' => ['Price (RM)', fn ($t) => (float) $t->price],
                'closing_at' => ['Closing Date', fn ($t) => $d($t->closing_at)],
            ], Tender::STATES],
            'customer' => ['Customer', Customer::class, 'last_engagement_at', 'relationship', 'owner_id', [
                'code' => ['Business ID', fn ($c) => $c->code],
                'name' => ['Name', fn ($c) => $c->name],
                'type' => ['Type', fn ($c) => $c->type],
                'sector' => ['Sector', fn ($c) => $c->sector],
                'state' => ['State', fn ($c) => $c->state],
                'relationship' => ['Relationship', fn ($c) => $c->relationship],
                'owner' => ['Owner', fn ($c) => $c->owner?->name],
                'last_engagement_at' => ['Last Engagement', fn ($c) => $d($c->last_engagement_at)],
            ], ['Hot', 'Warm', 'Cold']],
            'award' => ['Award', Award::class, 'award_date', 'status', null, [
                'code' => ['Business ID', fn ($a) => $a->code],
                'title' => ['Title', fn ($a) => $a->title],
                'customer' => ['Customer', fn ($a) => $a->customer?->name],
                'value' => ['Award Value (RM)', fn ($a) => (float) $a->value],
                'qualified_revenue' => ['Qualified Revenue (RM)', fn ($a) => $a->revenueContribution()],
                'status' => ['Status', fn ($a) => $a->status],
                'award_date' => ['Award Date', fn ($a) => $d($a->award_date)],
            ], ['PENDING_EVIDENCE', 'QUALIFIED']],
            'case' => ['Contact Centre', SupportCase::class, 'opened_at', 'status', 'assignee_id', [
                'code' => ['Business ID', fn ($c) => $c->code],
                'title' => ['Title', fn ($c) => $c->title],
                'customer' => ['Customer', fn ($c) => $c->customer?->name],
                'channel' => ['Channel', fn ($c) => $c->channel],
                'priority' => ['Priority', fn ($c) => $c->priority],
                'status' => ['Status', fn ($c) => $c->status],
                'owner' => ['Assignee', fn ($c) => $c->assignee?->name],
                'opened_at' => ['Opened', fn ($c) => $d($c->opened_at)],
                'csat' => ['CSAT', fn ($c) => $c->csat],
            ], SupportCase::STATUSES],
        ];
    }

    public static function rules(): array
    {
        return [
            'module' => 'required|in:'.implode(',', array_keys(self::modules())),
            'fields' => 'required|array|min:1',
            'fields.*' => 'string',
            'status' => 'nullable|string|max:60',
            'owner_id' => 'nullable|integer|exists:users,id',
            'group' => 'nullable|string',
            'sort' => 'nullable|string',
            'dir' => 'nullable|in:asc,desc',
            'period' => 'nullable|in:quarter,year,all,custom',
            'from' => 'nullable|required_if:period,custom|date',
            'to' => 'nullable|required_if:period,custom|date|after_or_equal:from',
        ];
    }

    /** Keep only fields that belong to the chosen module. */
    public static function normalise(array $def): array
    {
        $fields = self::modules()[$def['module']][5];
        $def['fields'] = array_values(array_intersect(array_keys($fields), $def['fields']));
        foreach (['group', 'sort'] as $k) {
            if (! empty($def[$k]) && ! isset($fields[$def[$k]])) {
                $def[$k] = null;
            }
        }
        $def['dir'] ??= 'asc';
        $def['period'] ??= 'all';
        return $def;
    }

    /** @return array{headers: array, rows: Collection, groups: Collection|null} */
    public function run(array $def, User $viewer, ?int $limit = null): array
    {
        [, $model, $dateCol, $statusCol, $ownerCol, $fields] = self::modules()[$def['module']];
        $q = $model::query()->with(array_values(array_filter(['customer', $ownerCol ? ($def['module'] === 'case' ? 'assignee' : 'owner') : null],
            fn ($r) => $r && method_exists($model, $r))));
        if ($def['module'] === 'award') {
            $q->visibleToProjectOwner($viewer);
        }

        if (! empty($def['status'])) {
            $q->where($statusCol, $def['status']);
        }
        if (! empty($def['owner_id']) && $ownerCol) {
            $q->where($ownerCol, $def['owner_id']);
        }
        $range = $def['period'] === 'custom'
            ? [Carbon::parse($def['from'])->startOfDay(), Carbon::parse($def['to'])->endOfDay()]
            : ReportCatalogue::range($def['period']);
        if ($range) {
            $q->whereBetween($dateCol, $range);
        }

        $cols = $def['fields'];
        $rows = $q->limit(self::MAX_ROWS)->get()->map(function ($m) use ($fields, $cols, $def) {
            $row = [];
            foreach (array_unique(array_filter([...$cols, $def['group'] ?? null, $def['sort'] ?? null])) as $f) {
                $row[$f] = $fields[$f][1]($m);
            }
            return $row;
        });

        if (! empty($def['sort'])) {
            $rows = $rows->sortBy($def['sort'], SORT_NATURAL | SORT_FLAG_CASE, $def['dir'] === 'desc')->values();
        }

        $groups = null;
        if (! empty($def['group'])) {
            $numeric = collect($cols)->filter(fn ($f) => $rows->every(fn ($r) => is_numeric($r[$f] ?? null) || ($r[$f] ?? null) === null) && $rows->isNotEmpty()
                && ! in_array($f, ['code', 'probability', 'csat'], true));
            $groups = $rows->groupBy(fn ($r) => (string) ($r[$def['group']] ?? '—'))->map(fn ($g) => [
                'count' => $g->count(),
                'totals' => $numeric->mapWithKeys(fn ($f) => [$fields[$f][0] => $g->sum($f)])->all(),
            ]);
        }

        return [
            'headers' => array_map(fn ($f) => $fields[$f][0], $cols),
            'rows' => ($limit ? $rows->take($limit) : $rows)->map(fn ($r) => array_map(fn ($f) => $r[$f], $cols))->values(),
            'total' => $rows->count(),
            'groups' => $groups,
        ];
    }
}
