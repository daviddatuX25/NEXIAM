<?php

declare(strict_types=1);

use App\Models\Auth\StaffAuthenticationState;
use App\Models\User;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/** @return array{user: User, state: StaffAuthenticationState} */
function staffProfileMfaHttpActor(): array
{
    seedE2Foundation();

    $user = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $user->forceFill(['password' => bcrypt('Profile-Secret-123!')])->save();
    $state = StaffAuthenticationState::query()->updateOrCreate(
        ['user_id' => $user->id],
        [
            'activation_completed_at' => now(),
            'password_set_at' => now(),
            'must_change_password' => false,
            'session_version' => 1,
            'mfa_required' => false,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_accepted_step' => null,
            'two_factor_generation' => (string) Str::uuid(),
            'recovery_generation' => null,
            'pending_two_factor_secret' => null,
            'pending_two_factor_expires_at' => null,
            'pending_enrollment_attempt_id' => null,
        ],
    );

    return ['user' => $user, 'state' => $state];
}

test('staff can begin profile MFA enrollment over the HTTP adapter', function (): void {
    ['user' => $user, 'state' => $state] = staffProfileMfaHttpActor();

    $this->actingAs($user)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
    ]);

    $response = $this->postJson(route('staff.profile.mfa.enroll.begin'), [
        'password' => 'Profile-Secret-123!',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['status', 'secret', 'qr_code', 'expires_at'])
        ->assertJsonPath('status', 'ok');
    expect($response->json('secret'))->toBeString()->not->toBeEmpty()
        ->and($response->json('qr_code'))->toStartWith('data:image/svg+xml;base64,')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('staff can confirm profile MFA enrollment over the HTTP adapter', function (): void {
    ['user' => $user, 'state' => $state] = staffProfileMfaHttpActor();

    $this->actingAs($user)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
    ]);

    $beginResponse = $this->postJson(route('staff.profile.mfa.enroll.begin'), [
        'password' => 'Profile-Secret-123!',
    ]);
    $secret = $beginResponse->json('secret');
    $code = (new Google2FA)->getCurrentOtp($secret);
    $this->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
        'staff.pending_profile_enrollment_attempt_id' => session('staff.pending_profile_enrollment_attempt_id'),
    ]);
    $confirmResponse = $this->postJson(route('staff.profile.mfa.enroll.confirm'), [
        'code' => $code,
    ]);

    $confirmResponse->assertOk()
        ->assertJsonStructure(['status', 'recovery_codes'])
        ->assertJsonPath('status', 'ok')
        ->assertJsonCount(8, 'recovery_codes');
    expect($confirmResponse->headers->get('Cache-Control'))->toContain('no-store')
        ->and(StaffAuthenticationState::query()->where('user_id', $user->id)->value('two_factor_confirmed_at'))->not->toBeNull();
});
