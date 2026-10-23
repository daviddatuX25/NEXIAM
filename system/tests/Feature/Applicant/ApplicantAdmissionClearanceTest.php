<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\InitializeAttemptRequirementSet;
use App\Admission\Actions\VerifyAdmissionAttemptDocument;
use App\Admission\Queries\AttemptRequirementChecklistQuery;
use App\Applicant\Support\ApplicantClearanceProjection;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptDocumentVerification;
use App\Models\Admission\AdmissionAttemptRequirementSet;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Admission\AdmissionDocumentCorrectionRequest;
use App\Models\Admission\AdmissionDocumentCorrectionRequestItem;
use App\Models\Admission\EnrollmentSheetHandoffReceipt;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

function createApplicantClearanceFixture(string $email): array
{
    seedE2Foundation();
    $submission = submitSyntheticApplicant($email, 'Clearance '.substr(hash('sha256', $email), 0, 12));
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $submission['access_token'],
        '2001-01-01',
    );

    return [$submission['attempt'], $applicant];
}

function createClearanceReceiptForTest(AdmissionAttempt $attempt, User $actor, string $reference, string $status, array $extra = []): AdmissionClearanceReceipt
{
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();

    return AdmissionClearanceReceipt::query()->create(array_merge([
        'admission_attempt_id' => $attempt->id,
        'program_id' => $program->id,
        'receipt_number' => $reference,
        'status' => $status,
        'evidence_vector' => ['private' => 'must_not_be_projected'],
        'confirmed_by_user_id' => $actor->id,
        'confirmed_at' => now()->subDays(3),
        'idempotency_key' => 'clearance-test-'.$reference,
    ], $extra));
}

test('latest revocation remains current when a retained handoff points to an earlier confirmation', function (): void {
    [$attempt, $applicant] = createApplicantClearanceFixture('clearance-revoked@example.test');
    $registrar = User::factory()->create();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $confirmed = createClearanceReceiptForTest(
        $attempt,
        $registrar,
        'CLR-HISTORICAL',
        AdmissionClearanceReceipt::STATUS_CONFIRMED,
    );
    $issuedAt = Carbon::parse('2026-03-04 09:15:00');
    EnrollmentSheetHandoffReceipt::query()->create([
        'admission_attempt_id' => $attempt->id,
        'admission_clearance_receipt_id' => $confirmed->id,
        'program_id' => $program->id,
        'receipt_number' => 'ESH-HISTORICAL',
        'issued_by_user_id' => $registrar->id,
        'issued_at' => $issuedAt,
        'paper_reference' => 'private-paper-ref',
        'idempotency_key' => 'sheet-historical',
    ]);
    $revoked = createClearanceReceiptForTest(
        $attempt,
        $registrar,
        'CLR-REVOKED',
        AdmissionClearanceReceipt::STATUS_REVOKED,
        [
            'predecessor_receipt_id' => $confirmed->id,
            'revoked_by_user_id' => $registrar->id,
            'revoked_at' => Carbon::parse('2026-04-05 10:20:00'),
            'revocation_reason' => 'private internal reason',
            'evidence_vector' => ['secret' => true],
        ],
    );

    $projection = app(ApplicantClearanceProjection::class)->for($attempt, $applicant);

    expect($projection['status'])->toBe('revoked')
        ->and($projection['latest_receipt'])->toMatchArray([
            'receipt_number' => 'CLR-REVOKED',
            'status' => 'revoked',
            'confirmed_at' => $revoked->confirmed_at->toIso8601String(),
            'revoked_at' => Carbon::parse('2026-04-05 10:20:00')->toIso8601String(),
        ])
        ->and($projection['historical_sheet_issue'])->toMatchArray([
            'receipt_number' => 'ESH-HISTORICAL',
            'issued_at' => $issuedAt->toIso8601String(),
            'belongs_to_latest_clearance' => false,
        ])
        ->and($projection['latest_receipt'])->not->toHaveKey('revocation_reason')
        ->and($projection['latest_receipt'])->not->toHaveKey('evidence_vector')
        ->and($projection['historical_sheet_issue'])->not->toHaveKey('paper_reference');
});

test('applicant checklist projection is allowlisted and rejects another applicants attempt', function (): void {
    [$attempt, $applicant] = createApplicantClearanceFixture('clearance-owner@example.test');
    [$foreignAttempt, $foreignApplicant] = createApplicantClearanceFixture('clearance-foreign@example.test');
    $attempt->update(['status' => AdmissionAttempt::STATUS_ACCEPTED]);
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    app(InitializeAttemptRequirementSet::class)->handle(
        $attempt->fresh(),
        $registrar,
        (int) $attempt->fresh()->version,
        'clearance-allowlist-init',
    );
    $projection = app(ApplicantClearanceProjection::class);

    $own = $projection->for($attempt->fresh(), $applicant);

    expect(array_keys($own['requirements']))->toBe([
        'set', 'items', 'all_mandatory_verified', 'mandatory_pending_count', 'status',
    ])->and($own['requirements']['items'])->not->toBeEmpty();
    foreach ($own['requirements']['items'] as $item) {
        expect(array_keys($item))->toBe([
            'id', 'code', 'label', 'item_kind', 'is_mandatory', 'latest_status', 'latest_verified_at', 'applicant_instruction',
        ]);
    }

    expect(fn () => $projection->for($foreignAttempt, $applicant))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => $projection->for($attempt, $foreignApplicant))
        ->toThrow(ModelNotFoundException::class);
});

