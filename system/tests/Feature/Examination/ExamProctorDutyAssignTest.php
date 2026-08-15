<?php

use App\Models\Examination\ExamSession;
use App\Models\Examination\ProctorDuty;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;

function proctorGuidanceCounselor(): User
{
    return User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
}

function proctorGuidanceClerk(): User
{
    return User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
}

function proctorCreateSession(User $guidance, string $code = 'EX-PR-1'): ExamSession
{
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    test()->actingAs($guidance)
        ->post(route('exams.sessions.store'), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => $code,
            'date' => now()->addDays(4)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
        ])
        ->assertRedirect();

    return ExamSession::query()->where('code', $code)->firstOrFail();
}

test('counselor show exposes eligible proctors and can assign clerk duty', function (): void {
    seedE2Foundation();
    $counselor = proctorGuidanceCounselor();
    $clerk = proctorGuidanceClerk();
    $session = proctorCreateSession($counselor, 'EX-PR-ASSIGN');

    $this->actingAs($counselor)
        ->get(route('exams.sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can_assign_proctor', true)
            ->where('eligible_proctors', fn ($rows) => collect($rows)->contains(
                fn ($row) => (int) ($row['id'] ?? 0) === $clerk->id
            ))
        );

    $this->actingAs($counselor)
        ->post(route('proctor-duty.assign', $session), [
            'proctor_user_id' => $clerk->id,
            'idempotency_key' => 'proctor-assign-1',
        ])
        ->assertRedirect();

    $duty = ProctorDuty::query()
        ->where('exam_session_id', $session->id)
        ->where('user_id', $clerk->id)
        ->where('status', ProctorDuty::STATUS_ACTIVE)
        ->first();

    expect($duty)->not->toBeNull()
        ->and($clerk->getRoleNames()->contains('proctor'))->toBeFalse();

    $this->actingAs($counselor)
        ->post(route('proctor-duty.revoke', $duty))
        ->assertRedirect();

    expect($duty->fresh()->status)->toBe(ProctorDuty::STATUS_REVOKED);
});

test('registrar cannot assign proctor and counselor cannot assign registrar', function (): void {
    seedE2Foundation();
    $counselor = proctorGuidanceCounselor();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $session = proctorCreateSession($counselor, 'EX-PR-DENY');

    $this->actingAs($registrar)
        ->post(route('proctor-duty.assign', $session), [
            'proctor_user_id' => proctorGuidanceClerk()->id,
            'idempotency_key' => 'proctor-deny-reg-1',
        ])
        ->assertForbidden();

    $this->actingAs($counselor)
        ->post(route('proctor-duty.assign', $session), [
            'proctor_user_id' => $registrar->id,
            'idempotency_key' => 'proctor-deny-target-1',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('proctor');

    expect(
        ProctorDuty::query()->where('exam_session_id', $session->id)->where('status', ProctorDuty::STATUS_ACTIVE)->count()
    )->toBe(0);
});

test('counselor can assign guidance support staff as proctor', function (): void {
    seedE2Foundation();
    $counselor = proctorGuidanceCounselor();
    $support = User::query()->where('email', 'guidance.support@nexiam.test')->firstOrFail();
    $session = proctorCreateSession($counselor, 'EX-PR-SUPPORT');

    $this->actingAs($counselor)
        ->get(route('exams.sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('eligible_proctors', fn ($rows) => collect($rows)->contains(
                fn ($row) => (int) ($row['id'] ?? 0) === $support->id
            ))
        );

    $this->actingAs($counselor)
        ->post(route('proctor-duty.assign', $session), [
            'proctor_user_id' => $support->id,
            'idempotency_key' => 'proctor-assign-support-1',
        ])
        ->assertRedirect();

    expect(
        ProctorDuty::query()
            ->where('exam_session_id', $session->id)
            ->where('user_id', $support->id)
            ->where('status', ProctorDuty::STATUS_ACTIVE)
            ->exists()
    )->toBeTrue()
        ->and($support->getRoleNames()->contains('proctor'))->toBeFalse();
});
