<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Effective-dated delegation of approval rights; expires automatically after ends_on
class ApprovalDelegation extends Model
{
    protected $guarded = [];
    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'revoked_at' => 'datetime'];

    public function delegator() { return $this->belongsTo(User::class, 'delegator_id'); }
    public function delegate() { return $this->belongsTo(User::class, 'delegate_id'); }

    public function scopeActiveOn(Builder $q, $date = null): Builder
    {
        $d = ($date ?? now())->toDateString();
        return $q->whereNull('revoked_at')->whereDate('starts_on', '<=', $d)->whereDate('ends_on', '>=', $d);
    }

    /** Is $delegateId currently allowed to act for $delegatorId? */
    public static function covers(int $delegatorId, int $delegateId): bool
    {
        return static::activeOn()->where('delegator_id', $delegatorId)->where('delegate_id', $delegateId)->exists();
    }

    /** User ids whose approvals $delegateId may currently decide. */
    public static function delegatorIdsFor(int $delegateId): array
    {
        return static::activeOn()->where('delegate_id', $delegateId)->pluck('delegator_id')->all();
    }

    public function statusLabel(): string
    {
        if ($this->revoked_at) {
            return 'Revoked';
        }
        if ($this->ends_on->lt(today())) {
            return 'Expired';
        }
        return $this->starts_on->gt(today()) ? 'Scheduled' : 'Active';
    }
}
