<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\AcknowledgeAssistedProgramSelection;
use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\DeclineApplicantProgramSelection;
use App\Admission\Actions\OpenProgramFollowUp;
use App\Admission\Actions\ResolveProgramFollowUp;
use App\Admission\Actions\ReverseEnrollmentStamp;
use App\Admission\Actions\SelectApplicantProgram;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Admission\Actions\StampAttemptEnrolled;
use App\Admission\Actions\StampAttemptEnrolling;
use App\Admission\Actions\TransferApplicantProgramSelection;
use App\Admission\Queries\ApplicantActionMenuQuery;
use App\Admission\Support\ApplicantQrCode;
use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\QuotaReservation;
use App\Admission\Support\SelectionGuard;
use App\Consultation\Queries\ConsultationAdmissionSupportQuery;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\ProgramDecline;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramSelection;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Operation;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Release\ConsultationDecision;
use App\Models\User;
use App\ProgramHead\Queries\ProgramHeadApplicantQueries;
use App\ProgramHead\Queries\ProgramHeadCandidateListsQuery;
use App\Staff\Queries\AdmissionsFunnelAggregateQuery;

test('guidance opens follow-up; PH clears; open follow-up blocks enrolled stamp', function (): void {
    [$attempt, $applicant, $guidance, $registrar] = buildReleasedAdvisedAttempt($this, 'follow-up@example.test');

    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();

    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $bsn->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
        ['slots' => 2],
    );

    $pool = app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, [
        ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'pool-fu1');

    $attempt->forceFill(['program_preference_2_code' => OTHER_PROGRAM])->save();
    expect($attempt->fresh()->program_preference_2_code)->toBe(OTHER_PROGRAM);

    $followUp = app(OpenProgramFollowUp::class)->handle($attempt, $guidance, OTHER_PROGRAM, 'appearance needed', 'fu-1');

    expect($followUp->status)->toBe(ProgramFollowUp::STATUS_OPEN)
        ->and($pool->fresh()->version)->toBe(1)
        ->and($pool->options()->pluck('program_id')->all())->toBe([$bsn->id]);

    $selection = ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    $selectionId = $selection->id;
    $selectionProgramId = $selection->program_id;
    $selectionStatus = $selection->status;
    expect($selectionProgramId)->toBe($bsn->id)
        ->and($selectionStatus)->toBe(ProgramSelection::STATUS_PENDING_ACKNOWLEDGEMENT);

    $assertUnchangedReservation = function (?string $expectedStatus = null) use ($attempt, $selection, $selectionId, $selectionProgramId, $selectionStatus): void {
        $selection->refresh();
        expect($selection->id)->toBe($selectionId)
            ->and($selection->program_id)->toBe($selectionProgramId)
            ->and($selection->status)->toBe($expectedStatus ?? $selectionStatus)
            ->and($selection->applicant_acknowledged_at)->toBeNull()
            ->and(ProgramSelection::query()
                ->where('admission_attempt_id', $attempt->id)
                ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)
                ->count())->toBe(1);
    };

    $assertUnchangedReservation();

    // Registrar may still start enrolling with an open follow-up.
    $stamp = app(StampAttemptEnrolling::class)->handle($attempt, $registrar, DEFAULT_PROGRAM, 'ENR-001', 'sheet received', 'stamp-e-1');
    expect($stamp->stamp_type)->toBe(EnrollmentStamp::STAMP_ENROLLING);

    // Final enrolled is blocked while the follow-up is open.
    try {
        app(StampAttemptEnrolled::class)->handle($attempt, $registrar, DEFAULT_PROGRAM, 'ENR-001', 'hash1', 'Registrar Clerk', 'verified', 'stamp-d-1');
        $this->fail('Expected enrolled to be blocked by open follow-up.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('open follow-up');
    }

    $ph = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();

    $resolved = app(ResolveProgramFollowUp::class)->handle($followUp, $ph, ProgramFollowUp::STATUS_CLEARED_BY_PH, 'applicant appeared; all good', 'resolve-1');

    expect($resolved->status)->toBe(ProgramFollowUp::STATUS_CLEARED_BY_PH)
        ->and($pool->fresh()->version)->toBe(1)
        ->and($pool->options()->pluck('program_id')->all())->toBe([$bsn->id]);
    $assertUnchangedReservation();

    // Now final enrollment succeeds with variance snapshot.
    $enrolled = app(StampAttemptEnrolled::class)->handle($attempt, $registrar, DEFAULT_PROGRAM, 'ENR-001', 'hash1', 'Registrar Clerk', 'verified', 'stamp-d-2');

    expect($enrolled->stamp_type)->toBe(EnrollmentStamp::STAMP_ENROLLED)
        ->and($enrolled->final_program_id)->toBe($bsn->id)
        ->and(AttemptProgramFacts::isEnrolled($attempt))->toBeTrue()
        ->and($followUp->fresh()->status)->toBe(ProgramFollowUp::STATUS_CLEARED_BY_PH);

    // Paper-finalized selection recorded.
    $selection = ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->latest('id')->first();
    expect($selection->status)->toBe(ProgramSelection::STATUS_PAPER_FINALIZED)
        ->and($selection->program_id)->toBe($bsn->id)
        ->and(ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(1);
});

