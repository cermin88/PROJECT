<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Ordered picklist values (UX-073). Values are deactivated, never deleted, to protect history.
class MasterDataValue extends Model
{
    protected $guarded = [];
    protected $casts = ['active' => 'boolean'];

    public const SETS = ['CustomerType', 'Sector', 'Relationship', 'CasePriority', 'ActivityType', 'PartnerClass'];

    // Where a set is referenced by existing records: set => [table, column]
    public const USAGE = [
        'CustomerType' => ['customers', 'type'],
        'Sector' => ['customers', 'sector'],
        'Relationship' => ['customers', 'relationship'],
        'CasePriority' => ['cases', 'priority'],
        'PartnerClass' => ['partners', 'classification'],
    ];

    public static function options(string $set): array
    {
        return static::where('set', $set)->where('active', true)->orderBy('sort')->pluck('value')->all();
    }
}
