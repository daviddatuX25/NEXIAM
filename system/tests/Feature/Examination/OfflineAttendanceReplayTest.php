<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\StartExamSession;
use App\Examination\Actions\SyncOfflineAttendance;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    seedE2Foundation();
    $this->freezeSecond();
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $attempt = app(AcceptAdmissionAttempt::class)->handle(submitSyntheticApplicant('offline-replay@example.test', 'Offline')['attempt'], $this->actor, 1);
    $session = app(CreateExamSession::class)->handle(
        AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail(),
        Room::query()->where('code', 'RM-101')->firstOrFail(),
        'OFFLINE-REPLAY', now()->toDateTimeString(), now()->addHour()->toDateTimeString(), $this->actor,
        ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
    );
    $this->assignment = app(AssignApplicantToExamSession::class)->handle($session, $attempt, $this->actor);
    $session = app(PublishExamSession::class)->handle($session, $this->actor, $session->version);
    $this->exam = app(StartExamSession::class)->handle($session, $this->actor, $session->version);
    $this->command = [
        'expected_actor_id' => $this->actor->id,
        'assignment_id' => $this->assignment->id,
        'idempotency_key' => (string) Str::uuid(),
        'outcome' => 'present',
        'expected_outcome' => null,
        'expected_attendance_id' => null,
        'expected_recorded_at' => null,
        'expected_started_at' => $this->exam->started_at->toIso8601String(),
    ];
});

test('offline attendance applies once and a lost response replay does not overwrite a later correction', function (): void {
    $sync = app(SyncOfflineAttendance::class);
    expect($sync->handle($this->exam, $this->actor, $this->command)['status'])->toBe('applied');
    app(RecordExamAttendance::class)->handle($this->assignment, $this->actor, 'absent', 'online-correction');
    expect($sync->handle($this->exam, $this->actor, $this->command)['status'])->toBe('already_applied')
        ->and($this->assignment->fresh()->attendanceRecord->outcome)->toBe('absent')
        ->and(ExamAttendanceRecord::query()->where('exam_assignment_id', $this->assignment->id)->count())->toBe(1);
});

test('offline attendance rejects an intervening outcome without committing a receipt', function (): void {
    app(RecordExamAttendance::class)->handle($this->assignment, $this->actor, 'absent', 'other-station');
    $this->actingAs($this->actor)->postJson(route('exams.sessions.monitoring.offline-attendance', $this->exam), $this->command)
        ->assertConflict()->assertJsonPath('status', 'conflict');
    expect($this->assignment->fresh()->attendanceRecord->outcome)->toBe('absent');
    $this->assertDatabaseMissing('examination_action_execution_receipts', ['execution_id' => $this->command['idempotency_key']]);
});

test('offline attendance rejects closed sessions and a different started sitting', function (): void {
    $this->exam->update(['closed_at' => now(), 'status' => ExamSession::STATUS_COMPLETED]);
    expect(fn () => app(SyncOfflineAttendance::class)->handle($this->exam, $this->actor, $this->command))->toThrow(DomainException::class);
    $this->exam->update(['closed_at' => null, 'status' => ExamSession::STATUS_IN_PROGRESS, 'started_at' => now()->addMinute()]);
    expect(fn () => app(SyncOfflineAttendance::class)->handle($this->exam, $this->actor, $this->command))->toThrow(DomainException::class);
    expect($this->assignment->fresh()->attendanceRecord)->toBeNull();
});

test('offline attendance rejects changed payload keys and actors without current authority', function (): void {
    $sync = app(SyncOfflineAttendance::class);
    $sync->handle($this->exam, $this->actor, $this->command);
    expect(fn () => $sync->handle($this->exam, $this->actor, [...$this->command, 'outcome' => 'absent']))->toThrow(DomainException::class);
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    Role::findByName('guidance_clerk')->revokePermissionTo(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);
    expect(fn () => $sync->handle($this->exam, $clerk, $this->command))->toThrow(AuthorizationException::class);
    expect(fn () => $sync->handle($this->exam, $clerk, [...$this->command, 'expected_actor_id' => $clerk->id]))->toThrow(AuthorizationException::class);
    $clerk->givePermissionTo(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);
    expect(fn () => $sync->handle($this->exam, $clerk, $this->command))->toThrow(AuthorizationException::class);
    expect($this->assignment->fresh()->attendanceRecord->outcome)->toBe('present');
});

test('offline attendance and receipt roll back if audit append fails', function (): void {
    $this->mock(RecordAuditEvent::class)->shouldReceive('handle')->andThrow(new RuntimeException('audit unavailable'));
    expect(fn () => app(SyncOfflineAttendance::class)->handle($this->exam, $this->actor, $this->command))->toThrow(RuntimeException::class, 'audit unavailable');
    expect($this->assignment->fresh()->attendanceRecord)->toBeNull()
        ->and(DB::table('examination_action_execution_receipts')->where('execution_id', $this->command['idempotency_key'])->exists())->toBeFalse();
});
