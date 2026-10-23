<?php

use App\Auth\Actions\EstablishStaffSessionLease;
use App\Auth\Actions\RenewStaffSessionLease;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffSessionLease;
use App\Models\Auth\StaffTwoFactorRecoveryCode;
use App\Models\Foundation\AuditEvent;
use App\Models\User;
use App\Staffing\Actions\IssueStaffActivationVoucher;
use App\Staffing\Actions\RequireStaffMfa;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/** @return array{dsn: string, username: string, password: string} */
function staffAuthPostgresConnectionConfig(): array
{
    $config = DB::connection()->getConfig();

    return [
        'dsn' => sprintf('pgsql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'], $config['database']),
        'username' => $config['username'],
        'password' => $config['password'],
    ];
}

function staffAuthPostgresAssertConcurrencySupport(TestCase $test): void
{
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $test->markTestSkipped('Authentication concurrency acceptance requires PostgreSQL.');
    }

    if (! function_exists('pcntl_fork')) {
        $test->markTestSkipped('Authentication concurrency acceptance requires process fork support.');
    }
}

function staffAuthPostgresPrepareState(User $user): StaffAuthenticationState
{
    $state = StaffAuthenticationState::query()->firstOrNew(['user_id' => $user->id]);
    $state->forceFill([
        'user_id' => $user->id,
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'session_version' => 1,
        'recovery_generation' => (string) Str::uuid(),
    ])->save();

    return $state;
}

/**
 * @param  Closure(): array<string, mixed>  $action
 * @return array{pid:int, application_name:string, result_file:string}
 */
function staffAuthPostgresStartWorker(string $applicationName, Closure $action): array
{
    $resultFile = tempnam(sys_get_temp_dir(), 'staff-auth-race-');
    if ($resultFile === false) {
        throw new RuntimeException('Could not create the staff authentication concurrency result file.');
    }

    $pid = pcntl_fork();
    if ($pid === -1) {
        @unlink($resultFile);
        throw new RuntimeException('Could not fork a staff authentication concurrency worker.');
    }

    if ($pid === 0) {
        try {
            DB::purge();
            config(['database.connections.pgsql.application_name' => $applicationName]);
            DB::reconnect();
            DB::select('SELECT set_config(?, ?, false)', ['application_name', $applicationName]);
            DB::select('SELECT set_config(?, ?, false)', ['statement_timeout', '15s']);
            $result = $action();
            file_put_contents($resultFile, json_encode(['result' => $result], JSON_THROW_ON_ERROR));
            exit(0);
        } catch (Throwable $exception) {
            file_put_contents($resultFile, json_encode([
                'error' => $exception::class,
                'message' => $exception->getMessage(),
            ], JSON_THROW_ON_ERROR));
            exit(1);
        }
    }

    return [
        'pid' => $pid,
        'application_name' => $applicationName,
        'result_file' => $resultFile,
    ];
}

/**
 * @param  list<array{pid:int, application_name:string, result_file:string}>  $workers
 */
function staffAuthPostgresWorkersAreBlocked(array $workers): bool
{
    $applicationNames = array_column($workers, 'application_name');
    $placeholders = implode(', ', array_fill(0, count($applicationNames), '?'));
    $deadline = microtime(true) + 10;

    do {
        DB::statement('SELECT pg_stat_clear_snapshot()');
        $waiting = (int) DB::scalar(
            "SELECT count(*) FROM pg_stat_activity WHERE datname = current_database() AND application_name IN ({$placeholders}) AND wait_event_type = ?",
            [...$applicationNames, 'Lock'],
        );
        if ($waiting === count($workers)) {
            return true;
        }

        foreach ($workers as $worker) {
            clearstatcache(true, $worker['result_file']);
            if (is_file($worker['result_file']) && filesize($worker['result_file']) > 0) {
                return false;
            }
        }

        usleep(10_000);
    } while (microtime(true) < $deadline);

    return false;
}

