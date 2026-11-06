<?php

namespace App\Http\Controllers\Governance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Renewal;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// UX-064 Bulk Reassignment Wizard: current owner -> new owner -> review -> execute
class ReassignmentController extends Controller
{
    private const VIEWERS = ['HOD', 'AHOD', 'Management', 'Superadmin'];
    private const EXECUTORS = ['HOD', 'Superadmin']; // prototype: execute requires HOD/Superadmin

    public const ENTITIES = [
        'customers' => ['label' => 'Customers', 'model' => Customer::class, 'code' => 'code', 'name' => 'name'],
        'opportunities' => ['label' => 'Opportunities', 'model' => Opportunity::class, 'code' => 'code', 'name' => 'name'],
        'tenders' => ['label' => 'Tenders', 'model' => Tender::class, 'code' => 'code', 'name' => 'title'],
        'renewals' => ['label' => 'Renewals', 'model' => Renewal::class, 'code' => 'id', 'name' => 'type'],
    ];

    public function index(Request $request)
    {
        abort_unless(in_array(auth()->user()->role, self::VIEWERS, true), 403);
        $users = User::where('active', true)->orderBy('name')->get();
        $from = $request->integer('from') ?: null;
        $to = $request->integer('to') ?: null;
        $types = (array) $request->query('types', array_keys(self::ENTITIES));
        $openOnly = $request->boolean('open_only', $request->missing('from'));

        // Step 3 preview of exactly what will move
        $review = null;
        if ($from) {
            $review = [];
            foreach (array_intersect($types, array_keys(self::ENTITIES)) as $k) {
                $review[$k] = $this->query($k, $from, $openOnly)->get();
            }
        }

        return view('governance.reassignment', [
            'users' => $users, 'from' => $from, 'to' => $to, 'types' => $types, 'openOnly' => $openOnly, 'review' => $review,
            'entities' => self::ENTITIES, 'canExecute' => in_array(auth()->user()->role, self::EXECUTORS, true),
            'owned' => collect(self::ENTITIES)->map(fn ($e) => $e['model']::whereNotNull('owner_id')->selectRaw('owner_id, count(*) n')->groupBy('owner_id')->pluck('n', 'owner_id')),
        ]);
    }

    public function execute(Request $request)
    {
        abort_unless(in_array(auth()->user()->role, self::EXECUTORS, true), 403);
        $d = $request->validate([
            'from' => 'required|exists:users,id', 'to' => 'required|exists:users,id|different:from',
            'types' => 'required|array|min:1', 'types.*' => 'in:'.implode(',', array_keys(self::ENTITIES)),
            'reason' => 'required|string|max:255', 'open_only' => 'nullable|boolean',
        ]);
        $to = User::findOrFail($d['to']);
        if (! $to->active) {
            return back()->with('err', 'New owner must be an active user.');
        }

        $moved = DB::transaction(function () use ($d) {
            $moved = [];
            foreach ($d['types'] as $k) {
                $ids = $this->query($k, (int) $d['from'], (bool) ($d['open_only'] ?? false))->pluck('id')->all();
                if ($ids) {
                    self::ENTITIES[$k]['model']::whereIn('id', $ids)->update(['owner_id' => $d['to']]);
                    $moved[$k] = $ids; // ids kept in the audit detail so history can be traced
                }
            }
            return $moved;
        });

        $count = array_sum(array_map('count', $moved));
        AuditLog::record('BULK_REASSIGN', 'user', (int) $d['from'], ['to' => (int) $d['to'], 'reason' => $d['reason'], 'count' => $count, 'records' => $moved]);
        return redirect()->route('reassignment')->with('ok', "{$count} records reassigned to {$to->name}; logged in the audit trail.");
    }

    private function query(string $k, int $from, bool $openOnly)
    {
        $q = self::ENTITIES[$k]['model']::where('owner_id', $from);
        if ($openOnly && $k === 'opportunities') {
            $q->active();
        } elseif ($openOnly && $k === 'tenders') {
            $q->whereNotIn('status', ['AWARDED', 'LOST', 'CANCELLED', 'NO_BID']);
        }
        return $q->orderBy('id');
    }
}
