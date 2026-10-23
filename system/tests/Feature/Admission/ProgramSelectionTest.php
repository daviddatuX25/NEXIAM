<?php

use App\Admission\Actions\AcknowledgeAssistedProgramSelection;
use App\Admission\Actions\SelectApplicantProgram;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Admission\Actions\TransferApplicantProgramSelection;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\ProgramSelection;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\User;

test('guidance sets pool with advised default reserving quota', function (): void {
    [$attempt, , $guidance] = buildReleasedAdvisedAttempt($this, 'pool-set@example.test');

    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();

    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $bsn->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
        ['slots' => 2],
    );

    $pool = app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, [
        ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
        ['program_code' => OTHER_PROGRAM, 'display_order' => 2, 'basis' => 'guidance_option'],
    ], 'initial pool', 'pool-1');

    expect($pool->version)->toBe(1)
        ->and($pool->options()->count())->toBe(2);

    $selection = ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();

    expect($selection->status)->toBe(ProgramSelection::STATUS_PENDING_ACKNOWLEDGEMENT)
        ->and($selection->source)->toBe(ProgramSelection::SOURCE_GUIDANCE_DEFAULT)
        ->and($selection->program_id)->toBe($bsn->id)
        ->and($selection->isNonTerminal())->toBeTrue();

    // Replay with the same key + identical payload returns the original pool.
    $replayed = app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, [
        ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
        ['program_code' => OTHER_PROGRAM, 'display_order' => 2, 'basis' => 'guidance_option'],
    ], 'initial pool', 'pool-1');

    expect($replayed->id)->toBe($pool->id);
});

test('pool set requires released result and confirmed consultation', function (): void {
    seedE2Foundation();
    $result = submitSyntheticApplicant('pool-gate@example.test', 'PoolGate');
    $attempt = $result['attempt'];
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)->post(route('admissions.accept', $attempt), ['expected_version' => 1]);

    try {
        app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, [
            ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
        ], null, 'pool-gate-1');
        $this->fail('Expected RuntimeException for missing release.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('result must be released');
    }
});

test('applicant acknowledges default then transfers with atomic quota swap', function (): void {
    [$attempt, $applicant, $guidance] = buildReleasedAdvisedAttempt($this, 'transfer@example.test');

    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();
    $bspsy = Program::query()->where('code', OTHER_PROGRAM)->firstOrFail();

    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $bsn->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
        ['slots' => 2],
    );
    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $bspsy->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
        ['slots' => 1],
    );

    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, [
        ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
        ['program_code' => OTHER_PROGRAM, 'display_order' => 2, 'basis' => 'guidance_option'],
    ], null, 'pool-t1');

    $acknowledged = app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'ack-1');

    expect($acknowledged->status)->toBe(ProgramSelection::STATUS_ACTIVE);

    $transferred = app(TransferApplicantProgramSelection::class)->handle($attempt, $applicant, OTHER_PROGRAM, 'xfer-1');

    expect($transferred->program_id)->toBe($bspsy->id)
        ->and($transferred->status)->toBe(ProgramSelection::STATUS_ACTIVE);

    // Old selection superseded, reservation released.
    expect($acknowledged->fresh()->status)->toBe(ProgramSelection::STATUS_SUPERSEDED)
        ->and(ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)->count())->toBe(1);
});

test('selection fails closed when quota is full', function (): void {
    [$attempt, $applicant, $guidance] = buildReleasedAdvisedAttempt($this, 'quota-full@example.test');

    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();

    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $bsn->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
        ['slots' => 1],
    );

    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, [
        ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'pool-q1');

    // A second attempt hits the full single-slot program and fails closed.
    [$other] = buildReleasedAdvisedAttempt($this, 'quota-other@example.test', 1);
    ProgramQuota::query()
        ->where('program_id', $bsn->id)
        ->where('academic_cycle_id', $other->academic_cycle_id)
        ->update(['slots' => 1]);

    try {
        app(SetGuidanceProgramPool::class)->handle($other, $guidance, DEFAULT_PROGRAM, [
            ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
        ], null, 'pool-q2');
        $this->fail('Expected RuntimeException when quota is full.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('quota capacity');
    }

    // First attempt's own selection is idempotent within its held slot.
    $selection = app(SelectApplicantProgram::class)->handle($attempt, $applicant, DEFAULT_PROGRAM, 'sel-q1');
    expect($selection->status)->toBe(ProgramSelection::STATUS_ACTIVE);
});

test('selection blocked while enrolling', function (): void {
    [$attempt, $applicant, $guidance, $registrar] = buildReleasedAdvisedAttempt($this, 'enroll-block@example.test');

    EnrollmentStamp::query()->create([
        'admission_attempt_id' => $attempt->id,
        'stamp_type' => EnrollmentStamp::STAMP_ENROLLING,
        'provisional_program_id' => Program::query()->where('code', OTHER_PROGRAM)->firstOrFail()->id,
        'stamped_by_user_id' => $registrar->id,
        'reason' => 'sheet received',
        'version' => 1,
        'idempotency_key' => 'stamp-e1',
        'created_at' => now(),
    ]);

    try {
        app(SelectApplicantProgram::class)->handle($attempt, $applicant, DEFAULT_PROGRAM, 'sel-e1');
        $this->fail('Expected RuntimeException while enrolling.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('not allowed while enrollment processing');
    }
});

test('same-payload replay returns original; cross-payload reuse conflicts without mutation', function (): void {
    [$attempt, $applicant, $guidance] = buildReleasedAdvisedAttempt($this, 'conflict@example.test');

    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();
    $bspsy = Program::query()->where('code', OTHER_PROGRAM)->firstOrFail();

    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $bsn->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
        ['slots' => 2],
    );
    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $bspsy->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
        ['slots' => 2],
    );

    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, [
        ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
        ['program_code' => OTHER_PROGRAM, 'display_order' => 2, 'basis' => 'guidance_option'],
    ], null, 'pool-c1');

    $created = app(SelectApplicantProgram::class)->handle($attempt, $applicant, DEFAULT_PROGRAM, 'key-c1');
    $replayed = app(SelectApplicantProgram::class)->handle($attempt, $applicant, DEFAULT_PROGRAM, 'key-c1');

    expect($replayed->id)->toBe($created->id)
        // Selecting the pending default upgrades it in place (no duplicate row).
        ->and(ProgramSelection::query()->count())->toBe(1)
        ->and($created->status)->toBe(ProgramSelection::STATUS_ACTIVE);

    $before = ProgramSelection::query()->count();

    try {
        app(SelectApplicantProgram::class)->handle($attempt, $applicant, OTHER_PROGRAM, 'key-c1');
        $this->fail('Expected RuntimeException for cross-payload key reuse.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('different request payload');
    }

    expect(ProgramSelection::query()->count())->toBe($before); // no mutation
});
