<?php

use App\Admission\Actions\AcknowledgeAssistedProgramSelection;
use App\Admission\Actions\MarkAdmissionSupportMessageRead;
use App\Admission\Actions\SendAdmissionSupportMessage;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Admission\Queries\CaseConversationQuery;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\RecordThread;
use App\Models\Admission\RecordThreadMessage;
use App\Models\Admission\RecordThreadRead;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

require_once __DIR__.'/../../Support/CaseMessagingHelpers.php';

/**
 * This suite is deliberately restricted to the disposable database named by
 * phpunit.messaging-postgres.xml. It must never run against the Compose app DB.
 */
function assertCaseConversationRaceDatabase(): void
{
    $connection = DB::connection();
    $database = (string) $connection->getDatabaseName();

    if ($connection->getDriverName() !== 'pgsql') {
        test()->fail('PostgreSQL acceptance requires phpunit.messaging-postgres.xml; active driver is '.$connection->getDriverName().'.');
    }

    if ($database !== 'nexiam_messaging_acceptance' || $database === 'nexiam') {
        test()->fail('Refusing case-conversation race test on database "'.$database.'"; use the disposable nexiam_messaging_acceptance database only.');
    }
}

/**
 * @return array{attempt: AdmissionAttempt, applicant: User, guidance: User, program: Program, thread: RecordThread, ph: User}
 */
