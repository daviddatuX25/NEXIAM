<?php

use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admissions show denies users without registrar view capability', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('detail.deny@example.test')['attempt'];
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('admissions.show', $attempt))
        ->assertForbidden();
});

test('registrar can view attempt detail payload', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('detail.view@example.test', 'Detail')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('admissions.show', $attempt))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admission/Show')
            ->where('page_state', 'ready')
            ->where('attempt.reference_code', $attempt->reference_code)
            ->where('attempt.status', 'pending')
            ->where('capabilities.accept', true)
            ->where('capabilities.correct', true)
            ->has('attempt.identity')
            ->has('attempt.grades')
        );
});

test('request correction moves pending to needs_correction with reason', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('detail.req@example.test')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->post(route('admissions.request-correction', $attempt), [
            'expected_version' => 1,
            'reason' => 'Missing Form 138 scan',
        ])
        ->assertRedirect();

    $fresh = $attempt->fresh();
    expect($fresh->status)->toBe(AdmissionAttempt::STATUS_NEEDS_CORRECTION)
        ->and($fresh->correction_notes)->toBe('Missing Form 138 scan')
        ->and($fresh->version)->toBe(2);

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'admission.correction_requested',
    ]);
});

test('in-place correct keeps pending status', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('detail.correct@example.test')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->post(route('admissions.correct', $attempt), [
            'expected_version' => 1,
            'contact_email' => 'fixed.email@example.test',
            'program_preference_1_code' => 'BSPSY',
            'last_school_enrolled' => 'Corrected Senior High',
            'strand' => 'HUMSS',
            'applicant_type' => 'transferee',
            'grades' => [
                'GWA' => '1.75',
                'ENG' => '88',
            ],
            'correction_notes' => 'Fixed email, school, strand, and grades',
        ])
        ->assertRedirect();

    $fresh = $attempt->fresh(['gradeValues']);
    expect($fresh->status)->toBe(AdmissionAttempt::STATUS_PENDING)
        ->and($fresh->contact_email)->toBe('fixed.email@example.test')
        ->and($fresh->program_preference_1_code)->toBe('BSPSY')
        ->and($fresh->last_school_enrolled)->toBe('Corrected Senior High')
        ->and($fresh->strand)->toBe('HUMSS')
        ->and($fresh->applicant_type)->toBe('transferee')
        ->and((string) $fresh->gwa)->toBe('1.75')
        ->and($fresh->version)->toBe(2);

    expect($fresh->gradeValues->firstWhere('field_code', 'GWA')?->value)->not->toBeNull();
    expect((string) $fresh->gradeValues->firstWhere('field_code', 'ENG')->value)->toBe('88.00');

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'admission.attempt_corrected',
    ]);
});

test('registrar office correct from needs_correction returns pending with audit', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('detail.officefix@example.test')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->post(route('admissions.request-correction', $attempt), [
            'expected_version' => 1,
            'reason' => 'Bring Form 138 to office',
        ])
        ->assertRedirect();

    expect($attempt->fresh()->status)->toBe(AdmissionAttempt::STATUS_NEEDS_CORRECTION);

    $this->actingAs($registrar)
        ->post(route('admissions.correct', $attempt->fresh()), [
            'expected_version' => 2,
            'contact_phone' => '09171234567',
            'last_school_enrolled' => 'Office Visited SHS',
            'strand' => 'STEM',
            'grades' => [
                'GWA' => '1.50',
            ],
            'correction_notes' => 'Applicant visited office; Form 138 verified',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Office fix recorded — application returned to pending review.');

    $fresh = $attempt->fresh(['gradeValues']);
    expect($fresh->status)->toBe(AdmissionAttempt::STATUS_PENDING)
        ->and($fresh->contact_phone)->toBe('09171234567')
        ->and($fresh->last_school_enrolled)->toBe('Office Visited SHS')
        ->and($fresh->strand)->toBe('STEM')
        ->and((string) $fresh->gwa)->toBe('1.50')
        ->and($fresh->correction_notes)->toBe('Applicant visited office; Form 138 verified')
        ->and($fresh->version)->toBe(3);

    $audit = \App\Models\Foundation\AuditEvent::query()
        ->where('event_type', 'admission.attempt_corrected')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and(data_get($audit->payload, 'returned_to_pending'))->toBeTrue()
        ->and(data_get($audit->payload, 'prior_status'))->toBe(AdmissionAttempt::STATUS_NEEDS_CORRECTION)
        ->and(data_get($audit->payload, 'actor_kind'))->toBe('registrar');
});

test('show page includes programs and grade_fields for update dialog', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('detail.catalog@example.test')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('admissions.show', $attempt))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admission/Show')
            ->has('programs')
            ->has('grade_fields')
            ->where('attempt.last_school_enrolled', fn ($v) => is_string($v) || $v === null)
            ->where('attempt.strand', fn ($v) => is_string($v) || $v === null));
});

