<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['detail' => 'array', 'created_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }

    public static function record(string $action, ?string $entityType = null, ?int $entityId = null, array $detail = []): void
    {
        static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'detail' => $detail ?: null,
            'ip' => request()->ip(),
        ]);
    }
}