function caseConversationRaceFixture(TestCase $test, string $email): array
{
    [$attempt, $applicant, $guidance] = buildMessagingReadyAttempt($test, $email);
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $program->code, [
        ['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'pg-race-pool-'.Str::uuid());
    app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'pg-race-selection-'.Str::uuid());
    $ph = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $thread = app(SendAdmissionSupportMessage::class)->openInternalThread(
        $attempt,
        $program->id,
        'PostgreSQL race acceptance',
        'Fixture opening message',
        $guidance,
        (string) Str::uuid(),
    );

    return compact('attempt', 'applicant', 'guidance', 'program', 'thread', 'ph');
}

/**
 * Start an independent PHP process that invokes the ordinary send Action.
 * The caller owns synchronization and validates persisted consumer state.
 *
 * @param  array{id:int,user_id:int,body:string,key:string,barrier:int|null,application_name:string}  $input
 */
function startCaseConversationSendProcess(array $input): Process
{
    $worker = <<<'PHP'
try {
    foreach ([
        'NEXIAM_TEST_PIN_SQLITE' => '0',
        'DB_CONNECTION' => 'pgsql',
        'DB_DATABASE' => 'nexiam_messaging_acceptance',
        'DB_HOST' => 'postgres',
        'DB_PORT' => '5432',
        'DB_USERNAME' => 'nexiam',
        'DB_PASSWORD' => 'nexiam-local-only',
        'APP_CONFIG_CACHE' => '/tmp/nexiam-messaging-postgres-acceptance-config.php',
    ] as $key => $val) {
        if (getenv($key) === false || getenv($key) === '') {
            putenv($key.'='.$val);
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }

    require getcwd().'/vendor/autoload.php';
    $app = require getcwd().'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $raw = getenv('NEXIAM_CASE_CONVERSATION_RACE_INPUT');
    if (! $raw && isset($argv[1]) && $argv[1] !== '') {
        $raw = $argv[1];
    } elseif (! $raw && isset($argv[0]) && $argv[0] !== '') {
        $raw = $argv[0];
    }
    $input = json_decode(base64_decode((string) $raw, true), true, 512, JSON_THROW_ON_ERROR);

    $connection = Illuminate\Support\Facades\DB::connection();
    if ($connection->getDriverName() !== 'pgsql' || $connection->getDatabaseName() !== 'nexiam_messaging_acceptance') {
        throw new RuntimeException('Refusing worker Action outside nexiam_messaging_acceptance PostgreSQL.');
    }
    Illuminate\Support\Facades\DB::select('select set_config(?, ?, false)', ['application_name', $input['application_name']]);
    if ($input['barrier'] !== null) {
        Illuminate\Support\Facades\DB::select('select pg_advisory_lock_shared(?)', [$input['barrier']]);
        fwrite(STDOUT, "READY\n");
        fflush(STDOUT);
    }
    $message = app(App\Admission\Actions\SendAdmissionSupportMessage::class)->handle(
        App\Models\Admission\RecordThread::query()->findOrFail($input['id']),
        App\Models\User::query()->findOrFail($input['user_id']),
        $input['body'],
        $input['key'],
    );
    fwrite(STDOUT, json_encode(['message_id' => (string) $message->id, 'sequence' => (string) $message->sequence], JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode(['class' => $exception::class, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR)."\n");
    exit(1);
}
PHP;

    $encodedInput = base64_encode(json_encode($input, JSON_THROW_ON_ERROR));

    $process = new Process([
        PHP_BINARY,
        '-r',
        $worker,
        $encodedInput,
    ], base_path(), [
        'NEXIAM_TEST_PIN_SQLITE' => '0',
        'DB_CONNECTION' => 'pgsql',
        'DB_DATABASE' => 'nexiam_messaging_acceptance',
        'DB_HOST' => 'postgres',
        'DB_PORT' => '5432',
        'DB_USERNAME' => 'nexiam',
        'DB_PASSWORD' => 'nexiam-local-only',
        'APP_CONFIG_CACHE' => '/tmp/nexiam-messaging-postgres-acceptance-config.php',
        'NEXIAM_CASE_CONVERSATION_RACE_INPUT' => $encodedInput,
    ]);
    $process->setTimeout(20);
    $process->start();

    return $process;
}

function spawnCaseConversationSendProcess(array $input): Process
{
    return startCaseConversationSendProcess($input);
}

/**
 * Waits on observable worker output with a fixed deadline; elapsed time alone
 * is never treated as evidence that a database race reached its intended state.
 *
 * @param  list<Process>  $processes
 * @return list<array{message_id:string,sequence:string}>
 */
function finishCaseConversationSendProcesses(array $processes): array
{
    $deadline = microtime(true) + 20;
    $results = [];

    do {
        $ready = true;
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $ready = false;
            }
        }
        if ($ready) {
            break;
        }
        usleep(10_000);
    } while (microtime(true) < $deadline);

    foreach ($processes as $process) {
        if ($process->isRunning()) {
            $process->stop(1);
            test()->fail('PostgreSQL message worker exceeded the 20-second bounded wait. stderr: '.$process->getErrorOutput());
        }
        if (! $process->isSuccessful()) {
            test()->fail('PostgreSQL message worker failed ('.$process->getExitCode().'): '.$process->getErrorOutput());
        }
        $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));
        $result = json_decode((string) end($lines), true, 512, JSON_THROW_ON_ERROR);
        if (! isset($result['message_id'], $result['sequence'])) {
            test()->fail('PostgreSQL message worker returned no committed message receipt: '.$process->getOutput());
        }
        $results[] = $result;
    }

    return $results;
}

beforeEach(function (): void {
    assertCaseConversationRaceDatabase();
});

