<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\InitializeAttemptRequirementSet;
use App\Admission\Actions\RecordRequirementInternalNote;
use App\Admission\Actions\VerifyAdmissionAttemptDocument;
use App\Admission\Queries\AttemptRequirementChecklistQuery;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptDocumentVerification;
use App\Models\Admission\AdmissionAttemptRequirementInternalNote;
use App\Models\Admission\AdmissionAttemptRequirementItem;
use App\Models\Admission\AdmissionAttemptRequirementSet;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\IntakeRequirement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** @return array{0: AdmissionAttempt, 1: User, 2: string} */
function requirementInternalNoteFixture(string $email): array
{
    seedE2Foundation();
    $submission = submitSyntheticApplicant($email, 'Internal Note '.substr(hash('sha256', $email), 0, 10));
    /** @var AdmissionAttempt $attempt */
    $attempt = $submission['attempt'];
    $attempt->forceFill(['status' => AdmissionAttempt::STATUS_ACCEPTED])->save();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    return [$attempt, $registrar, $submission['access_token']];
}

function initializeRequirementNoteSet(AdmissionAttempt $attempt, User $registrar, string $key): AdmissionAttemptRequirementSet
{
    $attempt->refresh();

    return app(InitializeAttemptRequirementSet::class)->handle(
        $attempt,
        $registrar,
        (int) $attempt->version,
        $key,
    );
}

/** @return array<string, mixed> */
function requirementInternalNotePayload(
    AdmissionAttempt $attempt,
    AdmissionAttemptRequirementSet $set,
    int $itemId,
    ?string $remarks,
    string $key,
): array {
    return [
        'expected_version' => (int) $attempt->fresh()->version,
        'requirement_set_id' => (int) $set->id,
        'expected_latest_verification_id' => DB::table('admission_attempt_document_verifications')
            ->where('admission_attempt_id', $attempt->id)
            ->where('requirement_item_id', $itemId)
            ->orderByDesc('id')
            ->value('id'),
        'expected_latest_note_id' => AdmissionAttemptRequirementInternalNote::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('requirement_item_id', $itemId)
            ->orderByDesc('id')
            ->value('id'),
        'internal_remarks' => $remarks,
        'idempotency_key' => $key,
    ];
}

function authenticateRequirementNoteStaff(User $user): void
{
    $state = StaffAuthenticationState::query()->where('user_id', $user->id)->firstOrFail();
    $state->forceFill([
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'must_change_password' => false,
        'mfa_required' => false,
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
        'two_factor_generation' => (string) Str::uuid(),
        'two_factor_last_accepted_step' => null,
    ])->save();

    test()->actingAs($user)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
        'auth.staff_inactivity_generation' => (string) Str::uuid(),
    ]);
}

/** @return array{0: AdmissionAttemptDocumentVerification, 1: int} */
function inspectRequirementNoteItem(
    AdmissionAttempt $attempt,
    AdmissionAttemptRequirementSet $set,
    int $itemId,
    User $registrar,
    string $status,
    ?string $instruction,
    string $key,
): array {
    $attempt->refresh();
    $latestVerificationId = DB::table('admission_attempt_document_verifications')
        ->where('admission_attempt_id', $attempt->id)
        ->where('requirement_item_id', $itemId)
        ->orderByDesc('id')
        ->value('id');

    $verification = app(VerifyAdmissionAttemptDocument::class)->handle(
        attempt: $attempt,
        requirementSetId: (int) $set->id,
        requirementItemId: $itemId,
        expectedLatestVerificationId: $latestVerificationId === null ? null : (int) $latestVerificationId,
        status: $status,
        actor: $registrar,
        expectedVersion: (int) $attempt->version,
        applicantInstruction: $instruction,
        internalRemarks: null,
        idempotencyKey: $key,
    );

    return [$verification, (int) $attempt->fresh()->version];
}
test('catalog item note atomically provisions checklist and records one attempt version', function (): void {
    [$attempt, $registrar] = requirementInternalNoteFixture('requirement-note-catalog@example.test');
    AdmissionAttemptRequirementSet::query()->where('admission_attempt_id', $attempt->id)->delete();
    $requirement = IntakeRequirement::query()
        ->where('institution_id', $attempt->institution_id)
        ->active()
        ->forTrack('freshman')
        ->orderBy('sort_order')
        ->orderBy('id')
        ->firstOrFail();

    authenticateRequirementNoteStaff($registrar);
    $this->post(route('registrar.clearance.requirements.internal-notes.catalog.store', $attempt), [
        'expected_version' => (int) $attempt->version,
        'expected_latest_verification_id' => null,
        'expected_latest_note_id' => null,
        'internal_remarks' => 'Catalog item reviewed at the counter',
        'idempotency_key' => 'requirement-note-catalog-write',
        'intake_requirement_id' => (int) $requirement->id,
    ])->assertRedirect();
    $note = AdmissionAttemptRequirementInternalNote::query()
        ->where('admission_attempt_id', $attempt->id)
        ->latest('id')
        ->firstOrFail();
    $provisionedItem = AdmissionAttemptRequirementItem::query()->findOrFail($note->requirement_item_id);

    expect($provisionedItem->intake_requirement_id)->toBe($requirement->id)
        ->and(AdmissionAttemptRequirementSet::query()->where('admission_attempt_id', $attempt->id)->where('is_current', true)->count())->toBe(1)
        ->and((int) $attempt->fresh()->version)->toBe(2)
        ->and($note->internal_remarks)->toBe('Catalog item reviewed at the counter');
});

