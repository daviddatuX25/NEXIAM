<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_question_booklet_versions', function (Blueprint $table): void {
            // Default true preserves existing active/finalized multi-set (A-D) versions
            // without firing PostgreSQL update triggers on active records.
            // Newly authored drafts default to false in PHP/model.
            $table->boolean('allow_multiple_sets')->default(true)->after('authoring_mode');
        });
    }

    public function down(): void
    {
        Schema::table('omr_question_booklet_versions', function (Blueprint $table): void {
            $table->dropColumn('allow_multiple_sets');
        });
    }
};
