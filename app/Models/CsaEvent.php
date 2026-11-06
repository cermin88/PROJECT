<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CsaEvent extends Model
{
    protected $table = 'csa_events';
    protected $guarded = [];
    protected $casts = ['event_date' => 'datetime', 'budget' => 'decimal:2', 'actual' => 'decimal:2', 'pm_saved_at' => 'datetime'];

    public const LIFECYCLE = ['DRAFT', 'PENDING_APPROVAL', 'APPROVED', 'ACTIVE/HELD', 'POST_MORTEM', 'CLOSED'];
    public const STATUSES = [...self::LIFECYCLE, 'REJECTED'];
    public const INVITE_STATUSES = ['Invited', 'Confirmed', 'Declined', 'Attended', 'No Show'];

    // Roles that may plan/run events (CC roles are view-only)
    public const MANAGER_ROLES = ['Management', 'HOD', 'AHOD', 'Superadmin', 'BD Manager', 'BD Executive', 'Sales Admin', 'Sales Admin / Inside Sales'];
    public const PRIVILEGED = ['Management', 'HOD', 'AHOD', 'Superadmin'];
    public const APPROVER_ROLES = ['HOD', 'AHOD']; // Approval Matrix: Event / Budget — HOD / AHOD — Immutable snapshot

    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function approval() { return $this->belongsTo(Approval::class); }
    public function invitations() { return $this->hasMany(CsaEventInvitation::class)->orderBy('name'); }
    public function opportunities() { return $this->belongsToMany(Opportunity::class, 'csa_event_opportunity')->withTimestamps(); }
    public function pmAuthor() { return $this->belongsTo(User::class, 'pm_by'); }

    public static function canCreate(User $u): bool
    {
        return in_array($u->role, self::MANAGER_ROLES, true);
    }

    // Owner or privileged role may manage the event
    public function canManage(User $u): bool
    {
        return self::canCreate($u) && ($this->owner_id === $u->id || in_array($u->role, self::PRIVILEGED, true));
    }

    public static function isApprover(User $u): bool
    {
        return in_array($u->role, self::APPROVER_ROLES, true);
    }

    public function isEditable(): bool { return $this->status === 'DRAFT'; } // budget frozen once submitted
    public function invitationsOpen(): bool { return in_array($this->status, ['DRAFT', 'PENDING_APPROVAL', 'APPROVED', 'ACTIVE/HELD'], true); }
    public function attendanceOpen(): bool { return in_array($this->status, ['ACTIVE/HELD', 'POST_MORTEM'], true); }

    public function countStatus(string $s): int { return $this->invitations->where('status', $s)->count(); }
    public function invitedCount(): int { return $this->invitations->count(); }
    public function attendedCount(): int { return $this->countStatus('Attended'); }

    public function attendanceRate(): ?int
    {
        $n = $this->invitedCount();
        return $n ? (int) round($this->attendedCount() / $n * 100) : null;
    }

    public function roiValue(): float { return (float) $this->opportunities->sum('value'); }
    public function overBudget(): bool { return (float) $this->actual > (float) $this->budget; }

    /**
     * Pull decisions made in the Action & Approval Inbox back onto pending events.
     * Approved → APPROVED, Returned → DRAFT (editable, resubmit), Rejected → REJECTED.
     */
    public static function syncApprovals(): void
    {
        $map = ['Approved' => 'APPROVED', 'Returned' => 'DRAFT', 'Rejected' => 'REJECTED'];
        static::with('approval')->where('status', 'PENDING_APPROVAL')->whereNotNull('approval_id')->get()
            ->each(function (self $e) use ($map) {
                $to = $map[$e->approval?->status] ?? null;
                if ($to) {
                    $e->update(['status' => $to]);
                    AuditLog::record('EVENT_STATUS', 'event', $e->id, ['status' => $to, 'approval' => $e->approval->code]);
                }
            });
    }
}
