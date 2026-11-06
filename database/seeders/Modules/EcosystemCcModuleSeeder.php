<?php

namespace Database\Seeders\Modules;

use App\Models\Competitor;
use App\Models\Opportunity;
use App\Models\Partner;
use App\Models\SupportCase;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Database\Seeder;

// Prototype seed for partner profiles/contacts/agreements/links, competitor values and case resolution times
class EcosystemCcModuleSeeder extends Seeder
{
    public function run(): void
    {
        $owners = User::whereIn('email', ['amir@csa.test', 'asyafiq@csa.test', 'nuzul@csa.test'])->get()->keyBy('email');
        $ownerOrder = ['amir@csa.test', 'asyafiq@csa.test', 'nuzul@csa.test'];
        $partners = Partner::orderBy('id')->get();

        foreach ($partners as $i => $p) {
            $p->update([
                'reg_no' => $p->reg_no ?? 'REG-' . (8000 + $i),
                'owner_id' => $p->owner_id ?? $owners->get($ownerOrder[$i % 3])?->id,
            ]);
            $p->contacts()->firstOrCreate(['name' => 'Partner Contact ' . ($i + 1)], [
                'position' => 'Account / Alliance Manager',
                'email' => 'partner' . ($i + 1) . '@example.com',
                'mobile' => '03-88' . (1000 + $i),
            ]);
            if ($i < 3) {
                $p->agreements()->firstOrCreate(['type' => 'Teaming Agreement'], [
                    'status' => 'Active', 'expiry_date' => now()->addDays(180 + $i * 45)->toDateString(),
                ]);
            }
        }

        // Prototype: opportunity i and tender i are associated with partner i % 8
        if ($partners->isNotEmpty()) {
            foreach (Opportunity::orderBy('id')->limit(20)->get() as $i => $o) {
                $partners[$i % $partners->count()]->opportunities()->syncWithoutDetaching([$o->id]);
            }
            foreach (Tender::orderBy('id')->limit(11)->get() as $i => $t) {
                $partners[$i % $partners->count()]->tenders()->syncWithoutDetaching([$t->id]);
            }
        }

        foreach (Competitor::orderBy('id')->get() as $i => $c) {
            if ($i % 2 && $c->known_winning_value === null) {
                $c->update(['known_winning_value' => 850000 + $i * 100000]);
            }
        }

        // Stop the resolution clock on already resolved/closed cases
        foreach (SupportCase::whereIn('status', SupportCase::DONE)->whereNull('resolved_at')->get() as $c) {
            $c->update(['resolved_at' => $c->confirmed_at ?? $c->opened_at->copy()->addHours(max(1, $c->sla_hours - 2))]);
        }
    }
}
