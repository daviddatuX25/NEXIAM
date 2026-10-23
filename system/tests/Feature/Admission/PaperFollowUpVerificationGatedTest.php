<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\ConfirmAdmissionClearance;
use App\Admission\Actions\InitializeAttemptRequirementSet;
use App\Admission\Actions\OpenProgramFollowUp;
use App\Admission\Actions\RecordPaperFollowUpVerification;
use App\Admission\Actions\ResolveProgramFollowUp;
use App\Admission\Actions\SelectApplicantProgram;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Admission\Actions\VerifyAdmissionAttemptDocument;
use App\Admission\Exceptions\PaperVerificationGatedException;
use App\Admission\Support\AttemptProgramFacts;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptDocumentVerification;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Admission\ProgramFollowUp;
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
use Illuminate\Support\Facades\Config;
use RuntimeException;

function buildPaperFollowUpGatedFixture(string $email): array
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
        ['code' => 'EX-PFU-'.substr(md5($email), 0, 8)],
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
        'checksum' => 'test-checksum-'.substr(md5($email), 0, 6),
        'status' => ResultDocument::STATUS_READY,
        'version' => 1,
        'snapshot_payload_json' => ['score' => 88],
    ]);

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

test('Digital follow-up resolution by Program Head continues to work cleanly via ResolveProgramFollowUp clearing pending follow-ups on the attempt', function (): void {
    [$attempt, $applicant, $guidance, $registrar] = buildPaperFollowUpGatedFixture('pfu-digital@example.test');

    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();
    $bspsy = Program::query()->where('code', OTHER_PROGRAM)->firstOrFail();

    // Guidance opens a follow-up for the attempt
    $followUp = app(OpenProgramFollowUp::class)->handle(
        attempt: $attempt,
        guidance: $guidance,
        programCode: OTHER_PROGRAM,
        reason: 'Department interview referral',
        idempotencyKey: 'fu-open-digital-test',
    );

    expect($followUp->status)->toBe(ProgramFollowUp::STATUS_OPEN)
        ->and(AttemptProgramFacts::hasOpenFollowUp($attempt))->toBeTrue();

    // Resolve digitally by Program Head
    $ph = User::query()->where('email', 'program.head.bsit@nexiam.test')->first()
        ?? User::factory()->create();

    $resolved = app(ResolveProgramFollowUp::class)->handle(
        followUp: $followUp,
        ph: $ph,
        outcome: ProgramFollowUp::STATUS_CLEARED_BY_PH,
        reason: 'Endorsed after department panel interview',
        idempotencyKey: 'fu-resolve-ph-digital-test',
    );

    expect($resolved->status)->toBe(ProgramFollowUp::STATUS_CLEARED_BY_PH)
        ->and($resolved->fresh()->status)->toBe(ProgramFollowUp::STATUS_CLEARED_BY_PH)
        ->and(AttemptProgramFacts::hasOpenFollowUp($attempt))->toBeFalse();
});

test('RecordPaperFollowUpVerification throws PaperVerificationGatedException when attempted without active paper specimen policy', function (): void {
    [$attempt, , $guidance, $registrar] = buildPaperFollowUpGatedFixture('pfu-gated@example.test');

    $followUp = app(OpenProgramFollowUp::class)->handle(
        attempt: $attempt,
        guidance: $guidance,
        programCode: OTHER_PROGRAM,
        reason: 'Department referral pending endorsement',
        idempotencyKey: 'fu-open-gated-test',
    );

    // Explicitly assert policy is inactive
    Config::set('admission.paper_follow_up_specimen_active', false);

    $action = app(RecordPaperFollowUpVerification::class);
    expect($action->isSpecimenPolicyActive())->toBeFalse();

    expect(fn () => $action->handle(
        followUp: $followUp,
        registrar: $registrar,
        paperReference: 'SPECIMEN-SHEET-REF-001',
        idempotencyKey: 'paper-verify-gated-key',
    ))->toThrow(PaperVerificationGatedException::class);

    // Follow-up remains open and ungated in database
    expect($followUp->fresh()->status)->toBe(ProgramFollowUp::STATUS_OPEN);
});