test('sheet receipt remains explicitly historical and uses issued_at rather than record timestamps', function (): void {
    [$attempt, $applicant] = createApplicantClearanceFixture('clearance-sheet-only@example.test');
    $registrar = User::factory()->create();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $receipt = createClearanceReceiptForTest(
        $attempt,
        $registrar,
        'CLR-SHEET-ONLY',
        AdmissionClearanceReceipt::STATUS_CONFIRMED,
    );
    $issuedAt = Carbon::parse('2026-05-07 08:45:00');
    $sheet = EnrollmentSheetHandoffReceipt::query()->create([
        'admission_attempt_id' => $attempt->id,
        'admission_clearance_receipt_id' => $receipt->id,
        'program_id' => $program->id,
        'receipt_number' => 'ESH-ONLY',
        'issued_by_user_id' => $registrar->id,
        'issued_at' => $issuedAt,
        'paper_reference' => 'private-paper-ref',
        'idempotency_key' => 'sheet-only',
    ]);

    $result = app(ApplicantClearanceProjection::class)->for($attempt, $applicant);

    expect($result['status'])->toBe('confirmed')
        ->and($result['latest_receipt']['receipt_number'])->toBe('CLR-SHEET-ONLY')
        ->and($result['historical_sheet_issue'])->toMatchArray([
            'receipt_number' => 'ESH-ONLY',
            'issued_at' => $issuedAt->toIso8601String(),
            'belongs_to_latest_clearance' => true,
        ])
        ->and($result['historical_sheet_issue']['issued_at'])->not->toBe($sheet->created_at?->toIso8601String());
});
test('clearance projection exposes current item instructions without private request notes', function (): void {
    [$attempt, $applicant] = createApplicantClearanceFixture('clearance-correction-projection@example.test');
    $attempt->update(['status' => AdmissionAttempt::STATUS_ACCEPTED]);
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    app(InitializeAttemptRequirementSet::class)->handle(
        $attempt->fresh(),
        $registrar,
        (int) $attempt->fresh()->version,
        'clearance-correction-init',
    );
    $item = app(AttemptRequirementChecklistQuery::class)->forApplicant($attempt->fresh(), $applicant)['items'][0];
    $set = AdmissionAttemptRequirementSet::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('is_current', true)
        ->firstOrFail();
    $verification = app(VerifyAdmissionAttemptDocument::class)->handle(
        attempt: $attempt->fresh(),
        requirementSetId: (int) $set->id,
        requirementItemId: (int) $item['id'],
        expectedLatestVerificationId: null,
        status: AdmissionAttemptDocumentVerification::STATUS_DEFICIENT,
        actor: $registrar,
        expectedVersion: (int) $attempt->fresh()->version,
        applicantInstruction: 'Upload a readable transcript.',
        internalRemarks: 'private inspection note',
        idempotencyKey: 'clearance-correction-verification',
    );
    $request = AdmissionDocumentCorrectionRequest::query()->create([
        'admission_attempt_id' => $attempt->id,
        'requirement_set_id' => $set->id,
        'institution_id' => $attempt->institution_id,
        'academic_cycle_id' => $attempt->academic_cycle_id,
        'requested_by_user_id' => $registrar->id,
        'expected_version' => (int) $attempt->fresh()->version,
        'recorded_version' => (int) $attempt->fresh()->version,
        'idempotency_key' => 'clearance-safe-correction',
        'overall_note' => 'private registrar note must not reach the applicant',
        'recorded_at' => now(),
    ]);
    AdmissionDocumentCorrectionRequestItem::query()->create([
        'document_correction_request_id' => $request->id,
        'requirement_item_id' => $item['id'],
        'expected_latest_verification_id' => $verification->id,
        'label' => $item['label'],
        'observed_status' => $verification->status,
        'applicant_instruction' => 'Upload a readable transcript.',
    ]);

    $projection = app(ApplicantClearanceProjection::class)->for($attempt, $applicant);

    expect($projection['correction_requests'])->toHaveCount(1)
        ->and($projection['correction_requests'][0]['items'][0])->toBe([
            'label' => $item['label'],
            'applicant_instruction' => 'Upload a readable transcript.',
        ])
        ->and(json_encode($projection))->not->toContain('private registrar note')
        ->and(json_encode($projection))->not->toContain('private inspection note');
});
