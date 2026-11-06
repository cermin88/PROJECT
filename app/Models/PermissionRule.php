<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// One cell of the Role × Module × Action matrix (UX-069)
class PermissionRule extends Model
{
    protected $table = 'permission_matrix';
    protected $guarded = [];
    protected $casts = ['allowed' => 'boolean'];

    public const ROLES = ['Management', 'HOD', 'AHOD', 'BD Manager', 'BD Executive', 'Sales Admin / Inside Sales', 'CC Manager', 'CC Team Lead', 'CC Executive', 'Superadmin'];
    public const MODULES = ['Customers', 'Sales', 'Tenders', 'Work', 'Events', 'Commercial', 'Ecosystem', 'Contact Centre', 'Performance', 'Intelligence', 'Governance', 'Admin'];
    public const ACTIONS = ['View', 'Create', 'Edit', 'Archive', 'Export', 'Approve', 'Admin'];

    // Baseline rule from the prototype's frozen RBAC matrix
    public static function baseline(string $role, string $action): bool
    {
        return $action === 'View'
            || in_array($role, ['HOD', 'AHOD', 'Superadmin'], true)
            || ($action === 'Create' && $role !== 'Management')
            || ($action === 'Approve' && in_array($role, ['HOD', 'AHOD', 'Management'], true));
    }

    public static function allows(string $role, string $module, string $action): bool
    {
        return (bool) static::where(compact('role', 'module', 'action'))->value('allowed');
    }
}
