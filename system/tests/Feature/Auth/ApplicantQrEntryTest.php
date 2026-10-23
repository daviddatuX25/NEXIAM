<?php

declare(strict_types=1);

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Support\ApplicantQrCode;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffSessionLease;
use App\Models\User;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;

beforeEach(function (): void {
    config(['app.url' => 'https://nexiam.test']);
});

it('accepts historical raw locators and only canonical same-origin HTTPS or local loopback links', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('qr-entry-normalize@example.test')['attempt'];
    $locator = ApplicantQrCode::issue($attempt);
    $entryUrl = ApplicantQrCode::entryUrl($attempt);

    expect(ApplicantQrCode::normalize($locator))->toBe($locator)
        ->and(ApplicantQrCode::normalize($entryUrl))->toBe($locator);
    config(['app.url' => 'https://nexiam.test/']);
    expect(ApplicantQrCode::entryUrl($attempt))->toBe($entryUrl);
    config(['app.url' => 'https://nexiam.test/subpath']);
    expect(fn () => ApplicantQrCode::entryUrl($attempt))->toThrow(RuntimeException::class)
        ->and(fn () => ApplicantQrCode::normalize($entryUrl))->toThrow(RuntimeException::class);
    config(['app.url' => 'https://nexiam.test']);

    config(['app.url' => 'http://localhost:18000']);
    $localEntryUrl = ApplicantQrCode::entryUrl($attempt);
    expect($localEntryUrl)->toStartWith('http://localhost:18000/scan/applicant/')
        ->and(ApplicantQrCode::normalize($localEntryUrl))->toBe($locator);

    config(['app.url' => 'http://192.168.1.15:18000']);
    expect(fn () => ApplicantQrCode::entryUrl($attempt))->toThrow(RuntimeException::class);
    config(['app.url' => 'https://nexiam.test']);

    foreach ([
        'https://elsewhere.test/scan/applicant/'.$locator,
        'http://nexiam.test/scan/applicant/'.$locator,
        'https://nexiam.test/applicant/login#v='.$locator.'&k=ABCD-EFGH',
        'https://nexiam.test/scan/applicant/'.$locator.'?next=https://elsewhere.test',
        'https://nexiam.test/scan/applicant/'.$locator.'#fragment',
    ] as $invalidQr) {
        expect(fn () => ApplicantQrCode::normalize($invalidQr))->toThrow(RuntimeException::class);
    }
});

it('continues a valid external-camera applicant lookup after completed staff authentication', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('qr-entry-continuation@example.test')['attempt'];
    $locator = ApplicantQrCode::issue($attempt);
    $staff = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    StaffAuthenticationState::query()->where('user_id', $staff->id)->firstOrFail()->forceFill(['mfa_required' => false, 'mfa_exempt' => true])->save();

    $this->get(ApplicantQrCode::entryUrl($attempt))
        ->assertRedirect(route('login'));

    $this->post('/login', [
        'email' => 'program.head.bsit@nexiam.test',
        'password' => 'password',
    ])->assertRedirect(route('staff.scan', ['code' => $locator]));

    $this->assertAuthenticatedAs(User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail());
});
it('resumes the applicant lookup only after the required staff MFA challenge completes', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('qr-entry-mfa@example.test')['attempt'];
    $locator = ApplicantQrCode::issue($attempt);
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

    $this->get(ApplicantQrCode::entryUrl($attempt))
        ->assertRedirect(route('login'));
    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/two-factor-challenge');

    $code = app(Google2FA::class)->getCurrentOtp($secret);
    $this->post(route('two-factor.login.store'), ['code' => $code])
        ->assertRedirect(route('staff.scan', ['code' => $locator]));
});
it('does not redeem a permanent applicant locator as a temporary login voucher', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('qr-entry-wrong-voucher-purpose@example.test')['attempt'];
    $locator = ApplicantQrCode::issue($attempt);

    $this->post(route('applicant.login.voucher'), ['voucher_token' => $locator])
        ->assertRedirect(route('applicant.login'))
        ->assertSessionHasErrors('voucher');

    $this->assertGuest();
});

it('discards an expired scanned-applicant continuation and preserves the safe staff landing', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('qr-entry-expired@example.test')['attempt'];
    $locator = ApplicantQrCode::issue($attempt);
    $staff = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    StaffAuthenticationState::query()->where('user_id', $staff->id)->firstOrFail()->forceFill(['mfa_required' => false, 'mfa_exempt' => true])->save();

    $this->get(ApplicantQrCode::entryUrl($attempt))
        ->assertRedirect(route('login'));

    $this->withSession([
        'auth.pending_applicant_lookup' => [
            'locator' => $locator,
            'expires_at' => now()->subSecond()->timestamp,
        ],
        'url.intended' => 'https://attacker.test/steal',
    ])->post('/login', [
        'email' => 'program.head.bsit@nexiam.test',
        'password' => 'password',
    ])->assertRedirect(route('staff.home'));
});

it('keeps an authenticated applicant on their own QR portal and hides another applicant locator', function (): void {
    seedE2Foundation();
    $ownSubmission = submitSyntheticApplicant('qr-entry-own@example.test');
    $foreignSubmission = submitSyntheticApplicant('qr-entry-foreign@example.test', 'OtherApplicant');
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $ownSubmission['access_token'],
        '2001-01-01',
    );

    $this->actingAs($applicant)
        ->get(ApplicantQrCode::entryUrl($ownSubmission['attempt']))
        ->assertRedirect(route('applicant.qr'));

    $this->actingAs($applicant)
        ->get(ApplicantQrCode::entryUrl($foreignSubmission['attempt']))
        ->assertNotFound();
});

it('retains a fresh QR lookup through an expired staff lease and reauthentication', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('qr-expired-staff@example.test')['attempt'];
    $staff = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    StaffAuthenticationState::query()->where('user_id', $staff->id)->firstOrFail()->forceFill(['mfa_required' => false, 'mfa_exempt' => true])->save();
    $this->post('/login', ['email' => $staff->email, 'password' => 'password'])
        ->assertRedirect('/staff');
    StaffSessionLease::query()->where('user_id', $staff->id)
        ->update(['idle_expires_at' => now()->subSecond()]);

    $this->get(ApplicantQrCode::entryUrl($attempt))->assertRedirect('/login?expired=1');
    $this->assertGuest();
    $this->post('/login', ['email' => $staff->email, 'password' => 'password'])
        ->assertRedirect(route('staff.scan', ['code' => ApplicantQrCode::issue($attempt)]));
});

it('retains only the fresh QR locator when a stale security version requires staff reauthentication', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('qr-revoked-staff@example.test')['attempt'];
    $staff = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    StaffAuthenticationState::query()->where('user_id', $staff->id)->firstOrFail()->forceFill(['mfa_required' => false, 'mfa_exempt' => true])->save();
    $this->post('/login', ['email' => $staff->email, 'password' => 'password'])
        ->assertRedirect('/staff');
    $staff->authenticationState()->increment('session_version');

    $this->get(ApplicantQrCode::entryUrl($attempt))->assertRedirect(route('login'));
    $this->assertGuest();
    $this->post('/login', ['email' => $staff->email, 'password' => 'password'])
        ->assertRedirect(route('staff.scan', ['code' => ApplicantQrCode::issue($attempt)]));
});
