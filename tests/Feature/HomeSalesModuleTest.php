<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\AttentionDisposition;
use App\Models\AuditLog;
use App\Models\Award;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeSalesModuleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function role(string $role): User
    {
        return User::where('role', $role)->firstOrFail();
    }

    private function bd(): User
    {
        return User::where('email', 'amir@csa.test')->firstOrFail();
    }

    public function test_pages_render_for_each_role(): void
    {
        $opp = Opportunity::first();
        foreach (User::all()->unique('role') as $u) {
            $customer = \App\Http\Controllers\Sales\SalesRules::canViewAllCustomers($u)
                ? Customer::firstOrFail()
                : Customer::where('owner_id', $u->id)->firstOrFail();
            foreach (['attention', 'contacts.index', 'customers.attention', 'tenders.calendar', 'tenders.warroom', 'customers.index', 'opportunities.index', 'pipeline', 'tenders.index'] as $r) {
                $this->actingAs($u)->get(route($r))->assertOk();
            }
            foreach (\App\Http\Controllers\CustomerController::TABS as $tab) {
                $this->actingAs($u)->get(route('customers.show', [$customer, 'tab' => $tab]))->assertOk();
            }
            foreach (\App\Http\Controllers\OpportunityController::TABS as $tab) {
                $this->actingAs($u)->get(route('opportunities.show', [$opp, 'tab' => $tab]))->assertOk();
            }
            $this->actingAs($u)->get(route('tenders.show', Tender::where('code', 'TND-2026-DEMO01')->first()))->assertOk();
        }
        $this->actingAs($this->bd())->get(route('tenders.calendar', ['view' => 'all']))->assertOk()->assertSee('Malaysia Time');
        $this->actingAs($this->bd())->get(route('tenders.warroom', ['filter' => 'blocked']))->assertOk()->assertSee('TND-2026-000001');
    }

    public function test_forecast_is_weighted_and_hidden_from_cc_executive(): void
    {
        $this->actingAs($this->role('HOD'))->get(route('forecast'))->assertOk()->assertSee('never replaces qualified Awarded Revenue');
        $this->actingAs($this->role('CC Executive'))->get(route('forecast'))->assertForbidden();
        $this->actingAs($this->role('CC Executive'))->get(route('customers.show', [Customer::first(), 'tab' => 'Commercial']))
            ->assertSee('minimum linked commercial context');
    }

    public function test_attention_centre_lists_items_and_records_dispositions(): void
    {
        $hod = $this->role('HOD');
        $res = $this->actingAs($hod)->get(route('attention'))->assertOk()->assertSee('Critical CC Case')->assertSee('Stale opportunity');
        $case = \App\Models\SupportCase::where('priority', 'Critical')->where('status', '!=', 'Closed')->first();
        $key = "case:{$case->id}:critical";

        $this->actingAs($this->bd())->post(route('attention.dispose'), ['key' => $key, 'disposition' => 'Risk Accepted', 'note' => 'x'])->assertForbidden();
        $this->actingAs($this->bd())->post(route('attention.dispose'), ['key' => $key, 'disposition' => 'Acknowledged'])->assertSessionHas('ok');
        $this->assertDatabaseHas('attention_dispositions', ['item_key' => $key, 'disposition' => 'Acknowledged']);
        $this->assertTrue(AuditLog::where('action', 'ACKNOWLEDGED')->exists());

        $this->actingAs($hod)->post(route('attention.dispose'), ['key' => $key, 'disposition' => 'Risk Accepted'])->assertSessionHasErrors('note');
        $this->actingAs($hod)->post(route('attention.dispose'), ['key' => 'bogus:1', 'disposition' => 'Acknowledged'])->assertSessionHas('err');
    }

    public function test_contacts_create_edit_duplicate_and_permissions(): void
    {
        $bd = $this->bd();
        $customer = Customer::where('owner_id', $bd->id)->first();
        $payload = ['customer_id' => $customer->id, 'name' => 'Encik Test', 'email' => 'test@example.gov.my', 'buying_role' => 'Champion'];

        $this->actingAs($this->role('Management'))->post(route('contacts.store'), $payload)->assertForbidden();
        $this->actingAs($bd)->post(route('contacts.store'), $payload)->assertSessionHas('ok');
        $this->actingAs($bd)->post(route('contacts.store'), ['name' => 'encik test'] + $payload)->assertSessionHas('err');
        $this->assertSame(1, Contact::where('email', 'test@example.gov.my')->count());

        $c = Contact::where('email', 'test@example.gov.my')->first();
        $this->assertSame('Warm', $c->relationship);
        $this->actingAs($bd)->get(route('contacts.edit', $c))->assertOk();
        $this->actingAs($this->role('CC Executive'))->get(route('contacts.edit', $c))->assertForbidden();
        $this->actingAs($bd)->post(route('contacts.update', $c), ['relationship' => 'Strong', 'division' => 'ICT'] + $payload)->assertRedirect(route('contacts.index'));
        $this->assertSame('Strong', $c->fresh()->relationship);
        $this->actingAs($bd)->get(route('contacts.index', ['q' => 'Encik Test']))->assertSee('Encik Test');
    }

    public function test_customer_create_duplicate_edit_archive_and_engagement(): void
    {
        $bd = $this->bd();
        $this->actingAs($this->role('Management'))->post(route('customers.store'), ['name' => 'X', 'type' => 'GLC'])->assertForbidden();
        $this->actingAs($bd)->post(route('customers.store'), ['name' => 'jakoa', 'type' => 'Federal Government', 'state' => 'Selangor'])->assertSessionHas('err');
        $this->actingAs($bd)->post(route('customers.store'), ['name' => 'New Agency', 'type' => 'Private', 'state' => 'Johor'])->assertSessionHas('ok');
        $c = Customer::where('name', 'New Agency')->firstOrFail();

        $other = Customer::where('owner_id', '!=', $bd->id)->first();
        $edit = ['name' => 'Renamed', 'type' => 'Private', 'relationship' => 'Strong'];
        $this->actingAs($bd)->post(route('customers.update', $other), $edit)->assertForbidden();
        $this->actingAs($bd)->post(route('customers.update', $c), $edit)->assertSessionHas('ok');
        $this->assertSame('Strong', $c->fresh()->relationship);

        $this->actingAs($bd)->post(route('customers.archive', $c))->assertForbidden();
        $this->actingAs($this->role('HOD'))->post(route('customers.archive', $c))->assertRedirect(route('customers.index'));
        $this->assertTrue($c->fresh()->archived);

        $due = Customer::where('code', 'CUS-000004')->first();
        $this->actingAs($bd)->get(route('customers.attention', ['filter' => 'due']))->assertSee($due->name);
        $this->actingAs($this->role('HOD'))->post(route('customers.engaged', $due), ['note' => 'Courtesy visit'])->assertSessionHas('ok');
        $this->assertTrue($due->fresh()->last_engagement_at->isToday());
        $this->actingAs($this->role('CC Executive'))->post(route('customers.engaged', $due), ['note' => 'x'])->assertForbidden();
    }

    public function test_opportunity_create_and_stage_guards(): void
    {
        $bd = $this->bd();
        $cust = Customer::first();
        $base = ['name' => 'Guarded Opp', 'customer_id' => $cust->id, 'next_action' => 'Call', 'next_action_date' => today()->addDay()->toDateString()];
        $this->actingAs($this->role('CC Executive'))->post(route('opportunities.store'), $base + ['stage' => 'Lead Opportunity'])->assertForbidden();
        $this->actingAs($bd)->post(route('opportunities.store'), $base + ['stage' => 'Budget Approval & Evaluation'])->assertSessionHas('err');
        $this->actingAs($bd)->post(route('opportunities.store'), $base + ['stage' => 'Lead Opportunity'])->assertSessionHas('ok');
        $o = Opportunity::where('name', 'Guarded Opp')->firstOrFail();
        $this->assertSame(10, (int) $o->probability);

        $this->actingAs($bd)->post(route('opportunities.stage', $o), ['stage' => 'Solution Presentation'])->assertSessionHas('ok');
        $this->assertSame(20, (int) $o->fresh()->probability);
        // skip without reason / value missing / backwards / Award
        $this->actingAs($bd)->post(route('opportunities.stage', $o), ['stage' => 'Specification Tender', 'reason' => 'skip'])->assertSessionHas('err', 'BLOCKED: Estimated Value is mandatory before Budget Approval & Evaluation.');
        $this->actingAs($bd)->post(route('opportunities.stage', $o), ['stage' => 'Lead Opportunity'])->assertSessionHas('err');
        $this->actingAs($bd)->post(route('opportunities.stage', $o), ['stage' => 'Award'])->assertSessionHasErrors('stage');
        $o->update(['value' => 100000]);
        $this->actingAs($bd)->post(route('opportunities.stage', $o), ['stage' => 'Specification Tender'])->assertSessionHas('err', 'Stage skip requires a mandatory reason.');
        $this->actingAs($bd)->post(route('opportunities.stage', $o), ['stage' => 'Specification Tender', 'reason' => 'Customer fast-tracked'])->assertSessionHas('ok');
        $this->actingAs($this->role('CC Executive'))->post(route('opportunities.stage', $o), ['stage' => 'Lost', 'reason' => 'x'])->assertForbidden();
        $this->actingAs($bd)->post(route('opportunities.stage', $o), ['stage' => 'Lost'])->assertSessionHasErrors('reason');
        $this->actingAs($bd)->post(route('opportunities.stage', $o), ['stage' => 'Lost', 'reason' => 'Price'])->assertSessionHas('ok');
        $this->actingAs($bd)->post(route('opportunities.stage', $o), ['stage' => 'Negotiable/Shortlisted'])->assertSessionHas('err');
        $this->actingAs($bd)->post(route('opportunities.next', $o), ['next_action' => 'Debrief', 'next_action_date' => today()->toDateString()])->assertSessionHas('ok');
    }

    public function test_major_value_change_needs_approval_and_applies_once(): void
    {
        $bd = $this->bd();
        $o = Opportunity::active()->where('owner_id', $bd->id)->first();
        $old = (float) $o->value;
        $this->actingAs($bd)->post(route('opportunities.value', $o), ['value' => $old * 1.1, 'reason' => 'minor'])->assertSessionHas('ok');
        $this->assertEquals(round($old * 1.1, 2), (float) $o->fresh()->value);

        $this->actingAs($bd)->post(route('opportunities.value', $o), ['value' => $old * 3, 'reason' => 'scope grew'])->assertSessionHas('ok');
        $a = Approval::where('type', 'Major Opportunity Value Change')->where('subject_id', $o->id)->firstOrFail();
        $this->assertEquals(round($old * 1.1, 2), (float) $o->fresh()->value); // unchanged until approved
        $this->actingAs($bd)->post(route('opportunities.value', $o), ['value' => $old * 4, 'reason' => 'again'])->assertSessionHas('err');
        $this->actingAs($bd)->post(route('opportunities.value.apply', [$o, $a]))->assertSessionHas('err');

        $this->actingAs($this->role('HOD'))->post(route('approvals.decide', $a), ['decision' => 'Approved']);
        $this->assertSame('Approved', $a->fresh()->status);
        $this->actingAs($bd)->post(route('opportunities.value.apply', [$o, $a]))->assertSessionHas('ok');
        $this->assertEquals(round($old * 3, 2), (float) $o->fresh()->value);
        $this->actingAs($bd)->post(route('opportunities.value.apply', [$o, $a]))->assertSessionHas('ok', 'Approved value was already applied (no duplicate change).');
        $this->assertSame(1, AuditLog::where('action', 'OPPORTUNITY_VALUE_APPLIED')->count());

        // HOD changes directly
        $this->actingAs($this->role('HOD'))->post(route('opportunities.value', $o), ['value' => 1, 'reason' => 'correction'])->assertSessionHas('ok');
        $this->assertEquals(1.0, (float) $o->fresh()->value);
    }

    public function test_tender_create_bid_no_bid_and_permissions(): void
    {
        $bd = $this->bd();
        $opp = Opportunity::active()->first();
        $this->actingAs($this->role('CC Team Lead'))->post(route('tenders.store'), ['opportunity_id' => $opp->id, 'title' => 'T', 'closing_at' => now()->addWeek()])->assertForbidden();
        $this->actingAs($bd)->post(route('tenders.store'), ['opportunity_id' => $opp->id, 'title' => 'New Tender', 'closing_at' => now()->addWeek()->format('Y-m-d\TH:i')]);
        $t = Tender::where('title', 'New Tender')->firstOrFail();
        $this->assertSame('DRAFT', $t->status);
        $this->assertSame(5, $t->checklist()->count());
        $this->assertSame($opp->customer_id, $t->customer_id);

        $this->actingAs($bd)->post(route('tenders.bid', $t), ['decision' => 'bid'])->assertForbidden();
        $this->actingAs($bd)->post(route('tenders.bid', $t), ['decision' => 'assess'])->assertSessionHas('ok');
        $this->assertSame('BID_ASSESSMENT', $t->fresh()->status);
        $this->actingAs($this->role('HOD'))->post(route('tenders.bid', $t), ['decision' => 'no_bid'])->assertSessionHasErrors('reason');
        $this->actingAs($this->role('HOD'))->post(route('tenders.bid', $t), ['decision' => 'bid'])->assertSessionHas('ok');
        $this->assertSame('PREPARATION', $t->fresh()->status);
        $this->actingAs($this->role('HOD'))->post(route('tenders.bid', $t), ['decision' => 'no_bid', 'reason' => 'x'])->assertSessionHas('err');

        $t2 = Tender::where('code', 'TND-2026-DEMO01')->first();
        $this->actingAs($bd)->post(route('tenders.bid', $t2), ['decision' => 'assess']);
        $this->actingAs($this->role('Management'))->post(route('tenders.bid', $t2), ['decision' => 'no_bid', 'reason' => 'Not strategic'])->assertSessionHas('ok');
        $this->assertSame('NO_BID', $t2->fresh()->status);
        $this->assertSame('Not strategic', $t2->fresh()->no_bid_reason);

        $this->actingAs($this->role('CC Executive'))->post(route('tenders.checklist', [$t, $t->checklist->first()]))->assertForbidden();
    }

    public function test_tender_outcomes_award_and_retender(): void
    {
        $hod = $this->role('HOD');
        $prep = Tender::where('status', 'PREPARATION')->first();
        $this->actingAs($hod)->post(route('tenders.outcome', $prep), ['status' => 'AWARDED'])->assertSessionHas('err');

        $sub = Tender::where('status', 'READY_FOR_SUBMISSION')->first();
        $sub->update(['status' => 'SUBMITTED', 'submitted_at' => now()]); // no Award linked yet
        $this->assertFalse(Award::where('tender_id', $sub->id)->exists());
        $this->actingAs($this->role('CC Executive'))->post(route('tenders.outcome', $sub), ['status' => 'SHORTLISTED'])->assertForbidden();
        $this->actingAs($hod)->post(route('tenders.outcome', $sub), ['status' => 'LOST'])->assertSessionHasErrors('reason');
        $this->actingAs($hod)->post(route('tenders.outcome', $sub), ['status' => 'SHORTLISTED'])->assertSessionHas('ok');
        $this->actingAs($hod)->post(route('tenders.outcome', $sub), ['status' => 'AWARDED'])->assertSessionHas('ok');
        $this->assertSame('AWARDED', $sub->fresh()->status);
        $award = Award::where('tender_id', $sub->id)->firstOrFail();
        $this->assertFalse($award->revenue_qualified);
        $this->actingAs($hod)->post(route('tenders.outcome', $sub), ['status' => 'LOST', 'reason' => 'x'])->assertSessionHas('err');
        $this->assertSame(1, Award::where('tender_id', $sub->id)->count());

        $owner = User::find($prep->owner_id);
        $this->actingAs($owner)->post(route('tenders.retender', $prep))->assertSessionHas('err');
        $this->actingAs($owner)->post(route('tenders.outcome', $prep), ['status' => 'CANCELLED'])->assertSessionHas('ok');
        $this->actingAs($owner)->post(route('tenders.retender', $prep))->assertSessionHas('ok');
        $re = Tender::where('retender_of_id', $prep->id)->firstOrFail();
        $this->assertSame('DRAFT', $re->status);
        $this->assertFalse($re->pricing_approved);
        $this->assertSame(0, $re->checklist()->where('done', true)->count());
        $this->actingAs($owner)->post(route('tenders.retender', $prep));
        $this->assertSame(1, Tender::where('retender_of_id', $prep->id)->count());
    }

    public function test_seeded_disposition_hidden_by_default(): void
    {
        $d = AttentionDisposition::first();
        $this->assertNotNull($d);
        $this->actingAs($this->role('HOD'))->get(route('attention'))->assertDontSee($d->title);
        $this->actingAs($this->role('HOD'))->get(route('attention', ['show' => 'all']))->assertSee($d->title);
    }
}
