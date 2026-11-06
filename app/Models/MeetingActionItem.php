<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingActionItem extends Model
{
    protected $guarded = [];
    protected $casts = ['due_date' => 'date'];

    public function minute() { return $this->belongsTo(MeetingMinute::class, 'meeting_minute_id'); }
    public function assignee() { return $this->belongsTo(User::class, 'assignee_id'); }
    public function task() { return $this->hasOne(Task::class, 'meeting_action_item_id'); }
}
