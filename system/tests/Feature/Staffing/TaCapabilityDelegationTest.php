<?php

use App\Authorization\CapabilityCatalog;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ProctorDuty;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;

function taGuidanceCounselor(): User
{
    return User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
}

function taGuidanceClerk(): User
{
    return User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
}

function taCreateSession(User $actor, string $code): ExamSession
{
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    test()->actingAs($actor)
        ->post(route('exams.sessions.store'), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => $code,
            'date' => now()->addDays(5)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
        ])
        ->assertRedirect();

    return ExamSession::query()->where('code', $code)->firstOrFail();
}

test('personnel page shows ta overlay and counselor grants then clerk assigns proctor; revoke restores', function (): void {
    seedE2Foundation();
    $counselor = taGuidanceCounselor();
    $clerk = taGuidanceClerk();

    $this->actingAs($counselor)
        ->get(route('staff.personnel.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Staff/Personnel/Index')
            ->where('can_delegate_ta', true)
            ->where('users.data', fn ($rows) => collect($rows)->contains(
                fn ($row) => (int) ($row['id'] ?? 0) === $clerk->id
                    && ($row['ta_status'] ?? '') === 'none'
                    && ($row['can_delegate_ta'] ?? false) === true
            ))
            ->where('users.data', fn ($rows) => collect($rows)->contains(
                fn ($row) => (int) ($row['id'] ?? 0) === $counselor->id
                    && ($row['ta_status'] ?? '') === 'inherent'
            ))
        );

    $this->actingAs($counselor)
        ->post(route('staff.personnel.grant-ta', $clerk))
        ->assertRedirect();

    $clerk->refresh();
    expect($clerk->getRoleNames()->contains('test_administrator'))->toBeFalse()
        ->and($clerk->hasPermissionTo(CapabilityCatalog::GUIDANCE_PROCTOR_ASSIGN))->toBeTrue()
        ->and($clerk->hasPermissionTo(CapabilityCatalog::GUIDANCE_TA_DELEGATE))->toBeFalse();

    $session = taCreateSession($counselor, 'EX-TA-DELEGATE');

    $this->actingAs($clerk)
        ->post(route('proctor-duty.assign', $session), [
            'proctor_user_id' => User::query()->where('email', 'guidance.support@nexiam.test')->firstOrFail()->id,
            'idempotency_key' => 'ta-delegate-proctor-1',
        ])
        ->assertRedirect();

    expect(
        ProctorDuty::query()
            ->where('exam_session_id', $session->id)
            ->where('status', ProctorDuty::STATUS_ACTIVE)
            ->exists()
    )->toBeTrue();

    $this->actingAs($counselor)
        ->post(route('staff.personnel.revoke-ta', $clerk))
        ->assertRedirect();

    $clerk->refresh();
    expect($clerk->hasPermissionTo(CapabilityCatalog::GUIDANCE_PROCTOR_ASSIGN))->toBeFalse()
        ->and($clerk->hasPermissionTo(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE))->toBeTrue();

    $this->actingAs($clerk)
        ->post(route('proctor-duty.assign', $session), [
            'proctor_user_id' => User::query()->where('email', 'guidance.support@nexiam.test')->firstOrFail()->id,
            'idempotency_key' => 'ta-delegate-proctor-2',
        ])
        ->assertForbidden();
});

test('hr and registrar cannot grant ta; counselor cannot grant registrar', function (): void {
    seedE2Foundation();
    $counselor = taGuidanceCounselor();
    $clerk = taGuidanceClerk();
    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($hr)
        ->post(route('staff.personnel.grant-ta', $clerk))
        ->assertForbidden();

    $this->actingAs($registrar)
        ->post(route('staff.personnel.grant-ta', $clerk))
        ->assertForbidden();

    $this->actingAs($counselor)
        ->post(route('staff.personnel.grant-ta', $registrar))
        ->assertRedirect()
        ->assertSessionHasErrors('ta');
});

test('personnel page hides ta overlay column actions for hr without delegate cap', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();

    $this->actingAs($hr)
        ->get(route('staff.personnel.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can_delegate_ta', false)
            ->where('users.data', fn ($rows) => collect($rows)->every(
                fn ($row) => ($row['can_delegate_ta'] ?? false) === false
            ))
        );
});

test('guidance.ta.delegate stays outside the ta capability set', function (): void {
    expect(CapabilityCatalog::testAdministratorCapabilities())
        ->not->toContain(CapabilityCatalog::GUIDANCE_TA_DELEGATE)
        ->and(CapabilityCatalog::roleHelperGrants()['guidance_counselor'])
        ->toContain(CapabilityCatalog::GUIDANCE_TA_DELEGATE)
        ->and(CapabilityCatalog::roleHelperGrants()['guidance_clerk'])
        ->not->toContain(CapabilityCatalog::GUIDANCE_TA_DELEGATE);
});
