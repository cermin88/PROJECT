<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomReportRun extends Model
{
    protected $guarded = [];
    protected $casts = ['definition' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
}
