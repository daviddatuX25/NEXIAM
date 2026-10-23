<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_benchmark_recoveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_benchmark_set_id')->constrained('admission_benchmark_sets')->restrictOnDelete();
            $table->foreignId('source_activation_id')->constrained('admission_benchmark_activations')->restrictOnDelete();
            $table->unsignedInteger('source_version');
            $table->string('source_content_sha256', 64);
            $table->string('source_schema_sha256', 64);
            $table->string('current_schema_sha256', 64);
            $table->unsignedInteger('source_activation_sequence');
            $table->json('source_snapshot_json');
            $table->string('source_snapshot_sha256', 64);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->timestamps();
            $table->unique(['source_benchmark_set_id', 'academic_cycle_id'], 'benchmark_recoveries_source_cycle_unique');
        });

        Schema::table('admission_benchmark_drafts', function (Blueprint $table): void {
            $table->foreignId('recovery_id')->nullable()
                ->constrained('admission_benchmark_recoveries', indexName: 'benchmark_drafts_recovery_foreign')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admission_benchmark_drafts', function (Blueprint $table): void {
            $table->dropForeign('benchmark_drafts_recovery_foreign');
            $table->dropColumn('recovery_id');
        });

        Schema::dropIfExists('admission_benchmark_recoveries');
    }
};
