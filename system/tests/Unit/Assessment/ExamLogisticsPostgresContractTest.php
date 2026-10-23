<?php

declare(strict_types=1);

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\OpenExamSessionReservation;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Actions\ReserveExamSlot;
use App\Examination\Support\ExamSessionPublishPreflight;
use App\Foundation\Actions\SaveSeatingArrangement;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAssignmentLogisticsSeatAllocation;
use App\Models\Examination\ExamAssignmentSeatAllocation;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionLogisticsPlan;
use App\Models\Examination\ExamSessionLogisticsSeat;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Examination\ExamSessionSeat;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\Foundation\SpaceKind;
use App\Models\User;
use App\Scheduling\AssignmentInvariant;
use App\Setup\Actions\UpdateInstitutionExamFeaturePolicy;
use Database\Seeders\CapabilityCatalogSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\IntakeRequirementSeeder;
use Database\Seeders\ReferenceCatalogSeeder;
use Database\Seeders\StaffIdentitySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

const EXAM_LOGISTICS_PG_DATABASE = 'nexiam_test_kit_acceptance_composition_20261015';

/** Refuse every Postgres target except the one pre-provisioned disposable acceptance database. */
function assertExamLogisticsPostgresAcceptanceDatabase(): void
{
    $connection = DB::connection();
    if ($connection->getDriverName() !== 'pgsql') {
        test()->markTestSkipped('Exam logistics acceptance requires the isolated PostgreSQL test-kit configuration.');
    }

    $database = (string) $connection->getDatabaseName();
    if ($database !== EXAM_LOGISTICS_PG_DATABASE || $database === 'nexiam') {
        test()->fail('Refusing exam-logistics PostgreSQL acceptance on database "'.$database.'"; only '.EXAM_LOGISTICS_PG_DATABASE.' is permitted.');
    }

    $expectedEnvironment = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_DATABASE' => EXAM_LOGISTICS_PG_DATABASE,
        'DB_HOST' => 'postgres',
        'DB_PORT' => '5432',
        'DB_USERNAME' => 'nexiam',
        'DB_PASSWORD' => 'nexiam-local-only',
        'DB_URL' => '',
        'NEXIAM_TEST_PIN_SQLITE' => '0',
        'NEXIAM_TEST_KIT_SOURCE_DATABASE' => 'nexiam',
    ];
    foreach ($expectedEnvironment as $key => $expected) {
        if (getenv($key) !== $expected) {
            test()->fail('Refusing exam-logistics PostgreSQL acceptance because '.$key.' is not exactly '.var_export($expected, true).'.');
        }
    }

    if (app()->environment() !== 'testing'
        || config('database.default') !== 'pgsql'
        || config('database.connections.pgsql.database') !== EXAM_LOGISTICS_PG_DATABASE
        || config('database.connections.pgsql.host') !== 'postgres'
        || (string) config('database.connections.pgsql.port') !== '5432'
        || config('database.connections.pgsql.username') !== 'nexiam'
        || config('database.connections.pgsql.password') !== 'nexiam-local-only') {
        test()->fail('Refusing exam-logistics PostgreSQL acceptance because the active Laravel configuration differs from the guarded target.');
    }
}

/** @return array{attempt: AdmissionAttempt, submission: array{attempt: AdmissionAttempt, access_token: string, credential_handoff: mixed}, applicant: User|null} */
function examLogisticsPostgresAcceptedApplicant(User $registrar, bool $authenticateApplicant = false): array
{
    $suffix = str_replace('-', '', (string) Str::uuid());
    $submission = submitSyntheticApplicant('exam-logistics-'.$suffix.'@example.test', 'Pg'.$suffix);
    $attempt = app(AcceptAdmissionAttempt::class)->handle($submission['attempt'], $registrar, 1);
    $applicant = $authenticateApplicant
        ? app(AuthenticateApplicantWithAccessKey::class)->handle($submission['access_token'], '2001-01-01')
        : null;

    if (! $attempt->isExamEligible()) {
        throw new RuntimeException('The real admission acceptance action did not produce an exam-eligible attempt.');
    }

    return compact('attempt', 'submission', 'applicant');
}

function examLogisticsPostgresEnableAssignedSeating(User $institutionAdmin): void
{
    $institution = Institution::current();
    $decision = app(CapabilityAuthorizer::class)->decideFresh(
        $institutionAdmin,
        CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
        ['institution_id' => (int) $institution->id],
    );
    if (! $decision->allowed) {
        throw new RuntimeException('The seeded institutional administrator must hold the GF-21 institution-settings capability.');
    }

    app(UpdateInstitutionExamFeaturePolicy::class)->updateSeatingAvailability(
        $institutionAdmin,
        true,
        $institution->examFeaturePolicyVersion(),
    );
}

function examLogisticsPostgresRoom(Institution $institution, int $capacity): Room
{
    $suffix = strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 12));

    return Room::factory()->create([
        'institution_id' => (int) $institution->id,
        'code' => 'PG-'.$suffix,
        'name' => 'PostgreSQL logistics '.$suffix,
        'space_kind' => SpaceKind::Room->value,
        'capacity' => $capacity,
        'is_active' => true,
    ]);
}

