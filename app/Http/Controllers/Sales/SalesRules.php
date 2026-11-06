<?php

namespace App\Http\Controllers\Sales;

use App\Models\User;

/**
 * Role rules for the Customers / Sales / Tenders modules, taken from the prototype's
 * Role & Permission Matrix (UX-069): everyone may View; everyone except Management may Create;
 * Edit / Archive are HOD, AHOD, Superadmin (plus the record owner for day-to-day edits);
 * Approve-type decisions are HOD, AHOD, Management.
 */
class SalesRules
{
    public const PRIV = ['HOD', 'AHOD', 'Superadmin'];
    public const SALES = ['HOD', 'AHOD', 'Superadmin', 'BD Manager', 'BD Executive', 'Sales Admin / Inside Sales', 'Sales Admin'];
    public const OWNER_ROLES = ['BD Manager', 'BD Executive', 'HOD', 'AHOD'];
    public const DECIDERS = ['HOD', 'AHOD', 'Management', 'Superadmin'];
    public const CUSTOMER_TYPES = ['Federal Government', 'State Government', 'Local Municipality', 'GLC', 'Private'];
    public const RELATIONSHIPS = ['Cold', 'Warm', 'Good', 'Strong'];
    public const BUYING_ROLES = ['Decision Maker', 'Influencer', 'Technical Evaluator', 'Procurement', 'Finance', 'End User', 'Gatekeeper', 'Champion'];
    public const STALE_DAYS = 30;      // prototype config.staleOpportunityDays
    public const FOLLOW_UP_DAYS = 30;  // prototype config.followUpThresholdDays

    public static function canCreate(User $u): bool { return $u->role !== 'Management'; }
    public static function canCreateSales(User $u): bool { return in_array($u->role, self::SALES, true); }
    public static function canViewAllCustomers(User $u): bool
    {
        return ! in_array($u->role, ['BD Manager', 'BD Executive'], true);
    }

    public static function canEdit(User $u, ?int $ownerId = null): bool
    {
        return in_array($u->role, self::PRIV, true) || ($ownerId && $ownerId === $u->id);
    }
    public static function canArchive(User $u): bool { return in_array($u->role, self::PRIV, true); }
    public static function canDecide(User $u): bool { return in_array($u->role, self::DECIDERS, true); }
    // Value changes above 20% need HOD/AHOD approval unless made by HOD/AHOD
    public static function canChangeValueDirectly(User $u): bool { return in_array($u->role, ['HOD', 'AHOD'], true); }
    // CC Executive sees only minimum commercial context
    public static function canSeeCommercial(User $u): bool { return $u->role !== 'CC Executive'; }
}
