<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_encounters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->restrictOnDelete();
            $table->foreignId('consultation_session_id')->nullable()->constrained('consultation_sessions')->nullOnDelete();
            $table->foreignId('consultation_assignment_id')->nullable()->constrained('consultation_assignments')->nullOnDelete();
            $table->foreignId('counselor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('medium', 16);
            $table->string('booking_path', 16);
            $table->string('status', 32)->default('invited');
            $table->string('room_name', 64)->nullable();
            $table->unsignedInteger('schedule_generation')->default(1);
            $table->timestamp('admitted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('close_reason')->nullable();
            $table->string('media_close_status', 32)->default('none');
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->index(['institution_id', 'admission_attempt_id', 'status']);
            $table->index(['consultation_session_id', 'status']);
            $table->index(['counselor_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_encounters');
    }
};
