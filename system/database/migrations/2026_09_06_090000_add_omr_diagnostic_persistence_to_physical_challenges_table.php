<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_physical_challenges', function (Blueprint $table): void {
            $table->json('scenario_manifest')->nullable()->after('page_manifest_hash');
            $table->char('scenario_manifest_hash', 64)->nullable()->after('scenario_manifest');
            $table->json('coverage_report')->nullable()->after('scenario_manifest_hash');
            $table->char('coverage_hash', 64)->nullable()->after('coverage_report');
            $table->json('renderer_identity')->nullable()->after('coverage_hash');
            $table->json('profile_identity')->nullable()->after('renderer_identity');
            $table->char('geometry_identity', 64)->nullable()->after('profile_identity');
            $table->json('source_artifact_hashes')->nullable()->after('geometry_identity');
            $table->json('transformed_artifact_hashes')->nullable()->after('source_artifact_hashes');
            $table->json('scenario_outcomes')->nullable()->after('transformed_artifact_hashes');
        });
    }

    public function down(): void
    {
        Schema::table('omr_physical_challenges', function (Blueprint $table): void {
            $table->dropColumn([
                'scenario_manifest',
                'scenario_manifest_hash',
                'coverage_report',
                'coverage_hash',
                'renderer_identity',
                'profile_identity',
                'geometry_identity',
                'source_artifact_hashes',
                'transformed_artifact_hashes',
                'scenario_outcomes',
            ]);
        });
    }
};
