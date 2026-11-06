<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // KPI definition per user + period, plus the review fields used by KPI Review & Lock
        Schema::table('kpis', function (Blueprint $t) {
            $t->string('period', 10)->nullable()->index(); // e.g. 2026-Q4
            $t->string('frequency', 20)->nullable();       // Monthly | Quarterly | Half-Yearly | Yearly
            $t->string('formula')->nullable();
            $t->unsignedTinyInteger('rating')->nullable(); // 1-10
            $t->text('review_comment')->nullable();
            $t->unsignedBigInteger('reviewed_by')->nullable();
            $t->timestamp('reviewed_at')->nullable();
        });

        // Custom Report Builder: generation runs (queued -> processing -> completed)
        Schema::create('custom_report_runs', function (Blueprint $t) {
            $t->id();
            $t->string('code', 24)->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('module', 40);
            $t->json('definition');
            $t->string('status', 20)->default('Queued')->index(); // Queued | Processing | Completed | Failed
            $t->unsignedInteger('row_count')->nullable();
            $t->string('file_path')->nullable();
            $t->string('error')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_report_runs');
        Schema::table('kpis', function (Blueprint $t) {
            $t->dropIndex(['period']);
            $t->dropColumn(['period', 'frequency', 'formula', 'rating', 'review_comment', 'reviewed_by', 'reviewed_at']);
        });
    }
};
