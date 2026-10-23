<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_cycles', function (Blueprint $table) {
            $table->jsonb('admission_notification_policy')->nullable();
            $table->jsonb('exam_notification_policy')->nullable();
        });

        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->jsonb('notification_policy')->nullable();
            $table->jsonb('notification_policy_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropColumn(['notification_policy', 'notification_policy_snapshot']);
        });

        Schema::table('academic_cycles', function (Blueprint $table) {
            $table->dropColumn(['admission_notification_policy', 'exam_notification_policy']);
        });
    }
};
