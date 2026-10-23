<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_benchmark_recovery_program_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recovery_id')->constrained('admission_benchmark_recoveries')->restrictOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('registered_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->string('idempotency_key', 128);
            $table->timestamps();
            $table->unique(['recovery_id', 'program_id'], 'benchmark_recovery_program_review_unique');
            $table->unique(['recovery_id', 'idempotency_key'], 'benchmark_recovery_program_review_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_benchmark_recovery_program_reviews');
    }
};
