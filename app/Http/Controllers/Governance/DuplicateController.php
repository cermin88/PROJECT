<?php

namespace App\Http\Controllers\Governance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\GovernanceDuplicateDecision;
use App\Services\Governance\DuplicateDetector;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// UX-062 Duplicate Review & Merge
class DuplicateController extends Controller
{
    private const VIEWERS = ['HOD', 'AHOD', 'Management', 'Superadmin'];
    private const MERGERS = ['HOD', 'Superadmin']; // prototype: merge requires HOD/Superadmin

    public function index(Request $request, DuplicateDetector $dup)
    {
        abort_unless(in_array(auth()->user()->role, self::VIEWERS, true), 403);
        $type = $request->query('type') === 'contact' ? 'contact' : 'customer';
        $pairs = $type === 'contact' ? $dup->contacts() : $dup->customers();
        // Filter candidates by record code / name and by match reason, then paginate the pair list
        $reasons = $type === 'contact' ? ['Same email', 'Same mobile', 'Same name at same customer'] : ['Same name', 'Same acronym', 'Similar name'];
        if ($s = mb_strtolower(trim((string) $request->query('q')))) {
            $pairs = $pairs->filter(fn ($p) => collect([$p['a']->code ?? null, $p['a']->name, $p['b']->code ?? null, $p['b']->name])
                ->contains(fn ($v) => str_contains(mb_strtolower((string) $v), $s)));
        }
        if (in_array($r = $request->query('reason'), $reasons, true)) {
            $pairs = $pairs->filter(fn ($p) => str_starts_with((string) $p['reason'], $r));
        }
        $pairs = $pairs->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $pairs = new LengthAwarePaginator($pairs->forPage($page, config('csa.per_page')), $pairs->count(), config('csa.per_page'), $page, ['path' => $request->url(), 'query' => $request->query()]);

        // Side-by-side compare with child impact
        $compare = null;
        if ($request->filled(['a', 'b'])) {
            $model = $type === 'contact' ? Contact::class : Customer::class;
            $a = $model::findOrFail($request->query('a'));
            $b = $model::findOrFail($request->query('b'));
            $compare = ['a' => $a, 'b' => $b, 'impact' => [$a->id => $this->impact($type, $a->id), $b->id => $this->impact($type, $b->id)],
                'reason' => $type === 'contact' ? DuplicateDetector::contactReason($a, $b) : DuplicateDetector::customerReason($a, $b)];
        }

        return view('governance.duplicates', [
            'type' => $type, 'pairs' => $pairs, 'compare' => $compare, 'reasons' => $reasons,
            'canMerge' => in_array(auth()->user()->role, self::MERGERS, true),
            'recent' => GovernanceDuplicateDecision::with('user')->latest('id')->limit(10)->get(),
        ]);
    }

    public function separate(Request $request)
    {
        abort_unless(in_array(auth()->user()->role, self::VIEWERS, true), 403);
        $d = $this->validated($request);
        GovernanceDuplicateDecision::create(['entity_type' => $d['type'], 'keep_id' => $d['keep_id'], 'other_id' => $d['merge_id'],
            'decision' => 'KEEP_SEPARATE', 'note' => $d['note'] ?? null, 'user_id' => auth()->id()]);
        AuditLog::record('DUPLICATE_KEEP_SEPARATE', $d['type'], $d['keep_id'], ['other_id' => $d['merge_id'], 'note' => $d['note'] ?? null]);
        return redirect()->route('duplicates.index', ['type' => $d['type']])->with('ok', 'Marked Keep Separate.');
    }

