<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SHEET = 'omr_answer_sheet_template_versions';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE omr_answer_sheet_template_versions
                DROP CONSTRAINT IF EXISTS omr_template_versions_status_check,
                DROP CONSTRAINT IF EXISTS omr_template_versions_activation_check,
                ADD CONSTRAINT omr_template_versions_status_check
                    CHECK (status IN ('draft', 'compiled', 'active', 'retired')) NOT VALID,
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
                        OR (status IN ('active', 'retired') AND activated_at IS NOT NULL)
                    ) NOT VALID
        SQL);

        $this->replaceSheetActivationGuard(allowActivation: true);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
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

        $this->replaceSheetActivationGuard(allowActivation: false);
    }

    private function replaceSheetActivationGuard(bool $allowActivation): void
    {
        $columns = array_values(array_diff(
            Schema::getColumnListing(self::SHEET),
            ['id', 'updated_at', 'status', 'activated_by_user_id', 'activated_at'],
        ));
        $changed = implode("\n                        OR ", array_map(
            static fn (string $column): string => "to_jsonb(OLD.{$column}) IS DISTINCT FROM to_jsonb(NEW.{$column})",
            $columns,
        ));

        if ($allowActivation) {
            $compiledUpdate = <<<'SQL'
                IF OLD.status = 'compiled' THEN
                    IF NEW.status = 'active' THEN
                        IF ({CHANGED})
                            OR NEW.activated_by_user_id IS NULL
                            OR NEW.activated_at IS NULL
                            OR NEW.native_bundle_hash IS NULL
                            OR NEW.native_bundle_hash !~ '^[a-f0-9]{64}$'
                            OR NEW.native_bundle_json IS NULL
                            OR NEW.native_bundle_json::jsonb = 'null'::jsonb THEN
                            RAISE EXCEPTION 'Compiled OMR material can only be activated without changing material identity';
                        END IF;
                        RETURN NEW;
                    END IF;
                    IF ({CHANGED})
                        OR NEW.status <> 'compiled'
                        OR NEW.activated_by_user_id IS DISTINCT FROM OLD.activated_by_user_id
                        OR NEW.activated_at IS DISTINCT FROM OLD.activated_at THEN
                        RAISE EXCEPTION 'Sealed OMR component material is immutable';
                    END IF;
                    RETURN NEW;
                END IF;
                SQL;
        } else {
            $compiledUpdate = <<<'SQL'
                IF OLD.status = 'compiled' THEN
                    IF ({CHANGED}) OR NEW.status <> 'compiled' THEN
                        RAISE EXCEPTION 'Sealed OMR component material is immutable';
                    END IF;
                    RETURN NEW;
                END IF;
                SQL;
        }

        $function = str_replace('{CHANGED}', $changed, $compiledUpdate);
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION nexiam_omr_component_omr_answer_sheet_template_versions_seal_guard() RETURNS trigger AS \$\$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.status = 'compiled' THEN
                        RAISE EXCEPTION 'Sealed OMR component versions cannot be deleted';
                    END IF;
                    RETURN OLD;
                END IF;
                IF TG_OP = 'INSERT' THEN
                    IF NEW.status = 'compiled' AND NOT (
                        NEW.native_bundle_hash IS NOT NULL
                        AND NEW.native_bundle_hash ~ '^[a-f0-9]{64}$'
                        AND NEW.native_bundle_json IS NOT NULL
                        AND NEW.native_bundle_json::jsonb <> 'null'::jsonb
                    ) THEN
                        RAISE EXCEPTION 'Sealed OMR component version is missing required material identity';
                    END IF;
                    RETURN NEW;
                END IF;
                {$function}
                IF NEW.status = 'compiled' AND NOT (
                    NEW.native_bundle_hash IS NOT NULL
                    AND NEW.native_bundle_hash ~ '^[a-f0-9]{64}$'
                    AND NEW.native_bundle_json IS NOT NULL
                    AND NEW.native_bundle_json::jsonb <> 'null'::jsonb
                ) THEN
                    RAISE EXCEPTION 'Sealed OMR component version is missing required material identity';
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql
        SQL);
    }
};
