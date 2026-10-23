<?php

declare(strict_types=1);

use App\Assessment\Actions\Omr\Camera\OmrCameraFrameFinalization;
use App\Assessment\Actions\Omr\Camera\OmrCameraProtocol;
use App\Assessment\Actions\Omr\Operational\CaptureOmrDiagnosticRun;
use App\Models\Assessment\OmrCameraCommand;
use App\Models\Assessment\OmrCameraFrame;
use App\Models\Assessment\OmrCameraPairing;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Refuse writes unless both environment and live PDO identify the one isolated target. */
function assertOmrCameraPostgresAcceptanceTarget(): void
{
    $expectedEnvironment = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_DATABASE' => OMR_CAMERA_POSTGRES_ACCEPTANCE_DATABASE,
        'DB_HOST' => 'postgres',
        'DB_PORT' => '5432',
        'DB_USERNAME' => 'nexiam',
        'DB_PASSWORD' => 'nexiam-local-only',
        'DB_URL' => '',
        'NEXIAM_TEST_PIN_SQLITE' => '0',
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
    ];
    foreach ($expectedEnvironment as $key => $expected) {
        if (getenv($key) !== $expected) {
            test()->fail('Refusing OMR camera acceptance writes because '.$key.' is not the exact isolated PostgreSQL value.');
        }
    }
    $configCache = getenv('APP_CONFIG_CACHE');
    if (! is_string($configCache) || ! str_starts_with($configCache, sys_get_temp_dir().'/omr-camera-postgres-')) {
        test()->fail('Refusing OMR camera acceptance without a unique isolated config-cache path.');
    }

    $connection = DB::connection();
    if (app()->environment() !== 'testing'
        || $connection->getDriverName() !== 'pgsql'
        || (string) $connection->scalar('SELECT current_database()') !== OMR_CAMERA_POSTGRES_ACCEPTANCE_DATABASE
        || config('database.default') !== 'pgsql'
        || config('database.connections.pgsql.database') !== OMR_CAMERA_POSTGRES_ACCEPTANCE_DATABASE
        || (string) config('database.connections.pgsql.url') !== '') {
        test()->fail('Refusing OMR camera acceptance writes outside the exact isolated PostgreSQL target.');
    }
}

/** @return array{application_name:string,config_cache:string,database:string} */
function omrCameraPostgresWorkerIdentity(string $label): array
{
    $token = substr(str_replace('-', '', (string) Str::uuid()), 0, 12);
    $applicationName = 'omrcam-'.$token.'-'.$label;

    return [
        'application_name' => $applicationName,
        'config_cache' => sys_get_temp_dir().'/'.OMR_CAMERA_POSTGRES_ACCEPTANCE_DATABASE.'-'.$applicationName.'-config.php',
        'database' => OMR_CAMERA_POSTGRES_ACCEPTANCE_DATABASE,
    ];
}

