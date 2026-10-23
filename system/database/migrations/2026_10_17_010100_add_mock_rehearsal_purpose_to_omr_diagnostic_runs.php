<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_diagnostic_runs', function (Blueprint $table): void {
            $table->string('diagnostic_purpose', 32)->default('material_diagnostic');
            $table->text('mock_manifest_json')->nullable();
            $table->string('mock_manifest_schema_version', 64)->nullable();
            $table->char('mock_manifest_sha256', 64)->nullable();
        });
        Schema::table('omr_pending_capture_sets', function (Blueprint $table): void {
            $table->string('mock_instance_key', 100)->nullable();
        });

        DB::table('omr_diagnostic_runs')->whereNotNull('acceptance_plan_id')->update(['diagnostic_purpose' => 'kit_acceptance']);
        DB::table('omr_diagnostic_runs')->whereNull('acceptance_plan_id')->update(['diagnostic_purpose' => 'material_diagnostic']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE omr_diagnostic_runs DROP CONSTRAINT IF EXISTS omr_diag_run_acceptance_context_check');
            DB::statement(<<<'SQL'
                ALTER TABLE omr_diagnostic_runs ADD CONSTRAINT omr_diag_run_purpose_context_check CHECK (
                    diagnostic_purpose IN ('material_diagnostic', 'kit_acceptance', 'mock_rehearsal')
                    AND (
                        (diagnostic_purpose = 'material_diagnostic'
                            AND test_kit_id IS NULL AND test_kit_version_id IS NULL AND acceptance_plan_id IS NULL
                            AND booklet_set_id IS NULL AND manifest_sha256 IS NULL AND test_instrument_version_id IS NULL
                            AND profile_content_sha256 IS NULL AND acceptance_protocol_schema_version IS NULL
                            AND acceptance_protocol_sha256 IS NULL AND acceptance_plan_schema_version IS NULL
                            AND acceptance_plan_sha256 IS NULL AND scoring_kernel_identity IS NULL
                            AND expected_worker_identity_json IS NULL AND acceptance_plan_json IS NULL
                            AND mock_manifest_json IS NULL AND mock_manifest_schema_version IS NULL AND mock_manifest_sha256 IS NULL)
                        OR
                        (diagnostic_purpose = 'kit_acceptance'
                            AND test_kit_id IS NOT NULL AND test_kit_version_id IS NOT NULL AND acceptance_plan_id IS NOT NULL
                            AND booklet_set_id IS NOT NULL AND manifest_sha256 IS NOT NULL AND manifest_sha256 ~ '^[a-f0-9]{64}$'
                            AND test_instrument_version_id IS NOT NULL AND profile_content_sha256 IS NOT NULL AND profile_content_sha256 ~ '^[a-f0-9]{64}$'
                            AND acceptance_protocol_schema_version IS NOT NULL AND length(trim(acceptance_protocol_schema_version)) > 0
                            AND acceptance_protocol_sha256 IS NOT NULL AND acceptance_protocol_sha256 ~ '^[a-f0-9]{64}$'
                            AND acceptance_plan_schema_version IS NOT NULL AND length(trim(acceptance_plan_schema_version)) > 0
                            AND acceptance_plan_sha256 IS NOT NULL AND acceptance_plan_sha256 ~ '^[a-f0-9]{64}$'
                            AND scoring_kernel_identity IS NOT NULL AND length(trim(scoring_kernel_identity)) > 0
                            AND expected_worker_identity_json IS NOT NULL AND jsonb_typeof(expected_worker_identity_json::jsonb) = 'object'
                            AND acceptance_plan_json IS NOT NULL AND length(trim(acceptance_plan_json)) > 0
                            AND left(ltrim(acceptance_plan_json), 1) NOT IN ('{', '[') AND declared_set_code = 'A'
                            AND mock_manifest_json IS NULL AND mock_manifest_schema_version IS NULL AND mock_manifest_sha256 IS NULL)
                        OR
                        (diagnostic_purpose = 'mock_rehearsal'
                            AND test_kit_id IS NOT NULL AND test_kit_version_id IS NOT NULL AND booklet_set_id IS NOT NULL
                            AND manifest_sha256 IS NOT NULL AND manifest_sha256 ~ '^[a-f0-9]{64}$' AND test_instrument_version_id IS NOT NULL
                            AND profile_content_sha256 IS NOT NULL AND profile_content_sha256 ~ '^[a-f0-9]{64}$' AND scoring_kernel_identity IS NOT NULL
                            AND length(trim(scoring_kernel_identity)) > 0 AND expected_worker_identity_json IS NOT NULL
                            AND jsonb_typeof(expected_worker_identity_json::jsonb) = 'object'
                            AND acceptance_plan_id IS NULL AND acceptance_protocol_schema_version IS NULL
                            AND acceptance_protocol_sha256 IS NULL AND acceptance_plan_schema_version IS NULL
                            AND acceptance_plan_sha256 IS NULL AND acceptance_plan_json IS NULL
                            AND mock_manifest_json IS NOT NULL AND length(trim(mock_manifest_json)) > 0
                            AND mock_manifest_schema_version IS NOT NULL AND length(trim(mock_manifest_schema_version)) > 0
                            AND mock_manifest_sha256 IS NOT NULL AND mock_manifest_sha256 ~ '^[a-f0-9]{64}$'
                            AND sealed_evidence_revision IS NULL AND sealed_evidence_vector_sha256 IS NULL AND sealed_at IS NULL)
                    )
                )
            SQL);
            DB::statement('ALTER TABLE omr_diagnostic_runs DROP CONSTRAINT IF EXISTS omr_diag_run_seal_check');
            DB::statement("ALTER TABLE omr_diagnostic_runs ADD CONSTRAINT omr_diag_run_seal_check CHECK ((sealed_evidence_revision IS NULL AND sealed_evidence_vector_sha256 IS NULL AND sealed_at IS NULL) OR (diagnostic_purpose = 'kit_acceptance' AND sealed_evidence_revision IS NOT NULL AND sealed_evidence_vector_sha256 IS NOT NULL AND sealed_evidence_vector_sha256 ~ '^[a-f0-9]{64}$' AND sealed_at IS NOT NULL))");
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS omr_diag_acceptance_context_insert_guard');
            DB::statement('DROP TRIGGER IF EXISTS omr_diag_acceptance_context_update_guard');
            $valid = "(NEW.diagnostic_purpose = 'material_diagnostic' AND NEW.test_kit_id IS NULL AND NEW.test_kit_version_id IS NULL AND NEW.acceptance_plan_id IS NULL AND NEW.booklet_set_id IS NULL AND NEW.manifest_sha256 IS NULL AND NEW.test_instrument_version_id IS NULL AND NEW.profile_content_sha256 IS NULL AND NEW.acceptance_protocol_schema_version IS NULL AND NEW.acceptance_protocol_sha256 IS NULL AND NEW.acceptance_plan_schema_version IS NULL AND NEW.acceptance_plan_sha256 IS NULL AND NEW.scoring_kernel_identity IS NULL AND NEW.expected_worker_identity_json IS NULL AND NEW.acceptance_plan_json IS NULL AND NEW.mock_manifest_json IS NULL AND NEW.mock_manifest_schema_version IS NULL AND NEW.mock_manifest_sha256 IS NULL) OR (NEW.diagnostic_purpose = 'kit_acceptance' AND NEW.test_kit_id IS NOT NULL AND NEW.test_kit_version_id IS NOT NULL AND NEW.acceptance_plan_id IS NOT NULL AND NEW.booklet_set_id IS NOT NULL AND length(NEW.manifest_sha256) = 64 AND NEW.test_instrument_version_id IS NOT NULL AND length(NEW.profile_content_sha256) = 64 AND NEW.acceptance_protocol_schema_version IS NOT NULL AND length(trim(NEW.acceptance_protocol_schema_version)) > 0 AND length(NEW.acceptance_protocol_sha256) = 64 AND NEW.acceptance_plan_schema_version IS NOT NULL AND length(trim(NEW.acceptance_plan_schema_version)) > 0 AND length(NEW.acceptance_plan_sha256) = 64 AND NEW.scoring_kernel_identity IS NOT NULL AND length(trim(NEW.scoring_kernel_identity)) > 0 AND json_valid(NEW.expected_worker_identity_json) = 1 AND json_type(NEW.expected_worker_identity_json) = 'object' AND NEW.acceptance_plan_json IS NOT NULL AND length(trim(NEW.acceptance_plan_json)) > 0 AND substr(ltrim(NEW.acceptance_plan_json), 1, 1) NOT IN ('{', '[') AND NEW.declared_set_code = 'A' AND NEW.mock_manifest_json IS NULL AND NEW.mock_manifest_schema_version IS NULL AND NEW.mock_manifest_sha256 IS NULL) OR (NEW.diagnostic_purpose = 'mock_rehearsal' AND NEW.test_kit_id IS NOT NULL AND NEW.test_kit_version_id IS NOT NULL AND NEW.booklet_set_id IS NOT NULL AND length(NEW.manifest_sha256) = 64 AND NEW.test_instrument_version_id IS NOT NULL AND length(NEW.profile_content_sha256) = 64 AND NEW.scoring_kernel_identity IS NOT NULL AND length(trim(NEW.scoring_kernel_identity)) > 0 AND json_valid(NEW.expected_worker_identity_json) = 1 AND json_type(NEW.expected_worker_identity_json) = 'object' AND NEW.acceptance_plan_id IS NULL AND NEW.acceptance_protocol_schema_version IS NULL AND NEW.acceptance_protocol_sha256 IS NULL AND NEW.acceptance_plan_schema_version IS NULL AND NEW.acceptance_plan_sha256 IS NULL AND NEW.acceptance_plan_json IS NULL AND NEW.mock_manifest_json IS NOT NULL AND NEW.mock_manifest_schema_version IS NOT NULL AND length(trim(NEW.mock_manifest_schema_version)) > 0 AND length(NEW.mock_manifest_sha256) = 64 AND NEW.sealed_evidence_revision IS NULL AND NEW.sealed_evidence_vector_sha256 IS NULL AND NEW.sealed_at IS NULL)";
            DB::statement("CREATE TRIGGER omr_diag_purpose_context_insert_guard BEFORE INSERT ON omr_diagnostic_runs WHEN NOT (COALESCE(($valid), 0)) BEGIN SELECT RAISE(ABORT, 'diagnostic purpose context invalid'); END");
            $immutable = 'OLD.diagnostic_purpose IS NOT NEW.diagnostic_purpose OR OLD.mock_manifest_json IS NOT NEW.mock_manifest_json OR OLD.mock_manifest_schema_version IS NOT NEW.mock_manifest_schema_version OR OLD.mock_manifest_sha256 IS NOT NEW.mock_manifest_sha256 OR OLD.test_kit_id IS NOT NEW.test_kit_id OR OLD.test_kit_version_id IS NOT NEW.test_kit_version_id OR OLD.acceptance_plan_id IS NOT NEW.acceptance_plan_id OR OLD.booklet_set_id IS NOT NEW.booklet_set_id OR OLD.manifest_sha256 IS NOT NEW.manifest_sha256 OR OLD.test_instrument_version_id IS NOT NEW.test_instrument_version_id OR OLD.profile_content_sha256 IS NOT NEW.profile_content_sha256 OR OLD.acceptance_protocol_schema_version IS NOT NEW.acceptance_protocol_schema_version OR OLD.acceptance_protocol_sha256 IS NOT NEW.acceptance_protocol_sha256 OR OLD.acceptance_plan_schema_version IS NOT NEW.acceptance_plan_schema_version OR OLD.acceptance_plan_sha256 IS NOT NEW.acceptance_plan_sha256 OR OLD.scoring_kernel_identity IS NOT NEW.scoring_kernel_identity OR OLD.expected_worker_identity_json IS NOT NEW.expected_worker_identity_json OR OLD.acceptance_plan_json IS NOT NEW.acceptance_plan_json';
            DB::statement("CREATE TRIGGER omr_diag_purpose_context_update_guard BEFORE UPDATE ON omr_diagnostic_runs WHEN $immutable BEGIN SELECT RAISE(ABORT, 'diagnostic purpose context is immutable'); END");
            DB::statement("CREATE TRIGGER omr_diag_purpose_context_values_update_guard BEFORE UPDATE ON omr_diagnostic_runs WHEN NOT (COALESCE(($valid), 0)) BEGIN SELECT RAISE(ABORT, 'diagnostic purpose context invalid'); END");
            DB::statement("CREATE TRIGGER omr_diag_mock_manifest_insert_guard BEFORE INSERT ON omr_diagnostic_runs WHEN NEW.diagnostic_purpose = 'mock_rehearsal' AND length(trim(COALESCE(NEW.mock_manifest_json, ''))) = 0 BEGIN SELECT RAISE(ABORT, 'diagnostic mock manifest required'); END");
            DB::statement("CREATE TRIGGER omr_diag_mock_manifest_update_guard BEFORE UPDATE ON omr_diagnostic_runs WHEN NEW.diagnostic_purpose = 'mock_rehearsal' AND length(trim(COALESCE(NEW.mock_manifest_json, ''))) = 0 BEGIN SELECT RAISE(ABORT, 'diagnostic mock manifest required'); END");
            DB::statement('DROP TRIGGER IF EXISTS omr_diag_seal_values_insert_guard');
            DB::statement('DROP TRIGGER IF EXISTS omr_diag_seal_values_update_guard');
            $seal = "(NEW.sealed_evidence_revision IS NULL AND NEW.sealed_evidence_vector_sha256 IS NULL AND NEW.sealed_at IS NULL) OR (NEW.diagnostic_purpose = 'kit_acceptance' AND NEW.sealed_evidence_revision IS NOT NULL AND NEW.sealed_evidence_vector_sha256 IS NOT NULL AND length(NEW.sealed_evidence_vector_sha256) = 64 AND NEW.sealed_at IS NOT NULL)";
            DB::statement("CREATE TRIGGER omr_diag_seal_values_insert_guard BEFORE INSERT ON omr_diagnostic_runs WHEN NOT (COALESCE(($seal), 0)) BEGIN SELECT RAISE(ABORT, 'diagnostic evidence seal must be complete and acceptance-bound'); END");
            DB::statement("CREATE TRIGGER omr_diag_seal_values_update_guard BEFORE UPDATE ON omr_diagnostic_runs WHEN NOT (COALESCE(($seal), 0)) BEGIN SELECT RAISE(ABORT, 'diagnostic evidence seal must be complete and acceptance-bound'); END");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('omr_mock_rehearsal_purpose_migration_is_irreversible');
    }
};
