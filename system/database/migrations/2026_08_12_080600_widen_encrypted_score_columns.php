<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (config('database.default') !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE score_records ALTER COLUMN score TYPE text');
        DB::statement('ALTER TABLE score_records ALTER COLUMN converted_score TYPE text');
    }

    public function down(): void
    {
        throw new RuntimeException('Encrypted score storage widening is forward-only; restore the pre-cut database backup.');
    }
};
