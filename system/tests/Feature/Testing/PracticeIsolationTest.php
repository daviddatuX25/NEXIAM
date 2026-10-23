<?php

use App\Admission\Support\QuotaReservation;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccount;
use App\Models\Admission\ApplicantOfficeInquiry;
use App\Models\Admission\ApplicantOfficeInquiryMessage;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\RecordThread;
use App\Models\Admission\RecordThreadMessage;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\User;
use App\Reports\Queries\CourseQuotaQualifiersDataQuery;
use App\Testing\PracticeFixtureSet;
use App\Testing\PracticePurpose;
use App\Testing\PracticeWorkspace;
use Illuminate\Support\Facades\Route;

function isolationPracticeSet(): PracticeFixtureSet
{
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    return PracticeFixtureSet::query()->create([
        'label' => 'Panel rehearsal', 'institution_id' => $cycle->institution_id,
        'academic_cycle_id' => $cycle->id, 'scenario' => 'application-review', 'purpose' => 'demo',
        'created_by_user_id' => $actor->id, 'actor_bindings' => ['guidance' => $actor->id],
        'material_snapshot' => [], 'credentials' => ['key' => 'private-practice-key'],
    ]);
}

test('practice intake with matching name and birthdate never reuses operational identity', function (): void {
    seedE2Foundation();
    $original = submitSyntheticApplicant('original@example.test', 'Rivera');
    $attempt = $original['attempt'];
    $identity = $attempt->identity;
    $identityBefore = $identity->getAttributes();
    $attemptBefore = $attempt->getAttributes();
    $workspace = app(PracticeWorkspace::class);
    $set = isolationPracticeSet();
    $practice = $workspace->run($set, fn () => submitSyntheticApplicant('fictional@example.test', 'Rivera'))['attempt'];

    expect($practice->applicant_identity_id)->not->toBe($attempt->applicant_identity_id)
        ->and($practice->practice_fixture_set_id)->toBe($set->id)
        ->and($practice->identity->practice_fixture_set_id)->toBe($set->id)
        ->and($identity->fresh()->getAttributes())->toBe($identityBefore)
        ->and($attempt->fresh()->getAttributes())->toBe($attemptBefore)
        ->and($workspace->currentId())->toBeNull()
        ->and($practice->toArray())->not->toHaveKey('practice_fixture_set_id')
        ->and($practice->identity->toArray())->not->toHaveKey('practice_fixture_set_id')
        ->and($set->toArray())->not->toHaveKey('credentials');
    expect($set->getRawOriginal('credentials'))->not->toContain('private-practice-key');
});

test('practice commitments and official reports remain separated from operational capacity', function (): void {
    seedE2Foundation();
    $set = isolationPracticeSet();
    $other = isolationPracticeSet();
    $workspace = app(PracticeWorkspace::class);
    $real = submitSyntheticApplicant(lastName: 'OperationalCapacity')['attempt'];
    $practice = $workspace->run($set, fn () => submitSyntheticApplicant(lastName: 'PracticeCapacity'))['attempt'];
    $foreign = $workspace->run($other, fn () => submitSyntheticApplicant(lastName: 'ForeignCapacity'))['attempt'];
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $quota = ProgramQuota::query()->firstOrCreate(['academic_cycle_id' => $real->academic_cycle_id, 'program_id' => $program->id], ['slots' => 23]);
    $before = $quota->fresh()->getAttributes();
    foreach ([$real, $practice, $foreign] as $attempt) {
        $attempt->update(['status' => AdmissionAttempt::STATUS_ACCEPTED, 'accepted_at' => now()]);
        EnrollmentStamp::query()->create([
            'admission_attempt_id' => $attempt->id, 'stamp_type' => EnrollmentStamp::STAMP_ENROLLING,
            'provisional_program_id' => $program->id, 'stamped_by_user_id' => $set->created_by_user_id,
            'reason' => 'Isolation test commitment',
        ]);
    }
    expect(QuotaReservation::committedForCycle($real->academic_cycle_id, $program->id))->toBe(1)
        ->and(QuotaReservation::committedAttemptCount($practice, $program->id))->toBe(1)
        ->and(QuotaReservation::committedAttemptCount($practice, $program->id, $practice->id))->toBe(0)
        ->and($quota->fresh()->getAttributes())->toBe($before);
    $report = $workspace->run($set, fn () => app(CourseQuotaQualifiersDataQuery::class)->handle(['academic_cycle_id' => $real->academic_cycle_id]));
    $references = array_column($report['qualifiers'], 'reference_code');
    expect($references)->toContain($real->reference_code)->not->toContain($practice->reference_code, $foreign->reference_code);
    $ids = $workspace->run($set, fn () => AdmissionAttempt::query()->forWorkspace()->pluck('id')->all());
    expect($ids)->toContain($practice->id)->not->toContain($real->id, $foreign->id);
});

