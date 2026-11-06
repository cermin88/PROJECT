<?php

namespace App\Http\Controllers\Governance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Services\Governance\DuplicateDetector;

// UX-061 Data Quality Centre: completeness checks over live (non-archived) records
class DataQualityController extends Controller
{
    public function index(DuplicateDetector $dup)
    {
        $checks = $this->checks();
        $customers = Customer::whereNull('archived_at')->where('archived', false)->get();
        $contacts = Contact::whereNull('archived_at')->get();
        $opps = Opportunity::active()->whereNull('archived_at')->get();

        return view('governance.dataquality', [
            'checks' => $checks,
            'completeness' => [
                'Customers' => $this->completeness($customers, ['type', 'sector', 'state', 'owner_id', 'last_engagement_at']),
                'Contacts' => $this->completeness($contacts, ['email', 'mobile', 'buying_role', 'division']),
                'Active Opportunities' => $this->completeness($opps, ['owner_id', 'next_action', 'next_action_date', 'value']),
            ],
            'dupCustomers' => $dup->customers(),
            'dupContacts' => $dup->contacts(),
            'archivedCount' => collect(array_keys(ArchiveController::TYPES))->sum(fn ($k) => ArchiveController::archived($k)->count()),
            'canDup' => in_array(auth()->user()->role, ['HOD', 'AHOD', 'Management', 'Superadmin'], true),
        ]);
    }

    // CSV of every gap row; logged as EXPORT so it shows in Export History
    public function export()
    {
        // Exports follow the prototype canExport() rule (Management / HOD / AHOD / Superadmin)
        abort_unless(\App\Http\Controllers\Intelligence\ReportController::canExport(), 403);
        $checks = $this->checks();
        $rows = 0;
        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['Check', 'Entity', 'Record', 'Name']);
        foreach ($checks as $c) {
            foreach ($c['rows'] as $r) {
                fputcsv($csv, [$c['label'], $c['entity'], $r['code'], $r['name']]);
                $rows++;
            }
        }
        rewind($csv);
        AuditLog::record('EXPORT', 'data_quality', null, ['module' => 'Data Quality', 'rows' => $rows, 'file' => 'data-quality-gaps.csv']);
        return response(stream_get_contents($csv), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="data-quality-gaps.csv"',
        ]);
    }

    private function checks(): array
    {
        $cust = fn () => Customer::whereNull('archived_at')->where('archived', false);
        $cRow = fn ($c) => ['code' => $c->code, 'name' => $c->name, 'url' => route('customers.show', $c)];
        $ctRow = fn ($c) => ['code' => 'Contact #'.$c->id, 'name' => $c->name.' ('.($c->customer?->name ?? '—').')', 'url' => route('customers.show', $c->customer_id)];
        $oRow = fn ($o) => ['code' => $o->code, 'name' => $o->name, 'url' => null];
        $today = now()->startOfDay();

        return [
            ['label' => 'Customer owner gaps (no owner or inactive owner)', 'entity' => 'Customer', 'rows' => $cust()
                ->where(fn ($q) => $q->whereNull('owner_id')->orWhereHas('owner', fn ($u) => $u->where('active', false)))->get()->map($cRow)],
            ['label' => 'Customers missing type / sector / state', 'entity' => 'Customer', 'rows' => $cust()
                ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '')->orWhereNull('sector')->orWhere('sector', '')->orWhereNull('state')->orWhere('state', ''))->get()->map($cRow)],
            ['label' => 'Customers with no contacts', 'entity' => 'Customer', 'rows' => $cust()
                ->whereDoesntHave('contacts', fn ($q) => $q->whereNull('archived_at'))->get()->map($cRow)],
            ['label' => 'Customers with no engagement in 90 days', 'entity' => 'Customer', 'rows' => $cust()
                ->where(fn ($q) => $q->whereNull('last_engagement_at')->orWhere('last_engagement_at', '<', now()->subDays(90)))->get()->map($cRow)],
            ['label' => 'Contacts with no email and no mobile', 'entity' => 'Contact', 'rows' => Contact::with('customer')->whereNull('archived_at')
                ->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))->where(fn ($q) => $q->whereNull('mobile')->orWhere('mobile', ''))->get()->map($ctRow)],
            ['label' => 'Contacts with invalid email', 'entity' => 'Contact', 'rows' => Contact::with('customer')->whereNull('archived_at')
                ->whereNotNull('email')->where('email', '!=', '')->get()->filter(fn ($c) => ! filter_var($c->email, FILTER_VALIDATE_EMAIL))->values()->map($ctRow)],
            ['label' => 'Active opportunities missing next action / date', 'entity' => 'Opportunity', 'rows' => Opportunity::active()->whereNull('archived_at')
                ->where(fn ($q) => $q->whereNull('next_action')->orWhere('next_action', '')->orWhereNull('next_action_date'))->get()->map($oRow)],
            ['label' => 'Active opportunities with overdue next action', 'entity' => 'Opportunity', 'rows' => Opportunity::active()->whereNull('archived_at')
                ->whereNotNull('next_action_date')->where('next_action_date', '<', $today)->get()->map($oRow)],
            ['label' => 'Active opportunities with no owner or zero value', 'entity' => 'Opportunity', 'rows' => Opportunity::active()->whereNull('archived_at')
                ->where(fn ($q) => $q->whereNull('owner_id')->orWhere('value', '<=', 0))->get()->map($oRow)],
        ];
    }

    // % of the listed fields that are filled across all rows
    private function completeness($rows, array $fields): array
    {
        $total = $rows->count() * count($fields);
        $filled = 0;
        foreach ($rows as $r) {
            foreach ($fields as $f) {
                $v = $r->{$f};
                $filled += ($v !== null && $v !== '' && ! (is_numeric($v) && $f === 'value' && $v <= 0)) ? 1 : 0;
            }
        }
        return ['count' => $rows->count(), 'pct' => $total ? round($filled / $total * 100) : 100];
    }
}
