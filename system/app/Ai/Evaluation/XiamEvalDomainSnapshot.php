<?php

declare(strict_types=1);

namespace App\Ai\Evaluation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Keyed, in-memory fingerprint of application state for evaluator mutation checks.
 * Fingerprints and row contents are never intended for reports.
 */
final class XiamEvalDomainSnapshot
{
    /** Framework/runtime control tables, not application/domain state. */
    private const EXCLUDED_TABLES = [
        'cache',
        'cache_locks',
        'failed_jobs',
        'job_batches',
        'jobs',
        'migrations',
        'password_reset_tokens',
        'sessions',
        'personal_access_tokens',
        'sqlite_sequence',
    ];

    /** @return array<string, array{fingerprint: string, rows: int}> */
    public function capture(): array
    {
        $appKey = config('app.key');
        if (! is_string($appKey) || $appKey === '') {
            throw new RuntimeException('An application key is required for evaluation snapshots.');
        }
        $key = hash_hmac('sha256', 'xiam-eval-domain-snapshot-v1', $appKey, true);
        $snapshot = [];
        $tables = array_map(
            static fn (string $table): string => self::normalizeTableName($table),
            Schema::getTableListing(),
        );
        $tables = array_values(array_unique(array_filter(
            $tables,
            static fn (string $table): bool => ! str_starts_with($table, 'xiam_') && ! in_array($table, self::EXCLUDED_TABLES, true),
        )));
        sort($tables, SORT_STRING);

        foreach ($tables as $table) {
            $rowFingerprints = [];
            foreach (DB::table($table)->cursor() as $record) {
                $row = (array) $record;
                ksort($row, SORT_STRING);
                $rowFingerprints[] = hash_hmac('sha256', serialize($row), $key);
            }
            sort($rowFingerprints, SORT_STRING);
            $snapshot[$table] = [
                'fingerprint' => hash_hmac('sha256', implode("\n", $rowFingerprints), $key),
                'rows' => count($rowFingerprints),
            ];
        }

        return $snapshot;
    }

    private static function normalizeTableName(string $table): string
    {
        $separator = strrpos($table, '.');

        return $separator === false ? $table : substr($table, $separator + 1);
    }

    /** @param array<string, array{fingerprint: string, rows: int}> $before
     * @param  array<string, array{fingerprint: string, rows: int}>  $after
     * @return array{changed: bool, changed_tables: list<array{table: string, before_rows: int, after_rows: int}>}
     */
    public function compare(array $before, array $after): array
    {
        $tables = array_values(array_unique(array_merge(array_keys($before), array_keys($after))));
        sort($tables, SORT_STRING);
        $changedTables = [];
        foreach ($tables as $table) {
            $beforeState = $before[$table] ?? ['fingerprint' => null, 'rows' => 0];
            $afterState = $after[$table] ?? ['fingerprint' => null, 'rows' => 0];
            if ($beforeState['fingerprint'] !== $afterState['fingerprint']) {
                $changedTables[] = [
                    'table' => $table,
                    'before_rows' => $beforeState['rows'],
                    'after_rows' => $afterState['rows'],
                ];
            }
        }

        return ['changed' => $changedTables !== [], 'changed_tables' => $changedTables];
    }
}
