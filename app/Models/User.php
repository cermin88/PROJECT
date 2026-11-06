<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['code', 'name', 'email', 'password', 'job_position', 'role', 'active'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'active' => 'boolean'];
    }

    // Roles that may open restricted documents
    public function canSeeRestricted(): bool
    {
        return in_array($this->role, ['Management', 'HOD', 'Superadmin'], true);
    }

    // Roles that may decide approvals on behalf of the named approver
    public function isApprovalAuthority(): bool
    {
        return in_array($this->role, ['Management', 'HOD', 'AHOD'], true);
    }

    public function isCcRole(): bool
    {
        return in_array($this->role, ['CC Manager', 'CC Team Lead', 'CC Executive'], true);
    }

    public function canManageCases(): bool
    {
        return in_array($this->role, ['HOD', 'CC Manager', 'CC Team Lead', 'CC Executive', 'Superadmin'], true);
    }

    public function canAssignCases(): bool
    {
        return in_array($this->role, ['CC Manager', 'CC Team Lead', 'HOD', 'Superadmin'], true);
    }

    public function canLockKpi(): bool
    {
        return in_array($this->role, ['HOD', 'Management', 'Superadmin'], true);
    }
}
