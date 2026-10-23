<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_kit_preparations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('test_kit_id')->constrained('test_kits')->restrictOnDelete();
            $table->string('state', 16)->default('preparing');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('test_instrument_version_id')->nullable()->constrained('test_instrument_versions')->restrictOnDelete();
            $table->foreignId('norm_package_version_id')->nullable()->constrained('assessment_norm_package_versions')->restrictOnDelete();
            $table->foreignId('answer_sheet_draft_id')->nullable()->constrained('omr_template_authoring_drafts')->restrictOnDelete();
            $table->foreignId('template_version_id')->nullable()->constrained('omr_answer_sheet_template_versions')->restrictOnDelete();
            $table->foreignId('booklet_version_id')->nullable()->constrained('omr_question_booklet_versions')->restrictOnDelete();
            $table->foreignId('booklet_set_id')->nullable()->constrained('omr_booklet_sets')->restrictOnDelete();
            $table->unsignedBigInteger('assembled_test_kit_version_id')->nullable();
            $table->json('source_context_json')->nullable();
            $table->char('source_context_sha256', 64)->nullable();
            $table->json('assembly_context_json')->nullable();
            $table->char('assembly_context_sha256', 64)->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['id', 'test_kit_id'], 'kit_preparations_id_family_unique');
            $table->unique('assembled_test_kit_version_id', 'kit_preparations_assembled_version_unique');
            $table->index(['test_kit_id', 'created_at'], 'kit_preparations_family_created_idx');
            $table->foreign(
                ['assembled_test_kit_version_id', 'test_kit_id'],
                'kit_preparations_assembled_version_family_fk',
            )->references(['id', 'test_kit_id'])->on('test_kit_versions')->restrictOnDelete();
        });

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX kit_preparations_one_open_per_family ON test_kit_preparations (test_kit_id) WHERE state = 'preparing'");
            DB::statement(<<<'SQL'
                ALTER TABLE test_kit_preparations
                    ADD CONSTRAINT kit_preparations_state_check
                        CHECK (state IN ('preparing', 'assembled')),
                    ADD CONSTRAINT kit_preparations_revision_check
                        CHECK (revision >= 1),
                    ADD CONSTRAINT kit_preparations_source_context_check
                        CHECK (
                            (source_context_json IS NULL AND source_context_sha256 IS NULL)
                            OR
                            (source_context_json IS NOT NULL AND source_context_sha256 ~ '^[0-9a-f]{64}$')
                        ),
                    ADD CONSTRAINT kit_preparations_assembly_context_check
                        CHECK (
                            (state = 'preparing' AND assembled_test_kit_version_id IS NULL AND assembly_context_json IS NULL AND assembly_context_sha256 IS NULL)
                            OR
                            (state = 'assembled' AND assembled_test_kit_version_id IS NOT NULL AND assembly_context_json IS NOT NULL AND assembly_context_sha256 ~ '^[0-9a-f]{64}$')
                        )
            SQL);
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION nexiam_test_kit_preparation_immutable_closed()
                RETURNS trigger
                LANGUAGE plpgsql
                AS $function$
                BEGIN
                    IF TG_OP = 'DELETE' THEN
                        IF OLD.state = 'assembled' THEN
                            RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'assembled Test Kit preparations are immutable history';
                        END IF;

                        RETURN OLD;
                    END IF;

                    IF OLD.state = 'assembled' THEN
                        RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'assembled Test Kit preparations are immutable history';
                    END IF;

                    IF OLD.test_kit_id IS DISTINCT FROM NEW.test_kit_id
                        OR OLD.created_by_user_id IS DISTINCT FROM NEW.created_by_user_id
                        OR OLD.created_at IS DISTINCT FROM NEW.created_at
                        OR OLD.source_context_json::jsonb IS DISTINCT FROM NEW.source_context_json::jsonb
                        OR OLD.source_context_sha256 IS DISTINCT FROM NEW.source_context_sha256
                        OR (NEW.state = 'assembled' AND (
                            OLD.test_instrument_version_id IS DISTINCT FROM NEW.test_instrument_version_id
                            OR OLD.norm_package_version_id IS DISTINCT FROM NEW.norm_package_version_id
                            OR OLD.answer_sheet_draft_id IS DISTINCT FROM NEW.answer_sheet_draft_id
                            OR OLD.template_version_id IS DISTINCT FROM NEW.template_version_id
                            OR OLD.booklet_version_id IS DISTINCT FROM NEW.booklet_version_id
                            OR OLD.booklet_set_id IS DISTINCT FROM NEW.booklet_set_id
                        )) THEN
                        RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'Test Kit preparation identity and snapshot selections are immutable';
                    END IF;

                    IF NEW.state NOT IN ('preparing', 'assembled')
                        OR NEW.revision < 1
                        OR (NEW.source_context_json IS NOT NULL AND NEW.source_context_sha256 !~ '^[0-9a-f]{64}$')
                        OR (NEW.source_context_json IS NULL AND NEW.source_context_sha256 IS NOT NULL)
                        OR (NEW.state = 'assembled' AND (
                            NEW.assembled_test_kit_version_id IS NULL
                            OR NEW.assembly_context_json IS NULL
                            OR NEW.assembly_context_sha256 !~ '^[0-9a-f]{64}$'
                        ))
                        OR (NEW.state = 'preparing' AND (
                            NEW.assembled_test_kit_version_id IS NOT NULL
                            OR NEW.assembly_context_json IS NOT NULL
                            OR NEW.assembly_context_sha256 IS NOT NULL
                        )) THEN
                        RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'Test Kit preparation state and assembly context are inconsistent';
                    END IF;


                    RETURN NEW;
                END;
                $function$
            SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER kit_preparations_closed_immutable_guard
                BEFORE UPDATE OR DELETE ON test_kit_preparations
                FOR EACH ROW EXECUTE FUNCTION nexiam_test_kit_preparation_immutable_closed()
            SQL);
        } elseif ($driver === 'sqlite') {
            DB::statement("CREATE UNIQUE INDEX kit_preparations_one_open_per_family ON test_kit_preparations (test_kit_id) WHERE state = 'preparing'");
            DB::statement(<<<'SQL'
                CREATE TRIGGER kit_preparations_state_insert_check
                BEFORE INSERT ON test_kit_preparations
                FOR EACH ROW
                WHEN NEW.state NOT IN ('preparing', 'assembled')
                    OR NEW.revision < 1
                    OR (NEW.source_context_json IS NOT NULL AND (NEW.source_context_sha256 IS NULL OR length(NEW.source_context_sha256) <> 64 OR NEW.source_context_sha256 GLOB '*[^0-9a-f]*'))
                    OR (NEW.source_context_json IS NULL AND NEW.source_context_sha256 IS NOT NULL)
                    OR (NEW.state = 'assembled' AND (NEW.assembled_test_kit_version_id IS NULL OR NEW.assembly_context_json IS NULL OR length(NEW.assembly_context_sha256) <> 64 OR NEW.assembly_context_sha256 GLOB '*[^0-9a-f]*'))
                    OR (NEW.state = 'preparing' AND (NEW.assembled_test_kit_version_id IS NOT NULL OR NEW.assembly_context_json IS NOT NULL OR NEW.assembly_context_sha256 IS NOT NULL))
                BEGIN
                    SELECT RAISE(ABORT, 'Test Kit preparation state and context are inconsistent');
                END
            SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER kit_preparations_state_update_check
                BEFORE UPDATE ON test_kit_preparations
                FOR EACH ROW
                WHEN OLD.state = 'assembled'
                    OR OLD.test_kit_id IS NOT NEW.test_kit_id
                    OR OLD.created_by_user_id IS NOT NEW.created_by_user_id
                    OR OLD.created_at IS NOT NEW.created_at
                    OR OLD.source_context_json IS NOT NEW.source_context_json
                    OR OLD.source_context_sha256 IS NOT NEW.source_context_sha256
                    OR (NEW.state = 'assembled' AND (
                        OLD.test_instrument_version_id IS NOT NEW.test_instrument_version_id
                        OR OLD.norm_package_version_id IS NOT NEW.norm_package_version_id
                        OR OLD.answer_sheet_draft_id IS NOT NEW.answer_sheet_draft_id
                        OR OLD.template_version_id IS NOT NEW.template_version_id
                        OR OLD.booklet_version_id IS NOT NEW.booklet_version_id
                        OR OLD.booklet_set_id IS NOT NEW.booklet_set_id
                    ))
                    OR NEW.state NOT IN ('preparing', 'assembled')
                    OR NEW.revision < 1
                    OR (NEW.source_context_json IS NOT NULL AND (NEW.source_context_sha256 IS NULL OR length(NEW.source_context_sha256) <> 64 OR NEW.source_context_sha256 GLOB '*[^0-9a-f]*'))
                    OR (NEW.source_context_json IS NULL AND NEW.source_context_sha256 IS NOT NULL)
                    OR (NEW.state = 'assembled' AND (NEW.assembled_test_kit_version_id IS NULL OR NEW.assembly_context_json IS NULL OR length(NEW.assembly_context_sha256) <> 64 OR NEW.assembly_context_sha256 GLOB '*[^0-9a-f]*'))
                    OR (NEW.state = 'preparing' AND (NEW.assembled_test_kit_version_id IS NOT NULL OR NEW.assembly_context_json IS NOT NULL OR NEW.assembly_context_sha256 IS NOT NULL))
                BEGIN
                    SELECT RAISE(ABORT, 'Assembled Test Kit preparations are immutable history');
                END
            SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER kit_preparations_closed_delete_guard
                BEFORE DELETE ON test_kit_preparations
                FOR EACH ROW
                WHEN OLD.state = 'assembled'
                BEGIN
                    SELECT RAISE(ABORT, 'Assembled Test Kit preparations are immutable history');
                END
            SQL);
        } else {
            throw new RuntimeException('Test Kit preparations require PostgreSQL or SQLite.');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS kit_preparations_closed_immutable_guard ON test_kit_preparations');
            DB::statement('DROP FUNCTION IF EXISTS nexiam_test_kit_preparation_immutable_closed()');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS kit_preparations_state_insert_check');
            DB::statement('DROP TRIGGER IF EXISTS kit_preparations_state_update_check');
            DB::statement('DROP TRIGGER IF EXISTS kit_preparations_closed_delete_guard');
        }

        Schema::dropIfExists('test_kit_preparations');
    }
};
