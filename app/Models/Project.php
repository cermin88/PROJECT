<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $guarded = [];

    public function scopeVisibleTo($query, User $user)
    {
        return $user->role === 'Superadmin'
            ? $query
            : $query->where('owner_id', $user->id);
    }

    public function award() { return $this->belongsTo(Award::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function milestones() { return $this->hasMany(ProjectMilestone::class); }

    // Weighted completion across milestones (weights total 100)
    public function progress(): int
    {
        return (int) round($this->milestones->sum(fn ($m) => $m->weight * $m->progress / 100));
    }
}
