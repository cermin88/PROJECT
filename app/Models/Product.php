<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = [];

    public const CATEGORIES = ['GIS', 'Data', 'Infrastructure', 'AI', 'Survey', 'Remote Sensing', 'Security', 'Services', 'Others'];
    public const STATUSES = ['Active', 'Inactive', 'End of Life'];

    public function partner() { return $this->belongsTo(Partner::class); }

    public function ref(): string { return sprintf('PRD-%03d', $this->id); }
}
