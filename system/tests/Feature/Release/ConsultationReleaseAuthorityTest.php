<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Room;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\User;
use App\Release\Actions\PrepareResultDocument;
use App\Release\Actions\RecordConsultationDecision;
use App\Release\Actions\ReleaseResult;
use App\Release\Actions\UnreleaseResult;
use App\ResultSheet\Actions\MarkResultSheetsPrinted;

function createTestGradingSession(AcademicCycle $cycle, Room $room, string $status = GradingSession::STATUS_FINALIZED): array
{
    $examSession = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-TEST-'.uniqid(),
        'starts_at' => now()->subDays(2),
        'effective_end_at' => now()->subDays(2)->addHours(2),
        'status' => ExamSession::STATUS_IN_PROGRESS,
        'version' => 3,
    ]);

    $gradingSession = GradingSession::query()->create([
        'exam_session_id' => $examSession->id,
        'status' => $status,
        'opened_at' => now()->subDays(2),
        'finalized_at' => $status === GradingSession::STATUS_FINALIZED ? now()->subDay() : null,
        'version' => 1,
    ]);

    return [$examSession, $gradingSession];
}

function setupTestAttemptWithGrading(
    string $email,
    string $lastName,
    string $gradingStatus = GradingSession::STATUS_FINALIZED,
    bool $printed = true,
): array {
    $result = submitSyntheticApplicant($email, $lastName);
    $attempt = $result['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    test()->actingAs($registrar)
        ->post(route('admissions.accept', $attempt), ['expected_version' => 1])
        ->assertRedirect();

    $attempt->refresh();

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    [$examSession, $gradingSession] = createTestGradingSession($cycle, $room, $gradingStatus);

    $assignment = ExamAssignment::query()->create([
        'exam_session_id' => $examSession->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);

    if ($gradingStatus === GradingSession::STATUS_FINALIZED) {
        $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        ExamAttendanceRecord::query()->create([
            'exam_assignment_id' => $assignment->id,
            'recorded_by_user_id' => $guidance->id,
            'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
            'idempotency_key' => 'release-authority-attendance-'.$attempt->id,
            'recorded_at' => now(),
        ]);
        ExamSubmissionRecord::query()->create([
            'exam_assignment_id' => $assignment->id,
            'recorded_by_user_id' => $guidance->id,
            'idempotency_key' => 'release-authority-submission-'.$attempt->id,
            'submitted_at' => now(),
        ]);
        foreach (AptitudeArea::query()->where('is_active', true)->get() as $area) {
            ScoreRecord::query()->create([
                'grading_session_id' => $gradingSession->id,
                'admission_attempt_id' => $attempt->id,
                'aptitude_area_id' => $area->id,
                'score' => min(20, (int) $area->max_items),
                'converted_score' => '85.00',
                'source' => ScoreRecord::SOURCE_MANUAL,
                'recorded_by_user_id' => $guidance->id,
                'idempotency_key' => 'release-authority-'.$attempt->id.'-'.$area->id,
            ]);
        }
        $examSession->update(['status' => ExamSession::STATUS_COMPLETED]);
    }

    if ($gradingStatus === GradingSession::STATUS_FINALIZED) {
        $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->first();
        if ($guidance !== null) {
            $document = app(PrepareResultDocument::class)->handle($attempt, $guidance);

            if ($printed) {
                app(MarkResultSheetsPrinted::class)->handle(
                    $guidance,
                    [(int) $document->id],
                    true,
                    idempotencyKey: 'consultation-printed-'.$attempt->id,
                );
            }
        }
    }

    return [$attempt, $result, $examSession, $gradingSession, $assignment];
}

test('release rejects foreign result document', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    [$attempt1] = setupTestAttemptWithGrading('foreign-doc-1@example.test', 'AttemptOne');
    [$attempt2] = setupTestAttemptWithGrading('foreign-doc-2@example.test', 'AttemptTwo');

    // Prepare document for attempt 1
    $doc1 = app(PrepareResultDocument::class)->handle($attempt1, $guidance);

    // Attempt to release attempt 2 using attempt 1's document -> foreign document rejection
    expect(fn () => app(ReleaseResult::class)->handle(
        $attempt2,
        $doc1,
        $guidance,
        'idemp-foreign-doc',
        ReleaseResult::MODE_F2F,
    ))->toThrow(RuntimeException::class, 'Result document does not belong to this admission attempt.');
});

test('release rejects a ready-looking document without a frozen current individual result', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    [$attempt] = setupTestAttemptWithGrading(
        'not-finalized@example.test',
        'NotFinalized',
        gradingStatus: GradingSession::STATUS_OPEN,
    );

    // Create a mock ready document directly to isolate ReleaseResult prerequisite check
    $document = ResultDocument::query()->create([
        'admission_attempt_id' => $attempt->id,
        'template_version' => 'e2-v1',
        'checksum' => hash('sha256', 'mock-doc-'.$attempt->id),
        'status' => ResultDocument::STATUS_READY,
        'storage_path' => 'results/'.$attempt->id.'/mock.html',
        'preview_path' => 'results/'.$attempt->id.'/mock.html',
    ]);

    expect(fn () => app(ReleaseResult::class)->handle(
        $attempt,
        $document,
        $guidance,
        'idemp-not-finalized',
        ReleaseResult::MODE_F2F,
    ))->toThrow(RuntimeException::class, 'The current prepared individual result is required before release.');
});

