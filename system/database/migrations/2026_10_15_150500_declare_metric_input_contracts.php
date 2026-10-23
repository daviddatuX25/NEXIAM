<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('test_instrument_metrics', 'input_contract_json')) {
            Schema::table('test_instrument_metrics', static function (Blueprint $table): void {
                $table->json('input_contract_json')->nullable();
            });
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS test_instrument_metric_contract_insert_guard;
DROP TRIGGER IF EXISTS test_instrument_metric_contract_update_guard;
DROP TRIGGER IF EXISTS test_instrument_metric_contract_delete_guard;
CREATE TRIGGER test_instrument_metric_contract_insert_guard
    BEFORE INSERT ON test_instrument_metrics
    WHEN (SELECT status FROM test_instrument_versions WHERE id = NEW.test_instrument_version_id) IS NOT 'draft'
    BEGIN
        SELECT RAISE(ABORT, 'Test instrument version children are mutable only while the version is a draft');
    END;
CREATE TRIGGER test_instrument_metric_contract_update_guard
    BEFORE UPDATE ON test_instrument_metrics
    WHEN NEW.test_instrument_version_id IS NOT OLD.test_instrument_version_id
        OR (SELECT status FROM test_instrument_versions WHERE id = OLD.test_instrument_version_id) IS NOT 'draft'
    BEGIN
        SELECT RAISE(ABORT, 'Test instrument version children are mutable only while the version is a draft');
    END;
CREATE TRIGGER test_instrument_metric_contract_delete_guard
    BEFORE DELETE ON test_instrument_metrics
    WHEN (SELECT status FROM test_instrument_versions WHERE id = OLD.test_instrument_version_id) IS NOT 'draft'
    BEGIN
        SELECT RAISE(ABORT, 'Test instrument version children are mutable only while the version is a draft');
    END;
SQL);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Metric input declarations preserve versioned history; use a forward-fix migration instead of rolling them back.');
    }
};
