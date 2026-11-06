<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpportunityStageHistory extends Model
{
    protected $table = 'opportunity_stage_history';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['created_at' => 'datetime'];
}
