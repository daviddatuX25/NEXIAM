<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private array $sqlitePreservedTriggers = [];

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->captureSqliteTestKitTriggers();
            $this->removeInvalidSqliteActiveVersionIndex();
        }
        Schema::table('test_kits', function (Blueprint $table): void {
            $table->unique(['id', 'institution_id', 'is_synthetic'], 'test_kits_owner_provenance_tuple_unique');
        });

        Schema::table('omr_question_booklets', function (Blueprint $table): void {
            $table->unique(['id', 'institution_id', 'is_synthetic'], 'omr_booklet_owner_provenance_tuple_unique');
        });

        Schema::table('test_instruments', function (Blueprint $table): void {
            $table->unique(['id', 'institution_id', 'is_synthetic'], 'test_instrument_owner_provenance_tuple_unique');
        });

        Schema::table('omr_answer_sheet_template_versions', function (Blueprint $table): void {
            $table->unique(['id', 'template_id', 'test_instrument_version_id'], 'omr_sheet_version_owner_material_tuple_unique');
        });

        Schema::table('omr_question_booklet_versions', function (Blueprint $table): void {
            $table->unique(
                ['id', 'booklet_id', 'template_version_id', 'test_instrument_version_id'],
                'omr_booklet_version_owner_material_tuple_unique',
            );
        });

        Schema::table('test_instrument_versions', function (Blueprint $table): void {
            $table->unique(['id', 'test_instrument_id'], 'test_instrument_version_owner_tuple_unique');
        });

        Schema::table('test_kit_versions', function (Blueprint $table): void {
            $table->foreignId('institution_id')->nullable();
            $table->boolean('is_synthetic')->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedBigInteger('booklet_id')->nullable();
            $table->unsignedBigInteger('test_instrument_id')->nullable();
            $table->unique(
                ['id', 'test_kit_id', 'institution_id', 'is_synthetic', 'template_version_id', 'booklet_version_id', 'booklet_set_id', 'test_instrument_version_id', 'manifest_sha256'],
                'test_kit_version_complete_owner_tuple_unique',
            );
            $table->foreign(['test_kit_id', 'institution_id', 'is_synthetic'], 'test_kit_version_family_owner_fk')
                ->references(['id', 'institution_id', 'is_synthetic'])->on('test_kits')->restrictOnDelete();
            $table->foreign(['template_id', 'institution_id', 'is_synthetic'], 'test_kit_version_sheet_owner_fk')
                ->references(['id', 'institution_id', 'is_synthetic'])->on('omr_answer_sheet_templates')->restrictOnDelete();
            $table->foreign(['booklet_id', 'institution_id', 'is_synthetic'], 'test_kit_version_booklet_owner_fk')
                ->references(['id', 'institution_id', 'is_synthetic'])->on('omr_question_booklets')->restrictOnDelete();
            $table->foreign(['test_instrument_id', 'institution_id', 'is_synthetic'], 'test_kit_version_instrument_owner_fk')
                ->references(['id', 'institution_id', 'is_synthetic'])->on('test_instruments')->restrictOnDelete();
            $table->foreign(['template_version_id', 'template_id', 'test_instrument_version_id'], 'test_kit_version_sheet_exact_fk')
                ->references(['id', 'template_id', 'test_instrument_version_id'])->on('omr_answer_sheet_template_versions')->restrictOnDelete();
            $table->foreign(['booklet_version_id', 'booklet_id', 'template_version_id', 'test_instrument_version_id'], 'test_kit_version_booklet_exact_fk')
                ->references(['id', 'booklet_id', 'template_version_id', 'test_instrument_version_id'])->on('omr_question_booklet_versions')->restrictOnDelete();
            $table->foreign(['test_instrument_version_id', 'test_instrument_id'], 'test_kit_version_instrument_exact_fk')
                ->references(['id', 'test_instrument_id'])->on('test_instrument_versions')->restrictOnDelete();
        });

        Schema::table('omr_diagnostic_runs', function (Blueprint $table): void {
            $table->unique(
                ['id', 'test_kit_version_id', 'acceptance_plan_id', 'manifest_sha256', 'acceptance_protocol_schema_version', 'acceptance_protocol_sha256', 'acceptance_plan_schema_version', 'acceptance_plan_sha256'],
                'omr_diag_run_plan_receipt_identity_unique',
            );
            $table->unique(
                ['id', 'test_kit_version_id', 'manifest_sha256', 'template_version_id', 'booklet_version_id', 'declared_set_code'],
                'omr_diag_run_kit_material_receipt_tuple_unique',
            );
        });

        Schema::table('test_kit_acceptance_receipts', function (Blueprint $table): void {
            $table->foreignId('acceptance_plan_id')->nullable();
            $table->foreign(
                ['acceptance_plan_id', 'test_kit_version_id', 'manifest_sha256', 'acceptance_protocol_schema_version', 'acceptance_protocol_sha256', 'acceptance_plan_schema_version', 'acceptance_plan_sha256'],
                'test_kit_receipt_plan_identity_fk',
            )->references(['id', 'test_kit_version_id', 'manifest_sha256', 'protocol_schema_version', 'protocol_sha256', 'plan_schema_version', 'plan_sha256'])
                ->on('test_kit_acceptance_plans')->restrictOnDelete();
            $table->foreign(
                ['diagnostic_run_id', 'test_kit_version_id', 'acceptance_plan_id', 'manifest_sha256', 'acceptance_protocol_schema_version', 'acceptance_protocol_sha256', 'acceptance_plan_schema_version', 'acceptance_plan_sha256'],
                'test_kit_receipt_run_plan_identity_fk',
            )->references(['id', 'test_kit_version_id', 'acceptance_plan_id', 'manifest_sha256', 'acceptance_protocol_schema_version', 'acceptance_protocol_sha256', 'acceptance_plan_schema_version', 'acceptance_plan_sha256'])
                ->on('omr_diagnostic_runs')->restrictOnDelete();
            $table->foreign(
                ['diagnostic_run_id', 'test_kit_version_id', 'manifest_sha256', 'template_version_id', 'booklet_version_id', 'booklet_set_code'],
                'test_kit_receipt_run_exact_owner_fk',
            )->references(['id', 'test_kit_version_id', 'manifest_sha256', 'template_version_id', 'booklet_version_id', 'declared_set_code'])
                ->on('omr_diagnostic_runs')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'sqlite') {
            $this->replaceActiveVersionIndex();
            $this->restoreSqliteCapturedTriggers();
            $this->restoreSqliteOriginalVersionAndReceiptGuards();
        }

        $this->installReceiptPlanGuard();
        $this->installVersionOwnerGuards();
        $this->installManifestImmutabilityGuard();
    }

    private function captureSqliteTestKitTriggers(): void
    {
        foreach (DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'trigger' AND tbl_name IN ('test_kits', 'test_kit_versions', 'test_kit_acceptance_receipts') AND sql IS NOT NULL") as $trigger) {
            $this->sqlitePreservedTriggers[$trigger->name] = $trigger->sql;
        }
    }

    private function restoreSqliteCapturedTriggers(): void
    {
        foreach ($this->sqlitePreservedTriggers as $name => $sql) {
            $exists = DB::table('sqlite_master')
                ->where('type', 'trigger')
                ->where('name', $name)
                ->exists();

            if (! $exists) {
                DB::statement($sql);
            }
        }
    }

    private function removeInvalidSqliteActiveVersionIndex(): void
    {
        $singleFamilyIndexes = [];

        foreach (DB::select("PRAGMA index_list('test_kit_versions')") as $index) {
            if (! (bool) $index->unique) {
                continue;
            }

            $quotedIndex = '"'.str_replace('"', '""', $index->name).'"';
            $columns = array_map(
                static fn (object $column): string => $column->name,
                DB::select("PRAGMA index_info({$quotedIndex})"),
            );

            if ($columns === ['test_kit_id']) {
                $singleFamilyIndexes[] = $index;
            }
        }

        $tableUnique = collect($singleFamilyIndexes)
            ->contains(static fn (object $index): bool => $index->origin === 'u');

        if (! $tableUnique) {
            foreach ($singleFamilyIndexes as $index) {
                $quotedIndex = '"'.str_replace('"', '""', $index->name).'"';
                DB::statement("DROP INDEX {$quotedIndex}");
            }

            return;
        }

        $table = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'test_kit_versions'");
        if (! is_string($table?->sql ?? null)) {
            throw new RuntimeException('The Test Kit version table definition is unavailable for its SQLite index repair.');
        }

        $tableSql = preg_replace(
            '/,\\s*(?:CONSTRAINT\\s+"?[^\"]+"?\\s+)?UNIQUE\\s*\\(\\s*"?test_kit_id"?\\s*\\)/i',
            '',
            $table->sql,
            1,
            $removedUniqueConstraint,
        );
        if (! is_string($tableSql) || $removedUniqueConstraint !== 1) {
            throw new RuntimeException('The unconditional Test Kit family index has no removable SQLite table constraint.');
        }

        $tableSql = preg_replace(
            '/(?<![A-Za-z0-9_])"?test_kit_versions"?(?![A-Za-z0-9_])/i',
            '"test_kit_versions_rebuild"',
            $tableSql,
        );
        if (! is_string($tableSql)) {
            throw new RuntimeException('The Test Kit version table definition could not be rewritten for its SQLite index repair.');
        }

        $columns = array_map(
            static fn (object $column): string => '"'.str_replace('"', '""', $column->name).'"',
            DB::select("PRAGMA table_info('test_kit_versions')"),
        );
        $columnList = implode(', ', $columns);
        $indexes = DB::select("SELECT sql FROM sqlite_master WHERE type = 'index' AND tbl_name = 'test_kit_versions' AND sql IS NOT NULL");
        $triggers = DB::select("SELECT sql FROM sqlite_master WHERE type = 'trigger' AND tbl_name = 'test_kit_versions' AND sql IS NOT NULL");

        DB::transaction(function () use ($tableSql, $columnList, $indexes, $triggers): void {
            DB::statement('PRAGMA defer_foreign_keys = ON');
            DB::statement($tableSql);
            DB::statement("INSERT INTO test_kit_versions_rebuild ({$columnList}) SELECT {$columnList} FROM test_kit_versions");
            DB::statement('DROP TABLE test_kit_versions');
            DB::statement('ALTER TABLE test_kit_versions_rebuild RENAME TO test_kit_versions');

            foreach ($indexes as $index) {
                if (str_contains(strtolower($index->sql), 'unique')
                    && preg_match('/\\(\\s*"?test_kit_id"?\\s*\\)/i', $index->sql) === 1) {
                    continue;
                }

                DB::statement($index->sql);
            }

            foreach ($triggers as $trigger) {
                DB::statement($trigger->sql);
            }

            if (DB::select('PRAGMA foreign_key_check') !== []) {
                throw new RuntimeException('The Test Kit version SQLite index repair found foreign-key violations.');
            }
        });
    }

    private function replaceActiveVersionIndex(): void
    {
        DB::statement('DROP INDEX IF EXISTS test_kit_versions_one_active_per_family');
        DB::statement("CREATE UNIQUE INDEX test_kit_versions_one_active_per_family ON test_kit_versions (test_kit_id) WHERE status = 'active'");
    }

    private function restoreSqliteOriginalVersionAndReceiptGuards(): void
    {
        foreach ([
            <<<'SQL'
                CREATE TRIGGER IF NOT EXISTS test_kits_owner_provenance_immutable_guard
                BEFORE UPDATE ON test_kits
                FOR EACH ROW
                WHEN OLD.public_id IS NOT NEW.public_id
                    OR OLD.institution_id IS NOT NEW.institution_id
                    OR OLD.code IS NOT NEW.code
                    OR OLD.is_synthetic IS NOT NEW.is_synthetic
                    OR OLD.created_by_user_id IS NOT NEW.created_by_user_id
                    OR OLD.created_at IS NOT NEW.created_at
                BEGIN SELECT RAISE(ABORT, 'test kit family owner and provenance are immutable'); END
            SQL,
            <<<'SQL'
                CREATE TRIGGER IF NOT EXISTS test_kit_versions_lifecycle_insert_check
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
                BEGIN SELECT RAISE(ABORT, 'test kit version lifecycle data is invalid'); END
            SQL,
            <<<'SQL'
                CREATE TRIGGER IF NOT EXISTS test_kit_versions_lifecycle_update_check
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
                BEGIN SELECT RAISE(ABORT, 'test kit version lifecycle data is invalid'); END
            SQL,
            <<<'SQL'
                CREATE TRIGGER IF NOT EXISTS test_kit_versions_material_immutable_guard
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
                BEGIN SELECT RAISE(ABORT, 'test kit version material is immutable'); END
            SQL,
            <<<'SQL'
                CREATE TRIGGER IF NOT EXISTS test_kit_versions_no_delete_guard
                BEFORE DELETE ON test_kit_versions
                FOR EACH ROW
                BEGIN SELECT RAISE(ABORT, 'test kit versions are immutable history'); END
            SQL,
            <<<'SQL'
                CREATE TRIGGER IF NOT EXISTS test_kit_acceptance_receipts_no_update_guard
                BEFORE UPDATE ON test_kit_acceptance_receipts
                FOR EACH ROW
                BEGIN SELECT RAISE(ABORT, 'test kit acceptance receipts are append-only'); END
            SQL,
            <<<'SQL'
                CREATE TRIGGER IF NOT EXISTS test_kit_acceptance_receipts_no_delete_guard
                BEFORE DELETE ON test_kit_acceptance_receipts
                FOR EACH ROW
                BEGIN SELECT RAISE(ABORT, 'test kit acceptance receipts are append-only'); END
            SQL,
        ] as $trigger) {
            DB::statement($trigger);
        }
    }

    private function installManifestImmutabilityGuard(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->setPostgresVersionMaterialGuard(true);
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION nexiam_test_kit_manifest_immutable()
                RETURNS trigger LANGUAGE plpgsql AS $function$
                BEGIN
                    IF OLD.manifest_json::text IS DISTINCT FROM NEW.manifest_json::text
                        OR OLD.manifest_sha256 IS DISTINCT FROM NEW.manifest_sha256 THEN
                        RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'test kit manifest payload and hash are immutable';
                    END IF;

                    RETURN NEW;
                END;
                $function$
            SQL);
            DB::statement('CREATE TRIGGER test_kit_manifest_immutable_guard BEFORE UPDATE ON test_kit_versions FOR EACH ROW EXECUTE FUNCTION nexiam_test_kit_manifest_immutable()');

            return;
        }

        DB::statement(<<<'SQL'
            CREATE TRIGGER test_kit_manifest_immutable_guard
            BEFORE UPDATE OF manifest_json, manifest_sha256 ON test_kit_versions
            FOR EACH ROW
            WHEN OLD.manifest_json IS NOT NEW.manifest_json OR OLD.manifest_sha256 IS NOT NEW.manifest_sha256
            BEGIN
                SELECT RAISE(ABORT, 'test kit manifest payload and hash are immutable');
            END
        SQL);
    }

    private function setPostgresVersionMaterialGuard(bool $compareJsonAsText): void
    {
        $manifestComparison = $compareJsonAsText
            ? 'OLD.manifest_json::text IS DISTINCT FROM NEW.manifest_json::text'
            : 'OLD.manifest_json IS DISTINCT FROM NEW.manifest_json';

        DB::statement(<<<SQL
            CREATE OR REPLACE FUNCTION nexiam_test_kit_version_material_immutable()
            RETURNS trigger
            LANGUAGE plpgsql
            AS \$function\$
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
                    OR {$manifestComparison}
                    OR OLD.manifest_sha256 IS DISTINCT FROM NEW.manifest_sha256
                    OR OLD.created_by_user_id IS DISTINCT FROM NEW.created_by_user_id
                    OR OLD.created_at IS DISTINCT FROM NEW.created_at THEN
                    RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'test kit version material is immutable';
                END IF;

                RETURN NEW;
            END;
            \$function\$
        SQL);
    }

    private function installReceiptPlanGuard(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION nexiam_test_kit_receipt_plan_required()
                RETURNS trigger LANGUAGE plpgsql AS $function$
                BEGIN
                    IF NEW.acceptance_plan_id IS NULL THEN
                        RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'new acceptance receipts require their frozen plan identity';
                    END IF;

                    RETURN NEW;
                END;
                $function$
            SQL);
            DB::statement('CREATE TRIGGER test_kit_receipt_plan_required_guard BEFORE INSERT ON test_kit_acceptance_receipts FOR EACH ROW EXECUTE FUNCTION nexiam_test_kit_receipt_plan_required()');

            return;
        }

        DB::statement(<<<'SQL'
            CREATE TRIGGER test_kit_receipt_plan_required_guard
            BEFORE INSERT ON test_kit_acceptance_receipts
            FOR EACH ROW
            WHEN NEW.acceptance_plan_id IS NULL
            BEGIN
                SELECT RAISE(ABORT, 'new acceptance receipts require their frozen plan identity');
            END
        SQL);
    }

    private function installVersionOwnerGuards(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION nexiam_test_kit_version_owner_guard()
                RETURNS trigger LANGUAGE plpgsql AS $function$
                BEGIN
                    IF NEW.institution_id IS NULL OR NEW.is_synthetic IS NULL OR NEW.template_id IS NULL
                        OR NEW.booklet_id IS NULL OR NEW.test_instrument_id IS NULL THEN
                        RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'new test kit versions require classified owner provenance';
                    END IF;

                    IF TG_OP = 'UPDATE' AND (
                        OLD.institution_id IS NULL OR OLD.is_synthetic IS NULL OR OLD.template_id IS NULL
                        OR OLD.booklet_id IS NULL OR OLD.test_instrument_id IS NULL
                        OR OLD.institution_id IS DISTINCT FROM NEW.institution_id
                        OR OLD.is_synthetic IS DISTINCT FROM NEW.is_synthetic
                        OR OLD.template_id IS DISTINCT FROM NEW.template_id
                        OR OLD.booklet_id IS DISTINCT FROM NEW.booklet_id
                        OR OLD.test_instrument_id IS DISTINCT FROM NEW.test_instrument_id
                    ) THEN
                        RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'test kit version owner provenance is immutable';
                    END IF;

                    RETURN NEW;
                END;
                $function$
            SQL);
            DB::statement('CREATE TRIGGER test_kit_version_owner_guard BEFORE INSERT OR UPDATE ON test_kit_versions FOR EACH ROW EXECUTE FUNCTION nexiam_test_kit_version_owner_guard()');

            return;
        }

        if ($driver !== 'sqlite') {
            throw new RuntimeException('Test Kit owner tuple constraints require PostgreSQL or SQLite.');
        }

        DB::statement(<<<'SQL'
            CREATE TRIGGER test_kit_version_owner_insert_guard
            BEFORE INSERT ON test_kit_versions
            FOR EACH ROW
            WHEN NEW.institution_id IS NULL OR NEW.is_synthetic IS NULL OR NEW.template_id IS NULL
                OR NEW.booklet_id IS NULL OR NEW.test_instrument_id IS NULL
            BEGIN
                SELECT RAISE(ABORT, 'new test kit versions require classified owner provenance');
            END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER test_kit_version_owner_update_guard
            BEFORE UPDATE ON test_kit_versions
            FOR EACH ROW
            WHEN OLD.institution_id IS NULL OR OLD.is_synthetic IS NULL OR OLD.template_id IS NULL
                OR OLD.booklet_id IS NULL OR OLD.test_instrument_id IS NULL
                OR OLD.institution_id IS NOT NEW.institution_id
                OR OLD.is_synthetic IS NOT NEW.is_synthetic
                OR OLD.template_id IS NOT NEW.template_id
                OR OLD.booklet_id IS NOT NEW.booklet_id
                OR OLD.test_instrument_id IS NOT NEW.test_instrument_id
            BEGIN
                SELECT RAISE(ABORT, 'test kit version owner provenance is immutable');
            END
        SQL);
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS test_kit_manifest_immutable_guard ON test_kit_versions');
            DB::statement('DROP FUNCTION IF EXISTS nexiam_test_kit_manifest_immutable()');
            DB::statement('DROP TRIGGER IF EXISTS test_kit_version_owner_guard ON test_kit_versions');
            DB::statement('DROP FUNCTION IF EXISTS nexiam_test_kit_version_owner_guard()');
            DB::statement('DROP TRIGGER IF EXISTS test_kit_receipt_plan_required_guard ON test_kit_acceptance_receipts');
            DB::statement('DROP FUNCTION IF EXISTS nexiam_test_kit_receipt_plan_required()');
            $this->setPostgresVersionMaterialGuard(false);
        } elseif ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS test_kit_version_owner_insert_guard');
            DB::statement('DROP TRIGGER IF EXISTS test_kit_version_owner_update_guard');
            DB::statement('DROP TRIGGER IF EXISTS test_kit_receipt_plan_required_guard');
            DB::statement('DROP TRIGGER IF EXISTS test_kit_manifest_immutable_guard');
            $this->captureSqliteTestKitTriggers();
            $this->removeInvalidSqliteActiveVersionIndex();
        }

        Schema::table('test_kit_acceptance_receipts', function (Blueprint $table): void {
            $table->dropForeign('test_kit_receipt_run_exact_owner_fk');
            $table->dropForeign('test_kit_receipt_run_plan_identity_fk');
            $table->dropForeign('test_kit_receipt_plan_identity_fk');
            $table->dropColumn('acceptance_plan_id');
        });
        Schema::table('omr_diagnostic_runs', function (Blueprint $table): void {
            $table->dropUnique('omr_diag_run_kit_material_receipt_tuple_unique');
            $table->dropUnique('omr_diag_run_plan_receipt_identity_unique');
        });
        Schema::table('test_kit_versions', function (Blueprint $table): void {
            foreach ([
                'test_kit_version_instrument_exact_fk',
                'test_kit_version_booklet_exact_fk',
                'test_kit_version_sheet_exact_fk',
                'test_kit_version_instrument_owner_fk',
                'test_kit_version_booklet_owner_fk',
                'test_kit_version_sheet_owner_fk',
                'test_kit_version_family_owner_fk',
            ] as $foreign) {
                $table->dropForeign($foreign);
            }
            $table->dropUnique('test_kit_version_complete_owner_tuple_unique');
            $table->dropColumn(['institution_id', 'is_synthetic', 'template_id', 'booklet_id', 'test_instrument_id']);
        });
        Schema::table('test_instrument_versions', function (Blueprint $table): void {
            $table->dropUnique('test_instrument_version_owner_tuple_unique');
        });
        Schema::table('omr_question_booklet_versions', function (Blueprint $table): void {
            $table->dropUnique('omr_booklet_version_owner_material_tuple_unique');
        });
        Schema::table('omr_answer_sheet_template_versions', function (Blueprint $table): void {
            $table->dropUnique('omr_sheet_version_owner_material_tuple_unique');
        });
        Schema::table('test_instruments', function (Blueprint $table): void {
            $table->dropUnique('test_instrument_owner_provenance_tuple_unique');
        });
        Schema::table('omr_question_booklets', function (Blueprint $table): void {
            $table->dropUnique('omr_booklet_owner_provenance_tuple_unique');
        });
        Schema::table('test_kits', function (Blueprint $table): void {
            $table->dropUnique('test_kits_owner_provenance_tuple_unique');
        });
        if ($driver === 'sqlite') {
            $this->restoreSqliteCapturedTriggers();
            $this->restoreSqliteOriginalVersionAndReceiptGuards();
            $this->replaceActiveVersionIndex();
        }
    }
};
