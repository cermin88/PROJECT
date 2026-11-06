<?php

// Sidebar menu, mirroring the Phase 2L prototype NAV (group => [route name, label, screen id, roles|null]).
// Items whose route is not registered yet are shown greyed out.
$priv = ['HOD', 'AHOD', 'Management', 'Superadmin'];

return [
    'Home' => [
        ['dashboard', 'Role Dashboard', 'UX-003'],
    ],
    'Customers' => [
        ['customers.index', 'Customers', 'UX-005'],
        ['contacts.index', 'Contacts', 'UX-008'],
        ['customers.attention', 'Customer Attention', 'UX-006'],
    ],
    'Sales' => [
        ['opportunities.index', 'Opportunities', 'UX-010'],
        ['pipeline', 'Pipeline', 'UX-011'],
        ['forecast', 'Forecast', 'UX-014'],
    ],
    'Tenders' => [
        ['tenders.index', 'Tender List', 'UX-015'],
        ['tenders.calendar', 'Tender Calendar', 'UX-016'],
        ['tenders.warroom', 'Tender War Room', 'UX-017'],
    ],
    'Work' => [
        ['mywork', 'My Work', 'UX-022'],
        ['activities.index', 'Activities', 'UX-023'],
        ['tasks.index', 'Tasks', 'UX-025'],
        ['calendar', 'Calendar', 'UX-026'],
        ['mom.index', 'MoM', 'UX-027'],
        ['approvals.index', 'Action & Approval Inbox', 'UX-028'],
    ],
    'Events' => [
        ['events.index', 'Events', 'UX-030'],
        ['events.attendance', 'Invitations / Attendance', 'UX-032'],
        ['events.postmortem', 'Post-Mortem', 'UX-033'],
    ],
    'Commercial' => [
        ['awards.index', 'Awards', 'UX-034'],
        ['projects.index', 'Projects', 'UX-036'],
        ['invoices.index', 'Invoices & Collection', 'UX-038'],
        ['renewals.index', 'Renewals', 'UX-039'],
    ],
    'Ecosystem' => [
        ['partners.index', 'Partners', 'UX-040'],
        ['products.index', 'Products & Solutions', 'UX-042'],
        ['competitors.index', 'Competitors', 'UX-043'],
    ],
    'Performance' => [
        ['kpi.index', 'My KPI', 'UX-048'],
        ['kpi.team', 'Team KPI', 'UX-049'],
        ['kpi.admin', 'KPI Administration', 'UX-050', $priv],
        ['kpi.review', 'KPI Review & Lock', 'UX-052'],
    ],
    'Intelligence' => [
        ['analytics', 'Analytics Hub', 'UX-055'],
        ['reports.index', 'Standard Reports', 'UX-056'],
        ['reports.builder', 'Custom Report Builder', 'UX-057'],
        ['reports.pack', 'Management Pack', 'UX-058'],
    ],
    'Governance' => [
        ['documents.index', 'Documents', 'UX-059'],
        ['dataquality', 'Data Quality', 'UX-061'],
        ['duplicates.index', 'Duplicate Review & Merge', 'UX-062', $priv],
        ['archive', 'Archive View', 'UX-063'],
        ['reassignment', 'Bulk Reassignment', 'UX-064', $priv],
        ['import.index', 'Import Wizard', 'UX-065', $priv],
        ['exports.index', 'Export History', 'UX-066'],
        ['audit', 'Audit Explorer', 'UX-067', $priv],
    ],
    'Admin' => [
        ['users', 'Users', 'UX-068', $priv],
        ['permissions', 'Role & Permission Matrix', 'UX-069', $priv],
        ['configuration', 'Configuration', 'UX-070', $priv],
        ['approval-matrix', 'Approval Matrix & Delegation', 'UX-071', $priv],
        ['working-calendar', 'Working Calendar', 'UX-072', $priv],
        ['master-data', 'Master Data', 'UX-073', $priv],
        ['system-health', 'System Health', 'UX-074', $priv],
        ['demo-control', 'Demo Control', 'P2L', $priv],
    ],
];
