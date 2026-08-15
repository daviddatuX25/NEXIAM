<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_processing_runs', function (Blueprint $table): void {
            $table->foreignId('actor_id')->nullable()->after('sheet_id')->constrained('users')->restrictOnDelete();
            $table->uuid('run_uuid')->nullable()->after('run_no')->unique();
            $table->unsignedBigInteger('expected_sheet_version')->nullable()->after('request_version');
            $table->char('native_bundle_hash', 64)->nullable()->after('booklet_hash');
            $table->char('manifest_hash', 64)->nullable()->after('native_bundle_hash');
            $table->string('patch_identity', 255)->nullable()->after('config_hash');
            $table->string('response_version', 32)->nullable()->after('patch_identity');
            $table->json('timings_json')->nullable()->after('duration_ms');
        });

        Schema::table('omr_capture_sheets', function (Blueprint $table): void {
            $table->string('aligned_mime', 100)->nullable()->after('original_bytes');
            $table->unsignedBigInteger('aligned_bytes')->nullable()->after('aligned_mime');
            $table->unsignedInteger('aligned_width')->nullable()->after('aligned_bytes');
            $table->unsignedInteger('aligned_height')->nullable()->after('aligned_width');
            $table->char('aligned_hash', 64)->nullable()->after('aligned_height');
            $table->string('aligned_asset_path', 255)->nullable()->after('asset_path');
            $table->char('aligned_asset_hmac', 64)->nullable()->after('asset_hmac');
            $table->string('aligned_asset_hmac_key_id', 100)->nullable()->after('asset_hmac_key_id');
            $table->string('aligned_asset_key_id', 100)->nullable()->after('asset_key_id');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Durable OMR run hardening is irreversible. Restore the pre-cut database backup instead.');
    }
};
