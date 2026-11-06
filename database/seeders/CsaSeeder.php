<?php

namespace Database\Seeders;

use App\Models\Approval;
use App\Models\Award;
use App\Models\Competitor;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Kpi;
use App\Models\Opportunity;
use App\Models\OpportunityStageHistory;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Project;
use App\Models\Renewal;
use App\Models\SupportCase;
use App\Models\Tender;
use App\Models\TenderStatusHistory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CsaSeeder extends Seeder
{
    public function run(): void
    {
        $pw = Hash::make('password'); // demo password for every seeded user

        /* ---- Users: KPI-workbook names; Job Position is separate from System Role ---- */
        $rows = [
            ['Stanley Chin Kok Hoon', 'SVP – CSA', 'Management', 'stanley'],
            ['Muhamad Fauzi Bin Jamhori', 'CSA HOD / AVP', 'HOD', 'fauzi'],
            ['Wan Nuzul Wan Mansor', 'Senior Manager – BD', 'BD Manager', 'nuzul'],
            ['Amir Zafran Bin Baharudin', 'Manager – BD', 'BD Manager', 'amir'],
            ['Muhammad Asyafiq Abdul Latiff', 'Manager – BD', 'BD Manager', 'asyafiq'],
            ['Nurzam Syahirah Binti Mohd Zamri', 'Coordinator', 'Sales Admin / Inside Sales', 'nurzam'],
            ['Muhammad Amin Bin Mansor', 'Manager – Helpdesk', 'CC Manager', 'amin'],
            ['Nur Aisyah Binti Che Mohamad Rahimi', 'Team Lead – Helpdesk', 'CC Team Lead', 'aisyah'],
            ['Nurfazlina Binti Zulkefeli', 'Team Lead – Helpdesk', 'CC Team Lead', 'fazlina'],
            ['Nur Azlini Binti Zaini', 'Team Lead – Helpdesk', 'CC Team Lead', 'azlini'],
            ['Nur Shahirah Binti Hamzah', 'CC Executive', 'CC Executive', 'shahirah'],
            ['Nursyuhada Ardilla Binti Aman Ezurra', 'CC Executive', 'CC Executive', 'syuhada'],
            ['Fifi Sofina Binti Ismail', 'CC Executive', 'CC Executive', 'fifi'],
            ['Wan Muhammad Irfan Bin Wan Nazli', 'CC Executive', 'CC Executive', 'irfan'],
        ];
        $users = [];
        foreach ($rows as $i => [$name, $pos, $role, $handle]) {
            $users[$i + 1] = User::create([
                'code' => sprintf('USR-%03d', $i + 1), 'name' => $name, 'job_position' => $pos,
                'role' => $role, 'email' => "{$handle}@csa.test", 'password' => $pw,
            ]);
        }
        $bd = [$users[4]->id, $users[5]->id, $users[3]->id]; // BD managers rotate as owners

        /* ---- Reference data ---- */
        $partners = [];
        foreach ([
            ['Hexagon Geospatial', 'Principal, OEM'], ['Esri Malaysia', 'Technology Partner'],
            ['HPE / VSTECS Astar', 'Distributor'], ['Intermap Technologies', 'Principal'],
            ['Maret Survey', 'Subcontractor'], ['Synspective', 'Technology Partner'],
            ['MetaSensing', 'Technology Partner'], ['DJI Enterprise', 'OEM'],
        ] as $i => [$n, $c]) {
            $partners[] = Partner::create(['name' => $n, 'classification' => $c, 'preferred' => $i < 4, 'rating' => 3 + ($i % 3)]);
        }
        $cats = ['GIS', 'Data', 'Infrastructure', 'AI', 'Survey', 'Remote Sensing'];
        foreach ([
            'Enterprise GIS Platform', 'Geospatial Data Lake', 'Object Storage Platform', 'AI Analytics Platform',
            'UAV LiDAR Survey', 'Satellite InSAR', 'IFSAR Mapping', 'IPAM & Network Services',
            'Smart City Platform', 'Contact Centre Platform', 'Cybersecurity Services', 'Data Integration & ETL',
        ] as $i => $n) {
            Product::create(['name' => $n, 'category' => $cats[$i % 6], 'partner_id' => $partners[$i % 8]->id]);
        }
        $competitors = [];
        foreach (['MAP2U', 'Antaragrafik Systems', 'Vendor Alpha', 'Vendor Beta', 'Systems Integrator X', 'Geospatial Consortium Y'] as $i => $n) {
            $competitors[] = Competitor::create([
                'name' => $n, 'encounters' => 2 + $i, 'won_against' => $i % 3, 'lost_against' => ($i + 1) % 3,
                'notes' => 'Lawful market intelligence only.',
            ]);
        }

        /* ---- Customers & contacts ---- */
        $names = [
            'Jabatan Ukur dan Pemetaan Malaysia (JUPEM)', 'Jabatan Pengairan dan Saliran Malaysia (JPS)',
            'Jabatan Perhutanan Semenanjung Malaysia (JPSM)', 'METMalaysia',
            'Kementerian Perumahan dan Kerajaan Tempatan (KPKT)', 'NADMA', 'NAHRIM', 'PLANMalaysia',
            'Tenaga Nasional Berhad (TNB)', 'Air Selangor', 'Majlis Bandaraya Subang Jaya (MBSJ)',
            'Majlis Bandaraya Johor Bahru (MBJB)', 'Majlis Bandaraya Pulau Pinang (MBPP)', 'JAKOA',
            'FGV Holdings', 'KUSEL',
        ];
        $types = ['Federal Government', 'Federal Government', 'Federal Government', 'Federal Government', 'Federal Government',
            'Federal Government', 'Federal Government', 'Federal Government', 'GLC', 'GLC', 'Local Municipality',
            'Local Municipality', 'Local Municipality', 'Federal Government', 'GLC', 'GLC'];
        $sectors = ['Geospatial', 'Water', 'Forestry', 'Meteorology', 'Local Government', 'Disaster', 'Research', 'Planning',
            'Utility', 'Utility', 'Municipal', 'Municipal', 'Municipal', 'Indigenous Affairs', 'Agriculture', 'Utility'];
        $customers = [];
        foreach ($names as $i => $n) {
            $c = Customer::create([
                'code' => sprintf('CUS-%06d', $i + 1), 'name' => $n, 'type' => $types[$i], 'sector' => $sectors[$i],
                'state' => $i < 9 ? 'Federal' : 'Selangor', 'parent' => $i < 9 ? 'Government of Malaysia' : null,
                'relationship' => ['Strong', 'Good', 'Warm'][$i % 3], 'owner_id' => $bd[$i % 3],
                'last_engagement_at' => now()->subDays(($i + 3) * 4),
            ]);
            $customers[] = $c;
            for ($j = 0; $j < 2; $j++) {
                Contact::create([
                    'customer_id' => $c->id,
                    'name' => ['Dato Ahmad', 'Puan Siti', 'Encik Hafiz', 'Puan Nadia', 'Encik Razak'][$i % 5] . ' ' . ($j + 1),
                    'email' => 'contact' . ($i * 2 + $j + 1) . '@example.gov.my',
                    'mobile' => '01' . (($i * 2 + $j) % 9 + 1) . '-555' . str_pad($i * 2 + $j + 10, 4, '0', STR_PAD_LEFT),
                    'buying_role' => ['Decision Maker', 'Technical Evaluator', 'Procurement', 'Influencer'][$j % 4],
                    'division' => ['ICT', 'Planning', 'Procurement'][$j % 3],
                ]);
            }
        }

        /* ---- Opportunities (20) ---- */
        $oppNames = [
            'JUPEM DRC Storage Expansion 7PB', 'MyGeoServe State Expansion', 'JPS Data Lake & Integration',
            'KPKT Datasarana Platform', 'JPS URS + LiDAR Survey', 'JPS GIS Retender', 'National IFSAR Programme',
            'Forestry LiDAR & Multispectral', 'METMalaysia NWP Modernisation', 'JAKOA DNS/DHCP/IPAM',
            'TNB Geospatial Platform', 'Air Selangor Spatial Analytics', 'MBSJ Smart City GIS',
            'MBJB Property Intelligence', 'MBPP Planning GIS', 'FGV Enterprise Data Platform',
            'KUSEL Utility Corridor Intelligence', 'NADMA Disaster Data Hub', 'NAHRIM Hydro Intelligence',
            'PLANMalaysia Urban Analytics',
        ];
        $stages = Opportunity::STAGES;
        $opps = [];
        foreach ($oppNames as $i => $n) {
            $stage = $i < 16 ? $stages[$i % count($stages)] : ($i % 2 ? 'Lost' : 'Award');
            $o = Opportunity::create([
                'code' => sprintf('OPP-2026-%06d', $i + 1), 'name' => $n,
                'customer_id' => $customers[$i % 16]->id, 'owner_id' => $bd[$i % 3],
                'stage' => $stage, 'value' => $i === 0 ? 8500000 : 650000 + $i * 235000,
                'probability' => [10, 20, 35, 45, 55, 65, 75, 80, 90][$i % 9],
                'next_action' => ['Courtesy visit', 'Technical workshop', 'Follow-up budget', 'Prepare proposal', 'Tender readiness review'][$i % 5],
                'next_action_date' => now()->addDays(($i % 8) - 3),
                'lost_reason' => $stage === 'Lost' ? 'Price / strategic fit' : null,
                'competitor_id' => $i % 3 === 0 ? $competitors[$i % 6]->id : null,
            ]);
            OpportunityStageHistory::create([
                'opportunity_id' => $o->id, 'stage' => 'Lead Opportunity', 'changed_by' => $bd[$i % 2], 'reason' => 'Opportunity identified',
            ]);
            $opps[] = $o;
        }

        /* ---- Tenders (11) with mandatory checklist ---- */
        $tStatus = ['PREPARATION', 'READY_FOR_SUBMISSION', 'SUBMISSION_APPROVAL', 'SUBMITTED', 'TECHNICAL_EVALUATION',
            'COMMERCIAL_EVALUATION', 'SHORTLISTED', 'AWARDED', 'LOST', 'CANCELLED', 'BID_ASSESSMENT'];
        $tenders = [];
        foreach ($tStatus as $i => $st) {
            $submitted = in_array($st, ['SUBMITTED', 'TECHNICAL_EVALUATION', 'COMMERCIAL_EVALUATION', 'SHORTLISTED', 'AWARDED', 'LOST'], true);
            $t = Tender::create([
                'code' => sprintf('TND-2026-%06d', $i + 1), 'title' => $oppNames[$i] . ' — Tender',
                'customer_id' => $customers[$i]->id, 'opportunity_id' => $opps[$i]->id, 'owner_id' => $bd[$i % 3],
                'status' => $st, 'closing_at' => now()->addDays(4 + $i * 8),
                'risk' => $i < 3 ? 'High' : ($i === 4 ? 'Medium' : 'Low'),
                'cost' => 550000 + $i * 90000, 'price' => 720000 + $i * 120000,
                'pricing_approved' => $i >= 1 && $i !== 2,   // tenders 1 and 3 (index 0 and 2) still need pricing approval
                'submitted_at' => $submitted ? now()->subDays(20 - $i) : null,
            ]);
            foreach ([
                'Tender document purchased/received' => $i > 0, 'Mandatory company documents' => $i > 1,
                'Technical proposal finalised' => $i > 2, 'Commercial pricing finalised' => $i > 2,
                'Submission packaging verified' => $i > 3,
            ] as $label => $done) {
                $t->checklist()->create(['label' => $label, 'mandatory' => true, 'done' => $done]);
            }
            TenderStatusHistory::create(['tender_id' => $t->id, 'status' => $st, 'changed_by' => $bd[$i % 3]]);
            $tenders[] = $t;
        }

        /* ---- Awards (6) / Projects / Invoices ---- */
        $awards = [];
        foreach (range(0, 5) as $i) {
            $qualified = $i !== 5;
            $a = Award::create([
                'code' => sprintf('AWD-2026-%06d', $i + 1), 'title' => 'Award — ' . $oppNames[$i + 1],
                'customer_id' => $customers[$i + 1]->id, 'opportunity_id' => $opps[$i + 1]->id,
                'tender_id' => $i < 4 ? $tenders[$i + 3]->id : null,
                'value' => 900000 + $i * 500000, 'award_date' => now()->subDays(90 - $i * 13),
                'status' => $qualified ? 'QUALIFIED' : 'PENDING_EVIDENCE', 'revenue_qualified' => $qualified,
            ]);
            if ($qualified) {
                $a->evidence()->create(['type' => $i % 2 ? 'LOA' : 'PO', 'reference' => ($i % 2 ? 'LOA' : 'PO') . '-2026-' . (100 + $i)]);
            }
            $awards[] = $a;
        }
        $pStatus = ['In Progress', 'UAT', 'Kickoff', 'Delivered', 'In Progress'];
        $progress = [[100, 70, 0], [100, 100, 80], [100, 30, 0], [100, 100, 100], [100, 55, 0]];
        $projects = [];
        foreach (range(0, 4) as $i) {
            $p = Project::create([
                'code' => sprintf('PRJ-2026-%06d', $i + 1), 'award_id' => $awards[$i]->id,
                'title' => str_replace('Award', 'Project', $awards[$i]->title),
                'status' => $pStatus[$i], 'owner_id' => $bd[$i % 2],
            ]);
            foreach ([['Kickoff', 20], ['Delivery', 50], ['UAT / Closure', 30]] as $k => [$mn, $w]) {
                $p->milestones()->create(['name' => $mn, 'weight' => $w, 'progress' => $progress[$i][$k]]);
            }
            $projects[] = $p;
        }
        $paid = [0, 50000, 220000, 390000, 0, 180000, 300000, 0, 460000];
        foreach (range(0, 8) as $i) {
            Invoice::create([
                'invoice_no' => 'INV/26/' . (1000 + $i), 'award_id' => $awards[$i % 5]->id, 'project_id' => $projects[$i % 5]->id,
                'amount' => 180000 + $i * 70000, 'paid' => $paid[$i],
                'invoice_date' => now()->subDays(60 - $i * 5), 'due_date' => now()->subDays(20 - $i * 8),
            ]);
        }

        /* ---- Renewals ---- */
        $expiry = [25, 55, 85, 150, 210, -10];
        foreach (range(0, 5) as $i) {
            Renewal::create([
                'customer_id' => $customers[$i + 2]->id, 'project_id' => $projects[$i % 5]->id,
                'type' => ['Maintenance', 'License', 'Warranty', 'Subscription'][$i % 4],
                'expiry_date' => now()->addDays($expiry[$i]), 'status' => $i === 5 ? 'EXPIRED' : ($i < 4 ? 'DUE_SOON' : 'ACTIVE'),
                'owner_id' => $bd[$i % 2],
            ]);
        }

        /* ---- Approvals (HOD is the approver) ---- */
        $aType = ['Tender Pricing/Submission', 'Bid/No-Bid', 'KPI Manual', 'Event/Budget', 'Major Opportunity Value Change', 'MoM', 'Award/Loss', 'Quotation'];
        $aStat = ['Pending', 'Pending', 'Returned', 'Approved', 'Pending', 'Rejected', 'Pending', 'Approved'];
        foreach ($aType as $i => $type) {
            $isFirst = $i === 0;
            Approval::create([
                'code' => sprintf('APR-2026-%06d', $i + 1), 'type' => $type,
                'subject_type' => $isFirst ? 'tender' : null, 'subject_id' => $isFirst ? $tenders[2]->id : null,
                'requester_id' => [$bd[0], $bd[1], $users[6]->id][$i % 3], 'approver_id' => $users[2]->id,
                'status' => $aStat[$i],
                'snapshot' => ['summary' => 'Controlled submission snapshot #' . ($i + 1), 'amount' => 500000 + $i * 125000],
                'decided_at' => in_array($aStat[$i], ['Approved', 'Rejected', 'Returned'], true) ? now()->subDays($i) : null,
            ]);
        }

        /* ---- Contact Centre cases (13) ---- */
        $cStat = ['Open', 'Assigned', 'In Progress', 'Pending Customer', 'Pending Internal', 'Resolved', 'Closed'];
        $cc = [$users[11]->id, $users[12]->id, $users[13]->id];
        foreach (range(0, 12) as $i) {
            $st = $cStat[$i % 7];
            SupportCase::create([
                'code' => sprintf('CAS-2026-%06d', $i + 1),
                'title' => ['PACS access issue', 'Data sync enquiry', 'Project support request', 'License assistance'][$i % 4] . ' #' . ($i + 1),
                'customer_id' => $customers[$i % 16]->id, 'channel' => ['Phone', 'Email', 'WhatsApp', 'Internal Referral'][$i % 4],
                'priority' => ['Low', 'Medium', 'High', 'Critical'][$i % 4], 'status' => $st,
                'assignee_id' => $cc[$i % 3], 'opened_at' => now()->subDays($i + 1),
                'sla_hours' => [48, 24, 8, 4][$i % 4],
                'paused_at' => $st === 'Pending Customer' ? now()->subHours(6) : null,
                'confirmation_method' => $st === 'Closed' ? 'Email' : null,
                'confirmed_at' => $st === 'Closed' ? now()->subDay() : null,
                'confirmation_remarks' => $st === 'Closed' ? 'Customer confirmed resolution' : null,
                'csat' => $st === 'Closed' ? 4 + ($i % 2) : null,
            ])->timeline()->create(['text' => 'Case opened', 'user_id' => $users[7]->id]);
        }

        /* ---- KPIs (no individual revenue target — official revenue is qualified Awards only) ---- */
        foreach ([
            [2, 'Team Performance Achievement Rate', 'MANUAL_EVIDENCE', 100, 82, 25, 'On Track', false, 'HR Grade B evidence required'],
            [4, 'Completed Business Development Activities', 'SYSTEM', 24, 19, 20, 'On Track', false, 'Drill-down to completed activities'],
            [4, 'Tender / Opportunity Execution Quality', 'SYSTEM', 90, 78, 20, 'At Risk', false, 'System-derived workflow evidence'],
            [5, 'Customer Engagement Discipline', 'SYSTEM', 20, 17, 20, 'On Track', true, 'Locked Q2 result'],
            [6, 'Sales Coordination & Data Completeness', 'MANUAL_EVIDENCE', 95, 92, 25, 'On Track', false, 'Monthly checklist and evidence'],
            [7, 'Contact Centre SLA Compliance', 'SYSTEM', 95, 91, 30, 'On Track', false, 'Case SLA drill-down'],
        ] as [$u, $n, $src, $tgt, $act, $w, $st, $lock, $ev]) {
            Kpi::create([
                'user_id' => $users[$u]->id, 'name' => $n, 'source' => $src, 'target' => $tgt, 'actual' => $act,
                'weight' => $w, 'status' => $st, 'locked' => $lock, 'evidence' => $ev,
            ]);
        }

        /* ---- Documents (every 4th is restricted) ---- */
        $dt = ['Tender Pricing Strategy', 'LOA / Award Evidence', 'Customer Meeting Minutes', 'Technical Proposal',
            'Project UAT Document', 'Partner Agreement', 'KPI Evidence'];
        $dc = ['Proposal', 'Award Evidence', 'MoM', 'Tender', 'Project', 'Agreement', 'KPI'];
        foreach (range(0, 13) as $i) {
            Document::create([
                'code' => sprintf('DOC-2026-%06d', $i + 1), 'title' => $dt[$i % 7] . ' ' . ($i + 1),
                'category' => $dc[$i % 7], 'version' => (1 + $i % 3) . '.0',
                'status' => ['Draft', 'Under Review', 'Approved', 'Final', 'Superseded'][$i % 5],
                'restricted' => $i % 4 === 0,
            ]);
        }
    }
}
