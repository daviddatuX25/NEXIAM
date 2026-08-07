<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('exam_session_applicant', 'slip_printed_at')) {
            Schema::table('exam_session_applicant', function (Blueprint $table) {
                $table->dropColumn('slip_printed_at');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('exam_session_applicant', 'slip_printed_at')) {
            Schema::table('exam_session_applicant', function (Blueprint $table) {
                $table->timestamp('slip_printed_at')->nullable();
            });
        }
    }
};