function examLogisticsPostgresArrangement(User $guidance, int $rows, int $columns, ?SeatingArrangement $arrangement = null): SeatingArrangement
{
    $suffix = $arrangement?->name ?? 'pg-arrangement-'.str_replace('-', '', (string) Str::uuid());

    return app(SaveSeatingArrangement::class)->handle($arrangement, [
        'name' => $suffix,
        'rows' => $rows,
        'columns' => $columns,
        'booklet_pattern' => SeatingArrangement::PATTERN_UNIFORM,
        'availability_mode' => SeatingArrangement::AVAILABILITY_ALL_ROOMS,
        'room_ids' => [],
        'is_active' => true,
        'lock_version' => $arrangement?->lock_version,
    ], $guidance);
}

function examLogisticsPostgresCreateSession(
    AcademicCycle $cycle,
    Room $room,
    User $guidance,
    string $seatingMode = ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
    ?SeatingArrangement $arrangement = null,
): ExamSession {
    $suffix = strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 12));
    $startsAt = now()->addDays(20)->setTime(9, 0)->addMinutes(random_int(0, 720));

    return app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        'EX-PG-'.$suffix,
        $startsAt->toDateTimeString(),
        $startsAt->copy()->addHours(2)->toDateTimeString(),
        $guidance,
        $seatingMode,
        null,
        $arrangement,
    );
}

/** @return array{application_name: string, database: string, config_cache: string} */
function examLogisticsPostgresWorkerIdentity(string $label): array
{
    $database = (string) DB::connection()->getDatabaseName();
    $token = substr(str_replace('-', '', (string) Str::uuid()), 0, 10);
    $applicationName = 'exlog-'.$token.'-'.$label;

    return [
        'application_name' => $applicationName,
        'database' => $database,
        'config_cache' => sys_get_temp_dir().'/'.$database.'-'.$applicationName.'-config.php',
    ];
}

/**
 * Start an independently bootstrapped PHP process that invokes an ordinary examination Action.
 * The child process may only connect to this suite's exact isolated acceptance database.
 *
 * @param  array<string, mixed>  $input
 */
function startExamLogisticsPostgresWorker(array $input): Process
{
    if (! isset($input['application_name'], $input['database'], $input['config_cache'])) {
        $input = [...$input, ...examLogisticsPostgresWorkerIdentity((string) $input['label'])];
    }
    $input['advisory_barrier'] ??= null;
    $encodedInput = base64_encode(json_encode($input, JSON_THROW_ON_ERROR));

    $worker = <<<'PHP'
try {
    $encoded = getenv('NEXIAM_EXAM_LOGISTICS_WORKER_INPUT') ?: (string) ($argv[1] ?? $argv[0] ?? '');
    $rawInput = base64_decode($encoded, true);
    if (! is_string($rawInput)) {
        throw new RuntimeException('Invalid encoded exam-logistics worker input.');
    }
    $input = json_decode($rawInput, true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($input) || ! is_string($input['database'] ?? null) || ! is_string($input['config_cache'] ?? null)) {
        throw new RuntimeException('Invalid exam-logistics worker configuration.');
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
        'NEXIAM_TEST_KIT_SOURCE_DATABASE' => 'nexiam',
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
    $database = (string) $connection->getDatabaseName();
    if (app()->environment() !== 'testing'
        || getenv('APP_ENV') !== 'testing'
        || getenv('NEXIAM_TEST_PIN_SQLITE') !== '0'
        || $connection->getDriverName() !== 'pgsql'
        || $database !== 'nexiam_test_kit_acceptance_composition_20261015'
        || $database !== $input['database']
        || $database === 'nexiam') {
        throw new RuntimeException('Refusing exam-logistics worker outside its exact isolated PostgreSQL target.');
    }

    Illuminate\Support\Facades\DB::select('SELECT set_config(?, ?, false)', ['application_name', $input['application_name']]);
    if ($input['advisory_barrier'] !== null) {
        Illuminate\Support\Facades\DB::select('SELECT pg_advisory_lock_shared(?)', [$input['advisory_barrier']]);
        fwrite(STDOUT, "READY\n");
        fflush(STDOUT);
    }

    try {
        $actor = App\Models\User::query()->findOrFail((int) $input['actor_id']);
        $session = App\Models\Examination\ExamSession::query()->findOrFail((int) ($input['session_id'] ?? 0));
        $attempt = isset($input['attempt_id'])
            ? App\Models\Admission\AdmissionAttempt::query()->findOrFail((int) $input['attempt_id'])
            : null;

        $result = match ($input['operation']) {
            'assign' => (function () use ($session, $attempt, $actor): array {
                if (! $attempt instanceof App\Models\Admission\AdmissionAttempt) {
                    throw new RuntimeException('Assignment worker is missing its real admission attempt.');
                }
                $assignment = app(App\Examination\Actions\AssignApplicantToExamSession::class)->handle($session, $attempt, $actor);

                return ['assignment_id' => (int) $assignment->id, 'attempt_id' => (int) $attempt->id];
            })(),
            'accommodate' => (function () use ($session, $attempt, $actor, $input): array {
                if (! $attempt instanceof App\Models\Admission\AdmissionAttempt) {
                    throw new RuntimeException('Accommodation worker is missing its real admission attempt.');
                }
                $assignment = app(App\Examination\Actions\AccommodateApplicantExamSession::class)->handle(
                    $session,
                    $attempt,
                    $actor,
                    'travel_distance',
                    'PostgreSQL final-place race',
                );

                return ['assignment_id' => (int) $assignment->id, 'attempt_id' => (int) $attempt->id];
            })(),
            'publish' => (function () use ($session, $actor, $input): array {
                $published = app(App\Examination\Actions\PublishExamSession::class)->handle(
                    $session,
                    $actor,
                    (int) $input['expected_version'],
                    (string) $input['expected_fingerprint'],
                );

                return ['session_id' => (int) $published->id, 'status' => (string) $published->status];
            })(),
            'save_arrangement' => (function () use ($actor, $input): array {
                $arrangement = App\Models\Foundation\SeatingArrangement::query()->findOrFail((int) $input['arrangement_id']);
                $saved = app(App\Foundation\Actions\SaveSeatingArrangement::class)->handle(
                    $arrangement,
                    $input['attributes'],
                    $actor,
                );

                return [
                    'arrangement_id' => (int) $saved->id,
                    'lock_version' => (int) $saved->lock_version,
                    'rows' => (int) $saved->rows,
                    'columns' => (int) $saved->columns,
                ];
            })(),
            default => throw new RuntimeException('Unsupported exam-logistics worker operation.'),
        };

        fwrite(STDOUT, json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR)."\n");
    } catch (Throwable $exception) {
        fwrite(STDOUT, json_encode([
            'ok' => false,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ], JSON_THROW_ON_ERROR)."\n");
    }
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode(['exception' => $exception::class, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR)."\n");
    exit(1);
}
PHP;

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
        'NEXIAM_TEST_KIT_SOURCE_DATABASE' => 'nexiam',
        'APP_CONFIG_CACHE' => $input['config_cache'],
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
        'NEXIAM_EXAM_LOGISTICS_WORKER_INPUT' => $encodedInput,
    ];

    $process = new Process([PHP_BINARY, '-r', $worker, $encodedInput], base_path(), $environment);
    $process->setTimeout(25);
    $process->start();

    return $process;
}

