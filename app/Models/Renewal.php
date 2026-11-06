<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Renewal extends Model
{
    protected $guarded = [];
    protected $casts = ['expiry_date' => 'date'];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function project() { return $this->belongsTo(Project::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function opportunity() { return $this->belongsTo(Opportunity::class); }

    public function scopeVisibleToProjectOwner($query, User $user)
    {
        return $query->where(function ($query) use ($user) {
            $query->whereNull('project_id')
                ->orWhereHas('project', fn ($project) => $project->visibleTo($user));
        });
    }
}
