<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_capture_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('omr_capture_batches')->cascadeOnDelete();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->restrictOnDelete();
            $table->string('status', 16)->default('detected');
            $table->decimal('sheet_confidence', 4, 3)->nullable();
            $table->json('proposed_score_json')->nullable();
            $table->string('abstention_reason', 255)->nullable();
            $table->string('overlay_asset_path', 255);
            $table->string('asset_hmac', 64);
            $table->foreignId('captured_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'admission_attempt_id']);
            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omr_capture_sheets');
    }
};
