<?php

namespace App\Models;

use App\Services\NotificationCentre;
use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    protected $guarded = [];
    protected $casts = ['snapshot' => 'array', 'decided_at' => 'datetime'];

    public function requester() { return $this->belongsTo(User::class, 'requester_id'); }
    public function approver() { return $this->belongsTo(User::class, 'approver_id'); }

    // Notification centre: approver (+ active delegates) on request, requester on decision
    protected static function booted(): void
    {
        static::created(function (Approval $a) {
            if (($a->status ?? 'Pending') !== 'Pending') { // DB default is Pending
                return;
            }
            $link = route('approvals.index', [], false);
            $ids = array_unique([$a->approver_id, ...ApprovalDelegation::activeOn()->where('delegator_id', $a->approver_id)->pluck('delegate_id')->all()]);
            foreach ($ids as $id) {
                NotificationCentre::send($id, "Approval pending: {$a->code} — {$a->type}", 'Action Required', $link,
                    $id === $a->approver_id ? null : 'Delegated to you');
            }
        });
        static::updated(function (Approval $a) {
            if ($a->wasChanged('status') && ($a->getOriginal('status') ?? 'Pending') === 'Pending' && $a->status !== 'Pending') {
                NotificationCentre::send($a->requester_id, "{$a->code} {$a->type} was {$a->status}",
                    $a->status === 'Approved' ? 'Information' : 'Warning', route('approvals.index', [], false), $a->decision_note);
            }
        });
    }
}