/** Wait until PostgreSQL itself shows every child blocked on the deliberately held lock. */
function awaitExamLogisticsPostgresLockWait(array $applicationNames, string $waitEventType, ?string $waitEvent = null, array $processes = []): void
{
    $placeholders = implode(', ', array_fill(0, count($applicationNames), '?'));
    $sql = 'SELECT count(*) AS waiting FROM pg_stat_activity WHERE datname = current_database() '
        .'AND application_name IN ('.$placeholders.') AND wait_event_type = ? '
        .'AND cardinality(pg_blocking_pids(pid)) > 0';
    $bindings = [...$applicationNames, $waitEventType];
    if ($waitEvent !== null) {
        $sql .= ' AND wait_event = ?';
        $bindings[] = $waitEvent;
    }

    $deadline = microtime(true) + 10;
    do {
        DB::select('SELECT pg_stat_clear_snapshot()');
        $waiting = (int) DB::scalar($sql, $bindings);
        if ($waiting === count($applicationNames)) {
            return;
        }
        usleep(10_000);
    } while (microtime(true) < $deadline);

    DB::select('SELECT pg_stat_clear_snapshot()');
    $states = DB::select(
        'SELECT application_name, state, wait_event_type, wait_event, pg_blocking_pids(pid) AS blocking_pids '
        .'FROM pg_stat_activity WHERE datname = current_database() AND application_name IN ('.$placeholders.')',
        $applicationNames,
    );
    $diagnostics = ['postgres='.json_encode($states, JSON_THROW_ON_ERROR)];
    foreach ($processes as $index => $process) {
        $diagnostics[] = $applicationNames[$index].' running='.($process->isRunning() ? 'yes' : 'no')
            .' exit='.var_export($process->getExitCode(), true)
            .' stdout='.$process->getOutput().' stderr='.$process->getErrorOutput();
    }
    test()->fail('Expected PostgreSQL lock wait was not observed for all independent exam-logistics workers within 10 seconds. '.implode(' | ', $diagnostics));
}

/** @param list<Process> $processes @return list<array{ok:bool,result?:array<string,mixed>,exception?:string,message?:string}> */
function finishExamLogisticsPostgresWorkers(array $processes): array
{
    $deadline = microtime(true) + 20;
    do {
        $running = false;
        foreach ($processes as $process) {
            $running = $running || $process->isRunning();
        }
        if (! $running) {
            break;
        }
        usleep(10_000);
    } while (microtime(true) < $deadline);

    $results = [];
    foreach ($processes as $process) {
        if ($process->isRunning()) {
            $process->stop(1);
            test()->fail('An exam-logistics PostgreSQL worker exceeded the bounded 20-second completion wait. stderr: '.$process->getErrorOutput());
        }
        if (! $process->isSuccessful()) {
            test()->fail('An exam-logistics PostgreSQL worker exited unexpectedly ('.$process->getExitCode().'): '.$process->getErrorOutput());
        }
        $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));
        $result = json_decode((string) end($lines), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($result) || ! is_bool($result['ok'] ?? null)) {
            test()->fail('An exam-logistics PostgreSQL worker returned no structured Action outcome: '.$process->getOutput());
        }
        $results[] = $result;
    }

    return $results;
}

