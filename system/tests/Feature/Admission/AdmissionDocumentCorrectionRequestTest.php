<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\InitializeAttemptRequirementSet;
use App\Admission\Actions\RequestAdmissionDocumentCorrection;
use App\Admission\Actions\VerifyAdmissionAttemptDocument;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptDocumentVerification;
use App\Models\Admission\AdmissionAttemptRequirementSet;
use App\Models\Admission\AdmissionDocumentCorrectionRequest;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\User;
use App\Notifications\NotificationBusinessValidity;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationProjection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{0:AdmissionAttempt,1:User,2:string} */
function documentCorrectionFixture(string $email): array
{
    seedE2Foundation();
    $lastName = 'Document Request '.substr(hash('sha256', $email), 0, 12);
    $submission = submitSyntheticApplicant($email, $lastName);
    $attempt = $submission['attempt'];
    $attempt->status = AdmissionAttempt::STATUS_ACCEPTED;
    $attempt->save();

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    return [$attempt, $registrar, $submission['access_token']];
}

function authenticateDocumentRequestStaff(User $user): void
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
    test()->assertAuthenticatedAs($user);
}

function initializeDocumentCorrectionChecklist(AdmissionAttempt $attempt, User $registrar): AdmissionAttemptRequirementSet
{
    $attempt->refresh();

    return app(InitializeAttemptRequirementSet::class)->handle(
        $attempt,
        $registrar,
        (int) $attempt->version,
        'document-correction-init-'.$attempt->id,
    );
}

function inspectDocumentCorrectionItem(
    AdmissionAttempt $attempt,
    AdmissionAttemptRequirementSet $set,
    int $itemId,
    User $registrar,
    string $status = AdmissionAttemptDocumentVerification::STATUS_MISSING,
    ?string $applicantInstruction = null,
    ?string $internalRemarks = null,
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
        idempotencyKey: 'document-correction-inspect-'.$attempt->id.'-'.$itemId.'-'.($latestId ?? 'initial').'-'.$status,
    );
}

/** @return array{expected_version:int,requirement_set_id:int,items:list<array{requirement_item_id:int,expected_latest_verification_id:?int,applicant_instruction:string}>,overall_note:?string,idempotency_key:string} */
function documentCorrectionPayload(AdmissionAttempt $attempt, AdmissionAttemptRequirementSet $set, AdmissionAttemptDocumentVerification $verification, string $key = 'document-request-send-1'): array
{
    return [
        'expected_version' => (int) $attempt->fresh()->version,
        'requirement_set_id' => (int) $set->id,
        'items' => [[
            'requirement_item_id' => (int) $verification->requirement_item_id,
            'expected_latest_verification_id' => (int) $verification->id,
            'applicant_instruction' => 'Please present the original document to the Registrar for inspection.',
        ]],
        'overall_note' => 'Please review the itemized document request.',
        'idempotency_key' => $key,
    ];
}

