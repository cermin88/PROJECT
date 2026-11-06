<?php

namespace Database\Seeders\Modules;

use App\Models\AdminSetting;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalType;
use App\Models\MasterDataValue;
use App\Models\PermissionRule;
use App\Models\User;
use App\Models\WorkingCalendarDay;
use App\Services\NotificationCentre;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminModuleSeeder extends Seeder
{
    public function run(): void
    {
        /* ---- Superadmin demo user (prototype has none; needed to edit the permission matrix) ---- */
        User::firstOrCreate(['email' => 'sysadmin.demo@csa.test'], [
            'code' => 'USR-SA01', 'name' => 'System Administrator', 'job_position' => 'IT Administrator',
            'role' => 'Superadmin', 'password' => Hash::make('password'),
        ]);

        /* ---- UX-069 permission matrix: prototype baseline for every module ---- */
        $now = now();
        $rows = [];
        foreach (PermissionRule::ROLES as $role) {
            foreach (PermissionRule::MODULES as $module) {
                foreach (PermissionRule::ACTIONS as $action) {
                    $rows[] = ['role' => $role, 'module' => $module, 'action' => $action,
                        'allowed' => PermissionRule::baseline($role, $action), 'created_at' => $now, 'updated_at' => $now];
                }
            }
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            PermissionRule::insertOrIgnore($chunk);
        }

        /* ---- UX-070 configuration (prototype state.config) ---- */
        foreach ([
            ['stale_opportunity_days', '30', 'int', 'Attention Thresholds', 'Stale Opportunity Days'],
            ['follow_up_threshold_days', '30', 'int', 'Attention Thresholds', 'Follow-up Threshold Days'],
            ['major_opportunity_value', '1000000', 'int', 'Attention Thresholds', 'Major Opportunity Value (RM) — needs approval'],
            ['renewal_reminders', '180,90,60,30', 'list', 'Renewal Reminders', 'Reminder days before expiry'],
            ['session_timeout_min', '30', 'int', 'Security', 'Session timeout (minutes)'],
            ['login_failures', '5', 'int', 'Security', 'Lock after failed login attempts'],
            ['weekend_days', 'Saturday,Sunday', 'list', 'Working Calendar', 'Weekend days'],
        ] as [$key, $value, $type, $group, $label]) {
            AdminSetting::firstOrCreate(['key' => $key], compact('value', 'type', 'group', 'label'));
        }

        /* ---- UX-071 approval matrix ---- */
        foreach ([
            ['Bid / No-Bid', 'HOD / AHOD', 'No self-approval'],
            ['Tender Pricing / Submission', 'HOD / AHOD', 'Final pricing before Submitted'],
            ['Event / Budget', 'HOD / AHOD', 'Immutable snapshot'],
            ['MoM', 'HOD / AHOD', 'Return distinct Reject'],
            ['KPI Manual / Lock', 'HOD / authorised reviewer', 'Evidence required'],
            ['Award / Loss', 'HOD / AHOD', 'Evidence / outcome control'],
            ['Major Opportunity Value', 'HOD / AHOD', 'Controlled threshold'],
        ] as $i => [$name, $auth, $rule]) {
            ApprovalType::firstOrCreate(['name' => $name], ['primary_authority' => $auth, 'fallback_rule' => $rule, 'sort' => $i + 1]);
        }
        // Demo delegation: no AHOD is seeded yet, so the HOD delegates to the Senior Manager – BD
        $hod = User::where('email', 'fauzi@csa.test')->first();
        $cover = User::where('email', 'nuzul@csa.test')->first();
        if ($hod && $cover && ! ApprovalDelegation::where('delegator_id', $hod->id)->exists()) {
            ApprovalDelegation::create([
                'delegator_id' => $hod->id, 'delegate_id' => $cover->id, 'created_by' => $hod->id,
                'starts_on' => today(), 'ends_on' => today()->addDays(14), 'reason' => 'Demo: HOD outstation — covering approvals',
            ]);
        }

        /* ---- UX-072 Malaysia 2026 holidays (lunar dates provisional) ---- */
        foreach ([
            ['2026-01-01', "New Year's Day", 'State', 'Selangor'], ['2026-02-01', 'Thaipusam', 'Federal', null],
            ['2026-02-17', 'Chinese New Year', 'Federal', null], ['2026-02-18', 'Chinese New Year (Day 2)', 'Federal', null],
            ['2026-03-20', 'Hari Raya Aidilfitri', 'Federal', null], ['2026-03-21', 'Hari Raya Aidilfitri (Day 2)', 'Federal', null],
            ['2026-05-01', 'Labour Day', 'Federal', null], ['2026-05-27', 'Hari Raya Haji', 'Federal', null],
            ['2026-05-31', 'Wesak Day', 'Federal', null], ['2026-06-01', "Agong's Birthday", 'Federal', null],
            ['2026-06-17', 'Awal Muharram', 'Federal', null], ['2026-08-25', 'Maulidur Rasul', 'Federal', null],
            ['2026-08-31', 'National Day', 'Federal', null], ['2026-09-16', 'Malaysia Day', 'Federal', null],
            ['2026-11-08', 'Deepavali', 'Federal', null], ['2026-12-11', "Sultan of Selangor's Birthday", 'State', 'Selangor'],
            ['2026-12-25', 'Christmas Day', 'Federal', null], ['2026-12-31', 'Company Year-End Closure', 'Company', null],
        ] as [$date, $name, $type, $state]) {
            WorkingCalendarDay::firstOrCreate(['date' => $date, 'name' => $name], compact('type', 'state'));
        }

        /* ---- UX-073 master data (prototype sets + sectors from seeded customers) ---- */
        $sets = [
            'CustomerType' => ['Federal Government', 'State Government', 'Local Municipality', 'GLC', 'Private'],
            'Sector' => ['Geospatial', 'Water', 'Forestry', 'Meteorology', 'Local Government', 'Disaster', 'Research', 'Planning', 'Utility', 'Municipal', 'Indigenous Affairs', 'Agriculture'],
            'Relationship' => ['Cold', 'Warm', 'Good', 'Strong'],
            'CasePriority' => ['Low', 'Medium', 'High', 'Critical'],
            'ActivityType' => ['Call', 'WhatsApp', 'Email', 'Physical Meeting', 'Online Meeting', 'Courtesy Visit', 'Presentation', 'Demo', 'POC', 'Workshop', 'Site Visit', 'Networking', 'Lunch', 'Tender Briefing', 'Event', 'Internal Meeting', 'Others'],
            'PartnerClass' => ['Principal', 'OEM', 'Distributor', 'Reseller', 'SI', 'Technology Partner', 'Business Partner', 'Subcontractor', 'Consultant', 'Others'],
        ];
        foreach ($sets as $set => $values) {
            foreach ($values as $i => $value) {
                MasterDataValue::firstOrCreate(['set' => $set, 'value' => $value], ['sort' => $i + 1]);
            }
        }

        /* ---- A few non-approval notifications (approval ones come from Approval events) ---- */
        foreach (User::whereIn('email', ['fauzi@csa.test', 'stanley@csa.test', 'amir@csa.test'])->get() as $u) {
            if ($u->notifications()->where('type', 'like', 'csa.reminder%')->exists()) {
                continue;
            }
            NotificationCentre::send($u, 'Renewal due within 90 days', 'Reminder', '/renewals');
            NotificationCentre::send($u, 'KPI review available', 'Information', '/kpi');
        }
    }
}
