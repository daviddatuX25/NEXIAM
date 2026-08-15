<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One active run per PAGE, not per sheet.
        Schema::getConnection()->statement('DROP INDEX IF EXISTS omr_processing_runs_one_active');
        Schema::getConnection()->statement(
            "CREATE UNIQUE INDEX omr_processing_runs_one_active"
            ." ON omr_processing_runs (sheet_id, page_no)"
            ." WHERE status IN ('pending', 'claimed', 'retryable_failed')",
        );

        // Multi-page sheets carry no asset of their own; the old check forbids
        // that. Drop both the asset and status constraints and re-add widened
        // versions (status gains pending_pages; asset allows null when page
        // rows own the bytes). PostgreSQL-only; SQLite tests skip constraint DDL.
        if (DB::getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'ALTER TABLE omr_capture_sheets'
                .' DROP CONSTRAINT IF EXISTS omr_capture_sheets_asset_check,'
                .' DROP CONSTRAINT IF EXISTS omr_capture_sheets_status_check',
            );
            Schema::getConnection()->statement(
                "ALTER TABLE omr_capture_sheets"
                ." ADD CONSTRAINT omr_capture_sheets_status_check"
                ." CHECK (status IN ('received', 'pending_pages', 'queued', 'processing', 'needs_review', 'reviewed', 'committed', 'failed_terminal')),"
                ." ADD CONSTRAINT omr_capture_sheets_asset_check"
                ." CHECK (asset_path IS NOT NULL OR expected_page_count > 1)",
            );
        }

        // Multi-page sheets own their assets on omr_capture_sheet_pages rows;
        // the logical sheet row no longer requires asset columns.
        Schema::table('omr_capture_sheets', function (Blueprint $table): void {
            $table->string('asset_path', 512)->nullable()->change();
            $table->char('asset_hmac', 64)->nullable()->change();
            $table->string('asset_format_version', 20)->nullable()->change();
            $table->string('asset_state', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'ALTER TABLE omr_capture_sheets'
                .' DROP CONSTRAINT IF EXISTS omr_capture_sheets_asset_check,'
                .' DROP CONSTRAINT IF EXISTS omr_capture_sheets_status_check',
            );
        }

        Schema::table('omr_capture_sheets', function (Blueprint $table): void {
            $table->string('asset_path', 512)->nullable(false)->change();
            $table->char('asset_hmac', 64)->nullable(false)->change();
            $table->string('asset_format_version', 20)->nullable(false)->change();
            $table->string('asset_state', 20)->nullable(false)->change();
        });
    }
};
