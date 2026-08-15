<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (config('database.default') !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION omr_guard_template_version() RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        IF OLD.status IN ('active', 'retired') THEN
            RAISE EXCEPTION 'OMR template versions are append-only after activation';
        END IF;
        RETURN OLD;
    END IF;

    IF OLD.status = 'retired' THEN
        RAISE EXCEPTION 'Retired OMR template versions are immutable';
    END IF;

    IF OLD.status = 'active' AND NOT (
        NEW.status = 'retired'
        AND NEW.template_id = OLD.template_id
        AND NEW.version = OLD.version
        AND NEW.page_width = OLD.page_width
        AND NEW.page_height = OLD.page_height
        AND NEW.identity_regions_json::jsonb IS NOT DISTINCT FROM OLD.identity_regions_json::jsonb
        AND NEW.provider_config_json::jsonb IS NOT DISTINCT FROM OLD.provider_config_json::jsonb
        AND NEW.config_hash = OLD.config_hash
        AND NEW.native_bundle_version IS NOT DISTINCT FROM OLD.native_bundle_version
        AND NEW.native_bundle_json::jsonb IS NOT DISTINCT FROM OLD.native_bundle_json::jsonb
        AND NEW.native_bundle_hash IS NOT DISTINCT FROM OLD.native_bundle_hash
        AND NEW.engine_name IS NOT DISTINCT FROM OLD.engine_name
        AND NEW.engine_commit IS NOT DISTINCT FROM OLD.engine_commit
        AND NEW.patch_identity IS NOT DISTINCT FROM OLD.patch_identity
        AND NEW.review_manifest_json::jsonb IS NOT DISTINCT FROM OLD.review_manifest_json::jsonb
        AND NEW.review_manifest_hash IS NOT DISTINCT FROM OLD.review_manifest_hash
        AND NEW.activated_by_user_id IS NOT DISTINCT FROM OLD.activated_by_user_id
        AND NEW.activated_at IS NOT DISTINCT FROM OLD.activated_at
    ) THEN
        RAISE EXCEPTION 'Active OMR template versions permit only retirement';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

ALTER TABLE omr_answer_sheet_template_versions
    DROP CONSTRAINT IF EXISTS omr_template_active_native_identity_check;

ALTER TABLE omr_answer_sheet_template_versions
    ADD CONSTRAINT omr_template_active_native_identity_check CHECK (
        status <> 'active' OR (
            native_bundle_version IS NOT NULL
            AND native_bundle_json IS NOT NULL
            AND native_bundle_hash IS NOT NULL
            AND native_bundle_hash ~ '^[a-f0-9]{64}$'
            AND engine_name IS NOT NULL
            AND engine_commit IS NOT NULL
            AND patch_identity IS NOT NULL
            AND review_manifest_json IS NOT NULL
            AND review_manifest_hash IS NOT NULL
            AND review_manifest_hash ~ '^[a-f0-9]{64}$'
        )
    );
SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('OMR native identity enforcement is forward-only; restore the pre-cut database backup.');
    }
};
