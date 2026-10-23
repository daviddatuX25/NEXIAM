<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_capture_sheet_pages', function (Blueprint $table): void {
            $table->unsignedInteger('current_revision')->default(1);
        });

        Schema::create('omr_capture_sheet_page_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained('omr_capture_sheet_pages')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('revision_kind', 24);
            $table->foreignId('source_revision_id')->nullable()->constrained('omr_capture_sheet_page_revisions')->restrictOnDelete();
            $table->uuid('operation_id')->nullable()->unique('omr_capture_page_revisions_operation_unique');
            $table->string('idempotency_key', 255)->nullable();
            $table->char('payload_fingerprint', 64)->nullable();
            $table->text('replacement_reason')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 32);
            $table->string('asset_path', 512)->nullable();
            $table->char('asset_hmac', 64)->nullable();
            $table->char('asset_hmac_key_id', 64)->nullable();
            $table->char('asset_key_id', 64)->nullable();
            $table->string('asset_format_version', 20)->nullable();
            $table->string('asset_state', 20)->nullable();
            $table->string('original_mime', 60)->nullable();
            $table->unsignedInteger('original_bytes')->nullable();
            $table->json('client_quad_json')->nullable();
            $table->string('crop_policy', 32)->nullable();
            $table->string('aligned_mime', 60)->nullable();
            $table->unsignedInteger('aligned_bytes')->nullable();
            $table->unsignedInteger('aligned_width')->nullable();
            $table->unsignedInteger('aligned_height')->nullable();
            $table->char('aligned_hash', 64)->nullable();
            $table->string('aligned_asset_path', 512)->nullable();
            $table->char('aligned_asset_hmac', 64)->nullable();
            $table->char('aligned_asset_hmac_key_id', 64)->nullable();
            $table->char('aligned_asset_key_id', 64)->nullable();
            $table->json('proposal_json')->nullable();
            $table->foreignId('current_processing_run_id')->nullable()->constrained('omr_processing_runs')->restrictOnDelete();
            $table->unsignedBigInteger('snapshot_sheet_version')->nullable();
            $table->string('snapshot_sheet_status', 32)->nullable();
            $table->unsignedBigInteger('snapshot_admission_attempt_id')->nullable();
            $table->unsignedBigInteger('snapshot_booklet_set_id')->nullable();
            $table->decimal('snapshot_sheet_confidence', 4, 3)->nullable();
            $table->json('snapshot_sheet_proposal_json')->nullable();
            $table->json('snapshot_review_draft_json')->nullable();
            $table->json('snapshot_reviewed_json')->nullable();
            $table->unsignedBigInteger('snapshot_reviewed_by_user_id')->nullable();
            $table->timestamp('snapshot_reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['page_id', 'idempotency_key'], 'omr_capture_page_revision_idempotency_unique');
            $table->index(['page_id', 'revision', 'id'], 'omr_capture_page_revision_history_idx');
        });

        Schema::table('omr_capture_sheet_pages', function (Blueprint $table): void {
            $table->foreignId('current_revision_id')
                ->nullable()
                ->constrained('omr_capture_sheet_page_revisions')
                ->restrictOnDelete();
        });

        Schema::table('omr_processing_runs', function (Blueprint $table): void {
            $table->unsignedInteger('page_revision')->default(1);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE omr_capture_sheet_page_revisions
                    ADD CONSTRAINT omr_capture_page_revision_kind_check CHECK (
                        (revision_kind = 'source' AND revision = 1 AND source_revision_id IS NULL)
                        OR (revision_kind = 'replacement' AND revision > 1 AND source_revision_id IS NOT NULL
                            AND actor_user_id IS NOT NULL AND idempotency_key IS NOT NULL
                            AND length(trim(idempotency_key)) > 0 AND payload_fingerprint ~ '^[a-f0-9]{64}$'
                            AND replacement_reason IS NOT NULL AND length(trim(replacement_reason)) > 0)
                        OR (revision_kind IN ('analysis', 'snapshot') AND source_revision_id IS NOT NULL)
                    ),
                    ADD CONSTRAINT omr_capture_page_revision_number_check CHECK (revision > 0),
                    ADD CONSTRAINT omr_capture_page_revision_reason_check CHECK (
                        replacement_reason IS NULL OR length(replacement_reason) <= 2000
                    ),
                    ADD CONSTRAINT omr_capture_page_revision_fingerprint_check CHECK (
                        payload_fingerprint IS NULL OR payload_fingerprint ~ '^[a-f0-9]{64}$'
                    )
            SQL);

            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION omr_capture_sheet_page_revision_immutable() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'omr_capture_sheet_page_revision_immutable';
                END;
                $$ LANGUAGE plpgsql
            SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER omr_capture_sheet_page_revision_immutable_trigger
                BEFORE UPDATE OR DELETE ON omr_capture_sheet_page_revisions
                FOR EACH ROW EXECUTE FUNCTION omr_capture_sheet_page_revision_immutable()
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE omr_processing_runs DROP CONSTRAINT omr_processing_runs_status_check
            SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE omr_processing_runs
                    ADD CONSTRAINT omr_processing_runs_status_check
                        CHECK (status IN ('pending', 'claimed', 'succeeded', 'retryable_failed', 'terminal_failed', 'superseded')),
                    ADD CONSTRAINT omr_processing_runs_page_revision_check CHECK (page_revision > 0)
            SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE TRIGGER omr_capture_sheet_page_revision_immutable_update
                BEFORE UPDATE ON omr_capture_sheet_page_revisions
                BEGIN
                    SELECT RAISE(ABORT, 'omr_capture_sheet_page_revision_immutable');
                END
            SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER omr_capture_sheet_page_revision_immutable_delete
                BEFORE DELETE ON omr_capture_sheet_page_revisions
                BEGIN
                    SELECT RAISE(ABORT, 'omr_capture_sheet_page_revision_immutable');
                END
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::table('omr_capture_sheet_page_revisions')->exists()
            || DB::table('omr_processing_runs')->where('status', 'superseded')->exists()) {
            throw new RuntimeException('OMR page revisions and superseded processing runs are retained; rollback requires a forward migration.');
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS omr_capture_sheet_page_revision_immutable_trigger ON omr_capture_sheet_page_revisions');
            DB::statement('DROP FUNCTION IF EXISTS omr_capture_sheet_page_revision_immutable()');
            DB::statement('ALTER TABLE omr_processing_runs DROP CONSTRAINT IF EXISTS omr_processing_runs_status_check');
            DB::statement('ALTER TABLE omr_processing_runs DROP CONSTRAINT IF EXISTS omr_processing_runs_page_revision_check');
            DB::statement(<<<'SQL'
                ALTER TABLE omr_processing_runs
                    ADD CONSTRAINT omr_processing_runs_status_check
                        CHECK (status IN ('pending', 'claimed', 'succeeded', 'retryable_failed', 'terminal_failed'))
            SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS omr_capture_sheet_page_revision_immutable_update');
            DB::statement('DROP TRIGGER IF EXISTS omr_capture_sheet_page_revision_immutable_delete');
        }

        Schema::table('omr_capture_sheet_pages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_revision_id');
        });
        Schema::table('omr_processing_runs', function (Blueprint $table): void {
            $table->dropColumn('page_revision');
        });
        Schema::dropIfExists('omr_capture_sheet_page_revisions');
        Schema::table('omr_capture_sheet_pages', function (Blueprint $table): void {
            $table->dropColumn('current_revision');
        });
    }
};
