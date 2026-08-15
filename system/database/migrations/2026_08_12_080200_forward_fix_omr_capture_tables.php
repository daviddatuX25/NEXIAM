<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_capture_batches', function (Blueprint $table): void {
            $table->dropUnique('omr_capture_batches_idempotency_key_unique');
            $table->dropColumn('idempotency_key');

            $table->foreignId('template_version_id')->after('grading_session_id')->constrained('omr_answer_sheet_template_versions')->restrictOnDelete();
            $table->foreignId('booklet_version_id')->after('template_version_id')->constrained('omr_question_booklet_versions')->restrictOnDelete();
            $table->string('status', 16)->default('open')->change();
            $table->unsignedBigInteger('version')->default(0);
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closing_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejecting_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closing_at')->nullable();
            $table->timestamp('rejecting_at')->nullable();
            $table->timestamp('confirmed_at')->nullable()->change();

            $table->index(['grading_session_id', 'status', 'id']);
            $table->index(['status', 'updated_at', 'id']);
        });

        Schema::table('omr_capture_sheets', function (Blueprint $table): void {
            $table->dropColumn('abstention_reason');
            $table->renameColumn('proposed_score_json', 'proposal_json');
            $table->renameColumn('overlay_asset_path', 'asset_path');
            $table->foreignId('admission_attempt_id')->nullable()->change();
            $table->string('status', 16)->default('received')->change();
            $table->string('capture_mode', 8)->default('manual');
            $table->unsignedBigInteger('version')->default(0);
            $table->uuid('client_capture_id');
            $table->string('detected_applicant_number', 100)->nullable();
            $table->decimal('detected_applicant_confidence', 4, 3)->nullable();
            $table->json('review_draft_json')->nullable();
            $table->json('reviewed_json')->nullable();
            $table->uuid('upload_claim_token')->nullable();
            $table->timestamp('upload_claimed_at')->nullable();
            $table->timestamp('upload_claim_expires_at')->nullable();
            $table->string('original_mime', 100)->nullable();
            $table->unsignedBigInteger('original_bytes')->nullable();
            $table->char('asset_hmac_key_id', 100)->nullable();
            $table->unsignedSmallInteger('asset_format_version')->default(1);
            $table->string('asset_key_id', 100)->nullable();
            $table->string('asset_state', 16)->default('pending_write');
            $table->timestamp('captured_at')->nullable();
            $table->foreignId('committed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('committed_at')->nullable();

            $table->unique(['batch_id', 'client_capture_id']);
            $table->index(['batch_id', 'status', 'created_at', 'id']);
            $table->index(['batch_id', 'detected_applicant_number']);
            $table->index(['batch_id', 'admission_attempt_id']);
            $table->index(['asset_state', 'updated_at', 'id']);
        });

        $this->addChecks();
    }

    private function addChecks(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::getConnection()->statement("ALTER TABLE omr_capture_batches ADD CONSTRAINT omr_capture_batches_status_check CHECK (status IN ('open', 'closing', 'rejecting', 'confirmed', 'rejected')), ADD CONSTRAINT omr_capture_batches_version_check CHECK (version >= 0), ADD CONSTRAINT omr_capture_batches_terminal_actor_check CHECK ((status IN ('confirmed', 'rejected') AND confirmed_at IS NOT NULL) OR status NOT IN ('confirmed', 'rejected'))");
        Schema::getConnection()->statement("CREATE UNIQUE INDEX omr_capture_batches_one_active_per_session ON omr_capture_batches (grading_session_id) WHERE status IN ('open', 'closing', 'rejecting')");
        Schema::getConnection()->statement("ALTER TABLE omr_capture_sheets ADD CONSTRAINT omr_capture_sheets_status_check CHECK (status IN ('received', 'queued', 'processing', 'needs_review', 'reviewed', 'committed', 'failed_terminal')), ADD CONSTRAINT omr_capture_sheets_mode_check CHECK (capture_mode IN ('manual', 'auto')), ADD CONSTRAINT omr_capture_sheets_version_check CHECK (version >= 0), ADD CONSTRAINT omr_capture_sheets_confidence_check CHECK ((sheet_confidence IS NULL OR (sheet_confidence >= 0 AND sheet_confidence <= 1)) AND (detected_applicant_confidence IS NULL OR (detected_applicant_confidence >= 0 AND detected_applicant_confidence <= 1))), ADD CONSTRAINT omr_capture_sheets_asset_check CHECK ((asset_state = 'pending_write' AND asset_path IS NOT NULL) OR (asset_state IN ('live', 'integrity_failed') AND asset_path IS NOT NULL))");
    }

    public function down(): void
    {
        throw new RuntimeException('OMR capture cutover is irreversible. Restore the pre-cut database backup instead of rolling this migration down.');
    }
};
