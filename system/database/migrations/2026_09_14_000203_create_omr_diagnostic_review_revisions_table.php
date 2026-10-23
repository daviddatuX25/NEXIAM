<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_diagnostic_review_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diagnostic_item_evidence_id')
                ->constrained('omr_diagnostic_item_evidence')
                ->restrictOnUpdate()
                ->restrictOnDelete();
            $table->unsignedBigInteger('revision');
            $table->unsignedBigInteger('parent_revision');
            $table->string('decision', 32);
            $table->string('final_option', 32)->nullable();
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->char('reviewed_evidence_hash', 64);
            $table->foreignId('actor_id')
                ->constrained('users')
                ->restrictOnUpdate()
                ->restrictOnDelete();
            $table->string('idempotency_key', 255);
            $table->timestamps();

            $table->unique(
                ['diagnostic_item_evidence_id', 'revision'],
                'omr_diag_review_item_revision_unique',
            );
            $table->unique(
                ['diagnostic_item_evidence_id', 'idempotency_key'],
                'omr_diag_review_item_idempotency_unique',
            );
            $table->index(
                ['diagnostic_item_evidence_id', 'parent_revision'],
                'omr_diag_review_item_parent_revision_idx',
            );
            $table->index(
                ['diagnostic_item_evidence_id', 'created_at'],
                'omr_diag_review_item_created_idx',
            );
            $table->index(['actor_id', 'created_at'], 'omr_diag_review_actor_created_idx');
        });

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::getConnection()->statement(<<<'SQL'
            ALTER TABLE omr_diagnostic_review_revisions
                ADD CONSTRAINT omr_diag_review_revision_check
                    CHECK (revision > 0),
                ADD CONSTRAINT omr_diag_review_parent_revision_check
                    CHECK (parent_revision >= 0),
                ADD CONSTRAINT omr_diag_review_decision_check
                    CHECK (decision IN ('confirm_machine', 'set_option', 'blank', 'unresolved')),
                ADD CONSTRAINT omr_diag_review_final_option_check
                    CHECK (
                        (decision IN ('confirm_machine', 'set_option') AND final_option IS NOT NULL AND char_length(trim(final_option)) > 0)
                        OR (decision IN ('blank', 'unresolved') AND final_option IS NULL)
                    ),
                ADD CONSTRAINT omr_diag_review_reason_check
                    CHECK (
                        (decision IN ('set_option', 'blank', 'unresolved') AND reason IS NOT NULL AND char_length(trim(reason)) > 0)
                        OR (decision = 'confirm_machine' AND (reason IS NULL OR char_length(trim(reason)) > 0))
                    ),
                ADD CONSTRAINT omr_diag_review_reason_length_check
                    CHECK (reason IS NULL OR char_length(reason) <= 2000),
                ADD CONSTRAINT omr_diag_review_note_length_check
                    CHECK (note IS NULL OR char_length(note) <= 2000),
                ADD CONSTRAINT omr_diag_review_evidence_hash_check
                    CHECK (reviewed_evidence_hash ~ '^[a-fA-F0-9]{64}$'),
                ADD CONSTRAINT omr_diag_review_idempotency_check
                    CHECK (char_length(trim(idempotency_key)) > 0)
        SQL);

        Schema::getConnection()->statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION nexiam_omr_diagnostic_review_revision_immutable()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            BEGIN
                RAISE EXCEPTION USING
                    ERRCODE = '55000',
                    MESSAGE = 'OMR diagnostic review revisions are append-only';
                RETURN NEW;
            END;
            $function$
        SQL);
        Schema::getConnection()->statement(<<<'SQL'
            DROP TRIGGER IF EXISTS omr_diag_review_revision_immutable_guard
                ON omr_diagnostic_review_revisions
        SQL);
        Schema::getConnection()->statement(<<<'SQL'
            CREATE TRIGGER omr_diag_review_revision_immutable_guard
            BEFORE UPDATE OR DELETE
            ON omr_diagnostic_review_revisions
            FOR EACH ROW
            EXECUTE FUNCTION nexiam_omr_diagnostic_review_revision_immutable()
        SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'DROP TRIGGER IF EXISTS omr_diag_review_revision_immutable_guard ON omr_diagnostic_review_revisions',
            );
            Schema::getConnection()->statement(
                'DROP FUNCTION IF EXISTS nexiam_omr_diagnostic_review_revision_immutable()',
            );
        }

        Schema::dropIfExists('omr_diagnostic_review_revisions');
    }
};
