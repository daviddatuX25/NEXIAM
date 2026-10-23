<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Support\ApplicantQrCode;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CloseExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\SessionQrCheckinAction;
use App\Examination\Actions\StartExamSession;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedE2Foundation();
    $this->freezeSecond();
    $this->admin = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->admin->givePermissionTo(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);
});

function attendanceLifecycleFixture(User $admin, string $email): array
{
    $result = submitSyntheticApplicant($email, 'Lifecycle');
    $attempt = app(AcceptAdmissionAttempt::class)->handle($result['attempt'], $admin, 1);
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $startsAt = now()->addHours(2);
    $session = app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        'LIFECYCLE-'.strtoupper(substr(md5($email), 0, 8)),
        $startsAt->toDateTimeString(),
        $startsAt->copy()->addHour()->toDateTimeString(),
        $admin,
        ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
    );
    $assignment = app(AssignApplicantToExamSession::class)->handle($session, $attempt, $admin);
    $session = app(PublishExamSession::class)->handle($session, $admin, $session->version);

    return [$attempt, $session, $assignment];
}

test('attendance opens only after actual start and remains open beyond the scheduled end', function (): void {
    [$attempt, $session, $assignment] = attendanceLifecycleFixture($this->admin, 'attendance-lifecycle-window@example.test');
    $start = app(StartExamSession::class);
    $attendance = app(RecordExamAttendance::class);
    $scheduledStart = $session->starts_at;
    $scheduledEnd = $session->effective_end_at;

    expect(fn () => $attendance->handle($assignment, $this->admin, ExamAttendanceRecord::OUTCOME_PRESENT, 'attendance-before-start'))
        ->toThrow(RuntimeException::class);

    $started = $start->handle($session, $this->admin, $session->version);
    $startedAt = $started->started_at;
    $this->travel(4)->hours();
    $record = $attendance->handle($assignment, $this->admin, ExamAttendanceRecord::OUTCOME_PRESENT, 'attendance-after-scheduled-end');
    $replayedStart = $start->handle($started->fresh(), $this->admin, $started->fresh()->version);

    expect($record->outcome)->toBe(ExamAttendanceRecord::OUTCOME_PRESENT)
        ->and($replayedStart->started_at->equalTo($startedAt))->toBeTrue()
        ->and($replayedStart->starts_at->equalTo($scheduledStart))->toBeTrue()
        ->and($replayedStart->effective_end_at->equalTo($scheduledEnd))->toBeTrue();
});

test('early manual start opens attendance and actual close ends it', function (): void {
    [$attempt, $session, $assignment] = attendanceLifecycleFixture($this->admin, 'attendance-lifecycle-close@example.test');
    expect(fn () => app(CloseExamSession::class)->handle($session, $this->admin, $session->version))
        ->toThrow(RuntimeException::class);
    $started = app(StartExamSession::class)->handle($session, $this->admin, $session->version);
    expect($started->started_at->equalTo(now()))->toBeTrue();
    $checkin = app(SessionQrCheckinAction::class)->handle($started, $this->admin, ApplicantQrCode::issue($attempt), 'qr-early-start');
    expect($checkin['status'])->toBe('ok')
        ->and($assignment->fresh()->attendanceRecord->outcome)->toBe(ExamAttendanceRecord::OUTCOME_PRESENT);

    app(RecordExamAttendance::class)->handle($assignment, $this->admin, ExamAttendanceRecord::OUTCOME_ABSENT, 'attendance-close-absent');
    $this->travel(10)->minutes();
    $closed = app(CloseExamSession::class)->handle($started, $this->admin, $started->version);

    expect($closed->closed_at->equalTo(now()))->toBeTrue()
        ->and($closed->starts_at->equalTo($session->starts_at))->toBeTrue()
        ->and($closed->effective_end_at->equalTo($session->effective_end_at))->toBeTrue();
    expect(fn () => app(RecordExamAttendance::class)->handle($assignment, $this->admin, ExamAttendanceRecord::OUTCOME_PRESENT, 'attendance-after-close'))
        ->toThrow(RuntimeException::class);
    expect(fn () => app(SessionQrCheckinAction::class)->handle($closed, $this->admin, ApplicantQrCode::issue($attempt), 'qr-after-close'))
        ->toThrow(RuntimeException::class);
    expect($assignment->fresh()->attendanceRecord->outcome)->toBe(ExamAttendanceRecord::OUTCOME_ABSENT)
        ->and($assignment->fresh()->status)->toBe(ExamAssignment::STATUS_CANCELLED)
        ->and($closed->fresh()->status)->toBe(ExamSession::STATUS_CANCELLED)
        ->and(GradingSession::query()->where('exam_session_id', $session->id)->exists())->toBeFalse();
});

