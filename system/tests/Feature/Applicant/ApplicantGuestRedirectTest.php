<?php

test('unauthenticated admission support requests redirect to applicant login', function (): void {
    $this->get('/applicant/admission-support?group=offices')
        ->assertRedirect(route('applicant.login'));
});

test('unauthenticated applicant portal requests redirect to applicant login', function (): void {
    $this->get('/applicant/portal')
        ->assertRedirect(route('applicant.login'));
});

test('unauthenticated staff requests redirect to staff login', function (): void {
    $this->get('/staff')
        ->assertRedirect(route('login'));
});

test('applicant login redirects to the requested applicant destination', function (): void {
    seedE2Foundation();
    $submission = submitSyntheticApplicant('guest.redirect@example.test');
    $intendedUrl = url('/applicant/admission-support?group=offices');

    $this->get('/applicant/admission-support?group=offices')
        ->assertRedirect(route('applicant.login'));

    $this->post(route('applicant.login.store'), [
        'access_key' => $submission['access_token'],
        'birthdate' => '2001-01-01',
    ])->assertRedirect($intendedUrl);
});
