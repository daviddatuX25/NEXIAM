<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_conversion_revisions', function (Blueprint $table): void {
            $table->char('raw_dependency_sha256', 64)->nullable();
            $table->unique(['id', 'administration_id'], 'assessment_conversion_id_admin_unique');
        });

        Schema::table('assessment_raw_result_revisions', function (Blueprint $table): void {
            $table->unique(['id', 'administration_id', 'metric_id'], 'assessment_raw_id_admin_metric_unique');
        });

        Schema::create('assessment_conversion_raw_dependencies', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('conversion_revision_id');
            $table->unsignedBigInteger('administration_id');
            $table->unsignedBigInteger('metric_id');
            $table->unsignedBigInteger('raw_result_revision_id');
            $table->foreign(['conversion_revision_id', 'administration_id'], 'conversion_raw_deps_conversion_owner_fk')
                ->references(['id', 'administration_id'])->on('assessment_conversion_revisions')->restrictOnDelete();
            $table->foreign(['raw_result_revision_id', 'administration_id', 'metric_id'], 'conversion_raw_deps_raw_owner_fk')
                ->references(['id', 'administration_id', 'metric_id'])->on('assessment_raw_result_revisions')->restrictOnDelete();
            $table->unique(['conversion_revision_id', 'metric_id'], 'conversion_raw_deps_conversion_metric_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX assessment_conversion_current_unique ON assessment_conversion_revisions (administration_id) WHERE is_current = true');
        } else {
            DB::statement('CREATE UNIQUE INDEX assessment_conversion_current_unique ON assessment_conversion_revisions (administration_id) WHERE is_current = 1');
        }

        if (DB::getDriverName() === 'pgsql') {
            $this->createPostgresGuards();
        } else {
            $this->createSqliteGuards();
        }
    }

    private function createPostgresGuards(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION assessment_conversion_lineage_guard() RETURNS trigger AS $$
            DECLARE
                dependency_count integer;
                norm_status varchar(16);
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    IF NEW.source = 'norm_converted' AND (
                        NEW.norm_package_version_id IS NULL
                        OR NEW.status <> 'pending'
                        OR NEW.is_current = true
                        OR NEW.raw_dependency_sha256 IS NULL
                        OR length(NEW.raw_dependency_sha256) <> 64
                    ) THEN
                        RAISE EXCEPTION 'Norm conversions must be assembled pending with an exact raw dependency digest';
                    END IF;
                    IF NEW.source = 'manual_converted' AND (
                        NEW.norm_package_version_id IS NOT NULL
                        OR NEW.raw_dependency_sha256 IS NOT NULL
                    ) THEN
                        RAISE EXCEPTION 'External converted finals cannot carry internal norm or raw dependency lineage';
                    END IF;
                    IF NEW.is_current = true AND NEW.status <> 'valid' THEN
                        RAISE EXCEPTION 'Only valid assessment conversions may be current';
                    END IF;
                    IF NEW.status = 'pending' AND NEW.source IS DISTINCT FROM 'norm_converted' THEN
                        RAISE EXCEPTION 'Pending conversion assembly is reserved for norm conversions';
                    END IF;
                    RETURN NEW;
                END IF;

                IF NEW.administration_id IS DISTINCT FROM OLD.administration_id
                    OR NEW.norm_package_version_id IS DISTINCT FROM OLD.norm_package_version_id
                    OR NEW.source IS DISTINCT FROM OLD.source
                    OR NEW.revision_no IS DISTINCT FROM OLD.revision_no
                    OR NEW.typed_outputs_json::text IS DISTINCT FROM OLD.typed_outputs_json::text
                    OR NEW.provenance_json::text IS DISTINCT FROM OLD.provenance_json::text
                    OR NEW.converted_by_user_id IS DISTINCT FROM OLD.converted_by_user_id
                    OR NEW.converted_at IS DISTINCT FROM OLD.converted_at
                    OR NEW.raw_dependency_sha256 IS DISTINCT FROM OLD.raw_dependency_sha256 THEN
                    RAISE EXCEPTION 'Assessment conversion lineage is immutable';
                END IF;

                IF NEW.source = 'norm_converted' AND NEW.status = 'valid'
                    AND OLD.status NOT IN ('pending', 'valid') THEN
                    RAISE EXCEPTION 'Only pending norm conversions can be sealed';
                END IF;

                IF NEW.source = 'norm_converted' AND NEW.status = 'valid'
                    AND OLD.status = 'pending' THEN
                    SELECT count(*) INTO dependency_count
                    FROM assessment_conversion_raw_dependencies
                    WHERE conversion_revision_id = NEW.id
                      AND administration_id = NEW.administration_id;
                    IF dependency_count = 0 THEN
                        RAISE EXCEPTION 'Norm conversion requires exact raw dependencies';
                    END IF;
                    SELECT status INTO norm_status
                    FROM assessment_norm_package_versions
                    WHERE id = NEW.norm_package_version_id;
                    IF norm_status IS DISTINCT FROM 'approved' THEN
                        RAISE EXCEPTION 'Norm conversion requires a currently approved norm version';
                    END IF;
                END IF;

                IF OLD.status = 'superseded' AND NEW.status <> 'superseded' THEN
                    RAISE EXCEPTION 'Superseded assessment conversions cannot be reactivated';
                END IF;

                IF OLD.status = 'valid' AND NEW.status NOT IN ('valid', 'superseded') THEN
                    RAISE EXCEPTION 'Valid assessment conversions may only be superseded';
                END IF;
                IF OLD.status = 'pending' AND NEW.status NOT IN ('pending', 'valid') THEN
                    RAISE EXCEPTION 'Pending norm conversions may only remain pending or become valid';
                END IF;

                IF NEW.is_current = true AND NEW.status <> 'valid' THEN
                    RAISE EXCEPTION 'Only valid assessment conversions may be current';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement('DROP TRIGGER IF EXISTS assessment_conversion_lineage_guard ON assessment_conversion_revisions');
        DB::statement('CREATE TRIGGER assessment_conversion_lineage_guard BEFORE INSERT OR UPDATE ON assessment_conversion_revisions FOR EACH ROW EXECUTE FUNCTION assessment_conversion_lineage_guard()');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION assessment_conversion_raw_dependency_guard() RETURNS trigger AS $$
            DECLARE
                conversion_status varchar(16);
                conversion_source varchar(32);
            BEGIN
                IF TG_OP <> 'INSERT' THEN
                    RAISE EXCEPTION 'Assessment conversion raw dependencies are immutable';
                END IF;
                SELECT status, source INTO conversion_status, conversion_source
                FROM assessment_conversion_revisions
                WHERE id = NEW.conversion_revision_id
                  AND administration_id = NEW.administration_id;
                IF conversion_status IS DISTINCT FROM 'pending'
                    OR conversion_source IS DISTINCT FROM 'norm_converted' THEN
                    RAISE EXCEPTION 'Raw dependencies may only be added while assembling a norm conversion';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement('DROP TRIGGER IF EXISTS assessment_conversion_raw_dependency_guard ON assessment_conversion_raw_dependencies');
        DB::statement('CREATE TRIGGER assessment_conversion_raw_dependency_guard BEFORE INSERT OR UPDATE OR DELETE ON assessment_conversion_raw_dependencies FOR EACH ROW EXECUTE FUNCTION assessment_conversion_raw_dependency_guard()');
    }

    private function createSqliteGuards(): void
    {
        DB::statement(<<<'SQL'
            CREATE TRIGGER assessment_conversion_lineage_insert_guard
            BEFORE INSERT ON assessment_conversion_revisions
            FOR EACH ROW
            WHEN (NEW.source = 'norm_converted' AND (
                    NEW.norm_package_version_id IS NULL
                    OR NEW.status <> 'pending'
                    OR NEW.is_current <> 0
                    OR NEW.raw_dependency_sha256 IS NULL
                    OR length(NEW.raw_dependency_sha256) <> 64
                 ))
                OR (NEW.source = 'manual_converted' AND (
                    NEW.norm_package_version_id IS NOT NULL
                    OR NEW.raw_dependency_sha256 IS NOT NULL
                 ))
                OR (NEW.is_current <> 0 AND NEW.status <> 'valid')
                OR (NEW.status = 'pending' AND NEW.source <> 'norm_converted')
            BEGIN
                SELECT RAISE(ABORT, 'Assessment conversion lineage must be assembled through its supported source');
            END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER assessment_conversion_lineage_update_guard
            BEFORE UPDATE ON assessment_conversion_revisions
            FOR EACH ROW
            WHEN NEW.administration_id <> OLD.administration_id
                OR NEW.norm_package_version_id IS NOT OLD.norm_package_version_id
                OR NEW.source <> OLD.source
                OR NEW.revision_no <> OLD.revision_no
                OR NEW.typed_outputs_json IS NOT OLD.typed_outputs_json
                OR NEW.provenance_json IS NOT OLD.provenance_json
                OR NEW.converted_by_user_id IS NOT OLD.converted_by_user_id
                OR NEW.converted_at IS NOT OLD.converted_at
                OR NEW.raw_dependency_sha256 IS NOT OLD.raw_dependency_sha256
                OR (NEW.source = 'norm_converted' AND NEW.status = 'valid' AND OLD.status NOT IN ('pending', 'valid'))
                OR (NEW.source = 'norm_converted' AND NEW.status = 'valid' AND OLD.status = 'pending'
                    AND (NOT EXISTS (
                        SELECT 1 FROM assessment_conversion_raw_dependencies
                        WHERE conversion_revision_id = NEW.id
                          AND administration_id = NEW.administration_id
                    ) OR NOT EXISTS (
                        SELECT 1 FROM assessment_norm_package_versions
                        WHERE id = NEW.norm_package_version_id AND status = 'approved'
                    )))
                OR (OLD.status = 'superseded' AND NEW.status <> 'superseded')
                OR (OLD.status = 'valid' AND NEW.status NOT IN ('valid', 'superseded'))
                OR (OLD.status = 'pending' AND NEW.status NOT IN ('pending', 'valid'))
                OR (NEW.is_current <> 0 AND NEW.status <> 'valid')
            BEGIN
                SELECT RAISE(ABORT, 'Assessment conversion lineage is immutable or incomplete');
            END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER assessment_conversion_raw_dependency_insert_guard
            BEFORE INSERT ON assessment_conversion_raw_dependencies
            FOR EACH ROW
            WHEN NOT EXISTS (
                SELECT 1 FROM assessment_conversion_revisions
                WHERE id = NEW.conversion_revision_id
                  AND administration_id = NEW.administration_id
                  AND source = 'norm_converted'
                  AND status = 'pending'
            )
            BEGIN
                SELECT RAISE(ABORT, 'Raw dependencies may only be added while assembling a norm conversion');
            END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER assessment_conversion_raw_dependency_update_guard
            BEFORE UPDATE ON assessment_conversion_raw_dependencies
            BEGIN
                SELECT RAISE(ABORT, 'Assessment conversion raw dependencies are immutable');
            END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER assessment_conversion_raw_dependency_delete_guard
            BEFORE DELETE ON assessment_conversion_raw_dependencies
            BEGIN
                SELECT RAISE(ABORT, 'Assessment conversion raw dependencies are immutable');
            END
        SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Conversion dependency lineage is prospective history integrity; use a forward-fix migration instead of rolling it back.');
    }
};
