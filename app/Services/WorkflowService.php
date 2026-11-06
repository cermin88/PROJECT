<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\ApprovalDelegation;
use App\Models\Award;
use App\Models\AuditLog;
use App\Models\Kpi;
use App\Models\Opportunity;
use App\Models\OpportunityStageHistory;
use App\Models\Project;
use App\Models\Renewal;
use App\Models\SupportCase;
use App\Models\Tender;
use App\Models\TenderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Business rules carried over from the Phase 2L prototype.
 * Every method returns [bool $ok, string $message] so controllers stay thin.
 */
class WorkflowService
{
    public static function nextCode(string $prefix, string $table, string $column = 'code'): string
    {
        $n = (int) DB::table($table)->max('id') + 1;
        return sprintf('%s-%s-%06d', $prefix, date('Y'), $n);
    }

    /* ------------------------------------------------------------------ Tender */

    public function requestPricingApproval(Tender $tender, User $by): array
    {
        $pending = Approval::where('subject_type', 'tender')->where('subject_id', $tender->id)
            ->where('type', 'Tender Pricing/Submission')->where('status', 'Pending')->exists();
        if ($pending) {
            return [false, 'A pricing approval is already pending for this tender.'];
        }
        $hod = User::where('role', 'HOD')->first();
        if (! $hod) {
            return [false, 'No HOD user configured to approve.'];
        }

        $a = Approval::create([
            'code' => self::nextCode('APR', 'approvals'),
            'type' => 'Tender Pricing/Submission',
            'subject_type' => 'tender',
            'subject_id' => $tender->id,
            'requester_id' => $by->id,
            'approver_id' => $hod->id,
            'snapshot' => [ // frozen at time of submission
                'tender' => $tender->code,
                'cost' => (float) $tender->cost,
                'price' => (float) $tender->price,
                'margin_pct' => $tender->margin(),
            ],
        ]);
        AuditLog::record('APPROVAL_REQUESTED', 'approval', $a->id, ['type' => $a->type]);
        return [true, 'Pricing approval requested from ' . $hod->name . '.'];
    }

    public function submitTender(Tender $tender, User $by): array
    {
        return DB::transaction(function () use ($tender, $by) {
            $t = Tender::whereKey($tender->id)->lockForUpdate()->first();

            if ($t->status === 'SUBMITTED') {
                return [true, 'Tender already submitted (no duplicate recorded).']; // idempotent retry
            }
            if (! in_array($t->status, Tender::SUBMITTABLE, true)) {
                return [false, "Tender in status {$t->status} cannot be submitted."];
            }

            $open = $t->checklist()->where('mandatory', true)->where('done', false)->count();
            if ($open > 0) {
                return [false, "Submit blocked: {$open} mandatory checklist item(s) incomplete."];
            }
            if (! $t->pricing_approved) {
                return [false, 'Submit blocked: pricing has not been approved.'];
            }

            $t->update(['status' => 'SUBMITTED', 'submitted_at' => now()]);
            TenderStatusHistory::create(['tender_id' => $t->id, 'status' => 'SUBMITTED', 'changed_by' => $by->id]);
            AuditLog::record('TENDER_SUBMITTED', 'tender', $t->id);
            return [true, 'Tender submitted.'];
        });
    }

    /* --------------------------------------------------------------- Approvals */

    public function decideApproval(Approval $approval, User $by, string $decision, ?string $note): array
    {
        if (! in_array($decision, ['Approved', 'Rejected', 'Returned'], true)) {
            return [false, 'Invalid decision.'];
        }
        if ($approval->requester_id === $by->id) {
            AuditLog::record('SELF_APPROVAL_BLOCKED', 'approval', $approval->id);
            return [false, 'You cannot decide an approval you requested.'];
        }
        // Active effective-dated delegation lets the delegate act for the named approver (UX-071)
        $delegated = $approval->approver_id !== $by->id && ApprovalDelegation::covers($approval->approver_id, $by->id);
        if ($approval->approver_id !== $by->id && ! $by->isApprovalAuthority() && ! $delegated) {
            return [false, 'You are not an approver for this item.'];
        }

        return DB::transaction(function () use ($approval, $by, $decision, $note, $delegated) {
            $a = Approval::whereKey($approval->id)->lockForUpdate()->first();
            if ($a->status !== 'Pending') {
                return [false, "Approval already {$a->status}."];
            }
            $a->update(['status' => $decision, 'decision_note' => $note, 'decided_at' => now()]);
            AuditLog::record('APPROVAL_' . strtoupper($decision), 'approval', $a->id,
                ['note' => $note] + ($delegated ? ['on_behalf_of' => $a->approver_id] : []));

            if ($decision === 'Approved') {
                $this->applyApprovedEffects($a, $by);
            }
            return [true, "Approval {$decision}."];
        });
    }

