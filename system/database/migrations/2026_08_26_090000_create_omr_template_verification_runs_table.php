<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable template-verification runs (OpenSpec 35.21, AD-6).
 *
 * The run row is the SOLE resume/qualification authority: phase, progress,
 * pinned target/proof-plan hashes, safe error, actor, timestamps, and the
 * optimistic version. Progress is NEVER reconstructed from operations or
 * job payloads. Exactly one target is required (draft XOR version).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_template_verification_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('draft_id')->nullable()->constrained('omr_template_authoring_drafts')->restrictOnDelete();
            $table->foreignId('template_version_id')->nullable()->constrained('omr_answer_sheet_template_versions')->restrictOnDelete();
            $table->string('status', 24)->default('queued');
            $table->string('mode', 24)->default('digital');
            $table->json('phase_detail')->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->string('plan_version', 16)->default('v1');
            $table->char('plan_hash', 64);
            $table->char('expected_vector_hash', 64);
            $table->char('artifact_hash', 64);
            $table->char('native_bundle_hash', 64);
            $table->char('manifest_hash', 64);
            $table->char('config_hash', 64);
            $table->char('mapping_hash', 64);
            $table->char('proof_hash', 64)->nullable();
            $table->char('clean_vector_hash', 64)->nullable();
            $table->string('challenge_code', 32)->nullable();
            $table->unsignedSmallInteger('progress_done')->default(0);
            $table->unsignedSmallInteger('progress_total')->default(0);
            $table->json('result_summary')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->string('safe_error', 512)->nullable();
            $table->unsignedBigInteger('optimistic_version')->default(0);
            $table->foreignId('started_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();

            $table->index(['draft_id', 'status'], 'omr_verification_runs_draft_status_idx');
            $table->index(['template_version_id', 'status'], 'omr_verification_runs_version_status_idx');
            $table->index(['status', 'attempt'], 'omr_verification_runs_status_attempt_idx');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE omr_template_verification_runs
                    ADD CONSTRAINT omr_verification_runs_status_check
                        CHECK (status IN ('queued', 'preparing_proof', 'clean_recovery', 'variant_matrix', 'awaiting_print', 'awaiting_capture', 'analyzing_capture', 'needs_review', 'passed', 'failed')),
                    ADD CONSTRAINT omr_verification_runs_mode_check
                        CHECK (mode IN ('digital', 'quick_rehearsal', 'full_qualification')),
                    ADD CONSTRAINT omr_verification_runs_attempt_check
                        CHECK (attempt >= 1),
                    ADD CONSTRAINT omr_verification_runs_target_check
                        CHECK ((draft_id IS NOT NULL AND template_version_id IS NULL)
                            OR (draft_id IS NULL AND template_version_id IS NOT NULL))
            SQL);
        }

        // Active-run lookup: at most one non-terminal run per target. This is
        // the concurrency backstop for StartOmrTemplateVerification replay.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::getConnection()->statement(<<<'SQL'
                CREATE UNIQUE INDEX omr_verification_runs_active_draft_unique
                    ON omr_template_verification_runs (draft_id)
                    WHERE draft_id IS NOT NULL
                      AND status IN ('queued', 'preparing_proof', 'clean_recovery', 'variant_matrix', 'awaiting_print', 'awaiting_capture', 'analyzing_capture')
            SQL);
            Schema::getConnection()->statement(<<<'SQL'
                CREATE UNIQUE INDEX omr_verification_runs_active_version_unique
                    ON omr_template_verification_runs (template_version_id)
                    WHERE template_version_id IS NOT NULL
                      AND status IN ('queued', 'preparing_proof', 'clean_recovery', 'variant_matrix', 'awaiting_print', 'awaiting_capture', 'analyzing_capture')
            SQL);
        } else {
            Schema::getConnection()->statement(<<<'SQL'
                CREATE UNIQUE INDEX omr_verification_runs_active_draft_unique
                    ON omr_template_verification_runs (draft_id)
                    WHERE draft_id IS NOT NULL
                      AND status IN ('queued', 'preparing_proof', 'clean_recovery', 'variant_matrix', 'awaiting_print', 'awaiting_capture', 'analyzing_capture')
            SQL);
            Schema::getConnection()->statement(<<<'SQL'
                CREATE UNIQUE INDEX omr_verification_runs_active_version_unique
                    ON omr_template_verification_runs (template_version_id)
                    WHERE template_version_id IS NOT NULL
                      AND status IN ('queued', 'preparing_proof', 'clean_recovery', 'variant_matrix', 'awaiting_print', 'awaiting_capture', 'analyzing_capture')
            SQL);
        }
    }

    /**
     * WARNING: down() is for test/dev teardown ONLY. Run rows are retained
     * per evidence policy; operational rollback keeps additive tables.
     * Deletion order: captures -> challenges -> runs.
     */
    public function down(): void
    {
        Schema::dropIfExists('omr_template_verification_runs');
    }
};
