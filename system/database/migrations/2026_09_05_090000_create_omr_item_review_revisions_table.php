<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_item_review_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('verification_item_evidence_id')
                ->nullable()
                ->constrained('omr_template_verification_item_evidence')
                ->restrictOnDelete();
            $table->foreignId('capture_sheet_id')
                ->nullable()
                ->constrained('omr_capture_sheets')
                ->restrictOnDelete();
            $table->string('item_key', 128);
            $table->unsignedBigInteger('revision');
            $table->foreignId('supersedes_revision_id')
                ->nullable()
                ->constrained('omr_item_review_revisions')
                ->restrictOnDelete();
            $table->string('decision', 24);
            $table->string('selected_option', 32)->nullable();
            $table->string('reason_code', 64)->nullable();
            $table->text('note')->nullable();
            $table->char('reviewed_machine_evidence_hash', 64);
            $table->unsignedBigInteger('parent_version');
            $table->foreignId('reviewed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('command_name', 100);
            $table->string('idempotency_key', 255);
            $table->uuid('correlation_id');
            $table->timestamps();

            $table->unique(
                ['verification_item_evidence_id', 'item_key', 'revision'],
                'omr_review_verification_item_revision_unique',
            );
            $table->unique(
                ['capture_sheet_id', 'item_key', 'revision'],
                'omr_review_capture_sheet_item_revision_unique',
            );
            $table->index(
                ['verification_item_evidence_id', 'item_key', 'revision'],
                'omr_review_verification_item_latest_idx',
            );
            $table->index(
                ['capture_sheet_id', 'item_key', 'revision'],
                'omr_review_capture_sheet_item_latest_idx',
            );
            $table->index(['reviewed_by_user_id', 'created_at'], 'omr_review_reviewer_created_idx');
            $table->index(['correlation_id'], 'omr_review_correlation_idx');
        });

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::getConnection()->statement(<<<'SQL'
            ALTER TABLE omr_item_review_revisions
                ADD CONSTRAINT omr_item_review_revisions_context_check
                    CHECK ((verification_item_evidence_id IS NOT NULL) <> (capture_sheet_id IS NOT NULL)),
                ADD CONSTRAINT omr_item_review_revisions_revision_check
                    CHECK (revision > 0),
                ADD CONSTRAINT omr_item_review_revisions_parent_version_check
                    CHECK (parent_version >= 0),
                ADD CONSTRAINT omr_item_review_revisions_decision_check
                    CHECK (decision IN ('confirm_machine', 'set_option', 'blank', 'unresolved')),
                ADD CONSTRAINT omr_item_review_revisions_selection_check
                    CHECK (
                        (decision IN ('confirm_machine', 'set_option') AND selected_option IS NOT NULL AND char_length(trim(selected_option)) > 0)
                        OR (decision IN ('blank', 'unresolved') AND selected_option IS NULL)
                    ),
                ADD CONSTRAINT omr_item_review_revisions_reason_code_check
                    CHECK (reason_code IS NULL OR reason_code ~ '^[a-z][a-z0-9_]{0,63}$'),
                ADD CONSTRAINT omr_item_review_revisions_unresolved_reason_check
                    CHECK (decision <> 'unresolved' OR char_length(trim(reason_code)) > 0),
                ADD CONSTRAINT omr_item_review_revisions_note_length_check
                    CHECK (note IS NULL OR char_length(note) <= 2000),
                ADD CONSTRAINT omr_item_review_revisions_item_key_check
                    CHECK (char_length(trim(item_key)) > 0),
                ADD CONSTRAINT omr_item_review_revisions_evidence_hash_check
                    CHECK (reviewed_machine_evidence_hash ~ '^[a-fA-F0-9]{64}$')
        SQL);

        Schema::getConnection()->statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION nexiam_omr_item_review_revision_immutable()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            BEGIN
                RAISE EXCEPTION USING
                    ERRCODE = '55000',
                    MESSAGE = 'OMR item review revisions are append-only';
                RETURN NEW;
            END;
            $function$
        SQL);
        Schema::getConnection()->statement(<<<'SQL'
            DROP TRIGGER IF EXISTS omr_item_review_revision_immutable_guard
                ON omr_item_review_revisions
        SQL);
        Schema::getConnection()->statement(<<<'SQL'
            CREATE TRIGGER omr_item_review_revision_immutable_guard
            BEFORE UPDATE OR DELETE
            ON omr_item_review_revisions
            FOR EACH ROW
            EXECUTE FUNCTION nexiam_omr_item_review_revision_immutable()
        SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'DROP TRIGGER IF EXISTS omr_item_review_revision_immutable_guard ON omr_item_review_revisions',
            );
            Schema::getConnection()->statement(
                'DROP FUNCTION IF EXISTS nexiam_omr_item_review_revision_immutable()',
            );
        }

        Schema::dropIfExists('omr_item_review_revisions');
    }
};
