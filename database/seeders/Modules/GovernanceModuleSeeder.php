<?php

namespace Database\Seeders\Modules;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Database\Seeder;

// Demo data for Data Quality / Duplicates / Archive / Export History (UX-061..066)
class GovernanceModuleSeeder extends Seeder
{
    public function run(): void
    {
        $hod = User::where('email', 'fauzi@csa.test')->first();
        $amir = User::where('email', 'amir@csa.test')->first();
        $jupem = Customer::where('code', 'CUS-000001')->first();
        if (! $hod || ! $jupem) {
            return; // core seed missing
        }

        // Likely duplicate customer (same acronym, "&" spelling) with one contact and no owner -> owner gap
        $dup = Customer::create([
            'code' => WorkflowService::nextCode('CUS', 'customers'), 'name' => 'Jabatan Ukur & Pemetaan Malaysia (JUPEM)',
            'type' => 'Federal Government', 'sector' => null, 'state' => 'Federal', 'relationship' => 'Warm', 'owner_id' => null,
        ]);
        $first = $jupem->contacts()->orderBy('id')->first();
        Contact::create(['customer_id' => $dup->id, 'name' => $first?->name ?? 'Dato Ahmad 1', 'email' => strtoupper($first?->email ?? 'contact1@example.gov.my'),
            'mobile' => null, 'buying_role' => 'Decision Maker']);
        // Contact with no way to reach them
        Contact::create(['customer_id' => $jupem->id, 'name' => 'Encik Tanpa Maklumat', 'buying_role' => 'Influencer']);

        // Next-action gap on one active opportunity
        Opportunity::active()->orderBy('id')->skip(2)->first()?->update(['next_action' => null, 'next_action_date' => null]);

        // An archived record so Archive View is not empty
        Document::where('code', 'DOC-2026-000014')->first()?->forceFill([
            'archived_at' => now()->subDays(3), 'archived_by' => $hod->id, 'archive_reason' => 'Obsolete duplicate upload',
        ])->save();

        // Documents: context ids and one version chain (v1 Superseded -> v2 Draft)
        $opp = Opportunity::orderBy('id')->first();
        Document::whereNull('context_code')->update(['context_code' => $opp?->code]);
        $old = Document::where('code', 'DOC-2026-000005')->first();
        $new = Document::where('code', 'DOC-2026-000012')->first();
        if ($old && $new) {
            $old->update(['status' => 'Superseded', 'version' => '1.0', 'title' => 'Project UAT Document 5']);
            $new->update(['supersedes_id' => $old->id, 'version' => '2.0', 'status' => 'Draft', 'title' => 'Project UAT Document 5', 'category' => 'Project', 'restricted' => false]);
        }

        // Export history rows (the prototype logs EXPORT for customer list, reports and the management pack)
        foreach ([
            [$amir?->id ?? $hod->id, 'customer', ['module' => 'Customers', 'rows' => 16, 'file' => 'customers.csv', 'filters' => 'none'], 5],
            [$hod->id, 'report', ['module' => 'Standard Reports', 'rows' => 20, 'file' => 'RPT-001.csv', 'report' => 'Pipeline by Stage'], 2],
            [$hod->id, 'management_pack', ['module' => 'Management Pack', 'rows' => 1, 'period' => 'Current Quarter'], 1],
        ] as [$uid, $entity, $detail, $days]) {
            AuditLog::create(['user_id' => $uid, 'action' => 'EXPORT', 'entity_type' => $entity, 'detail' => $detail, 'ip' => '127.0.0.1', 'created_at' => now()->subDays($days)]);
        }
    }
}
