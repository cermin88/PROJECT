<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Award extends Model
{
    protected $guarded = [];
    protected $casts = ['value' => 'decimal:2', 'award_date' => 'date', 'revenue_qualified' => 'boolean'];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function opportunity() { return $this->belongsTo(Opportunity::class); }
    public function tender() { return $this->belongsTo(Tender::class); }
    public function evidence() { return $this->hasMany(AwardEvidence::class); }
    public function project() { return $this->hasOne(Project::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }

    public function scopeVisibleToProjectOwner($query, User $user)
    {
        return $query->where(function ($query) use ($user) {
            $query->whereDoesntHave('project')
                ->orWhereHas('project', fn ($project) => $project->visibleTo($user));
        });
    }

    // Official CSA revenue = qualified Awards only
    public function scopeQualified($q) { return $q->where('revenue_qualified', true); }

    // An unqualified Award contributes RM0
    public function revenueContribution(): float
    {
        return $this->revenue_qualified ? (float) $this->value : 0.0;
    }
}
