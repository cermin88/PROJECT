<?php

namespace Tests\Feature;

use App\Models\CsaEvent;
use App\Models\Opportunity;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Cross-module checks added by the integration review
class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_archived_opportunity_leaves_lists_and_pipeline(): void
    {
        $hod = User::where('role', 'HOD')->firstOrFail();
        $o = Opportunity::active()->firstOrFail();
        $this->actingAs($hod)->get(route('opportunities.index'))->assertSee($o->code);
        $o->forceFill(['archived_at' => now(), 'archive_reason' => 'test'])->save();
        $this->actingAs($hod)->get(route('opportunities.index', ['view' => 'all']))->assertDontSee($o->code);
        $this->assertFalse(Opportunity::active()->whereKey($o->id)->exists());
    }

    public function test_search_respects_case_scope_and_export_needs_export_role(): void
    {
        $exec = User::where('role', 'CC Executive')->firstOrFail();
        $other = SupportCase::where(fn ($q) => $q->whereNull('assignee_id')->orWhere('assignee_id', '!=', $exec->id))->firstOrFail();
        $this->actingAs($exec)->get(route('search', ['q' => $other->code]))->assertOk()->assertDontSee(route('cases.show', $other));

        $bd = User::where('role', 'BD Manager')->firstOrFail();
        $this->actingAs($bd)->get(route('dataquality.export'))->assertForbidden();
        $this->actingAs(User::where('role', 'HOD')->firstOrFail())->get(route('dataquality.export'))->assertOk();
    }

    public function test_calendar_shows_events(): void
    {
        $e = CsaEvent::where('status', '!=', 'REJECTED')->firstOrFail();
        $this->actingAs(User::where('role', 'BD Manager')->firstOrFail())
            ->get(route('calendar', ['month' => $e->event_date->format('Y-m')]))
            ->assertOk()->assertSee(route('events.show', $e));
    }
}
