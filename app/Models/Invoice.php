<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $guarded = [];
    protected $casts = ['amount' => 'decimal:2', 'paid' => 'decimal:2', 'invoice_date' => 'date', 'due_date' => 'date'];

    public function award() { return $this->belongsTo(Award::class); }
    public function project() { return $this->belongsTo(Project::class); }

    public function scopeVisibleToProjectOwner($query, User $user)
    {
        return $query->where(function ($query) use ($user) {
            $query->whereHas('project', fn ($project) => $project->visibleTo($user))
                ->orWhereHas('award.project', fn ($project) => $project->visibleTo($user))
                ->orWhere(function ($unlinked) {
                    $unlinked->whereNull('project_id')->whereDoesntHave('award.project');
                });
        });
    }

    public function outstanding(): float { return (float) $this->amount - (float) $this->paid; }
    public function isOverdue(): bool { return $this->outstanding() > 0 && $this->due_date->isPast(); }
}
