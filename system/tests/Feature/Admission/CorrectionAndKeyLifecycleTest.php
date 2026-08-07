<?php

use App\Admission\Support\ApplicantAccessKey;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('registrar reissue revokes prior key and authenticates with new key only', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant('reissue@example.test');
    $attempt = $result['attempt'];
    $oldKey = $result['access_token'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $response = $this->actingAs($registrar)
        ->post(route('admissions.reissue-access-key', $attempt), [
            'expected_version' => 1,
            'reason' => 'Applicant lost paper slip',
        ]);

    $response->assertRedirect(route('admissions.show', $attempt->fresh()));
    $response->assertSessionHas('reissued_access_key');
    $newKey = session('reissued_access_key');

    expect($newKey)->toMatch('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/')
        ->and($newKey)->not->toBe($oldKey);

    $oldChallenge = ApplicantAccessChallenge::query()
        ->where('token_hash', ApplicantAccessKey::hash($oldKey))
        ->firstOrFail();
    expect($oldChallenge->consumed_at)->not->toBeNull();

    expect(fn () => app(\App\Admission\Actions\AuthenticateApplicantWithAccessKey::class)->handle(
        $oldKey,
        '2001-01-01',
    ))->toThrow(RuntimeException::class);

    $user = app(\App\Admission\Actions\AuthenticateApplicantWithAccessKey::class)->handle(
        $newKey,
        '2001-01-01',
    );
    expect($user->email)->toBe('reissue@example.test');

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'admission.access_key_reissued',
    ]);
});

test('needs_correction end-to-end: request then applicant resubmit returns pending', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant('e2e.correct@example.test');
    $attempt = $result['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->post(route('admissions.request-correction', $attempt), [
            'expected_version' => 1,
            'reason' => 'Update phone and preference 1',
        ])
        ->assertRedirect();

    expect($attempt->fresh()->status)->toBe(AdmissionAttempt::STATUS_NEEDS_CORRECTION);

    $this->post(route('logout'));
    $this->assertGuest();

    $this->post(route('applicant.login.store'), [
        'access_key' => $result['access_token'],
        'birthdate' => '2001-01-01',
    ])->assertRedirect(route('applicant.portal'));

    $this->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applicant/Dashboard')
            ->where('attempts.0.needs_correction', true)
            ->where('attempts.0.correction_notes', 'Update phone and preference 1')
        );

    $this->get(route('applicant.correction', $attempt->fresh()))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applicant/Correction')
            ->where('attempt.reference_code', $attempt->reference_code)
            ->has('programs')
            ->has('grade_fields')
            ->has('attempt.identity')
        );

    $this->post(route('applicant.correction.store', $attempt->fresh()), [
        'expected_version' => 2,
        'contact_phone' => '09170001111',
        'program_preference_1_code' => 'BSPSY',
        'last_school_enrolled' => 'Resubmit High School',
        'strand' => 'ABM',
        'grades' => ['GWA' => '1.90'],
        'resubmission_notes' => 'Updated phone and preference',
    ])->assertRedirect(route('applicant.portal'));

    $fresh = $attempt->fresh();
    expect($fresh->status)->toBe(AdmissionAttempt::STATUS_PENDING)
        ->and($fresh->contact_phone)->toBe('09170001111')
        ->and($fresh->program_preference_1_code)->toBe('BSPSY')
        ->and($fresh->last_school_enrolled)->toBe('Resubmit High School')
        ->and($fresh->strand)->toBe('ABM')
        ->and((string) $fresh->gwa)->toBe('1.90')
        ->and($fresh->version)->toBe(3);

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'admission.correction_resubmitted',
    ]);
});

test('applicant can quietly amend while pending and stays pending', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant('quiet.amend@example.test');
    $attempt = $result['attempt'];

    $this->post(route('applicant.login.store'), [
        'access_key' => $result['access_token'],
        'birthdate' => '2001-01-01',
    ])->assertRedirect(route('applicant.portal'));

    $this->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applicant/Dashboard')
            ->where('attempts.0.can_quiet_amend', true)
            ->where('attempts.0.frozen_after_accept', false)
        );

    $this->get(route('applicant.correction', $attempt))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applicant/Correction')
            ->where('mode', 'amend')
            ->has('programs')
            ->has('grade_fields')
        );

    $this->post(route('applicant.correction.store', $attempt), [
        'expected_version' => 1,
        'contact_phone' => '09171112222',
        'city' => 'Quezon City',
    ])->assertRedirect(route('applicant.portal'));

    $fresh = $attempt->fresh();
    expect($fresh->status)->toBe(AdmissionAttempt::STATUS_PENDING)
        ->and($fresh->contact_phone)->toBe('09171112222')
        ->and($fresh->city)->toBe('Quezon City')
        ->and($fresh->version)->toBe(2);

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'admission.applicant_amended_pending',
    ]);
});

test('accepted applications freeze quiet portal and registrar correct', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant('accepted.freeze@example.test');
    $attempt = $result['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->post(route('admissions.accept', $attempt), ['expected_version' => 1])
        ->assertRedirect();

    expect($attempt->fresh()->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED);

    $this->actingAs($registrar)
        ->post(route('admissions.correct', $attempt->fresh()), [
            'expected_version' => 2,
            'contact_phone' => '09999999999',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('version');

    $this->post(route('logout'));

    $this->post(route('applicant.login.store'), [
        'access_key' => $result['access_token'],
        'birthdate' => '2001-01-01',
    ])->assertRedirect(route('applicant.portal'));

    $this->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('attempts.0.frozen_after_accept', true)
            ->where('attempts.0.can_quiet_amend', false)
        );

    $this->get(route('applicant.correction', $attempt->fresh()))
        ->assertRedirect(route('applicant.portal'));

    $this->post(route('applicant.correction.store', $attempt->fresh()), [
        'expected_version' => 2,
        'contact_phone' => '09170000000',
    ])->assertRedirect(route('applicant.portal'));

    expect($attempt->fresh()->contact_phone)->not->toBe('09170000000')
        ->and($attempt->fresh()->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED);
});

test('applicant cannot resubmit another identity correction', function (): void {
    seedE2Foundation();
    $mine = submitSyntheticApplicant('own.correct@example.test', 'OwnPerson');
    $theirs = submitSyntheticApplicant('other.correct@example.test', 'OtherPerson');
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)->post(route('admissions.request-correction', $theirs['attempt']), [
        'expected_version' => 1,
        'reason' => 'Fix address',
    ]);

    $this->post(route('logout'));

    $this->post(route('applicant.login.store'), [
        'access_key' => $mine['access_token'],
        'birthdate' => '2001-01-01',
    ])->assertRedirect();

    $this->get(route('applicant.correction', $theirs['attempt']->fresh()))
        ->assertForbidden();

    $this->post(route('applicant.correction.store', $theirs['attempt']->fresh()), [
        'expected_version' => 2,
        'city' => 'Hacked',
    ])->assertForbidden();
});
