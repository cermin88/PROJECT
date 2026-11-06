<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickCreateTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_quick_create_saves_customer_and_shows_it_on_dashboard(): void
    {
        $user = User::where('email', 'amir@csa.test')->firstOrFail();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('quick-create-dialog')
            ->assertSee('Create customer');

        $this->actingAs($user)->post(route('customers.store'), [
            'quick_create' => 'customer',
            'name' => 'Quick Create Dashboard Customer',
            'type' => 'Private',
            'sector' => 'Technology',
            'state' => 'Selangor',
        ])->assertRedirect(route('dashboard'));

        $customer = Customer::where('name', 'Quick Create Dashboard Customer')->firstOrFail();
        $this->assertSame($user->id, $customer->owner_id);
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Quick Create Dashboard Customer')
            ->assertSee(route('customers.show', $customer));
    }

    public function test_quick_create_validation_returns_to_modal_with_errors(): void
    {
        $user = User::where('email', 'amir@csa.test')->firstOrFail();

        $this->actingAs($user)->from(route('dashboard'))->post(route('customers.store'), [
            'quick_create' => 'customer',
            'name' => '',
            'type' => 'Private',
        ])->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('name');

        $this->get(route('dashboard'))->assertOk()->assertSee('quick-create-dialog');
    }

    public function test_customers_page_has_quick_create_button_instead_of_inline_customer_form(): void
    {
        $user = User::where('email', 'amir@csa.test')->firstOrFail();

        $this->actingAs($user)->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Add New Customer')
            ->assertSee('data-quick-create-open="customer"', false)
            ->assertSeeInOrder(['name="q"', '>Search</button>', 'data-quick-create-open="customer"'])
            ->assertSee('quick-create-dialog')
            ->assertSee('data-quick-create-form="customer"', false)
            ->assertDontSee('Organisation name *')
            ->assertDontSee('<h3 style="margin-top:0">New customer</h3>', false);
    }

    public function test_create_buttons_on_module_pages_open_the_matching_quick_create_form(): void
    {
        $user = User::where('email', 'fauzi@csa.test')->firstOrFail();
        $pages = [
            ['contacts.index', 'contact', 'Create Contact'],
            ['opportunities.index', 'opportunity', 'Create Opportunity'],
            ['activities.index', 'activity', 'Create Activity'],
            ['tasks.index', 'task', 'Create Task'],
            ['tenders.index', 'tender', 'Create Tender'],
            ['events.index', 'event', 'Create Event'],
            ['partners.index', 'partner', 'Create Partner'],
        ];

        foreach ($pages as [$route, $type, $label]) {
            $this->actingAs($user)->get(route($route))
                ->assertOk()
                ->assertSee($label)
                ->assertSee('data-quick-create-open="' . $type . '"', false)
                ->assertSee('data-quick-create-form="' . $type . '"', false);
        }

        $this->get(route('opportunities.index'))
            ->assertOk()
            ->assertSee('Select owner')
            ->assertDontSee('>Me</option>', false);
    }

    public function test_page_specific_customer_create_returns_to_customer_list(): void
    {
        $user = User::where('email', 'amir@csa.test')->firstOrFail();

        $this->actingAs($user)->post(route('customers.store'), [
            'quick_create' => 'customer',
            'quick_create_page' => '1',
            'name' => 'Customer List Return Test',
            'type' => 'Private',
            'state' => 'Selangor',
        ])->assertRedirect(route('customers.index'));
    }

    public function test_page_specific_contact_create_returns_to_contacts_list(): void
    {
        $user = User::where('email', 'fauzi@csa.test')->firstOrFail();
        $customer = Customer::active()->firstOrFail();

        $this->actingAs($user)->post(route('contacts.store'), [
            'quick_create' => 'contact',
            'quick_create_page' => '1',
            'customer_id' => $customer->id,
            'name' => 'Contact List Return Test',
            'relationship' => 'Warm',
        ])->assertRedirect(route('contacts.index'));
    }
}
