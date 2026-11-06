<?php

namespace Database\Seeders\Modules;

use App\Models\Approval;
use App\Models\Contact;
use App\Models\CsaEvent;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Database\Seeder;

// Prototype events (EVT-2026-000001..3) with guest lists, approvals and linked opportunities
class EventsModuleSeeder extends Seeder
{
    public function run(): void
    {
        if (CsaEvent::exists()) {
            return;
        }
        $owner6 = User::where('code', 'USR-006')->first();
        $owner4 = User::where('code', 'USR-004')->first();
        $hod = User::where('role', 'HOD')->first();
        if (! $owner6 || ! $owner4 || ! $hod) {
            return;
        }
        $contacts = Contact::orderBy('id')->get();

        $rows = [
            ['Government Geospatial Innovation Workshop', 'APPROVED', 14, 25000, 0, 80, 0, $owner6, 'Putrajaya International Convention Centre'],
            ['CSA Public Sector Technology Day', 'POST_MORTEM', -40, 40000, 37500, 120, 96, $owner6, 'Kuala Lumpur Convention Centre'],
            ['Utility Intelligence Roundtable', 'DRAFT', 35, 18000, 0, 40, 0, $owner4, 'Cyberjaya'],
        ];
        foreach ($rows as $i => [$name, $status, $days, $budget, $actual, $invited, $attended, $owner, $venue]) {
            $e = CsaEvent::create([
                'code' => sprintf('EVT-2026-%06d', $i + 1), 'name' => $name, 'event_date' => now()->addDays($days)->setTime(9, 0),
                'venue' => $venue, 'objective' => 'Engage public-sector decision makers and generate qualified pipeline.',
                'budget' => $budget, 'actual' => $actual, 'status' => $status, 'owner_id' => $owner->id,
            ]);

            if ($status !== 'DRAFT') {
                $a = Approval::create([
                    'code' => WorkflowService::nextCode('APR', 'approvals'), 'type' => 'Event/Budget',
                    'subject_type' => 'event', 'subject_id' => $e->id, 'requester_id' => $owner->id, 'approver_id' => $hod->id,
                    'status' => 'Approved', 'decided_at' => now()->addDays($days - 20),
                    'snapshot' => ['event' => $e->code, 'name' => $name, 'date' => $e->event_date->format('Y-m-d H:i'), 'venue' => $venue, 'budget' => (float) $budget, 'owner' => $owner->name],
                ]);
                $e->update(['approval_id' => $a->id]);
            }

            // Guest list: customer contacts first, then external guests
            for ($g = 0; $g < $invited; $g++) {
                $c = $contacts[$g] ?? null;
                $st = $attended ? ($g < $attended ? 'Attended' : 'No Show') : ($g % 3 === 0 ? 'Confirmed' : ($g % 7 === 0 ? 'Declined' : 'Invited'));
                $e->invitations()->create([
                    'contact_id' => $c?->id, 'customer_id' => $c?->customer_id,
                    'name' => $c?->name ?? 'Guest ' . ($g + 1), 'email' => $c?->email, 'status' => $st,
                ]);
            }
        }

        // Technology Day sourced pipeline (associated opportunity value)
        $tech = CsaEvent::where('code', 'EVT-2026-000002')->first();
        $tech->opportunities()->attach(Opportunity::orderBy('id')->take(3)->pluck('id'));
    }
}
