<?php

namespace Tests\Feature;

use App\Models\Competitor;
use App\Models\Customer;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Filter bar + pagination on cases, CC assignment / SLA, competitors and the legacy ecosystem page
class CaseCcListFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function login(string $handle): static
    {
        return $this->actingAs(User::where('email', "{$handle}@csa.test")->firstOrFail());
    }

    private function makeCases(): void
    {
        $cust = Customer::where('archived', false)->firstOrFail();
        foreach ([['ZEBRA-1', 'Zebra outage', 'Critical'], ['QUOKKA-1', 'Quokka glitch', 'Low']] as [$code, $title, $prio]) {
            SupportCase::create(['code' => $code, 'title' => $title, 'customer_id' => $cust->id, 'channel' => SupportCase::CHANNELS[0],
                'priority' => $prio, 'status' => 'Open', 'opened_at' => now(), 'sla_hours' => SupportCase::SLA_HOURS[$prio]]);
        }
    }

    public function test_cases_index_filters_and_pages(): void
    {
        $this->makeCases();
        $this->login('amin')->get(route('cases.index', ['q' => 'Zebra']))->assertOk()->assertSee('ZEBRA-1')->assertDontSee('QUOKKA-1');
        $this->login('amin')->get(route('cases.index', ['priority' => 'Low', 'q' => 'glitch']))->assertOk()->assertSee('QUOKKA-1')->assertDontSee('ZEBRA-1');
        $this->login('amin')->get(route('cases.index', ['from' => now()->addDay()->toDateString()]))->assertOk()->assertDontSee('ZEBRA-1');
        $this->login('amin')->get(route('cases.index', ['priority' => 'Bogus', 'page' => 2]))->assertOk();
    }

    public function test_cc_assignment_filters_and_pages(): void
    {
        $this->makeCases();
        $this->login('amin')->get(route('cc.assignment', ['q' => 'zebra']))->assertOk()->assertSee('ZEBRA-1')->assertDontSee('QUOKKA-1');
        $this->login('amin')->get(route('cc.assignment', ['priority' => 'Low', 'show' => 'unassigned']))->assertOk()->assertSee('QUOKKA-1')->assertDontSee('ZEBRA-1');
        $this->login('amin')->get(route('cc.assignment', ['page' => 2]))->assertOk();
    }

    public function test_cc_sla_filters_and_pages(): void
    {
        $this->makeCases();
        $this->login('amin')->get(route('cc.sla', ['q' => 'Quokka']))->assertOk()->assertSee('QUOKKA-1')->assertDontSee('ZEBRA-1');
        $this->login('amin')->get(route('cc.sla', ['state' => 'Running', 'priority' => 'Critical']))->assertOk()->assertSee('ZEBRA-1')->assertDontSee('QUOKKA-1');
        $this->login('amin')->get(route('cc.sla', ['to' => now()->subYears(5)->toDateString()]))->assertOk()->assertDontSee('ZEBRA-1');
        $this->login('amin')->get(route('cc.sla', ['state' => 'Nope', 'page' => 2]))->assertOk();
    }

    public function test_competitors_filters_and_pages(): void
    {
        Competitor::create(['name' => 'Zebracorp', 'encounters' => 4, 'won_against' => 3, 'lost_against' => 1]);
        Competitor::create(['name' => 'Quokkacorp', 'encounters' => 4, 'won_against' => 0, 'lost_against' => 2]);
        $this->login('amir')->get(route('competitors.index', ['q' => 'Zebra']))->assertOk()->assertSee('Zebracorp')->assertDontSee('Quokkacorp');
        $this->login('amir')->get(route('competitors.index', ['record' => 'losing']))->assertOk()->assertSee('Quokkacorp')->assertDontSee('Zebracorp');
        $this->login('amir')->get(route('competitors.index', ['page' => 2]))->assertOk();
    }

    public function test_legacy_ecosystem_page_filters_and_pages(): void
    {
        Competitor::create(['name' => 'Zebracorp', 'encounters' => 1, 'won_against' => 0, 'lost_against' => 0]);
        Competitor::create(['name' => 'Quokkacorp', 'encounters' => 1, 'won_against' => 0, 'lost_against' => 0]);
        $this->login('amir')->get(route('ecosystem', ['q' => 'Zebracorp']))->assertOk()->assertSee('Zebracorp')->assertDontSee('Quokkacorp');
        $this->login('amir')->get(route('ecosystem', ['category' => 'GIS', 'partners_page' => 2, 'products_page' => 2, 'competitors_page' => 2, 'page' => 2]))->assertOk();
    }
}
