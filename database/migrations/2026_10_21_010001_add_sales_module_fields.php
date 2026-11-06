<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $t) {
            $t->string('relationship')->default('Warm');
            $t->string('position')->nullable();
        });

        Schema::table('tenders', function (Blueprint $t) {
            $t->string('loss_reason')->nullable();
            $t->string('no_bid_reason')->nullable();
            $t->unsignedBigInteger('retender_of_id')->nullable()->index(); // source tender of a Retender
        });

        // Controlled disposition of Attention Centre items (Acknowledged / Risk Accepted)
        Schema::create('attention_dispositions', function (Blueprint $t) {
            $t->id();
            $t->string('item_key', 80)->index(); // e.g. tender:12:closing
            $t->string('title');
            $t->string('disposition', 30);
            $t->string('note')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attention_dispositions');
        Schema::table('tenders', function (Blueprint $t) {
            $t->dropIndex(['retender_of_id']);
            $t->dropColumn(['loss_reason', 'no_bid_reason', 'retender_of_id']);
        });
        Schema::table('contacts', function (Blueprint $t) {
            $t->dropColumn(['relationship', 'position']);
        });
    }
};
