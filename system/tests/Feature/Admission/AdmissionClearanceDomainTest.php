<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\ConfirmAdmissionClearance;
use App\Admission\Actions\InitializeAttemptRequirementSet;
use App\Admission\Actions\RevokeAdmissionClearance;
use App\Admission\Actions\SelectApplicantProgram;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Admission\Actions\VerifyAdmissionAttemptDocument;
use App\Admission\Exceptions\AttemptClearanceConfirmedException;
use App\Admission\Support\QuotaReservation;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptDocumentVerification;
use App\Models\Admission\AdmissionAttemptRequirementSet;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Admission\EnrollmentSheetHandoffReceipt;
use App\Models\Admission\ProgramSelection;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

function buildDomainClearanceFixture(string $email): array
{
    seedE2Foundation();
    $result = submitSyntheticApplicant($email);
    $attempt = $result['attempt'];
    $attempt->status = AdmissionAttempt::STATUS_ACCEPTED;
    $attempt->save();

    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $result['access_token'],
        '2001-01-01',
    );

    $registrar = User::query()->where('email', 'registrar.lead@nexiam.test')->first()
        ?? User::query()->where('email', 'like', '%registrar%')->first()
        ?? User::query()->first();

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->first()
        ?? User::query()->first();

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    foreach ([DEFAULT_PROGRAM, OTHER_PROGRAM] as $code) {
        $p = Program::query()->where('code', $code)->firstOrFail();
        ProgramQuota::query()->updateOrCreate(
            ['program_id' => $p->id, 'academic_cycle_id' => $cycle->id],
            ['slots' => 10],
        );
    }

    $room = Room::query()->firstOrFail();
    $session = ExamSession::query()->firstOrCreate(
        ['code' => 'EX-CLR-'.substr(md5($email), 0, 8)],
        [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'starts_at' => now()->subHours(3),
            'effective_end_at' => now()->subHours(1),
            'status' => 'closed',
        ]
    );

    ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
    ]);

    GradingSession::query()->firstOrCreate(
        ['exam_session_id' => $session->id],
        [
            'status' => GradingSession::STATUS_FINALIZED,
            'opened_at' => now()->subHours(2),
            'finalized_at' => now()->subHour(),
        ]
    );

    $resultDoc = ResultDocument::query()->create([
        'admission_attempt_id' => $attempt->id,
        'template_version' => 1,
        'checksum' => 'test-checksum-001',
        'status' => ResultDocument::STATUS_READY,
        'version' => 1,
        'snapshot_payload_json' => ['score' => 85],
    ]);
    $attempt->forceFill(['current_result_document_id' => $resultDoc->id])->save();

    ResultRelease::query()->create([
        'admission_attempt_id' => $attempt->id,
        'result_document_id' => $resultDoc->id,
        'released_by_user_id' => $guidance->id,
        'release_version' => 1,
        'is_current' => true,
        'released_at' => now(),
        'mode' => 'f2f',
        'channel' => 'physical',
        'idempotency_key' => 'rel-'.substr(md5($email), 0, 8),
    ]);
    ConsultationDecision::query()->create([
        'admission_attempt_id' => $attempt->id,
        'decision_type' => 'approve',
        'counselor_user_id' => $guidance->id,
        'decided_at' => now(),
        'idempotency_key' => 'cons-dec-'.substr(md5($email), 0, 8),
    ]);
    app(SetGuidanceProgramPool::class)->handle(
        attempt: $attempt,
        counselor: $guidance,
        defaultProgramCode: DEFAULT_PROGRAM,
        options: [
            ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'qualifies'],
            ['program_code' => OTHER_PROGRAM, 'display_order' => 2, 'basis' => 'qualifies'],
        ],
        reason: 'Qualified for both programs',
        idempotencyKey: 'pool-'.substr(md5($email), 0, 8),
    );

    return [$attempt, $applicant, $guidance, $registrar];
}
function initializeClearanceDocuments(AdmissionAttempt $attempt, User $registrar): AdmissionAttemptRequirementSet
{
    $attempt->refresh();

    return app(InitializeAttemptRequirementSet::class)->handle(
        $attempt,
        $registrar,
        (int) $attempt->version,
        'clearance-init-'.$attempt->id,
    );
}

function verifyClearanceDocument(
    AdmissionAttempt $attempt,
    AdmissionAttemptRequirementSet $set,
    int $itemId,
    string $status,
    User $registrar,
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
        idempotencyKey: 'clearance-verify-'.$attempt->id.'-'.$itemId.'-'.($latestId ?? 'initial').'-'.$status,
    );
}

