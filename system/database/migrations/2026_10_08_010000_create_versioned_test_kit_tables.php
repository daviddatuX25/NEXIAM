<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_kits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->string('code', 64);
            $table->string('name', 255);
            $table->boolean('is_synthetic')->default(false);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['institution_id', 'code'], 'test_kits_institution_code_unique');
            $table->unique(['id', 'is_synthetic'], 'test_kits_id_synthetic_unique');
            $table->index(['institution_id', 'created_at'], 'test_kits_institution_created_idx');
        });

        $this->addMaterialReferenceKeys();

        Schema::create('test_kit_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('test_kit_id')->constrained('test_kits')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->unsignedBigInteger('predecessor_version_id')->nullable();
            $table->foreignId('template_version_id');
            $table->foreignId('booklet_version_id');
            $table->foreignId('booklet_set_id');
            $table->foreignId('test_instrument_version_id')->constrained('test_instrument_versions')->restrictOnDelete();
            $table->string('booklet_set_code', 1)->default('A');
            $table->char('booklet_set_order_hash', 64);
            $table->char('booklet_set_answer_key_hash', 64);
            $table->unsignedSmallInteger('manifest_schema_version');
            $table->string('canonicalizer_version', 64);
            $table->json('manifest_json');
            $table->char('manifest_sha256', 64);
            $table->string('status', 16)->default('candidate');
            $table->unsignedBigInteger('revision')->default(1);
            $table->unsignedBigInteger('selected_acceptance_receipt_id')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->foreignId('retired_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('revocation_reason', 1000)->nullable();
            $table->timestamps();

            $table->unique(['test_kit_id', 'version_number'], 'test_kit_versions_family_number_unique');
            $table->unique(['id', 'test_kit_id'], 'test_kit_versions_id_family_unique');
            $table->unique(
                ['id', 'template_version_id', 'booklet_version_id', 'booklet_set_code', 'manifest_sha256'],
                'test_kit_versions_receipt_pins_unique',
            );
            $table->index(['test_kit_id', 'status', 'version_number'], 'test_kit_versions_family_status_idx');
            $table->index(['template_version_id', 'booklet_version_id', 'booklet_set_id'], 'test_kit_versions_materials_idx');

            $table->foreign(['predecessor_version_id', 'test_kit_id'], 'test_kit_versions_predecessor_family_fk')
                ->references(['id', 'test_kit_id'])
                ->on('test_kit_versions')
                ->restrictOnDelete();
            $table->foreign(['template_version_id', 'test_instrument_version_id'], 'test_kit_versions_sheet_profile_fk')
                ->references(['id', 'test_instrument_version_id'])
                ->on('omr_answer_sheet_template_versions')
                ->restrictOnDelete();
            $table->foreign(['booklet_version_id', 'template_version_id', 'test_instrument_version_id'], 'test_kit_versions_booklet_material_fk')
                ->references(['id', 'template_version_id', 'test_instrument_version_id'])
                ->on('omr_question_booklet_versions')
                ->restrictOnDelete();
            $table->foreign(
                ['booklet_set_id', 'booklet_version_id', 'booklet_set_code', 'booklet_set_order_hash', 'booklet_set_answer_key_hash'],
                'test_kit_versions_set_material_fk',
            )
                ->references(['id', 'booklet_version_id', 'set_code', 'order_hash', 'answer_key_hash'])
                ->on('omr_booklet_sets')
                ->restrictOnDelete();
        });

        Schema::create('test_kit_acceptance_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('test_kit_version_id');
            $table->unsignedBigInteger('diagnostic_run_id');
            $table->foreignId('template_version_id');
            $table->foreignId('booklet_version_id');
            $table->string('booklet_set_code', 1);
            $table->unsignedBigInteger('run_revision');
            $table->json('evidence_revision_vector_json');
            $table->char('evidence_revision_vector_sha256', 64);
            $table->string('acceptance_protocol_schema_version', 64);
            $table->char('acceptance_protocol_sha256', 64);
            $table->string('acceptance_plan_schema_version', 64);
            $table->char('acceptance_plan_sha256', 64);
            $table->string('scoring_kernel_identity', 128);
            $table->string('worker_commit', 128);
            $table->char('manifest_sha256', 64);
            $table->string('result', 16);
            $table->json('check_report_json');
            $table->text('confidential_evidence_references');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['id', 'test_kit_version_id', 'manifest_sha256'], 'test_kit_receipts_id_version_manifest_unique');
            $table->unique(
                [
                    'diagnostic_run_id',
                    'run_revision',
                    'acceptance_protocol_schema_version',
                    'acceptance_protocol_sha256',
                    'acceptance_plan_schema_version',
                    'acceptance_plan_sha256',
                    'manifest_sha256',
                ],
                'test_kit_receipts_run_protocol_unique',
            );
            $table->index(['test_kit_version_id', 'created_at'], 'test_kit_receipts_version_created_idx');
            $table->index(['diagnostic_run_id', 'run_revision'], 'test_kit_receipts_run_revision_idx');

            $table->foreign(
                ['test_kit_version_id', 'template_version_id', 'booklet_version_id', 'booklet_set_code', 'manifest_sha256'],
                'test_kit_receipts_version_material_fk',
            )
                ->references(['id', 'template_version_id', 'booklet_version_id', 'booklet_set_code', 'manifest_sha256'])
                ->on('test_kit_versions')
                ->restrictOnDelete();
            $table->foreign(
                ['diagnostic_run_id', 'template_version_id', 'booklet_version_id', 'booklet_set_code'],
                'test_kit_receipts_run_material_fk',
            )
                ->references(['id', 'template_version_id', 'booklet_version_id', 'declared_set_code'])
                ->on('omr_diagnostic_runs')
                ->restrictOnDelete();
        });

        Schema::table('test_kit_versions', function (Blueprint $table): void {
            $table->foreign(
                ['selected_acceptance_receipt_id', 'id', 'manifest_sha256'],
                'test_kit_versions_selected_receipt_same_version_fk',
            )
                ->references(['id', 'test_kit_version_id', 'manifest_sha256'])
                ->on('test_kit_acceptance_receipts')
                ->restrictOnDelete();
        });

        DB::statement("CREATE UNIQUE INDEX test_kit_versions_one_active_per_family ON test_kit_versions (test_kit_id) WHERE status = 'active'");
        $this->addLifecycleChecksAndGuards();
    }

    private function addMaterialReferenceKeys(): void
    {
        Schema::table('omr_answer_sheet_template_versions', function (Blueprint $table): void {
            $table->unique(['id', 'test_instrument_version_id'], 'omr_sheet_version_profile_ref_unique');
        });

        Schema::table('omr_question_booklet_versions', function (Blueprint $table): void {
            $table->unique(
                ['id', 'template_version_id', 'test_instrument_version_id'],
                'omr_booklet_version_material_ref_unique',
            );
        });

        Schema::table('omr_booklet_sets', function (Blueprint $table): void {
            $table->unique(
                ['id', 'booklet_version_id', 'set_code', 'order_hash', 'answer_key_hash'],
                'omr_booklet_set_material_ref_unique',
            );
        });

        Schema::table('omr_diagnostic_runs', function (Blueprint $table): void {
            $table->unique(
                ['id', 'template_version_id', 'booklet_version_id', 'declared_set_code'],
                'omr_diagnostic_run_material_ref_unique',
            );
        });
    }

    private function addLifecycleChecksAndGuards(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE test_kit_versions ADD CONSTRAINT test_kit_versions_lifecycle_check CHECK (status IN ('candidate', 'active', 'retired') AND revision >= 1 AND booklet_set_code = 'A' AND predecessor_version_id IS DISTINCT FROM id AND ((activated_at IS NULL AND activated_by_user_id IS NULL) OR (activated_at IS NOT NULL AND activated_by_user_id IS NOT NULL)) AND ((retired_at IS NULL AND retired_by_user_id IS NULL) OR (retired_at IS NOT NULL AND retired_by_user_id IS NOT NULL)) AND ((revoked_at IS NULL AND revoked_by_user_id IS NULL AND revocation_reason IS NULL) OR (revoked_at IS NOT NULL AND revoked_by_user_id IS NOT NULL AND NULLIF(BTRIM(revocation_reason), '') IS NOT NULL)) AND (status <> 'active' OR (activated_at IS NOT NULL AND retired_at IS NULL)) AND (status <> 'retired' OR retired_at IS NOT NULL) AND (status <> 'candidate' OR (activated_at IS NULL AND retired_at IS NULL)))");

            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION nexiam_test_kit_family_immutable()
                RETURNS trigger
                LANGUAGE plpgsql
                AS $function$
                BEGIN
                    IF OLD.public_id IS DISTINCT FROM NEW.public_id
                        OR OLD.institution_id IS DISTINCT FROM NEW.institution_id
                        OR OLD.code IS DISTINCT FROM NEW.code
                        OR OLD.is_synthetic IS DISTINCT FROM NEW.is_synthetic
                        OR OLD.created_by_user_id IS DISTINCT FROM NEW.created_by_user_id
                        OR OLD.created_at IS DISTINCT FROM NEW.created_at THEN
                        RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'test kit family owner and provenance are immutable';
                    END IF;

                    RETURN NEW;
                END;
                $function$
            SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER test_kits_owner_provenance_immutable_guard
                BEFORE UPDATE ON test_kits
                FOR EACH ROW EXECUTE FUNCTION nexiam_test_kit_family_immutable()
            SQL);
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION nexiam_test_kit_version_material_immutable()
                RETURNS trigger
                LANGUAGE plpgsql
                AS $function$
                BEGIN
                    IF TG_OP = 'DELETE' THEN
                        RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'test kit versions are immutable history';
                    END IF;

                    IF OLD.test_kit_id IS DISTINCT FROM NEW.test_kit_id
                        OR OLD.version_number IS DISTINCT FROM NEW.version_number
                        OR OLD.predecessor_version_id IS DISTINCT FROM NEW.predecessor_version_id
                        OR OLD.template_version_id IS DISTINCT FROM NEW.template_version_id
                        OR OLD.booklet_version_id IS DISTINCT FROM NEW.booklet_version_id
                        OR OLD.booklet_set_id IS DISTINCT FROM NEW.booklet_set_id
                        OR OLD.test_instrument_version_id IS DISTINCT FROM NEW.test_instrument_version_id
                        OR OLD.booklet_set_code IS DISTINCT FROM NEW.booklet_set_code
                        OR OLD.booklet_set_order_hash IS DISTINCT FROM NEW.booklet_set_order_hash
                        OR OLD.booklet_set_answer_key_hash IS DISTINCT FROM NEW.booklet_set_answer_key_hash
                        OR OLD.manifest_schema_version IS DISTINCT FROM NEW.manifest_schema_version
                        OR OLD.canonicalizer_version IS DISTINCT FROM NEW.canonicalizer_version
                        OR OLD.manifest_json IS DISTINCT FROM NEW.manifest_json
                        OR OLD.manifest_sha256 IS DISTINCT FROM NEW.manifest_sha256
                        OR OLD.created_by_user_id IS DISTINCT FROM NEW.created_by_user_id
                        OR OLD.created_at IS DISTINCT FROM NEW.created_at THEN
                        RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'test kit version material is immutable';
                    END IF;

                    RETURN NEW;
                END;
                $function$
            SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER test_kit_versions_material_immutable_guard
                BEFORE UPDATE OR DELETE ON test_kit_versions
                FOR EACH ROW EXECUTE FUNCTION nexiam_test_kit_version_material_immutable()
            SQL);
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION nexiam_test_kit_receipt_append_only()
                RETURNS trigger
                LANGUAGE plpgsql
                AS $function$
                BEGIN
                    RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'test kit acceptance receipts are append-only';
                END;
                $function$
            SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER test_kit_acceptance_receipts_append_only_guard
                BEFORE UPDATE OR DELETE ON test_kit_acceptance_receipts
                FOR EACH ROW EXECUTE FUNCTION nexiam_test_kit_receipt_append_only()
            SQL);

            return;
        }

        if ($driver !== 'sqlite') {
            throw new RuntimeException('Versioned Test Kit persistence requires PostgreSQL or SQLite.');
        }

        DB::statement(<<<'SQL'
            CREATE TRIGGER test_kits_owner_provenance_immutable_guard
            BEFORE UPDATE ON test_kits
            FOR EACH ROW
            WHEN OLD.public_id IS NOT NEW.public_id
                OR OLD.institution_id IS NOT NEW.institution_id
                OR OLD.code IS NOT NEW.code
                OR OLD.is_synthetic IS NOT NEW.is_synthetic
                OR OLD.created_by_user_id IS NOT NEW.created_by_user_id
                OR OLD.created_at IS NOT NEW.created_at
            BEGIN
                SELECT RAISE(ABORT, 'test kit family owner and provenance are immutable');
            END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER test_kit_versions_lifecycle_insert_check
            BEFORE INSERT ON test_kit_versions
            FOR EACH ROW
            WHEN NEW.status NOT IN ('candidate', 'active', 'retired')
                OR NEW.revision < 1
                OR NEW.booklet_set_code <> 'A'
                OR NEW.predecessor_version_id = NEW.id
                OR NOT ((NEW.activated_at IS NULL AND NEW.activated_by_user_id IS NULL) OR (NEW.activated_at IS NOT NULL AND NEW.activated_by_user_id IS NOT NULL))
                OR NOT ((NEW.retired_at IS NULL AND NEW.retired_by_user_id IS NULL) OR (NEW.retired_at IS NOT NULL AND NEW.retired_by_user_id IS NOT NULL))
                OR NOT ((NEW.revoked_at IS NULL AND NEW.revoked_by_user_id IS NULL AND NEW.revocation_reason IS NULL) OR (NEW.revoked_at IS NOT NULL AND NEW.revoked_by_user_id IS NOT NULL AND length(trim(NEW.revocation_reason)) > 0))
                OR (NEW.status = 'active' AND (NEW.activated_at IS NULL OR NEW.retired_at IS NOT NULL))
                OR (NEW.status = 'retired' AND NEW.retired_at IS NULL)
                OR (NEW.status = 'candidate' AND (NEW.activated_at IS NOT NULL OR NEW.retired_at IS NOT NULL))
            BEGIN
                SELECT RAISE(ABORT, 'test kit version lifecycle data is invalid');
            END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER test_kit_versions_lifecycle_update_check
            BEFORE UPDATE ON test_kit_versions
            FOR EACH ROW
            WHEN NEW.status NOT IN ('candidate', 'active', 'retired')
                OR NEW.revision < 1
                OR NEW.booklet_set_code <> 'A'
                OR NEW.predecessor_version_id = NEW.id
                OR NOT ((NEW.activated_at IS NULL AND NEW.activated_by_user_id IS NULL) OR (NEW.activated_at IS NOT NULL AND NEW.activated_by_user_id IS NOT NULL))
                OR NOT ((NEW.retired_at IS NULL AND NEW.retired_by_user_id IS NULL) OR (NEW.retired_at IS NOT NULL AND NEW.retired_by_user_id IS NOT NULL))
                OR NOT ((NEW.revoked_at IS NULL AND NEW.revoked_by_user_id IS NULL AND NEW.revocation_reason IS NULL) OR (NEW.revoked_at IS NOT NULL AND NEW.revoked_by_user_id IS NOT NULL AND length(trim(NEW.revocation_reason)) > 0))
                OR (NEW.status = 'active' AND (NEW.activated_at IS NULL OR NEW.retired_at IS NOT NULL))
                OR (NEW.status = 'retired' AND NEW.retired_at IS NULL)
                OR (NEW.status = 'candidate' AND (NEW.activated_at IS NOT NULL OR NEW.retired_at IS NOT NULL))
            BEGIN
                SELECT RAISE(ABORT, 'test kit version lifecycle data is invalid');
            END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER test_kit_versions_material_immutable_guard
            BEFORE UPDATE ON test_kit_versions
            FOR EACH ROW
            WHEN OLD.test_kit_id IS NOT NEW.test_kit_id
                OR OLD.version_number IS NOT NEW.version_number
                OR OLD.predecessor_version_id IS NOT NEW.predecessor_version_id
                OR OLD.template_version_id IS NOT NEW.template_version_id
                OR OLD.booklet_version_id IS NOT NEW.booklet_version_id
                OR OLD.booklet_set_id IS NOT NEW.booklet_set_id
                OR OLD.test_instrument_version_id IS NOT NEW.test_instrument_version_id
                OR OLD.booklet_set_code IS NOT NEW.booklet_set_code
                OR OLD.booklet_set_order_hash IS NOT NEW.booklet_set_order_hash
                OR OLD.booklet_set_answer_key_hash IS NOT NEW.booklet_set_answer_key_hash
                OR OLD.manifest_schema_version IS NOT NEW.manifest_schema_version
                OR OLD.canonicalizer_version IS NOT NEW.canonicalizer_version
                OR OLD.manifest_json IS NOT NEW.manifest_json
                OR OLD.manifest_sha256 IS NOT NEW.manifest_sha256
                OR OLD.created_by_user_id IS NOT NEW.created_by_user_id
                OR OLD.created_at IS NOT NEW.created_at
            BEGIN
                SELECT RAISE(ABORT, 'test kit version material is immutable');
            END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER test_kit_versions_no_delete_guard
            BEFORE DELETE ON test_kit_versions
            FOR EACH ROW
            BEGIN
                SELECT RAISE(ABORT, 'test kit versions are immutable history');
            END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER test_kit_acceptance_receipts_no_update_guard
            BEFORE UPDATE ON test_kit_acceptance_receipts
            FOR EACH ROW
            BEGIN
                SELECT RAISE(ABORT, 'test kit acceptance receipts are append-only');
            END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER test_kit_acceptance_receipts_no_delete_guard
            BEFORE DELETE ON test_kit_acceptance_receipts
            FOR EACH ROW
            BEGIN
                SELECT RAISE(ABORT, 'test kit acceptance receipts are append-only');
            END
        SQL);
    }

    public function down(): void
    {
        $this->dropLifecycleGuards();

        Schema::table('test_kit_versions', function (Blueprint $table): void {
            $table->dropForeign('test_kit_versions_selected_receipt_same_version_fk');
        });

        Schema::dropIfExists('test_kit_acceptance_receipts');
        Schema::dropIfExists('test_kit_versions');
        Schema::dropIfExists('test_kits');

        Schema::table('omr_diagnostic_runs', function (Blueprint $table): void {
            $table->dropUnique('omr_diagnostic_run_material_ref_unique');
        });
        Schema::table('omr_booklet_sets', function (Blueprint $table): void {
            $table->dropUnique('omr_booklet_set_material_ref_unique');
        });
        Schema::table('omr_question_booklet_versions', function (Blueprint $table): void {
            $table->dropUnique('omr_booklet_version_material_ref_unique');
        });
        Schema::table('omr_answer_sheet_template_versions', function (Blueprint $table): void {
            $table->dropUnique('omr_sheet_version_profile_ref_unique');
        });
    }

    private function dropLifecycleGuards(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS test_kit_acceptance_receipts_append_only_guard ON test_kit_acceptance_receipts');
            DB::statement('DROP TRIGGER IF EXISTS test_kit_versions_material_immutable_guard ON test_kit_versions');
            DB::statement('DROP TRIGGER IF EXISTS test_kits_owner_provenance_immutable_guard ON test_kits');
            DB::statement('DROP FUNCTION IF EXISTS nexiam_test_kit_receipt_append_only()');
            DB::statement('DROP FUNCTION IF EXISTS nexiam_test_kit_version_material_immutable()');
            DB::statement('DROP FUNCTION IF EXISTS nexiam_test_kit_family_immutable()');

            return;
        }

        if ($driver === 'sqlite') {
            foreach ([
                'test_kits_owner_provenance_immutable_guard',
                'test_kit_versions_lifecycle_insert_check',
                'test_kit_versions_lifecycle_update_check',
                'test_kit_versions_material_immutable_guard',
                'test_kit_versions_no_delete_guard',
                'test_kit_acceptance_receipts_no_update_guard',
                'test_kit_acceptance_receipts_no_delete_guard',
            ] as $trigger) {
                DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
            }

            return;
        }

        throw new RuntimeException('Versioned Test Kit persistence requires PostgreSQL or SQLite.');
    }
};
