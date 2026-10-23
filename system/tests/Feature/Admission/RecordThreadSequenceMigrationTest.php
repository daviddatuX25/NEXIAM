<?php

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\RecordThread;
use App\Models\Admission\RecordThreadMessage;
use App\Models\Admission\RecordThreadRead;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function recordThreadSequenceMigration(): Migration
{
    return require database_path('migrations/2026_10_05_120000_add_sequence_and_read_markers_to_record_threads.php');
}
function assertRecordThreadQueryRejected(Closure $operation): void
{
    static $savepoint = 0;
    $name = 'sp_record_thread_reject_'.(++$savepoint);
    DB::statement('SAVEPOINT '.$name);
    $threw = false;
    try {
        $operation();
    } catch (QueryException $exception) {
        $threw = true;
        DB::statement('ROLLBACK TO SAVEPOINT '.$name);
        DB::statement('RELEASE SAVEPOINT '.$name);
        expect($exception)->toBeInstanceOf(QueryException::class);
    } catch (Throwable $exception) {
        DB::statement('ROLLBACK TO SAVEPOINT '.$name);
        DB::statement('RELEASE SAVEPOINT '.$name);
        throw $exception;
    }

    if (! $threw) {
        DB::statement('RELEASE SAVEPOINT '.$name);
        test()->fail('Expected QueryException was not thrown.');
    }
}

function createMigrationTestFixture(): array
{
    seedE2Foundation();

    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $identity = ApplicantIdentity::query()->create([
        'institution_id' => $institution->id,
        'normalized_key' => 'SEQ-MIG-APPLICANT-'.uniqid('', true),
        'first_name' => 'Sequence',
        'last_name' => 'MigrationApplicant',
        'birthdate' => '2001-01-01',
        'sex' => 'female',
    ]);

    $attempt = AdmissionAttempt::query()->create([
        'applicant_identity_id' => $identity->id,
        'institution_id' => $institution->id,
        'academic_cycle_id' => $cycle->id,
        'reference_code' => 'SEQ-MIG-ATTEMPT-'.uniqid('', true),
        'status' => AdmissionAttempt::STATUS_ACCEPTED,
    ]);

    return compact('institution', 'cycle', 'program', 'user', 'attempt');
}

