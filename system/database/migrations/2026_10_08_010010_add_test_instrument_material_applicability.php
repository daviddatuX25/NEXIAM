<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_instrument_versions', function (Blueprint $table): void {
            $table->enum('material_requirement', ['unresolved', 'paper_backed', 'no_paper'])
                ->default('unresolved')
                ->after('permitted_use_reference');
            $table->string('administration_procedure_reference', 500)
                ->nullable()
                ->after('material_requirement');
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS test_instrument_version_immutability ON test_instrument_versions;

CREATE OR REPLACE FUNCTION test_instrument_version_guard() RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        IF NEW.status IN ('approved', 'retired')
            AND (NEW.material_requirement = 'unresolved'
                OR NEW.administration_procedure_reference IS NULL
                OR btrim(NEW.administration_procedure_reference) = '') THEN
            RAISE EXCEPTION 'Approved test instrument versions require declared material applicability and an administration procedure reference';
        END IF;
        RETURN NEW;
    END IF;

    IF TG_OP = 'DELETE' THEN
        IF OLD.status <> 'draft' THEN
            RAISE EXCEPTION 'Approved test instrument versions are immutable';
        END IF;
        RETURN OLD;
    END IF;

    IF OLD.status = 'retired' THEN
        RAISE EXCEPTION 'Retired test instrument versions are immutable';
    END IF;

    IF OLD.status = 'draft' AND NEW.status NOT IN ('draft', 'approved') THEN
        RAISE EXCEPTION 'Draft test instrument versions may only be saved or approved';
    END IF;

    IF OLD.status = 'draft' AND NEW.status = 'approved'
        AND (NEW.material_requirement = 'unresolved'
            OR NEW.administration_procedure_reference IS NULL
            OR btrim(NEW.administration_procedure_reference) = '') THEN
        RAISE EXCEPTION 'Approved test instrument versions require declared material applicability and an administration procedure reference';
    END IF;

    IF OLD.status = 'approved' AND NOT (
        NEW.status = 'retired'
        AND NEW.id = OLD.id
        AND NEW.test_instrument_id = OLD.test_instrument_id
        AND NEW.version_number = OLD.version_number
        AND NEW.display_name = OLD.display_name
        AND NEW.publisher_name IS NOT DISTINCT FROM OLD.publisher_name
        AND NEW.form_code IS NOT DISTINCT FROM OLD.form_code
        AND NEW.edition IS NOT DISTINCT FROM OLD.edition
        AND NEW.language IS NOT DISTINCT FROM OLD.language
        AND NEW.source_reference IS NOT DISTINCT FROM OLD.source_reference
        AND NEW.permitted_use_reference IS NOT DISTINCT FROM OLD.permitted_use_reference
        AND NEW.material_requirement = OLD.material_requirement
        AND NEW.administration_procedure_reference IS NOT DISTINCT FROM OLD.administration_procedure_reference
        AND NEW.source_reviewed_by_user_id = OLD.source_reviewed_by_user_id
        AND NEW.source_reviewed_at = OLD.source_reviewed_at
        AND NEW.approved_by_user_id = OLD.approved_by_user_id
        AND NEW.approved_at = OLD.approved_at
        AND NEW.predecessor_version_id IS NOT DISTINCT FROM OLD.predecessor_version_id
        AND NEW.lock_version = OLD.lock_version
        AND NEW.content_sha256 = OLD.content_sha256
        AND NEW.created_by_user_id IS NOT DISTINCT FROM OLD.created_by_user_id
        AND NEW.created_at = OLD.created_at
    ) THEN
        RAISE EXCEPTION 'Approved test instrument versions permit retirement only';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER test_instrument_version_immutability
    BEFORE INSERT OR UPDATE OR DELETE ON test_instrument_versions
    FOR EACH ROW EXECUTE FUNCTION test_instrument_version_guard();
SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS test_instrument_version_immutability ON test_instrument_versions;

CREATE OR REPLACE FUNCTION test_instrument_version_guard() RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        IF OLD.status <> 'draft' THEN
            RAISE EXCEPTION 'Approved test instrument versions are immutable';
        END IF;
        RETURN OLD;
    END IF;

    IF OLD.status = 'retired' THEN
        RAISE EXCEPTION 'Retired test instrument versions are immutable';
    END IF;

    IF OLD.status = 'draft' AND NEW.status NOT IN ('draft', 'approved') THEN
        RAISE EXCEPTION 'Draft test instrument versions may only be saved or approved';
    END IF;

    IF OLD.status = 'approved' AND NOT (
        NEW.status = 'retired'
        AND NEW.id = OLD.id
        AND NEW.test_instrument_id = OLD.test_instrument_id
        AND NEW.version_number = OLD.version_number
        AND NEW.display_name = OLD.display_name
        AND NEW.publisher_name IS NOT DISTINCT FROM OLD.publisher_name
        AND NEW.form_code IS NOT DISTINCT FROM OLD.form_code
        AND NEW.edition IS NOT DISTINCT FROM OLD.edition
        AND NEW.language IS NOT DISTINCT FROM OLD.language
        AND NEW.source_reference IS NOT DISTINCT FROM OLD.source_reference
        AND NEW.permitted_use_reference IS NOT DISTINCT FROM OLD.permitted_use_reference
        AND NEW.source_reviewed_by_user_id = OLD.source_reviewed_by_user_id
        AND NEW.source_reviewed_at = OLD.source_reviewed_at
        AND NEW.approved_by_user_id = OLD.approved_by_user_id
        AND NEW.approved_at = OLD.approved_at
        AND NEW.predecessor_version_id IS NOT DISTINCT FROM OLD.predecessor_version_id
        AND NEW.lock_version = OLD.lock_version
        AND NEW.content_sha256 = OLD.content_sha256
        AND NEW.created_by_user_id IS NOT DISTINCT FROM OLD.created_by_user_id
        AND NEW.created_at = OLD.created_at
    ) THEN
        RAISE EXCEPTION 'Approved test instrument versions permit retirement only';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER test_instrument_version_immutability
    BEFORE UPDATE OR DELETE ON test_instrument_versions
    FOR EACH ROW EXECUTE FUNCTION test_instrument_version_guard();
SQL);
        }

        Schema::table('test_instrument_versions', function (Blueprint $table): void {
            $table->dropColumn(['administration_procedure_reference', 'material_requirement']);
        });
    }
};
