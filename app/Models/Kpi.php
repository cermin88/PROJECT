<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kpi extends Model
{
    protected $guarded = [];
    protected $casts = ['locked' => 'boolean', 'target' => 'float', 'actual' => 'float', 'reviewed_at' => 'datetime'];

    public const SOURCES = ['SYSTEM', 'MANUAL_EVIDENCE'];
    public const FREQUENCIES = ['Monthly', 'Quarterly', 'Half-Yearly', 'Yearly'];
    public const STATUSES = ['On Track', 'At Risk', 'Behind', 'Achieved'];

    // Roles that see other people's KPIs (Team KPI / Review & Lock)
    public const TEAM_ROLES = ['HOD', 'AHOD', 'Management', 'Superadmin', 'CC Manager', 'BD Manager'];

    public function user() { return $this->belongsTo(User::class); }
    public function adjustments() { return $this->hasMany(KpiAdjustment::class)->latest('id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }

    // Team scope: HOD/AHOD/Management/Superadmin see all; CC Manager sees CC staff; BD Manager sees BD + Sales Admin
    public function scopeVisibleTo($q, User $u)
    {
        return match (true) {
            in_array($u->role, ['HOD', 'AHOD', 'Management', 'Superadmin'], true) => $q,
            $u->role === 'CC Manager' => $q->whereHas('user', fn ($w) => $w->whereIn('role', ['CC Manager', 'CC Team Lead', 'CC Executive'])),
            $u->role === 'BD Manager' => $q->whereHas('user', fn ($w) => $w->where(fn ($r) => $r->where('role', 'like', 'BD %')->orWhere('role', 'like', 'Sales Admin%'))),
            default => $q->where('user_id', $u->id),
        };
    }

    public static function currentPeriod(): string
    {
        return now()->year.'-Q'.now()->quarter;
    }

    // Effective result = latest adjustment if any, original actual stays untouched
    public function effectiveActual(): float
    {
        $last = $this->adjustments->first();
        return $last ? (float) $last->new_actual : $this->actual;
    }

    public function achievement(): float
    {
        return $this->target > 0 ? round($this->effectiveActual() / $this->target * 100, 1) : 0.0;
    }

    // Weighted score for a set of KPIs (achievement capped at 100%)
    public static function weightedScore($kpis): ?float
    {
        $w = $kpis->sum('weight');
        return $w > 0 ? round($kpis->sum(fn ($k) => $k->weight * min($k->achievement(), 100)) / $w, 1) : null;
    }
}
