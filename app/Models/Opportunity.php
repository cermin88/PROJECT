<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    protected $guarded = [];
    protected $casts = ['value' => 'decimal:2', 'next_action_date' => 'date', 'is_renewal' => 'boolean'];

    public const STAGES = [
        'Lead Opportunity', 'Solution Presentation', 'Proposal & Quotation Submission',
        'Budget Approval & Evaluation', 'Specification Tender', 'Release Tender',
        'Document Tender', 'Tender Evaluation', 'Negotiable/Shortlisted',
    ];
    public const CLOSED = ['Award', 'Lost', 'Cancelled'];

    // Active Pipeline excludes Lost / Award / Cancelled
    public function scopeActive($q) { return $q->whereNotIn('stage', self::CLOSED)->whereNull('archived_at'); } // archived rows leave the pipeline (UX-063)

    public function customer() { return $this->belongsTo(Customer::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function competitor() { return $this->belongsTo(Competitor::class); }
    public function history() { return $this->hasMany(OpportunityStageHistory::class)->latest('id'); }
    public function tenders() { return $this->hasMany(Tender::class); }

    // Default probability per pipeline stage (prototype: 10..90)
    public const STAGE_PROBABILITY = [10, 20, 35, 45, 55, 65, 75, 80, 90];

    // Last stage change time (falls back to creation) — used for aging / stale attention
    public function lastStageAt()
    {
        $h = $this->relationLoaded('history') ? $this->history->first() : $this->history()->first();
        return $h?->created_at ?? $this->created_at;
    }
}
