<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\PermissionRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermissionController extends AdminController
{
    public function index(Request $request)
    {
        $this->gate(self::VIEWERS);
        $role = in_array($request->query('role'), PermissionRule::ROLES, true) ? $request->query('role') : 'BD Manager';
        $all = PermissionRule::all();
        // Role × Action summary: how many modules each role may perform the action in
        $summary = $all->where('allowed', true)->groupBy(fn ($r) => $r->role . '|' . $r->action)->map->count();
        $cells = $all->where('role', $role)->mapWithKeys(fn ($r) => [$r->module . '|' . $r->action => $r->allowed]);
        return view('admin.permissions', [
            'role' => $role, 'summary' => $summary, 'cells' => $cells, 'canEdit' => $this->canEdit(['Superadmin']),
            'roles' => PermissionRule::ROLES, 'modules' => PermissionRule::MODULES, 'actions' => PermissionRule::ACTIONS,
        ]);
    }

    public function update(Request $request)
    {
        $this->gate(['Superadmin']);
        $d = $request->validate(['role' => ['required', Rule::in(PermissionRule::ROLES)], 'cells' => 'array']);
        $on = $d['cells'] ?? [];
        $changes = [];
        foreach (PermissionRule::MODULES as $m) {
            foreach (PermissionRule::ACTIONS as $a) {
                $allowed = ! empty($on[$m][$a]);
                if ($d['role'] === 'Superadmin' && $m === 'Admin') {
                    $allowed = true; // lock-out guard: Superadmin always keeps Admin module rights
                }
                $rule = PermissionRule::firstOrNew(['role' => $d['role'], 'module' => $m, 'action' => $a]);
                if (! $rule->exists || $rule->allowed !== $allowed) {
                    $changes[] = "{$m}.{$a}=" . ($allowed ? 'Y' : 'N');
                    $rule->allowed = $allowed;
                    $rule->save();
                }
            }
        }
        if (! $changes) {
            return back()->with('ok', 'No changes.');
        }
        AuditLog::record('PERMISSION_MATRIX_CHANGED', 'role', null, ['role' => $d['role'], 'changes' => $changes]);
        return redirect()->route('permissions', ['role' => $d['role']])->with('ok', count($changes) . " permission(s) updated for {$d['role']}.");
    }
}
