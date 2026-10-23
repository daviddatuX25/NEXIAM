<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SHEET = 'omr_answer_sheet_template_versions';

    private const BOOKLET = 'omr_question_booklet_versions';

    /** @var array<string, string> */
    private const DESCENDANTS = [
        'omr_template_areas' => 'sheet_area',
        'omr_template_questions' => 'sheet_question',
        'omr_template_question_options' => 'sheet_option',
        'omr_answer_sheet_pages' => 'sheet_version',
        'omr_template_item_mappings' => 'sheet_version',
        'omr_template_assets' => 'sheet_asset',
        'omr_question_booklet_questions' => 'booklet_version',
        'omr_booklet_items' => 'booklet_version',
        'omr_booklet_sets' => 'booklet_set',
        'omr_booklet_set_items' => 'booklet_set_item',
    ];

    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException('OMR material seals require PostgreSQL or SQLite.');
        }

        foreach ([self::SHEET => 'sheet', self::BOOKLET => 'booklet'] as $table => $kind) {
            $this->installRootGuards($table, $kind, $driver);
        }
        foreach (self::DESCENDANTS as $table => $kind) {
            $this->installDescendantGuards($table, $kind, $driver);
        }
        $this->replaceLifecycleChecks($driver);
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        foreach ([...array_keys(self::DESCENDANTS), self::SHEET, self::BOOKLET] as $table) {
            foreach (['insert', 'update', 'delete'] as $event) {
                DB::statement("DROP TRIGGER IF EXISTS omr_component_{$table}_{$event}_seal_guard".($driver === 'pgsql' ? " ON {$table}" : ''));
                if ($driver === 'pgsql' && isset(self::DESCENDANTS[$table])) {
                    DB::statement("DROP FUNCTION IF EXISTS nexiam_omr_component_{$table}_".strtolower($event).'_seal_guard()');
                }
            }
            if ($driver === 'pgsql' && in_array($table, [self::SHEET, self::BOOKLET], true)) {
                DB::statement("DROP FUNCTION IF EXISTS nexiam_omr_component_{$table}_seal_guard()");
            }
        }
        $this->restoreLegacyLifecycleChecks($driver);
    }

    private function replaceLifecycleChecks(string $driver): void
    {
        if ($driver !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE omr_answer_sheet_template_versions
                DROP CONSTRAINT IF EXISTS omr_template_versions_status_check,
                DROP CONSTRAINT IF EXISTS omr_template_versions_activation_check,
                ADD CONSTRAINT omr_template_versions_status_check
                    CHECK (status IN ('draft', 'compiled')) NOT VALID,
                ADD CONSTRAINT omr_template_versions_activation_check
                    CHECK (
                        (status = 'draft' AND activated_at IS NULL)
                        OR (
                            status = 'compiled'
                            AND activated_at IS NULL
                            AND activated_by_user_id IS NULL
                            AND native_bundle_hash IS NOT NULL
                            AND native_bundle_hash ~ '^[a-f0-9]{64}$'
                            AND native_bundle_json IS NOT NULL
                            AND native_bundle_json::jsonb <> 'null'::jsonb
                        )
                    ) NOT VALID
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE omr_question_booklet_versions
                DROP CONSTRAINT IF EXISTS omr_booklet_versions_status_check,
                DROP CONSTRAINT IF EXISTS omr_booklet_versions_activation_check,
                ADD CONSTRAINT omr_booklet_versions_status_check
                    CHECK (status IN ('draft', 'finalized')) NOT VALID,
                ADD CONSTRAINT omr_booklet_versions_activation_check
                    CHECK (
                        (status = 'draft' AND activated_at IS NULL AND finalized_at IS NULL AND finalized_by_user_id IS NULL)
                        OR (
                            status = 'finalized'
                            AND activated_at IS NULL
                            AND activated_by_user_id IS NULL
                            AND finalized_at IS NOT NULL
                            AND finalized_by_user_id IS NOT NULL
                            AND content_hash IS NOT NULL
                            AND content_hash ~ '^[a-f0-9]{64}$'
                            AND answer_key_hash IS NOT NULL
                            AND answer_key_hash ~ '^[a-f0-9]{64}$'
                        )
                    ) NOT VALID
        SQL);
    }

    private function restoreLegacyLifecycleChecks(string $driver): void
    {
        if ($driver !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE omr_answer_sheet_template_versions
                DROP CONSTRAINT IF EXISTS omr_template_versions_status_check,
                DROP CONSTRAINT IF EXISTS omr_template_versions_activation_check,
                ADD CONSTRAINT omr_template_versions_status_check
                    CHECK (status IN ('draft', 'active', 'retired')) NOT VALID,
                ADD CONSTRAINT omr_template_versions_activation_check
                    CHECK (
                        (status = 'draft' AND activated_at IS NULL)
                        OR (status IN ('active', 'retired') AND activated_at IS NOT NULL)
                    ) NOT VALID
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE omr_question_booklet_versions
                DROP CONSTRAINT IF EXISTS omr_booklet_versions_status_check,
                DROP CONSTRAINT IF EXISTS omr_booklet_versions_activation_check,
                ADD CONSTRAINT omr_booklet_versions_status_check
                    CHECK (status IN ('draft', 'active', 'retired')) NOT VALID,
                ADD CONSTRAINT omr_booklet_versions_activation_check
                    CHECK (
                        (status = 'draft' AND activated_at IS NULL)
                        OR (status IN ('active', 'retired') AND activated_at IS NOT NULL)
                    ) NOT VALID
        SQL);
    }

    private function installRootGuards(string $table, string $kind, string $driver): void
    {
        $columns = array_values(array_diff(Schema::getColumnListing($table), ['id', 'updated_at']));

        $mutable = $kind === 'sheet'
            ? []
            : ['archived_at', 'lock_version'];
        $materialColumns = array_values(array_diff($columns, $mutable));
        $sealedOld = $kind === 'sheet' ? "OLD.status = 'compiled'" : "OLD.status = 'finalized'";
        $sealedNew = $kind === 'sheet' ? "NEW.status = 'compiled'" : "NEW.status = 'finalized'";
        $hashCheck = $driver === 'pgsql'
            ? "NEW.native_bundle_hash IS NOT NULL AND NEW.native_bundle_hash ~ '^[a-f0-9]{64}$'"
            : "NEW.native_bundle_hash IS NOT NULL AND length(NEW.native_bundle_hash) = 64 AND NEW.native_bundle_hash NOT GLOB '*[^a-f0-9]*'";
        $contentHashCheck = $driver === 'pgsql'
            ? "NEW.content_hash IS NOT NULL AND NEW.content_hash ~ '^[a-f0-9]{64}$' AND NEW.answer_key_hash IS NOT NULL AND NEW.answer_key_hash ~ '^[a-f0-9]{64}$'"
            : "NEW.content_hash IS NOT NULL AND length(NEW.content_hash) = 64 AND NEW.content_hash NOT GLOB '*[^a-f0-9]*' AND NEW.answer_key_hash IS NOT NULL AND length(NEW.answer_key_hash) = 64 AND NEW.answer_key_hash NOT GLOB '*[^a-f0-9]*'";
        $bundleJsonCheck = $driver === 'pgsql'
            ? "NEW.native_bundle_json IS NOT NULL AND NEW.native_bundle_json::jsonb <> 'null'::jsonb"
            : "NEW.native_bundle_json IS NOT NULL AND length(trim(NEW.native_bundle_json)) > 0 AND NEW.native_bundle_json <> 'null'";
        $seal = $kind === 'sheet'
            ? "NEW.status = 'compiled' AND {$hashCheck} AND {$bundleJsonCheck}"
            : "NEW.status = 'finalized' AND NEW.finalized_at IS NOT NULL AND {$contentHashCheck}";

        if ($driver === 'pgsql') {
            $changed = implode("\n                    OR ", array_map(fn (string $column): string => "to_jsonb(OLD.{$column}) IS DISTINCT FROM to_jsonb(NEW.{$column})", $materialColumns));
            DB::unprepared(<<<SQL
                CREATE OR REPLACE FUNCTION nexiam_omr_component_{$table}_seal_guard() RETURNS trigger AS \$\$
                BEGIN
                    IF TG_OP = 'DELETE' THEN
                        IF {$sealedOld} THEN
                            RAISE EXCEPTION 'Sealed OMR component versions cannot be deleted';
                        END IF;
                        RETURN OLD;
                    END IF;
                    IF TG_OP = 'INSERT' THEN
                        IF {$sealedNew} AND NOT ({$seal}) THEN
                            RAISE EXCEPTION 'Sealed OMR component version is missing required material identity';
                        END IF;
                        RETURN NEW;
                    END IF;
                    IF {$sealedOld} THEN
                        IF {$changed} THEN
                            RAISE EXCEPTION 'Sealed OMR component material is immutable';
                        END IF;
                        IF NOT {$sealedNew} THEN
                            RAISE EXCEPTION 'Sealed OMR component cannot be reopened';
                        END IF;
                        RETURN NEW;
                    END IF;
                    IF {$sealedNew} AND NOT ({$seal}) THEN
                        RAISE EXCEPTION 'Sealed OMR component version is missing required material identity';
                    END IF;
                    RETURN NEW;
                END;
                \$\$ LANGUAGE plpgsql
                SQL);
            foreach (['INSERT', 'UPDATE', 'DELETE'] as $event) {
                $triggerName = "omr_component_{$table}_".strtolower($event).'_seal_guard';
                DB::unprepared("DROP TRIGGER IF EXISTS {$triggerName} ON {$table}");
                DB::unprepared("CREATE TRIGGER {$triggerName} BEFORE {$event} ON {$table} FOR EACH ROW EXECUTE FUNCTION nexiam_omr_component_{$table}_seal_guard()");
            }

            return;
        }

        $changed = implode(' OR ', array_map(fn (string $column): string => "OLD.{$column} IS NOT NEW.{$column}", $materialColumns));
        DB::unprepared("CREATE TRIGGER omr_component_{$table}_insert_seal_guard BEFORE INSERT ON {$table} FOR EACH ROW WHEN ({$sealedNew}) AND NOT ({$seal}) BEGIN SELECT RAISE(ABORT, 'Sealed OMR component version is missing required material identity'); END");
        DB::unprepared("CREATE TRIGGER omr_component_{$table}_delete_seal_guard BEFORE DELETE ON {$table} FOR EACH ROW WHEN ({$sealedOld}) BEGIN SELECT RAISE(ABORT, 'Sealed OMR component versions cannot be deleted'); END");
        DB::unprepared("CREATE TRIGGER omr_component_{$table}_update_seal_guard BEFORE UPDATE ON {$table} FOR EACH ROW WHEN (({$sealedOld}) AND (({$changed}) OR NOT ({$sealedNew}))) OR ((NOT ({$sealedOld})) AND ({$sealedNew}) AND NOT ({$seal})) BEGIN SELECT RAISE(ABORT, 'Sealed OMR component material is immutable or incomplete'); END");
    }

    private function installDescendantGuards(string $table, string $kind, string $driver): void
    {
        foreach (['INSERT', 'UPDATE', 'DELETE'] as $event) {
            if ($driver === 'pgsql') {
                $rootTable = str_starts_with($kind, 'sheet') ? self::SHEET : self::BOOKLET;
                $sealedStatus = str_starts_with($kind, 'sheet') ? 'compiled' : 'finalized';
                $roots = [
                    $this->rootExpression($kind, $event === 'INSERT' ? 'NULL' : 'OLD'),
                    $this->rootExpression($kind, $event === 'DELETE' ? 'NULL' : 'NEW'),
                ];
                if ($kind === 'booklet_set_item') {
                    if ($event !== 'INSERT') {
                        $roots[] = $this->rootExpression('booklet_item', 'OLD');
                    }
                    if ($event !== 'DELETE') {
                        $roots[] = $this->rootExpression('booklet_item', 'NEW');
                    }
                }
                $parentLocks = implode("\n", $this->ancestorLockStatements($kind, $event));
                $rootIds = implode(', ', array_unique($roots));
                $lockRoots = "PERFORM v.id FROM {$rootTable} v WHERE v.id IN ({$rootIds}) ORDER BY v.id FOR SHARE;";
                $condition = "EXISTS (SELECT 1 FROM {$rootTable} v WHERE v.id IN ({$rootIds}) AND v.status = '{$sealedStatus}')";
                $allowEvidenceInsert = $table === 'omr_template_assets' && $event === 'INSERT';
                $functionEvent = strtolower($event);
                $guardStart = $allowEvidenceInsert
                    ? "IF NEW.kind NOT IN ('evidence', 'digital_proof')
                       AND NOT (
                           (NEW.kind = 'print_pdf' AND (NEW.metadata->>'role' IN ('challenge_sheet', 'challenge_guide_pdf')))
                           OR (NEW.kind = 'raster_page' AND (NEW.metadata->>'role' = 'challenge_sheet_page'))
                       ) THEN"
                    : '';
                $guardEnd = $allowEvidenceInsert ? 'END IF;' : '';
                DB::unprepared(<<<SQL
                    CREATE OR REPLACE FUNCTION nexiam_omr_component_{$table}_{$functionEvent}_seal_guard() RETURNS trigger AS \$\$
                    BEGIN
                        {$guardStart}
                            {$parentLocks}
                            {$lockRoots}
                            IF {$condition} THEN
                                RAISE EXCEPTION 'Sealed OMR component descendants cannot be inserted, changed, deleted or reparented';
                            END IF;
                        {$guardEnd}
                        IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                        RETURN NEW;
                    END;
                    \$\$ LANGUAGE plpgsql
                    SQL);
                $triggerName = "omr_component_{$table}_".strtolower($event).'_seal_guard';
                DB::unprepared("DROP TRIGGER IF EXISTS {$triggerName} ON {$table}");
                DB::unprepared("CREATE TRIGGER {$triggerName} BEFORE {$event} ON {$table} FOR EACH ROW EXECUTE FUNCTION nexiam_omr_component_{$table}_{$functionEvent}_seal_guard()");

                continue;
            }

            $newRoot = $this->rootExpression($kind, $event === 'DELETE' ? 'OLD' : 'NEW');
            $sealedNew = $this->sealedRootExpression($kind, $newRoot);
            $checks = [$sealedNew];
            if ($event === 'UPDATE') {
                $checks[] = $this->sealedRootExpression($kind, $this->rootExpression($kind, 'OLD'));
            }
            if ($kind === 'booklet_set_item') {
                if ($event !== 'INSERT') {
                    $checks[] = $this->sealedRootExpression('booklet_item', $this->rootExpression('booklet_item', 'OLD'));
                }
                if ($event !== 'DELETE') {
                    $checks[] = $this->sealedRootExpression('booklet_item', $this->rootExpression('booklet_item', 'NEW'));
                }
            }
            $allowEvidenceInsert = $table === 'omr_template_assets' && $event === 'INSERT';
            $condition = '('.implode(' OR ', $checks).')'.($allowEvidenceInsert
                ? " AND NEW.kind NOT IN ('evidence', 'digital_proof')
                   AND NOT (NEW.kind = 'print_pdf' AND json_extract(NEW.metadata, '$.role') IN ('challenge_sheet', 'challenge_guide_pdf'))
                   AND NOT (NEW.kind = 'raster_page' AND json_extract(NEW.metadata, '$.role') = 'challenge_sheet_page')"
                : '');
            DB::unprepared("CREATE TRIGGER omr_component_{$table}_".strtolower($event)."_seal_guard BEFORE {$event} ON {$table} FOR EACH ROW WHEN {$condition} BEGIN SELECT RAISE(ABORT, 'Sealed OMR component descendants cannot be inserted, changed, deleted or reparented'); END");
        }
    }

    /**
     * @return list<string>
     */
    private function ancestorLockStatements(string $kind, string $event): array
    {
        $old = $event === 'INSERT' ? 'NULL' : 'OLD';
        $new = $event === 'DELETE' ? 'NULL' : 'NEW';

        if ($kind === 'sheet_question') {
            $areaIds = implode(', ', array_unique([
                $old === 'NULL' ? 'NULL' : "{$old}.template_area_id",
                $new === 'NULL' ? 'NULL' : "{$new}.template_area_id",
            ]));

            return ["PERFORM a.id FROM omr_template_areas a WHERE a.id IN ({$areaIds}) ORDER BY a.id FOR SHARE;"];
        }

        if ($kind === 'sheet_option') {
            $questionIds = implode(', ', array_unique([
                $old === 'NULL' ? 'NULL' : "{$old}.template_question_id",
                $new === 'NULL' ? 'NULL' : "{$new}.template_question_id",
            ]));
            $areaIds = "SELECT q.template_area_id FROM omr_template_questions q WHERE q.id IN ({$questionIds})";

            return [
                "PERFORM q.id FROM omr_template_questions q WHERE q.id IN ({$questionIds}) ORDER BY q.id FOR SHARE;",
                "PERFORM a.id FROM omr_template_areas a WHERE a.id IN ({$areaIds}) ORDER BY a.id FOR SHARE;",
            ];
        }

        if ($kind === 'booklet_set_item') {
            $setIds = implode(', ', array_unique([
                $old === 'NULL' ? 'NULL' : "{$old}.booklet_set_id",
                $new === 'NULL' ? 'NULL' : "{$new}.booklet_set_id",
            ]));
            $itemIds = implode(', ', array_unique([
                $old === 'NULL' ? 'NULL' : "{$old}.booklet_item_id",
                $new === 'NULL' ? 'NULL' : "{$new}.booklet_item_id",
            ]));

            return [
                "PERFORM i.id FROM omr_booklet_items i WHERE i.id IN ({$itemIds}) ORDER BY i.id FOR SHARE;",
                "PERFORM s.id FROM omr_booklet_sets s WHERE s.id IN ({$setIds}) ORDER BY s.id FOR SHARE;",
            ];
        }

        return [];
    }

    private function sealedRootExpression(string $kind, string $root): string
    {
        return str_starts_with($kind, 'sheet')
            ? 'EXISTS (SELECT 1 FROM '.self::SHEET." v WHERE v.id = {$root} AND v.status = 'compiled')"
            : 'EXISTS (SELECT 1 FROM '.self::BOOKLET." v WHERE v.id = {$root} AND v.status = 'finalized')";
    }

    private function rootExpression(string $kind, string $row): string
    {
        if ($row === 'NULL') {
            return 'NULL';
        }

        return match ($kind) {
            'sheet_area', 'sheet_version' => "{$row}.template_version_id",
            'sheet_question' => "(SELECT a.template_version_id FROM omr_template_areas a WHERE a.id = {$row}.template_area_id)",
            'sheet_option' => "(SELECT a.template_version_id FROM omr_template_questions q JOIN omr_template_areas a ON a.id = q.template_area_id WHERE q.id = {$row}.template_question_id)",
            'sheet_asset' => "{$row}.template_version_id",
            'booklet_version' => "{$row}.booklet_version_id",
            'booklet_set' => "{$row}.booklet_version_id",
            'booklet_set_item' => "(SELECT s.booklet_version_id FROM omr_booklet_sets s WHERE s.id = {$row}.booklet_set_id)",
            'booklet_item' => "(SELECT i.booklet_version_id FROM omr_booklet_items i WHERE i.id = {$row}.booklet_item_id)",
            default => throw new InvalidArgumentException("Unknown OMR component descendant {$kind}."),
        };
    }
};
