<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_administrations', function (Blueprint $table): void {
            $table->unique(['id', 'test_kit_version_id', 'manifest_sha256'], 'assessment_admin_kit_generation_owner_unique');
        });

        Schema::table('assessment_scoring_runs', function (Blueprint $table): void {
            $table->unsignedBigInteger('test_kit_version_id')->nullable();
            $table->unsignedInteger('test_kit_version_revision')->nullable();
            $table->char('manifest_sha256', 64)->nullable();
            $table->unsignedBigInteger('acceptance_receipt_id')->nullable();
            $table->foreign(['test_kit_version_id', 'manifest_sha256'], 'assessment_scoring_run_kit_manifest_fk')
                ->references(['id', 'manifest_sha256'])->on('test_kit_versions')->restrictOnDelete();
            $table->foreign(['acceptance_receipt_id', 'test_kit_version_id', 'manifest_sha256'], 'assessment_scoring_run_receipt_exact_fk')
                ->references(['id', 'test_kit_version_id', 'manifest_sha256'])->on('test_kit_acceptance_receipts')->restrictOnDelete();
            $table->foreign(['administration_id', 'test_kit_version_id', 'manifest_sha256'], 'assessment_scoring_run_admin_kit_owner_fk')
                ->references(['id', 'test_kit_version_id', 'manifest_sha256'])->on('assessment_administrations')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE assessment_scoring_runs
                ADD CONSTRAINT assessment_scoring_run_operation_pin_check
                CHECK (
                    (test_kit_version_id IS NULL AND test_kit_version_revision IS NULL AND manifest_sha256 IS NULL AND acceptance_receipt_id IS NULL)
                    OR
                    (source = 'omr' AND test_kit_version_id IS NOT NULL AND test_kit_version_revision IS NOT NULL AND manifest_sha256 IS NOT NULL AND acceptance_receipt_id IS NOT NULL)
                )
            SQL);
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION assessment_scoring_run_operation_pin_immutable() RETURNS trigger AS $$
                BEGIN
                    IF NEW.test_kit_version_id IS DISTINCT FROM OLD.test_kit_version_id
                        OR NEW.test_kit_version_revision IS DISTINCT FROM OLD.test_kit_version_revision
                        OR NEW.manifest_sha256 IS DISTINCT FROM OLD.manifest_sha256
                        OR NEW.acceptance_receipt_id IS DISTINCT FROM OLD.acceptance_receipt_id THEN
                        RAISE EXCEPTION 'Assessment scoring run operation acceptance pins are immutable';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
            SQL);
            DB::statement('DROP TRIGGER IF EXISTS assessment_scoring_run_operation_pin_immutable ON assessment_scoring_runs');
            DB::statement('CREATE TRIGGER assessment_scoring_run_operation_pin_immutable BEFORE UPDATE ON assessment_scoring_runs FOR EACH ROW EXECUTE FUNCTION assessment_scoring_run_operation_pin_immutable()');
        } else {
            DB::statement(<<<'SQL'
                CREATE TRIGGER assessment_scoring_run_operation_pin_insert_guard
                BEFORE INSERT ON assessment_scoring_runs
                WHEN NOT (
                    (NEW.test_kit_version_id IS NULL AND NEW.test_kit_version_revision IS NULL AND NEW.manifest_sha256 IS NULL AND NEW.acceptance_receipt_id IS NULL)
                    OR
                    (NEW.source = 'omr' AND NEW.test_kit_version_id IS NOT NULL AND NEW.test_kit_version_revision IS NOT NULL AND NEW.manifest_sha256 IS NOT NULL AND NEW.acceptance_receipt_id IS NOT NULL)
                )
                BEGIN SELECT RAISE(ABORT, 'assessment scoring run operation acceptance pins are incomplete'); END
            SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER assessment_scoring_run_operation_pin_update_guard
                BEFORE UPDATE OF source, test_kit_version_id, test_kit_version_revision, manifest_sha256, acceptance_receipt_id ON assessment_scoring_runs
                WHEN NOT (
                    (NEW.test_kit_version_id IS NULL AND NEW.test_kit_version_revision IS NULL AND NEW.manifest_sha256 IS NULL AND NEW.acceptance_receipt_id IS NULL)
                    OR
                    (NEW.source = 'omr' AND NEW.test_kit_version_id IS NOT NULL AND NEW.test_kit_version_revision IS NOT NULL AND NEW.manifest_sha256 IS NOT NULL AND NEW.acceptance_receipt_id IS NOT NULL)
                )
                BEGIN SELECT RAISE(ABORT, 'assessment scoring run operation acceptance pins do not match source'); END
            SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER assessment_scoring_run_operation_pin_immutable
                BEFORE UPDATE OF test_kit_version_id, test_kit_version_revision, manifest_sha256, acceptance_receipt_id ON assessment_scoring_runs
                WHEN NEW.test_kit_version_id IS NOT OLD.test_kit_version_id
                    OR NEW.test_kit_version_revision IS NOT OLD.test_kit_version_revision
                    OR NEW.manifest_sha256 IS NOT OLD.manifest_sha256
                    OR NEW.acceptance_receipt_id IS NOT OLD.acceptance_receipt_id
                BEGIN SELECT RAISE(ABORT, 'assessment scoring run operation acceptance pins are immutable'); END
            SQL);
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Operation acceptance proof pins are historical evidence; use a forward-fix migration instead of rolling this migration back.',
        );
    }
};