test('migration deterministically backfills sequence per thread matching created_at and id order and sets latest_message_sequence', function (): void {
    $fixture = createMigrationTestFixture();
    $migration = recordThreadSequenceMigration();

    // Revert migration so we verify backfill from raw pre-migration table state
    $migration->down();

    try {
        // Create Thread 1 (has 3 messages out of created_at insertion order)
        $thread1Id = DB::table('record_threads')->insertGetId([
            'admission_attempt_id' => $fixture['attempt']->id,
            'program_id' => $fixture['program']->id,
            'thread_type' => RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
            'subject' => 'Thread 1 Backfill Test',
            'status' => RecordThread::STATUS_OPEN,
            'created_by_user_id' => $fixture['user']->id,
            'created_at' => Carbon::parse('2026-10-01 09:00:00'),
            'updated_at' => Carbon::parse('2026-10-01 09:00:00'),
        ]);

        // Create Thread 2 (has 2 messages with identical created_at to verify id tie-breaker)
        $thread2Id = DB::table('record_threads')->insertGetId([
            'admission_attempt_id' => $fixture['attempt']->id,
            'program_id' => $fixture['program']->id,
            'thread_type' => RecordThread::TYPE_APPLICANT_SUPPORT,
            'subject' => 'Thread 2 Backfill Test',
            'status' => RecordThread::STATUS_OPEN,
            'created_by_user_id' => $fixture['user']->id,
            'created_at' => Carbon::parse('2026-10-01 09:30:00'),
            'updated_at' => Carbon::parse('2026-10-01 09:30:00'),
        ]);

        // Create Thread 3 (has 0 messages)
        $thread3Id = DB::table('record_threads')->insertGetId([
            'admission_attempt_id' => $fixture['attempt']->id,
            'program_id' => $fixture['program']->id,
            'thread_type' => RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
            'subject' => 'Thread 3 Empty',
            'status' => RecordThread::STATUS_OPEN,
            'created_by_user_id' => $fixture['user']->id,
            'created_at' => Carbon::parse('2026-10-01 10:00:00'),
            'updated_at' => Carbon::parse('2026-10-01 10:00:00'),
        ]);

        // Thread 1 messages inserted out of created_at order:
        // First message: created_at 10:00:00 -> expected sequence 1
        $msg1AId = DB::table('record_thread_messages')->insertGetId([
            'record_thread_id' => $thread1Id,
            'sender_user_id' => $fixture['user']->id,
            'body' => 'Thread 1 Msg A',
            'created_at' => Carbon::parse('2026-10-01 10:00:00'),
        ]);

        // Third message inserted before second: created_at 12:00:00 -> expected sequence 3
        $msg1CId = DB::table('record_thread_messages')->insertGetId([
            'record_thread_id' => $thread1Id,
            'sender_user_id' => $fixture['user']->id,
            'body' => 'Thread 1 Msg C',
            'created_at' => Carbon::parse('2026-10-01 12:00:00'),
        ]);

        // Second message inserted last: created_at 11:00:00 -> expected sequence 2
        $msg1BId = DB::table('record_thread_messages')->insertGetId([
            'record_thread_id' => $thread1Id,
            'sender_user_id' => $fixture['user']->id,
            'body' => 'Thread 1 Msg B',
            'created_at' => Carbon::parse('2026-10-01 11:00:00'),
        ]);

        // Thread 2 messages: identical timestamps, expected sequence sorted by id asc
        $msg2AId = DB::table('record_thread_messages')->insertGetId([
            'record_thread_id' => $thread2Id,
            'sender_user_id' => $fixture['user']->id,
            'body' => 'Thread 2 Msg A',
            'created_at' => Carbon::parse('2026-10-02 08:00:00'),
        ]);

        $msg2BId = DB::table('record_thread_messages')->insertGetId([
            'record_thread_id' => $thread2Id,
            'sender_user_id' => $fixture['user']->id,
            'body' => 'Thread 2 Msg B',
            'created_at' => Carbon::parse('2026-10-02 08:00:00'),
        ]);

        // Seed an existing read record prior to migration
        $readId = DB::table('record_thread_reads')->insertGetId([
            'record_thread_id' => $thread1Id,
            'user_id' => $fixture['user']->id,
            'last_read_at' => Carbon::parse('2026-10-01 10:30:00'),
        ]);

        $receiptOperationId = (string) Str::uuid();
        $receiptOperationKey = 'sequence-migration-receipt-'.Str::uuid();
        DB::table('operations')->insert([
            'id' => $receiptOperationId,
            'idempotency_key' => $receiptOperationKey,
            'type' => 'record.thread.message',
            'status' => 'succeeded',
            'attempts' => 0,
            'payload' => json_encode(['receipt_message_id' => $msg1CId], JSON_THROW_ON_ERROR),
            'finished_at' => Carbon::parse('2026-10-02 09:00:00'),
            'created_at' => Carbon::parse('2026-10-02 09:00:00'),
            'updated_at' => Carbon::parse('2026-10-02 09:00:00'),
        ]);

        // Execute migration up to trigger backfill
        $migration->up();

        // 1. Assert sequences on Thread 1 messages follow (created_at ASC, id ASC)
        $msg1A = DB::table('record_thread_messages')->find($msg1AId);
        $msg1B = DB::table('record_thread_messages')->find($msg1BId);
        $msg1C = DB::table('record_thread_messages')->find($msg1CId);

        expect($msg1A->sequence)->toBe(1)
            ->and($msg1B->sequence)->toBe(2)
            ->and($msg1C->sequence)->toBe(3);
        expect($msg1A->id)->toBe($msg1AId)
            ->and($msg1A->body)->toBe('Thread 1 Msg A')
            ->and($msg1A->sender_user_id)->toBe($fixture['user']->id)
            ->and($msg1B->id)->toBe($msg1BId)
            ->and($msg1B->body)->toBe('Thread 1 Msg B')
            ->and($msg1B->sender_user_id)->toBe($fixture['user']->id)
            ->and($msg1C->id)->toBe($msg1CId)
            ->and($msg1C->body)->toBe('Thread 1 Msg C')
            ->and($msg1C->sender_user_id)->toBe($fixture['user']->id);

        // 2. Assert Thread 1 latest_message_sequence matches max sequence (3)
        $thread1 = DB::table('record_threads')->find($thread1Id);
        expect($thread1->latest_message_sequence)->toBe(3);
        expect($thread1->id)->toBe($thread1Id)
            ->and($thread1->created_by_user_id)->toBe($fixture['user']->id);

        // 3. Assert sequences on Thread 2 messages follow id tie-breaker
        $msg2A = DB::table('record_thread_messages')->find($msg2AId);
        $msg2B = DB::table('record_thread_messages')->find($msg2BId);

        expect($msg2A->sequence)->toBe(1)
            ->and($msg2B->sequence)->toBe(2);
        expect($msg2A->id)->toBe($msg2AId)
            ->and($msg2A->body)->toBe('Thread 2 Msg A')
            ->and($msg2A->sender_user_id)->toBe($fixture['user']->id)
            ->and($msg2B->id)->toBe($msg2BId)
            ->and($msg2B->body)->toBe('Thread 2 Msg B')
            ->and($msg2B->sender_user_id)->toBe($fixture['user']->id);

        // 4. Assert Thread 2 latest_message_sequence matches max sequence (2)
        $thread2 = DB::table('record_threads')->find($thread2Id);
        expect($thread2->latest_message_sequence)->toBe(2);
        expect($thread2->id)->toBe($thread2Id)
            ->and($thread2->created_by_user_id)->toBe($fixture['user']->id);

        // 5. Assert Thread 3 (no messages) latest_message_sequence remains 0
        $thread3 = DB::table('record_threads')->find($thread3Id);
        expect($thread3->latest_message_sequence)->toBe(0);

        // 6. Assert existing read row last_read_sequence defaults to 0
        $read = DB::table('record_thread_reads')->find($readId);
        expect($read->last_read_sequence)->toBe(0);
        expect($read->id)->toBe($readId)
            ->and($read->user_id)->toBe($fixture['user']->id)
            ->and($read->last_read_sequence)->toBe(0);
        $receipt = DB::table('operations')->where('id', $receiptOperationId)->first();
        expect($receipt->id)->toBe($receiptOperationId)
            ->and($receipt->idempotency_key)->toBe($receiptOperationKey)
            ->and(json_decode($receipt->payload, true, 512, JSON_THROW_ON_ERROR)['receipt_message_id'])->toBe($msg1CId);
    } finally {
        $migration->up();
    }
});

