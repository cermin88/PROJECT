<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\AttentionDisposition;
use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\Renewal;
use App\Models\SupportCase;
use App\Models\Tender;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AttentionController extends Controller
{
    public const SEVERITIES = ['Critical', 'High', 'Medium', 'Low'];
    // Item source = first segment of the item key
    public const SOURCES = ['tender' => 'Tenders', 'opp' => 'Opportunities', 'case' => 'CC Cases', 'renewal' => 'Renewals', 'approval' => 'Approvals'];

    public function index(Request $request)
    {
        $done = AttentionDisposition::with('user')->latest('id')->get()->unique('item_key')->keyBy('item_key');
        $all = $this->items()->map(fn ($i) => $i + ['disposition' => $done[$i['key']] ?? null]);
        $items = $all;

        if (in_array($s = $request->query('severity'), self::SEVERITIES, true)) {
            $items = $items->where('severity', $s);
        }
        if (isset(self::SOURCES[$t = (string) $request->query('type')])) {
            $items = $items->filter(fn ($i) => str_starts_with($i['key'], "{$t}:"));
        }
        if (($q = trim((string) $request->query('q'))) !== '') {
            $items = $items->filter(fn ($i) => mb_stripos($i['title'], $q) !== false || mb_stripos($i['detail'], $q) !== false);
        }
        if ($request->query('show') !== 'all') {
            $items = $items->whereNull('disposition'); // default: only items still needing a decision
        }
        $order = array_flip(self::SEVERITIES);
        $items = $items->sortBy(fn ($i) => $order[$i['severity']])->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        return view('sales.attention', [
            'items' => new LengthAwarePaginator($items->forPage($page, config('csa.per_page')), $items->count(), config('csa.per_page'), $page, ['path' => $request->url(), 'query' => $request->query()]),
            'counts' => $all->whereNull('disposition')->countBy('severity'),
            'severities' => self::SEVERITIES,
            'sources' => self::SOURCES,
            'canAccept' => SalesRules::canDecide(auth()->user()),
        ]);
    }

    public function dispose(Request $request)
    {
        $d = $request->validate([
            'key' => 'required|string|max:80',
            'disposition' => 'required|in:Acknowledged,Risk Accepted',
            'note' => 'required_if:disposition,Risk Accepted|nullable|string|max:255',
        ]);
        // Accepting a risk is an approval-type decision
        abort_if($d['disposition'] === 'Risk Accepted' && ! SalesRules::canDecide(auth()->user()), 403);

        $item = $this->items()->firstWhere('key', $d['key']);
        if (! $item) {
            return back()->with('err', 'Attention item is no longer active.');
        }
        $row = AttentionDisposition::create([
            'item_key' => $d['key'], 'title' => $item['title'], 'disposition' => $d['disposition'],
            'note' => $d['note'] ?? null, 'user_id' => auth()->id(),
        ]);
        AuditLog::record(strtoupper(str_replace(' ', '_', $d['disposition'])), 'attention', $row->id, ['item' => $d['key'], 'note' => $d['note'] ?? null]);
        return back()->with('ok', "{$d['disposition']} recorded for “{$item['title']}”.");
    }

    // Same sources as the prototype's attentionItems(), computed live from the database
    private function items()
    {
        $items = collect();
        foreach (Tender::open()->whereNotNull('closing_at')->where('closing_at', '<', now()->addDays(10))->orderBy('closing_at')->get() as $t) {
            $items->push(['key' => "tender:{$t->id}:closing", 'severity' => $t->risk === 'High' ? 'High' : 'Medium',
                'title' => "Tender closing: {$t->code}", 'detail' => $t->title . ' · ' . $t->closing_at->timezone('Asia/Kuala_Lumpur')->format('d M Y H:i'),
                'url' => route('tenders.show', $t)]);
        }
        foreach (Opportunity::active()->with('history')->get() as $o) {
            if ($o->lastStageAt()->lt(now()->subDays(SalesRules::STALE_DAYS))) {
                $items->push(['key' => "opp:{$o->id}:stale", 'severity' => 'Medium', 'title' => "Stale opportunity: {$o->code}",
                    'detail' => $o->name, 'url' => route('opportunities.show', $o)]);
            }
            if ($o->next_action_date && $o->next_action_date->lt(today())) {
                $items->push(['key' => "opp:{$o->id}:overdue:{$o->next_action_date->toDateString()}", 'severity' => 'High',
                    'title' => "Overdue follow-up: {$o->code}", 'detail' => "{$o->next_action} · due {$o->next_action_date->format('d M Y')}",
                    'url' => route('opportunities.show', $o)]);
            }
        }
        foreach (SupportCase::where('priority', 'Critical')->where('status', '!=', 'Closed')->get() as $c) {
            $items->push(['key' => "case:{$c->id}:critical", 'severity' => 'Critical', 'title' => "Critical CC Case: {$c->code}",
                'detail' => $c->title, 'url' => route('cases.show', $c)]);
        }
        foreach (Renewal::where('status', 'DUE_SOON')->with('customer')->get() as $r) {
            $items->push(['key' => "renewal:{$r->id}:due", 'severity' => 'Medium', 'title' => 'Renewal due',
                'detail' => "{$r->type} · {$r->customer?->name} · {$r->expiry_date->format('d M Y')}", 'url' => route('renewals.index')]);
        }
        foreach (Approval::where('status', 'Pending')->oldest('id')->take(3)->get() as $a) {
            $items->push(['key' => "approval:{$a->id}:pending", 'severity' => 'High', 'title' => "Approval pending: {$a->code}",
                'detail' => $a->type, 'url' => route('approvals.index')]);
        }
        return $items;
    }
}
