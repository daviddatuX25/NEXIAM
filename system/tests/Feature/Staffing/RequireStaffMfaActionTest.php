<?php

declare(strict_types=1);

use App\Auth\Actions\InvalidateStaffAuthentication;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AuditEvent;
use App\Models\User;
use App\Staffing\Actions\DisableStaffMfa;
use App\Staffing\Actions\RequireStaffMfa;

test('HR can require MFA for staff and invalidates their authentication state', function (): void {
    seedE2Foundation();

    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $target = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $state = StaffAuthenticationState::query()->where('user_id', $target->id)->firstOrFail();
    $state->forceFill(['mfa_required' => false])->save();
    $sessionVersion = (int) $state->session_version;
    $rememberToken = $target->remember_token;

    $invalidation = Mockery::mock(InvalidateStaffAuthentication::class)->makePartial();
    $invalidation->shouldReceive('handle')
        ->once()
        ->withArgs(fn (User $user, StaffAuthenticationState $lockedState): bool => $user->id === $target->id
            && $lockedState->user_id === $target->id)
        ->passthru();
    $this->app->instance(InvalidateStaffAuthentication::class, $invalidation);

    $updated = app(RequireStaffMfa::class)->handle($hr, $target, 'Protect administrative access');

    expect($updated->mfa_required)->toBeTrue()
        ->and($updated->session_version)->toBe($sessionVersion + 1)
        ->and(User::query()->findOrFail($target->id)->remember_token)->not->toBe($rememberToken);

    $audit = AuditEvent::query()->where('event_type', 'security.staff_mfa_enforced')->firstOrFail();
    expect($audit->actor)->toBe((string) $hr->id)
        ->and($audit->payload['user_id'])->toBe($target->id)
        ->and($audit->payload['reason'])->toBe('Protect administrative access')
        ->and($audit->payload['already_required'])->toBeFalse();
});

test('repeating Require MFA records audit without invalidating authentication again', function (): void {
    seedE2Foundation();

    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $target = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $state = StaffAuthenticationState::query()->where('user_id', $target->id)->firstOrFail();
    $state->forceFill(['mfa_required' => true])->save();
    $sessionVersion = (int) $state->session_version;
    $factorGeneration = $state->two_factor_generation;

    app(RequireStaffMfa::class)->handle($hr, $target);

    $updated = app(RequireStaffMfa::class)->handle($hr, $target, 'Repeated request');

    expect($updated->mfa_required)->toBeTrue()
        ->and($updated->session_version)->toBe($sessionVersion)
        ->and($updated->two_factor_generation)->toBe($factorGeneration)
        ->and(AuditEvent::query()->where('event_type', 'security.staff_mfa_enforced')->count())->toBe(2);
});

test('HR and office heads can require and turn off MFA on their own account without invalidating their own active session epoch', function (): void {
    seedE2Foundation();

    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $hrState = StaffAuthenticationState::query()->where('user_id', $hr->id)->firstOrFail();
    $hrVersion = (int) $hrState->session_version;

    $enforced = app(RequireStaffMfa::class)->handle($hr, $hr, 'Self-enforced MFA');
    expect($enforced->mfa_required)->toBeTrue()
        ->and($enforced->mfa_exempt)->toBeFalse()
        ->and((int) $enforced->session_version)->toBe($hrVersion);

    $disabled = app(DisableStaffMfa::class)->handle($hr, $hr, 'Self-disabled MFA');
    expect($disabled->mfa_required)->toBeFalse()
        ->and($disabled->mfa_exempt)->toBeTrue()
        ->and((int) $disabled->session_version)->toBe($hrVersion);

    $lead = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $leadEnforced = app(RequireStaffMfa::class)->handle($lead, $lead, 'Counselor self MFA on');
    expect($leadEnforced->mfa_required)->toBeTrue()
        ->and($leadEnforced->mfa_exempt)->toBeFalse();

    $leadDisabled = app(DisableStaffMfa::class)->handle($lead, $lead, 'Counselor self MFA off');
    expect($leadDisabled->mfa_required)->toBeFalse()
        ->and($leadDisabled->mfa_exempt)->toBeTrue();
});

test('an institutional administrator without HR authority cannot require MFA', function (): void {
    seedE2Foundation();

    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $target = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    expect(fn () => app(RequireStaffMfa::class)->handle($admin, $target))
        ->toThrow(RuntimeException::class, 'Actor cannot require MFA for this staff account.');
});

test('office head can require MFA for subordinate staff in the same office', function (): void {
    seedE2Foundation();

    $lead = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $subordinate = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $state = StaffAuthenticationState::query()->where('user_id', $subordinate->id)->firstOrFail();
    $state->forceFill(['mfa_required' => false])->save();

    $updated = app(RequireStaffMfa::class)->handle($lead, $subordinate, 'Office security mandate');

    expect($updated->mfa_required)->toBeTrue();
});

test('office head cannot require MFA for staff in another office', function (): void {
    seedE2Foundation();

    $lead = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $foreign = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    expect(fn () => app(RequireStaffMfa::class)->handle($lead, $foreign))
        ->toThrow(RuntimeException::class, 'Actor cannot require MFA for this staff account.');
});

test('subordinate cannot require MFA for their office head', function (): void {
    seedE2Foundation();

    $subordinate = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $lead = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    expect(fn () => app(RequireStaffMfa::class)->handle($subordinate, $lead))
        ->toThrow(RuntimeException::class, 'Actor cannot require MFA for this staff account.');
});

test('office head can turn off MFA for subordinate in same office and subordinate cannot toggle MFA on self or head', function (): void {
    seedE2Foundation();

    $lead = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $subordinate = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $state = StaffAuthenticationState::query()->where('user_id', $subordinate->id)->firstOrFail();
    $state->forceFill([
        'mfa_required' => true,
        'mfa_exempt' => false,
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
    ])->save();

    $disabled = app(DisableStaffMfa::class)->handle($lead, $subordinate, 'Office head turned off MFA');
    expect($disabled->mfa_required)->toBeFalse()
        ->and($disabled->mfa_exempt)->toBeTrue()
        ->and($disabled->hasConfirmedTwoFactor())->toBeFalse()
        ->and($disabled->two_factor_secret)->toBeNull();

    expect(fn () => app(RequireStaffMfa::class)->handle($subordinate, $subordinate))
        ->toThrow(RuntimeException::class, 'Actor cannot require MFA for this staff account.');
    expect(fn () => app(DisableStaffMfa::class)->handle($subordinate, $subordinate))
        ->toThrow(RuntimeException::class, 'Actor cannot disable MFA for this staff account.');
    expect(fn () => app(DisableStaffMfa::class)->handle($subordinate, $lead))
        ->toThrow(RuntimeException::class, 'Actor cannot disable MFA for this staff account.');
});

test('HR can require MFA for office heads', function (): void {
    seedE2Foundation();

    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $lead = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $state = StaffAuthenticationState::query()->where('user_id', $lead->id)->firstOrFail();
    $state->forceFill(['mfa_required' => false])->save();

    $updated = app(RequireStaffMfa::class)->handle($hr, $lead, 'Institutional policy');

    expect($updated->mfa_required)->toBeTrue();
});