    private function applyApprovedEffects(Approval $a, User $by): void
    {
        if ($a->subject_type === 'tender' && $a->type === 'Tender Pricing/Submission') {
            $t = Tender::find($a->subject_id);
            if ($t) {
                $t->pricing_approved = true;
                if ($t->status === 'PREPARATION') {
                    $t->status = 'READY_FOR_SUBMISSION';
                    TenderStatusHistory::create(['tender_id' => $t->id, 'status' => $t->status, 'changed_by' => $by->id]);
                }
                $t->save();
            }
        }
        if ($a->subject_type === 'award' && $a->type === 'Award Qualification') {
            $award = Award::find($a->subject_id);
            if ($award) {
                $this->qualifyAward($award, $by);
            }
        }
    }

    /* ------------------------------------------------------------------ Awards */

    public function addEvidence(Award $award, string $type, string $reference): array
    {
        $award->evidence()->create(['type' => $type, 'reference' => $reference]);
        AuditLog::record('AWARD_EVIDENCE_ADDED', 'award', $award->id, compact('type', 'reference'));
        return [true, 'Evidence added.'];
    }

    public function requestAwardQualification(Award $award, User $by): array
    {
        if ($award->revenue_qualified) {
            return [false, 'Award is already qualified.'];
        }
        if ($award->evidence()->count() === 0) {
            return [false, 'Cannot qualify: attach PO / LOA / official Award evidence first.'];
        }
        $hod = User::where('role', 'HOD')->first();
        $a = Approval::create([
            'code' => self::nextCode('APR', 'approvals'),
            'type' => 'Award Qualification',
            'subject_type' => 'award',
            'subject_id' => $award->id,
            'requester_id' => $by->id,
            'approver_id' => $hod->id,
            'snapshot' => [
                'award' => $award->code,
                'value' => (float) $award->value,
                'evidence' => $award->evidence->map(fn ($e) => $e->type . ':' . $e->reference)->all(),
            ],
        ]);
        AuditLog::record('APPROVAL_REQUESTED', 'approval', $a->id, ['type' => $a->type]);
        return [true, 'Award qualification sent for approval.'];
    }

    /** Idempotent: calling twice never creates a second Project (also enforced by UNIQUE award_id). */
    public function qualifyAward(Award $award, User $by): array
    {
        return DB::transaction(function () use ($award, $by) {
            $a = Award::whereKey($award->id)->lockForUpdate()->first();
            if ($a->evidence()->count() === 0) {
                return [false, 'No evidence attached; Award stays unqualified and contributes RM0.'];
            }

            $a->update(['status' => 'QUALIFIED', 'revenue_qualified' => true]);

            $project = Project::where('award_id', $a->id)->first();
            if (! $project) {
                $project = Project::create([
                    'code' => self::nextCode('PRJ', 'projects'),
                    'award_id' => $a->id,
                    'title' => str_replace('Award', 'Project', $a->title),
                    'status' => 'Kickoff',
                    'owner_id' => $a->opportunity?->owner_id,
                ]);
                foreach ([['Kickoff', 20], ['Delivery', 50], ['UAT / Closure', 30]] as [$name, $w]) {
                    $project->milestones()->create(['name' => $name, 'weight' => $w, 'progress' => 0]);
                }
            }

            if ($a->opportunity && $a->opportunity->stage !== 'Award') {
                $a->opportunity->update(['stage' => 'Award']);
                OpportunityStageHistory::create([
                    'opportunity_id' => $a->opportunity_id, 'stage' => 'Award',
                    'changed_by' => $by->id, 'reason' => 'Award qualified',
                ]);
            }
            AuditLog::record('AWARD_QUALIFIED', 'award', $a->id, ['project' => $project->code]);
            return [true, "Award qualified; project {$project->code} ready."];
        });
    }

    /* ---------------------------------------------------------------- Renewals */

    /** One Renewal -> exactly one linked Opportunity; retries return the existing one. */
    public function createRenewalOpportunity(Renewal $renewal, User $by): array
    {
        return DB::transaction(function () use ($renewal, $by) {
            $r = Renewal::whereKey($renewal->id)->lockForUpdate()->first();
            if ($r->opportunity_id) {
                return [true, 'Renewal opportunity already exists (no duplicate created).'];
            }
            $opp = Opportunity::create([
                'code' => self::nextCode('OPP', 'opportunities'),
                'name' => "Renewal — {$r->type} — {$r->customer->name}",
                'customer_id' => $r->customer_id,
                'owner_id' => $r->owner_id ?? $by->id,
                'stage' => 'Lead Opportunity',
                'value' => 0,
                'probability' => 10,
                'is_renewal' => true,
            ]);
            OpportunityStageHistory::create([
                'opportunity_id' => $opp->id, 'stage' => 'Lead Opportunity',
                'changed_by' => $by->id, 'reason' => 'Created from renewal',
            ]);
            $r->update(['opportunity_id' => $opp->id]);
            AuditLog::record('RENEWAL_OPPORTUNITY_CREATED', 'renewal', $r->id, ['opportunity' => $opp->code]);
            return [true, "Renewal opportunity {$opp->code} created."];
        });
    }

    /* ------------------------------------------------------------------- Cases */

