<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Key/value configuration (UX-070)
class AdminSetting extends Model
{
    protected $guarded = [];

    public function editor() { return $this->belongsTo(User::class, 'updated_by'); }

    public static function get(string $key, mixed $default = null): mixed
    {
        $s = static::where('key', $key)->first();
        if (! $s) {
            return $default;
        }
        return match ($s->type) {
            'int' => (int) $s->value,
            'list' => array_values(array_filter(array_map('trim', explode(',', (string) $s->value)), 'strlen')),
            default => $s->value,
        };
    }
}
