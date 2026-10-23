<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_diagnostic_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('execution_kind', 16)->default('diagnostic');
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->foreignId('academic_cycle_id')->constrained('academic_cycles')->restrictOnDelete();
            $table->foreignId('template_version_id')->constrained('omr_answer_sheet_template_versions')->restrictOnDelete();
            $table->foreignId('booklet_version_id')->constrained('omr_question_booklet_versions')->restrictOnDelete();
            $table->string('declared_set_code', 8);
            $table->string('status', 24)->default('open');
            $table->string('phase', 24)->default('capture');
            $table->unsignedBigInteger('revision')->default(0);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['institution_id', 'academic_cycle_id', 'status', 'id'],
                'omr_diagnostic_runs_scope_status_idx',
            );
            $table->index(
                ['template_version_id', 'booklet_version_id', 'declared_set_code'],
                'omr_diagnostic_runs_material_idx',
            );
            $table->index(
                ['status', 'phase', 'updated_at', 'id'],
                'omr_diagnostic_runs_status_phase_idx',
            );
            $table->index(
                ['created_by_user_id', 'created_at'],
                'omr_diagnostic_runs_creator_idx',
            );
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE omr_diagnostic_runs
                    ADD CONSTRAINT omr_diagnostic_runs_execution_kind_check
                        CHECK (execution_kind = 'diagnostic'),
                    ADD CONSTRAINT omr_diagnostic_runs_status_check
                        CHECK (status IN ('open', 'processing', 'needs_review', 'completed', 'failed')),
                    ADD CONSTRAINT omr_diagnostic_runs_phase_check
                        CHECK (phase IN ('capture', 'processing', 'evidence', 'review', 'recovery', 'complete')),
                    ADD CONSTRAINT omr_diagnostic_runs_set_code_check
                        CHECK (declared_set_code IN ('A', 'B', 'C', 'D')),
                    ADD CONSTRAINT omr_diagnostic_runs_revision_check
                        CHECK (revision >= 0),
                    ADD CONSTRAINT omr_diagnostic_runs_completed_at_check
                        CHECK (
                            (status IN ('completed', 'failed') AND completed_at IS NOT NULL)
                            OR (status NOT IN ('completed', 'failed') AND completed_at IS NULL)
                        ),
                    ADD CONSTRAINT omr_diagnostic_runs_owner_check
                        CHECK (
                            institution_id IS NOT NULL
                            AND academic_cycle_id IS NOT NULL
                            AND template_version_id IS NOT NULL
                            AND booklet_version_id IS NOT NULL
                            AND created_by_user_id IS NOT NULL
                        )
            SQL);
        }

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::getConnection()->statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION nexiam_omr_diagnostic_run_immutable()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            BEGIN
                IF NEW.id IS DISTINCT FROM OLD.id
                    OR NEW.public_id IS DISTINCT FROM OLD.public_id
                    OR NEW.execution_kind IS DISTINCT FROM OLD.execution_kind
                    OR NEW.institution_id IS DISTINCT FROM OLD.institution_id
                    OR NEW.academic_cycle_id IS DISTINCT FROM OLD.academic_cycle_id
                    OR NEW.template_version_id IS DISTINCT FROM OLD.template_version_id
                    OR NEW.booklet_version_id IS DISTINCT FROM OLD.booklet_version_id
                    OR NEW.declared_set_code IS DISTINCT FROM OLD.declared_set_code
                    OR NEW.created_by_user_id IS DISTINCT FROM OLD.created_by_user_id
                    OR NEW.created_at IS DISTINCT FROM OLD.created_at
                THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '55000',
                        MESSAGE = 'OMR diagnostic run material, owner, and context are immutable after insert';
                END IF;

                RETURN NEW;
            END;
            $function$
        SQL);
        Schema::getConnection()->statement(<<<'SQL'
            DROP TRIGGER IF EXISTS omr_diag_run_immutable_guard
                ON omr_diagnostic_runs
        SQL);
        Schema::getConnection()->statement(<<<'SQL'
            CREATE TRIGGER omr_diag_run_immutable_guard
            BEFORE UPDATE
            ON omr_diagnostic_runs
            FOR EACH ROW
            EXECUTE FUNCTION nexiam_omr_diagnostic_run_immutable()
        SQL);

        Schema::getConnection()->statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION nexiam_omr_diagnostic_run_revision_guard()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            BEGIN
                IF NEW.revision < OLD.revision THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '55000',
                        MESSAGE = 'OMR diagnostic run revision must be monotonic';
                END IF;

                RETURN NEW;
            END;
            $function$
        SQL);
        Schema::getConnection()->statement(<<<'SQL'
            DROP TRIGGER IF EXISTS omr_diag_run_revision_guard
                ON omr_diagnostic_runs
        SQL);
        Schema::getConnection()->statement(<<<'SQL'
            CREATE TRIGGER omr_diag_run_revision_guard
            BEFORE UPDATE
            ON omr_diagnostic_runs
            FOR EACH ROW
            EXECUTE FUNCTION nexiam_omr_diagnostic_run_revision_guard()
        SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'DROP TRIGGER IF EXISTS omr_diag_run_revision_guard ON omr_diagnostic_runs',
            );
            Schema::getConnection()->statement(
                'DROP FUNCTION IF EXISTS nexiam_omr_diagnostic_run_revision_guard()',
            );
            Schema::getConnection()->statement(
                'DROP TRIGGER IF EXISTS omr_diag_run_immutable_guard ON omr_diagnostic_runs',
            );
            Schema::getConnection()->statement(
                'DROP FUNCTION IF EXISTS nexiam_omr_diagnostic_run_immutable()',
            );
        }

        Schema::dropIfExists('omr_diagnostic_runs');
    }
};