/**
 * @param  list<array{pid:int, application_name:string, result_file:string}>  $workers
 * @return list<array<string, mixed>>
 */
function staffAuthPostgresCollectWorkers(TestCase $test, array $workers): array
{
    $pending = array_fill_keys(array_column($workers, 'pid'), true);
    $statuses = [];
    $deadline = microtime(true) + 20;

    do {
        foreach (array_keys($pending) as $pid) {
            $waited = pcntl_waitpid($pid, $status, WNOHANG);
            if ($waited > 0) {
                $statuses[$pid] = $status;
                unset($pending[$pid]);
            } elseif ($waited === -1) {
                $statuses[$pid] = null;
                unset($pending[$pid]);
            }
        }

        if ($pending !== []) {
            usleep(10_000);
        }
    } while ($pending !== [] && microtime(true) < $deadline);

    $timedOut = $pending !== [];
    foreach (array_keys($pending) as $pid) {
        pcntl_kill($pid, SIGTERM);
        pcntl_waitpid($pid, $status);
        $statuses[$pid] = $status;
    }

    $results = [];

    try {
        if ($timedOut) {
            $test->fail('A PostgreSQL staff authentication worker exceeded the bounded wait.');
        }

        foreach ($workers as $worker) {
            $status = $statuses[$worker['pid']] ?? null;
            if (! is_int($status) || ! pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
                $test->fail('PostgreSQL staff authentication worker failed: '.(string) @file_get_contents($worker['result_file']));
            }

            $payload = json_decode((string) @file_get_contents($worker['result_file']), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload) || ! isset($payload['result']) || ! is_array($payload['result'])) {
                $test->fail('PostgreSQL staff authentication worker returned no action result.');
            }

            $results[] = $payload['result'];
        }

        return $results;
    } finally {
        foreach ($workers as $worker) {
            @unlink($worker['result_file']);
        }
    }
}

/**
 * @param  list<mixed>  $lockBindings
 * @param  list<Closure(): array<string, mixed>>  $actions
 * @return list<array<string, mixed>>
 */
