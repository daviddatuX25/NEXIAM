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
        if (! Schema::hasColumn('exam_session_applicant', 'attendance_status')) {
            Schema::table('exam_session_applicant', function (Blueprint $table) {
$table->enum('attendance_status', ['pending', 'present', 'absent'])->default('pending')->after('applicant_id');
                $table->timestamp('attendance_marked_at')->nullable()->after('attendance_status');
                $table->foreignId('attendance_marked_by')->nullable()->after('attendance_marked_at')->constrained('users')->nullOnDelete();
                $table->enum('submission_status', ['pending', 'submitted'])->default('pending')->after('attendance_marked_by');
                $table->timestamp('submitted_at')->nullable()->after('submission_status');
                $table->foreignId('submitted_to')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('exam_session_applicant', 'attendance_status')) {
            Schema::table('exam_session_applicant', function (Blueprint $table) {
                $table->dropForeign(['attendance_marked_by']);
                $table->dropForeign(['submitted_to']);
                $table->dropColumn([
                    'attendance_status',
                    'attendance_marked_at',
                    'attendance_marked_by',
                    'submission_status',
                    'submitted_at',
                    'submitted_to',
                ]);
            });
        }
    }
};
