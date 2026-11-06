<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GovernanceImport extends Model
{
    protected $guarded = [];
    protected $casts = ['headers' => 'array', 'mapping' => 'array', 'preview' => 'array', 'committed_at' => 'datetime'];

    // Target fields per entity; '*' marks required
    public const FIELDS = [
        'customers' => ['name*' => 'Organisation Name', 'type' => 'Type', 'sector' => 'Sector', 'state' => 'State', 'owner_email' => 'Owner Email'],
        'contacts' => ['customer*' => 'Customer Code or Name', 'name*' => 'Contact Name', 'email' => 'Email', 'mobile' => 'Mobile', 'buying_role' => 'Buying Role', 'division' => 'Division'],
    ];

    public function user() { return $this->belongsTo(User::class); }

    public function fields(): array
    {
        $out = [];
        foreach (self::FIELDS[$this->entity] as $k => $label) {
            $out[rtrim($k, '*')] = ['label' => $label, 'required' => str_ends_with($k, '*')];
        }
        return $out;
    }

    public function counts(): array
    {
        $c = ['Valid' => 0, 'Warning' => 0, 'Duplicate' => 0, 'Error' => 0];
        foreach ($this->preview ?? [] as $r) {
            $c[$r['status']]++;
        }
        return $c;
    }
}