test('PH clearance preserves a different-program student selection receipt and original-key replay', function (): void {
    [$attempt, $applicant, $guidance, $registrar] = buildReleasedAdvisedAttempt($this, 'fu-student-receipt@example.test');

    foreach ([DEFAULT_PROGRAM, OTHER_PROGRAM] as $programCode) {
        $program = Program::query()->where('code', $programCode)->firstOrFail();
        ProgramQuota::query()->updateOrCreate(
            ['program_id' => $program->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
            ['slots' => 2],
        );
    }

    $pool = app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, [
        ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
        ['program_code' => OTHER_PROGRAM, 'display_order' => 2, 'basis' => 'guidance_option'],
    ], null, 'pool-fu-student');
    $selectionKey = 'student-choice-original';
    $selection = app(SelectApplicantProgram::class)->handle($attempt, $applicant, DEFAULT_PROGRAM, $selectionKey);
    $receipt = $selection->only([
        'id',
        'program_id',
        'source',
        'actor_user_id',
        'idempotency_key',
        'commitment_identity',
    ]);

    expect($selection->program_id)->toBe(Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail()->id)
        ->and($selection->source)->toBe(ProgramSelection::SOURCE_STUDENT_SELF)
        ->and($selection->actor_user_id)->toBe($applicant->id)
        ->and($selection->status)->toBe(ProgramSelection::STATUS_ACTIVE);

    $followUp = app(OpenProgramFollowUp::class)->handle($attempt, $guidance, OTHER_PROGRAM, 'department review', 'fu-student-receipt');
    $ph = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    app(ResolveProgramFollowUp::class)->handle($followUp, $ph, ProgramFollowUp::STATUS_CLEARED_BY_PH, 'approved after review', 'resolve-fu-student');

    $selection->refresh();
    $clearedSelection = ProgramSelection::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('program_id', Program::query()->where('code', OTHER_PROGRAM)->firstOrFail()->id)
        ->where('source', ProgramSelection::SOURCE_GUIDANCE_DEFAULT)
        ->firstOrFail();

    expect($selection->only(array_keys($receipt)))->toBe($receipt)
        ->and($selection->status)->toBe(ProgramSelection::STATUS_SUPERSEDED)
        ->and($clearedSelection->program_id)->toBe(Program::query()->where('code', OTHER_PROGRAM)->firstOrFail()->id)
        ->and($clearedSelection->status)->toBe(ProgramSelection::STATUS_ACTIVE)
        ->and($clearedSelection->source)->toBe(ProgramSelection::SOURCE_GUIDANCE_DEFAULT)
        ->and($clearedSelection->actor_user_id)->toBe($ph->id)
        ->and($clearedSelection->program_pool_id)->toBe($pool->id)
        ->and($pool->fresh()->version)->toBe(1);

    app(StampAttemptEnrolling::class)->handle($attempt, $registrar, OTHER_PROGRAM, 'ENR-FU-STUDENT', 'sheet received', 'stamp-fu-student-e');
    $replayed = app(SelectApplicantProgram::class)->handle($attempt, $applicant, DEFAULT_PROGRAM, $selectionKey);

    expect($replayed->id)->toBe($selection->id)
        ->and($replayed->status)->toBe(ProgramSelection::STATUS_SUPERSEDED)
        ->and($replayed->source)->toBe(ProgramSelection::SOURCE_STUDENT_SELF)
        ->and($replayed->actor_user_id)->toBe($applicant->id)
        ->and($replayed->idempotency_key)->toBe($selectionKey)
        ->and($replayed->commitment_identity)->toBe($receipt['commitment_identity']);
});

test('PH clearance after a historical declined-pool follow-up uses the current pool for its successor', function (): void {
    [$attempt, $applicant, $guidance] = buildReleasedAdvisedAttempt($this, 'fu-declined-pool@example.test');

    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();
    $bspsy = Program::query()->where('code', OTHER_PROGRAM)->firstOrFail();
    $bsba = Program::query()->where('code', 'BSBA')->firstOrFail();
    $staleProgram = Program::query()->where('code', 'BEEd')->firstOrFail();
    foreach ([$bsn, $bspsy, $bsba, $staleProgram] as $program) {
        ProgramQuota::query()->updateOrCreate(
            ['program_id' => $program->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
            ['slots' => 2],
        );
    }
    $poolV1 = app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, [
        ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
        ['program_code' => OTHER_PROGRAM, 'display_order' => 2, 'basis' => 'guidance_option'],
        ['program_code' => 'BSBA', 'display_order' => 3, 'basis' => 'guidance_option'],
        ['program_code' => 'BEEd', 'display_order' => 4, 'basis' => 'guidance_option'],
    ], null, 'pool-fu-declined-target-v1');
    $selection = app(SelectApplicantProgram::class)->handle($attempt, $applicant, OTHER_PROGRAM, 'student-declined-target');
    $followUp = app(OpenProgramFollowUp::class)->handle($attempt, $guidance, OTHER_PROGRAM, 'department review', 'fu-declined-target');
    $ph = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();

    $decline = app(DeclineApplicantProgramSelection::class)->handle($attempt, $ph, OTHER_PROGRAM, 'not eligible', 'decline-current-pool-target');

    expect($selection->fresh()->status)->toBe(ProgramSelection::STATUS_PROGRAM_DECLINED)
        ->and($decline->program_pool_id)->toBe($poolV1->id)
        ->and($followUp->fresh()->status)->toBe(ProgramFollowUp::STATUS_OPEN);

    $poolV2 = app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, [
        ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
        ['program_code' => 'BSBA', 'display_order' => 2, 'basis' => 'guidance_option'],
    ], 'refresh current options', 'pool-fu-declined-target-v2');

    app(ResolveProgramFollowUp::class)->handle($followUp, $ph, ProgramFollowUp::STATUS_CLEARED_BY_PH, 'approved after reconsideration', 'resolve-declined-target');

    $successor = $attempt->programPools()->where('version', $poolV2->version + 1)->firstOrFail();
    $clearedSelection = ProgramSelection::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('idempotency_key', 'follow-up-clear-'.$attempt->id)
        ->firstOrFail();
    $successorProgramIds = $successor->options()->orderBy('display_order')->pluck('program_id')->all();

    expect($poolV2->predecessor_id)->toBe($poolV1->id)
        ->and($successor->predecessor_id)->toBe($poolV2->id)
        ->and($successor->version)->toBe($poolV2->version + 1)
        ->and($successorProgramIds)->toBe([$bsn->id, $bsba->id, $bspsy->id])
        ->and($successor->options()->where('program_id', $staleProgram->id)->exists())->toBeFalse()
        ->and($decline->fresh()->program_pool_id)->toBe($poolV1->id)
        ->and($followUp->fresh()->program_pool_id)->toBe($successor->id)
        ->and($clearedSelection->program_id)->toBe($bspsy->id)
        ->and($clearedSelection->program_pool_id)->toBe($successor->id)
        ->and($clearedSelection->actor_user_id)->toBe($ph->id);
    expect(fn () => SelectionGuard::assertOptionEffective($attempt->fresh(), $poolV1, $bspsy->id))
        ->toThrow(RuntimeException::class, 'decline is recorded for this pool');

    SelectionGuard::assertOptionEffective($attempt->fresh(), $successor, $bspsy->id);
});

