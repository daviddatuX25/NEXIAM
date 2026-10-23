<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    private const SQLITE_REBUILD_TABLE = 'test_kit_preparation_composition_revisions_conditional_rebuild';

    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::transaction(fn () => $this->alterPostgres());

            return;
        }
        if ($driver !== 'sqlite') {
            throw new RuntimeException('Conditional preparation composition requires PostgreSQL or SQLite.');
        }

        $this->rebuildSqlite();
        $this->installSqliteGuard();
    }

    private function alterPostgres(): void
    {
        DB::statement('ALTER TABLE test_kit_preparation_composition_revisions ALTER COLUMN template_version_id DROP NOT NULL');
        DB::statement('ALTER TABLE test_kit_preparation_composition_revisions ALTER COLUMN booklet_version_id DROP NOT NULL');
        DB::statement('ALTER TABLE test_kit_preparation_composition_revisions ALTER COLUMN booklet_set_id DROP NOT NULL');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION nexiam_test_kit_preparation_composition_revision_guard()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                preparation test_kit_preparations%ROWTYPE;
                kit test_kits%ROWTYPE;
                profile test_instrument_versions%ROWTYPE;
                metric_count integer;
                undeclared_metric_count integer;
                requires_sheet boolean;
                requires_booklet boolean;
                requires_key boolean;
                omr_applicable boolean;
                selected_set omr_booklet_sets%ROWTYPE;
            BEGIN
                SELECT * INTO preparation
                FROM test_kit_preparations
                WHERE id = NEW.test_kit_preparation_id AND test_kit_id = NEW.test_kit_id
                FOR UPDATE;
                SELECT * INTO kit FROM test_kits WHERE id = NEW.test_kit_id FOR KEY SHARE;
                SELECT * INTO profile FROM test_instrument_versions WHERE id = NEW.test_instrument_version_id FOR SHARE;

                IF NOT FOUND OR preparation.state <> 'preparing'
                    OR preparation.composition_revision <> NEW.composition_revision
                    OR preparation.template_version_id IS DISTINCT FROM NEW.template_version_id
                    OR preparation.booklet_version_id IS DISTINCT FROM NEW.booklet_version_id
                    OR preparation.booklet_set_id IS DISTINCT FROM NEW.booklet_set_id
                    OR preparation.test_instrument_version_id IS DISTINCT FROM NEW.test_instrument_version_id
                    OR NEW.test_instrument_version_id IS NULL
                    OR profile.id IS NULL OR profile.status <> 'approved'
                    OR kit.id IS NULL OR profile.test_instrument_id IS NULL THEN
                    RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'Test Kit composition vector is stale or has no approved scoring profile';
                END IF;
                IF NEW.component_vector_sha256 !~ '^[0-9a-f]{64}$'
                    OR NEW.component_vector_json::jsonb ->> 'test_kit_id' IS DISTINCT FROM NEW.test_kit_id::text
                    OR NEW.component_vector_json::jsonb ->> 'institution_id' IS DISTINCT FROM kit.institution_id::text
                    OR (NEW.component_vector_json::jsonb ->> 'is_synthetic')::boolean IS DISTINCT FROM kit.is_synthetic
                    OR (NEW.component_vector_json::jsonb ->> 'template_version_id')::bigint IS DISTINCT FROM NEW.template_version_id
                    OR (NEW.component_vector_json::jsonb ->> 'booklet_version_id')::bigint IS DISTINCT FROM NEW.booklet_version_id
                    OR (NEW.component_vector_json::jsonb ->> 'booklet_set_id')::bigint IS DISTINCT FROM NEW.booklet_set_id
                    OR (NEW.component_vector_json::jsonb ->> 'test_instrument_version_id')::bigint IS DISTINCT FROM NEW.test_instrument_version_id
                    OR (NEW.component_vector_json::jsonb #>> '{profile,instrument_id}')::bigint IS DISTINCT FROM profile.test_instrument_id
                    OR (NEW.component_vector_json::jsonb #>> '{profile,version_number}')::integer IS DISTINCT FROM profile.version_number
                    OR NEW.component_vector_json::jsonb #>> '{profile,content_sha256}' IS DISTINCT FROM profile.content_sha256 THEN
                    RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'Test Kit composition vector identity or digest is invalid';
                END IF;

                SELECT count(*), count(*) FILTER (WHERE m.input_contract_json IS NULL),
                    COALESCE(bool_or(m.input_contract_json::jsonb -> 'required_resources' ? 'answer_sheet'), false),
                    COALESCE(bool_or(m.input_contract_json::jsonb -> 'required_resources' ? 'booklet_form'), false),
                    COALESCE(bool_or(m.input_contract_json::jsonb -> 'required_resources' ? 'key'), false),
                    COALESCE(bool_or(m.input_contract_json::jsonb -> 'allowed_sources' ? 'omr'), false)
                INTO metric_count, undeclared_metric_count, requires_sheet, requires_booklet, requires_key, omr_applicable
                FROM test_instrument_metrics m
                WHERE m.test_instrument_version_id = NEW.test_instrument_version_id;

                IF metric_count = 0 THEN
                    RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'A Test Kit composition snapshot requires at least one profile metric';
                END IF;
                IF undeclared_metric_count > 0 THEN
                    IF NEW.template_version_id IS NULL OR NEW.booklet_version_id IS NULL OR NEW.booklet_set_id IS NULL THEN
                        RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'Undeclared historical profile inputs require the existing complete paper composition';
                    END IF;
                ELSE
                    IF (NEW.template_version_id IS NOT NULL) IS DISTINCT FROM requires_sheet
                        OR (NEW.booklet_version_id IS NOT NULL) IS DISTINCT FROM requires_booklet
                        OR (NEW.booklet_set_id IS NOT NULL) IS DISTINCT FROM requires_booklet
                        OR (requires_key AND NOT requires_booklet)
                        OR (omr_applicable AND (NOT requires_sheet OR NOT requires_booklet OR NOT requires_key)) THEN
                        RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'Conditional preparation components do not match the approved profile resource declarations';
                    END IF;
                END IF;

                IF (NEW.template_version_id IS NULL) IS DISTINCT FROM (NEW.component_vector_json::jsonb -> 'sheet' = 'null'::jsonb)
                    OR (NEW.booklet_version_id IS NULL) IS DISTINCT FROM (NEW.component_vector_json::jsonb -> 'booklet' = 'null'::jsonb) THEN
                    RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'Conditional preparation component vector contains an invented material';
                END IF;
                IF NEW.template_version_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM omr_answer_sheet_template_versions sheet_version
                    JOIN omr_answer_sheet_templates sheet ON sheet.id = sheet_version.template_id
                    WHERE sheet_version.id = NEW.template_version_id
                        AND sheet.institution_id = kit.institution_id AND sheet.is_synthetic = kit.is_synthetic
                ) THEN
                    RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'Preparation Answer Sheet owner does not match its Kit';
                END IF;
                IF NEW.booklet_version_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM omr_question_booklet_versions booklet_version
                    JOIN omr_question_booklets booklet ON booklet.id = booklet_version.booklet_id
                    WHERE booklet_version.id = NEW.booklet_version_id
                        AND booklet.institution_id = kit.institution_id AND booklet.is_synthetic = kit.is_synthetic
                ) THEN
                    RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'Preparation Booklet owner does not match its Kit';
                END IF;
                IF NEW.booklet_set_id IS NOT NULL THEN
                    SELECT * INTO selected_set FROM omr_booklet_sets WHERE id = NEW.booklet_set_id FOR KEY SHARE;
                    IF selected_set.id IS NULL OR selected_set.booklet_version_id IS DISTINCT FROM NEW.booklet_version_id
                        OR selected_set.set_code <> 'A' OR selected_set.status <> 'finalized' OR selected_set.finalized_at IS NULL
                        OR NEW.component_vector_json::jsonb #>> '{booklet,set_code}' IS DISTINCT FROM selected_set.set_code
                        OR NEW.component_vector_json::jsonb #>> '{booklet,set_order_hash}' IS DISTINCT FROM selected_set.order_hash
                        OR (requires_key AND NEW.component_vector_json::jsonb #>> '{booklet,set_answer_key_hash}' IS DISTINCT FROM selected_set.answer_key_hash)
                        OR (NOT requires_key AND NEW.component_vector_json::jsonb #>> '{booklet,set_answer_key_hash}' IS NOT NULL) THEN
                        RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'Preparation Set identity does not match its exact Booklet and applicable key contract';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $function$
        SQL);
    }

    private function rebuildSqlite(): void
    {
        $connection = DB::connection();
        if ($connection->transactionLevel() !== 0) {
            throw new RuntimeException('The SQLite preparation-revision table rebuild cannot run inside an existing transaction.');
        }
        $foreignKeyState = DB::selectOne('PRAGMA foreign_keys');
        $legacyAlterState = DB::selectOne('PRAGMA legacy_alter_table');
        if ((int) ($foreignKeyState->foreign_keys ?? 0) !== 1 || ! in_array((int) ($legacyAlterState->legacy_alter_table ?? -1), [0, 1], true)) {
            throw new RuntimeException('SQLite foreign-key or legacy ALTER TABLE state is unavailable for preparation revisions.');
        }
        $legacy = (int) $legacyAlterState->legacy_alter_table;
        $tableName = 'test_kit_preparation_composition_revisions';
        $table = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$tableName]);
        if (! is_string($table?->sql ?? null)
            || DB::table('sqlite_master')->where('type', 'table')->where('name', self::SQLITE_REBUILD_TABLE)->exists()) {
            throw new RuntimeException('SQLite preparation-revision DDL is unavailable or its rebuild table already exists.');
        }
        $indexes = DB::select("SELECT sql FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND sql IS NOT NULL", [$tableName]);
        $triggers = DB::select("SELECT sql FROM sqlite_master WHERE type = 'trigger' AND tbl_name = ? AND sql IS NOT NULL", [$tableName]);
        $columns = array_map(
            static fn (object $column): string => '"'.str_replace('"', '""', $column->name).'"',
            DB::select("PRAGMA table_info('{$tableName}')"),
        );
        $sql = $table->sql;
        $open = strpos($sql, '(');
        $close = strrpos($sql, ')');
        if ($open === false || $close === false || $close <= $open) {
            throw new RuntimeException('SQLite preparation-revision table definition is malformed.');
        }
        $body = substr($sql, $open + 1, $close - $open - 1);
        foreach (['template_version_id', 'booklet_version_id', 'booklet_set_id'] as $name) {
            $body = preg_replace('/("?'.preg_quote($name, '/').'"?\s+[^,]*?)\s+NOT\s+NULL/i', '$1 NULL', $body, 1, $count);
            if ($count !== 1) {
                throw new RuntimeException("SQLite preparation-revision column {$name} was not found.");
            }
        }
        $prefix = substr($sql, 0, $open);
        $prefix = preg_replace('/["`\[]?'.preg_quote($tableName, '/').'["`\]]?/i', '"'.self::SQLITE_REBUILD_TABLE.'"', $prefix, 1);
        if (! is_string($prefix)) {
            throw new RuntimeException('SQLite preparation-revision table name could not be rewritten.');
        }

        DB::statement('PRAGMA foreign_keys = OFF');
        try {
            DB::statement('PRAGMA legacy_alter_table = ON');
            $connection->transaction(function () use ($body, $prefix, $columns, $indexes, $triggers, $tableName): void {
                DB::statement($prefix.'('.$body.')');
                $columnList = implode(', ', $columns);
                DB::statement('INSERT INTO '.self::SQLITE_REBUILD_TABLE.' ('.$columnList.') SELECT '.$columnList.' FROM '.$tableName);
                DB::statement('DROP TABLE '.$tableName);
                DB::statement('ALTER TABLE '.self::SQLITE_REBUILD_TABLE.' RENAME TO '.$tableName);
                foreach ($indexes as $index) {
                    DB::statement($index->sql);
                }
                foreach ($triggers as $trigger) {
                    DB::statement($trigger->sql);
                }
                if (DB::select('PRAGMA foreign_key_check') !== []) {
                    throw new RuntimeException('SQLite preparation-revision rebuild found foreign-key violations.');
                }
            });
        } finally {
            try {
                DB::statement('PRAGMA legacy_alter_table = '.$legacy);
            } finally {
                DB::statement('PRAGMA foreign_keys = '.(int) $foreignKeyState->foreign_keys);
            }
        }
    }

    private function installSqliteGuard(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS kit_prep_comp_revision_insert_guard;
            CREATE TRIGGER kit_prep_comp_revision_insert_guard
            BEFORE INSERT ON test_kit_preparation_composition_revisions
            FOR EACH ROW
            WHEN NOT EXISTS (
                SELECT 1 FROM test_kit_preparations p
                JOIN test_kits k ON k.id = p.test_kit_id
                JOIN test_instrument_versions profile ON profile.id = NEW.test_instrument_version_id
                WHERE p.id = NEW.test_kit_preparation_id AND p.test_kit_id = NEW.test_kit_id
                    AND p.state = 'preparing' AND p.composition_revision = NEW.composition_revision
                    AND p.template_version_id IS NEW.template_version_id
                    AND p.booklet_version_id IS NEW.booklet_version_id
                    AND p.booklet_set_id IS NEW.booklet_set_id
                    AND p.test_instrument_version_id = NEW.test_instrument_version_id
                    AND profile.status = 'approved'
                    AND profile.test_instrument_id IS NOT NULL
                    AND json_valid(NEW.component_vector_json) = 1
                    AND json_extract(NEW.component_vector_json, '$.test_kit_id') = NEW.test_kit_id
                    AND json_extract(NEW.component_vector_json, '$.institution_id') = k.institution_id
                    AND json_extract(NEW.component_vector_json, '$.is_synthetic') = k.is_synthetic
                    AND json_extract(NEW.component_vector_json, '$.template_version_id') IS NEW.template_version_id
                    AND json_extract(NEW.component_vector_json, '$.booklet_version_id') IS NEW.booklet_version_id
                    AND json_extract(NEW.component_vector_json, '$.booklet_set_id') IS NEW.booklet_set_id
                    AND json_extract(NEW.component_vector_json, '$.test_instrument_version_id') = NEW.test_instrument_version_id
                    AND json_extract(NEW.component_vector_json, '$.profile.instrument_id') = profile.test_instrument_id
                    AND json_extract(NEW.component_vector_json, '$.profile.version_number') = profile.version_number
                    AND json_extract(NEW.component_vector_json, '$.profile.content_sha256') = profile.content_sha256
                    AND length(NEW.component_vector_sha256) = 64
                    AND NEW.component_vector_sha256 NOT GLOB '*[^0-9a-f]*'
                    AND (json_extract(NEW.component_vector_json, '$.sheet') IS NULL) = (NEW.template_version_id IS NULL)
                    AND (json_extract(NEW.component_vector_json, '$.booklet') IS NULL) = (NEW.booklet_version_id IS NULL)
                    AND (
                        (SELECT count(*) FROM test_instrument_metrics m WHERE m.test_instrument_version_id = profile.id) > 0
                        AND (
                            EXISTS (SELECT 1 FROM test_instrument_metrics m WHERE m.test_instrument_version_id = profile.id AND m.input_contract_json IS NULL)
                                AND NEW.template_version_id IS NOT NULL AND NEW.booklet_version_id IS NOT NULL AND NEW.booklet_set_id IS NOT NULL
                            OR
                            NOT EXISTS (SELECT 1 FROM test_instrument_metrics m WHERE m.test_instrument_version_id = profile.id AND m.input_contract_json IS NULL)
                                AND (NEW.template_version_id IS NOT NULL) = EXISTS (
                                    SELECT 1 FROM test_instrument_metrics m, json_each(m.input_contract_json, '$.required_resources') resource
                                    WHERE m.test_instrument_version_id = profile.id AND resource.value = 'answer_sheet'
                                )
                                AND (NEW.booklet_version_id IS NOT NULL) = EXISTS (
                                    SELECT 1 FROM test_instrument_metrics m, json_each(m.input_contract_json, '$.required_resources') resource
                                    WHERE m.test_instrument_version_id = profile.id AND resource.value = 'booklet_form'
                                )
                                AND (NEW.booklet_set_id IS NOT NULL) = EXISTS (
                                    SELECT 1 FROM test_instrument_metrics m, json_each(m.input_contract_json, '$.required_resources') resource
                                    WHERE m.test_instrument_version_id = profile.id AND resource.value = 'booklet_form'
                                )
                                AND NOT EXISTS (
                                    SELECT 1 FROM test_instrument_metrics m, json_each(m.input_contract_json, '$.required_resources') resource
                                    WHERE m.test_instrument_version_id = profile.id AND resource.value = 'key'
                                        AND NOT EXISTS (
                                            SELECT 1 FROM test_instrument_metrics required_form, json_each(required_form.input_contract_json, '$.required_resources') form
                                            WHERE required_form.test_instrument_version_id = profile.id AND form.value = 'booklet_form'
                                        )
                                )
                                AND (NOT EXISTS (
                                    SELECT 1 FROM test_instrument_metrics m, json_each(m.input_contract_json, '$.allowed_sources') source
                                    WHERE m.test_instrument_version_id = profile.id AND source.value = 'omr'
                                ) OR (NEW.template_version_id IS NOT NULL AND NEW.booklet_version_id IS NOT NULL AND NEW.booklet_set_id IS NOT NULL))
                        )
                    )
                    AND (NEW.template_version_id IS NULL OR EXISTS (
                        SELECT 1 FROM omr_answer_sheet_template_versions sv
                        JOIN omr_answer_sheet_templates s ON s.id = sv.template_id
                        WHERE sv.id = NEW.template_version_id AND s.institution_id = k.institution_id AND s.is_synthetic = k.is_synthetic
                    ))
                    AND (NEW.booklet_version_id IS NULL OR EXISTS (
                        SELECT 1 FROM omr_question_booklet_versions bv
                        JOIN omr_question_booklets b ON b.id = bv.booklet_id
                        WHERE bv.id = NEW.booklet_version_id AND b.institution_id = k.institution_id AND b.is_synthetic = k.is_synthetic
                    ))
                    AND (NEW.booklet_set_id IS NULL OR EXISTS (
                        SELECT 1 FROM omr_booklet_sets s
                        WHERE s.id = NEW.booklet_set_id AND s.booklet_version_id = NEW.booklet_version_id
                            AND s.set_code = 'A' AND s.status = 'finalized' AND s.finalized_at IS NOT NULL
                            AND s.order_hash = json_extract(NEW.component_vector_json, '$.booklet.set_order_hash')
                            AND ((NEW.booklet_version_id IS NOT NULL AND EXISTS (
                                SELECT 1 FROM test_instrument_metrics m, json_each(m.input_contract_json, '$.required_resources') resource
                                WHERE m.test_instrument_version_id = profile.id AND resource.value = 'key'
                            ) AND s.answer_key_hash = json_extract(NEW.component_vector_json, '$.booklet.set_answer_key_hash'))
                                OR (NEW.booklet_version_id IS NOT NULL AND NOT EXISTS (
                                    SELECT 1 FROM test_instrument_metrics m, json_each(m.input_contract_json, '$.required_resources') resource
                                    WHERE m.test_instrument_version_id = profile.id AND resource.value = 'key'
                                ) AND json_extract(NEW.component_vector_json, '$.booklet.set_answer_key_hash') IS NULL))
                    ))
            )
                OR length(NEW.component_vector_sha256) <> 64
                OR NEW.component_vector_sha256 GLOB '*[^0-9a-f]*'
                OR json_valid(NEW.component_vector_json) <> 1
            BEGIN SELECT RAISE(ABORT, 'Stale, foreign, or undeclared Test Kit composition vector'); END;
        SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Conditional preparation snapshots are a forward-only integrity correction; use a forward migration instead of restoring mandatory paper pins.');
    }
};
