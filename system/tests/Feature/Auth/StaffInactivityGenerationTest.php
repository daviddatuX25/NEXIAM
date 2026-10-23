<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffProviderIdentity;
use App\Models\Auth\StaffTwoFactorRecoveryCode;
use App\Models\User;
use App\Staffing\Actions\IssueStaffActivationVoucher;
use App\Staffing\Actions\RedeemStaffActivationVoucher;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use PragmaRX\Google2FA\Google2FA;

test('direct password login establishes a fresh generation and shares it stably across staff page reloads', function (): void {
    seedE2Foundation();
    $user = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    StaffAuthenticationState::query()->where('user_id', $user->id)->firstOrFail()->forceFill(['mfa_required' => false, 'mfa_exempt' => true])->save();

    $this->withSession(['auth.staff_inactivity_generation' => 'previous-generation'])
        ->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('staff.home'));

    $generation = session('auth.staff_inactivity_generation');
    expect($generation)->toBeString()->not->toBeEmpty()->not->toBe('previous-generation');

    $this->get('/staff')->assertInertia(fn ($page) => $page
        ->where('auth.staff_inactivity_generation', $generation));
    $this->get('/staff')->assertInertia(fn ($page) => $page
        ->where('auth.staff_inactivity_generation', $generation));
});

test('a new successful login for the same staff user gets a different generation', function (): void {
    seedE2Foundation();
    $user = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    StaffAuthenticationState::query()->where('user_id', $user->id)->firstOrFail()->forceFill(['mfa_required' => false, 'mfa_exempt' => true])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('staff.home'));
    $firstGeneration = session('auth.staff_inactivity_generation');

    $this->post('/logout')->assertRedirect(route('login'))
        ->assertSessionMissing('auth.staff_inactivity_generation');
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('staff.home'));

    $secondGeneration = session('auth.staff_inactivity_generation');
    expect($firstGeneration)->toBeString()->not->toBeEmpty()
        ->and($secondGeneration)->toBeString()->not->toBeEmpty()
        ->and($secondGeneration)->not->toBe($firstGeneration);
});

test('activation account setup completion establishes a staff generation', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $state = StaffAuthenticationState::query()->where('user_id', $staff->id)->firstOrFail();
    $state->forceFill([
        'activation_completed_at' => null,
        'password_set_at' => null,
        'mfa_required' => true,
        'mfa_exempt' => false,
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ])->save();

    $voucher = app(IssueStaffActivationVoucher::class)->handle($hr, $staff);
    $attempt = app(RedeemStaffActivationVoucher::class)->handle($voucher['public_reference'], $voucher['token']);
    $this->withSession(['staff.pending_attempt_id' => $attempt->id]);
    Mail::fake();
    $this->post('/staff/account-setup/email', ['email' => $staff->email])->assertRedirect('/staff/account-setup');
    $this->post('/staff/account-setup/password', [
        'password' => 'New-staff-password-234',
        'password_confirmation' => 'New-staff-password-234',
    ])->assertRedirect('/staff/account-setup');
    $this->post('/staff/account-setup/complete')->assertForbidden();
    expect(StaffAuthenticationAttempt::query()->whereKey(session('staff.pending_attempt_id'))->value('phase'))->toBe('mfa_required')
        ->and(session('staff.pending_attempt_id'))->not->toBeNull();
    $enrollment = $this->postJson('/staff/account-setup/two-factor')->assertOk();
    $this->postJson('/staff/account-setup/two-factor/confirm', [
        'code' => app(Google2FA::class)->getCurrentOtp($enrollment->json('secret')),
    ])->assertOk();
    $this->post('/staff/account-setup/complete')->assertRedirect('/staff');

    expect(session('auth.staff_inactivity_generation'))->toBeString()->not->toBeEmpty();
    $this->assertAuthenticatedAs($staff);
});

