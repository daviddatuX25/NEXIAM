<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_template_verification_captures', static function (Blueprint $table): void {
            $table->timestamp('discarded_at')->nullable();
            $table->foreignId('discarded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('discard_reason', 255)->nullable();
        });

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE omr_template_verification_captures
                ADD CONSTRAINT omr_verification_captures_discard_metadata_check
                CHECK (
                    (discarded_at IS NULL AND discarded_by_user_id IS NULL AND discard_reason IS NULL)
                    OR (discarded_at IS NOT NULL AND discard_reason IS NOT NULL AND char_length(trim(discard_reason)) > 0)
                )
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION nexiam_omr_capture_discard_insert_guard()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            BEGIN
                IF NEW.discarded_at IS NOT NULL
                    OR NEW.discarded_by_user_id IS NOT NULL
                    OR NEW.discard_reason IS NOT NULL
                THEN
                    RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'verification capture cannot be inserted as discarded';
                END IF;

                RETURN NEW;
            END;
            $function$
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER omr_capture_discard_insert_guard
            BEFORE INSERT ON omr_template_verification_captures
            FOR EACH ROW
            EXECUTE FUNCTION nexiam_omr_capture_discard_insert_guard()
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.nexiam_omr_capture_immutable()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
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

                IF OLD.discarded_at IS NOT NULL THEN
                    IF NEW.discarded_at IS DISTINCT FROM OLD.discarded_at
                        OR NEW.discard_reason IS DISTINCT FROM OLD.discard_reason
                        OR (NEW.discarded_by_user_id IS DISTINCT FROM OLD.discarded_by_user_id
                            AND NOT (OLD.discarded_by_user_id IS NOT NULL AND NEW.discarded_by_user_id IS NULL))
                        OR (to_jsonb(OLD) - ARRAY['discarded_by_user_id', 'updated_at']::text[])
                            IS DISTINCT FROM (to_jsonb(NEW) - ARRAY['discarded_by_user_id', 'updated_at']::text[])
                    THEN
                        RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'discarded verification capture is immutable';
                    END IF;
                    RETURN NEW;
                END IF;

                IF NEW.discarded_at IS DISTINCT FROM OLD.discarded_at
                    OR NEW.discarded_by_user_id IS DISTINCT FROM OLD.discarded_by_user_id
                    OR NEW.discard_reason IS DISTINCT FROM OLD.discard_reason
                THEN
                    IF OLD.discarded_at IS NOT NULL
                        OR OLD.attempt_state NOT IN ('failed', 'passed', 'superseded')
                        OR NEW.discarded_at IS NULL
                        OR NEW.discarded_by_user_id IS NULL
                        OR NEW.discard_reason IS NULL
                        OR char_length(trim(NEW.discard_reason)) = 0
                        OR NEW.lock_version IS DISTINCT FROM OLD.lock_version + 1
                        OR EXISTS (SELECT 1 FROM omr_template_verification_method_pages WHERE selected_attempt_id = OLD.id)
                        OR (to_jsonb(OLD) - ARRAY['discarded_at', 'discarded_by_user_id', 'discard_reason', 'lock_version', 'updated_at']::text[])
                            IS DISTINCT FROM (to_jsonb(NEW) - ARRAY['discarded_at', 'discarded_by_user_id', 'discard_reason', 'lock_version', 'updated_at']::text[])
                    THEN
                        RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'verification capture discard transition is invalid';
                    END IF;
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
            $function$
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.nexiam_omr_method_page_selected_attempt_guard()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                method_run_id bigint;
                method_name text;
                method_target_kind text;
                method_target_id bigint;
                method_target_version text;
                method_target_hash text;
                method_plan_hash text;
                method_vector_hash text;
                method_challenge_id bigint;
                attempt_method_id bigint;
                attempt_run_id bigint;
                attempt_method text;
                attempt_page_no smallint;
                attempt_challenge_id bigint;
                attempt_target_kind text;
                attempt_target_id bigint;
                attempt_target_version text;
                attempt_state text;
                attempt_discarded_at timestamp with time zone;
                attempt_target_hash text;
                attempt_plan_hash text;
                attempt_vector_hash text;
            BEGIN
                IF NEW.selected_attempt_id IS NULL THEN
                    RETURN NEW;
                END IF;
                SELECT method_row.run_id, method_row.method,
                       method_row.target_kind, method_row.target_id,
                       method_row.target_version, method_row.target_hash,
                       method_row.plan_hash, method_row.expected_vector_hash,
                       method_row.challenge_id
                  INTO method_run_id, method_name,
                       method_target_kind, method_target_id,
                       method_target_version, method_target_hash,
                       method_plan_hash, method_vector_hash,
                       method_challenge_id
                  FROM omr_template_verification_methods AS method_row
                 WHERE method_row.id = NEW.method_id;
                SELECT capture_row.method_id, capture_row.run_id,
                       capture_row.method, capture_row.page_no,
                       capture_row.challenge_id, capture_row.target_kind,
                       capture_row.target_id, capture_row.target_version,
                       capture_row.attempt_state, capture_row.discarded_at, capture_row.target_hash,
                       capture_row.plan_hash, capture_row.expected_vector_hash
                  INTO attempt_method_id, attempt_run_id,
                       attempt_method, attempt_page_no,
                       attempt_challenge_id, attempt_target_kind,
                       attempt_target_id, attempt_target_version,
                       attempt_state, attempt_discarded_at, attempt_target_hash,
                       attempt_plan_hash, attempt_vector_hash
                  FROM omr_template_verification_captures AS capture_row
                 WHERE capture_row.id = NEW.selected_attempt_id;
                IF NOT FOUND
                    OR attempt_method_id IS DISTINCT FROM NEW.method_id
                    OR attempt_run_id IS DISTINCT FROM method_run_id
                    OR attempt_method IS DISTINCT FROM method_name
                    OR attempt_page_no IS DISTINCT FROM NEW.page_no
                    OR attempt_challenge_id IS DISTINCT FROM method_challenge_id
                    OR attempt_target_kind IS DISTINCT FROM method_target_kind
                    OR attempt_target_id IS DISTINCT FROM method_target_id
                    OR attempt_target_version IS DISTINCT FROM method_target_version
                    OR attempt_state IS DISTINCT FROM 'passed'
                    OR attempt_discarded_at IS NOT NULL
                    OR attempt_target_hash IS DISTINCT FROM method_target_hash
                    OR attempt_plan_hash IS DISTINCT FROM method_plan_hash
                    OR attempt_vector_hash IS DISTINCT FROM method_vector_hash
                THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        MESSAGE = 'selected_attempt_id must reference a same-method/page passed attempt with matching target, plan, vector, and challenge';
                END IF;
                RETURN NEW;
            END;
            $function$
        SQL);
    }

    public function down(): void
    {
        // Keep the forward-compatible columns, constraints, and guards during rollback.
    }
};