function confirmDomainClearance(AdmissionAttempt $attempt, User $registrar, string $idempotencyKey): AdmissionClearanceReceipt
{
    $attempt->refresh();

    return app(ConfirmAdmissionClearance::class)->handle(
        $attempt,
        $registrar,
        (int) $attempt->version,
        $idempotencyKey,
    );
}

function reopenDomainClearance(AdmissionAttempt $attempt, User $registrar, string $reason, string $idempotencyKey): AdmissionClearanceReceipt
{
    $attempt->refresh();

    return app(RevokeAdmissionClearance::class)->handle(
        $attempt,
        $registrar,
        (int) $attempt->version,
        $reason,
        $idempotencyKey,
    );
}

test('ConfirmAdmissionClearance succeeds when all prerequisites pass', function (): void {
    [$attempt, $applicant, $guidance, $registrar] = buildDomainClearanceFixture('clr-happy@example.test');
    // 1. Acknowledge/select program so there's an active program choice
    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();
    app(SelectApplicantProgram::class)->handle($attempt, $applicant, DEFAULT_PROGRAM, 'clr-happy-sel');

    // 2. Initialize and verify all mandatory documents
    $set = initializeClearanceDocuments($attempt, $registrar);
    foreach ($set->items()->where('is_mandatory', true)->get() as $item) {
        verifyClearanceDocument(
            $attempt,
            $set,
            (int) $item->id,
            AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
            $registrar,
        );
    }

    $receipt = confirmDomainClearance($attempt, $registrar, 'clr-happy-confirm-1');
    expect($receipt)->toBeInstanceOf(AdmissionClearanceReceipt::class)
        ->and($receipt->status)->toBe(AdmissionClearanceReceipt::STATUS_CONFIRMED)
        ->and($receipt->admission_attempt_id)->toBe($attempt->id)
        ->and($receipt->confirmed_by_user_id)->toBe($registrar->id)
        ->and($receipt->program_id)->toBe($bsn->id)
        ->and($receipt->evidence_vector)->toBeArray()
        ->and($receipt->receipt_number)->toStartWith('CLR-');

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'admission.clearance.confirmed',
    ]);
});

test('a document writer carrying the pre-confirmation version cannot append after confirmation commits', function (): void {
    [$attempt, $applicant, $guidance, $registrar] = buildDomainClearanceFixture('clr-confirm-writer-race@example.test');
    app(SelectApplicantProgram::class)->handle($attempt, $applicant, DEFAULT_PROGRAM, 'clr-confirm-writer-race-select');

    $set = initializeClearanceDocuments($attempt, $registrar);
    $items = $set->items()->where('is_mandatory', true)->get();
    foreach ($items as $item) {
        verifyClearanceDocument(
            $attempt,
            $set,
            (int) $item->id,
            AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
            $registrar,
        );
    }

    $attemptVersionBeforeConfirm = (int) $attempt->fresh()->version;
    $item = $items->firstOrFail();
    $latestId = AdmissionAttemptDocumentVerification::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('requirement_item_id', $item->id)
        ->latest('id')
        ->value('id');
    confirmDomainClearance($attempt, $registrar, 'clr-confirm-writer-race-confirm');

    expect(fn () => app(VerifyAdmissionAttemptDocument::class)->handle(
        attempt: $attempt->fresh(),
        requirementSetId: (int) $set->id,
        requirementItemId: (int) $item->id,
        expectedLatestVerificationId: (int) $latestId,
        status: AdmissionAttemptDocumentVerification::STATUS_MISSING,
        actor: $registrar,
        expectedVersion: $attemptVersionBeforeConfirm,
        applicantInstruction: null,
        internalRemarks: 'This stale concurrent edit must not commit.',
        idempotencyKey: 'clr-confirm-writer-race-late-write',
    ))->toThrow(RuntimeException::class);

    expect(AdmissionAttemptDocumentVerification::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('requirement_item_id', $item->id)
        ->count())->toBe($items->count() > 0 ? 1 : 0);
});

test('ConfirmAdmissionClearance is rejected when documents are missing or deficient', function (): void {
    [$attempt, $applicant, $guidance, $registrar] = buildDomainClearanceFixture('clr-doc-fail@example.test');

    app(SelectApplicantProgram::class)->handle($attempt, $applicant, DEFAULT_PROGRAM, 'clr-doc-fail-sel');

    // Initialize requirements but don't verify all of them (or mark as deficient)
    $set = initializeClearanceDocuments($attempt, $registrar);
    $mandatoryItems = $set->items()->where('is_mandatory', true)->get();
    expect($mandatoryItems->count())->toBeGreaterThan(0);

    verifyClearanceDocument(
        $attempt,
        $set,
        (int) $mandatoryItems->first()->id,
        AdmissionAttemptDocumentVerification::STATUS_DEFICIENT,
        $registrar,
        applicantInstruction: 'Please bring the complete document for inspection.',
        internalRemarks: 'Document is incomplete',
    );

    expect(fn () => confirmDomainClearance($attempt, $registrar, 'clr-doc-fail-key'))
        ->toThrow(RuntimeException::class);
});

