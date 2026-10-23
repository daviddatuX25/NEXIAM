<?php

declare(strict_types=1);

namespace App\Ai\Evaluation;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Throwable;

final class XiamEvalIsolationGuard
{
    /** @return array{driver: string, database: string}|null */
    public function verifiedDatabase(): ?array
    {
        if (! App::environment(['local', 'testing']) || config('xiam.eval.enabled') !== true) {
            return null;
        }

        $expected = config('xiam.eval.database');
        if (! is_string($expected) || trim($expected) === '' || strtolower($expected) === 'nexiam') {
            return null;
        }

        $connectionName = (string) config('database.default');
        $connection = config('database.connections.'.$connectionName);
        if (! is_array($connection) || ! is_string($connection['driver'] ?? null)) {
            return null;
        }

        $driver = $connection['driver'];
        if ($driver === 'pgsql') {
            $configuredHost = $connection['host'] ?? null;
            $allowedHosts = config('xiam.eval.database_hosts');
            if (! is_string($configuredHost) || $configuredHost === ''
                || ! is_array($allowedHosts) || ! in_array($configuredHost, $allowedHosts, true)) {
                return null;
            }
        }

        try {
            $pdo = DB::connection($connectionName)->getPdo();
            if ($driver === 'pgsql') {
                $actual = (string) $pdo->query('select current_database()')->fetchColumn();
                if ($actual !== $expected || preg_match('/(?:_xiam_eval$|^xiam_eval_)/i', $actual) !== 1 || strtolower($actual) === 'nexiam') {
                    return null;
                }

                return ['driver' => $driver, 'database' => $actual];
            }

            if ($driver === 'sqlite') {
                $configuredPath = $connection['database'] ?? null;
                $databaseRow = $pdo->query('PRAGMA database_list')?->fetch(\PDO::FETCH_ASSOC);
                $actual = is_array($databaseRow) ? (string) ($databaseRow['file'] ?? '') : '';
                if (! is_string($configuredPath) || $configuredPath === '' || $configuredPath === ':memory:'
                    || ! str_contains(strtolower($configuredPath), 'xiam-eval')
                    || $configuredPath !== $expected || $actual === '' || $actual === ':memory:' || $actual !== $expected
                    || realpath($configuredPath) === false || realpath($actual) !== realpath($configuredPath)) {
                    return null;
                }

                return ['driver' => $driver, 'database' => basename($actual)];
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    public function failure(): ?string
    {
        if (! App::environment(['local', 'testing'])) {
            return 'Xiam evaluation is restricted to APP_ENV=local or APP_ENV=testing.';
        }
        if (config('xiam.eval.enabled') !== true) {
            return 'Live evaluation requires explicit XIAM_EVAL_ENABLED=true.';
        }
        if ($this->verifiedDatabase() === null) {
            return 'The connected database is not an explicitly approved isolated Xiam evaluation database.';
        }

        return null;
    }
}
