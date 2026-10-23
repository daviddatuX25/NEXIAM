<?php

declare(strict_types=1);

use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\BindAssessmentAdministration;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Process\Process;
use Tests\TestCase;

require_once __DIR__.'/../../Support/NormConversionFixtures.php';

const ASSESSMENT_BINDING_CONTENTION_PG_DATABASE = 'nexiam_test_kit_acceptance_composition_20261015';

/** Refuse every non-target environment before this test creates any fixture rows. */
function assertAssessmentBindingContentionPostgresTarget(): void
{
    $expectedEnvironment = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_DATABASE' => ASSESSMENT_BINDING_CONTENTION_PG_DATABASE,
        'DB_HOST' => 'postgres',
        'DB_PORT' => '5432',
        'DB_USERNAME' => 'nexiam',
        'DB_PASSWORD' => 'nexiam-local-only',
        'DB_URL' => '',
        'NEXIAM_TEST_PIN_SQLITE' => '0',
    ];
    foreach ($expectedEnvironment as $key => $expected) {
        if (getenv($key) !== $expected) {
            test()->fail('Refusing assessment-binding contention writes because '.$key.' is not exactly '.var_export($expected, true).'.');
        }
    }

    $connection = DB::connection();
    if (app()->environment() !== 'testing'
        || $connection->getDriverName() !== 'pgsql'
        || (string) $connection->scalar('SELECT current_database()') !== ASSESSMENT_BINDING_CONTENTION_PG_DATABASE
        || config('database.default') !== 'pgsql'
        || config('database.connections.pgsql.database') !== ASSESSMENT_BINDING_CONTENTION_PG_DATABASE
        || (string) config('database.connections.pgsql.url') !== '') {
        test()->fail('Refusing assessment-binding contention writes outside the exact isolated PostgreSQL target.');
    }
}

/** @return array{application_name:string,config_cache:string,database:string} */
function assessmentBindingContentionWorkerIdentity(string $label): array
{
    $token = substr(str_replace('-', '', (string) Str::uuid()), 0, 12);
    $applicationName = 'assessbind-'.$token.'-'.$label;

    return [
        'application_name' => $applicationName,
        'config_cache' => sys_get_temp_dir().'/'.ASSESSMENT_BINDING_CONTENTION_PG_DATABASE.'-'.$applicationName.'-config.php',
        'database' => ASSESSMENT_BINDING_CONTENTION_PG_DATABASE,
    ];
}