test('last_read_sequence defaults to 0 on reads when omitted', function (): void {
    $fixture = createMigrationTestFixture();
    $migration = recordThreadSequenceMigration();
    $migration->up();

    $threadId = DB::table('record_threads')->insertGetId([
        'admission_attempt_id' => $fixture['attempt']->id,
        'program_id' => $fixture['program']->id,
        'thread_type' => RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
        'subject' => 'Default Read Sequence Test',
        'status' => RecordThread::STATUS_OPEN,
        'created_by_user_id' => $fixture['user']->id,
        'latest_message_sequence' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $readId = DB::table('record_thread_reads')->insertGetId([
        'record_thread_id' => $threadId,
        'user_id' => $fixture['user']->id,
        'last_read_at' => now(),
    ]);

    $read = RecordThreadRead::query()->findOrFail($readId);
    expect($read->last_read_sequence)->toBe(0);
});

test('unique constraint on record_thread_id and sequence prevents duplicate sequences within the same thread', function (): void {
    $fixture = createMigrationTestFixture();
    $migration = recordThreadSequenceMigration();
    $migration->up();

    $thread1Id = DB::table('record_threads')->insertGetId([
        'admission_attempt_id' => $fixture['attempt']->id,
        'program_id' => $fixture['program']->id,
        'thread_type' => RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
        'subject' => 'Unique Constraint Thread 1',
        'status' => RecordThread::STATUS_OPEN,
        'created_by_user_id' => $fixture['user']->id,
        'latest_message_sequence' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $thread2Id = DB::table('record_threads')->insertGetId([
        'admission_attempt_id' => $fixture['attempt']->id,
        'program_id' => $fixture['program']->id,
        'thread_type' => RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
        'subject' => 'Unique Constraint Thread 2',
        'status' => RecordThread::STATUS_OPEN,
        'created_by_user_id' => $fixture['user']->id,
        'latest_message_sequence' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Insert sequence 1 in Thread 1
    DB::table('record_thread_messages')->insert([
        'record_thread_id' => $thread1Id,
        'sender_user_id' => $fixture['user']->id,
        'sequence' => 1,
        'body' => 'Message 1 in Thread 1',
        'created_at' => now(),
    ]);

    // Insert sequence 1 in Thread 2 (should succeed because record_thread_id is different)
    $thread2MsgId = DB::table('record_thread_messages')->insertGetId([
        'record_thread_id' => $thread2Id,
        'sender_user_id' => $fixture['user']->id,
        'sequence' => 1,
        'body' => 'Message 1 in Thread 2',
        'created_at' => now(),
    ]);
    expect($thread2MsgId)->toBeGreaterThan(0);

    // Attempt duplicate sequence 1 in Thread 1 (MUST throw QueryException)
    assertRecordThreadQueryRejected(function () use ($thread1Id, $fixture): void {
        DB::table('record_thread_messages')->insert([
            'record_thread_id' => $thread1Id,
            'sender_user_id' => $fixture['user']->id,
            'sequence' => 1,
            'body' => 'Duplicate sequence 1 in Thread 1',
            'created_at' => now(),
        ]);
    });
});

test('migration down drops columns and indexes gracefully and up restores them', function (): void {
    $migration = recordThreadSequenceMigration();

    // Ensure up state
    $migration->up();
    expect(Schema::hasColumn('record_threads', 'latest_message_sequence'))->toBeTrue()
        ->and(Schema::hasColumn('record_thread_messages', 'sequence'))->toBeTrue()
        ->and(Schema::hasColumn('record_thread_reads', 'last_read_sequence'))->toBeTrue()
        ->and(Schema::hasIndex('record_thread_messages', 'record_thread_messages_thread_seq_unique'))->toBeTrue()
        ->and(Schema::hasIndex('record_thread_messages', 'record_thread_messages_thread_seq_index'))->toBeFalse();
    // Revert via down
    $migration->down();
    expect(Schema::hasColumn('record_threads', 'latest_message_sequence'))->toBeFalse()
        ->and(Schema::hasColumn('record_thread_messages', 'sequence'))->toBeFalse()
        ->and(Schema::hasColumn('record_thread_reads', 'last_read_sequence'))->toBeFalse()
        ->and(Schema::hasIndex('record_thread_messages', 'record_thread_messages_thread_seq_unique'))->toBeFalse()
        ->and(Schema::hasIndex('record_thread_messages', 'record_thread_messages_thread_seq_index'))->toBeFalse();

    // Restore via up
    $migration->up();
    expect(Schema::hasColumn('record_threads', 'latest_message_sequence'))->toBeTrue()
        ->and(Schema::hasColumn('record_thread_messages', 'sequence'))->toBeTrue()
        ->and(Schema::hasColumn('record_thread_reads', 'last_read_sequence'))->toBeTrue()
        ->and(Schema::hasIndex('record_thread_messages', 'record_thread_messages_thread_seq_unique'))->toBeTrue()
        ->and(Schema::hasIndex('record_thread_messages', 'record_thread_messages_thread_seq_index'))->toBeFalse();
});

test('compatible rerun preserves assigned sequences, bodies, and read pointers', function (): void {
    $fixture = createMigrationTestFixture();
    $migration = recordThreadSequenceMigration();
    $migration->up();

    $threadId = DB::table('record_threads')->insertGetId([
        'admission_attempt_id' => $fixture['attempt']->id,
        'program_id' => $fixture['program']->id,
        'thread_type' => RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
        'subject' => 'Preserve rerun state',
        'status' => RecordThread::STATUS_OPEN,
        'created_by_user_id' => $fixture['user']->id,
        'latest_message_sequence' => 2,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $firstId = DB::table('record_thread_messages')->insertGetId([
        'record_thread_id' => $threadId,
        'sender_user_id' => $fixture['user']->id,
        'sequence' => 2,
        'body' => 'second sequence, earlier timestamp',
        'created_at' => Carbon::parse('2026-10-01 08:00:00'),
    ]);
    $secondId = DB::table('record_thread_messages')->insertGetId([
        'record_thread_id' => $threadId,
        'sender_user_id' => $fixture['user']->id,
        'sequence' => 1,
        'body' => 'first sequence, later timestamp',
        'created_at' => Carbon::parse('2026-10-01 09:00:00'),
    ]);
    $readId = DB::table('record_thread_reads')->insertGetId([
        'record_thread_id' => $threadId,
        'user_id' => $fixture['user']->id,
        'last_read_at' => Carbon::parse('2026-10-01 10:00:00'),
        'last_read_sequence' => 1,
    ]);

    $migration->up();

    expect(DB::table('record_thread_messages')->find($firstId)->sequence)->toBe(2)
        ->and(DB::table('record_thread_messages')->find($firstId)->body)->toBe('second sequence, earlier timestamp')
        ->and(DB::table('record_thread_messages')->find($secondId)->sequence)->toBe(1)
        ->and(DB::table('record_thread_reads')->find($readId)->last_read_sequence)->toBe(1)
        ->and(DB::table('record_thread_reads')->find($readId)->last_read_at)->toBe('2026-10-01 10:00:00');
});

test('migration rejects partially populated legacy sequence data instead of assigning collisions', function (): void {
    $fixture = createMigrationTestFixture();
    $migration = recordThreadSequenceMigration();
    $migration->down();

    Schema::table('record_threads', function ($table): void {
        $table->unsignedBigInteger('latest_message_sequence')->default(0);
    });
    Schema::table('record_thread_messages', function ($table): void {
        $table->unsignedBigInteger('sequence')->nullable();
    });

    $threadId = DB::table('record_threads')->insertGetId([
        'admission_attempt_id' => $fixture['attempt']->id,
        'program_id' => $fixture['program']->id,
        'thread_type' => RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
        'subject' => 'Partial sequence state',
        'status' => RecordThread::STATUS_OPEN,
        'created_by_user_id' => $fixture['user']->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('record_thread_messages')->insert([
        [
            'record_thread_id' => $threadId,
            'sender_user_id' => $fixture['user']->id,
            'sequence' => 1,
            'body' => 'already numbered',
            'created_at' => now(),
        ],
        [
            'record_thread_id' => $threadId,
            'sender_user_id' => $fixture['user']->id,
            'sequence' => null,
            'body' => 'unassigned',
            'created_at' => now(),
        ],
    ]);

    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, 'sequence data is partially populated');
});

test('database rejects nonpositive messages and negative thread and read counters', function (): void {
    $fixture = createMigrationTestFixture();
    recordThreadSequenceMigration()->up();

    assertRecordThreadQueryRejected(fn () => DB::table('record_threads')->insert([
        'admission_attempt_id' => $fixture['attempt']->id,
        'program_id' => $fixture['program']->id,
        'thread_type' => RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
        'subject' => 'Negative counter',
        'status' => RecordThread::STATUS_OPEN,
        'created_by_user_id' => $fixture['user']->id,
        'latest_message_sequence' => -1,
        'created_at' => now(),
    ]));

    $thread = RecordThread::query()->create([
        'admission_attempt_id' => $fixture['attempt']->id,
        'program_id' => $fixture['program']->id,
        'thread_type' => RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
        'subject' => 'Positive checks',
        'status' => RecordThread::STATUS_OPEN,
        'created_by_user_id' => $fixture['user']->id,
        'latest_message_sequence' => 0,
    ]);

    assertRecordThreadQueryRejected(fn () => DB::table('record_thread_messages')->insert([
        'record_thread_id' => $thread->id,
        'sender_user_id' => $fixture['user']->id,
        'sequence' => 0,
        'body' => 'zero sequence',
        'created_at' => now(),
    ]));

    assertRecordThreadQueryRejected(fn () => DB::table('record_thread_reads')->insert([
        'record_thread_id' => $thread->id,
        'user_id' => $fixture['user']->id,
        'last_read_at' => now(),
        'last_read_sequence' => -1,
    ]));
});

test('migration rejects thread counter mismatch on rerun without renumbering', function (): void {
    $fixture = createMigrationTestFixture();
    $migration = recordThreadSequenceMigration();
    $migration->up();

    $thread = RecordThread::query()->create([
        'admission_attempt_id' => $fixture['attempt']->id,
        'program_id' => $fixture['program']->id,
        'thread_type' => RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
        'subject' => 'Mismatched counter',
        'status' => RecordThread::STATUS_OPEN,
        'created_by_user_id' => $fixture['user']->id,
        'latest_message_sequence' => 0,
    ]);
    $messageId = DB::table('record_thread_messages')->insertGetId([
        'record_thread_id' => $thread->id,
        'sender_user_id' => $fixture['user']->id,
        'sequence' => 3,
        'body' => 'preserve this value',
        'created_at' => now(),
    ]);

    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, 'counter does not match')
        ->and(DB::table('record_thread_messages')->find($messageId)->sequence)->toBe(3);
});

test('models expose sequence attributes as fillable and cast to integer', function (): void {
    $fixture = createMigrationTestFixture();
    $migration = recordThreadSequenceMigration();
    $migration->up();

    $thread = RecordThread::query()->create([
        'admission_attempt_id' => $fixture['attempt']->id,
        'program_id' => $fixture['program']->id,
        'thread_type' => RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
        'subject' => 'Model Casts Test',
        'status' => RecordThread::STATUS_OPEN,
        'created_by_user_id' => $fixture['user']->id,
        'latest_message_sequence' => '7',
    ]);

    expect($thread->latest_message_sequence)->toBe(7)
        ->and(is_int($thread->latest_message_sequence))->toBeTrue();

    $message = RecordThreadMessage::query()->create([
        'record_thread_id' => $thread->id,
        'sender_user_id' => $fixture['user']->id,
        'sequence' => '5',
        'body' => 'Message with string sequence',
        'created_at' => now(),
    ]);

    expect($message->sequence)->toBe(5)
        ->and(is_int($message->sequence))->toBeTrue();

    $read = RecordThreadRead::query()->create([
        'record_thread_id' => $thread->id,
        'user_id' => $fixture['user']->id,
        'last_read_sequence' => '3',
        'last_read_at' => now(),
    ]);

    expect($read->last_read_sequence)->toBe(3)
        ->and(is_int($read->last_read_sequence))->toBeTrue();
});
