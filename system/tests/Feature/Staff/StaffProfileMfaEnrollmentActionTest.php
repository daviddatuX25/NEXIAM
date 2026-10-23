<?php

declare(strict_types=1);

use App\Assessment\Omr\Canonical\OmrQrCodeRenderer;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffTwoFactorRecoveryCode;
use App\Models\User;
use App\Staffing\Actions\BeginStaffProfileTwoFactorEnrollment;
use App\Staffing\Actions\ConfirmStaffProfileTwoFactorEnrollment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

/** @return array{user: User, state: StaffAuthenticationState} */
function profileMfaEnrollmentActor(): array
{
    seedE2Foundation();

    $user = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
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

function beginProfileMfaEnrollment(User $user): array
{
    return app(BeginStaffProfileTwoFactorEnrollment::class)->handle(
        $user,
        'Profile-Secret-123!',
        app(OmrQrCodeRenderer::class),
        request(),
    );
}

test('begin profile MFA enrollment returns a secret and QR code', function (): void {
    ['user' => $user, 'state' => $state] = profileMfaEnrollmentActor();
    $this->actingAs($user)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
    ]);

    $enrollment = beginProfileMfaEnrollment($user);

    expect($enrollment['secret'])->toBeString()->not->toBeEmpty()
        ->and($enrollment['qr_code'])->toStartWith('data:image/svg+xml;base64,')
        ->and($enrollment['expires_at'])->toBeInstanceOf(Carbon::class);

    $attempt = StaffAuthenticationAttempt::query()->whereKey(session('staff.pending_profile_enrollment_attempt_id'))->firstOrFail();
    expect($attempt->purpose)->toBe('mfa_profile_enrollment')
        ->and($attempt->expected_session_version)->toBe((int) $state->session_version);
});

test('confirm profile MFA enrollment activates the factor and returns eight recovery codes', function (): void {
    ['user' => $user, 'state' => $state] = profileMfaEnrollmentActor();
    $this->actingAs($user)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
    ]);
    $enrollment = beginProfileMfaEnrollment($user);

    $codes = app(ConfirmStaffProfileTwoFactorEnrollment::class)->handle(
        $user,
        (new Google2FA)->getCurrentOtp($enrollment['secret']),
        request(),
    );

    $freshState = $state->fresh();
    $attempt = StaffAuthenticationAttempt::query()->where('user_id', $user->id)->where('purpose', 'mfa_profile_enrollment')->firstOrFail();
    expect($codes)->toHaveCount(8)
        ->and($freshState->two_factor_secret)->toBe($enrollment['secret'])
        ->and($freshState->two_factor_confirmed_at)->not->toBeNull()
        ->and($freshState->pending_two_factor_secret)->toBeNull()
        ->and($freshState->pending_two_factor_expires_at)->toBeNull()
        ->and($freshState->pending_enrollment_attempt_id)->toBeNull()
        ->and($freshState->two_factor_generation)->not->toBe($state->two_factor_generation)
        ->and($attempt->completed_at)->not->toBeNull()
        ->and(StaffTwoFactorRecoveryCode::query()->where('user_id', $user->id)->where('generation', $freshState->recovery_generation)->count())->toBe(8);
});

test('confirm profile MFA enrollment rejects an invalid TOTP code', function (): void {
    ['user' => $user, 'state' => $state] = profileMfaEnrollmentActor();
    $this->actingAs($user)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
    ]);
    beginProfileMfaEnrollment($user);

    expect(fn () => app(ConfirmStaffProfileTwoFactorEnrollment::class)->handle($user, '000000', request()))
        ->toThrow(ValidationException::class);

    expect($state->fresh()->two_factor_secret)->toBeNull()
        ->and($state->fresh()->two_factor_confirmed_at)->toBeNull();
});
