<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_benchmark_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('predecessor_id')->nullable()->constrained('admission_benchmark_sets')->nullOnDelete();
            $table->string('status', 32)->default('draft');
            $table->string('name', 255);
            $table->string('change_reason', 500)->nullable();
            $table->string('schema_sha256', 64);
            $table->string('content_sha256', 64);
            $table->json('raw_ranges_json')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->string('idempotency_key', 255)->nullable()->unique();
            $table->timestamps();
            $table->unique(['institution_id', 'version']);
        });

        Schema::create('admission_benchmark_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('benchmark_set_id')->constrained('admission_benchmark_sets')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('feature_key', 64);
            $table->string('label', 128);
            $table->string('logical_type', 32);
            $table->foreignId('aptitude_area_id')->nullable()->constrained('aptitude_areas')->nullOnDelete();
            $table->boolean('is_required')->default(true);
            $table->timestamps();
            $table->unique(['benchmark_set_id', 'position']);
            $table->unique(['benchmark_set_id', 'feature_key']);
        });

        Schema::create('admission_benchmark_exemplars', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('benchmark_set_id')->constrained('admission_benchmark_sets')->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('tier_level');
            $table->string('tier_label', 64);
            $table->unsignedTinyInteger('target_class')->default(1);
            $table->timestamps();
            $table->unique(['benchmark_set_id', 'program_id', 'tier_level']);
        });

        Schema::create('admission_benchmark_exemplar_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exemplar_id')->constrained('admission_benchmark_exemplars')->cascadeOnDelete();
            $table->foreignId('benchmark_feature_id')->constrained('admission_benchmark_features')->cascadeOnDelete();
            $table->boolean('is_missing')->default(false);
            $table->decimal('numeric_value', 6, 2)->nullable();
            $table->string('categorical_value', 64)->nullable();
            $table->timestamps();
            $table->unique(['exemplar_id', 'benchmark_feature_id']);
        });

        Schema::create('admission_benchmark_activations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('benchmark_set_id')->constrained('admission_benchmark_sets')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 255)->nullable()->unique();
            $table->timestamp('activated_at')->useCurrent();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();
            $table->unique(['academic_cycle_id', 'sequence']);
            $table->index(['academic_cycle_id', 'superseded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_benchmark_activations');
        Schema::dropIfExists('admission_benchmark_exemplar_values');
        Schema::dropIfExists('admission_benchmark_exemplars');
        Schema::dropIfExists('admission_benchmark_features');
        Schema::dropIfExists('admission_benchmark_sets');
    }
};
