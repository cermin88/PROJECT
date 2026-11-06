<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $guarded = [];
    protected $casts = ['preferred' => 'boolean', 'archived' => 'boolean'];

    public const CLASSIFICATIONS = ['Principal', 'OEM', 'Distributor', 'Reseller', 'SI', 'Technology Partner',
        'Business Partner', 'Subcontractor', 'Consultant', 'Others'];
    public const AGREEMENT_TYPES = ['Teaming Agreement', 'NDA', 'Reseller Agreement', 'Principal / OEM Appointment',
        'Subcontract Agreement', 'Other'];

    public function products() { return $this->hasMany(Product::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function contacts() { return $this->hasMany(PartnerContact::class); }
    public function agreements() { return $this->hasMany(PartnerAgreement::class)->orderBy('expiry_date'); }
    public function opportunities() { return $this->belongsToMany(Opportunity::class, 'partner_opportunity'); }
    public function tenders() { return $this->belongsToMany(Tender::class, 'partner_tender'); }

    public function scopeActive($q) { return $q->where('archived', false); }

    public function ref(): string { return sprintf('PAR-%06d', $this->id); }

    // "Principal, OEM" is stored as one string; one partner may hold several classifications
    public function classificationList(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->classification))));
    }

    // Same name or registration no. among active partners (excluding $exceptId)
    public static function duplicatesOf(string $name, ?string $regNo, ?int $exceptId = null)
    {
        return static::active()
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->where(function ($q) use ($name, $regNo) {
                $q->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))]);
                if ($regNo) {
                    $q->orWhereRaw('LOWER(reg_no) = ?', [mb_strtolower(trim($regNo))]);
                }
            })->get();
    }
}
