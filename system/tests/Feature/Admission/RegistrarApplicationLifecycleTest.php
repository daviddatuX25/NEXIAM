<?php

declare(strict_types=1);

use App\Auth\Actions\EstablishStaffSessionLease;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptDocumentVerification;
use App\Models\Admission\AdmissionAttemptRequirementSet;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\IntakeRequirement;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function lifecycleAuthenticatedStaff(User $user): TestCase
{
    $state = StaffAuthenticationState::query()->firstOrCreate(
        ['user_id' => $user->id],
        [
            'session_version' => 1,
            'mfa_required' => false,
        ]
    );

    $state->forceFill([
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'must_change_password' => false,
        'mfa_required' => false,
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
        'two_factor_generation' => (string) ($state->two_factor_generation ?: Str::uuid()),
        'two_factor_last_accepted_step' => null,
    ])->save();

    if (! session()->isStarted()) {
        session()->start();
    }
    $sessionId = (string) session()->getId();
    $lease = app(EstablishStaffSessionLease::class)->handle($user, $sessionId, now());

    session()->put([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
        'auth.staff_inactivity_generation' => $lease->inactivity_generation,
    ]);

    return test()->actingAs($user);
}

test('registrar can view admissions create page', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    lifecycleAuthenticatedStaff($registrar)
        ->get(route('admissions.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admission/Create')
            ->where('page_state', 'ready')
            ->has('programs')
            ->has('requirements')
            ->where('can_verify_documents', true)
            ->where('grade_fields', [])
            ->has('cycle')
        );
});

test('non-registrar cannot view admissions create page', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    lifecycleAuthenticatedStaff($guidance)
        ->get(route('admissions.create'))
        ->assertForbidden();
});

test('registrar can punch in a walk-in application', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $data = [
        'first_name' => 'Juana',
        'last_name' => 'Dela Cruz',
        'middle_name' => 'Luna',
        'suffix' => null,
        'birthdate' => '2005-08-20',
        'sex' => 'female',
        'applicant_type' => 'new',
        'contact_email' => 'juana.delacruz.walkin@example.test',
        'contact_phone' => '09181234567',
        'last_school_enrolled' => 'Ilocos Sur National High School',
        'strand' => 'STEM',
        'address_line' => 'Barangay 2',
        'city' => 'Vigan City',
        'province' => 'Ilocos Sur',
        'zip_code' => '2700',
        'program_preference_1_code' => 'BSIT',
        'status' => 'pending',
        'correction_notes' => 'Assisted walk-in at admissions counter.',
    ];

    $response = lifecycleAuthenticatedStaff($registrar)
        ->post(route('admissions.store'), $data);

    $response->assertRedirect();
    $attempt = AdmissionAttempt::query()->where('contact_email', 'juana.delacruz.walkin@example.test')->firstOrFail();
    $response->assertRedirect(route('admissions.show', $attempt->id));
    $response->assertSessionHas(
        'success',
        "Application created successfully with reference {$attempt->reference_code}."
    );

    expect($attempt->status)->toBe('pending')
        ->and($attempt->preferred_program_code)->toBe('BSIT')
        ->and(ApplicantAccessChallenge::query()->where('admission_attempt_id', $attempt->id)->exists())->toBeTrue();

    lifecycleAuthenticatedStaff($registrar)
        ->get(route('admissions.show', $attempt->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admission/Show')
            ->where('reissued_access_key', fn ($v) => is_string($v) && $v !== '')
            ->where('credential_handoff.origin', 'created')
            ->where('credential_handoff.reference_code', $attempt->reference_code)
            ->where('credential_handoff.voucher_token', fn ($v) => is_string($v) && strlen($v) === 32)
            ->where('credential_handoff.qr_url', fn ($v) => is_string($v) && str_contains($v, '#v='))
        );

    lifecycleAuthenticatedStaff($registrar)
        ->get(route('admissions.show', $attempt->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admission/Show')
            ->where('reissued_access_key', null)
            ->where('credential_handoff', null)
        );
});

test('registrar immediate walk-in acceptance with complete_documents verifies all track requirements', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $response = lifecycleAuthenticatedStaff($registrar)
        ->post(route('admissions.store'), [
            'first_name' => 'Marco',
            'last_name' => 'Reyes',
            'birthdate' => '2005-04-12',
            'sex' => 'male',
            'applicant_type' => 'new',
            'contact_email' => 'marco.reyes.complete@example.test',
            'program_preference_1_code' => 'BSIT',
            'status' => 'accepted',
            'complete_documents' => true,
        ]);

    $response->assertRedirect();
    $attempt = AdmissionAttempt::query()->where('contact_email', 'marco.reyes.complete@example.test')->firstOrFail();
    expect($attempt->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED);

    $set = AdmissionAttemptRequirementSet::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('is_current', true)
        ->with('items')
        ->firstOrFail();

    expect($set->items)->not->toBeEmpty();

    $verifiedCount = AdmissionAttemptDocumentVerification::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('status', AdmissionAttemptDocumentVerification::STATUS_VERIFIED)
        ->count();

    expect($verifiedCount)->toBe($set->items->count());
});

