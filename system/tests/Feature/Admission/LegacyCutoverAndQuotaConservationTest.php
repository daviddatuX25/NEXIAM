<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\ConfirmAdmissionClearance;
use App\Admission\Actions\InitializeAttemptRequirementSet;
use App\Admission\Actions\SelectApplicantProgram;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Admission\Actions\VerifyAdmissionAttemptDocument;
use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\QuotaReservation;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptDocumentVerification;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Admission\EnrollmentStamp;
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

function buildQuotaConservationFixture(string $email): array
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
        ['code' => 'EX-CNS-'.substr(md5($email), 0, 8)],
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
        'checksum' => 'test-checksum-cns-'.substr(md5($email), 0, 6),
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
        'idempotency_key' => 'rel-cns-'.substr(md5($email), 0, 8),
    ]);

    ConsultationDecision::query()->create([
        'admission_attempt_id' => $attempt->id,
        'decision_type' => 'approve',
        'counselor_user_id' => $guidance->id,
        'decided_at' => now(),
        'idempotency_key' => 'cons-dec-cns-'.substr(md5($email), 0, 8),
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
        idempotencyKey: 'pool-cns-'.substr(md5($email), 0, 8),
    );

    return [$attempt, $applicant, $guidance, $registrar];
}

test('quota counting deduplicates across selection, confirmed clearance, and legacy stamp (single commitment)', function (): void {
    [$attempt, $applicant, , $registrar] = buildQuotaConservationFixture('cns-dedupe@example.test');
    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();

    // With guidance pool set, applicant has 1 commitment for default program
    $initialCommitted = QuotaReservation::committedAttemptCount($attempt, $bsn->id);
    expect($initialCommitted)->toBeGreaterThanOrEqual(1);

    // 1. Applicant explicitly selects program -> still 1 (deduplicated against existing reservation)
    app(SelectApplicantProgram::class)->handle($attempt, $applicant, DEFAULT_PROGRAM, 'cns-sel-1');
    expect(QuotaReservation::committedAttemptCount($attempt, $bsn->id))->toBe($initialCommitted);
    // 2. Add an active AdmissionClearanceReceipt for the same attempt and program
    $receipt = AdmissionClearanceReceipt::query()->create([
        'admission_attempt_id' => $attempt->id,
        'program_id' => $bsn->id,
        'receipt_number' => 'CLR-TEST-'.uniqid(),
        'status' => AdmissionClearanceReceipt::STATUS_CONFIRMED,
        'evidence_vector' => ['ready' => true],
        'confirmed_by_user_id' => $registrar->id,
        'confirmed_at' => now(),
        'idempotency_key' => 'clr-dedupe-key-1',
    ]);
    expect($receipt)->not->toBeNull();

    // Still exactly identical commitment count: deduplicated by admission_attempt_id
    expect(QuotaReservation::committedAttemptCount($attempt, $bsn->id))->toBe($initialCommitted);

    // 3. Add an un-reversed legacy EnrollmentStamp for the same attempt and program
    $stamp = EnrollmentStamp::query()->create([
        'admission_attempt_id' => $attempt->id,
        'stamp_type' => EnrollmentStamp::STAMP_ENROLLING,
        'provisional_program_id' => $bsn->id,
        'stamped_by_user_id' => $registrar->id,
        'stamped_at' => now(),
        'reason' => 'Legacy historical stamp',
        'idempotency_key' => 'stamp-dedupe-key-1',
    ]);
    expect($stamp)->not->toBeNull();

    // Still exactly identical commitment count across selection + clearance + legacy stamp
    expect(QuotaReservation::committedAttemptCount($attempt, $bsn->id))->toBe($initialCommitted);
});

