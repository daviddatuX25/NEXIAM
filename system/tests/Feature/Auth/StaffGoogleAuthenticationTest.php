<?php

use App\Models\Auth\StaffActivationVoucher;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffProviderIdentity;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;
use App\Staffing\Actions\IssueStaffActivationVoucher;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    seedE2Foundation();
});

test('an unlinked Google identity matching active exempt staff does not authenticate or auto-link', function (): void {
    Role::findOrCreate('program_head', 'web');
    $institution = Institution::query()->firstOrFail();

    $staff = User::factory()->create(['email' => 'head@gmail.com']);
    $staff->syncRoles(['program_head']);

    StaffProfile::query()->create([
        'user_id' => $staff->id,
        'institution_id' => $institution->id,
        'office' => 'academic',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);

    StaffAuthenticationState::query()->create([
        'user_id' => $staff->id,
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'mfa_required' => false,
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-sub-head-123',
        'email' => 'head@gmail.com',
        'user' => ['email_verified' => true],
    ]));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'email' => 'No staff account is linked to this Google identity. Please sign in with your password and link Google in your profile.',
        ]);

    $this->assertGuest();
    expect(StaffProviderIdentity::query()->where('user_id', $staff->id)->exists())->toBeFalse();

});

test('an unlinked Google identity matching active enforced staff does not authenticate or auto-link', function (): void {
    Role::findOrCreate('registrar_clerk', 'web');
    $institution = Institution::query()->firstOrFail();

    $staff = User::factory()->create(['email' => 'clerk@gmail.com']);
    $staff->syncRoles(['registrar_clerk']);

    StaffProfile::query()->create([
        'user_id' => $staff->id,
        'institution_id' => $institution->id,
        'office' => 'registrar',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);

    StaffAuthenticationState::query()->create([
        'user_id' => $staff->id,
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'mfa_required' => false,
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-sub-clerk-456',
        'email' => 'clerk@gmail.com',
        'user' => ['email_verified' => true],
    ]));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'email' => 'No staff account is linked to this Google identity. Please sign in with your password and link Google in your profile.',
        ]);

    $this->assertGuest();
    expect(StaffProviderIdentity::query()->where('user_id', $staff->id)->exists())->toBeFalse();

});

test('Google callback rejects an unverified account email', function (): void {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-subject-unverified',
        'email' => 'staff@gmail.com',
        'user' => ['email_verified' => false],
    ]));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Your Google account email is not verified.']);

    $this->assertGuest();
    expect(StaffProviderIdentity::query()->exists())->toBeFalse();
});

test('Google callback rejects an unauthorized email domain', function (): void {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-subject-yahoo',
        'email' => 'staff@yahoo.com',
        'user' => ['email_verified' => true],
    ]));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Google domain [yahoo.com] is not authorized for staff auto-linking.']);

    $this->assertGuest();
    expect(StaffProviderIdentity::query()->exists())->toBeFalse();
});

test('Google callback rejects when no staff account matches the email without auto-linking', function (): void {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-subject-unknown',
        'email' => 'unknown@gmail.com',
        'user' => ['email_verified' => true],
    ]));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'email' => 'No staff account is linked to this Google identity. Please sign in with your password and link Google in your profile.',
        ]);

    $this->assertGuest();
    expect(StaffProviderIdentity::query()->exists())->toBeFalse();
});

