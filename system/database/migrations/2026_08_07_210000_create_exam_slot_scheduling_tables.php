<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Option A (D-088 amended): session booking_mode; assignment source; no SlotPlan/soft holds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->string('booking_mode')->default('staff_only')->after('status');
            $table->timestamp('reservation_opened_at')->nullable()->after('published_at');
            $table->timestamp('reservation_closed_at')->nullable()->after('reservation_opened_at');
            $table->index(['booking_mode', 'status']);
        });

        Schema::table('exam_assignments', function (Blueprint $table) {
            $table->string('source')->default('staff_assigned')->after('status');
            $table->timestamp('ended_at')->nullable()->after('source');
            $table->text('end_reason')->nullable()->after('ended_at');

            $table->dropUnique(['exam_session_id', 'admission_attempt_id']);
            $table->index(['exam_session_id', 'status']);
            $table->index(['source', 'status']);
        });

        // D-088 §3: at most one active assignment per attempt.
        DB::statement(
            "CREATE UNIQUE INDEX exam_assignments_one_active_per_attempt ON exam_assignments (admission_attempt_id) WHERE status = 'assigned'"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS exam_assignments_one_active_per_attempt');

        Schema::table('exam_assignments', function (Blueprint $table) {
            $table->dropIndex(['exam_session_id', 'status']);
            $table->dropIndex(['source', 'status']);
            $table->dropColumn(['source', 'ended_at', 'end_reason']);
            $table->unique(['exam_session_id', 'admission_attempt_id']);
        });

        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropIndex(['booking_mode', 'status']);
            $table->dropColumn(['booking_mode', 'reservation_opened_at', 'reservation_closed_at']);
        });
    }
};