test('historical legacy stamps remain intact and queryable as history', function (): void {
    [$attempt, , , $registrar] = buildQuotaConservationFixture('cns-legacy-hist@example.test');
    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();

    // Create a chain of legacy stamps: enrolling -> enrolled -> reversed
    $enrolling = EnrollmentStamp::query()->create([
        'admission_attempt_id' => $attempt->id,
        'stamp_type' => EnrollmentStamp::STAMP_ENROLLING,
        'provisional_program_id' => $bsn->id,
        'stamped_by_user_id' => $registrar->id,
        'stamped_at' => now()->subMinutes(10),
        'reason' => 'Historical enrolling',
        'idempotency_key' => 'hist-enrolling-key',
    ]);

    $enrolled = EnrollmentStamp::query()->create([
        'admission_attempt_id' => $attempt->id,
        'stamp_type' => EnrollmentStamp::STAMP_ENROLLED,
        'final_program_id' => $bsn->id,
        'stamped_by_user_id' => $registrar->id,
        'stamped_at' => now()->subMinutes(5),
        'reason' => 'Historical enrolled',
        'idempotency_key' => 'hist-enrolled-key',
    ]);

    $reversed = EnrollmentStamp::query()->create([
        'admission_attempt_id' => $attempt->id,
        'stamp_type' => EnrollmentStamp::STAMP_REVERSE_ENROLLED,
        'stamped_by_user_id' => $registrar->id,
        'stamped_at' => now()->subMinutes(1),
        'reason' => 'Historical reversal',
        'idempotency_key' => 'hist-reversed-key',
    ]);

    // All rows are immutable and retained in database history
    $allStamps = EnrollmentStamp::query()
        ->where('admission_attempt_id', $attempt->id)
        ->orderBy('id')
        ->get();

    expect($allStamps)->toHaveCount(3)
        ->and($allStamps->pluck('id')->all())->toBe([$enrolling->id, $enrolled->id, $reversed->id])
        ->and($allStamps->pluck('stamp_type')->all())->toBe([
            EnrollmentStamp::STAMP_ENROLLING,
            EnrollmentStamp::STAMP_ENROLLED,
            EnrollmentStamp::STAMP_REVERSE_ENROLLED,
        ]);

    // Reversal row correctly indicates no active stamp currently
    expect(AttemptProgramFacts::activeStamp($attempt))->toBeNull()
        ->and(AttemptProgramFacts::isEnrolled($attempt))->toBeFalse()
        ->and(AttemptProgramFacts::isEnrolling($attempt))->toBeFalse();
});

test('an attempt with confirmed clearance is recognized by AttemptProgramFacts::isClearanceConfirmed', function (): void {
    [$attempt, $applicant, , $registrar] = buildQuotaConservationFixture('cns-clearance-facts@example.test');
    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();

    expect(AttemptProgramFacts::isClearanceConfirmed($attempt))->toBeFalse();

    // Create confirmed clearance receipt
    AdmissionClearanceReceipt::query()->create([
        'admission_attempt_id' => $attempt->id,
        'program_id' => $bsn->id,
        'receipt_number' => 'CLR-FACTS-'.uniqid(),
        'status' => AdmissionClearanceReceipt::STATUS_CONFIRMED,
        'evidence_vector' => ['ready' => true],
        'confirmed_by_user_id' => $registrar->id,
        'confirmed_at' => now(),
        'idempotency_key' => 'clr-facts-key-1',
    ]);

    expect(AttemptProgramFacts::isClearanceConfirmed($attempt))->toBeTrue();
});

test('quota is properly reserved when clearance is confirmed', function (): void {
    [$attempt, $applicant, , $registrar] = buildQuotaConservationFixture('cns-reserved@example.test');
    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();

    // Configure tight quota slots for this cycle
    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $bsn->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
        ['slots' => 1],
    );

    // 1. Select program and verify mandatory documents
    app(SelectApplicantProgram::class)->handle($attempt, $applicant, DEFAULT_PROGRAM, 'cns-res-sel');
    $attempt->refresh();
    $set = app(InitializeAttemptRequirementSet::class)->handle(
        $attempt,
        $registrar,
        (int) $attempt->version,
        'cns-res-init',
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
            idempotencyKey: 'cns-res-verify-'.$item->id,
        );
    }

    // 2. Confirm admission clearance
    $attempt->refresh();
    $receipt = app(ConfirmAdmissionClearance::class)->handle(
        $attempt,
        $registrar,
        (int) $attempt->version,
        'cns-res-confirm-1',
    );
    expect($receipt->status)->toBe(AdmissionClearanceReceipt::STATUS_CONFIRMED);

    // 3. Confirm that quota commitment is held
    $committed = QuotaReservation::committedAttemptCount($attempt, $bsn->id);
    expect($committed)->toBe(1);

    $snapshot = QuotaReservation::varianceSnapshot($attempt, $bsn->id);
    expect($snapshot['committed'])->toBe(1)
        ->and($snapshot['capacity'])->toBe(1)
        ->and($snapshot['remaining'])->toBe(0);

    // A second applicant sees a non-blocking advisory overflow for this program
    $result2 = submitSyntheticApplicant('cns-second@example.test', 'ApplicantSecond');
    $attempt2 = $result2['attempt'];
    $attempt2->status = AdmissionAttempt::STATUS_ACCEPTED;
    $attempt2->save();

    $capacity = QuotaReservation::assertCapacityFor($attempt2, $bsn->id);

    expect($capacity)->toMatchArray([
        'configured' => true,
        'is_overflow' => true,
    ])->and($capacity['over_capacity'])->toBeGreaterThan(0);
});

