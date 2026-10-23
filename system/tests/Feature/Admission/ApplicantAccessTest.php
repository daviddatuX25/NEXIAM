<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Support\ApplicantAccessKey;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Admission\ApplicantAccount;
use App\Models\Communications\PublicPolicy;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\User;
use Database\Seeders\PublicIntakeConsentDemoSeeder;

test('access key plus birthdate authenticates and keeps key reusable', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant('portal@example.test');

    $user = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $result['access_token'],
        '2001-01-01',
    );

    expect($user->email)->toBe('portal@example.test')
        ->and($user->applicantAccount)->not->toBeNull();

    $challenge = ApplicantAccessChallenge::query()
        ->where('token_hash', ApplicantAccessKey::hash($result['access_token']))
        ->firstOrFail();

    expect($challenge->consumed_at)->toBeNull();

    $again = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $result['access_token'],
        '2001-01-01',
    );

    expect($again->id)->toBe($user->id);
});

test('birthdate alone cannot authenticate', function (): void {
    seedE2Foundation();
    submitSyntheticApplicant('bday@example.test');

    expect(fn () => app(AuthenticateApplicantWithAccessKey::class)->handle(
        str_repeat('x', 48),
        '2001-01-01',
    ))->toThrow(RuntimeException::class);
});

test('wrong birthdate with valid key fails', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant('wrongbday@example.test');

    expect(fn () => app(AuthenticateApplicantWithAccessKey::class)->handle(
        $result['access_token'],
        '1999-12-31',
    ))->toThrow(RuntimeException::class);
});

test('http applicant login redirects to portal', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant('http-login@example.test');

    $this->post(route('applicant.login.store'), [
        'access_key' => $result['access_token'],
        'birthdate' => '2001-01-01',
    ])->assertRedirect(route('applicant.portal'));

    $this->assertAuthenticated();
    expect(User::query()->where('email', 'http-login@example.test')->exists())->toBeTrue();
});