test('RevokeAdmissionClearance marks clearance revoked with reason and re-allows edits without retaining former program quota', function (): void {
    [$attempt, $applicant, $guidance, $registrar] = buildDomainClearanceFixture('clr-revoke@example.test');

    $defaultProgramId = Program::query()->where('code', DEFAULT_PROGRAM)->value('id');
    $otherProgramId = Program::query()->where('code', OTHER_PROGRAM)->value('id');
    $cycleId = (int) $attempt->academic_cycle_id;
    $committedForProgram = fn (int $programId): int => QuotaReservation::committedForCycle(
        $cycleId,
        $programId,
        practiceSetId: $attempt->practice_fixture_set_id,
    );

    app(SelectApplicantProgram::class)->handle($attempt, $applicant, DEFAULT_PROGRAM, 'clr-revoke-sel');
    expect($committedForProgram($defaultProgramId))->toBe(1)
        ->and($committedForProgram($otherProgramId))->toBe(0);

    $set = initializeClearanceDocuments($attempt, $registrar);
    foreach ($set->items()->where('is_mandatory', true)->get() as $item) {
        verifyClearanceDocument(
            $attempt,
            $set,
            (int) $item->id,
            AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
            $registrar,
        );
    }

    $receipt = confirmDomainClearance($attempt, $registrar, 'clr-revoke-confirm');

    expect($receipt->status)->toBe(AdmissionClearanceReceipt::STATUS_CONFIRMED)
        ->and($committedForProgram($defaultProgramId))->toBe(1)
        ->and($committedForProgram($otherProgramId))->toBe(0);

    // Seed a historical receipt directly; issuing a new sheet is no longer a managed operation.
    $sheetReceipt = EnrollmentSheetHandoffReceipt::query()->create([
        'admission_attempt_id' => $attempt->id,
        'admission_clearance_receipt_id' => $receipt->id,
        'program_id' => $receipt->program_id,
        'receipt_number' => 'ESH-HISTORY-CLR-REVOKE',
        'issued_by_user_id' => $registrar->id,
        'issued_at' => now(),
        'paper_reference' => 'SHEET-CLR-REVOKE-001',
        'idempotency_key' => 'legacy-sheet-history-clr-revoke',
    ]);
    expect(fn () => app(SelectApplicantProgram::class)->handle($attempt, $applicant, OTHER_PROGRAM, 'clr-revoke-sel-2'))
        ->toThrow(AttemptClearanceConfirmedException::class);

    $revocationReason = 'Applicant submitted forged certificate requiring re-verification';
    $registrarClerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    expect(fn () => reopenDomainClearance($attempt, $registrarClerk, 'Unauthorized reopen attempt', 'clr-revoke-clerk-denied'))
        ->toThrow(AuthorizationException::class);
    expect($receipt->fresh()->status)->toBe(AdmissionClearanceReceipt::STATUS_CONFIRMED)
        ->and($committedForProgram($defaultProgramId))->toBe(1)
        ->and($committedForProgram($otherProgramId))->toBe(0);

    $reopeningRegistrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $revokedReceipt = reopenDomainClearance($attempt, $reopeningRegistrar, $revocationReason, 'clr-revoke-reopen');

    expect($revokedReceipt->id)->not->toBe($receipt->id)
        ->and($revokedReceipt->status)->toBe(AdmissionClearanceReceipt::STATUS_REVOKED)
        ->and($revokedReceipt->predecessor_receipt_id)->toBe($receipt->id)
        ->and($revokedReceipt->revoked_by_user_id)->toBe($reopeningRegistrar->id)
        ->and($revokedReceipt->revocation_reason)->toBe($revocationReason)
        ->and($revokedReceipt->revoked_at)->not->toBeNull()
        ->and($receipt->fresh()->status)->toBe(AdmissionClearanceReceipt::STATUS_CONFIRMED)
        ->and($sheetReceipt->fresh()->admission_clearance_receipt_id)->toBe($receipt->id)
        ->and($committedForProgram($defaultProgramId))->toBe(1)
        ->and($committedForProgram($otherProgramId))->toBe(0);

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'admission.clearance.revoked',
    ]);

    // Now program selection and document verification are unblocked; the
    // prior confirmed receipt remains history and contributes to neither A
    // nor B after the current reservation transfers to B.
    $selection = app(SelectApplicantProgram::class)->handle($attempt, $applicant, OTHER_PROGRAM, 'clr-revoke-sel-2');
    expect($selection->program->code)->toBe(OTHER_PROGRAM)
        ->and($committedForProgram($defaultProgramId))->toBe(0)
        ->and($committedForProgram($otherProgramId))->toBe(1);
});

