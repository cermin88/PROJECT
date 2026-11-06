<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Events module (UX-030 / 032 / 033). Lifecycle: DRAFT → PENDING_APPROVAL → APPROVED → ACTIVE/HELD → POST_MORTEM → CLOSED
        Schema::create('csa_events', function (Blueprint $t) {
            $t->id();
            $t->string('code', 24)->unique();
            $t->string('name');
            $t->dateTime('event_date');
            $t->string('venue')->nullable();
            $t->text('objective')->nullable();
            $t->decimal('budget', 15, 2)->default(0);
            $t->decimal('actual', 15, 2)->default(0);
            $t->string('status', 30)->default('DRAFT')->index();
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approval_id')->nullable()->constrained('approvals')->nullOnDelete(); // latest Event/Budget approval
            // Post-mortem (UX-033)
            $t->string('pm_objective_met', 10)->nullable(); // Yes | Partly | No
            $t->text('pm_issues')->nullable();
            $t->text('pm_lessons')->nullable();
            $t->text('pm_follow_up')->nullable();
            $t->foreignId('pm_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('pm_saved_at')->nullable();
            $t->timestamps();
        });

        Schema::create('csa_event_invitations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('csa_event_id')->constrained('csa_events')->cascadeOnDelete();
            $t->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('status', 20)->default('Invited'); // Invited | Confirmed | Declined | Attended | No Show
            $t->timestamps();
            $t->unique(['csa_event_id', 'contact_id']);
        });

        // Opportunities sourced from an event → associated opportunity value (ROI)
        Schema::create('csa_event_opportunity', function (Blueprint $t) {
            $t->id();
            $t->foreignId('csa_event_id')->constrained('csa_events')->cascadeOnDelete();
            $t->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['csa_event_id', 'opportunity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csa_event_opportunity');
        Schema::dropIfExists('csa_event_invitations');
        Schema::dropIfExists('csa_events');
    }
};