test('release succeeds WITHOUT a consultation decision (founder inviolable decision)', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    [$attempt, $result] = setupTestAttemptWithGrading('release-no-consult@example.test', 'NoConsult', printed: false);

    // Create applicant portal account for notification delivery
    app(AuthenticateApplicantWithAccessKey::class)->handle(
        $result['access_token'],
        '2001-01-01',
    );

    // Ensure NO consultation decision exists
    expect(ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->exists())->toBeFalse();

    $document = app(PrepareResultDocument::class)->handle($attempt, $guidance);

    $release = app(ReleaseResult::class)->handle(
        $attempt,
        $document,
        $guidance,
        'idemp-release-no-consult',
        ReleaseResult::MODE_ONLINE,
    );

    expect($release)->toBeInstanceOf(ResultRelease::class)
        ->and($release->is_current)->toBeTrue()
        ->and($release->mode)->toBe('online')
        ->and($release->release_version)->toBe(1);

    // Confirm still no consultation decision was required
    expect(ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->exists())->toBeFalse();
});

test('concurrent and idempotent release returns existing receipt and demotes prior releases', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    [$attempt, $result] = setupTestAttemptWithGrading('idemp-release@example.test', 'IdempRel', printed: false);

    app(AuthenticateApplicantWithAccessKey::class)->handle(
        $result['access_token'],
        '2001-01-01',
    );

    $document = app(PrepareResultDocument::class)->handle($attempt, $guidance);

    // First release
    $release1 = app(ReleaseResult::class)->handle(
        $attempt,
        $document,
        $guidance,
        'key-release-1',
        ReleaseResult::MODE_F2F,
    );

    expect($release1->release_version)->toBe(1)
        ->and($release1->is_current)->toBeTrue();

    // Replay with same key -> returns existing release receipt
    $replay = app(ReleaseResult::class)->handle(
        $attempt,
        $document,
        $guidance,
        'key-release-1',
        ReleaseResult::MODE_F2F,
    );

    expect($replay->id)->toBe($release1->id)
        ->and(ResultRelease::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(1);

    // Second release with new key -> demotes previous release and creates version 2
    $release2 = app(ReleaseResult::class)->handle(
        $attempt,
        $document,
        $guidance,
        'key-release-2',
        ReleaseResult::MODE_ONLINE,
    );

    expect($release2->release_version)->toBe(2)
        ->and($release2->is_current)->toBeTrue();

    $release1->refresh();
    expect($release1->is_current)->toBeFalse()
        ->and($release1->superseded_at)->not->toBeNull()
        ->and($release1->unreleased_at)->not->toBeNull();

    // Reusing existing key on a different attempt throws exception
    [$attempt2] = setupTestAttemptWithGrading('idemp-other@example.test', 'IdempOther');
    $doc2 = app(PrepareResultDocument::class)->handle($attempt2, $guidance);

    expect(fn () => app(ReleaseResult::class)->handle(
        $attempt2,
        $doc2,
        $guidance,
        'key-release-1',
        ReleaseResult::MODE_F2F,
    ))->toThrow(RuntimeException::class, 'Idempotency key has already been used for a different admission attempt or document.');
});