/** @param array<string,mixed> $input */
function startAssessmentBindingContentionWorker(array $input): Process
{
    $encodedInput = base64_encode(json_encode($input, JSON_THROW_ON_ERROR));
    $worker = <<<'PHP'
try {
    $raw = base64_decode((string) ($argv[1] ?? ''), true);
    if (! is_string($raw)) {
        throw new RuntimeException('Invalid worker input.');
    }
    $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($input)) {
        throw new RuntimeException('Invalid worker configuration.');
    }
    $environment = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_DATABASE' => $input['database'],
        'DB_HOST' => 'postgres',
        'DB_PORT' => '5432',
        'DB_USERNAME' => 'nexiam',
        'DB_PASSWORD' => 'nexiam-local-only',
        'DB_URL' => '',
        'NEXIAM_TEST_PIN_SQLITE' => '0',
        'APP_CONFIG_CACHE' => $input['config_cache'],
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
    ];
    foreach ($environment as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    require getcwd().'/vendor/autoload.php';
    $app = require getcwd().'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $connection = Illuminate\Support\Facades\DB::connection();
    if (app()->environment() !== 'testing'
        || getenv('APP_ENV') !== 'testing'
        || getenv('DB_URL') !== ''
        || $connection->getDriverName() !== 'pgsql'
        || (string) $connection->scalar('SELECT current_database()') !== 'nexiam_test_kit_acceptance_composition_20261015'
        || (string) config('database.connections.pgsql.database') !== $input['database']
        || (string) config('database.connections.pgsql.url') !== '') {
        throw new RuntimeException('Refusing assessment-binding worker outside its exact isolated PostgreSQL target.');
    }
    Illuminate\Support\Facades\DB::select('SELECT set_config(?, ?, false)', ['application_name', $input['application_name']]);
    Illuminate\Support\Facades\DB::select('SELECT pg_advisory_lock_shared(?)', [(int) $input['barrier']]);
    fwrite(STDOUT, "READY\n");
    fflush(STDOUT);

    try {
        $actor = App\Models\User::query()->findOrFail((int) $input['actor_id']);
        if ($input['action'] === 'bind') {
            $administration = app(App\Examination\Actions\BindAssessmentAdministration::class)->handle(
                App\Models\Examination\ExamAssignment::query()->findOrFail((int) $input['assignment_id']),
                App\Models\Assessment\TestKitVersion::query()->findOrFail((int) $input['kit_id']),
                $actor,
                'manual',
                $input['replacement_reason'] ?? null,
            );
            fwrite(STDOUT, json_encode(['ok' => true, 'administration_id' => (int) $administration->id], JSON_THROW_ON_ERROR)."\n");
        } else {
            $result = app(App\Assessment\Actions\AcceptAssessmentRawResults::class)->handle(
                App\Models\Examination\AssessmentAdministration::query()->findOrFail((int) $input['administration_id']),
                ['RAW_A' => 1],
                App\Models\Assessment\AssessmentScoringRun::SOURCE_MANUAL,
                $actor,
                (string) $input['idempotency_key'],
            );
            fwrite(STDOUT, json_encode(['ok' => true, 'scoring_run_id' => (int) $result['scoring_run_id']], JSON_THROW_ON_ERROR)."\n");
        }
    } catch (Illuminate\Validation\ValidationException $exception) {
        fwrite(STDOUT, json_encode([
            'ok' => false,
            'rejection' => $exception::class,
            'errors' => $exception->errors(),
        ], JSON_THROW_ON_ERROR)."\n");
    } catch (Throwable $exception) {
        fwrite(STDOUT, json_encode([
            'ok' => false,
            'unexpected' => $exception::class,
            'message' => substr(preg_replace('/\\s+/', ' ', $exception->getMessage()) ?? '', 0, 240),
        ], JSON_THROW_ON_ERROR)."\n");
    }
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode(['worker_error' => $exception::class], JSON_THROW_ON_ERROR)."\n");
    exit(1);
}
PHP;

    $process = new Process([PHP_BINARY, '-r', $worker, $encodedInput], base_path(), [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_DATABASE' => $input['database'],
        'DB_HOST' => 'postgres',
        'DB_PORT' => '5432',
        'DB_USERNAME' => 'nexiam',
        'DB_PASSWORD' => 'nexiam-local-only',
        'DB_URL' => '',
        'NEXIAM_TEST_PIN_SQLITE' => '0',
        'APP_CONFIG_CACHE' => $input['config_cache'],
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
    ]);
    $process->setTimeout(30);
    $process->start();

    return $process;
}

/** @param list<Process> $processes @return list<array<string,mixed>> */
function finishAssessmentBindingContentionWorkers(array $processes): array
{
    $results = [];
    foreach ($processes as $process) {
        $process->wait();
        if (! $process->isSuccessful()) {
            test()->fail('An assessment-binding worker exited unexpectedly: '.$process->getErrorOutput());
        }
        $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));
        $result = json_decode((string) end($lines), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($result)) {
            test()->fail('An assessment-binding worker returned no structured result.');
        }
        $results[] = $result;
    }

    return $results;
}

/** Wait until every independent worker is blocked on the same PostgreSQL advisory barrier. */
function awaitAssessmentBindingContentionWorkers(array $processes, array $applicationNames): void
{
    $deadline = microtime(true) + 10;
    do {
        DB::select('SELECT pg_stat_clear_snapshot()');
        $placeholders = implode(', ', array_fill(0, count($applicationNames), '?'));
        $waiting = (int) DB::scalar(
            'SELECT count(*) FROM pg_stat_activity WHERE datname = current_database() AND application_name IN ('.$placeholders.') AND wait_event_type = ? AND wait_event = ? AND cardinality(pg_blocking_pids(pid)) > 0',
            [...$applicationNames, 'Lock', 'advisory'],
        );
        if ($waiting === count($processes)) {
            return;
        }
        usleep(10_000);
    } while (microtime(true) < $deadline);

    test()->fail('Not all assessment-binding workers reached the PostgreSQL advisory barrier.');
}

