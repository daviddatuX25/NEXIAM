<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assessment_result_sets', function (Blueprint $table): void {
            $table->foreignId('exam_session_id')
                ->nullable()
                ->after('grading_session_id')
                ->constrained('exam_sessions')
                ->restrictOnDelete();
        });

        DB::table('assessment_result_sets')->update([
            'exam_session_id' => DB::raw(
                '(SELECT exam_session_id FROM grading_sessions WHERE grading_sessions.id = assessment_result_sets.grading_session_id)',
            ),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessment_result_sets', function (Blueprint $table): void {
            $table->dropForeign(['exam_session_id']);
            $table->dropColumn('exam_session_id');
        });
    }
};