/** @param list<array<string, mixed>> $workers @return list<array{ok:bool,result?:array<string,mixed>,exception?:string,message?:string}> */
function runExamLogisticsPostgresBarrierRace(array $workers): array
{
    $barrier = random_int(1, 2_000_000_000);
    $processes = [];
    $applicationNames = [];
    $lockHeld = false;

    try {
        DB::select('SELECT pg_advisory_lock(?)', [$barrier]);
        $lockHeld = true;
        foreach ($workers as $index => $worker) {
            $worker['label'] = 'race-'.$index;
            $worker['advisory_barrier'] = $barrier;
            $identity = examLogisticsPostgresWorkerIdentity($worker['label']);
            $worker = [...$worker, ...$identity];
            $processes[] = startExamLogisticsPostgresWorker($worker);
            $applicationNames[] = $identity['application_name'];
        }
        awaitExamLogisticsPostgresLockWait($applicationNames, 'Lock', 'advisory', $processes);
    } catch (Throwable $exception) {
        if ($lockHeld) {
            DB::select('SELECT pg_advisory_unlock(?)', [$barrier]);
        }
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
        throw $exception;
    }

    DB::select('SELECT pg_advisory_unlock(?)', [$barrier]);

    return finishExamLogisticsPostgresWorkers($processes);
}

/** @param Closure(): void $write */
function expectExamLogisticsPostgresConstraint(Closure $write, string $constraint): void
{
    static $sequence = 0;
    $savepoint = 'exam_logistics_pg_constraint_'.(++$sequence);
    DB::statement('SAVEPOINT '.$savepoint);

    try {
        $write();
    } catch (QueryException $exception) {
        DB::statement('ROLLBACK TO SAVEPOINT '.$savepoint);
        DB::statement('RELEASE SAVEPOINT '.$savepoint);
        if (! str_contains($exception->getMessage(), $constraint)) {
            test()->fail('Expected PostgreSQL constraint '.$constraint.'; received '.$exception->getMessage());
        }

        return;
    } catch (Throwable $exception) {
        DB::statement('ROLLBACK TO SAVEPOINT '.$savepoint);
        DB::statement('RELEASE SAVEPOINT '.$savepoint);
        throw $exception;
    }

    DB::statement('ROLLBACK TO SAVEPOINT '.$savepoint);
    DB::statement('RELEASE SAVEPOINT '.$savepoint);
    test()->fail('PostgreSQL accepted a write that must violate '.$constraint.'.');
}

beforeEach(function (): void {
    assertExamLogisticsPostgresAcceptanceDatabase();

    DB::beginTransaction();
    (new DatabaseSeeder)->run([
        ReferenceCatalogSeeder::class,
        CapabilityCatalogSeeder::class,
        StaffIdentitySeeder::class,
        DepartmentSeeder::class,
        IntakeRequirementSeeder::class,
    ]);

    $this->institution = Institution::current();
    $this->cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $this->institutionAdmin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();

    foreach ([
        [$this->guidance, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE],
        [$this->institutionAdmin, CapabilityCatalog::SETUP_INSTITUTION_MANAGE],
    ] as [$actor, $capability]) {
        $decision = app(CapabilityAuthorizer::class)->decideFresh(
            $actor,
            $capability,
            ['institution_id' => (int) $this->institution->id],
        );
        expect($decision->allowed)->toBeTrue();
    }
});

afterEach(function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        return;
    }
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    if (! isset($this->examLogisticsOriginalSeatingAvailability, $this->institution, $this->institutionAdmin)) {
        return;
    }

    $institution = Institution::query()->findOrFail((int) $this->institution->id);
    if ($institution->usesSeatingArrangements() !== $this->examLogisticsOriginalSeatingAvailability) {
        app(UpdateInstitutionExamFeaturePolicy::class)->updateSeatingAvailability(
            $this->institutionAdmin,
            $this->examLogisticsOriginalSeatingAvailability,
            $institution->examFeaturePolicyVersion(),
        );
    }
});

