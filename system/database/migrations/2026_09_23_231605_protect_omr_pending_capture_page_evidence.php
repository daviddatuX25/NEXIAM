<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION omr_pending_page_evidence_immutable() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'omr_capture_set_evidence_immutable';
            END;
            $$ LANGUAGE plpgsql
            SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER omr_pending_page_evidence_immutable_trigger
            BEFORE UPDATE OR DELETE ON omr_pending_capture_page_evidence
            FOR EACH ROW EXECUTE FUNCTION omr_pending_page_evidence_immutable()
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS omr_pending_page_evidence_immutable_trigger ON omr_pending_capture_page_evidence');
        DB::statement('DROP FUNCTION IF EXISTS omr_pending_page_evidence_immutable()');
    }
};
