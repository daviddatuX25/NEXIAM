<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answer_sheet_import_operations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('replacement_for_id')->nullable();

            $table->string('phase', 32)->default('queued');
            $table->unsignedBigInteger('revision')->default(1);
            $table->unsignedBigInteger('generation')->default(1);
            $table->unsignedBigInteger('review_revision')->default(0);
            $table->string('parser_version', 32)->nullable();

            $table->string('original_filename', 255);
            $table->string('archive_disk', 32);
            $table->string('archive_path', 512);
            $table->string('archive_mime', 127);
            $table->unsignedBigInteger('archive_bytes');
            $table->char('archive_sha256', 64);
            $table->string('ledger_disk', 32)->nullable();
            $table->string('ledger_path', 512)->nullable();
            $table->unsignedBigInteger('ledger_bytes')->nullable();
            $table->char('ledger_sha256', 64)->nullable();

            $table->unsignedInteger('progress_done')->default(0);
            $table->unsignedInteger('progress_total')->default(0);
            $table->string('progress_unit', 32)->nullable();
            $table->json('report_json')->nullable();
            $table->char('report_sha256', 64)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->string('safe_error', 512)->nullable();

            $table->uuid('lease_token')->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();

            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('confirmed_review_revision')->nullable();
            $table->char('confirmation_review_token', 64)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('result_draft_id')->nullable()->unique()
                ->constrained('omr_template_authoring_drafts')->restrictOnDelete();
            $table->uuid('result_receipt')->nullable()->unique();
            $table->json('result_summary')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['institution_id', 'owner_user_id', 'created_at'], 'answer_sheet_import_owner_created_idx');
            $table->index(['phase', 'lease_expires_at'], 'answer_sheet_import_phase_lease_idx');
            $table->index('replacement_for_id', 'answer_sheet_import_replacement_idx');
        });

        Schema::table('answer_sheet_import_operations', function (Blueprint $table): void {
            $table->foreign('replacement_for_id')->references('id')->on('answer_sheet_import_operations')->restrictOnDelete();
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE answer_sheet_import_operations
                    ADD CONSTRAINT answer_sheet_import_phase_check
                        CHECK (phase IN ('queued', 'checking', 'needs_review', 'ready_to_restore', 'restoring', 'completed', 'failed', 'cancelled')),
                    ADD CONSTRAINT answer_sheet_import_progress_check
                        CHECK (progress_total = 0 OR progress_done <= progress_total),
                    ADD CONSTRAINT answer_sheet_import_result_check
                        CHECK ((phase = 'completed' AND result_draft_id IS NOT NULL AND result_receipt IS NOT NULL)
                            OR (phase <> 'completed' AND result_draft_id IS NULL AND result_receipt IS NULL))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('answer_sheet_import_operations');
    }
};