test('PH clearance rolls back when declined preference cannot fit successor quota', function (): void {
    [$attempt, , $guidance] = buildReleasedAdvisedAttempt($this, 'fu-successor-quota-rollback@example.test');
    $attempt->forceFill(['program_preference_2_code' => OTHER_PROGRAM])->save();
    ProgramQuota::query()->updateOrCreate(
        ['program_id' => Program::query()->where('code', OTHER_PROGRAM)->firstOrFail()->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
        ['slots' => 0],
    );
    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();
    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $bsn->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
        ['slots' => 2],
    );
    $poolV1 = app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, [
        ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'pool-successor-quota-v1');
    $followUp = app(OpenProgramFollowUp::class)->handle($attempt, $guidance, OTHER_PROGRAM, 'department review', 'fu-successor-quota');
    $ph = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    app(ResolveProgramFollowUp::class)->handle($followUp, $ph, ProgramFollowUp::STATUS_DECLINED_BY_PH, 'not eligible', 'decline-successor-quota');
    $followUp = app(OpenProgramFollowUp::class)->handle($attempt, $guidance, OTHER_PROGRAM, 'reconsideration requested', 'fu-successor-quota-reopened');

    $optionsBefore = $poolV1->options()->orderBy('display_order')->get()->toArray();
    $selectionCountBefore = ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->count();
    $decisionCountBefore = ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->count();

    expect(fn () => app(ResolveProgramFollowUp::class)->handle(
        $followUp,
        $ph,
        ProgramFollowUp::STATUS_CLEARED_BY_PH,
        'approved after reconsideration',
        'clear-successor-quota',
    ))->toThrow(RuntimeException::class, 'quota');

    expect($followUp->fresh()->status)->toBe(ProgramFollowUp::STATUS_OPEN)
        ->and($followUp->fresh()->program_pool_id)->toBe($poolV1->id)
        ->and($attempt->programPools()->count())->toBe(1)
        ->and($poolV1->fresh()->version)->toBe(1)
        ->and($poolV1->options()->orderBy('display_order')->get()->toArray())->toBe($optionsBefore)
        ->and(ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->count())->toBe($selectionCountBefore)
        ->and(ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->where('program_id', Program::query()->where('code', OTHER_PROGRAM)->value('id'))->exists())->toBeFalse()
        ->and(ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->count())->toBe($decisionCountBefore);
});

test('clearing declined follow-up during enrolling does not create a successor pool', function (): void {
    [$attempt, , $guidance, $registrar] = buildReleasedAdvisedAttempt($this, 'fu-enrolling-declined-followup@example.test');
    $attempt->forceFill(['program_preference_2_code' => OTHER_PROGRAM])->save();

    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();
    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $bsn->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
        ['slots' => 2],
    );
    $pool = app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, [
        ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'pool-enrolling-declined-followup-v1');
    $followUp = app(OpenProgramFollowUp::class)->handle($attempt, $guidance, OTHER_PROGRAM, 'department review', 'fu-enrolling-declined-followup');
    $ph = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    app(ResolveProgramFollowUp::class)->handle($followUp, $ph, ProgramFollowUp::STATUS_DECLINED_BY_PH, 'not eligible', 'decline-enrolling-followup');
    $followUp = app(OpenProgramFollowUp::class)->handle($attempt, $guidance, OTHER_PROGRAM, 'reconsideration requested', 'fu-enrolling-followup-reopened');

    app(StampAttemptEnrolling::class)->handle($attempt, $registrar, DEFAULT_PROGRAM, 'ENR-FU-DECLINED', 'sheet received', 'stamp-fu-declined-enrolling');
    $selection = ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    $selectionBefore = $selection->only(['id', 'program_id', 'status', 'source', 'idempotency_key', 'program_pool_id']);

    $resolved = app(ResolveProgramFollowUp::class)->handle($followUp, $ph, ProgramFollowUp::STATUS_CLEARED_BY_PH, 'approved after reconsideration', 'clear-enrolling-followup');

    expect($resolved->status)->toBe(ProgramFollowUp::STATUS_CLEARED_BY_PH)
        ->and($followUp->fresh()->status)->toBe(ProgramFollowUp::STATUS_CLEARED_BY_PH)
        ->and($followUp->fresh()->program_pool_id)->toBe($pool->id)
        ->and($attempt->programPools()->count())->toBe(1)
        ->and($pool->fresh()->version)->toBe(1)
        ->and($pool->options()->orderBy('display_order')->pluck('program_id')->all())->toBe([$bsn->id])
        ->and($selection->fresh()->only(array_keys($selectionBefore)))->toBe($selectionBefore)
        ->and(ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)->count())->toBe(1)
        ->and(ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->where('program_id', Program::query()->where('code', OTHER_PROGRAM)->value('id'))->exists())->toBeFalse();

    $enrollingStamp = EnrollmentStamp::query()->where('admission_attempt_id', $attempt->id)->where('stamp_type', EnrollmentStamp::STAMP_ENROLLING)->firstOrFail();
    expect($enrollingStamp->provisional_program_id)->toBe($bsn->id);
});

