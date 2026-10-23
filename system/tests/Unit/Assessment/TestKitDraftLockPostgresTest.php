<?php

declare(strict_types=1);

use App\Assessment\Actions\AssembleTestKitPreparation;
use App\Assessment\Actions\SaveTestKitPreparation;
use App\Assessment\Actions\StartTestKitPreparation;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Process\Process;

require_once __DIR__.'/../../Support/NormConversionFixtures.php';

const TEST_KIT_DRAFT_LOCK_PG_DATABASE = 'nexiam_test_kit_acceptance_composition_20261015';

/** Refuse every target except the isolated PostgreSQL acceptance database before writing fixture rows. */
function assertTestKitDraftLockPostgresAcceptanceDatabase(): void
{
    $expectedEnvironment = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_DATABASE' => TEST_KIT_DRAFT_LOCK_PG_DATABASE,
        'DB_HOST' => 'postgres',
        'DB_PORT' => '5432',
        'DB_USERNAME' => 'nexiam',
        'DB_PASSWORD' => 'nexiam-local-only',
        'DB_URL' => '',
        'NEXIAM_TEST_PIN_SQLITE' => '0',
    ];
    foreach ($expectedEnvironment as $key => $expected) {
        if (getenv($key) !== $expected) {
            test()->fail('Refusing Test Kit draft-lock acceptance because '.$key.' is not exactly '.var_export($expected, true).'.');
        }
    }

    $connection = DB::connection();
    $database = (string) $connection->scalar('SELECT current_database()');
    if (app()->environment() !== 'testing'
        || $connection->getDriverName() !== 'pgsql'
        || $database !== TEST_KIT_DRAFT_LOCK_PG_DATABASE
        || config('database.default') !== 'pgsql'
        || config('database.connections.pgsql.database') !== TEST_KIT_DRAFT_LOCK_PG_DATABASE
        || (string) config('database.connections.pgsql.url') !== '') {
        test()->fail('Refusing Test Kit draft-lock acceptance outside the exact isolated PostgreSQL target.');
    }
}

/** @return array{application_name:string,config_cache:string,database:string} */
function testKitDraftLockWorkerIdentity(string $label): array
{
    $token = substr(str_replace('-', '', (string) Str::uuid()), 0, 12);
    $applicationName = 'kitlock-'.$token.'-'.$label;

    return [
        'application_name' => $applicationName,
        'config_cache' => sys_get_temp_dir().'/'.TEST_KIT_DRAFT_LOCK_PG_DATABASE.'-'.$applicationName.'-config.php',
        'database' => TEST_KIT_DRAFT_LOCK_PG_DATABASE,
    ];
}

