<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\MasterDataValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class MasterDataController extends AdminController
{
    public function index(Request $request)
    {
        $this->gate(self::VIEWERS);
        $set = in_array($request->query('set'), MasterDataValue::SETS, true) ? $request->query('set') : null;
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : null;
        $s = trim((string) $request->query('q'));
        // Grouped per set (ordered picklists with reorder controls), so filtered but not paginated
        $values = MasterDataValue::when($set, fn ($q) => $q->where('set', $set))
            ->when($status, fn ($q) => $q->where('active', $status === 'active'))
            ->when($s !== '', fn ($q) => $q->where('value', 'like', "%{$s}%"))
            ->orderBy('set')->orderBy('sort')->get()->groupBy('set');
        // Historical usage per value, so admins see what is referenced before deactivating
        $usage = [];
        foreach (MasterDataValue::USAGE as $s => [$table, $col]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $col)) {
                $usage[$s] = DB::table($table)->whereNotNull($col)->groupBy($col)->pluck(DB::raw('count(*)'), $col)->all();
            }
        }
        return view('admin.master-data', ['values' => $values, 'usage' => $usage, 'set' => $set, 'sets' => MasterDataValue::SETS, 'canEdit' => $this->canEdit()]);
    }

    public function store(Request $request)
    {
        $this->gate(self::EDITORS);
        $d = $request->validate([
            'set' => ['required', Rule::in(MasterDataValue::SETS)],
            'value' => ['required', 'string', 'max:120', Rule::unique('master_data_values')->where('set', $request->input('set'))],
        ]);
        $v = MasterDataValue::create($d + ['sort' => (int) MasterDataValue::where('set', $d['set'])->max('sort') + 1]);
        AuditLog::record('MASTER_DATA_ADDED', 'master_data', $v->id, $d);
        return back()->with('ok', "{$v->value} added to {$v->set}.");
    }

    public function toggle(MasterDataValue $value)
    {
        $this->gate(self::EDITORS);
        $value->update(['active' => ! $value->active]);
        AuditLog::record($value->active ? 'MASTER_DATA_ACTIVATED' : 'MASTER_DATA_DEACTIVATED', 'master_data', $value->id, ['set' => $value->set, 'value' => $value->value]);
        return back()->with('ok', "{$value->value} " . ($value->active ? 'activated' : 'deactivated (kept for historical records)') . '.');
    }

    public function move(Request $request, MasterDataValue $value)
    {
        $this->gate(self::EDITORS);
        $dir = $request->validate(['dir' => 'required|in:up,down'])['dir'];
        $swap = MasterDataValue::where('set', $value->set)
            ->where('sort', $dir === 'up' ? '<' : '>', $value->sort)
            ->orderBy('sort', $dir === 'up' ? 'desc' : 'asc')->first();
        if (! $swap) {
            return back();
        }
        DB::transaction(function () use ($value, $swap) {
            [$a, $b] = [$value->sort, $swap->sort];
            $value->update(['sort' => $b]);
            $swap->update(['sort' => $a]);
        });
        AuditLog::record('MASTER_DATA_REORDERED', 'master_data', $value->id, ['set' => $value->set, 'dir' => $dir]);
        return back();
    }
}
