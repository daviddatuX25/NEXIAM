<?php

use App\Models\User;

test('clean applicant access key login redirects directly to applicant.portal and stamps applicant surface', function (): void {
    seedE2Foundation();
    $submission = submitSyntheticApplicant('clean.login@example.test');

    $this->post(route('applicant.login.store'), [
        'access_key' => $submission['access_token'],
        'birthdate' => '2001-01-01',
    ])
        ->assertRedirect(route('applicant.portal'))
        ->assertSessionHas('auth.surface', 'applicant')
        ->assertSessionMissing('auth.staff_inactivity_generation')
        ->assertSessionMissing('staff.pending_attempt_id');
});

test('applicant access key login purges stale url.intended and stale staff session markers', function (): void {
    seedE2Foundation();
    $submission = submitSyntheticApplicant('stale.intent@example.test');

    $this->withSession([
        'url.intended' => '/staff/profile',
        'auth.surface' => 'staff',
        'auth.session_version' => 12,
        'staff.pending_attempt_id' => 'some-attempt',
    ])
        ->post(route('applicant.login.store'), [
            'access_key' => $submission['access_token'],
            'birthdate' => '2001-01-01',
        ])
        ->assertRedirect(route('applicant.portal'))
        ->assertSessionHas('auth.surface', 'applicant')
        ->assertSessionMissing('url.intended')
        ->assertSessionMissing('auth.session_version')
        ->assertSessionMissing('staff.pending_attempt_id');
});

test('clean applicant voucher login redirects directly to applicant.portal and stamps applicant surface', function (): void {
    seedE2Foundation();
    $submission = submitSyntheticApplicant('clean.voucher@example.test');
    $voucherToken = $submission['credential_handoff']['voucher_token'];

    $this->post(route('applicant.login.voucher'), [
        'voucher_token' => $voucherToken,
    ])
        ->assertRedirect(route('applicant.portal'))
        ->assertSessionHas('auth.surface', 'applicant');
});

test('applicant voucher login purges stale staff session markers', function (): void {
    seedE2Foundation();
    $submission = submitSyntheticApplicant('stale.voucher@example.test');
    $voucherToken = $submission['credential_handoff']['voucher_token'];

    $this->withSession([
        'auth.surface' => 'staff',
        'staff.pending_attempt_id' => 'some-attempt',
        'url.intended' => '/staff/profile',
    ])
        ->post(route('applicant.login.voucher'), [
            'voucher_token' => $voucherToken,
        ])
        ->assertRedirect(route('applicant.portal'))
        ->assertSessionHas('auth.surface', 'applicant')
        ->assertSessionMissing('staff.pending_attempt_id');
});
test('valid staff sessions recover from the applicant dashboard before projection', function (): void {
    seedE2Foundation();
    $staff = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $staff->authenticationState()->update([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
        'mfa_required' => false,
    ]);

    $this->post(route('login.store'), [
        'email' => $staff->email,
        'password' => 'password',
    ])->assertRedirect(route('staff.home'));

    $this->get('/applicant/portal')
        ->assertRedirect(route('staff.home'))
        ->assertDontSee('Applicant Menu');
});
test('old staff generation from an applicant page is rejected without clearing applicant authentication', function (): void {
    seedE2Foundation();
    $submission = submitSyntheticApplicant('stale.staff.generation@example.test');

    $this->post(route('applicant.login.store'), [
        'access_key' => $submission['access_token'],
        'birthdate' => '2001-01-01',
    ])->assertRedirect(route('applicant.portal'));

    $staleApplicantPage = $this->withHeader('X-Staff-Generation', 'former-staff-generation')
        ->getJson(route('applicant.portal'));
    $staleApplicantPage->assertStatus(409)
        ->assertExactJson(['error' => 'staff_account_changed'])
        ->assertHeader('X-Inertia-Location', route('staff.home'));
    expect($staleApplicantPage->headers->get('Cache-Control'))->toContain('no-store');

    expect(session('auth.surface'))->toBe('applicant')
        ->and(session()->has('auth.staff_inactivity_generation'))->toBeFalse();
});
