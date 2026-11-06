<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Http\Request;

// Tender Calendar (UX-016) and War Room overview (UX-017)
class TenderBoardController extends Controller
{
    public function calendar(Request $request)
    {
        $q = Tender::with(['customer', 'owner'])->whereNull('archived_at')->whereNotNull('closing_at')->orderBy('closing_at');
        if ($request->query('view') !== 'all') {
            $q->open()->where('closing_at', '>=', now()->startOfDay()); // default: upcoming, still in play
        }
        if ($o = (int) $request->query('owner_id')) {
            $q->where('owner_id', $o);
        }
        if (in_array($s = $request->query('status'), Tender::STATES, true)) {
            $q->where('status', $s);
        }
        // Grouped calendar view: filter only, no pagination. Month is matched in MYT, the same zone the groups use.
        $tenders = $q->get();
        $myt = fn ($t) => $t->closing_at->timezone('Asia/Kuala_Lumpur');
        $monthOptions = $tenders->mapWithKeys(fn ($t) => [$myt($t)->format('Y-m') => $myt($t)->format('F Y')])->all();
        if (isset($monthOptions[$m = (string) $request->query('month')])) {
            $tenders = $tenders->filter(fn ($t) => $myt($t)->format('Y-m') === $m)->values();
        }
        return view('sales.tender-calendar', [
            'months' => $tenders->groupBy(fn ($t) => $myt($t)->format('F Y')),
            'count' => $tenders->count(),
            'monthOptions' => $monthOptions,
            'statuses' => Tender::STATES,
            'owners' => User::whereIn('id', Tender::whereNull('archived_at')->whereNotNull('owner_id')->select('owner_id'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function warroom(Request $request)
    {
        $tenders = Tender::open()->with(['customer', 'owner', 'checklist'])->orderBy('closing_at')->get()
            ->each(function ($t) {
                $t->blockers = self::blockers($t);
                $t->days_left = $t->closing_at ? (int) floor(now()->diffInDays($t->closing_at, false)) : null;
            });
        if ($request->query('filter') === 'blocked') {
            $tenders = $tenders->filter(fn ($t) => $t->blockers)->values();
        }
        return view('sales.tender-warroom', ['tenders' => $tenders]);
    }

    // Same blocker rules as the prototype War Room; only meaningful before submission
    public static function blockers(Tender $t): array
    {
        if (in_array($t->status, Tender::POST_SUBMISSION, true)) {
            return [];
        }
        $b = [];
        if ($n = $t->checklist->where('mandatory', true)->where('done', false)->count()) {
            $b[] = "{$n} mandatory checklist item(s) incomplete";
        }
        if (! $t->pricing_approved) {
            $b[] = 'Final pricing not approved';
        }
        if (in_array($t->status, ['DRAFT', 'BID_ASSESSMENT'], true)) {
            $b[] = 'Bid / No-Bid decision pending';
        }
        return $b;
    }
}
