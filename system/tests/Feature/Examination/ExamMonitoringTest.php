<?php

use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\StartExamSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ProctorDuty;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;

function monitoringStaffSession(User $user): array
{
    $state = $user->staffProfile?->authenticationState;

    return [
        'auth.surface' => 'staff',
        'auth.session_version' => (int) ($state?->session_version ?? 1),
    ];
}

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
test('test administrator sees completed sessions in history_sessions and persistent telemetry on standby', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $session = createMonitoringSession($guidance, 'EX-MON-HIST');

    $assignment = $session->assignments()->firstOrFail();
    ExamAttendanceRecord::query()->create([
        'exam_assignment_id' => $assignment->id,
        'recorded_by_user_id' => $guidance->id,
        'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
        'idempotency_key' => 'hist-att-1',
        'recorded_at' => now(),
    ]);

    // Close session to move it to completed history
    $session->update([
        'status' => ExamSession::STATUS_COMPLETED,
        'closed_at' => now(),
    ]);
    $this->actingAs($guidance)
        ->get(route('exams.monitoring'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/MonitoringIndex')
            ->where('page_state', 'ready')
            ->where('can_manage', true)
            ->has('sessions', 0)
            ->has('active_sessions', 0)
            ->has('history_sessions.data', 1)
            ->where('history_sessions.data.0.code', 'EX-MON-HIST')
            ->where('history_sessions.data.0.status', ExamSession::STATUS_COMPLETED)
            ->where('history_sessions.data.0.submission_status', 'missing_records')
            ->where('history_sessions.total', 1)
            ->has('telemetry')
            ->where('telemetry.is_live', false)
            ->where('telemetry.completed_sessions', 1)
        );
});

test('historical duty holder without test administrator capability cannot see monitoring sessions', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $dutySession = createMonitoringSession($guidance, 'EX-MON-HISTORICAL-DUTY');
    createMonitoringSession($guidance, 'EX-MON-OTHER', 'RM-LabA');

    ProctorDuty::query()->create([
        'exam_session_id' => $dutySession->id,
        'user_id' => $registrar->id,
        'status' => ProctorDuty::STATUS_ACTIVE,
        'idempotency_key' => 'mon-duty-1',
        'assigned_at' => now(),
    ]);

    $this->actingAs($registrar)
        ->get(route('exams.monitoring'))
        ->assertForbidden();
});

test('monitoring index denied without schedule capability', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();

    $this->actingAs($hr)
        ->withSession(monitoringStaffSession($hr))
        ->get(route('exams.monitoring'))
        ->assertForbidden();
});

test('test administrator sees roster and conduct actions without session duty', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $session = createMonitoringSession($guidance, 'EX-MON-SHOW');

    $this->actingAs($guidance)
        ->get(route('exams.sessions.monitoring', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/Monitoring')
            ->where('can_manage', true)
            ->where('can_record', false)
            ->where('session.code', 'EX-MON-SHOW')
            ->has('roster', 1)
            ->has('roster.0.sex')
            ->where('roster.0.attendance', null)
            ->where('roster.0.submitted', false)
        );

    app(StartExamSession::class)->handle($session, $guidance, $session->version);

    $this->actingAs($guidance)
        ->get(route('exams.sessions.monitoring', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can_record', true)
            ->where('roster.0.attendance', null)
        );
});

test('monitoring page denied for non-test-administrator without capability', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $session = createMonitoringSession($guidance, 'EX-MON-DENY');

    $this->actingAs($hr)
        ->get(route('exams.sessions.monitoring', $session))
        ->assertForbidden();
});

