<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenderStatusHistory extends Model
{
    protected $table = 'tender_status_history';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['created_at' => 'datetime'];
}