test('independent PostgreSQL senders serialize duplicate receipts and distinct sequences', function (): void {
    $fixture = caseConversationRaceFixture($this, 'case-pg-concurrent-'.Str::uuid().'@example.test');
    $thread = $fixture['thread'];
    $guidance = $fixture['guidance'];
    $attempt = $fixture['attempt'];

    // RefreshDatabase normally keeps this fixture in the test transaction.
    // Commit it explicitly so independently bootstrapped worker connections
    // can observe it; this disposable database is the only permitted target.
    DB::commit();

    $barrier = random_int(1, 2_000_000_000);
    $lockConnection = DB::connection()->getPdo();
    $lockConnection->prepare('SELECT pg_advisory_lock(?)')->execute([$barrier]);
    $sameKey = (string) Str::uuid();
    $first = startCaseConversationSendProcess([
        'id' => $thread->id, 'user_id' => $guidance->id, 'body' => 'same operation', 'key' => $sameKey, 'barrier' => $barrier,
        'application_name' => 'case-msg-race-'.$barrier.'-a',
    ]);
    $second = startCaseConversationSendProcess([
        'id' => $thread->id, 'user_id' => $guidance->id, 'body' => 'same operation', 'key' => $sameKey, 'barrier' => $barrier,
        'application_name' => 'case-msg-race-'.$barrier.'-b',
    ]);

    // PostgreSQL reports each worker waiting on the same advisory barrier. Do
    // not release until both independent server sessions have reached it.
    $deadline = microtime(true) + 10;
    do {
        $waiting = (int) DB::scalar("select count(*) from pg_stat_activity where datname = current_database() and application_name like 'case-msg-race-{$barrier}-%' and wait_event = 'advisory'");
        if ($waiting >= 2) {
            break;
        }
        usleep(10_000);
    } while (microtime(true) < $deadline);
    if ($waiting < 2) {
        $lockConnection->prepare('SELECT pg_advisory_unlock(?)')->execute([$barrier]);
        test()->fail('Both duplicate-send processes did not reach the PostgreSQL advisory barrier within 10 seconds.');
    }
    $lockConnection->prepare('SELECT pg_advisory_unlock(?)')->execute([$barrier]);
    $duplicates = finishCaseConversationSendProcesses([$first, $second]);

    expect($duplicates[0]['message_id'])->toBe($duplicates[1]['message_id'])
        ->and($duplicates[0]['sequence'])->toBe('2')
        ->and($duplicates[1]['sequence'])->toBe('2')
        ->and(RecordThreadMessage::query()->where('record_thread_id', $thread->id)->count())->toBe(2)
        ->and((int) $thread->fresh()->latest_message_sequence)->toBe(2);
    $messageId = $duplicates[0]['message_id'];
    $receiptKey = $guidance->id.':record.thread.message:thread:'.$thread->id.':'.$sameKey;
    $receiptPayload = json_decode((string) DB::table('operations')->where('idempotency_key', $receiptKey)->value('payload'), true, 512, JSON_THROW_ON_ERROR);
    $noticeEventId = hash('sha256', 'case_message:'.$messageId.':2');
    expect((string) ($receiptPayload['receipt_message_id'] ?? ''))->toBe($messageId)
        ->and(DB::table('audit_events')->where('event_type', 'thread.message_sent')->where('payload->message_id', $messageId)->count())->toBe(1)
        ->and(NotificationDelivery::query()->where('event_id', $noticeEventId)->where('event_type', 'admission.case_message_sent.v1')->where('channel', NotificationDelivery::CHANNEL_IN_APP)->exists())->toBeTrue();

    $orderedBarrier = random_int(2_000_001_000, 2_100_000_000);
    $lockConnection->prepare('SELECT pg_advisory_lock(?)')->execute([$orderedBarrier]);
    $firstOrdered = startCaseConversationSendProcess([
        'id' => $thread->id, 'user_id' => $guidance->id, 'body' => 'ordered one', 'key' => (string) Str::uuid(), 'barrier' => $orderedBarrier,
        'application_name' => 'case-msg-race-'.$orderedBarrier.'-a',
    ]);
    $secondOrdered = startCaseConversationSendProcess([
        'id' => $thread->id, 'user_id' => $guidance->id, 'body' => 'ordered two', 'key' => (string) Str::uuid(), 'barrier' => $orderedBarrier,
        'application_name' => 'case-msg-race-'.$orderedBarrier.'-b',
    ]);
    $deadline = microtime(true) + 10;
    do {
        $waiting = (int) DB::scalar("select count(*) from pg_stat_activity where datname = current_database() and application_name like 'case-msg-race-{$orderedBarrier}-%' and wait_event = 'advisory'");
        if ($waiting >= 2) {
            break;
        }
        usleep(10_000);
    } while (microtime(true) < $deadline);
    if ($waiting < 2) {
        $lockConnection->prepare('SELECT pg_advisory_unlock(?)')->execute([$orderedBarrier]);
        test()->fail('Both distinct-send processes did not reach the PostgreSQL advisory barrier within 10 seconds.');
    }
    $lockConnection->prepare('SELECT pg_advisory_unlock(?)')->execute([$orderedBarrier]);
    $ordered = finishCaseConversationSendProcesses([$firstOrdered, $secondOrdered]);

    $rows = RecordThreadMessage::query()->where('record_thread_id', $thread->id)->orderBy('sequence')->get();
    expect($ordered)->toHaveCount(2)
        ->and($rows->pluck('sequence')->map(fn ($sequence): string => (string) $sequence)->all())->toBe(['1', '2', '3', '4'])
        ->and($rows->pluck('body')->all())->toContain('ordered one', 'ordered two');
    foreach ($ordered as $index => $result) {
        $message = $rows->firstWhere('sequence', (int) $result['sequence']);
        expect($message)->not->toBeNull()
            ->and((string) $message->id)->toBe((string) $result['message_id'])
            ->and($message->body)->toBe($index === 0 ? 'ordered one' : 'ordered two');
    }
    expect((int) $thread->fresh()->latest_message_sequence)->toBe(4)
        ->and((int) $attempt->fresh()->id)->toBe((int) $thread->admission_attempt_id);
})->group('postgres-messaging');

