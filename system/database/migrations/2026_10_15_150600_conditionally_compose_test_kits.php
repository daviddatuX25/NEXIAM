<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    private const SQLITE_REBUILD_TABLE = 'test_kit_versions_conditional_rebuild';

    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::transaction(fn () => $this->alterPostgres());

            return;
        }
        if ($driver !== 'sqlite') {
            throw new RuntimeException('Conditional Test Kit composition requires PostgreSQL or SQLite.');
        }

        $this->rebuildSqlite();
        $this->installSqliteGuards();
    }

    private function alterPostgres(): void
    {
        DB::statement('ALTER TABLE test_kit_versions ALTER COLUMN template_id DROP NOT NULL');
        DB::statement('ALTER TABLE test_kit_versions ALTER COLUMN booklet_id DROP NOT NULL');
        DB::statement('ALTER TABLE test_kit_versions ALTER COLUMN template_version_id DROP NOT NULL');
        DB::statement('ALTER TABLE test_kit_versions ALTER COLUMN booklet_version_id DROP NOT NULL');
        DB::statement('ALTER TABLE test_kit_versions ALTER COLUMN booklet_set_id DROP NOT NULL');
        DB::statement('ALTER TABLE test_kit_versions ALTER COLUMN booklet_set_code DROP NOT NULL');
        DB::statement('ALTER TABLE test_kit_versions ALTER COLUMN booklet_set_code DROP DEFAULT');
        DB::statement('ALTER TABLE test_kit_versions ALTER COLUMN booklet_set_order_hash DROP NOT NULL');
        DB::statement('ALTER TABLE test_kit_versions ALTER COLUMN booklet_set_answer_key_hash DROP NOT NULL');
        DB::statement('ALTER TABLE test_kit_versions DROP CONSTRAINT test_kit_versions_lifecycle_check');
        DB::unprepared(<<<'SQL'
            ALTER TABLE test_kit_versions ADD CONSTRAINT test_kit_versions_lifecycle_check CHECK (
                status IN ('candidate', 'active', 'retired') AND revision >= 1
                AND predecessor_version_id IS DISTINCT FROM id
                AND ((activated_at IS NULL AND activated_by_user_id IS NULL) OR (activated_at IS NOT NULL AND activated_by_user_id IS NOT NULL))
                AND ((retired_at IS NULL AND retired_by_user_id IS NULL) OR (retired_at IS NOT NULL AND retired_by_user_id IS NOT NULL))
                AND ((revoked_at IS NULL AND revoked_by_user_id IS NULL AND revocation_reason IS NULL) OR (revoked_at IS NOT NULL AND revoked_by_user_id IS NOT NULL AND NULLIF(BTRIM(revocation_reason), '') IS NOT NULL))
                AND (status <> 'active' OR (activated_at IS NOT NULL AND retired_at IS NULL))
                AND (status <> 'retired' OR retired_at IS NOT NULL)
                AND (status <> 'candidate' OR (activated_at IS NULL AND retired_at IS NULL))
                AND (
                    (manifest_schema_version IN (1, 2) AND template_id IS NOT NULL AND booklet_id IS NOT NULL
                        AND template_version_id IS NOT NULL AND booklet_version_id IS NOT NULL AND booklet_set_id IS NOT NULL
                        AND booklet_set_code = 'A' AND booklet_set_order_hash IS NOT NULL AND booklet_set_answer_key_hash IS NOT NULL)
                    OR
                    (manifest_schema_version = 3 AND test_instrument_version_id IS NOT NULL
                        AND ((template_version_id IS NOT NULL) = COALESCE((manifest_json::jsonb #> '{requirements,required_resources}') ? 'answer_sheet', false))
                        AND ((template_version_id IS NULL) = (template_id IS NULL))
                        AND ((booklet_version_id IS NOT NULL) = COALESCE((manifest_json::jsonb #> '{requirements,required_resources}') ? 'booklet_form', false))
                        AND ((booklet_set_id IS NOT NULL) = COALESCE((manifest_json::jsonb #> '{requirements,required_resources}') ? 'booklet_form', false))
                        AND ((booklet_version_id IS NULL) = (booklet_id IS NULL))
                        AND ((booklet_set_id IS NULL) = (booklet_set_code IS NULL))
                        AND ((booklet_set_id IS NULL) = (booklet_set_order_hash IS NULL))
                        AND ((booklet_set_answer_key_hash IS NOT NULL) = COALESCE((manifest_json::jsonb #> '{requirements,required_resources}') ? 'key', false))
                        AND (NOT COALESCE((manifest_json::jsonb #> '{requirements,required_resources}') ? 'key', false)
                            OR COALESCE((manifest_json::jsonb #> '{requirements,required_resources}') ? 'booklet_form', false))
                        AND (booklet_set_id IS NULL OR booklet_set_code = 'A')
                        AND (manifest_json::jsonb #> '{requirements,schema_version}' = '1'::jsonb)
                        AND (manifest_json::jsonb #>> '{requirements,omr_applicable}' <> 'true'
                            OR (template_version_id IS NOT NULL AND booklet_version_id IS NOT NULL AND booklet_set_id IS NOT NULL)))
                )
            )
        SQL);
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION nexiam_test_kit_version_owner_guard()
            RETURNS trigger LANGUAGE plpgsql AS $function$
            DECLARE
                family test_kits%ROWTYPE;
                instrument test_instruments%ROWTYPE;
                profile test_instrument_versions%ROWTYPE;
                declared_metrics jsonb;
                declared_resources jsonb;
                declared_omr boolean;
                selected_set omr_booklet_sets%ROWTYPE;
            BEGIN
                IF NEW.institution_id IS NULL OR NEW.is_synthetic IS NULL OR NEW.test_instrument_id IS NULL
                    OR NEW.test_instrument_version_id IS NULL THEN
                    RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'new test kit versions require scoring owner provenance';
                END IF;
                SELECT * INTO family FROM test_kits WHERE id = NEW.test_kit_id FOR KEY SHARE;
                SELECT * INTO instrument FROM test_instruments WHERE id = NEW.test_instrument_id FOR KEY SHARE;
                SELECT * INTO profile FROM test_instrument_versions WHERE id = NEW.test_instrument_version_id FOR SHARE;
                IF family.id IS NULL OR instrument.id IS NULL OR profile.id IS NULL
                    OR (family.institution_id, family.is_synthetic) IS DISTINCT FROM (NEW.institution_id, NEW.is_synthetic)
                    OR (instrument.institution_id, instrument.is_synthetic) IS DISTINCT FROM (NEW.institution_id, NEW.is_synthetic)
                    OR profile.test_instrument_id IS DISTINCT FROM NEW.test_instrument_id THEN
                    RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'test kit scoring owner provenance does not match its parents';
                END IF;
                IF NEW.manifest_schema_version = 3
                    AND ((TG_OP = 'INSERT')
                        OR (TG_OP = 'UPDATE' AND OLD.composition_rows_sealed_at IS NULL AND NEW.composition_rows_sealed_at IS NOT NULL))
                    AND profile.status <> 'approved' THEN
                    RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'schema-3 Test Kit sealing requires an approved scoring profile';
                END IF;
                IF NEW.manifest_schema_version IN (1, 2) AND (NEW.template_id IS NULL OR NEW.booklet_id IS NULL) THEN
                    RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'historical Test Kit generations require their paper owners';
                END IF;
                IF NEW.manifest_schema_version = 3 THEN
                    IF NOT EXISTS (SELECT 1 FROM test_instrument_metrics m WHERE m.test_instrument_version_id = NEW.test_instrument_version_id) THEN
                        RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'schema-3 composition requires at least one approved profile metric';
                    END IF;
                    IF EXISTS (SELECT 1 FROM test_instrument_metrics m WHERE m.test_instrument_version_id = NEW.test_instrument_version_id AND m.input_contract_json IS NULL) THEN
                        RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'schema-3 composition requires declared input contracts for every profile metric';
                    END IF;
                    SELECT COALESCE(jsonb_agg(jsonb_build_object(
                        'metric_id', m.id, 'code', m.code, 'input_contract_json', m.input_contract_json
                    ) ORDER BY m.id), '[]'::jsonb)
                    INTO declared_metrics
                    FROM test_instrument_metrics m
                    WHERE m.test_instrument_version_id = NEW.test_instrument_version_id;
                    SELECT COALESCE(jsonb_agg(resources.resource ORDER BY resources.resource), '[]'::jsonb)
                    INTO declared_resources
                    FROM (
                        SELECT DISTINCT jsonb_array_elements_text(m.input_contract_json::jsonb -> 'required_resources') AS resource
                        FROM test_instrument_metrics m
                        WHERE m.test_instrument_version_id = NEW.test_instrument_version_id
                    ) resources;
                    SELECT EXISTS (
                        SELECT 1 FROM test_instrument_metrics m
                        CROSS JOIN LATERAL jsonb_array_elements_text(m.input_contract_json::jsonb -> 'allowed_sources') source(value)
                        WHERE m.test_instrument_version_id = NEW.test_instrument_version_id AND source.value = 'omr'
                    ) INTO declared_omr;
                    IF NEW.manifest_json::jsonb #> '{requirements,metrics}' IS DISTINCT FROM declared_metrics
                        OR NEW.manifest_json::jsonb #> '{requirements,required_resources}' IS DISTINCT FROM declared_resources
                        OR (NEW.manifest_json::jsonb #>> '{requirements,omr_applicable}' = 'true') IS DISTINCT FROM declared_omr THEN
                        RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'schema-3 manifest requirements do not match approved profile declarations';
                    END IF;
                    IF NEW.template_version_id IS NOT NULL AND NOT EXISTS (
                        SELECT 1 FROM omr_answer_sheet_templates s WHERE s.id = NEW.template_id
                            AND s.institution_id = NEW.institution_id AND s.is_synthetic = NEW.is_synthetic
                    ) THEN
                        RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'Test Kit Answer Sheet owner does not match its institution and provenance';
                    END IF;
                    IF NEW.booklet_version_id IS NOT NULL AND NOT EXISTS (
                        SELECT 1 FROM omr_question_booklets b WHERE b.id = NEW.booklet_id
                            AND b.institution_id = NEW.institution_id AND b.is_synthetic = NEW.is_synthetic
                    ) THEN
                        RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'Test Kit Booklet owner does not match its institution and provenance';
                    END IF;
                    IF NEW.booklet_set_id IS NOT NULL THEN
                        SELECT * INTO selected_set FROM omr_booklet_sets WHERE id = NEW.booklet_set_id FOR KEY SHARE;
                        IF selected_set.id IS NULL
                            OR selected_set.booklet_version_id IS DISTINCT FROM NEW.booklet_version_id
                            OR selected_set.set_code IS DISTINCT FROM NEW.booklet_set_code
                            OR selected_set.order_hash IS DISTINCT FROM NEW.booklet_set_order_hash
                            OR (COALESCE((NEW.manifest_json::jsonb #> '{requirements,required_resources}') ? 'key', false)
                                AND selected_set.answer_key_hash IS DISTINCT FROM NEW.booklet_set_answer_key_hash)
                            OR (NOT COALESCE((NEW.manifest_json::jsonb #> '{requirements,required_resources}') ? 'key', false)
                                AND NEW.booklet_set_answer_key_hash IS NOT NULL) THEN
                            RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'schema-3 Set identity and optional key hash do not match the exact Booklet Set';
                        END IF;
                    END IF;
                END IF;
                IF TG_OP = 'UPDATE' AND (
                    OLD.institution_id IS DISTINCT FROM NEW.institution_id OR OLD.is_synthetic IS DISTINCT FROM NEW.is_synthetic
                    OR OLD.test_instrument_id IS DISTINCT FROM NEW.test_instrument_id
                    OR OLD.template_id IS DISTINCT FROM NEW.template_id OR OLD.booklet_id IS DISTINCT FROM NEW.booklet_id
                ) THEN
                    RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'test kit version owner provenance is immutable';
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
            throw new RuntimeException('The SQLite Test Kit table rebuild cannot run inside an existing transaction.');
        }
        $foreignKeyState = DB::selectOne('PRAGMA foreign_keys');
        $legacyAlterState = DB::selectOne('PRAGMA legacy_alter_table');
        if ((int) ($foreignKeyState->foreign_keys ?? 0) !== 1 || ! in_array((int) ($legacyAlterState->legacy_alter_table ?? -1), [0, 1], true)) {
            throw new RuntimeException('SQLite foreign-key or legacy ALTER TABLE state is unavailable.');
        }
        $legacy = (int) $legacyAlterState->legacy_alter_table;
        $row = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'test_kit_versions'");
        if (! is_string($row?->sql ?? null) || DB::table('sqlite_master')->where('type', 'table')->where('name', self::SQLITE_REBUILD_TABLE)->exists()) {
            throw new RuntimeException('SQLite Test Kit version DDL is unavailable or a rebuild table already exists.');
        }
        $indexes = DB::select("SELECT sql FROM sqlite_master WHERE type = 'index' AND tbl_name = 'test_kit_versions' AND sql IS NOT NULL");
        $triggers = DB::select("SELECT sql FROM sqlite_master WHERE type = 'trigger' AND tbl_name = 'test_kit_versions' AND sql IS NOT NULL");
        $columns = array_map(static fn (object $column): string => '"'.str_replace('"', '""', $column->name).'"', DB::select("PRAGMA table_info('test_kit_versions')"));
        $sql = $row->sql;
        $open = strpos($sql, '(');
        $close = strrpos($sql, ')');
        if ($open === false || $close === false || $close <= $open) {
            throw new RuntimeException('SQLite Test Kit version table definition is malformed.');
        }
        $body = substr($sql, $open + 1, $close - $open - 1);
        foreach (['template_version_id', 'booklet_version_id', 'booklet_set_id', 'booklet_set_code', 'booklet_set_order_hash', 'booklet_set_answer_key_hash'] as $name) {
            $body = preg_replace('/("?'.preg_quote($name, '/').'"?\s+[^,]*?)\s+NOT\s+NULL/i', '$1 NULL', $body, 1, $count);
            if ($count !== 1) {
                throw new RuntimeException("SQLite Test Kit version column {$name} was not found for conditional composition.");
            }
        }
        $body = preg_replace("/(\"?booklet_set_code\"?[^,]*?)\\s+DEFAULT\\s+\(?['\"]A['\"]\)?/i", '$1', $body, 1, $defaultCount);
        if ($defaultCount !== 1) {
            throw new RuntimeException('SQLite Test Kit Set code default was not found for conditional composition.');
        }
        $prefix = substr($sql, 0, $open);
        $prefix = preg_replace('/["`\[]?test_kit_versions["`\]]?/i', '"'.self::SQLITE_REBUILD_TABLE.'"', $prefix, 1);
        DB::statement('PRAGMA foreign_keys = OFF');
        try {
            DB::statement('PRAGMA legacy_alter_table = ON');
            $connection->transaction(function () use ($body, $prefix, $columns, $indexes, $triggers): void {
                DB::statement($prefix.'('.$body.')');
                $list = implode(', ', $columns);
                DB::statement('INSERT INTO '.self::SQLITE_REBUILD_TABLE.' ('.$list.') SELECT '.$list.' FROM test_kit_versions');
                DB::statement('DROP TABLE test_kit_versions');
                DB::statement('ALTER TABLE '.self::SQLITE_REBUILD_TABLE.' RENAME TO test_kit_versions');
                foreach ($indexes as $index) {
                    DB::statement($index->sql);
                }
                foreach ($triggers as $trigger) {
                    DB::statement($trigger->sql);
                }
                if (DB::select('PRAGMA foreign_key_check') !== []) {
                    throw new RuntimeException('SQLite conditional Test Kit rebuild found foreign-key violations.');
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

    private function installSqliteGuards(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS test_kit_versions_lifecycle_insert_check;
            DROP TRIGGER IF EXISTS test_kit_versions_lifecycle_update_check;
            CREATE TRIGGER test_kit_versions_lifecycle_insert_check
            BEFORE INSERT ON test_kit_versions
            FOR EACH ROW
            WHEN NEW.status NOT IN ('candidate', 'active', 'retired')
                OR NEW.revision < 1
                OR NEW.predecessor_version_id = NEW.id
                OR NOT ((NEW.activated_at IS NULL AND NEW.activated_by_user_id IS NULL) OR (NEW.activated_at IS NOT NULL AND NEW.activated_by_user_id IS NOT NULL))
                OR NOT ((NEW.retired_at IS NULL AND NEW.retired_by_user_id IS NULL) OR (NEW.retired_at IS NOT NULL AND NEW.retired_by_user_id IS NOT NULL))
                OR NOT ((NEW.revoked_at IS NULL AND NEW.revoked_by_user_id IS NULL AND NEW.revocation_reason IS NULL) OR (NEW.revoked_at IS NOT NULL AND NEW.revoked_by_user_id IS NOT NULL AND length(trim(NEW.revocation_reason)) > 0))
                OR (NEW.status = 'active' AND (NEW.activated_at IS NULL OR NEW.retired_at IS NOT NULL))
                OR (NEW.status = 'retired' AND NEW.retired_at IS NULL)
                OR (NEW.status = 'candidate' AND (NEW.activated_at IS NOT NULL OR NEW.retired_at IS NOT NULL))
                OR (NEW.manifest_schema_version IN (1, 2) AND (NEW.template_id IS NULL OR NEW.booklet_id IS NULL OR NEW.template_version_id IS NULL OR NEW.booklet_version_id IS NULL OR NEW.booklet_set_id IS NULL OR NEW.booklet_set_code <> 'A' OR NEW.booklet_set_order_hash IS NULL OR NEW.booklet_set_answer_key_hash IS NULL))
                OR (NEW.manifest_schema_version = 3 AND (
                    NEW.test_instrument_version_id IS NULL
                    OR ((NEW.template_version_id IS NOT NULL) <> EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'answer_sheet'))
                    OR ((NEW.template_version_id IS NULL) <> (NEW.template_id IS NULL))
                    OR ((NEW.booklet_version_id IS NOT NULL) <> EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'booklet_form'))
                    OR ((NEW.booklet_version_id IS NULL) <> (NEW.booklet_id IS NULL))
                    OR ((NEW.booklet_set_id IS NOT NULL) <> EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'booklet_form'))
                    OR (NEW.booklet_set_id IS NOT NULL AND NEW.booklet_version_id IS NULL)
                    OR (NEW.booklet_set_id IS NOT NULL AND NEW.booklet_set_code <> 'A')
                    OR ((NEW.booklet_set_id IS NULL) <> (NEW.booklet_set_code IS NULL))
                    OR ((NEW.booklet_set_id IS NULL) <> (NEW.booklet_set_order_hash IS NULL))
                    OR ((NEW.booklet_set_answer_key_hash IS NOT NULL) <> EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'key'))
                    OR (NEW.booklet_set_id IS NOT NULL AND NOT EXISTS (
                        SELECT 1 FROM omr_booklet_sets s
                        WHERE s.id = NEW.booklet_set_id AND s.booklet_version_id = NEW.booklet_version_id
                            AND s.set_code = NEW.booklet_set_code AND s.order_hash = NEW.booklet_set_order_hash
                            AND ((EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'key')
                                    AND s.answer_key_hash = NEW.booklet_set_answer_key_hash)
                                OR (NOT EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'key')
                                    AND NEW.booklet_set_answer_key_hash IS NULL))
                    ))
                    OR (EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'key') AND NOT EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'booklet_form'))
                    OR json_extract(NEW.manifest_json, '$.requirements.schema_version') IS NOT 1
                    OR json_type(NEW.manifest_json, '$.requirements.required_resources') IS NOT 'array'
                    OR json_type(NEW.manifest_json, '$.requirements.metrics') IS NOT 'array'
                    OR COALESCE(json_type(NEW.manifest_json, '$.requirements.omr_applicable'), '') NOT IN ('true', 'false')
                    OR (json_extract(NEW.manifest_json, '$.requirements.omr_applicable') = 1 AND (NEW.template_version_id IS NULL OR NEW.booklet_version_id IS NULL OR NEW.booklet_set_id IS NULL))
                    OR (SELECT count(*) FROM test_instrument_metrics m WHERE m.test_instrument_version_id = NEW.test_instrument_version_id) = 0
                    OR EXISTS (SELECT 1 FROM test_instrument_metrics m WHERE m.test_instrument_version_id = NEW.test_instrument_version_id AND m.input_contract_json IS NULL)
                    OR (SELECT count(*) FROM json_each(NEW.manifest_json, '$.requirements.metrics')) <> (SELECT count(*) FROM test_instrument_metrics m WHERE m.test_instrument_version_id = NEW.test_instrument_version_id)
                    OR (SELECT count(DISTINCT json_extract(value, '$.metric_id')) FROM json_each(NEW.manifest_json, '$.requirements.metrics')) <> (SELECT count(*) FROM test_instrument_metrics m WHERE m.test_instrument_version_id = NEW.test_instrument_version_id)
                    OR EXISTS (
                        SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.metrics') first
                        JOIN json_each(NEW.manifest_json, '$.requirements.metrics') second ON CAST(first.key AS INTEGER) < CAST(second.key AS INTEGER)
                        WHERE json_extract(first.value, '$.metric_id') >= json_extract(second.value, '$.metric_id')
                    )
                    OR (SELECT count(*) FROM json_each(NEW.manifest_json, '$.requirements.required_resources')) <> (SELECT count(DISTINCT value) FROM json_each(NEW.manifest_json, '$.requirements.required_resources'))
                    OR EXISTS (
                        SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') first
                        JOIN json_each(NEW.manifest_json, '$.requirements.required_resources') second ON CAST(first.key AS INTEGER) < CAST(second.key AS INTEGER)
                        WHERE first.value >= second.value
                    )
                    OR EXISTS (
                        SELECT 1 FROM test_instrument_metrics m
                        WHERE m.test_instrument_version_id = NEW.test_instrument_version_id
                            AND NOT EXISTS (
                                SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.metrics') declared
                                WHERE json_extract(declared.value, '$.metric_id') = m.id
                                    AND json_extract(declared.value, '$.code') = m.code
                                    AND NOT EXISTS (
                                        SELECT fullkey, type, atom FROM json_tree(m.input_contract_json)
                                        EXCEPT
                                        SELECT fullkey, type, atom FROM json_tree(json_extract(declared.value, '$.input_contract_json'))
                                    )
                                    AND NOT EXISTS (
                                        SELECT fullkey, type, atom FROM json_tree(json_extract(declared.value, '$.input_contract_json'))
                                        EXCEPT
                                        SELECT fullkey, type, atom FROM json_tree(m.input_contract_json)
                                    )
                            )
                    )
                    OR ((json_extract(NEW.manifest_json, '$.requirements.omr_applicable') = 1) <> EXISTS (
                        SELECT 1 FROM test_instrument_metrics m, json_each(m.input_contract_json, '$.allowed_sources') source
                        WHERE m.test_instrument_version_id = NEW.test_instrument_version_id AND source.value = 'omr'
                    ))
                    OR EXISTS (
                        SELECT 1 FROM test_instrument_metrics m, json_each(m.input_contract_json, '$.required_resources') resource
                        WHERE m.test_instrument_version_id = NEW.test_instrument_version_id
                            AND NOT EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') declared WHERE declared.value = resource.value)
                    )
                    OR EXISTS (
                        SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') declared
                        WHERE NOT EXISTS (
                            SELECT 1 FROM test_instrument_metrics m, json_each(m.input_contract_json, '$.required_resources') resource
                            WHERE m.test_instrument_version_id = NEW.test_instrument_version_id AND resource.value = declared.value
                        )
                    )
                ))
                OR NEW.manifest_schema_version NOT IN (1, 2, 3)
            BEGIN SELECT RAISE(ABORT, 'Test Kit conditional material or lifecycle data is invalid'); END;
            CREATE TRIGGER test_kit_versions_lifecycle_update_check
            BEFORE UPDATE ON test_kit_versions
            FOR EACH ROW
            WHEN NEW.status NOT IN ('candidate', 'active', 'retired')
                OR NEW.revision < 1
                OR NEW.predecessor_version_id = NEW.id
                OR NOT ((NEW.activated_at IS NULL AND NEW.activated_by_user_id IS NULL) OR (NEW.activated_at IS NOT NULL AND NEW.activated_by_user_id IS NOT NULL))
                OR NOT ((NEW.retired_at IS NULL AND NEW.retired_by_user_id IS NULL) OR (NEW.retired_at IS NOT NULL AND NEW.retired_by_user_id IS NOT NULL))
                OR NOT ((NEW.revoked_at IS NULL AND NEW.revoked_by_user_id IS NULL AND NEW.revocation_reason IS NULL) OR (NEW.revoked_at IS NOT NULL AND NEW.revoked_by_user_id IS NOT NULL AND length(trim(NEW.revocation_reason)) > 0))
                OR (NEW.status = 'active' AND (NEW.activated_at IS NULL OR NEW.retired_at IS NOT NULL))
                OR (NEW.status = 'retired' AND NEW.retired_at IS NULL)
                OR (NEW.status = 'candidate' AND (NEW.activated_at IS NOT NULL OR NEW.retired_at IS NOT NULL))
                OR (NEW.manifest_schema_version IN (1, 2) AND (NEW.template_id IS NULL OR NEW.booklet_id IS NULL OR NEW.template_version_id IS NULL OR NEW.booklet_version_id IS NULL OR NEW.booklet_set_id IS NULL OR NEW.booklet_set_code <> 'A' OR NEW.booklet_set_order_hash IS NULL OR NEW.booklet_set_answer_key_hash IS NULL))
                OR (NEW.manifest_schema_version = 3 AND (
                    NEW.test_instrument_version_id IS NULL
                    OR ((NEW.template_version_id IS NOT NULL) <> EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'answer_sheet'))
                    OR ((NEW.template_version_id IS NULL) <> (NEW.template_id IS NULL))
                    OR ((NEW.booklet_version_id IS NOT NULL) <> EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'booklet_form'))
                    OR ((NEW.booklet_version_id IS NULL) <> (NEW.booklet_id IS NULL))
                    OR ((NEW.booklet_set_id IS NOT NULL) <> EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'booklet_form'))
                    OR (NEW.booklet_set_id IS NOT NULL AND NEW.booklet_version_id IS NULL)
                    OR (NEW.booklet_set_id IS NOT NULL AND NEW.booklet_set_code <> 'A')
                    OR ((NEW.booklet_set_id IS NULL) <> (NEW.booklet_set_code IS NULL))
                    OR ((NEW.booklet_set_id IS NULL) <> (NEW.booklet_set_order_hash IS NULL))
                    OR ((NEW.booklet_set_answer_key_hash IS NOT NULL) <> EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'key'))
                    OR (NEW.booklet_set_id IS NOT NULL AND NOT EXISTS (
                        SELECT 1 FROM omr_booklet_sets s
                        WHERE s.id = NEW.booklet_set_id AND s.booklet_version_id = NEW.booklet_version_id
                            AND s.set_code = NEW.booklet_set_code AND s.order_hash = NEW.booklet_set_order_hash
                            AND ((EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'key')
                                    AND s.answer_key_hash = NEW.booklet_set_answer_key_hash)
                                OR (NOT EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'key')
                                    AND NEW.booklet_set_answer_key_hash IS NULL))
                    ))
                    OR (EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'key') AND NOT EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'booklet_form'))
                    OR json_extract(NEW.manifest_json, '$.requirements.schema_version') IS NOT 1
                    OR json_type(NEW.manifest_json, '$.requirements.required_resources') IS NOT 'array'
                    OR json_type(NEW.manifest_json, '$.requirements.metrics') IS NOT 'array'
                    OR COALESCE(json_type(NEW.manifest_json, '$.requirements.omr_applicable'), '') NOT IN ('true', 'false')
                    OR (json_extract(NEW.manifest_json, '$.requirements.omr_applicable') = 1 AND (NEW.template_version_id IS NULL OR NEW.booklet_version_id IS NULL OR NEW.booklet_set_id IS NULL))
                    OR (SELECT count(*) FROM test_instrument_metrics m WHERE m.test_instrument_version_id = NEW.test_instrument_version_id) = 0
                    OR EXISTS (SELECT 1 FROM test_instrument_metrics m WHERE m.test_instrument_version_id = NEW.test_instrument_version_id AND m.input_contract_json IS NULL)
                    OR (SELECT count(*) FROM json_each(NEW.manifest_json, '$.requirements.metrics')) <> (SELECT count(*) FROM test_instrument_metrics m WHERE m.test_instrument_version_id = NEW.test_instrument_version_id)
                    OR (SELECT count(DISTINCT json_extract(value, '$.metric_id')) FROM json_each(NEW.manifest_json, '$.requirements.metrics')) <> (SELECT count(*) FROM test_instrument_metrics m WHERE m.test_instrument_version_id = NEW.test_instrument_version_id)
                    OR EXISTS (
                        SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.metrics') first
                        JOIN json_each(NEW.manifest_json, '$.requirements.metrics') second ON CAST(first.key AS INTEGER) < CAST(second.key AS INTEGER)
                        WHERE json_extract(first.value, '$.metric_id') >= json_extract(second.value, '$.metric_id')
                    )
                    OR (SELECT count(*) FROM json_each(NEW.manifest_json, '$.requirements.required_resources')) <> (SELECT count(DISTINCT value) FROM json_each(NEW.manifest_json, '$.requirements.required_resources'))
                    OR EXISTS (
                        SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') first
                        JOIN json_each(NEW.manifest_json, '$.requirements.required_resources') second ON CAST(first.key AS INTEGER) < CAST(second.key AS INTEGER)
                        WHERE first.value >= second.value
                    )
                    OR EXISTS (
                        SELECT 1 FROM test_instrument_metrics m
                        WHERE m.test_instrument_version_id = NEW.test_instrument_version_id
                            AND NOT EXISTS (
                                SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.metrics') declared
                                WHERE json_extract(declared.value, '$.metric_id') = m.id
                                    AND json_extract(declared.value, '$.code') = m.code
                                    AND NOT EXISTS (
                                        SELECT fullkey, type, atom FROM json_tree(m.input_contract_json)
                                        EXCEPT
                                        SELECT fullkey, type, atom FROM json_tree(json_extract(declared.value, '$.input_contract_json'))
                                    )
                                    AND NOT EXISTS (
                                        SELECT fullkey, type, atom FROM json_tree(json_extract(declared.value, '$.input_contract_json'))
                                        EXCEPT
                                        SELECT fullkey, type, atom FROM json_tree(m.input_contract_json)
                                    )
                            )
                    )
                    OR ((json_extract(NEW.manifest_json, '$.requirements.omr_applicable') = 1) <> EXISTS (
                        SELECT 1 FROM test_instrument_metrics m, json_each(m.input_contract_json, '$.allowed_sources') source
                        WHERE m.test_instrument_version_id = NEW.test_instrument_version_id AND source.value = 'omr'
                    ))
                    OR EXISTS (
                        SELECT 1 FROM test_instrument_metrics m, json_each(m.input_contract_json, '$.required_resources') resource
                        WHERE m.test_instrument_version_id = NEW.test_instrument_version_id
                            AND NOT EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') declared WHERE declared.value = resource.value)
                    )
                    OR EXISTS (
                        SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') declared
                        WHERE NOT EXISTS (
                            SELECT 1 FROM test_instrument_metrics m, json_each(m.input_contract_json, '$.required_resources') resource
                            WHERE m.test_instrument_version_id = NEW.test_instrument_version_id AND resource.value = declared.value
                        )
                    )
                ))
                OR NEW.manifest_schema_version NOT IN (1, 2, 3)
            BEGIN SELECT RAISE(ABORT, 'Test Kit conditional material or lifecycle data is invalid'); END;
            DROP TRIGGER IF EXISTS test_kit_version_owner_insert_guard;
            DROP TRIGGER IF EXISTS test_kit_version_owner_update_guard;
            CREATE TRIGGER test_kit_version_owner_insert_guard
            BEFORE INSERT ON test_kit_versions
            FOR EACH ROW
            WHEN NEW.institution_id IS NULL OR NEW.is_synthetic IS NULL OR NEW.test_instrument_id IS NULL OR NEW.test_instrument_version_id IS NULL
                OR NOT EXISTS (SELECT 1 FROM test_instruments i WHERE i.id = NEW.test_instrument_id AND i.institution_id = NEW.institution_id AND i.is_synthetic = NEW.is_synthetic)
                OR NOT EXISTS (SELECT 1 FROM test_instrument_versions v WHERE v.id = NEW.test_instrument_version_id AND v.test_instrument_id = NEW.test_instrument_id)
                OR (NEW.manifest_schema_version IN (1, 2) AND (NEW.template_id IS NULL OR NEW.booklet_id IS NULL))
                OR (NEW.template_version_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM omr_answer_sheet_templates s WHERE s.id = NEW.template_id AND s.institution_id = NEW.institution_id AND s.is_synthetic = NEW.is_synthetic))
                OR (NEW.booklet_version_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM omr_question_booklets b WHERE b.id = NEW.booklet_id AND b.institution_id = NEW.institution_id AND b.is_synthetic = NEW.is_synthetic))
            BEGIN SELECT RAISE(ABORT, 'Test Kit version owner provenance does not match its parents'); END;
            CREATE TRIGGER test_kit_version_owner_update_guard
            BEFORE UPDATE ON test_kit_versions
            FOR EACH ROW
            WHEN OLD.institution_id IS NULL OR OLD.is_synthetic IS NULL OR OLD.test_instrument_id IS NULL
                OR OLD.institution_id IS NOT NEW.institution_id OR OLD.is_synthetic IS NOT NEW.is_synthetic
                OR OLD.test_instrument_id IS NOT NEW.test_instrument_id OR OLD.template_id IS NOT NEW.template_id OR OLD.booklet_id IS NOT NEW.booklet_id
            BEGIN SELECT RAISE(ABORT, 'test kit version owner provenance is immutable'); END;
            DROP TRIGGER IF EXISTS test_kit_version_profile_approved_on_insert_guard;
            CREATE TRIGGER test_kit_version_profile_approved_on_insert_guard
            BEFORE INSERT ON test_kit_versions
            FOR EACH ROW
            WHEN NEW.manifest_schema_version = 3
                AND NEW.composition_rows_sealed_at IS NOT NULL
                AND NOT EXISTS (
                    SELECT 1 FROM test_instrument_versions v
                    WHERE v.id = NEW.test_instrument_version_id AND v.status = 'approved'
                )
            BEGIN SELECT RAISE(ABORT, 'schema-3 Test Kit sealing requires an approved scoring profile'); END;
            CREATE TRIGGER test_kit_version_profile_approved_at_seal_guard
            BEFORE UPDATE OF composition_rows_sealed_at ON test_kit_versions
            FOR EACH ROW
            WHEN NEW.manifest_schema_version = 3
                AND OLD.composition_rows_sealed_at IS NULL
                AND NEW.composition_rows_sealed_at IS NOT NULL
                AND NOT EXISTS (
                    SELECT 1 FROM test_instrument_versions v
                    WHERE v.id = NEW.test_instrument_version_id AND v.status = 'approved'
                )
            BEGIN SELECT RAISE(ABORT, 'schema-3 Test Kit sealing requires an approved scoring profile'); END;
        SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Conditional Test Kit composition is a forward-only schema generation; use a forward migration instead of restoring mandatory paper fields.');
    }
};
