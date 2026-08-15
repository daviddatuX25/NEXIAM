<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_answer_sheet_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_version_id')->constrained('omr_answer_sheet_template_versions')->cascadeOnDelete();
            $table->unsignedSmallInteger('page_no');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->json('page_manifest_json')->nullable();
            $table->char('page_manifest_hash', 64)->nullable();
            $table->timestamps();
            $table->unique(['template_version_id', 'page_no'], 'omr_answer_sheet_pages_version_page_unique');
        });

        Schema::table('omr_template_questions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('page_no')->default(1)->after('template_area_id');
        });

        Schema::table('omr_capture_sheets', function (Blueprint $table): void {
            $table->unsignedSmallInteger('expected_page_count')->default(1)->after('version');
        });

        Schema::table('omr_capture_sheet_issues', function (Blueprint $table): void {
            $table->unsignedSmallInteger('page_no')->default(1)->after('sheet_id');
        });

        Schema::table('omr_processing_runs', function (Blueprint $table): void {
            $table->unsignedSmallInteger('page_no')->default(1)->after('sheet_id');
        });

        Schema::create('omr_capture_sheet_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sheet_id')->constrained('omr_capture_sheets')->cascadeOnDelete();
            $table->unsignedSmallInteger('page_no');
            $table->string('status', 32)->default('queued');
            $table->string('asset_path', 512)->nullable();
            $table->char('asset_hmac', 64)->nullable();
            $table->char('asset_hmac_key_id', 64)->nullable();
            $table->char('asset_key_id', 64)->nullable();
            $table->string('asset_format_version', 20)->nullable();
            $table->string('asset_state', 20)->nullable();
            $table->string('original_mime', 60)->nullable();
            $table->unsignedInteger('original_bytes')->nullable();
            $table->string('aligned_mime', 60)->nullable();
            $table->unsignedInteger('aligned_bytes')->nullable();
            $table->unsignedInteger('aligned_width')->nullable();
            $table->unsignedInteger('aligned_height')->nullable();
            $table->char('aligned_hash', 64)->nullable();
            $table->string('aligned_asset_path', 512)->nullable();
            $table->char('aligned_asset_hmac', 64)->nullable();
            $table->char('aligned_asset_hmac_key_id', 64)->nullable();
            $table->char('aligned_asset_key_id', 64)->nullable();
            $table->json('proposal_json')->nullable();
            $table->unsignedBigInteger('current_processing_run_id')->nullable();
            $table->timestamps();
            $table->unique(['sheet_id', 'page_no'], 'omr_capture_sheet_pages_sheet_page_unique');
            $table->index(['status', 'created_at'], 'omr_capture_sheet_pages_status_idx');
        });

        // Backfill: existing template versions get one page row mirroring their data.
        DB::statement(
            'INSERT INTO omr_answer_sheet_pages (template_version_id, page_no, width, height, page_manifest_json, page_manifest_hash, created_at, updated_at)'
            .' SELECT id, 1, page_width, page_height, review_manifest_json, review_manifest_hash, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP'
            .' FROM omr_answer_sheet_template_versions'
            .' WHERE NOT EXISTS (SELECT 1 FROM omr_answer_sheet_pages WHERE omr_answer_sheet_pages.template_version_id = omr_answer_sheet_template_versions.id)',
        );

        // Backfill: existing sheets get one page row mirroring their asset/aligned data.
        DB::statement(
            'INSERT INTO omr_capture_sheet_pages (sheet_id, page_no, status, asset_path, asset_hmac, asset_hmac_key_id, asset_key_id, asset_format_version, asset_state, original_mime, original_bytes, aligned_mime, aligned_bytes, aligned_width, aligned_height, aligned_hash, aligned_asset_path, aligned_asset_hmac, aligned_asset_hmac_key_id, aligned_asset_key_id, current_processing_run_id, created_at, updated_at)'
            .' SELECT id, 1, CASE WHEN status = \'needs_review\' OR status = \'reviewed\' OR status = \'committed\' THEN \'succeeded\' ELSE status END, asset_path, asset_hmac, asset_hmac_key_id, asset_key_id, asset_format_version, asset_state, original_mime, original_bytes, aligned_mime, aligned_bytes, aligned_width, aligned_height, aligned_hash, aligned_asset_path, aligned_asset_hmac, aligned_asset_hmac_key_id, aligned_asset_key_id, current_processing_run_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP'
            .' FROM omr_capture_sheets'
            .' WHERE NOT EXISTS (SELECT 1 FROM omr_capture_sheet_pages WHERE omr_capture_sheet_pages.sheet_id = omr_capture_sheets.id)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('omr_capture_sheet_pages');

        Schema::table('omr_processing_runs', function (Blueprint $table): void {
            $table->dropColumn('page_no');
        });
        Schema::table('omr_capture_sheet_issues', function (Blueprint $table): void {
            $table->dropColumn('page_no');
        });
        Schema::table('omr_capture_sheets', function (Blueprint $table): void {
            $table->dropColumn('expected_page_count');
        });
        Schema::table('omr_template_questions', function (Blueprint $table): void {
            $table->dropColumn('page_no');
        });

        Schema::dropIfExists('omr_answer_sheet_pages');
    }
};