test('a read observation at M leaves the concurrently committed M plus one unread', function (): void {
    $fixture = caseConversationRaceFixture($this, 'case-pg-observed-read-'.Str::uuid().'@example.test');
    $thread = $fixture['thread'];
    $sender = $fixture['guidance'];
    $reader = $fixture['ph'];
    $action = app(SendAdmissionSupportMessage::class);
    $atM = $action->handle($thread, $sender, 'visible at observation M', (string) Str::uuid());
    DB::commit();

    $snapshot = app(CaseConversationQuery::class)->forThread($thread, $reader);
    $observedSequence = (string) $snapshot['latest_sequence'];
    $afterM = startCaseConversationSendProcess([
        'id' => $thread->id,
        'user_id' => $sender->id,
        'body' => 'arrived after observation',
        'key' => (string) Str::uuid(),
        'barrier' => null,
        'application_name' => 'case-msg-observed-read-'.Str::uuid(),
    ]);
    finishCaseConversationSendProcesses([$afterM]);

    app(MarkAdmissionSupportMessageRead::class)->handle(
        $thread,
        $reader,
        $observedSequence,
        (string) $snapshot['observation_token'],
    );

    $readSequence = (int) RecordThreadRead::query()
        ->where('record_thread_id', $thread->id)
        ->where('user_id', $reader->id)
        ->value('last_read_sequence');
    expect((int) $atM->sequence)->toBe((int) $observedSequence)
        ->and((int) $thread->fresh()->latest_message_sequence)->toBe((int) $observedSequence + 1)
        ->and($readSequence)->toBe((int) $observedSequence)
        ->and((int) $thread->fresh()->latest_message_sequence - $readSequence)->toBe(1);
})->group('postgres-messaging');

