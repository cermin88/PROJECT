# CSA Command Hub — Laravel 12 + MySQL (phpMyAdmin)

Complete Laravel project (not an overlay). Server-side version of the Phase 2L HOD prototype:
data is stored in MySQL and business rules are enforced on the server.

The SQL dump and seeders contain sample data for local development. Do not import or seed
that sample data into a live database.

## Requirements
PHP 8.2+, Composer, MySQL/MariaDB + phpMyAdmin (XAMPP or Laragon works).

## Setup (choose ONE option)

Both options use the new database `csa_command_hub`, so nothing clashes with existing databases.

**Option A — import the development SQL fixture**
1. For local development only, phpMyAdmin → *Import* → choose
   `database/csa_command_hub.sql` → *Go* (it creates the database `csa_command_hub` itself).
2. In a terminal inside this folder:
```
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
php artisan serve
```
The SQL fixture only holds the original tables. For local development, add the module tables
and sample data:
```
php artisan migrate
php artisan db:seed --class="Database\Seeders\Modules\AdminModuleSeeder"   # repeat for each file in database/seeders/Modules/
```
(Do not run the seeders against a live database.)

**Option B — build a local development database**
1. Same terminal steps as above, but first create an empty database `csa_command_hub` in phpMyAdmin.
2. `php artisan migrate --seed` then `php artisan serve`. This loads sample users and sample business data;
   never use this option for a live database.

Check `DB_USERNAME` / `DB_PASSWORD` in `.env` (XAMPP default: root / empty).
Open http://127.0.0.1:8000 and sign in using credentials provisioned for your environment.
The account-activation migration enables existing accounts; it does not change passwords or
create new accounts. Use individual, securely provisioned passwords for live users.

## Business rules (app/Services/WorkflowService.php)
- Tender submit blocked until mandatory checklist is done and pricing approved; retries never duplicate.
- Changing price/cost clears earlier pricing approval.
- No self-approval (attempt is audited).
- Award without PO/LOA/Official Award evidence stays unqualified and counts RM 0;
  qualification creates exactly one Project (UNIQUE projects.award_id; idempotent).
- Project records and linked award, invoice, and renewal details are visible to their project owner;
  Superadmin can see all projects. The owner is inherited from the awarded opportunity.
- The account menu opens a read-only profile page showing the user's job position and system role.
  Existing role assignments are preserved; the current seeded Fauzi account remains HOD.
  The local sample accounts do not include a Superadmin, so assign that role through your
  approved account-provisioning process if all-project administration is required.
- Renewal creates exactly one Opportunity (UNIQUE renewals.opportunity_id).
- Cases: Pending Customer pauses SLA, Pending Internal does not; no close without customer confirmation.
- Restricted documents: HOD/Management/Superadmin only, denials audited.
- Locked KPIs keep the original result; corrections append to kpi_adjustments.
- Active Pipeline excludes Lost/Award/Cancelled; win rate = Awarded ÷ (Awarded + Lost); no individual revenue target.

## Modules
All 13 prototype menu groups are built (sidebar in `config/csa_nav.php`; routes per group in `routes/modules/`).
Every list table has a filter bar (`resources/views/components/filters.blade.php`) and pagination;
rows per page is set in `config/csa.php` (`per_page`, default 15).

## Known gaps vs the prototype
- Permission matrix / configuration values are stored but not yet used by access checks (each controller checks roles).
- No outgoing email/notifications for MoM, events, case assignment, CSAT surveys; no MFA.
- MoM approval is decided on the MoM page, not in the Approval Inbox.
- Opportunity tabs Products & Partners, Team and Documents are not built.
