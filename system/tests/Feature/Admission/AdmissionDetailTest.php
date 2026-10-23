<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\InitializeAttemptRequirementSet;
use App\Admission\Actions\VerifyAdmissionAttemptDocument;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptRequirementSet;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Admission\ApplicantAccount;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeRequirement;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

function admissionAuthenticatedStaff(User $user): TestCase
{
    $user = User::query()->with('authenticationState')->findOrFail($user->id);

    return test()->actingAs($user)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $user->authenticationState->session_version,
        'auth.staff_inactivity_generation' => 'admission-detail-test',
    ]);
}

test('admissions show denies users without registrar view capability', function (): void {

    seedE2Foundation();
    $attempt = submitSyntheticApplicant('detail.deny@example.test')['attempt'];
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    admissionAuthenticatedStaff($guidance)
        ->get(route('admissions.show', $attempt))
        ->assertForbidden();
});

test('registrar can view attempt detail payload', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('detail.view@example.test', 'Detail')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    admissionAuthenticatedStaff($registrar)
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
            ->has('document_checklist')
            ->where('document_checklist.status', 'current')
            ->where('document_checklist.source', 'snapshot')
            ->where('document_checklist.documents_view', true)
            ->where('document_checklist.documents_initialize', false)
            ->where('document_checklist.documents_verify', true)
            ->has('document_checklist.items')
        );
});

test('view-only Registrar can read the auto-provisioned checklist without mutating it', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('detail.read-only-checklist@example.test')['attempt'];
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW);
    StaffProfile::query()->create([
        'user_id' => $viewer->id,
        'institution_id' => $attempt->institution_id,
        'office' => 'registrar',
        'active_cycle_code' => $attempt->academicCycle->code,
        'program_codes' => [],
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);

    StaffAuthenticationState::query()->create([
        'user_id' => $viewer->id,
        'session_version' => 1,
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'two_factor_generation' => 'view-only-test',
    ]);
    admissionAuthenticatedStaff($viewer)
        ->get(route('admissions.show', $attempt))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('document_checklist.documents_view', true)
            ->where('document_checklist.documents_initialize', false)
            ->where('document_checklist.documents_verify', false)
            ->where('document_checklist.documents_request', false)
        );

    expect(AdmissionAttemptRequirementSet::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(1);

    admissionAuthenticatedStaff($viewer)
        ->postJson(route('registrar.clearance.requirements.initialize', $attempt), [
            'expected_version' => $attempt->version,
            'idempotency_key' => 'view-only-checklist-init',
        ])
        ->assertForbidden();

    expect(AdmissionAttemptRequirementSet::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(1);
});

test('request correction moves pending to needs_correction with reason', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('detail.req@example.test')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    admissionAuthenticatedStaff($registrar)
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

    admissionAuthenticatedStaff($registrar)
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
            'correction_notes' => 'Fixed email, school, and strand',
        ])
        ->assertRedirect();

    $fresh = $attempt->fresh(['gradeValues']);
    expect($fresh->status)->toBe(AdmissionAttempt::STATUS_PENDING)
        ->and($fresh->contact_email)->toBe('fixed.email@example.test')
        ->and($fresh->program_preference_1_code)->toBe('BSPSY')
        ->and($fresh->last_school_enrolled)->toBe('Corrected Senior High')
        ->and($fresh->strand)->toBe('HUMSS')
        ->and($fresh->applicant_type)->toBe('transferee')
        ->and($fresh->gwa)->toBeNull()
        ->and($fresh->version)->toBe(2);

    expect($fresh->gradeValues)->toBeEmpty();

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'admission.attempt_corrected',
    ]);
});

test('registrar office correct from needs_correction returns pending with audit', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('detail.officefix@example.test')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    admissionAuthenticatedStaff($registrar)
        ->post(route('admissions.request-correction', $attempt), [
            'expected_version' => 1,
            'reason' => 'Bring Form 138 to office',
        ])
        ->assertRedirect();

    expect($attempt->fresh()->status)->toBe(AdmissionAttempt::STATUS_NEEDS_CORRECTION);

    admissionAuthenticatedStaff($registrar)
        ->post(route('admissions.correct', $attempt->fresh()), [
            'expected_version' => 2,
            'contact_phone' => '09171234567',
            'last_school_enrolled' => 'Office Visited SHS',
            'strand' => 'STEM',
            'correction_notes' => 'Applicant visited office; Form 138 verified',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Office fix recorded — application returned to pending review.');

    $fresh = $attempt->fresh(['gradeValues']);
    expect($fresh->status)->toBe(AdmissionAttempt::STATUS_PENDING)
        ->and($fresh->contact_phone)->toBe('09171234567')
        ->and($fresh->last_school_enrolled)->toBe('Office Visited SHS')
        ->and($fresh->strand)->toBe('STEM')
        ->and($fresh->gwa)->toBeNull()
        ->and($fresh->correction_notes)->toBe('Applicant visited office; Form 138 verified')
        ->and($fresh->version)->toBe(3);

    $audit = AuditEvent::query()
        ->where('event_type', 'admission.attempt_corrected')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and(data_get($audit->payload, 'returned_to_pending'))->toBeTrue()
        ->and(data_get($audit->payload, 'prior_status'))->toBe(AdmissionAttempt::STATUS_NEEDS_CORRECTION)
        ->and(data_get($audit->payload, 'actor_kind'))->toBe('registrar');
});

test('show page includes programs and no catalog grade fields', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('detail.catalog@example.test')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    admissionAuthenticatedStaff($registrar)
        ->get(route('admissions.show', $attempt))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admission/Show')
            ->has('programs')
            ->where('grade_fields', [])
            ->where('attempt.last_school_enrolled', fn ($v) => is_string($v) || $v === null)
            ->where('attempt.strand', fn ($v) => is_string($v) || $v === null));
});

