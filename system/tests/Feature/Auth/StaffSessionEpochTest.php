<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffActivationVoucher;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AuditEvent;
use App\Models\User;
use App\Staffing\Actions\DeactivateStaffAccount;
use App\Staffing\Actions\IssueStaffActivationVoucher;
use App\Staffing\Actions\UpdateStaffSelfPassword;
use Illuminate\Auth\Recaller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

test('a genuine remembered staff recaller restores only after completed MFA and current server binding', function (): void {
    seedE2Foundation();

    $staff = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $state = StaffAuthenticationState::query()->where('user_id', $staff->id)->firstOrFail();
    $secret = 'JBSWY3DPEHPK3PXP';
    $state->forceFill([
        'mfa_required' => true,
        'mfa_exempt' => false,
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
        'two_factor_generation' => (string) Str::uuid(),
        'two_factor_last_accepted_step' => null,
    ])->save();

    $this->post(route('login.store'), [
        'email' => $staff->email,
        'password' => 'password',
        'remember' => true,
    ])->assertRedirect('/two-factor-challenge');

    $recallerResponse = $this->post(route('two-factor.login.store'), [
        'code' => app(Google2FA::class)->getCurrentOtp($secret),
    ])->assertRedirect('/staff');

    $cookieName = Auth::guard('web')->getRecallerName();
    $recaller = $recallerResponse->getCookie($cookieName, false);
    expect($recaller)->not->toBeNull();

    $this->flushSession();
    Auth::forgetGuards();

    $this->withUnencryptedCookie($cookieName, $recaller->getValue())
        ->get('/staff')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Staff/Home'));
    expect(session('auth.surface'))->toBe('staff')
        ->and((int) session('auth.session_version'))->toBe((int) $state->fresh()->session_version);

    $this->assertAuthenticatedAs($staff);

});

test('an opaque remember cookie without full staff authentication proof is rejected', function (): void {
    seedE2Foundation();

    $staff = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    Auth::guard('web')->login($staff, true);
    $cookieName = Auth::guard('web')->getRecallerName();
    $recaller = Cookie::queued($cookieName);
    expect($recaller)->not->toBeNull();

    $this->flushSession();
    Auth::forgetGuards();

    $this->withCookie($cookieName, $recaller->getValue())
        ->get('/staff')
        ->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a second full remembered login preserves other valid workstation cookies', function (): void {
    seedE2Foundation();

    $staff = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $state = StaffAuthenticationState::query()->where('user_id', $staff->id)->firstOrFail();
    $secret = 'JBSWY3DPEHPK3PXP';
    $state->forceFill([
        'mfa_required' => true,
        'mfa_exempt' => false,
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
        'two_factor_generation' => (string) Str::uuid(),
        'two_factor_last_accepted_step' => null,
    ])->save();

    $this->post(route('login.store'), [
        'email' => $staff->email,
        'password' => 'password',
        'remember' => true,
    ])->assertRedirect('/two-factor-challenge');
    $firstLogin = $this->post(route('two-factor.login.store'), [
        'code' => app(Google2FA::class)->getCurrentOtp($secret),
    ])->assertRedirect('/staff');
    $cookieName = Auth::guard('web')->getRecallerName();
    $firstRawCookie = $firstLogin->getCookie($cookieName, false);
    $firstDecryptedCookie = $firstLogin->getCookie($cookieName);
    expect($firstRawCookie)->not->toBeNull()
        ->and($firstDecryptedCookie)->not->toBeNull();

    $nextOtp = app(Google2FA::class)->oathTotp(
        $secret,
        (int) $state->fresh()->two_factor_last_accepted_step + 1,
    );
    $this->flushSession();
    Auth::forgetGuards();
    $this->post(route('login.store'), [
        'email' => $staff->email,
        'password' => 'password',
        'remember' => true,
    ])->assertRedirect('/two-factor-challenge');
    $secondLogin = $this->post(route('two-factor.login.store'), [
        'code' => $nextOtp,
    ])->assertRedirect('/staff');
    $secondDecryptedCookie = $secondLogin->getCookie($cookieName);
    expect($secondDecryptedCookie)->not->toBeNull()
        ->and((new Recaller($secondDecryptedCookie->getValue()))->token())
        ->toBe((new Recaller($firstDecryptedCookie->getValue()))->token());

    $this->flushSession();
    Auth::forgetGuards();
    $this->withUnencryptedCookie($cookieName, $firstRawCookie->getValue())
        ->get('/staff')
        ->assertOk();
});
test('password changes atomically rotate the remembered recaller token with audit evidence', function (): void {
    seedE2Foundation();

    $staff = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $state = StaffAuthenticationState::query()->where('user_id', $staff->id)->firstOrFail();
    $secret = 'JBSWY3DPEHPK3PXP';

    $state->forceFill([
        'mfa_required' => true,
        'mfa_exempt' => false,
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
        'two_factor_generation' => (string) Str::uuid(),
        'two_factor_last_accepted_step' => null,
    ])->save();

    $this->post(route('login.store'), [
        'email' => $staff->email,
        'password' => 'password',
        'remember' => true,
    ])->assertRedirect('/two-factor-challenge');
    $recallerResponse = $this->post(route('two-factor.login.store'), [
        'code' => app(Google2FA::class)->getCurrentOtp($secret),
    ])->assertRedirect('/staff');

    $cookieName = Auth::guard('web')->getRecallerName();
    $decryptedRecaller = $recallerResponse->getCookie($cookieName);
    expect($decryptedRecaller)->not->toBeNull();
    $parsedRecaller = new Recaller($decryptedRecaller->getValue());
    expect($parsedRecaller->valid())->toBeTrue()
        ->and(
            Auth::createUserProvider(config('auth.guards.web.provider'))
                ->retrieveByToken($parsedRecaller->id(), $parsedRecaller->token())?->is($staff),
        )->toBeTrue();
    $recaller = $recallerResponse->getCookie($cookieName, false);
    expect($recaller)->not->toBeNull();
    $originalToken = $staff->fresh()->remember_token;
    $originalVersion = (int) $state->fresh()->session_version;
    $nextOtp = app(Google2FA::class)->oathTotp(
        $secret,
        (int) $state->fresh()->two_factor_last_accepted_step + 1,
    );

    $this->put('/staff/profile/password', [
        'current_password' => 'password',
        'password' => 'New-strong-password-456',
        'password_confirmation' => 'New-strong-password-456',
        'totp_code' => $nextOtp,
    ])->assertRedirect('/staff/profile');

    expect($staff->fresh()->remember_token)->not->toBe($originalToken)
        ->and((int) $state->fresh()->session_version)->toBe($originalVersion + 1)
        ->and(AuditEvent::query()->where('event_type', 'auth.staff_password.changed')->exists())->toBeTrue()
        ->and(
            Auth::createUserProvider(config('auth.guards.web.provider'))
                ->retrieveByToken($parsedRecaller->id(), $parsedRecaller->token()),
        )->toBeNull();

    $this->flushSession();
    Auth::forgetGuards();
    $this->withUnencryptedCookie($cookieName, $recaller->getValue())
        ->get('/staff')
        ->assertRedirect(route('login'));
    $this->assertGuest();
    $this->post(route('login.store'), [
        'email' => $staff->email,
        'password' => 'New-strong-password-456',
    ])->assertRedirect('/two-factor-challenge');
    $this->assertGuest();
});