test('registrar immediate walk-in acceptance with complete_documents off verifies only selected requirements', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $freshmanRequirements = IntakeRequirement::query()
        ->active()
        ->forTrack(IntakeRequirement::TRACK_FRESHMAN)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get();

    $selectedRequirementIds = $freshmanRequirements->take(2)->pluck('id')->map(fn ($id) => (int) $id)->all();

    $response = lifecycleAuthenticatedStaff($registrar)
        ->post(route('admissions.store'), [
            'first_name' => 'Elena',
            'last_name' => 'Santos',
            'birthdate' => '2005-07-19',
            'sex' => 'female',
            'applicant_type' => 'new',
            'contact_email' => 'elena.santos.partial@example.test',
            'program_preference_1_code' => 'BSIT',
            'status' => 'accepted',
            'complete_documents' => false,
            'verified_requirement_ids' => $selectedRequirementIds,
        ]);

    $response->assertRedirect();
    $attempt = AdmissionAttempt::query()->where('contact_email', 'elena.santos.partial@example.test')->firstOrFail();
    expect($attempt->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED);

    $set = AdmissionAttemptRequirementSet::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('is_current', true)
        ->with('items.latestVerification')
        ->firstOrFail();

    $verifiedIntakeIds = $set->items
        ->filter(fn ($item) => $item->latestVerification?->status === AdmissionAttemptDocumentVerification::STATUS_VERIFIED)
        ->pluck('intake_requirement_id')
        ->map(fn ($id) => (int) $id)
        ->values()
        ->all();

    expect($verifiedIntakeIds)->toEqualCanonicalizing($selectedRequirementIds)
        ->and($set->items->count())->toBeGreaterThan(count($selectedRequirementIds));
});

test('registrar can view admissions edit page', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('lifecycle.edit@example.test', 'Lifecycle')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    lifecycleAuthenticatedStaff($registrar)
        ->get(route('admissions.edit', $attempt->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admission/Edit')
            ->where('page_state', 'ready')
            ->where('attempt.reference_code', $attempt->reference_code)
            ->has('programs')
            ->where('grade_fields', [])
            ->has('expected_version')
        );
});

test('admissions edit page enforces capability and clearance fences', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('lifecycle.fence@example.test', 'FenceApplicant')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

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
        'two_factor_generation' => 'view-only-lifecycle-test',
    ]);

    lifecycleAuthenticatedStaff($viewer)
        ->get(route('admissions.edit', $attempt->id))
        ->assertForbidden();

    $dismissedAttempt = submitSyntheticApplicant('lifecycle.dismissed@example.test', 'DismissedApplicant')['attempt'];
    $dismissedAttempt->update(['status' => AdmissionAttempt::STATUS_DISMISSED]);

    lifecycleAuthenticatedStaff($registrar)
        ->get(route('admissions.edit', $dismissedAttempt->id))
        ->assertRedirect(route('admissions.show', $dismissedAttempt->id))
        ->assertSessionHasErrors('version');

    $acceptedAttempt = submitSyntheticApplicant('lifecycle.accepted.clearance@example.test', 'ClearanceApplicant')['attempt'];
    lifecycleAuthenticatedStaff($registrar)
        ->post(route('admissions.accept', $acceptedAttempt), ['expected_version' => 1])
        ->assertRedirect();

    $program = Program::query()->where('code', $acceptedAttempt->preferred_program_code ?? 'BSIT')->first()
        ?? Program::query()->firstOrFail();

    AdmissionClearanceReceipt::create([
        'institution_id' => $acceptedAttempt->institution_id,
        'admission_attempt_id' => $acceptedAttempt->id,
        'program_id' => $program->id,
        'receipt_number' => 'CLR-TEST-EDIT-FENCE',
        'status' => AdmissionClearanceReceipt::STATUS_CONFIRMED,
        'evidence_vector' => ['ready' => true],
        'confirmed_by' => $registrar->id,
        'confirmed_by_user_id' => $registrar->id,
        'confirmed_at' => now(),
        'idempotency_key' => 'clr-test-edit-fence-key',
    ]);

    lifecycleAuthenticatedStaff($registrar)
        ->get(route('admissions.edit', $acceptedAttempt->id))
        ->assertRedirect(route('admissions.show', $acceptedAttempt->id))
        ->assertSessionHasErrors('version');
});
