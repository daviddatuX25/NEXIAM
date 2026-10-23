<?php

use App\Admission\Actions\AcknowledgeAssistedProgramSelection;
use App\Admission\Actions\OpenApplicantSupportConversation;
use App\Admission\Actions\SendAdmissionSupportMessage;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Admission\Queries\CaseConversationQuery;
use App\Admission\Support\CaseConversationToken;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\RecordThread;
use App\Models\Admission\RecordThreadMessage;
use App\Models\Admission\RecordThreadRead;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

require_once __DIR__.'/../../Support/CaseMessagingHelpers.php';

/**
 * @return array{attempt: AdmissionAttempt, applicant: User, guidance: User, phUser: User, program: Program}
 */
function createDirectCaseConversationQueryFixture(TestCase $test, string $email): array
{
    seedE2Foundation();
    [$attempt, $applicant, $guidance] = buildMessagingReadyAttempt($test, $email);
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $program->code, [
        ['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'query-pool-'.md5($email));
    app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'query-selection-'.md5($email));
    $phUser = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $phUser->staffProfile->update(['program_codes' => [$program->code]]);
    $phUser->refresh();

    session()->start();

    return compact('attempt', 'applicant', 'guidance', 'phUser', 'program');
}

/** @return array{thread: RecordThread, fixture: array} */
function createDirectConversationWithMessages(TestCase $test, string $email, int $count): array
{
    $fixture = createDirectCaseConversationQueryFixture($test, $email);
    $thread = app(OpenApplicantSupportConversation::class)->handle(
        $fixture['attempt'],
        $fixture['program']->id,
        'Bounded query history',
        'Message 1',
        $fixture['phUser'],
        Str::uuid()->toString(),
    );
    $send = app(SendAdmissionSupportMessage::class);

    for ($index = 2; $index <= $count; $index++) {
        $send->handle($thread, $index % 2 === 0 ? $fixture['applicant'] : $fixture['phUser'], "Message {$index}", Str::uuid()->toString());
    }

    return compact('thread', 'fixture');
}

test('query returns only 50 messages per window with ordered, non-skipping overflow and cursor-bound tokens', function (): void {
    ['thread' => $thread, 'fixture' => $fixture] = createDirectConversationWithMessages($this, 'case-query-window@example.test', 105);
    $query = app(CaseConversationQuery::class);

    $latest = $query->forThread($thread, $fixture['phUser']);
    expect($latest['messages'])->toHaveCount(50)
        ->and($latest['messages'][0]['sequence'])->toBe('56')
        ->and($latest['messages'][49]['sequence'])->toBe('105')
        ->and($latest['page']['has_older'])->toBeTrue()
        ->and($latest['page']['has_more_after'])->toBeFalse();
    expect(CaseConversationToken::verify($fixture['phUser'], $thread->id, '105', $latest['observation_token']))->toBeTrue();
    expect(CaseConversationToken::verify($fixture['phUser'], $thread->id, '56', $latest['observation_token']))->toBeFalse();

    $before = $query->forThread($thread, $fixture['phUser'], beforeSequence: '56');
    expect(array_column($before['messages'], 'sequence'))->toBe(array_map('strval', range(6, 55)))
        ->and($before['page']['has_older'])->toBeTrue();

    $after = $query->forThread($thread, $fixture['phUser'], afterSequence: '0');
    expect(array_column($after['messages'], 'sequence'))->toBe(array_map('strval', range(1, 50)))
        ->and($after['page']['has_more_after'])->toBeTrue();
    $finalDelta = $query->forThread($thread, $fixture['phUser'], afterSequence: '100');
    expect(array_column($finalDelta['messages'], 'sequence'))->toBe(array_map('strval', range(101, 105)))
        ->and($finalDelta['page']['has_more_after'])->toBeFalse();

    $drained = $query->forThread($thread, $fixture['phUser'], afterSequence: '50');
    expect(array_column($drained['messages'], 'sequence'))->toBe(array_map('strval', range(51, 100)))
        ->and($drained['page']['has_more_after'])->toBeTrue();

    $empty = $query->forThread($thread, $fixture['phUser'], afterSequence: '105');
    expect($empty['messages'])->toBeEmpty()
        ->and(CaseConversationToken::verify($fixture['phUser'], $thread->id, '0', $empty['observation_token']))->toBeTrue()
        ->and(CaseConversationToken::verify($fixture['phUser'], $thread->id, '105', $empty['observation_token']))->toBeFalse();
});

test('query rejects malformed, out-of-range, future, and simultaneous cursors', function (): void {
    ['thread' => $thread, 'fixture' => $fixture] = createDirectConversationWithMessages($this, 'case-query-cursor@example.test', 2);
    $query = app(CaseConversationQuery::class);

    foreach (['', '-1', '+1', '01', '1.0', '9223372036854775808'] as $cursor) {
        expect(fn () => $query->forThread($thread, $fixture['phUser'], afterSequence: $cursor))
            ->toThrow(ValidationException::class);
    }
    expect(fn () => $query->forThread($thread, $fixture['phUser'], afterSequence: '3'))
        ->toThrow(ValidationException::class);
    expect(fn () => $query->forThread($thread, $fixture['phUser'], afterSequence: '1', beforeSequence: '2'))
        ->toThrow(ValidationException::class);
});
test('selected and summary projections bind local contexts and staff metadata to exact readable case attempts', function (): void {
    $fixture = createDirectCaseConversationQueryFixture($this, 'case-query-presentation-context@example.test');
    $thread = app(OpenApplicantSupportConversation::class)->handle(
        $fixture['attempt'],
        $fixture['program']->id,
        'Presentation context',
        'Applicant-visible message',
        $fixture['phUser'],
        Str::uuid()->toString(),
    );
    $query = app(CaseConversationQuery::class);
    $selected = $query->selectedSummary((string) $thread->id, $fixture['phUser']);
    $staffPage = $query->summariesForAttempt($fixture['attempt'], RecordThread::TYPE_APPLICANT_SUPPORT, $fixture['phUser']);
    $applicantPage = $query->applicantSummaries($fixture['applicant']);
    $row = $staffPage['data'][0];

    expect($selected['principal_key'])->toStartWith('messaging-client-v1.')
        ->and($selected['attempt_key'])->toBe(json_encode([
            'case',
            (string) $fixture['attempt']->institution_id,
            (string) $fixture['attempt']->id,
            (string) $fixture['attempt']->academicCycle()->value('code'),
            $fixture['program']->code,
        ], JSON_UNESCAPED_SLASHES))
        ->and($selected['applicant'])->toBe([
            'display_name' => trim(implode(' ', array_filter([
                $fixture['attempt']->identity->first_name,
                $fixture['attempt']->identity->middle_name,
                $fixture['attempt']->identity->last_name,
                $fixture['attempt']->identity->suffix,
            ]))),
            'reference_code' => $fixture['attempt']->reference_code,
        ])
        ->and($row['principal_key'])->toBe($selected['principal_key'])
        ->and($row['attempt_key'])->toBe($selected['attempt_key'])
        ->and($row['applicant'])->toBe($selected['applicant'])
        ->and(array_key_exists('applicant', $applicantPage['data'][0]))->toBeFalse();
});

test('summary and selected-root audit failures prevent protected metadata from returning', function (): void {
    $fixture = createDirectCaseConversationQueryFixture($this, 'case-query-summary-audit@example.test');
    $thread = app(OpenApplicantSupportConversation::class)->handle(
        $fixture['attempt'],
        $fixture['program']->id,
        'Protected summary',
        'Applicant-visible message',
        $fixture['phUser'],
        Str::uuid()->toString(),
    );
    $readCount = RecordThreadRead::query()->count();
    $auditCount = AuditEvent::query()->count();
    $realAudit = app(RecordAuditEvent::class);
    $failedAudit = Mockery::mock(RecordAuditEvent::class)->makePartial();
    $failedAudit->shouldReceive('handle')->twice()->andReturnUsing(function (...$arguments) use ($realAudit): never {
        $realAudit->handle(...$arguments);
        throw new RuntimeException('Audit sink failed after insert.');
    });
    app()->instance(RecordAuditEvent::class, $failedAudit);
    $query = app(CaseConversationQuery::class);
    $listResult = null;
    $selectedResult = null;

    expect(fn () => $listResult = $query->summariesForAttempt(
        $fixture['attempt'],
        RecordThread::TYPE_APPLICANT_SUPPORT,
        $fixture['phUser'],
    ))->toThrow(RuntimeException::class);
    expect(fn () => $selectedResult = $query->selectedSummary((string) $thread->id, $fixture['phUser']))
        ->toThrow(RuntimeException::class);
    expect($listResult)->toBeNull()
        ->and($selectedResult)->toBeNull()
        ->and(AuditEvent::query()->count())->toBe($auditCount)
        ->and(RecordThreadRead::query()->count())->toBe($readCount);
});

test('message history reads audit failure rolls back audit writes and never mutates read markers', function (): void {
    ['thread' => $thread, 'fixture' => $fixture] = createDirectConversationWithMessages($this, 'case-query-audit@example.test', 1);
    $readCount = RecordThreadRead::query()->count();
    $auditCount = AuditEvent::query()->count();
    $realAudit = app(RecordAuditEvent::class);
    $failedAudit = Mockery::mock(RecordAuditEvent::class)->makePartial();
    $failedAudit->shouldReceive('handle')->once()->andReturnUsing(function (...$arguments) use ($realAudit): never {
        $realAudit->handle(...$arguments);
        throw new RuntimeException('Audit sink failed after insert.');
    });
    app()->instance(RecordAuditEvent::class, $failedAudit);

    expect(fn () => app(CaseConversationQuery::class)->forThread($thread, $fixture['phUser']))
        ->toThrow(RuntimeException::class);
    expect(AuditEvent::query()->count())->toBe($auditCount)
        ->and(RecordThreadRead::query()->count())->toBe($readCount);

    app()->instance(RecordAuditEvent::class, $realAudit);
    app(CaseConversationQuery::class)->forThread($thread, $fixture['phUser']);
    expect(RecordThreadRead::query()->count())->toBe($readCount);
});

test('applicant cannot read an internal thread through the query', function (): void {
    $fixture = createDirectCaseConversationQueryFixture($this, 'case-query-internal-denied@example.test');
    $thread = app(SendAdmissionSupportMessage::class)->openInternalThread(
        $fixture['attempt'],
        $fixture['program']->id,
        'Internal only',
        'Internal first message',
        $fixture['guidance'],
        Str::uuid()->toString(),
    );

    expect(fn () => app(CaseConversationQuery::class)->forThread($thread, $fixture['applicant']))
        ->toThrow(HttpException::class);

    $staffSummaries = app(CaseConversationQuery::class)->summariesForAttempt(
        $fixture['attempt'],
        RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
        $fixture['guidance'],
    );
    expect($staffSummaries['data'])->toHaveCount(1)
        ->and($staffSummaries['data'][0]['id'])->toBe((string) $thread->id)
        ->and($staffSummaries['data'][0]['thread_type'])->toBe(RecordThread::TYPE_INTERNAL_PH_GUIDANCE)
        ->and($staffSummaries['data'][0])->not->toHaveKey('messages');

});

test('summary pagination uses message activity and tied IDs with encrypted bounded continuation', function (): void {
    $fixture = createDirectCaseConversationQueryFixture($this, 'case-query-summary-pages@example.test');
    $threadIds = [];
    for ($index = 1; $index <= 25; $index++) {
        $thread = app(OpenApplicantSupportConversation::class)->handle(
            $fixture['attempt'],
            $fixture['program']->id,
            "Summary thread {$index}",
            "First message {$index}",
            $fixture['phUser'],
            Str::uuid()->toString(),
        );
        $threadIds[] = (string) $thread->id;
    }
    RecordThreadMessage::query()
        ->whereIn('record_thread_id', $threadIds)
        ->update(['created_at' => '2026-10-08 12:00:00']);

    $readCount = RecordThreadRead::query()->count();
    $query = app(CaseConversationQuery::class);
    $firstPage = $query->summariesForAttempt(
        $fixture['attempt'],
        RecordThread::TYPE_APPLICANT_SUPPORT,
        $fixture['phUser'],
    );
    $expectedIds = array_reverse($threadIds);
    expect($firstPage['data'])->toHaveCount(20)
        ->and(array_column($firstPage['data'], 'id'))->toBe(array_slice($expectedIds, 0, 20))
        ->and($firstPage['data'][0])->toHaveKeys([
            'subject', 'thread_type', 'program_code', 'cycle_code', 'attempt_id',
            'last_activity_at', 'can_read', 'can_send', 'snapshot_url', 'send_url', 'read_url', 'html_url',
        ])
        ->and($firstPage['data'][0]['last_activity_at'])->toBeString()
        ->and($firstPage['data'][0])->not->toHaveKey('updated_at')
        ->and($firstPage['pagination']['has_more'])->toBeTrue()
        ->and($firstPage['pagination']['next_cursor'])->toBeString()
        ->and(array_key_exists('total', $firstPage['pagination']))->toBeFalse();

    $secondPage = $query->summariesForAttempt(
        $fixture['attempt'],
        RecordThread::TYPE_APPLICANT_SUPPORT,
        $fixture['phUser'],
        2,
        20,
        $firstPage['pagination']['next_cursor'],
    );
    expect($secondPage['data'])->toHaveCount(5)
        ->and(array_column($secondPage['data'], 'id'))->toBe(array_slice($expectedIds, 20))
        ->and($secondPage['pagination']['has_more'])->toBeFalse()
        ->and(array_intersect(
            array_column($firstPage['data'], 'id'),
            array_column($secondPage['data'], 'id'),
        ))->toBeEmpty()
        ->and(RecordThreadRead::query()->count())->toBe($readCount);
});
test('applicant collections cross linked attempts with subject-only search and exact selected roots', function (): void {
    $first = createDirectCaseConversationQueryFixture($this, 'case-query-linked-first@example.test');
    $second = createDirectCaseConversationQueryFixture($this, 'case-query-linked-second@example.test');
    $secondCycle = AcademicCycle::query()->firstOrCreate(
        ['institution_id' => $first['attempt']->institution_id, 'code' => 'SYN-CYCLE-2025'],
        [
            'name' => 'Previous Academic Cycle',
            'is_active' => true,
            'school_year' => '2024-2025',
            'semester' => 2,
            'opens_on' => '2024-08-01',
            'closes_on' => '2025-01-31',
        ]
    );
    $second['attempt']->forceFill([
        'academic_cycle_id' => $secondCycle->id,
        'applicant_identity_id' => $first['attempt']->applicant_identity_id,
    ])->save();

    $firstThread = app(OpenApplicantSupportConversation::class)->handle(
        $first['attempt'],
        $first['program']->id,
        'Program support — 東京 one',
        'First actual message',
        $first['phUser'],
        Str::uuid()->toString(),
    );
    $second['phUser']->staffProfile->update(['active_cycle_code' => $secondCycle->code]);
    $secondThread = app(OpenApplicantSupportConversation::class)->handle(
        $second['attempt'],
        $second['program']->id,
        'Program support — 東京 two',
        'Second actual message',
        $second['phUser'],
        Str::uuid()->toString(),
    );
    $internalThread = app(SendAdmissionSupportMessage::class)->openInternalThread(
        $first['attempt'],
        $first['program']->id,
        'Internal deliberation',
        'Staff-only first message',
        $first['guidance'],
        Str::uuid()->toString(),
    );

    $query = app(CaseConversationQuery::class);
    $summaries = $query->applicantSummaries($first['applicant'], ' 東京 ', perPage: 50);
    expect(array_column($summaries['data'], 'id'))
        ->toContain((string) $firstThread->id)
        ->toContain((string) $secondThread->id)
        ->and(array_column($summaries['data'], 'id'))->not->toContain((string) $internalThread->id)
        ->and(array_column($summaries['data'], 'attempt_id'))
        ->toContain((string) $first['attempt']->id)
        ->toContain((string) $second['attempt']->id)
        ->and($summaries['pagination'])->toHaveKeys(['per_page', 'has_more', 'next_cursor'])
        ->and($summaries['pagination'])->not->toHaveKey('total');

    $selected = $query->selectedSummary(
        (string) $secondThread->id,
        $first['applicant'],
        RecordThread::TYPE_APPLICANT_SUPPORT,
        (string) $second['attempt']->id,
        $second['program']->code,
        (string) $second['attempt']->academicCycle()->value('code'),
    );
    expect($selected['id'])->toBe((string) $secondThread->id)
        ->and($selected)->not->toHaveKey('messages');

    expect(fn () => $query->selectedSummary(
        (string) $secondThread->id,
        $first['applicant'],
        RecordThread::TYPE_APPLICANT_SUPPORT,
        (string) $first['attempt']->id,
        $first['program']->code,
        (string) $first['attempt']->academicCycle()->value('code'),
    ))->toThrow(HttpException::class);
});

test('applicant summary cursors are bound to actor session and subject search', function (): void {
    $fixture = createDirectCaseConversationQueryFixture($this, 'case-query-search-cursor@example.test');
    $outsider = createDirectCaseConversationQueryFixture($this, 'case-query-search-outsider@example.test');
    for ($index = 1; $index <= 2; $index++) {
        app(OpenApplicantSupportConversation::class)->handle(
            $fixture['attempt'],
            $fixture['program']->id,
            "Searchable support 東京 {$index}",
            "First message {$index}",
            $fixture['phUser'],
            Str::uuid()->toString(),
        );
    }

    $query = app(CaseConversationQuery::class);
    $firstPage = $query->applicantSummaries($fixture['applicant'], ' support ', perPage: 1);
    expect($firstPage['data'])->toHaveCount(1)
        ->and($firstPage['pagination']['has_more'])->toBeTrue();
    $continuedPage = $query->applicantSummaries(
        $fixture['applicant'],
        'support',
        $firstPage['pagination']['next_cursor'],
        1,
    );
    expect($continuedPage['data'])->toHaveCount(1)
        ->and($continuedPage['data'][0]['id'])->not->toBe($firstPage['data'][0]['id'])
        ->and($continuedPage['pagination']['has_more'])->toBeFalse();

    expect(fn () => $query->applicantSummaries(
        $fixture['applicant'],
        'different search',
        $firstPage['pagination']['next_cursor'],
        1,
    ))->toThrow(ValidationException::class);

    expect(fn () => $query->summariesForAttempt(
        $fixture['attempt'],
        RecordThread::TYPE_APPLICANT_SUPPORT,
        $fixture['phUser'],
        2,
        1,
        $firstPage['pagination']['next_cursor'],
    ))->toThrow(ValidationException::class);

    $sessionId = session()->getId();
    session()->setId('case-query-other-session');
    expect(fn () => $query->applicantSummaries(
        $fixture['applicant'],
        'support',
        $firstPage['pagination']['next_cursor'],
        1,
    ))->toThrow(ValidationException::class);
    session()->setId($sessionId);

    expect(fn () => $query->applicantSummaries(
        $outsider['applicant'],
        'support',
        $firstPage['pagination']['next_cursor'],
        1,
    ))->toThrow(ValidationException::class);

    expect(fn () => $query->applicantSummaries(
        $fixture['applicant'],
        str_repeat('a', 121),
    ))->toThrow(ValidationException::class);
});
test('program summaries follow tied activity cursors while preserving search and scope', function (): void {
    $fixture = createDirectCaseConversationQueryFixture($this, 'case-query-program-scope@example.test');
    $threads = [];
    for ($index = 1; $index <= 2; $index++) {
        $threads[] = app(OpenApplicantSupportConversation::class)->handle(
            $fixture['attempt'],
            $fixture['program']->id,
            "Scoped program support {$index}",
            'Applicant-visible first message',
            $fixture['phUser'],
            Str::uuid()->toString(),
        );
    }
    RecordThreadMessage::query()
        ->whereIn('record_thread_id', array_map(static fn (RecordThread $thread): int => (int) $thread->id, $threads))
        ->update(['created_at' => '2026-10-08 12:00:00']);
    $cycleCode = (string) $fixture['attempt']->academicCycle()->value('code');
    $query = app(CaseConversationQuery::class);

    $summaries = $query->summariesForProgram(
        $fixture['program'],
        $cycleCode,
        $fixture['phUser'],
        'Scoped',
        perPage: 1,
    );
    $nextPage = $query->summariesForProgram(
        $fixture['program'],
        $cycleCode,
        $fixture['phUser'],
        'Scoped',
        $summaries['pagination']['next_cursor'],
        1,
    );

    expect($summaries['data'])->toHaveCount(1)
        ->and($nextPage['data'])->toHaveCount(1)
        ->and($nextPage['data'][0]['id'])->not->toBe($summaries['data'][0]['id'])
        ->and($summaries['data'][0]['last_activity_at'])->toBe($nextPage['data'][0]['last_activity_at'])
        ->and($nextPage['pagination']['has_more'])->toBeFalse();

    expect(fn () => app(CaseConversationQuery::class)->summariesForProgram(
        $fixture['program'],
        'FOREIGN-CYCLE',
        $fixture['phUser'],
    ))->toThrow(HttpException::class);

    $fixture['phUser']->staffProfile()->update(['program_codes' => []]);
    $revokedAttemptSummaries = app(CaseConversationQuery::class)->summariesForAttempt(
        $fixture['attempt'],
        RecordThread::TYPE_APPLICANT_SUPPORT,
        $fixture['phUser'],
    );
    expect($revokedAttemptSummaries['data'])->toBeEmpty()
        ->and($revokedAttemptSummaries['pagination']['has_more'])->toBeFalse()
        ->and($revokedAttemptSummaries['pagination']['next_cursor'])->toBeNull()
        ->and($revokedAttemptSummaries['pagination'])->not->toHaveKey('total');

    expect(fn () => app(CaseConversationQuery::class)->summariesForProgram(
        $fixture['program'],
        $cycleCode,
        $fixture['phUser'],
    ))->toThrow(HttpException::class);

});

test('applicant summary traversal continues past one hundred ineligible roots to older eligible activity', function (): void {
    $deniedFixture = createDirectCaseConversationQueryFixture($this, 'case-query-denied-roots@example.test');
    $eligibleFixture = createDirectCaseConversationQueryFixture($this, 'case-query-older-readable@example.test');
    $secondCycle = AcademicCycle::query()->firstOrCreate(
        ['institution_id' => $deniedFixture['attempt']->institution_id, 'code' => 'SYN-CYCLE-2025'],
        [
            'name' => 'Previous Academic Cycle',
            'is_active' => true,
            'school_year' => '2024-2025',
            'semester' => 2,
            'opens_on' => '2024-08-01',
            'closes_on' => '2025-01-31',
        ]
    );
    $eligibleFixture['attempt']->forceFill([
        'academic_cycle_id' => $secondCycle->id,
        'applicant_identity_id' => $deniedFixture['attempt']->applicant_identity_id,
    ])->save();

    $deniedThreadIds = [];
    for ($index = 1; $index <= 100; $index++) {
        $thread = app(OpenApplicantSupportConversation::class)->handle(
            $deniedFixture['attempt'],
            $deniedFixture['program']->id,
            "New denied support {$index}",
            "New denied message {$index}",
            $deniedFixture['phUser'],
            Str::uuid()->toString(),
        );
        $deniedThreadIds[] = $thread->id;
    }
    $eligibleFixture['phUser']->staffProfile->update(['active_cycle_code' => $secondCycle->code]);
    $eligibleThread = app(OpenApplicantSupportConversation::class)->handle(
        $eligibleFixture['attempt'],
        $eligibleFixture['program']->id,
        'Older readable support',
        'Older readable message',
        $eligibleFixture['phUser'],
        Str::uuid()->toString(),
    );
    $eligibleFixture['phUser']->staffProfile->update(['active_cycle_code' => 'SYN-CYCLE-2026']);
    RecordThreadMessage::query()->whereIn('record_thread_id', $deniedThreadIds)
        ->update(['created_at' => '2026-10-08 12:00:00']);
    RecordThreadMessage::query()->where('record_thread_id', $eligibleThread->id)
        ->update(['created_at' => '2026-10-08 11:00:00']);
    EnrollmentStamp::query()->create([
        'admission_attempt_id' => $deniedFixture['attempt']->id,
        'stamp_type' => EnrollmentStamp::STAMP_ENROLLED,
        'stamped_by_user_id' => $deniedFixture['guidance']->id,
        'reason' => 'Query traversal exclusion fixture',
        'version' => 1,
        'created_at' => now(),
    ]);

    $page = app(CaseConversationQuery::class)->applicantSummaries(
        $deniedFixture['applicant'],
        perPage: 1,
    );

    expect($page['data'])->toHaveCount(1)
        ->and($page['data'][0]['id'])->toBe((string) $eligibleThread->id)
        ->and($page['data'][0]['attempt_id'])->toBe((string) $eligibleFixture['attempt']->id)
        ->and($page['pagination']['has_more'])->toBeFalse()
        ->and($page['pagination']['next_cursor'])->toBeNull()
        ->and(array_intersect(array_column($page['data'], 'id'), array_map('strval', $deniedThreadIds)))->toBeEmpty();
});

test('actual enrolled stamps remove case rows before applicant and staff metadata projection', function (): void {
    $fixture = createDirectCaseConversationQueryFixture($this, 'case-query-enrolled-cutoff@example.test');
    $thread = app(OpenApplicantSupportConversation::class)->handle(
        $fixture['attempt'],
        $fixture['program']->id,
        'Enrolled case support',
        'Historical message retained',
        $fixture['phUser'],
        Str::uuid()->toString(),
    );
    EnrollmentStamp::query()->create([
        'admission_attempt_id' => $fixture['attempt']->id,
        'stamp_type' => EnrollmentStamp::STAMP_ENROLLED,
        'stamped_by_user_id' => $fixture['guidance']->id,
        'reason' => 'Query collection exclusion fixture',
        'version' => 1,
        'created_at' => now(),
    ]);

    $query = app(CaseConversationQuery::class);
    $applicantPage = $query->applicantSummaries($fixture['applicant']);
    $staffPage = $query->summariesForAttempt(
        $fixture['attempt'],
        RecordThread::TYPE_APPLICANT_SUPPORT,
        $fixture['phUser'],
    );

    expect($applicantPage['data'])->toBeEmpty()
        ->and($applicantPage['pagination']['has_more'])->toBeFalse()
        ->and($applicantPage['pagination']['next_cursor'])->toBeNull()
        ->and($staffPage['data'])->toBeEmpty()
        ->and($staffPage['pagination']['has_more'])->toBeFalse()
        ->and(RecordThread::query()->whereKey($thread->id)->exists())->toBeTrue();

    expect(fn () => $query->selectedSummary((string) $thread->id, $fixture['applicant']))
        ->toThrow(HttpException::class);
});
