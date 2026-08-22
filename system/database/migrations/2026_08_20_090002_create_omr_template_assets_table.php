<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_template_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('authoring_draft_id')->nullable()->constrained('omr_template_authoring_drafts')->cascadeOnDelete();
            $table->foreignId('template_version_id')->nullable()->constrained('omr_answer_sheet_template_versions')->restrictOnDelete();
            $table->string('kind', 24);
            $table->unsignedInteger('page_no')->nullable();
            $table->string('disk', 64);
            $table->string('path', 512);
            $table->string('mime', 127);
            $table->unsignedBigInteger('bytes');
            $table->char('sha256', 64);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['authoring_draft_id', 'kind', 'page_no'], 'omr_template_assets_draft_kind_page_idx');
            $table->index(['template_version_id', 'kind', 'page_no'], 'omr_template_assets_version_kind_page_idx');
            $table->unique(['disk', 'path'], 'omr_template_assets_disk_path_unique');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE omr_template_assets
                    ADD CONSTRAINT omr_template_assets_kind_check
                        CHECK (kind IN ('source', 'intermediate_page', 'raster_page', 'print_pdf', 'evidence', 'digital_proof', 'marker_profile')),
                    ADD CONSTRAINT omr_template_assets_bytes_check
                        CHECK (bytes >= 0),
                    ADD CONSTRAINT omr_template_assets_owner_check
                        CHECK ((authoring_draft_id IS NOT NULL AND template_version_id IS NULL)
                            OR (authoring_draft_id IS NULL AND template_version_id IS NOT NULL)),
                    ADD CONSTRAINT omr_template_assets_page_no_check
                        CHECK (page_no IS NULL OR page_no >= 1)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('omr_template_assets');
    }
};