test('a real no-kit draft assignment and reservation publish with positive simple capacity and no assessment or physical-seat records', function (): void {
    $room = examLogisticsPostgresRoom($this->institution, 3);
    $session = examLogisticsPostgresCreateSession($this->cycle, $room, $this->guidance);
    $staffApplicant = examLogisticsPostgresAcceptedApplicant($this->registrar);
    $reservedApplicant = examLogisticsPostgresAcceptedApplicant($this->registrar, authenticateApplicant: true);
    $reservedUser = $reservedApplicant['applicant']
        ?? throw new RuntimeException('A real applicant actor is required for draft reservation.');

    $staffAssignment = app(AssignApplicantToExamSession::class)->handle($session, $staffApplicant['attempt'], $this->guidance);
    $opened = app(OpenExamSessionReservation::class)->handle($session->fresh(), $this->guidance, $session->version);
    $reservation = app(ReserveExamSlot::class)->handle(
        $opened,
        $reservedApplicant['attempt'],
        $reservedUser,
        'pg-logistics-reservation-'.Str::uuid(),
    );
    $session = $session->fresh();
    $preflight = app(ExamSessionPublishPreflight::class)->evaluate($session);
    $published = app(PublishExamSession::class)->handle(
        $session,
        $this->guidance,
        (int) $session->version,
        $preflight['roster_material_fingerprint'],
    );
    $plan = ExamSessionLogisticsPlan::query()
        ->where('exam_session_id', $session->id)
        ->where('is_current', true)
        ->firstOrFail();

    expect($preflight['ok'])->toBeTrue()
        ->and($staffAssignment->isActive())->toBeTrue()
        ->and($reservation->isActive())->toBeTrue()
        ->and($published->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($published->test_kit_version_id)->toBeNull()
        ->and($published->test_instrument_version_id)->toBeNull()
        ->and($published->booklet_version_id)->toBeNull()
        ->and($plan->capacity_limit)->toBe(3)
        ->and($plan->capacity_limit)->toBeGreaterThan(0)
        ->and($plan->seating_mode)->toBe(ExamSession::SEATING_MODE_SIMPLE_CAPACITY)
        ->and($plan->seating_arrangement_id)->toBeNull()
        ->and($plan->arrangement_lock_version)->toBeNull()
        ->and($plan->seat_manifest_hash)->toBeNull()
        ->and($plan->seats()->count())->toBe(0)
        ->and(ExamSessionLogisticsSeat::query()->where('logistics_plan_id', $plan->id)->count())->toBe(0)
        ->and(ExamAssignmentLogisticsSeatAllocation::query()->where('exam_session_id', $session->id)->count())->toBe(0)
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $session->id)->exists())->toBeFalse()
        ->and(ExamSessionSeat::query()->whereHas('materialSnapshot', fn ($query) => $query->where('exam_session_id', $session->id))->exists())->toBeFalse()
        ->and(ExamAssignmentSeatAllocation::query()->whereIn('exam_assignment_id', $session->assignments()->pluck('id'))->exists())->toBeFalse()
        ->and(AssessmentAdministration::query()->whereHas('examAssignment', fn ($query) => $query->where('exam_session_id', $session->id))->exists())->toBeFalse()
        ->and($published->booking_mode)->toBe(ExamSession::BOOKING_STAFF_ONLY)
        ->and($published->reservation_closed_at)->not->toBeNull();

    $lateReservation = examLogisticsPostgresAcceptedApplicant($this->registrar, authenticateApplicant: true);
    expect(fn () => app(ReserveExamSlot::class)->handle(
        $published->fresh(),
        $lateReservation['attempt'],
        $lateReservation['applicant'],
        'pg-logistics-post-publication-'.Str::uuid(),
    ))->toThrow(RuntimeException::class, 'Only draft sessions open for applicant reservation are available.');

    expect($session->assignments()->active()->count())->toBe(2)
        ->and(app(AssignmentInvariant::class)->capacityLimit($published->fresh()))->toBe(3)
        ->and($session->assignments()->active()->where('admission_attempt_id', $lateReservation['attempt']->id)->exists())->toBeFalse();
})->group('postgres-exam-logistics');

