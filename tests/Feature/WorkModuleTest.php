<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\MeetingMinute;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkModuleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function user(string $handle): User
    {
        return User::where('email', "{$handle}@csa.test")->firstOrFail();
    }

    private function ahod(): User
    {
        return User::create(['code' => 'USR-T01', 'name' => 'Test AHOD', 'email' => 'ahod@csa.test', 'password' => 'x', 'role' => 'AHOD']);
    }

    public function test_pages_render_for_every_role(): void
    {
        $m = MeetingMinute::first();
        foreach (User::all()->unique('role') as $u) {
            foreach (['mywork', 'activities.index', 'tasks.index', 'calendar', 'mom.index', 'mom.create'] as $r) {
                $this->actingAs($u)->get(route($r))->assertOk();
            }
        }
        $this->actingAs($this->user('fauzi'))->get(route('mom.show', $m))->assertOk()->assertSee($m->title);
        $this->actingAs($this->user('fauzi'))->get(route('calendar', ['month' => '2026-11']))->assertOk()->assertSee('November 2026');
        $this->actingAs($this->user('fauzi'))->get(route('calendar', ['month' => 'bogus']))->assertOk();
    }

    public function test_scope_hides_other_peoples_work(): void
    {
        $amir = $this->user('amir');
        $cc = $this->user('shahirah');
        $task = Task::where('assignee_id', $amir->id)->first();
        $activity = Activity::where('owner_id', $amir->id)->where('status', 'PLANNED')->first();

        $this->actingAs($cc)->get(route('tasks.index', ['status' => 'all']))->assertOk()->assertDontSee($task->code);
        $this->actingAs($this->user('fauzi'))->get(route('tasks.index', ['status' => 'all']))->assertSee($task->code);
        $this->actingAs($cc)->get(route('tasks.edit', $task))->assertForbidden();
        $this->actingAs($cc)->post(route('tasks.advance', $task))->assertForbidden();
        $this->actingAs($cc)->get(route('activities.edit', $activity))->assertForbidden();
        $this->actingAs($cc)->post(route('activities.status', $activity), ['status' => 'CANCELLED'])->assertForbidden();
        $this->actingAs($cc)->get(route('mom.show', MeetingMinute::first()))->assertForbidden();
    }

    public function test_completed_activity_creates_single_follow_up_and_updates_engagement(): void
    {
        $amir = $this->user('amir');
        $c = Customer::orderBy('code')->first();
        $c->update(['last_engagement_at' => now()->subYear()]);

        $this->actingAs($amir)->post(route('activities.store'), [
            'type' => 'Courtesy Visit', 'subject' => 'Visit CIO', 'customer_id' => $c->id,
            'activity_at' => now()->subHour()->format('Y-m-d H:i'), 'status' => 'COMPLETED',
            'next_action' => 'Send proposal deck', 'next_action_date' => today()->addDays(2)->toDateString(),
        ])->assertRedirect(route('activities.index'));

        $a = Activity::where('subject', 'Visit CIO')->firstOrFail();
        $this->assertSame(1, Task::where('source_activity_id', $a->id)->count());
        $this->assertSame($amir->id, $a->followUp->assignee_id);
        $this->assertTrue($c->fresh()->last_engagement_at->gt(now()->subDay()));
        $this->assertDatabaseHas('audit_logs', ['action' => 'ACTIVITY_CREATED', 'entity_id' => $a->id]);

        // Completed activities are locked
        $this->actingAs($amir)->post(route('activities.status', $a), ['status' => 'CANCELLED'])->assertSessionHas('err');
        $this->actingAs($amir)->post(route('activities.update', $a), ['type' => 'Call', 'subject' => 'x', 'customer_id' => $c->id, 'activity_at' => now()->subDay()->toDateString()])->assertSessionHas('err');
    }

    public function test_activity_business_rules(): void
    {
        $amir = $this->user('amir');
        $c = Customer::orderBy('code')->first();
        $otherOpp = Opportunity::where('customer_id', '!=', $c->id)->first();

        // Linked opportunity must belong to the customer
        $this->actingAs($amir)->post(route('activities.store'), [
            'type' => 'Call', 'subject' => 'Bad link', 'customer_id' => $c->id, 'opportunity_id' => $otherOpp->id,
            'activity_at' => now()->subHour()->toDateTimeString(), 'status' => 'PLANNED',
        ])->assertSessionHasErrors('opportunity_id');
        // Unknown type rejected
        $this->actingAs($amir)->post(route('activities.store'), ['type' => 'Golf', 'subject' => 'x', 'customer_id' => $c->id, 'activity_at' => now()->toDateTimeString(), 'status' => 'PLANNED'])
            ->assertSessionHasErrors('type');
        // Future activity cannot be completed
        $this->actingAs($amir)->post(route('activities.store'), ['type' => 'Call', 'subject' => 'Future', 'customer_id' => $c->id, 'activity_at' => now()->addDays(3)->toDateTimeString(), 'status' => 'COMPLETED'])
            ->assertSessionHasErrors('status');

        // Plan, then complete (outcome required) — status transition
        $this->actingAs($amir)->post(route('activities.store'), ['type' => 'Demo', 'subject' => 'Demo GIS', 'customer_id' => $c->id, 'activity_at' => now()->subMinutes(5)->toDateTimeString(), 'status' => 'PLANNED']);
        $a = Activity::where('subject', 'Demo GIS')->firstOrFail();
        $this->actingAs($amir)->post(route('activities.status', $a), ['status' => 'COMPLETED'])->assertSessionHasErrors('outcome');
        $this->actingAs($amir)->post(route('activities.status', $a), ['status' => 'COMPLETED', 'outcome' => 'Good demo'])->assertSessionHas('ok');
        $this->assertSame('COMPLETED', $a->fresh()->status);
        $this->assertTrue($a->fresh()->qualifiesForKpi());
    }

    public function test_task_lifecycle_and_assignment_rules(): void
    {
        $amir = $this->user('amir');      // BD Manager: may assign others
        $exec = $this->user('shahirah');  // CC Executive: self only

        $this->actingAs($exec)->post(route('tasks.store'), ['title' => 'For Amir', 'assignee_id' => $amir->id, 'due_date' => today()->toDateString()])->assertForbidden();
        $this->actingAs($exec)->post(route('tasks.store'), ['title' => 'My own', 'due_date' => today()->toDateString()])->assertSessionHas('ok');
        $this->assertSame($exec->id, Task::where('title', 'My own')->value('assignee_id'));

        $this->actingAs($amir)->post(route('tasks.store'), ['title' => 'For exec', 'assignee_id' => $exec->id, 'due_date' => today()->subDay()->toDateString()])->assertSessionHas('ok');
        $t = Task::where('title', 'For exec')->firstOrFail();
        $this->assertTrue($t->isOverdue());
        $this->assertSame(1, Task::overdue()->whereKey($t->id)->count());

        $this->actingAs($exec)->post(route('tasks.advance', $t))->assertSessionHas('ok');
        $this->assertSame('In Progress', $t->fresh()->status);
        $this->actingAs($exec)->post(route('tasks.advance', $t));
        $this->assertSame('Completed', $t->fresh()->status);
        $this->assertNotNull($t->fresh()->completed_at);
        $this->assertFalse($t->fresh()->isOverdue());
        $this->actingAs($exec)->post(route('tasks.advance', $t))->assertSessionHas('err');
        $this->actingAs($exec)->post(route('tasks.update', $t), ['title' => 'x', 'due_date' => today()->toDateString()])->assertSessionHas('err');

        $own = Task::where('title', 'My own')->first();
        $this->actingAs($exec)->post(route('tasks.cancel', $own), [])->assertSessionHasErrors('reason');
        $this->actingAs($exec)->post(route('tasks.cancel', $own), ['reason' => 'Duplicate'])->assertSessionHas('ok');
        $this->assertSame('Cancelled', $own->fresh()->status);

        $this->actingAs($amir)->get(route('tasks.edit', $t))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'TASK_STATUS', 'entity_id' => $t->id]);
    }

    public function test_mom_workflow_with_action_items_spawning_tasks(): void
    {
        $amir = $this->user('amir');
        $hod = $this->user('fauzi');
        $exec = $this->user('shahirah');
        $c = Customer::orderBy('code')->first();

        $this->actingAs($amir)->post(route('mom.store'), [
            'title' => 'Quarterly review', 'meeting_at' => now()->subDay()->toDateTimeString(), 'customer_id' => $c->id,
            'attendee_ids' => [$amir->id, $exec->id], 'external' => "Encik Ali, {$c->name}\nPuan Siti",
            'actions' => [
                ['description' => 'Send pricing', 'assignee_id' => $amir->id, 'due_date' => today()->addDays(3)->toDateString()],
                ['description' => 'Log support ticket', 'assignee_id' => $exec->id, 'due_date' => today()->addDays(1)->toDateString()],
                ['description' => '', 'assignee_id' => $amir->id, 'due_date' => ''],
            ],
        ])->assertRedirect();
        $m = MeetingMinute::where('title', 'Quarterly review')->firstOrFail();
        $this->assertSame('DRAFT', $m->status);
        $this->assertSame(4, $m->attendees()->count());
        $this->assertSame(2, $m->actionItems()->count());

        // Attendee can view; non-attendee cannot; only author edits
        $this->actingAs($exec)->get(route('mom.show', $m))->assertOk();
        $this->actingAs($this->user('irfan'))->get(route('mom.show', $m))->assertForbidden();
        $this->actingAs($exec)->get(route('mom.edit', $m))->assertForbidden();
        $this->actingAs($amir)->get(route('mom.edit', $m))->assertOk();

        // Only HOD/AHOD decide, and only once pending
        $this->actingAs($amir)->post(route('mom.submit', $m))->assertSessionHas('ok');
        $this->assertSame('PENDING_APPROVAL', $m->fresh()->status);
        $this->actingAs($amir)->get(route('mom.edit', $m))->assertForbidden();
        $this->actingAs($exec)->post(route('mom.decide', $m), ['decision' => 'APPROVED'])->assertForbidden();
        $this->actingAs($this->user('stanley'))->post(route('mom.decide', $m), ['decision' => 'APPROVED'])->assertForbidden();

        // Return needs a comment, goes back to author, then resubmit
        $this->actingAs($hod)->post(route('mom.decide', $m), ['decision' => 'RETURNED'])->assertSessionHasErrors('note');
        $this->actingAs($hod)->post(route('mom.decide', $m), ['decision' => 'RETURNED', 'note' => 'Add decisions'])->assertSessionHas('ok');
        $this->assertSame('RETURNED', $m->fresh()->status);
        $this->assertSame(0, Task::whereNotNull('meeting_action_item_id')->whereIn('meeting_action_item_id', $m->actionItems()->pluck('id'))->count());
        $this->actingAs($amir)->post(route('mom.update', $m), ['title' => 'Quarterly review', 'meeting_at' => now()->subDay()->toDateTimeString(),
            'decisions' => 'Proceed', 'attendee_ids' => [$amir->id, $exec->id]])->assertRedirect();
        $this->actingAs($amir)->post(route('mom.submit', $m));

        // Approval spawns one task per action item, assigned to the item owner
        $this->actingAs($hod)->post(route('mom.decide', $m), ['decision' => 'APPROVED'])->assertSessionHas('ok');
        $m->refresh();
        $this->assertSame('APPROVED', $m->status);
        $this->assertSame($hod->id, $m->decided_by);
        $items = $m->actionItems()->with('task')->get();
        $this->assertTrue($items->every(fn ($i) => $i->task && $i->task->assignee_id === $i->assignee_id));
        $this->assertSame('Proceed', $m->decisions);

        // No double decision, no duplicate task, locked after approval
        $this->actingAs($hod)->post(route('mom.decide', $m), ['decision' => 'APPROVED'])->assertSessionHas('err');
        $this->actingAs($amir)->post(route('mom.actions.task', [$m, $items[0]]))->assertSessionHas('ok');
        $this->assertSame(1, Task::where('meeting_action_item_id', $items[0]->id)->count());
        $this->actingAs($amir)->post(route('mom.actions.store', $m), ['description' => 'Late item', 'assignee_id' => $amir->id, 'due_date' => today()->toDateString()])->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'MOM_APPROVED', 'entity_id' => $m->id]);
    }

    public function test_mom_self_approval_blocked_and_ahod_can_approve(): void
    {
        $hod = $this->user('fauzi');
        $ahod = $this->ahod();
        $this->actingAs($hod)->post(route('mom.store'), ['title' => 'HOD sync', 'meeting_at' => now()->toDateTimeString(), 'attendee_ids' => [$hod->id]]);
        $m = MeetingMinute::where('title', 'HOD sync')->firstOrFail();

        $this->actingAs($hod)->post(route('mom.submit', $m));
        $this->actingAs($hod)->post(route('mom.decide', $m), ['decision' => 'APPROVED'])->assertSessionHas('err');
        $this->assertSame('PENDING_APPROVAL', $m->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'SELF_APPROVAL_BLOCKED', 'entity_id' => $m->id]);

        $this->actingAs($ahod)->post(route('mom.decide', $m), ['decision' => 'APPROVED'])->assertSessionHas('ok');
        $this->assertSame('APPROVED', $m->fresh()->status);
    }

    public function test_manual_spawn_rules_and_submit_needs_attendee(): void
    {
        $amir = $this->user('amir');
        $exec = $this->user('shahirah');
        $this->actingAs($exec)->post(route('mom.store'), ['title' => 'CC huddle', 'meeting_at' => now()->toDateTimeString(),
            'actions' => [['description' => 'Amir to call', 'assignee_id' => $amir->id, 'due_date' => today()->toDateString()],
                ['description' => 'I will update KB', 'assignee_id' => $exec->id, 'due_date' => today()->toDateString()]]]);
        $m = MeetingMinute::where('title', 'CC huddle')->firstOrFail();
        [$forAmir, $forMe] = $m->actionItems()->orderBy('id')->get()->all();

        $this->actingAs($exec)->post(route('mom.submit', $m))->assertSessionHas('err');
        $this->actingAs($exec)->post(route('mom.actions.task', [$m, $forAmir]))->assertForbidden();
        $this->actingAs($exec)->post(route('mom.actions.task', [$m, $forMe]))->assertSessionHas('ok');
        $this->assertNotNull($forMe->task()->first());
    }

    public function test_my_work_and_calendar_show_items(): void
    {
        $amir = $this->user('amir');
        $this->actingAs($amir)->post(route('tasks.store'), ['title' => 'Agenda item today', 'due_date' => today()->toDateString()]);
        $this->actingAs($amir)->get(route('mywork'))->assertOk()->assertSee('Agenda item today');
        $this->actingAs($amir)->get(route('calendar', ['month' => today()->format('Y-m')]))->assertOk()->assertSee('Agenda item today');
        $this->actingAs($amir)->get(route('calendar', ['month' => today()->format('Y-m'), 'show' => ['tender']]))->assertOk()->assertDontSee('Agenda item today');
    }
}