test('MFA enrollment account setup completion establishes a staff generation', function (): void {
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

    $this->withSession(['auth.staff_inactivity_generation' => 'previous-generation'])
        ->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/staff/account-setup')
        ->assertSessionHas('staff.pending_attempt_id');
    expect(session('auth.staff_inactivity_generation'))->toBe('previous-generation');
    $this->post('/staff/account-setup/complete')->assertForbidden();
    expect(StaffAuthenticationAttempt::query()->whereKey(session('staff.pending_attempt_id'))->value('phase'))->toBe('mfa_required')
        ->and(session('staff.pending_attempt_id'))->not->toBeNull();
    expect(session('auth.staff_inactivity_generation'))->toBe('previous-generation');
    $enrollment = $this->postJson('/staff/account-setup/two-factor')->assertOk();
    $this->postJson('/staff/account-setup/two-factor/confirm', [
        'code' => app(Google2FA::class)->getCurrentOtp($enrollment->json('secret')),
    ])->assertOk();
    expect(session('auth.staff_inactivity_generation'))->toBe('previous-generation');

    $this->post('/staff/account-setup/complete')->assertRedirect('/staff')
        ->assertSessionMissing('staff.pending_attempt_id');

    expect(session('auth.staff_inactivity_generation'))->toBeString()->not->toBe('previous-generation');
    $this->assertAuthenticatedAs($user);
});

test('successful MFA challenge establishes a fresh staff generation', function (): void {
    seedE2Foundation();
    $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $secret = 'JBSWY3DPEHPK3PXP';
    StaffAuthenticationState::query()->where('user_id', $user->id)->firstOrFail()->forceFill([
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'mfa_required' => true,
        'mfa_exempt' => false,
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
        'two_factor_generation' => (string) Str::uuid(),
    ])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/two-factor-challenge');
    $this->post(route('two-factor.login.store'), ['code' => app(Google2FA::class)->getCurrentOtp($secret)])
        ->assertRedirect('/staff');

    expect(session('auth.staff_inactivity_generation'))->toBeString()->not->toBeEmpty();
    $this->assertAuthenticatedAs($user);
});

test('bound Google login establishes a staff inactivity generation', function (): void {
    seedE2Foundation();
    $user = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    StaffAuthenticationState::query()->where('user_id', $user->id)->firstOrFail()->forceFill(['mfa_required' => false, 'mfa_exempt' => true])->save();
    $user->forceFill(['email' => 'generation-google-'.Str::uuid().'@gmail.com'])->save();

    StaffProviderIdentity::query()->create([
        'user_id' => $user->id,
        'provider' => 'google',
        'subject' => 'google-sub-program-head-generation',
        'email_at_link' => $user->email,
        'email_verified_at_link' => true,
        'linked_at' => now(),
    ]);
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-sub-program-head-generation',
        'email' => $user->email,
        'user' => ['email_verified' => true],
    ]));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('staff.home'));
    $this->assertAuthenticatedAs($user);
    expect(session('auth.staff_inactivity_generation'))->toBeString()->not->toBeEmpty();
});

test('a legacy staff session gets one stable generation without changing its security epoch', function (): void {
    seedE2Foundation();
    $user = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    StaffAuthenticationState::query()->where('user_id', $user->id)->firstOrFail()->forceFill(['mfa_required' => false, 'mfa_exempt' => true])->save();
    $sessionVersion = (int) $user->authenticationState->session_version;

    $this->actingAs($user)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => $sessionVersion,
    ]);

    $this->get('/staff')->assertInertia(fn ($page) => $page
        ->where('auth.staff_inactivity_generation', fn ($generation) => is_string($generation) && $generation !== ''));
    $generation = session('auth.staff_inactivity_generation');
    expect(session('auth.session_version'))->toBe($sessionVersion);

    $this->get('/staff')->assertInertia(fn ($page) => $page
        ->where('auth.staff_inactivity_generation', $generation));
    expect(session('auth.staff_inactivity_generation'))->toBe($generation)
        ->and(session('auth.session_version'))->toBe($sessionVersion);
});

test('guest and applicant Inertia props have no staff inactivity generation', function (): void {
    seedE2Foundation();

    $this->get('/login')->assertInertia(fn ($page) => $page
        ->where('auth.staff_inactivity_generation', null));

    $applicant = submitSyntheticApplicant('inactivity.generation.applicant@example.test');
    $applicantUser = app(AuthenticateApplicantWithAccessKey::class)
        ->handle($applicant['access_token'], '2001-01-01');

    $this->actingAs($applicantUser)->withSession(['auth.surface' => 'applicant'])
        ->get(route('applicant.schedule'))
        ->assertInertia(fn ($page) => $page->where('auth.staff_inactivity_generation', null));
});

