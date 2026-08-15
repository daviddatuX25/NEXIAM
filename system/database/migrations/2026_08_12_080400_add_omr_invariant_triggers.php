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
        AND NEW.identity_regions_json = OLD.identity_regions_json
        AND NEW.provider_config_json = OLD.provider_config_json
        AND NEW.config_hash = OLD.config_hash
        AND NEW.activated_by_user_id = OLD.activated_by_user_id
        AND NEW.activated_at = OLD.activated_at
    ) THEN
        RAISE EXCEPTION 'Active OMR template versions permit only retirement';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

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

CREATE OR REPLACE FUNCTION omr_guard_template_descendant() RETURNS trigger AS $$
DECLARE
    parent_status text;
    question_id bigint;
    area_id bigint;
BEGIN
    IF TG_TABLE_NAME = 'omr_template_areas' THEN
        area_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.id ELSE NEW.id END;
    ELSIF TG_TABLE_NAME = 'omr_template_questions' THEN
        area_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.template_area_id ELSE NEW.template_area_id END;
    ELSE
        question_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.template_question_id ELSE NEW.template_question_id END;
        SELECT template_area_id INTO area_id FROM omr_template_questions WHERE id = question_id;
    END IF;

    SELECT v.status INTO parent_status
    FROM omr_answer_sheet_template_versions v
    JOIN omr_template_areas a ON a.template_version_id = v.id
    WHERE a.id = area_id;

    IF parent_status IN ('active', 'retired') THEN
        RAISE EXCEPTION 'OMR template descendants are immutable after activation';
    END IF;

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION omr_guard_template_area() RETURNS trigger AS $$
DECLARE
    parent_status text;
    version_id bigint;
BEGIN
    version_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.template_version_id ELSE NEW.template_version_id END;
    SELECT status INTO parent_status FROM omr_answer_sheet_template_versions WHERE id = version_id;
    IF parent_status IN ('active', 'retired') THEN
        RAISE EXCEPTION 'OMR template descendants are immutable after activation';
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION omr_guard_booklet_descendant() RETURNS trigger AS $$
DECLARE
    parent_status text;
    version_id bigint;
BEGIN
    version_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.booklet_version_id ELSE NEW.booklet_version_id END;
    SELECT status INTO parent_status FROM omr_question_booklet_versions WHERE id = version_id;
    IF parent_status IN ('active', 'retired') THEN
        RAISE EXCEPTION 'OMR booklet descendants are immutable after activation';
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION omr_guard_pair() RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' OR OLD.status = 'retired' OR NOT (NEW.status = 'retired' AND NEW.template_version_id = OLD.template_version_id AND NEW.booklet_version_id = OLD.booklet_version_id AND NEW.approved_by_user_id = OLD.approved_by_user_id AND NEW.approved_at = OLD.approved_at) THEN
        RAISE EXCEPTION 'OMR compatibility pairs are immutable except active-to-retired';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION omr_guard_current_run() RETURNS trigger AS $$
DECLARE
    run_sheet_id bigint;
BEGIN
    IF NEW.current_processing_run_id IS NOT NULL THEN
        SELECT sheet_id INTO run_sheet_id FROM omr_processing_runs WHERE id = NEW.current_processing_run_id;
        IF run_sheet_id IS NULL OR run_sheet_id <> NEW.id THEN
            RAISE EXCEPTION 'Current OMR processing run must belong to the same sheet';
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION omr_guard_score_revision_identity() RETURNS trigger AS $$
DECLARE
    record_session bigint;
    record_attempt bigint;
    record_area bigint;
BEGIN
    IF NEW.score_record_id IS NOT NULL THEN
        SELECT grading_session_id, admission_attempt_id, aptitude_area_id INTO record_session, record_attempt, record_area FROM score_records WHERE id = NEW.score_record_id;
        IF record_session IS NULL OR record_session <> NEW.grading_session_id OR record_attempt <> NEW.admission_attempt_id OR record_area <> NEW.aptitude_area_id THEN
            RAISE EXCEPTION 'Score revision identity must match its score projection';
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER omr_template_version_immutability BEFORE UPDATE OR DELETE ON omr_answer_sheet_template_versions FOR EACH ROW EXECUTE FUNCTION omr_guard_template_version();
CREATE TRIGGER omr_booklet_version_immutability BEFORE UPDATE OR DELETE ON omr_question_booklet_versions FOR EACH ROW EXECUTE FUNCTION omr_guard_booklet_version();
CREATE TRIGGER omr_template_area_immutability BEFORE INSERT OR UPDATE OR DELETE ON omr_template_areas FOR EACH ROW EXECUTE FUNCTION omr_guard_template_area();
CREATE TRIGGER omr_template_question_immutability BEFORE INSERT OR UPDATE OR DELETE ON omr_template_questions FOR EACH ROW EXECUTE FUNCTION omr_guard_template_descendant();
CREATE TRIGGER omr_template_option_immutability BEFORE INSERT OR UPDATE OR DELETE ON omr_template_question_options FOR EACH ROW EXECUTE FUNCTION omr_guard_template_descendant();
CREATE TRIGGER omr_booklet_question_immutability BEFORE INSERT OR UPDATE OR DELETE ON omr_question_booklet_questions FOR EACH ROW EXECUTE FUNCTION omr_guard_booklet_descendant();
CREATE TRIGGER omr_pair_immutability BEFORE UPDATE OR DELETE ON omr_template_booklet_pairs FOR EACH ROW EXECUTE FUNCTION omr_guard_pair();
CREATE TRIGGER omr_sheet_current_run_coherence BEFORE INSERT OR UPDATE OF current_processing_run_id ON omr_capture_sheets FOR EACH ROW EXECUTE FUNCTION omr_guard_current_run();
CREATE TRIGGER omr_score_revision_identity BEFORE INSERT OR UPDATE ON score_record_revisions FOR EACH ROW EXECUTE FUNCTION omr_guard_score_revision_identity();
SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('OMR invariant triggers are irreversible. Restore the pre-cut database backup instead of rolling this migration down.');
    }
};
