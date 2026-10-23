<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Support\ApplicantAccessKey;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Admission\ApplicantAccount;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

function admissionLifecycleAuthenticatedStaff(User $user): TestCase
{
    $user = User::query()->with('authenticationState')->findOrFail($user->id);

    return test()->actingAs($user)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $user->authenticationState->session_version,
        'auth.staff_inactivity_generation' => 'admission-lifecycle-test',
    ]);
}
test('registrar reissue revokes prior key and authenticates with new key only', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant('reissue@example.test');
    $attempt = $result['attempt'];
    $oldKey = $result['access_token'];
    $oldVoucherToken = $result['credential_handoff']['voucher_token'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $response = admissionLifecycleAuthenticatedStaff($registrar)
        ->post(route('admissions.reissue-access-key', $attempt), [
            'expected_version' => 1,
            'reason' => 'Applicant lost paper slip',
        ]);

    $response->assertRedirect(route('admissions.show', $attempt->fresh()));
    $response->assertSessionHas('reissued_access_key');
    $response->assertSessionHas('credential_handoff');
    $newKey = session('reissued_access_key');
    $newHandoff = session('credential_handoff');

    expect($newKey)->toMatch('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/')
        ->and($newKey)->not->toBe($oldKey)
        ->and($newHandoff['origin'])->toBe('reissued')
        ->and($newHandoff['voucher_token'])->not->toBe($oldVoucherToken)
        ->and($newHandoff['qr_url'])->toContain('#v='.$newHandoff['voucher_token'].'&k='.rawurlencode($newKey));

    $oldChallenge = ApplicantAccessChallenge::query()
        ->where('token_hash', ApplicantAccessKey::hash($oldKey))
        ->firstOrFail();
    expect($oldChallenge->consumed_at)->not->toBeNull();

    $oldVoucherChallenge = ApplicantAccessChallenge::query()
        ->where('purpose', 'login_voucher')
        ->where('token_hash', hash('sha256', $oldVoucherToken))
        ->firstOrFail();
    expect($oldVoucherChallenge->consumed_at)->not->toBeNull();
    expect(fn () => app(AuthenticateApplicantWithAccessKey::class)->handle(
        $oldKey,
        '2001-01-01',
    ))->toThrow(RuntimeException::class);

    $user = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $newKey,
        '2001-01-01',
    );
    expect($user->email)->toBe('reissue@example.test');

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'admission.access_key_reissued',
    ]);

    $this->post(route('logout'));
    $this->assertGuest();

    $this->post(route('applicant.login.voucher'), [
        'voucher_token' => $oldVoucherToken,
    ])
        ->assertRedirect(route('applicant.login'))
        ->assertSessionHasErrors('voucher');
    $this->assertGuest();

    $this->post(route('applicant.login.voucher'), [
        'voucher_token' => $newHandoff['voucher_token'],
    ])->assertRedirect(route('applicant.portal'));
    $this->assertAuthenticated();
});

