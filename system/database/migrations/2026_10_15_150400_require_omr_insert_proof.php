<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION assessment_scoring_run_omr_insert_proof_guard() RETURNS trigger AS $$
                BEGIN
                    IF NEW.source = 'omr'
                        AND (
                            NEW.test_kit_version_id IS NULL
                            OR NEW.test_kit_version_revision IS NULL
                            OR NEW.manifest_sha256 IS NULL
                            OR NEW.acceptance_receipt_id IS NULL
                        ) THEN
                        RAISE EXCEPTION 'Assessment scoring run cannot be created as OMR without operation acceptance proof';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
            SQL);
            DB::statement('DROP TRIGGER IF EXISTS assessment_scoring_run_omr_insert_proof_guard ON assessment_scoring_runs');
            DB::statement('CREATE TRIGGER assessment_scoring_run_omr_insert_proof_guard BEFORE INSERT ON assessment_scoring_runs FOR EACH ROW EXECUTE FUNCTION assessment_scoring_run_omr_insert_proof_guard()');

            return;
        }

        DB::statement(<<<'SQL'
            CREATE TRIGGER assessment_scoring_run_omr_insert_proof_guard
            BEFORE INSERT ON assessment_scoring_runs
            FOR EACH ROW
            WHEN NEW.source = 'omr'
                AND (
                    NEW.test_kit_version_id IS NULL
                    OR NEW.test_kit_version_revision IS NULL
                    OR NEW.manifest_sha256 IS NULL
                    OR NEW.acceptance_receipt_id IS NULL
                )
            BEGIN
                SELECT RAISE(ABORT, 'Assessment scoring run cannot be created as OMR without operation acceptance proof');
            END
        SQL);
    }

    public function down(): void
    {
        throw new RuntimeException(
            'OMR operation proof is prospective history integrity; use a forward-fix migration instead of rolling it back.',
        );
    }
};
