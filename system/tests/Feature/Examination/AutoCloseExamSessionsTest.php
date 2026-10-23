<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\StartExamSession;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    seedE2Foundation();
});

function autoClosePublishedSession(User $actor, string $code, array $attempts): ExamSession
{
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $startsAt = now()->subMinutes(150);
    $session = app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        $code,
        $startsAt->toDateTimeString(),
        $startsAt->copy()->addHours(2)->toDateTimeString(),
        $actor,
        ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
    );

    foreach ($attempts as $attempt) {
        app(AssignApplicantToExamSession::class)->handle($session, $attempt, $actor);
    }

    return app(PublishExamSession::class)->handle($session, $actor, $session->version)->fresh();
}

test('sessions:auto-close cancels an overdue no-kit session with no attendance per D-040', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $result = submitSyntheticApplicant('examinee.noclose@example.test', 'NoClose');
    $attempt = app(AcceptAdmissionAttempt::class)->handle($result['attempt'], $guidance, 1);
    $session = autoClosePublishedSession($guidance, 'EX-AUTOCLOSE-ZERO', [$attempt]);

    expect($session->test_kit_version_id)->toBeNull()
        ->and($session->currentLogisticsPlan)->not->toBeNull();

    $exit = Artisan::call('sessions:auto-close', ['--grace' => 15]);
    expect($exit)->toBe(0);

    $session->refresh();
    expect($session->status)->toBe(ExamSession::STATUS_CANCELLED)
        ->and(GradingSession::query()->where('exam_session_id', $session->id)->exists())->toBeFalse()
        ->and($session->assignments()->first()?->attendanceRecord?->outcome)->toBe(ExamAttendanceRecord::OUTCOME_ABSENT);
});

test('sessions:auto-close completes session and opens grading when attendance recorded per D-040', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $applicantA = submitSyntheticApplicant('examinee.att_a@example.test', 'AttA');
    $applicantB = submitSyntheticApplicant('examinee.att_b@example.test', 'AttB');

    $acceptedA = app(AcceptAdmissionAttempt::class)->handle($applicantA['attempt'], $guidance, 1);
    $acceptedB = app(AcceptAdmissionAttempt::class)->handle($applicantB['attempt'], $guidance, 1);
    $session = autoClosePublishedSession($guidance, 'EX-AUTOCLOSE-ATT', [$acceptedA, $acceptedB]);
    // The scheduled end is thirty minutes before the command's close attempt.
    $session = app(StartExamSession::class)->handle($session, $guidance, $session->version);
    $assignments = $session->assignments()->active()->orderBy('id')->get()->keyBy('admission_attempt_id');
    app(RecordExamAttendance::class)->handle($assignments[$acceptedA->id], $guidance, ExamAttendanceRecord::OUTCOME_PRESENT, 'att-test-a');

    $exit = Artisan::call('sessions:auto-close', ['--grace' => 30]);
    expect($exit)->toBe(0);

    $session->refresh();
    expect($session->status)->toBe(ExamSession::STATUS_COMPLETED)
        ->and(GradingSession::query()->where('exam_session_id', $session->id)->count())->toBe(1);

    // Verify unrecorded applicant B was marked absent and released from active seat hold for waitlist rescheduling
    $assignB = $assignments[$acceptedB->id]->fresh();
    expect($assignB->attendanceRecord)->not->toBeNull()
        ->and($assignB->attendanceRecord->outcome)->toBe(ExamAttendanceRecord::OUTCOME_ABSENT)
        ->and($assignB->status)->toBe(ExamAssignment::STATUS_REMOVED);
});

test('sessions:auto-close cancels an overdue in-progress session when zero examinees are present', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $applicant = submitSyntheticApplicant('examinee.inprogress_zero@example.test', 'InProgressZero');
    $accepted = app(AcceptAdmissionAttempt::class)->handle($applicant['attempt'], $guidance, 1);
    $session = autoClosePublishedSession($guidance, 'EX-AUTOCLOSE-INPROG-ZERO', [$accepted]);
    $session = app(StartExamSession::class)->handle($session, $guidance, $session->version);

    $exit = Artisan::call('sessions:auto-close', ['--grace' => 30]);
    expect($exit)->toBe(0);

    $session->refresh();
    $assignment = $session->assignments()->firstOrFail();
    expect($session->status)->toBe(ExamSession::STATUS_CANCELLED)
        ->and($session->closed_at)->not->toBeNull()
        ->and(GradingSession::query()->where('exam_session_id', $session->id)->exists())->toBeFalse()
        ->and($assignment->status)->toBe(ExamAssignment::STATUS_CANCELLED)
        ->and($assignment->attendanceRecord?->outcome)->toBe(ExamAttendanceRecord::OUTCOME_ABSENT);
});
