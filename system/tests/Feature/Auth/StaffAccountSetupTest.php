<?php

use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\User;
use App\Staffing\Actions\IssueStaffActivationVoucher;
use App\Staffing\Actions\RedeemStaffActivationVoucher;
use App\Staffing\Mail\StaffSetupEmailVerificationMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

function createPendingStaffSetupAttempt(TestCase $test, User $hr, User $staff): void
{
    StaffAuthenticationState::query()->updateOrCreate(
        ['user_id' => $staff->id],
        ['activation_completed_at' => null, 'password_set_at' => null, 'session_version' => 1, 'mfa_required' => false, 'mfa_exempt' => true],
    );

    $voucherData = app(IssueStaffActivationVoucher::class)->handle($hr, $staff);

    $attempt = app(RedeemStaffActivationVoucher::class)->handle(
        $voucherData['public_reference'],
        $voucherData['token'],
    );
    Mail::fake();
    $test->withSession(['staff.pending_attempt_id' => $attempt->id])
        ->post('/staff/account-setup/email', ['email' => 'staff.setup@example.com'])
        ->assertRedirect('/staff/account-setup');
}

function createPendingMfaResetAttempt(TestCase $test, User $hr, User $staff): StaffAuthenticationAttempt
{
    $voucherData = app(IssueStaffActivationVoucher::class)->handle($hr, $staff, 'mfa_reset');
    $attempt = app(RedeemStaffActivationVoucher::class)->handle(
        $voucherData['public_reference'],
        $voucherData['token'],
    );
    $test->withSession(['staff.pending_attempt_id' => $attempt->id]);

    return $attempt;
}

test('setting a password completes only the password stage before setup finalization', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    createPendingStaffSetupAttempt($this, $hr, $staff);

    $this->post('/staff/account-setup/password', [
        'password' => 'New-staff-password-234',
        'password_confirmation' => 'New-staff-password-234',
    ])->assertRedirect('/staff/account-setup');

    $state = StaffAuthenticationState::query()->where('user_id', $staff->id)->firstOrFail();
    $attempt = StaffAuthenticationAttempt::query()->where('user_id', $staff->id)->latest('created_at')->firstOrFail();
    expect(Hash::check('New-staff-password-234', $staff->fresh()->password))->toBeTrue()
        ->and($state->password_set_at)->not->toBeNull()
        ->and($attempt->phase)->toBe('ready_to_complete')
        ->and($attempt->completed_at)->toBeNull();

    $this->withSession(['url.intended' => '/staff/profile'])
        ->post('/staff/account-setup/complete')
        ->assertRedirect('/staff')
        ->assertSessionMissing('url.intended');
    expect($state->fresh()->activation_completed_at)->not->toBeNull()
        ->and($attempt->fresh()->completed_at)->not->toBeNull();
});

test('an mfa-reset voucher starts a new authenticator enrollment without a password stage', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    createPendingMfaResetAttempt($this, $hr, $staff);

    $response = $this->postJson('/staff/account-setup/two-factor');
    $response->assertOk()->assertJsonStructure(['secret', 'qr_svg', 'expires_at']);
    expect($response->json('secret'))->toMatch('/^[A-Z2-7]+$/')
        ->and($response->json('qr_svg'))->toStartWith('data:image/svg+xml;base64,');
});