test('unrelease active release updates is_current to false with timestamp, reason, and audit', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    [$attempt, $result] = setupTestAttemptWithGrading('unrelease-test@example.test', 'Unrelease', printed: false);

    app(AuthenticateApplicantWithAccessKey::class)->handle(
        $result['access_token'],
        '2001-01-01',
    );

    $document = app(PrepareResultDocument::class)->handle($attempt, $guidance);

    $release = app(ReleaseResult::class)->handle(
        $attempt,
        $document,
        $guidance,
        'key-unrel-1',
        ReleaseResult::MODE_F2F,
    );

    expect($release->is_current)->toBeTrue();

    // Unrelease
    $unreleased = app(UnreleaseResult::class)->handle($release, $guidance, 'Administrative score adjustment requested.');

    expect($unreleased->is_current)->toBeFalse()
        ->and($unreleased->unreleased_at)->not->toBeNull()
        ->and($unreleased->unrelease_reason)->toBe('Administrative score adjustment requested.');

    // Unreleasing already inactive release throws exception
    expect(fn () => app(UnreleaseResult::class)->handle(
        $unreleased,
        $guidance,
        'Second unrelease attempt',
    ))->toThrow(RuntimeException::class, 'Release is not currently active.');
});

test('prepare result document prevents duplicate ready documents for same attempt', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    [$attempt] = setupTestAttemptWithGrading('dup-prepare@example.test', 'DupPrepare');

    $doc1 = app(PrepareResultDocument::class)->handle($attempt, $guidance);

    expect($doc1->status)->toBe(ResultDocument::STATUS_READY);

    // Call prepare again with same attempt and version
    $doc2 = app(PrepareResultDocument::class)->handle($attempt, $guidance);

    expect($doc2->id)->toBe($doc1->id)
        ->and(ResultDocument::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(1);
});

test('preparation rejects an open-batch applicant with incomplete legacy scoring', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    [$attempt] = setupTestAttemptWithGrading(
        'prep-not-fin@example.test',
        'PrepNotFin',
        gradingStatus: GradingSession::STATUS_OPEN,
    );

    expect(fn () => app(PrepareResultDocument::class)->handle(
        $attempt,
        $guidance,
    ))->toThrow(RuntimeException::class);

    expect(ResultDocument::query()->where('admission_attempt_id', $attempt->id)->exists())->toBeFalse();
});

test('record consultation decision allows walk-in when unassigned and tags direct_walk_in origin', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    [$attempt] = setupTestAttemptWithGrading('consult-no-assign@example.test', 'NoAssign');

    // Attempt has NO consultation assignment
    expect(ConsultationAssignment::query()->where('admission_attempt_id', $attempt->id)->exists())->toBeFalse();

    $decision = app(RecordConsultationDecision::class)->handle(
        $attempt,
        $guidance,
        'approve',
        'Notes',
        'key-consult-no-assign',
    );

    expect($decision)->not->toBeNull()
        ->and($decision->decision_type)->toBe('approve');
});

test('record consultation decision succeeds with active assignment and supports idempotency and update', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    [$attempt] = setupTestAttemptWithGrading('consult-ok@example.test', 'ConsultOk');

    $session = ConsultationSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'CONS-TEST-'.uniqid(),
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'status' => ConsultationSession::STATUS_PUBLISHED,
        'scheduled_at' => now()->addDays(2),
        'version' => 1,
    ]);

    ConsultationAssignment::query()->create([
        'consultation_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ConsultationAssignment::STATUS_ASSIGNED,
        'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
    ]);

    // First decision
    $decision = app(RecordConsultationDecision::class)->handle(
        $attempt,
        $guidance,
        'approve',
        'Initial counseling approval',
        'key-dec-1',
    );

    expect($decision->decision_type)->toBe('approve')
        ->and($decision->version)->toBe(1)
        ->and($decision->counselor_user_id)->toBe($guidance->id);

    // Replay with same key -> returns existing decision
    $replay = app(RecordConsultationDecision::class)->handle(
        $attempt,
        $guidance,
        'approve',
        'Initial counseling approval',
        'key-dec-1',
    );

    expect($replay->id)->toBe($decision->id)
        ->and(ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(1);

    // Update with new decision and new key -> bumps version
    $updated = app(RecordConsultationDecision::class)->handle(
        $attempt,
        $guidance,
        'modify',
        'Counselor advised program modification',
        'key-dec-2',
    );

    expect($updated->decision_type)->toBe('modify')
        ->and($updated->version)->toBe(2)
        ->and($updated->notes)->toBe('Counselor advised program modification')
        ->and(ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(2);
});
