<?php

use App\Admission\Actions\InitializeAttemptRequirementSet;
use App\Admission\Actions\VerifyAdmissionAttemptDocument;
use App\Admission\Queries\AttemptRequirementChecklistQuery;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptDocumentVerification;
use App\Models\Admission\AdmissionAttemptRequirementSet;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\IntakeRequirement;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function getDocumentTestFixture(string $email): array
{
    seedE2Foundation();
    $lastName = 'Ledger '.substr(hash('sha256', $email), 0, 12);
    $result = submitSyntheticApplicant($email, $lastName);
    $attempt = $result['attempt'];
    $attempt->status = AdmissionAttempt::STATUS_ACCEPTED;
    $attempt->save();
    $registrar = User::query()->where('email', 'registrar.lead@nexiam.test')->first()
        ?? User::query()->where('email', 'like', '%registrar%')->first()
        ?? User::query()->first();

    return [$attempt, $registrar];
}
function initializeLedgerRequirements(AdmissionAttempt $attempt, User $registrar, string $key = 'ledger-init'): AdmissionAttemptRequirementSet
{
    $attempt->refresh();

    return app(InitializeAttemptRequirementSet::class)->handle(
        $attempt,
        $registrar,
        (int) $attempt->version,
        $key,
    );
}

function verifyLedgerItem(
    AdmissionAttempt $attempt,
    AdmissionAttemptRequirementSet $set,
    int $itemId,
    string $status,
    User $registrar,
    ?string $applicantInstruction = null,
    ?string $internalRemarks = null,
    ?string $idempotencyKey = null,
): AdmissionAttemptDocumentVerification {
    $attempt->refresh();
    $latestId = AdmissionAttemptDocumentVerification::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('requirement_item_id', $itemId)
        ->latest('id')
        ->value('id');

    return app(VerifyAdmissionAttemptDocument::class)->handle(
        attempt: $attempt,
        requirementSetId: (int) $set->id,
        requirementItemId: $itemId,
        expectedLatestVerificationId: $latestId === null ? null : (int) $latestId,
        status: $status,
        actor: $registrar,
        expectedVersion: (int) $attempt->version,
        applicantInstruction: $applicantInstruction,
        internalRemarks: $internalRemarks,
        idempotencyKey: $idempotencyKey ?? 'ledger-verify-'.$attempt->id.'-'.$itemId.'-'.$status.'-'.now()->timestamp,
    );
}

test('catalog projections can be verified and atomically provision a checklist', function (): void {
    [$attempt, $registrar] = getDocumentTestFixture('ledger-catalog-projection@example.test');
    AdmissionAttemptRequirementSet::query()->where('admission_attempt_id', $attempt->id)->delete();

    $requirement = IntakeRequirement::query()
        ->where('institution_id', $attempt->institution_id)
        ->active()
        ->forTrack('freshman')
        ->orderBy('sort_order')
        ->orderBy('id')
        ->firstOrFail();
    $projection = app(AttemptRequirementChecklistQuery::class)->get($attempt->fresh(), $registrar);

    expect($projection['status'])->toBe('current')
        ->and($projection['source'])->toBe('catalog')
        ->and($projection['items'][0]['requirement_item_id'])->toBeNull()
        ->and($projection['items'][0]['intake_requirement_id'])->toBe($requirement->id)
        ->and($projection['all_mandatory_verified'])->toBeFalse()
        ->and($projection['documents_verify'])->toBeTrue();

    app(VerifyAdmissionAttemptDocument::class)->handle(
        attempt: $attempt->fresh(),
        requirementSetId: null,
        requirementItemId: null,
        expectedLatestVerificationId: null,
        status: AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
        actor: $registrar,
        expectedVersion: (int) $attempt->version,
        applicantInstruction: null,
        internalRemarks: null,
        idempotencyKey: 'ledger-catalog-projection-verify',
        intakeRequirementId: (int) $requirement->id,
    );

    expect(AdmissionAttemptRequirementSet::query()->where('admission_attempt_id', $attempt->id)->where('is_current', true)->count())->toBe(1)
        ->and((int) $attempt->fresh()->version)->toBe(2)
        ->and(AdmissionAttemptDocumentVerification::query()->where('admission_attempt_id', $attempt->id)->where('status', AdmissionAttemptDocumentVerification::STATUS_VERIFIED)->exists())->toBeTrue();
});

