<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportCase extends Model
{
    protected $table = 'cases';
    protected $guarded = [];
    protected $casts = [
        'opened_at' => 'datetime', 'paused_at' => 'datetime', 'confirmed_at' => 'datetime', 'resolved_at' => 'datetime',
    ];

    public const STATUSES = ['Open', 'Assigned', 'In Progress', 'Pending Customer', 'Pending Internal', 'Resolved', 'Closed', 'Reopened'];
    public const DONE = ['Resolved', 'Closed'];
    public const PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];
    public const SLA_HOURS = ['Low' => 48, 'Medium' => 24, 'High' => 8, 'Critical' => 4];
    public const CHANNELS = ['Phone', 'Email', 'WhatsApp', 'Walk-in', 'Internal Referral', 'Survey', 'Others'];
    public const CONFIRM_METHODS = ['Email', 'WhatsApp', 'Phone', 'Written Confirmation', 'System Survey', 'Portal', 'Others'];
    public const ASSIGNEE_ROLES = ['CC Executive', 'CC Team Lead'];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function contact() { return $this->belongsTo(Contact::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assignee_id'); }
    public function timeline() { return $this->hasMany(CaseTimeline::class, 'case_id')->latest('id'); }

    // CC Executive only sees cases assigned to them; other roles see the whole queue
    public function scopeVisibleTo($q, User $u)
    {
        return $u->role === 'CC Executive' ? $q->where('assignee_id', $u->id) : $q;
    }

    public function visibleTo(User $u): bool
    {
        return $u->role !== 'CC Executive' || $this->assignee_id === $u->id;
    }

    public function isDone(): bool { return in_array($this->status, self::DONE, true); }

    // Total minutes spent in Pending Customer, including a pause still running
    public function pausedMinutesTotal(): int
    {
        $m = (int) $this->paused_minutes;
        if ($this->paused_at) {
            $m += (int) $this->paused_at->diffInMinutes(now());
        }
        return $m;
    }

    // Working minutes on the resolution clock: opened → resolved (or now), minus customer-wait time
    public function elapsedMinutes(): int
    {
        $end = $this->resolved_at ?? ($this->isDone() ? ($this->confirmed_at ?? $this->updated_at) : null) ?? now();
        return max(0, (int) $this->opened_at->diffInMinutes($end) - $this->pausedMinutesTotal());
    }

    public function remainingMinutes(): int { return $this->sla_hours * 60 - $this->elapsedMinutes(); }

    // Running | Paused | Breached (open) · Met | Missed (resolved/closed)
    public function slaState(): string
    {
        if ($this->isDone()) {
            return $this->remainingMinutes() >= 0 ? 'Met' : 'Missed';
        }
        if ($this->paused_at) {
            return 'Paused';
        }
        return $this->remainingMinutes() < 0 ? 'Breached' : 'Running';
    }

    public function slaClass(): string
    {
        return ['Running' => 'ok', 'Met' => 'ok', 'Paused' => 'warn', 'Breached' => 'bad', 'Missed' => 'bad'][$this->slaState()];
    }

    // Resolution deadline = opened + SLA hours + time spent waiting on the customer
    public function slaDueAt()
    {
        return $this->opened_at->copy()->addHours($this->sla_hours)->addMinutes($this->pausedMinutesTotal());
    }

    public function isBreached(): bool
    {
        return $this->slaState() === 'Breached';
    }

    public function agingHours(): int
    {
        return (int) $this->opened_at->diffInHours($this->resolved_at ?? now());
    }

    public static function fmtMinutes(int $m): string
    {
        $sign = $m < 0 ? '-' : '';
        $m = abs($m);
        return $sign . intdiv($m, 60) . 'h ' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT) . 'm';
    }
}