/** @param array<string,mixed> $input */
function startOmrCameraPostgresWorker(array $input): Process
{
    $encodedInput = base64_encode(json_encode($input, JSON_THROW_ON_ERROR));
    $worker = <<<'PHP'
try {
    $raw = base64_decode((string) ($argv[1] ?? ''), true);
    if (! is_string($raw)) {
        throw new RuntimeException('Invalid worker input.');
    }
    $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($input) || ($input['database'] ?? null) !== 'nexiam_omr_assisted_verify_20261019_a4c8') {
        throw new RuntimeException('Invalid or forbidden PostgreSQL worker target.');
    }
    $expectedConfigCache = sys_get_temp_dir().'/'.$input['database'].'-'.$input['application_name'].'-config.php';
    if (($input['config_cache'] ?? null) !== $expectedConfigCache) {
        throw new RuntimeException('Refusing an OMR camera worker without its own isolated config cache.');
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
        || getenv('DB_URL') !== ''
        || $connection->getDriverName() !== 'pgsql'
        || (string) $connection->scalar('SELECT current_database()') !== $input['database']
        || (string) config('database.connections.pgsql.database') !== $input['database']
        || (string) config('database.connections.pgsql.url') !== ''
        || config('cache.default') !== 'array'
        || config('queue.default') !== 'sync'
        || config('session.driver') !== 'array') {
        throw new RuntimeException('Refusing OMR camera worker outside exact isolated PostgreSQL target.');
    }
    Illuminate\Support\Facades\DB::select('SELECT set_config(?, ?, false)', ['application_name', $input['application_name']]);
    Illuminate\Support\Facades\DB::select('SELECT pg_advisory_lock_shared(?)', [(int) $input['barrier']]);
    fwrite(STDOUT, "READY\n");
    fflush(STDOUT);

    try {
        $actor = App\Models\User::query()->findOrFail((int) $input['actor_id']);
        $protocol = app(App\Assessment\Actions\Omr\Camera\OmrCameraProtocol::class);
        if ($input['action'] === 'create') {
            $result = $protocol->create($actor, $input['workbench_id'], (int) $input['institution_id'],
                (int) $input['cycle_id'], null, (int) $input['run_id'], $input['target'],
                (bool) ($input['takeover'] ?? false), isset($input['expected_generation']) ? (int) $input['expected_generation'] : null);
            fwrite(STDOUT, json_encode(['ok' => true, 'pairing_id' => $result['pairing']->public_id], JSON_THROW_ON_ERROR)."\n");
        } elseif ($input['action'] === 'command_ack') {
            $pairing = App\Models\Assessment\OmrCameraPairing::query()->findOrFail((int) $input['pairing_id']);
            $command = $protocol->command($pairing, $input['kind'], $input['command_id'], $input['operation_id'], $input['payload']);
            $frame = $protocol->acknowledgeAcquired($pairing, $input['ack']);
            fwrite(STDOUT, json_encode(['ok' => true, 'command_id' => (int) $command->id, 'frame_id' => $frame->public_id], JSON_THROW_ON_ERROR)."\n");
        } elseif ($input['action'] === 'reserve_only') {
            $reservation = app(App\Assessment\Actions\Omr\Camera\OmrCameraFrameFinalization::class)->reserveOperation(
                $actor, $input['workbench_key'], $input['workbench'], $input['target'], $input['operation_id'],
                $input['idempotency_key'], $input['payload_fingerprint'], $input['crop_policy'], null,
            );
            fwrite(STDOUT, json_encode(['ok' => true, 'operation_id' => (int) $reservation->operation->id], JSON_THROW_ON_ERROR)."\n");
        } elseif ($input['action'] === 'stale_commit') {
            $operation = App\Models\Assessment\OmrCameraCommand::query()->findOrFail((int) $input['operation_record_id']);
            $reservation = new App\Assessment\Actions\Omr\Camera\OmrCameraFrameReservation(
                $operation, null, $input['lease_token'], (string) $operation->payload_fingerprint, null, (int) $input['lease_generation'],
            );
            Illuminate\Support\Facades\DB::transaction(fn () => app(App\Assessment\Actions\Omr\Camera\OmrCameraFrameFinalization::class)
                ->commitAccepted($reservation, (array) $operation->domain_receipt_json));
            fwrite(STDOUT, json_encode(['ok' => true], JSON_THROW_ON_ERROR)."\n");
        } else {
            throw new RuntimeException('Unknown worker action.');
        }
    } catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
        fwrite(STDOUT, json_encode(['ok' => false, 'status' => $exception->getStatusCode(), 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR)."\n");
    } catch (Throwable $exception) {
        fwrite(STDOUT, json_encode(['ok' => false, 'unexpected' => $exception::class, 'message' => substr($exception->getMessage(), 0, 240)], JSON_THROW_ON_ERROR)."\n");
    }
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode(['worker_error' => $exception::class, 'message' => substr($exception->getMessage(), 0, 240)], JSON_THROW_ON_ERROR)."\n");
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
        'APP_CONFIG_CACHE' => $input['config_cache'],
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
    ];
    $process = new Process([PHP_BINARY, '-r', $worker, $encodedInput], base_path(), $environment);
    $process->setTimeout(30);
    $process->start();

    return $process;
}

/** @param list<Process> $processes @return list<array<string,mixed>> */
function finishOmrCameraPostgresWorkers(array $processes): array
{
    try {
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            if (! $process->isSuccessful()) {
                test()->fail('An OMR camera PostgreSQL worker failed: '.$process->getErrorOutput());
            }
            $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));
            $result = json_decode((string) end($lines), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($result)) {
                test()->fail('An OMR camera worker returned no structured result.');
            }
            $results[] = $result;
        }

        return $results;
    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
    }
}

/** @param list<Process> $processes @param list<string> $applicationNames */
function awaitOmrCameraPostgresWorkers(array $processes, array $applicationNames): void
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
        foreach ($processes as $process) {
            if (! $process->isRunning()) {
                test()->fail('An OMR camera worker exited before reaching the PostgreSQL barrier: '.$process->getErrorOutput());
            }
        }
        usleep(10_000);
    } while (microtime(true) < $deadline);

    test()->fail('OMR camera workers did not all reach the PostgreSQL advisory barrier.');
}

