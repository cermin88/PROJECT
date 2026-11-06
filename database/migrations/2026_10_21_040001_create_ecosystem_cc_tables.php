<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ecosystem (UX-040/042/043) + Contact Centre SLA (UX-046/047) additions
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $t) {
            $t->string('reg_no', 60)->nullable()->index();
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('website')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('archived')->default(false);
        });

        Schema::create('partner_contacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('position')->nullable();
            $t->string('email')->nullable();
            $t->string('mobile', 40)->nullable();
            $t->timestamps();
        });

        Schema::create('partner_agreements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $t->string('type');
            $t->string('status')->default('Active'); // Active|Draft|Expired
            $t->date('expiry_date')->nullable();
            $t->timestamps();
        });

        // Association only (not revenue attribution)
        Schema::create('partner_opportunity', function (Blueprint $t) {
            $t->id();
            $t->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $t->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $t->unique(['partner_id', 'opportunity_id']);
        });

        Schema::create('partner_tender', function (Blueprint $t) {
            $t->id();
            $t->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tender_id')->constrained()->cascadeOnDelete();
            $t->unique(['partner_id', 'tender_id']);
        });

        Schema::table('products', function (Blueprint $t) {
            $t->string('status')->default('Active');
            $t->text('description')->nullable();
        });

        Schema::table('competitors', function (Blueprint $t) {
            $t->decimal('known_winning_value', 15, 2)->nullable();
        });

        // Stops the resolution clock when a case is Resolved / Closed
        Schema::table('cases', function (Blueprint $t) {
            $t->timestamp('resolved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cases', fn (Blueprint $t) => $t->dropColumn('resolved_at'));
        Schema::table('competitors', fn (Blueprint $t) => $t->dropColumn('known_winning_value'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['status', 'description']));
        foreach (['partner_tender', 'partner_opportunity', 'partner_agreements', 'partner_contacts'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
        Schema::table('partners', function (Blueprint $t) {
            $t->dropConstrainedForeignId('owner_id');
            $t->dropIndex(['reg_no']);
            $t->dropColumn(['reg_no', 'website', 'notes', 'archived']);
        });
    }
};
