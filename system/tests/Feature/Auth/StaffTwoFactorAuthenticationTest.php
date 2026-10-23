<?php

use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffTwoFactorRecoveryCode;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/** @return array{user: User, secret: string} */
function staffTwoFactorFixture(): array
{
    seedE2Foundation();

    $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $secret = 'JBSWY3DPEHPK3PXP';

    StaffAuthenticationState::query()->firstOrNew(['user_id' => $user->id])->forceFill([
        'user_id' => $user->id,
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'session_version' => 1,
        'mfa_required' => true,
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
        'two_factor_generation' => (string) Str::uuid(),
        'two_factor_last_accepted_step' => null,
        'recovery_generation' => (string) Str::uuid(),
    ])->save();

    return ['user' => $user, 'secret' => $secret];
}

function beginStaffTwoFactorChallenge(TestCase $test, User $user): TestResponse
{
    return $test->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/two-factor-challenge');
}

test('staff with an active TOTP factor must complete the challenge after password verification', function () {
    ['user' => $user] = staffTwoFactorFixture();

    beginStaffTwoFactorChallenge($this, $user)
        ->assertSessionHas('staff.pending_attempt_id');

    $this->assertGuest();
});

test('a correct six digit TOTP completes staff authentication', function () {
    ['user' => $user, 'secret' => $secret] = staffTwoFactorFixture();
    beginStaffTwoFactorChallenge($this, $user);

    $code = app(Google2FA::class)->getCurrentOtp($secret);

    $this->post(route('two-factor.login.store'), ['code' => $code])
        ->assertRedirect('/staff');

    $this->assertAuthenticated();
    expect(session('auth.surface'))->toBe('staff');
});

test('a confirmed TOTP factor completes authentication even when mandatory MFA policy is disabled', function () {
    config(['ciam.mfa.enabled' => false]);
    ['user' => $user, 'secret' => $secret] = staffTwoFactorFixture();

    beginStaffTwoFactorChallenge($this, $user)
        ->assertSessionHas('staff.pending_attempt_id');

    $code = app(Google2FA::class)->getCurrentOtp($secret);

    $this->post(route('two-factor.login.store'), ['code' => $code])
        ->assertRedirect('/staff');

    $this->assertAuthenticatedAs($user);
    expect(session('auth.surface'))->toBe('staff');
});

test('an accepted TOTP code cannot be replayed in the same time step', function () {
    ['user' => $user, 'secret' => $secret] = staffTwoFactorFixture();
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    beginStaffTwoFactorChallenge($this, $user);
    $this->post(route('two-factor.login.store'), ['code' => $code])->assertRedirect('/staff');

    auth()->logout();
    beginStaffTwoFactorChallenge($this, $user);

    $this->post(route('two-factor.login.store'), ['code' => $code])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('a recovery code authenticates once and is marked consumed', function () {
    ['user' => $user] = staffTwoFactorFixture();
    $state = StaffAuthenticationState::query()->where('user_id', $user->id)->firstOrFail();
    $recoveryCode = '0123456789abcdef0123456789abcdef';
    $record = StaffTwoFactorRecoveryCode::query()->create([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'generation' => $state->recovery_generation,
        'code_digest' => hash('sha256', $recoveryCode),
    ]);

    beginStaffTwoFactorChallenge($this, $user);
    $this->post(route('two-factor.login.store'), ['recovery_code' => $recoveryCode])
        ->assertRedirect('/staff');

    $this->assertAuthenticated();
    expect($record->fresh()->consumed_at)->not->toBeNull();

    auth()->logout();
    beginStaffTwoFactorChallenge($this, $user);

    $this->post(route('two-factor.login.store'), ['recovery_code' => $recoveryCode])
        ->assertSessionHasErrors('recovery_code');

    $this->assertGuest();
});

test('cancelling a pending staff challenge clears it and returns to login', function () {
    ['user' => $user] = staffTwoFactorFixture();
    beginStaffTwoFactorChallenge($this, $user);

    $this->delete(route('two-factor.cancel'))
        ->assertRedirect('/login')
        ->assertSessionMissing('staff.pending_attempt_id');

    $this->assertGuest();
});
