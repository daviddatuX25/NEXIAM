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
        Schema::create('enrollment_sheet_handoff_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->cascadeOnDelete();
            $table->foreignId('admission_clearance_receipt_id')->constrained('admission_clearance_receipts')->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('programs')->restrictOnDelete();
            $table->string('receipt_number', 64)->unique();
            $table->foreignId('issued_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at');
            $table->string('paper_reference', 128)->nullable();
            $table->string('idempotency_key', 128)->unique();
            $table->timestamps();

            $table->index(['admission_attempt_id', 'admission_clearance_receipt_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollment_sheet_handoff_receipts');
    }
};
