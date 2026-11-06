<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $guarded = [];
    protected $casts = ['archived' => 'boolean', 'last_engagement_at' => 'datetime'];

    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function contacts() { return $this->hasMany(Contact::class); }
    public function opportunities() { return $this->hasMany(Opportunity::class); }
    public function tenders() { return $this->hasMany(Tender::class); }
    public function awards() { return $this->hasMany(Award::class); }
    public function cases() { return $this->hasMany(SupportCase::class); }

    public function scopeActive($q) { return $q->where('archived', false); }

    public function daysSinceEngagement(): ?int
    {
        return $this->last_engagement_at ? (int) $this->last_engagement_at->diffInDays(now()) : null;
    }
}
