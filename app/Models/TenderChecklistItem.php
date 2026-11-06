<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenderChecklistItem extends Model
{
    protected $guarded = [];
    protected $casts = ['mandatory' => 'boolean', 'done' => 'boolean'];

    public function tender() { return $this->belongsTo(Tender::class); }
}
