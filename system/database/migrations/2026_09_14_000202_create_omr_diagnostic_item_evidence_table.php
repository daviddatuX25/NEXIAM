<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_diagnostic_item_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diagnostic_capture_id')
                ->constrained('omr_diagnostic_captures')
                ->restrictOnUpdate()
                ->restrictOnDelete();
            $table->string('item_key', 128);
            $table->unsignedSmallInteger('page_no');
            $table->string('area_code', 100)->nullable();
            $table->unsignedSmallInteger('question_no')->nullable();
            $table->string('option_code', 32)->nullable();
            $table->json('option_evidence_json')->nullable();
            $table->json('machine_proposal_json')->nullable();
            $table->decimal('machine_confidence', 5, 4)->nullable();
            $table->char('evidence_hash', 64);
            $table->string('evidence_version', 32)->default('v1');
            $table->timestamps();

            $table->unique(
                ['diagnostic_capture_id', 'item_key'],
                'omr_diag_item_evidence_capture_item_unique',
            );
            $table->index(
                ['diagnostic_capture_id', 'page_no', 'area_code', 'question_no', 'option_code'],
                'omr_diag_item_evidence_capture_order_idx',
            );
            $table->index(
                ['diagnostic_capture_id', 'evidence_hash'],
                'omr_diag_item_evidence_capture_hash_idx',
            );
        });

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::getConnection()->statement(<<<'SQL'
            ALTER TABLE omr_diagnostic_item_evidence
                ADD CONSTRAINT omr_diag_item_evidence_item_key_check
                    CHECK (char_length(trim(item_key)) > 0),
                ADD CONSTRAINT omr_diag_item_evidence_page_no_check
                    CHECK (page_no > 0),
                ADD CONSTRAINT omr_diag_item_evidence_area_code_check
                    CHECK (area_code IS NULL OR char_length(trim(area_code)) > 0),
                ADD CONSTRAINT omr_diag_item_evidence_question_no_check
                    CHECK (question_no IS NULL OR question_no > 0),
                ADD CONSTRAINT omr_diag_item_evidence_option_code_check
                    CHECK (option_code IS NULL OR char_length(trim(option_code)) > 0),
                ADD CONSTRAINT omr_diag_item_evidence_confidence_check
                    CHECK (machine_confidence IS NULL OR (machine_confidence >= 0 AND machine_confidence <= 1)),
                ADD CONSTRAINT omr_diag_item_evidence_hash_check
                    CHECK (evidence_hash ~ '^[a-fA-F0-9]{64}$'),
                ADD CONSTRAINT omr_diag_item_evidence_version_check
                    CHECK (char_length(trim(evidence_version)) > 0)
        SQL);

        Schema::getConnection()->statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION nexiam_omr_diagnostic_item_evidence_immutable()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            BEGIN
                RAISE EXCEPTION USING
                    ERRCODE = '55000',
                    MESSAGE = 'OMR diagnostic item evidence is immutable after insert';
                RETURN NEW;
            END;
            $function$
        SQL);
        Schema::getConnection()->statement(<<<'SQL'
            DROP TRIGGER IF EXISTS omr_diag_item_evidence_immutable_guard
                ON omr_diagnostic_item_evidence
        SQL);
        Schema::getConnection()->statement(<<<'SQL'
            CREATE TRIGGER omr_diag_item_evidence_immutable_guard
            BEFORE UPDATE OR DELETE
            ON omr_diagnostic_item_evidence
            FOR EACH ROW
            EXECUTE FUNCTION nexiam_omr_diagnostic_item_evidence_immutable()
        SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'DROP TRIGGER IF EXISTS omr_diag_item_evidence_immutable_guard ON omr_diagnostic_item_evidence',
            );
            Schema::getConnection()->statement(
                'DROP FUNCTION IF EXISTS nexiam_omr_diagnostic_item_evidence_immutable()',
            );
        }

        Schema::dropIfExists('omr_diagnostic_item_evidence');
    }
};
