<?php

declare(strict_types=1);

use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('staff with confirmed TOTP is redirected to the two-factor challenge', function (): void {
    seedE2Foundation();
    $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    StaffAuthenticationState::query()->where('user_id', $user->id)->firstOrFail()->forceFill([
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'mfa_required' => true,
        'mfa_exempt' => false,
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
        'two_factor_generation' => (string) Str::uuid(),
    ])->save();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/two-factor-challenge');

    $this->assertGuest();
});

test('staff with confirmed TOTP is redirected to the two-factor challenge even when mandatory MFA policy is disabled', function (): void {
    config(['ciam.mfa.enabled' => false]);
    seedE2Foundation();
    $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    StaffAuthenticationState::query()->where('user_id', $user->id)->firstOrFail()->forceFill([
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'mfa_required' => true,
        'mfa_exempt' => false,
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
        'two_factor_generation' => (string) Str::uuid(),
    ])->save();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/two-factor-challenge');

    $this->assertGuest();
});

test('staff requiring MFA without a factor is sent to account setup with an enrollment attempt', function (): void {
    config(['ciam.mfa.enabled' => true]);
    seedE2Foundation();
    $user = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    StaffAuthenticationState::query()->where('user_id', $user->id)->firstOrFail()->forceFill([
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'mfa_required' => true,
        'mfa_exempt' => false,
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ])->save();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/staff/account-setup');

    $this->assertGuest();
    $attempt = StaffAuthenticationAttempt::query()->where('user_id', $user->id)->sole();
    expect($attempt->purpose)->toBe('mfa_enrollment')
        ->and(session('staff.pending_attempt_id'))->toBe($attempt->id);

    $this->get('/staff/account-setup')->assertInertia(fn ($page) => $page
        ->component('Staff/AccountSetup')
        ->where('purpose', 'mfa_enrollment')
        ->where('two_factor_required', true)
        ->where('password_set', true));

    $this->post('/staff/account-setup/complete')->assertForbidden();
    $this->assertGuest();
});

test('staff exempt from MFA without a factor is logged in directly', function (): void {
    seedE2Foundation();
    $user = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    StaffAuthenticationState::query()->where('user_id', $user->id)->firstOrFail()->forceFill([
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'mfa_required' => false,
        'mfa_exempt' => true,
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ])->save();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/staff');

    $this->assertAuthenticatedAs($user);
});
test('successful staff login ignores an old staff destination and clears the intended URL', function (): void {
    seedE2Foundation();
    $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $user->authenticationState()->update([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
        'mfa_exempt' => true,
    ]);

    $this->withSession(['url.intended' => '/setup/cycles'])
        ->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
        ->assertRedirect(route('staff.home'))
        ->assertSessionMissing('url.intended');
});

test('successful staff login ignores an applicant destination and clears the intended URL', function (): void {
    seedE2Foundation();
    $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $user->authenticationState()->update([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
        'mfa_exempt' => true,
    ]);

    $this->withSession(['url.intended' => '/applicant/portal'])
        ->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
        ->assertRedirect(route('staff.home'))
        ->assertSessionMissing('url.intended');
});
