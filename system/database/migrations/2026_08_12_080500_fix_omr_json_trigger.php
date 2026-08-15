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
        AND NEW.identity_regions_json::jsonb = OLD.identity_regions_json::jsonb
        AND NEW.provider_config_json::jsonb = OLD.provider_config_json::jsonb
        AND NEW.config_hash = OLD.config_hash
        AND NEW.activated_by_user_id = OLD.activated_by_user_id
        AND NEW.activated_at = OLD.activated_at
    ) THEN
        RAISE EXCEPTION 'Active OMR template versions permit only retirement';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('OMR trigger correction is forward-only; restore the pre-cut database backup.');
    }
};
