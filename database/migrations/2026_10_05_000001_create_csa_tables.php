<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend Laravel's default users table (Job Position is kept separate from System Role)
        Schema::table('users', function (Blueprint $t) {
            $t->string('code', 20)->nullable()->unique()->after('id');
            $t->string('job_position')->nullable();
            $t->string('role', 40)->default('BD Executive')->index();
            $t->boolean('active')->default(true);
        });

        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('name');
            $t->string('type')->nullable();
            $t->string('sector')->nullable();
            $t->string('state')->nullable();
            $t->string('parent')->nullable();
            $t->string('relationship')->default('Warm');
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('last_engagement_at')->nullable();
            $t->boolean('archived')->default(false);
            $t->timestamps();
        });

        Schema::create('contacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('mobile')->nullable();
            $t->string('buying_role')->nullable();
            $t->string('division')->nullable();
            $t->timestamps();
        });

        Schema::create('partners', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('classification')->nullable();
            $t->boolean('preferred')->default(false);
            $t->unsignedTinyInteger('rating')->default(3);
            $t->string('status')->default('Active');
            $t->timestamps();
        });

        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('category')->nullable();
            $t->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('competitors', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->unsignedInteger('encounters')->default(0);
            $t->unsignedInteger('won_against')->default(0);
            $t->unsignedInteger('lost_against')->default(0);
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('opportunities', function (Blueprint $t) {
            $t->id();
            $t->string('code', 24)->unique();
            $t->string('name');
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('stage')->index(); // pipeline stages | Award | Lost | Cancelled
            $t->decimal('value', 15, 2)->default(0);
            $t->unsignedTinyInteger('probability')->default(10);
            $t->string('next_action')->nullable();
            $t->date('next_action_date')->nullable();
            $t->string('lost_reason')->nullable();
            $t->foreignId('competitor_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('is_renewal')->default(false);
            $t->timestamps();
        });

        Schema::create('opportunity_stage_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $t->string('stage');
            $t->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reason')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('tenders', function (Blueprint $t) {
            $t->id();
            $t->string('code', 24)->unique();
            $t->string('title');
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status')->index();
            $t->dateTime('closing_at')->nullable();
            $t->string('risk')->default('Low');
            $t->decimal('cost', 15, 2)->default(0);
            $t->decimal('price', 15, 2)->default(0);
            $t->boolean('pricing_approved')->default(false);
            $t->timestamp('submitted_at')->nullable();
            $t->timestamps();
        });

        Schema::create('tender_checklist_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tender_id')->constrained()->cascadeOnDelete();
            $t->string('label');
            $t->boolean('mandatory')->default(true);
            $t->boolean('done')->default(false);
            $t->timestamps();
        });

        Schema::create('tender_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tender_id')->constrained()->cascadeOnDelete();
            $t->string('status');
            $t->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });

        // Generic approval record. subject_type/subject_id points at Tender, Award, etc.
        Schema::create('approvals', function (Blueprint $t) {
            $t->id();
            $t->string('code', 24)->unique();
            $t->string('type');
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->foreignId('requester_id')->constrained('users');
            $t->foreignId('approver_id')->constrained('users');
            $t->string('status')->default('Pending')->index(); // Pending|Approved|Rejected|Returned
            $t->json('snapshot')->nullable();
            $t->text('decision_note')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->index(['subject_type', 'subject_id']);
        });

        Schema::create('awards', function (Blueprint $t) {
            $t->id();
            $t->string('code', 24)->unique();
            $t->string('title');
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('tender_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('value', 15, 2)->default(0);
            $t->date('award_date');
            $t->string('status')->default('PENDING_EVIDENCE'); // PENDING_EVIDENCE | QUALIFIED
            $t->boolean('revenue_qualified')->default(false)->index();
            $t->timestamps();
        });

        Schema::create('award_evidence', function (Blueprint $t) {
            $t->id();
            $t->foreignId('award_id')->constrained()->cascadeOnDelete();
            $t->enum('type', ['PO', 'LOA', 'OFFICIAL_AWARD']);
            $t->string('reference');
            $t->timestamps();
        });

        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->string('code', 24)->unique();
            $t->foreignId('award_id')->unique()->constrained()->cascadeOnDelete(); // exactly one project per award
            $t->string('title');
            $t->string('status')->default('Kickoff');
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('project_milestones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->unsignedTinyInteger('weight'); // sums to 100 per project
            $t->unsignedTinyInteger('progress')->default(0);
            $t->timestamps();
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->string('invoice_no')->unique();
            $t->foreignId('award_id')->constrained()->cascadeOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('amount', 15, 2);
            $t->decimal('paid', 15, 2)->default(0);
            $t->date('invoice_date');
            $t->date('due_date');
            $t->timestamps();
        });

        Schema::create('renewals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type');
            $t->date('expiry_date');
            $t->string('status')->default('ACTIVE');
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('opportunity_id')->nullable()->unique()->constrained()->nullOnDelete(); // one renewal -> one opportunity
            $t->timestamps();
        });

        Schema::create('cases', function (Blueprint $t) {
            $t->id();
            $t->string('code', 24)->unique();
            $t->string('title');
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $t->string('channel');
            $t->string('priority');
            $t->string('status')->index(); // Open|Assigned|In Progress|Pending Customer|Pending Internal|Resolved|Closed|Reopened
            $t->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('opened_at');
            $t->unsignedInteger('sla_hours');
            $t->unsignedInteger('paused_minutes')->default(0);
            $t->timestamp('paused_at')->nullable(); // set while Pending Customer
            $t->string('confirmation_method')->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->text('confirmation_remarks')->nullable();
            $t->unsignedTinyInteger('csat')->nullable();
            $t->timestamps();
        });

        Schema::create('case_timeline', function (Blueprint $t) {
            $t->id();
            $t->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $t->string('text');
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('kpis', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->enum('source', ['SYSTEM', 'MANUAL_EVIDENCE']);
            $t->decimal('target', 10, 2);
            $t->decimal('actual', 10, 2);
            $t->unsignedTinyInteger('weight');
            $t->string('status')->default('On Track');
            $t->boolean('locked')->default(false);
            $t->string('evidence')->nullable();
            $t->timestamps();
        });

        // Locked KPI results are never overwritten; corrections are appended here
        Schema::create('kpi_adjustments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('kpi_id')->constrained()->cascadeOnDelete();
            $t->decimal('old_actual', 10, 2);
            $t->decimal('new_actual', 10, 2);
            $t->string('reason');
            $t->foreignId('adjusted_by')->constrained('users');
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->string('code', 24)->unique();
            $t->string('title');
            $t->string('category')->nullable();
            $t->string('version', 10)->default('1.0');
            $t->string('status')->default('Draft');
            $t->boolean('restricted')->default(false);
            $t->string('context_type')->nullable();
            $t->unsignedBigInteger('context_id')->nullable();
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action')->index();
            $t->string('entity_type')->nullable();
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->json('detail')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach ([
            'audit_logs', 'documents', 'kpi_adjustments', 'kpis', 'case_timeline', 'cases', 'renewals', 'invoices',
            'project_milestones', 'projects', 'award_evidence', 'awards', 'approvals', 'tender_status_history',
            'tender_checklist_items', 'tenders', 'opportunity_stage_history', 'opportunities', 'competitors',
            'products', 'partners', 'contacts', 'customers',
        ] as $tbl) {
            Schema::dropIfExists($tbl);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['code', 'job_position', 'role', 'active']);
        });
    }
};
