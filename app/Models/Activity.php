<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $guarded = [];
    protected $casts = ['activity_at' => 'datetime', 'next_action_date' => 'date'];

    public const TYPES = [
        'Call', 'WhatsApp', 'Email', 'Physical Meeting', 'Online Meeting', 'Courtesy Visit', 'Presentation', 'Demo',
        'POC', 'Workshop', 'Site Visit', 'Networking', 'Lunch', 'Tender Briefing', 'Event', 'Internal Meeting', 'Others',
    ];
    public const STATUSES = ['PLANNED', 'COMPLETED', 'CANCELLED'];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function opportunity() { return $this->belongsTo(Opportunity::class); }
    public function tender() { return $this->belongsTo(Tender::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function followUp() { return $this->hasOne(Task::class, 'source_activity_id'); }

    // Only COMPLETED activities count towards the activity KPI
    public function qualifiesForKpi(): bool { return $this->status === 'COMPLETED'; }
}
