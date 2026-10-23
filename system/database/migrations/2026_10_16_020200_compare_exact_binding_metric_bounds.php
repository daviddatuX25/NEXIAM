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
            $this->replacePostgresGuard();

            return;
        }
        if ($driver !== 'sqlite') {
            throw new RuntimeException('Exact assessment metric bounds require PostgreSQL or SQLite.');
        }
        $this->replaceSqliteGuards();
    }

    private function replacePostgresGuard(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION nexiam_assessment_binding_guard() RETURNS trigger
            LANGUAGE plpgsql AS $function$
            DECLARE
                a exam_assignments%ROWTYPE;
                s exam_sessions%ROWTYPE;
                attempt admission_attempts%ROWTYPE;
                kit test_kit_versions%ROWTYPE;
                profile test_instrument_versions%ROWTYPE;
                used boolean;
                changed boolean;
            BEGIN
                IF TG_OP='UPDATE' THEN
                    changed := ROW(NEW.exam_assignment_id,NEW.admission_attempt_id,NEW.binding_kind,NEW.binding_revision,
                        NEW.test_instrument_version_id,NEW.material_snapshot_id,NEW.exam_session_seat_id,NEW.material_set_id,
                        NEW.booklet_set_id,NEW.required_metric_set::jsonb,NEW.bound_by_user_id,NEW.bound_at,
                        NEW.test_kit_version_id,NEW.manifest_sha256,NEW.acceptance_receipt_id,NEW.procedure_snapshot_json::jsonb,NEW.created_at)
                        IS DISTINCT FROM ROW(OLD.exam_assignment_id,OLD.admission_attempt_id,OLD.binding_kind,OLD.binding_revision,
                        OLD.test_instrument_version_id,OLD.material_snapshot_id,OLD.exam_session_seat_id,OLD.material_set_id,
                        OLD.booklet_set_id,OLD.required_metric_set::jsonb,OLD.bound_by_user_id,OLD.bound_at,
                        OLD.test_kit_version_id,OLD.manifest_sha256,OLD.acceptance_receipt_id,OLD.procedure_snapshot_json::jsonb,OLD.created_at);
                    IF OLD.test_kit_version_id IS NULL AND changed THEN
                        RAISE EXCEPTION USING ERRCODE='23514', MESSAGE='historical unpinned binding identity is immutable';
                    END IF;
                    IF changed OR (NEW.superseded_at IS DISTINCT FROM OLD.superseded_at AND NEW.superseded_at IS NOT NULL) THEN
                        SELECT EXISTS(SELECT 1 FROM assessment_scoring_runs WHERE administration_id=OLD.id)
                            OR EXISTS(SELECT 1 FROM assessment_raw_result_revisions WHERE administration_id=OLD.id)
                            OR EXISTS(SELECT 1 FROM assessment_conversion_revisions WHERE administration_id=OLD.id)
                            OR EXISTS(SELECT 1 FROM assessment_result_sets WHERE administration_id=OLD.id) INTO used;
                        IF used THEN RAISE EXCEPTION USING ERRCODE='23514', MESSAGE='used binding identity cannot change or be superseded'; END IF;
                    END IF;
                    IF OLD.superseded_at IS NOT NULL AND NEW.superseded_at IS NULL THEN
                        PERFORM 1 FROM exam_assignments WHERE id=OLD.exam_assignment_id FOR UPDATE;
                        IF EXISTS(SELECT 1 FROM assessment_administrations d WHERE d.exam_assignment_id=OLD.exam_assignment_id
                            AND d.superseded_at IS NULL AND d.id<>OLD.id) THEN
                            RAISE EXCEPTION USING ERRCODE='23514', MESSAGE='assignment already has current assessment binding';
                        END IF;
                    END IF;
                    IF NEW.test_kit_version_id IS NULL OR NOT changed THEN RETURN NEW; END IF;
                END IF;
                IF NEW.test_kit_version_id IS NULL OR NEW.test_instrument_version_id IS NULL
                    OR NEW.manifest_sha256 IS NULL OR NEW.procedure_snapshot_json IS NULL THEN
                    RAISE EXCEPTION USING ERRCODE='23514', MESSAGE='new binding requires exact Kit/profile/manifest/procedure';
                END IF;
                SELECT * INTO a FROM exam_assignments WHERE id=NEW.exam_assignment_id FOR UPDATE;
                SELECT * INTO s FROM exam_sessions WHERE id=a.exam_session_id;
                SELECT * INTO attempt FROM admission_attempts WHERE id=NEW.admission_attempt_id;
                SELECT * INTO kit FROM test_kit_versions WHERE id=NEW.test_kit_version_id AND manifest_sha256=NEW.manifest_sha256 FOR KEY SHARE;
                SELECT * INTO profile FROM test_instrument_versions WHERE id=NEW.test_instrument_version_id FOR SHARE;
                IF a.id IS NULL OR attempt.id IS NULL OR a.admission_attempt_id<>NEW.admission_attempt_id
                    OR s.id IS NULL OR s.academic_cycle_id<>attempt.academic_cycle_id
                    OR (SELECT institution_id FROM academic_cycles WHERE id=s.academic_cycle_id)<>attempt.institution_id
                    OR kit.id IS NULL OR profile.id IS NULL OR kit.test_instrument_version_id<>profile.id OR profile.status<>'approved'
                    OR (SELECT institution_id FROM test_kits WHERE id=kit.test_kit_id)<>attempt.institution_id
                    OR (SELECT institution_id FROM test_instruments WHERE id=profile.test_instrument_id)<>attempt.institution_id
                    OR (SELECT is_synthetic FROM test_instruments WHERE id=profile.test_instrument_id) IS DISTINCT FROM (SELECT is_synthetic FROM test_kits WHERE id=kit.test_kit_id)
                    OR kit.manifest_json::jsonb #>> '{profile,content_sha256}' IS DISTINCT FROM profile.content_sha256
                    OR NEW.procedure_snapshot_json::jsonb ->> 'content_sha256' IS DISTINCT FROM profile.content_sha256
                    OR NEW.procedure_snapshot_json::jsonb ->> 'administration_procedure_reference' IS DISTINCT FROM profile.administration_procedure_reference
                    OR NEW.procedure_snapshot_json::jsonb ->> 'material_requirement' IS DISTINCT FROM profile.material_requirement
                    OR (NEW.procedure_snapshot_json::jsonb ->> 'manifest_schema_version')::integer IS DISTINCT FROM kit.manifest_schema_version
                    OR NEW.procedure_snapshot_json::jsonb ->> 'kit_manifest_sha256' IS DISTINCT FROM kit.manifest_sha256
                    OR NEW.booklet_set_id IS DISTINCT FROM kit.booklet_set_id THEN
                    RAISE EXCEPTION USING ERRCODE='23514', MESSAGE='binding Kit/profile/session owner mismatch';
                END IF;
                IF kit.manifest_schema_version=3 THEN
                    IF NEW.required_metric_set::jsonb IS DISTINCT FROM (
                        SELECT jsonb_agg(jsonb_build_object('code',m.code,'name',m.name,'rule_kind',m.rule_kind,'unit',m.unit,
                            'min_raw_value',m.min_raw_value::text,'max_raw_value',m.max_raw_value::text,'precision_scale',m.precision_scale,
                            'required_output_scales',m.required_output_scales,'input_contract_json',m.input_contract_json) ORDER BY m.code)
                        FROM test_instrument_metrics m WHERE m.test_instrument_version_id=profile.id AND m.is_required=true)
                        OR (kit.manifest_json::jsonb #> '{requirements,required_resources}') IS DISTINCT FROM
                            (SELECT COALESCE(jsonb_agg(DISTINCT resource ORDER BY resource),'[]'::jsonb)
                             FROM test_instrument_metrics m CROSS JOIN LATERAL jsonb_array_elements_text(m.input_contract_json::jsonb->'required_resources') resource
                             WHERE m.test_instrument_version_id=profile.id AND m.input_contract_json IS NOT NULL)
                        OR NOT EXISTS(SELECT 1 FROM test_kit_versions k WHERE k.id=kit.id
                            AND k.manifest_json::jsonb #> '{requirements,required_resources}' IS NOT NULL
                            AND k.manifest_json::jsonb #> '{requirements,schema_version}'='1'::jsonb
                            AND ((k.template_version_id IS NOT NULL)=COALESCE((k.manifest_json::jsonb #> '{requirements,required_resources}') ? 'answer_sheet',false))
                            AND ((k.booklet_version_id IS NOT NULL)=COALESCE((k.manifest_json::jsonb #> '{requirements,required_resources}') ? 'booklet_form',false))
                            AND ((k.booklet_set_id IS NOT NULL)=COALESCE((k.manifest_json::jsonb #> '{requirements,required_resources}') ? 'booklet_form',false))
                            AND ((k.booklet_set_answer_key_hash IS NOT NULL)=COALESCE((k.manifest_json::jsonb #> '{requirements,required_resources}') ? 'key',false))
                            AND (k.manifest_json::jsonb #>> '{requirements,omr_applicable}'<>'true'
                                OR (k.template_version_id IS NOT NULL AND k.booklet_version_id IS NOT NULL AND k.booklet_set_id IS NOT NULL))) THEN
                        RAISE EXCEPTION USING ERRCODE='23514', MESSAGE='conditional Kit requirements/profile declaration mismatch';
                    END IF;
                    IF NEW.acceptance_receipt_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM test_kit_acceptance_receipts r
                        WHERE r.id=NEW.acceptance_receipt_id AND r.test_kit_version_id=kit.id AND r.manifest_sha256=kit.manifest_sha256) THEN
                        RAISE EXCEPTION USING ERRCODE='23514', MESSAGE='receipt does not belong to exact Kit manifest';
                    END IF;
                ELSIF kit.manifest_schema_version IN (1,2) THEN
                    IF NEW.material_snapshot_id IS NULL OR NEW.acceptance_receipt_id IS NULL OR NEW.exam_session_seat_id IS NULL THEN
                        RAISE EXCEPTION USING ERRCODE='23514', MESSAGE='schema 1/2 bindings require complete snapshot/seat/receipt';
                    END IF;
                ELSE
                    RAISE EXCEPTION USING ERRCODE='23514', MESSAGE='unsupported Kit manifest schema';
                END IF;
                IF NEW.material_snapshot_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM exam_session_material_snapshots x
                    WHERE x.id=NEW.material_snapshot_id AND x.exam_session_id=a.exam_session_id
                    AND x.test_kit_version_id=kit.id AND x.manifest_sha256=kit.manifest_sha256
                    AND x.test_instrument_version_id=profile.id) THEN
                    RAISE EXCEPTION USING ERRCODE='23514', MESSAGE='snapshot does not belong to exact sitting Kit/profile';
                END IF;
                IF NEW.exam_session_seat_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM exam_session_seats seat
                    JOIN exam_session_material_snapshots x ON x.id=seat.material_snapshot_id
                    WHERE seat.id=NEW.exam_session_seat_id AND x.exam_session_id=a.exam_session_id
                    AND x.test_kit_version_id=kit.id AND x.manifest_sha256=kit.manifest_sha256
                    AND x.test_instrument_version_id=profile.id AND seat.booklet_set_id=kit.booklet_set_id) THEN
                    RAISE EXCEPTION USING ERRCODE='23514', MESSAGE='seat does not belong to exact sitting Kit/profile';
                END IF;
                IF NEW.material_set_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM exam_session_material_sets ms
                    JOIN exam_session_material_snapshots x ON x.id=ms.snapshot_id
                    WHERE ms.id=NEW.material_set_id AND x.exam_session_id=a.exam_session_id
                    AND x.test_kit_version_id=kit.id AND x.manifest_sha256=kit.manifest_sha256
                    AND x.test_instrument_version_id=profile.id) THEN
                    RAISE EXCEPTION USING ERRCODE='23514', MESSAGE='material set does not belong to exact sitting Kit/profile';
                END IF;
                IF NEW.superseded_at IS NULL AND EXISTS(SELECT 1 FROM assessment_administrations d
                    WHERE d.exam_assignment_id=NEW.exam_assignment_id AND d.superseded_at IS NULL AND d.id<>NEW.id) THEN
                    RAISE EXCEPTION USING ERRCODE='23514', MESSAGE='assignment already has current assessment binding';
                END IF;
                RETURN NEW;
            END $function$
        SQL);
    }

    private function replaceSqliteGuards(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS assessment_admin_exact_binding_insert');
        DB::statement('DROP TRIGGER IF EXISTS assessment_admin_exact_binding_update');
        DB::statement('DROP TRIGGER IF EXISTS assessment_admin_test_kit_pins_insert');
        DB::statement('DROP TRIGGER IF EXISTS assessment_admin_test_kit_pins_update');
        $resources = "((k.template_version_id IS NOT NULL)=EXISTS(SELECT 1 FROM json_each(k.manifest_json,'$.requirements.required_resources') WHERE value='answer_sheet')) AND ((k.booklet_version_id IS NOT NULL)=EXISTS(SELECT 1 FROM json_each(k.manifest_json,'$.requirements.required_resources') WHERE value='booklet_form')) AND ((k.booklet_set_id IS NOT NULL)=EXISTS(SELECT 1 FROM json_each(k.manifest_json,'$.requirements.required_resources') WHERE value='booklet_form')) AND ((k.booklet_set_answer_key_hash IS NOT NULL)=EXISTS(SELECT 1 FROM json_each(k.manifest_json,'$.requirements.required_resources') WHERE value='key')) AND (json_extract(k.manifest_json,'$.requirements.omr_applicable')<>1 OR (k.template_version_id IS NOT NULL AND k.booklet_version_id IS NOT NULL AND k.booklet_set_id IS NOT NULL))";
        $requirements = "EXISTS(SELECT 1 FROM test_kit_versions k WHERE k.id=NEW.test_kit_version_id AND json_valid(k.manifest_json)=1 AND json_extract(k.manifest_json,'$.requirements.schema_version')=1 AND json_type(k.manifest_json,'$.requirements.required_resources')='array' AND json((SELECT json_group_array(value) FROM (SELECT DISTINCT j.value AS value FROM test_instrument_metrics m JOIN json_each(m.input_contract_json,'$.required_resources') j WHERE m.test_instrument_version_id=NEW.test_instrument_version_id ORDER BY j.value)))=json((SELECT json_group_array(value) FROM (SELECT value FROM json_each(k.manifest_json,'$.requirements.required_resources') ORDER BY value))) AND {$resources})";
        $base = "NEW.test_kit_version_id IS NOT NULL AND NEW.test_instrument_version_id IS NOT NULL AND NEW.manifest_sha256 IS NOT NULL AND NEW.procedure_snapshot_json IS NOT NULL AND EXISTS(SELECT 1 FROM test_kit_versions k JOIN test_kits tk ON tk.id=k.test_kit_id JOIN test_instrument_versions p ON p.id=NEW.test_instrument_version_id JOIN test_instruments ti ON ti.id=p.test_instrument_id WHERE k.id=NEW.test_kit_version_id AND k.manifest_sha256=NEW.manifest_sha256 AND k.test_instrument_version_id=p.id AND p.status='approved' AND tk.institution_id=ti.institution_id AND tk.is_synthetic=ti.is_synthetic AND json_extract(k.manifest_json,'$.profile.content_sha256')=p.content_sha256 AND json_extract(NEW.procedure_snapshot_json,'$.content_sha256')=p.content_sha256 AND json_extract(NEW.procedure_snapshot_json,'$.administration_procedure_reference') IS p.administration_procedure_reference AND json_extract(NEW.procedure_snapshot_json,'$.material_requirement') IS p.material_requirement AND json_extract(NEW.procedure_snapshot_json,'$.manifest_schema_version')=k.manifest_schema_version AND json_extract(NEW.procedure_snapshot_json,'$.kit_manifest_sha256')=k.manifest_sha256 AND NEW.booklet_set_id IS k.booklet_set_id) AND EXISTS(SELECT 1 FROM exam_assignments a JOIN exam_sessions s ON s.id=a.exam_session_id JOIN academic_cycles cy ON cy.id=s.academic_cycle_id JOIN admission_attempts aa ON aa.id=a.admission_attempt_id JOIN test_kit_versions k ON k.id=NEW.test_kit_version_id JOIN test_kits tk ON tk.id=k.test_kit_id WHERE a.id=NEW.exam_assignment_id AND a.admission_attempt_id=NEW.admission_attempt_id AND aa.id=NEW.admission_attempt_id AND s.academic_cycle_id=aa.academic_cycle_id AND cy.institution_id=aa.institution_id AND tk.institution_id=aa.institution_id) AND ((SELECT manifest_schema_version FROM test_kit_versions WHERE id=NEW.test_kit_version_id)=3 AND {$requirements} OR (SELECT manifest_schema_version FROM test_kit_versions WHERE id=NEW.test_kit_version_id) IN (1,2) AND NEW.material_snapshot_id IS NOT NULL AND NEW.exam_session_seat_id IS NOT NULL AND NEW.acceptance_receipt_id IS NOT NULL) AND (NEW.acceptance_receipt_id IS NULL OR EXISTS(SELECT 1 FROM test_kit_acceptance_receipts r WHERE r.id=NEW.acceptance_receipt_id AND r.test_kit_version_id=NEW.test_kit_version_id AND r.manifest_sha256=NEW.manifest_sha256)) AND (NEW.material_snapshot_id IS NULL OR EXISTS(SELECT 1 FROM exam_session_material_snapshots x JOIN exam_assignments a ON a.exam_session_id=x.exam_session_id WHERE x.id=NEW.material_snapshot_id AND a.id=NEW.exam_assignment_id AND x.test_kit_version_id=NEW.test_kit_version_id AND x.manifest_sha256=NEW.manifest_sha256 AND x.test_instrument_version_id=NEW.test_instrument_version_id)) AND (NEW.exam_session_seat_id IS NULL OR EXISTS(SELECT 1 FROM exam_session_seats seat JOIN exam_session_material_snapshots x ON x.id=seat.material_snapshot_id JOIN exam_assignments a ON a.exam_session_id=x.exam_session_id WHERE seat.id=NEW.exam_session_seat_id AND a.id=NEW.exam_assignment_id AND x.test_kit_version_id=NEW.test_kit_version_id AND x.manifest_sha256=NEW.manifest_sha256 AND x.test_instrument_version_id=NEW.test_instrument_version_id AND seat.booklet_set_id=NEW.booklet_set_id)) AND (NEW.material_set_id IS NULL OR EXISTS(SELECT 1 FROM exam_session_material_sets ms JOIN exam_session_material_snapshots x ON x.id=ms.snapshot_id JOIN exam_assignments a ON a.exam_session_id=x.exam_session_id WHERE ms.id=NEW.material_set_id AND a.id=NEW.exam_assignment_id AND x.test_kit_version_id=NEW.test_kit_version_id AND x.manifest_sha256=NEW.manifest_sha256 AND x.test_instrument_version_id=NEW.test_instrument_version_id))";
        $expectedMetrics = "(SELECT json_group_array(json(m)) FROM (SELECT json_object('code',m.code,'name',m.name,'rule_kind',m.rule_kind,'unit',m.unit,'min_raw_value',CASE WHEN m.min_raw_value IS NULL THEN NULL ELSE printf('%.6f',m.min_raw_value) END,'max_raw_value',CASE WHEN m.max_raw_value IS NULL THEN NULL ELSE printf('%.6f',m.max_raw_value) END,'precision_scale',m.precision_scale,'required_output_scales',json(m.required_output_scales),'input_contract_json',json(m.input_contract_json)) AS m FROM test_instrument_metrics m WHERE m.test_instrument_version_id=NEW.test_instrument_version_id AND m.is_required=1 ORDER BY m.code))";
        $used = 'EXISTS(SELECT 1 FROM assessment_scoring_runs r WHERE r.administration_id=OLD.id) OR EXISTS(SELECT 1 FROM assessment_raw_result_revisions r WHERE r.administration_id=OLD.id) OR EXISTS(SELECT 1 FROM assessment_conversion_revisions c WHERE c.administration_id=OLD.id) OR EXISTS(SELECT 1 FROM assessment_result_sets z WHERE z.administration_id=OLD.id)';
        $identity = 'NEW.exam_assignment_id IS NOT OLD.exam_assignment_id OR NEW.admission_attempt_id IS NOT OLD.admission_attempt_id OR NEW.binding_kind IS NOT OLD.binding_kind OR NEW.binding_revision IS NOT OLD.binding_revision OR NEW.test_instrument_version_id IS NOT OLD.test_instrument_version_id OR NEW.material_snapshot_id IS NOT OLD.material_snapshot_id OR NEW.exam_session_seat_id IS NOT OLD.exam_session_seat_id OR NEW.material_set_id IS NOT OLD.material_set_id OR NEW.booklet_set_id IS NOT OLD.booklet_set_id OR NEW.required_metric_set IS NOT OLD.required_metric_set OR NEW.bound_by_user_id IS NOT OLD.bound_by_user_id OR NEW.bound_at IS NOT OLD.bound_at OR NEW.test_kit_version_id IS NOT OLD.test_kit_version_id OR NEW.manifest_sha256 IS NOT OLD.manifest_sha256 OR NEW.acceptance_receipt_id IS NOT OLD.acceptance_receipt_id OR NEW.procedure_snapshot_json IS NOT OLD.procedure_snapshot_json OR NEW.created_at IS NOT OLD.created_at';
        DB::unprepared("CREATE TRIGGER assessment_admin_exact_binding_insert BEFORE INSERT ON assessment_administrations WHEN NOT ({$base}) OR ((SELECT manifest_schema_version FROM test_kit_versions WHERE id=NEW.test_kit_version_id)=3 AND NEW.required_metric_set IS NOT {$expectedMetrics}) OR (NEW.superseded_at IS NULL AND EXISTS(SELECT 1 FROM assessment_administrations d WHERE d.exam_assignment_id=NEW.exam_assignment_id AND d.superseded_at IS NULL)) BEGIN SELECT RAISE(ABORT,'invalid or duplicate exact assessment binding'); END");
        DB::unprepared("CREATE TRIGGER assessment_admin_exact_binding_update BEFORE UPDATE ON assessment_administrations WHEN (OLD.test_kit_version_id IS NULL AND ({$identity})) OR (({$identity}) AND ({$used})) OR (NEW.superseded_at IS NOT OLD.superseded_at AND NEW.superseded_at IS NOT NULL AND ({$used})) OR (NEW.test_kit_version_id IS NOT NULL AND ({$identity}) AND (NOT ({$base}) OR ((SELECT manifest_schema_version FROM test_kit_versions WHERE id=NEW.test_kit_version_id)=3 AND NEW.required_metric_set IS NOT {$expectedMetrics}))) OR (NEW.test_kit_version_id IS NOT NULL AND NEW.superseded_at IS NULL AND EXISTS(SELECT 1 FROM assessment_administrations d WHERE d.exam_assignment_id=NEW.exam_assignment_id AND d.superseded_at IS NULL AND d.id IS NOT NEW.id)) BEGIN SELECT RAISE(ABORT,'invalid or duplicate exact assessment binding'); END");
    }

    public function down(): void
    {
        throw new RuntimeException('Numeric-safe assessment binding comparison is forward-only; use a forward migration.');
    }
};