test('legacy enrolled stamp releases provisional program and counts only against final program', function (): void {
    [$attempt, , , $registrar] = buildQuotaConservationFixture('cns-transfer-release@example.test');
    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();
    $bspsy = Program::query()->where('code', OTHER_PROGRAM)->firstOrFail();

    $initialBsn = QuotaReservation::committedAttemptCount($attempt, $bsn->id);
    $initialBspsy = QuotaReservation::committedAttemptCount($attempt, $bspsy->id);

    // Stamped as enrolled in BSPSY (with BSN as historical provisional program)
    EnrollmentStamp::query()->create([
        'admission_attempt_id' => $attempt->id,
        'stamp_type' => EnrollmentStamp::STAMP_ENROLLED,
        'provisional_program_id' => $bsn->id,
        'final_program_id' => $bspsy->id,
        'stamped_by_user_id' => $registrar->id,
        'stamped_at' => now(),
        'reason' => 'Program transfer finalized',
        'idempotency_key' => 'stamp-finalized-transfer',
    ]);

    // BSN is released (not counted against BSN), counted against BSPSY
    // BSN is released (decreased by 1 from initial held reservation), counted against BSPSY (+1)
    expect(QuotaReservation::committedAttemptCount($attempt, $bsn->id))->toBe($initialBsn - 1)
        ->and(QuotaReservation::committedAttemptCount($attempt, $bspsy->id))->toBe($initialBspsy + 1);
});

test('provisional clearance commitment is released when attempt finalizes in another program', function (): void {
    [$attempt, , , $registrar] = buildQuotaConservationFixture('cns-clearance-superseded@example.test');
    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();
    $bspsy = Program::query()->where('code', OTHER_PROGRAM)->firstOrFail();

    $initialBsn = QuotaReservation::committedAttemptCount($attempt, $bsn->id);
    $initialBspsy = QuotaReservation::committedAttemptCount($attempt, $bspsy->id);

    // 1. Confirmed clearance receipt for provisional program BSN
    AdmissionClearanceReceipt::query()->create([
        'admission_attempt_id' => $attempt->id,
        'program_id' => $bsn->id,
        'receipt_number' => 'CLR-SUPERSEDED-01',
        'status' => AdmissionClearanceReceipt::STATUS_CONFIRMED,
        'evidence_vector' => ['ready' => true],
        'confirmed_by_user_id' => $registrar->id,
        'confirmed_at' => now(),
        'idempotency_key' => 'clr-superseded-key',
    ]);

    // 2. Finalized enrollment stamp in BSPSY
    EnrollmentStamp::query()->create([
        'admission_attempt_id' => $attempt->id,
        'stamp_type' => EnrollmentStamp::STAMP_ENROLLED,
        'provisional_program_id' => $bsn->id,
        'final_program_id' => $bspsy->id,
        'stamped_by_user_id' => $registrar->id,
        'stamped_at' => now(),
        'reason' => 'Transfer finalized',
        'idempotency_key' => 'stamp-final-release-clr',
    ]);

    // The effective enrolled final program supersedes both the provisional selection and clearance commitment.
    // BSN releases one held commitment; BSPSY gains exactly one.
    expect(QuotaReservation::committedAttemptCount($attempt, $bsn->id))->toBe($initialBsn - 1)
        ->and(QuotaReservation::committedAttemptCount($attempt, $bspsy->id))->toBe($initialBspsy + 1);
});

test('batchCommittedForCycle returns identical results to committedForCycle in a single pass', function (): void {
    [$attempt] = buildQuotaConservationFixture('cns-batch-parity@example.test');
    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();
    $bspsy = Program::query()->where('code', OTHER_PROGRAM)->firstOrFail();

    $cycleId = $attempt->academic_cycle_id;
    $individualBsn = QuotaReservation::committedForCycle($cycleId, $bsn->id);
    $individualBspsy = QuotaReservation::committedForCycle($cycleId, $bspsy->id);

    $batch = QuotaReservation::batchCommittedForCycle($cycleId, [$bsn->id, $bspsy->id]);

    expect($batch)->toBeArray()
        ->and($batch[$bsn->id])->toBe($individualBsn)
        ->and($batch[$bspsy->id])->toBe($individualBspsy);
});
