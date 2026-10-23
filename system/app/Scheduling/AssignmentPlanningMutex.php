<?php

namespace App\Scheduling;

use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AssignmentPlanningMutex
{
    public const DOMAIN_EXAM = 'exam';

    public const DOMAIN_CONSULTATION = 'consultation';

    public const DOMAIN_QUOTA = 'quota';

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function run(string $domain, int $academicCycleId, Closure $callback): mixed
    {
        if (! in_array($domain, [self::DOMAIN_EXAM, self::DOMAIN_CONSULTATION, self::DOMAIN_QUOTA], true)) {
            throw new InvalidArgumentException("Unsupported assignment planning domain [{$domain}].");
        }

        if ($academicCycleId <= 0) {
            throw new InvalidArgumentException('Academic cycle ID must be positive.');
        }

        return DB::transaction(function () use ($domain, $academicCycleId, $callback): mixed {
            if (DB::getDriverName() === 'pgsql') {
                DB::selectOne(
                    'select pg_advisory_xact_lock(CAST(? AS bigint))',
                    [(string) $this->lockKey($domain, $academicCycleId)],
                );
            }

            return $callback();
        });
    }

    private function lockKey(string $domain, int $academicCycleId): int
    {
        return (int) hexdec(substr(hash('sha256', "assignment-planning:{$domain}:{$academicCycleId}"), 0, 15));
    }
}