test('explicit itemized send preserves accepted application state, is private-safe and exactly replayable', function (): void {
    [$attempt, $registrar, $accessKey] = documentCorrectionFixture('document-request-send@example.test');
    $set = initializeDocumentCorrectionChecklist($attempt, $registrar);
    $item = $set->items()->firstOrFail();
    $verification = inspectDocumentCorrectionItem(
        $attempt,
        $set,
        (int) $item->id,
        $registrar,
        internalRemarks: 'STAFF_ONLY internal counter observation',
    );
    $payload = documentCorrectionPayload($attempt, $set, $verification);
    $acceptedAt = $attempt->fresh()->accepted_at?->toIso8601String();
    $url = route('admissions.document-correction-requests.store', $attempt);

    authenticateDocumentRequestStaff($registrar);
    $this->post($url, $payload)->assertRedirect();

    $receipt = AdmissionDocumentCorrectionRequest::query()
        ->where('admission_attempt_id', $attempt->id)
        ->with('items')
        ->firstOrFail();
    $deliveryCount = NotificationDelivery::query()
        ->where('event_type', 'admission.document_correction_requested.v1')
        ->count();
    $auditCount = DB::table('audit_events')->where('event_type', 'admission.document_correction_requested')->count();
    $delivery = NotificationDelivery::query()
        ->where('event_type', 'admission.document_correction_requested.v1')
        ->firstOrFail();
    $snapshot = json_encode($delivery->payload, JSON_THROW_ON_ERROR);
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle($accessKey, '2001-01-01');
    $projectedNotice = collect(app(NotificationProjection::class)->list($applicant))
        ->firstWhere('event_type', 'admission.document_correction_requested.v1');
    $renderedBody = $projectedNotice['body'];
    $documentRequests = $projectedNotice['document_correction_request'];

    expect($attempt->fresh()->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED)
        ->and($attempt->fresh()->isExamEligible())->toBeTrue()
        ->and($attempt->fresh()->version)->toBe($payload['expected_version'] + 1)
        ->and($attempt->fresh()->accepted_at?->toIso8601String())->toBe($acceptedAt)
        ->and($attempt->fresh()->correction_notes)->toBeNull()
        ->and($receipt->items)->toHaveCount(1)
        ->and($receipt->items->first()->applicant_instruction)->toBe($payload['items'][0]['applicant_instruction'])
        ->and($deliveryCount)->toBeGreaterThan(0)
        ->and($auditCount)->toBe(1)
        ->and($snapshot)->toContain('Please present the original document')
        ->and($snapshot)->toContain('Please review the itemized document request.')
        ->and($snapshot)->not->toContain('STAFF_ONLY')
        ->and($documentRequests['items'][0]['applicant_instruction'])->toBe($payload['items'][0]['applicant_instruction'])
        ->and($documentRequests['overall_note'])->toBe($payload['overall_note'])
        ->and($renderedBody)->toContain($payload['items'][0]['applicant_instruction'])
        ->and($renderedBody)->toContain($payload['overall_note'])
        ->and($renderedBody)->not->toContain('{{document_requests}}')
        ->and(json_encode($projectedNotice, JSON_THROW_ON_ERROR))->not->toContain('STAFF_ONLY');

    $this->post($url, $payload)->assertRedirect();
    expect(AdmissionDocumentCorrectionRequest::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(1)
        ->and(NotificationDelivery::query()->where('event_type', 'admission.document_correction_requested.v1')->count())->toBe($deliveryCount)
        ->and(DB::table('audit_events')->where('event_type', 'admission.document_correction_requested')->count())->toBe($auditCount);

    $changedPayload = [...$payload, 'overall_note' => 'Changed text under the committed key.'];
    $this->post($url, $changedPayload)->assertSessionHasErrors('document_request');
    expect(AdmissionDocumentCorrectionRequest::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(1)
        ->and(NotificationDelivery::query()->where('event_type', 'admission.document_correction_requested.v1')->count())->toBe($deliveryCount)
        ->and(DB::table('audit_events')->where('event_type', 'admission.document_correction_requested')->count())->toBe($auditCount);

    $this->post(route('logout'));
    $this->assertGuest();
    $this->post(route('applicant.login.store'), [
        'access_key' => $accessKey,
        'birthdate' => '2001-01-01',
    ])->assertRedirect(route('applicant.portal'));
    $this->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applicant/Portal')
            ->missing('document_checklist')
        );

    $this->get(route('applicant.application', ['attempt' => $attempt->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applicant/MyApplication')
            ->has('document_checklist')
            ->has('document_checklist.request_history.0.items.0.applicant_instruction')
            ->where('document_checklist.request_history.0.items.0.status', 'current')
            ->missing('document_checklist.request_history.0.items.0.internal_remarks')
            ->missing('document_checklist.request_history.0.items.0.verified_by_user_id')
            ->missing('document_checklist.request_history.0.items.0.practice_fixture_set_id')
        )
        ->assertDontSee('STAFF_ONLY');

    $resolvedInspection = inspectDocumentCorrectionItem(
        $attempt,
        $set,
        (int) $item->id,
        $registrar,
        AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
        internalRemarks: 'STAFF_ONLY verified during a later visit',
    );
    expect($resolvedInspection->predecessor_id)->toBe($verification->id)
        ->and(app(NotificationBusinessValidity::class)->isCurrent($delivery))->toBeFalse();

    $this->get(route('applicant.application', ['attempt' => $attempt->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('document_checklist.request_history.0.status', 'historical')
            ->where('document_checklist.request_history.0.items.0.status', 'resolved')
            ->missing('document_checklist.request_history.0.items.0.internal_remarks')
        )
        ->assertDontSee('STAFF_ONLY');
});

test('direct send rejects foreign, unchecked and verified items without creating request or delivery', function (): void {
    [$attemptA, $registrar] = documentCorrectionFixture('document-request-scope-a@example.test');
    [$attemptB] = documentCorrectionFixture('document-request-scope-b@example.test');
    $setA = initializeDocumentCorrectionChecklist($attemptA, $registrar);
    $setB = initializeDocumentCorrectionChecklist($attemptB, $registrar);
    $foreignItem = $setB->items()->firstOrFail();
    $foreignVerification = inspectDocumentCorrectionItem($attemptB, $setB, (int) $foreignItem->id, $registrar);
    $baselineDeliveries = NotificationDelivery::query()->where('event_type', 'admission.document_correction_requested.v1')->count();

    $foreignPayload = [
        'expected_version' => (int) $attemptA->fresh()->version,
        'requirement_set_id' => (int) $setA->id,
        'items' => [[
            'requirement_item_id' => (int) $foreignItem->id,
            'expected_latest_verification_id' => (int) $foreignVerification->id,
            'applicant_instruction' => 'Please present the document.',
        ]],
        'overall_note' => null,
        'idempotency_key' => 'document-request-foreign-item',
    ];

    expect(fn () => app(RequestAdmissionDocumentCorrection::class)->handle($attemptA, $registrar, $foreignPayload))
        ->toThrow(ModelNotFoundException::class);

    $uncheckedItem = $setA->items()->where('id', '!=', $foreignItem->id)->firstOrFail();
    $uncheckedPayload = $foreignPayload;
    $uncheckedPayload['items'][0]['requirement_item_id'] = (int) $uncheckedItem->id;
    $uncheckedPayload['items'][0]['expected_latest_verification_id'] = null;
    $uncheckedPayload['idempotency_key'] = 'document-request-unchecked-item';
    expect(fn () => app(RequestAdmissionDocumentCorrection::class)->handle($attemptA, $registrar, $uncheckedPayload))
        ->toThrow(RuntimeException::class);

    $verifiedItem = $setA->items()->firstOrFail();
    $verified = inspectDocumentCorrectionItem(
        $attemptA,
        $setA,
        (int) $verifiedItem->id,
        $registrar,
        AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
    );
    $verifiedPayload = documentCorrectionPayload($attemptA, $setA, $verified, 'document-request-verified-item');
    expect(fn () => app(RequestAdmissionDocumentCorrection::class)->handle($attemptA, $registrar, $verifiedPayload))
        ->toThrow(RuntimeException::class);

    expect(AdmissionDocumentCorrectionRequest::query()->where('admission_attempt_id', $attemptA->id)->count())->toBe(0)
        ->and(NotificationDelivery::query()->where('event_type', 'admission.document_correction_requested.v1')->count())->toBe($baselineDeliveries);
});

test('read-only principal cannot send document requests over HTTP', function (): void {
    [$attempt, $registrar] = documentCorrectionFixture('document-request-readonly@example.test');
    $set = initializeDocumentCorrectionChecklist($attempt, $registrar);
    $item = $set->items()->firstOrFail();
    $verification = inspectDocumentCorrectionItem($attempt, $set, (int) $item->id, $registrar);
    $payload = documentCorrectionPayload($attempt, $set, $verification, 'document-request-readonly');
    $readOnly = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    authenticateDocumentRequestStaff($readOnly);
    $this->postJson(route('admissions.document-correction-requests.store', $attempt), $payload)
        ->assertForbidden();

    expect(AdmissionDocumentCorrectionRequest::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0);
    expect(fn () => app(RequestAdmissionDocumentCorrection::class)->handle($attempt, $readOnly, $payload))
        ->toThrow(AuthorizationException::class);

    expect(AdmissionDocumentCorrectionRequest::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0);
});

test('send HTTP validation rejects duplicate items and prohibited private fields without side effects', function (): void {
    [$attempt, $registrar] = documentCorrectionFixture('document-request-validation@example.test');
    $set = initializeDocumentCorrectionChecklist($attempt, $registrar);
    $item = $set->items()->firstOrFail();
    $verification = inspectDocumentCorrectionItem($attempt, $set, (int) $item->id, $registrar);
    $payload = documentCorrectionPayload($attempt, $set, $verification, 'document-request-duplicate-items');
    $payload['items'][] = $payload['items'][0];

    authenticateDocumentRequestStaff($registrar);
    $this->postJson(route('admissions.document-correction-requests.store', $attempt), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.1.requirement_item_id');

    $payload = documentCorrectionPayload($attempt, $set, $verification, 'document-request-prohibited-fields');
    $payload['internal_remarks'] = 'Never pass internal notes to the applicant.';
    $this->postJson(route('admissions.document-correction-requests.store', $attempt), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('internal_remarks');

    expect(AdmissionDocumentCorrectionRequest::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0)
        ->and(NotificationDelivery::query()->where('event_type', 'admission.document_correction_requested.v1')->count())->toBe(0)
        ->and(DB::table('audit_events')->where('event_type', 'admission.document_correction_requested')->count())->toBe(0);
});

test('changed inspection evidence and stale attempt version block new sends without evidence', function (): void {
    [$attempt, $registrar] = documentCorrectionFixture('document-request-stale-evidence@example.test');
    $set = initializeDocumentCorrectionChecklist($attempt, $registrar);
    $item = $set->items()->firstOrFail();
    $first = inspectDocumentCorrectionItem($attempt, $set, (int) $item->id, $registrar);
    $payload = documentCorrectionPayload($attempt, $set, $first, 'document-request-stale-evidence');

    inspectDocumentCorrectionItem(
        $attempt,
        $set,
        (int) $item->id,
        $registrar,
        AdmissionAttemptDocumentVerification::STATUS_MISSING,
        internalRemarks: 'Later inspection observation.',
    );

    expect(fn () => app(RequestAdmissionDocumentCorrection::class)->handle($attempt, $registrar, $payload))
        ->toThrow(RuntimeException::class);

    authenticateDocumentRequestStaff($registrar);
    $response = $this->post(route('admissions.document-correction-requests.store', $attempt), $payload)
        ->assertRedirect()
        ->assertSessionHasErrors('document_request')
        ->assertSessionHas('document_correction_request_outcome', 'definite_rejection');
    $this->get($response->headers->get('Location'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.document_correction_request_outcome', 'definite_rejection')
            ->where('errors.document_request', 'Stale admission attempt version; refresh and review the document request.'));

    expect(AdmissionDocumentCorrectionRequest::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0)
        ->and(NotificationDelivery::query()->where('event_type', 'admission.document_correction_requested.v1')->count())->toBe(0)
        ->and(DB::table('audit_events')->where('event_type', 'admission.document_correction_requested')->count())->toBe(0);
});

test('after-commit queue failure retains the recorded receipt and exact replay does not duplicate work', function (): void {
    [$attempt, $registrar] = documentCorrectionFixture('document-request-queue-failure@example.test');
    $set = initializeDocumentCorrectionChecklist($attempt, $registrar);
    $item = $set->items()->firstOrFail();
    $verification = inspectDocumentCorrectionItem($attempt, $set, (int) $item->id, $registrar);
    $payload = documentCorrectionPayload($attempt, $set, $verification, 'document-request-queue-failure');

    $bus = Mockery::mock(BusDispatcher::class);
    $bus->shouldReceive('dispatch')
        ->once()
        ->andThrow(new RuntimeException('queue unavailable'));
    app()->instance(BusDispatcher::class, $bus);

    authenticateDocumentRequestStaff($registrar);
    $url = route('admissions.document-correction-requests.store', $attempt);
    $response = $this->post($url, $payload)
        ->assertRedirect()
        ->assertSessionHas('document_correction_request_delivery', 'failed');
    $receipt = AdmissionDocumentCorrectionRequest::query()
        ->where('admission_attempt_id', $attempt->id)
        ->firstOrFail();
    $this->get($response->headers->get('Location'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.document_correction_request_recorded', (int) $receipt->getKey())
            ->where('flash.document_correction_request_delivery', 'failed'));
    $deliveryCount = NotificationDelivery::query()->where('event_type', 'admission.document_correction_requested.v1')->count();
    $auditCount = DB::table('audit_events')->where('event_type', 'admission.document_correction_requested')->count();

    $this->post($url, $payload)
        ->assertRedirect()
        ->assertSessionHas('document_correction_request_recorded', (int) $receipt->getKey())
        ->assertSessionHasNoErrors();

    expect(AdmissionDocumentCorrectionRequest::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(1)
        ->and(NotificationDelivery::query()->where('event_type', 'admission.document_correction_requested.v1')->count())->toBe($deliveryCount)
        ->and(DB::table('audit_events')->where('event_type', 'admission.document_correction_requested')->count())->toBe($auditCount);
});