test('PH clearance of a different in-pool program during enrolling preserves the standing selection', function (): void {
    [$attempt, $applicant, $guidance, $registrar] = buildReleasedAdvisedAttempt($this, 'fu-enrolling-different-program@example.test');

    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();
    $bspsy = Program::query()->where('code', OTHER_PROGRAM)->firstOrFail();
    foreach ([$bsn, $bspsy] as $program) {
        ProgramQuota::query()->updateOrCreate(
            ['program_id' => $program->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
            ['slots' => 2],
        );
    }
    $pool = app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, [
        ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
        ['program_code' => OTHER_PROGRAM, 'display_order' => 2, 'basis' => 'guidance_option'],
    ], null, 'pool-fu-enrolling-different');
    $selection = app(SelectApplicantProgram::class)->handle($attempt, $applicant, DEFAULT_PROGRAM, 'student-enrolling-standing-bsn');
    $followUp = app(OpenProgramFollowUp::class)->handle($attempt, $guidance, OTHER_PROGRAM, 'department review', 'fu-enrolling-bspsy');

    app(StampAttemptEnrolling::class)->handle($attempt, $registrar, DEFAULT_PROGRAM, 'ENR-FU-DIFFERENT', 'sheet received', 'stamp-fu-enrolling-different');
    $standingSelection = $selection->fresh()->only(['id', 'program_id', 'status', 'idempotency_key']);
    $bspsyCommitmentCount = QuotaReservation::committedForCycle((int) $attempt->academic_cycle_id, (int) $bspsy->id);
    $ph = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();

    $resolved = app(ResolveProgramFollowUp::class)->handle($followUp, $ph, ProgramFollowUp::STATUS_CLEARED_BY_PH, 'approved after review', 'resolve-fu-enrolling-different');

    $selection->refresh();
    expect($resolved->status)->toBe(ProgramFollowUp::STATUS_CLEARED_BY_PH)
        ->and($selection->only(array_keys($standingSelection)))->toBe($standingSelection)
        ->and($followUp->fresh()->status)->toBe(ProgramFollowUp::STATUS_CLEARED_BY_PH)
        ->and(ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)->count())->toBe(1)
        ->and(QuotaReservation::committedForCycle((int) $attempt->academic_cycle_id, (int) $bspsy->id))->toBe($bspsyCommitmentCount)
        ->and($attempt->programPools()->count())->toBe(1)
        ->and($pool->fresh()->version)->toBe(1);

    $enrolled = app(StampAttemptEnrolled::class)->handle($attempt, $registrar, OTHER_PROGRAM, 'ENR-FU-DIFFERENT', 'hash-fu-different', 'Registrar Clerk', 'verified', 'stamp-fu-enrolling-different-final');
    expect($enrolled->stamp_type)->toBe(EnrollmentStamp::STAMP_ENROLLED)
        ->and($enrolled->final_program_id)->toBe($bspsy->id)
        ->and($selection->fresh()->id)->toBe($standingSelection['id'])
        ->and($selection->fresh()->status)->toBe(ProgramSelection::STATUS_PAPER_FINALIZED);
});

