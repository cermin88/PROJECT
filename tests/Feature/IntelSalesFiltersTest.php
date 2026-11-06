<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Kpi;
use App\Models\Opportunity;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Filter bar + pagination on KPI / Intelligence / Sales insight pages
class IntelSalesFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function as(string $handle): User
    {
        $u = User::where('email', "{$handle}@csa.test")->firstOrFail();
        $this->actingAs($u);
        return $u;
    }

    // Two distinct values where neither contains the other, so a search for one can't match the other
    private function pair($values): array
    {
        $values = collect($values)->filter()->unique()->values();
        foreach ($values as $a) {
            foreach ($values as $b) {
                if ($a !== $b && ! str_contains(mb_strtolower($b), mb_strtolower($a))) {
                    return [$a, $b];
                }
            }
        }
        $this->fail('Seed data has no distinct pair');
    }

    public function test_my_kpi_filters_and_paginates(): void
    {
        $u = $this->as('amir');
        [$a, $b] = $this->pair(Kpi::where('user_id', $u->id)->pluck('name'));
        $this->get(route('kpi.index', ['period' => 'all', 'q' => $a]))->assertOk()->assertSee($a)->assertDontSee($b);
        $this->get(route('kpi.index', ['period' => 'all', 'status' => 'bogus', 'source' => 'x']))->assertOk()->assertSee($a)->assertSee($b);
        $this->get(route('kpi.index', ['period' => 'all', 'page' => 2]))->assertOk();
    }

    public function test_team_and_review_kpi_filter_and_paginate(): void
    {
        $this->as('fauzi');
        foreach (['kpi.team', 'kpi.review'] as $r) {
            $this->get(route($r, ['q' => 'Case First Response']))->assertOk()
                ->assertSee('Case First Response Discipline')->assertDontSee('Completed Business Development Activities');
            $this->get(route($r, ['status' => 'bogus']))->assertOk()->assertSee('Completed Business Development Activities');
            $this->get(route($r, ['period' => 'all', 'page' => 2]))->assertOk();
        }
    }

    public function test_kpi_admin_filters_and_paginates(): void
    {
        $this->as('fauzi');
        $this->get(route('kpi.admin', ['period' => 'all', 'q' => 'Case First Response']))->assertOk()
            ->assertSee('Case First Response Discipline')->assertDontSee('Completed Business Development Activities');
        $this->get(route('kpi.admin', ['period' => 'all', 'source' => 'bogus', 'frequency' => 'bogus']))->assertOk()->assertSee('Completed Business Development Activities');
        $this->get(route('kpi.admin', ['period' => 'all', 'page' => 2]))->assertOk();
    }

    public function test_report_output_filters_and_paginates(): void
    {
        $this->as('fauzi');
        [$a, $b] = $this->pair(Opportunity::active()->pluck('code'));
        $this->get(route('reports.show', ['report' => 2, 'period' => 'all', 'q' => $a]))->assertOk()->assertSee($a)->assertDontSee($b);
        $this->get(route('reports.show', ['report' => 2, 'period' => 'all', 'page' => 2]))->assertOk();
    }

    public function test_attention_centre_filters_and_paginates(): void
    {
        $this->as('fauzi');
        $this->get(route('attention', ['type' => 'case']))->assertOk()->assertSee('Critical CC Case')->assertDontSee('Stale opportunity');
        $this->get(route('attention', ['q' => 'Stale opportunity']))->assertOk()->assertSee('Stale opportunity')->assertDontSee('Critical CC Case');
        $this->get(route('attention', ['type' => 'bogus', 'severity' => 'bogus']))->assertOk()->assertSee('Critical CC Case')->assertSee('Stale opportunity');
        $this->get(route('attention', ['show' => 'all', 'page' => 2]))->assertOk();
    }

    public function test_customer_attention_filters_and_paginates(): void
    {
        $this->as('fauzi');
        [$a, $b] = $this->pair(Customer::active()->pluck('name'));
        $this->get(route('customers.attention', ['q' => $a]))->assertOk()->assertSee($a)->assertDontSee($b);
        // Invalid filter values are ignored: same total as no filter
        $total = $this->get(route('customers.attention'))->viewData('rows')->total();
        $this->assertSame($total, $this->get(route('customers.attention', ['relationship' => 'bogus', 'from' => 'not-a-date']))->assertOk()->viewData('rows')->total());
        $this->get(route('customers.attention', ['page' => 2]))->assertOk();
    }

    public function test_forecast_filters_and_paginates(): void
    {
        $this->as('fauzi');
        [$a, $b] = $this->pair(Opportunity::active()->pluck('code'));
        $this->get(route('forecast', ['q' => $a]))->assertOk()->assertSee($a)->assertDontSee($b);
        $total = $this->get(route('forecast'))->viewData('rows')->total();
        $this->assertSame($total, $this->get(route('forecast', ['stage' => 'bogus']))->assertOk()->viewData('rows')->total());
        $this->get(route('forecast', ['page' => 2]))->assertOk();
    }

    public function test_tender_calendar_filters_by_status_owner_and_month(): void
    {
        $this->as('fauzi');
        $ts = Tender::whereNull('archived_at')->whereNotNull('closing_at')->get();
        $a = $ts->first();
        $other = $ts->first(fn ($t) => $t->status !== $a->status && ! str_contains($t->code, $a->code) && ! str_contains($a->code, $t->code));
        $this->assertNotNull($other);
        $this->get(route('tenders.calendar', ['view' => 'all', 'status' => $a->status]))->assertOk()->assertSee($a->code)->assertDontSee($other->code);
        $month = $a->closing_at->timezone('Asia/Kuala_Lumpur')->format('Y-m');
        $this->get(route('tenders.calendar', ['view' => 'all', 'month' => $month]))->assertOk()->assertSee($a->code);
        $this->get(route('tenders.calendar', ['view' => 'all', 'owner_id' => 999999]))->assertOk()->assertSee('No tenders in this view.');
        $this->get(route('tenders.calendar', ['view' => 'all', 'status' => 'bogus', 'month' => 'bogus', 'page' => 2]))->assertOk()->assertSee($a->code);
    }
}