test('stale version on request correction returns validation error', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('detail.stale@example.test')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    admissionAuthenticatedStaff($registrar)
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

    admissionAuthenticatedStaff($registrar)
        ->post(route('admissions.accept', $attempt), ['expected_version' => 1])
        ->assertRedirect();

    $accepted = $attempt->fresh(['identity']);
    expect($accepted->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED)
        ->and($accepted->isExamEligible())->toBeTrue();

    admissionAuthenticatedStaff($registrar)
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

    admissionAuthenticatedStaff($registrar)
        ->post(route('admissions.accept', $attempt), ['expected_version' => 1])
        ->assertRedirect();

    expect($attempt->fresh()->isExamEligible())->toBeTrue();

    admissionAuthenticatedStaff($registrar)
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

    admissionAuthenticatedStaff($registrar)
        ->post(route('admissions.accept', $attempt), ['expected_version' => 1]);

    admissionAuthenticatedStaff($registrar)
        ->post(route('admissions.correct', $attempt->fresh()), [
            'expected_version' => 2,
            'contact_phone' => '09999999999',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('version');

    expect($attempt->fresh()->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED);
});

test('admissions index includes initialized document counts in its list DTO', function (): void {
    seedE2Foundation();
    $institution = Institution::current();
    IntakeRequirement::query()->create([
        'institution_id' => $institution->id,
        'code' => 'test_optional_checklist_document',
        'label' => 'Optional supporting document',
        'applicant_track' => IntakeRequirement::TRACK_FRESHMAN,
        'item_kind' => IntakeRequirement::KIND_DOCUMENT,
        'is_mandatory' => false,
        'is_active' => true,
        'sort_order' => 1000,
    ]);
    $attempt = submitSyntheticApplicant('detail.list-counts@example.test')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $set = app(InitializeAttemptRequirementSet::class)->handle(
        $attempt,
        $registrar,
        (int) $attempt->version,
        'list-counts-init',
    );
    $items = $set->items()->orderBy('sort_order')->get();
    $mandatory = $items->where('is_mandatory', true)->values();
    $optional = $items->where('is_mandatory', false)->values();
    expect($mandatory->count())->toBeGreaterThanOrEqual(3)
        ->and($optional)->not->toBeEmpty();

    foreach ([
        [$mandatory[0], 'verified'],
        [$mandatory[1], 'missing'],
        [$mandatory[2], 'deficient'],
    ] as [$item, $status]) {
        $attempt->refresh();
        app(VerifyAdmissionAttemptDocument::class)->handle(
            attempt: $attempt,
            requirementSetId: (int) $set->id,
            requirementItemId: (int) $item->id,
            expectedLatestVerificationId: null,
            status: $status,
            actor: $registrar,
            expectedVersion: (int) $attempt->version,
            applicantInstruction: $status === 'deficient' ? 'Please upload a clearer copy of this document.' : null,
            internalRemarks: null,
            idempotencyKey: 'list-count-'.$item->id,
        );
    }

    admissionAuthenticatedStaff($registrar)
        ->get(route('admissions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('attempts.data', fn ($rows) => collect($rows)->contains(
                fn ($row) => $row['id'] === $attempt->id
                    && $row['document_summary'] === [
                        'status' => 'current',
                        'verified_count' => 1,
                        'missing_count' => 1,
                        'deficient_count' => 1,
                        'not_checked_count' => $items->count() - 3,
                        'mandatory_pending_count' => $mandatory->count() - 1,
                    ]
            ))
        );
});

