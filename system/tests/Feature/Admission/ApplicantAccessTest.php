<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Support\ApplicantAccessKey;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AuditEvent;
use App\Models\User;

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

    $response = $this->post(route('apply.submit'), [
        'academic_cycle_id' => $cycle->id,
        'application_window_id' => $window->id,
        'first_name' => 'Flash',
        'last_name' => 'Key',
        'email' => 'flash-key@example.test',
        'birthdate' => '2001-01-01',
        'sex' => 'female',
        'applicant_type' => 'new',
        'program_preference_1' => 'BSN',
        'terms_accepted' => true,
        'last_school_enrolled' => 'Sample SHS',
        'strand' => 'STEM',
        'grades' => ['GWA' => '1.50', 'ENG' => '88', 'FIL' => '90', 'MATH' => '85', 'SCI' => '87'],
        'city' => 'Tagudin',
    ]);

    $response->assertRedirect(route('apply.confirmation'));
    $response->assertSessionHas('access_key');
    $response->assertSessionHas('reference_code');

    $follow = $this->get(route('apply.confirmation'));
    $follow->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admission/Confirmation')
        ->where('access_key', fn ($key) => is_string($key) && preg_match('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $key) === 1)
    );

    // Second load must not still expose the key from session.
    $this->get(route('apply.confirmation'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admission/Confirmation')
            ->where('access_key', null)
        );
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
