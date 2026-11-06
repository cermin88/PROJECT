<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Work module: activities, tasks, minutes of meeting (UX-022..027)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $t) {
            $t->id();
            $t->string('code', 24)->unique();
            $t->string('type', 40);
            $t->string('subject');
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('tender_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('owner_id')->constrained('users');
            $t->string('status', 20)->default('PLANNED')->index(); // PLANNED | COMPLETED | CANCELLED
            $t->dateTime('activity_at')->index();
            $t->text('outcome')->nullable();
            $t->string('next_action')->nullable();
            $t->date('next_action_date')->nullable();
            $t->timestamps();
        });

        Schema::create('meeting_minutes', function (Blueprint $t) {
            $t->id();
            $t->string('code', 24)->unique();
            $t->string('title');
            $t->dateTime('meeting_at')->index();
            $t->string('location')->nullable();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('tender_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('author_id')->constrained('users');
            $t->string('status', 20)->default('DRAFT')->index(); // DRAFT | PENDING_APPROVAL | APPROVED | RETURNED
            $t->text('summary')->nullable();
            $t->text('decisions')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->text('decision_note')->nullable();
            $t->timestamps();
        });

        Schema::create('meeting_attendees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('meeting_minute_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // internal attendee
            $t->string('name');
            $t->string('organisation')->nullable();
        });

        Schema::create('meeting_action_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('meeting_minute_id')->constrained()->cascadeOnDelete();
            $t->string('description');
            $t->foreignId('assignee_id')->constrained('users');
            $t->date('due_date');
            $t->timestamps();
        });

        Schema::create('tasks', function (Blueprint $t) {
            $t->id();
            $t->string('code', 24)->unique();
            $t->string('title');
            $t->text('notes')->nullable();
            $t->foreignId('assignee_id')->constrained('users');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->date('due_date')->index();
            $t->string('status', 20)->default('Not Started')->index(); // Not Started | In Progress | Completed | Cancelled
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('tender_id')->nullable()->constrained()->nullOnDelete();
            // At most one follow-up task per activity / per MoM action item (nullable unique)
            $t->foreignId('source_activity_id')->nullable()->unique()->constrained('activities')->nullOnDelete();
            $t->foreignId('meeting_action_item_id')->nullable()->unique()->constrained()->nullOnDelete();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['tasks', 'meeting_action_items', 'meeting_attendees', 'meeting_minutes', 'activities'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
