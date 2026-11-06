<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Admin module (UX-069 .. UX-074): permission matrix, settings, approval matrix/delegation, calendar, master data
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_matrix', function (Blueprint $t) {
            $t->id();
            $t->string('role', 40);
            $t->string('module', 40);
            $t->string('action', 20);
            $t->boolean('allowed')->default(false);
            $t->timestamps();
            $t->unique(['role', 'module', 'action']);
        });

        Schema::create('admin_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 60)->unique();
            $t->text('value')->nullable();
            $t->string('type', 10)->default('int'); // int | list | text
            $t->string('group', 40)->default('General');
            $t->string('label');
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('approval_types', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('primary_authority');
            $t->string('fallback_rule')->nullable();
            $t->boolean('active')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        // Effective-dated delegation of a user's approval rights (auto-expires after ends_on)
        Schema::create('approval_delegations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('delegator_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('delegate_id')->constrained('users')->cascadeOnDelete();
            $t->date('starts_on');
            $t->date('ends_on');
            $t->string('reason')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['delegate_id', 'starts_on', 'ends_on']);
        });

        Schema::create('working_calendar_days', function (Blueprint $t) {
            $t->id();
            $t->date('date');
            $t->string('name');
            $t->string('type', 20)->default('Federal'); // Federal | State | Company
            $t->string('state')->nullable();
            $t->timestamps();
            $t->unique(['date', 'name']);
        });

        Schema::create('master_data_values', function (Blueprint $t) {
            $t->id();
            $t->string('set', 40)->index();
            $t->string('value');
            $t->unsignedSmallInteger('sort')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['set', 'value']);
        });
    }

    public function down(): void
    {
        foreach (['master_data_values', 'working_calendar_days', 'approval_delegations', 'approval_types', 'admin_settings', 'permission_matrix'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
