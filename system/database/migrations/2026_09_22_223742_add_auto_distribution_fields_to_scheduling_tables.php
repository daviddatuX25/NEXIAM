<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->boolean('auto_assign_enabled')->default(false)->after('booking_mode');
            $table->index(
                ['academic_cycle_id', 'status', 'booking_mode', 'auto_assign_enabled', 'starts_at'],
                'exam_sessions_distribution_targets_index',
            );
        });

        Schema::table('consultation_sessions', function (Blueprint $table) {
            $table->boolean('auto_assign_enabled')->default(false)->after('booking_mode');
            $table->index(
                ['academic_cycle_id', 'status', 'booking_mode', 'auto_assign_enabled', 'scheduled_at'],
                'consultation_sessions_distribution_targets_index',
            );
        });

        Schema::table('exam_assignments', function (Blueprint $table) {
            $table->enum('origin', ['manual', 'batch', 'continuous', 'reservation'])
                ->default('manual')
                ->after('source');
            $table->index(['origin', 'status'], 'exam_assignments_origin_status_index');
        });

        Schema::table('consultation_assignments', function (Blueprint $table) {
            $table->enum('origin', ['manual', 'batch', 'continuous', 'reservation'])
                ->default('manual')
                ->after('source');
            $table->index(['origin', 'status'], 'consultation_assignments_origin_status_index');
        });

        DB::table('exam_assignments')
            ->where('source', 'applicant_reserved')
            ->update(['origin' => 'reservation']);
        DB::table('consultation_assignments')
            ->where('source', 'applicant_reserved')
            ->update(['origin' => 'reservation']);

    }

    public function down(): void
    {

        Schema::table('exam_assignments', function (Blueprint $table) {
            $table->dropIndex('exam_assignments_origin_status_index');
            $table->dropColumn('origin');
        });

        Schema::table('consultation_assignments', function (Blueprint $table) {
            $table->dropIndex('consultation_assignments_origin_status_index');
            $table->dropColumn('origin');
        });

        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropIndex('exam_sessions_distribution_targets_index');
            $table->dropColumn('auto_assign_enabled');
        });

        Schema::table('consultation_sessions', function (Blueprint $table) {
            $table->dropIndex('consultation_sessions_distribution_targets_index');
            $table->dropColumn('auto_assign_enabled');
        });
    }
};
