<?php

namespace Database\Seeders\Modules;

use App\Models\Kpi;
use App\Models\KpiAdjustment;
use App\Models\User;
use Illuminate\Database\Seeder;

// KPI periods, ratings and a locked prior quarter for Team KPI / Review & Lock demos
class PerformanceIntelModuleSeeder extends Seeder
{
    public function run(): void
    {
        $now = Kpi::currentPeriod();
        $prev = now()->subQuarter()->year.'-Q'.now()->subQuarter()->quarter;
        $hod = User::where('email', 'fauzi@csa.test')->first();

        // Core KPIs from CsaSeeder belong to the current period
        Kpi::whereNull('period')->get()->each(fn ($k) => $k->update([
            'period' => $now, 'frequency' => 'Quarterly', 'rating' => $k->locked ? 8 : null,
        ]));

        $add = [
            // email, name, source, target, actual, weight, status, period, locked, rating, evidence
            ['nuzul', 'Completed Business Development Activities', 'SYSTEM', 24, 21, 40, 'On Track', $now, false, null, 'Drill-down to completed activities'],
            ['nuzul', 'Customer Engagement Discipline', 'SYSTEM', 20, 12, 30, 'At Risk', $now, false, null, 'Engagement within 60 days'],
            ['aisyah', 'Contact Centre SLA Compliance', 'SYSTEM', 95, 93, 50, 'On Track', $now, false, null, 'Case SLA drill-down'],
            ['shahirah', 'Case First Response Discipline', 'SYSTEM', 90, 84, 50, 'At Risk', $now, false, null, 'Response within 30 minutes'],
            ['amir', 'Completed Business Development Activities', 'SYSTEM', 24, 22, 20, 'Achieved', $prev, true, 9, 'Locked prior quarter'],
            ['amir', 'Tender / Opportunity Execution Quality', 'SYSTEM', 90, 81, 20, 'On Track', $prev, true, 7, 'Locked prior quarter'],
            ['nurzam', 'Sales Coordination & Data Completeness', 'MANUAL_EVIDENCE', 95, 90, 25, 'On Track', $prev, true, 8, 'Monthly checklist'],
            ['amin', 'Contact Centre SLA Compliance', 'SYSTEM', 95, 89, 30, 'Behind', $prev, true, 6, 'Case SLA drill-down'],
        ];
        foreach ($add as [$email, $name, $src, $tgt, $act, $w, $st, $period, $lock, $rating, $ev]) {
            $u = User::where('email', "{$email}@csa.test")->first();
            if (! $u || Kpi::where(['user_id' => $u->id, 'name' => $name, 'period' => $period])->exists()) {
                continue;
            }
            $k = Kpi::create([
                'user_id' => $u->id, 'name' => $name, 'source' => $src, 'target' => $tgt, 'actual' => $act, 'weight' => $w,
                'status' => $st, 'period' => $period, 'frequency' => 'Quarterly', 'locked' => $lock, 'rating' => $rating, 'evidence' => $ev,
                'reviewed_by' => $lock ? $hod?->id : null, 'reviewed_at' => $lock ? now()->subQuarter()->endOfQuarter() : null,
                'review_comment' => $lock ? 'Quarter closed and locked' : null,
            ]);
            // One correction after lock: original stays, adjustment is appended
            if ($email === 'amin' && $hod) {
                KpiAdjustment::create(['kpi_id' => $k->id, 'old_actual' => 89, 'new_actual' => 92, 'reason' => 'Two cases re-classified after customer-pause audit', 'adjusted_by' => $hod->id]);
            }
        }
    }
}