test('needs_correction end-to-end: request then applicant resubmit returns pending', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant('e2e.correct@example.test');
    $attempt = $result['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $offeredProgramCode = Program::query()
        ->where('institution_id', $attempt->institution_id)
        ->where('is_active', true)
        ->where('is_admission_offered', true)
        ->firstOrFail()
        ->code;

    admissionLifecycleAuthenticatedStaff($registrar)
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
        ->assertInertia(fn (Assert $page) => $page->component('Applicant/Portal'));

    $this->get(route('applicant.schedule'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applicant/Schedule')
            ->where('application.needs_correction', true)
            ->where('application.correction_notes', 'Update phone and preference 1')
        );

    $this->get(route('applicant.correction', $attempt->fresh()))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applicant/Correction')
            ->where('attempt.reference_code', $attempt->reference_code)
            ->has('programs')
            ->where('grade_fields', [])
            ->has('attempt.identity')
        );

    $this->post(route('applicant.correction.store', $attempt->fresh()), [
        'expected_version' => 2,
        'contact_phone' => '09170001111',
        'program_preference_1_code' => $offeredProgramCode,
        'last_school_enrolled' => 'Resubmit High School',
        'strand' => 'ABM',
        'grades' => ['GWA' => '1.90'],
        'resubmission_notes' => 'Updated phone and preference',
    ])->assertRedirect(route('applicant.portal'));

    $fresh = $attempt->fresh();
    expect($fresh->status)->toBe(AdmissionAttempt::STATUS_PENDING)
        ->and($fresh->contact_phone)->toBe('09170001111')
        ->and($fresh->program_preference_1_code)->toBe($offeredProgramCode)
        ->and($fresh->last_school_enrolled)->toBe('Resubmit High School')
        ->and($fresh->strand)->toBe('ABM')
        ->and($fresh->gwa)->toBeNull()
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
        ->assertInertia(fn (Assert $page) => $page->component('Applicant/Portal'));

    $this->get(route('applicant.schedule'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applicant/Schedule')
            ->where('application.can_quiet_amend', true)
            ->where('application.frozen_after_accept', false)
        );

    $this->get(route('applicant.correction', $attempt))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applicant/Correction')
            ->where('mode', 'amend')
            ->has('programs')
            ->where('grade_fields', [])
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

    admissionLifecycleAuthenticatedStaff($registrar)
        ->post(route('admissions.accept', $attempt), ['expected_version' => 1])
        ->assertRedirect();

    expect($attempt->fresh()->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED);

    admissionLifecycleAuthenticatedStaff($registrar)
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
        ->assertInertia(fn (Assert $page) => $page->component('Applicant/Portal'));

    $this->get(route('applicant.schedule'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applicant/Schedule')
            ->where('application.frozen_after_accept', true)
            ->where('application.can_quiet_amend', false)
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

    admissionLifecycleAuthenticatedStaff($registrar)->post(route('admissions.request-correction', $theirs['attempt']), [
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

test('applicant can self-correct legal name and sex while pending but birthdate remains locked', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant('self.amend@example.test', 'OriginalLast');
    $attempt = $result['attempt'];

    $this->post(route('applicant.login.store'), [
        'access_key' => $result['access_token'],
        'birthdate' => '2001-01-01',
    ])->assertRedirect(route('applicant.portal'));

    $this->get(route('applicant.correction', $attempt))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applicant/Correction')
            ->has('attempt.identity')
            ->where('attempt.identity.first_name', 'Test')
            ->where('attempt.identity.last_name', 'OriginalLast')
            ->where('attempt.identity.sex', 'male')
        );

    $this->post(route('applicant.correction.store', $attempt), [
        'expected_version' => 1,
        'identity_first_name' => 'UpdatedFirst',
        'identity_last_name' => 'UpdatedLast',
        'identity_middle_name' => 'Middle',
        'identity_sex' => 'female',
        'identity_birthdate' => '2005-12-31',
    ])->assertRedirect(route('applicant.portal'));

    $fresh = $attempt->fresh();
    expect($fresh->identity->first_name)->toBe('UpdatedFirst')
        ->and($fresh->identity->last_name)->toBe('UpdatedLast')
        ->and($fresh->identity->middle_name)->toBe('Middle')
        ->and($fresh->identity->sex)->toBe('female')
        ->and($fresh->identity->birthdate->format('Y-m-d'))->toBe('2001-01-01');

    expect(ApplicantAccount::where('applicant_identity_id', $attempt->applicant_identity_id)->first()->user->name)
        ->toBe('UpdatedFirst UpdatedLast');
});

test('applicant can correct name during needs_correction and resubmits to pending', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant('resubmit.name@example.test', 'OriginalLast');
    $attempt = $result['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    admissionLifecycleAuthenticatedStaff($registrar)
        ->post(route('admissions.request-correction', $attempt), [
            'expected_version' => 1,
            'reason' => 'Please fix legal name',
        ])
        ->assertRedirect();

    expect($attempt->fresh()->status)->toBe(AdmissionAttempt::STATUS_NEEDS_CORRECTION);

    $this->post(route('logout'));

    $this->post(route('applicant.login.store'), [
        'access_key' => $result['access_token'],
        'birthdate' => '2001-01-01',
    ])->assertRedirect(route('applicant.portal'));

    $this->post(route('applicant.correction.store', $attempt->fresh()), [
        'expected_version' => 2,
        'identity_first_name' => 'ResubmittedFirst',
        'identity_last_name' => 'ResubmittedLast',
    ])->assertRedirect(route('applicant.portal'));

    $fresh = $attempt->fresh();
    expect($fresh->status)->toBe(AdmissionAttempt::STATUS_PENDING)
        ->and($fresh->identity->first_name)->toBe('ResubmittedFirst');
});

test('applicant my application page displays update application link while pending', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant('amend.link@example.test');
    $attempt = $result['attempt'];

    $this->post(route('applicant.login.store'), [
        'access_key' => $result['access_token'],
        'birthdate' => '2001-01-01',
    ])->assertRedirect(route('applicant.portal'));

    $this->get(route('applicant.application', $attempt->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applicant/MyApplication')
            ->where('application.can_amend', true)
            ->where('application.correction_href', fn ($href) => $href !== null)
        );
});
