<?php

// Standalone PostgreSQL acceptance: Unit scope avoids Feature's migration reset.

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\InitializeAttemptRequirementSet;
use App\Admission\Actions\SelectApplicantProgram;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Admission\Actions\VerifyAdmissionAttemptDocument;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptDocumentVerification;
use App\Models\Admission\AdmissionAttemptRequirementSet;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\User;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * This proof runs only against the disposable PostgreSQL acceptance database.
 * Independent workers cannot see a RefreshDatabase transaction, so fixtures
 * are committed before each race and use unique synthetic applicant identities.
 */
function assertDocumentChecklistRaceDatabase(): void
{
    $connection = DB::connection();
    $database = (string) $connection->getDatabaseName();

    if ($connection->getDriverName() !== 'pgsql' || $database !== 'nexiam_messaging_acceptance') {
        test()->fail('Document checklist concurrency proof requires the disposable nexiam_messaging_acceptance PostgreSQL database; refusing '.$connection->getDriverName().'/'.$database.'.');
    }
    $schema = (string) DB::scalar('SELECT current_schema()');
    $expectedSchema = (string) getenv('NEXIAM_ADMISSION_TEST_SCHEMA');
    $resolvedSchemas = json_decode(
        (string) DB::scalar('SELECT array_to_json(current_schemas(false))::text'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    if (! preg_match('/^nexiam_admission_checklist_[a-f0-9]{16}$/', $expectedSchema)
        || $schema !== $expectedSchema
        || $resolvedSchemas !== [$expectedSchema]
    ) {
        test()->fail('Refusing checklist concurrency outside its unique isolated schema; expected '.$expectedSchema.', resolved '.$schema.'.');
    }
}

/** @return array{attempt: AdmissionAttempt, registrar: User} */
function documentChecklistRaceAttempt(string $email): array
{
    seedE2Foundation();
    $submitted = submitSyntheticApplicant($email, 'DocRace'.substr(hash('sha256', $email), 0, 8));
    $attempt = $submitted['attempt'];
    $attempt->forceFill(['status' => AdmissionAttempt::STATUS_ACCEPTED])->save();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    return compact('attempt', 'registrar');
}

/**
 * Start a fresh Laravel/PHP process. The parent holds the attempt row lock until
 * every worker is observably queued for that lock in PostgreSQL.
 *
 * @param  array<string, int|string|null>  $input
 */
function startDocumentChecklistRaceWorker(array $input): Process
{
    $worker = <<<'PHP'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$input = json_decode(base64_decode((string) getenv('NEXIAM_ADMISSION_RACE_INPUT'), true), true, 512, JSON_THROW_ON_ERROR);
try {
    $connection = Illuminate\Support\Facades\DB::connection();
    $expectedSchema = (string) getenv('NEXIAM_ADMISSION_TEST_SCHEMA');
    $resolvedSchemas = json_decode(
        (string) $connection->scalar('SELECT array_to_json(current_schemas(false))::text'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    if ($connection->getDriverName() !== 'pgsql'
        || $connection->getDatabaseName() !== 'nexiam_messaging_acceptance'
        || (string) $connection->scalar('SELECT current_schema()') !== $expectedSchema
        || $resolvedSchemas !== [$expectedSchema]
    ) {
        throw new RuntimeException('Refusing worker outside the guarded disposable PostgreSQL acceptance schema.');
    }
    Illuminate\Support\Facades\DB::select('select set_config(?, ?, false)', ['application_name', $input['application_name']]);
    $attempt = App\Models\Admission\AdmissionAttempt::query()->findOrFail($input['attempt_id']);
    $actor = App\Models\User::query()->findOrFail($input['actor_id']);
    $result = match ($input['action']) {
        'initialize' => (function () use ($attempt, $actor, $input): array {
            $set = app(App\Admission\Actions\InitializeAttemptRequirementSet::class)->handle($attempt, $actor, $input['expected_version'], $input['idempotency_key']);
            return ['set_id' => (int) $set->getKey()];
        })(),
        'verify' => (function () use ($attempt, $actor, $input): array {
            $verification = app(App\Admission\Actions\VerifyAdmissionAttemptDocument::class)->handle(
                attempt: $attempt,
                requirementSetId: $input['set_id'],
                requirementItemId: $input['item_id'],
                expectedLatestVerificationId: $input['predecessor_id'],
                status: $input['status'],
                actor: $actor,
                expectedVersion: $input['expected_version'],
                applicantInstruction: $input['status'] === App\Models\Admission\AdmissionAttemptDocumentVerification::STATUS_DEFICIENT ? 'Please provide a complete document for Registrar review.' : null,
                internalRemarks: $input['status'] === App\Models\Admission\AdmissionAttemptDocumentVerification::STATUS_DEFICIENT ? 'Concurrent deficiency proof.' : null,
                idempotencyKey: $input['idempotency_key'],
            );
            return ['verification_id' => (int) $verification->getKey(), 'predecessor_id' => $verification->predecessor_id === null ? null : (int) $verification->predecessor_id];
        })(),
        'confirm' => (function () use ($attempt, $actor, $input): array {
            $receipt = app(App\Admission\Actions\ConfirmAdmissionClearance::class)->handle($attempt, $actor, $input['expected_version'], $input['idempotency_key']);
            return ['receipt_id' => (int) $receipt->getKey(), 'status' => $receipt->status];
        })(),
        default => throw new RuntimeException('Unknown concurrency worker action.'),
    };
    fwrite(STDOUT, json_encode(['outcome' => 'committed', ...$result], JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    fwrite(STDOUT, json_encode(['outcome' => 'rejected', 'class' => $exception::class, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR)."\n");
}
PHP;

    $process = new Process([
        PHP_BINARY,
        '-r',
        $worker,
    ], base_path(), [
        'NEXIAM_ADMISSION_RACE_INPUT' => base64_encode(json_encode($input, JSON_THROW_ON_ERROR)),
    ]);
    $process->setTimeout(45);
    $process->start();

    return $process;
}

/**
 * Run workers against the same locked attempt and wait for PostgreSQL to report
 * every independent connection blocked on a lock before releasing the parent.
 *
 * @param  list<array<string, int|string|null>>  $inputs
 * @return list<array<string, mixed>>
 */
function runDocumentChecklistRace(int $attemptId, array $inputs): array
{
    if (DB::connection()->transactionLevel() > 0) {
        DB::commit();
    }
    $observerName = 'document_checklist_observer';
    config(['database.connections.'.$observerName => config('database.connections.'.config('database.default'))]);
    DB::purge($observerName);
    $observer = DB::connection($observerName);
    $pdo = DB::connection()->getPdo();
    $pdo->beginTransaction();
    $lock = $pdo->prepare('SELECT id FROM admission_attempts WHERE id = ? FOR UPDATE');
    $lock->execute([$attemptId]);
    if ($lock->fetchColumn() === false) {
        $pdo->rollBack();
        DB::purge($observerName);
        test()->fail('Could not lock the synthetic admission attempt before the race.');
    }

    $processes = [];
    $allWorkersQueued = false;
    try {
        foreach ($inputs as $input) {
            $processes[] = startDocumentChecklistRaceWorker($input);
        }

        $names = array_column($inputs, 'application_name');
        $deadline = microtime(true) + 20;
        $waiting = 0;
        do {
            $placeholders = implode(', ', array_fill(0, count($names), '?'));
            $observer->statement('SELECT pg_stat_clear_snapshot()');
            $waiting = (int) $observer->scalar(
                "SELECT count(*) FROM pg_stat_activity WHERE datname = current_database() AND application_name IN ({$placeholders}) AND wait_event_type = 'Lock'",
                $names,
            );
            if ($waiting === count($processes)) {
                break;
            }
            if (collect($processes)->contains(fn (Process $process): bool => ! $process->isRunning())) {
                break;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);

        if ($waiting !== count($processes)) {
            $details = collect($processes)->map(fn (Process $process): string => $process->getErrorOutput().' '.$process->getOutput())->implode(' | ');
            test()->fail('Independent workers did not all reach the PostgreSQL attempt-row lock ('.$waiting.'/'.count($processes).'). '.$details);
        }
        $allWorkersQueued = true;
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        if (! $allWorkersQueued) {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
        }
        DB::purge($observerName);
    }

    $deadline = microtime(true) + 45;
    foreach ($processes as $process) {
        while ($process->isRunning() && microtime(true) < $deadline) {
            usleep(10_000);
        }
        if ($process->isRunning()) {
            $process->stop(1);
            test()->fail('A PostgreSQL checklist worker exceeded the bounded completion wait.');
        }
        if (! $process->isSuccessful()) {
            test()->fail('PostgreSQL checklist worker exited '.$process->getExitCode().': '.$process->getErrorOutput());
        }
    }

    return array_map(function (Process $process): array {
        $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));
        $result = json_decode((string) end($lines), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($result) || ! isset($result['outcome'])) {
            test()->fail('PostgreSQL checklist worker returned no outcome: '.$process->getOutput());
        }

        return $result;
    }, $processes);
}

/** @return array{attempt: AdmissionAttempt, applicant: User, registrar: User, confirmationRegistrar: User, set: AdmissionAttemptRequirementSet} */
function readyDocumentChecklistRaceAttempt(string $email): array
{
    seedE2Foundation();
    $submitted = submitSyntheticApplicant($email, 'DocRace'.substr(hash('sha256', $email), 0, 12));
    $attempt = $submitted['attempt'];
    $attempt->forceFill(['status' => AdmissionAttempt::STATUS_ACCEPTED])->save();
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle($submitted['access_token'], '2001-01-01');
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $confirmationRegistrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    foreach ([DEFAULT_PROGRAM, OTHER_PROGRAM] as $programCode) {
        $program = Program::query()->where('code', $programCode)->firstOrFail();
        ProgramQuota::query()->updateOrCreate(
            ['program_id' => $program->id, 'academic_cycle_id' => $cycle->id],
            ['slots' => 10],
        );
    }

    $room = Room::query()->firstOrFail();
    $session = ExamSession::query()->firstOrCreate(
        ['code' => 'EX-CHK-'.substr(hash('sha256', $email), 0, 10)],
        [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'starts_at' => now()->subHours(3),
            'effective_end_at' => now()->subHours(1),
            'status' => 'closed',
        ],
    );
    ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
    ]);
    GradingSession::query()->firstOrCreate(
        ['exam_session_id' => $session->id],
        ['status' => GradingSession::STATUS_FINALIZED, 'opened_at' => now()->subHours(2), 'finalized_at' => now()->subHour()],
    );

    $resultDocument = ResultDocument::query()->create([
        'admission_attempt_id' => $attempt->id,
        'template_version' => 1,
        'checksum' => 'checklist-race-'.substr(hash('sha256', $email), 0, 32),
        'status' => ResultDocument::STATUS_READY,
        'version' => 1,
        'snapshot_payload_json' => ['score' => 85],
    ]);
    ResultRelease::query()->create([
        'admission_attempt_id' => $attempt->id,
        'result_document_id' => $resultDocument->id,
        'released_by_user_id' => $guidance->id,
        'release_version' => 1,
        'is_current' => true,
        'released_at' => now(),
        'mode' => 'f2f',
        'channel' => 'physical',
        'idempotency_key' => 'checklist-release-'.substr(hash('sha256', $email), 0, 24),
    ]);
    ConsultationDecision::query()->create([
        'admission_attempt_id' => $attempt->id,
        'decision_type' => 'approve',
        'counselor_user_id' => $guidance->id,
        'decided_at' => now(),
        'idempotency_key' => 'checklist-decision-'.substr(hash('sha256', $email), 0, 24),
    ]);
    app(SetGuidanceProgramPool::class)->handle(
        attempt: $attempt,
        counselor: $guidance,
        defaultProgramCode: DEFAULT_PROGRAM,
        options: [
            ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'qualifies'],
            ['program_code' => OTHER_PROGRAM, 'display_order' => 2, 'basis' => 'qualifies'],
        ],
        reason: 'Checklist concurrency proof fixture.',
        idempotencyKey: 'checklist-pool-'.substr(hash('sha256', $email), 0, 24),
    );

    app(SelectApplicantProgram::class)->handle($attempt, $applicant, DEFAULT_PROGRAM, 'race-select-'.Str::uuid());
    $attempt->refresh();
    $set = app(InitializeAttemptRequirementSet::class)->handle(
        $attempt,
        $registrar,
        (int) $attempt->version,
        'race-init-'.Str::uuid(),
    );
    foreach ($set->items()->where('is_mandatory', true)->get() as $item) {
        $attempt->refresh();
        app(VerifyAdmissionAttemptDocument::class)->handle(
            attempt: $attempt,
            requirementSetId: (int) $set->getKey(),
            requirementItemId: (int) $item->getKey(),
            expectedLatestVerificationId: null,
            status: AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
            actor: $registrar,
            expectedVersion: (int) $attempt->version,
            applicantInstruction: null,
            internalRemarks: null,
            idempotencyKey: 'race-verified-'.Str::uuid(),
        );
    }

    return compact('attempt', 'applicant', 'registrar', 'confirmationRegistrar', 'set');
}

beforeEach(function (): void {
    assertDocumentChecklistRaceDatabase();
});

test('two independent initializers cannot create two current requirement sets', function (): void {
    ['attempt' => $attempt, 'registrar' => $registrar] = documentChecklistRaceAttempt('checklist-init-'.Str::uuid().'@example.test');
    $version = (int) $attempt->version;
    $suffix = (string) Str::uuid();
    $base = ['attempt_id' => (int) $attempt->id, 'actor_id' => (int) $registrar->id, 'action' => 'initialize', 'expected_version' => $version];
    $results = runDocumentChecklistRace((int) $attempt->id, [
        [...$base, 'idempotency_key' => 'race-init-a-'.$suffix, 'application_name' => 'doc-init-'.$suffix.'-a'],
        [...$base, 'idempotency_key' => 'race-init-b-'.$suffix, 'application_name' => 'doc-init-'.$suffix.'-b'],
    ]);

    $sets = AdmissionAttemptRequirementSet::query()->where('admission_attempt_id', $attempt->id)->get();
    expect(collect($results)->where('outcome', 'committed'))->toHaveCount(1)
        ->and($sets)->toHaveCount(1)
        ->and($sets->where('is_current', true))->toHaveCount(1)
        ->and((int) $attempt->fresh()->version)->toBe($version + 1);
});

test('two independent inspection writers cannot fork the same predecessor', function (): void {
    ['attempt' => $attempt, 'registrar' => $registrar, 'set' => $set] = readyDocumentChecklistRaceAttempt('checklist-inspect-'.Str::uuid().'@example.test');
    $item = $set->items()->where('is_mandatory', true)->firstOrFail();
    $version = (int) $attempt->fresh()->version;
    $predecessorId = (int) AdmissionAttemptDocumentVerification::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('requirement_item_id', $item->id)
        ->latest('id')
        ->value('id');
    $suffix = (string) Str::uuid();
    $base = [
        'attempt_id' => (int) $attempt->id,
        'actor_id' => (int) $registrar->id,
        'action' => 'verify',
        'expected_version' => $version,
        'set_id' => (int) $set->id,
        'item_id' => (int) $item->id,
        'predecessor_id' => $predecessorId,
    ];
    $results = runDocumentChecklistRace((int) $attempt->id, [
        [...$base, 'status' => AdmissionAttemptDocumentVerification::STATUS_MISSING, 'idempotency_key' => 'race-inspect-a-'.$suffix, 'application_name' => 'doc-inspect-'.$suffix.'-a'],
        [...$base, 'status' => AdmissionAttemptDocumentVerification::STATUS_VERIFIED, 'idempotency_key' => 'race-inspect-b-'.$suffix, 'application_name' => 'doc-inspect-'.$suffix.'-b'],
    ]);

    $rows = AdmissionAttemptDocumentVerification::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('requirement_item_id', $item->id)
        ->orderBy('id')
        ->get();
    expect(collect($results)->where('outcome', 'committed'))->toHaveCount(1)
        ->and($rows)->toHaveCount(2)
        ->and($rows->last()->predecessor_id)->toBe($rows->first()->id)
        ->and($rows->whereNull('predecessor_id'))->toHaveCount(1);
});

test('confirmation racing a deficient save leaves one serially consistent root state', function (): void {
    ['attempt' => $attempt, 'registrar' => $registrar, 'confirmationRegistrar' => $confirmationRegistrar, 'set' => $set] = readyDocumentChecklistRaceAttempt('checklist-confirm-'.Str::uuid().'@example.test');
    $item = $set->items()->where('is_mandatory', true)->firstOrFail();
    $version = (int) $attempt->fresh()->version;
    $predecessorId = (int) AdmissionAttemptDocumentVerification::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('requirement_item_id', $item->id)
        ->latest('id')
        ->value('id');
    $suffix = (string) Str::uuid();
    $base = ['attempt_id' => (int) $attempt->id, 'expected_version' => $version];
    $results = runDocumentChecklistRace((int) $attempt->id, [
        [...$base, 'actor_id' => (int) $confirmationRegistrar->id, 'action' => 'confirm', 'idempotency_key' => 'race-confirm-'.$suffix, 'application_name' => 'doc-confirm-'.$suffix],
        [...$base, 'actor_id' => (int) $registrar->id, 'action' => 'verify', 'set_id' => (int) $set->id, 'item_id' => (int) $item->id, 'predecessor_id' => $predecessorId, 'status' => AdmissionAttemptDocumentVerification::STATUS_DEFICIENT, 'idempotency_key' => 'race-deficient-'.$suffix, 'application_name' => 'doc-deficient-'.$suffix],
    ]);

    $confirmed = AdmissionClearanceReceipt::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('status', AdmissionClearanceReceipt::STATUS_CONFIRMED)
        ->exists();
    $latest = AdmissionAttemptDocumentVerification::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('requirement_item_id', $item->id)
        ->latest('id')
        ->firstOrFail();
    $committed = collect($results)->where('outcome', 'committed');

    expect($committed)->toHaveCount(1)
        ->and($confirmed)->toBe($latest->status === AdmissionAttemptDocumentVerification::STATUS_VERIFIED)
        ->and($confirmed || $latest->status === AdmissionAttemptDocumentVerification::STATUS_DEFICIENT)->toBeTrue();
})->group('postgres-admission-checklist');
