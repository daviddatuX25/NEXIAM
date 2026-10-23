<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $definition = DB::selectOne(
            "SELECT pg_get_functiondef('nexiam_exam_session_kit_pin_guard()'::regprocedure) AS definition",
        )?->definition;
        $expected = <<<'SQL'
            requires_booklet := (kit_row.manifest_json::jsonb #> '{requirements,required_resources}') ? 'booklet_form'
                OR kit_row.manifest_json::jsonb #>> '{optional_attachment,version}' = '1';
            SQL;
        $replacement = <<<'SQL'
            requires_booklet := (kit_row.manifest_json::jsonb #> '{requirements,required_resources}') ? 'booklet_form'
                OR COALESCE(kit_row.manifest_json::jsonb #>> '{optional_attachment,version}' = '1', false);
            SQL;
        $pattern = '/'.implode('\s+', array_map(
            static fn (string $part): string => preg_quote($part, '/'),
            preg_split('/\s+/', trim($expected)) ?: [],
        )).'/';

        if (! is_string($definition) || preg_match_all($pattern, $definition) !== 1) {
            throw new RuntimeException('The session Kit guard did not match the expected optional-attachment definition.');
        }

        DB::unprepared((string) preg_replace($pattern, addcslashes($replacement, '\\$'), $definition, 1));
    }

    public function down(): void
    {
        throw new RuntimeException('The null-safe session Kit guard is forward-only; restoring it would reject valid Setup-only sessions.');
    }
};