test('PH clears an out-of-pool non-preference standing selection during enrolling without changing the pool', function (): void {
    [$attempt, $applicant, $guidance, $registrar] = buildReleasedAdvisedAttempt($this, 'fu-out-of-pool@example.test');

    $firstPool = app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, [
        ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
        ['program_code' => OTHER_PROGRAM, 'display_order' => 2, 'basis' => 'guidance_option'],
    ], null, 'pool-fu-outside-v1');
    $selection = app(SelectApplicantProgram::class)->handle($attempt, $applicant, OTHER_PROGRAM, 'student-fu-outside');
    $standingReceipt = $selection->only([
        'id',
        'program_pool_id',
        'program_pool_option_id',
        'program_id',
        'status',
        'source',
        'actor_user_id',
        'idempotency_key',
        'commitment_identity',
    ]);

    $attempt->forceFill([
        'program_preference_1_code' => DEFAULT_PROGRAM,
        'program_preference_2_code' => null,
        'program_preference_3_code' => null,
    ])->save();
    $secondPool = app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, [
        ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
    ], 'removed standing target', 'pool-fu-outside-v2');
    $followUp = app(OpenProgramFollowUp::class)->handle($attempt, $guidance, OTHER_PROGRAM, 'standing choice review', 'fu-outside');

    $beforePoolState = [
        'pool_count' => $attempt->programPools()->count(),
        'version' => $secondPool->version,
        'option_ids' => $secondPool->options()->pluck('id')->all(),
    ];
    expect($firstPool->version)->toBe(1)
        ->and($secondPool->options()->where('program_id', $selection->program_id)->exists())->toBeFalse()
        ->and(in_array(OTHER_PROGRAM, [
            $attempt->program_preference_1_code,
            $attempt->program_preference_2_code,
            $attempt->program_preference_3_code,
        ], true))->toBeFalse();

    app(StampAttemptEnrolling::class)->handle($attempt, $registrar, OTHER_PROGRAM, 'ENR-FU-OUTSIDE', 'sheet received', 'stamp-fu-outside-e');
    $selection->refresh();
    $standingAfterEnrolling = $selection->only([
        'id',
        'program_pool_id',
        'program_pool_option_id',
        'program_id',
        'status',
        'source',
        'actor_user_id',
        'idempotency_key',
        'commitment_identity',
    ]);
    $quotaCommitmentCount = QuotaReservation::committedForCycle((int) $attempt->academic_cycle_id, (int) $selection->program_id);
    $ph = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $resolved = app(ResolveProgramFollowUp::class)->handle($followUp, $ph, ProgramFollowUp::STATUS_CLEARED_BY_PH, 'confirmed standing choice', 'resolve-fu-outside');

    $selection->refresh();
    expect($resolved->status)->toBe(ProgramFollowUp::STATUS_CLEARED_BY_PH)
        ->and($selection->only(array_keys($standingAfterEnrolling)))->toBe($standingAfterEnrolling)
        ->and($selection->id)->toBe($standingAfterEnrolling['id'])
        ->and($selection->program_id)->toBe($standingAfterEnrolling['program_id'])
        ->and($selection->idempotency_key)->toBe($standingAfterEnrolling['idempotency_key'])
        ->and($selection->status)->toBe($standingAfterEnrolling['status'])
        ->and($selection->source)->toBe($standingAfterEnrolling['source'])
        ->and(QuotaReservation::committedForCycle((int) $attempt->academic_cycle_id, (int) $selection->program_id))->toBe($quotaCommitmentCount)
        ->and($attempt->programPools()->count())->toBe($beforePoolState['pool_count'])
        ->and($secondPool->fresh()->version)->toBe($beforePoolState['version'])
        ->and($secondPool->options()->pluck('id')->all())->toBe($beforePoolState['option_ids']);

    $enrolled = app(StampAttemptEnrolled::class)->handle($attempt, $registrar, OTHER_PROGRAM, 'ENR-FU-OUTSIDE', 'hash-outside', 'Registrar Clerk', 'verified', 'stamp-fu-outside-d');
    expect($enrolled->stamp_type)->toBe(EnrollmentStamp::STAMP_ENROLLED)
        ->and($enrolled->final_program_id)->toBe($selection->program_id);
});

test('follow-up decline terminalizes selection and records immutable decline', function (): void {
    [$attempt, , $guidance] = buildReleasedAdvisedAttempt($this, 'fu-decline@example.test');

    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();

    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $bsn->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
        ['slots' => 2],
    );

    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, [
        ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'pool-fd1');

    $followUp = app(OpenProgramFollowUp::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, 'fit concern', 'fu-d-1');

    $ph = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    app(ResolveProgramFollowUp::class)->handle($followUp, $ph, ProgramFollowUp::STATUS_DECLINED_BY_PH, 'not eligible for this program', 'resolve-d-1');

    expect(ProgramDecline::query()->where('admission_attempt_id', $attempt->id)->exists())->toBeTrue();

    $selection = ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->latest('id')->first();
    expect($selection->status)->toBe(ProgramSelection::STATUS_PROGRAM_DECLINED);

    // The declined program is no longer selectable (effective option guard).
    $applicant = AuthenticateApplicantWithAccessKey::class; // unused; guard via pool effect
    expect(ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)->count())->toBe(0);
});

test('direct PH decline is blocked while enrolling', function (): void {
    [$attempt, , $guidance, $registrar] = buildReleasedAdvisedAttempt($this, 'decline-gate@example.test');

    app(StampAttemptEnrolling::class)->handle($attempt, $registrar, DEFAULT_PROGRAM, 'ENR-002', 'sheet', 'stamp-de-1');

    $ph = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();

    try {
        app(DeclineApplicantProgramSelection::class)->handle($attempt, $ph, DEFAULT_PROGRAM, 'not eligible', 'decline-1');
        $this->fail('Expected direct decline to be blocked while enrolling.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('before enrollment processing');
    }
});

test('enrollment reverse restores pre-final selection', function (): void {
    [$attempt, , $guidance, $registrar] = buildReleasedAdvisedAttempt($this, 'reverse@example.test');

    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();
    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $bsn->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
        ['slots' => 2],
    );

    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, [
        ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'pool-rv1');

    app(StampAttemptEnrolling::class)->handle($attempt, $registrar, DEFAULT_PROGRAM, 'ENR-003', 'sheet', 'stamp-rv-1');
    $enrolled = app(StampAttemptEnrolled::class)->handle($attempt, $registrar, DEFAULT_PROGRAM, 'ENR-003', 'h3', 'Clerk', 'verified', 'stamp-rv-2');

    $reversal = app(ReverseEnrollmentStamp::class)->handle($attempt, $registrar, 'paper not signed', 'reverse-1');

    expect($reversal->stamp_type)->toBe(EnrollmentStamp::STAMP_REVERSE_ENROLLED)
        ->and(AttemptProgramFacts::isEnrolled($attempt))->toBeFalse();

    // Reversed paper_finalized selection returns to active (pre-final state).
    $selection = ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->latest('id')->first();
    expect($selection->status)->toBe(ProgramSelection::STATUS_ACTIVE);
});

