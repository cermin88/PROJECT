<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\CsaEvent;
use App\Models\CsaEventInvitation;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function u(string $handle): User
    {
        return User::where('email', "{$handle}@csa.test")->firstOrFail();
    }

    private function draft(User $owner, array $over = []): CsaEvent
    {
        return CsaEvent::create($over + [
            'code' => 'EVT-T-' . uniqid(), 'name' => 'Test Event', 'event_date' => now()->addDays(10),
            'budget' => 10000, 'status' => 'DRAFT', 'owner_id' => $owner->id,
        ]);
    }

    public function test_pages_render_for_all_roles(): void
    {
        $e = CsaEvent::where('code', 'EVT-2026-000002')->first();
        foreach (User::all()->unique('role') as $user) {
            $this->actingAs($user)->get(route('events.index'))->assertOk()->assertSee('EVT-2026-000001');
            $this->actingAs($user)->get(route('events.show', $e))->assertOk();
            $this->actingAs($user)->get(route('events.attendance', ['event' => $e->id]))->assertOk()->assertSee('80%');
            $this->actingAs($user)->get(route('events.postmortem'))->assertOk()->assertSee('CSA Public Sector Technology Day');
        }
        $this->actingAs($this->u('amir'))->get(route('events.index', ['status' => 'DRAFT']))
            ->assertSee('Utility Intelligence Roundtable')->assertDontSee('Government Geospatial');
    }

    public function test_create_and_edit_draft_and_cc_is_denied(): void
    {
        $this->actingAs($this->u('shahirah'))->post(route('events.store'), ['name' => 'X', 'event_date' => '2026-12-01'])->assertForbidden();

        $this->actingAs($this->u('amir'))->post(route('events.store'), ['name' => 'Smart City Forum', 'event_date' => '2026-12-01', 'budget' => 5000])
            ->assertRedirect();
        $e = CsaEvent::where('name', 'Smart City Forum')->firstOrFail();
        $this->assertSame('DRAFT', $e->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'EVENT_CREATED', 'entity_id' => $e->id]);

        $this->actingAs($this->u('amir'))->post(route('events.update', $e), ['name' => 'Smart City Forum 2', 'event_date' => '2026-12-02', 'budget' => 7000])
            ->assertSessionHas('ok');
        $this->assertEquals(7000, (float) $e->fresh()->budget);

        // non-owner BD cannot manage someone else's event; HOD can
        $this->actingAs($this->u('nuzul'))->post(route('events.update', $e), ['name' => 'Y', 'event_date' => '2026-12-02', 'budget' => 1])->assertForbidden();
        $this->actingAs($this->u('fauzi'))->post(route('events.update', $e), ['name' => 'Z', 'event_date' => '2026-12-02', 'budget' => 7000])->assertSessionHas('ok');

        $this->actingAs($this->u('amir'))->post(route('events.store'), ['name' => ''])->assertSessionHasErrors(['name', 'event_date']);
    }

    public function test_budget_approval_flow_with_snapshot_and_no_self_approval(): void
    {
        $owner = $this->u('amir');
        $e = $this->draft($owner);

        $this->actingAs($owner)->post(route('events.submit', $e))->assertSessionHas('ok');
        $e->refresh();
        $this->assertSame('PENDING_APPROVAL', $e->status);
        $a = Approval::findOrFail($e->approval_id);
        $this->assertSame('Event/Budget', $a->type);
        $this->assertSame($this->u('fauzi')->id, $a->approver_id);
        $this->assertEquals(10000, $a->snapshot['budget']);

        // budget frozen while pending
        $this->actingAs($owner)->post(route('events.update', $e), ['name' => 'N', 'event_date' => '2026-12-02', 'budget' => 99999])->assertSessionHas('err');
        $this->assertEquals(10000, (float) $e->fresh()->budget);
        // cannot proceed without approval
        $this->actingAs($owner)->post(route('events.advance', $e))->assertSessionHas('err');

        // only HOD / AHOD may decide from the event page
        $this->actingAs($this->u('stanley'))->post(route('events.decide', $e), ['decision' => 'Approved'])->assertForbidden();
        $this->actingAs($owner)->post(route('events.decide', $e), ['decision' => 'Approved'])->assertForbidden();

        $this->actingAs($this->u('fauzi'))->post(route('events.decide', $e), ['decision' => 'Approved'])->assertSessionHas('ok');
        $this->assertSame('APPROVED', $e->fresh()->status);
        $this->assertSame('Approved', $a->fresh()->status);
    }

    public function test_hod_cannot_self_approve_and_ahod_gets_the_request(): void
    {
        $hod = $this->u('fauzi');
        $ahod = User::create(['code' => 'USR-T1', 'name' => 'AHOD Tester', 'role' => 'AHOD', 'email' => 'ahod@csa.test', 'password' => 'x']);
        $e = $this->draft($hod);

        $this->actingAs($hod)->post(route('events.submit', $e))->assertSessionHas('ok');
        $a = Approval::findOrFail($e->fresh()->approval_id);
        $this->assertSame($ahod->id, $a->approver_id);

        $this->actingAs($hod)->post(route('events.decide', $e), ['decision' => 'Approved'])->assertSessionHas('err');
        $this->assertSame('PENDING_APPROVAL', $e->fresh()->status);

        $this->actingAs($ahod)->post(route('events.decide', $e), ['decision' => 'Rejected'])->assertSessionHasErrors('note');
        $this->actingAs($ahod)->post(route('events.decide', $e), ['decision' => 'Rejected', 'note' => 'Not this quarter'])->assertSessionHas('ok');
        $this->assertSame('REJECTED', $e->fresh()->status);
    }

    public function test_inbox_decision_syncs_back_and_return_reopens_draft(): void
    {
        $owner = $this->u('nurzam');
        $e = $this->draft($owner);
        $this->actingAs($owner)->post(route('events.submit', $e));
        $a = Approval::findOrFail($e->fresh()->approval_id);

        // decided in the existing Action & Approval Inbox
        $this->actingAs($this->u('fauzi'))->post(route('approvals.decide', $a), ['decision' => 'Returned', 'note' => 'Trim catering'])->assertSessionHas('ok');
        $this->actingAs($owner)->get(route('events.show', $e))->assertOk();
        $this->assertSame('DRAFT', $e->fresh()->status);

        // resubmit creates a new immutable snapshot; old one is untouched
        $this->actingAs($owner)->post(route('events.update', $e), ['name' => 'Test Event', 'event_date' => '2026-12-02', 'budget' => 8000]);
        $this->actingAs($owner)->post(route('events.submit', $e))->assertSessionHas('ok');
        $new = Approval::findOrFail($e->fresh()->approval_id);
        $this->assertNotSame($a->id, $new->id);
        $this->assertEquals(10000, $a->fresh()->snapshot['budget']);
        $this->assertEquals(8000, $new->snapshot['budget']);

        $this->actingAs($this->u('fauzi'))->get(route('approvals.index'))->assertSee($new->code);
    }

    public function test_lifecycle_attendance_postmortem_and_close(): void
    {
        $owner = $this->u('amir');
        $e = $this->draft($owner, ['status' => 'APPROVED']);
        $contact = Contact::first();

        $this->actingAs($this->u('shahirah'))->post(route('events.invite', $e), ['name' => 'X'])->assertForbidden();
        $this->actingAs($owner)->post(route('events.invite', $e), ['contact_id' => $contact->id])->assertSessionHas('ok');
        $this->actingAs($owner)->post(route('events.invite', $e), ['contact_id' => $contact->id])->assertSessionHas('err');
        $this->actingAs($owner)->post(route('events.invite', $e), ['name' => 'Guest A', 'email' => 'a@example.com'])->assertSessionHas('ok');
        $inv = CsaEventInvitation::where('csa_event_id', $e->id)->where('contact_id', $contact->id)->first();

        $this->actingAs($owner)->post(route('events.invitations.status', $inv), ['status' => 'Confirmed'])->assertSessionHas('ok');
        $this->actingAs($owner)->post(route('events.invitations.status', $inv), ['status' => 'Attended'])->assertSessionHas('err');

        $this->actingAs($owner)->post(route('events.advance', $e))->assertSessionHas('ok');
        $this->assertSame('ACTIVE/HELD', $e->fresh()->status);
        $this->actingAs($owner)->post(route('events.invitations.status', $inv), ['status' => 'Attended'])->assertSessionHas('ok');
        $this->actingAs($this->u('shahirah'))->post(route('events.invitations.status', $inv), ['status' => 'No Show'])->assertForbidden();
        $this->assertSame(50, $e->fresh()->load('invitations')->attendanceRate());

        // actual cost and ROI via linked opportunities
        $this->actingAs($owner)->post(route('events.actual', $e), ['actual' => 12000])->assertSessionHas('ok', 'Actual cost saved — over approved budget.');
        $opp = Opportunity::first();
        $this->actingAs($owner)->post(route('events.opportunities.link', $e), ['opportunity_id' => $opp->id])->assertSessionHas('ok');
        $this->assertEquals((float) $opp->value, $e->fresh()->load('opportunities')->roiValue());

        // post-mortem only in POST_MORTEM; close requires lessons
        $pm = ['pm_objective_met' => 'Partly', 'pm_lessons' => 'Start invitations earlier', 'pm_follow_up' => 'Call attendees'];
        $this->actingAs($owner)->post(route('events.postmortem.save', $e), $pm)->assertSessionHas('err');
        $this->actingAs($owner)->post(route('events.advance', $e));
        $this->assertSame('POST_MORTEM', $e->fresh()->status);
        $this->actingAs($owner)->post(route('events.advance', $e))->assertSessionHas('err');
        $this->actingAs($this->u('shahirah'))->post(route('events.postmortem.save', $e), $pm)->assertForbidden();
        $this->actingAs($owner)->post(route('events.postmortem.save', $e), $pm)->assertSessionHas('ok');
        $this->actingAs($owner)->post(route('events.advance', $e))->assertSessionHas('ok');
        $this->assertSame('CLOSED', $e->fresh()->status);

        // closed is locked
        $this->actingAs($owner)->post(route('events.postmortem.save', $e), $pm)->assertSessionHas('err');
        $this->actingAs($owner)->post(route('events.invite', $e), ['name' => 'Late'])->assertSessionHas('err');
        $this->actingAs($owner)->post(route('events.actual', $e), ['actual' => 1])->assertSessionHas('err');
        $this->assertDatabaseHas('audit_logs', ['action' => 'EVENT_POSTMORTEM_SAVED', 'entity_id' => $e->id]);
        $this->actingAs($owner)->get(route('events.postmortem'))->assertSee('Start invitations earlier');
    }
}
