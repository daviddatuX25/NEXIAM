<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grading_sessions', function (Blueprint $table) {
            $table->foreignId('opened_by_user_id')->nullable()->after('opened_at')->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by_user_id')->nullable()->after('finalized_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('grading_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('opened_by_user_id');
            $table->dropConstrainedForeignId('finalized_by_user_id');
        });
    }
};