test('failed and pending staff authentication do not establish a generation', function (): void {
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

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(session('auth.staff_inactivity_generation'))->toBeNull();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/two-factor-challenge');
    $this->assertGuest();
    expect(session('auth.staff_inactivity_generation'))->toBeNull();
});

test('recovery authentication rotates generation only after a valid challenge', function (): void {
    seedE2Foundation();
    $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $user->forceFill(['password' => 'password'])->save();
    $user->refresh();
    expect(Hash::check('password', $user->password))->toBeTrue();
    $recoveryGeneration = (string) Str::uuid();
    StaffAuthenticationState::query()->where('user_id', $user->id)->firstOrFail()->forceFill([
        'activation_completed_at' => now(),
        'must_change_password' => false,
        'password_set_at' => now(),
        'mfa_required' => true,
        'mfa_exempt' => false,
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
        'two_factor_generation' => (string) Str::uuid(),
        'recovery_generation' => $recoveryGeneration,
    ])->save();
    $recoveryCode = '0123456789abcdef0123456789abcdef';
    StaffTwoFactorRecoveryCode::query()->create([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'generation' => $recoveryGeneration,
        'code_digest' => hash('sha256', $recoveryCode),
    ]);
    $this->withSession(['auth.staff_inactivity_generation' => 'previous-generation'])
        ->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/two-factor-challenge');
    $this->post(route('two-factor.login.store'), ['code' => 'invalid'])
        ->assertSessionHasErrors('code');
    $this->post(route('two-factor.login.store'), ['recovery_code' => 'invalid'])
        ->assertSessionHasErrors('recovery_code');
    expect(session('auth.staff_inactivity_generation'))->toBe('previous-generation');
    $this->assertGuest();
    $this->delete(route('two-factor.cancel'))->assertRedirect('/login');
    expect(session('auth.staff_inactivity_generation'))->toBe('previous-generation');
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/two-factor-challenge');
    $this->post(route('two-factor.login.store'), ['recovery_code' => $recoveryCode])
        ->assertRedirect('/staff');
    expect(session('auth.staff_inactivity_generation'))->toBeString()->not->toBe('previous-generation');
    $this->assertAuthenticatedAs($user);
});

test('linking a Google identity preserves the completed authentication generation', function (): void {
    seedE2Foundation();
    $user = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    StaffAuthenticationState::query()->where('user_id', $user->id)->firstOrFail()->forceFill(['mfa_required' => false, 'mfa_exempt' => true])->save();
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/staff');
    $generation = session('auth.staff_inactivity_generation');
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'generation-explicit-link',
        'email' => 'generation-link@gmail.com',
        'user' => ['email_verified' => true],
    ]));
    $this->get(route('auth.google.callback'))->assertRedirect(route('staff.profile'));
    expect(session('auth.staff_inactivity_generation'))->toBe($generation);
    $this->get(route('auth.google.callback'))->assertRedirect(route('staff.profile'));
    expect(session('auth.staff_inactivity_generation'))->toBe($generation);
    $this->assertAuthenticatedAs($user);
});

test('rejected provider authentication does not establish a generation', function (bool $verified): void {
    seedE2Foundation();
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'generation-unbound-provider',
        'email' => 'generation-unbound@gmail.com',
        'user' => ['email_verified' => $verified],
    ]));
    $this->get(route('auth.google.callback'))->assertRedirect(route('login'))
        ->assertSessionHasErrors('email')
        ->assertSessionMissing('auth.staff_inactivity_generation');
    $this->assertGuest();
})->with([true, false]);

test('a new inactivity generation cannot bypass a revoked security epoch', function (): void {
    seedE2Foundation();
    $user = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    StaffAuthenticationState::query()->where('user_id', $user->id)->firstOrFail()->forceFill(['mfa_required' => false, 'mfa_exempt' => true])->save();
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/staff');
    $epoch = session('auth.session_version');
    expect(session('auth.staff_inactivity_generation'))->toBeString();
    $user->authenticationState->forceFill(['session_version' => $epoch + 1])->save();
    auth()->user()->unsetRelation('authenticationState');
    $this->get('/staff')->assertRedirect(route('login'))
        ->assertSessionMissing('auth.staff_inactivity_generation')
        ->assertSessionMissing('auth.session_version');
    $this->assertGuest();
});