test('a remembered staff recaller cannot restore after the server epoch advances', function (): void {
    seedE2Foundation();

    $staff = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $state = StaffAuthenticationState::query()->where('user_id', $staff->id)->firstOrFail();
    $state->forceFill(['mfa_required' => false, 'mfa_exempt' => true])->save();

    $loginResponse = $this->post(route('login.store'), [
        'email' => $staff->email,
        'password' => 'password',
        'remember' => true,
    ])->assertRedirect('/staff');
    $cookieName = Auth::guard('web')->getRecallerName();
    $recaller = $loginResponse->getCookie($cookieName, false);
    expect($recaller)->not->toBeNull();

    $state->increment('session_version');
    $this->flushSession();
    Auth::forgetGuards();

    $this->withUnencryptedCookie($cookieName, $recaller->getValue())
        ->get('/staff')
        ->assertRedirect(route('login'));
    $this->assertGuest();
});

test('password epoch and recaller rotation roll back when password audit persistence fails', function (): void {
    seedE2Foundation();

    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $state = StaffAuthenticationState::query()->where('user_id', $staff->id)->firstOrFail();
    $password = $staff->password;
    $rememberToken = $staff->remember_token;
    $sessionVersion = (int) $state->session_version;

    $audit = Mockery::mock(RecordAuditEvent::class);
    $audit->shouldReceive('handle')->once()->andThrow(new RuntimeException('audit unavailable'));
    $this->app->instance(RecordAuditEvent::class, $audit);

    expect(fn () => app(UpdateStaffSelfPassword::class)->handle(
        $staff,
        'password',
        'New-strong-password-456',
    ))->toThrow(RuntimeException::class, 'audit unavailable');

    expect($staff->fresh()->password)->toBe($password)
        ->and($staff->fresh()->remember_token)->toBe($rememberToken)
        ->and((int) $state->fresh()->session_version)->toBe($sessionVersion);
});

test('staff identity fails closed for missing surface or epoch even when marked as an applicant', function (): void {
    seedE2Foundation();

    $staff = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();

    foreach ([
        [],
        ['auth.surface' => 'staff'],
        ['auth.surface' => 'applicant', 'auth.session_version' => (int) $staff->authenticationState->session_version],
    ] as $session) {
        Auth::forgetGuards();
        $this->flushSession();
        $this->actingAs($staff)->withSession($session)
            ->get('/staff')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }
});

