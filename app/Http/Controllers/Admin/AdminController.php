<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

// Shared role gate for the Admin group (server-side; nav hiding is not security)
abstract class AdminController extends Controller
{
    public const VIEWERS = ['HOD', 'AHOD', 'Management', 'Superadmin'];
    public const EDITORS = ['HOD', 'Superadmin'];

    protected function gate(array $roles): void
    {
        abort_unless(in_array(auth()->user()->role, $roles, true), 403);
    }

    protected function canEdit(array $roles = self::EDITORS): bool
    {
        return in_array(auth()->user()->role, $roles, true);
    }
}
