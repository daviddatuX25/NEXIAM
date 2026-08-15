<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consultation scheduling (2026-08-12-consultation-plan-aggregate, Phase 1).
 *
 * Mirrors exam_sessions/exam_assignments 1:1 per founder direction. Deliberate
 * deviation from the exam pair-unique precedent is none — exam dropped the hard
 * (session, attempt) unique when sources/statuses landed; consultation follows
 * the same pattern: partial unique one-active-per-attempt (rebooking the same
 * session after cancellation stays possible, history preserved).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('status')->default('draft');
            $table->string('booking_mode')->default('staff_only');
            $table->timestamp('scheduled_at');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('reservation_opened_at')->nullable();
            $table->timestamp('reservation_closed_at')->nullable();
            $table->timestamps();

            $table->unique(['academic_cycle_id', 'code']);
            $table->index(['academic_cycle_id', 'status']);
            $table->index(['booking_mode', 'status']);
        });

        Schema::create('consultation_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->restrictOnDelete();
            $table->string('status')->default('assigned');
            $table->string('source')->default('staff_assigned');
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reserved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('consulted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['admission_attempt_id', 'status']);
            $table->index(['consultation_session_id', 'status']);
            $table->index(['source', 'status']);
        });

        // At most one active consultation assignment per attempt (exam parity).
        DB::statement(
            "CREATE UNIQUE INDEX consultation_assignments_one_active_per_attempt ON consultation_assignments (admission_attempt_id) WHERE status = 'assigned'"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS consultation_assignments_one_active_per_attempt');
        Schema::dropIfExists('consultation_assignments');
        Schema::dropIfExists('consultation_sessions');
    }
};
