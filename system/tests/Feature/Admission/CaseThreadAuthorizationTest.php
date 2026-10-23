<?php

use App\Admission\Actions\AcknowledgeAssistedProgramSelection;
use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\MarkAdmissionSupportMessageRead;
use App\Admission\Actions\OpenApplicantSupportConversation;
use App\Admission\Actions\SendAdmissionSupportMessage;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Admission\Support\RecordThreadService;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\RecordThread;
use App\Models\Assessment\GradingSession;
use App\Models\Communications\PublicPolicy;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\StaffProfile;
use App\Models\User;
use App\Release\Actions\ReleaseResult;
use App\Testing\PracticeFixtureSet;
use App\Testing\PracticeWorkspace;
use Database\Seeders\PublicIntakeConsentDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../../Support/CaseMessagingHelpers.php';

test('closed internal case history remains readable, cannot receive messages, and requires observed read proof', function (): void {
    seedE2Foundation();
    [$attempt, $applicant, $guidance] = buildMessagingReadyAttempt($this, 'closed-case-thread@example.test');
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $program->code, [
        ['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'closed-thread-pool');
    app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'closed-thread-selection');
    $phUser = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $threads = app(RecordThreadService::class);
    $thread = app(SendAdmissionSupportMessage::class)->openInternalThread($attempt, $program->id, 'Case review', 'Initial review note.', $guidance, '11111111-1111-4111-8111-111111111111');
    $thread->update(['status' => RecordThread::STATUS_CLOSED]);

    expect($threads->canRead($thread, $phUser))
        ->toBeTrue()
        ->and($threads->canSend($thread, $phUser))->toBeFalse();
    expect(fn () => app(MarkAdmissionSupportMessageRead::class)->handle($thread, $phUser, '0', 'invalid-token'))
        ->toThrow(RuntimeException::class, 'Invalid or expired observation token.');
    expect(fn () => app(MarkAdmissionSupportMessageRead::class)->handle($thread, $phUser, 'not-a-sequence', 'invalid-token'))
        ->toThrow(ValidationException::class);

    expect(fn () => app(SendAdmissionSupportMessage::class)->handle($thread, $phUser, 'Too late', '22222222-2222-4222-8222-222222222222'))
        ->toThrow(RuntimeException::class);
});

test('applicant identity cannot read internal staff case threads', function (): void {
    seedE2Foundation();
    [$attempt, $applicant, $guidance] = buildMessagingReadyAttempt($this, 'applicant-internal-thread@example.test');
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $program->code, [
        ['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'internal-thread-pool');
    app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'internal-thread-selection');
    $thread = app(SendAdmissionSupportMessage::class)->openInternalThread($attempt, $program->id, 'Internal only', 'Private staff note.', $guidance, '33333333-3333-4333-8333-333333333333');

    expect(app(RecordThreadService::class)->canRead($thread, $applicant))->toBeFalse();
    expect(fn () => app(MarkAdmissionSupportMessageRead::class)->handle($thread, $applicant, '0', 'invalid-token'))
        ->toThrow(RuntimeException::class);
});

test('case thread open rejects a forged route program override', function (): void {
    seedE2Foundation();
    [$attempt, $applicant, $guidance] = buildMessagingReadyAttempt($this, 'forged-thread-program@example.test');
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $program->code, [
        ['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'forged-thread-pool');
    app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'forged-thread-selection');
    $otherProgram = Program::query()->where('code', '!=', $program->code)->firstOrFail();

    actingAsMessagingActor($this, $guidance)
        ->post(route('program-head.threads.open', [
            'program' => $otherProgram->code,
            'attempt' => $attempt->id,
        ]), [
            'subject' => 'Forged scope',
            'body' => 'Please review my case.',
            'idempotency_key' => '15151515-1515-4515-8515-151515151515',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('thread');

    expect(RecordThread::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0);
});

test('case threads reject a program with no current case relationship', function (): void {
    seedE2Foundation();
    [$attempt, , $guidance] = buildMessagingReadyAttempt($this, 'unrelated-case-thread@example.test');
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();

    expect(fn () => app(SendAdmissionSupportMessage::class)->openInternalThread(
        $attempt, $program->id, 'Unrelated case', 'Not allowed.', $guidance, '44444444-4444-4444-8444-444444444444',
    ))->toThrow(RuntimeException::class, 'You are not authorized to open this case thread.');

    expect(RecordThread::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0);
});

test('applicant-support thread is owned by its applicant identity with stable message replay', function (): void {
    seedE2Foundation();
    [$attempt, $applicant, $guidance] = buildMessagingReadyAttempt($this, 'applicant-support-owner@example.test');
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $program->code, [
        ['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'applicant-thread-pool');
    app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'applicant-thread-selection');
    $threads = app(RecordThreadService::class);
    $phUser = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $thread = app(OpenApplicantSupportConversation::class)->handle(
        $attempt, $program->id, 'Applicant support', 'Initial support message.', $phUser, '55555555-5555-4555-8555-555555555555',
    );

    expect($threads->canRead($thread, $applicant))
        ->toBeTrue()
        ->and($threads->canSend($thread, $applicant))
        ->toBeTrue();

    $action = app(SendAdmissionSupportMessage::class);
    $messagesBeforeReply = $thread->messages()->count();
    $first = $action->handle($thread, $applicant, 'I have a question.', '66666666-6666-4666-8666-666666666666');
    $messagesAfterReply = $thread->messages()->count();
    $replay = $action->handle($thread, $applicant, 'I have a question.', '66666666-6666-4666-8666-666666666666');

    expect($replay->id)->toBe($first->id)
        ->and($first->sequence)->toBe(2)
        ->and($thread->messages()->count())->toBe($messagesAfterReply)
        ->and($messagesAfterReply)->toBe($messagesBeforeReply + 1)
        ->and($thread->messages()->whereKey($replay->id)->value('body'))->toBe('I have a question.');
});
test('applicant support relationship ends after enrollment', function (): void {
    seedE2Foundation();
    [$attempt, $applicant, $guidance] = buildMessagingReadyAttempt($this, 'enrolled-applicant-thread@example.test');
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $program->code, [
        ['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'enrolled-thread-pool');
    app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'enrolled-thread-selection');
    $threads = app(RecordThreadService::class);
    $phUser = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $thread = app(OpenApplicantSupportConversation::class)->handle(
        $attempt, $program->id, 'Applicant support', 'Initial support message.', $phUser, '77777777-7777-4777-8777-777777777777',
    );
    $send = app(SendAdmissionSupportMessage::class);
    $priorMessage = $send->handle($thread, $applicant, 'Before enrollment', '88888888-8888-4888-8888-888888888888');
    expect($priorMessage->sequence)->toBe(2);
    EnrollmentStamp::query()->create([
        'admission_attempt_id' => $attempt->id,
        'stamp_type' => EnrollmentStamp::STAMP_ENROLLED,
        'stamped_by_user_id' => $guidance->id,
        'reason' => 'Authorization regression fixture',
        'version' => 1,
        'created_at' => now(),
    ]);

    expect($threads->canRead($thread, $applicant))
        ->toBeFalse()
        ->and($threads->canSend($thread, $applicant))
        ->toBeFalse()
        ->and($thread->messages()->count())
        ->toBe(2)
        ->and(RecordThread::query()->whereKey($thread->id)->exists())
        ->toBeTrue();
    expect(fn () => $send->handle($thread, $applicant, 'Before enrollment', '88888888-8888-4888-8888-888888888888'))
        ->toThrow(RuntimeException::class);
    expect(fn () => $send->handle($thread, $applicant, 'After enrollment', '99999999-9999-4999-8999-999999999999'))
        ->toThrow(RuntimeException::class);
});
test('revoking applicant thread capability denies reads and exact send replay without deleting history', function (): void {
    seedE2Foundation();
    [$attempt, $applicant, $guidance] = buildMessagingReadyAttempt($this, 'revoked-applicant-thread@example.test');
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $program->code, [
        ['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'revoked-applicant-thread-pool');
    app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'revoked-applicant-thread-selection');
    $phUser = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $thread = app(OpenApplicantSupportConversation::class)->handle(
        $attempt, $program->id, 'Applicant support', 'Initial support message.', $phUser,
        '20202020-2020-4020-8020-202020202020',
    );
    $send = app(SendAdmissionSupportMessage::class);
    $message = $send->handle($thread, $applicant, 'Exact reply payload.', '21212121-2121-4121-8121-212121212121');
    expect(app(RecordThreadService::class)->canRead($thread, $applicant))->toBeTrue();

    $applicant->revokePermissionTo(CapabilityCatalog::RECORD_THREAD_PARTICIPATE);

    expect(app(RecordThreadService::class)->canRead($thread, $applicant))->toBeFalse()
        ->and(app(RecordThreadService::class)->canSend($thread, $applicant))->toBeFalse();
    expect(fn () => $send->handle($thread, $applicant, 'Exact reply payload.', '21212121-2121-4121-8121-212121212121'))
        ->toThrow(RuntimeException::class);

    expect($thread->messages()->count())->toBe(2)
        ->and(RecordThread::query()->whereKey($thread->id)->exists())->toBeTrue()
        ->and($thread->messages()->whereKey($message->id)->exists())->toBeTrue();
});
test('clearance confirmation does not revoke eligible case history or replies', function (): void {
    seedE2Foundation();
    [$attempt, $applicant, $guidance] = buildMessagingReadyAttempt($this, 'case-clearance-not-cutoff@example.test');
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $program->code, [
        ['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'case-clearance-pool');
    app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'case-clearance-selection');
    $threads = app(RecordThreadService::class);
    $phUser = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $thread = app(OpenApplicantSupportConversation::class)->handle(
        $attempt, $program->id, 'Clearance question', 'Please confirm the remaining document.', $phUser,
        'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
    );
    $clearance = AdmissionClearanceReceipt::query()->create([
        'admission_attempt_id' => $attempt->id,
        'program_id' => $program->id,
        'receipt_number' => 'CLR-CASE-CUTOFF',
        'status' => AdmissionClearanceReceipt::STATUS_CONFIRMED,
        'evidence_vector' => ['eligible' => true],
        'confirmed_by_user_id' => $guidance->id,
        'confirmed_at' => now(),
        'idempotency_key' => 'case-clearance-receipt',
    ]);

    expect($threads->canRead($thread, $applicant))
        ->toBeTrue()
        ->and($threads->canSend($thread, $applicant))
        ->toBeTrue();

    $message = app(SendAdmissionSupportMessage::class)->handle(
        $thread, $applicant, 'I have a question after clearance.', 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
    );
    expect($message->sequence)->toBe(2)
        ->and($thread->fresh()->latest_message_sequence)->toBe(2);
});

test('preloaded staff profile does not preserve revoked program scope', function (): void {
    seedE2Foundation();
    [$attempt, $applicant, $guidance] = buildMessagingReadyAttempt($this, 'revoked-case-thread@example.test');
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $program->code, [
        ['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'revoked-thread-pool');
    app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'revoked-thread-selection');
    $phUser = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $phUser->load('staffProfile');
    $thread = app(SendAdmissionSupportMessage::class)->openInternalThread($attempt, $program->id, 'Revocation', 'Initial internal note.', $guidance, '99999999-9999-4999-8999-999999999999');
    $service = app(RecordThreadService::class);

    expect($service->canRead($thread, $phUser))->toBeTrue();
    StaffProfile::query()->where('user_id', $phUser->id)->firstOrFail()->update(['program_codes' => []]);
    expect($service->canRead($thread, $phUser))->toBeFalse();
    expect(fn () => app(SendAdmissionSupportMessage::class)->handle($thread, $phUser, 'Revoked', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'))
        ->toThrow(RuntimeException::class);
});
test('thread history defaults to the newest bounded page and honors an explicit older page', function (): void {
    seedE2Foundation();
    [$attempt, , $guidance] = buildMessagingReadyAttempt($this, 'thread-history-boundary@example.test');
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $program->code, [
        ['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'thread-history-pool');
    $thread = app(SendAdmissionSupportMessage::class)->openInternalThread($attempt, $program->id, 'History boundary', 'Opening history note.', $guidance, 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb');
    $sendMessage = app(SendAdmissionSupportMessage::class);
    $createdAt = now()->subMinutes(51);
    $thread->messages()->where('sequence', 1)->update(['created_at' => $createdAt->copy()->addMinute()]);
    foreach (range(2, 51) as $number) {
        $message = $sendMessage->handle(
            $thread,
            $guidance,
            "Message {$number}",
            sprintf('00000000-0000-4000-8000-%012d', $number),
        );
        $message->forceFill([
            'created_at' => $createdAt->copy()->addMinutes($number),
        ])->save();
    }

    $pageName = 'thread_messages_'.$thread->id.'_page';
    request()->query->remove($pageName);
    $service = app(RecordThreadService::class);
    $latest = $service->messagesFor($thread, $guidance);

    expect($latest->count())->toBe(1)
        ->and($latest->currentPage())->toBe(2)
        ->and($latest->lastPage())->toBe(2)
        ->and($latest->first()->body)->toBe('Message 51');

    request()->query->set($pageName, '1');
    $older = $service->messagesFor($thread, $guidance);
    expect($older->count())->toBe(50)
        ->and($older->currentPage())->toBe(1)
        ->and($older->first()->body)->toBe('Opening history note.')
        ->and($older->last()->body)->toBe('Message 50');
});
test('direct case opening validates its payload and rolls back root, first message, audits, and receipt on audit failure', function (): void {
    seedE2Foundation();
    [$attempt, $applicant, $guidance] = buildMessagingReadyAttempt($this, 'case-open-atomicity@example.test');
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $program->code, [
        ['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'case-open-atomicity-pool');
    app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'case-open-atomicity-selection');
    $phUser = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $support = app(OpenApplicantSupportConversation::class);

    expect(fn () => $support->handle(
        $attempt, $program->id, str_repeat('s', 121), 'Initial body.', $phUser, 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
    ))->toThrow(ValidationException::class);
    expect(fn () => $support->handle(
        $attempt, $program->id, 'Subject', str_repeat('b', 4001), $phUser, 'ffffffff-ffff-4fff-8fff-ffffffffffff',
    ))->toThrow(ValidationException::class);
    expect(fn () => $support->handle(
        $attempt, $program->id, 'Subject', 'Initial body.', $phUser, 'not-a-uuid',
    ))->toThrow(ValidationException::class);
    expect(fn () => $support->handle(
        $attempt, $program->id, 'Subject', "Invalid \xB1 UTF-8", $phUser, '19191919-1919-4919-8919-191919191919',
    ))->toThrow(ValidationException::class);
    $internal = app(SendAdmissionSupportMessage::class);
    expect(fn () => $internal->openInternalThread(
        $attempt, $program->id, str_repeat('s', 121), 'Initial body.', $guidance, '17171717-1717-4717-8717-171717171717',
    ))->toThrow(ValidationException::class);
    expect(fn () => $internal->openInternalThread(
        $attempt, $program->id, 'Subject', str_repeat('b', 4001), $guidance, '18181818-1818-4818-8818-181818181818',
    ))->toThrow(ValidationException::class);
    expect(fn () => $internal->openInternalThread(
        $attempt, $program->id, 'Subject', 'Initial body.', $guidance, 'not-a-uuid',
    ))->toThrow(ValidationException::class);
    expect(RecordThread::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0);
    $thread = $support->handle(
        $attempt, $program->id, 'Valid support', 'Initial body.', $phUser, '12121212-1212-4212-8212-121212121212',
    );
    $send = app(SendAdmissionSupportMessage::class);
    expect(fn () => $send->handle($thread, $applicant, '   ', '13131313-1313-4313-8313-131313131313'))
        ->toThrow(ValidationException::class);
    expect(fn () => $send->handle($thread, $applicant, str_repeat('b', 4001), '14141414-1414-4414-8414-141414141414'))
        ->toThrow(ValidationException::class);
    expect(fn () => $send->handle($thread, $applicant, 'Valid text', 'not-a-uuid'))
        ->toThrow(ValidationException::class);

    $beforeThreads = RecordThread::query()->where('admission_attempt_id', $attempt->id)->count();
    $beforeMessages = DB::table('record_thread_messages')->count();
    $beforeThreadAudits = DB::table('audit_events')->whereIn('event_type', ['thread.opened', 'thread.message_sent'])->count();
    $beforeNotificationDeliveries = DB::table('notification_deliveries')->count();
    $beforeOpenReceipts = DB::table('operations')->where('type', 'record.thread.open_internal')->count();
    expect($thread->fresh()->latest_message_sequence)->toBe(1);

    $audit = Mockery::mock(RecordAuditEvent::class)->makePartial();
    $audit->shouldReceive('handle')
        ->once()
        ->with('thread.message_sent', Mockery::type('array'), (string) $guidance->id)
        ->andThrow(new RuntimeException('audit unavailable'));
    app()->instance(RecordAuditEvent::class, $audit);

    expect(fn () => app(SendAdmissionSupportMessage::class)->openInternalThread(
        $attempt, $program->id, 'Internal review', 'Actual first internal message.', $guidance,
        '10101010-1010-4010-8010-101010101010',
    ))->toThrow(RuntimeException::class, 'audit unavailable');

    expect(RecordThread::query()->where('admission_attempt_id', $attempt->id)->count())->toBe($beforeThreads)
        ->and(DB::table('record_thread_messages')->count())->toBe($beforeMessages)
        ->and(DB::table('operations')->where('type', 'record.thread.open_internal')->count())->toBe($beforeOpenReceipts)
        ->and(DB::table('notification_deliveries')->count())->toBe($beforeNotificationDeliveries)
        ->and(DB::table('audit_events')->whereIn('event_type', ['thread.opened', 'thread.message_sent'])->count())->toBe($beforeThreadAudits);
});

test('retired practice cases preserve exact applicant-support opening replay but deny new roots', function (): void {
    seedE2Foundation();
    (new PublicIntakeConsentDemoSeeder)->run();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $phUser = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $otherProgram = Program::query()->where('code', 'BA-Psych')->firstOrFail();

    foreach ([$program, $otherProgram] as $prog) {
        $prog->update(['is_admission_offered' => true]);
        ProgramQuota::query()->updateOrCreate(
            ['program_id' => $prog->id, 'academic_cycle_id' => $cycle->id],
            ['slots' => 5],
        );
    }

    $policy = PublicPolicy::designatedIntake((int) $institution->id);
    $revision = $policy?->publishedRevision;
    expect($revision)->not->toBeNull();

    $practiceSet = PracticeFixtureSet::query()->create([
        'id' => (string) Str::uuid(),
        'label' => 'Synthetic messaging fixture',
        'institution_id' => $institution->id,
        'academic_cycle_id' => $cycle->id,
        'scenario' => 'application-review',
        'purpose' => 'demo',
        'created_by_user_id' => $guidance->id,
        'actor_bindings' => ['guidance' => $guidance->id, 'academic-leader' => $phUser->id],
        'material_snapshot' => [],
        'credentials' => [],
    ]);

    [$attempt, $session, $accessToken] = app(PracticeWorkspace::class)->run($practiceSet, function () use (
        $institution, $cycle, $window, $policy, $revision, $program, $room,
    ): array {
        $submission = app(SubmitAdmissionAttempt::class)->handle(
            $institution,
            $cycle,
            $window,
            new AdmissionIntakeData(
                firstName: 'Test',
                lastName: 'Retired Practice Support',
                birthdate: '2001-01-01',
                sex: 'male',
                contactEmail: 'retired-practice-support@example.test',
                applicantType: 'new',
                programPreferenceCodes: [$program->code],
                grades: ['GWA' => '1.75', 'ENG' => '88.00', 'FIL' => '90.00', 'MATH' => '85.00', 'SCI' => '87.00'],
                middleName: null,
                suffix: null,
                contactPhone: '09171234567',
                lastSchoolEnrolled: 'Sample Senior High School',
                strand: 'STEM',
                addressLine: '123 Sample St',
                city: 'Tagudin',
                province: 'Ilocos Sur',
                zipCode: '2714',
                termsAccepted: true,
                publicPolicyId: (int) $policy->id,
                publicPolicyRevisionId: (int) $revision->id,
                publicPolicyRevisionDigest: $revision->contentDigest(),
            ),
            'test',
        );
        $attempt = $submission['attempt']->forceFill(['status' => AdmissionAttempt::STATUS_ACCEPTED]);
        $attempt->save();

        $session = ExamSession::query()->create([
            'code' => 'MSG-RETIRED-'.substr((string) Str::uuid(), 0, 8),
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'starts_at' => now()->subHours(3),
            'effective_end_at' => now()->subHours(1),
            'status' => 'closed',
        ]);
        ExamAssignment::query()->create([
            'exam_session_id' => $session->id,
            'admission_attempt_id' => $attempt->id,
            'status' => ExamAssignment::STATUS_ASSIGNED,
        ]);

        return [$attempt, $session, $submission['access_token']];
    });

    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle($accessToken, '2001-01-01');

    GradingSession::query()->create([
        'exam_session_id' => $session->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subHours(2),
        'finalized_at' => now()->subHour(),
    ]);
    $document = ResultDocument::query()->create([
        'admission_attempt_id' => $attempt->id,
        'template_version' => 1,
        'checksum' => 'synthetic-retired-messaging',
        'status' => ResultDocument::STATUS_READY,
        'version' => 1,
        'snapshot_payload_json' => ['synthetic_messaging_prerequisite' => true],
    ]);
    $attempt->forceFill(['current_result_document_id' => $document->id])->save();
    ResultRelease::query()->create([
        'admission_attempt_id' => $attempt->id,
        'result_document_id' => $document->id,
        'released_by_user_id' => $guidance->id,
        'release_version' => 1,
        'is_current' => true,
        'released_at' => now(),
        'mode' => ReleaseResult::MODE_F2F,
        'channel' => 'physical',
        'idempotency_key' => 'retired-practice-support-release',
    ]);
    ConsultationDecision::query()->create([
        'admission_attempt_id' => $attempt->id,
        'decision_type' => 'approve',
        'counselor_user_id' => $guidance->id,
        'decided_at' => now(),
        'idempotency_key' => 'retired-practice-support-decision',
    ]);
    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $program->code, [
        ['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'retired-practice-support-pool');
    app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'retired-practice-support-selection');

    $action = app(OpenApplicantSupportConversation::class);
    $key = '30303030-3030-4030-8030-303030303030';
    $thread = $action->handle($attempt, $program->id, 'Retained support', 'Original first message.', $phUser, $key);
    $originalMessageId = DB::table('record_thread_messages')->where('record_thread_id', $thread->id)->value('id');
    $counts = [
        'threads' => DB::table('record_threads')->count(),
        'messages' => DB::table('record_thread_messages')->count(),
        'audits' => DB::table('audit_events')->whereIn('event_type', ['thread.opened', 'thread.message_sent'])->count(),
        'deliveries' => DB::table('notification_deliveries')->count(),
    ];

    $retirementPreview = actingAsMessagingActor($this, $guidance)->postJson('/demo/preparation/preview', [
        'purpose' => 'demo', 'scenario' => 'application-review', 'action' => 'retire', 'set_id' => $practiceSet->id,
    ])->assertOk()->assertJsonPath('can_confirm', true);
    actingAsMessagingActor($this, $guidance)->postJson($retirementPreview->json('operation.confirm_url'), [])->assertOk();

    $replayedThread = $action->handle($attempt, $program->id, 'Retained support', 'Original first message.', $phUser, $key);
    expect($replayedThread->id)->toBe($thread->id)
        ->and(DB::table('record_thread_messages')->where('record_thread_id', $thread->id)->value('id'))->toBe($originalMessageId);
    expect(fn () => $action->handle(
        $attempt, $program->id, 'New support', 'Must not be created.', $phUser, '31313131-3131-4131-8131-313131313131',
    ))->toThrow(RuntimeException::class, 'The practice case is retired.');

    expect(DB::table('record_threads')->count())->toBe($counts['threads'])
        ->and(DB::table('record_thread_messages')->count())->toBe($counts['messages'])
        ->and(DB::table('audit_events')->whereIn('event_type', ['thread.opened', 'thread.message_sent'])->count())->toBe($counts['audits'])
        ->and(DB::table('notification_deliveries')->count())->toBe($counts['deliveries'])
        ->and(DB::table('operations')->where('type', 'record.thread.open_support')->count())->toBe(1);
});
