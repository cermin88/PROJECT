<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerAgreement extends Model
{
    protected $guarded = [];
    protected $casts = ['expiry_date' => 'date'];

    public const STATUSES = ['Active', 'Draft', 'Expired'];

    public function partner() { return $this->belongsTo(Partner::class); }

    // Active agreements expiring within 90 days need renewal attention
    public function expiringSoon(): bool
    {
        return $this->status === 'Active' && $this->expiry_date && $this->expiry_date->lte(now()->addDays(90));
    }
}
