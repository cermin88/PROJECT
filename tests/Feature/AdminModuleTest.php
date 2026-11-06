<?php

namespace Tests\Feature;

use App\Models\AdminSetting;
use App\Models\Approval;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalType;
use App\Models\MasterDataValue;
use App\Models\PermissionRule;
use App\Models\User;
use App\Models\WorkingCalendarDay;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminModuleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function role(string $role): User
    {
        if ($role === 'AHOD' && ! User::where('role', 'AHOD')->exists()) { // no AHOD in the core seed
            User::create(['code' => 'USR-T-AH', 'name' => 'Test AHOD', 'role' => 'AHOD', 'email' => 'ahod.admintest@csa.test', 'password' => 'x']);
        }
        return User::where('role', $role)->orderBy('id')->firstOrFail();
    }

    private const PAGES = ['permissions', 'configuration', 'approval-matrix', 'working-calendar', 'master-data', 'system-health'];

    public function test_admin_pages_render_for_privileged_roles_and_are_denied_to_others(): void
    {
        foreach (['HOD', 'AHOD', 'Management', 'Superadmin'] as $r) {
            foreach (self::PAGES as $p) {
                $this->actingAs($this->role($r))->get(route($p))->assertOk();
            }
        }
        foreach (['BD Manager', 'CC Executive', 'Sales Admin / Inside Sales'] as $r) {
            foreach (self::PAGES as $p) {
                $this->actingAs($this->role($r))->get(route($p))->assertForbidden();
            }
        }
    }

    public function test_permission_matrix_is_seeded_and_only_superadmin_edits(): void
    {
        $this->assertTrue(PermissionRule::allows('HOD', 'Tenders', 'Approve'));
        $this->assertFalse(PermissionRule::allows('BD Executive', 'Tenders', 'Approve'));
        $this->assertFalse(PermissionRule::allows('Management', 'Sales', 'Create'));

        $payload = ['role' => 'BD Executive', 'cells' => ['Sales' => ['View' => 1, 'Create' => 1, 'Export' => 1]]];
        $this->actingAs($this->role('HOD'))->post(route('permissions.update'), $payload)->assertForbidden();

        $this->actingAs($this->role('Superadmin'))->post(route('permissions.update'), $payload)->assertRedirect();
        $this->assertTrue(PermissionRule::allows('BD Executive', 'Sales', 'Export'));
        $this->assertFalse(PermissionRule::allows('BD Executive', 'Tenders', 'View')); // unchecked = revoked
        $this->assertDatabaseHas('audit_logs', ['action' => 'PERMISSION_MATRIX_CHANGED']);

        // Lock-out guard: Superadmin keeps Admin rights even if unticked
        $this->post(route('permissions.update'), ['role' => 'Superadmin', 'cells' => []]);
        $this->assertTrue(PermissionRule::allows('Superadmin', 'Admin', 'Admin'));
        $this->assertFalse(PermissionRule::allows('Superadmin', 'Sales', 'View'));
    }

    public function test_configuration_update_validates_and_audits(): void
    {
        $this->actingAs($this->role('Management'))->post(route('configuration.update'), [])->assertForbidden();

        $hod = $this->role('HOD');
        $all = AdminSetting::pluck('value', 'key')->all();
        $this->actingAs($hod)->post(route('configuration.update'), ['s' => ['stale_opportunity_days' => 45] + $all])->assertSessionHas('ok');
        $this->assertSame(45, AdminSetting::get('stale_opportunity_days'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'CONFIG_CHANGE', 'user_id' => $hod->id]);

        $this->post(route('configuration.update'), ['s' => ['renewal_reminders' => '90, abc'] + $all])->assertSessionHasErrors();
        $this->post(route('configuration.update'), ['s' => ['login_failures' => -1] + $all])->assertSessionHasErrors();
        $this->assertSame([180, 90, 60, 30], array_map('intval', AdminSetting::get('renewal_reminders')));
    }

    public function test_approval_types_superadmin_only(): void
    {
        $this->actingAs($this->role('HOD'))->post(route('approval-matrix.types.store'), ['name' => 'X', 'primary_authority' => 'HOD'])->assertForbidden();
        $this->actingAs($this->role('Superadmin'))->post(route('approval-matrix.types.store'), ['name' => 'Quotation', 'primary_authority' => 'HOD / AHOD'])->assertSessionHas('ok');
        $t = ApprovalType::where('name', 'Quotation')->firstOrFail();
        $this->post(route('approval-matrix.types.update', $t), ['primary_authority' => 'Management'])->assertSessionHas('ok');
        $this->assertFalse($t->fresh()->active);
        $this->assertSame('Management', $t->fresh()->primary_authority);
    }

    public function test_delegation_rules_and_wiring_into_approval_decide(): void
    {
        $hod = $this->role('HOD');
        $bdm = User::where('email', 'nuzul@csa.test')->firstOrFail(); // BD Manager: no approval authority of his own
        $requester = User::where('email', 'amir@csa.test')->firstOrFail();
        ApprovalDelegation::query()->delete();

        // BD roles cannot delegate; HOD cannot delegate someone else's rights
        $this->actingAs($bdm)->post(route('delegations.store'), [])->assertForbidden();
        $this->actingAs($this->role('Management'))->post(route('delegations.store'), [
            'delegator_id' => $hod->id, 'delegate_id' => $bdm->id, 'starts_on' => today()->toDateString(), 'ends_on' => today()->toDateString(), 'reason' => 'x',
        ])->assertForbidden();
        // End before start is rejected
        $this->actingAs($hod)->post(route('delegations.store'), [
            'delegator_id' => $hod->id, 'delegate_id' => $bdm->id, 'starts_on' => today()->addDays(3)->toDateString(), 'ends_on' => today()->toDateString(), 'reason' => 'x',
        ])->assertSessionHasErrors('ends_on');

        $a = Approval::create(['code' => 'APR-T-1', 'type' => 'Bid/No-Bid', 'requester_id' => $requester->id, 'approver_id' => $hod->id]);

        // Without delegation the BD Manager may not decide
        $this->actingAs($bdm)->post(route('approvals.decide', $a), ['decision' => 'Approved'])->assertSessionHas('err');
        $this->assertSame('Pending', $a->fresh()->status);

        $this->actingAs($hod)->post(route('delegations.store'), [
            'delegator_id' => $hod->id, 'delegate_id' => $bdm->id, 'starts_on' => today()->toDateString(), 'ends_on' => today()->addDays(5)->toDateString(), 'reason' => 'Leave',
        ])->assertSessionHas('ok');
        // Overlap blocked
        $this->post(route('delegations.store'), [
            'delegator_id' => $hod->id, 'delegate_id' => $this->role('AHOD')->id, 'starts_on' => today()->addDay()->toDateString(), 'ends_on' => today()->addDays(2)->toDateString(), 'reason' => 'x',
        ])->assertSessionHas('err');

        // Delegate sees it in the inbox and may decide it, audited on behalf of the HOD
        $this->actingAs($bdm)->get(route('approvals.index'))->assertSee('APR-T-1')->assertSee('Delegated to you');
        $this->post(route('approvals.decide', $a), ['decision' => 'Approved'])->assertSessionHas('ok');
        $this->assertSame('Approved', $a->fresh()->status);
        $log = \App\Models\AuditLog::where('action', 'APPROVAL_APPROVED')->where('entity_id', $a->id)->firstOrFail();
        $this->assertSame($hod->id, $log->detail['on_behalf_of']);

        // No self-approval still holds for a delegate
        $own = Approval::create(['code' => 'APR-T-2', 'type' => 'MoM', 'requester_id' => $bdm->id, 'approver_id' => $hod->id]);
        $this->post(route('approvals.decide', $own), ['decision' => 'Approved'])->assertSessionHas('err');
        $this->assertSame('Pending', $own->fresh()->status);

        // Revoked / expired delegation stops working
        $del = ApprovalDelegation::where('delegate_id', $bdm->id)->firstOrFail();
        $this->actingAs($bdm)->post(route('delegations.revoke', $del))->assertForbidden();
        $this->actingAs($hod)->post(route('delegations.revoke', $del))->assertSessionHas('ok');
        $b = Approval::create(['code' => 'APR-T-3', 'type' => 'MoM', 'requester_id' => $requester->id, 'approver_id' => $hod->id]);
        $this->actingAs($bdm)->post(route('approvals.decide', $b), ['decision' => 'Approved'])->assertSessionHas('err');

        ApprovalDelegation::create(['delegator_id' => $hod->id, 'delegate_id' => $bdm->id, 'starts_on' => today()->subDays(10), 'ends_on' => today()->subDay(), 'reason' => 'old']);
        $this->assertFalse(ApprovalDelegation::covers($hod->id, $bdm->id));
    }

    public function test_notifications_created_on_request_and_decision_and_mark_read(): void
    {
        $hod = $this->role('HOD');
        $req = User::where('email', 'amir@csa.test')->firstOrFail();
        $before = $hod->unreadNotifications()->count();

        $a = Approval::create(['code' => 'APR-N-1', 'type' => 'MoM', 'requester_id' => $req->id, 'approver_id' => $hod->id]);
        $this->assertSame($before + 1, $hod->unreadNotifications()->count());
        // Seeded HOD -> Senior Manager BD delegation: the delegate is told too
        $cover = User::where('email', 'nuzul@csa.test')->firstOrFail();
        $this->assertTrue($cover->notifications()->get()->contains(fn ($n) => str_contains($n->data['title'], 'APR-N-1') && $n->data['body'] === 'Delegated to you'));

        $this->actingAs($hod)->post(route('approvals.decide', $a), ['decision' => 'Rejected', 'note' => 'Missing scope']);
        $n = $req->notifications()->get()->first(fn ($n) => str_contains($n->data['title'], 'APR-N-1'));
        $this->assertNotNull($n);
        $this->assertStringContainsString('Rejected', $n->data['title']);

        $this->actingAs($req)->get(route('notifications.index'))->assertOk()->assertSee('APR-N-1');
        $this->get('/')->assertSee('Notifications');
        $this->post(route('notifications.read', $n->id))->assertRedirect('/approvals');
        $this->assertNotNull($n->fresh()->read_at);

        // Someone else's notification is not reachable
        $other = $hod->notifications()->first();
        $this->post(route('notifications.read', $other->id))->assertNotFound();

        $this->actingAs($hod)->post(route('notifications.read-all'))->assertSessionHas('ok');
        $this->assertSame(0, $hod->unreadNotifications()->count());
    }

    public function test_tender_pricing_request_notifies_hod(): void
    {
        $hod = $this->role('HOD');
        $t = \App\Models\Tender::where('status', 'PREPARATION')->first();
        if (! $t) {
            $this->markTestSkipped('No tender in PREPARATION');
        }
        Approval::where('subject_type', 'tender')->where('subject_id', $t->id)->delete();
        $before = $hod->notifications()->count();
        app(\App\Services\WorkflowService::class)->requestPricingApproval($t, User::where('email', 'amir@csa.test')->first());
        $this->assertSame($before + 1, $hod->notifications()->count());
    }

    public function test_working_calendar_crud_and_working_days(): void
    {
        $this->assertTrue(WorkingCalendarDay::whereDate('date', '2026-08-31')->exists());
        // 31 Aug 2026 (Mon, National Day) to 4 Sep (Fri) = 4 working days
        $this->assertSame(4, WorkingCalendarDay::workingDaysBetween(Carbon::parse('2026-08-31'), Carbon::parse('2026-09-04')));

        $this->actingAs($this->role('AHOD'))->post(route('working-calendar.store'), ['date' => '2026-10-23', 'name' => 'X', 'type' => 'Company'])->assertForbidden();
        $this->actingAs($this->role('Superadmin'))->post(route('working-calendar.store'), ['date' => '2026-09-01', 'name' => 'Company Offsite', 'type' => 'Company'])->assertSessionHas('ok');
        $this->assertSame(3, WorkingCalendarDay::workingDaysBetween(Carbon::parse('2026-08-31'), Carbon::parse('2026-09-04')));
        $this->post(route('working-calendar.store'), ['date' => '2026-09-01', 'name' => 'Company Offsite', 'type' => 'Company'])->assertSessionHas('err');
        $this->post(route('working-calendar.store'), ['date' => '2026-09-02', 'name' => 'State day', 'type' => 'State'])->assertSessionHasErrors('state');

        $h = WorkingCalendarDay::where('name', 'Company Offsite')->firstOrFail();
        $this->post(route('working-calendar.destroy', $h))->assertSessionHas('ok');
        $this->assertModelMissing($h);

        $this->post(route('working-calendar.weekend'), ['weekend' => ['Friday', 'Saturday']])->assertSessionHas('ok');
        $this->assertSame(['Friday', 'Saturday'], WorkingCalendarDay::weekend());
        $this->get(route('working-calendar', ['year' => 2026, 'from' => '2026-08-31', 'to' => '2026-09-04']))->assertOk()->assertSee('National Day');
    }

    public function test_master_data_add_toggle_reorder(): void
    {
        $this->actingAs($this->role('Management'))->post(route('master-data.store'), ['set' => 'Sector', 'value' => 'Defence'])->assertForbidden();
        $hod = $this->role('HOD');
        $this->actingAs($hod)->post(route('master-data.store'), ['set' => 'Sector', 'value' => 'Defence'])->assertSessionHas('ok');
        $this->post(route('master-data.store'), ['set' => 'Sector', 'value' => 'Defence'])->assertSessionHasErrors('value');
        $this->post(route('master-data.store'), ['set' => 'Nope', 'value' => 'x'])->assertSessionHasErrors('set');
        $this->assertContains('Defence', MasterDataValue::options('Sector'));

        $v = MasterDataValue::where('set', 'Sector')->where('value', 'Defence')->first();
        $this->post(route('master-data.toggle', $v))->assertSessionHas('ok');
        $this->assertNotContains('Defence', MasterDataValue::options('Sector'));
        $this->assertDatabaseHas('master_data_values', ['value' => 'Defence', 'active' => false]); // kept, not deleted

        $first = MasterDataValue::where('set', 'Relationship')->orderBy('sort')->first();
        $this->post(route('master-data.move', $first), ['dir' => 'down']);
        $this->assertNotSame($first->value, MasterDataValue::options('Relationship')[0]);
        $this->get(route('master-data', ['set' => 'Sector']))->assertOk()->assertSee('Geospatial');
    }

    public function test_system_health_reports_checks(): void
    {
        $this->actingAs($this->role('Superadmin'))->get(route('system-health'))
            ->assertOk()->assertSee('Database')->assertSee('Cache')->assertSee('Storage')->assertSee(app()->version());
    }

    public function test_demo_control_and_reset_routes_are_removed(): void
    {
        $this->assertFalse(Route::has('demo-control'));
        $this->actingAs($this->role('HOD'))->get('/admin/demo-control')->assertNotFound();
        $this->post('/admin/demo-control/reset')->assertNotFound();
    }

    public function test_global_search_and_header(): void
    {
        $u = User::where('email', 'amir@csa.test')->firstOrFail();
        $this->actingAs($u)->get(route('search', ['q' => 'CUS-000001']))->assertOk()->assertSee('Customer');
        $this->get(route('search', ['q' => 'MBJB']))->assertOk()->assertSee('Majlis Bandaraya Johor Bahru');
        $this->get(route('search', ['q' => 'x']))->assertOk()->assertSee('at least 2 characters');
        $this->get(route('search', ['q' => 'zzzzqqq']))->assertOk()->assertSee('No results');

        // Header: current page context, notifications, quick create, and own profile
        $this->actingAs($this->role('HOD'))->get('/')->assertOk()
            ->assertSee('Role Dashboard')
            ->assertSee('Notifications')
            ->assertSee('Quick Create')
            ->assertSee(route('profile.show'), false);
        // Case quick-create only for CC roles / HOD / Superadmin
        $this->actingAs($u)->get(route('search'))->assertDontSee('>Case</a>', false);
    }
}
