<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\Renewal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAccessProfileTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_users_only_see_projects_assigned_to_them_and_cannot_update_another_users_milestones(): void
    {
        $user = User::where('email', 'asyafiq@csa.test')->firstOrFail();
        $owned = Project::where('owner_id', $user->id)->with('milestones')->firstOrFail();
        $other = Project::whereNotNull('owner_id')->where('owner_id', '!=', $user->id)->with('milestones')->firstOrFail();

        $response = $this->actingAs($user)->get(route('projects.index'))
            ->assertOk()
            ->assertSee($owned->code)
            ->assertDontSee($other->code);
        $this->assertSame(
            Project::where('owner_id', $user->id)->orderBy('code')->pluck('code')->all(),
            $response->viewData('projects')->getCollection()->pluck('code')->all()
        );

        $milestone = $other->milestones->firstOrFail();
        $progress = (int) $milestone->progress;
        $this->post(route('milestones.update', $milestone), ['progress' => 100])->assertNotFound();
        $this->assertSame($progress, (int) $milestone->fresh()->progress);

        $this->get(route('awards.show', $other->award_id))->assertNotFound();
        $this->get(route('awards.index'))->assertDontSee($other->award->code);

        $invoice = Invoice::create([
            'invoice_no' => 'INV-ACCESS-OWNER-TEST',
            'award_id' => $other->award_id,
            'project_id' => $other->id,
            'amount' => 1000,
            'paid' => 0,
            'invoice_date' => today(),
            'due_date' => today()->addMonth(),
        ]);
        $this->get(route('invoices.index'))->assertDontSee($invoice->invoice_no);
    }

    public function test_superadmin_can_see_all_projects_and_project_linked_records(): void
    {
        $admin = User::create([
            'code' => 'USR-ACCESS-ADMIN',
            'name' => 'Access Test Admin',
            'email' => 'access-admin@example.test',
            'password' => 'secure-test-password',
            'role' => 'Superadmin',
            'active' => true,
        ]);
        $projects = Project::whereNotNull('owner_id')->with('award')->get();
        $other = $projects->last();

        $this->actingAs($admin)->get(route('projects.index'))
            ->assertOk()
            ->assertSee($projects->first()->code)
            ->assertSee($other->code);

        $invoice = Invoice::create([
            'invoice_no' => 'INV-ACCESS-TEST',
            'award_id' => $other->award_id,
            'project_id' => $other->id,
            'amount' => 1000,
            'paid' => 0,
            'invoice_date' => today(),
            'due_date' => today()->addMonth(),
        ]);

        $this->get(route('invoices.index'))->assertOk()->assertSee($invoice->invoice_no);
    }

    public function test_profile_page_displays_the_signed_in_users_account_and_role(): void
    {
        $user = User::where('email', 'fauzi@csa.test')->firstOrFail();

        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee($user->email)
            ->assertSee($user->role)
            ->assertSee('Account status')
            ->assertSee(route('profile.show'))
            ->assertDontSee('Demo only');
    }

    public function test_users_cannot_see_or_act_on_a_renewal_linked_to_another_users_project(): void
    {
        $project = Project::whereNotNull('owner_id')->firstOrFail();
        $otherUser = User::where('id', '!=', $project->owner_id)->firstOrFail();
        $renewal = Renewal::create([
            'customer_id' => $project->award->customer_id,
            'project_id' => $project->id,
            'type' => 'Private project renewal',
            'expiry_date' => today()->addMonth(),
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($otherUser)->get(route('renewals.index'))->assertDontSee($renewal->type);
        $this->post(route('renewals.opportunity', $renewal))->assertNotFound();
    }

    public function test_bd_manager_only_sees_and_opens_customers_they_own(): void
    {
        $user = User::where('email', 'asyafiq@csa.test')->firstOrFail();
        $owned = \App\Models\Customer::where('owner_id', $user->id)->firstOrFail();
        $other = \App\Models\Customer::whereNotNull('owner_id')->where('owner_id', '!=', $user->id)->firstOrFail();

        $response = $this->actingAs($user)->get(route('customers.index'))->assertOk();
        $this->assertSame(
            \App\Models\Customer::where('owner_id', $user->id)->where('archived', false)->orderBy('name')->pluck('id')->all(),
            $response->viewData('customers')->getCollection()->pluck('id')->all()
        );
        $this->assertContains($owned->id, $response->viewData('customers')->getCollection()->pluck('id')->all());
        $this->get(route('customers.show', $other))->assertNotFound();
    }
}