test('initializes requirement sets for an attempt from IntakeRequirement catalog', function (): void {
    [$attempt, $registrar] = getDocumentTestFixture('ledger-init@example.test');

    // Ensure catalog has requirements for the institution
    $institutionId = $attempt->institution_id;
    $initialCount = IntakeRequirement::query()->where('institution_id', $institutionId)->count();
    expect($initialCount)->toBeGreaterThan(0);

    $initialVersion = (int) $attempt->version;
    $key = 'ledger-init-replay-'.$attempt->id;
    $action = app(InitializeAttemptRequirementSet::class);
    $set = $action->handle($attempt, $registrar, $initialVersion, $key);

    expect($set)->toBeInstanceOf(AdmissionAttemptRequirementSet::class)
        ->and($set->admission_attempt_id)->toBe($attempt->id)
        ->and($set->is_current)->toBeTrue()
        ->and($set->items()->count())->toBeGreaterThan(0);

    $replayedSet = $action->handle($attempt->fresh(), $registrar, $initialVersion, $key);
    expect($replayedSet->id)->toBe($set->id);
});

test('verifying individual items marks them as verified, deficient, or missing', function (): void {
    [$attempt, $registrar] = getDocumentTestFixture('ledger-verify-items@example.test');

    $set = initializeLedgerRequirements($attempt, $registrar);
    $items = $set->items()->orderBy('sort_order')->get();
    expect($items->count())->toBeGreaterThanOrEqual(3);

    $v1 = verifyLedgerItem(
        $attempt,
        $set,
        (int) $items[0]->id,
        AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
        $registrar,
        internalRemarks: 'Original diploma presented',
        idempotencyKey: 'item-verify-1',
    );
    expect($v1->status)->toBe(AdmissionAttemptDocumentVerification::STATUS_VERIFIED)
        ->and($v1->requirement_item_id)->toBe($items[0]->id)
        ->and($v1->verified_by_user_id)->toBe($registrar->id);

    $v2 = verifyLedgerItem(
        $attempt,
        $set,
        (int) $items[1]->id,
        AdmissionAttemptDocumentVerification::STATUS_DEFICIENT,
        $registrar,
        applicantInstruction: 'Please bring the original document for inspection.',
        internalRemarks: 'Photocopy only',
        idempotencyKey: 'item-verify-2',
    );
    expect($v2->status)->toBe(AdmissionAttemptDocumentVerification::STATUS_DEFICIENT)
        ->and($v2->applicant_instruction)->toBe('Please bring the original document for inspection.')
        ->and($v2->internal_remarks)->toBe('Photocopy only');

    $v3 = verifyLedgerItem(
        $attempt,
        $set,
        (int) $items[2]->id,
        AdmissionAttemptDocumentVerification::STATUS_MISSING,
        $registrar,
        internalRemarks: 'Not submitted at counter',
        idempotencyKey: 'item-verify-3',
    );
    expect($v3->status)->toBe(AdmissionAttemptDocumentVerification::STATUS_MISSING);

    expect(NotificationDelivery::query()->where('event_type', 'admission.document_correction_requested.v1')->count())->toBe(0);
    $query = app(AttemptRequirementChecklistQuery::class);
    $checklist = $query->forAttempt($attempt);

    expect($checklist['items'])->toBeArray()
        ->and(count($checklist['items']))->toBe($items->count());

    $reportedItem1 = collect($checklist['items'])->firstWhere('id', $items[0]->id);
    expect($reportedItem1['latest_status'])->toBe(AdmissionAttemptDocumentVerification::STATUS_VERIFIED);

    $reportedItem2 = collect($checklist['items'])->firstWhere('id', $items[1]->id);
    expect($reportedItem2['latest_status'])->toBe(AdmissionAttemptDocumentVerification::STATUS_DEFICIENT);

    $reportedItem3 = collect($checklist['items'])->firstWhere('id', $items[2]->id);
    expect($reportedItem3['latest_status'])->toBe(AdmissionAttemptDocumentVerification::STATUS_MISSING);
});

