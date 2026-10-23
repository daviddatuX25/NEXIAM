<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_kit_versions', function (Blueprint $table): void {
            $table->unique(
                ['id', 'manifest_sha256', 'template_version_id', 'booklet_version_id', 'test_instrument_version_id'],
                'test_kit_version_production_material_tuple_unique',
            );
            $table->unique(['id', 'test_instrument_version_id'], 'test_kit_version_profile_tuple_unique');
        });

        Schema::table('exam_session_material_snapshots', function (Blueprint $table): void {
            $table->foreignId('test_kit_version_id')->nullable()->constrained('test_kit_versions')->restrictOnDelete();
            $table->char('manifest_sha256', 64)->nullable();
            $table->foreignId('acceptance_receipt_id')->nullable()->constrained('test_kit_acceptance_receipts')->restrictOnDelete();
            $table->unique(
                ['id', 'test_kit_version_id', 'manifest_sha256', 'acceptance_receipt_id', 'test_instrument_version_id'],
                'exam_snapshot_kit_profile_tuple_unique',
            );
            $table->unique(
                ['id', 'test_kit_version_id', 'manifest_sha256', 'acceptance_receipt_id', 'test_instrument_version_id', 'template_version_id', 'booklet_version_id'],
                'exam_snapshot_kit_material_tuple_unique',
            );
            $table->foreign(
                ['test_kit_version_id', 'manifest_sha256', 'template_version_id', 'booklet_version_id', 'test_instrument_version_id'],
                'exam_snapshot_kit_exact_material_fk',
            )->references(['id', 'manifest_sha256', 'template_version_id', 'booklet_version_id', 'test_instrument_version_id'])
                ->on('test_kit_versions')->restrictOnDelete();
            $table->foreign(['acceptance_receipt_id', 'test_kit_version_id', 'manifest_sha256'], 'exam_snapshot_kit_receipt_fk')
                ->references(['id', 'test_kit_version_id', 'manifest_sha256'])
                ->on('test_kit_acceptance_receipts')->restrictOnDelete();
        });

        Schema::table('assessment_administrations', function (Blueprint $table): void {
            $table->foreignId('test_kit_version_id')->nullable()->constrained('test_kit_versions')->restrictOnDelete();
            $table->char('manifest_sha256', 64)->nullable();
            $table->foreignId('acceptance_receipt_id')->nullable()->constrained('test_kit_acceptance_receipts')->restrictOnDelete();
            $table->json('procedure_snapshot_json')->nullable();
            $table->foreign(['test_kit_version_id', 'test_instrument_version_id'], 'assessment_admin_kit_profile_fk')
                ->references(['id', 'test_instrument_version_id'])
                ->on('test_kit_versions')->restrictOnDelete();
            $table->foreign(['acceptance_receipt_id', 'test_kit_version_id', 'manifest_sha256'], 'assessment_admin_kit_receipt_fk')
                ->references(['id', 'test_kit_version_id', 'manifest_sha256'])
                ->on('test_kit_acceptance_receipts')->restrictOnDelete();
            $table->foreign(
                ['material_snapshot_id', 'test_kit_version_id', 'manifest_sha256', 'acceptance_receipt_id', 'test_instrument_version_id'],
                'assessment_admin_snapshot_kit_material_fk',
            )->references(['id', 'test_kit_version_id', 'manifest_sha256', 'acceptance_receipt_id', 'test_instrument_version_id'])
                ->on('exam_session_material_snapshots')->restrictOnDelete();
        });

        $this->addPinCompletenessGuards();
    }

    private function addPinCompletenessGuards(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE exam_session_material_snapshots ADD CONSTRAINT exam_snapshot_test_kit_pin_completeness_check CHECK ((test_kit_version_id IS NULL AND manifest_sha256 IS NULL AND acceptance_receipt_id IS NULL) OR (test_kit_version_id IS NOT NULL AND manifest_sha256 IS NOT NULL AND manifest_sha256 ~ '^[a-f0-9]{64}$' AND acceptance_receipt_id IS NOT NULL AND test_instrument_version_id IS NOT NULL))");
            DB::statement("ALTER TABLE assessment_administrations ADD CONSTRAINT assessment_admin_test_kit_pin_completeness_check CHECK ((test_kit_version_id IS NULL AND manifest_sha256 IS NULL AND acceptance_receipt_id IS NULL) OR (test_kit_version_id IS NOT NULL AND manifest_sha256 IS NOT NULL AND manifest_sha256 ~ '^[a-f0-9]{64}$' AND acceptance_receipt_id IS NOT NULL AND material_snapshot_id IS NOT NULL))");

            return;
        }

        if (DB::getDriverName() !== 'sqlite') {
            throw new RuntimeException('Test Kit production pins require PostgreSQL or SQLite.');
        }

        foreach (['INSERT', 'UPDATE'] as $operation) {
            DB::statement('CREATE TRIGGER exam_snapshot_test_kit_pins_'.strtolower($operation)." BEFORE {$operation} ON exam_session_material_snapshots FOR EACH ROW WHEN NOT ((NEW.test_kit_version_id IS NULL AND NEW.manifest_sha256 IS NULL AND NEW.acceptance_receipt_id IS NULL) OR (NEW.test_kit_version_id IS NOT NULL AND NEW.manifest_sha256 IS NOT NULL AND length(NEW.manifest_sha256) = 64 AND NEW.acceptance_receipt_id IS NOT NULL AND NEW.test_instrument_version_id IS NOT NULL)) BEGIN SELECT RAISE(ABORT, 'exam snapshot Test Kit pins must be complete'); END");
            DB::statement('CREATE TRIGGER assessment_admin_test_kit_pins_'.strtolower($operation)." BEFORE {$operation} ON assessment_administrations FOR EACH ROW WHEN NOT ((NEW.test_kit_version_id IS NULL AND NEW.manifest_sha256 IS NULL AND NEW.acceptance_receipt_id IS NULL) OR (NEW.test_kit_version_id IS NOT NULL AND NEW.manifest_sha256 IS NOT NULL AND length(NEW.manifest_sha256) = 64 AND NEW.acceptance_receipt_id IS NOT NULL AND NEW.material_snapshot_id IS NOT NULL)) BEGIN SELECT RAISE(ABORT, 'assessment administration Test Kit pins must be complete'); END");
        }
    }

    private function dropPinCompletenessGuards(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE exam_session_material_snapshots DROP CONSTRAINT IF EXISTS exam_snapshot_test_kit_pin_completeness_check');
            DB::statement('ALTER TABLE assessment_administrations DROP CONSTRAINT IF EXISTS assessment_admin_test_kit_pin_completeness_check');

            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS exam_snapshot_test_kit_pins_insert');
        DB::statement('DROP TRIGGER IF EXISTS exam_snapshot_test_kit_pins_update');
        DB::statement('DROP TRIGGER IF EXISTS assessment_admin_test_kit_pins_insert');
        DB::statement('DROP TRIGGER IF EXISTS assessment_admin_test_kit_pins_update');
    }

    public function down(): void
    {
        $this->dropPinCompletenessGuards();

        Schema::table('assessment_administrations', function (Blueprint $table): void {
            $table->dropForeign('assessment_admin_snapshot_kit_material_fk');
            $table->dropForeign('assessment_admin_kit_receipt_fk');
            $table->dropForeign('assessment_admin_kit_profile_fk');
            $table->dropConstrainedForeignId('acceptance_receipt_id');
            $table->dropConstrainedForeignId('test_kit_version_id');
            $table->dropColumn(['manifest_sha256', 'procedure_snapshot_json']);
        });

        Schema::table('exam_session_material_snapshots', function (Blueprint $table): void {
            $table->dropForeign('exam_snapshot_kit_receipt_fk');
            $table->dropForeign('exam_snapshot_kit_exact_material_fk');
            $table->dropUnique('exam_snapshot_kit_material_tuple_unique');
            $table->dropUnique('exam_snapshot_kit_profile_tuple_unique');
            $table->dropConstrainedForeignId('test_kit_version_id');
            $table->dropColumn('manifest_sha256');
        });

        Schema::table('test_kit_versions', function (Blueprint $table): void {
            $table->dropUnique('test_kit_version_profile_tuple_unique');
            $table->dropUnique('test_kit_version_production_material_tuple_unique');
        });
    }
};