/** @return array{actor:User,assignment:ExamAssignment,attempt:AdmissionAttempt,institution:Institution,kits:list<TestKitVersion>,session:ExamSession} */
function assessmentBindingContentionFixture(TestCase $test, int $kitCount): array
{
    $suffix = strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 12));
    $institution = Institution::query()->create(['code' => 'BIND-RACE-'.$suffix, 'name' => 'Synthetic binding contention institution']);
    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'BIND-RACE-CYCLE-'.$suffix,
        'name' => 'Synthetic binding contention cycle',
        'opens_on' => now()->subYear()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);
    $room = Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'BIND-RACE-ROOM-'.$suffix,
        'name' => 'Synthetic binding contention room',
        'capacity' => 10,
        'is_active' => true,
    ]);
    $actor = User::factory()->create(['name' => 'Synthetic assessment binding actor '.$suffix]);
    StaffProfile::query()->create([
        'user_id' => $actor->id,
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'active_cycle_code' => $cycle->code,
        'status' => StaffProfile::STATUS_ACTIVE,
        'deactivated_at' => null,
    ]);
    $capabilities = [
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
    ];
    foreach ($capabilities as $capability) {
        Permission::findOrCreate($capability, 'web');
    }
    $actor->givePermissionTo($capabilities);

    $profile = createTestApprovedProfileVersion($test, $actor, 'BIND-RACE-SETUP-'.$suffix);
    $kits = [];
    for ($index = 0; $index < $kitCount; $index++) {
        $kitFixture = testKitSetupOnlyFixture($actor, $profile);
        $kit = $kitFixture['version'];
        // Active is only the retained-use Given; Setup-only is not an acceptance or qualification claim.
        $kit->forceFill([
            'status' => TestKitVersion::STATUS_ACTIVE,
            'activated_at' => now(),
            'activated_by_user_id' => $actor->id,
            'revision' => (int) $kit->revision + 1,
        ])->save();
        $kits[] = $kit->fresh();
    }

    $identityId = DB::table('applicant_identities')->insertGetId([
        'institution_id' => $institution->id,
        'normalized_key' => hash('sha256', 'binding-race-'.$suffix),
        'first_name' => 'Synthetic',
        'last_name' => 'BindingRace'.$suffix,
        'birthdate' => '2001-01-01',
        'sex' => 'unspecified',
        'email' => null,
        'phone' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $attemptId = DB::table('admission_attempts')->insertGetId([
        'applicant_identity_id' => $identityId,
        'institution_id' => $institution->id,
        'academic_cycle_id' => $cycle->id,
        'reference_code' => 'BIND-RACE-ATTEMPT-'.$suffix,
        'status' => AdmissionAttempt::STATUS_ACCEPTED,
        'preferred_program_code' => 'SYNTHETIC',
        'accepted_at' => now(),
        'payload' => json_encode(['first_name' => 'Synthetic', 'last_name' => 'BindingRace'.$suffix], JSON_THROW_ON_ERROR),
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $startsAt = now()->subHour();
    $sessionId = DB::table('exam_sessions')->insertGetId([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'BIND-RACE-SESSION-'.$suffix,
        'label' => 'Synthetic assessment binding contention',
        'status' => 'in_progress',
        'booking_mode' => 'staff_only',
        'starts_at' => $startsAt,
        'effective_end_at' => $startsAt->copy()->addHour(),
        'published_at' => now(),
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $session = ExamSession::query()->findOrFail($sessionId);
    $assignment = ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $attemptId,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'version' => 1,
    ]);

    return [
        'actor' => $actor,
        'assignment' => $assignment,
        'attempt' => AdmissionAttempt::query()->findOrFail($attemptId),
        'institution' => $institution,
        'kits' => $kits,
        'session' => $session,
    ];
}

/** @param list<array<string,mixed>> $workerInputs @return list<array<string,mixed>> */
function raceAssessmentBindingWorkers(array $workerInputs): array
{
    $barrier = random_int(1, 2_000_000_000);
    $processes = [];
    $applicationNames = [];
    $configCaches = [];
    $barrierHeld = false;
    try {
        DB::select('SELECT pg_advisory_lock(?)', [$barrier]);
        $barrierHeld = true;
        foreach ($workerInputs as $input) {
            $identity = assessmentBindingContentionWorkerIdentity((string) $input['label']);
            $input = [...$input, ...$identity, 'barrier' => $barrier];
            $processes[] = startAssessmentBindingContentionWorker($input);
            $applicationNames[] = $identity['application_name'];
            $configCaches[] = $identity['config_cache'];
        }
        awaitAssessmentBindingContentionWorkers($processes, $applicationNames);
        DB::select('SELECT pg_advisory_unlock(?)', [$barrier]);
        $barrierHeld = false;

        return finishAssessmentBindingContentionWorkers($processes);
    } finally {
        if ($barrierHeld) {
            DB::select('SELECT pg_advisory_unlock(?)', [$barrier]);
        }
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
        foreach ($configCaches as $configCache) {
            if (is_file($configCache)) {
                unlink($configCache);
            }
        }
    }
}

test('PostgreSQL serializes competing exact Kit bindings from independent workers', function (): void {
    assertAssessmentBindingContentionPostgresTarget();
    $fixture = assessmentBindingContentionFixture($this, 2);
    $reason = 'Replace the unused exact Kit binding in the isolated contention proof.';

    $outcomes = raceAssessmentBindingWorkers([
        [
            'action' => 'bind', 'actor_id' => (int) $fixture['actor']->id,
            'assignment_id' => (int) $fixture['assignment']->id, 'kit_id' => (int) $fixture['kits'][0]->id,
            'replacement_reason' => $reason, 'label' => 'kit-a',
        ],
        [
            'action' => 'bind', 'actor_id' => (int) $fixture['actor']->id,
            'assignment_id' => (int) $fixture['assignment']->id, 'kit_id' => (int) $fixture['kits'][1]->id,
            'replacement_reason' => $reason, 'label' => 'kit-b',
        ],
    ]);
    expect($outcomes)->toHaveCount(2)
        ->and(array_filter($outcomes, static fn (array $outcome): bool => ($outcome['ok'] ?? false) === true))->toHaveCount(2)
        ->and(array_filter($outcomes, static fn (array $outcome): bool => isset($outcome['unexpected']) || isset($outcome['worker_error'])))->toBe([]);

    $current = AssessmentAdministration::query()->where('exam_assignment_id', $fixture['assignment']->id)->whereNull('superseded_at')->get();
    $all = AssessmentAdministration::query()->where('exam_assignment_id', $fixture['assignment']->id)->orderBy('binding_revision')->get();
    expect($current)->toHaveCount(1)
        ->and($all)->toHaveCount(2)
        ->and($all->pluck('test_kit_version_id')->unique()->sort()->values()->all())
        ->toBe(collect($fixture['kits'])->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all())
        ->and($current->sole()->binding_revision)->toBe(2)
        ->and(AssessmentRawResultRevision::query()->whereIn('administration_id', $all->pluck('id'))->count())->toBe(0);
})->group('postgres-assessment-binding-contention');

test('PostgreSQL serializes accepted raw input against reasoned unused-binding replacement', function (): void {
    assertAssessmentBindingContentionPostgresTarget();
    $fixture = assessmentBindingContentionFixture($this, 2);
    $original = app(BindAssessmentAdministration::class)->handle(
        $fixture['assignment'], $fixture['kits'][0], $fixture['actor'], 'manual',
    );

    $outcomes = raceAssessmentBindingWorkers([
        [
            'action' => 'accept_raw', 'actor_id' => (int) $fixture['actor']->id,
            'administration_id' => (int) $original->id, 'idempotency_key' => (string) Str::uuid(),
            'label' => 'raw-input',
        ],
        [
            'action' => 'bind', 'actor_id' => (int) $fixture['actor']->id,
            'assignment_id' => (int) $fixture['assignment']->id, 'kit_id' => (int) $fixture['kits'][1]->id,
            'replacement_reason' => 'Replace the unused exact Kit binding in the isolated raw-input race.',
            'label' => 'replacement',
        ],
    ]);

    $successful = array_values(array_filter($outcomes, static fn (array $outcome): bool => ($outcome['ok'] ?? false) === true));
    $rejected = array_values(array_filter($outcomes, static fn (array $outcome): bool => ($outcome['ok'] ?? null) === false
        && ($outcome['rejection'] ?? null) === ValidationException::class
        && is_array($outcome['errors'] ?? null)));
    expect($outcomes)->toHaveCount(2)
        ->and($successful)->toHaveCount(1)
        ->and($rejected)->toHaveCount(1)
        ->and(array_filter($outcomes, static fn (array $outcome): bool => isset($outcome['unexpected']) || isset($outcome['worker_error'])))->toBe([]);

    $current = AssessmentAdministration::query()->where('exam_assignment_id', $fixture['assignment']->id)->whereNull('superseded_at')->sole();
    $rawCount = AssessmentRawResultRevision::query()->where('administration_id', $original->id)->where('is_current', true)->count();
    if ((int) $current->id === (int) $original->id) {
        expect($successful[0])->toHaveKey('scoring_run_id')
            ->and($rejected[0]['errors'])->toHaveKey('test_kit_version_id')
            ->and($rawCount)->toBe(1);
    } else {
        expect($current->test_kit_version_id)->toBe($fixture['kits'][1]->id)
            ->and($rejected[0]['errors'])->toHaveKey('administration')
            ->and($rawCount)->toBe(0);
    }
    expect(AssessmentRawResultRevision::query()
        ->where('administration_id', $original->id)
        ->where('is_current', true)
        ->whereHas('administration', fn ($query) => $query->whereNotNull('superseded_at'))
        ->count())->toBe(0);
})->group('postgres-assessment-binding-contention');