test('marking a deficient document complete clears its current instruction and keeps deficiency history', function (): void {
    [$attempt, $registrar] = getDocumentTestFixture('ledger-clear-instruction@example.test');
    $set = initializeLedgerRequirements($attempt, $registrar);
    $item = $set->items()->firstOrFail();

    $deficiency = verifyLedgerItem(
        $attempt,
        $set,
        (int) $item->id,
        AdmissionAttemptDocumentVerification::STATUS_DEFICIENT,
        $registrar,
        applicantInstruction: 'Please bring the original document for inspection.',
        idempotencyKey: 'ledger-clear-instruction-deficient',
    );
    $verified = verifyLedgerItem(
        $attempt,
        $set,
        (int) $item->id,
        AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
        $registrar,
        applicantInstruction: 'This prior instruction must be cleared.',
        idempotencyKey: 'ledger-clear-instruction-verified',
    );
    $projectedItem = collect(app(AttemptRequirementChecklistQuery::class)->forAttempt($attempt)['items'])
        ->firstWhere('id', $item->id);

    expect($verified->applicant_instruction)->toBeNull()
        ->and($projectedItem)->toHaveKey('applicant_instruction')
        ->and($projectedItem['applicant_instruction'])->toBeNull()
        ->and($projectedItem['latest_status'])->toBe(AdmissionAttemptDocumentVerification::STATUS_VERIFIED)
        ->and($projectedItem['history'])->toHaveCount(2)
        ->and($projectedItem['history'][0]['id'])->toBe($deficiency->id)
        ->and($projectedItem['history'][0]['status'])->toBe(AdmissionAttemptDocumentVerification::STATUS_DEFICIENT)
        ->and($projectedItem['history'][0]['applicant_instruction'])->toBe('Please bring the original document for inspection.')
        ->and($projectedItem['history'][1]['applicant_instruction'])->toBeNull();
});

test('unverified mandatory items report all_mandatory_verified = false', function (): void {
    [$attempt, $registrar] = getDocumentTestFixture('ledger-unverified@example.test');

    $set = initializeLedgerRequirements($attempt, $registrar);
    $mandatoryItems = $set->items()->where('is_mandatory', true)->get();
    expect($mandatoryItems->count())->toBeGreaterThan(0);

    // With none verified
    $query = app(AttemptRequirementChecklistQuery::class);
    $checklist = $query->forAttempt($attempt);

    expect($checklist['all_mandatory_verified'])->toBeFalse()
        ->and($checklist['mandatory_pending_count'])->toBe($mandatoryItems->count())
        ->and($query->allMandatoryVerified($attempt))->toBeFalse();

    // Verify only the first mandatory item if there are more than 1
    if ($mandatoryItems->count() > 1) {
        verifyLedgerItem(
            $attempt,
            $set,
            (int) $mandatoryItems[0]->id,
            AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
            $registrar,
        );

        $checklistPartial = $query->forAttempt($attempt);
        expect($checklistPartial['all_mandatory_verified'])->toBeFalse()
            ->and($checklistPartial['mandatory_pending_count'])->toBe($mandatoryItems->count() - 1);
    }
});

test('verifying all mandatory items reports all_mandatory_verified = true', function (): void {
    [$attempt, $registrar] = getDocumentTestFixture('ledger-all-verified@example.test');

    $set = initializeLedgerRequirements($attempt, $registrar);
    $mandatoryItems = $set->items()->where('is_mandatory', true)->get();
    expect($mandatoryItems->count())->toBeGreaterThan(0);

    foreach ($mandatoryItems as $item) {
        verifyLedgerItem(
            $attempt,
            $set,
            (int) $item->id,
            AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
            $registrar,
            internalRemarks: 'Verified',
        );
    }

    $query = app(AttemptRequirementChecklistQuery::class);
    $checklist = $query->forAttempt($attempt);

    expect($checklist['all_mandatory_verified'])->toBeTrue()
        ->and($checklist['mandatory_pending_count'])->toBe(0)
        ->and($query->allMandatoryVerified($attempt))->toBeTrue();
});