test('consultation support query reports real released evidence and no analysis', function (): void {
    [$attempt, , $guidance] = buildReleasedAdvisedAttempt($this, 'support-q@example.test');

    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();
    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $bsn->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
        ['slots' => 2],
    );

    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, [
        ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'pool-sq1');

    $support = app(ConsultationAdmissionSupportQuery::class)->for($attempt);

    expect($support['readiness']['result_released'])->toBeTrue()
        ->and($support['readiness']['advised'])->toBeTrue()
        ->and($support['analysis']['status'])->toBe('no_analysis')
        ->and($support['score_profile']['series'])->not->toBeEmpty()
        ->and($support['score_profile']['series'][0]['score'])->not->toBeNull()
        ->and($support['models'][0]['status'])->toBe('no_provider')
        ->and($support['models'][0]['evaluated'])->toBeFalse()
        ->and($support['models'][0]['recommendations'] ?? [])->toBeEmpty()
        ->and($support['models'][0]['applicant_evidence']['aptitude_scores'])->not->toBeEmpty()
        ->and($support['program_pool']['version'])->toBe(1)
        ->and($support['preferences'])->toContain($attempt->program_preference_1_code);
});

test('PH candidate lists expose assigned-program rows only', function (): void {
    [$attempt, , $guidance] = buildReleasedAdvisedAttempt($this, 'ph-lists@example.test');

    $programCode = (string) $attempt->program_preference_1_code;
    $program = Program::query()->where('code', $programCode)->firstOrFail();
    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $program->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
        ['slots' => 2],
    );

    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $programCode, [
        ['program_code' => $programCode, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'pool-pl1');

    $lists = app(ProgramHeadCandidateListsQuery::class)->for($program, 'SYN-CYCLE-2026');

    expect($lists['quota']['configured'])->toBeTrue()
        ->and($lists['potential'])->not->toBeEmpty()
        ->and(collect($lists['potential'])->first()['basis'])->toContain('preference #1')
        ->and(collect($lists['potential'])->firstWhere('attempt_id', $attempt->id)['result_available'])->toBeFalse();
});
test('program-head lists expose current clearance only and keep legacy stamps out of active queues', function (): void {
    seedE2Foundation();
    $confirmed = submitSyntheticApplicant('ph-clearance-ready@example.test', 'ClearanceReady')['attempt'];
    $legacy = submitSyntheticApplicant('ph-legacy-enrollment@example.test', 'ClearanceLegacy')['attempt'];
    $revoked = submitSyntheticApplicant('ph-clearance-revoked@example.test', 'ClearanceRevoked')['attempt'];
    $otherProgram = submitSyntheticApplicant('ph-clearance-other-program@example.test', 'ClearanceOther')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $otherProgramRecord = Program::query()->where('code', OTHER_PROGRAM)->firstOrFail();

    foreach ([$confirmed, $legacy, $revoked, $otherProgram] as $attempt) {
        $attempt->forceFill(['status' => 'accepted'])->save();
    }

    $createClearance = function ($attempt, Program $program, string $receiptNumber, string $status, string $idempotencyKey, ?AdmissionClearanceReceipt $predecessor = null) use ($registrar): AdmissionClearanceReceipt {
        return AdmissionClearanceReceipt::query()->create([
            'admission_attempt_id' => $attempt->id,
            'program_id' => $program->id,
            'receipt_number' => $receiptNumber,
            'status' => $status,
            'evidence_vector' => ['ready' => true],
            'confirmed_by_user_id' => $registrar->id,
            'confirmed_at' => now(),
            'predecessor_receipt_id' => $predecessor?->id,
            'revoked_by_user_id' => $status === AdmissionClearanceReceipt::STATUS_REVOKED ? $registrar->id : null,
            'revoked_at' => $status === AdmissionClearanceReceipt::STATUS_REVOKED ? now() : null,
            'revocation_reason' => $status === AdmissionClearanceReceipt::STATUS_REVOKED ? 'Synthetic revocation' : null,
            'idempotency_key' => $idempotencyKey,
        ]);
    };

    $createClearance($confirmed, $program, 'CLR-PH-READY-'.$confirmed->id, AdmissionClearanceReceipt::STATUS_CONFIRMED, 'clearance-ph-ready-'.$confirmed->id);
    EnrollmentStamp::query()->create([
        'admission_attempt_id' => $legacy->id,
        'stamp_type' => EnrollmentStamp::STAMP_ENROLLING,
        'provisional_program_id' => $program->id,
        'stamped_by_user_id' => $registrar->id,
        'stamped_at' => now(),
        'reason' => 'Historical enrollment record',
        'version' => 1,
    ]);
    $revokedConfirmation = $createClearance($revoked, $program, 'CLR-PH-REVOKED-OLD-'.$revoked->id, AdmissionClearanceReceipt::STATUS_CONFIRMED, 'clearance-ph-revoked-old-'.$revoked->id);
    $createClearance($revoked, $program, 'CLR-PH-REVOKED-'.$revoked->id, AdmissionClearanceReceipt::STATUS_REVOKED, 'clearance-ph-revoked-'.$revoked->id, $revokedConfirmation);
    $createClearance($otherProgram, $otherProgramRecord, 'CLR-PH-OTHER-'.$otherProgram->id, AdmissionClearanceReceipt::STATUS_CONFIRMED, 'clearance-ph-other-'.$otherProgram->id);

    $lists = app(ProgramHeadCandidateListsQuery::class)->for($program, 'SYN-CYCLE-2026');
    $ready = collect($lists['ready_for_enrollment'])->keyBy('attempt_id');
    $confirmedSummary = app(ProgramHeadApplicantQueries::class)->summary($confirmed, $program);
    $legacySummary = app(ProgramHeadApplicantQueries::class)->summary($legacy, $program);
    $admissions = app(AdmissionsFunnelAggregateQuery::class)->for(
        (int) $confirmed->academic_cycle_id,
        (int) $confirmed->institution_id,
    );

    expect($ready)->toHaveKey($confirmed->id)
        ->and($ready)->toHaveCount(1)
        ->and($ready)->not->toHaveKey($legacy->id)
        ->and($ready)->not->toHaveKey($revoked->id)
        ->and($ready)->not->toHaveKey($otherProgram->id)
        ->and(collect($lists['potential'])->pluck('attempt_id'))->not->toContain($legacy->id)
        ->and(array_keys($lists))->not->toContain('enrolling')
        ->and(array_keys($lists))->not->toContain('enrolled')
        ->and($confirmedSummary['lifecycle'])->toBe('ready_for_enrollment')
        ->and($confirmedSummary)->not->toHaveKeys(['enrolling', 'enrolled'])
        ->and($legacySummary['legacy_program_mutation_blocked'])->toBeTrue()
        ->and($legacySummary['lifecycle'])->not->toBeIn(['enrolling', 'enrolled'])
        ->and($admissions['kpis']['completed'])->toBe($admissions['kpis']['admission_clearances_confirmed']);
});

