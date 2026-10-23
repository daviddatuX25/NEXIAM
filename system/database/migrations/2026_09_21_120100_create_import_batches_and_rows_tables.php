<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('academic_cycle_id')->constrained('academic_cycles')->cascadeOnDelete();
            $table->unsignedInteger('policy_version')->default(1);
            $table->string('import_type', 32); // all_in_one, multi_session, applicants_only, scores_only
            $table->string('file_name', 255);
            $table->string('file_hash', 64); // sha256 of uploaded file
            $table->string('batch_fingerprint', 64); // sha256(inst + cycle + policy + file)
            $table->string('status', 32)->default('staged'); // staged, committing, completed, failed
            $table->json('topology_meta')->nullable(); // sheet names, row counts, detected columns
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'batch_fingerprint'], 'uniq_import_batches_fingerprint');
            $table->index(['institution_id', 'academic_cycle_id', 'status']);
        });

        Schema::create('import_batch_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->string('sheet_name', 128);
            $table->unsignedInteger('row_index'); // 1-based index in sheet
            $table->string('idempotency_key', 64); // sha256(batch + sheet + row + payload)
            $table->string('status', 32)->default('staged'); // staged, valid, invalid, imported, failed, skipped
            $table->json('raw_payload');
            $table->json('normalized_payload')->nullable();
            $table->json('errors')->nullable(); // list of validation error strings
            $table->string('reference_code', 64)->nullable(); // examinee ref code if detected/generated
            $table->foreignId('target_exam_session_id')->nullable()->constrained('exam_sessions')->nullOnDelete();
            $table->foreignId('admission_attempt_id')->nullable()->constrained('admission_attempts')->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['import_batch_id', 'idempotency_key'], 'uniq_import_batch_rows_key');
            $table->index(['import_batch_id', 'status']);
            $table->index('reference_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batch_rows');
        Schema::dropIfExists('import_batches');
    }
};