    // Merge B into A: re-point every FK to B, fill A's blanks from B, archive B. History is kept.
    public function merge(Request $request)
    {
        abort_unless(in_array(auth()->user()->role, self::MERGERS, true), 403);
        $d = $this->validated($request);
        if ($d['keep_id'] == $d['merge_id']) {
            return back()->with('err', 'Choose two different records.');
        }
        $model = $d['type'] === 'contact' ? Contact::class : Customer::class;
        $keep = $model::findOrFail($d['keep_id']);
        $merge = $model::findOrFail($d['merge_id']);
        if ($keep->archived_at || $merge->archived_at || $keep->archived || $merge->archived) {
            return back()->with('err', 'Archived records cannot be merged. Restore first.');
        }
        $fk = $d['type'] === 'contact' ? 'contact_id' : 'customer_id';

        try {
            $moved = DB::transaction(function () use ($keep, $merge, $fk, $d) {
                $moved = [];
                foreach ($this->tablesWith($fk) as $tbl) {
                    if ($n = DB::table($tbl)->where($fk, $merge->id)->update([$fk => $keep->id])) {
                        $moved[$tbl] = $n;
                    }
                }
                // Documents attached via context
                if ($n = DB::table('documents')->where('context_type', $d['type'])->where('context_id', $merge->id)->update(['context_id' => $keep->id])) {
                    $moved['documents'] = $n;
                }
                $fill = $d['type'] === 'contact' ? ['email', 'mobile', 'buying_role', 'division'] : ['type', 'sector', 'state', 'parent', 'owner_id'];
                foreach ($fill as $f) {
                    if (blank($keep->{$f}) && filled($merge->{$f})) {
                        $keep->{$f} = $merge->{$f};
                    }
                }
                $keep->save();
                $merge->forceFill(['archived_at' => now(), 'archived_by' => auth()->id(), 'archive_reason' => 'Merged into '.($keep->code ?? '#'.$keep->id)]
                    + ($d['type'] === 'customer' ? ['archived' => true] : []))->save();
                GovernanceDuplicateDecision::create(['entity_type' => $d['type'], 'keep_id' => $keep->id, 'other_id' => $merge->id,
                    'decision' => 'MERGED', 'note' => $d['note'] ?? null, 'user_id' => auth()->id()]);
                return $moved;
            });
        } catch (QueryException $e) {
            report($e);
            return back()->with('err', 'Merge rolled back: a child record could not be re-pointed (unique constraint).');
        }

        AuditLog::record('MERGE', $d['type'], $keep->id, ['merged_id' => $merge->id, 'moved' => $moved, 'note' => $d['note'] ?? null]);
        return redirect()->route('duplicates.index', ['type' => $d['type']])
            ->with('ok', 'Merged '.($merge->code ?? $merge->name).' into '.($keep->code ?? $keep->name).'; '.array_sum($moved).' child records re-pointed.');
    }

    private function validated(Request $request): array
    {
        $d = $request->validate(['type' => 'required|in:customer,contact', 'keep_id' => 'required|integer', 'merge_id' => 'required|integer', 'note' => 'nullable|string|max:255']);
        $tbl = $d['type'] === 'contact' ? 'contacts' : 'customers';
        abort_unless(DB::table($tbl)->whereIn('id', [$d['keep_id'], $d['merge_id']])->count() === count(array_unique([$d['keep_id'], $d['merge_id']])), 404);
        return $d;
    }

    // Every table (including ones other modules add later) carrying the FK column
    private function tablesWith(string $fk): array
    {
        $self = $fk === 'contact_id' ? 'contacts' : 'customers';
        return collect(Schema::getTableListing(schemaQualified: false))
            ->reject(fn ($t) => $t === $self || $t === 'migrations')
            ->filter(fn ($t) => Schema::hasColumn($t, $fk))->values()->all();
    }

    private function impact(string $type, int $id): array
    {
        $fk = $type === 'contact' ? 'contact_id' : 'customer_id';
        $out = [];
        foreach ($this->tablesWith($fk) as $tbl) {
            if ($n = DB::table($tbl)->where($fk, $id)->count()) {
                $out[$tbl] = $n;
            }
        }
        return $out;
    }
}
