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

        // 1. Update nexiam_omr_item_evidence_immutable
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION nexiam_omr_item_evidence_immutable()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND current_setting('nexiam.sandbox_purge', true) = 'on' THEN
                    RETURN OLD;
                END IF;

                RAISE EXCEPTION USING
                    ERRCODE = '55000',
                    MESSAGE = 'verification item evidence is immutable after insert';
                RETURN NEW;
            END;
            $$;
        SQL);

        // 2. Update nexiam_omr_capture_immutable
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION nexiam_omr_capture_immutable()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                replacement_run_id bigint;
                replacement_method_id bigint;
                replacement_method text;
                replacement_page_no smallint;
                replacement_target_kind text;
                replacement_target_id bigint;
                replacement_target_version text;
                replacement_target_hash text;
                replacement_plan_hash text;
                replacement_vector_hash text;
                replacement_state text;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF current_setting('nexiam.sandbox_purge', true) = 'on' THEN
                        RETURN OLD;
                    END IF;

                    -- Digital matrix variants have no physical attempt binding
                    -- and retain their pre-cutover replacement lifecycle.
                    IF OLD.attempt_state IS NULL
                        AND OLD.method_id IS NULL
                        AND OLD.challenge_id IS NULL
                        AND OLD.method IS NULL
                    THEN
                        RETURN OLD;
                    END IF;
                    RAISE EXCEPTION USING
                        ERRCODE = '55000',
                        MESSAGE = 'verification capture is immutable after insert';
                END IF;

                -- Leave legacy digital matrix rows outside the physical
                -- attempt state machine; all method-bound rows are strict.
                IF OLD.attempt_state IS NULL
                    AND OLD.method_id IS NULL
                    AND OLD.challenge_id IS NULL
                    AND OLD.method IS NULL
                    AND NEW.attempt_state IS NULL
                    AND NEW.method_id IS NULL
                    AND NEW.challenge_id IS NULL
                    AND NEW.method IS NULL
                THEN
                    RETURN NEW;
                END IF;

                IF OLD.attempt_state = 'active' THEN
                    IF NEW.attempt_state IS NULL OR NEW.attempt_state NOT IN ('passed', 'failed') THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '55000',
                            MESSAGE = 'active verification capture must finalize to passed or failed';
                    END IF;
                    IF (to_jsonb(OLD) - ARRAY[
                        'attempt_state', 'actual', 'status', 'error_code',
                        'failure_code', 'failure_detail', 'aligned_asset_hash',
                        'aligned_asset_id', 'overlay_asset_hash',
                        'overlay_asset_id', 'evidence_version', 'evidence_hash',
                        'detected_vector_hash', 'vector_match', 'orientation_tried',
                        'accepted_orientation', 'orientation_diagnostics',
                        'marker_result', 'layout_result', 'gate_result', 'summary',
                        'printer', 'device', 'paper', 'measurements',
                        'selected_by_user_id', 'selected_at', 'selection_reason',
                        'lock_version', 'updated_at'
                    ]::text[]) IS DISTINCT FROM (to_jsonb(NEW) - ARRAY[
                        'attempt_state', 'actual', 'status', 'error_code',
                        'failure_code', 'failure_detail', 'aligned_asset_hash',
                        'aligned_asset_id', 'overlay_asset_hash',
                        'overlay_asset_id', 'evidence_version', 'evidence_hash',
                        'detected_vector_hash', 'vector_match', 'orientation_tried',
                        'accepted_orientation', 'orientation_diagnostics',
                        'marker_result', 'layout_result', 'gate_result', 'summary',
                        'printer', 'device', 'paper', 'measurements',
                        'selected_by_user_id', 'selected_at', 'selection_reason',
                        'lock_version', 'updated_at'
                    ]::text[]) THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '55000',
                            MESSAGE = 'verification capture facts are immutable after insert';
                    END IF;
                ELSIF OLD.attempt_state = 'failed' THEN
                    IF NEW.attempt_state IS NULL OR NEW.attempt_state NOT IN ('failed', 'passed') THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '55000',
                            MESSAGE = 'failed verification capture has an invalid transition';
                    END IF;
                    IF (to_jsonb(OLD) - ARRAY[
                        'attempt_state', 'actual', 'status', 'failure_code',
                        'failure_detail', 'lock_version', 'updated_at'
                    ]::text[]) IS DISTINCT FROM (to_jsonb(NEW) - ARRAY[
                        'attempt_state', 'actual', 'status', 'failure_code',
                        'failure_detail', 'lock_version', 'updated_at'
                    ]::text[]) THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '55000',
                            MESSAGE = 'verification capture facts are immutable after insert';
                    END IF;
                ELSIF OLD.attempt_state = 'passed' THEN
                    IF NEW.attempt_state IS NULL OR NEW.attempt_state NOT IN ('passed', 'superseded') THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '55000',
                            MESSAGE = 'passed verification capture has an invalid transition';
                    END IF;
                    IF (to_jsonb(OLD) - ARRAY[
                        'attempt_state', 'superseded_by_attempt_id',
                        'superseded_by_user_id', 'superseded_at',
                        'supersede_reason', 'selected_by_user_id', 'selected_at',
                        'selection_reason', 'lock_version', 'updated_at'
                    ]::text[]) IS DISTINCT FROM (to_jsonb(NEW) - ARRAY[
                        'attempt_state', 'superseded_by_attempt_id',
                        'superseded_by_user_id', 'superseded_at',
                        'supersede_reason', 'selected_by_user_id', 'selected_at',
                        'selection_reason', 'lock_version', 'updated_at'
                    ]::text[]) THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '55000',
                            MESSAGE = 'verification capture facts are immutable after insert';
                    END IF;
                ELSIF OLD.attempt_state = 'superseded' THEN
                    IF NEW.attempt_state IS NULL OR NEW.attempt_state <> 'superseded'
                        OR (to_jsonb(OLD) - ARRAY['updated_at']::text[])
                            IS DISTINCT FROM (to_jsonb(NEW) - ARRAY['updated_at']::text[])
                    THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '55000',
                            MESSAGE = 'superseded verification capture is immutable';
                    END IF;
                ELSE
                    RAISE EXCEPTION USING
                        ERRCODE = '55000',
                        MESSAGE = 'physical verification capture requires an active or terminal attempt state';
                END IF;

                IF EXISTS (
                    SELECT 1
                      FROM omr_template_verification_method_pages
                     WHERE selected_attempt_id = OLD.id
                ) AND NEW.attempt_state IS DISTINCT FROM 'passed' THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        MESSAGE = 'selected verification capture must remain passed until its pointer is cleared';
                END IF;

                IF NEW.attempt_state = 'superseded' THEN
                    IF NEW.superseded_by_attempt_id IS NULL
                        OR NEW.supersede_reason IS NULL
                        OR char_length(trim(NEW.supersede_reason)) = 0
                    THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '23514',
                            MESSAGE = 'superseded verification capture requires a replacement attempt and reason';
                    END IF;
                    SELECT replacement.run_id, replacement.method_id,
                           replacement.method, replacement.page_no,
                           replacement.target_kind, replacement.target_id,
                           replacement.target_version, replacement.target_hash,
                           replacement.plan_hash, replacement.expected_vector_hash,
                           replacement.attempt_state
                      INTO replacement_run_id, replacement_method_id,
                           replacement_method, replacement_page_no,
                           replacement_target_kind, replacement_target_id,
                           replacement_target_version, replacement_target_hash,
                           replacement_plan_hash, replacement_vector_hash,
                           replacement_state
                      FROM omr_template_verification_captures AS replacement
                     WHERE replacement.id = NEW.superseded_by_attempt_id;
                    IF NOT FOUND
                        OR replacement_state IS DISTINCT FROM 'passed'
                        OR replacement_run_id IS DISTINCT FROM OLD.run_id
                        OR replacement_method_id IS DISTINCT FROM OLD.method_id
                        OR replacement_method IS DISTINCT FROM OLD.method
                        OR replacement_page_no IS DISTINCT FROM OLD.page_no
                        OR replacement_target_kind IS DISTINCT FROM OLD.target_kind
                        OR replacement_target_id IS DISTINCT FROM OLD.target_id
                        OR replacement_target_version IS DISTINCT FROM OLD.target_version
                        OR replacement_target_hash IS DISTINCT FROM OLD.target_hash
                        OR replacement_plan_hash IS DISTINCT FROM OLD.plan_hash
                        OR replacement_vector_hash IS DISTINCT FROM OLD.expected_vector_hash
                    THEN
                        RAISE EXCEPTION USING
                            ERRCODE = '23514',
                            MESSAGE = 'replacement attempt must be a same-boundary passed capture';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$;
        SQL);

        // 3. Update nexiam_omr_item_review_revision_immutable
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION nexiam_omr_item_review_revision_immutable()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND current_setting('nexiam.sandbox_purge', true) = 'on' THEN
                    RETURN OLD;
                END IF;

                RAISE EXCEPTION USING
                    ERRCODE = '55000',
                    MESSAGE = 'OMR item review revisions are append-only';
                RETURN NEW;
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        // down reverts back to strict delete rejection if ever rolled back
    }
};