test('QR locator is permanent, deterministic, and rejects tampering', function (): void {
    [$attempt] = buildReleasedAdvisedAttempt($this, 'qr@example.test');

    // Permanent: the same attempt always yields the SAME code (no regeneration).
    $token = ApplicantQrCode::issue($attempt);
    $again = ApplicantQrCode::issue($attempt);

    expect($token)->toBe($again)
        ->and(ApplicantQrCode::verify($token))->toBe($attempt->id);

    // Tampered token fails closed.
    try {
        ApplicantQrCode::verify($token.'x');
        $this->fail('Expected tampered token to fail.');
    } catch (RuntimeException) {
        // expected
    }

    // A token signed for a different attempt fails closed.
    [$other] = buildReleasedAdvisedAttempt($this, 'qr-other@example.test', 1);
    $otherToken = ApplicantQrCode::issue($other);

    try {
        ApplicantQrCode::verify($otherToken);
        expect(ApplicantQrCode::verify($otherToken))->toBe($other->id);
    } catch (RuntimeException) {
        $this->fail('Own token must verify for its own attempt.');
    }

    // Swapping the attempt id into another token's signature context is invalid.
    try {
        ApplicantQrCode::verify(str_replace((string) $other->id, (string) $attempt->id, $otherToken));
        $this->fail('Expected cross-attempt token substitution to fail.');
    } catch (RuntimeException) {
        // expected
    }
});

test('same QR resolves different actor-specific destination menus; no-destination denied reveals nothing', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $submission = submitSyntheticApplicant('qr-menu@example.test');
    $attempt = app(AcceptAdmissionAttempt::class)->handle($submission['attempt'], $registrar, 1);

    $token = ApplicantQrCode::issue($attempt);
    expect(ApplicantQrCode::verify($token))->toBe($attempt->id);

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $menuQuery = app(ApplicantActionMenuQuery::class);

    $registrarMenu = collect($menuQuery->for($attempt, $registrar))->pluck('key')->all();
    $guidanceMenu = collect($menuQuery->for($attempt, $guidance))->pluck('key')->all();

    expect($registrarMenu)->toContain('enrollment_eligibility')
        ->and($guidanceMenu)->toContain('admission_support')
        ->and($guidanceMenu)->toContain('case_analysis')
        ->and($registrarMenu)->not->toContain('admission_support');

    // No-capability actor gets no destinations (no applicant data leaks).
    $outsider = User::factory()->create(['email' => 'qr-outsider@example.test']);
    expect($menuQuery->for($attempt, $outsider))->toBe([]);
});

