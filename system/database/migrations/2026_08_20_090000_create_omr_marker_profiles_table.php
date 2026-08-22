<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_marker_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64);
            $table->unsignedInteger('version');
            $table->string('family', 64);
            $table->string('asset_path', 512);
            $table->char('asset_sha256', 64);
            $table->decimal('size_mm', 8, 3)->nullable();
            $table->decimal('quiet_zone_mm', 8, 3)->nullable();
            $table->json('matching_config');
            $table->string('status', 16)->default('candidate');
            $table->char('qualification_evidence_hash', 64)->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('qualified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamps();

            $table->unique(['code', 'version'], 'omr_marker_profiles_code_version_unique');
            $table->index(['status', 'family'], 'omr_marker_profiles_status_family_idx');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE omr_marker_profiles
                    ADD CONSTRAINT omr_marker_profiles_status_check
                        CHECK (status IN ('candidate', 'qualified', 'retired')),
                    ADD CONSTRAINT omr_marker_profiles_size_check
                        CHECK (size_mm IS NULL OR size_mm > 0),
                    ADD CONSTRAINT omr_marker_profiles_quiet_zone_check
                        CHECK (quiet_zone_mm IS NULL OR quiet_zone_mm >= 0)
            SQL);
        }
    }

    /**
     * WARNING: down() is for test/dev teardown ONLY. Dropping canonical tables
     * destroys all staged authoring drafts and evidence. Operational rollback
     * must deploy the previous release while retaining additive tables.
     */
    public function down(): void
    {
        Schema::dropIfExists('omr_marker_profiles');
    }
};
