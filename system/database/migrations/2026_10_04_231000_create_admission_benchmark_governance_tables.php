<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_benchmark_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('base_benchmark_set_id')->nullable()->constrained('admission_benchmark_sets')->restrictOnDelete();
            $table->string('feature_schema_digest', 64);
            $table->unsignedInteger('current_version')->default(0);
            $table->timestamps();
            $table->unique(['institution_id', 'program_id', 'academic_cycle_id'], 'admission_benchmark_drafts_scope_unique');
        });

        Schema::create('admission_benchmark_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('draft_id')->constrained('admission_benchmark_drafts')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->json('old_content')->nullable();
            $table->json('content');
            $table->string('reason', 500);
            $table->foreignId('predecessor_revision_id')->nullable()->constrained('admission_benchmark_revisions')->restrictOnDelete();
            $table->foreignId('restored_from_revision_id')->nullable()->constrained('admission_benchmark_revisions')->restrictOnDelete();
            $table->string('command_receipt', 255)->unique();
            $table->timestamps();
            $table->unique(['draft_id', 'version']);
        });

        Schema::table('admission_benchmark_activations', function (Blueprint $table): void {
            $table->string('idempotency_payload_sha256', 64)->nullable();
            $table->unsignedBigInteger('expected_current_activation_id')->nullable();
        });

        Schema::table('admission_benchmark_sets', function (Blueprint $table): void {
            $table->string('import_payload_sha256', 64)->nullable();
        });
        Schema::table('admission_benchmark_sets', function (Blueprint $table): void {
            $table->json('publication_manifest_json')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('admission_benchmark_activations', function (Blueprint $table): void {
            $table->dropColumn(['idempotency_payload_sha256', 'expected_current_activation_id']);
        });
        Schema::table('admission_benchmark_sets', function (Blueprint $table): void {
            $table->dropColumn('import_payload_sha256');
            $table->dropColumn('publication_manifest_json');
        });

        Schema::dropIfExists('admission_benchmark_revisions');
        Schema::dropIfExists('admission_benchmark_drafts');
    }
};
