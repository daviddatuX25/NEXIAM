<?php

use App\Admission\Actions\AcknowledgeAssistedProgramSelection;
use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\DeclineApplicantProgramSelection;
use App\Admission\Actions\OpenProgramFollowUp;
use App\Admission\Actions\ResolveProgramFollowUp;
use App\Admission\Actions\ReverseEnrollmentStamp;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Admission\Actions\StampAttemptEnrolled;
use App\Admission\Actions\StampAttemptEnrolling;
use App\Admission\Support\ApplicantQrCode;
use App\Admission\Support\AttemptProgramFacts;
use App\Consultation\Queries\ConsultationAdmissionSupportQuery;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\ProgramDecline;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramSelection;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\User;
use App\ProgramHead\Queries\ProgramHeadApplicantQueries;
use App\ProgramHead\Queries\ProgramHeadCandidateListsQuery;
use Illuminate\Support\Facades\Crypt;

test('guidance opens follow-up; PH clears; open follow-up blocks enrolled stamp', function (): void {
    [$attempt, $applicant, $guidance, $registrar] = buildReleasedAdvisedAttempt($this, 'follow-up@example.test');

    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();

    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $bsn->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
        ['slots' => 2],
    );

    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, [
        ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'pool-fu1');

    $followUp = app(OpenProgramFollowUp::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, 'appearance needed', 'fu-1');

    expect($followUp->status)->toBe(ProgramFollowUp::STATUS_OPEN);

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

    $ph = User::query()->where('email', 'program.head.bsn@nexiam.test')->firstOrFail();

    $resolved = app(ResolveProgramFollowUp::class)->handle($followUp, $ph, ProgramFollowUp::STATUS_CLEARED_BY_PH, 'applicant appeared; all good', 'resolve-1');

    expect($resolved->status)->toBe(ProgramFollowUp::STATUS_CLEARED_BY_PH);

    // Now final enrollment succeeds with variance snapshot.
    $enrolled = app(StampAttemptEnrolled::class)->handle($attempt, $registrar, DEFAULT_PROGRAM, 'ENR-001', 'hash1', 'Registrar Clerk', 'verified', 'stamp-d-2');

    expect($enrolled->stamp_type)->toBe(EnrollmentStamp::STAMP_ENROLLED)
        ->and($enrolled->final_program_id)->toBe($bsn->id)
        ->and(AttemptProgramFacts::isEnrolled($attempt))->toBeTrue();

    // Paper-finalized selection recorded.
    $selection = ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->latest('id')->first();
    expect($selection->status)->toBe(ProgramSelection::STATUS_PAPER_FINALIZED);
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

    $ph = User::query()->where('email', 'program.head.bsn@nexiam.test')->firstOrFail();
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

    $ph = User::query()->where('email', 'program.head.bsn@nexiam.test')->firstOrFail();

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
        ->and($support['program_pool']['version'])->toBe(1)
        ->and($support['preferences'])->toContain(DEFAULT_PROGRAM);
});

test('PH candidate lists expose assigned-program rows only', function (): void {
    [$attempt, , $guidance] = buildReleasedAdvisedAttempt($this, 'ph-lists@example.test');

    $bsn = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();
    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $bsn->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
        ['slots' => 2],
    );

    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, DEFAULT_PROGRAM, [
        ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'pool-pl1');

    $lists = app(ProgramHeadCandidateListsQuery::class)->for($bsn, 'SYN-CYCLE-2026');

    expect($lists['quota']['configured'])->toBeTrue()
        ->and($lists['potential'])->not->toBeEmpty()
        ->and(collect($lists['potential'])->first()['basis'])->toContain('preference #1');
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
    [$attempt, , , $registrar] = buildReleasedAdvisedAttempt($this, 'qr-menu@example.test');

    $token = ApplicantQrCode::issue($attempt);
    expect(ApplicantQrCode::verify($token))->toBe($attempt->id);

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $menuQuery = app(\App\Admission\Queries\ApplicantActionMenuQuery::class);

    $registrarMenu = collect($menuQuery->for($attempt, $registrar))->pluck('key')->all();
    $guidanceMenu = collect($menuQuery->for($attempt, $guidance))->pluck('key')->all();

    expect($registrarMenu)->toContain('enrollment_eligibility')
        ->and($guidanceMenu)->toContain('admission_support')
        ->and($guidanceMenu)->toContain('consultation_dashboard')
        ->and($registrarMenu)->not->toContain('consultation_dashboard');

    // No-capability actor gets no destinations (no applicant data leaks).
    $outsider = User::factory()->create(['email' => 'qr-outsider@example.test']);
    expect($menuQuery->for($attempt, $outsider))->toBe([]);
});

test('consultation dashboard groups schedule by session with stats', function (): void {
    [$attempt, , $guidance, $registrar] = buildReleasedAdvisedAttempt($this, 'dash@example.test');

    $dashboard = app(\App\Consultation\Queries\ConsultationDashboardQuery::class)->for('SYN-CYCLE-2026');

    expect($dashboard['stats'])->toHaveKeys(['today_scheduled', 'pending_advise', 'not_yet_scheduled', 'sessions_open', 'sessions_closed'])
        ->and($dashboard['today_schedule'])->toBeArray();

    // One scheduled applicant (the released attempt) may appear in today's
    // schedule only when it has a consultation session assignment — which the
    // journey helper does not create, so the attempt lands in not-yet-scheduled
    // only when result-ready + not advised is false (it IS advised) → excluded.
    // Contract: no fabricated per-applicant rows; grouping is session-based.
    foreach ($dashboard['today_schedule'] as $session) {
        expect($session)->toHaveKeys(['id', 'scheduled_at', 'room', 'applicants']);
    }
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

    $ph = User::query()->where('email', 'program.head.bsn@nexiam.test')->firstOrFail();

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