test('confirming a new factor on an mfa-reset continuation yields recovery codes without completing the account attempt', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $attempt = createPendingMfaResetAttempt($this, $hr, $staff);
    $enrollment = $this->postJson('/staff/account-setup/two-factor')->assertOk();
    $secret = $enrollment->json('secret');
    $code = (new Google2FA)->getCurrentOtp($secret);

    $response = $this->postJson('/staff/account-setup/two-factor/confirm', ['code' => $code]);
    $response->assertOk()->assertJsonStructure(['recovery_codes']);
    $recoveryCodes = $response->json('recovery_codes');
    expect($recoveryCodes)->toHaveCount(8);

    $state = StaffAuthenticationState::query()->where('user_id', $staff->id)->firstOrFail();
    $storedCodes = DB::table('staff_two_factor_recovery_codes')->where('user_id', $staff->id)->pluck('code_digest');
    expect($state->two_factor_secret)->toBe($secret)
        ->and($state->two_factor_confirmed_at)->not->toBeNull()
        ->and($storedCodes)->toHaveCount(8)
        ->and($attempt->fresh()->phase)->toBe('ready_to_complete')
        ->and($attempt->fresh()->completed_at)->toBeNull();
    foreach ($recoveryCodes as $recoveryCode) {
        expect($storedCodes->contains(hash('sha256', strtolower(str_replace('-', '', $recoveryCode)))))->toBeTrue();
    }
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('an existing staff account completes mfa-reset enrollment and authenticates only after setup completion', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $attempt = createPendingMfaResetAttempt($this, $hr, $staff);

    $enrollment = $this->postJson('/staff/account-setup/two-factor')->assertOk();
    $secret = $enrollment->json('secret');
    expect($secret)->not->toBeEmpty();

    $code = (new Google2FA)->getCurrentOtp($secret);
    $confirmResponse = $this->postJson('/staff/account-setup/two-factor/confirm', ['code' => $code])->assertOk();
    expect($confirmResponse->json('recovery_codes'))->toHaveCount(8)
        ->and($attempt->fresh()->phase)->toBe('ready_to_complete')
        ->and($attempt->fresh()->completed_at)->toBeNull();

    $this->post('/staff/account-setup/complete')->assertRedirect('/staff');
    $this->assertAuthenticatedAs($staff);
    expect(StaffAuthenticationState::query()->where('user_id', $staff->id)->value('activation_completed_at'))->not->toBeNull()
        ->and($attempt->fresh()->completed_at)->not->toBeNull();
});
test('recipient activation requires an explicit unique email choice before password setup', function () {
    seedE2Foundation();
    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    StaffAuthenticationState::query()->where('user_id', $staff->id)->firstOrFail()->forceFill([
        'activation_completed_at' => null,
        'password_set_at' => null,
        'mfa_required' => false,
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ])->save();
    $voucher = app(IssueStaffActivationVoucher::class)->handle($hr, $staff);
    $attempt = app(RedeemStaffActivationVoucher::class)->handle($voucher['public_reference'], $voucher['token']);
    Mail::fake();
    $this->withSession(['staff.pending_attempt_id' => $attempt->id]);

    $this->get('/staff/account-setup')->assertOk()
        ->assertInertia(fn ($page) => $page->where('phase', 'email_required'));
    $this->post('/staff/account-setup/password', [
        'password' => 'New-staff-password-234',
        'password_confirmation' => 'New-staff-password-234',
    ])->assertSessionHasErrors('password');
    $this->post('/staff/account-setup/email', ['email' => $hr->email])
        ->assertSessionHasErrors('email');
    expect($attempt->fresh()->phase)->toBe('email_required');

    $this->post('/staff/account-setup/email', ['email' => 'recipient@example.org'])
        ->assertRedirect('/staff/account-setup');
    expect($attempt->fresh()->phase)->toBe('password_required')
        ->and($attempt->fresh()->recipient_email_chosen_at)->not->toBeNull()
        ->and($staff->fresh()->email)->toBe('recipient@example.org')
        ->and($staff->fresh()->email_verified_at)->toBeNull();
    Mail::assertQueued(StaffSetupEmailVerificationMail::class, function (StaffSetupEmailVerificationMail $mail) use ($staff): bool {
        $token = basename((string) parse_url($mail->confirmationUrl, PHP_URL_PATH));
        $this->get("/staff/account-setup/email/confirm/{$token}")
            ->assertInertia(fn ($page) => $page->component('Staff/SetupEmailConfirmation')->where('can_confirm', true));
        expect($staff->fresh()->email_verified_at)->toBeNull();
        $this->post('/staff/account-setup/email/confirm')->assertRedirect('/staff/account-setup');
        expect($staff->fresh()->email_verified_at)->not->toBeNull();

        return true;
    });
});