test('confirmation flash exposes access key once after submit', function (): void {
    seedE2Foundation();

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
    (new PublicIntakeConsentDemoSeeder)->run();
    $institution = Institution::current();
    $policy = PublicPolicy::designatedIntake((int) $institution->getKey())
        ?? throw new RuntimeException('A designated public intake policy is required.');
    $revision = $policy->publishedRevision
        ?? throw new RuntimeException('The designated policy must have a published revision.');

    $response = $this->post(route('apply.submit'), [
        'academic_cycle_id' => $cycle->id,
        'application_window_id' => $window->id,
        'public_policy_id' => $policy->id,
        'public_policy_revision_id' => $revision->id,
        'public_policy_revision_digest' => $revision->contentDigest(),
        'first_name' => 'Flash',
        'last_name' => 'Key',
        'email' => 'flash-key@example.test',
        'birthdate' => '2001-01-01',
        'sex' => 'female',
        'applicant_type' => 'new',
        'program_preference_1' => 'BSIT',
        'terms_accepted' => true,
        'last_school_enrolled' => 'Sample SHS',
        'strand' => 'STEM',
        'grades' => ['GWA' => '1.50', 'ENG' => '88', 'FIL' => '90', 'MATH' => '85', 'SCI' => '87'],
        'city' => 'Tagudin',
    ]);

    $response->assertRedirect(route('apply.confirmation'));
    $response->assertPlainCookie('nexiam_applicant_submitted', '1');
    $response->assertSessionHas('access_key');
    $response->assertSessionHas('credential_handoff');
    $attempt = AdmissionAttempt::query()
        ->where('reference_code', session('confirmation_reference_code'))
        ->firstOrFail();
    $existingApplicant = User::factory()->create([
        'email' => 'existing-applicant-account@example.test',
    ]);
    ApplicantAccount::query()->create([
        'applicant_identity_id' => $attempt->applicant_identity_id,
        'user_id' => $existingApplicant->id,
        'setup_completed_at' => now(),
    ]);

    $follow = $this->get(route('apply.confirmation'));
    $follow->assertPlainCookie('nexiam_applicant_submitted', '1');
    $follow->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admission/Confirmation')
        ->where('access_key', fn ($key) => is_string($key) && preg_match('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $key) === 1)
        ->where('credential_handoff.origin', 'submitted')
        ->where('credential_handoff.qr_url', fn ($url) => is_string($url) && str_contains($url, '/applicant/login#v=') && str_contains($url, '&k='))
        ->has('intake_requirements', 6)
        ->where('intake_requirements.0.label', 'High School Report Card (Form 138 / SF9 - Original)')
    );

    // Reload keeps the non-sensitive reminder, but never replays the key or voucher handoff.
    $this->get(route('apply.confirmation'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admission/Confirmation')
            ->where('access_key', null)
            ->where('credential_handoff', null)
            ->has('intake_requirements', 6)
        );

    $this->post(route('apply.confirmation.enter'))
        ->assertRedirect(route('applicant.portal'));
    $this->assertAuthenticatedAs($existingApplicant);
    expect(ApplicantAccessChallenge::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('purpose', 'login_voucher')
        ->whereNotNull('consumed_at')
        ->exists())->toBeTrue();
    $this->get(route('apply.form'))->assertOk();
    $this->get(route('apply.confirmation'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admission/Confirmation')
            ->where('intake_requirements', null)
        );
});

test('an authenticated applicant can enter their matching confirmation directly', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant('existing-login@example.test');
    $attempt = $result['attempt'];
    $user = User::factory()->create(['email' => 'existing-login-account@example.test']);

    ApplicantAccount::query()->create([
        'applicant_identity_id' => $attempt->applicant_identity_id,
        'user_id' => $user->id,
        'setup_completed_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['confirmation_reference_code' => $attempt->reference_code])
        ->post(route('apply.confirmation.enter'))
        ->assertRedirect(route('applicant.portal'));

    $this->assertAuthenticatedAs($user);
});

test('confirmation entry does not switch a mismatched authenticated applicant', function (): void {
    seedE2Foundation();
    $target = submitSyntheticApplicant('target-applicant@example.test', 'Target');
    $other = submitSyntheticApplicant('other-applicant@example.test', 'Other');
    $otherUser = User::factory()->create(['email' => 'other-applicant-account@example.test']);

    ApplicantAccount::query()->create([
        'applicant_identity_id' => $other['attempt']->applicant_identity_id,
        'user_id' => $otherUser->id,
        'setup_completed_at' => now(),
    ]);

    $this->actingAs($otherUser)
        ->withSession([
            'confirmation_reference_code' => $target['attempt']->reference_code,
            'confirmation_voucher_token' => $target['credential_handoff']['voucher_token'],
        ])
        ->post(route('apply.confirmation.enter'))
        ->assertRedirect(route('apply.confirmation'))
        ->assertSessionHas('portal_entry_error');

    $this->assertAuthenticatedAs($otherUser);
    expect(ApplicantAccessChallenge::query()
        ->where('admission_attempt_id', $target['attempt']->id)
        ->where('purpose', 'login_voucher')
        ->whereNull('consumed_at')
        ->exists())->toBeTrue();
});

test('applicant can logout and returns to applicant login', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant('logout@example.test');

    $this->post(route('applicant.login.store'), [
        'access_key' => $result['access_token'],
        'birthdate' => '2001-01-01',
    ])->assertRedirect(route('applicant.portal'));

    $this->assertAuthenticated();

    $this->post(route('logout'))
        ->assertRedirect(route('applicant.login'));

    $this->assertGuest();

    expect(AuditEvent::query()
        ->where('event_type', 'auth.applicant_logout')
        ->exists())->toBeTrue();
});

test('seeded demo accepted applicant can sign in with fixed access key', function (): void {
    seedE2Foundation();
    (new PublicIntakeConsentDemoSeeder)->run();
    seedE2JourneyFoundation();

    $accepted = config('seed.applicants.accepted');

    $this->get(route('applicant.login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Login')
            ->where('demo.access_key', 'PORT-DEFL')
        );

    $this->post(route('applicant.login.store'), [
        'access_key' => $accepted['access_key'],
        'birthdate' => $accepted['birthdate'],
    ])->assertRedirect(route('applicant.portal'));

    $this->assertAuthenticated();

    $this->get(route('applicant.schedule'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Schedule')
            ->where('page_state', 'waiting_for_reservation_open')
            ->has('pipeline', 4)
        );
});

test('access key shape validation and disambiguation handles lookalike characters', function (): void {
    // Valid 8-character keys
    expect(ApplicantAccessKey::isValidShape('ABCD-EFGH'))->toBeTrue()
        ->and(ApplicantAccessKey::isValidShape('ABCDEFGH'))->toBeTrue()
        ->and(ApplicantAccessKey::isValidShape('2345-6789'))->toBeTrue()
        ->and(ApplicantAccessKey::isValidShape('PORT-DEFL'))->toBeTrue()
        ->and(ApplicantAccessKey::isValidShape('DEMO-ACPT'))->toBeTrue();

    // Disambiguation: 0 maps to O, 1 maps to I
    expect(ApplicantAccessKey::normalize('ABCD-EFG0'))->toBe('ABCDEFGO')
        ->and(ApplicantAccessKey::normalize('ABCD-EFG1'))->toBe('ABCDEFGI')
        ->and(ApplicantAccessKey::normalize('abcd-efgh'))->toBe('ABCDEFGH');

    // Invalid lengths or characters
    expect(ApplicantAccessKey::isValidShape('SHORT'))->toBeFalse()
        ->and(ApplicantAccessKey::isValidShape('WAY-TOO-LONG-KEY'))->toBeFalse()
        ->and(ApplicantAccessKey::isValidShape('!@#$-%^&*'))->toBeFalse();
});

test('three failed birthdate attempts locks the access key for 15 minutes', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant('lockout-test@example.test');
    $key = $result['access_token'];

    // Attempt 1: Wrong birthdate
    $this->post(route('applicant.login.store'), [
        'access_key' => $key,
        'birthdate' => '1999-01-01',
    ])->assertSessionHasErrors('access_key');

    // Attempt 2: Wrong birthdate
    $this->post(route('applicant.login.store'), [
        'access_key' => $key,
        'birthdate' => '1999-01-02',
    ])->assertSessionHasErrors('access_key');

    // Attempt 3: Wrong birthdate -> Triggers lockout
    $this->post(route('applicant.login.store'), [
        'access_key' => $key,
        'birthdate' => '1999-01-03',
    ])->assertSessionHasErrors('access_key');

    // Attempt 4: Even with the CORRECT birthdate, login is blocked due to active lockout
    $this->post(route('applicant.login.store'), [
        'access_key' => $key,
        'birthdate' => '2001-01-01',
    ])->assertSessionHasErrors([
        'access_key' => 'This access key is temporarily locked due to repeated failed attempts. Please try again in 15 minute(s).',
    ]);

    $this->assertGuest();
});

test('temporary qr login voucher logs applicant in once and preserves durable access key', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant('voucher-login@example.test');
    $handoff = $result['credential_handoff'];
    $voucherToken = $handoff['voucher_token'];
    $accessKey = $result['access_token'];

    expect($handoff['origin'])->toBe('submitted')
        ->and($voucherToken)->toMatch('/^[0-9a-f]{32}$/')
        ->and($handoff['qr_url'])->toContain('#v='.$voucherToken.'&k='.rawurlencode($accessKey));

    $voucherChallenge = ApplicantAccessChallenge::query()
        ->where('purpose', 'login_voucher')
        ->where('token_hash', hash('sha256', $voucherToken))
        ->firstOrFail();
    expect($voucherChallenge->consumed_at)->toBeNull();

    $this->post(route('applicant.login.voucher'), [
        'voucher_token' => $voucherToken,
    ])->assertRedirect(route('applicant.portal'));

    $this->assertAuthenticated();
    expect(User::query()->where('email', 'voucher-login@example.test')->exists())->toBeTrue();
    expect($voucherChallenge->fresh()->consumed_at)->not->toBeNull();

    $accessKeyChallenge = ApplicantAccessChallenge::query()
        ->where('purpose', 'access_key')
        ->where('token_hash', ApplicantAccessKey::hash($accessKey))
        ->firstOrFail();
    expect($accessKeyChallenge->consumed_at)->toBeNull();

    expect(AuditEvent::query()
        ->where('event_type', 'admission.applicant_voucher_login')
        ->exists())->toBeTrue();

    $this->post(route('logout'))->assertRedirect(route('applicant.login'));
    $this->assertGuest();

    // Second use of the same single-use voucher is rejected
    $this->post(route('applicant.login.voucher'), [
        'voucher_token' => $voucherToken,
    ])
        ->assertRedirect(route('applicant.login'))
        ->assertSessionHasErrors('voucher');
    $this->assertGuest();

    // Durable access key + birthdate still works after voucher consumption
    $this->post(route('applicant.login.store'), [
        'access_key' => $accessKey,
        'birthdate' => '2001-01-01',
    ])->assertRedirect(route('applicant.portal'));
    $this->assertAuthenticated();
});

test('applicant submitted cookie drives has_submitted_application in inertia shared props', function (): void {
    seedE2Foundation();

    // Without cookie
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('has_submitted_application', false)
        );

    // With unencrypted cookie
    $this->withUnencryptedCookie('nexiam_applicant_submitted', '1')
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('has_submitted_application', true)
        );
});