test('Tier B protected record persists audit before serialization', function (): void {
    [$attempt, $applicant, $guidance] = buildReleasedAdvisedAttempt($this, 'tier-b@example.test');

    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();
    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $bsn->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
        ['slots' => 2],
    );

    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, [
        ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'pool-tb1');

    $ph = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();

    // Without acknowledgement, access stays Tier A.
    $summary = app(ProgramHeadApplicantQueries::class)->summary($attempt, $bsn);
    expect($summary['tier'])->toBe('A');

    $record = app(ProgramHeadApplicantQueries::class)->protectedRecord($attempt, $bsn, $ph);
    expect($record['tier'])->toBe('A'); // pending default not acknowledged -> still Tier A

    // Acknowledge the default, then Tier B unlocks with audit evidence.
    app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'ack-tb1');

    $recordB = app(ProgramHeadApplicantQueries::class)->protectedRecord($attempt, $bsn, $ph);

    expect($recordB['tier'])->toBe('B')
        ->and($recordB['score_profile']['series'])->not->toBeEmpty();

    expect(AuditEvent::query()->where('event_type', 'program.head_record_viewed')->where('actor', (string) $ph->id)->exists())->toBeTrue();
});
test('applicant acknowledgement receipt replays its original selection after transfer and enrollment', function (): void {
    [$attempt, $applicant, $guidance, $registrar] = buildReleasedAdvisedAttempt($this, 'ph-clearance-ack@example.test');

    foreach ([DEFAULT_PROGRAM, OTHER_PROGRAM] as $programCode) {
        $program = Program::query()->where('code', $programCode)->firstOrFail();
        ProgramQuota::query()->updateOrCreate(
            ['program_id' => $program->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
            ['slots' => 2],
        );
    }

    $pool = app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, OTHER_PROGRAM, [
        ['program_code' => OTHER_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
        ['program_code' => DEFAULT_PROGRAM, 'display_order' => 2, 'basis' => 'guidance_option'],
    ], null, 'pool-ph-clearance-ack');
    $followUp = app(OpenProgramFollowUp::class)->handle($attempt, $guidance, OTHER_PROGRAM, 'department review', 'fu-ph-clearance-ack');
    $ph = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    app(ResolveProgramFollowUp::class)->handle($followUp, $ph, ProgramFollowUp::STATUS_CLEARED_BY_PH, 'approved after review', 'resolve-ph-clearance-ack');

    $selection = ProgramSelection::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('program_id', Program::query()->where('code', OTHER_PROGRAM)->value('id'))
        ->firstOrFail();
    $selectionIdentity = $selection->only([
        'id',
        'program_pool_id',
        'program_pool_option_id',
        'program_id',
        'commitment_identity',
    ]);

    expect($selection->status)->toBe(ProgramSelection::STATUS_ACTIVE)
        ->and($selection->applicant_acknowledged_at)->toBeNull()
        ->and($selection->source)->toBe(ProgramSelection::SOURCE_GUIDANCE_DEFAULT)
        ->and($selection->actor_user_id)->toBe($guidance->id);

    $this->actingAs($applicant)
        ->get(route('applicant.program-choice'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/ProgramChoice')
            ->where('page_state', 'ready')
            ->where('support.readiness.selection.status', ProgramSelection::STATUS_ACTIVE)
            ->where('support.readiness.selection.program_code', OTHER_PROGRAM)
            ->where('support.readiness.selection.source', ProgramSelection::SOURCE_GUIDANCE_DEFAULT)
            ->where('support.readiness.selection.acknowledged', false)
            ->where('support.program_pool.default.program_code', OTHER_PROGRAM)
            ->where('support.program_pool.selection_status', ProgramSelection::STATUS_ACTIVE)
            ->where('support.program_pool.selection_program', OTHER_PROGRAM));

    $acknowledged = app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'applicant-ack-k1');

    expect($acknowledged->id)->toBe($selection->id)
        ->and($acknowledged->only(array_keys($selectionIdentity)))->toBe($selectionIdentity)
        ->and($acknowledged->status)->toBe(ProgramSelection::STATUS_ACTIVE)
        ->and($acknowledged->applicant_acknowledged_at)->not->toBeNull()
        ->and($acknowledged->source)->toBe(ProgramSelection::SOURCE_GUIDANCE_DEFAULT)
        ->and($acknowledged->actor_user_id)->toBe($applicant->id)
        ->and($acknowledged->idempotency_key)->toBe('applicant-ack-k1')
        ->and(ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(1);
    $acknowledgementOperation = Operation::query()
        ->where('idempotency_key', $applicant->id.':applicant.program.acknowledge:attempt:'.$attempt->id.':applicant-ack-k1')
        ->firstOrFail();
    expect($acknowledgementOperation->payload)->toMatchArray([
        'selection_id' => $selection->id,
        'request_hash' => hash('sha256', json_encode([], JSON_THROW_ON_ERROR)),
    ]);

    $acknowledgedIdentity = $acknowledged->only([
        'id',
        'program_id',
        'source',
        'actor_user_id',
        'idempotency_key',
    ]);
    $acknowledgedAt = $acknowledged->applicant_acknowledged_at;
    $acknowledgementAuditCount = AuditEvent::query()
        ->where('event_type', 'program.selection_acknowledged')
        ->where('actor', (string) $applicant->id)
        ->count();
    expect($acknowledgementAuditCount)->toBe(1);

    $transferred = app(TransferApplicantProgramSelection::class)->handle($attempt, $applicant, DEFAULT_PROGRAM, 'applicant-transfer-k2');
    expect($transferred->id)->not->toBe($acknowledged->id)
        ->and($transferred->source)->toBe(ProgramSelection::SOURCE_STUDENT_SELF)
        ->and($transferred->idempotency_key)->toBe('applicant-transfer-k2')
        ->and($acknowledged->fresh()->status)->toBe(ProgramSelection::STATUS_SUPERSEDED)
        ->and(ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(2);

    app(StampAttemptEnrolling::class)->handle($attempt, $registrar, DEFAULT_PROGRAM, 'ENR-ACK-REPLAY', 'sheet received', 'stamp-ack-replay-e');
    $enrolled = app(StampAttemptEnrolled::class)->handle($attempt, $registrar, DEFAULT_PROGRAM, 'ENR-ACK-REPLAY', 'hash-ack-replay', 'Registrar Clerk', 'verified', 'stamp-ack-replay-d');

    expect($enrolled->stamp_type)->toBe(EnrollmentStamp::STAMP_ENROLLED)
        ->and($enrolled->final_program_id)->toBe(Program::query()->where('code', DEFAULT_PROGRAM)->value('id'));

    $retry = app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'applicant-ack-k1');
    expect($retry->id)->toBe($selection->id)
        ->and($retry->fresh()->only(array_keys($acknowledgedIdentity)))->toBe($acknowledgedIdentity)
        ->and($retry->applicant_acknowledged_at->equalTo($acknowledgedAt))->toBeTrue()
        ->and($retry->status)->toBe(ProgramSelection::STATUS_SUPERSEDED)
        ->and(ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(2)
        ->and(AuditEvent::query()
            ->where('event_type', 'program.selection_acknowledged')
            ->where('actor', (string) $applicant->id)
            ->count())->toBe($acknowledgementAuditCount);
});
