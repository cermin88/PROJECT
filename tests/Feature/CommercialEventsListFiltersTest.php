<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\CsaEvent;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Renewal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialEventsListFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function u(): User
    {
        return User::where('email', 'amir@csa.test')->firstOrFail();
    }

    private function bulkEvents(string $status, int $n = 22): void
    {
        foreach (range(1, $n) as $i) {
            CsaEvent::create(['code' => sprintf('EVT-B-%s-%03d', substr($status, 0, 3), $i), 'name' => "Bulk {$status} {$i}",
                'event_date' => now()->addDays($i), 'budget' => 100, 'status' => $status, 'owner_id' => $this->u()->id]);
        }
    }

    public function test_awards_filter_and_pagination(): void
    {
        $a = Award::first();
        foreach (range(1, 22) as $i) {
            Award::create(['code' => sprintf('AWD-B-%03d', $i), 'title' => "Bulk award {$i}", 'customer_id' => $a->customer_id,
                'value' => 1000, 'award_date' => now()->subYears(2), 'status' => 'PENDING_EVIDENCE']);
        }
        $this->actingAs($this->u())->get(route('awards.index', ['q' => 'AWD-2026-000001']))
            ->assertOk()->assertSee('AWD-2026-000001')->assertDontSee('AWD-2026-000002');
        $this->get(route('awards.index', ['status' => 'QUALIFIED']))->assertSee('AWD-2026-000001')->assertDontSee('AWD-B-001');
        $this->get(route('awards.index', ['to' => now()->subYear()->toDateString()]))->assertSee('AWD-B-001')->assertDontSee('AWD-2026-000001');
        $this->get(route('awards.index', ['page' => 2]))->assertOk()->assertSee('page=1');
        $this->get(route('awards.index', ['status' => 'bogus']))->assertOk(); // non-whitelisted value is ignored
    }

    public function test_invoices_filter_totals_and_pagination(): void
    {
        $base = Invoice::first();
        foreach (range(1, 22) as $i) {
            Invoice::create(['invoice_no' => "INV/B/{$i}", 'award_id' => $base->award_id, 'amount' => 10, 'paid' => 10,
                'invoice_date' => now(), 'due_date' => now()->addYear()]);
        }
        $this->actingAs($this->u())->get(route('invoices.index', ['q' => 'INV/26/1000']))
            ->assertOk()->assertSee('INV/26/1000')->assertDontSee('INV/26/1001')->assertSee('RM 180,000');
        $r = $this->get(route('invoices.index', ['state' => 'paid', 'from' => now()->addMonths(6)->toDateString()]))->assertOk()->assertDontSee('INV/26/1000');
        $this->assertSame(22, $r->viewData('invoices')->total());
        $this->assertSame(220.0, $r->viewData('totalInvoiced')); // totals over all filtered rows, not one page
        $this->get(route('invoices.index', ['page' => 2]))->assertOk();
    }

    public function test_projects_and_renewals_filter_and_pagination(): void
    {
        $user = $this->u();
        $owned = Project::where('owner_id', $user->id)->firstOrFail();
        $other = Project::where('owner_id', '!=', $user->id)->firstOrFail();
        $this->actingAs($user)->get(route('projects.index', ['status' => $owned->status]))
            ->assertOk()->assertSee($owned->code)->assertDontSee($other->code);
        $foreignSearch = $this->get(route('projects.index', ['q' => $other->code]))->assertOk();
        $this->assertSame(0, $foreignSearch->viewData('projects')->total());
        $this->get(route('projects.index', ['page' => 2]))->assertOk();

        $r = Renewal::first();
        foreach (range(1, 22) as $i) {
            Renewal::create(['customer_id' => $r->customer_id, 'type' => 'Support', 'expiry_date' => now()->addYears(3), 'status' => 'ACTIVE']);
        }
        $res = $this->get(route('renewals.index', ['type' => 'Support']))->assertOk();
        $this->assertSame(22, $res->viewData('renewals')->total());
        $this->assertCount(config('csa.per_page'), $res->viewData('renewals')->items());
        $this->assertSame(1, $this->get(route('renewals.index', ['status' => 'EXPIRED']))->viewData('renewals')->total());
        $this->get(route('renewals.index', ['type' => 'Support', 'page' => 2]))->assertOk()->assertSee('Support');
    }

    public function test_events_index_filter_kpis_and_pagination(): void
    {
        $this->bulkEvents('DRAFT');
        $r = $this->actingAs($this->u())->get(route('events.index', ['q' => 'Roundtable']))->assertOk()
            ->assertSee('Utility Intelligence Roundtable')->assertDontSee('Government Geospatial');
        $this->assertSame(1, (int) $r->viewData('kpi')->n);
        $all = $this->get(route('events.index'))->assertOk();
        $this->assertSame(25, (int) $all->viewData('kpi')->n); // KPI over the full set, page holds per_page
        $this->assertCount(config('csa.per_page'), $all->viewData('events')->items());
        $this->get(route('events.index', ['page' => 2]))->assertOk()->assertSee('Bulk DRAFT 22');
    }

    public function test_attendance_and_postmortem_filter_and_pagination(): void
    {
        $this->bulkEvents('APPROVED');
        $this->actingAs($this->u())->get(route('events.attendance', ['q' => 'Technology Day']))->assertOk()
            ->assertSee('CSA Public Sector Technology Day')->assertDontSee('Utility Intelligence Roundtable');
        $e = CsaEvent::where('code', 'EVT-2026-000002')->first();
        // selected event's guest list still shows when it is not on the current page
        $this->get(route('events.attendance', ['event' => $e->id, 'page' => 2]))->assertOk()->assertSee('guest list');

        $this->bulkEvents('CLOSED');
        $this->get(route('events.postmortem', ['status' => 'POST_MORTEM']))->assertOk()
            ->assertSee('CSA Public Sector Technology Day')->assertDontSee('Bulk CLOSED');
        $this->get(route('events.postmortem', ['q' => 'Bulk CLOSED 5']))->assertSee('Bulk CLOSED 5')->assertDontSee('Technology Day');
        $this->get(route('events.postmortem', ['page' => 2]))->assertOk();
    }
}
