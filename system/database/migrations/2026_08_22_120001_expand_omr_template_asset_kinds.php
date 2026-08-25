<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::getConnection()->statement('ALTER TABLE omr_template_assets DROP CONSTRAINT IF EXISTS omr_template_assets_kind_check');
        Schema::getConnection()->statement(<<<'SQL'
            ALTER TABLE omr_template_assets
                ADD CONSTRAINT omr_template_assets_kind_check
                    CHECK (kind IN ('source', 'intermediate_page', 'raster_page', 'print_pdf', 'evidence', 'digital_proof', 'marker_profile', 'native_template', 'native_config', 'native_manifest', 'reference_page'))
        SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::getConnection()->statement('ALTER TABLE omr_template_assets DROP CONSTRAINT IF EXISTS omr_template_assets_kind_check');
        Schema::getConnection()->statement(<<<'SQL'
            ALTER TABLE omr_template_assets
                ADD CONSTRAINT omr_template_assets_kind_check
                    CHECK (kind IN ('source', 'intermediate_page', 'raster_page', 'print_pdf', 'evidence', 'digital_proof', 'marker_profile'))
        SQL);
    }
};
