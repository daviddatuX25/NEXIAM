<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_instrument_metrics', function (Blueprint $table): void {
            $table->text('formula_expression')->nullable();
            $table->json('formula_ast_json')->nullable();
            $table->json('item_codes_json')->nullable();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE test_instrument_metrics DROP CONSTRAINT test_instrument_metrics_rule_check');
            Schema::getConnection()->statement("ALTER TABLE test_instrument_metrics ADD CONSTRAINT test_instrument_metrics_rule_check CHECK (rule_kind IN ('correct_item_count', 'sum_raw_metrics', 'formula_raw_metrics', 'recorded_measure'))");
        }
    }

    public function down(): void
    {
        $hasFormulaExtensions = Schema::getConnection()->table('test_instrument_metrics')
            ->where('rule_kind', 'formula_raw_metrics')
            ->orWhereNotNull('formula_expression')
            ->orWhereNotNull('formula_ast_json')
            ->orWhereNotNull('item_codes_json')
            ->exists();
        if ($hasFormulaExtensions) {
            throw new RuntimeException('Formula or item-subset metric rows prevent rolling back the formula-scoring migration.');
        }
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE test_instrument_metrics DROP CONSTRAINT test_instrument_metrics_rule_check');
            Schema::getConnection()->statement("ALTER TABLE test_instrument_metrics ADD CONSTRAINT test_instrument_metrics_rule_check CHECK (rule_kind IN ('correct_item_count', 'sum_raw_metrics', 'recorded_measure'))");
        }
        Schema::table('test_instrument_metrics', function (Blueprint $table): void {
            $table->dropColumn(['formula_expression', 'formula_ast_json', 'item_codes_json']);
        });
    }
};