test('PostgreSQL rejects duplicate current plans, cross-session plan or seat references, and duplicate active physical occupancy', function (): void {
    $this->examLogisticsOriginalSeatingAvailability = $this->institution->usesSeatingArrangements();
    examLogisticsPostgresEnableAssignedSeating($this->institutionAdmin);
    $arrangement = examLogisticsPostgresArrangement($this->guidance, 1, 4);

    $room = examLogisticsPostgresRoom($this->institution, 4);
    $session = examLogisticsPostgresCreateSession(
        $this->cycle,
        $room,
        $this->guidance,
        ExamSession::SEATING_MODE_ASSIGNED_SEATING,
        $arrangement,
    );
    $first = examLogisticsPostgresAcceptedApplicant($this->registrar);
    $second = examLogisticsPostgresAcceptedApplicant($this->registrar);
    app(AssignApplicantToExamSession::class)->handle($session, $first['attempt'], $this->guidance);
    app(AssignApplicantToExamSession::class)->handle($session, $second['attempt'], $this->guidance);
    $preflight = app(ExamSessionPublishPreflight::class)->evaluate($session->fresh());
    $published = app(PublishExamSession::class)->handle(
        $session->fresh(),
        $this->guidance,
        (int) $session->version,
        $preflight['roster_material_fingerprint'],
    );
    $plan = $published->currentLogisticsPlan;
    $seats = $plan->seats()->orderBy('ordinal')->get();
    $assignments = $published->assignments()->active()->orderBy('admission_attempt_id')->get();

    expect($plan->capacity_limit)->toBe(4)
        ->and($seats)->toHaveCount(4)
        ->and($assignments)->toHaveCount(2)
        ->and(ExamAssignmentLogisticsSeatAllocation::query()->where('exam_session_id', $session->id)->whereNull('effective_to')->count())->toBe(2)
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $session->id)->exists())->toBeFalse()
        ->and(AssessmentAdministration::query()->whereHas('examAssignment', fn ($query) => $query->where('exam_session_id', $session->id))->exists())->toBeFalse();

    $duplicateCurrentPlan = [
        'exam_session_id' => $session->id,
        'revision' => (int) $plan->revision + 1,
        'capacity_limit' => 4,
        'seating_mode' => ExamSession::SEATING_MODE_ASSIGNED_SEATING,
        'seating_arrangement_id' => $arrangement->id,
        'arrangement_lock_version' => $arrangement->lock_version,
        'arrangement_rows' => $arrangement->rows,
        'arrangement_columns' => $arrangement->columns,
        'arrangement_booklet_pattern' => $arrangement->booklet_pattern,
        'seat_manifest_hash' => str_repeat('a', 64),
        'is_current' => true,
        'published_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ];
    expectExamLogisticsPostgresConstraint(
        fn () => DB::table('exam_session_logistics_plans')->insert($duplicateCurrentPlan),
        'exam_logistics_plans_current_unique',
    );

    $unallocatedAttempt = examLogisticsPostgresAcceptedApplicant($this->registrar);
    $unallocatedAssignment = ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $unallocatedAttempt['attempt']->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
        'origin' => ExamAssignment::ORIGIN_MANUAL,
    ]);
    $secondUnallocatedAttempt = examLogisticsPostgresAcceptedApplicant($this->registrar);
    $secondUnallocatedAssignment = ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $secondUnallocatedAttempt['attempt']->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
        'origin' => ExamAssignment::ORIGIN_MANUAL,
    ]);
    $otherRoom = examLogisticsPostgresRoom($this->institution, 4);
    $otherSession = examLogisticsPostgresCreateSession(
        $this->cycle,
        $otherRoom,
        $this->guidance,
        ExamSession::SEATING_MODE_ASSIGNED_SEATING,
        $arrangement,
    );
    $otherAttempt = examLogisticsPostgresAcceptedApplicant($this->registrar);
    app(AssignApplicantToExamSession::class)->handle($otherSession, $otherAttempt['attempt'], $this->guidance);
    $otherPreflight = app(ExamSessionPublishPreflight::class)->evaluate($otherSession->fresh());
    $otherPublished = app(PublishExamSession::class)->handle(
        $otherSession->fresh(),
        $this->guidance,
        (int) $otherSession->version,
        $otherPreflight['roster_material_fingerprint'],
    );
    $otherPlan = $otherPublished->currentLogisticsPlan;
    $otherSeat = $otherPlan->seats()->orderBy('ordinal')->firstOrFail();
    $firstSeat = $seats->firstOrFail();

    $allocation = [
        'exam_assignment_id' => $unallocatedAssignment->id,
        'exam_session_id' => $session->id,
        'logistics_plan_id' => $otherPlan->id,
        'logistics_seat_id' => $otherPlan->seats()->orderBy('ordinal')->skip(1)->firstOrFail()->id,
        'effective_from' => now(),
        'reason' => 'foreign plan must be rejected',
        'assigned_by_user_id' => $this->guidance->id,
        'created_at' => now(),
        'updated_at' => now(),
    ];
    expectExamLogisticsPostgresConstraint(
        fn () => DB::table('exam_assignment_logistics_seat_allocations')->insert($allocation),
        'exam_logistics_alloc_plan_session_fk',
    );

    $allocation['logistics_plan_id'] = $plan->id;
    expectExamLogisticsPostgresConstraint(
        fn () => DB::table('exam_assignment_logistics_seat_allocations')->insert($allocation),
        'exam_logistics_alloc_seat_plan_fk',
    );

    $allocation['logistics_seat_id'] = $seats[2]->id;
    $allocation['reason'] = 'valid same-session allocation';
    DB::table('exam_assignment_logistics_seat_allocations')->insert($allocation);

    $allocation['logistics_seat_id'] = $seats[3]->id;
    $allocation['reason'] = 'duplicate active allocation for one assignment';
    expectExamLogisticsPostgresConstraint(
        fn () => DB::table('exam_assignment_logistics_seat_allocations')->insert($allocation),
        'exam_logistics_alloc_active_assignment_unique',
    );

    $allocation['exam_assignment_id'] = $secondUnallocatedAssignment->id;
    $allocation['logistics_seat_id'] = $firstSeat->id;
    $allocation['reason'] = 'duplicate active seat occupancy';
    expectExamLogisticsPostgresConstraint(
        fn () => DB::table('exam_assignment_logistics_seat_allocations')->insert($allocation),
        'exam_logistics_alloc_active_seat_unique',
    );

    expect(ExamSessionLogisticsPlan::query()->where('exam_session_id', $session->id)->where('is_current', true)->count())->toBe(1)
        ->and(ExamAssignmentLogisticsSeatAllocation::query()->where('exam_session_id', $session->id)->whereNull('effective_to')->count())->toBe(3)
        ->and(ExamAssignmentLogisticsSeatAllocation::query()->where('exam_assignment_id', $unallocatedAssignment->id)->whereNull('effective_to')->count())->toBe(1)
        ->and(ExamAssignmentLogisticsSeatAllocation::query()->where('exam_assignment_id', $secondUnallocatedAssignment->id)->exists())->toBeFalse();
})->group('postgres-exam-logistics');