test('office entered descendants retain practice ownership and retired history rejects changes', function (): void {
    seedE2Foundation();
    $set = isolationPracticeSet();
    $workspace = app(PracticeWorkspace::class);
    $attempt = $workspace->run($set, fn () => submitSyntheticApplicant(lastName: 'PracticeDescendant'))['attempt'];
    $session = $workspace->run($set, fn () => ExamSession::factory()->create(['academic_cycle_id' => $attempt->academic_cycle_id]));
    $assignment = ExamAssignment::query()->create([
        'admission_attempt_id' => $attempt->id, 'exam_session_id' => $session->id,
        'status' => ExamAssignment::STATUS_ASSIGNED, 'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
        'origin' => ExamAssignment::ORIGIN_MANUAL,
    ]);
    expect(PracticePurpose::attempts(ExamAssignment::query(), 'admission_attempt_id', null)->whereKey($assignment->id)->exists())->toBeFalse()
        ->and(PracticePurpose::attempts(ExamAssignment::query(), 'admission_attempt_id', $set->id)->whereKey($assignment->id)->exists())->toBeTrue();
    $set->update(['status' => 'retired', 'retired_at' => now(), 'revision' => 2]);
    expect(fn () => $assignment->update(['version' => 2]))->toThrow(RuntimeException::class);
    expect(ExamAssignment::query()->whereKey($assignment->id)->exists())->toBeTrue()
        ->and(AdmissionAttempt::query()->whereKey($attempt->id)->exists())->toBeTrue();
});

test('practice roots cannot adopt unknown records or cross into an operational exam session', function (): void {
    seedE2Foundation();
    $real = submitSyntheticApplicant(lastName: 'UnknownProtected')['attempt'];
    $set = isolationPracticeSet();
    expect(fn () => $real->update(['practice_fixture_set_id' => $set->id]))->toThrow(RuntimeException::class);
    $practice = app(PracticeWorkspace::class)->run($set, fn () => submitSyntheticApplicant(lastName: 'SeparateCase'))['attempt'];
    $session = ExamSession::factory()->create(['academic_cycle_id' => $real->academic_cycle_id]);
    expect(fn () => ExamAssignment::query()->create([
        'admission_attempt_id' => $practice->id, 'exam_session_id' => $session->id,
        'status' => ExamAssignment::STATUS_ASSIGNED, 'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
        'origin' => ExamAssignment::ORIGIN_MANUAL,
    ]))->toThrow(RuntimeException::class);
    expect($real->fresh()->practice_fixture_set_id)->toBeNull();
});

test('validated practice handoff does not alter actor scope and cannot edit shared quotas', function (): void {
    seedE2Foundation();
    $set = isolationPracticeSet();
    $quota = ProgramQuota::query()->firstOrCreate([
        'academic_cycle_id' => $set->academic_cycle_id,
        'program_id' => Program::query()->where('code', 'BSIT')->firstOrFail()->id,
    ], ['slots' => 23]);
    $actor = User::query()->findOrFail($set->created_by_user_id);
    $scope = $actor->actorScope()->toArray();
    $permissions = $actor->getAllPermissions()->pluck('name')->all();
    $staffSession = [
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $actor->authenticationState->session_version,
    ];
    Route::get('/__track-f/practice-workspace', fn () => response()->json([
        'workspace_id' => app(PracticeWorkspace::class)->currentId(),
    ]))->middleware('web');
    $this->actingAs($actor)->withSession($staffSession)->get('/__track-f/practice-workspace?practice_set='.$set->id)
        ->assertOk()->assertJsonPath('workspace_id', $set->id);
    expect(app(PracticeWorkspace::class)->currentId())->toBeNull();
    expect($actor->fresh()->actorScope()->toArray())->toBe($scope)
        ->and($actor->fresh()->getAllPermissions()->pluck('name')->all())->toBe($permissions);
    expect(fn () => app(PracticeWorkspace::class)->run($set, fn () => $quota->update(['slots' => $quota->slots + 1])))->toThrow(RuntimeException::class);
    $this->flushSession();
    $this->actingAs($outsider)->withSession([])->get('/demo/preparation?practice_set='.$set->id)->assertForbidden();
    $this->flushSession();
    $this->actingAs($outsider)->get('/demo')->assertOk();
    expect(session('practice_fixture_set_id'))->toBeNull();

    $this->flushSession();
    $this->actingAs($actor)->withSession($staffSession)
        ->get('/demo/preparation?purpose=demo&scenario=application-review&practice_set='.$set->id)->assertOk();
    expect(app(PracticeWorkspace::class)->currentId())->toBeNull();
    $set->update(['created_by_user_id' => $outsider->id, 'actor_bindings' => []]);
    $this->flushSession();
    $this->actingAs($actor)->withSession($staffSession)->get('/demo')->assertForbidden();
});

