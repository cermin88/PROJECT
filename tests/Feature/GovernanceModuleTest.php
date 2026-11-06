<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Document;
use App\Models\GovernanceImport;
use App\Models\Opportunity;
use App\Models\SupportCase;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GovernanceModuleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function u(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function ahod(): User
    {
        return User::where('role', 'AHOD')->first()
            ?? User::create(['code' => 'USR-T01', 'name' => 'Test AHOD', 'email' => 'gov-ahod@csa.test', 'password' => 'password', 'role' => 'AHOD']);
    }

    public function test_pages_render_and_role_gates(): void
    {
        $hod = $this->u('fauzi@csa.test');
        $bd = $this->u('amir@csa.test');
        $cc = $this->u('shahirah@csa.test');
        $ahod = $this->ahod();

        foreach (['dataquality', 'archive', 'exports.index', 'documents.index'] as $r) {
            $this->actingAs($cc)->get(route($r))->assertOk();
            $this->actingAs($hod)->get(route($r))->assertOk();
        }
        foreach (['duplicates.index', 'reassignment', 'import.index'] as $r) {
            $this->actingAs($hod)->get(route($r))->assertOk();
            $this->actingAs($ahod)->get(route($r))->assertOk();
            $this->actingAs($bd)->get(route($r))->assertForbidden();
            $this->actingAs($cc)->get(route($r))->assertForbidden();
        }
        // existing governance pages still work
        $this->actingAs($hod)->get(route('audit'))->assertOk();
        $this->actingAs($hod)->get(route('users'))->assertOk();
        $this->actingAs($bd)->get(route('ecosystem'))->assertOk();
    }

    public function test_data_quality_shows_seeded_gaps_and_export_is_logged(): void
    {
        $hod = $this->u('fauzi@csa.test');
        $this->actingAs($hod)->get(route('dataquality'))->assertOk()
            ->assertSee('Same name')->assertSee('Contacts with no email and no mobile');

        $res = $this->actingAs($hod)->get(route('dataquality.export'));
        $res->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Encik Tanpa Maklumat', $res->getContent());
        $this->assertDatabaseHas('audit_logs', ['action' => 'EXPORT', 'entity_type' => 'data_quality', 'user_id' => $hod->id]);

        // shows up in export history, and only own rows for non-privileged roles
        $this->actingAs($hod)->get(route('exports.index'))->assertSee('data-quality-gaps.csv')->assertSee('RPT-001.csv');
        $this->actingAs($this->u('shahirah@csa.test'))->get(route('exports.index'))->assertDontSee('RPT-001.csv');
    }

    public function test_merge_customer_repoints_children_and_archives_loser(): void
    {
        $keep = Customer::where('code', 'CUS-000001')->first();
        $dup = Customer::where('name', 'Jabatan Ukur & Pemetaan Malaysia (JUPEM)')->first();
        $opp = Opportunity::where('customer_id', $keep->id)->first() ?? Opportunity::first();
        $opp->update(['customer_id' => $dup->id]);
        $dupContacts = $dup->contacts()->count();
        $before = $keep->contacts()->count();
        $payload = ['type' => 'customer', 'keep_id' => $keep->id, 'merge_id' => $dup->id, 'note' => 'same agency'];

        $this->actingAs($this->u('fauzi@csa.test'))->get(route('duplicates.index', ['a' => $keep->id, 'b' => $dup->id]))->assertOk()->assertSee('Merge B into A');
        $this->actingAs($this->ahod())->post(route('duplicates.merge'), $payload)->assertForbidden(); // AHOD can view, not merge
        $this->actingAs($this->u('fauzi@csa.test'))->post(route('duplicates.merge'), $payload)->assertRedirect();

        $this->assertSame($before + $dupContacts, $keep->contacts()->count());
        $this->assertSame($keep->id, $opp->fresh()->customer_id);
        $dup->refresh();
        $this->assertNotNull($dup->archived_at);
        $this->assertTrue($dup->archived);
        $this->assertDatabaseHas('audit_logs', ['action' => 'MERGE', 'entity_type' => 'customer', 'entity_id' => $keep->id]);
        // merged pair no longer listed; archived record can't be merged again
        $this->actingAs($this->u('fauzi@csa.test'))->post(route('duplicates.merge'), $payload)->assertSessionHas('err');
    }

    public function test_merge_contact_and_keep_separate(): void
    {
        $hod = $this->u('fauzi@csa.test');
        $a = Contact::orderBy('id')->first();
        $b = Contact::whereRaw('lower(email) = ?', [strtolower($a->email)])->where('id', '!=', $a->id)->firstOrFail();
        $case = SupportCase::first();
        $case->update(['contact_id' => $b->id]);

        $this->actingAs($hod)->post(route('duplicates.merge'), ['type' => 'contact', 'keep_id' => $a->id, 'merge_id' => $b->id])->assertRedirect();
        $this->assertSame($a->id, $case->fresh()->contact_id);
        $this->assertNotNull($b->fresh()->archived_at);

        // Keep separate: AHOD allowed, pair drops off the candidate list
        $c1 = Customer::where('code', 'CUS-000002')->first();
        $c2 = Customer::where('code', 'CUS-000003')->first();
        $this->actingAs($this->ahod())->post(route('duplicates.separate'), ['type' => 'customer', 'keep_id' => $c1->id, 'merge_id' => $c2->id])->assertRedirect();
        $this->assertDatabaseHas('governance_duplicate_decisions', ['decision' => 'KEEP_SEPARATE', 'keep_id' => $c1->id]);
        $this->actingAs($this->u('amir@csa.test'))->post(route('duplicates.separate'), ['type' => 'customer', 'keep_id' => $c1->id, 'merge_id' => $c2->id])->assertForbidden();
    }

    public function test_archive_and_restore(): void
    {
        $hod = $this->u('fauzi@csa.test');
        $tender = Tender::first();
        $this->actingAs($this->u('amir@csa.test'))->post(route('archive.store'), ['type' => 'tender', 'key' => $tender->code, 'reason' => 'x'])->assertForbidden();
        $this->actingAs($hod)->post(route('archive.store'), ['type' => 'tender', 'key' => $tender->code, 'reason' => 'Cancelled programme'])->assertSessionHas('ok');
        $this->assertNotNull($tender->fresh()->archived_at);
        $this->actingAs($hod)->get(route('archive'))->assertSee($tender->code)->assertSee('Cancelled programme');
        $this->actingAs($hod)->post(route('archive.store'), ['type' => 'tender', 'key' => $tender->code, 'reason' => 'again'])->assertSessionHas('err');

        $cust = Customer::where('code', 'CUS-000004')->first();
        $this->actingAs($hod)->post(route('archive.store'), ['type' => 'customer', 'key' => $cust->code, 'reason' => 'Defunct']);
        $this->assertTrue($cust->fresh()->archived); // legacy flag kept in sync for the Customers list
        $this->actingAs($hod)->get(route('customers.index'))->assertDontSee($cust->name);

        $this->actingAs($this->u('shahirah@csa.test'))->post(route('archive.restore'), ['type' => 'customer', 'id' => $cust->id])->assertForbidden();
        $this->actingAs($hod)->post(route('archive.restore'), ['type' => 'customer', 'id' => $cust->id])->assertSessionHas('ok');
        $this->assertNull($cust->fresh()->archived_at);
        $this->assertFalse($cust->fresh()->archived);
        $this->assertDatabaseHas('audit_logs', ['action' => 'RESTORE', 'entity_type' => 'customer', 'entity_id' => $cust->id]);

        // customers archived only via the legacy flag (Customers module) still appear and can be restored
        $legacy = Customer::where('code', 'CUS-000005')->first();
        $legacy->update(['archived' => true]);
        $this->actingAs($hod)->get(route('archive'))->assertSee($legacy->code);
        $this->actingAs($hod)->post(route('archive.restore'), ['type' => 'customer', 'id' => $legacy->id])->assertSessionHas('ok');
        $this->assertFalse($legacy->fresh()->archived);
    }

    public function test_bulk_reassignment(): void
    {
        $hod = $this->u('fauzi@csa.test');
        $from = $this->u('amir@csa.test');
        $to = $this->u('nuzul@csa.test');
        $owned = Customer::where('owner_id', $from->id)->count();
        $this->assertGreaterThan(0, $owned);

        $this->actingAs($hod)->get(route('reassignment', ['from' => $from->id, 'to' => $to->id]))->assertOk()->assertSee('Execute Reassignment');
        $payload = ['from' => $from->id, 'to' => $to->id, 'types' => ['customers', 'opportunities', 'tenders'], 'reason' => 'Resigned'];
        $this->actingAs($this->ahod())->post(route('reassignment.execute'), $payload)->assertForbidden();
        $this->actingAs($hod)->post(route('reassignment.execute'), ['to' => $from->id] + $payload)->assertSessionHasErrors('to'); // same owner

        $this->actingAs($hod)->post(route('reassignment.execute'), $payload)->assertSessionHas('ok');
        $this->assertSame(0, Customer::where('owner_id', $from->id)->count());
        $this->assertSame(0, Opportunity::where('owner_id', $from->id)->count());
        $log = AuditLog::where('action', 'BULK_REASSIGN')->latest('id')->first();
        $this->assertSame($to->id, $log->detail['to']);
        $this->assertCount($owned, $log->detail['records']['customers']);
    }

    public function test_import_wizard_customers(): void
    {
        Storage::fake('local');
        $hod = $this->u('fauzi@csa.test');
        $csv = "Org,Kind,Sector,State\nDemo Digital Agency,Federal Government,Digital,Federal\nJabatan Ukur dan Pemetaan Malaysia (JUPEM),Federal Government,Geospatial,Federal\n,Private,Technology,Selangor\nNo Type Sdn Bhd,,Tech,Selangor\n";
        $file = UploadedFile::fake()->createWithContent('cust.csv', $csv);

        $this->actingAs($this->u('amir@csa.test'))->post(route('import.upload'), ['entity' => 'customers', 'file' => $file])->assertForbidden();
        $this->actingAs($hod)->post(route('import.upload'), ['entity' => 'customers', 'file' => $file])->assertRedirect();
        $batch = GovernanceImport::latest('id')->first();
        Storage::disk('local')->assertExists($batch->file_path);
        $this->assertSame(4, $batch->row_count);

        // commit before preview is refused
        $this->actingAs($hod)->post(route('import.commit', $batch))->assertSessionHas('err');
        $this->actingAs($hod)->post(route('import.map', $batch), ['map' => ['name' => '', 'type' => 1]])->assertSessionHasErrors();
        $this->actingAs($hod)->post(route('import.map', $batch), ['map' => ['name' => 0, 'type' => 1, 'sector' => 2, 'state' => 3, 'owner_email' => '']])->assertRedirect();
        $batch->refresh();
        $this->assertSame(['Valid' => 1, 'Warning' => 1, 'Duplicate' => 1, 'Error' => 1], $batch->counts());
        $this->actingAs($hod)->get(route('import.show', $batch))->assertOk()->assertSee('Customer already exists');

        $count = Customer::count();
        $this->actingAs($hod)->post(route('import.commit', $batch))->assertSessionHas('ok');
        $this->assertSame($count + 1, Customer::count());
        $this->assertDatabaseHas('customers', ['name' => 'Demo Digital Agency', 'owner_id' => $hod->id]);
        $this->assertDatabaseMissing('customers', ['name' => 'No Type Sdn Bhd']);
        $this->actingAs($hod)->post(route('import.commit', $batch))->assertSessionHas('err'); // no double commit
        $this->assertDatabaseHas('audit_logs', ['action' => 'IMPORT_COMMIT', 'entity_id' => $batch->id]);
    }

    public function test_import_wizard_contacts_with_warnings(): void
    {
        Storage::fake('local');
        $hod = $this->u('fauzi@csa.test');
        $csv = "Customer Code or Name,Contact Name,Email,Mobile\nCUS-000002,New Person,new.person@example.gov.my,\nCUS-000002,Phone Only,,\nNOPE-1,Ghost,ghost@example.com,\nCUS-000003,Dup,contact1@example.gov.my,\nCUS-000003,Bad,not-an-email,\n";
        $this->actingAs($hod)->post(route('import.upload'), ['entity' => 'contacts', 'file' => UploadedFile::fake()->createWithContent('c.csv', $csv)]);
        $batch = GovernanceImport::latest('id')->first();
        $this->assertSame(0, $batch->mapping['customer']); // auto-mapped from header
        $this->actingAs($hod)->post(route('import.map', $batch), ['map' => $batch->mapping]);
        $this->assertSame(['Valid' => 1, 'Warning' => 1, 'Duplicate' => 1, 'Error' => 2], $batch->fresh()->counts());
        $this->actingAs($hod)->post(route('import.commit', $batch), ['include_warnings' => 1]);
        $this->assertSame(2, $batch->fresh()->committed_count);
        $this->assertDatabaseHas('contacts', ['name' => 'Phone Only']);
    }

    public function test_documents_upload_versions_status_and_restricted_rule(): void
    {
        Storage::fake('local');
        $hod = $this->u('fauzi@csa.test');
        $bd = $this->u('amir@csa.test');

        // BD may add a standard document with a file, but not a restricted one
        $this->actingAs($bd)->post(route('documents.store'), ['title' => 'Secret', 'context_code' => 'OPP-2026-000001', 'category' => 'Proposal', 'restricted' => 1])->assertForbidden();
        $this->actingAs($bd)->post(route('documents.store'), ['title' => 'BD Proposal', 'context_code' => 'OPP-2026-000001', 'category' => 'Proposal',
            'file' => UploadedFile::fake()->create('proposal.pdf', 50, 'application/pdf')])->assertRedirect();
        $doc = Document::where('title', 'BD Proposal')->firstOrFail();
        $this->assertSame('Draft', $doc->status);
        Storage::disk('local')->assertExists($doc->file_path);
        $this->actingAs($bd)->get(route('documents.download', $doc))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'DOCUMENT_DOWNLOAD', 'entity_id' => $doc->id]);

        // Workflow: BD submits for review, cannot approve; HOD approves; illegal jump refused
        $this->actingAs($bd)->post(route('documents.status', $doc), ['status' => 'Approved'])->assertSessionHas('err');
        $this->actingAs($bd)->post(route('documents.status', $doc), ['status' => 'Under Review'])->assertSessionHas('ok');
        $this->actingAs($bd)->post(route('documents.status', $doc), ['status' => 'Approved'])->assertForbidden();
        $this->actingAs($hod)->post(route('documents.status', $doc), ['status' => 'Approved'])->assertSessionHas('ok');
        $this->actingAs($hod)->post(route('documents.status', $doc), ['status' => 'Final'])->assertSessionHas('ok');

        // New version supersedes the old one and carries the file forward
        $this->actingAs($bd)->post(route('documents.version', $doc))->assertRedirect();
        $v2 = Document::where('supersedes_id', $doc->id)->firstOrFail();
        $this->assertSame(['2.0', 'Draft', 'Superseded'], [$v2->version, $v2->status, $doc->fresh()->status]);
        $this->assertSame($doc->file_path, $v2->file_path);
        $this->actingAs($bd)->post(route('documents.version', $doc))->assertSessionHas('err'); // only the latest
        $this->actingAs($bd)->get(route('documents.show', $v2))->assertOk()->assertSee('Version history');

        // Restricted: hidden from list, every access attempt denied and audited
        $r = Document::where('restricted', true)->whereNull('archived_at')->first();
        $this->actingAs($bd)->get(route('documents.index'))->assertDontSee($r->title.'<')->assertSee('restricted document(s) are not listed');
        $this->actingAs($hod)->get(route('documents.index'))->assertSee($r->title);
        foreach ([['get', 'documents.show'], ['get', 'documents.download'], ['post', 'documents.version'], ['post', 'documents.status']] as [$m, $route]) {
            $this->actingAs($bd)->{$m}(route($route, $r), ['status' => 'Under Review'])->assertForbidden();
        }
        $this->assertSame(4, AuditLog::where('action', 'ACCESS_DENIED')->where('entity_id', $r->id)->count());
        $this->actingAs($hod)->get(route('documents.show', $r))->assertOk();
    }
}