test('independent real staff assignment Actions serialize exactly one claimant into the draft final place', function (): void {
    $room = examLogisticsPostgresRoom($this->institution, 2);
    $session = examLogisticsPostgresCreateSession($this->cycle, $room, $this->guidance);
    $occupant = examLogisticsPostgresAcceptedApplicant($this->registrar);
    $first = examLogisticsPostgresAcceptedApplicant($this->registrar);
    $second = examLogisticsPostgresAcceptedApplicant($this->registrar);
    app(AssignApplicantToExamSession::class)->handle($session, $occupant['attempt'], $this->guidance);

    DB::commit();
    $outcomes = runExamLogisticsPostgresBarrierRace([
        [
            'label' => 'staff-a',
            'operation' => 'assign',
            'actor_id' => (int) $this->guidance->id,
            'session_id' => (int) $session->id,
            'attempt_id' => (int) $first['attempt']->id,
        ],
        [
            'label' => 'staff-b',
            'operation' => 'assign',
            'actor_id' => (int) $this->guidance->id,
            'session_id' => (int) $session->id,
            'attempt_id' => (int) $second['attempt']->id,
        ],
    ]);

    $successes = array_values(array_filter($outcomes, static fn (array $outcome): bool => $outcome['ok']));
    $failures = array_values(array_filter($outcomes, static fn (array $outcome): bool => ! $outcome['ok']));
    $active = ExamAssignment::query()->active()->where('exam_session_id', $session->id)->get();

    expect($successes)->toHaveCount(1)
        ->and($failures)->toHaveCount(1)
        ->and($failures[0]['exception'])->toBe(RuntimeException::class)
        ->and($failures[0]['message'])->toContain('capacity')
        ->and($active)->toHaveCount(2)
        ->and($active->whereIn('admission_attempt_id', [$first['attempt']->id, $second['attempt']->id]))->toHaveCount(1)
        ->and($active->where('admission_attempt_id', $occupant['attempt']->id))->toHaveCount(1)
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $session->id)->exists())->toBeFalse();
})->group('postgres-exam-logistics');

test('independent real accommodation Actions serialize exactly one applicant into the published final place without materials', function (): void {
    $room = examLogisticsPostgresRoom($this->institution, 2);
    $session = examLogisticsPostgresCreateSession($this->cycle, $room, $this->guidance);
    $occupant = examLogisticsPostgresAcceptedApplicant($this->registrar);
    $first = examLogisticsPostgresAcceptedApplicant($this->registrar);
    $second = examLogisticsPostgresAcceptedApplicant($this->registrar);
    app(AssignApplicantToExamSession::class)->handle($session, $occupant['attempt'], $this->guidance);
    $preflight = app(ExamSessionPublishPreflight::class)->evaluate($session->fresh());
    $published = app(PublishExamSession::class)->handle(
        $session->fresh(),
        $this->guidance,
        (int) $session->version,
        $preflight['roster_material_fingerprint'],
    );

    DB::commit();
    $outcomes = runExamLogisticsPostgresBarrierRace([
        [
            'label' => 'walkin-a',
            'operation' => 'accommodate',
            'actor_id' => (int) $this->guidance->id,
            'session_id' => (int) $published->id,
            'attempt_id' => (int) $first['attempt']->id,
        ],
        [
            'label' => 'walkin-b',
            'operation' => 'accommodate',
            'actor_id' => (int) $this->guidance->id,
            'session_id' => (int) $published->id,
            'attempt_id' => (int) $second['attempt']->id,
        ],
    ]);

    $successes = array_values(array_filter($outcomes, static fn (array $outcome): bool => $outcome['ok']));
    $failures = array_values(array_filter($outcomes, static fn (array $outcome): bool => ! $outcome['ok']));
    $active = ExamAssignment::query()->active()->where('exam_session_id', $published->id)->get();
    $winningAttemptIds = [$first['attempt']->id, $second['attempt']->id];

    expect($successes)->toHaveCount(1)
        ->and($failures)->toHaveCount(1)
        ->and($failures[0]['exception'])->toBe(RuntimeException::class)
        ->and($failures[0]['message'])->toContain('maximum published capacity')
        ->and($active)->toHaveCount(2)
        ->and($active->whereIn('admission_attempt_id', $winningAttemptIds))->toHaveCount(1)
        ->and($active->where('admission_attempt_id', $occupant['attempt']->id))->toHaveCount(1)
        ->and($published->currentLogisticsPlan->capacity_limit)->toBe(2)
        ->and($published->currentLogisticsPlan->seats()->count())->toBe(0)
        ->and(ExamAssignmentLogisticsSeatAllocation::query()->where('exam_session_id', $published->id)->exists())->toBeFalse()
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $published->id)->exists())->toBeFalse()
        ->and(AssessmentAdministration::query()->whereHas('examAssignment', fn ($query) => $query->where('exam_session_id', $published->id))->exists())->toBeFalse();
})->group('postgres-exam-logistics');