test('an already bound Google identity signs in without re-linking', function (): void {
    Role::findOrCreate('program_head', 'web');
    $institution = Institution::query()->firstOrFail();

    $staff = User::factory()->create(['email' => 'bound@gmail.com']);
    $staff->syncRoles(['program_head']);

    StaffProfile::query()->create([
        'user_id' => $staff->id,
        'institution_id' => $institution->id,
        'office' => 'academic',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);

    StaffAuthenticationState::query()->create([
        'user_id' => $staff->id,
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'mfa_required' => false,
        'mfa_exempt' => true,
    ]);

    StaffProviderIdentity::query()->create([
        'user_id' => $staff->id,
        'provider' => 'google',
        'subject' => 'google-sub-already-bound',
        'email_at_link' => 'bound@gmail.com',
        'email_verified_at_link' => true,
        'linked_at' => now(),
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-sub-already-bound',
        'email' => 'bound@gmail.com',
        'user' => ['email_verified' => true],
    ]));

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('staff.home'));
    $this->assertAuthenticatedAs($staff);
    expect(StaffProviderIdentity::query()->where('user_id', $staff->id)->count())->toBe(1);
});
test('a voucher recipient can explicitly link verified Google email without bypassing password or MFA setup', function (): void {
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
    Mail::fake();
    $activationResponse = $this->post('/staff/activate', [
        'reference' => $voucher['public_reference'],
        'token' => $voucher['token'],
    ])->assertRedirect('/staff/account-setup');
    $sessionCookieName = config('session.cookie');
    $sessionCookie = $activationResponse->getCookie($sessionCookieName);
    expect($sessionCookie)->not->toBeNull();
    $this->withCookie($sessionCookieName, $sessionCookie->getValue());

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-setup-recipient-1001',
        'email' => 'recipient@gmail.com',
        'user' => ['email_verified' => true],
    ]));
    $oauthRedirect = $this->get('/staff/account-setup/google')->assertRedirect();
    $sessionCookie = $oauthRedirect->getCookie($sessionCookieName);
    expect($sessionCookie)->not->toBeNull();
    $this->withCookie($sessionCookieName, $sessionCookie->getValue());
    $state->refresh();
    $attempt = StaffAuthenticationAttempt::query()->whereKey(session('staff.pending_attempt_id'))->firstOrFail();
    $redeemedVoucher = StaffActivationVoucher::query()->whereKey($attempt->voucher_id)->firstOrFail();
    $context = session('staff.setup_google_context');
    expect($attempt->purpose)->toBe('activation')
        ->and($attempt->primary_method)->toBe('voucher')
        ->and($attempt->phase)->toBe('email_required')
        ->and($attempt->isExpired())->toBeFalse()
        ->and($attempt->isCompleted())->toBeFalse()
        ->and($attempt->isRevoked())->toBeFalse()
        ->and($attempt->session_binding_digest)->toBe(hash('sha256', session()->getId()))
        ->and($redeemedVoucher->redeemed_at)->not->toBeNull()
        ->and($redeemedVoucher->revoked_at)->toBeNull()
        ->and($redeemedVoucher->expires_at->isFuture())->toBeTrue()
        ->and($context['session_digest'])->toBe(hash('sha256', session()->getId()))
        ->and($context['session_version'])->toBe($state->session_version)
        ->and($context['factor_generation'])->toBe($state->fresh()->two_factor_generation)
        ->and($context['recovery_generation'])->toBeNull()
        ->and($attempt->expected_session_version)->toBe($state->session_version)
        ->and($attempt->expected_factor_generation)->toBe($state->two_factor_generation)
        ->and($attempt->expected_recovery_generation)->toBe($state->recovery_generation);

    $providerGeneration = hash('sha256', StaffProviderIdentity::query()
        ->where('user_id', $staff->id)
        ->orderBy('id')
        ->get(['id', 'provider', 'subject', 'revoked_at'])
        ->toJson());
    expect($context['provider_generation'])->toBe($providerGeneration)
        ->and(now()->lessThan($context['expires_at']))->toBeTrue();
    $this->get(route('auth.google.callback'))
        ->assertRedirect('/staff/account-setup')
        ->assertSessionHas('status');

    expect($staff->fresh()->email)->toBe('recipient@gmail.com')
        ->and($staff->fresh()->email_verified_at)->not->toBeNull()
        ->and(StaffProviderIdentity::query()->where('user_id', $staff->id)->where('subject', 'google-setup-recipient-1001')->exists())->toBeTrue()
        ->and($attempt->fresh()->phase)->toBe('password_required');
    $this->post('/staff/account-setup/password', [
        'password' => 'RecipientSetup!2026',
        'password_confirmation' => 'RecipientSetup!2026',
    ])->assertRedirect('/staff/account-setup');
    expect($attempt->fresh()->phase)->toBe('mfa_required')
        ->and($staff->fresh()->authenticationState->password_set_at)->not->toBeNull()
        ->and(Hash::check('RecipientSetup!2026', $staff->fresh()->password))->toBeTrue();
    $this->post('/staff/account-setup/complete')->assertForbidden();
    expect($attempt->fresh()->phase)->toBe('mfa_required')
        ->and($attempt->fresh()->completed_at)->toBeNull();
    $this->assertGuest();
    $enrollment = $this->postJson('/staff/account-setup/two-factor')->assertOk();
    $this->postJson('/staff/account-setup/two-factor/confirm', [
        'code' => app(Google2FA::class)->getCurrentOtp($enrollment->json('secret')),
    ])->assertOk();
    $this->post('/staff/account-setup/complete')->assertRedirect('/staff');
    $this->assertAuthenticatedAs($staff);
    expect($attempt->fresh()->completed_at)->not->toBeNull();
});
