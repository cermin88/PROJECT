<?php

namespace Database\Seeders\Modules;

use App\Models\AttentionDisposition;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\OpportunityStageHistory;
use App\Models\Tender;
use App\Models\TenderStatusHistory;
use App\Models\User;
use Illuminate\Database\Seeder;

// Demo data for Attention Centre, Contacts, Customer Attention, Forecast and Tender screens
class HomeSalesModuleSeeder extends Seeder
{
    public function run(): void
    {
        // Contact relationship / position intelligence
        $rel = ['Strong', 'Good', 'Warm', 'Cold'];
        $pos = ['Director', 'Deputy Director', 'Senior Officer', 'Procurement Officer'];
        foreach (Contact::orderBy('id')->get() as $i => $c) {
            $c->update(['relationship' => $rel[$i % 4], 'position' => $pos[$i % 4]]);
        }

        // A few opportunities have sat in their stage for weeks -> stale attention
        foreach (['OPP-2026-000003', 'OPP-2026-000007', 'OPP-2026-000011'] as $k => $code) {
            if ($o = Opportunity::where('code', $code)->first()) {
                OpportunityStageHistory::where('opportunity_id', $o->id)->update(['created_at' => now()->subDays(40 + $k * 10)]);
            }
        }

        // A draft tender so the Bid / No-Bid flow can be demoed
        $opp = Opportunity::where('code', 'OPP-2026-000012')->first();
        $owner = User::where('email', 'amir@csa.test')->first();
        if ($opp && ! Tender::where('code', 'TND-2026-DEMO01')->exists()) {
            $t = Tender::create([
                'code' => 'TND-2026-DEMO01', 'title' => $opp->name . ' — Tender', 'customer_id' => $opp->customer_id,
                'opportunity_id' => $opp->id, 'owner_id' => $owner?->id, 'status' => 'DRAFT',
                'closing_at' => now()->addDays(21)->setTime(12, 0), 'risk' => 'Medium',
            ]);
            foreach (\App\Http\Controllers\TenderController::CHECKLIST as $label) {
                $t->checklist()->create(['label' => $label, 'mandatory' => true]);
            }
            TenderStatusHistory::create(['tender_id' => $t->id, 'status' => 'DRAFT', 'changed_by' => $owner?->id]);
        }

        // One acknowledged attention item for the "include dispositioned" view
        $hod = User::where('role', 'HOD')->first();
        $o = Opportunity::where('code', 'OPP-2026-000011')->first();
        if ($o && $hod) {
            AttentionDisposition::firstOrCreate(
                ['item_key' => "opp:{$o->id}:stale"],
                ['title' => "Stale opportunity: {$o->code}", 'disposition' => 'Acknowledged', 'note' => 'Customer budget cycle — revisit next month', 'user_id' => $hod->id],
            );
        }

        // Older engagement for two accounts -> Follow-up Due
        Customer::whereIn('code', ['CUS-000004', 'CUS-000010'])->update(['last_engagement_at' => now()->subDays(75)]);
    }
}
