<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalType extends Model
{
    protected $guarded = [];
    protected $casts = ['active' => 'boolean'];
}
