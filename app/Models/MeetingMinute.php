<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingMinute extends Model
{
    protected $guarded = [];
    protected $casts = ['meeting_at' => 'datetime', 'submitted_at' => 'datetime', 'decided_at' => 'datetime'];

    public const STATUSES = ['DRAFT', 'PENDING_APPROVAL', 'APPROVED', 'RETURNED'];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function opportunity() { return $this->belongsTo(Opportunity::class); }
    public function tender() { return $this->belongsTo(Tender::class); }
    public function author() { return $this->belongsTo(User::class, 'author_id'); }
    public function decider() { return $this->belongsTo(User::class, 'decided_by'); }
    public function attendees() { return $this->hasMany(MeetingAttendee::class); }
    public function actionItems() { return $this->hasMany(MeetingActionItem::class)->orderBy('due_date'); }

    // Content may only change while Draft or Returned
    public function isEditable(): bool { return in_array($this->status, ['DRAFT', 'RETURNED'], true); }
}
