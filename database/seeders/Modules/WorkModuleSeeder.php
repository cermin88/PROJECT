<?php

namespace Database\Seeders\Modules;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\MeetingMinute;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Database\Seeder;

// Demo activities / tasks / MoM, shaped like the prototype seed (36 activities, follow-up tasks from next actions)
class WorkModuleSeeder extends Seeder
{
    public function run(): void
    {
        if (Activity::exists()) {
            return; // already seeded
        }
        $u = fn (string $handle) => User::where('email', "{$handle}@csa.test")->first();
        $owners = array_values(array_filter([$u('amir'), $u('asyafiq'), $u('nuzul'), $u('nurzam')]));
        $hod = $u('fauzi');
        $customers = Customer::orderBy('code')->get();
        if (! $owners || $customers->isEmpty()) {
            return;
        }

        $taskNo = 0;
        foreach (range(0, 35) as $i) {
            $c = $customers[$i % $customers->count()];
            $opp = Opportunity::where('customer_id', $c->id)->orderBy('code')->first();
            $status = $i % 5 === 0 ? 'PLANNED' : ($i % 7 === 0 ? 'CANCELLED' : 'COMPLETED');
            $type = Activity::TYPES[$i % count(Activity::TYPES)];
            $when = $status === 'PLANNED' ? now()->addDays($i % 9)->setTime(10, 0) : now()->subDays($i * 3 + 1)->setTime(10 + $i % 6, 0);
            $a = Activity::create([
                'code' => sprintf('ACT-2026-%06d', $i + 1), 'type' => $type, 'subject' => "{$type} — {$c->name}",
                'customer_id' => $c->id, 'opportunity_id' => $i < 20 ? $opp?->id : null,
                'owner_id' => $owners[$i % count($owners)]->id, 'status' => $status, 'activity_at' => $when,
                'outcome' => $status === 'COMPLETED' ? 'Engagement completed; follow-up recorded' : null,
                'next_action' => $i % 4 === 0 ? 'Send follow-up and confirm next meeting' : null,
                'next_action_date' => $i % 4 === 0 ? today()->addDays(($i % 9) - 3) : null,
            ]);
            if ($a->next_action) {
                Task::create([
                    'code' => sprintf('TSK-2026-%06d', ++$taskNo), 'title' => $a->next_action, 'assignee_id' => $a->owner_id,
                    'created_by' => $a->owner_id, 'due_date' => $a->next_action_date, 'status' => $taskNo % 3 === 0 ? 'In Progress' : 'Not Started',
                    'customer_id' => $a->customer_id, 'opportunity_id' => $a->opportunity_id,
                    'source_activity_id' => $status === 'COMPLETED' ? $a->id : null,
                ]);
            }
        }

        // A tender-prep task handed out by the HOD
        if ($hod && ($t = Tender::whereIn('status', ['PREPARATION', 'READY_FOR_SUBMISSION'])->orderBy('closing_at')->first())) {
            Task::create([
                'code' => sprintf('TSK-2026-%06d', ++$taskNo), 'title' => "Complete compliance documents for {$t->code}",
                'assignee_id' => $t->owner_id ?? $owners[0]->id, 'created_by' => $hod->id, 'due_date' => today()->addDays(2),
                'status' => 'Not Started', 'customer_id' => $t->customer_id, 'tender_id' => $t->id,
            ]);
        }

        // MoM: one approved (with its action task), one pending HOD approval
        $c = $customers->first();
        $m1 = MeetingMinute::create([
            'code' => 'MOM-2026-000001', 'title' => 'Customer Technical Workshop', 'meeting_at' => now()->subDays(6)->setTime(14, 30),
            'location' => 'Customer HQ', 'customer_id' => $c->id, 'author_id' => $owners[0]->id, 'status' => 'APPROVED',
            'summary' => 'Walked through current geospatial platform pain points and integration needs.',
            'decisions' => "Proceed with a scoped POC.\nCustomer to share sample datasets.",
            'submitted_at' => now()->subDays(5), 'decided_by' => $hod?->id, 'decided_at' => now()->subDays(4),
        ]);
        $m1->attendees()->createMany([
            ['user_id' => $owners[0]->id, 'name' => $owners[0]->name, 'organisation' => 'CSA'],
            ['name' => 'Puan Rohana', 'organisation' => $c->name],
        ]);
        $item = $m1->actionItems()->create(['description' => 'Prepare POC scope and timeline', 'assignee_id' => $owners[0]->id, 'due_date' => today()->addDays(3)]);
        Task::create([
            'code' => sprintf('TSK-2026-%06d', ++$taskNo), 'title' => 'MoM Action: ' . $item->description, 'assignee_id' => $item->assignee_id,
            'created_by' => $owners[0]->id, 'due_date' => $item->due_date, 'status' => 'Not Started', 'customer_id' => $c->id,
            'meeting_action_item_id' => $item->id,
        ]);

        $c2 = $customers[1] ?? $c;
        $m2 = MeetingMinute::create([
            'code' => 'MOM-2026-000002', 'title' => 'Tender clarification briefing', 'meeting_at' => now()->subDays(2)->setTime(10, 0),
            'location' => 'Online', 'customer_id' => $c2->id, 'author_id' => $owners[1 % count($owners)]->id, 'status' => 'PENDING_APPROVAL',
            'summary' => 'Clarified submission format and evaluation weighting.', 'decisions' => 'Submit clarification queries by Friday.',
            'submitted_at' => now()->subDay(),
        ]);
        $m2->attendees()->create(['user_id' => $m2->author_id, 'name' => $owners[1 % count($owners)]->name, 'organisation' => 'CSA']);
        $m2->actionItems()->create(['description' => 'Send clarification queries', 'assignee_id' => $m2->author_id, 'due_date' => today()->addDays(2)]);
    }
}
