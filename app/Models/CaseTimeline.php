<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseTimeline extends Model
{
    protected $table = 'case_timeline';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['created_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
}