test('SelectApplicantProgram is blocked when clearance is confirmed, and permitted again after revocation', function (): void {
    [$attempt, $applicant, $guidance, $registrar] = buildDomainClearanceFixture('clr-sel-guard@example.test');

    app(SelectApplicantProgram::class)->handle($attempt, $applicant, DEFAULT_PROGRAM, 'clr-sel-guard-1');

    $set = initializeClearanceDocuments($attempt, $registrar);
    foreach ($set->items()->where('is_mandatory', true)->get() as $item) {
        verifyClearanceDocument(
            $attempt,
            $set,
            (int) $item->id,
            AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
            $registrar,
        );
    }
    confirmDomainClearance($attempt, $registrar, 'clr-sel-guard-confirm');

    expect(fn () => app(SelectApplicantProgram::class)->handle($attempt, $applicant, OTHER_PROGRAM, 'clr-sel-guard-blocked'))
        ->toThrow(AttemptClearanceConfirmedException::class);
    $reopeningRegistrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    reopenDomainClearance($attempt, $reopeningRegistrar, 'Program choice adjustment authorized', 'clr-sel-guard-reopen');

    // Mutating program selection is allowed again
    $newSelection = app(SelectApplicantProgram::class)->handle($attempt, $applicant, OTHER_PROGRAM, 'clr-sel-guard-unblocked');
    expect($newSelection->program->code)->toBe(OTHER_PROGRAM);
});

test('confirmation is idempotent', function (): void {
    [$attempt, $applicant, $guidance, $registrar] = buildDomainClearanceFixture('clr-idempotency@example.test');

    app(SelectApplicantProgram::class)->handle($attempt, $applicant, DEFAULT_PROGRAM, 'clr-idem-sel');

    $set = initializeClearanceDocuments($attempt, $registrar);
    foreach ($set->items()->where('is_mandatory', true)->get() as $item) {
        verifyClearanceDocument(
            $attempt,
            $set,
            (int) $item->id,
            AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
            $registrar,
        );
    }

    $confirmAction = app(ConfirmAdmissionClearance::class);
    $confirmVersion = (int) $attempt->fresh()->version;
    $c1 = $confirmAction->handle($attempt, $registrar, $confirmVersion, 'clr-idem-confirm-key');
    $c2 = $confirmAction->handle($attempt->fresh(), $registrar, $confirmVersion, 'clr-idem-confirm-key');

    expect($c2->id)->toBe($c1->id)
        ->and($c2->receipt_number)->toBe($c1->receipt_number);

    expect(AdmissionClearanceReceipt::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('status', AdmissionClearanceReceipt::STATUS_CONFIRMED)
        ->count())->toBe(1);

});

test('supplemental program acknowledgement is not required to confirm otherwise-ready admission clearance', function (): void {
    [$attempt, $applicant, $guidance, $registrar] = buildDomainClearanceFixture('clr-optional-ack@example.test');
    $selection = ProgramSelection::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('status', ProgramSelection::STATUS_PENDING_ACKNOWLEDGEMENT)
        ->firstOrFail();
    $set = initializeClearanceDocuments($attempt, $registrar);
    foreach ($set->items()->where('is_mandatory', true)->get() as $item) {
        verifyClearanceDocument(
            $attempt,
            $set,
            (int) $item->id,
            AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
            $registrar,
        );
    }

    $receipt = confirmDomainClearance($attempt, $registrar, 'clearance-without-supplemental-ack');

    expect($receipt->status)->toBe(AdmissionClearanceReceipt::STATUS_CONFIRMED)
        ->and($receipt->program_id)->toBe($selection->program_id)
        ->and($selection->fresh()->status)->toBe(ProgramSelection::STATUS_PENDING_ACKNOWLEDGEMENT)
        ->and($selection->fresh()->applicant_acknowledged_at)->toBeNull()
        ->and(QuotaReservation::committedForCycle(
            (int) $attempt->academic_cycle_id,
            (int) $selection->program_id,
            practiceSetId: $attempt->practice_fixture_set_id,
        ))->toBe(1);
});
