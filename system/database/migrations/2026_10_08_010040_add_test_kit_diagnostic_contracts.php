<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException('Test Kit diagnostic contracts require PostgreSQL or SQLite.');
        }

        $this->addTupleReferenceKeys();
        $this->createAcceptancePlans();
        $this->addRunAcceptanceContext();
        $this->createPageAttempts();
        $this->createPageSelections();
        $this->addDatabaseGuards($driver);
    }

    private function addTupleReferenceKeys(): void
    {
        Schema::table('test_kits', function (Blueprint $table): void {
            $table->unique(['id', 'institution_id'], 'test_kits_id_institution_ref_unique');
        });

        Schema::table('test_kit_versions', function (Blueprint $table): void {
            $table->unique(['id', 'manifest_sha256'], 'test_kit_version_id_manifest_unique');
            $table->unique(
                ['id', 'test_kit_id', 'template_version_id', 'booklet_version_id', 'booklet_set_id', 'test_instrument_version_id', 'manifest_sha256'],
                'test_kit_version_diagnostic_owner_tuple_unique',
            );
        });

        Schema::table('test_instrument_versions', function (Blueprint $table): void {
            $table->unique(['id', 'content_sha256'], 'test_instrument_version_content_unique');
        });

        Schema::table('academic_cycles', function (Blueprint $table): void {
            $table->unique(['id', 'institution_id'], 'academic_cycle_institution_ref_unique');
        });

        Schema::table('omr_diagnostic_captures', function (Blueprint $table): void {
            $table->unique(['id', 'diagnostic_run_id'], 'omr_diag_capture_run_ref_unique');
        });

        Schema::table('omr_pending_capture_sets', function (Blueprint $table): void {
            $table->unique(['id', 'diagnostic_run_id'], 'omr_pending_set_run_ref_unique');
        });

        Schema::table('omr_pending_capture_page_evidence', function (Blueprint $table): void {
            $table->unique(['id', 'set_id', 'page_no'], 'omr_pending_page_set_number_unique');
        });
    }

    private function createAcceptancePlans(): void
    {
        Schema::create('test_kit_acceptance_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('test_kit_version_id')->constrained('test_kit_versions')->restrictOnDelete();
            $table->char('manifest_sha256', 64);
            $table->string('protocol_schema_version', 64);
            $table->char('protocol_sha256', 64);
            $table->string('plan_schema_version', 64);
            $table->char('plan_sha256', 64);
            $table->text('plan_payload');
            $table->string('source_key', 128);
            $table->char('authored_source_sha256', 64);
            $table->string('author_reference', 500);
            $table->json('truth_source_references_json');
            $table->string('review_evidence_reference', 500);
            $table->char('review_evidence_sha256', 64);
            $table->string('provenance_kind', 40);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                [
                    'id',
                    'test_kit_version_id',
                    'manifest_sha256',
                    'protocol_schema_version',
                    'protocol_sha256',
                    'plan_schema_version',
                    'plan_sha256',
                ],
                'test_kit_plan_run_identity_unique',
            );
            $table->unique(
                ['test_kit_version_id', 'source_key', 'authored_source_sha256', 'plan_sha256'],
                'test_kit_plan_source_identity_unique',
            );
            $table->index(['test_kit_version_id', 'created_at'], 'test_kit_plan_version_created_idx');

            $table->foreign(['test_kit_version_id', 'manifest_sha256'], 'test_kit_plan_material_fk')
                ->references(['id', 'manifest_sha256'])
                ->on('test_kit_versions')
                ->restrictOnDelete();
        });
    }

    private function addRunAcceptanceContext(): void
    {
        Schema::table('omr_diagnostic_runs', function (Blueprint $table): void {
            $table->foreignId('test_kit_id')->nullable();
            $table->foreignId('test_kit_version_id')->nullable();
            $table->foreignId('acceptance_plan_id')->nullable();
            $table->foreignId('booklet_set_id')->nullable();
            $table->char('manifest_sha256', 64)->nullable();
            $table->foreignId('test_instrument_version_id')->nullable();
            $table->char('profile_content_sha256', 64)->nullable();
            $table->string('acceptance_protocol_schema_version', 64)->nullable();
            $table->char('acceptance_protocol_sha256', 64)->nullable();
            $table->string('acceptance_plan_schema_version', 64)->nullable();
            $table->char('acceptance_plan_sha256', 64)->nullable();
            $table->string('scoring_kernel_identity', 128)->nullable();
            $table->json('expected_worker_identity_json')->nullable();
            $table->text('acceptance_plan_json')->nullable();
            $table->unsignedBigInteger('sealed_evidence_revision')->nullable();
            $table->char('sealed_evidence_vector_sha256', 64)->nullable();
            $table->timestamp('sealed_at')->nullable();

            $table->unique(
                [
                    'id',
                    'test_kit_id',
                    'test_kit_version_id',
                    'acceptance_plan_id',
                    'manifest_sha256',
                    'acceptance_protocol_schema_version',
                    'acceptance_protocol_sha256',
                    'acceptance_plan_schema_version',
                    'acceptance_plan_sha256',
                ],
                'omr_diag_run_acceptance_identity_unique',
            );
            $table->index(['test_kit_version_id', 'sealed_at'], 'omr_diag_run_test_kit_seal_idx');

            $table->foreign(
                ['test_kit_id', 'institution_id'],
                'omr_diag_run_test_kit_institution_fk',
            )
                ->references(['id', 'institution_id'])
                ->on('test_kits')
                ->restrictOnDelete();
            $table->foreign(
                ['academic_cycle_id', 'institution_id'],
                'omr_diag_run_cycle_institution_fk',
            )
                ->references(['id', 'institution_id'])
                ->on('academic_cycles')
                ->restrictOnDelete();
            $table->foreign(
                ['test_kit_version_id', 'test_kit_id', 'template_version_id', 'booklet_version_id', 'booklet_set_id', 'test_instrument_version_id', 'manifest_sha256'],
                'omr_diag_run_test_kit_tuple_fk',
            )
                ->references(['id', 'test_kit_id', 'template_version_id', 'booklet_version_id', 'booklet_set_id', 'test_instrument_version_id', 'manifest_sha256'])
                ->on('test_kit_versions')
                ->restrictOnDelete();
            $table->foreign(
                ['test_instrument_version_id', 'profile_content_sha256'],
                'omr_diag_run_profile_content_fk',
            )
                ->references(['id', 'content_sha256'])
                ->on('test_instrument_versions')
                ->restrictOnDelete();
            $table->foreign(
                ['acceptance_plan_id', 'test_kit_version_id', 'manifest_sha256', 'acceptance_protocol_schema_version', 'acceptance_protocol_sha256', 'acceptance_plan_schema_version', 'acceptance_plan_sha256'],
                'omr_diag_run_plan_identity_fk',
            )
                ->references(['id', 'test_kit_version_id', 'manifest_sha256', 'protocol_schema_version', 'protocol_sha256', 'plan_schema_version', 'plan_sha256'])
                ->on('test_kit_acceptance_plans')
                ->restrictOnDelete();
        });
    }

    private function createPageAttempts(): void
    {
        Schema::create('test_kit_diagnostic_page_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diagnostic_run_id')->constrained('omr_diagnostic_runs')->restrictOnDelete();
            $table->string('case_id', 100);
            $table->unsignedSmallInteger('page_no');
            $table->foreignId('diagnostic_capture_id');
            $table->foreignId('pending_set_id')->nullable();
            $table->foreignId('pending_page_evidence_id')->nullable();
            $table->char('input_sha256', 64);
            $table->unsignedBigInteger('predecessor_attempt_id')->nullable();
            $table->json('item_bindings_json');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['id', 'diagnostic_run_id', 'case_id', 'page_no'],
                'test_kit_diag_attempt_lineage_unique',
            );
            $table->unique('diagnostic_capture_id', 'test_kit_diag_attempt_capture_unique');
            $table->unique('predecessor_attempt_id', 'test_kit_diag_attempt_predecessor_unique');
            $table->index(['diagnostic_run_id', 'case_id', 'page_no', 'id'], 'test_kit_diag_attempt_page_idx');

            $table->foreign(['diagnostic_capture_id', 'diagnostic_run_id'], 'test_kit_diag_attempt_capture_run_fk')
                ->references(['id', 'diagnostic_run_id'])
                ->on('omr_diagnostic_captures')
                ->restrictOnDelete();
            $table->foreign(['pending_set_id', 'diagnostic_run_id'], 'test_kit_diag_attempt_pending_run_fk')
                ->references(['id', 'diagnostic_run_id'])
                ->on('omr_pending_capture_sets')
                ->restrictOnDelete();
            $table->foreign(['pending_page_evidence_id', 'pending_set_id', 'page_no'], 'test_kit_diag_attempt_pending_page_fk')
                ->references(['id', 'set_id', 'page_no'])
                ->on('omr_pending_capture_page_evidence')
                ->restrictOnDelete();
            $table->foreign(
                ['predecessor_attempt_id', 'diagnostic_run_id', 'case_id', 'page_no'],
                'test_kit_diag_attempt_predecessor_fk',
            )
                ->references(['id', 'diagnostic_run_id', 'case_id', 'page_no'])
                ->on('test_kit_diagnostic_page_attempts')
                ->restrictOnDelete();
        });
    }

    private function createPageSelections(): void
    {
        Schema::create('test_kit_diagnostic_page_selections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diagnostic_run_id')->constrained('omr_diagnostic_runs')->restrictOnDelete();
            $table->string('case_id', 100);
            $table->unsignedSmallInteger('page_no');
            $table->unsignedBigInteger('selected_attempt_id');
            $table->unsignedBigInteger('revision')->default(1);
            $table->timestamps();

            $table->unique(['diagnostic_run_id', 'case_id', 'page_no'], 'test_kit_diag_selection_page_unique');
            $table->unique(
                ['diagnostic_run_id', 'case_id', 'page_no', 'selected_attempt_id'],
                'test_kit_diag_selection_attempt_unique',
            );
            $table->index(['diagnostic_run_id', 'revision'], 'test_kit_diag_selection_revision_idx');

            $table->foreign(
                ['selected_attempt_id', 'diagnostic_run_id', 'case_id', 'page_no'],
                'test_kit_diag_selection_attempt_fk',
            )
                ->references(['id', 'diagnostic_run_id', 'case_id', 'page_no'])
                ->on('test_kit_diagnostic_page_attempts')
                ->restrictOnDelete();
        });
    }

    private function addDatabaseGuards(string $driver): void
    {
        if ($driver === 'pgsql') {
            $this->addPostgresGuards();

            return;
        }

        $this->addSqliteGuards();
    }

    private function addPostgresGuards(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE test_kit_acceptance_plans
                ADD CONSTRAINT test_kit_acceptance_plan_values_check CHECK (
                    length(trim(source_key)) > 0
                    AND length(trim(author_reference)) > 0
                    AND length(trim(review_evidence_reference)) > 0
                    AND provenance_kind IN ('synthetic_software_fixture', 'reviewed_software_plan')
                    AND authored_source_sha256 ~ '^[a-f0-9]{64}$'
                    AND protocol_sha256 ~ '^[a-f0-9]{64}$'
                    AND plan_sha256 ~ '^[a-f0-9]{64}$'
                    AND review_evidence_sha256 ~ '^[a-f0-9]{64}$'
                    AND jsonb_typeof(truth_source_references_json::jsonb) = 'array'
                    AND jsonb_array_length(truth_source_references_json::jsonb) > 0
                    AND length(trim(plan_payload)) > 0
                    AND left(ltrim(plan_payload), 1) NOT IN ('{', '[')
                )
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE omr_diagnostic_runs
                ADD CONSTRAINT omr_diag_run_acceptance_context_check CHECK (
                    (
                        test_kit_id IS NULL
                        AND test_kit_version_id IS NULL
                        AND acceptance_plan_id IS NULL
                        AND booklet_set_id IS NULL
                        AND manifest_sha256 IS NULL
                        AND test_instrument_version_id IS NULL
                        AND profile_content_sha256 IS NULL
                        AND acceptance_protocol_schema_version IS NULL
                        AND acceptance_protocol_sha256 IS NULL
                        AND acceptance_plan_schema_version IS NULL
                        AND acceptance_plan_sha256 IS NULL
                        AND scoring_kernel_identity IS NULL
                        AND expected_worker_identity_json IS NULL
                        AND acceptance_plan_json IS NULL
                    )
                    OR (
                        test_kit_id IS NOT NULL
                        AND test_kit_version_id IS NOT NULL
                        AND acceptance_plan_id IS NOT NULL
                        AND booklet_set_id IS NOT NULL
                        AND manifest_sha256 IS NOT NULL AND manifest_sha256 ~ '^[a-f0-9]{64}$'
                        AND test_instrument_version_id IS NOT NULL
                        AND profile_content_sha256 IS NOT NULL AND profile_content_sha256 ~ '^[a-f0-9]{64}$'
                        AND acceptance_protocol_schema_version IS NOT NULL AND length(trim(acceptance_protocol_schema_version)) > 0
                        AND acceptance_protocol_sha256 IS NOT NULL AND acceptance_protocol_sha256 ~ '^[a-f0-9]{64}$'
                        AND acceptance_plan_schema_version IS NOT NULL AND length(trim(acceptance_plan_schema_version)) > 0
                        AND acceptance_plan_sha256 IS NOT NULL AND acceptance_plan_sha256 ~ '^[a-f0-9]{64}$'
                        AND scoring_kernel_identity IS NOT NULL AND length(trim(scoring_kernel_identity)) > 0
                        AND expected_worker_identity_json IS NOT NULL
                        AND jsonb_typeof(expected_worker_identity_json::jsonb) = 'object'
                        AND acceptance_plan_json IS NOT NULL AND length(trim(acceptance_plan_json)) > 0
                        AND left(ltrim(acceptance_plan_json), 1) NOT IN ('{', '[')
                        AND declared_set_code = 'A'
                    )
                ),
                ADD CONSTRAINT omr_diag_run_seal_check CHECK (
                    (sealed_evidence_revision IS NULL AND sealed_evidence_vector_sha256 IS NULL AND sealed_at IS NULL)
                    OR (
                        test_kit_version_id IS NOT NULL
                        AND sealed_evidence_revision IS NOT NULL
                        AND sealed_evidence_vector_sha256 IS NOT NULL
                        AND sealed_evidence_vector_sha256 ~ '^[a-f0-9]{64}$'
                        AND sealed_at IS NOT NULL
                    )
                )
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE test_kit_diagnostic_page_attempts
                ADD CONSTRAINT test_kit_diag_attempt_page_values_check CHECK (
                    page_no BETWEEN 1 AND 4
                    AND length(trim(case_id)) > 0
                    AND input_sha256 ~ '^[a-f0-9]{64}$'
                    AND jsonb_typeof(item_bindings_json::jsonb) = 'array'
                    AND jsonb_array_length(item_bindings_json::jsonb) > 0
                    AND ((pending_set_id IS NULL AND pending_page_evidence_id IS NULL)
                        OR (pending_set_id IS NOT NULL AND pending_page_evidence_id IS NOT NULL))
                )
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE test_kit_diagnostic_page_selections
                ADD CONSTRAINT test_kit_diag_selection_revision_check CHECK (
                    revision > 0 AND page_no BETWEEN 1 AND 4 AND length(trim(case_id)) > 0
                )
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION nexiam_test_kit_acceptance_plan_append_only()
            RETURNS trigger LANGUAGE plpgsql AS $function$
            BEGIN
                RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'test kit acceptance plans are append-only';
            END;
            $function$
        SQL);
        DB::statement('DROP TRIGGER IF EXISTS test_kit_acceptance_plans_append_only_guard ON test_kit_acceptance_plans');
        DB::statement('CREATE TRIGGER test_kit_acceptance_plans_append_only_guard BEFORE UPDATE OR DELETE ON test_kit_acceptance_plans FOR EACH ROW EXECUTE FUNCTION nexiam_test_kit_acceptance_plan_append_only()');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION nexiam_omr_diag_acceptance_context_guard()
            RETURNS trigger LANGUAGE plpgsql AS $function$
            BEGIN
                IF NEW.test_kit_id IS DISTINCT FROM OLD.test_kit_id
                    OR NEW.test_kit_version_id IS DISTINCT FROM OLD.test_kit_version_id
                    OR NEW.acceptance_plan_id IS DISTINCT FROM OLD.acceptance_plan_id
                    OR NEW.booklet_set_id IS DISTINCT FROM OLD.booklet_set_id
                    OR NEW.manifest_sha256 IS DISTINCT FROM OLD.manifest_sha256
                    OR NEW.test_instrument_version_id IS DISTINCT FROM OLD.test_instrument_version_id
                    OR NEW.profile_content_sha256 IS DISTINCT FROM OLD.profile_content_sha256
                    OR NEW.acceptance_protocol_schema_version IS DISTINCT FROM OLD.acceptance_protocol_schema_version
                    OR NEW.acceptance_protocol_sha256 IS DISTINCT FROM OLD.acceptance_protocol_sha256
                    OR NEW.acceptance_plan_schema_version IS DISTINCT FROM OLD.acceptance_plan_schema_version
                    OR NEW.acceptance_plan_sha256 IS DISTINCT FROM OLD.acceptance_plan_sha256
                    OR NEW.scoring_kernel_identity IS DISTINCT FROM OLD.scoring_kernel_identity
                    OR NEW.expected_worker_identity_json::text IS DISTINCT FROM OLD.expected_worker_identity_json::text
                    OR NEW.acceptance_plan_json IS DISTINCT FROM OLD.acceptance_plan_json THEN
                    RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'diagnostic acceptance context is immutable after insert';
                END IF;

                IF OLD.sealed_at IS NOT NULL AND (
                    NEW.sealed_evidence_revision IS DISTINCT FROM OLD.sealed_evidence_revision
                    OR NEW.sealed_evidence_vector_sha256 IS DISTINCT FROM OLD.sealed_evidence_vector_sha256
                    OR NEW.sealed_at IS DISTINCT FROM OLD.sealed_at
                ) THEN
                    RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'diagnostic evidence seal is immutable';
                END IF;

                RETURN NEW;
            END;
            $function$
        SQL);
        DB::statement('DROP TRIGGER IF EXISTS omr_diag_acceptance_context_guard ON omr_diagnostic_runs');
        DB::statement('CREATE TRIGGER omr_diag_acceptance_context_guard BEFORE UPDATE ON omr_diagnostic_runs FOR EACH ROW EXECUTE FUNCTION nexiam_omr_diag_acceptance_context_guard()');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION nexiam_test_kit_diag_attempt_append_only()
            RETURNS trigger LANGUAGE plpgsql AS $function$
            BEGIN
                RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'diagnostic page attempts are append-only';
            END;
            $function$
        SQL);
        DB::statement('DROP TRIGGER IF EXISTS test_kit_diag_attempt_append_only_guard ON test_kit_diagnostic_page_attempts');
        DB::statement('CREATE TRIGGER test_kit_diag_attempt_append_only_guard BEFORE UPDATE OR DELETE ON test_kit_diagnostic_page_attempts FOR EACH ROW EXECUTE FUNCTION nexiam_test_kit_diag_attempt_append_only()');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION nexiam_test_kit_diag_selection_guard()
            RETURNS trigger LANGUAGE plpgsql AS $function$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'diagnostic page selections are retained';
                END IF;

                IF TG_OP = 'INSERT' THEN
                    IF NEW.revision <> 1 THEN
                        RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'diagnostic page selection revision must start at one';
                    END IF;
                    RETURN NEW;
                END IF;

                IF NEW.id IS DISTINCT FROM OLD.id
                    OR NEW.diagnostic_run_id IS DISTINCT FROM OLD.diagnostic_run_id
                    OR NEW.case_id IS DISTINCT FROM OLD.case_id
                    OR NEW.page_no IS DISTINCT FROM OLD.page_no
                    OR NEW.created_at IS DISTINCT FROM OLD.created_at
                    OR NEW.selected_attempt_id IS NOT DISTINCT FROM OLD.selected_attempt_id
                    OR NEW.revision <> OLD.revision + 1 THEN
                    RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'diagnostic selection requires a new same-page attempt and next revision';
                END IF;

                RETURN NEW;
            END;
            $function$
        SQL);
        DB::statement('DROP TRIGGER IF EXISTS test_kit_diag_selection_revision_guard ON test_kit_diagnostic_page_selections');
        DB::statement('CREATE TRIGGER test_kit_diag_selection_revision_guard BEFORE INSERT OR UPDATE OR DELETE ON test_kit_diagnostic_page_selections FOR EACH ROW EXECUTE FUNCTION nexiam_test_kit_diag_selection_guard()');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION nexiam_omr_diag_capture_after_seal_guard()
            RETURNS trigger LANGUAGE plpgsql AS $function$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM omr_diagnostic_runs
                    WHERE id = NEW.diagnostic_run_id AND sealed_at IS NOT NULL
                ) THEN
                    RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'sealed diagnostic evidence cannot receive captures';
                END IF;

                RETURN NEW;
            END;
            $function$
        SQL);
        DB::statement('DROP TRIGGER IF EXISTS omr_diag_capture_after_seal_guard ON omr_diagnostic_captures');
        DB::statement('CREATE TRIGGER omr_diag_capture_after_seal_guard BEFORE INSERT ON omr_diagnostic_captures FOR EACH ROW EXECUTE FUNCTION nexiam_omr_diag_capture_after_seal_guard()');
    }

    private function addSqliteGuards(): void
    {
        DB::statement(<<<'SQL'
            CREATE TRIGGER test_kit_acceptance_plans_append_only_update_guard
            BEFORE UPDATE ON test_kit_acceptance_plans
            BEGIN SELECT RAISE(ABORT, 'test kit acceptance plans are append-only'); END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER test_kit_acceptance_plans_append_only_delete_guard
            BEFORE DELETE ON test_kit_acceptance_plans
            BEGIN SELECT RAISE(ABORT, 'test kit acceptance plans are append-only'); END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER test_kit_acceptance_plan_values_insert_guard
            BEFORE INSERT ON test_kit_acceptance_plans
            WHEN length(trim(NEW.source_key)) = 0
                OR length(trim(NEW.author_reference)) = 0
                OR length(trim(NEW.review_evidence_reference)) = 0
                OR NEW.provenance_kind NOT IN ('synthetic_software_fixture', 'reviewed_software_plan')
                OR NEW.authored_source_sha256 IS NULL OR length(NEW.authored_source_sha256) <> 64
                OR NEW.protocol_sha256 IS NULL OR length(NEW.protocol_sha256) <> 64
                OR NEW.plan_sha256 IS NULL OR length(NEW.plan_sha256) <> 64
                OR NEW.review_evidence_sha256 IS NULL OR length(NEW.review_evidence_sha256) <> 64
                OR NEW.truth_source_references_json IS NULL
                OR json_valid(NEW.truth_source_references_json) = 0
                OR json_type(NEW.truth_source_references_json) <> 'array'
                OR json_array_length(NEW.truth_source_references_json) = 0
                OR length(trim(NEW.plan_payload)) = 0
                OR substr(ltrim(NEW.plan_payload), 1, 1) IN ('{', '[')
            BEGIN SELECT RAISE(ABORT, 'test kit acceptance plan attribution or private payload is invalid'); END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER omr_diag_acceptance_context_insert_guard
            BEFORE INSERT ON omr_diagnostic_runs
            WHEN NOT (
                (
                    NEW.test_kit_id IS NULL
                    AND NEW.test_kit_version_id IS NULL
                    AND NEW.acceptance_plan_id IS NULL
                    AND NEW.booklet_set_id IS NULL
                    AND NEW.manifest_sha256 IS NULL
                    AND NEW.test_instrument_version_id IS NULL
                    AND NEW.profile_content_sha256 IS NULL
                    AND NEW.acceptance_protocol_schema_version IS NULL
                    AND NEW.acceptance_protocol_sha256 IS NULL
                    AND NEW.acceptance_plan_schema_version IS NULL
                    AND NEW.acceptance_plan_sha256 IS NULL
                    AND NEW.scoring_kernel_identity IS NULL
                    AND NEW.expected_worker_identity_json IS NULL
                    AND NEW.acceptance_plan_json IS NULL
                )
                OR
                (
                    NEW.test_kit_id IS NOT NULL
                    AND NEW.test_kit_version_id IS NOT NULL
                    AND NEW.acceptance_plan_id IS NOT NULL
                    AND NEW.booklet_set_id IS NOT NULL
                    AND NEW.manifest_sha256 IS NOT NULL AND length(NEW.manifest_sha256) = 64
                    AND NEW.test_instrument_version_id IS NOT NULL
                    AND NEW.profile_content_sha256 IS NOT NULL AND length(NEW.profile_content_sha256) = 64
                    AND NEW.acceptance_protocol_schema_version IS NOT NULL AND length(trim(NEW.acceptance_protocol_schema_version)) > 0
                    AND NEW.acceptance_protocol_sha256 IS NOT NULL AND length(NEW.acceptance_protocol_sha256) = 64
                    AND NEW.acceptance_plan_schema_version IS NOT NULL AND length(trim(NEW.acceptance_plan_schema_version)) > 0
                    AND NEW.acceptance_plan_sha256 IS NOT NULL AND length(NEW.acceptance_plan_sha256) = 64
                    AND NEW.scoring_kernel_identity IS NOT NULL AND length(trim(NEW.scoring_kernel_identity)) > 0
                    AND NEW.expected_worker_identity_json IS NOT NULL
                    AND json_valid(NEW.expected_worker_identity_json) = 1
                    AND json_type(NEW.expected_worker_identity_json) = 'object'
                    AND NEW.acceptance_plan_json IS NOT NULL AND length(trim(NEW.acceptance_plan_json)) > 0
                    AND substr(ltrim(NEW.acceptance_plan_json), 1, 1) NOT IN ('{', '[')
                    AND NEW.declared_set_code = 'A'
                )
            )
            BEGIN SELECT RAISE(ABORT, 'diagnostic acceptance pins must be complete and encrypted before capture'); END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER omr_diag_acceptance_context_update_guard
            BEFORE UPDATE ON omr_diagnostic_runs
            WHEN OLD.test_kit_id IS NOT NEW.test_kit_id
                OR OLD.test_kit_version_id IS NOT NEW.test_kit_version_id
                OR OLD.acceptance_plan_id IS NOT NEW.acceptance_plan_id
                OR OLD.booklet_set_id IS NOT NEW.booklet_set_id
                OR OLD.manifest_sha256 IS NOT NEW.manifest_sha256
                OR OLD.test_instrument_version_id IS NOT NEW.test_instrument_version_id
                OR OLD.profile_content_sha256 IS NOT NEW.profile_content_sha256
                OR OLD.acceptance_protocol_schema_version IS NOT NEW.acceptance_protocol_schema_version
                OR OLD.acceptance_protocol_sha256 IS NOT NEW.acceptance_protocol_sha256
                OR OLD.acceptance_plan_schema_version IS NOT NEW.acceptance_plan_schema_version
                OR OLD.acceptance_plan_sha256 IS NOT NEW.acceptance_plan_sha256
                OR OLD.scoring_kernel_identity IS NOT NEW.scoring_kernel_identity
                OR OLD.expected_worker_identity_json IS NOT NEW.expected_worker_identity_json
                OR OLD.acceptance_plan_json IS NOT NEW.acceptance_plan_json
                OR (OLD.sealed_at IS NOT NULL AND (
                    OLD.sealed_evidence_revision IS NOT NEW.sealed_evidence_revision
                    OR OLD.sealed_evidence_vector_sha256 IS NOT NEW.sealed_evidence_vector_sha256
                    OR OLD.sealed_at IS NOT NEW.sealed_at
                ))
            BEGIN SELECT RAISE(ABORT, 'diagnostic acceptance context and sealed evidence are immutable'); END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER omr_diag_seal_values_insert_guard
            BEFORE INSERT ON omr_diagnostic_runs
            WHEN NOT (
                (NEW.sealed_evidence_revision IS NULL AND NEW.sealed_evidence_vector_sha256 IS NULL AND NEW.sealed_at IS NULL)
                OR (NEW.test_kit_version_id IS NOT NULL AND NEW.sealed_evidence_revision IS NOT NULL
                    AND NEW.sealed_evidence_vector_sha256 IS NOT NULL
                    AND length(NEW.sealed_evidence_vector_sha256) = 64 AND NEW.sealed_at IS NOT NULL)
            )
            BEGIN SELECT RAISE(ABORT, 'diagnostic evidence seal must be complete and acceptance-bound'); END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER omr_diag_seal_values_update_guard
            BEFORE UPDATE ON omr_diagnostic_runs
            WHEN NOT (
                (NEW.sealed_evidence_revision IS NULL AND NEW.sealed_evidence_vector_sha256 IS NULL AND NEW.sealed_at IS NULL)
                OR (NEW.test_kit_version_id IS NOT NULL AND NEW.sealed_evidence_revision IS NOT NULL
                    AND NEW.sealed_evidence_vector_sha256 IS NOT NULL
                    AND length(NEW.sealed_evidence_vector_sha256) = 64 AND NEW.sealed_at IS NOT NULL)
            )
            BEGIN SELECT RAISE(ABORT, 'diagnostic evidence seal must be complete and acceptance-bound'); END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER test_kit_diag_attempt_values_insert_guard
            BEFORE INSERT ON test_kit_diagnostic_page_attempts
            WHEN NEW.page_no NOT BETWEEN 1 AND 4
                OR length(trim(NEW.case_id)) = 0
                OR length(NEW.input_sha256) <> 64
                OR json_valid(NEW.item_bindings_json) = 0
                OR json_type(NEW.item_bindings_json) <> 'array'
                OR json_array_length(NEW.item_bindings_json) = 0
                OR NOT ((NEW.pending_set_id IS NULL AND NEW.pending_page_evidence_id IS NULL)
                    OR (NEW.pending_set_id IS NOT NULL AND NEW.pending_page_evidence_id IS NOT NULL))
            BEGIN SELECT RAISE(ABORT, 'diagnostic page attempt linkage is invalid'); END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER test_kit_diag_attempt_append_only_update_guard
            BEFORE UPDATE ON test_kit_diagnostic_page_attempts
            BEGIN SELECT RAISE(ABORT, 'diagnostic page attempts are append-only'); END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER test_kit_diag_attempt_append_only_delete_guard
            BEFORE DELETE ON test_kit_diagnostic_page_attempts
            BEGIN SELECT RAISE(ABORT, 'diagnostic page attempts are append-only'); END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER test_kit_diag_selection_insert_guard
            BEFORE INSERT ON test_kit_diagnostic_page_selections
            WHEN NEW.revision <> 1 OR NEW.page_no NOT BETWEEN 1 AND 4 OR length(trim(NEW.case_id)) = 0
            BEGIN SELECT RAISE(ABORT, 'diagnostic page selection must start at revision one'); END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER test_kit_diag_selection_update_guard
            BEFORE UPDATE ON test_kit_diagnostic_page_selections
            WHEN OLD.id IS NOT NEW.id
                OR OLD.diagnostic_run_id IS NOT NEW.diagnostic_run_id
                OR OLD.case_id IS NOT NEW.case_id
                OR OLD.page_no IS NOT NEW.page_no
                OR OLD.created_at IS NOT NEW.created_at
                OR OLD.selected_attempt_id IS NEW.selected_attempt_id
                OR NEW.revision <> OLD.revision + 1
            BEGIN SELECT RAISE(ABORT, 'diagnostic selection requires a new same-page attempt and next revision'); END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER test_kit_diag_selection_delete_guard
            BEFORE DELETE ON test_kit_diagnostic_page_selections
            BEGIN SELECT RAISE(ABORT, 'diagnostic page selections are retained'); END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER omr_diag_capture_after_seal_guard
            BEFORE INSERT ON omr_diagnostic_captures
            WHEN EXISTS (SELECT 1 FROM omr_diagnostic_runs WHERE id = NEW.diagnostic_run_id AND sealed_at IS NOT NULL)
            BEGIN SELECT RAISE(ABORT, 'sealed diagnostic evidence cannot receive captures'); END
        SQL);
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        $this->dropDatabaseGuards($driver);

        Schema::dropIfExists('test_kit_diagnostic_page_selections');
        Schema::dropIfExists('test_kit_diagnostic_page_attempts');

        Schema::table('omr_diagnostic_runs', function (Blueprint $table): void {
            $table->dropForeign('omr_diag_run_plan_identity_fk');
            $table->dropForeign('omr_diag_run_profile_content_fk');
            $table->dropForeign('omr_diag_run_test_kit_tuple_fk');
            $table->dropForeign('omr_diag_run_cycle_institution_fk');
            $table->dropForeign('omr_diag_run_test_kit_institution_fk');
            $table->dropUnique('omr_diag_run_acceptance_identity_unique');
            $table->dropIndex('omr_diag_run_test_kit_seal_idx');
            $table->dropColumn([
                'test_kit_id',
                'test_kit_version_id',
                'acceptance_plan_id',
                'booklet_set_id',
                'manifest_sha256',
                'test_instrument_version_id',
                'profile_content_sha256',
                'acceptance_protocol_schema_version',
                'acceptance_protocol_sha256',
                'acceptance_plan_schema_version',
                'acceptance_plan_sha256',
                'scoring_kernel_identity',
                'expected_worker_identity_json',
                'acceptance_plan_json',
                'sealed_evidence_revision',
                'sealed_evidence_vector_sha256',
                'sealed_at',
            ]);
        });

        Schema::dropIfExists('test_kit_acceptance_plans');

        Schema::table('omr_pending_capture_page_evidence', function (Blueprint $table): void {
            $table->dropUnique('omr_pending_page_set_number_unique');
        });
        Schema::table('omr_pending_capture_sets', function (Blueprint $table): void {
            $table->dropUnique('omr_pending_set_run_ref_unique');
        });
        Schema::table('omr_diagnostic_captures', function (Blueprint $table): void {
            $table->dropUnique('omr_diag_capture_run_ref_unique');
        });
        Schema::table('test_instrument_versions', function (Blueprint $table): void {
            $table->dropUnique('test_instrument_version_content_unique');
        });
        Schema::table('test_kit_versions', function (Blueprint $table): void {
            $table->dropUnique('test_kit_version_diagnostic_owner_tuple_unique');
            $table->dropUnique('test_kit_version_id_manifest_unique');
        });
        Schema::table('academic_cycles', function (Blueprint $table): void {
            $table->dropUnique('academic_cycle_institution_ref_unique');
        });
        Schema::table('test_kits', function (Blueprint $table): void {
            $table->dropUnique('test_kits_id_institution_ref_unique');
        });
    }

    private function dropDatabaseGuards(string $driver): void
    {
        if ($driver === 'pgsql') {
            foreach ([
                'omr_diag_capture_after_seal_guard ON omr_diagnostic_captures',
                'test_kit_diag_selection_revision_guard ON test_kit_diagnostic_page_selections',
                'test_kit_diag_attempt_append_only_guard ON test_kit_diagnostic_page_attempts',
                'omr_diag_acceptance_context_guard ON omr_diagnostic_runs',
                'test_kit_acceptance_plans_append_only_guard ON test_kit_acceptance_plans',
            ] as $trigger) {
                DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
            }

            foreach ([
                'nexiam_omr_diag_capture_after_seal_guard()',
                'nexiam_test_kit_diag_selection_guard()',
                'nexiam_test_kit_diag_attempt_append_only()',
                'nexiam_omr_diag_acceptance_context_guard()',
                'nexiam_test_kit_acceptance_plan_append_only()',
            ] as $function) {
                DB::statement("DROP FUNCTION IF EXISTS {$function}");
            }

            DB::statement('ALTER TABLE test_kit_diagnostic_page_selections DROP CONSTRAINT IF EXISTS test_kit_diag_selection_revision_check');
            DB::statement('ALTER TABLE test_kit_diagnostic_page_attempts DROP CONSTRAINT IF EXISTS test_kit_diag_attempt_page_values_check');
            DB::statement('ALTER TABLE omr_diagnostic_runs DROP CONSTRAINT IF EXISTS omr_diag_run_seal_check');
            DB::statement('ALTER TABLE omr_diagnostic_runs DROP CONSTRAINT IF EXISTS omr_diag_run_acceptance_context_check');
            DB::statement('ALTER TABLE test_kit_acceptance_plans DROP CONSTRAINT IF EXISTS test_kit_acceptance_plan_values_check');

            return;
        }

        foreach ([
            'omr_diag_capture_after_seal_guard',
            'test_kit_diag_selection_delete_guard',
            'test_kit_diag_selection_update_guard',
            'test_kit_diag_selection_insert_guard',
            'test_kit_diag_attempt_append_only_delete_guard',
            'test_kit_diag_attempt_append_only_update_guard',
            'test_kit_diag_attempt_values_insert_guard',
            'omr_diag_seal_values_update_guard',
            'omr_diag_seal_values_insert_guard',
            'omr_diag_acceptance_context_update_guard',
            'omr_diag_acceptance_context_insert_guard',
            'test_kit_acceptance_plan_values_insert_guard',
            'test_kit_acceptance_plans_append_only_delete_guard',
            'test_kit_acceptance_plans_append_only_update_guard',
        ] as $trigger) {
            DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
};