test('unassigned test administrator records attendance and submission from monitoring page', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $session = createMonitoringSession($guidance, 'EX-MON-RECORD');
    app(StartExamSession::class)->handle($session, $guidance, $session->version);

    $assignment = ExamAssignment::query()->where('exam_session_id', $session->id)->active()->firstOrFail();

    $this->actingAs($guidance)
        ->post(route('exams.sessions.monitoring.attendance', [$session, $assignment]), [
            'outcome' => 'present',
            'idempotency_key' => 'mon-att-1',
        ])
        ->assertRedirect();

    expect(ExamAttendanceRecord::query()->where('exam_assignment_id', $assignment->id)->exists())->toBeTrue();

    $this->actingAs($guidance)
        ->post(route('exams.sessions.monitoring.submission', [$session, $assignment]), [
            'idempotency_key' => 'mon-sub-1',
        ])
        ->assertRedirect();

    expect($assignment->fresh()->submissionRecord()->exists())->toBeTrue();
});

test('monitoring page projects eligible inbound accommodate_candidates and outbound transfer_target_sessions for test administrator', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $sessionA = createMonitoringSession($guidance, 'EX-MON-CAND-A');
    $sessionB = createMonitoringSession($guidance, 'EX-MON-CAND-B', 'RM-LabA');

    $applicantInB = $sessionB->assignments()->active()->firstOrFail()->admissionAttempt;

    $this->actingAs($guidance)
        ->get(route('exams.sessions.monitoring', $sessionA))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/Monitoring')
            ->where('can_manage', true)
            ->has('accommodate_candidates', 1)
            ->where('accommodate_candidates.0.reference_code', $applicantInB->reference_code)
            ->where('accommodate_candidates.0.current_session_code', 'EX-MON-CAND-B')
            ->where('accommodate_candidates.0.source_kind', 'scheduled_other')
            ->has('transfer_target_sessions', 1)
            ->where('transfer_target_sessions.0.code', 'EX-MON-CAND-B')
        );

    $this->actingAs($guidance)
        ->postJson(route('exams.sessions.monitoring.accommodate', $sessionA), [
            'reference_code' => $applicantInB->reference_code,
            'reason_category' => 'travel_distance',
            'reason_notes' => 'Transferred into EX-MON-CAND-A via monitoring modal',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'ok');

    expect(ExamAssignment::query()->active()->where('exam_session_id', $sessionA->id)->where('admission_attempt_id', $applicantInB->id)->exists())->toBeTrue();
});

test('accommodate endpoint supports bulk reference_codes transfer across sessions', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $sessionA = createMonitoringSession($guidance, 'EX-MON-BULK-A');
    $sessionB = createMonitoringSession($guidance, 'EX-MON-BULK-B', 'RM-LabA');

    $extra = submitSyntheticApplicant('bulk-extra@example.test', 'BulkExtra');
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $this->actingAs($registrar)
        ->post(route('admissions.accept', $extra['attempt']), ['expected_version' => 1])
        ->assertRedirect();

    $applicantInB = $sessionB->assignments()->active()->firstOrFail()->admissionAttempt;

    $this->actingAs($guidance)
        ->postJson(route('exams.sessions.monitoring.accommodate', $sessionA), [
            'reference_codes' => [$applicantInB->reference_code, $extra['attempt']->reference_code],
            'reason_category' => 'travel_distance',
            'reason_notes' => 'Bulk accommodation into EX-MON-BULK-A',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('count', 2);

    expect(ExamAssignment::query()->active()->where('exam_session_id', $sessionA->id)->whereIn('admission_attempt_id', [$applicantInB->id, $extra['attempt']->id])->count())->toBe(2);
});

function createMonitoringSession(User $guidance, string $code, string $roomCode = 'RM-101'): ExamSession
{
    $guidance->givePermissionTo(CapabilityCatalog::SETUP_INSTITUTION_MANAGE);
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', $roomCode)->firstOrFail();

    test()->actingAs($guidance)
        ->post(route('exams.sessions.store'), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => $code,
            'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
            'starts_at' => now()->subHours(2)->toDateTimeString(),
            'effective_end_at' => now()->addMinutes(30)->toDateTimeString(),
        ])
        ->assertSessionHasNoErrors()
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
        ->post(route('exams.sessions.publish', $session), [
            'expected_version' => $session->fresh()->version,
        ])
        ->assertRedirect();

    return $session->fresh();
}
