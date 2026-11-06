<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tender extends Model
{
    protected $guarded = [];
    protected $casts = [
        'closing_at' => 'datetime', 'submitted_at' => 'datetime',
        'pricing_approved' => 'boolean', 'cost' => 'decimal:2', 'price' => 'decimal:2',
    ];

    public const STATES = [
        'DRAFT', 'BID_ASSESSMENT', 'NO_BID', 'PREPARATION', 'READY_FOR_SUBMISSION', 'SUBMISSION_APPROVAL',
        'SUBMITTED', 'TECHNICAL_EVALUATION', 'COMMERCIAL_EVALUATION', 'SHORTLISTED', 'AWARDED', 'LOST', 'CANCELLED',
    ];
    // Awarded + Lost only form the win-rate denominator
    public const DECIDED = ['AWARDED', 'LOST'];
    // States from which a tender may still be submitted
    public const SUBMITTABLE = ['PREPARATION', 'READY_FOR_SUBMISSION', 'SUBMISSION_APPROVAL'];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function opportunity() { return $this->belongsTo(Opportunity::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function checklist() { return $this->hasMany(TenderChecklistItem::class); }
    public function history() { return $this->hasMany(TenderStatusHistory::class)->latest('id'); }
    public function retenderOf() { return $this->belongsTo(Tender::class, 'retender_of_id'); }
    public function retenders() { return $this->hasMany(Tender::class, 'retender_of_id'); }
    public function awards() { return $this->hasMany(Award::class); }

    public const FINAL = ['AWARDED', 'LOST', 'CANCELLED'];
    // Outcome states reachable only after a valid submission
    public const POST_SUBMISSION = ['SUBMITTED', 'TECHNICAL_EVALUATION', 'COMMERCIAL_EVALUATION', 'SHORTLISTED', 'AWARDED', 'LOST', 'CANCELLED'];

    // Still in play (not final, not a No-Bid)
    public function scopeOpen($q) { return $q->whereNotIn('status', array_merge(self::FINAL, ['NO_BID']))->whereNull('archived_at'); }

    // Prototype readiness: 70% mandatory checklist + 20% pricing approved + 10% at/after submission approval
    public function readiness(): int
    {
        $chk = $this->checklist->where('mandatory', true);
        $base = $chk->count() ? $chk->where('done', true)->count() / $chk->count() * 70 : 0;
        $stage = array_search($this->status, self::STATES, true) >= array_search('SUBMISSION_APPROVAL', self::STATES, true) ? 10 : 0;
        return (int) round($base + ($this->pricing_approved ? 20 : 0) + $stage);
    }

    public function margin(): float
    {
        return $this->price > 0 ? round((($this->price - $this->cost) / $this->price) * 100, 1) : 0.0;
    }
}
