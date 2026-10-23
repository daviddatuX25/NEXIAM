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

        DB::statement('ALTER TABLE score_records DROP CONSTRAINT IF EXISTS score_records_source_check');
        DB::statement("ALTER TABLE score_records ADD CONSTRAINT score_records_source_check CHECK (source IN ('manual', 'omr', 'import'))");
        DB::statement('ALTER TABLE score_records DROP CONSTRAINT IF EXISTS score_records_provenance_check');
        DB::statement("ALTER TABLE score_records ADD CONSTRAINT score_records_provenance_check CHECK ((source = 'omr' AND capture_sheet_id IS NOT NULL AND confidence IS NOT NULL) OR (source IN ('manual', 'import') AND capture_sheet_id IS NULL AND confidence IS NULL))");

        DB::statement('ALTER TABLE score_record_revisions DROP CONSTRAINT IF EXISTS score_revisions_source_check');
        DB::statement("ALTER TABLE score_record_revisions ADD CONSTRAINT score_revisions_source_check CHECK (source IN ('manual', 'omr', 'import'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE score_records DROP CONSTRAINT IF EXISTS score_records_source_check');
        DB::statement("ALTER TABLE score_records ADD CONSTRAINT score_records_source_check CHECK (source IN ('manual', 'omr'))");
        DB::statement('ALTER TABLE score_records DROP CONSTRAINT IF EXISTS score_records_provenance_check');
        DB::statement("ALTER TABLE score_records ADD CONSTRAINT score_records_provenance_check CHECK ((source = 'omr' AND capture_sheet_id IS NOT NULL) OR (source = 'manual' AND capture_sheet_id IS NULL AND confidence IS NULL))");

        DB::statement('ALTER TABLE score_record_revisions DROP CONSTRAINT IF EXISTS score_revisions_source_check');
        DB::statement("ALTER TABLE score_record_revisions ADD CONSTRAINT score_revisions_source_check CHECK (source IN ('manual', 'omr'))");
    }
};
