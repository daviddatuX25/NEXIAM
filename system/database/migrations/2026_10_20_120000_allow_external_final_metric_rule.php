<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE test_instrument_metrics DROP CONSTRAINT IF EXISTS test_instrument_metrics_rule_check');
            DB::statement("ALTER TABLE test_instrument_metrics ADD CONSTRAINT test_instrument_metrics_rule_check CHECK (rule_kind IN ('correct_item_count', 'sum_raw_metrics', 'formula_raw_metrics', 'recorded_measure', 'external_final'))");
        }

        DB::statement("CREATE UNIQUE INDEX test_instrument_metrics_external_construct_unique ON test_instrument_metrics (test_instrument_version_id, construct_aptitude_area_id) WHERE rule_kind = 'external_final'");
    }

    public function down(): void
    {
        if (DB::table('test_instrument_metrics')->where('rule_kind', 'external_final')->exists()) {
            throw new RuntimeException('External-final metric rows prevent rolling back the external-final metric migration.');
        }

        DB::statement('DROP INDEX test_instrument_metrics_external_construct_unique');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE test_instrument_metrics DROP CONSTRAINT IF EXISTS test_instrument_metrics_rule_check');
            DB::statement("ALTER TABLE test_instrument_metrics ADD CONSTRAINT test_instrument_metrics_rule_check CHECK (rule_kind IN ('correct_item_count', 'sum_raw_metrics', 'formula_raw_metrics', 'recorded_measure'))");
        }
    }
};
