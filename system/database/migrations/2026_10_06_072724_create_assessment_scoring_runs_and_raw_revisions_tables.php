<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assessment_scoring_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('administration_id')->constrained('assessment_administrations')->restrictOnDelete();
            $table->uuid('run_uuid');
            $table->string('source', 16);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 255);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['administration_id', 'idempotency_key'], 'assessment_scoring_runs_admin_idempotency_unique');
        });

        Schema::create('assessment_raw_result_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('administration_id')->constrained('assessment_administrations')->restrictOnDelete();
            $table->foreignId('scoring_run_id')->constrained('assessment_scoring_runs')->restrictOnDelete();
            $table->foreignId('metric_id')->constrained('test_instrument_metrics')->restrictOnDelete();
            $table->string('metric_code', 32);
            $table->unsignedInteger('revision_no')->default(1);
            $table->decimal('raw_value', 18, 6);
            $table->string('source', 32);
            $table->string('status', 16);
            $table->json('provenance_json');
            $table->boolean('is_current')->default(true);
            $table->timestamp('superseded_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['administration_id', 'metric_id', 'revision_no'], 'assessment_raw_revisions_metric_revision_unique');
            $table->index(['administration_id', 'is_current'], 'assessment_raw_revisions_admin_current_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_raw_result_revisions');
        Schema::dropIfExists('assessment_scoring_runs');
    }
};
