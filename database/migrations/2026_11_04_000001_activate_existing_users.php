<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->update(['active' => true]);
    }

    public function down(): void
    {
        // Account activation is an operational change and is not reversed automatically.
    }
};
