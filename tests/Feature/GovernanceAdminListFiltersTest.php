<?php

namespace Tests\Feature;

use App\Models\ApprovalDelegation;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\GovernanceImport;
use App\Models\MasterDataValue;
use App\Models\Tender;
use App\Models\User;
use App\Models\WorkingCalendarDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Filter bar + pagination on the Governance / Admin / Documents list pages
class GovernanceAdminListFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function hod(): User
    {
        return User::where('email', 'fauzi@csa.test')->firstOrFail();
    }

    private function superadmin(): User
    {
        return User::where('role', 'Superadmin')->orderBy('id')->firstOrFail();
    }

    public function test_documents_filter_and_pagination(): void
    {
        foreach (range(1, 22) as $i) {
            Document::create(['code' => sprintf('DOC-T%03d', $i), 'title' => "Filler doc {$i}", 'context_code' => 'CTX-T', 'category' => 'MoM', 'version' => '1.0', 'status' => 'Draft', 'restricted' => false]);
        }
        Document::create(['code' => 'DOC-TZZZ', 'title' => 'Zebra Final Report', 'context_code' => 'CTX-Z', 'category' => 'KPI', 'version' => '1.0', 'status' => 'Final', 'restricted' => false]);

        $this->actingAs($this->hod())->get(route('documents.index', ['q' => 'Zebra']))
            ->assertOk()->assertSee('Zebra Final Report')->assertDontSee('Filler doc 1<', false);
        $this->get(route('documents.index', ['status' => 'Final', 'category' => 'MoM']))->assertOk()->assertDontSee('Zebra Final Report');
        $this->get(route('documents.index', ['status' => 'Bogus']))->assertOk(); // unknown values are ignored
        $this->get(route('documents.index', ['page' => 2]))->assertOk();
        $this->get(route('documents.index', ['from' => '2000-01-01', 'to' => '2000-01-02']))->assertOk()->assertSee('No documents match');
    }

    public function test_archive_filter_and_pagination(): void
    {
        $hod = $this->hod();
        $tenders = Tender::whereNull('archived_at')->take(2)->get();
        $this->actingAs($hod)->post(route('archive.store'), ['type' => 'tender', 'key' => $tenders[0]->code, 'reason' => 'Alpha reason']);
        $this->post(route('archive.store'), ['type' => 'tender', 'key' => $tenders[1]->code, 'reason' => 'Beta reason']);

        $this->get(route('archive', ['q' => 'alpha']))->assertOk()->assertSee('Alpha reason')->assertDontSee('Beta reason');
        $this->get(route('archive', ['type' => 'customer']))->assertOk()->assertDontSee('Alpha reason');
        $this->get(route('archive', ['by' => $hod->id, 'from' => today()->toDateString()]))->assertOk()->assertSee('Beta reason');
        $this->get(route('archive', ['page' => 2]))->assertOk();
    }

    public function test_duplicates_filter_and_pagination(): void
    {
        $this->actingAs($this->hod())->get(route('duplicates.index', ['q' => 'zzz-no-such-record']))->assertOk()->assertSee('No open duplicate candidates');
        $this->get(route('duplicates.index', ['type' => 'contact', 'reason' => 'Same email']))->assertOk();
        $this->get(route('duplicates.index', ['page' => 2]))->assertOk();
    }

    public function test_audit_filters_and_pagination(): void
    {
        $hod = $this->hod();
        $other = User::where('email', 'amir@csa.test')->firstOrFail();
        foreach (range(1, 55) as $i) {
            AuditLog::create(['user_id' => $other->id, 'action' => 'FILLER_EVENT', 'entity_type' => 'tender', 'entity_id' => $i]);
        }
        AuditLog::create(['user_id' => $hod->id, 'action' => 'UNIQUE_MARKER', 'entity_type' => 'kpi', 'entity_id' => 777]);

        $this->actingAs($hod)->get(route('audit', ['action' => 'UNIQUE_MARKER']))->assertOk()->assertSee('UNIQUE_MARKER')->assertDontSee('FILLER_EVENT</td>', false);
        $this->get(route('audit', ['user_id' => $other->id, 'entity_type' => 'tender']))->assertOk()->assertSee('FILLER_EVENT')->assertDontSee('UNIQUE_MARKER</td>', false);
        $this->get(route('audit', ['entity_type' => 'kpi', 'from' => today()->toDateString(), 'to' => today()->toDateString()]))->assertOk()->assertSee('UNIQUE_MARKER');
        $this->get(route('audit', ['from' => '2000-01-01', 'to' => '2000-01-01']))->assertOk()->assertSee('No audit entries match');
        $this->get(route('audit', ['page' => 2]))->assertOk()->assertSee('FILLER_EVENT');
    }

    public function test_users_filters_and_pagination(): void
    {
        foreach (range(1, 22) as $i) {
            User::create(['code' => sprintf('USR-F%02d', $i), 'name' => "Filler User {$i}", 'email' => "filler{$i}@csa.test", 'password' => 'x', 'role' => 'BD Executive']);
        }
        User::create(['code' => 'USR-FZZ', 'name' => 'Inactive Ivy', 'email' => 'ivy@csa.test', 'password' => 'x', 'role' => 'CC Executive', 'active' => false]);

        $this->actingAs($this->hod())->get(route('users', ['q' => 'Ivy']))->assertOk()->assertSee('Inactive Ivy')->assertDontSee('Filler User');
        $this->get(route('users', ['active' => '0']))->assertOk()->assertSee('Inactive Ivy')->assertDontSee('Filler User');
        $this->get(route('users', ['role' => 'BD Executive']))->assertOk()->assertDontSee('Inactive Ivy');
        $this->get(route('users', ['page' => 2]))->assertOk();
    }

    public function test_import_batches_filter_and_pagination(): void
    {
        $hod = $this->hod();
        foreach (range(1, 21) as $i) {
            GovernanceImport::create(['entity' => 'customers', 'file_name' => "filler-{$i}.csv", 'file_path' => 'imports/x.csv', 'headers' => [], 'row_count' => 1, 'user_id' => $hod->id]);
        }
        GovernanceImport::create(['entity' => 'contacts', 'file_name' => 'special-contacts.csv', 'file_path' => 'imports/y.csv', 'headers' => [], 'row_count' => 1, 'user_id' => $hod->id]);

        $this->actingAs($hod)->get(route('import.index', ['entity' => 'contacts']))->assertOk()->assertSee('special-contacts.csv')->assertDontSee('filler-1.csv');
        $this->get(route('import.index', ['q' => 'filler-2']))->assertOk()->assertDontSee('special-contacts.csv');
        $this->get(route('import.index', ['page' => 2]))->assertOk()->assertSee('filler-1.csv');
    }

    public function test_delegations_filter_and_pagination(): void
    {
        $sa = $this->superadmin();
        $hod = $this->hod();
        $delegate = User::where('email', 'amir@csa.test')->firstOrFail();
        ApprovalDelegation::create(['delegator_id' => $hod->id, 'delegate_id' => $delegate->id, 'starts_on' => today()->subDays(30), 'ends_on' => today()->subDays(20), 'reason' => 'Old leave trip']);
        ApprovalDelegation::create(['delegator_id' => $hod->id, 'delegate_id' => $delegate->id, 'starts_on' => today(), 'ends_on' => today()->addDays(3), 'reason' => 'Current conference']);

        $this->actingAs($sa)->get(route('approval-matrix', ['status' => 'Expired']))->assertOk()->assertSee('Old leave trip')->assertDontSee('Current conference');
        $this->get(route('approval-matrix', ['q' => 'conference']))->assertOk()->assertSee('Current conference')->assertDontSee('Old leave trip');
        $this->get(route('approval-matrix', ['from' => today()->toDateString()]))->assertOk()->assertDontSee('Old leave trip');
        $this->get(route('approval-matrix', ['page' => 2]))->assertOk();
    }

    public function test_master_data_filters(): void
    {
        $this->actingAs($this->hod())->get(route('master-data', ['q' => 'Geospatial']))->assertOk()->assertSee('Geospatial');
        $other = MasterDataValue::where('set', 'Relationship')->firstOrFail();
        $this->get(route('master-data', ['q' => 'Geospatial']))->assertDontSee('>'.$other->value.'<', false);
        $this->get(route('master-data', ['status' => 'inactive', 'q' => 'Geospatial']))->assertOk()->assertSee('No values match');
    }

    public function test_working_calendar_filter_and_pagination(): void
    {
        foreach (range(1, 25) as $i) {
            WorkingCalendarDay::create(['date' => now()->startOfYear()->addDays($i)->toDateString(), 'name' => "Filler Holiday {$i}", 'type' => 'Company']);
        }
        $year = now()->year;
        $this->actingAs($this->hod())->get(route('working-calendar', ['year' => $year, 'type' => 'Federal']))->assertOk()->assertDontSee('Filler Holiday');
        $this->get(route('working-calendar', ['year' => $year, 'q' => 'Filler Holiday 7']))->assertOk()->assertSee('Filler Holiday 7')->assertDontSee('Filler Holiday 8');
        $this->get(route('working-calendar', ['year' => $year, 'page' => 2]))->assertOk();
    }

    public function test_search_type_filter_and_pagination(): void
    {
        $u = User::where('email', 'amir@csa.test')->firstOrFail();
        $this->actingAs($u)->get(route('search', ['q' => 'CUS-0', 'type' => 'Customer']))->assertOk()->assertSee('Customer')->assertDontSee('<h3 style="margin-top:0">Tender', false);
        $this->get(route('search', ['q' => 'CUS-0', 'type' => 'Customer', 'page' => 2]))->assertOk();
        $this->get(route('search', ['q' => 'CUS-0', 'type' => 'Nope']))->assertOk(); // unknown type = all types
    }
}
