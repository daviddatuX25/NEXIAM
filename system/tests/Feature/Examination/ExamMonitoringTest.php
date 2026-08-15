<?php

use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ProctorDuty;
use App\Models\User;

test('test administrator sees conduct sessions in monitoring index', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $session = createMonitoringSession($guidance, 'EX-MON-1');

    $this->actingAs($guidance)
        ->get(route('exams.monitoring'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/MonitoringIndex')
            ->where('page_state', 'ready')
            ->where('can_manage', true)
            ->has('sessions', 1)
            ->where('sessions.0.code', 'EX-MON-1')
            ->where('sessions.0.status', ExamSession::STATUS_PUBLISHED)
        );
});

test('proctor duty holder sees only their duty sessions in monitoring index', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    // Registrar clerk holds no exam-schedule capability; only the duty overlay
    // grants monitoring access.
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $dutySession = createMonitoringSession($guidance, 'EX-MON-DUTY');
    $otherSession = createMonitoringSession($guidance, 'EX-MON-OTHER', 'RM-LabA');

    ProctorDuty::query()->create([
        'exam_session_id' => $dutySession->id,
        'user_id' => $registrar->id,
        'status' => ProctorDuty::STATUS_ACTIVE,
        'idempotency_key' => 'mon-duty-1',
        'assigned_at' => now(),
    ]);

    $this->actingAs($registrar)
        ->get(route('exams.monitoring'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/MonitoringIndex')
            ->where('can_manage', false)
            ->has('sessions', 1)
            ->where('sessions.0.code', 'EX-MON-DUTY')
        );
});

test('monitoring index denied without schedule capability or active duty', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();

    $this->actingAs($hr)
        ->get(route('exams.monitoring'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/MonitoringIndex')
            ->where('page_state', 'denied'));
});

test('monitoring page shows roster with attendance and submission actions', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    // Registrar clerk lacks exam-schedule capability; an active duty overlay
    // grants conduct access (can_manage false, can_record true).
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $session = createMonitoringSession($guidance, 'EX-MON-SHOW');

    ProctorDuty::query()->create([
        'exam_session_id' => $session->id,
        'user_id' => $registrar->id,
        'status' => ProctorDuty::STATUS_ACTIVE,
        'idempotency_key' => 'mon-show-1',
        'assigned_at' => now(),
    ]);

    $this->actingAs($registrar)
        ->get(route('exams.sessions.monitoring', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/Monitoring')
            ->where('can_manage', false)
            ->where('can_record', true)
            ->where('session.code', 'EX-MON-SHOW')
            ->has('roster', 1)
            ->where('roster.0.attendance', null)
            ->where('roster.0.submitted', false)
        );
});

test('monitoring page denied for non-duty holder without schedule capability', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $session = createMonitoringSession($guidance, 'EX-MON-DENY');

    $this->actingAs($hr)
        ->get(route('exams.sessions.monitoring', $session))
        ->assertForbidden();
});

test('duty holder records attendance and submission from monitoring page', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    // Registrar clerk holds no exam-schedule capability; the active duty
    // overlay grants conduct recording for this session only.
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $session = createMonitoringSession($guidance, 'EX-MON-RECORD');

    ProctorDuty::query()->create([
        'exam_session_id' => $session->id,
        'user_id' => $registrar->id,
        'status' => ProctorDuty::STATUS_ACTIVE,
        'idempotency_key' => 'mon-record-1',
        'assigned_at' => now(),
    ]);

    $assignment = ExamAssignment::query()->where('exam_session_id', $session->id)->active()->firstOrFail();

    $this->actingAs($registrar)
        ->post(route('exams.sessions.monitoring.attendance', [$session, $assignment]), [
            'outcome' => 'present',
            'idempotency_key' => 'mon-att-1',
        ])
        ->assertRedirect();

    expect(ExamAttendanceRecord::query()->where('exam_assignment_id', $assignment->id)->exists())->toBeTrue();

    $this->actingAs($registrar)
        ->post(route('exams.sessions.monitoring.submission', [$session, $assignment]), [
            'idempotency_key' => 'mon-sub-1',
        ])
        ->assertRedirect();

    expect($assignment->fresh()->submissionRecord()->exists())->toBeTrue();
});

function createMonitoringSession(User $guidance, string $code, string $roomCode = 'RM-101'): ExamSession
{
    $cycle = \App\Models\Foundation\AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = \App\Models\Foundation\Room::query()->where('code', $roomCode)->firstOrFail();

    test()->actingAs($guidance)
        ->post(route('exams.sessions.store'), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => $code,
            'starts_at' => now()->subHours(2)->toDateTimeString(),
            'effective_end_at' => now()->addMinutes(30)->toDateTimeString(),
        ])
        ->assertRedirect();

    $session = ExamSession::query()->where('code', $code)->firstOrFail();

    $result = submitSyntheticApplicant("{$code}@example.test", "Monitoring {$code}");
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    test()->actingAs($registrar)
        ->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1])
        ->assertRedirect();

    test()->actingAs($guidance)
        ->post(route('exams.sessions.assign', $session), [
            'admission_attempt_id' => $result['attempt']->id,
        ])
        ->assertRedirect();

    test()->actingAs($guidance)
        ->post(route('exams.sessions.publish', $session), ['expected_version' => 1])
        ->assertRedirect();

    return $session->fresh();
}
