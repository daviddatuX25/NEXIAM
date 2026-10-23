<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX omr_verification_captures_variant_unique
                ON omr_template_verification_captures (run_id, attempt, variant, page_no)
                WHERE variant IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS omr_verification_captures_variant_unique');
    }
};
