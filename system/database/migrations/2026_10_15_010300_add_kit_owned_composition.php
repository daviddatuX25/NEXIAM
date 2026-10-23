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
            $this->captureSqliteTriggers();
        }

        Schema::table('omr_question_booklet_versions', function (Blueprint $table): void {
            $table->unique(['id', 'booklet_id'], 'omr_booklet_version_id_owner_unique');
        });

        Schema::table('test_instrument_items', function (Blueprint $table): void {
            $table->unique(
                ['id', 'test_instrument_version_id', 'test_instrument_subtest_id'],
                'instrument_item_exact_map_tuple_unique',
            );
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE test_kit_preparations ADD COLUMN composition_revision INTEGER NOT NULL DEFAULT 1');
            DB::statement('ALTER TABLE test_kit_preparations ADD COLUMN omr_enabled BOOLEAN NOT NULL DEFAULT 0');
            DB::statement('CREATE UNIQUE INDEX kit_preparation_revision_tuple_unique ON test_kit_preparations (id, revision)');
            DB::statement('ALTER TABLE test_kit_versions ADD COLUMN source_preparation_id INTEGER NULL REFERENCES test_kit_preparations(id) ON DELETE RESTRICT');
            DB::statement('ALTER TABLE test_kit_versions ADD COLUMN source_preparation_revision INTEGER NULL');
            DB::statement('ALTER TABLE test_kit_versions ADD COLUMN composition_revision INTEGER NULL');
            DB::statement('ALTER TABLE test_kit_versions ADD COLUMN composition_sha256 VARCHAR(64) NULL');
            DB::statement('ALTER TABLE test_kit_versions ADD COLUMN composition_rows_sealed_at DATETIME NULL');
            DB::statement('CREATE UNIQUE INDEX kit_version_composition_vector_unique ON test_kit_versions (id, template_version_id, booklet_version_id, booklet_set_id, test_instrument_version_id, composition_revision, composition_sha256)');
        } else {
            Schema::table('test_kit_preparations', function (Blueprint $table): void {
                $table->unsignedInteger('composition_revision')->default(1);
                $table->boolean('omr_enabled')->default(false);
                $table->unique(['id', 'revision'], 'kit_preparation_revision_tuple_unique');
            });

            Schema::table('test_kit_versions', function (Blueprint $table): void {
                $table->unsignedBigInteger('source_preparation_id')->nullable();
                $table->unsignedInteger('source_preparation_revision')->nullable();
                $table->unsignedInteger('composition_revision')->nullable();
                $table->char('composition_sha256', 64)->nullable();
                $table->timestamp('composition_rows_sealed_at')->nullable();
                $table->unique(
                    [
                        'id', 'template_version_id', 'booklet_version_id', 'booklet_set_id',
                        'test_instrument_version_id', 'composition_revision', 'composition_sha256',
                    ],
                    'kit_version_composition_vector_unique',
                );
                $table->foreign('source_preparation_id', 'kit_version_source_preparation_fk')
                    ->references('id')->on('test_kit_preparations')->restrictOnDelete();
                $table->foreign(['source_preparation_id', 'source_preparation_revision'], 'kit_version_source_preparation_revision_fk')
                    ->references(['id', 'revision'])->on('test_kit_preparations')->restrictOnDelete();

                $table->dropForeign('test_kit_versions_sheet_profile_fk');
                $table->dropForeign('test_kit_versions_booklet_material_fk');
                $table->dropForeign('test_kit_version_sheet_exact_fk');
                $table->dropForeign('test_kit_version_booklet_exact_fk');

                $table->foreign(['template_version_id', 'template_id'], 'test_kit_version_sheet_exact_fk')
                    ->references(['id', 'template_id'])->on('omr_answer_sheet_template_versions')->restrictOnDelete();
                $table->foreign(['booklet_version_id', 'booklet_id'], 'test_kit_version_booklet_exact_fk')
                    ->references(['id', 'booklet_id'])->on('omr_question_booklet_versions')->restrictOnDelete();
            });
        }

        $this->createPreparationCompositionRevisions();
        $this->createPreparationCompositionRows();
        $this->createSealedCompositionRows();
        $this->installDatabaseGuards();

        if (DB::getDriverName() === 'sqlite') {
            $this->restoreSqliteTriggers();
        }
    }

    private function createPreparationCompositionRevisions(): void
    {
        Schema::create('test_kit_preparation_composition_revisions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('test_kit_preparation_id');
            $table->unsignedBigInteger('test_kit_id');
            $table->unsignedInteger('composition_revision');
            $table->unsignedBigInteger('template_version_id');
            $table->unsignedBigInteger('booklet_version_id');
            $table->unsignedBigInteger('booklet_set_id');
            $table->unsignedBigInteger('test_instrument_version_id');
            $table->json('component_vector_json');
            $table->char('component_vector_sha256', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['test_kit_preparation_id', 'composition_revision'], 'kit_prep_comp_revision_unique');
            $table->unique(
                [
                    'id', 'test_kit_preparation_id', 'test_kit_id', 'composition_revision',
                    'template_version_id', 'booklet_version_id', 'booklet_set_id',
                    'test_instrument_version_id', 'component_vector_sha256',
                ],
                'kit_prep_comp_revision_vector_unique',
            );
            $table->foreign(['test_kit_preparation_id', 'test_kit_id'], 'kit_prep_comp_revision_family_fk')
                ->references(['id', 'test_kit_id'])->on('test_kit_preparations')->restrictOnDelete();
            $table->foreign('template_version_id', 'kit_prep_comp_sheet_fk')
                ->references('id')->on('omr_answer_sheet_template_versions')->restrictOnDelete();
            $table->foreign('booklet_version_id', 'kit_prep_comp_booklet_fk')
                ->references('id')->on('omr_question_booklet_versions')->restrictOnDelete();
            $table->foreign(['booklet_set_id', 'booklet_version_id'], 'kit_prep_comp_set_version_fk')
                ->references(['id', 'booklet_version_id'])->on('omr_booklet_sets')->restrictOnDelete();
            $table->foreign('test_instrument_version_id', 'kit_prep_comp_profile_fk')
                ->references('id')->on('test_instrument_versions')->restrictOnDelete();
        });
    }

    private function createPreparationCompositionRows(): void
    {
        Schema::create('test_kit_preparation_composition_rows', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('test_kit_preparation_id');
            $table->unsignedBigInteger('test_kit_id');
            $table->unsignedBigInteger('composition_revision_id');
            $table->unsignedInteger('composition_revision');
            $table->unsignedBigInteger('template_version_id');
            $table->unsignedBigInteger('booklet_version_id');
            $table->unsignedBigInteger('booklet_set_id');
            $table->unsignedBigInteger('test_instrument_version_id');
            $table->char('component_vector_sha256', 64);
            $table->string('disposition', 32);
            $table->string('physical_item_uid', 36)->nullable();
            $table->unsignedBigInteger('sheet_item_id')->nullable();
            $table->unsignedBigInteger('booklet_set_item_id')->nullable();
            $table->unsignedBigInteger('booklet_item_id')->nullable();
            $table->uuid('booklet_item_uid')->nullable();
            $table->uuid('booklet_section_uid')->nullable();
            $table->unsignedBigInteger('test_instrument_subtest_id');
            $table->unsignedBigInteger('test_instrument_item_id');
            $table->timestamps();

            $table->unique(
                ['test_kit_preparation_id', 'composition_revision', 'physical_item_uid'],
                'kit_prep_map_physical_unique',
            );
            $table->unique(
                ['test_kit_preparation_id', 'composition_revision', 'booklet_set_item_id'],
                'kit_prep_map_set_item_unique',
            );
            $table->unique(
                ['test_kit_preparation_id', 'composition_revision', 'test_instrument_item_id'],
                'kit_prep_map_formal_item_unique',
            );
            $table->index(['test_kit_preparation_id', 'composition_revision'], 'kit_prep_map_revision_idx');
            $table->foreign(['test_kit_preparation_id', 'test_kit_id'], 'kit_prep_map_family_fk')
                ->references(['id', 'test_kit_id'])->on('test_kit_preparations')->restrictOnDelete();
            $table->foreign(
                [
                    'composition_revision_id', 'test_kit_preparation_id', 'test_kit_id', 'composition_revision',
                    'template_version_id', 'booklet_version_id', 'booklet_set_id', 'test_instrument_version_id',
                    'component_vector_sha256',
                ],
                'kit_prep_map_vector_fk',
            )->references([
                'id', 'test_kit_preparation_id', 'test_kit_id', 'composition_revision',
                'template_version_id', 'booklet_version_id', 'booklet_set_id',
                'test_instrument_version_id', 'component_vector_sha256',
            ])->on('test_kit_preparation_composition_revisions')->restrictOnDelete();
            $table->foreign(['sheet_item_id', 'template_version_id', 'physical_item_uid'], 'kit_prep_map_physical_fk')
                ->references(['id', 'template_version_id', 'physical_item_uid'])
                ->on('omr_template_questions')->restrictOnDelete();
            $table->foreign(['booklet_set_id', 'booklet_version_id'], 'kit_prep_map_set_version_fk')
                ->references(['id', 'booklet_version_id'])->on('omr_booklet_sets')->restrictOnDelete();
            $table->foreign(['booklet_item_id', 'booklet_version_id', 'booklet_item_uid', 'booklet_section_uid'], 'kit_prep_map_booklet_item_fk')
                ->references(['id', 'booklet_version_id', 'booklet_item_uid', 'booklet_section_uid'])->on('omr_booklet_items')->restrictOnDelete();
            $table->foreign([
                'booklet_set_item_id', 'booklet_set_id', 'booklet_version_id', 'booklet_item_id',
                'booklet_item_uid', 'booklet_section_uid',
            ], 'kit_prep_map_set_occurrence_fk')->references([
                'id', 'booklet_set_id', 'booklet_version_id', 'booklet_item_id', 'booklet_item_uid', 'booklet_section_uid',
            ])->on('omr_booklet_set_items')->restrictOnDelete();
            $table->foreign(
                ['test_instrument_item_id', 'test_instrument_version_id', 'test_instrument_subtest_id'],
                'kit_prep_map_formal_item_fk',
            )->references(['id', 'test_instrument_version_id', 'test_instrument_subtest_id'])
                ->on('test_instrument_items')->restrictOnDelete();
            $table->foreign(['test_instrument_version_id', 'test_instrument_subtest_id'], 'kit_prep_map_subtest_fk')
                ->references(['test_instrument_version_id', 'id'])->on('test_instrument_subtests')->restrictOnDelete();
        });
    }

    private function createSealedCompositionRows(): void
    {
        Schema::create('test_kit_version_composition_rows', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('test_kit_version_id');
            $table->unsignedBigInteger('template_version_id');
            $table->unsignedBigInteger('booklet_version_id');
            $table->unsignedBigInteger('booklet_set_id');
            $table->unsignedBigInteger('test_instrument_version_id');
            $table->unsignedInteger('composition_revision');
            $table->char('composition_sha256', 64);
            $table->string('disposition', 32);
            $table->string('physical_item_uid', 36)->nullable();
            $table->unsignedBigInteger('sheet_item_id')->nullable();
            $table->unsignedBigInteger('booklet_set_item_id')->nullable();
            $table->unsignedBigInteger('booklet_item_id')->nullable();
            $table->uuid('booklet_item_uid')->nullable();
            $table->uuid('booklet_section_uid')->nullable();
            $table->unsignedBigInteger('test_instrument_subtest_id');
            $table->unsignedBigInteger('test_instrument_item_id');
            $table->timestamps();

            $table->unique(['test_kit_version_id', 'physical_item_uid'], 'kit_version_map_physical_unique');
            $table->unique(['test_kit_version_id', 'booklet_set_item_id'], 'kit_version_map_set_item_unique');
            $table->unique(['test_kit_version_id', 'test_instrument_item_id'], 'kit_version_map_formal_item_unique');
            $table->index(['test_kit_version_id', 'template_version_id'], 'kit_version_map_template_idx');
            $table->foreign(
                [
                    'test_kit_version_id', 'template_version_id', 'booklet_version_id', 'booklet_set_id',
                    'test_instrument_version_id', 'composition_revision', 'composition_sha256',
                ],
                'kit_version_map_vector_fk',
            )->references([
                'id', 'template_version_id', 'booklet_version_id', 'booklet_set_id',
                'test_instrument_version_id', 'composition_revision', 'composition_sha256',
            ])->on('test_kit_versions')->restrictOnDelete();
            $table->foreign(['sheet_item_id', 'template_version_id', 'physical_item_uid'], 'kit_version_map_physical_fk')
                ->references(['id', 'template_version_id', 'physical_item_uid'])
                ->on('omr_template_questions')->restrictOnDelete();
            $table->foreign(['booklet_set_id', 'booklet_version_id'], 'kit_version_map_set_version_fk')
                ->references(['id', 'booklet_version_id'])->on('omr_booklet_sets')->restrictOnDelete();
            $table->foreign(['booklet_item_id', 'booklet_version_id', 'booklet_item_uid', 'booklet_section_uid'], 'kit_version_map_booklet_item_fk')
                ->references(['id', 'booklet_version_id', 'booklet_item_uid', 'booklet_section_uid'])->on('omr_booklet_items')->restrictOnDelete();
            $table->foreign([
                'booklet_set_item_id', 'booklet_set_id', 'booklet_version_id', 'booklet_item_id',
                'booklet_item_uid', 'booklet_section_uid',
            ], 'kit_version_map_set_occurrence_fk')->references([
                'id', 'booklet_set_id', 'booklet_version_id', 'booklet_item_id', 'booklet_item_uid', 'booklet_section_uid',
            ])->on('omr_booklet_set_items')->restrictOnDelete();
            $table->foreign(
                ['test_instrument_item_id', 'test_instrument_version_id', 'test_instrument_subtest_id'],
                'kit_version_map_formal_item_fk',
            )->references(['id', 'test_instrument_version_id', 'test_instrument_subtest_id'])
                ->on('test_instrument_items')->restrictOnDelete();
            $table->foreign(['test_instrument_version_id', 'test_instrument_subtest_id'], 'kit_version_map_subtest_fk')
                ->references(['test_instrument_version_id', 'id'])->on('test_instrument_subtests')->restrictOnDelete();
        });
    }

    private function installDatabaseGuards(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE test_kit_preparations ADD CONSTRAINT kit_preparation_composition_revision_check CHECK (composition_revision >= 1)');
            DB::statement(<<<'SQL'
                ALTER TABLE test_kit_preparation_composition_revisions
                    ADD CONSTRAINT kit_prep_comp_revision_hash_check
                    CHECK (component_vector_sha256 ~ '^[0-9a-f]{64}$')
            SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE test_kit_versions
                    ADD CONSTRAINT kit_version_composition_vector_check CHECK (
                        (source_preparation_id IS NULL AND source_preparation_revision IS NULL AND composition_revision IS NULL
                            AND composition_sha256 IS NULL AND composition_rows_sealed_at IS NULL)
                        OR (source_preparation_id IS NOT NULL AND source_preparation_revision IS NOT NULL
                            AND composition_revision >= 1 AND composition_sha256 ~ '^[0-9a-f]{64}$')
                    )
            SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE test_kit_preparation_composition_rows
                    ADD CONSTRAINT kit_prep_map_disposition_check CHECK (
                        (disposition = 'physical' AND physical_item_uid IS NOT NULL AND sheet_item_id IS NOT NULL
                            AND booklet_set_item_id IS NOT NULL AND booklet_item_id IS NOT NULL
                            AND booklet_item_uid IS NOT NULL AND booklet_section_uid IS NOT NULL)
                        OR (disposition = 'nonphysical_practice' AND physical_item_uid IS NULL AND sheet_item_id IS NULL
                            AND booklet_set_item_id IS NULL AND booklet_item_id IS NULL
                            AND booklet_item_uid IS NULL AND booklet_section_uid IS NULL)
                    )
            SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE test_kit_version_composition_rows
                    ADD CONSTRAINT kit_version_map_disposition_check CHECK (
                        (disposition = 'physical' AND physical_item_uid IS NOT NULL AND sheet_item_id IS NOT NULL
                            AND booklet_set_item_id IS NOT NULL AND booklet_item_id IS NOT NULL
                            AND booklet_item_uid IS NOT NULL AND booklet_section_uid IS NOT NULL)
                        OR (disposition = 'nonphysical_practice' AND physical_item_uid IS NULL AND sheet_item_id IS NULL
                            AND booklet_set_item_id IS NULL AND booklet_item_id IS NULL
                            AND booklet_item_uid IS NULL AND booklet_section_uid IS NULL)
                    )
            SQL);
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION nexiam_test_kit_preparation_composition_revision_guard()
                RETURNS trigger LANGUAGE plpgsql AS $function$
                DECLARE
                    prep test_kit_preparations%ROWTYPE;
                BEGIN
                    SELECT * INTO prep FROM test_kit_preparations
                    WHERE id = NEW.test_kit_preparation_id AND test_kit_id = NEW.test_kit_id
                    FOR UPDATE;
                    IF NOT FOUND OR prep.state <> 'preparing'
                        OR prep.composition_revision <> NEW.composition_revision
                        OR prep.template_version_id IS DISTINCT FROM NEW.template_version_id
                        OR prep.booklet_version_id IS DISTINCT FROM NEW.booklet_version_id
                        OR prep.booklet_set_id IS DISTINCT FROM NEW.booklet_set_id
                        OR prep.test_instrument_version_id IS DISTINCT FROM NEW.test_instrument_version_id
                        OR NEW.component_vector_sha256 !~ '^[0-9a-f]{64}$' THEN
                        RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'Test Kit composition vector is stale';
                    END IF;
                    RETURN NEW;
                END;
                $function$
            SQL);
            DB::statement('CREATE TRIGGER kit_prep_comp_revision_insert_guard BEFORE INSERT ON test_kit_preparation_composition_revisions FOR EACH ROW EXECUTE FUNCTION nexiam_test_kit_preparation_composition_revision_guard()');
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION nexiam_test_kit_preparation_composition_immutable()
                RETURNS trigger LANGUAGE plpgsql AS $function$
                BEGIN
                    RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'Test Kit composition revision records are append-only';
                END;
                $function$
            SQL);
            DB::statement('CREATE TRIGGER kit_prep_comp_revision_immutable BEFORE UPDATE OR DELETE ON test_kit_preparation_composition_revisions FOR EACH ROW EXECUTE FUNCTION nexiam_test_kit_preparation_composition_immutable()');
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION nexiam_test_kit_preparation_map_guard()
                RETURNS trigger LANGUAGE plpgsql AS $function$
                DECLARE
                    prep test_kit_preparations%ROWTYPE;
                BEGIN
                    IF TG_OP <> 'INSERT' THEN
                        RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'Test Kit preparation composition rows are append-only';
                    END IF;
                    SELECT * INTO prep FROM test_kit_preparations
                    WHERE id = NEW.test_kit_preparation_id AND test_kit_id = NEW.test_kit_id
                    FOR UPDATE;
                    IF NOT FOUND OR prep.state <> 'preparing'
                        OR prep.composition_revision <> NEW.composition_revision THEN
                        RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'Test Kit composition row revision is stale';
                    END IF;
                    RETURN NEW;
                END;
                $function$
            SQL);
            DB::statement('CREATE TRIGGER kit_preparation_map_guard BEFORE INSERT OR UPDATE OR DELETE ON test_kit_preparation_composition_rows FOR EACH ROW EXECUTE FUNCTION nexiam_test_kit_preparation_map_guard()');
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION nexiam_test_kit_version_map_guard()
                RETURNS trigger LANGUAGE plpgsql AS $function$
                DECLARE
                    sealed_at timestamp;
                BEGIN
                    IF TG_OP = 'UPDATE' THEN
                        PERFORM id FROM test_kit_versions
                        WHERE id IN (OLD.test_kit_version_id, NEW.test_kit_version_id)
                        ORDER BY id FOR UPDATE;
                        RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'Test Kit composition rows are immutable';
                    END IF;
                    IF TG_OP = 'DELETE' THEN
                        SELECT composition_rows_sealed_at INTO sealed_at FROM test_kit_versions
                        WHERE id = OLD.test_kit_version_id FOR UPDATE;
                        RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'Test Kit composition rows are immutable';
                    END IF;
                    SELECT composition_rows_sealed_at INTO sealed_at FROM test_kit_versions
                    WHERE id = NEW.test_kit_version_id FOR UPDATE;
                    IF NOT FOUND OR sealed_at IS NOT NULL THEN
                        RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'sealed or missing Test Kit composition parent';
                    END IF;
                    RETURN NEW;
                END;
                $function$
            SQL);
            DB::statement('CREATE TRIGGER kit_version_map_immutable BEFORE INSERT OR UPDATE OR DELETE ON test_kit_version_composition_rows FOR EACH ROW EXECUTE FUNCTION nexiam_test_kit_version_map_guard()');
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION nexiam_test_kit_composition_seal_guard()
                RETURNS trigger LANGUAGE plpgsql AS $function$
                BEGIN
                    IF OLD.composition_rows_sealed_at IS NOT NULL
                        AND NEW.composition_rows_sealed_at IS DISTINCT FROM OLD.composition_rows_sealed_at THEN
                        RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'Test Kit composition seal is immutable';
                    END IF;
                    IF OLD.composition_sha256 IS NOT NULL AND (
                        NEW.source_preparation_id IS DISTINCT FROM OLD.source_preparation_id
                        OR NEW.source_preparation_revision IS DISTINCT FROM OLD.source_preparation_revision
                        OR NEW.composition_revision IS DISTINCT FROM OLD.composition_revision
                        OR NEW.composition_sha256 IS DISTINCT FROM OLD.composition_sha256
                    ) THEN
                        RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'Test Kit composition identity is immutable';
                    END IF;
                    IF NEW.status = 'active' AND NEW.composition_sha256 IS NOT NULL
                        AND NEW.composition_rows_sealed_at IS NULL THEN
                        RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'Test Kit composition rows must be sealed before activation';
                    END IF;
                    RETURN NEW;
                END;
                $function$
            SQL);
            DB::statement('CREATE TRIGGER kit_version_composition_seal_guard BEFORE UPDATE ON test_kit_versions FOR EACH ROW EXECUTE FUNCTION nexiam_test_kit_composition_seal_guard()');

            return;
        }

        if ($driver !== 'sqlite') {
            throw new RuntimeException('Kit-owned composition requires PostgreSQL or SQLite.');
        }

        DB::statement("CREATE TRIGGER kit_prep_composition_revision_insert_check BEFORE INSERT ON test_kit_preparations FOR EACH ROW WHEN NEW.composition_revision < 1 OR NEW.omr_enabled NOT IN (0, 1) BEGIN SELECT RAISE(ABORT, 'Invalid Test Kit composition revision'); END");
        DB::statement("CREATE TRIGGER kit_prep_composition_revision_update_check BEFORE UPDATE ON test_kit_preparations FOR EACH ROW WHEN NEW.composition_revision < OLD.composition_revision OR ((NEW.template_version_id IS NOT OLD.template_version_id OR NEW.booklet_version_id IS NOT OLD.booklet_version_id OR NEW.booklet_set_id IS NOT OLD.booklet_set_id OR NEW.test_instrument_version_id IS NOT OLD.test_instrument_version_id) AND NEW.composition_revision <= OLD.composition_revision) OR NEW.omr_enabled NOT IN (0, 1) OR (OLD.state = 'assembled' AND (NEW.composition_revision IS NOT OLD.composition_revision OR NEW.omr_enabled IS NOT OLD.omr_enabled)) BEGIN SELECT RAISE(ABORT, 'Stale or immutable Test Kit composition'); END");
        DB::statement("CREATE TRIGGER kit_prep_comp_revision_insert_guard BEFORE INSERT ON test_kit_preparation_composition_revisions FOR EACH ROW WHEN NOT EXISTS (SELECT 1 FROM test_kit_preparations p WHERE p.id = NEW.test_kit_preparation_id AND p.test_kit_id = NEW.test_kit_id AND p.state = 'preparing' AND p.composition_revision = NEW.composition_revision AND p.template_version_id = NEW.template_version_id AND p.booklet_version_id = NEW.booklet_version_id AND p.booklet_set_id = NEW.booklet_set_id AND p.test_instrument_version_id = NEW.test_instrument_version_id) OR length(NEW.component_vector_sha256) <> 64 OR NEW.component_vector_sha256 GLOB '*[^0-9a-f]*' OR json_valid(NEW.component_vector_json) <> 1 BEGIN SELECT RAISE(ABORT, 'Stale or invalid Test Kit composition vector'); END");
        DB::statement("CREATE TRIGGER kit_prep_comp_revision_update_guard BEFORE UPDATE ON test_kit_preparation_composition_revisions BEGIN SELECT RAISE(ABORT, 'Test Kit composition revision records are append-only'); END");
        DB::statement("CREATE TRIGGER kit_prep_comp_revision_delete_guard BEFORE DELETE ON test_kit_preparation_composition_revisions BEGIN SELECT RAISE(ABORT, 'Test Kit composition revision records are append-only'); END");
        DB::statement("CREATE TRIGGER kit_prep_map_insert_guard BEFORE INSERT ON test_kit_preparation_composition_rows WHEN NOT EXISTS (SELECT 1 FROM test_kit_preparations p WHERE p.id = NEW.test_kit_preparation_id AND p.test_kit_id = NEW.test_kit_id AND p.state = 'preparing' AND p.composition_revision = NEW.composition_revision) OR NOT ((NEW.disposition = 'physical' AND NEW.physical_item_uid IS NOT NULL AND NEW.sheet_item_id IS NOT NULL AND NEW.booklet_set_item_id IS NOT NULL AND NEW.booklet_item_id IS NOT NULL AND NEW.booklet_item_uid IS NOT NULL AND NEW.booklet_section_uid IS NOT NULL) OR (NEW.disposition = 'nonphysical_practice' AND NEW.physical_item_uid IS NULL AND NEW.sheet_item_id IS NULL AND NEW.booklet_set_item_id IS NULL AND NEW.booklet_item_id IS NULL AND NEW.booklet_item_uid IS NULL AND NEW.booklet_section_uid IS NULL)) BEGIN SELECT RAISE(ABORT, 'Stale or invalid Test Kit composition row'); END");
        DB::statement("CREATE TRIGGER kit_prep_map_update_guard BEFORE UPDATE ON test_kit_preparation_composition_rows BEGIN SELECT RAISE(ABORT, 'Test Kit preparation composition rows are append-only'); END");
        DB::statement("CREATE TRIGGER kit_prep_map_delete_guard BEFORE DELETE ON test_kit_preparation_composition_rows BEGIN SELECT RAISE(ABORT, 'Test Kit preparation composition rows are append-only'); END");
        DB::statement("CREATE TRIGGER kit_version_map_insert_guard BEFORE INSERT ON test_kit_version_composition_rows WHEN (SELECT composition_rows_sealed_at FROM test_kit_versions WHERE id = NEW.test_kit_version_id) IS NOT NULL OR NOT ((NEW.disposition = 'physical' AND NEW.physical_item_uid IS NOT NULL AND NEW.sheet_item_id IS NOT NULL AND NEW.booklet_set_item_id IS NOT NULL AND NEW.booklet_item_id IS NOT NULL AND NEW.booklet_item_uid IS NOT NULL AND NEW.booklet_section_uid IS NOT NULL) OR (NEW.disposition = 'nonphysical_practice' AND NEW.physical_item_uid IS NULL AND NEW.sheet_item_id IS NULL AND NEW.booklet_set_item_id IS NULL AND NEW.booklet_item_id IS NULL AND NEW.booklet_item_uid IS NULL AND NEW.booklet_section_uid IS NULL)) BEGIN SELECT RAISE(ABORT, 'Sealed or invalid Test Kit composition row'); END");
        DB::statement("CREATE TRIGGER kit_version_map_update_guard BEFORE UPDATE ON test_kit_version_composition_rows BEGIN SELECT RAISE(ABORT, 'Test Kit composition rows are immutable'); END");
        DB::statement("CREATE TRIGGER kit_version_map_delete_guard BEFORE DELETE ON test_kit_version_composition_rows BEGIN SELECT RAISE(ABORT, 'Test Kit composition rows are immutable'); END");
        DB::statement("CREATE TRIGGER kit_version_composition_insert_guard BEFORE INSERT ON test_kit_versions FOR EACH ROW WHEN NOT ((NEW.composition_revision IS NULL AND NEW.composition_sha256 IS NULL AND NEW.composition_rows_sealed_at IS NULL) OR (NEW.composition_revision >= 1 AND length(NEW.composition_sha256) = 64 AND NEW.composition_sha256 NOT GLOB '*[^0-9a-f]*')) BEGIN SELECT RAISE(ABORT, 'Invalid Test Kit composition identity'); END");
        DB::statement("CREATE TRIGGER kit_version_composition_seal_guard BEFORE UPDATE ON test_kit_versions FOR EACH ROW WHEN (OLD.composition_rows_sealed_at IS NOT NULL AND NEW.composition_rows_sealed_at IS NOT OLD.composition_rows_sealed_at) OR (OLD.composition_sha256 IS NOT NULL AND (NEW.source_preparation_id IS NOT OLD.source_preparation_id OR NEW.source_preparation_revision IS NOT OLD.source_preparation_revision OR NEW.composition_revision IS NOT OLD.composition_revision OR NEW.composition_sha256 IS NOT OLD.composition_sha256)) OR (NEW.status = 'active' AND NEW.composition_sha256 IS NOT NULL AND NEW.composition_rows_sealed_at IS NULL) BEGIN SELECT RAISE(ABORT, 'Test Kit composition seal is immutable or required'); END");
    }

    private function captureSqliteTriggers(): void
    {
        $this->sqlitePreservedTriggers = DB::table('sqlite_master')
            ->where('type', 'trigger')
            ->whereIn('tbl_name', ['test_kit_versions', 'test_kit_preparations'])
            ->whereNotLike('name', 'kit_prep_comp_revision_%')
            ->whereNotLike('name', 'kit_prep_composition_revision_%')
            ->whereNotLike('name', 'kit_prep_map_%')
            ->whereNotLike('name', 'kit_prep_composition_revision_%')
            ->whereNotLike('name', 'kit_preparation_composition_%')
            ->whereNotLike('name', 'kit_preparation_map_%')
            ->whereNotLike('name', 'kit_version_map_%')
            ->whereNotLike('name', 'kit_version_composition_%')
            ->pluck('sql', 'name')
            ->filter(static fn (mixed $sql): bool => is_string($sql) && trim($sql) !== '')
            ->all();
    }

    private function restoreSqliteTriggers(): void
    {
        foreach ($this->sqlitePreservedTriggers as $trigger => $sql) {
            if (DB::table('sqlite_master')->where('type', 'trigger')->where('name', $trigger)->exists()) {
                continue;
            }
            DB::statement($sql);
        }
    }

    public function down(): void
    {
        if (DB::table('test_kit_preparation_composition_revisions')->exists()
            || DB::table('test_kit_preparation_composition_rows')->exists()
            || DB::table('test_kit_version_composition_rows')->exists()
            || DB::table('test_kit_versions')->whereNotNull('composition_sha256')->exists()
            || DB::table('test_kit_preparations')->where('composition_revision', '>', 1)->orWhere('omr_enabled', true)->exists()) {
            throw new RuntimeException('Kit composition data exists; use a forward migration instead of rolling this cutover back.');
        }

        if (DB::getDriverName() === 'sqlite') {
            $this->captureSqliteTriggers();
        }
        $this->dropDatabaseGuards();
        Schema::dropIfExists('test_kit_version_composition_rows');
        Schema::dropIfExists('test_kit_preparation_composition_rows');
        Schema::dropIfExists('test_kit_preparation_composition_revisions');

        Schema::table('test_kit_versions', function (Blueprint $table): void {
            $table->dropForeign('kit_version_source_preparation_fk');
            $table->dropForeign('kit_version_source_preparation_revision_fk');
            $table->dropForeign('test_kit_version_sheet_exact_fk');
            $table->dropForeign('test_kit_version_booklet_exact_fk');
            $table->dropUnique('kit_version_composition_vector_unique');
            $table->dropColumn([
                'source_preparation_id', 'source_preparation_revision', 'composition_revision',
                'composition_sha256', 'composition_rows_sealed_at',
            ]);
            $table->foreign(['template_version_id', 'test_instrument_version_id'], 'test_kit_versions_sheet_profile_fk')
                ->references(['id', 'test_instrument_version_id'])->on('omr_answer_sheet_template_versions')->restrictOnDelete();
            $table->foreign(['booklet_version_id', 'template_version_id', 'test_instrument_version_id'], 'test_kit_versions_booklet_material_fk')
                ->references(['id', 'template_version_id', 'test_instrument_version_id'])->on('omr_question_booklet_versions')->restrictOnDelete();
            $table->foreign(['template_version_id', 'template_id', 'test_instrument_version_id'], 'test_kit_version_sheet_exact_fk')
                ->references(['id', 'template_id', 'test_instrument_version_id'])->on('omr_answer_sheet_template_versions')->restrictOnDelete();
            $table->foreign(['booklet_version_id', 'booklet_id', 'template_version_id', 'test_instrument_version_id'], 'test_kit_version_booklet_exact_fk')
                ->references(['id', 'booklet_id', 'template_version_id', 'test_instrument_version_id'])->on('omr_question_booklet_versions')->restrictOnDelete();
        });

        Schema::table('test_kit_preparations', function (Blueprint $table): void {
            $table->dropUnique('kit_preparation_revision_tuple_unique');
            $table->dropColumn(['composition_revision', 'omr_enabled']);
        });
        Schema::table('test_instrument_items', function (Blueprint $table): void {
            $table->dropUnique('instrument_item_exact_map_tuple_unique');
        });
        Schema::table('omr_question_booklet_versions', function (Blueprint $table): void {
            $table->dropUnique('omr_booklet_version_id_owner_unique');
        });

        if (DB::getDriverName() === 'sqlite') {
            $this->restoreSqliteTriggers();
        }
    }

    private function dropDatabaseGuards(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS kit_prep_comp_revision_insert_guard ON test_kit_preparation_composition_revisions');
            DB::statement('DROP FUNCTION IF EXISTS nexiam_test_kit_preparation_composition_revision_guard()');
            DB::statement('DROP TRIGGER IF EXISTS kit_prep_comp_revision_immutable ON test_kit_preparation_composition_revisions');
            DB::statement('DROP FUNCTION IF EXISTS nexiam_test_kit_preparation_composition_immutable()');
            DB::statement('DROP TRIGGER IF EXISTS kit_preparation_map_guard ON test_kit_preparation_composition_rows');
            DB::statement('DROP FUNCTION IF EXISTS nexiam_test_kit_preparation_map_guard()');
            DB::statement('DROP TRIGGER IF EXISTS kit_version_map_immutable ON test_kit_version_composition_rows');
            DB::statement('DROP FUNCTION IF EXISTS nexiam_test_kit_version_map_guard()');
            DB::statement('DROP TRIGGER IF EXISTS kit_version_composition_seal_guard ON test_kit_versions');
            DB::statement('DROP FUNCTION IF EXISTS nexiam_test_kit_composition_seal_guard()');
            DB::statement('ALTER TABLE test_kit_preparations DROP CONSTRAINT IF EXISTS kit_preparation_composition_revision_check');
            DB::statement('ALTER TABLE test_kit_preparation_composition_revisions DROP CONSTRAINT IF EXISTS kit_prep_comp_revision_hash_check');
            DB::statement('ALTER TABLE test_kit_versions DROP CONSTRAINT IF EXISTS kit_version_composition_vector_check');
            DB::statement('ALTER TABLE test_kit_preparation_composition_rows DROP CONSTRAINT IF EXISTS kit_prep_map_disposition_check');
            DB::statement('ALTER TABLE test_kit_version_composition_rows DROP CONSTRAINT IF EXISTS kit_version_map_disposition_check');

            return;
        }

        foreach ([
            'kit_prep_composition_revision_insert_check', 'kit_prep_composition_revision_update_check',
            'kit_prep_comp_revision_insert_guard', 'kit_prep_comp_revision_update_guard',
            'kit_prep_comp_revision_delete_guard', 'kit_prep_map_insert_guard',
            'kit_prep_map_update_guard', 'kit_prep_map_delete_guard',
            'kit_version_map_insert_guard', 'kit_version_map_update_guard', 'kit_version_map_delete_guard',
            'kit_version_composition_insert_guard', 'kit_version_composition_seal_guard',
        ] as $trigger) {
            DB::statement('DROP TRIGGER IF EXISTS '.$trigger);
        }
    }
};