    public function changeCaseStatus(SupportCase $case, string $status, User $by): array
    {
        if (! in_array($status, SupportCase::STATUSES, true)) {
            return [false, 'Unknown status.'];
        }
        if ($status === 'Closed' && ! $case->confirmation_method) {
            return [false, 'Cannot close: record customer confirmation first.'];
        }
        if ($status === 'Assigned' && ! $case->assignee_id) {
            return [false, 'Assign the case to an executive first.'];
        }

        return DB::transaction(function () use ($case, $status, $by) {
            $c = SupportCase::whereKey($case->id)->lockForUpdate()->first();

            // Only "Pending Customer" pauses the resolution SLA; "Pending Internal" does not.
            if ($status === 'Pending Customer' && ! $c->paused_at) {
                $c->paused_at = now();
            } elseif ($status !== 'Pending Customer' && $c->paused_at) {
                $c->paused_minutes += $c->paused_at->diffInMinutes(now());
                $c->paused_at = null;
            }

            if ($status === 'Reopened') {
                $c->confirmation_method = null;
                $c->confirmed_at = null;
                $c->csat = null;
            }
            $c->status = $status;
            $c->save();
            $c->timeline()->create(['text' => "Status → {$status}", 'user_id' => $by->id]);
            AuditLog::record('CASE_STATUS', 'case', $c->id, ['status' => $status]);
            return [true, "Case moved to {$status}."];
        });
    }

    public function assignCase(SupportCase $case, User $assignee, User $by): array
    {
        if (! $by->canAssignCases()) {
            AuditLog::record('ACCESS_DENIED', 'case', $case->id, ['action' => 'assign']);
            return [false, 'Only CC Manager / Team Lead may assign cases.'];
        }
        $case->update(['assignee_id' => $assignee->id, 'status' => $case->status === 'Open' ? 'Assigned' : $case->status]);
        $case->timeline()->create(['text' => "Assigned to {$assignee->name}", 'user_id' => $by->id]);
        AuditLog::record('CASE_ASSIGNED', 'case', $case->id, ['assignee' => $assignee->id]);
        return [true, 'Case assigned.'];
    }

    public function confirmCase(SupportCase $case, string $method, ?string $remarks, ?int $csat, User $by): array
    {
        $case->update([
            'confirmation_method' => $method,
            'confirmed_at' => now(),
            'confirmation_remarks' => $remarks,
            'csat' => $csat,
        ]);
        $case->timeline()->create(['text' => "Customer confirmation recorded via {$method}", 'user_id' => $by->id]);
        AuditLog::record('CASE_CONFIRMED', 'case', $case->id, compact('method'));
        return [true, 'Confirmation recorded. The case can now be closed.'];
    }

    /* --------------------------------------------------------------------- KPI */

    public function lockKpi(Kpi $kpi, User $by): array
    {
        if (! $by->canLockKpi()) {
            return [false, 'Only HOD / Management may lock KPI results.'];
        }
        $kpi->update(['locked' => true]);
        AuditLog::record('KPI_LOCKED', 'kpi', $kpi->id);
        return [true, 'KPI locked.'];
    }

    /** Locked KPIs keep the original result; the correction is appended as an adjustment. */
    public function adjustKpi(Kpi $kpi, float $newActual, string $reason, User $by): array
    {
        if ($kpi->locked) {
            $kpi->adjustments()->create([
                'old_actual' => $kpi->effectiveActual(),
                'new_actual' => $newActual,
                'reason' => $reason,
                'adjusted_by' => $by->id,
            ]);
            AuditLog::record('KPI_ADJUSTED', 'kpi', $kpi->id, ['reason' => $reason, 'new' => $newActual]);
            return [true, 'Adjustment appended; original locked result is preserved.'];
        }
        $kpi->update(['actual' => $newActual]);
        AuditLog::record('KPI_UPDATED', 'kpi', $kpi->id, ['new' => $newActual]);
        return [true, 'KPI updated.'];
    }

    /* --------------------------------------------------------------- Dashboard */

    public function metrics(?User $viewer = null): array
    {
        $won = Tender::where('status', 'AWARDED')->count();
        $lost = Tender::where('status', 'LOST')->count();
        $decided = $won + $lost;
        $awards = $viewer ? Award::visibleToProjectOwner($viewer) : Award::query();

        return [
            'revenue' => (float) (clone $awards)->qualified()->sum('value'),
            'revenue_target' => (float) config('csa.revenue_target', 8000000),
            'pipeline_value' => (float) Opportunity::active()->sum('value'),
            'pipeline_count' => Opportunity::active()->count(),
            'win_rate' => $decided > 0 ? round($won / $decided * 100, 1) : null, // Awarded + Lost only
            'tenders_open' => Tender::whereIn('status', ['PREPARATION', 'READY_FOR_SUBMISSION', 'SUBMISSION_APPROVAL'])->count(),
            'pending_approvals' => Approval::where('status', 'Pending')->count(),
            'open_cases' => SupportCase::whereNotIn('status', ['Resolved', 'Closed'])->count(),
            'unqualified_awards' => (clone $awards)->where('revenue_qualified', false)->count(),
        ];
    }
}
