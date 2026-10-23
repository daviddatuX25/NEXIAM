<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION nexiam_omr_component_omr_template_assets_insert_seal_guard() RETURNS trigger AS $$
                BEGIN
                    IF NEW.kind NOT IN ('evidence', 'digital_proof')
                       AND NOT (
                           (NEW.kind = 'print_pdf' AND (NEW.metadata->>'role' IN ('challenge_sheet', 'challenge_guide_pdf')))
                           OR (NEW.kind = 'raster_page' AND (NEW.metadata->>'role' = 'challenge_sheet_page'))
                       ) THEN
                        PERFORM v.id FROM omr_answer_sheet_template_versions v WHERE v.id IN (NEW.template_version_id) ORDER BY v.id FOR SHARE;
                        IF EXISTS (SELECT 1 FROM omr_answer_sheet_template_versions v WHERE v.id IN (NEW.template_version_id) AND v.status = 'compiled') THEN
                            RAISE EXCEPTION 'Sealed OMR component descendants cannot be inserted, changed, deleted or reparented';
                        END IF;
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
            SQL);
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION nexiam_omr_component_omr_template_assets_insert_seal_guard() RETURNS trigger AS $$
                BEGIN
                    IF NEW.kind NOT IN ('evidence', 'digital_proof') THEN
                        PERFORM v.id FROM omr_answer_sheet_template_versions v WHERE v.id IN (NEW.template_version_id) ORDER BY v.id FOR SHARE;
                        IF EXISTS (SELECT 1 FROM omr_answer_sheet_template_versions v WHERE v.id IN (NEW.template_version_id) AND v.status = 'compiled') THEN
                            RAISE EXCEPTION 'Sealed OMR component descendants cannot be inserted, changed, deleted or reparented';
                        END IF;
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
            SQL);
        }
    }
};
