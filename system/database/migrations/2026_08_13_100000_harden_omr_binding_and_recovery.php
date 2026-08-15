<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_processing_runs', function (Blueprint $table): void {
            $table->index(['status', 'claim_expires_at'], 'omr_processing_runs_sweep_idx');
        });

        // One roster binding per batch: a duplicate capture of the same physical
        // sheet must never double-commit scores. Partial index works on both
        // PostgreSQL and SQLite.
        DB::statement(
            'CREATE UNIQUE INDEX omr_capture_sheets_one_attempt_per_batch'
            .' ON omr_capture_sheets (batch_id, admission_attempt_id)'
            .' WHERE admission_attempt_id IS NOT NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS omr_capture_sheets_one_attempt_per_batch');

        Schema::table('omr_processing_runs', function (Blueprint $table): void {
            $table->dropIndex('omr_processing_runs_sweep_idx');
        });
    }
};