test('private notes append history and project to staff without fabricating an inspection', function (): void {
    [$attempt, $registrar] = requirementInternalNoteFixture('requirement-note-unchecked@example.test');
    $set = initializeRequirementNoteSet($attempt, $registrar, 'requirement-note-unchecked-init');
    $item = $set->items()->firstOrFail();
    $beforeVersion = (int) $attempt->fresh()->version;
    $payload = requirementInternalNotePayload($attempt, $set, (int) $item->id, 'Original counter observation', 'unchecked-note-1');

    authenticateRequirementNoteStaff($registrar);
    $this->post(route('registrar.clearance.requirements.internal-notes.store', [$attempt, $item]), $payload)
        ->assertRedirect();

    $note = AdmissionAttemptRequirementInternalNote::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    $projection = app(AttemptRequirementChecklistQuery::class)->get($attempt->fresh(), $registrar);
    $projectedItem = collect($projection['items'])->firstWhere('id', $item->id);

    expect($note->internal_remarks)->toBe('Original counter observation')
        ->and($note->predecessor_id)->toBeNull()
        ->and($attempt->fresh()->version)->toBe($beforeVersion + 1)
        ->and(AuditEvent::query()->where('event_type', 'admission.document_internal_note_recorded')
            ->where('payload->note_id', $note->id)->exists())->toBeTrue()
        ->and($projectedItem['latest_status'])->toBe(AdmissionAttemptDocumentVerification::STATUS_NOT_CHECKED)
        ->and($projectedItem['latest_verification_id'])->toBeNull()
        ->and($projectedItem['internal_note']['body'])->toBe('Original counter observation')
        ->and($projectedItem['internal_note']['actor_name'])->toBe($registrar->name)
        ->and($projectedItem['internal_note_history'])->toHaveCount(1)
        ->and(AdmissionAttemptDocumentVerification::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0);
});

test('private note create update and clear preserve each inspected status and applicant instruction', function (): void {
    [$attempt, $registrar] = requirementInternalNoteFixture('requirement-note-inspected@example.test');
    $set = initializeRequirementNoteSet($attempt, $registrar, 'requirement-note-inspected-init');
    $items = $set->items()->take(3)->get();
    expect($items)->toHaveCount(3);
    $statuses = [
        [AdmissionAttemptDocumentVerification::STATUS_VERIFIED, null],
        [AdmissionAttemptDocumentVerification::STATUS_DEFICIENT, 'Please provide the original document.'],
        [AdmissionAttemptDocumentVerification::STATUS_MISSING, 'Please submit this document.'],
    ];
    authenticateRequirementNoteStaff($registrar);

    foreach ($items as $index => $item) {
        [$verification] = inspectRequirementNoteItem(
            $attempt,
            $set,
            (int) $item->id,
            $registrar,
            $statuses[$index][0],
            $statuses[$index][1],
            'requirement-note-inspection-'.$index,
        );
        $status = $statuses[$index][0];
        $instruction = $verification->applicant_instruction;

        $firstPayload = requirementInternalNotePayload($attempt, $set, (int) $item->id, 'First private note', 'inspected-note-'.$index.'-1');
        $this->post(route('registrar.clearance.requirements.internal-notes.store', [$attempt, $item]), $firstPayload)->assertRedirect();
        $firstNote = AdmissionAttemptRequirementInternalNote::query()->where('requirement_item_id', $item->id)->latest('id')->firstOrFail();

        $updatePayload = requirementInternalNotePayload($attempt, $set, (int) $item->id, 'Updated private note', 'inspected-note-'.$index.'-2');
        $this->post(route('registrar.clearance.requirements.internal-notes.store', [$attempt, $item]), $updatePayload)->assertRedirect();
        $updatedNote = AdmissionAttemptRequirementInternalNote::query()->where('requirement_item_id', $item->id)->latest('id')->firstOrFail();

        $clearPayload = requirementInternalNotePayload($attempt, $set, (int) $item->id, null, 'inspected-note-'.$index.'-3');
        $this->post(route('registrar.clearance.requirements.internal-notes.store', [$attempt, $item]), $clearPayload)->assertRedirect();
        $checklistItem = collect(app(AttemptRequirementChecklistQuery::class)->get($attempt->fresh(), $registrar)['items'])
            ->firstWhere('id', $item->id);

        expect($firstNote->predecessor_id)->toBeNull()
            ->and($updatedNote->predecessor_id)->toBe($firstNote->id)
            ->and($checklistItem['latest_status'])->toBe($status)
            ->and($checklistItem['latest_verification_id'])->toBe($verification->id)
            ->and($checklistItem['applicant_instruction'])->toBe($instruction)
            ->and($checklistItem['internal_note'])->toBeNull()
            ->and($checklistItem['internal_remarks'])->toBeNull()
            ->and($checklistItem['internal_note_history'])->toHaveCount(3);
    }
});

