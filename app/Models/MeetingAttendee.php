<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingAttendee extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    public function user() { return $this->belongsTo(User::class); }
}
