<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;

test('a detail page keeps Home scoped to the signed-in applicant and rejects another application', function (): void {
    seedE2Foundation();
    $own = submitSyntheticApplicant('home-scope-own@example.test', 'Home Scope Own');
    $other = submitSyntheticApplicant('home-scope-other@example.test', 'Home Scope Other');
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle($own['access_token'], '2001-01-01');

    $this->actingAs($applicant)
        ->withSession(['auth.surface' => 'applicant'])
        ->get(route('applicant.release-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/ReleaseDay')
            ->where('applicant_home.identity.applicant_no', $own['attempt']->reference_code));

    $this->get(route('applicant.application', ['attempt' => $other['attempt']->id]))
        ->assertForbidden();
});

test('switching applicants replaces the detail Home context instead of retaining the prior identity', function (): void {
    seedE2Foundation();
    $first = submitSyntheticApplicant('home-switch-first@example.test', 'Home Switch First');
    $second = submitSyntheticApplicant('home-switch-second@example.test', 'Home Switch Second');
    $firstAccount = app(AuthenticateApplicantWithAccessKey::class)->handle($first['access_token'], '2001-01-01');
    $secondAccount = app(AuthenticateApplicantWithAccessKey::class)->handle($second['access_token'], '2001-01-01');

    foreach ([[$firstAccount, $first], [$secondAccount, $second]] as [$account, $submission]) {
        $this->actingAs($account)
            ->withSession(['auth.surface' => 'applicant'])
            ->get(route('applicant.admission-clearance'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('applicant_home.identity.applicant_no', $submission['attempt']->reference_code));
    }
});