function staffAuthPostgresRunLockedWorkers(
    TestCase $test,
    string $lockSql,
    array $lockBindings,
    array $actions,
    string $waitFailure,
): array {
    $connection = staffAuthPostgresConnectionConfig();
    $parent = new PDO($connection['dsn'], $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $parent->beginTransaction();
    $workers = [];
    $workersCollected = false;

    try {
        $parent->prepare($lockSql)->execute($lockBindings);

        foreach ($actions as $index => $action) {
            $workers[] = staffAuthPostgresStartWorker('staff-auth-race-'.Str::uuid().'-'.$index, $action);
        }

        if (! staffAuthPostgresWorkersAreBlocked($workers)) {
            $parent->rollBack();
            $workersCollected = true;
            staffAuthPostgresCollectWorkers($test, $workers);
            $test->fail($waitFailure);
        }

        $parent->commit();
    } catch (Throwable $exception) {
        if ($parent->inTransaction()) {
            $parent->rollBack();
        }

        if (! $workersCollected && $workers !== []) {
            $workersCollected = true;
            staffAuthPostgresCollectWorkers($test, $workers);
        }

        throw $exception;
    }

    $workersCollected = true;

    return staffAuthPostgresCollectWorkers($test, $workers);
}

test('concurrent PostgreSQL redemption of one staff voucher succeeds exactly once', function () {
    staffAuthPostgresAssertConcurrencySupport($this);
    seedE2Foundation();

    $actor = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $target = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    staffAuthPostgresPrepareState($target);
    $voucher = app(IssueStaffActivationVoucher::class)->handle($actor, $target);
    $connection = staffAuthPostgresConnectionConfig();
    $parent = new PDO($connection['dsn'], $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $parent->beginTransaction();
    $parent->prepare('SELECT id FROM staff_activation_vouchers WHERE public_reference = ? FOR UPDATE')
        ->execute([$voucher['public_reference']]);

    $resultFile = tempnam(sys_get_temp_dir(), 'staff-voucher-race-');
    if ($resultFile === false) {
        $parent->rollBack();
        $this->fail('Could not create the voucher concurrency result file.');
    }

    $pid = pcntl_fork();
    if ($pid === -1) {
        @unlink($resultFile);
        $parent->rollBack();
        $this->fail('Could not fork the voucher concurrency worker.');
    }

    if ($pid === 0) {
        $outcome = 'rejected';
        try {
            $worker = new PDO($connection['dsn'], $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $worker->beginTransaction();
            $statement = $worker->prepare('SELECT redeemed_at FROM staff_activation_vouchers WHERE public_reference = ? FOR UPDATE');
            $statement->execute([$voucher['public_reference']]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if ($row !== false && $row['redeemed_at'] === null) {
                $update = $worker->prepare('UPDATE staff_activation_vouchers SET redeemed_at = CURRENT_TIMESTAMP WHERE public_reference = ? AND redeemed_at IS NULL');
                $update->execute([$voucher['public_reference']]);
                $outcome = $update->rowCount() === 1 ? 'succeeded' : 'rejected';
            }
            $worker->commit();
        } catch (Throwable $exception) {
            $outcome = 'error:'.$exception::class;
            if (isset($worker) && $worker->inTransaction()) {
                $worker->rollBack();
            }
        }
        file_put_contents($resultFile, $outcome);
        exit(0);
    }

    usleep(100000);
    $parent->prepare('UPDATE staff_activation_vouchers SET redeemed_at = CURRENT_TIMESTAMP WHERE public_reference = ? AND redeemed_at IS NULL')
        ->execute([$voucher['public_reference']]);
    $parent->commit();
    pcntl_waitpid($pid, $status);
    $workerOutcome = (string) file_get_contents($resultFile);
    @unlink($resultFile);

    expect($workerOutcome)->toBe('rejected')
        ->and(DB::table('staff_activation_vouchers')->where('public_reference', $voucher['public_reference'])->whereNotNull('redeemed_at')->count())->toBe(1);
});

test('concurrent PostgreSQL consumption of one recovery code succeeds exactly once', function () {
    staffAuthPostgresAssertConcurrencySupport($this);
    seedE2Foundation();

    $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $state = staffAuthPostgresPrepareState($user);
    $recoveryCode = bin2hex(random_bytes(16));
    $record = StaffTwoFactorRecoveryCode::query()->create([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'generation' => $state->recovery_generation,
        'code_digest' => hash('sha256', $recoveryCode),
    ]);
    $connection = staffAuthPostgresConnectionConfig();
    $parent = new PDO($connection['dsn'], $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $parent->beginTransaction();
    $parent->prepare('SELECT id FROM staff_two_factor_recovery_codes WHERE id = ? AND consumed_at IS NULL FOR UPDATE')
        ->execute([$record->id]);

    $resultFile = tempnam(sys_get_temp_dir(), 'staff-recovery-race-');
    if ($resultFile === false) {
        $parent->rollBack();
        $this->fail('Could not create the recovery-code concurrency result file.');
    }

    $pid = pcntl_fork();
    if ($pid === -1) {
        @unlink($resultFile);
        $parent->rollBack();
        $this->fail('Could not fork the recovery-code concurrency worker.');
    }

    if ($pid === 0) {
        $outcome = 'rejected';
        try {
            $worker = new PDO($connection['dsn'], $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $worker->beginTransaction();
            $statement = $worker->prepare('SELECT id FROM staff_two_factor_recovery_codes WHERE id = ? AND consumed_at IS NULL FOR UPDATE');
            $statement->execute([$record->id]);
            if ($statement->fetchColumn() !== false) {
                $update = $worker->prepare('UPDATE staff_two_factor_recovery_codes SET consumed_at = CURRENT_TIMESTAMP WHERE id = ? AND consumed_at IS NULL');
                $update->execute([$record->id]);
                $outcome = $update->rowCount() === 1 ? 'succeeded' : 'rejected';
            }
            $worker->commit();
        } catch (Throwable $exception) {
            $outcome = 'error:'.$exception::class;
            if (isset($worker) && $worker->inTransaction()) {
                $worker->rollBack();
            }
        }
        file_put_contents($resultFile, $outcome);
        exit(0);
    }

    usleep(100000);
    $parent->prepare('UPDATE staff_two_factor_recovery_codes SET consumed_at = CURRENT_TIMESTAMP WHERE id = ? AND consumed_at IS NULL')
        ->execute([$record->id]);
    $parent->commit();
    pcntl_waitpid($pid, $status);
    $workerOutcome = (string) file_get_contents($resultFile);
    @unlink($resultFile);

    expect($workerOutcome)->toBe('rejected')
        ->and($record->fresh()->consumed_at)->not->toBeNull();
});

test('concurrent PostgreSQL staff lease renewals serialize and stay at the absolute expiry', function (): void {
    staffAuthPostgresAssertConcurrencySupport($this);
    seedE2Foundation();

    $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $now = CarbonImmutable::parse('2026-10-15 12:00:00 UTC');
    $sessionId = 'staff-lease-race-'.Str::uuid();
    $lease = app(EstablishStaffSessionLease::class)->handle(
        $user,
        $sessionId,
        $now,
        15,
        $now->subHours(7)->subMinutes(30),
    );
    $userId = (int) $user->id;
    $renewLease = static function () use ($userId, $sessionId, $now): array {
        $result = app(RenewStaffSessionLease::class)->handle(
            User::query()->findOrFail($userId),
            $sessionId,
            $now,
            60,
        );

        return [
            'idle_expires_at' => $result['idle_expires_at']->toIso8601String(),
            'absolute_expires_at' => $result['absolute_expires_at']->toIso8601String(),
        ];
    };

    $renewals = staffAuthPostgresRunLockedWorkers(
        $this,
        'SELECT id FROM staff_session_leases WHERE session_id_hash = ? FOR UPDATE',
        [hash_hmac('sha256', $sessionId, (string) config('app.key'))],
        [$renewLease, $renewLease],
        'Both independent lease renewals did not block on the PostgreSQL lease row lock.',
    );
    $updatedLease = $lease->fresh();

    expect($renewals)->toHaveCount(2)
        ->and($updatedLease->idle_expires_at->equalTo($updatedLease->absolute_expires_at))->toBeTrue()
        ->and($updatedLease->absolute_expires_at->equalTo($now->addMinutes(30)))->toBeTrue();

    foreach ($renewals as $renewal) {
        expect(CarbonImmutable::parse($renewal['idle_expires_at'])->equalTo($updatedLease->absolute_expires_at))->toBeTrue()
            ->and(CarbonImmutable::parse($renewal['absolute_expires_at'])->equalTo($updatedLease->absolute_expires_at))->toBeTrue();
    }
});

test('concurrent PostgreSQL Require MFA calls serialize and invalidate authentication once', function (): void {
    staffAuthPostgresAssertConcurrencySupport($this);
    seedE2Foundation();

    $actor = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $target = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $state = StaffAuthenticationState::query()->where('user_id', $target->id)->firstOrFail();
    $state->forceFill([
        'mfa_required' => false,
        'updated_at' => CarbonImmutable::parse('2020-01-01 00:00:00 UTC'),
    ])->save();
    $sessionVersion = (int) $state->session_version;
    $factorGeneration = $state->two_factor_generation;
    $rememberToken = $target->fresh()->remember_token;
    $auditIdsBefore = AuditEvent::query()
        ->where('event_type', 'security.staff_mfa_enforced')
        ->where('actor', (string) $actor->id)
        ->get()
        ->filter(fn (AuditEvent $event): bool => (int) ($event->payload['user_id'] ?? 0) === (int) $target->id)
        ->pluck('id')
        ->all();
    $actorId = (int) $actor->id;
    $targetId = (int) $target->id;
    $requireMfa = static function () use ($actorId, $targetId): array {
        $updated = app(RequireStaffMfa::class)->handle(
            User::query()->findOrFail($actorId),
            User::query()->findOrFail($targetId),
            'Concurrent PostgreSQL acceptance',
        );

        return [
            'mfa_required' => (bool) $updated->mfa_required,
            'session_version' => (int) $updated->session_version,
            'two_factor_generation' => $updated->two_factor_generation,
            'updated_at' => $updated->updated_at?->toISOString(),
        ];
    };

    $results = staffAuthPostgresRunLockedWorkers(
        $this,
        'SELECT id FROM users WHERE id = ? FOR UPDATE',
        [$targetId],
        [$requireMfa, $requireMfa],
        'Both independent Require MFA calls did not block on the PostgreSQL staff row lock.',
    );
    $updatedState = StaffAuthenticationState::query()->where('user_id', $targetId)->firstOrFail();
    $updatedTarget = User::query()->findOrFail($targetId);
    $newAuditEvents = AuditEvent::query()
        ->where('event_type', 'security.staff_mfa_enforced')
        ->where('actor', (string) $actorId)
        ->get()
        ->filter(fn (AuditEvent $event): bool => (int) ($event->payload['user_id'] ?? 0) === $targetId
            && ! in_array($event->id, $auditIdsBefore, true))
        ->values();
    $alreadyRequiredValues = $newAuditEvents
        ->map(fn (AuditEvent $event): bool => (bool) $event->payload['already_required'])
        ->sort()
        ->values()
        ->all();

    expect($results)->toHaveCount(2)
        ->and($updatedState->mfa_required)->toBeTrue()
        ->and($updatedState->session_version)->toBe($sessionVersion + 1)
        ->and($updatedState->two_factor_generation)->toBe($factorGeneration)
        ->and($updatedState->updated_at->greaterThan(CarbonImmutable::parse('2020-01-01 00:00:00 UTC')))->toBeTrue()
        ->and($updatedTarget->remember_token)->not->toBe($rememberToken)
        ->and($newAuditEvents)->toHaveCount(2)
        ->and($alreadyRequiredValues)->toBe([false, true]);

    foreach ($results as $result) {
        expect($result['mfa_required'])->toBeTrue()
            ->and($result['session_version'])->toBe($sessionVersion + 1)
            ->and($result['two_factor_generation'])->toBe($factorGeneration)
            ->and(CarbonImmutable::parse($result['updated_at'])->equalTo($updatedState->updated_at))->toBeTrue();
    }
});

test('expired PostgreSQL staff lease renewal fails inside an existing transaction', function (): void {
    staffAuthPostgresAssertConcurrencySupport($this);
    seedE2Foundation();

    $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $now = CarbonImmutable::parse('2026-10-15 12:00:00 UTC');
    $sessionId = 'staff-lease-expired-'.Str::uuid();
    $lease = app(EstablishStaffSessionLease::class)->handle($user, $sessionId, $now);
    $lease->forceFill(['idle_expires_at' => $now])->save();

    DB::beginTransaction();
    try {
        expect(fn () => app(RenewStaffSessionLease::class)->handle($user, $sessionId, $now))
            ->toThrow(RuntimeException::class, 'The staff session lease is expired, revoked, or unavailable.');

        $persistedLease = StaffSessionLease::query()->findOrFail($lease->id);
        expect(DB::transactionLevel())->toBe(1)
            ->and($persistedLease->idle_expires_at->equalTo($now))->toBeTrue()
            ->and($persistedLease->effective_idle_minutes)->toBe(15);
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});
