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
        Schema::create('admission_clearance_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('programs')->restrictOnDelete();
            $table->string('receipt_number', 64)->unique();
            $table->string('status', 32)->default('confirmed');
            $table->json('evidence_vector');
            $table->foreignId('confirmed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at');
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason', 255)->nullable();
            $table->string('idempotency_key', 128)->unique();
            $table->timestamps();

            $table->index(['admission_attempt_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_clearance_receipts');
    }
};
