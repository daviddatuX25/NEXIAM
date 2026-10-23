<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE test_kit_preparations DROP CONSTRAINT kit_preparations_state_check');
            DB::statement("ALTER TABLE test_kit_preparations ADD CONSTRAINT kit_preparations_state_check CHECK (state IN ('preparing', 'assembled', 'discarded'))");
            DB::statement('ALTER TABLE test_kit_preparations DROP CONSTRAINT kit_preparations_assembly_context_check');
            DB::statement(<<<'SQL'
                ALTER TABLE test_kit_preparations ADD CONSTRAINT kit_preparations_assembly_context_check CHECK (
                    (state = 'preparing' AND assembled_test_kit_version_id IS NULL AND assembly_context_json IS NULL AND assembly_context_sha256 IS NULL)
                    OR (state = 'discarded' AND assembled_test_kit_version_id IS NULL AND assembly_context_json IS NULL AND assembly_context_sha256 IS NULL)
                    OR (state = 'assembled' AND assembled_test_kit_version_id IS NOT NULL AND assembly_context_json IS NOT NULL AND assembly_context_sha256 ~ '^[0-9a-f]{64}$')
                )
            SQL);
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION nexiam_test_kit_preparation_immutable_closed()
                RETURNS trigger LANGUAGE plpgsql AS $function$
                BEGIN
                    IF TG_OP = 'DELETE' THEN
                        IF OLD.state IN ('assembled', 'discarded') THEN
                            RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'closed Test Kit preparations are immutable history';
                        END IF;
                        RETURN OLD;
                    END IF;
                    IF OLD.state IN ('assembled', 'discarded') THEN
                        RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'closed Test Kit preparations are immutable history';
                    END IF;
                    IF OLD.test_kit_id IS DISTINCT FROM NEW.test_kit_id
                        OR OLD.created_by_user_id IS DISTINCT FROM NEW.created_by_user_id
                        OR OLD.created_at IS DISTINCT FROM NEW.created_at
                        OR OLD.source_context_json::jsonb IS DISTINCT FROM NEW.source_context_json::jsonb
                        OR OLD.source_context_sha256 IS DISTINCT FROM NEW.source_context_sha256
                        OR (NEW.state IN ('assembled', 'discarded') AND (
                            OLD.test_instrument_version_id IS DISTINCT FROM NEW.test_instrument_version_id
                            OR OLD.norm_package_version_id IS DISTINCT FROM NEW.norm_package_version_id
                            OR OLD.answer_sheet_draft_id IS DISTINCT FROM NEW.answer_sheet_draft_id
                            OR OLD.template_version_id IS DISTINCT FROM NEW.template_version_id
                            OR OLD.booklet_version_id IS DISTINCT FROM NEW.booklet_version_id
                            OR OLD.booklet_set_id IS DISTINCT FROM NEW.booklet_set_id
                            OR OLD.composition_revision IS DISTINCT FROM NEW.composition_revision
                        )) THEN
                        RAISE EXCEPTION USING ERRCODE = '55000', MESSAGE = 'Test Kit preparation identity and snapshot selections are immutable';
                    END IF;
                    IF NEW.state NOT IN ('preparing', 'assembled', 'discarded') OR NEW.revision < 1
                        OR (NEW.source_context_json IS NOT NULL AND NEW.source_context_sha256 !~ '^[0-9a-f]{64}$')
                        OR (NEW.source_context_json IS NULL AND NEW.source_context_sha256 IS NOT NULL)
                        OR (NEW.state = 'assembled' AND (NEW.assembled_test_kit_version_id IS NULL OR NEW.assembly_context_json IS NULL OR NEW.assembly_context_sha256 !~ '^[0-9a-f]{64}$'))
                        OR (NEW.state IN ('preparing', 'discarded') AND (NEW.assembled_test_kit_version_id IS NOT NULL OR NEW.assembly_context_json IS NOT NULL OR NEW.assembly_context_sha256 IS NOT NULL))
                        OR (NEW.state = 'discarded' AND OLD.state <> 'preparing') THEN
                        RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'Test Kit preparation state and assembly context are inconsistent';
                    END IF;
                    RETURN NEW;
                END;
                $function$
            SQL);
            $this->upgradeOptionalPostgresGuards();

            return;
        }

        if ($driver !== 'sqlite') {
            throw new RuntimeException('Test Kit preparation lifecycle requires PostgreSQL or SQLite.');
        }

        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS kit_preparations_state_insert_check;
            DROP TRIGGER IF EXISTS kit_preparations_state_update_check;
            DROP TRIGGER IF EXISTS kit_preparations_closed_delete_guard;
            CREATE TRIGGER kit_preparations_state_insert_check
            BEFORE INSERT ON test_kit_preparations FOR EACH ROW
            WHEN NEW.state NOT IN ('preparing', 'assembled', 'discarded') OR NEW.revision < 1
                OR (NEW.source_context_json IS NOT NULL AND (NEW.source_context_sha256 IS NULL OR length(NEW.source_context_sha256) <> 64 OR NEW.source_context_sha256 GLOB '*[^0-9a-f]*'))
                OR (NEW.source_context_json IS NULL AND NEW.source_context_sha256 IS NOT NULL)
                OR (NEW.state = 'assembled' AND (NEW.assembled_test_kit_version_id IS NULL OR NEW.assembly_context_json IS NULL OR length(NEW.assembly_context_sha256) <> 64 OR NEW.assembly_context_sha256 GLOB '*[^0-9a-f]*'))
                OR (NEW.state IN ('preparing', 'discarded') AND (NEW.assembled_test_kit_version_id IS NOT NULL OR NEW.assembly_context_json IS NOT NULL OR NEW.assembly_context_sha256 IS NOT NULL))
            BEGIN SELECT RAISE(ABORT, 'Test Kit preparation state and context are inconsistent'); END;
            CREATE TRIGGER kit_preparations_state_update_check
            BEFORE UPDATE ON test_kit_preparations FOR EACH ROW
            WHEN OLD.state IN ('assembled', 'discarded')
                OR OLD.test_kit_id IS NOT NEW.test_kit_id
                OR OLD.created_by_user_id IS NOT NEW.created_by_user_id
                OR OLD.created_at IS NOT NEW.created_at
                OR OLD.source_context_json IS NOT NEW.source_context_json
                OR OLD.source_context_sha256 IS NOT NEW.source_context_sha256
                OR (NEW.state IN ('assembled', 'discarded') AND (
                    OLD.test_instrument_version_id IS NOT NEW.test_instrument_version_id
                    OR OLD.norm_package_version_id IS NOT NEW.norm_package_version_id
                    OR OLD.answer_sheet_draft_id IS NOT NEW.answer_sheet_draft_id
                    OR OLD.template_version_id IS NOT NEW.template_version_id
                    OR OLD.booklet_version_id IS NOT NEW.booklet_version_id
                    OR OLD.booklet_set_id IS NOT NEW.booklet_set_id
                    OR OLD.composition_revision IS NOT NEW.composition_revision
                ))
                OR NEW.state NOT IN ('preparing', 'assembled', 'discarded') OR NEW.revision < 1
                OR (NEW.source_context_json IS NOT NULL AND (NEW.source_context_sha256 IS NULL OR length(NEW.source_context_sha256) <> 64 OR NEW.source_context_sha256 GLOB '*[^0-9a-f]*'))
                OR (NEW.source_context_json IS NULL AND NEW.source_context_sha256 IS NOT NULL)
                OR (NEW.state = 'assembled' AND (NEW.assembled_test_kit_version_id IS NULL OR NEW.assembly_context_json IS NULL OR length(NEW.assembly_context_sha256) <> 64 OR NEW.assembly_context_sha256 GLOB '*[^0-9a-f]*'))
                OR (NEW.state IN ('preparing', 'discarded') AND (NEW.assembled_test_kit_version_id IS NOT NULL OR NEW.assembly_context_json IS NOT NULL OR NEW.assembly_context_sha256 IS NOT NULL))
                OR (NEW.state = 'discarded' AND OLD.state <> 'preparing')
            BEGIN SELECT RAISE(ABORT, 'Test Kit preparation state and context are inconsistent'); END;
            CREATE TRIGGER kit_preparations_closed_delete_guard
            BEFORE DELETE ON test_kit_preparations FOR EACH ROW
            WHEN OLD.state IN ('assembled', 'discarded')
            BEGIN SELECT RAISE(ABORT, 'Closed Test Kit preparations are immutable history'); END;
        SQL);
        $this->upgradeOptionalSqliteGuards();
    }

    private function upgradeOptionalPostgresGuards(): void
    {
        DB::statement('ALTER TABLE test_kit_versions DROP CONSTRAINT IF EXISTS test_kit_versions_lifecycle_check');
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
                        AND ((
                            ((template_version_id IS NOT NULL) = COALESCE((manifest_json::jsonb #> '{requirements,required_resources}') ? 'answer_sheet', false))
                            AND ((template_version_id IS NULL) = (template_id IS NULL))
                            AND ((booklet_version_id IS NOT NULL) = COALESCE((manifest_json::jsonb #> '{requirements,required_resources}') ? 'booklet_form', false))
                            AND ((booklet_set_id IS NOT NULL) = COALESCE((manifest_json::jsonb #> '{requirements,required_resources}') ? 'booklet_form', false))
                            AND ((booklet_version_id IS NULL) = (booklet_id IS NULL))
                            AND ((booklet_set_id IS NULL) = (booklet_set_code IS NULL))
                            AND ((booklet_set_id IS NULL) = (booklet_set_order_hash IS NULL))
                            AND ((booklet_set_answer_key_hash IS NOT NULL) = COALESCE((manifest_json::jsonb #> '{requirements,required_resources}') ? 'key', false))
                            AND (NOT COALESCE((manifest_json::jsonb #> '{requirements,required_resources}') ? 'key', false)
                                OR COALESCE((manifest_json::jsonb #> '{requirements,required_resources}') ? 'booklet_form', false))
                        ) OR (
                            manifest_json::jsonb #>> '{optional_attachment,version}' = '1'
                            AND (NOT COALESCE((manifest_json::jsonb #> '{requirements,required_resources}') ? 'answer_sheet', false)
                                OR NOT COALESCE((manifest_json::jsonb #> '{requirements,required_resources}') ? 'booklet_form', false))
                            AND template_id IS NOT NULL AND template_version_id IS NOT NULL
                            AND booklet_id IS NOT NULL AND booklet_version_id IS NOT NULL
                            AND booklet_set_id IS NOT NULL AND booklet_set_code = 'A'
                            AND booklet_set_order_hash IS NOT NULL AND booklet_set_answer_key_hash IS NOT NULL
                            AND manifest_json::jsonb #>> '{optional_attachment,answer_sheet_version_id}' = template_version_id::text
                            AND manifest_json::jsonb #>> '{optional_attachment,booklet_version_id}' = booklet_version_id::text
                            AND manifest_json::jsonb #>> '{optional_attachment,booklet_set_id}' = booklet_set_id::text
                            AND manifest_json::jsonb #>> '{optional_attachment,composition_sha256}' = composition_sha256
                            AND composition_revision IS NOT NULL
                            AND manifest_json::jsonb #> '{composition,rows}' IS NOT NULL
                            AND manifest_json::jsonb #> '{item_map,items}' IS NOT NULL
                        ))
                        AND (booklet_set_id IS NULL OR booklet_set_code = 'A')
                        AND manifest_json::jsonb #> '{requirements,schema_version}' = '1'::jsonb
                        AND (manifest_json::jsonb #>> '{requirements,omr_applicable}' <> 'true'
                            OR (template_version_id IS NOT NULL AND booklet_version_id IS NOT NULL AND booklet_set_id IS NOT NULL)))
                )
            )
        SQL);

        $this->replacePostgresFunctionFragment(
            'nexiam_test_kit_version_owner_guard',
            <<<'SQL'
                            OR (COALESCE((NEW.manifest_json::jsonb #> '{requirements,required_resources}') ? 'key', false)
                                AND selected_set.answer_key_hash IS DISTINCT FROM NEW.booklet_set_answer_key_hash)
                            OR (NOT COALESCE((NEW.manifest_json::jsonb #> '{requirements,required_resources}') ? 'key', false)
                                AND NEW.booklet_set_answer_key_hash IS NOT NULL) THEN
            SQL,
            <<<'SQL'
                            OR ((COALESCE((NEW.manifest_json::jsonb #> '{requirements,required_resources}') ? 'key', false)
                                    OR NEW.manifest_json::jsonb #>> '{optional_attachment,version}' = '1')
                                AND selected_set.answer_key_hash IS DISTINCT FROM NEW.booklet_set_answer_key_hash)
                            OR (NOT (COALESCE((NEW.manifest_json::jsonb #> '{requirements,required_resources}') ? 'key', false)
                                    OR NEW.manifest_json::jsonb #>> '{optional_attachment,version}' = '1')
                                AND NEW.booklet_set_answer_key_hash IS NOT NULL) THEN
            SQL,
        );
        $this->replacePostgresFunctionFragment(
            'nexiam_test_kit_preparation_composition_revision_guard',
            <<<'SQL'
                    IF (NEW.template_version_id IS NOT NULL) IS DISTINCT FROM requires_sheet
                        OR (NEW.booklet_version_id IS NOT NULL) IS DISTINCT FROM requires_booklet
                        OR (NEW.booklet_set_id IS NOT NULL) IS DISTINCT FROM requires_booklet
                        OR (requires_key AND NOT requires_booklet)
                        OR (omr_applicable AND (NOT requires_sheet OR NOT requires_booklet OR NOT requires_key)) THEN
            SQL,
            <<<'SQL'
                    IF (
                        ((NEW.template_version_id IS NOT NULL) IS DISTINCT FROM requires_sheet
                            OR (NEW.booklet_version_id IS NOT NULL) IS DISTINCT FROM requires_booklet
                            OR (NEW.booklet_set_id IS NOT NULL) IS DISTINCT FROM requires_booklet)
                        AND NOT (NEW.template_version_id IS NOT NULL AND NEW.booklet_version_id IS NOT NULL AND NEW.booklet_set_id IS NOT NULL
                            AND (NOT requires_sheet OR NOT requires_booklet))
                    ) OR (requires_key AND NOT requires_booklet)
                        OR (omr_applicable AND (NOT requires_sheet OR NOT requires_booklet OR NOT requires_key)) THEN
            SQL,
        );
        $this->replacePostgresFunctionFragment(
            'nexiam_test_kit_preparation_composition_revision_guard',
            <<<'SQL'
                        OR (requires_key AND NEW.component_vector_json::jsonb #>> '{booklet,set_answer_key_hash}' IS DISTINCT FROM selected_set.answer_key_hash)
                        OR (NOT requires_key AND NEW.component_vector_json::jsonb #>> '{booklet,set_answer_key_hash}' IS NOT NULL) THEN
            SQL,
            <<<'SQL'
                        OR ((requires_key OR NEW.component_vector_json::jsonb #>> '{sheet,physical_inventory_sha256}' IS NOT NULL)
                            AND NEW.component_vector_json::jsonb #>> '{booklet,set_answer_key_hash}' IS DISTINCT FROM selected_set.answer_key_hash)
                        OR (NOT (requires_key OR NEW.component_vector_json::jsonb #>> '{sheet,physical_inventory_sha256}' IS NOT NULL)
                            AND NEW.component_vector_json::jsonb #>> '{booklet,set_answer_key_hash}' IS NOT NULL) THEN
            SQL,
        );
        $this->replacePostgresFunctionFragment(
            'nexiam_assessment_binding_guard',
            <<<'SQL'
                            AND ((k.template_version_id IS NOT NULL)=COALESCE((k.manifest_json::jsonb #> '{requirements,required_resources}') ? 'answer_sheet',false))
                            AND ((k.booklet_version_id IS NOT NULL)=COALESCE((k.manifest_json::jsonb #> '{requirements,required_resources}') ? 'booklet_form',false))
                            AND ((k.booklet_set_id IS NOT NULL)=COALESCE((k.manifest_json::jsonb #> '{requirements,required_resources}') ? 'booklet_form',false))
                            AND ((k.booklet_set_answer_key_hash IS NOT NULL)=COALESCE((k.manifest_json::jsonb #> '{requirements,required_resources}') ? 'key',false))
            SQL,
            <<<'SQL'
                            AND (((k.template_version_id IS NOT NULL)=COALESCE((k.manifest_json::jsonb #> '{requirements,required_resources}') ? 'answer_sheet',false))
                                OR k.manifest_json::jsonb #>> '{optional_attachment,version}' = '1')
                            AND (((k.booklet_version_id IS NOT NULL)=COALESCE((k.manifest_json::jsonb #> '{requirements,required_resources}') ? 'booklet_form',false))
                                OR k.manifest_json::jsonb #>> '{optional_attachment,version}' = '1')
                            AND (((k.booklet_set_id IS NOT NULL)=COALESCE((k.manifest_json::jsonb #> '{requirements,required_resources}') ? 'booklet_form',false))
                                OR k.manifest_json::jsonb #>> '{optional_attachment,version}' = '1')
                            AND (((k.booklet_set_answer_key_hash IS NOT NULL)=COALESCE((k.manifest_json::jsonb #> '{requirements,required_resources}') ? 'key',false))
                                OR (k.booklet_set_answer_key_hash IS NOT NULL AND k.manifest_json::jsonb #>> '{optional_attachment,version}' = '1'))
            SQL,
        );
        $this->replacePostgresFunctionFragment(
            'nexiam_exam_session_kit_pin_guard',
            "requires_booklet := (kit_row.manifest_json::jsonb #> '{requirements,required_resources}') ? 'booklet_form';",
            "requires_booklet := (kit_row.manifest_json::jsonb #> '{requirements,required_resources}') ? 'booklet_form'\n                            OR kit_row.manifest_json::jsonb #>> '{optional_attachment,version}' = '1';",
        );
    }

    private function replacePostgresFunctionFragment(string $function, string $expected, string $replacement): void
    {
        $definition = DB::selectOne('SELECT pg_get_functiondef(?::regprocedure) AS definition', [$function.'()'])?->definition;
        $pattern = '/'.implode('\s+', array_map(static fn (string $part): string => preg_quote($part, '/'), preg_split('/\s+/', trim($expected)) ?: [])).'/';
        if (! is_string($definition) || preg_match_all($pattern, $definition) !== 1) {
            throw new RuntimeException("The PostgreSQL {$function} definition did not match its expected forward-migration source.");
        }
        DB::unprepared((string) preg_replace($pattern, addcslashes($replacement, '\\$'), $definition, 1));
    }

    private function upgradeOptionalSqliteGuards(): void
    {
        foreach (['test_kit_versions_lifecycle_insert_check', 'test_kit_versions_lifecycle_update_check'] as $trigger) {
            $this->replaceSqliteTriggerFragment(
                $trigger,
                "((NEW.template_version_id IS NOT NULL) <> EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'answer_sheet'))",
                "(((NEW.template_version_id IS NOT NULL) <> EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'answer_sheet')) AND json_extract(NEW.manifest_json, '$.optional_attachment.version') IS NOT 1)",
            );
            $this->replaceSqliteTriggerFragment(
                $trigger,
                "((NEW.booklet_version_id IS NOT NULL) <> EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'booklet_form'))",
                "(((NEW.booklet_version_id IS NOT NULL) <> EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'booklet_form')) AND json_extract(NEW.manifest_json, '$.optional_attachment.version') IS NOT 1)",
            );
            $this->replaceSqliteTriggerFragment(
                $trigger,
                "((NEW.booklet_set_id IS NOT NULL) <> EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'booklet_form'))",
                "(((NEW.booklet_set_id IS NOT NULL) <> EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'booklet_form')) AND json_extract(NEW.manifest_json, '$.optional_attachment.version') IS NOT 1)",
            );
            $this->replaceSqliteTriggerFragment(
                $trigger,
                "((NEW.booklet_set_answer_key_hash IS NOT NULL) <> EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'key'))",
                "(((NEW.booklet_set_answer_key_hash IS NOT NULL) <> EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'key')) AND json_extract(NEW.manifest_json, '$.optional_attachment.version') IS NOT 1)",
            );
            $this->replaceSqliteTriggerFragment(
                $trigger,
                "OR (NOT EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'key')\n                                    AND NEW.booklet_set_answer_key_hash IS NULL)",
                "OR ((json_extract(NEW.manifest_json, '$.optional_attachment.version') = 1 AND EXISTS (SELECT 1 FROM omr_booklet_sets s WHERE s.id = NEW.booklet_set_id AND s.answer_key_hash = NEW.booklet_set_answer_key_hash))\n                                    OR (json_extract(NEW.manifest_json, '$.optional_attachment.version') IS NOT 1 AND NOT EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'key') AND NEW.booklet_set_answer_key_hash IS NULL))",
            );
            $this->appendSqliteTriggerGuard($trigger, <<<'SQL'
                OR (json_extract(NEW.manifest_json, '$.optional_attachment.version') = 1 AND (
                    NEW.template_version_id IS NULL OR NEW.booklet_version_id IS NULL OR NEW.booklet_set_id IS NULL
                    OR (EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'answer_sheet')
                        AND EXISTS (SELECT 1 FROM json_each(NEW.manifest_json, '$.requirements.required_resources') WHERE value = 'booklet_form'))
                    OR json_extract(NEW.manifest_json, '$.optional_attachment.answer_sheet_version_id') IS NOT NEW.template_version_id
                    OR json_extract(NEW.manifest_json, '$.optional_attachment.booklet_version_id') IS NOT NEW.booklet_version_id
                    OR json_extract(NEW.manifest_json, '$.optional_attachment.booklet_set_id') IS NOT NEW.booklet_set_id
                    OR json_extract(NEW.manifest_json, '$.optional_attachment.composition_sha256') IS NOT NEW.composition_sha256
                    OR json_extract(NEW.manifest_json, '$.optional_attachment.set_order_sha256') IS NOT NEW.booklet_set_order_hash
                    OR json_extract(NEW.manifest_json, '$.optional_attachment.set_key_sha256') IS NOT NEW.booklet_set_answer_key_hash
                    OR NEW.composition_revision IS NULL
                    OR json_type(NEW.manifest_json, '$.composition.rows') IS NOT 'array'
                    OR json_type(NEW.manifest_json, '$.item_map.items') IS NOT 'array'
                ))
                SQL);
        }
        foreach ([
            "(NEW.template_version_id IS NOT NULL) = EXISTS (\n                                    SELECT 1 FROM test_instrument_metrics m, json_each(m.input_contract_json, '$.required_resources') resource\n                                    WHERE m.test_instrument_version_id = profile.id AND resource.value = 'answer_sheet'\n                                )" => "(((NEW.template_version_id IS NOT NULL) = EXISTS (\n                                    SELECT 1 FROM test_instrument_metrics m, json_each(m.input_contract_json, '$.required_resources') resource\n                                    WHERE m.test_instrument_version_id = profile.id AND resource.value = 'answer_sheet'\n                                )) OR (NEW.template_version_id IS NOT NULL AND NEW.booklet_version_id IS NOT NULL AND NEW.booklet_set_id IS NOT NULL))",
            "(NEW.booklet_version_id IS NOT NULL) = EXISTS (\n                                    SELECT 1 FROM test_instrument_metrics m, json_each(m.input_contract_json, '$.required_resources') resource\n                                    WHERE m.test_instrument_version_id = profile.id AND resource.value = 'booklet_form'\n                                )" => "(((NEW.booklet_version_id IS NOT NULL) = EXISTS (\n                                    SELECT 1 FROM test_instrument_metrics m, json_each(m.input_contract_json, '$.required_resources') resource\n                                    WHERE m.test_instrument_version_id = profile.id AND resource.value = 'booklet_form'\n                                )) OR (NEW.template_version_id IS NOT NULL AND NEW.booklet_version_id IS NOT NULL AND NEW.booklet_set_id IS NOT NULL))",
            "(NEW.booklet_set_id IS NOT NULL) = EXISTS (\n                                    SELECT 1 FROM test_instrument_metrics m, json_each(m.input_contract_json, '$.required_resources') resource\n                                    WHERE m.test_instrument_version_id = profile.id AND resource.value = 'booklet_form'\n                                )" => "(((NEW.booklet_set_id IS NOT NULL) = EXISTS (\n                                    SELECT 1 FROM test_instrument_metrics m, json_each(m.input_contract_json, '$.required_resources') resource\n                                    WHERE m.test_instrument_version_id = profile.id AND resource.value = 'booklet_form'\n                                )) OR (NEW.template_version_id IS NOT NULL AND NEW.booklet_version_id IS NOT NULL AND NEW.booklet_set_id IS NOT NULL))",
        ] as $expected => $replacement) {
            $this->replaceSqliteTriggerFragment('kit_prep_comp_revision_insert_guard', $expected, $replacement);
        }
        $this->replaceSqliteTriggerFragment(
            'kit_prep_comp_revision_insert_guard',
            "OR (NEW.booklet_version_id IS NOT NULL AND NOT EXISTS (\n                                    SELECT 1 FROM test_instrument_metrics m, json_each(m.input_contract_json, '$.required_resources') resource\n                                    WHERE m.test_instrument_version_id = profile.id AND resource.value = 'key'\n                                ) AND json_extract(NEW.component_vector_json, '$.booklet.set_answer_key_hash') IS NULL))",
            "OR (NEW.booklet_version_id IS NOT NULL AND NOT EXISTS (\n                                    SELECT 1 FROM test_instrument_metrics m, json_each(m.input_contract_json, '$.required_resources') resource\n                                    WHERE m.test_instrument_version_id = profile.id AND resource.value = 'key'\n                                ) AND ((json_extract(NEW.component_vector_json, '$.sheet.physical_inventory_sha256') IS NOT NULL AND json_extract(NEW.component_vector_json, '$.booklet.set_answer_key_hash') IS NOT NULL)\n                                    OR (json_extract(NEW.component_vector_json, '$.sheet.physical_inventory_sha256') IS NULL AND json_extract(NEW.component_vector_json, '$.booklet.set_answer_key_hash') IS NULL))))",
        );

        foreach (['assessment_admin_exact_binding_insert', 'assessment_admin_exact_binding_update'] as $trigger) {
            foreach ([
                "((k.template_version_id IS NOT NULL)=EXISTS(SELECT 1 FROM json_each(k.manifest_json,'$.requirements.required_resources') WHERE value='answer_sheet'))" => "(((k.template_version_id IS NOT NULL)=EXISTS(SELECT 1 FROM json_each(k.manifest_json,'$.requirements.required_resources') WHERE value='answer_sheet')) OR json_extract(k.manifest_json,'$.optional_attachment.version')=1)",
                "((k.booklet_version_id IS NOT NULL)=EXISTS(SELECT 1 FROM json_each(k.manifest_json,'$.requirements.required_resources') WHERE value='booklet_form'))" => "(((k.booklet_version_id IS NOT NULL)=EXISTS(SELECT 1 FROM json_each(k.manifest_json,'$.requirements.required_resources') WHERE value='booklet_form')) OR json_extract(k.manifest_json,'$.optional_attachment.version')=1)",
                "((k.booklet_set_id IS NOT NULL)=EXISTS(SELECT 1 FROM json_each(k.manifest_json,'$.requirements.required_resources') WHERE value='booklet_form'))" => "(((k.booklet_set_id IS NOT NULL)=EXISTS(SELECT 1 FROM json_each(k.manifest_json,'$.requirements.required_resources') WHERE value='booklet_form')) OR json_extract(k.manifest_json,'$.optional_attachment.version')=1)",
                "((k.booklet_set_answer_key_hash IS NOT NULL)=EXISTS(SELECT 1 FROM json_each(k.manifest_json,'$.requirements.required_resources') WHERE value='key'))" => "(((k.booklet_set_answer_key_hash IS NOT NULL)=EXISTS(SELECT 1 FROM json_each(k.manifest_json,'$.requirements.required_resources') WHERE value='key')) OR (k.booklet_set_answer_key_hash IS NOT NULL AND json_extract(k.manifest_json,'$.optional_attachment.version')=1))",
            ] as $expected => $replacement) {
                $this->replaceSqliteTriggerFragment($trigger, $expected, $replacement);
            }
        }

        foreach (['exam_session_kit_pin_insert_guard', 'exam_session_kit_pin_update_guard'] as $trigger) {
            $this->replaceSqliteTriggerFragment(
                $trigger,
                "(EXISTS (SELECT 1 FROM json_each(kv.manifest_json, '$.requirements.required_resources') WHERE value = 'booklet_form') AND NEW.booklet_version_id IS NOT NULL)",
                "((EXISTS (SELECT 1 FROM json_each(kv.manifest_json, '$.requirements.required_resources') WHERE value = 'booklet_form') OR json_extract(kv.manifest_json, '$.optional_attachment.version') = 1) AND NEW.booklet_version_id IS NOT NULL)",
            );
            $this->replaceSqliteTriggerFragment(
                $trigger,
                "(NOT EXISTS (SELECT 1 FROM json_each(kv.manifest_json, '$.requirements.required_resources') WHERE value = 'booklet_form') AND NEW.booklet_version_id IS NULL)",
                "((NOT EXISTS (SELECT 1 FROM json_each(kv.manifest_json, '$.requirements.required_resources') WHERE value = 'booklet_form') AND json_extract(kv.manifest_json, '$.optional_attachment.version') IS NOT 1) AND NEW.booklet_version_id IS NULL)",
            );
        }
    }

    private function replaceSqliteTriggerFragment(string $trigger, string $expected, string $replacement): void
    {
        $definition = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'trigger' AND name = ?", [$trigger])?->sql;
        $pattern = '/'.implode('\s+', array_map(static fn (string $part): string => preg_quote($part, '/'), preg_split('/\s+/', trim($expected)) ?: [])).'/';
        if (! is_string($definition) || preg_match_all($pattern, $definition) !== 1) {
            throw new RuntimeException("The SQLite {$trigger} definition did not match its expected forward-migration source.");
        }
        DB::statement('DROP TRIGGER "'.str_replace('"', '""', $trigger).'"');
        DB::unprepared((string) preg_replace($pattern, addcslashes($replacement, '\\$'), $definition, 1));
    }

    private function appendSqliteTriggerGuard(string $trigger, string $condition): void
    {
        $definition = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'trigger' AND name = ?", [$trigger])?->sql;
        if (! is_string($definition) || ! str_contains($definition, 'BEGIN SELECT RAISE(ABORT,')) {
            throw new RuntimeException("The SQLite {$trigger} definition is unavailable for the optional-attachment guard.");
        }
        $definition = str_replace('BEGIN SELECT RAISE(ABORT,', $condition.' BEGIN SELECT RAISE(ABORT,', $definition);
        DB::statement('DROP TRIGGER "'.str_replace('"', '""', $trigger).'"');
        DB::unprepared($definition);
    }

    public function down(): void
    {
        throw new RuntimeException('Discarded preparations and optional material bindings are forward-only; use a forward migration.');
    }
};
