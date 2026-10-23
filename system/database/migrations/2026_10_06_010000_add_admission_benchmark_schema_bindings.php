<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_benchmark_features', function (Blueprint $table): void {
            $table->json('source_definition_json')->nullable();
        });

        Schema::table('admission_benchmark_drafts', function (Blueprint $table): void {
            $table->json('feature_schema_json')->nullable();
        });

        Schema::table('admission_benchmark_revisions', function (Blueprint $table): void {
            $table->json('feature_schema_json')->nullable();
        });

        Schema::create('admission_benchmark_subject_bindings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('intake_grade_field_id')->constrained('intake_grade_fields')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('feature_key', 64);
            $table->string('label', 128);
            $table->string('unit', 64);
            $table->decimal('min_value', 8, 2)->nullable();
            $table->decimal('max_value', 8, 2)->nullable();
            $table->boolean('is_model_required')->default(false);
            $table->json('source_snapshot_json');
            $table->foreignId('approved_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->string('reason', 500);
            $table->string('idempotency_key', 128)->unique();
            $table->timestamps();
            $table->unique(['institution_id', 'feature_key', 'version'], 'benchmark_subject_binding_version_unique');
            $table->index(['institution_id', 'intake_grade_field_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_benchmark_subject_bindings');
        Schema::table('admission_benchmark_revisions', function (Blueprint $table): void {
            $table->dropColumn('feature_schema_json');
        });
        Schema::table('admission_benchmark_drafts', function (Blueprint $table): void {
            $table->dropColumn('feature_schema_json');
        });
        Schema::table('admission_benchmark_features', function (Blueprint $table): void {
            $table->dropColumn('source_definition_json');
        });
    }
};