test('modifying verifications appends new rows and records audit events and predecessor', function (): void {
    [$attempt, $registrar] = getDocumentTestFixture('ledger-append-history@example.test');

    $set = initializeLedgerRequirements($attempt, $registrar);
    $item = $set->items()->firstOrFail();

    $v1 = verifyLedgerItem(
        $attempt,
        $set,
        (int) $item->id,
        AdmissionAttemptDocumentVerification::STATUS_DEFICIENT,
        $registrar,
        applicantInstruction: 'Please bring the original document for inspection.',
        internalRemarks: 'Original required, submitted copy',
        idempotencyKey: 'append-step-1',
    );

    expect($v1->predecessor_id)->toBeNull();

    $v2 = verifyLedgerItem(
        $attempt,
        $set,
        (int) $item->id,
        AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
        $registrar,
        internalRemarks: 'Original brought and checked',
        idempotencyKey: 'append-step-2',
    );

    // History is preserved: both rows exist
    $verifications = AdmissionAttemptDocumentVerification::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('requirement_item_id', $item->id)
        ->orderBy('id')
        ->get();

    expect($verifications->count())->toBe(2)
        ->and($verifications[0]->status)->toBe(AdmissionAttemptDocumentVerification::STATUS_DEFICIENT)
        ->and($verifications[1]->status)->toBe(AdmissionAttemptDocumentVerification::STATUS_VERIFIED)
        ->and($verifications[1]->predecessor_id)->toBe($verifications[0]->id);

    // Audit event was recorded
    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'admission.document_verified',
    ]);
});

