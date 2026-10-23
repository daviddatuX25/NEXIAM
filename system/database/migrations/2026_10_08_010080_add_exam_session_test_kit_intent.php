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
                ['id', 'booklet_version_id', 'test_instrument_version_id'],
                'test_kit_session_material_tuple_unique',
            );
        });

        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->foreignId('test_kit_version_id')
                ->nullable()
                ->after('test_instrument_version_id');
            $table->foreign(
                ['test_kit_version_id', 'booklet_version_id', 'test_instrument_version_id'],
                'exam_session_kit_material_fk',
            )
                ->references(['id', 'booklet_version_id', 'test_instrument_version_id'])
                ->on('test_kit_versions')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE exam_sessions ADD CONSTRAINT exam_session_kit_pin_completeness_check CHECK (test_kit_version_id IS NULL OR (booklet_version_id IS NOT NULL AND test_instrument_version_id IS NOT NULL))');

            return;
        }

        foreach (['INSERT', 'UPDATE'] as $operation) {
            DB::statement('CREATE TRIGGER exam_session_kit_pin_'.strtolower($operation)."_guard BEFORE {$operation} ON exam_sessions FOR EACH ROW WHEN NEW.test_kit_version_id IS NOT NULL AND (NEW.booklet_version_id IS NULL OR NEW.test_instrument_version_id IS NULL) BEGIN SELECT RAISE(ABORT, 'exam session Test Kit pins require exact Booklet and profile'); END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE exam_sessions DROP CONSTRAINT exam_session_kit_pin_completeness_check');
        } else {
            DB::statement('DROP TRIGGER IF EXISTS exam_session_kit_pin_insert_guard');
            DB::statement('DROP TRIGGER IF EXISTS exam_session_kit_pin_update_guard');
        }

        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->dropForeign('exam_session_kit_material_fk');
            $table->dropColumn('test_kit_version_id');
        });

        Schema::table('test_kit_versions', function (Blueprint $table): void {
            $table->dropUnique('test_kit_session_material_tuple_unique');
        });
    }
};
