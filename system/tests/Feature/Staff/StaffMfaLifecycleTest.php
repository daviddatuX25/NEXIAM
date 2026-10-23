<?php

declare(strict_types=1);

use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffTwoFactorRecoveryCode;
use App\Models\User;
use App\Staffing\Actions\RedeemStaffActivationVoucher;
use App\Staffing\Actions\RegenerateStaffRecoveryCodes;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/** @return array{user: User, secret: string, state: StaffAuthenticationState} */
function staffMfaLifecycleActor(): array
{
    seedE2Foundation();

    $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $secret = 'JBSWY3DPEHPK3PXP';
    $state = StaffAuthenticationState::query()->updateOrCreate(
        ['user_id' => $user->id],
        [
            'activation_completed_at' => now(),
            'password_set_at' => now(),
            'session_version' => 1,
            'mfa_required' => true,
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_generation' => (string) Str::uuid(),
            'two_factor_last_accepted_step' => null,
            'recovery_generation' => (string) Str::uuid(),
        ],
    );

    return ['user' => $user, 'secret' => $secret, 'state' => $state];
}

test('staff with MFA can regenerate recovery codes with valid password and TOTP code', function (): void {
    ['user' => $user, 'secret' => $secret, 'state' => $state] = staffMfaLifecycleActor();
    $this->actingAs($user)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
    ]);
    $code = (new Google2FA)->getCurrentOtp($secret);

    $response = $this->postJson(route('staff.profile.mfa.recovery-codes'), [
        'current_password' => 'password',
        'totp_code' => $code,
    ]);

    $response->assertOk()->assertJsonCount(8, 'recovery_codes');
    expect($response->json('recovery_codes'))->toHaveCount(8)
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('regenerating recovery codes revokes old recovery codes', function (): void {
    ['user' => $user, 'secret' => $secret, 'state' => $state] = staffMfaLifecycleActor();
    $this->actingAs($user)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
    ]);

    $oldCodes = app(RegenerateStaffRecoveryCodes::class)->handle(
        $user,
        'password',
        (new Google2FA)->getCurrentOtp($secret),
        request(),
    );
    $oldDigests = array_map(
        static fn (string $code): string => hash('sha256', str_replace('-', '', $code)),
        $oldCodes,
    );
    $nextStep = (int) $state->fresh()->two_factor_last_accepted_step + 1;
    $nextCode = (new Google2FA)->oathTotp($secret, $nextStep);

    $this->actingAs($user)->postJson(route('staff.profile.mfa.recovery-codes'), [
        'current_password' => 'password',
        'totp_code' => $nextCode,
    ])->assertOk()->assertJsonCount(8, 'recovery_codes');

    $oldRows = StaffTwoFactorRecoveryCode::query()->whereIn('code_digest', $oldDigests)->get();
    expect($oldRows)->toHaveCount(8);
    foreach ($oldRows as $oldRow) {
        expect($oldRow->revoked_at)->not->toBeNull();
    }
});

test('staff can initiate and confirm authenticator re-enrollment on a new secret', function (): void {
    ['user' => $user, 'secret' => $oldSecret, 'state' => $state] = staffMfaLifecycleActor();
    $this->actingAs($user)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
    ]);

    $beginResponse = $this->postJson(route('staff.profile.mfa.re-enroll.begin'), [
        'current_password' => 'password',
    ]);
    $beginResponse->assertOk()->assertJsonStructure(['secret', 'qr_svg', 'expires_at']);
    $newSecret = $beginResponse->json('secret');
    expect($newSecret)->not->toBe($oldSecret);

    $confirmResponse = $this->postJson(route('staff.profile.mfa.re-enroll.confirm'), [
        'code' => (new Google2FA)->getCurrentOtp($newSecret),
    ]);

    $confirmResponse->assertOk()->assertJsonCount(8, 'recovery_codes');
    expect(StaffAuthenticationState::query()->where('user_id', $user->id)->value('two_factor_secret'))->toBe($newSecret);
});

test('HR mfa_reset invalidates an authenticated browser and continues through a guest setup session', function (): void {
    ['user' => $staff, 'secret' => $oldSecret, 'state' => $state] = staffMfaLifecycleActor();
    $this->actingAs($staff)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
    ]);
    app(RegenerateStaffRecoveryCodes::class)->handle(
        $staff,
        'password',
        (new Google2FA)->getCurrentOtp($oldSecret),
        request(),
    );

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $hrState = StaffAuthenticationState::query()->where('user_id', $hr->id)->firstOrFail();
    $voucherResponse = $this->actingAs($hr)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $hrState->session_version,
    ])->postJson(route('staff.personnel.vouchers.store', $staff), [
        'purpose' => 'mfa_reset',
    ]);
    $voucherResponse->assertOk()->assertJsonStructure(['public_reference', 'token']);

    $attempt = app(RedeemStaffActivationVoucher::class)->handle(
        $voucherResponse->json('public_reference'),
        $voucherResponse->json('token'),
    );

    $state->refresh();
    expect($attempt->purpose)->toBe('mfa_reset')
        ->and($attempt->phase)->toBe('mfa_required')
        ->and($state->two_factor_secret)->toBeNull()
        ->and($state->two_factor_confirmed_at)->toBeNull()
        ->and(StaffTwoFactorRecoveryCode::query()->where('user_id', $staff->id)->whereNull('revoked_at')->exists())->toBeFalse();

    $this->assertGuest();
    expect(session()->has('auth.surface'))->toBeFalse();
    $this->withSession(['staff.pending_attempt_id' => $attempt->id])
        ->get('/staff/account-setup')
        ->assertInertia(fn ($page) => $page
            ->component('Staff/AccountSetup')
            ->where('purpose', 'mfa_reset')
            ->where('phase', 'mfa_required')
            ->where('password_set', true)
            ->where('two_factor_confirmed', false)
            ->where('can_enroll_factor', true));
    $this->postJson('/staff/account-setup/two-factor')
        ->assertOk()
        ->assertJsonStructure(['secret', 'qr_svg', 'expires_at']);

    expect($state->fresh()->two_factor_secret)->toBeNull();
});
