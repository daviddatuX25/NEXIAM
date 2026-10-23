<?php

use App\Admission\Actions\BulkVerifyAdmissionAttemptDocuments;
use App\Admission\Actions\InitializeAttemptRequirementSet;
use App\Admission\Actions\VerifyAdmissionAttemptDocument;
use App\Auth\Actions\EstablishStaffSessionLease;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptDocumentVerification;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\User;
use Illuminate\Support\Str;

function getBulkVerifyFixture(string $email): array
{
    seedE2Foundation();
    $lastName = 'BulkVerify '.substr(hash('sha256', $email), 0, 10);
    $result = submitSyntheticApplicant($email, $lastName);
    $attempt = $result['attempt'];
    $attempt->status = AdmissionAttempt::STATUS_ACCEPTED;
    $attempt->save();

    $registrar = User::query()->where('email', 'registrar.lead@nexiam.test')->first()
        ?? User::query()->where('email', 'like', '%registrar%')->first()
        ?? User::query()->first();

    $set = app(InitializeAttemptRequirementSet::class)->handle(
        $attempt,
        $registrar,
        (int) $attempt->version,
        'bulk-init-'.$attempt->id,
    );

    return [$attempt, $registrar, $set];
}

function authenticateStaffForBulkVerify(User $user): void
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
        'two_factor_generation' => (string) Str::uuid(),
        'two_factor_last_accepted_step' => null,
    ])->save();

    test()->actingAs($user);
    $sessionId = (string) Str::random(40);
    session()->setId($sessionId);
    $lease = app(EstablishStaffSessionLease::class)->handle($user, $sessionId, now());

    test()->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
        'auth.staff_inactivity_generation' => $lease->inactivity_generation,
    ]);
}

test('action verifies multiple requirements atomically with a single version bump', function (): void {
    [$attempt, $registrar, $set] = getBulkVerifyFixture('bulk-action-atomic@example.test');
    $items = $set->items()->take(3)->get();
    expect($items->count())->toBeGreaterThanOrEqual(2);

    $initialVersion = (int) $attempt->version;

    $itemsPayload = $items->map(fn ($item) => [
        'requirement_item_id' => (int) $item->id,
        'expected_latest_verification_id' => null,
    ])->all();

    $action = app(BulkVerifyAdmissionAttemptDocuments::class);
    $verifications = $action->handle(
        attempt: $attempt,
        requirementSetId: (int) $set->id,
        items: $itemsPayload,
        actor: $registrar,
        expectedVersion: $initialVersion,
        idempotencyKey: 'bulk-key-1',
    );

    expect($verifications->count())->toBe($items->count());
    $attempt->refresh();
    expect((int) $attempt->version)->toBe($initialVersion + 1);

    foreach ($items as $item) {
        $ver = AdmissionAttemptDocumentVerification::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('requirement_item_id', $item->id)
            ->latest('id')
            ->first();

        expect($ver)->not->toBeNull()
            ->and($ver->status)->toBe(AdmissionAttemptDocumentVerification::STATUS_VERIFIED)
            ->and($ver->applicant_instruction)->toBeNull()
            ->and($ver->verified_by_user_id)->toBe($registrar->id);
    }
});

test('idempotent replay returns existing verifications without bumping version again', function (): void {
    [$attempt, $registrar, $set] = getBulkVerifyFixture('bulk-replay@example.test');
    $items = $set->items()->take(2)->get();
    $initialVersion = (int) $attempt->version;

    $itemsPayload = $items->map(fn ($item) => [
        'requirement_item_id' => (int) $item->id,
        'expected_latest_verification_id' => null,
    ])->all();

    $action = app(BulkVerifyAdmissionAttemptDocuments::class);
    $firstRun = $action->handle(
        attempt: $attempt,
        requirementSetId: (int) $set->id,
        items: $itemsPayload,
        actor: $registrar,
        expectedVersion: $initialVersion,
        idempotencyKey: 'bulk-replay-key',
    );

    $attempt->refresh();
    $versionAfterFirst = (int) $attempt->version;
    expect($versionAfterFirst)->toBe($initialVersion + 1);

    // Replay with exact same idempotency key
    $replayRun = $action->handle(
        attempt: $attempt,
        requirementSetId: (int) $set->id,
        items: $itemsPayload,
        actor: $registrar,
        expectedVersion: $initialVersion,
        idempotencyKey: 'bulk-replay-key',
    );

    $attempt->refresh();
    expect((int) $attempt->version)->toBe($versionAfterFirst)
        ->and($replayRun->pluck('id')->sort()->values()->all())
        ->toBe($firstRun->pluck('id')->sort()->values()->all());
});

test('bulk verify rejects stale expected version', function (): void {
    [$attempt, $registrar, $set] = getBulkVerifyFixture('bulk-stale-ver@example.test');
    $items = $set->items()->take(2)->get();

    $itemsPayload = $items->map(fn ($item) => [
        'requirement_item_id' => (int) $item->id,
        'expected_latest_verification_id' => null,
    ])->all();

    $action = app(BulkVerifyAdmissionAttemptDocuments::class);
    $action->handle(
        attempt: $attempt,
        requirementSetId: (int) $set->id,
        items: $itemsPayload,
        actor: $registrar,
        expectedVersion: 9999, // intentionally stale
        idempotencyKey: 'bulk-stale-key',
    );
})->throws(RuntimeException::class, 'The admission attempt changed');