/** @param array<string,mixed> $input */
function startTestKitDraftLockWorker(array $input): Process
{
    if (! isset($input['application_name'], $input['config_cache'], $input['database'])) {
        $input = [...$input, ...testKitDraftLockWorkerIdentity((string) $input['label'])];
    }
    $input['advisory_barrier'] ??= null;
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
    $database = (string) $connection->scalar('SELECT current_database()');
    if (app()->environment() !== 'testing'
        || getenv('APP_ENV') !== 'testing'
        || getenv('DB_URL') !== ''
        || $connection->getDriverName() !== 'pgsql'
        || $database !== 'nexiam_test_kit_acceptance_composition_20261015'
        || $database !== $input['database']) {
        throw new RuntimeException('Refusing draft-lock worker outside its exact isolated PostgreSQL target.');
    }
    Illuminate\Support\Facades\DB::select('SELECT set_config(?, ?, false)', ['application_name', $input['application_name']]);
    if (is_int($input['advisory_barrier'] ?? null)) {
        Illuminate\Support\Facades\DB::select('SELECT pg_advisory_lock_shared(?)', [$input['advisory_barrier']]);
        fwrite(STDOUT, "READY\n");
        fflush(STDOUT);
    }

    try {
        $actor = App\Models\User::query()->findOrFail((int) $input['actor_id']);
        $version = app(App\Assessment\Actions\AssembleTestKitPreparation::class)->handle(
            $actor,
            (int) $input['kit_id'],
            [
                'preparation_id' => (int) $input['preparation_id'],
                'expected_revision' => (int) $input['expected_revision'],
                'idempotency_key' => (string) $input['idempotency_key'],
            ],
        );
        fwrite(STDOUT, json_encode(['ok' => true, 'version_id' => (int) $version->id, 'version_number' => (int) $version->version_number], JSON_THROW_ON_ERROR)."\n");
    } catch (\Illuminate\Validation\ValidationException $exception) {
        fwrite(STDOUT, json_encode(['ok' => false, 'rejection' => $exception::class], JSON_THROW_ON_ERROR)."\n");
    } catch (Throwable $exception) {
        $message = preg_replace('/\\s+/', ' ', $exception->getMessage()) ?? '';
        $message = str_replace(
            [getenv('DB_PASSWORD') ?: 'no-password', getenv('DB_DATABASE') ?: 'no-database'],
            ['<redacted>', '<database>'],
            $message,
        );
        fwrite(STDOUT, json_encode([
            'ok' => false,
            'unexpected' => $exception::class,
            'message' => substr($message, 0, 240),
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
function finishTestKitDraftLockWorkers(array $processes): array
{
    $results = [];
    foreach ($processes as $process) {
        $process->wait();
        if (! $process->isSuccessful()) {
            test()->fail('A Test Kit draft-lock worker exited unexpectedly: '.$process->getErrorOutput());
        }
        $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));
        $result = json_decode((string) end($lines), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($result)) {
            test()->fail('A Test Kit draft-lock worker returned no structured result.');
        }
        $results[] = $result;
    }

    return $results;
}

test('isolated PostgreSQL serializes competing Setup-only draft locks and preserves exact replay', function (): void {
    assertTestKitDraftLockPostgresAcceptanceDatabase();
    DB::beginTransaction();

    $uuid = (string) Str::uuid();
    $institution = Institution::query()->create([
        'code' => 'SYN-DRAFT-RACE-'.str_replace('-', '', $uuid),
        'name' => 'Synthetic draft-lock race institution',
    ]);
    $actor = User::factory()->create([
        'name' => 'Synthetic draft-lock race staff',
        'email' => 'draft-lock-race-'.str_replace('-', '', $uuid).'@example.test',
    ]);
    StaffProfile::query()->create([
        'user_id' => $actor->id,
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);

    $profile = createTestApprovedProfileVersion($this, $actor, 'SYN-DRAFT-RACE-'.str_replace('-', '', $uuid));
    $kit = TestKit::query()->create([
        'public_id' => (string) Str::uuid(),
        'institution_id' => $institution->id,
        'code' => 'SYN-KIT-RACE-'.substr(str_replace('-', '', $uuid), 0, 16),
        'name' => 'Synthetic Setup-only lock race',
        'is_synthetic' => true,
        'created_by_user_id' => $actor->id,
    ]);
    $preparation = app(StartTestKitPreparation::class)->handle($actor, (int) $kit->id, [
        'expected_family_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $preparation = app(SaveTestKitPreparation::class)->handle($actor, (int) $kit->id, [
        'preparation_id' => (int) $preparation->id,
        'expected_revision' => (int) $preparation->revision,
        'idempotency_key' => (string) Str::uuid(),
        'test_instrument_version_id' => (int) $profile->id,
        'norm_package_version_id' => null,
        'answer_sheet_draft_id' => null,
        'template_version_id' => null,
        'booklet_version_id' => null,
        'booklet_set_id' => null,
    ]);
    $artifactTables = [
        'omr_answer_sheet_templates',
        'omr_answer_sheet_template_versions',
        'omr_question_booklets',
        'omr_question_booklet_versions',
        'omr_booklet_sets',
        'test_kit_version_composition_rows',
        'test_kit_preparation_composition_revisions',
        'test_kit_preparation_composition_rows',
        'test_kit_acceptance_receipts',
        'test_kit_acceptance_plans',
        'assessment_administrations',
        'assessment_scoring_runs',
        'assessment_raw_result_revisions',
    ];
    $artifactsBeforeLock = [];
    foreach ($artifactTables as $table) {
        $artifactsBeforeLock[$table] = DB::table($table)->count();
    }
    $preparation->refresh();
    $expectedRevision = (int) $preparation->revision;
    $actionKeyOne = (string) Str::uuid();
    $actionKeyTwo = (string) Str::uuid();

    // All actor and immutable Setup rows are committed before separate workers bootstrap.
    DB::commit();

    $barrier = random_int(1, 2_000_000_000);
    $processes = [];
    $applicationNames = [];
    $barrierHeld = false;
    try {
        DB::select('SELECT pg_advisory_lock(?)', [$barrier]);
        $barrierHeld = true;
        foreach ([['left', $actionKeyOne], ['right', $actionKeyTwo]] as [$label, $key]) {
            $input = [
                'actor_id' => (int) $actor->id,
                'expected_revision' => $expectedRevision,
                'idempotency_key' => $key,
                'kit_id' => (int) $kit->id,
                'label' => $label,
                'preparation_id' => (int) $preparation->id,
            ];
            $identity = testKitDraftLockWorkerIdentity($label);
            $input = [...$input, ...$identity, 'advisory_barrier' => $barrier];
            $processes[] = startTestKitDraftLockWorker($input);
            $applicationNames[] = $identity['application_name'];
        }

        $deadline = microtime(true) + 10;
        do {
            DB::select('SELECT pg_stat_clear_snapshot()');
            $placeholders = implode(', ', array_fill(0, count($applicationNames), '?'));
            $waiting = (int) DB::scalar(
                'SELECT count(*) FROM pg_stat_activity WHERE datname = current_database() AND application_name IN ('.$placeholders.') AND wait_event_type = ? AND wait_event = ? AND cardinality(pg_blocking_pids(pid)) > 0',
                [...$applicationNames, 'Lock', 'advisory'],
            );
            if ($waiting === count($applicationNames)) {
                break;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);
        if ($waiting !== count($applicationNames)) {
            test()->fail('Both draft-lock workers did not reach the PostgreSQL advisory barrier.');
        }
        DB::select('SELECT pg_advisory_unlock(?)', [$barrier]);
        $barrierHeld = false;
        $outcomes = finishTestKitDraftLockWorkers($processes);
    } finally {
        if ($barrierHeld) {
            DB::select('SELECT pg_advisory_unlock(?)', [$barrier]);
        }
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
    }

    $successful = array_values(array_filter($outcomes, static fn (array $outcome): bool => ($outcome['ok'] ?? false) === true));
    $rejected = array_values(array_filter($outcomes, static fn (array $outcome): bool => ($outcome['ok'] ?? null) === false
        && ($outcome['rejection'] ?? null) === ValidationException::class));
    expect($outcomes)->toHaveCount(2)
        ->and($successful)->toHaveCount(1)
        ->and($rejected)->toHaveCount(1)
        ->and(array_filter($outcomes, static fn (array $outcome): bool => isset($outcome['unexpected'])))->toBe([]);

    $version = TestKitVersion::query()->where('test_kit_id', $kit->id)->sole();
    $draft = TestKitPreparation::query()->whereKey($preparation->id)->firstOrFail();
    $successfulKey = ($outcomes[0]['ok'] ?? false) ? $actionKeyOne : $actionKeyTwo;
    $successfulVersionId = (int) (($outcomes[0]['ok'] ?? false) ? $outcomes[0]['version_id'] : $outcomes[1]['version_id']);
    $replay = app(AssembleTestKitPreparation::class)->handle($actor, (int) $kit->id, [
        'preparation_id' => (int) $preparation->id,
        'expected_revision' => $expectedRevision,
        'idempotency_key' => $successfulKey,
    ]);
    expect((int) $version->id)->toBe($successfulVersionId)
        ->and($version->version_number)->toBe(1)
        ->and((int) $replay->id)->toBe($successfulVersionId)
        ->and($draft->state)->toBe(TestKitPreparation::STATE_ASSEMBLED)
        ->and((int) $draft->assembled_test_kit_version_id)->toBe($successfulVersionId)
        ->and(TestKitVersion::query()->where('test_kit_id', $kit->id)->count())->toBe(1);

    expect(fn () => app(AssembleTestKitPreparation::class)->handle($actor, (int) $kit->id, [
        'preparation_id' => (int) $preparation->id,
        'expected_revision' => $expectedRevision + 1,
        'idempotency_key' => $successfulKey,
    ]))->toThrow(ValidationException::class);

    foreach ($artifactsBeforeLock as $table => $count) {
        expect(DB::table($table)->count())->toBe($count, $table.' must not be created by a Setup-only lock.');
    }

    expect($version->test_instrument_version_id)->toBe($profile->id)
        ->and($version->norm_package_version_id)->toBeNull()
        ->and($version->template_version_id)->toBeNull()
        ->and($version->booklet_version_id)->toBeNull()
        ->and($version->booklet_set_id)->toBeNull();
})->group('postgres-test-kit-draft-lock');
