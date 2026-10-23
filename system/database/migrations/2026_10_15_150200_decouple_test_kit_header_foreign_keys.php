<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    private const SQLITE_REBUILD_TABLE = 'test_kit_versions_component_fk_rebuild';

    private const REPLACED_FOREIGN_KEY_SIGNATURES = [
        [
            ['template_version_id', 'test_instrument_version_id'],
            'omr_answer_sheet_template_versions',
            ['id', 'test_instrument_version_id'],
        ],
        [
            ['booklet_version_id', 'template_version_id', 'test_instrument_version_id'],
            'omr_question_booklet_versions',
            ['id', 'template_version_id', 'test_instrument_version_id'],
        ],
        [
            ['template_version_id', 'template_id', 'test_instrument_version_id'],
            'omr_answer_sheet_template_versions',
            ['id', 'template_id', 'test_instrument_version_id'],
        ],
        [
            ['booklet_version_id', 'booklet_id', 'template_version_id', 'test_instrument_version_id'],
            'omr_question_booklet_versions',
            ['id', 'booklet_id', 'template_version_id', 'test_instrument_version_id'],
        ],
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            return;
        }

        if (DB::getDriverName() !== 'sqlite') {
            throw new RuntimeException('Test Kit independent component ownership requires PostgreSQL or SQLite.');
        }

        $this->rebuildSqliteTestKitVersions();
    }

    private function rebuildSqliteTestKitVersions(): void
    {
        $connection = DB::connection();
        if ($connection->transactionLevel() !== 0) {
            throw new RuntimeException('The SQLite Test Kit table rebuild cannot run inside an existing transaction.');
        }
        $foreignKeyState = DB::selectOne('PRAGMA foreign_keys');
        if ((int) ($foreignKeyState->foreign_keys ?? 0) !== 1) {
            throw new RuntimeException('SQLite foreign-key enforcement must be enabled before the Test Kit table rebuild.');
        }
        $legacyAlterState = DB::selectOne('PRAGMA legacy_alter_table');
        if (! in_array((int) ($legacyAlterState->legacy_alter_table ?? -1), [0, 1], true)) {
            throw new RuntimeException('SQLite legacy ALTER TABLE mode is unavailable for the Test Kit table rebuild.');
        }
        $legacyAlterValue = (int) $legacyAlterState->legacy_alter_table;
        $table = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'test_kit_versions'");
        if (! is_string($table?->sql ?? null)) {
            throw new RuntimeException('The Test Kit version table definition is unavailable for its component ownership migration.');
        }
        if (DB::table('sqlite_master')->where('type', 'table')->where('name', self::SQLITE_REBUILD_TABLE)->exists()) {
            throw new RuntimeException('A prior Test Kit version rebuild table exists; refusing to overwrite it.');
        }

        $indexes = DB::select("SELECT sql FROM sqlite_master WHERE type = 'index' AND tbl_name = 'test_kit_versions' AND sql IS NOT NULL");
        $triggers = DB::select("SELECT sql FROM sqlite_master WHERE type = 'trigger' AND tbl_name = 'test_kit_versions' AND sql IS NOT NULL");
        $columns = array_map(
            static fn (object $column): string => '"'.str_replace('"', '""', $column->name).'"',
            DB::select("PRAGMA table_info('test_kit_versions')"),
        );

        $definitions = $this->splitSqliteTableDefinitions($table->sql);
        $replacedForeignKeyCount = 0;
        $definitions = array_values(array_filter($definitions, function (string $definition) use (&$replacedForeignKeyCount): bool {
            if (! $this->isReplacedCrossHeaderForeignKey($definition)) {
                return true;
            }
            $replacedForeignKeyCount++;

            return false;
        }));
        if ($replacedForeignKeyCount !== count(self::REPLACED_FOREIGN_KEY_SIGNATURES)) {
            throw new RuntimeException('The SQLite Test Kit version table does not contain all expected cross-header foreign-key constraints.');
        }
        $definitions[] = 'CONSTRAINT test_kit_version_sheet_component_exact_fk FOREIGN KEY (template_version_id, template_id) REFERENCES omr_answer_sheet_template_versions (id, template_id) ON DELETE RESTRICT';
        $definitions[] = 'CONSTRAINT test_kit_version_booklet_component_exact_fk FOREIGN KEY (booklet_version_id, booklet_id) REFERENCES omr_question_booklet_versions (id, booklet_id) ON DELETE RESTRICT';

        $open = strpos($table->sql, '(');
        $close = strrpos($table->sql, ')');
        if ($open === false || $close === false || $close <= $open) {
            throw new RuntimeException('The Test Kit version SQLite table definition is malformed.');
        }

        $createPrefix = substr($table->sql, 0, $open);
        $createPrefix = preg_replace('/["`\\[]?test_kit_versions["`\\]]?/i', '"'.self::SQLITE_REBUILD_TABLE.'"', $createPrefix, 1);
        if (! is_string($createPrefix)) {
            throw new RuntimeException('The Test Kit version SQLite table name could not be rewritten.');
        }
        $body = implode(', ', $definitions);

        DB::statement('PRAGMA foreign_keys = OFF');
        try {
            DB::statement('PRAGMA legacy_alter_table = ON');
            $connection->transaction(function () use ($createPrefix, $body, $columns, $indexes, $triggers): void {
                DB::statement($createPrefix.'('.$body.')');
                $columnList = implode(', ', $columns);
                DB::statement('INSERT INTO '.self::SQLITE_REBUILD_TABLE.' ('.$columnList.') SELECT '.$columnList.' FROM test_kit_versions');
                DB::statement('DROP TABLE test_kit_versions');
                DB::statement('ALTER TABLE '.self::SQLITE_REBUILD_TABLE.' RENAME TO test_kit_versions');

                foreach ($indexes as $index) {
                    DB::statement($index->sql);
                }
                foreach ($triggers as $trigger) {
                    DB::statement($trigger->sql);
                }

                if (DB::select('PRAGMA foreign_key_check') !== []) {
                    throw new RuntimeException('The Test Kit version SQLite component ownership rebuild found foreign-key violations.');
                }
            });
        } finally {
            try {
                DB::statement('PRAGMA legacy_alter_table = '.$legacyAlterValue);
            } finally {
                DB::statement('PRAGMA foreign_keys = ON');
                $restoredForeignKeyState = DB::selectOne('PRAGMA foreign_keys');
                if ((int) ($restoredForeignKeyState->foreign_keys ?? 0) !== 1) {
                    throw new RuntimeException('SQLite foreign-key enforcement could not be restored after the Test Kit table rebuild.');
                }
            }
        }
    }

    private function isReplacedCrossHeaderForeignKey(string $definition): bool
    {
        if (preg_match(
            '/^\\s*(?:CONSTRAINT\\s+(?:"[^"]+"|`[^`]+`|\\[[^\\]]+\\]|[A-Za-z_][A-Za-z0-9_]*)\\s+)?FOREIGN\\s+KEY\\s*\\(([^()]*)\\)\\s+REFERENCES\\s+(?:"([^"]+)"|`([^`]+)`|\\[([^\\]]+)\\]|([A-Za-z_][A-Za-z0-9_]*))\\s*\\(([^()]*)\\)/i',
            trim($definition),
            $matches,
        ) !== 1) {
            return false;
        }

        $normalizeColumns = static fn (string $columns): array => array_map(
            static fn (string $column): string => strtolower(trim($column, " \t\n\r\0\x0B\"`[]")),
            explode(',', $columns),
        );
        $localColumns = $normalizeColumns($matches[1]);
        $referencedTable = strtolower((string) ($matches[2] ?: $matches[3] ?: $matches[4] ?: $matches[5]));
        $referencedColumns = $normalizeColumns($matches[6]);

        foreach (self::REPLACED_FOREIGN_KEY_SIGNATURES as [$local, $table, $referenced]) {
            if ($localColumns === $local
                && $referencedTable === $table
                && $referencedColumns === $referenced) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function splitSqliteTableDefinitions(string $sql): array
    {
        $open = strpos($sql, '(');
        $close = strrpos($sql, ')');
        if ($open === false || $close === false || $close <= $open) {
            throw new RuntimeException('The Test Kit version SQLite table definition is malformed.');
        }

        $body = substr($sql, $open + 1, $close - $open - 1);
        $definitions = [];
        $start = 0;
        $depth = 0;
        $quote = null;
        $length = strlen($body);
        for ($index = 0; $index < $length; $index++) {
            $character = $body[$index];
            if ($quote !== null) {
                if ($character === $quote) {
                    if (($body[$index + 1] ?? null) === $quote && $quote !== ']') {
                        $index++;
                    } else {
                        $quote = null;
                    }
                }

                continue;
            }
            if ($character === "'" || $character === '"' || $character === '`' || $character === '[') {
                $quote = $character === '[' ? ']' : $character;

                continue;
            }
            if ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;
            } elseif ($character === ',' && $depth === 0) {
                $definitions[] = trim(substr($body, $start, $index - $start));
                $start = $index + 1;
            }
        }
        $definitions[] = trim(substr($body, $start));

        return array_values(array_filter($definitions, static fn (string $definition): bool => $definition !== ''));
    }

    public function down(): void
    {
        throw new RuntimeException('Independent Test Kit component ownership is a forward-only integrity correction; use a forward migration instead of restoring header coupling.');
    }
};
