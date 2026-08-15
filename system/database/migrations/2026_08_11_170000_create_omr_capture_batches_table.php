<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_capture_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_session_id')->constrained('grading_sessions')->restrictOnDelete();
            $table->string('status', 16)->default('staging');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->index(['grading_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omr_capture_batches');
    }
};
