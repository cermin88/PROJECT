<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $guarded = [];
    protected $casts = ['due_date' => 'date', 'completed_at' => 'datetime'];

    public const STATUSES = ['Not Started', 'In Progress', 'Completed', 'Cancelled'];
    public const CLOSED = ['Completed', 'Cancelled'];

    public function assignee() { return $this->belongsTo(User::class, 'assignee_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function opportunity() { return $this->belongsTo(Opportunity::class); }
    public function tender() { return $this->belongsTo(Tender::class); }
    public function sourceActivity() { return $this->belongsTo(Activity::class, 'source_activity_id'); }
    public function actionItem() { return $this->belongsTo(MeetingActionItem::class, 'meeting_action_item_id'); }

    public function scopeOpen($q) { return $q->whereNotIn('status', self::CLOSED); }
    public function scopeOverdue($q) { return $q->open()->whereDate('due_date', '<', today()); }

    // Overdue is derived, never stored
    public function isOverdue(): bool
    {
        return ! in_array($this->status, self::CLOSED, true) && $this->due_date->lt(today());
    }

    public function nextStatus(): ?string
    {
        return ['Not Started' => 'In Progress', 'In Progress' => 'Completed'][$this->status] ?? null;
    }

    public function contextLabel(): string
    {
        if ($this->meeting_action_item_id) {
            return 'MoM ' . ($this->actionItem?->minute?->code ?? '');
        }
        return $this->tender?->code ?? $this->opportunity?->code ?? $this->customer?->name ?? '—';
    }
}
