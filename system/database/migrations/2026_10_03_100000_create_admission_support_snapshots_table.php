<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_support_snapshots', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->cascadeOnDelete();
            $table->foreignId('academic_cycle_id')->constrained('academic_cycles')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('engine_slot');
            $table->string('engine_version');
            $table->foreignId('benchmark_activation_id')->nullable()->constrained('admission_benchmark_activations')->nullOnDelete();
            $table->string('benchmark_hash', 64)->nullable();
            $table->string('input_digest', 64)->nullable();
            $table->jsonb('payload');
            $table->string('trigger_event');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['admission_attempt_id', 'version']);
            $table->index('academic_cycle_id');
            $table->index(['admission_attempt_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_support_snapshots');
    }
};