test('verification idempotency replays only the exact actor-owned payload', function (): void {
    [$attempt, $registrar] = getDocumentTestFixture('ledger-verification-replay@example.test');
    $set = initializeLedgerRequirements($attempt, $registrar);
    $item = $set->items()->firstOrFail();
    $key = 'ledger-verification-payload-replay';

    $first = verifyLedgerItem(
        $attempt,
        $set,
        (int) $item->id,
        AdmissionAttemptDocumentVerification::STATUS_MISSING,
        $registrar,
        internalRemarks: 'Observed at first visit.',
        idempotencyKey: $key,
    );
    $replayed = app(VerifyAdmissionAttemptDocument::class)->handle(
        attempt: $attempt->fresh(),
        requirementSetId: (int) $set->id,
        requirementItemId: (int) $item->id,
        expectedLatestVerificationId: null,
        status: AdmissionAttemptDocumentVerification::STATUS_MISSING,
        actor: $registrar,
        expectedVersion: (int) $attempt->version,
        applicantInstruction: null,
        internalRemarks: 'Observed at first visit.',
        idempotencyKey: $key,
    );

    expect($replayed->id)->toBe($first->id)
        ->and(AdmissionAttemptDocumentVerification::query()->where('admission_attempt_id', $attempt->id)
            ->where('requirement_item_id', $item->id)->count())->toBe(1);

    expect(fn () => app(VerifyAdmissionAttemptDocument::class)->handle(
        attempt: $attempt->fresh(),
        requirementSetId: (int) $set->id,
        requirementItemId: (int) $item->id,
        expectedLatestVerificationId: null,
        status: AdmissionAttemptDocumentVerification::STATUS_MISSING,
        actor: $registrar,
        expectedVersion: (int) $attempt->version,
        applicantInstruction: null,
        internalRemarks: 'Changed payload with the same key.',
        idempotencyKey: $key,
    ))->toThrow(RuntimeException::class);

    $secondRegistrar = User::factory()->create();
    $secondRegistrar->givePermissionTo(CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY);
    StaffProfile::query()->create([
        'user_id' => $secondRegistrar->id,
        'institution_id' => $attempt->institution_id,
        'office' => 'registrar',
        'active_cycle_code' => $attempt->academicCycle->code,
        'program_codes' => [],
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    expect(fn () => app(VerifyAdmissionAttemptDocument::class)->handle(
        attempt: $attempt->fresh(),
        requirementSetId: (int) $set->id,
        requirementItemId: (int) $item->id,
        expectedLatestVerificationId: null,
        status: AdmissionAttemptDocumentVerification::STATUS_MISSING,
        actor: $secondRegistrar,
        expectedVersion: (int) $attempt->version,
        applicantInstruction: null,
        internalRemarks: 'Observed at first visit.',
        idempotencyKey: $key,
    ))->toThrow(RuntimeException::class);
});

test('verification is blocked when clearance is actively confirmed', function (): void {
    [$attempt, $registrar] = getDocumentTestFixture('ledger-clearance-lock@example.test');

    $set = initializeLedgerRequirements($attempt, $registrar);
    $item = $set->items()->firstOrFail();

    AdmissionClearanceReceipt::query()->create([
        'admission_attempt_id' => $attempt->id,
        'program_id' => $attempt->program_id ?? 1,
        'receipt_number' => 'CLR-TEST-LOCK-001',
        'status' => AdmissionClearanceReceipt::STATUS_CONFIRMED,
        'evidence_vector' => ['locked' => true],
        'confirmed_by_user_id' => $registrar->id,
        'confirmed_at' => now(),
        'idempotency_key' => 'test-clearance-lock-key',
    ]);

    expect(fn () => verifyLedgerItem(
        $attempt,
        $set,
        (int) $item->id,
        AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
        $registrar,
    ))->toThrow(RuntimeException::class);
});

test('deficient inspections require applicant-safe instructions, not internal remarks', function (): void {
    [$attempt, $registrar] = getDocumentTestFixture('ledger-safe-instruction@example.test');
    $set = initializeLedgerRequirements($attempt, $registrar);
    $item = $set->items()->firstOrFail();

    expect(fn () => verifyLedgerItem(
        $attempt,
        $set,
        (int) $item->id,
        AdmissionAttemptDocumentVerification::STATUS_DEFICIENT,
        $registrar,
        internalRemarks: 'Internal-only reason cannot be sent to the applicant.',
        idempotencyKey: 'deficient-with-private-only',
    ))->toThrow(ValidationException::class);

    expect(fn () => verifyLedgerItem(
        $attempt,
        $set,
        (int) $item->id,
        AdmissionAttemptDocumentVerification::STATUS_DEFICIENT,
        $registrar,
        applicantInstruction: "\u{00A0}\u{00A0}",
        idempotencyKey: 'deficient-unicode-whitespace-only',
    ))->toThrow(ValidationException::class);

    expect(AdmissionAttemptDocumentVerification::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('requirement_item_id', $item->id)
        ->count())->toBe(0);
});

test('direct verification rejects a requirement item owned by another attempt without contaminating evidence', function (): void {
    [$attemptA, $registrar] = getDocumentTestFixture('ledger-cross-attempt-a@example.test');
    [$attemptB] = getDocumentTestFixture('ledger-cross-attempt-b@example.test');
    $setA = initializeLedgerRequirements($attemptA, $registrar);
    $setB = initializeLedgerRequirements($attemptB, $registrar);
    $foreignItem = $setB->items()->firstOrFail();

    expect(fn () => verifyLedgerItem(
        $attemptA,
        $setA,
        (int) $foreignItem->id,
        AdmissionAttemptDocumentVerification::STATUS_MISSING,
        $registrar,
    ))->toThrow(ModelNotFoundException::class);

    expect(AdmissionAttemptDocumentVerification::query()
        ->where('admission_attempt_id', $attemptA->id)
        ->where('requirement_item_id', $foreignItem->id)
        ->count())->toBe(0)
        ->and(AdmissionAttemptDocumentVerification::query()
            ->where('admission_attempt_id', $attemptB->id)
            ->where('requirement_item_id', $foreignItem->id)
            ->count())->toBe(0);
});

test('inspection HTTP route authorizes freshly and hides a foreign nested requirement item', function (): void {
    [$attemptA, $registrar] = getDocumentTestFixture('ledger-http-cross-a@example.test');
    [$attemptB] = getDocumentTestFixture('ledger-http-cross-b@example.test');
    $setA = initializeLedgerRequirements($attemptA, $registrar);
    $setB = initializeLedgerRequirements($attemptB, $registrar);
    $httpRegistrar = User::factory()->create();
    $httpRegistrar->givePermissionTo(CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY);
    StaffProfile::query()->create([
        'user_id' => $httpRegistrar->id,
        'institution_id' => $attemptA->institution_id,
        'office' => 'registrar',
        'active_cycle_code' => $attemptA->academicCycle->code,
        'program_codes' => [],
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    StaffAuthenticationState::query()->create([
        'user_id' => $httpRegistrar->id,
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'session_version' => 1,
        'mfa_required' => false,
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
        'two_factor_generation' => (string) Str::uuid(),
    ]);
    $foreignItem = $setB->items()->firstOrFail();
    $payload = [
        'expected_version' => (int) $attemptA->fresh()->version,
        'requirement_set_id' => $setA->id,
        'requirement_item_id' => $foreignItem->id,
        'expected_latest_verification_id' => null,
        'status' => AdmissionAttemptDocumentVerification::STATUS_MISSING,
        'applicant_instruction' => null,
        'internal_remarks' => 'Do not disclose this cross-attempt attempt.',
        'idempotency_key' => 'http-foreign-item',
    ];

    $sessionFor = function (User $user, string $generation): array {
        $user->authenticationState()->firstOrFail()->forceFill([
            'activation_completed_at' => now(),
            'password_set_at' => now(),
            'mfa_required' => false,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_generation' => (string) Str::uuid(),
        ])->save();

        return [
            'auth.surface' => 'staff',
            'auth.session_version' => (int) $user->authenticationState->session_version,
            'auth.staff_inactivity_generation' => $generation,
        ];
    };
    $this->actingAs($httpRegistrar)->withSession($sessionFor($httpRegistrar, 'document-ledger-http-test'))
        ->postJson(route('registrar.clearance.documents.verify', $attemptA), $payload)
        ->assertNotFound();

    expect(AdmissionAttemptDocumentVerification::query()
        ->where('admission_attempt_id', $attemptA->id)
        ->where('requirement_item_id', $foreignItem->id)
        ->count())->toBe(0)
        ->and(AdmissionAttemptDocumentVerification::query()
            ->where('admission_attempt_id', $attemptB->id)
            ->where('requirement_item_id', $foreignItem->id)
            ->count())->toBe(0);

    $viewOnly = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actingAs($viewOnly)->withSession($sessionFor($viewOnly, 'document-ledger-http-view-test'))
        ->postJson(route('registrar.clearance.documents.verify', $attemptA), $payload)
        ->assertForbidden();

    expect(fn () => verifyLedgerItem(
        $attemptA,
        $setA,
        (int) $setA->items()->firstOrFail()->id,
        AdmissionAttemptDocumentVerification::STATUS_MISSING,
        $viewOnly,
    ))->toThrow(AuthorizationException::class);
});

test('initialization rejects stale versions, unknown classification, and an empty active catalog', function (): void {
    [$staleAttempt, $registrar] = getDocumentTestFixture('ledger-stale-init@example.test');
    expect(fn () => app(InitializeAttemptRequirementSet::class)->handle(
        $staleAttempt,
        $registrar,
        (int) $staleAttempt->version + 1,
        'ledger-stale-init-key',
    ))->toThrow(RuntimeException::class);
    expect(AdmissionAttemptRequirementSet::query()->where('admission_attempt_id', $staleAttempt->id)->count())->toBe(1);

    [$unknownAttempt, $unknownRegistrar] = getDocumentTestFixture('ledger-unknown-track@example.test');
    $unknownAttempt->forceFill(['applicant_type' => 'unrecognized-track'])->save();
    expect(fn () => app(InitializeAttemptRequirementSet::class)->handle(
        $unknownAttempt->fresh(),
        $unknownRegistrar,
        (int) $unknownAttempt->fresh()->version,
        'ledger-unknown-track-key',
    ))->toThrow(RuntimeException::class);
    expect(app(AttemptRequirementChecklistQuery::class)->get($unknownAttempt->fresh(), $unknownRegistrar)['status'])->toBe('unknown_track')
        ->and(AdmissionAttemptRequirementSet::query()->where('admission_attempt_id', $unknownAttempt->id)->count())->toBe(1);

    [$emptyAttempt, $emptyRegistrar] = getDocumentTestFixture('ledger-empty-set@example.test');
    AdmissionAttemptRequirementSet::query()->where('admission_attempt_id', $emptyAttempt->id)->delete();
    AdmissionAttemptRequirementSet::query()->create([
        'admission_attempt_id' => $emptyAttempt->id,
        'applicant_track' => 'freshman',
        'catalog_version' => 1,
        'is_current' => true,
        'initialized_by_user_id' => $emptyRegistrar->id,
        'initialized_at' => now(),
    ]);
    $emptyProjection = app(AttemptRequirementChecklistQuery::class)->get($emptyAttempt, $emptyRegistrar);

    expect($emptyProjection['status'])->toBe('empty')
        ->and($emptyProjection['all_mandatory_verified'])->toBeFalse()
        ->and($emptyProjection['documents_verify'])->toBeFalse()
        ->and(fn () => app(InitializeAttemptRequirementSet::class)->handle(
            $emptyAttempt->fresh(),
            $emptyRegistrar,
            (int) $emptyAttempt->fresh()->version,
            'ledger-empty-set-init',
        ))->toThrow(RuntimeException::class);
});

test('changed applicant classification makes the pinned set stale and blocks verification', function (): void {
    [$attempt, $registrar] = getDocumentTestFixture('ledger-stale-classification@example.test');
    $set = initializeLedgerRequirements($attempt, $registrar);
    $item = $set->items()->firstOrFail();
    $attempt->forceFill(['applicant_type' => 'transferee'])->save();
    $freshAttempt = $attempt->fresh();
    $projection = app(AttemptRequirementChecklistQuery::class)->get($freshAttempt, $registrar);

    expect($projection['status'])->toBe('stale')
        ->and($projection['all_mandatory_verified'])->toBeFalse()
        ->and(fn () => verifyLedgerItem(
            $freshAttempt,
            $set,
            (int) $item->id,
            AdmissionAttemptDocumentVerification::STATUS_MISSING,
            $registrar,
        ))->toThrow(RuntimeException::class)
        ->and(AdmissionAttemptDocumentVerification::query()
            ->where('admission_attempt_id', $attempt->id)
            ->count())->toBe(0);
});

test('migration refuses duplicate current requirement sets before changing schema or evidence', function (): void {
    [$attempt] = getDocumentTestFixture('ledger-migration-duplicate-current@example.test');

    DB::table('admission_attempt_requirement_sets')->where('admission_attempt_id', $attempt->id)->delete();
    Schema::table('admission_attempt_requirement_sets', function ($table): void {
        $table->dropIndex('admission_attempt_requirement_sets_single_current');
    });
    Schema::table('admission_attempt_document_verifications', function ($table): void {
        $table->dropColumn(['applicant_instruction', 'internal_remarks']);
    });

    $firstSetId = DB::table('admission_attempt_requirement_sets')->insertGetId([
        'admission_attempt_id' => $attempt->id,
        'applicant_track' => 'freshman',
        'catalog_version' => 1,
        'is_current' => true,
        'initialized_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $secondSetId = DB::table('admission_attempt_requirement_sets')->insertGetId([
        'admission_attempt_id' => $attempt->id,
        'applicant_track' => 'freshman',
        'catalog_version' => 2,
        'is_current' => true,
        'initialized_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_10_08_140100_harden_admission_document_checklist_ledgers.php');

    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, "attempt {$attempt->id} (current set IDs: {$firstSetId}, {$secondSetId})")
        ->and(Schema::hasColumns('admission_attempt_document_verifications', ['applicant_instruction', 'internal_remarks']))
        ->toBeFalse()
        ->and(DB::table('admission_attempt_requirement_sets')
            ->where('admission_attempt_id', $attempt->id)
            ->where('is_current', true)
            ->orderBy('id')
            ->pluck('id')
            ->all())
        ->toBe([$firstSetId, $secondSetId]);
});
