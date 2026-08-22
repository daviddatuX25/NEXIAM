<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_template_authoring_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->nullable()->constrained('omr_answer_sheet_templates')->restrictOnDelete();
            $table->string('code', 64);
            $table->string('name', 190);
            $table->string('source_kind', 16);
            $table->string('stage', 32)->default('draft');
            $table->foreignId('marker_profile_id')->nullable()->constrained('omr_marker_profiles')->restrictOnDelete();
            $table->json('authoring_json');
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('compiled_version_id')->nullable()->constrained('omr_answer_sheet_template_versions')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['template_id', 'code'], 'omr_authoring_drafts_template_code_unique');
            $table->index(['owner_user_id', 'stage'], 'omr_authoring_drafts_owner_stage_idx');
            $table->index(['stage', 'updated_at'], 'omr_authoring_drafts_stage_updated_idx');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE omr_template_authoring_drafts
                    ADD CONSTRAINT omr_authoring_drafts_source_kind_check
                        CHECK (source_kind IN ('html', 'docx', 'pdf', 'image', 'preset')),
                    ADD CONSTRAINT omr_authoring_drafts_stage_check
                        CHECK (stage IN ('draft', 'source_confirmed', 'page_prepared', 'mapping_complete', 'digital_verified', 'compiled')),
                    ADD CONSTRAINT omr_authoring_drafts_lock_version_check
                        CHECK (lock_version >= 0),
                    ADD CONSTRAINT omr_authoring_drafts_compiled_stage_check
                        CHECK ((stage = 'compiled' AND compiled_version_id IS NOT NULL) OR stage <> 'compiled')
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('omr_template_authoring_drafts');
    }
};