test('Attempting paper verification without registrar capability fails authorization', function (): void {
    [$attempt, , $guidance, $registrar] = buildPaperFollowUpGatedFixture('pfu-authz@example.test');

    $followUp = app(OpenProgramFollowUp::class)->handle(
        attempt: $attempt,
        guidance: $guidance,
        programCode: OTHER_PROGRAM,
        reason: 'Paper verification authorization check',
        idempotencyKey: 'fu-open-authz-test',
    );

    // Create an unprivileged user without registrar capabilities
    $unprivilegedUser = User::factory()->create();

    $authorizer = app(CapabilityAuthorizer::class);

    // Verify unprivileged user lacks document/clearance verification capabilities
    $decision = $authorizer->decide($unprivilegedUser, CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY);
    expect($decision->allowed)->toBeFalse();

    // Invoking authorizeUser throws AuthorizationException
    expect(fn () => CapabilityAuthorizer::authorizeUser($unprivilegedUser, CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY))
        ->toThrow(AuthorizationException::class);

    // An authorized registrar with capability succeeds check
    expect($authorizer->allows($registrar, CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY))->toBeTrue();
});

test('Open follow-ups prevent clearance confirmation until resolved digitally or paper-verified', function (): void {
    [$attempt, $applicant, $guidance, $registrar] = buildPaperFollowUpGatedFixture('pfu-clearance-block@example.test');

    // 1. Acknowledge and select program
    app(SelectApplicantProgram::class)->handle($attempt, $applicant, DEFAULT_PROGRAM, 'pfu-clr-sel');

    // 2. Initialize and verify all mandatory documents
    $attempt->refresh();
    $set = app(InitializeAttemptRequirementSet::class)->handle(
        $attempt,
        $registrar,
        (int) $attempt->version,
        'pfu-clr-init',
    );
    foreach ($set->items()->where('is_mandatory', true)->get() as $item) {
        $attempt->refresh();
        $latestId = AdmissionAttemptDocumentVerification::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('requirement_item_id', $item->id)
            ->latest('id')
            ->value('id');
        app(VerifyAdmissionAttemptDocument::class)->handle(
            attempt: $attempt,
            requirementSetId: (int) $set->id,
            requirementItemId: (int) $item->id,
            expectedLatestVerificationId: $latestId === null ? null : (int) $latestId,
            status: AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
            actor: $registrar,
            expectedVersion: (int) $attempt->version,
            applicantInstruction: null,
            internalRemarks: null,
            idempotencyKey: 'pfu-clr-verify-'.$item->id,
        );
    }

    // 3. Open an academic follow-up
    $followUp = app(OpenProgramFollowUp::class)->handle(
        attempt: $attempt,
        guidance: $guidance,
        programCode: OTHER_PROGRAM,
        reason: 'Academic interview required',
        idempotencyKey: 'fu-clr-block-open',
    );

    expect(AttemptProgramFacts::hasOpenFollowUp($attempt))->toBeTrue();

    // 4. Clearance confirmation is BLOCKED while follow-up is open
    $clearanceAction = app(ConfirmAdmissionClearance::class);
    $attempt->refresh();
    expect(fn () => $clearanceAction->handle(
        $attempt,
        $registrar,
        (int) $attempt->version,
        'pfu-clr-block-key-1',
    ))->toThrow(RuntimeException::class, 'Applicant has open academic follow-up requirements.');

    // 5. Resolve follow-up digitally
    $ph = User::query()->where('email', 'program.head.bsit@nexiam.test')->first()
        ?? User::factory()->create();

    app(ResolveProgramFollowUp::class)->handle(
        followUp: $followUp,
        ph: $ph,
        outcome: ProgramFollowUp::STATUS_CLEARED_BY_PH,
        reason: 'Applicant cleared by Program Head',
        idempotencyKey: 'fu-clr-resolve-ph-key',
    );

    expect(AttemptProgramFacts::hasOpenFollowUp($attempt))->toBeFalse();

    // 6. Clearance confirmation now succeeds cleanly
    $attempt->refresh();
    $receipt = $clearanceAction->handle($attempt, $registrar, (int) $attempt->version, 'pfu-clr-success-key');
    expect($receipt)->toBeInstanceOf(AdmissionClearanceReceipt::class)
        ->and($receipt->status)->toBe(AdmissionClearanceReceipt::STATUS_CONFIRMED)
        ->and($receipt->admission_attempt_id)->toBe($attempt->id);
});
