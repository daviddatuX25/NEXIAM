<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_capture_sheet_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sheet_id')->constrained('omr_capture_sheets')->cascadeOnDelete();
            $table->string('area_code', 100)->nullable();
            $table->unsignedSmallInteger('question_no')->nullable();
            $table->string('issue_code', 64);
            $table->string('severity', 16);
            $table->string('state', 16)->default('open');
            $table->json('evidence_json')->nullable();
            $table->string('resolution_code', 64)->nullable();
            $table->text('resolution_reason')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();

            $table->index(['sheet_id', 'state', 'severity']);
            $table->index(['sheet_id', 'area_code', 'question_no']);
        });

        Schema::create('omr_processing_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sheet_id')->constrained('omr_capture_sheets')->cascadeOnDelete();
            $table->unsignedInteger('run_no');
            $table->string('status', 20)->default('pending');
            $table->string('provider_name', 100);
            $table->string('provider_version', 100);
            $table->string('provider_commit', 100)->nullable();
            $table->string('request_version', 32);
            $table->char('input_hash', 64);
            $table->char('template_hash', 64)->nullable();
            $table->char('booklet_hash', 64)->nullable();
            $table->char('config_hash', 64)->nullable();
            $table->uuid('claim_token')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('claim_expires_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->string('diagnostic_code', 100)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('output_json')->nullable();
            $table->timestamps();

            $table->unique(['sheet_id', 'run_no']);
            $table->index(['status', 'next_retry_at', 'id']);
            $table->index(['sheet_id', 'status', 'id']);
        });

        Schema::table('omr_capture_sheets', function (Blueprint $table): void {
            $table->foreignId('current_processing_run_id')->nullable()->after('upload_claim_expires_at')->constrained('omr_processing_runs')->restrictOnDelete();
        });

        Schema::create('omr_command_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('command_name', 100);
            $table->string('aggregate_type', 100);
            $table->string('aggregate_id', 100);
            $table->string('idempotency_key', 255);
            $table->char('payload_fingerprint', 64);
            $table->string('status', 16)->default('started');
            $table->uuid('execution_token')->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->json('safe_result_json')->nullable();
            $table->uuid('correlation_id');
            $table->timestamps();

            $table->unique(['actor_id', 'command_name', 'aggregate_type', 'aggregate_id', 'idempotency_key'], 'omr_receipts_scope_unique');
            $table->index(['status', 'lease_expires_at', 'id']);
            $table->index(['aggregate_type', 'aggregate_id']);
        });

        Schema::create('omr_asset_cleanup_intents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('omr_capture_batches')->restrictOnDelete();
            $table->unsignedBigInteger('sheet_id');
            $table->string('asset_path', 255);
            $table->char('asset_hmac', 64)->nullable();
            $table->string('asset_hmac_key_id', 100)->nullable();
            $table->string('asset_key_id', 100)->nullable();
            $table->string('reason', 255);
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'status', 'id']);
            $table->index(['status', 'next_retry_at', 'id']);
            $table->index(['sheet_id', 'status']);
        });

        Schema::table('score_records', function (Blueprint $table): void {
            $table->dropForeign(['capture_sheet_id']);
            $table->foreign('capture_sheet_id')->references('id')->on('omr_capture_sheets')->restrictOnDelete();
            $table->unsignedBigInteger('version')->default(0);
        });

        Schema::create('score_record_revisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('stream_id');
            $table->unsignedInteger('revision_no');
            $table->foreignId('score_record_id')->nullable()->constrained('score_records')->nullOnDelete();
            $table->foreignId('grading_session_id')->constrained('grading_sessions')->restrictOnDelete();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->restrictOnDelete();
            $table->foreignId('aptitude_area_id')->constrained('aptitude_areas')->restrictOnDelete();
            $table->string('operation', 16);
            $table->text('prior_values_ciphertext')->nullable();
            $table->text('new_values_ciphertext')->nullable();
            $table->string('source', 16);
            $table->foreignId('capture_sheet_id')->nullable()->constrained('omr_capture_sheets')->restrictOnDelete();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->string('command_name', 100);
            $table->uuid('correlation_id');
            $table->timestamps();

            $table->unique(['stream_id', 'revision_no']);
            $table->index(['grading_session_id', 'admission_attempt_id', 'aptitude_area_id']);
            $table->index(['score_record_id', 'created_at']);
        });

        $this->addChecks();
    }

    private function addChecks(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::getConnection()->statement("CREATE UNIQUE INDEX omr_sheet_issues_open_identity_unique ON omr_capture_sheet_issues (sheet_id, issue_code, COALESCE(area_code, ''), COALESCE(question_no, 0)) WHERE state = 'open'");
        Schema::getConnection()->statement("CREATE UNIQUE INDEX omr_processing_runs_one_active ON omr_processing_runs (sheet_id) WHERE status IN ('pending', 'claimed', 'retryable_failed')");
        Schema::getConnection()->statement("ALTER TABLE omr_capture_sheet_issues ADD CONSTRAINT omr_sheet_issues_severity_check CHECK (severity IN ('warning', 'required', 'terminal')), ADD CONSTRAINT omr_sheet_issues_state_check CHECK (state IN ('open', 'resolved')), ADD CONSTRAINT omr_sheet_issues_version_check CHECK (version >= 0), ADD CONSTRAINT omr_sheet_issues_resolution_check CHECK ((state = 'open' AND resolved_at IS NULL AND resolved_by_user_id IS NULL) OR (state = 'resolved' AND resolved_at IS NOT NULL))");
        Schema::getConnection()->statement("ALTER TABLE omr_processing_runs ADD CONSTRAINT omr_processing_runs_status_check CHECK (status IN ('pending', 'claimed', 'succeeded', 'retryable_failed', 'terminal_failed')), ADD CONSTRAINT omr_processing_runs_attempts_check CHECK (attempts >= 0), ADD CONSTRAINT omr_processing_runs_claim_check CHECK ((status = 'claimed' AND claim_token IS NOT NULL AND claim_expires_at IS NOT NULL) OR status <> 'claimed')");
        Schema::getConnection()->statement("ALTER TABLE omr_command_receipts ADD CONSTRAINT omr_receipts_status_check CHECK (status IN ('started', 'succeeded', 'failed_safe'))");
        Schema::getConnection()->statement("ALTER TABLE omr_asset_cleanup_intents ADD CONSTRAINT omr_cleanup_status_check CHECK (status IN ('pending', 'processing', 'completed', 'failed')), ADD CONSTRAINT omr_cleanup_attempts_check CHECK (attempts >= 0)");
        Schema::getConnection()->statement("ALTER TABLE score_records ADD CONSTRAINT score_records_source_check CHECK (source IN ('manual', 'omr')), ADD CONSTRAINT score_records_confidence_check CHECK (confidence IS NULL OR (confidence >= 0 AND confidence <= 1)), ADD CONSTRAINT score_records_provenance_check CHECK ((source = 'omr' AND capture_sheet_id IS NOT NULL) OR (source = 'manual' AND capture_sheet_id IS NULL AND confidence IS NULL)), ADD CONSTRAINT score_records_version_check CHECK (version >= 0)");
        Schema::getConnection()->statement("ALTER TABLE score_record_revisions ADD CONSTRAINT score_revisions_operation_check CHECK (operation IN ('write', 'correct', 'clear')), ADD CONSTRAINT score_revisions_source_check CHECK (source IN ('manual', 'omr')), ADD CONSTRAINT score_revisions_confidence_check CHECK (confidence IS NULL OR (confidence >= 0 AND confidence <= 1)), ADD CONSTRAINT score_revisions_reason_check CHECK ((operation IN ('correct', 'clear') AND char_length(trim(reason)) > 0) OR operation = 'write')");
    }

    public function down(): void
    {
        throw new RuntimeException('OMR workflow cutover is irreversible. Restore the pre-cut database backup instead of rolling this migration down.');
    }
};