test('stale version on request correction returns validation error', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('detail.stale@example.test')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->post(route('admissions.request-correction', $attempt), [
            'expected_version' => 99,
            'reason' => 'Should fail',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('version');

    expect($attempt->fresh()->status)->toBe(AdmissionAttempt::STATUS_PENDING);
});

test('authorized amendment while accepted fixes identity and stays exam-eligible', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('detail.amend@example.test', 'Wrongname')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->post(route('admissions.accept', $attempt), ['expected_version' => 1])
        ->assertRedirect();

    $accepted = $attempt->fresh(['identity']);
    expect($accepted->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED)
        ->and($accepted->isExamEligible())->toBeTrue();

    $this->actingAs($registrar)
        ->post(route('admissions.authorize-amendment', $accepted), [
            'expected_version' => 2,
            'reason' => 'Proctor reported wrong first name on roster',
            'identity_first_name' => 'Corrected',
            'identity_last_name' => $accepted->identity->last_name,
            'identity_middle_name' => $accepted->identity->middle_name,
            'identity_suffix' => $accepted->identity->suffix,
            'identity_birthdate' => $accepted->identity->birthdate?->format('Y-m-d'),
            'identity_sex' => $accepted->identity->sex,
            'identity_email' => $accepted->identity->email,
            'contact_phone' => '09170009999',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $fresh = $attempt->fresh(['identity']);
    expect($fresh->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED)
        ->and($fresh->isExamEligible())->toBeTrue()
        ->and($fresh->identity->first_name)->toBe('Corrected')
        ->and($fresh->contact_phone)->toBe('09170009999')
        ->and($fresh->reference_code)->toBe($accepted->reference_code);

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'admission.accepted_amendment_authorized',
    ]);
});

test('reconsider acceptance returns pending and loses exam eligibility', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('detail.reconsider@example.test')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->post(route('admissions.accept', $attempt), ['expected_version' => 1])
        ->assertRedirect();

    expect($attempt->fresh()->isExamEligible())->toBeTrue();

    $this->actingAs($registrar)
        ->post(route('admissions.reconsider', $attempt->fresh()), [
            'expected_version' => 2,
            'reason' => 'Accepted in error — incomplete documents',
        ])
        ->assertRedirect();

    $fresh = $attempt->fresh();
    expect($fresh->status)->toBe(AdmissionAttempt::STATUS_PENDING)
        ->and($fresh->isExamEligible())->toBeFalse()
        ->and($fresh->accepted_at)->toBeNull();

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'admission.attempt_reconsidered',
    ]);
});

test('quiet correct is rejected after accept', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('detail.quiet.block@example.test')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->post(route('admissions.accept', $attempt), ['expected_version' => 1]);

    $this->actingAs($registrar)
        ->post(route('admissions.correct', $attempt->fresh()), [
            'expected_version' => 2,
            'contact_phone' => '09999999999',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('version');

    expect($attempt->fresh()->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED);
});
