<?php

namespace App\Http\Controllers\Governance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Opportunity;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

// UX-063 Archive View: archived records, reason and controlled restore
class ArchiveController extends Controller
{
    // type => model, display column, lookup column used by the "archive a record" form
    public const TYPES = [
        'customer' => ['model' => Customer::class, 'label' => 'Customer', 'name' => 'name', 'key' => 'code'],
        'contact' => ['model' => Contact::class, 'label' => 'Contact', 'name' => 'name', 'key' => 'email'],
        'opportunity' => ['model' => Opportunity::class, 'label' => 'Opportunity', 'name' => 'name', 'key' => 'code'],
        'tender' => ['model' => Tender::class, 'label' => 'Tender', 'name' => 'title', 'key' => 'code'],
        'document' => ['model' => Document::class, 'label' => 'Document', 'name' => 'title', 'key' => 'code'],
    ];
    private const MANAGERS = ['HOD', 'Superadmin']; // prototype: restore requires HOD/Superadmin

    // Customers may also be archived by the Customers module via the legacy `archived` flag only
    public static function archived(string $type)
    {
        $q = self::TYPES[$type]['model']::query();
        return $type === 'customer'
            ? $q->where(fn ($w) => $w->whereNotNull('archived_at')->orWhere('archived', true))
            : $q->whereNotNull('archived_at');
    }

    public function index(Request $request)
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);
        $only = $request->query('type');
        $rows = collect();
        foreach (self::TYPES as $type => $t) {
            if ($only && $only !== $type) {
                continue;
            }
            foreach (self::archived($type)->get() as $r) {
                // Restricted documents stay hidden from roles that cannot see them
                if ($type === 'document' && $r->restricted && ! auth()->user()->canSeeRestricted()) {
                    continue;
                }
                $rows->push(['type' => $type, 'label' => $t['label'], 'id' => $r->id, 'code' => $r->code ?? ($r->email ?: '#'.$r->id),
                    'name' => $r->{$t['name']}, 'at' => Carbon::parse($r->archived_at ?? $r->updated_at), 'by' => $r->archived_by, 'reason' => $r->archive_reason]);
            }
        }
        $users = User::whereIn('id', $rows->pluck('by')->filter()->unique())->pluck('name', 'id');

        // Filters run over the merged collection, then the result is paginated
        if ($s = mb_strtolower(trim((string) $request->query('q')))) {
            $rows = $rows->filter(fn ($r) => collect([$r['code'], $r['name'], $r['reason']])->contains(fn ($v) => str_contains(mb_strtolower((string) $v), $s)));
        }
        if (($by = (int) $request->query('by')) && $users->has($by)) {
            $rows = $rows->where('by', $by);
        }
        if ($d = $request->date('from')) {
            $rows = $rows->filter(fn ($r) => $r['at']->gte($d->startOfDay()));
        }
        if ($d = $request->date('to')) {
            $rows = $rows->filter(fn ($r) => $r['at']->lte($d->endOfDay()));
        }
        $rows = $rows->sortByDesc('at')->values();
        $page = LengthAwarePaginator::resolveCurrentPage();

        return view('governance.archive', [
            'rows' => new LengthAwarePaginator($rows->forPage($page, config('csa.per_page')), $rows->count(), config('csa.per_page'), $page, ['path' => $request->url(), 'query' => $request->query()]),
            'users' => $users, 'types' => self::TYPES,
            'canManage' => in_array(auth()->user()->role, self::MANAGERS, true),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(in_array(auth()->user()->role, self::MANAGERS, true), 403);
        $d = $request->validate(['type' => 'required|in:'.implode(',', array_keys(self::TYPES)), 'key' => 'required|string|max:120', 'reason' => 'required|string|max:255']);
        $t = self::TYPES[$d['type']];
        $rec = $t['model']::where($t['key'], trim($d['key']))->first();
        if (! $rec) {
            return back()->with('err', "{$t['label']} '{$d['key']}' not found (look up by {$t['key']}).");
        }
        if ($rec->archived_at || ($d['type'] === 'customer' && $rec->archived)) {
            return back()->with('err', "{$t['label']} is already archived.");
        }
        $rec->forceFill(['archived_at' => now(), 'archived_by' => auth()->id(), 'archive_reason' => $d['reason']]
            + ($d['type'] === 'customer' ? ['archived' => true] : []))->save();
        AuditLog::record('ARCHIVE', $d['type'], $rec->id, ['reason' => $d['reason']]);
        return back()->with('ok', "{$t['label']} archived.");
    }

    public function restore(Request $request)
    {
        abort_unless(in_array(auth()->user()->role, self::MANAGERS, true), 403);
        $d = $request->validate(['type' => 'required|in:'.implode(',', array_keys(self::TYPES)), 'id' => 'required|integer']);
        $t = self::TYPES[$d['type']];
        $rec = self::archived($d['type'])->findOrFail($d['id']);
        $reason = $rec->archive_reason;
        $rec->forceFill(['archived_at' => null, 'archived_by' => null, 'archive_reason' => null]
            + ($d['type'] === 'customer' ? ['archived' => false] : []))->save();
        AuditLog::record('RESTORE', $d['type'], $rec->id, ['was' => $reason]);
        $note = str_starts_with((string) $reason, 'Merged into') ? ' Child records stay with the surviving record.' : '';
        return back()->with('ok', "{$t['label']} restored.".$note);
    }
}
