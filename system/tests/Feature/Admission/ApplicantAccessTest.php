<?php

use App\Admission\Support\ApplicantAccessKey;
use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Models\Admission\ApplicantAccessChallenge;
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
    ])->assertRedirect(route('applicant.schedule'));

    $this->assertAuthenticated();
    expect(User::query()->where('email', 'http-login@example.test')->exists())->toBeTrue();
});

test('confirmation flash exposes access key once after submit', function (): void {
    seedE2Foundation();

    $cycle = \App\Models\Foundation\AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $window = \App\Models\Foundation\ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();

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
    ])->assertRedirect(route('applicant.schedule'));

    $this->assertAuthenticated();

    $this->post(route('logout'))
        ->assertRedirect(route('applicant.login'));

    $this->assertGuest();

    expect(\App\Models\Foundation\AuditEvent::query()
        ->where('event_type', 'auth.applicant_logout')
        ->exists())->toBeTrue();
});

test('seeded demo accepted applicant can sign in with fixed access key', function (): void {
    seedE2Foundation();

    $accepted = config('seed.applicants.accepted');

    $this->get(route('applicant.login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Login')
            ->where('demo.access_key', $accepted['access_key'])
        );

    $this->post(route('applicant.login.store'), [
        'access_key' => $accepted['access_key'],
        'birthdate' => $accepted['birthdate'],
    ])->assertRedirect(route('applicant.schedule'));

    $this->assertAuthenticated();

    $this->get(route('applicant.schedule'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Schedule')
            ->where('page_state', 'waiting_for_reservation_open')
            ->has('pipeline', 4)
        );
});
