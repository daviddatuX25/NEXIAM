<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_diagnostic_captures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diagnostic_run_id')->constrained('omr_diagnostic_runs')->restrictOnDelete();
            $table->uuid('client_capture_id');
            $table->string('status', 24)->default('received');
            $table->unsignedBigInteger('version')->default(0);

            $table->foreignId('input_asset_id')->constrained('omr_template_assets')->restrictOnDelete();
            $table->char('input_asset_hash', 64);
            $table->foreignId('aligned_asset_id')->nullable()->constrained('omr_template_assets')->restrictOnDelete();
            $table->char('aligned_asset_hash', 64)->nullable();
            $table->foreignId('overlay_asset_id')->nullable()->constrained('omr_template_assets')->restrictOnDelete();
            $table->char('overlay_asset_hash', 64)->nullable();

            $table->json('evidence_json')->nullable();
            $table->char('evidence_hash', 64)->nullable();
            $table->json('detected_answers_json')->nullable();
            $table->json('effective_answers_json')->nullable();
            $table->json('projected_score_json')->nullable();

            $table->string('failure_code', 100)->nullable();
            $table->text('failure_detail')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['diagnostic_run_id', 'client_capture_id'],
                'omr_diagnostic_captures_run_client_unique',
            );
            $table->index(
                ['diagnostic_run_id', 'status', 'updated_at', 'id'],
                'omr_diagnostic_captures_run_status_idx',
            );
            $table->index(
                ['status', 'version', 'updated_at'],
                'omr_diagnostic_captures_status_version_idx',
            );
            $table->index(
                ['input_asset_id', 'input_asset_hash'],
                'omr_diagnostic_captures_input_asset_idx',
            );
            $table->index(
                ['created_by_user_id', 'created_at'],
                'omr_diagnostic_captures_creator_idx',
            );
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE omr_diagnostic_captures
                    ADD CONSTRAINT omr_diagnostic_captures_status_check
                        CHECK (
                            status IN (
                                'received',
                                'queued',
                                'processing',
                                'needs_review',
                                'reviewed',
                                'completed',
                                'failed'
                            )
                        ),
                    ADD CONSTRAINT omr_diagnostic_captures_version_check
                        CHECK (version >= 0),
                    ADD CONSTRAINT omr_diagnostic_captures_input_asset_hash_check
                        CHECK (input_asset_hash ~ '^[0-9A-Fa-f]{64}$'),
                    ADD CONSTRAINT omr_diagnostic_captures_aligned_asset_pair_check
                        CHECK (
                            (aligned_asset_id IS NULL AND aligned_asset_hash IS NULL)
                            OR (
                                aligned_asset_id IS NOT NULL
                                AND aligned_asset_hash ~ '^[0-9A-Fa-f]{64}$'
                            )
                        ),
                    ADD CONSTRAINT omr_diagnostic_captures_overlay_asset_pair_check
                        CHECK (
                            (overlay_asset_id IS NULL AND overlay_asset_hash IS NULL)
                            OR (
                                overlay_asset_id IS NOT NULL
                                AND overlay_asset_hash ~ '^[0-9A-Fa-f]{64}$'
                            )
                        ),
                    ADD CONSTRAINT omr_diagnostic_captures_evidence_pair_check
                        CHECK (
                            (evidence_json IS NULL AND evidence_hash IS NULL)
                            OR (
                                evidence_json IS NOT NULL
                                AND evidence_hash ~ '^[0-9A-Fa-f]{64}$'
                            )
                        ),
                    ADD CONSTRAINT omr_diagnostic_captures_completed_at_check
                        CHECK (
                            (status IN ('completed', 'failed') AND completed_at IS NOT NULL)
                            OR (status NOT IN ('completed', 'failed') AND completed_at IS NULL)
                        ),
                    ADD CONSTRAINT omr_diagnostic_captures_owner_check
                        CHECK (
                            diagnostic_run_id IS NOT NULL
                            AND client_capture_id IS NOT NULL
                            AND created_by_user_id IS NOT NULL
                            AND input_asset_id IS NOT NULL
                        )
            SQL);
        }

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::getConnection()->statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION nexiam_omr_diagnostic_capture_immutable()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '55000',
                        MESSAGE = 'OMR diagnostic captures are undeletable';
                END IF;

                IF NEW.id IS DISTINCT FROM OLD.id
                    OR NEW.diagnostic_run_id IS DISTINCT FROM OLD.diagnostic_run_id
                    OR NEW.client_capture_id IS DISTINCT FROM OLD.client_capture_id
                    OR NEW.input_asset_id IS DISTINCT FROM OLD.input_asset_id
                    OR NEW.input_asset_hash IS DISTINCT FROM OLD.input_asset_hash
                    OR NEW.created_by_user_id IS DISTINCT FROM OLD.created_by_user_id
                    OR NEW.captured_at IS DISTINCT FROM OLD.captured_at
                    OR NEW.created_at IS DISTINCT FROM OLD.created_at
                THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '55000',
                        MESSAGE = 'OMR diagnostic capture input identity is immutable after insert';
                END IF;

                RETURN NEW;
            END;
            $function$
        SQL);
        Schema::getConnection()->statement(<<<'SQL'
            DROP TRIGGER IF EXISTS omr_diag_capture_immutable_guard
                ON omr_diagnostic_captures
        SQL);
        Schema::getConnection()->statement(<<<'SQL'
            CREATE TRIGGER omr_diag_capture_immutable_guard
            BEFORE UPDATE OR DELETE
            ON omr_diagnostic_captures
            FOR EACH ROW
            EXECUTE FUNCTION nexiam_omr_diagnostic_capture_immutable()
        SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'DROP TRIGGER IF EXISTS omr_diag_capture_immutable_guard ON omr_diagnostic_captures',
            );
            Schema::getConnection()->statement(
                'DROP FUNCTION IF EXISTS nexiam_omr_diagnostic_capture_immutable()',
            );
        }

        Schema::dropIfExists('omr_diagnostic_captures');
    }
};
