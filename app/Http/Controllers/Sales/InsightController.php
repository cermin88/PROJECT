<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

// Customer Attention (UX-006) and Sales Forecast (UX-014)
class InsightController extends Controller
{
    public function customerAttention(Request $request)
    {
        $q = Customer::active()->with('owner');
        if ($o = (int) $request->query('owner_id')) {
            $q->where('owner_id', $o);
        }
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%")->orWhere('sector', 'like', "%{$s}%"));
        }
        if (in_array($r = $request->query('relationship'), SalesRules::RELATIONSHIPS, true)) {
            $q->where('relationship', $r);
        }
        if (in_array($t = $request->query('type'), SalesRules::CUSTOMER_TYPES, true)) {
            $q->where('type', $t);
        }
        foreach (['from' => '>=', 'to' => '<='] as $k => $op) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($k))) {
                $q->whereDate('last_engagement_at', $op, $request->query($k));
            }
        }
        $rows = $q->get()->map(function ($c) {
            $days = $c->daysSinceEngagement();
            $c->days = $days;
            $c->due = $days === null || $days > SalesRules::FOLLOW_UP_DAYS;
            return $c;
        })->sortByDesc(fn ($c) => $c->days ?? PHP_INT_MAX)->values();
        if ($request->query('filter') === 'due') {
            $rows = $rows->where('due', true)->values();
        }
        $page = LengthAwarePaginator::resolveCurrentPage();
        return view('sales.customer-attention', [
            // summary cards use the full filtered set; the table is paginated
            'total' => $rows->count(),
            'dueCount' => $rows->where('due', true)->count(),
            'rows' => new LengthAwarePaginator($rows->forPage($page, config('csa.per_page')), $rows->count(), config('csa.per_page'), $page, ['path' => $request->url(), 'query' => $request->query()]),
            'threshold' => SalesRules::FOLLOW_UP_DAYS,
            'owners' => User::whereIn('role', SalesRules::OWNER_ROLES)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function markEngaged(Request $request, Customer $customer)
    {
        abort_unless(SalesRules::canEdit(auth()->user(), $customer->owner_id), 403);
        $d = $request->validate(['note' => 'required|string|max:255']);
        $customer->update(['last_engagement_at' => now()]);
        AuditLog::record('CUSTOMER_ENGAGED', 'customer', $customer->id, ['note' => $d['note']]);
        return back()->with('ok', "Engagement recorded for {$customer->name}.");
    }

    public function forecast(Request $request)
    {
        abort_unless(SalesRules::canSeeCommercial(auth()->user()), 403);
        $q = Opportunity::active()->with(['customer', 'owner']);
        if ($o = (int) $request->query('owner_id')) {
            $q->where('owner_id', $o);
        }
        if (in_array($s = $request->query('stage'), Opportunity::STAGES, true)) {
            $q->where('stage', $s);
        }
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('code', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")));
        }
        $opps = $q->orderByDesc('value')->get()->each(fn ($o) => $o->expected = (float) $o->value * $o->probability / 100);
        $page = LengthAwarePaginator::resolveCurrentPage();
        return view('sales.forecast', [
            // totals and "By stage" stay over the full filtered set; only the detail rows are paginated
            'opps' => $opps,
            'rows' => new LengthAwarePaginator($opps->forPage($page, config('csa.per_page')), $opps->count(), config('csa.per_page'), $page, ['path' => $request->url(), 'query' => $request->query()]),
            'byStage' => $opps->groupBy('stage'),
            'stages' => Opportunity::STAGES,
            'owners' => User::whereIn('role', SalesRules::OWNER_ROLES)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