test('bulk verify rejects stale expected latest verification id', function (): void {
    [$attempt, $registrar, $set] = getBulkVerifyFixture('bulk-stale-inspection@example.test');
    $item = $set->items()->first();

    // Verify item once first
    app(VerifyAdmissionAttemptDocument::class)->handle(
        attempt: $attempt,
        requirementSetId: (int) $set->id,
        requirementItemId: (int) $item->id,
        expectedLatestVerificationId: null,
        status: 'deficient',
        actor: $registrar,
        expectedVersion: (int) $attempt->version,
        applicantInstruction: 'Please present original',
        internalRemarks: null,
        idempotencyKey: 'first-def-verify',
    );

    $attempt->refresh();

    // Try bulk verify pretending expected_latest_verification_id is still null
    $action = app(BulkVerifyAdmissionAttemptDocuments::class);
    $action->handle(
        attempt: $attempt,
        requirementSetId: (int) $set->id,
        items: [
            [
                'requirement_item_id' => (int) $item->id,
                'expected_latest_verification_id' => null, // stale: should be the deficient verification id
            ],
        ],
        actor: $registrar,
        expectedVersion: (int) $attempt->version,
        idempotencyKey: 'bulk-stale-latest-key',
    );
})->throws(RuntimeException::class, 'changed. Refresh and review before saving.');

test('HTTP endpoint bulk verifies documents for authorized registrar', function (): void {
    [$attempt, $registrar, $set] = getBulkVerifyFixture('bulk-http@example.test');
    $items = $set->items()->take(2)->get();
    authenticateStaffForBulkVerify($registrar);

    $payload = [
        'expected_version' => (int) $attempt->version,
        'requirement_set_id' => (int) $set->id,
        'items' => $items->map(fn ($i) => [
            'requirement_item_id' => (int) $i->id,
            'expected_latest_verification_id' => null,
        ])->all(),
        'idempotency_key' => 'http-bulk-key-'.Str::random(8),
    ];

    $response = test()->post(
        route('registrar.clearance.documents.bulk-verify', $attempt),
        $payload,
    );
    $response->assertRedirect();
    $response->assertSessionHas('status', '2 document requirement(s) verified.');

    $attempt->refresh();
    foreach ($items as $item) {
        expect(AdmissionAttemptDocumentVerification::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('requirement_item_id', $item->id)
            ->where('status', 'verified')
            ->exists())->toBeTrue();
    }
});

test('HTTP accept endpoint accepts application and verifies all documents when verify_documents is true', function (): void {
    [$attempt, $registrar, $set] = getBulkVerifyFixture('accept-and-verify-all@example.test');
    $attempt->status = AdmissionAttempt::STATUS_PENDING;
    $attempt->save();

    authenticateStaffForBulkVerify($registrar);

    $response = test()->post(
        route('admissions.accept', $attempt),
        [
            'expected_version' => (int) $attempt->version,
            'verify_documents' => true,
        ],
    );

    $response->assertRedirect();
    $attempt->refresh();

    expect($attempt->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED);

    $setItems = $set->items()->get();
    foreach ($setItems as $item) {
        expect(AdmissionAttemptDocumentVerification::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('requirement_item_id', $item->id)
            ->where('status', 'verified')
            ->exists())->toBeTrue();
    }
});

test('HTTP accept endpoint without verify_documents leaves documents unverified', function (): void {
    [$attempt, $registrar, $set] = getBulkVerifyFixture('accept-only-normal@example.test');
    $attempt->status = AdmissionAttempt::STATUS_PENDING;
    $attempt->save();

    authenticateStaffForBulkVerify($registrar);

    $response = test()->post(
        route('admissions.accept', $attempt),
        [
            'expected_version' => (int) $attempt->version,
        ],
    );

    $response->assertRedirect();
    $attempt->refresh();

    expect($attempt->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED);
    expect(AdmissionAttemptDocumentVerification::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('status', 'verified')
        ->count())->toBe(0);
});

test('HTTP bulk-accept endpoint accepts applications and verifies all documents when verify_documents is true', function (): void {
    [$attempt, $registrar, $set] = getBulkVerifyFixture('bulk-accept-and-verify-all@example.test');
    $attempt->status = AdmissionAttempt::STATUS_PENDING;
    $attempt->save();

    authenticateStaffForBulkVerify($registrar);

    $response = test()->post(
        route('admissions.bulk-accept'),
        [
            'attempts' => [
                [
                    'id' => (int) $attempt->id,
                    'version' => (int) $attempt->version,
                ],
            ],
            'verify_documents' => true,
        ],
    );

    $response->assertRedirect();
    $attempt->refresh();

    expect($attempt->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED);

    $setItems = $set->items()->get();
    foreach ($setItems as $item) {
        expect(AdmissionAttemptDocumentVerification::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('requirement_item_id', $item->id)
            ->where('status', 'verified')
            ->exists())->toBeTrue();
    }
});