test('missing staff authentication state and forged staff markers fail closed', function (): void {
    seedE2Foundation();

    $staff = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    StaffAuthenticationState::query()->where('user_id', $staff->id)->delete();

    $this->actingAs($staff)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => 1,
    ])->get('/staff')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');
    $this->assertGuest();

    Auth::forgetGuards();
    $unknown = User::factory()->create();
    $this->actingAs($unknown)->withSession(['auth.surface' => 'staff'])
        ->get('/staff')
        ->assertRedirect(route('login'));
    $this->assertGuest();
});

test('applicant sessions remain unaffected by staff identity checks', function (): void {
    seedE2Foundation();

    $applicant = submitSyntheticApplicant('session.epoch.applicant@example.test');
    $applicantUser = app(AuthenticateApplicantWithAccessKey::class)
        ->handle($applicant['access_token'], '2001-01-01');

    $this->actingAs($applicantUser)->withSession(['auth.surface' => 'applicant'])
        ->get(route('applicant.schedule'))
        ->assertOk();
});

test('active staff with a matching authentication epoch can access staff home', function (): void {
    seedE2Foundation();

    $staff = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $state = $staff->authenticationState;
    $state->forceFill(['mfa_required' => false, 'mfa_exempt' => true])->save();
    $this->actingAs($staff)
        ->withSession([
            'auth.surface' => 'staff',
            'auth.session_version' => (int) $state->session_version,
        ])
        ->get('/staff')
        ->assertOk();
});

test('deactivation advances the epoch, revokes pending credentials, and destroys the staff session', function (): void {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $state = StaffAuthenticationState::query()->where('user_id', $staff->id)->firstOrFail();
    $version = (int) $state->session_version;

    $voucherData = app(IssueStaffActivationVoucher::class)->handle($hr, $staff);
    $voucher = StaffActivationVoucher::query()
        ->where('public_reference', $voucherData['public_reference'])
        ->firstOrFail();
    $attempt = StaffAuthenticationAttempt::query()->create([
        'id' => (string) Str::uuid(),
        'user_id' => $staff->id,
        'purpose' => 'activation',
        'primary_method' => 'voucher',
        'phase' => 'setup',
        'voucher_id' => $voucher->id,
        'expected_session_version' => $version,
        'session_binding_digest' => hash('sha256', Str::random(40)),
        'primary_verified_at' => now(),
        'expires_at' => now()->addHour(),
    ]);

    $this->actingAs($staff)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => $version,
    ]);

    app(DeactivateStaffAccount::class)->handle($hr, $staff);

    expect((int) $state->fresh()->session_version)->toBe($version + 1)
        ->and($voucher->fresh()->revoked_at)->not->toBeNull()
        ->and($attempt->fresh()->revoked_at)->not->toBeNull();

    $this->get('/staff')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email', 'Your staff account is inactive or suspended. Please contact HR.')
        ->assertSessionMissing('auth.surface')
        ->assertSessionMissing('auth.session_version');

    $this->assertGuest();
});

test('administrator password reset voucher invalidates an old staff session epoch', function (): void {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $state = StaffAuthenticationState::query()->where('user_id', $staff->id)->firstOrFail();
    $state->forceFill(['mfa_required' => false, 'mfa_exempt' => true])->save();
    $version = (int) $state->session_version;
    $voucher = app(IssueStaffActivationVoucher::class)->handle($hr, $staff, 'password_reset');

    $this->post('/staff/activate', [
        'reference' => $voucher['public_reference'],
        'token' => $voucher['token'],
    ])->assertRedirect('/staff/account-setup');
    $this->post('/staff/account-setup/password', [
        'password' => 'Reset-staff-password-456',
        'password_confirmation' => 'Reset-staff-password-456',
    ])->assertRedirect('/staff/account-setup');

    expect((int) $state->fresh()->session_version)->toBe($version + 1)
        ->and(Hash::check('Reset-staff-password-456', $staff->fresh()->password))->toBeTrue();
    $this->post('/staff/account-setup/complete')->assertRedirect('/staff');
    $this->assertAuthenticatedAs($staff);

    $this->actingAs($staff)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => $version,
    ])->get('/staff')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email', 'Your session has expired due to a security update. Please sign in again.')
        ->assertSessionMissing('auth.surface')
        ->assertSessionMissing('auth.session_version');

    $this->assertGuest();
});

test('applicant surface and unauthenticated requests are not rejected by staff epoch checks', function (): void {
    seedE2Foundation();

    $applicant = submitSyntheticApplicant('session.epoch.applicant@example.test');
    $applicantUser = app(AuthenticateApplicantWithAccessKey::class)
        ->handle($applicant['access_token'], '2001-01-01');

    $this->actingAs($applicantUser)->withSession([
        'auth.surface' => 'applicant',
        'auth.session_version' => 0,
    ])
        ->get('/staff')
        ->assertRedirect(route('applicant.schedule'));

    $this->actingAsGuest()->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => 0,
    ])
        ->get('/staff')
        ->assertRedirect(route('login'))
        ->assertSessionMissing('errors');
});