/** @return array{actor:User,cycle:AcademicCycle,institution:Institution,kit:array<string,mixed>} */
function omrCameraPostgresSharedMaterials(TestCase $test): array
{
    static $materials = null;
    if ($materials !== null) {
        return $materials;
    }
    assertOmrCameraPostgresAcceptanceTarget();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->first();
    if ($actor === null) {
        seedE2Foundation();
        $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    }
    $institution = Institution::query()->findOrFail($actor->staffProfile->institution_id);
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $kit = testKitAcceptanceFixture($actor, $institution);

    $materials = [
        'actor' => $actor,
        'cycle' => $cycle,
        'institution' => $institution,
        'kit' => $kit,
    ];

    return $materials;
}

/** @return array{actor:User,cycle:AcademicCycle,institution:Institution,run:OmrDiagnosticRun,target:array<string,mixed>} */
function omrCameraPostgresDiagnosticFixture(TestCase $test): array
{
    $materials = omrCameraPostgresSharedMaterials($test);
    $actor = $materials['actor'];
    $institution = $materials['institution'];
    $cycle = $materials['cycle'];
    $kit = $materials['kit'];

    $guide = $test->actingAs($actor)->getJson(route('setup.exam-materials.omr-test.runs.mock-guide', [
        'test_kit_version_id' => (int) $kit['version']->id,
        'expected_revision' => (int) $kit['version']->revision,
        'instance_count' => 2,
        'pattern_mode' => 'perfect_set_a',
    ]))->assertOk();

    $manifestSha = (string) $guide->json('manifest_sha256');

    $response = $test->actingAs($actor)->postJson(route('setup.exam-materials.omr-test.runs.start'), [
        'diagnostic_purpose' => 'mock_rehearsal',
        'test_kit_version_id' => (int) $kit['version']->id,
        'expected_revision' => (int) $kit['version']->revision,
        'instance_count' => 2,
        'pattern_mode' => 'perfect_set_a',
        'expected_mock_manifest_sha256' => $manifestSha,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertOk();

    $run = OmrDiagnosticRun::query()->where('public_id', $response->json('context.public_id'))->firstOrFail();
    $projectedTarget = $response->json('context.capture_target');
    if (! is_array($projectedTarget)) {
        throw new RuntimeException('A server-authorized Mock diagnostic capture target is required for OMR camera contention tests.');
    }
    $workbenchId = 'diagnostic:'.$run->public_id;
    $target = [
        'workbenchId' => $workbenchId,
        'clientCaptureId' => (string) ($projectedTarget['client_capture_id'] ?? ''),
        'pageNo' => (int) ($projectedTarget['page_no'] ?? 0),
        'captureRevision' => (int) ($projectedTarget['target_revision'] ?? $run->revision),
        'instanceId' => (string) ($projectedTarget['instance_id'] ?? ''),
        'sheetId' => $projectedTarget['sheet_id'] ?? null,
    ];
    $target['targetKey'] = json_encode([
        $target['workbenchId'],
        $target['clientCaptureId'],
        $target['pageNo'],
        $target['captureRevision'],
        $target['instanceId'],
        $target['sheetId'],
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    return [
        'actor' => $actor,
        'cycle' => $cycle,
        'institution' => $institution,
        'run' => $run,
        'target' => $target,
    ];
}

/** @param array<string,mixed> $base @param array<string,mixed> $identity */
function omrCameraPostgresWorkerInput(array $base, array $identity, string $barrier, string $action): array
{
    return [...$base, ...$identity, 'barrier' => $barrier, 'action' => $action];
}

test('PostgreSQL races competing controller creation and returns a typed conflict to the loser', function (): void {
    assertOmrCameraPostgresAcceptanceTarget();
    $fixture = omrCameraPostgresDiagnosticFixture($this);
    $workbenchId = 'diagnostic:'.$fixture['run']->public_id;
    $barrier = (string) random_int(2_000_000_000, 2_100_000_000);
    DB::select('SELECT pg_advisory_lock(?)', [(int) $barrier]);
    $barrierReached = false;
    $processes = [];
    $identities = [omrCameraPostgresWorkerIdentity('create-a'), omrCameraPostgresWorkerIdentity('create-b')];
    try {
        foreach ($identities as $identity) {
            $processes[] = startOmrCameraPostgresWorker(omrCameraPostgresWorkerInput([
                'actor_id' => (int) $fixture['actor']->id,
                'workbench_id' => $workbenchId,
                'institution_id' => (int) $fixture['institution']->id,
                'cycle_id' => (int) $fixture['cycle']->id,
                'run_id' => (int) $fixture['run']->id,
                'target' => $fixture['target'],
            ], $identity, $barrier, 'create'));
        }
        awaitOmrCameraPostgresWorkers($processes, array_column($identities, 'application_name'));
        $barrierReached = true;
    } finally {
        DB::select('SELECT pg_advisory_unlock(?)', [(int) $barrier]);
        if (! $barrierReached) {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
        }
    }
    $results = finishOmrCameraPostgresWorkers($processes);

    expect(collect($results)->where('ok', true)->count())->toBe(1)
        ->and(collect($results)->where('status', 409)->count())->toBe(1)
        ->and(collect($results)->whereNotIn('status', [409])->where('ok', false)->all())->toBe([])
        ->and(OmrCameraPairing::query()->where('workbench_id', $workbenchId)->whereNull('revoked_at')->count())->toBe(1);

    $current = OmrCameraPairing::query()->where('workbench_id', $workbenchId)->whereNull('revoked_at')->firstOrFail();
    $takeoverBarrier = (string) random_int(2_000_000_000, 2_100_000_000);
    DB::select('SELECT pg_advisory_lock(?)', [(int) $takeoverBarrier]);
    $takeoverProcesses = [];
    $takeoverIdentities = [omrCameraPostgresWorkerIdentity('takeover-a'), omrCameraPostgresWorkerIdentity('takeover-b')];
    $takeoverBarrierReached = false;
    try {
        foreach ($takeoverIdentities as $identity) {
            $takeoverProcesses[] = startOmrCameraPostgresWorker(omrCameraPostgresWorkerInput([
                'actor_id' => (int) $fixture['actor']->id,
                'workbench_id' => $workbenchId,
                'institution_id' => (int) $fixture['institution']->id,
                'cycle_id' => (int) $fixture['cycle']->id,
                'run_id' => (int) $fixture['run']->id,
                'target' => $fixture['target'],
                'takeover' => true,
                'expected_generation' => (int) $current->controller_generation,
            ], $identity, $takeoverBarrier, 'create'));
        }
        awaitOmrCameraPostgresWorkers($takeoverProcesses, array_column($takeoverIdentities, 'application_name'));
        $takeoverBarrierReached = true;
    } finally {
        DB::select('SELECT pg_advisory_unlock(?)', [(int) $takeoverBarrier]);
        if (! $takeoverBarrierReached) {
            foreach ($takeoverProcesses as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
        }
    }
    $takeoverResults = finishOmrCameraPostgresWorkers($takeoverProcesses);
    expect(collect($takeoverResults)->where('ok', true)->count())->toBe(1)
        ->and(collect($takeoverResults)->where('status', 409)->count())->toBe(1)
        ->and(OmrCameraPairing::query()->where('workbench_id', $workbenchId)->whereNull('revoked_at')->count())->toBe(1)
        ->and((int) OmrCameraPairing::query()->where('workbench_id', $workbenchId)->whereNull('revoked_at')->value('controller_generation'))->toBe((int) $current->controller_generation + 1);
})->group('postgres-omr-camera-contention');

test('PostgreSQL races exact command and acquired ACK replay without changing the durable frame', function (): void {
    assertOmrCameraPostgresAcceptanceTarget();
    $fixture = omrCameraPostgresDiagnosticFixture($this);
    $workbenchId = 'diagnostic:'.$fixture['run']->public_id;
    $pairResult = app(OmrCameraProtocol::class)->create($fixture['actor'], $workbenchId,
        (int) $fixture['institution']->id, (int) $fixture['cycle']->id, null, (int) $fixture['run']->id, $fixture['target']);
    $pairing = $pairResult['pairing'];
    app(OmrCameraProtocol::class)->join($fixture['actor'], $pairResult['pair_code']);
    $pairing = $pairing->fresh();
    $commandId = (string) Str::uuid();
    $operationId = (string) Str::uuid();
    $commandPayload = [
        'target' => $fixture['target'],
        'config_revision' => (int) $pairing->config_revision,
        'source_generation' => (int) $pairing->source_generation,
    ];
    $acquiredBytes = physicalChallengePng();
    $dimensions = getimagesizefromstring($acquiredBytes);
    if ($dimensions === false) {
        throw new RuntimeException('The physical challenge image must decode for the camera ACK test.');
    }
    $ack = [
        'command_id' => $commandId,
        'input_hash' => hash('sha256', $acquiredBytes),
        'byte_count' => strlen($acquiredBytes),
        'mime' => 'image/png',
        'width' => $dimensions[0],
        'height' => $dimensions[1],
        'orientation' => 1,
        'target_key' => $fixture['target']['targetKey'],
        'config_revision' => 1,
        'source_generation' => (int) $pairing->source_generation,
    ];
    $barrier = (string) random_int(2_100_000_000, 2_200_000_000);
    DB::select('SELECT pg_advisory_lock(?)', [(int) $barrier]);
    $barrierReached = false;
    $processes = [];
    $identities = [omrCameraPostgresWorkerIdentity('ack-a'), omrCameraPostgresWorkerIdentity('ack-b')];
    try {
        foreach ($identities as $identity) {
            $processes[] = startOmrCameraPostgresWorker(omrCameraPostgresWorkerInput([
                'actor_id' => (int) $fixture['actor']->id,
                'pairing_id' => (int) $pairing->id,
                'kind' => 'CAPTURE',
                'command_id' => $commandId,
                'operation_id' => $operationId,
                'payload' => $commandPayload,
                'ack' => $ack,
            ], $identity, $barrier, 'command_ack'));
        }
        awaitOmrCameraPostgresWorkers($processes, array_column($identities, 'application_name'));
        $barrierReached = true;
    } finally {
        DB::select('SELECT pg_advisory_unlock(?)', [(int) $barrier]);
        if (! $barrierReached) {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
        }
    }
    $results = finishOmrCameraPostgresWorkers($processes);
    $pairing = $pairing->fresh();
    $command = OmrCameraCommand::query()->where('pairing_id', $pairing->id)->where('command_id', $commandId)->first();
    if ($command === null) {
        test()->fail('Workers returned: '.json_encode($results).' but command was not found in pairing '.$pairing->id);
    }
    $frame = OmrCameraFrame::query()->where('command_id', $command->id)->firstOrFail();
    $command->forceFill(['execution_expires_at' => now()->subSecond()])->save();
    $replayedFrame = app(OmrCameraProtocol::class)->acknowledgeAcquired($pairing, $ack);

    expect(collect($results)->where('ok', true)->count())->toBe(2)
        ->and($results[0]['command_id'])->toBe($results[1]['command_id'])
        ->and($results[0]['frame_id'])->toBe($results[1]['frame_id'])
        ->and(OmrCameraCommand::query()->where('pairing_id', $pairing->id)->where('command_id', $commandId)->count())->toBe(1)
        ->and(OmrCameraFrame::query()->where('command_id', $command->id)->count())->toBe(1)
        ->and($frame->input_sha256)->toBe($ack['input_hash'])
        ->and($frame->byte_count)->toBe($ack['byte_count'])
        ->and($replayedFrame->id)->toBe($frame->id)
        ->and($frame->state)->toBe(OmrCameraFrame::STATE_ACQUIRED);
    $changedAck = [...$ack, 'input_hash' => hash('sha256', 'changed-image')];
    $ackConflictStatus = null;
    try {
        app(OmrCameraProtocol::class)->acknowledgeAcquired($pairing, $changedAck);
    } catch (HttpExceptionInterface $exception) {
        $ackConflictStatus = $exception->getStatusCode();
    }
    $commandConflictStatus = null;
    try {
        app(OmrCameraProtocol::class)->command(
            $pairing,
            'CAPTURE',
            $commandId,
            $operationId,
            ['target' => ['targetKey' => 'different-target']],
        );
    } catch (HttpExceptionInterface $exception) {
        $commandConflictStatus = $exception->getStatusCode();
    }
    expect($ackConflictStatus)->toBe(409)
        ->and($commandConflictStatus)->toBe(409);
})->group('postgres-omr-camera-contention');

test('PostgreSQL fences a recovered finalization lease and commits one real diagnostic receipt', function (): void {
    assertOmrCameraPostgresAcceptanceTarget();
    $fixture = omrCameraPostgresDiagnosticFixture($this);
    $run = $fixture['run'];
    $actor = $fixture['actor'];
    $target = $fixture['target'];
    $workbenchId = 'diagnostic:'.$run->public_id;
    $workbenchKey = hash('sha256', $workbenchId);
    $workbench = ['diagnostic_run_id' => (int) $run->id];
    $bytes = physicalChallengePng();
    $operationId = (string) Str::uuid();
    $idempotencyKey = (string) Str::uuid();
    $payloadFingerprint = hash('sha256', $bytes);
    $cameraFinalization = app(OmrCameraFrameFinalization::class);
    $oldReservation = $cameraFinalization->reserveOperation(
        $actor, $workbenchKey, $workbench, $target, $operationId, $idempotencyKey,
        $payloadFingerprint, 'auto_document_quad', null,
    );

    $barrier = (string) random_int(2_200_000_000, 2_300_000_000);
    DB::select('SELECT pg_advisory_lock(?)', [(int) $barrier]);
    $identity = omrCameraPostgresWorkerIdentity('active-lease');
    $process = startOmrCameraPostgresWorker(omrCameraPostgresWorkerInput([
        'actor_id' => (int) $actor->id,
        'workbench_key' => $workbenchKey,
        'workbench' => $workbench,
        'target' => $target,
        'operation_id' => $operationId,
        'idempotency_key' => $idempotencyKey,
        'payload_fingerprint' => $payloadFingerprint,
        'crop_policy' => 'auto_document_quad',
    ], $identity, $barrier, 'reserve_only'));
    $barrierReached = false;
    try {
        awaitOmrCameraPostgresWorkers([$process], [$identity['application_name']]);
        $barrierReached = true;
    } finally {
        DB::select('SELECT pg_advisory_unlock(?)', [(int) $barrier]);
        if (! $barrierReached && $process->isRunning()) {
            $process->stop(1);
        }
    }
    $contender = finishOmrCameraPostgresWorkers([$process])[0];
    expect($contender['status'])->toBe(409);

    $oldReservation->operation->forceFill(['finalization_lease_expires_at' => now()->subSecond()])->save();
    $recoveryReservation = $cameraFinalization->reserveOperation(
        $actor, $workbenchKey, $workbench, $target, $operationId, $idempotencyKey,
        $payloadFingerprint, 'auto_document_quad', null,
    );
    $result = app(CaptureOmrDiagnosticRun::class)->handle(
        $run,
        $bytes,
        'image/png',
        $target['clientCaptureId'],
        $actor,
        (int) $run->revision,
        $idempotencyKey,
        (int) $target['pageNo'],
        caseId: $target['instanceId'],
        targetRevision: (int) $target['captureRevision'],
        cropPolicy: 'auto_document_quad',
        operationId: $operationId,
        cameraReservation: $recoveryReservation,
    );
    $acceptedOperation = $recoveryReservation->operation->fresh();
    $domainReceipt = (array) $acceptedOperation->domain_receipt_json;

    $staleIdentity = omrCameraPostgresWorkerIdentity('stale-lease');
    $staleWorker = startOmrCameraPostgresWorker(omrCameraPostgresWorkerInput([
        'actor_id' => (int) $actor->id,
        'operation_record_id' => (int) $acceptedOperation->id,
        'lease_token' => $oldReservation->leaseToken,
        'lease_generation' => $oldReservation->leaseGeneration,
    ], $staleIdentity, (string) random_int(2_300_000_001, 2_400_000_000), 'stale_commit'));
    $staleResult = finishOmrCameraPostgresWorkers([$staleWorker])[0];
    $capture = OmrDiagnosticCapture::query()->whereKey($result['capture']->id)->firstOrFail();
    $domainCommand = $acceptedOperation->fresh();
    $diagnosticReceipt = DB::table('omr_command_receipts')
        ->where('actor_id', $actor->id)
        ->where('command_name', CaptureOmrDiagnosticRun::COMMAND_NAME)
        ->where('aggregate_id', (string) $run->id)
        ->where('idempotency_key', $idempotencyKey)
        ->first();

    expect($recoveryReservation->leaseGeneration)->toBe($oldReservation->leaseGeneration + 1)
        ->and($staleResult['status'])->toBe(409)
        ->and($domainCommand->domain_outcome)->toBe('accepted')
        ->and($domainReceipt['capture_id'])->toBe((int) $capture->id)
        ->and($capture->input_asset_hash)->toBe($payloadFingerprint)
        ->and($diagnosticReceipt?->status)->toBe('succeeded')
        ->and(OmrDiagnosticCapture::query()->where('diagnostic_run_id', $run->id)->count())->toBe(1);
})->group('postgres-omr-camera-contention');
