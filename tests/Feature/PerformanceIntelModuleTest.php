<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CustomReportRun;
use App\Models\Kpi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PerformanceIntelModuleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function as(string $handle): User
    {
        $u = User::where('email', "{$handle}@csa.test")->firstOrFail();
        $this->actingAs($u);
        return $u;
    }

    private function role(string $role): User
    {
        $u = User::firstOrCreate(['email' => strtolower(str_replace(' ', '', $role)).'@test.local'],
            ['name' => "Test {$role}", 'role' => $role, 'password' => 'password']);
        $this->actingAs($u);
        return $u;
    }

    public function test_every_page_renders_for_each_seeded_role(): void
    {
        $pages = ['kpi.index', 'kpi.team', 'kpi.review', 'analytics', 'reports.index', 'reports.builder', 'reports.pack'];
        foreach (User::all()->unique('role') as $u) {
            foreach ($pages as $p) {
                $this->actingAs($u)->get(route($p))->assertOk();
            }
            $this->actingAs($u)->get(route('reports.show', 1))->assertOk();
            $status = $this->actingAs($u)->get(route('kpi.admin'))->status();
            $this->assertSame(in_array($u->role, ['HOD', 'AHOD', 'Management', 'Superadmin'], true) ? 200 : 403, $status, "kpi.admin as {$u->role}");
        }
        foreach (['AHOD', 'Superadmin'] as $r) {
            $this->role($r);
            $this->get(route('kpi.admin'))->assertOk();
        }
        foreach (array_keys(\App\Services\Intelligence\ReportCatalogue::REPORTS) as $id) {
            $this->as('fauzi');
            foreach (['quarter', 'year', 'all'] as $period) {
                $this->get(route('reports.show', ['report' => $id, 'period' => $period]))->assertOk();
            }
        }
        $this->get(route('reports.show', 99))->assertNotFound();
    }

    public function test_my_kpi_shows_only_own_kpis(): void
    {
        $u = $this->as('amir');
        $other = Kpi::where('user_id', '!=', $u->id)->where('name', 'Team Performance Achievement Rate')->firstOrFail();
        $this->get(route('kpi.index', ['period' => 'all']))->assertOk()
            ->assertSee('Tender / Opportunity Execution Quality')->assertDontSee($other->name);
    }

    public function test_team_kpi_is_scoped_by_role(): void
    {
        $this->as('amin'); // CC Manager: CC staff only
        $this->get(route('kpi.team'))->assertOk()->assertSee('Case First Response Discipline')->assertDontSee('Completed Business Development Activities');
        $this->as('fauzi'); // HOD sees all
        $this->get(route('kpi.team'))->assertSee('Case First Response Discipline')->assertSee('Completed Business Development Activities');
        $this->as('shahirah'); // CC Executive: own only
        $this->get(route('kpi.team'))->assertSee('Case First Response Discipline')->assertDontSee('Contact Centre SLA Compliance');
    }

    public function test_owner_saves_evidence_only_on_open_own_kpi(): void
    {
        $u = $this->as('nuzul');
        $k = Kpi::where('user_id', $u->id)->where('locked', false)->firstOrFail();
        $this->post(route('kpi.evidence', $k), ['evidence' => 'Visit log uploaded'])->assertSessionHas('ok');
        $this->assertSame('Visit log uploaded', $k->fresh()->evidence);

        $notMine = Kpi::where('user_id', '!=', $u->id)->firstOrFail();
        $this->post(route('kpi.evidence', $notMine), ['evidence' => 'x'])->assertForbidden();

        $locked = Kpi::create(['user_id' => $u->id, 'name' => 'Locked one', 'source' => 'SYSTEM', 'target' => 10, 'actual' => 5, 'weight' => 10, 'locked' => true, 'period' => '2026-Q1']);
        $this->post(route('kpi.evidence', $locked), ['evidence' => 'late'])->assertSessionHas('err');
    }

    public function test_review_save_and_lock_rules(): void
    {
        $k = Kpi::where('locked', false)->where('period', Kpi::currentPeriod())->firstOrFail();

        $this->as('amir'); // BD Manager cannot review
        $this->post(route('kpi.review.save', $k), ['actual' => 5, 'rating' => 5, 'status' => 'On Track'])->assertForbidden();
        $this->role('AHOD');
        $this->post(route('kpi.review.save', $k), ['actual' => 5, 'rating' => 5, 'status' => 'On Track'])->assertForbidden();

        $this->as('fauzi');
        $this->post(route('kpi.review.save', $k), ['actual' => 50, 'rating' => 11, 'status' => 'On Track'])->assertSessionHasErrors('rating');
        $this->post(route('kpi.review.save', $k), ['actual' => 50, 'rating' => 7, 'status' => 'At Risk', 'review_comment' => 'ok', 'lock' => 1])->assertSessionHas('ok');
        $k->refresh();
        $this->assertTrue($k->locked);
        $this->assertSame(7, (int) $k->rating);
        $this->assertSame(50.0, $k->actual);
        $this->assertDatabaseHas('audit_logs', ['action' => 'KPI_REVIEW', 'entity_id' => $k->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'KPI_LOCKED', 'entity_id' => $k->id]);

        // Locked: review is refused, adjustment appends and keeps the original
        $this->post(route('kpi.review.save', $k), ['actual' => 60, 'rating' => 7, 'status' => 'On Track'])->assertSessionHas('err');
        $this->post(route('kpi.adjust', $k), ['actual' => 60, 'reason' => 'Late evidence'])->assertSessionHas('ok');
        $k->refresh()->load('adjustments');
        $this->assertSame(50.0, $k->actual);
        $this->assertSame(60.0, $k->effectiveActual());
        $this->assertCount(1, $k->adjustments);
        $this->get(route('kpi.review', ['period' => 'all']))->assertOk()->assertSee('Late evidence');
    }

    public function test_kpi_admin_define_edit_copy_and_rules(): void
    {
        $emp = User::where('email', 'nuzul@csa.test')->firstOrFail();
        $base = ['user_id' => $emp->id, 'period' => '2027-Q1', 'source' => 'SYSTEM', 'target' => 10, 'weight' => 60, 'frequency' => 'Quarterly', 'name' => 'Proposal Turnaround'];

        $this->role('AHOD'); // view only
        $this->post(route('kpi.admin.store'), $base)->assertForbidden();
        $this->as('stanley'); // Management: view only
        $this->post(route('kpi.admin.store'), $base)->assertForbidden();

        $this->as('fauzi');
        $this->post(route('kpi.admin.store'), ['name' => 'Individual Revenue Target'] + $base)->assertSessionHasErrors('name');
        $this->post(route('kpi.admin.store'), $base)->assertSessionHas('ok');
        $this->post(route('kpi.admin.store'), ['name' => 'Second', 'weight' => 50] + $base)->assertSessionHas('err'); // 110%
        $k = Kpi::where(['user_id' => $emp->id, 'period' => '2027-Q1'])->firstOrFail();
        $this->assertDatabaseHas('audit_logs', ['action' => 'KPI_DEFINED', 'entity_id' => $k->id]);

        $this->post(route('kpi.admin.update', $k), ['target' => 12] + $base)->assertRedirect();
        $this->assertSame(12.0, $k->fresh()->target);
        $this->get(route('kpi.admin', ['period' => '2027-Q1', 'edit' => $k->id]))->assertOk()->assertSee('Proposal Turnaround');

        $k->update(['locked' => true]);
        $this->post(route('kpi.admin.update', $k), ['target' => 99] + $base)->assertSessionHas('err');
        $this->assertSame(12.0, $k->fresh()->target);

        $this->post(route('kpi.admin.copy'), ['from' => '2027-Q1', 'to' => '2027-Q2'])->assertRedirect();
        $copy = Kpi::where(['user_id' => $emp->id, 'period' => '2027-Q2'])->firstOrFail();
        $this->assertFalse($copy->locked);
        $this->assertSame(0.0, $copy->actual);
    }

    public function test_analytics_follows_business_rules(): void
    {
        $this->as('nurzam');
        $m = app(\App\Services\WorkflowService::class)->metrics(auth()->user());
        $this->get(route('analytics', ['period' => 'all']))->assertOk()
            ->assertSee('RM '.number_format($m['revenue'], 0))
            ->assertSee('RM '.number_format($m['pipeline_value'], 0))
            ->assertSee($m['win_rate'] === null ? 'N/A' : $m['win_rate'].'%');
        $this->get(route('analytics', ['period' => 'bogus']))->assertNotFound();
    }

    public function test_standard_report_export_is_permissioned_and_audited(): void
    {
        $this->as('amir');
        $this->get(route('reports.export', 5))->assertForbidden();

        $this->as('fauzi');
        $res = $this->get(route('reports.export', ['report' => 5, 'period' => 'all']));
        $res->assertOk();
        $csv = $res->streamedContent();
        $this->assertStringContainsString('Qualified Revenue', $csv);
        $this->assertStringContainsString('Excluded', $csv); // unqualified awards contribute RM0
        $this->assertDatabaseHas('audit_logs', ['action' => 'EXPORT', 'entity_type' => 'report', 'entity_id' => 5]);

        // Pipeline report excludes closed stages
        $csv = $this->get(route('reports.export', ['report' => 2, 'period' => 'all']))->streamedContent();
        foreach (\App\Models\Opportunity::whereIn('stage', ['Lost', 'Award', 'Cancelled'])->pluck('code') as $code) {
            $this->assertStringNotContainsString($code, $csv);
        }
    }

    public function test_management_pack_export(): void
    {
        $this->as('shahirah');
        $this->get(route('reports.pack'))->assertOk()->assertDontSee('Export CSV');
        $this->get(route('reports.pack.export'))->assertForbidden();
        $this->as('stanley');
        $res = $this->get(route('reports.pack.export', ['period' => 'year']));
        $res->assertOk();
        $this->assertStringContainsString('Win Rate', $res->streamedContent());
        $this->assertDatabaseHas('audit_logs', ['action' => 'EXPORT', 'entity_type' => 'management_pack']);
    }

    public function test_report_builder_preview_generate_download(): void
    {
        Storage::fake('local');
        $def = ['module' => 'opportunity', 'fields' => ['code', 'stage', 'value'], 'group' => 'stage', 'sort' => 'value', 'dir' => 'desc', 'period' => 'all'];

        $this->as('amir'); // preview allowed, generate denied
        $this->get(route('reports.builder', $def))->assertOk()->assertSee('Preview — Opportunity')->assertDontSee('8. Generate');
        $this->post(route('reports.builder.generate'), $def)->assertForbidden();

        $this->as('fauzi');
        $this->get(route('reports.builder', ['module' => 'case']))->assertOk(); // module chosen, no fields yet
        $this->get(route('reports.builder', ['module' => 'award', 'fields' => ['code', 'qualified_revenue'], 'period' => 'custom', 'from' => '2026-01-01', 'to' => '2026-12-31']))->assertOk();
        $this->get(route('reports.builder', ['module' => 'nope', 'fields' => ['code']]))->assertSessionHasErrors('module');

        $this->post(route('reports.builder.generate'), $def)->assertRedirect();
        $run = CustomReportRun::latest('id')->firstOrFail();
        $this->assertSame('Completed', $run->status); // after-response job ran
        $this->assertSame(\App\Models\Opportunity::count(), $run->row_count);
        Storage::disk('local')->assertExists($run->file_path);

        $this->get(route('reports.builder.download', $run))->assertOk();
        $this->assertTrue(AuditLog::where(['action' => 'EXPORT', 'entity_type' => 'custom_report_run', 'entity_id' => $run->id])->exists());

        $this->role('AHOD'); // can export, but not someone else's run
        $this->get(route('reports.builder.download', $run))->assertForbidden();
    }
}
