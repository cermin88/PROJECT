<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CsaEventInvitation extends Model
{
    protected $table = 'csa_event_invitations';
    protected $guarded = [];

    public function event() { return $this->belongsTo(CsaEvent::class, 'csa_event_id'); }
    public function contact() { return $this->belongsTo(Contact::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
}
