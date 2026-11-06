<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Competitor extends Model
{
    protected $guarded = [];
    protected $casts = ['known_winning_value' => 'decimal:2'];

    public function opportunities() { return $this->hasMany(Opportunity::class); }

    public function ref(): string { return sprintf('CMP-%03d', $this->id); }

    // Share of decided head-to-heads we won
    public function winRate(): ?float
    {
        $d = $this->won_against + $this->lost_against;
        return $d > 0 ? round($this->won_against / $d * 100, 1) : null;
    }
}