test('published and cancelled sessions reject manual and QR attendance', function (): void {
    [$attempt, $session, $assignment] = attendanceLifecycleFixture($this->admin, 'attendance-lifecycle-denied@example.test');
    expect(fn () => app(RecordExamAttendance::class)->handle($assignment, $this->admin, ExamAttendanceRecord::OUTCOME_PRESENT, 'attendance-published'))
        ->toThrow(RuntimeException::class);
    expect(fn () => app(SessionQrCheckinAction::class)->handle($session, $this->admin, ApplicantQrCode::issue($attempt), 'qr-published'))
        ->toThrow(RuntimeException::class);

    $session->update(['status' => ExamSession::STATUS_CANCELLED]);
    expect(fn () => app(RecordExamAttendance::class)->handle($assignment, $this->admin, ExamAttendanceRecord::OUTCOME_PRESENT, 'attendance-cancelled'))
        ->toThrow(RuntimeException::class);
    expect(fn () => app(SessionQrCheckinAction::class)->handle($session->fresh(), $this->admin, ApplicantQrCode::issue($attempt), 'qr-cancelled'))
        ->toThrow(RuntimeException::class);
});

test('early start with duration anchors effective_end_at to started_at and supports extension', function (): void {
    [, $session] = attendanceLifecycleFixture($this->admin, 'attendance-lifecycle-duration@example.test');
    $futureStart = now()->addDay()->setTime(10, 30);
    $futureEnd = $futureStart->copy()->addMinutes(90);
    $session->update([
        'starts_at' => $futureStart,
        'effective_end_at' => $futureEnd,
    ]);

    expect($session->fresh()->scheduledDurationMinutes())->toBe(90);

    $this->actingAs($this->admin)
        ->post(route('exams.sessions.start', $session), [
            'expected_version' => $session->version,
            'duration_minutes' => 90,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $started = $session->fresh();
    expect($started->status)->toBe(ExamSession::STATUS_IN_PROGRESS)
        ->and($started->started_at->equalTo(now()))->toBeTrue()
        ->and($started->starts_at->equalTo($futureStart))->toBeTrue()
        ->and($started->effective_end_at->equalTo(now()->copy()->addMinutes(90)))->toBeTrue();

    $extendedEnd = $started->effective_end_at->copy()->addMinutes(15);
    $this->actingAs($this->admin)
        ->post(route('exams.sessions.extend', $started), [
            'expected_version' => $started->version,
            'effective_end_at' => $extendedEnd->toIso8601String(),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($started->fresh()->effective_end_at->equalTo($extendedEnd))->toBeTrue();
});

test('in-progress session stays open during overtime grace window and auto-closes once grace has lapsed', function (): void {
    [, $session, $assignment] = attendanceLifecycleFixture($this->admin, 'attendance-lifecycle-autoclose@example.test');
    $session->update([
        'starts_at' => now()->subMinutes(5),
        'effective_end_at' => now()->addMinutes(55),
    ]);

    $this->actingAs($this->admin)
        ->post(route('exams.sessions.start', $session), [
            'expected_version' => $session->version,
            'duration_minutes' => 60,
        ])
        ->assertRedirect();

    $this->travel(65)->minutes();

    $this->actingAs($this->admin)
        ->get(route('exams.sessions.monitoring', $session))
        ->assertOk();

    expect($session->fresh()->status)->toBe(ExamSession::STATUS_IN_PROGRESS)
        ->and($assignment->fresh()->attendanceRecord)->toBeNull();

    $this->travel(15)->minutes();

    $this->actingAs($this->admin)
        ->get(route('exams.sessions.monitoring', $session))
        ->assertOk();

    $reconciled = $session->fresh();
    expect($reconciled->status)->toBe(ExamSession::STATUS_CANCELLED)
        ->and($reconciled->closed_at)->not->toBeNull()
        ->and($assignment->fresh()->status)->toBe(ExamAssignment::STATUS_CANCELLED)
        ->and($assignment->fresh()->attendanceRecord?->outcome)->toBe(ExamAttendanceRecord::OUTCOME_ABSENT)
        ->and(GradingSession::query()->where('exam_session_id', $session->id)->exists())->toBeFalse();
});

test('reverts an unattended prematurely started session back to published when its scheduled start is still in the future', function (): void {
    [, $session, $assignment] = attendanceLifecycleFixture($this->admin, 'attendance-lifecycle-premature-revert@example.test');
    $scheduledStart = now()->addHours(5);
    $scheduledEnd = $scheduledStart->copy()->addMinutes(90);
    $session->update([
        'starts_at' => $scheduledStart,
        'effective_end_at' => $scheduledEnd,
    ]);

    $this->actingAs($this->admin)
        ->post(route('exams.sessions.start', $session), [
            'expected_version' => $session->version,
            'duration_minutes' => 60,
        ])
        ->assertRedirect();

    $this->travel(80)->minutes();

    $this->actingAs($this->admin)
        ->get(route('exams.sessions.monitoring', $session))
        ->assertOk();

    $reconciled = $session->fresh();
    expect($reconciled->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($reconciled->started_at)->toBeNull()
        ->and($reconciled->closed_at)->toBeNull()
        ->and($assignment->fresh()->attendanceRecord)->toBeNull();
});

test('revert-start returns an accidentally started zero-attendance session to published and rejects revert once attendance exists', function (): void {
    [, $session, $assignment] = attendanceLifecycleFixture($this->admin, 'attendance-lifecycle-revert@example.test');
    $scheduledStart = $session->starts_at->copy();

    $this->actingAs($this->admin)
        ->post(route('exams.sessions.start', $session), [
            'expected_version' => $session->version,
            'duration_minutes' => 90,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $started = $session->fresh();
    expect($started->status)->toBe(ExamSession::STATUS_IN_PROGRESS)
        ->and($started->started_at)->not->toBeNull();

    $this->actingAs($this->admin)
        ->post(route('exams.sessions.revert-start', $started), [
            'expected_version' => $started->version,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $reverted = $session->fresh();
    expect($reverted->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($reverted->started_at)->toBeNull()
        ->and($reverted->closed_at)->toBeNull()
        ->and($reverted->starts_at->equalTo($scheduledStart))->toBeTrue()
        ->and($reverted->effective_end_at->equalTo($scheduledStart->copy()->addMinutes(90)))->toBeTrue()
        ->and($assignment->fresh()->attendanceRecord)->toBeNull();

    $this->actingAs($this->admin)
        ->post(route('exams.sessions.start', $reverted), [
            'expected_version' => $reverted->version,
            'duration_minutes' => 90,
        ])
        ->assertRedirect();

    $startedAgain = $session->fresh();
    app(RecordExamAttendance::class)->handle($assignment, $this->admin, ExamAttendanceRecord::OUTCOME_PRESENT, 'att-revert-block');

    $this->actingAs($this->admin)
        ->post(route('exams.sessions.revert-start', $startedAgain), [
            'expected_version' => $startedAgain->version,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['revert_start']);

    expect($session->fresh()->status)->toBe(ExamSession::STATUS_IN_PROGRESS);
});
