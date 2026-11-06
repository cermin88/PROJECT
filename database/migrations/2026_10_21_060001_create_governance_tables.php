<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Records that can be archived from the Archive View (UX-063)
    private array $archivable = ['customers', 'contacts', 'opportunities', 'tenders', 'documents'];

    public function up(): void
    {
        foreach ($this->archivable as $tbl) {
            if (! Schema::hasColumn($tbl, 'archived_at')) {
                Schema::table($tbl, function (Blueprint $t) {
                    $t->timestamp('archived_at')->nullable()->index();
                    $t->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
                    $t->string('archive_reason')->nullable();
                });
            }
        }

        // Document versioning + real file storage (UX-059)
        if (! Schema::hasColumn('documents', 'context_code')) {
            Schema::table('documents', function (Blueprint $t) {
                $t->string('context_code', 40)->nullable();
            });
        }
        if (! Schema::hasColumn('documents', 'supersedes_id')) {
            Schema::table('documents', function (Blueprint $t) {
                $t->foreignId('supersedes_id')->nullable()->constrained('documents')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('documents', 'file_path')) {
            Schema::table('documents', function (Blueprint $t) {
                $t->string('file_path')->nullable();
            });
        }
        if (! Schema::hasColumn('documents', 'file_name')) {
            Schema::table('documents', function (Blueprint $t) {
                $t->string('file_name')->nullable();
            });
        }
        if (! Schema::hasColumn('documents', 'file_size')) {
            Schema::table('documents', function (Blueprint $t) {
                $t->unsignedBigInteger('file_size')->nullable();
            });
        }
        if (! Schema::hasColumn('documents', 'mime')) {
            Schema::table('documents', function (Blueprint $t) {
                $t->string('mime', 120)->nullable();
            });
        }
        if (! Schema::hasColumn('documents', 'uploaded_by')) {
            Schema::table('documents', function (Blueprint $t) {
                $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        // "Keep Separate" / "Merged" decisions so reviewed pairs drop off the duplicate list
Schema::create('governance_duplicate_decisions', function (Blueprint $t) {
    $t->id();
    $t->string('entity_type', 20); // customer | contact
    $t->unsignedBigInteger('keep_id');
    $t->unsignedBigInteger('other_id');
    $t->string('decision', 20); // KEEP_SEPARATE | MERGED
    $t->string('note')->nullable();
    $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $t->timestamps();

    // Use a short index name to avoid MySQL's 64-character limit
    $t->index(
        ['entity_type', 'keep_id', 'other_id'],
        'gov_dup_decisions_idx'
    );
});


        // CSV import batches: upload -> mapping -> preview -> commit
        Schema::create('governance_imports', function (Blueprint $t) {
            $t->id();
            $t->string('entity', 20); // customers | contacts
            $t->string('file_name');
            $t->string('file_path');
            $t->string('status', 20)->default('Uploaded'); // Uploaded | Previewed | Committed
            $t->json('headers')->nullable();
            $t->json('mapping')->nullable();
            $t->json('preview')->nullable();
            $t->unsignedInteger('row_count')->default(0);
            $t->unsignedInteger('committed_count')->default(0);
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('committed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_imports');
        Schema::dropIfExists('governance_duplicate_decisions');
        Schema::table('documents', function (Blueprint $t) {
            $t->dropConstrainedForeignId('supersedes_id');
            $t->dropConstrainedForeignId('uploaded_by');
            $t->dropColumn(['context_code', 'file_path', 'file_name', 'file_size', 'mime']);
        });
        foreach ($this->archivable as $tbl) {
            Schema::table($tbl, function (Blueprint $t) {
                $t->dropConstrainedForeignId('archived_by');
                $t->dropColumn(['archived_at', 'archive_reason']);
            });
        }
    }
};
