<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION omr_guard_booklet_version() RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        IF OLD.status IN ('active', 'retired') THEN
            RAISE EXCEPTION 'OMR booklet versions are append-only after activation';
        END IF;
        RETURN OLD;
    END IF;

    IF OLD.status = 'retired' AND NOT (
        NEW.status = 'active'
        AND NEW.booklet_id = OLD.booklet_id
        AND NEW.version = OLD.version
        AND NEW.answer_key_hash = OLD.answer_key_hash
        AND NEW.activated_by_user_id = OLD.activated_by_user_id
        AND NEW.activated_at = OLD.activated_at
    ) THEN
        RAISE EXCEPTION 'Retired OMR booklet versions permit only restoration to active';
    END IF;

    IF OLD.status = 'active' AND NOT (
        NEW.status = 'retired'
        AND NEW.booklet_id = OLD.booklet_id
        AND NEW.version = OLD.version
        AND NEW.answer_key_hash = OLD.answer_key_hash
        AND NEW.activated_by_user_id = OLD.activated_by_user_id
        AND NEW.activated_at = OLD.activated_at
    ) THEN
        RAISE EXCEPTION 'Active OMR booklet versions permit only retirement';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION omr_guard_booklet_version() RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        IF OLD.status IN ('active', 'retired') THEN
            RAISE EXCEPTION 'OMR booklet versions are append-only after activation';
        END IF;
        RETURN OLD;
    END IF;

    IF OLD.status = 'retired' THEN
        RAISE EXCEPTION 'Retired OMR booklet versions are immutable';
    END IF;

    IF OLD.status = 'active' AND NOT (
        NEW.status = 'retired'
        AND NEW.booklet_id = OLD.booklet_id
        AND NEW.version = OLD.version
        AND NEW.answer_key_hash = OLD.answer_key_hash
        AND NEW.activated_by_user_id = OLD.activated_by_user_id
        AND NEW.activated_at = OLD.activated_at
    ) THEN
        RAISE EXCEPTION 'Active OMR booklet versions permit only retirement';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);
    }
};