test('private note idempotency replays its receipt without changing version and conflicts on changed payload', function (): void {
    [$attempt, $registrar] = requirementInternalNoteFixture('requirement-note-replay@example.test');
    $set = initializeRequirementNoteSet($attempt, $registrar, 'requirement-note-replay-init');
    $item = $set->items()->firstOrFail();
    $payload = requirementInternalNotePayload($attempt, $set, (int) $item->id, 'Replay-safe note', 'requirement-note-replay-key');
    authenticateRequirementNoteStaff($registrar);
    $url = route('registrar.clearance.requirements.internal-notes.store', [$attempt, $item]);

    $this->post($url, $payload)->assertRedirect();
    $versionAfterWrite = (int) $attempt->fresh()->version;
    $this->post($url, $payload)->assertRedirect();

    expect(AdmissionAttemptRequirementInternalNote::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(1)
        ->and($attempt->fresh()->version)->toBe($versionAfterWrite)
        ->and(AuditEvent::query()->where('event_type', 'admission.document_internal_note_recorded')->count())->toBe(1);

    $this->post($url, [...$payload, 'internal_remarks' => 'Changed payload'])->assertSessionHasErrors('conflict');
    expect(AdmissionAttemptRequirementInternalNote::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(1)
        ->and($attempt->fresh()->version)->toBe($versionAfterWrite);
});

test('stale evidence versions clearance freeze and wrong scope fail closed', function (): void {
    [$attempt, $registrar] = requirementInternalNoteFixture('requirement-note-stale@example.test');
    $set = initializeRequirementNoteSet($attempt, $registrar, 'requirement-note-stale-init');
    $item = $set->items()->firstOrFail();
    authenticateRequirementNoteStaff($registrar);
    $url = route('registrar.clearance.requirements.internal-notes.store', [$attempt, $item]);
    $base = requirementInternalNotePayload($attempt, $set, (int) $item->id, 'Should not persist', 'requirement-note-stale-version');

    $this->post($url, [...$base, 'expected_version' => (int) $attempt->fresh()->version + 1])
        ->assertSessionHasErrors('conflict');
    expect(AdmissionAttemptRequirementInternalNote::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0);

    [$verification] = inspectRequirementNoteItem(
        $attempt,
        $set,
        (int) $item->id,
        $registrar,
        AdmissionAttemptDocumentVerification::STATUS_MISSING,
        'Please submit this document.',
        'requirement-note-stale-inspection',
    );
    $staleVerificationPayload = requirementInternalNotePayload($attempt, $set, (int) $item->id, 'Stale verification', 'requirement-note-stale-verification');
    $this->post($url, [...$staleVerificationPayload, 'expected_latest_verification_id' => null])
        ->assertSessionHasErrors('conflict');

    $firstNotePayload = requirementInternalNotePayload($attempt, $set, (int) $item->id, 'First note', 'requirement-note-stale-note-first');
    $this->post($url, $firstNotePayload)->assertRedirect();
    $staleNotePayload = requirementInternalNotePayload($attempt, $set, (int) $item->id, 'Stale note', 'requirement-note-stale-note');
    $this->post($url, [...$staleNotePayload, 'expected_latest_note_id' => null])->assertSessionHasErrors('conflict');

    [$foreignAttempt, $foreignRegistrar] = requirementInternalNoteFixture('requirement-note-foreign@example.test');
    $foreignSet = initializeRequirementNoteSet($foreignAttempt, $foreignRegistrar, 'requirement-note-foreign-init');
    $foreignItem = $foreignSet->items()->firstOrFail();
    $wrongSetPayload = requirementInternalNotePayload($attempt, $set, (int) $item->id, 'Wrong set', 'requirement-note-wrong-set');
    $this->post($url, [...$wrongSetPayload, 'requirement_set_id' => $foreignSet->id])
        ->assertNotFound();
    $foreignItemPayload = requirementInternalNotePayload($attempt, $set, (int) $foreignItem->id, 'Wrong attempt item', 'requirement-note-wrong-item');
    $this->post(route('registrar.clearance.requirements.internal-notes.store', [$attempt, $foreignItem]), $foreignItemPayload)
        ->assertNotFound();
    $foreignAttemptPayload = requirementInternalNotePayload($foreignAttempt, $foreignSet, (int) $foreignItem->id, 'Foreign attempt set', 'requirement-note-foreign-attempt');
    $this->post(route('registrar.clearance.requirements.internal-notes.store', [$foreignAttempt, $foreignItem]), [
        ...$foreignAttemptPayload,
        'requirement_set_id' => $set->id,
    ])->assertNotFound();

    AdmissionClearanceReceipt::query()->create([
        'admission_attempt_id' => $attempt->id,
        'program_id' => $attempt->program_id ?? 1,
        'receipt_number' => 'CLR-NOTE-LOCK-'.substr(hash('sha256', (string) $attempt->id), 0, 12),
        'status' => AdmissionClearanceReceipt::STATUS_CONFIRMED,
        'evidence_vector' => ['locked' => true],
        'confirmed_by_user_id' => $registrar->id,
        'confirmed_at' => now(),
        'idempotency_key' => 'requirement-note-clearance-lock-'.$attempt->id,
    ]);
    $frozenPayload = requirementInternalNotePayload($attempt, $set, (int) $item->id, 'Frozen note', 'requirement-note-frozen');
    $this->post($url, $frozenPayload)->assertSessionHasErrors('conflict');
    expect(AdmissionAttemptRequirementInternalNote::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(1)
        ->and($verification->status)->toBe(AdmissionAttemptDocumentVerification::STATUS_MISSING);

    $unauthorized = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    authenticateRequirementNoteStaff($unauthorized);
    $this->post($url, requirementInternalNotePayload($attempt, $set, (int) $item->id, 'Unauthorized', 'requirement-note-unauthorized'))
        ->assertForbidden();
    expect(fn () => app(RecordRequirementInternalNote::class)->handle(
        $attempt->fresh(),
        (int) $set->id,
        (int) $item->id,
        (int) $verification->id,
        (int) AdmissionAttemptRequirementInternalNote::query()->where('admission_attempt_id', $attempt->id)->value('id'),
        $unauthorized,
        (int) $attempt->fresh()->version,
        'Unauthorized direct write',
        'requirement-note-unauthorized-direct',
    ))->toThrow(AuthorizationException::class);
});

test('applicant checklist projection excludes all internal note fields', function (): void {
    [$attempt, $registrar, $accessKey] = requirementInternalNoteFixture('requirement-note-private@example.test');
    $set = initializeRequirementNoteSet($attempt, $registrar, 'requirement-note-private-init');
    $item = $set->items()->firstOrFail();
    $payload = requirementInternalNotePayload($attempt, $set, (int) $item->id, 'STAFF ONLY confidential note', 'requirement-note-private-key');
    authenticateRequirementNoteStaff($registrar);
    $this->post(route('registrar.clearance.requirements.internal-notes.store', [$attempt, $item]), $payload)->assertRedirect();

    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle($accessKey, '2001-01-01');
    $projection = app(AttemptRequirementChecklistQuery::class)->forApplicant($attempt, $applicant);
    $projectedItem = collect($projection['items'])->firstWhere('id', $item->id);

    expect($projectedItem)->not->toHaveKeys([
        'internal_note',
        'latest_note_id',
        'internal_note_history',
        'internal_remarks',
    ])
        ->and(json_encode($projection, JSON_THROW_ON_ERROR))->not->toContain('STAFF ONLY confidential note');
});
