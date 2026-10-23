<?php

use App\Admission\Support\QuotaReservation;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramSelection;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\User;
use App\Setup\Actions\UpsertProgramQuota;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

function assertPostgresQuotaTestDatabase(): void
{
    $connection = DB::connection();
    if ($connection->getDriverName() !== 'pgsql') {
        test()->markTestSkipped('PostgreSQL concurrency test requires pgsql driver.');
    }
}

/**
 * Multi-process PostgreSQL worker running an authentic quota Action under an advisory lock barrier.
 *
 * @param  array{action:string,actor_id:int,program_id:int,cycle_id:int,slots:int,expected_version:int,key:string,barrier:int|null,application_name:string}  $input
 */
function startQuotaWorkerProcess(array $input): Process
{
    $worker = <<<'PHP'
try {
    require getcwd().'/vendor/autoload.php';
    $app = require getcwd().'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    config()->set('admission.quota_writes_enabled', true);

    $raw = getenv('NEXIAM_QUOTA_RACE_INPUT') ?: ($argv[1] ?? '');
    $input = json_decode(base64_decode((string) $raw, true), true, 512, JSON_THROW_ON_ERROR);

    Illuminate\Support\Facades\DB::select('select set_config(?, ?, false)', ['application_name', $input['application_name']]);

    if ($input['barrier'] !== null) {
        Illuminate\Support\Facades\DB::select('select pg_advisory_lock_shared(?)', [$input['barrier']]);
    }

    $actor = App\Models\User::query()->findOrFail($input['actor_id']);
    $program = App\Models\Foundation\Program::query()->findOrFail($input['program_id']);
    $cycle = App\Models\Foundation\AcademicCycle::query()->findOrFail($input['cycle_id']);

    $change = app(App\Setup\Actions\UpsertProgramQuota::class)->handle(
        $actor,
        $program,
        $cycle,
        $input['slots'],
        'Multiprocess concurrency test execution',
        $input['expected_version'],
        $input['key'],
    );

    fwrite(STDOUT, json_encode(['change_id' => $change->id, 'version' => $change->version, 'slots' => $change->to_slots], JSON_THROW_ON_ERROR)."\n");
} catch (\App\Setup\Actions\QuotaCommandConflict $conflict) {
    fwrite(STDOUT, json_encode(['conflict' => true, 'current' => $conflict->current], JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode(['class' => $exception::class, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR)."\n");
    exit(1);
}
PHP;

    $encoded = base64_encode(json_encode($input, JSON_THROW_ON_ERROR));
    $process = new Process([
        PHP_BINARY,
        '-r',
        $worker,
        $encoded,
    ], base_path(), array_merge($_SERVER, [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_HOST' => (string) config('database.connections.pgsql.host'),
        'DB_PORT' => (string) config('database.connections.pgsql.port'),
        'DB_DATABASE' => (string) config('database.connections.pgsql.database'),
        'DB_USERNAME' => (string) config('database.connections.pgsql.username'),
        'DB_PASSWORD' => (string) config('database.connections.pgsql.password'),
        'DB_URL' => '',
        'NEXIAM_TEST_PIN_SQLITE' => '0',
        'APP_CONFIG_CACHE' => '/tmp/demo-ready-worker-no-config.php',
        'NEXIAM_QUOTA_RACE_INPUT' => $encoded,
    ]));
    $process->start();

    return $process;
}

test('multiprocess PostgreSQL first-create race serializes duplicate roots and produces one winner and one stale conflict', function (): void {
    assertPostgresQuotaTestDatabase();
    seedE2Foundation();
    config()->set('admission.quota_writes_enabled', true);

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    // Ensure no existing quota root
    ProgramQuota::query()->where('program_id', $program->id)->where('academic_cycle_id', $cycle->id)->delete();
    DB::commit();

    $barrier = random_int(1_000_000, 2_000_000_000);
    $lockConnection = DB::connection()->getPdo();
    $lockConnection->prepare('SELECT pg_advisory_lock(?)')->execute([$barrier]);

    $procA = startQuotaWorkerProcess([
        'action' => 'upsert', 'actor_id' => $guidance->id, 'program_id' => $program->id,
        'cycle_id' => $cycle->id, 'slots' => 50, 'expected_version' => 0,
        'key' => (string) Str::uuid(), 'barrier' => $barrier,
        'application_name' => 'quota-create-race-'.$barrier.'-a',
    ]);
    $procB = startQuotaWorkerProcess([
        'action' => 'upsert', 'actor_id' => $guidance->id, 'program_id' => $program->id,
        'cycle_id' => $cycle->id, 'slots' => 60, 'expected_version' => 0,
        'key' => (string) Str::uuid(), 'barrier' => $barrier,
        'application_name' => 'quota-create-race-'.$barrier.'-b',
    ]);

    $deadline = microtime(true) + 10;
    do {
        $waiting = (int) DB::scalar("select count(*) from pg_stat_activity where datname = current_database() and application_name like 'quota-create-race-{$barrier}-%' and wait_event = 'advisory'");
        if ($waiting >= 2) {
            break;
        }
        usleep(10_000);
    } while (microtime(true) < $deadline);

    $lockConnection->prepare('SELECT pg_advisory_unlock(?)')->execute([$barrier]);

    $procA->wait();
    $procB->wait();

    $outA = json_decode(trim($procA->getOutput()), true);
    $outB = json_decode(trim($procB->getOutput()), true);

    // Exactly one winner and one conflict
    $winners = array_filter([$outA, $outB], fn ($res) => isset($res['change_id']));
    $conflicts = array_filter([$outA, $outB], fn ($res) => isset($res['conflict']) && $res['conflict'] === true);

    expect($winners)->toHaveCount(1)
        ->and($conflicts)->toHaveCount(1);

    // Database has exactly one root at version 1
    $quota = ProgramQuota::query()->where('program_id', $program->id)->where('academic_cycle_id', $cycle->id)->firstOrFail();
    expect($quota->version)->toBe(1);
});

test('capacity reduction below existing commitments preserves reservations and reports over-capacity without mutating applicants', function (): void {
    seedE2Foundation();
    config()->set('admission.quota_writes_enabled', true);

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    // Set initial capacity to 10
    $initial = app(UpsertProgramQuota::class)->handle($guidance, $program, $cycle, 10, 'Initial capacity', 0, (string) Str::uuid());

    // Create 3 committed applicant selections
    $attempts = [];
    for ($i = 0; $i < 3; $i++) {
        $fixture = submitSyntheticApplicant("quota-reduction-{$i}@example.test", "QuotaReduction{$i}");
        $attempt = $fixture['attempt'];
        $attempt->update(['status' => AdmissionAttempt::STATUS_ACCEPTED]);
        $attempts[] = $attempt;

        $pool = ProgramPool::query()->create([
            'admission_attempt_id' => $attempt->id,
            'version' => 1,
            'source' => 'guidance_consultation',
            'idempotency_key' => (string) Str::uuid(),
            'created_at' => now(),
        ]);
        $option = $pool->options()->create([
            'program_id' => $program->id,
            'display_order' => 1,
            'basis' => 'first_choice',
        ]);
        ProgramSelection::query()->create([
            'admission_attempt_id' => $attempt->id,
            'program_pool_id' => $pool->id,
            'program_pool_option_id' => $option->id,
            'academic_cycle_id' => $cycle->id,
            'program_id' => $program->id,
            'status' => ProgramSelection::STATUS_ACTIVE,
            'source' => ProgramSelection::SOURCE_STUDENT_SELF,
            'acknowledged_at' => now(),
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    $committedBefore = QuotaReservation::batchCommittedForCycle((int) $cycle->id, [$program->id])[$program->id] ?? 0;
    expect($committedBefore)->toBe(3);

    // Reduce capacity to 2 (below 3 commitments)
    $reduction = app(UpsertProgramQuota::class)->handle($guidance, $program, $cycle, 2, 'Capacity cut to two', 1, (string) Str::uuid());
    expect($reduction->to_slots)->toBe(2);

    // Commitments are completely preserved
    $committedAfter = QuotaReservation::batchCommittedForCycle((int) $cycle->id, [$program->id])[$program->id] ?? 0;
    expect($committedAfter)->toBe(3);

    // Assert every applicant attempt status and selection remains untouched
    foreach ($attempts as $attempt) {
        $fresh = $attempt->fresh();
        expect($fresh->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED);
        $standing = ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->where('status', ProgramSelection::STATUS_ACTIVE)->first();
        expect($standing)->not->toBeNull()
            ->and($standing->program_id)->toBe($program->id);
    }

    // Now further reservation attempt into this over-capacity program is rejected by assertCapacityFor
    $extraAttempt = submitSyntheticApplicant('extra-overcapacity@example.test', 'Overcapacity')['attempt'];
    $extraAttempt->update(['status' => AdmissionAttempt::STATUS_ACCEPTED]);
    $capacity = QuotaReservation::assertCapacityFor($extraAttempt, $program->id);
    expect($capacity['is_overflow'])->toBeTrue()
        ->and($capacity['remaining'])->toBe(0)
        ->and($capacity['over_capacity'])->toBeGreaterThan(0);
});