test('a send queued behind an actual enrollment stamp is denied without mutating the root', function (): void {
    $fixture = caseConversationRaceFixture($this, 'case-pg-stamp-race-'.Str::uuid().'@example.test');
    $thread = $fixture['thread'];
    $attempt = $fixture['attempt'];
    $sender = $fixture['guidance'];
    DB::commit();

    $connection = DB::connection()->getPdo();
    $connection->beginTransaction();
    $connection->prepare('SELECT id FROM admission_attempts WHERE id = ? FOR UPDATE')->execute([$attempt->id]);
    $workerName = 'case-msg-stamp-race-'.Str::uuid();
    $process = startCaseConversationSendProcess([
        'id' => $thread->id,
        'user_id' => $sender->id,
        'body' => 'must lose to the enrollment stamp',
        'key' => (string) Str::uuid(),
        'barrier' => null,
        'application_name' => $workerName,
    ]);

    $deadline = microtime(true) + 10;
    do {
        if (! $process->isRunning()) {
            $connection->rollBack();
            test()->fail('Worker process exited prematurely: '.$process->getErrorOutput().' stdout: '.$process->getOutput());
        }
        DB::statement('SELECT pg_stat_clear_snapshot()');
        $waiting = (int) DB::scalar(
            'SELECT count(*) FROM pg_stat_activity WHERE datname = current_database() AND application_name = ? AND (wait_event_type = ? OR wait_event IN (?, ?))',
            [$workerName, 'Lock', 'transactionid', 'tuple'],
        );
        if ($waiting === 0) {
            $waiting = (int) DB::scalar(
                'SELECT count(*) FROM pg_stat_activity a JOIN pg_locks l ON a.pid = l.pid WHERE a.datname = current_database() AND a.application_name = ? AND NOT l.granted',
                [$workerName],
            );
        }
        if ($waiting >= 1) {
            break;
        }
        usleep(10_000);
    } while (microtime(true) < $deadline);
    if ($waiting < 1) {
        $connection->rollBack();
        $process->stop(1);
        test()->fail('The independent send process did not block on the locked attempt before enrollment-stamp commit.');
    }

    DB::table('admission_attempt_enrollment_stamps')->insert([
        'admission_attempt_id' => $attempt->id,
        'stamp_type' => 'enrolled',
        'stamped_by_user_id' => $sender->id,
        'reason' => 'PostgreSQL race cutoff acceptance',
        'version' => 1,
        'created_at' => now(),
    ]);
    $connection->commit();

    $deadline = microtime(true) + 20;
    while ($process->isRunning() && microtime(true) < $deadline) {
        usleep(10_000);
    }
    if ($process->isRunning()) {
        $process->stop(1);
        test()->fail('The send process did not finish after enrollment-stamp commit.');
    }
    $failure = json_decode(trim($process->getErrorOutput()), true, 512, JSON_THROW_ON_ERROR);
    expect($process->isSuccessful())->toBeFalse()
        ->and($failure['class'] ?? null)->toBe(RuntimeException::class)
        ->and($failure['message'] ?? '')->toContain('not authorized')
        ->and((int) $thread->fresh()->latest_message_sequence)->toBe(1)
        ->and(RecordThreadMessage::query()->where('record_thread_id', $thread->id)->count())->toBe(1);
})->group('postgres-messaging');

test('audit failure rolls back message sequence receipt and notice intent together', function (): void {
    $fixture = caseConversationRaceFixture($this, 'case-pg-audit-rollback-'.Str::uuid().'@example.test');
    $thread = $fixture['thread'];
    $attempt = $fixture['attempt'];
    $sender = $fixture['guidance'];
    DB::commit();

    $suffix = str_replace('-', '', (string) Str::uuid());
    $function = 'reject_case_message_audit_'.$suffix;
    $trigger = 'reject_case_message_audit_trigger_'.$suffix;
    DB::unprepared("CREATE FUNCTION {$function}() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF NEW.event_type = 'thread.message_sent' THEN RAISE EXCEPTION 'case message audit rejected'; END IF; RETURN NEW; END; \$\$");
    DB::unprepared("CREATE TRIGGER {$trigger} BEFORE INSERT ON audit_events FOR EACH ROW EXECUTE FUNCTION {$function}()");

    $key = (string) Str::uuid();
    $threadCount = RecordThread::query()->count();
    $operationKey = $sender->id.':record.thread.open_internal:attempt:'.$attempt->id.':program:'.$fixture['program']->id.':'.$key;
    $deliveryCount = NotificationDelivery::query()->count();
    try {
        expect(fn () => app(SendAdmissionSupportMessage::class)->openInternalThread(
            $attempt,
            $fixture['program']->id,
            'Rollback root and first message',
            'audit failure must roll this back',
            $sender,
            $key,
        ))->toThrow(QueryException::class, 'case message audit rejected');
    } finally {
        DB::unprepared("DROP TRIGGER IF EXISTS {$trigger} ON audit_events");
        DB::unprepared("DROP FUNCTION IF EXISTS {$function}()");
    }

    expect((int) $thread->fresh()->latest_message_sequence)->toBe(1)
        ->and(RecordThreadMessage::query()->where('record_thread_id', $thread->id)->count())->toBe(1)
        ->and(RecordThread::query()->count())->toBe($threadCount)
        ->and(DB::table('operations')->where('idempotency_key', $operationKey)->exists())->toBeFalse()
        ->and(NotificationDelivery::query()->count())->toBe($deliveryCount);
})->group('postgres-messaging');