test('registrar can correct identity name and birthdate while pending and syncs linked user name', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant('detail.namefix@example.test', 'WrongLast');
    $attempt = $result['attempt'];

    app(AuthenticateApplicantWithAccessKey::class)->handle($result['access_token'], '2001-01-01');

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    admissionAuthenticatedStaff($registrar)
        ->post(route('admissions.correct', $attempt), [
            'expected_version' => 1,
            'identity_first_name' => 'FixedFirst',
            'identity_last_name' => 'FixedLast',
            'identity_birthdate' => '2002-02-02',
            'identity_sex' => 'female',
        ])
        ->assertRedirect(route('admissions.show', $attempt->id))
        ->assertSessionHas('status');

    $fresh = $attempt->fresh(['identity']);
    expect($fresh->identity->first_name)->toBe('FixedFirst')
        ->and($fresh->identity->last_name)->toBe('FixedLast')
        ->and($fresh->identity->birthdate->format('Y-m-d'))->toBe('2002-02-02')
        ->and($fresh->identity->sex)->toBe('female');

    expect(ApplicantAccount::where('applicant_identity_id', $attempt->applicant_identity_id)->first()->user->name)
        ->toBe('FixedFirst FixedLast');

    $audit = AuditEvent::where('event_type', 'admission.attempt_corrected')->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->payload['identity_fields'])->toContain('first_name', 'last_name', 'birthdate', 'sex')
        ->and($audit->payload['before_identity'])->not->toBeEmpty()
        ->and($audit->payload['after_identity'])->not->toBeEmpty();
});

test('registrar in-place correct rejects duplicate normalized identity collision', function (): void {
    seedE2Foundation();
    $applicantA = submitSyntheticApplicant('collision.a@example.test', 'Person')['attempt'];
    $applicantB = submitSyntheticApplicant('collision.b@example.test', 'CollisionB')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $response = admissionAuthenticatedStaff($registrar)
        ->post(route('admissions.correct', $applicantB), [
            'expected_version' => 1,
            'identity_first_name' => 'Test',
            'identity_last_name' => 'Person',
            'identity_birthdate' => '2001-01-01',
            'identity_sex' => 'male',
        ]);

    $response->assertSessionHasErrors(['identity_first_name', 'version']);

    expect($applicantB->fresh()->identity->first_name)->toBe('Test')
        ->and($applicantB->fresh()->identity->last_name)->toBe('CollisionB');
});

test('confirmed admission clearance blocks authorized amendment until revoked', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('detail.clearance.fence@example.test')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    admissionAuthenticatedStaff($registrar)
        ->post(route('admissions.accept', $attempt), ['expected_version' => 1])
        ->assertRedirect();

    $program = Program::query()->where('code', $attempt->preferred_program_code ?? 'BSIT')->first()
        ?? Program::query()->firstOrFail();

    AdmissionClearanceReceipt::create([
        'institution_id' => $attempt->institution_id,
        'admission_attempt_id' => $attempt->id,
        'program_id' => $program->id,
        'receipt_number' => 'CLR-TEST-1',
        'status' => AdmissionClearanceReceipt::STATUS_CONFIRMED,
        'evidence_vector' => ['ready' => true],
        'confirmed_by' => $registrar->id,
        'confirmed_by_user_id' => $registrar->id,
        'confirmed_at' => now(),
        'idempotency_key' => 'clr-test-fence-1',
    ]);

    $amendResponse = admissionAuthenticatedStaff($registrar)
        ->post(route('admissions.authorize-amendment', $attempt->fresh()), [
            'expected_version' => 2,
            'reason' => 'Correct demographic info',
            'contact_phone' => '09999999999',
        ]);
    $amendResponse->assertRedirect();
    $amendResponse->assertSessionHasErrors('version');
    expect(session('errors')->first('version'))->toContain('Admission clearance is confirmed');

    $reconsiderResponse = admissionAuthenticatedStaff($registrar)
        ->post(route('admissions.reconsider', $attempt->fresh()), [
            'expected_version' => 2,
            'reason' => 'Clearance error',
        ]);
    $reconsiderResponse->assertRedirect();
    $reconsiderResponse->assertSessionHasErrors('version');
    expect(session('errors')->first('version'))->toContain('Admission clearance is confirmed');
});

test('reissue access key surfaces reissued_access_key and credential_handoff on show page once', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('detail.reissue.handoff@example.test', 'ReissueApplicant')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    admissionAuthenticatedStaff($registrar)
        ->post(route('admissions.reissue-access-key', $attempt), [
            'expected_version' => 1,
            'reason' => 'Applicant lost credential slip',
        ])
        ->assertRedirect(route('admissions.show', $attempt));

    admissionAuthenticatedStaff($registrar)
        ->get(route('admissions.show', $attempt))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admission/Show')
            ->where('reissued_access_key', fn ($v) => is_string($v) && $v !== '')
            ->where('credential_handoff.origin', 'reissued')
            ->where('credential_handoff.reference_code', $attempt->reference_code)
            ->where('credential_handoff.voucher_token', fn ($v) => is_string($v) && strlen($v) === 32)
            ->where('credential_handoff.qr_url', fn ($v) => is_string($v) && str_contains($v, '#v='))
        );

    admissionAuthenticatedStaff($registrar)
        ->get(route('admissions.show', $attempt))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admission/Show')
            ->where('reissued_access_key', null)
            ->where('credential_handoff', null)
        );
});