test('publication and a real arrangement revision race reject a stale fingerprint or freeze the acknowledged geometry', function (): void {
    $this->examLogisticsOriginalSeatingAvailability = $this->institution->usesSeatingArrangements();
    examLogisticsPostgresEnableAssignedSeating($this->institutionAdmin);
    $arrangement = examLogisticsPostgresArrangement($this->guidance, 1, 2);
    $room = examLogisticsPostgresRoom($this->institution, 4);
    $session = examLogisticsPostgresCreateSession(
        $this->cycle,
        $room,
        $this->guidance,
        ExamSession::SEATING_MODE_ASSIGNED_SEATING,
        $arrangement,
    );
    $applicant = examLogisticsPostgresAcceptedApplicant($this->registrar);
    app(AssignApplicantToExamSession::class)->handle($session, $applicant['attempt'], $this->guidance);
    $preflight = app(ExamSessionPublishPreflight::class)->evaluate($session->fresh());
    $fingerprint = $preflight['roster_material_fingerprint'];
    $expectedVersion = (int) $session->version;

    DB::commit();
    DB::beginTransaction();
    DB::select('SELECT id FROM seating_arrangements WHERE id = ? FOR UPDATE', [$arrangement->id]);

    $workers = [
        [
            'label' => 'publisher',
            'operation' => 'publish',
            'actor_id' => (int) $this->guidance->id,
            'session_id' => (int) $session->id,
            'expected_version' => $expectedVersion,
            'expected_fingerprint' => $fingerprint,
        ],
        [
            'label' => 'arrangement-writer',
            'operation' => 'save_arrangement',
            'actor_id' => (int) $this->guidance->id,
            'session_id' => (int) $session->id,
            'arrangement_id' => (int) $arrangement->id,
            'attributes' => [
                'name' => $arrangement->name,
                'rows' => 2,
                'columns' => 2,
                'booklet_pattern' => SeatingArrangement::PATTERN_UNIFORM,
                'availability_mode' => SeatingArrangement::AVAILABILITY_ALL_ROOMS,
                'room_ids' => [],
                'is_active' => true,
                'lock_version' => (int) $arrangement->lock_version,
            ],
        ],
    ];
    $processes = [];
    $applicationNames = [];
    foreach ($workers as $worker) {
        $identity = examLogisticsPostgresWorkerIdentity($worker['label']);
        $worker = [...$worker, ...$identity];
        $processes[] = startExamLogisticsPostgresWorker($worker);
        $applicationNames[] = $identity['application_name'];
    }

    try {
        awaitExamLogisticsPostgresLockWait($applicationNames, 'Lock', null, $processes);
    } catch (Throwable $exception) {
        DB::rollBack();
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
        throw $exception;
    }
    DB::commit();

    $outcomes = finishExamLogisticsPostgresWorkers($processes);
    $publishOutcome = $outcomes[0];
    $arrangementOutcome = $outcomes[1];
    $freshArrangement = SeatingArrangement::query()->findOrFail($arrangement->id);
    $freshSession = ExamSession::query()->findOrFail($session->id);
    $acknowledgedManifest = [
        ['ordinal' => 1, 'row_number' => 1, 'column_number' => 1, 'seat_label' => 'A1'],
        ['ordinal' => 2, 'row_number' => 1, 'column_number' => 2, 'seat_label' => 'A2'],
    ];

    expect($arrangementOutcome['ok'])->toBeTrue()
        ->and($freshArrangement->lock_version)->toBe(2);

    if ($publishOutcome['ok']) {
        $frozenPlan = ExamSessionLogisticsPlan::query()
            ->where('exam_session_id', $session->id)
            ->where('is_current', true)
            ->firstOrFail();
        expect($freshSession->status)->toBe(ExamSession::STATUS_PUBLISHED)
            ->and($frozenPlan->arrangement_lock_version)->toBe((int) $arrangement->lock_version)
            ->and($frozenPlan->arrangement_rows)->toBe(1)
            ->and($frozenPlan->arrangement_columns)->toBe(2)
            ->and($frozenPlan->capacity_limit)->toBe(2)
            ->and($frozenPlan->seats()->pluck('seat_label')->all())->toBe(['A1', 'A2'])
            ->and($frozenPlan->seat_manifest_hash)->toBe(hash('sha256', json_encode($acknowledgedManifest, JSON_THROW_ON_ERROR)));
    } else {
        expect($publishOutcome['exception'])->toBe(RuntimeException::class)
            ->and($publishOutcome['message'])->toBe('Stale exam session roster or logistics state.')
            ->and($freshSession->status)->toBe(ExamSession::STATUS_DRAFT)
            ->and(ExamSessionLogisticsPlan::query()->where('exam_session_id', $session->id)->exists())->toBeFalse();
    }
    expect(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $session->id)->exists())->toBeFalse()
        ->and(AssessmentAdministration::query()->whereHas('examAssignment', fn ($query) => $query->where('exam_session_id', $session->id))->exists())->toBeFalse();
})->group('postgres-exam-logistics');
