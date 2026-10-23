<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_benchmark_revisions', function (Blueprint $table): void {
            $table->foreignId('base_benchmark_set_id')->nullable()
                ->constrained('admission_benchmark_sets', indexName: 'benchmark_revisions_base_set_foreign')->restrictOnDelete();
            $table->string('feature_schema_digest', 64)->nullable();
        });

        DB::statement('UPDATE admission_benchmark_revisions
            SET base_benchmark_set_id = (
                SELECT base_benchmark_set_id FROM admission_benchmark_drafts
                WHERE admission_benchmark_drafts.id = admission_benchmark_revisions.draft_id
            ), feature_schema_digest = (
                SELECT feature_schema_digest FROM admission_benchmark_drafts
                WHERE admission_benchmark_drafts.id = admission_benchmark_revisions.draft_id
            )
            WHERE base_benchmark_set_id IS NULL AND feature_schema_digest IS NULL');
    }

    public function down(): void
    {
        Schema::table('admission_benchmark_revisions', function (Blueprint $table): void {
            $table->dropForeign('benchmark_revisions_base_set_foreign');
            $table->dropColumn(['base_benchmark_set_id', 'feature_schema_digest']);
        });
    }
};
