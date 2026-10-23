<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return;
        }
        if ($driver !== 'pgsql') {
            throw new RuntimeException("OMR physical assurance constraints require pgsql or sqlite; got {$driver}.");
        }

        // Normalize legacy duplicate attempt numbers before the immutable
        // capture trigger is installed; the index migration repeats this
        // idempotently after the trigger exists.
        $this->normalizeDuplicateAttemptNumbers();
        $connection = Schema::getConnection();

        // Historical challenge rows use quick/full. Keep those values readable
        // while permitting the method-bound internal modes going forward.
        $connection->statement(<<<'SQL'
            ALTER TABLE omr_physical_challenges
                DROP CONSTRAINT IF EXISTS omr_physical_challenges_mode_check,
                DROP CONSTRAINT IF EXISTS omr_physical_challenges_method_check,
                DROP CONSTRAINT IF EXISTS omr_physical_challenges_target_kind_check,
                DROP CONSTRAINT IF EXISTS omr_physical_challenges_target_id_check,
                DROP CONSTRAINT IF EXISTS omr_physical_challenges_marker_profile_version_check
        SQL);
        $connection->statement(<<<'SQL'
            ALTER TABLE omr_physical_challenges
                ADD CONSTRAINT omr_physical_challenges_mode_check
                    CHECK (mode IN ('quick', 'full', 'quick_rehearsal', 'full_qualification')),
                ADD CONSTRAINT omr_physical_challenges_method_check
                    CHECK (method IS NULL OR method IN ('pre_shaded', 'hand_shaded')),
                ADD CONSTRAINT omr_physical_challenges_target_kind_check
                    CHECK (target_kind IS NULL OR target_kind IN ('candidate', 'compiled_version')),
                ADD CONSTRAINT omr_physical_challenges_target_id_check
                    CHECK (target_id IS NULL OR target_id > 0),
                ADD CONSTRAINT omr_physical_challenges_marker_profile_version_check
                    CHECK (marker_profile_version IS NULL OR marker_profile_version > 0)
        SQL);

        $connection->statement(<<<'SQL'
            ALTER TABLE omr_template_verification_captures
                DROP CONSTRAINT IF EXISTS omr_verification_captures_method_check,
                DROP CONSTRAINT IF EXISTS omr_verification_captures_attempt_no_check,
                DROP CONSTRAINT IF EXISTS omr_verification_captures_attempt_state_check,
                DROP CONSTRAINT IF EXISTS omr_verification_captures_target_kind_check,
                DROP CONSTRAINT IF EXISTS omr_verification_captures_target_id_check,
                DROP CONSTRAINT IF EXISTS omr_verification_captures_accepted_orientation_check,
                DROP CONSTRAINT IF EXISTS omr_verification_captures_medium_check,
                DROP CONSTRAINT IF EXISTS omr_verification_captures_hand_medium_check,
                DROP CONSTRAINT IF EXISTS omr_verification_captures_marker_profile_version_check,
                DROP CONSTRAINT IF EXISTS omr_verification_captures_lock_version_check,
                DROP CONSTRAINT IF EXISTS omr_verification_captures_supersession_check
        SQL);
        $connection->statement(<<<'SQL'
            ALTER TABLE omr_template_verification_captures
                ADD CONSTRAINT omr_verification_captures_method_check
                    CHECK (method IS NULL OR method IN ('pre_shaded', 'hand_shaded')),
                ADD CONSTRAINT omr_verification_captures_attempt_no_check
                    CHECK (attempt_no IS NULL OR attempt_no > 0),
                ADD CONSTRAINT omr_verification_captures_attempt_state_check
                    CHECK (attempt_state IS NULL OR attempt_state IN ('active', 'passed', 'failed', 'superseded')),
                ADD CONSTRAINT omr_verification_captures_target_kind_check
                    CHECK (target_kind IS NULL OR target_kind IN ('candidate', 'compiled_version')),
                ADD CONSTRAINT omr_verification_captures_target_id_check
                    CHECK (target_id IS NULL OR target_id > 0),
                ADD CONSTRAINT omr_verification_captures_accepted_orientation_check
                    CHECK (accepted_orientation IS NULL OR accepted_orientation IN (0, 90, 180, 270)),
                ADD CONSTRAINT omr_verification_captures_medium_check
                    CHECK (medium IS NULL OR medium IN ('hb_2_pencil', 'dark_black_pen')),
                ADD CONSTRAINT omr_verification_captures_hand_medium_check
                    CHECK (
                        method IS NULL
                        OR (method = 'pre_shaded' AND medium IS NULL)
                        OR (method = 'hand_shaded' AND medium IS NOT NULL)
                    ),
                ADD CONSTRAINT omr_verification_captures_marker_profile_version_check
                    CHECK (marker_profile_version IS NULL OR marker_profile_version > 0),
                ADD CONSTRAINT omr_verification_captures_lock_version_check
                    CHECK (lock_version IS NULL OR lock_version >= 0),
                ADD CONSTRAINT omr_verification_captures_supersession_check
                    CHECK (attempt_state IS DISTINCT FROM 'superseded'
                        OR (superseded_by_attempt_id IS NOT NULL AND supersede_reason IS NOT NULL AND char_length(trim(supersede_reason)) > 0))
        SQL);

        $connection->statement(<<<'SQL'
            ALTER TABLE omr_template_verification_item_evidence
                DROP CONSTRAINT IF EXISTS omr_verification_item_evidence_options_check,
                DROP CONSTRAINT IF EXISTS omr_verification_item_evidence_means_check
        SQL);
        $connection->statement(<<<'SQL'
            ALTER TABLE omr_template_verification_item_evidence
                ADD CONSTRAINT omr_verification_item_evidence_options_check
                    CHECK (options IS NULL OR jsonb_typeof(options::jsonb) IN ('array', 'object')),
                ADD CONSTRAINT omr_verification_item_evidence_means_check
                    CHECK (means IS NULL OR jsonb_typeof(means::jsonb) IN ('array', 'object'))
        SQL);

        $connection->statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION nexiam_omr_method_page_selected_attempt_guard()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                method_run_id bigint;
                method_name text;
                method_target_hash text;
                method_plan_hash text;
                method_vector_hash text;
                attempt_method_id bigint;
                attempt_run_id bigint;
                attempt_method text;
                attempt_page_no smallint;
                attempt_state text;
                attempt_target_hash text;
                attempt_plan_hash text;
                attempt_vector_hash text;
            BEGIN
                IF NEW.selected_attempt_id IS NULL THEN
                    RETURN NEW;
                END IF;

                SELECT method_row.run_id, method_row.method, method_row.target_hash, method_row.plan_hash, method_row.expected_vector_hash
                  INTO method_run_id, method_name, method_target_hash, method_plan_hash, method_vector_hash
                  FROM omr_template_verification_methods AS method_row
                 WHERE method_row.id = NEW.method_id;

                SELECT capture_row.method_id, capture_row.run_id, capture_row.method, capture_row.page_no, capture_row.attempt_state, capture_row.target_hash, capture_row.plan_hash, capture_row.expected_vector_hash
                  INTO attempt_method_id, attempt_run_id, attempt_method, attempt_page_no, attempt_state, attempt_target_hash, attempt_plan_hash, attempt_vector_hash
                  FROM omr_template_verification_captures AS capture_row
                 WHERE capture_row.id = NEW.selected_attempt_id;

                IF NOT FOUND
                    OR attempt_method_id IS DISTINCT FROM NEW.method_id
                    OR attempt_run_id IS DISTINCT FROM method_run_id
                    OR attempt_method IS DISTINCT FROM method_name
                    OR attempt_page_no IS DISTINCT FROM NEW.page_no
                    OR attempt_state IS DISTINCT FROM 'passed'
                    OR attempt_target_hash IS DISTINCT FROM method_target_hash
                    OR attempt_plan_hash IS DISTINCT FROM method_plan_hash
                    OR attempt_vector_hash IS DISTINCT FROM method_vector_hash
                THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '23514',
                        MESSAGE = 'selected_attempt_id must reference a same-method/page passed attempt with matching target, plan, and vector hashes';
                END IF;

                RETURN NEW;
            END;
            $function$
        SQL);

        $connection->statement(<<<'SQL'
            DROP TRIGGER IF EXISTS omr_method_pages_selected_attempt_guard
                ON omr_template_verification_method_pages
        SQL);
        $connection->statement(<<<'SQL'
            CREATE CONSTRAINT TRIGGER omr_method_pages_selected_attempt_guard
            AFTER INSERT OR UPDATE OF method_id, page_no, selected_attempt_id
            ON omr_template_verification_method_pages
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW
            EXECUTE FUNCTION nexiam_omr_method_page_selected_attempt_guard()
        SQL);

        $connection->statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION nexiam_omr_item_evidence_immutable()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            BEGIN
                RAISE EXCEPTION USING
                    ERRCODE = '55000',
                    MESSAGE = 'verification item evidence is immutable after insert';
                RETURN NEW;
            END;
            $function$
        SQL);
        $connection->statement(<<<'SQL'
            DROP TRIGGER IF EXISTS omr_item_evidence_immutable_guard
                ON omr_template_verification_item_evidence
        SQL);
        $connection->statement(<<<'SQL'
            CREATE TRIGGER omr_item_evidence_immutable_guard
            BEFORE UPDATE OR DELETE
            ON omr_template_verification_item_evidence
            FOR EACH ROW
            EXECUTE FUNCTION nexiam_omr_item_evidence_immutable()
        SQL);

        $connection->statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION nexiam_omr_capture_immutable()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '55000',
                        MESSAGE = 'verification capture is immutable after insert';
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

                IF (to_jsonb(OLD) - ARRAY[
                    'attempt_state',
                    'superseded_by_attempt_id',
                    'superseded_by_user_id',
                    'superseded_at',
                    'supersede_reason',
                    'selected_by_user_id',
                    'selected_at',
                    'selection_reason',
                    'lock_version',
                    'updated_at'
                ]::text[]) IS DISTINCT FROM (to_jsonb(NEW) - ARRAY[
                    'attempt_state',
                    'superseded_by_attempt_id',
                    'superseded_by_user_id',
                    'superseded_at',
                    'supersede_reason',
                    'selected_by_user_id',
                    'selected_at',
                    'selection_reason',
                    'lock_version',
                    'updated_at'
                ]::text[]) THEN
                    RAISE EXCEPTION USING
                        ERRCODE = '55000',
                        MESSAGE = 'verification capture facts are immutable after insert';
                END IF;

                RETURN NEW;
            END;
            $function$
        SQL);
        $connection->statement(<<<'SQL'
            DROP TRIGGER IF EXISTS omr_capture_immutable_guard
                ON omr_template_verification_captures
        SQL);
        $connection->statement(<<<'SQL'
            CREATE TRIGGER omr_capture_immutable_guard
            BEFORE UPDATE OR DELETE
            ON omr_template_verification_captures
            FOR EACH ROW
            EXECUTE FUNCTION nexiam_omr_capture_immutable()
        SQL);

    }

    private function normalizeDuplicateAttemptNumbers(): void
    {
        $nextByGroup = [];
        foreach (DB::table('omr_template_verification_captures')
            ->whereNotNull('method')
            ->where('page_no', '>', 0)
            ->orderBy('id')
            ->cursor() as $capture) {
            if (! in_array((string) $capture->method, ['pre_shaded', 'hand_shaded'], true)) {
                continue;
            }
            $group = implode(':', [(int) $capture->run_id, (string) $capture->method, (int) $capture->page_no]);
            $attemptNo = ($nextByGroup[$group] ?? 0) + 1;
            $nextByGroup[$group] = $attemptNo;
            if ((int) $capture->attempt_no === $attemptNo) {
                continue;
            }
            DB::table('omr_template_verification_captures')
                ->where('id', $capture->id)
                ->update(['attempt_no' => $attemptNo]);
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return;
        }
        if ($driver !== 'pgsql') {
            throw new RuntimeException("OMR physical assurance constraints require pgsql or sqlite; got {$driver}.");
        }
        $connection = Schema::getConnection();
        $connection->statement('DROP TRIGGER IF EXISTS omr_method_pages_selected_attempt_guard ON omr_template_verification_method_pages');
        $connection->statement('DROP FUNCTION IF EXISTS nexiam_omr_method_page_selected_attempt_guard()');
        $connection->statement('DROP TRIGGER IF EXISTS omr_item_evidence_immutable_guard ON omr_template_verification_item_evidence');
        $connection->statement('DROP FUNCTION IF EXISTS nexiam_omr_item_evidence_immutable()');
        $connection->statement('DROP TRIGGER IF EXISTS omr_capture_immutable_guard ON omr_template_verification_captures');
        $connection->statement('DROP FUNCTION IF EXISTS nexiam_omr_capture_immutable()');
        $connection->statement(<<<'SQL'
            ALTER TABLE omr_template_verification_item_evidence
                DROP CONSTRAINT IF EXISTS omr_verification_item_evidence_options_check,
                DROP CONSTRAINT IF EXISTS omr_verification_item_evidence_means_check
        SQL);
        $connection->statement(<<<'SQL'
            ALTER TABLE omr_template_verification_captures
                DROP CONSTRAINT IF EXISTS omr_verification_captures_method_check,
                DROP CONSTRAINT IF EXISTS omr_verification_captures_attempt_no_check,
                DROP CONSTRAINT IF EXISTS omr_verification_captures_attempt_state_check,
                DROP CONSTRAINT IF EXISTS omr_verification_captures_target_kind_check,
                DROP CONSTRAINT IF EXISTS omr_verification_captures_target_id_check,
                DROP CONSTRAINT IF EXISTS omr_verification_captures_accepted_orientation_check,
                DROP CONSTRAINT IF EXISTS omr_verification_captures_medium_check,
                DROP CONSTRAINT IF EXISTS omr_verification_captures_hand_medium_check,
                DROP CONSTRAINT IF EXISTS omr_verification_captures_marker_profile_version_check,
                DROP CONSTRAINT IF EXISTS omr_verification_captures_lock_version_check,
                DROP CONSTRAINT IF EXISTS omr_verification_captures_supersession_check
        SQL);
        $connection->statement(<<<'SQL'
            ALTER TABLE omr_physical_challenges
                DROP CONSTRAINT IF EXISTS omr_physical_challenges_mode_check,
                DROP CONSTRAINT IF EXISTS omr_physical_challenges_method_check,
                DROP CONSTRAINT IF EXISTS omr_physical_challenges_target_kind_check,
                DROP CONSTRAINT IF EXISTS omr_physical_challenges_target_id_check,
                DROP CONSTRAINT IF EXISTS omr_physical_challenges_marker_profile_version_check,
                ADD CONSTRAINT omr_physical_challenges_mode_check CHECK (mode IN ('quick', 'full'))
        SQL);
    }
};