test('retirement preserves case messages and blocks new thread and inquiry replies', function (): void {
    seedE2Foundation();
    $set = isolationPracticeSet();
    $attempt = app(PracticeWorkspace::class)->run($set, fn () => submitSyntheticApplicant(lastName: 'MessageHistory'))['attempt'];
    $actorId = $set->created_by_user_id;
    $thread = RecordThread::query()->create([
        'admission_attempt_id' => $attempt->id,
        'program_id' => Program::query()->where('code', 'BSIT')->firstOrFail()->id,
        'thread_type' => RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
        'subject' => 'Practice case handoff',
        'status' => RecordThread::STATUS_OPEN,
        'created_by_user_id' => $actorId,
    ]);
    $inquiry = ApplicantOfficeInquiry::query()->create([
        'institution_id' => $attempt->institution_id,
        'academic_cycle_id' => $attempt->academic_cycle_id,
        'admission_attempt_id' => $attempt->id,
        'applicant_identity_id' => $attempt->applicant_identity_id,
        'office' => 'registrar',
        'subject' => 'Practice admission question',
        'created_by_user_id' => $actorId,
    ]);
    $threadMessage = RecordThreadMessage::query()->create([
        'record_thread_id' => $thread->id, 'sender_user_id' => $actorId,
        'sequence' => 1, 'body' => 'Recorded before retirement', 'created_at' => now(),
    ]);
    $inquiryMessage = ApplicantOfficeInquiryMessage::query()->create([
        'applicant_office_inquiry_id' => $inquiry->id, 'sender_user_id' => $actorId,
        'sequence' => 1, 'body' => 'Recorded before retirement', 'created_at' => now(),
    ]);
    $set->update(['status' => 'retired', 'retired_at' => now(), 'revision' => 2]);

    expect(fn () => RecordThreadMessage::query()->create([
        'record_thread_id' => $thread->id, 'sender_user_id' => $actorId,
        'sequence' => 2, 'body' => 'Must not be recorded', 'created_at' => now(),
    ]))->toThrow(RuntimeException::class);
    expect(fn () => ApplicantOfficeInquiryMessage::query()->create([
        'applicant_office_inquiry_id' => $inquiry->id, 'sender_user_id' => $actorId,
        'sequence' => 2, 'body' => 'Must not be recorded', 'created_at' => now(),
    ]))->toThrow(RuntimeException::class);
    expect($threadMessage->fresh()->body)->toBe('Recorded before retirement')
        ->and($inquiryMessage->fresh()->body)->toBe('Recorded before retirement')
        ->and($thread->messages()->pluck('sequence')->all())->toBe([1])
        ->and($inquiry->messages()->pluck('sequence')->all())->toBe([1]);
});

test('practice applicants can terminate native sessions after retirement or cycle closure', function (bool $closeCycle): void {
    seedE2Foundation();
    $set = isolationPracticeSet();
    $attempt = app(PracticeWorkspace::class)->run($set, fn () => submitSyntheticApplicant(lastName: 'SessionExit'))['attempt'];
    $user = User::factory()->create(['email' => $attempt->identity->email]);
    ApplicantAccount::query()->create([
        'applicant_identity_id' => $attempt->applicant_identity_id,
        'user_id' => $user->id,
        'setup_completed_at' => now(),
    ]);
    $set->update(['status' => 'retired', 'retired_at' => now()]);
    if ($closeCycle) {
        $set->academicCycle->update(['is_active' => false]);
    }
    $this->actingAs($user)->post('/logout')->assertRedirect();
    $this->assertGuest();
    expect($attempt->fresh()->practice_fixture_set_id)->toBe($set->id)
        ->and($set->fresh()->status)->toBe('retired');
})->with([false, true]);
