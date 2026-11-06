<?php

namespace App\Http\Controllers\Ecosystem;

use App\Models\User;

// Role rules for the Ecosystem + Contact Centre module (Phase 2L prototype is the source of truth)
final class Access
{
    // BD-side roles maintain partner / product / competitor master data (Management is view-only)
    public const ECOSYSTEM_EDITORS = ['HOD', 'AHOD', 'Superadmin', 'BD Manager', 'BD Executive', 'Sales Admin', 'Sales Admin / Inside Sales'];
    public const PARTNER_ARCHIVERS = ['HOD', 'AHOD', 'Superadmin', 'BD Manager'];
    // CC dashboards: CC roles plus leadership
    public const CC_VIEWERS = ['CC Manager', 'CC Team Lead', 'CC Executive', 'HOD', 'AHOD', 'Management', 'Superadmin'];

    public static function editsEcosystem(User $u): bool { return in_array($u->role, self::ECOSYSTEM_EDITORS, true); }
    public static function archivesPartner(User $u): bool { return in_array($u->role, self::PARTNER_ARCHIVERS, true); }
    public static function viewsCc(User $u): bool { return in_array($u->role, self::CC_VIEWERS, true); }
    // Partner 360 commercial associations are not exposed to Contact Centre roles
    public static function seesCommercial(User $u): bool { return ! $u->isCcRole(); }
}
