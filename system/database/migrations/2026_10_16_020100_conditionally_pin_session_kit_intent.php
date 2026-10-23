<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE exam_sessions DROP CONSTRAINT exam_session_kit_pin_completeness_check');
            DB::statement('ALTER TABLE exam_sessions ADD CONSTRAINT exam_session_kit_pin_completeness_check CHECK (test_kit_version_id IS NULL OR test_instrument_version_id IS NOT NULL)');
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION nexiam_exam_session_kit_pin_guard() RETURNS trigger AS $$
                DECLARE
                    kit_row test_kit_versions%ROWTYPE;
                    session_institution_id bigint;
                    requires_booklet boolean;
                BEGIN
                    IF NEW.test_kit_version_id IS NULL THEN
                        RETURN NEW;
                    END IF;

                    IF NEW.test_instrument_version_id IS NULL THEN
                        RAISE EXCEPTION 'exam session Test Kit pins require the exact profile';
                    END IF;

                    SELECT ac.institution_id INTO session_institution_id
                    FROM academic_cycles ac
                    WHERE ac.id = NEW.academic_cycle_id;

                    SELECT * INTO kit_row
                    FROM test_kit_versions
                    WHERE id = NEW.test_kit_version_id;

                    IF NOT FOUND OR kit_row.test_instrument_version_id IS DISTINCT FROM NEW.test_instrument_version_id
                        OR kit_row.booklet_version_id IS DISTINCT FROM NEW.booklet_version_id
                        OR NOT EXISTS (
                            SELECT 1 FROM test_kits tk
                            WHERE tk.id = kit_row.test_kit_id
                                AND tk.institution_id = session_institution_id
                                AND COALESCE((kit_row.manifest_json::jsonb ->> 'institution_id')::bigint, -1) = tk.institution_id
                                AND kit_row.manifest_json::jsonb #>> '{kit,provenance_class}' =
                                    CASE WHEN tk.is_synthetic THEN 'synthetic' ELSE 'institutional' END
                        ) THEN
                        RAISE EXCEPTION 'exam session Test Kit pins must match the exact institution, Booklet, and profile';
                    END IF;

                    IF kit_row.manifest_schema_version IN (1, 2) THEN
                        requires_booklet := true;
                    ELSIF kit_row.manifest_schema_version = 3 THEN
                        IF (kit_row.manifest_json::jsonb #>> '{requirements,schema_version}') IS DISTINCT FROM '1'
                            OR jsonb_typeof(kit_row.manifest_json::jsonb #> '{requirements,required_resources}') IS DISTINCT FROM 'array' THEN
                            RAISE EXCEPTION 'exam session Test Kit requirements are unavailable';
                        END IF;
                        requires_booklet := (kit_row.manifest_json::jsonb #> '{requirements,required_resources}') ? 'booklet_form';
                    ELSE
                        RAISE EXCEPTION 'exam session Test Kit manifest schema is unsupported';
                    END IF;

                    IF requires_booklet IS DISTINCT FROM (NEW.booklet_version_id IS NOT NULL) THEN
                        RAISE EXCEPTION 'exam session Booklet pin must match the Test Kit Setup requirements';
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
            SQL);
            DB::statement('DROP TRIGGER IF EXISTS exam_session_kit_pin_insert_guard ON exam_sessions');
            DB::statement('CREATE TRIGGER exam_session_kit_pin_insert_guard BEFORE INSERT ON exam_sessions FOR EACH ROW EXECUTE FUNCTION nexiam_exam_session_kit_pin_guard()');
            DB::statement('DROP TRIGGER IF EXISTS exam_session_kit_pin_update_guard ON exam_sessions');
            DB::statement('CREATE TRIGGER exam_session_kit_pin_update_guard BEFORE UPDATE OF test_kit_version_id, booklet_version_id, test_instrument_version_id, academic_cycle_id ON exam_sessions FOR EACH ROW EXECUTE FUNCTION nexiam_exam_session_kit_pin_guard()');

            return;
        }

        if ($driver !== 'sqlite') {
            throw new RuntimeException('Conditional session Kit pins require PostgreSQL or SQLite.');
        }

        DB::statement('DROP TRIGGER IF EXISTS exam_session_kit_pin_insert_guard');
        DB::statement('DROP TRIGGER IF EXISTS exam_session_kit_pin_update_guard');

        $condition = <<<'SQL'
            NEW.test_kit_version_id IS NOT NULL AND (
                NEW.test_instrument_version_id IS NULL
                OR NOT EXISTS (
                    SELECT 1
                    FROM test_kit_versions kv
                    JOIN test_kits tk ON tk.id = kv.test_kit_id
                    JOIN academic_cycles ac ON ac.id = NEW.academic_cycle_id
                    WHERE kv.id = NEW.test_kit_version_id
                        AND kv.test_instrument_version_id = NEW.test_instrument_version_id
                        AND kv.booklet_version_id IS NEW.booklet_version_id
                        AND tk.institution_id = ac.institution_id
                        AND json_extract(kv.manifest_json, '$.institution_id') = tk.institution_id
                        AND json_extract(kv.manifest_json, '$.kit.provenance_class') = CASE WHEN tk.is_synthetic THEN 'synthetic' ELSE 'institutional' END
                        AND (
                            (kv.manifest_schema_version IN (1, 2) AND NEW.booklet_version_id IS NOT NULL)
                            OR (kv.manifest_schema_version = 3
                                AND json_extract(kv.manifest_json, '$.requirements.schema_version') = 1
                                AND json_type(kv.manifest_json, '$.requirements.required_resources') = 'array'
                                AND (
                                    (EXISTS (SELECT 1 FROM json_each(kv.manifest_json, '$.requirements.required_resources') WHERE value = 'booklet_form') AND NEW.booklet_version_id IS NOT NULL)
                                    OR (NOT EXISTS (SELECT 1 FROM json_each(kv.manifest_json, '$.requirements.required_resources') WHERE value = 'booklet_form') AND NEW.booklet_version_id IS NULL)
                                ))
                        )
                )
            )
            SQL;

        foreach (['INSERT', 'UPDATE OF test_kit_version_id, booklet_version_id, test_instrument_version_id, academic_cycle_id'] as $operation) {
            $name = str_starts_with($operation, 'INSERT') ? 'insert' : 'update';
            DB::statement("CREATE TRIGGER exam_session_kit_pin_{$name}_guard BEFORE {$operation} ON exam_sessions FOR EACH ROW WHEN {$condition} BEGIN SELECT RAISE(ABORT, 'exam session Test Kit pins must match exact institution, profile, and Setup requirements'); END");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('This conditional session Kit pin migration is forward-only.');
    }
};
