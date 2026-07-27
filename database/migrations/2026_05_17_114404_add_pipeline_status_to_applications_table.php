<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add pipeline_status and pipeline_milestones columns to applications.
     *
     * pipeline_status    – full lifecycle status (pending → … → released / dismissed).
     *                      Separate from the acceptance-only `status` column.
     * pipeline_milestones – JSON map of milestone key → { at, ...extra } timestamps.
     *
     * The index on pipeline_status enables the P0 fix: native DB filtering/sorting
     * instead of loading the entire table into memory.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('pipeline_status')->nullable()->after('status');
            $table->json('pipeline_milestones')->nullable()->after('pipeline_status');
            $table->index('pipeline_status', 'applications_pipeline_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex('applications_pipeline_status_index');
            $table->dropColumn(['pipeline_status', 'pipeline_milestones']);
        });
    }
};
