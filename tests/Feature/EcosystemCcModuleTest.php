<?php

namespace Tests\Feature;

use App\Models\Competitor;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Partner;
use App\Models\Product;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcosystemCcModuleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function login(string $handle): static
    {
        return $this->actingAs(User::where('email', "{$handle}@csa.test")->firstOrFail());
    }

    private function partnerPayload(array $over = []): array
    {
        return $over + [
            'name' => 'Acme Spatial Sdn Bhd', 'reg_no' => 'REG-NEW-1',
            'owner_id' => User::where('email', 'amir@csa.test')->value('id'),
            'status' => 'Active', 'rating' => 4, 'classifications' => ['OEM', 'Reseller'], 'preferred' => '1',
        ];
    }

    public function test_ecosystem_pages_render_and_editors_are_enforced(): void
    {
        $p = Partner::first();
        foreach (['amir', 'fauzi', 'stanley', 'shahirah'] as $h) {
            $this->login($h)->get(route('partners.index'))->assertOk();
            $this->login($h)->get(route('partners.show', $p))->assertOk();
            $this->login($h)->get(route('products.index'))->assertOk();
            $this->login($h)->get(route('competitors.index'))->assertOk();
        }
        $this->login('amir')->get(route('partners.create'))->assertOk();
        $this->login('amir')->get(route('partners.edit', $p))->assertOk();
        $this->login('amir')->get(route('products.edit', Product::first()))->assertOk();
        $this->login('amir')->get(route('competitors.edit', Competitor::first()))->assertOk();
        // Management is view-only, CC roles do not maintain ecosystem master data
        foreach (['stanley', 'amin'] as $h) {
            $this->login($h)->get(route('partners.create'))->assertForbidden();
            $this->login($h)->post(route('partners.store'), $this->partnerPayload())->assertForbidden();
            $this->login($h)->post(route('products.store'), ['name' => 'X', 'category' => 'GIS', 'status' => 'Active'])->assertForbidden();
            $this->login($h)->get(route('competitors.edit', Competitor::first()))->assertForbidden();
        }
        // CC roles do not see commercial associations on the partner 360
        $this->login('shahirah')->get(route('partners.show', $p))->assertDontSee('Associated opportunities');
        $this->login('amir')->get(route('partners.show', $p))->assertSee('Associated opportunities');
    }

    public function test_partner_create_duplicate_guard_edit_and_archive(): void
    {
        $this->login('amir')->post(route('partners.store'), $this->partnerPayload())->assertRedirect();
        $p = Partner::where('name', 'Acme Spatial Sdn Bhd')->firstOrFail();
        $this->assertSame('OEM, Reseller', $p->classification);
        $this->assertTrue($p->preferred);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PARTNER_CREATED', 'entity_id' => $p->id]);

        // classification is mandatory
        $this->login('amir')->post(route('partners.store'), $this->partnerPayload(['name' => 'Other', 'classifications' => []]))->assertSessionHasErrors('classifications');

        // same reg no. -> blocked until confirmed
        $this->login('amir')->post(route('partners.store'), $this->partnerPayload(['name' => 'Acme Two']))->assertSessionHas('err');
        $this->assertSame(1, Partner::where('reg_no', 'REG-NEW-1')->count());
        $this->login('amir')->post(route('partners.store'), $this->partnerPayload(['name' => 'Acme Two', 'confirm_duplicate' => '1']))->assertRedirect();
        $this->assertSame(2, Partner::where('reg_no', 'REG-NEW-1')->count());

        $this->login('amir')->put(route('partners.update', $p), $this->partnerPayload(['rating' => 2, 'reg_no' => 'REG-NEW-9', 'confirm_duplicate' => '1']))->assertRedirect(route('partners.show', $p));
        $this->assertSame(2, $p->fresh()->rating);

        // BD Executive-type roles may edit, but archive needs HOD/AHOD/Superadmin/BD Manager
        $this->login('nurzam')->post(route('partners.archive', $p))->assertForbidden();
        $this->login('amir')->post(route('partners.archive', $p))->assertRedirect(route('partners.index'));
        $this->assertTrue($p->fresh()->archived);
        $this->login('amir')->get(route('partners.index'))->assertViewHas('partners', fn ($ps) => ! $ps->contains('id', $p->id));
        $this->login('amir')->get(route('partners.edit', $p))->assertForbidden(); // archived is read-only
        $this->login('fauzi')->post(route('partners.archive', $p), ['restore' => 1])->assertRedirect();
        $this->assertFalse($p->fresh()->archived);
    }

    public function test_partner_contacts_agreements_products_and_links(): void
    {
        $p = Partner::first();
        $this->login('amir')->post(route('partners.contacts.store', $p), ['name' => 'Jane Alliance', 'email' => 'jane@example.com'])->assertSessionHas('ok');
        $c = $p->contacts()->where('name', 'Jane Alliance')->firstOrFail();
        $this->login('amir')->delete(route('partners.contacts.destroy', [$p, $c]))->assertSessionHas('ok');
        $this->assertDatabaseMissing('partner_contacts', ['id' => $c->id]);

        $this->login('amir')->post(route('partners.agreements.store', $p), ['type' => 'NDA', 'status' => 'Draft', 'expiry_date' => now()->addYear()->toDateString()])->assertSessionHas('ok');
        $a = $p->agreements()->where('type', 'NDA')->firstOrFail();
        $this->login('amir')->put(route('partners.agreements.update', [$p, $a]), ['status' => 'Active', 'expiry_date' => now()->addMonth()->toDateString()])->assertSessionHas('ok');
        $this->assertTrue($a->fresh()->expiringSoon());
        $this->login('amir')->post(route('partners.agreements.store', $p), ['type' => 'Bogus', 'status' => 'Active'])->assertSessionHasErrors('type');

        $prod = Product::where('partner_id', '!=', $p->id)->first();
        $this->login('amir')->post(route('partners.products.link', $p), ['product_id' => $prod->id])->assertSessionHas('ok');
        $this->assertSame($p->id, $prod->fresh()->partner_id);

        $opp = Opportunity::whereNotIn('id', $p->opportunities()->pluck('opportunities.id'))->first();
        $this->login('amir')->post(route('partners.links.store', $p), ['type' => 'opportunity', 'id' => $opp->id])->assertSessionHas('ok');
        $this->login('amir')->post(route('partners.links.store', $p), ['type' => 'opportunity', 'id' => $opp->id]); // idempotent
        $this->assertSame(1, $p->opportunities()->where('opportunities.id', $opp->id)->count());
        $this->login('amir')->delete(route('partners.links.destroy', [$p, 'opportunity', $opp->id]))->assertSessionHas('ok');
        $this->assertSame(0, $p->opportunities()->where('opportunities.id', $opp->id)->count());
        $this->login('stanley')->post(route('partners.contacts.store', $p), ['name' => 'X'])->assertForbidden();
    }

    public function test_products_and_competitors_create_edit(): void
    {
        $partner = Partner::first();
        $this->login('nurzam')->post(route('products.store'), ['name' => 'Digital Twin Suite', 'category' => 'GIS', 'partner_id' => $partner->id, 'status' => 'Active'])->assertRedirect();
        $prod = Product::where('name', 'Digital Twin Suite')->firstOrFail();
        $this->assertSame($partner->id, $prod->partner_id);
        $this->login('nurzam')->put(route('products.update', $prod), ['name' => 'Digital Twin Suite', 'category' => 'AI', 'status' => 'End of Life'])->assertRedirect();
        $this->assertSame('End of Life', $prod->fresh()->status);
        $this->assertNull($prod->fresh()->partner_id);
        $this->login('nurzam')->get(route('products.index', ['category' => 'AI']))->assertSee('Digital Twin Suite');

        $this->login('amir')->post(route('competitors.store'), ['name' => 'Rival Co', 'encounters' => 3, 'won_against' => 1, 'lost_against' => 1, 'known_winning_value' => 500000])->assertRedirect();
        $c = Competitor::where('name', 'Rival Co')->firstOrFail();
        $this->assertSame(50.0, $c->winRate());
        // won + lost may not exceed encounters
        $this->login('amir')->put(route('competitors.update', $c), ['name' => 'Rival Co', 'encounters' => 1, 'won_against' => 1, 'lost_against' => 1])->assertSessionHasErrors('encounters');
        $this->login('amir')->put(route('competitors.update', $c), ['name' => 'Rival Co', 'encounters' => 5, 'won_against' => 3, 'lost_against' => 1])->assertRedirect();
        $this->assertSame(3, $c->fresh()->won_against);
    }

    public function test_cc_pages_access_and_scope(): void
    {
        foreach (['amin', 'aisyah', 'shahirah', 'fauzi', 'stanley'] as $h) {
            foreach (['cc.dashboard', 'cc.assignment', 'cc.sla'] as $r) {
                $this->login($h)->get(route($r))->assertOk();
            }
        }
        foreach (['amir', 'nurzam'] as $h) {
            foreach (['cc.dashboard', 'cc.assignment', 'cc.sla'] as $r) {
                $this->login($h)->get(route($r))->assertForbidden();
            }
        }
        // CC Executive sees only own cases, and cannot open someone else's
        $me = User::where('email', 'shahirah@csa.test')->first();
        $other = SupportCase::where('assignee_id', '!=', $me->id)->first();
        $mine = SupportCase::where('assignee_id', $me->id)->first();
        $this->login('shahirah')->get(route('cases.index'))->assertSee($mine->code)->assertDontSee($other->code);
        $this->login('shahirah')->get(route('cc.sla'))->assertDontSee($other->code);
        $this->login('shahirah')->get(route('cases.show', $other))->assertForbidden();
        $this->login('shahirah')->get(route('cases.show', $mine))->assertOk();
        $this->login('shahirah')->post(route('cases.note', $other), ['text' => 'x'])->assertForbidden();
        $this->login('amin')->get(route('cases.show', $other))->assertOk();
    }

    public function test_assignment_and_reassignment_rules(): void
    {
        $case = SupportCase::whereNotIn('status', SupportCase::DONE)->whereNotNull('assignee_id')->first();
        $to = User::where('role', 'CC Executive')->where('id', '!=', $case->assignee_id)->first();

        // executives cannot assign
        $this->login('shahirah')->post(route('cc.assign', $case), ['assignee_id' => $to->id, 'reason' => 'x'])->assertForbidden();
        // reassign needs a reason
        $this->login('aisyah')->post(route('cc.assign', $case), ['assignee_id' => $to->id])->assertSessionHasErrors('reason');
        // assignee must be a CC executive / team lead
        $bd = User::where('role', 'BD Manager')->first();
        $this->login('amin')->post(route('cc.assign', $case), ['assignee_id' => $bd->id, 'reason' => 'x'])->assertSessionHasErrors('assignee_id');
        $this->login('amin')->post(route('cases.assign', $case), ['assignee_id' => $bd->id])->assertSessionHasErrors('assignee_id');

        $this->login('aisyah')->post(route('cc.assign', $case), ['assignee_id' => $to->id, 'reason' => 'Load balancing'])->assertSessionHas('ok');
        $this->assertSame($to->id, $case->fresh()->assignee_id);
        $this->assertDatabaseHas('case_timeline', ['case_id' => $case->id, 'text' => "Reassigned from {$case->assignee->name}: Load balancing"]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'CASE_REASSIGNED', 'entity_id' => $case->id]);

        // first assignment of an Open case moves it to Assigned, no reason needed
        $new = SupportCase::create(['code' => 'CAS-T-1', 'title' => 'T', 'customer_id' => Customer::first()->id, 'channel' => 'Phone',
            'priority' => 'High', 'status' => 'Open', 'opened_at' => now(), 'sla_hours' => 8]);
        $this->login('amin')->post(route('cc.assign', $new), ['assignee_id' => $to->id])->assertSessionHas('ok');
        $this->assertSame('Assigned', $new->fresh()->status);

        $closed = SupportCase::where('status', 'Closed')->first();
        $this->login('amin')->post(route('cc.assign', $closed), ['assignee_id' => $to->id, 'reason' => 'x'])->assertSessionHas('err');
    }

    public function test_sla_elapsed_respects_pending_customer_pause(): void
    {
        $this->travelTo(now()->startOfMinute());
        $case = SupportCase::create(['code' => 'CAS-T-2', 'title' => 'Clock', 'customer_id' => Customer::first()->id, 'channel' => 'Email',
            'priority' => 'Critical', 'status' => 'In Progress', 'opened_at' => now(), 'sla_hours' => 4,
            'assignee_id' => User::where('email', 'shahirah@csa.test')->value('id')]);

        $this->travel(2)->hours();
        $this->login('shahirah')->post(route('cases.status', $case), ['status' => 'Pending Customer'])->assertSessionHas('ok');
        $this->travel(5)->hours(); // waiting on customer: clock frozen
        $c = $case->fresh();
        $this->assertSame('Paused', $c->slaState());
        $this->assertSame(120, $c->elapsedMinutes());

        $this->login('shahirah')->post(route('cases.status', $case), ['status' => 'Pending Internal']);
        $this->travel(1)->hours(); // Pending Internal keeps running
        $c = $case->fresh();
        $this->assertSame(180, $c->elapsedMinutes());
        $this->assertSame('Running', $c->slaState());

        $this->travel(2)->hours();
        $this->assertSame('Breached', $case->fresh()->slaState());
        $this->login('shahirah')->post(route('cases.status', $case), ['status' => 'Resolved']);
        $c = $case->fresh();
        $this->assertNotNull($c->resolved_at);
        $this->travel(3)->hours(); // resolved: clock stopped
        $this->assertSame(300, $c->fresh()->elapsedMinutes());
        $this->assertSame('Missed', $c->fresh()->slaState());
        $this->login('amin')->get(route('cc.sla', ['state' => 'Missed']))->assertSee('CAS-T-2');
    }

    public function test_case_create_note_confirm_close_csat_and_reopen(): void
    {
        $cust = Customer::first();
        $this->login('stanley')->post(route('cases.store'), ['title' => 'Nope', 'customer_id' => $cust->id, 'channel' => 'Phone', 'priority' => 'Low'])->assertForbidden();
        $this->login('aisyah')->post(route('cases.store'), ['title' => 'Portal down', 'customer_id' => $cust->id, 'channel' => 'Phone', 'priority' => 'Critical'])->assertRedirect();
        $case = SupportCase::where('title', 'Portal down')->firstOrFail();
        $this->assertSame(4, $case->sla_hours);
        $this->assertSame('Open', $case->status);

        $this->login('aisyah')->post(route('cases.note', $case), ['text' => 'Called customer'])->assertSessionHas('ok');
        $this->assertDatabaseHas('case_timeline', ['case_id' => $case->id, 'text' => 'Called customer']);

        // cannot close without confirmation; remarks are mandatory
        $this->login('aisyah')->post(route('cases.status', $case), ['status' => 'Closed'])->assertSessionHas('err');
        $this->login('aisyah')->post(route('cases.confirm', $case), ['method' => 'Email'])->assertSessionHasErrors('remarks');
        // CSAT before confirmation is refused
        $this->login('aisyah')->post(route('cc.csat', $case), ['csat' => 5])->assertSessionHas('err');

        $this->login('aisyah')->post(route('cases.confirm', $case), ['method' => 'WhatsApp', 'remarks' => 'Customer confirmed fix', 'close' => 1])->assertSessionHas('ok');
        $case->refresh();
        $this->assertSame('Closed', $case->status);
        $this->assertNotNull($case->resolved_at);

        $this->login('stanley')->post(route('cc.csat', $case), ['csat' => 4])->assertForbidden();
        $this->login('aisyah')->post(route('cc.csat', $case), ['csat' => 4, 'comment' => 'Quick'])->assertSessionHas('ok');
        $this->assertSame(4, $case->fresh()->csat);
        $this->login('aisyah')->post(route('cc.csat', $case), ['csat' => 1])->assertSessionHas('err'); // captured once

        $this->login('aisyah')->post(route('cases.reopen', $case), [])->assertSessionHasErrors('reason');
        $this->login('aisyah')->post(route('cases.reopen', $case), ['reason' => 'Issue recurred'])->assertSessionHas('ok');
        $case->refresh();
        $this->assertSame('Reopened', $case->status);
        $this->assertNull($case->resolved_at);
        $this->assertNull($case->confirmation_method);
        $this->assertDatabaseHas('case_timeline', ['case_id' => $case->id, 'text' => 'Reopened: Issue recurred']);
        $this->login('aisyah')->post(route('cases.reopen', $case), ['reason' => 'again'])->assertSessionHas('err');
    }
}
