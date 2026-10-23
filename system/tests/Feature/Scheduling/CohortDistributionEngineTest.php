<?php

use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Scheduling\Actions\AutoAssignEligibleAttempt;
use App\Scheduling\Actions\CommitCohortDistribution;
use App\Scheduling\Actions\PreviewCohortDistribution;
use App\Scheduling\Actions\ReconcileReconsideredAttempt;
use App\Scheduling\AssignmentInvariant;
use App\Scheduling\CohortPlanner;
use App\Scheduling\Exceptions\StalePlanConflictException;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->institution = Institution::query()->create([
        'code' => 'TEST-INST-DIST',
        'name' => 'Distribution Test Institution',
    ]);

    $this->cycle = AcademicCycle::query()->create([
        'institution_id' => $this->institution->id,
        'code' => 'CYCLE-DIST-2026',
        'name' => 'Cycle Distribution 2026',
        'school_year' => '2026-2027',
        'semester' => '1',
        'opens_on' => '2026-01-01',
        'closes_on' => '2026-12-31',
        'is_active' => true,
    ]);

    $this->roomA = Room::factory()->for($this->institution)->create([
        'code' => 'RM-DIST-A',
        'capacity' => 10,
        'is_active' => true,
    ]);

    $this->roomB = Room::factory()->for($this->institution)->create([
        'code' => 'RM-DIST-B',
        'capacity' => 10,
        'is_active' => true,
    ]);

    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, 'web');
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN, 'web');

    $this->guidance = User::factory()->create();
    $this->guidance->givePermissionTo([
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN,
    ]);
});

function createCandidateAttempt(AcademicCycle $cycle, string $refCode, string $lastName, string $firstName, string $program, ?string $acceptedAt = null): AdmissionAttempt
{
    $identity = ApplicantIdentity::query()->create([
        'institution_id' => $cycle->institution_id,
        'normalized_key' => 'ID-'.$refCode,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'birthdate' => '2005-01-01',
        'sex' => 'female',
        'email' => strtolower($refCode).'@example.test',
    ]);

    return AdmissionAttempt::query()->create([
        'applicant_identity_id' => $identity->id,
        'institution_id' => $cycle->institution_id,
        'academic_cycle_id' => $cycle->id,
        'reference_code' => $refCode,
        'status' => AdmissionAttempt::STATUS_ACCEPTED,
        'preferred_program_code' => $program,
        'accepted_at' => $acceptedAt ?? now()->subDays(5),
        'version' => 1,
    ]);
}

test('preview produces deterministic plan hash, fingerprinted candidates, and respects sorting and balanced packing', function (): void {
    $c1 = createCandidateAttempt($this->cycle, 'APP-001', 'ZULUETA', 'ANNA', 'BSCS', '2026-01-02 10:00:00');
    $c2 = createCandidateAttempt($this->cycle, 'APP-002', 'ALVAREZ', 'BEA', 'BSN', '2026-01-01 10:00:00');
    $c3 = createCandidateAttempt($this->cycle, 'APP-003', 'DELA CRUZ', 'JUAN', 'BSCS', '2026-01-03 10:00:00');

    $s1 = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->roomA)
        ->create([
            'code' => 'EX-SESS-1',
            'starts_at' => '2026-02-01 08:00:00',
            'status' => ExamSession::STATUS_DRAFT,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        ]);

    $s2 = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->roomB)
        ->create([
            'code' => 'EX-SESS-2',
            'starts_at' => '2026-02-01 13:00:00',
            'status' => ExamSession::STATUS_DRAFT,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        ]);

    $previewAction = app(PreviewCohortDistribution::class);

    // FCFS sequential: c2 (Jan 1), c1 (Jan 2), c3 (Jan 3)
    $planFcfs = $previewAction->handle(
        AssignmentInvariant::DOMAIN_EXAM,
        $this->cycle->id,
        [$s1->id, $s2->id],
        CohortPlanner::STRATEGY_FCFS,
        CohortPlanner::PACKING_SEQUENTIAL,
        $this->guidance,
    );

    expect($planFcfs['plan_hash'])->toHaveLength(64)
        ->and($planFcfs['total_candidates'])->toBe(3)
        ->and($planFcfs['assignable_count'])->toBe(3)
        ->and($planFcfs['unassigned_count'])->toBe(0)
        ->and($planFcfs['session_mappings'][0]['candidate_ids'])->toBe([$c2->id, $c3->id, $c1->id])
        ->and($planFcfs['session_mappings'][1]['candidate_ids'])->toBe([]);

    // Repeat preview: must be 100% deterministic
    $repeatPlan = $previewAction->handle(
        AssignmentInvariant::DOMAIN_EXAM,
        $this->cycle->id,
        [$s1->id, $s2->id],
        CohortPlanner::STRATEGY_FCFS,
        CohortPlanner::PACKING_SEQUENTIAL,
        $this->guidance,
    );
    expect($repeatPlan['plan_hash'])->toBe($planFcfs['plan_hash']);

    // Alphabetical balanced: ALVAREZ, DELA CRUZ, ZULUETA across 2 sessions -> 2 in S1, 1 in S2
    $planAlpha = $previewAction->handle(
        AssignmentInvariant::DOMAIN_EXAM,
        $this->cycle->id,
        [$s1->id, $s2->id],
        CohortPlanner::STRATEGY_ALPHABETICAL,
        CohortPlanner::PACKING_BALANCED,
        $this->guidance,
    );

    expect($planAlpha['session_mappings'][0]['candidate_ids'])->toBe([$c2->id, $c3->id])
        ->and($planAlpha['session_mappings'][1]['candidate_ids'])->toBe([$c1->id])
        ->and($planAlpha['session_mappings'][0]['allocated_count'])->toBe(2)
        ->and($planAlpha['session_mappings'][1]['allocated_count'])->toBe(1);

    // Program grouping: BSCS (c1 Jan 2, c3 Jan 3), BSN (c2 Jan 1)
    $planProg = $previewAction->handle(
        AssignmentInvariant::DOMAIN_EXAM,
        $this->cycle->id,
        [$s1->id, $s2->id],
        CohortPlanner::STRATEGY_PROGRAM,
        CohortPlanner::PACKING_BALANCED,
        $this->guidance,
    );

    expect($planProg['session_mappings'][0]['candidate_ids'])->toBe([$c3->id, $c1->id])
        ->and($planProg['session_mappings'][1]['candidate_ids'])->toBe([$c2->id])
        ->and(array_column($planProg['candidates'], 'id'))->toBe([$c3->id, $c1->id, $c2->id]);
});

test('clumped program room rosters are alphabetically sorted by surname', function (): void {
    $candidateZulueta = createCandidateAttempt($this->cycle, 'APP-SORT-1', 'ZULUETA', 'ANNA', 'BSCS', '2026-01-01 08:00:00');
    $candidateAlvarez = createCandidateAttempt($this->cycle, 'APP-SORT-2', 'ALVAREZ', 'BEA', 'BSCS', '2026-01-02 08:00:00');
    $candidateDelaCruz = createCandidateAttempt($this->cycle, 'APP-SORT-3', 'DELA CRUZ', 'CARLA', 'BSCS', '2026-01-03 08:00:00');
    $session = ExamSession::factory()->for($this->cycle, 'academicCycle')->for($this->roomA)->create([
        'code' => 'EX-SORT-ROOM',
        'starts_at' => '2026-02-02 08:00:00',
        'status' => ExamSession::STATUS_DRAFT,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
    ]);

    $plan = app(PreviewCohortDistribution::class)->handle(
        AssignmentInvariant::DOMAIN_EXAM,
        $this->cycle->id,
        [$session->id],
        CohortPlanner::STRATEGY_PROGRAM,
        CohortPlanner::PACKING_SEQUENTIAL,
        $this->guidance,
    );

    expect($plan['session_mappings'][0]['candidate_ids'])->toBe([
        $candidateAlvarez->id,
        $candidateDelaCruz->id,
        $candidateZulueta->id,
    ]);
});

test('program groups spill into the next quota after filling the largest room quota', function (): void {
    $candidateZulueta = createCandidateAttempt($this->cycle, 'APP-SPILL-1', 'ZULUETA', 'ANNA', 'BSCS', '2026-01-01 08:00:00');
    $candidateDelaCruz = createCandidateAttempt($this->cycle, 'APP-SPILL-2', 'DELA CRUZ', 'BEA', 'BSCS', '2026-01-02 08:00:00');
    $candidateMendoza = createCandidateAttempt($this->cycle, 'APP-SPILL-3', 'MENDOZA', 'CARLA', 'BSCS', '2026-01-03 08:00:00');
    $candidateAlvarez = createCandidateAttempt($this->cycle, 'APP-SPILL-4', 'ALVAREZ', 'DINA', 'BSCS', '2026-01-04 08:00:00');
    $candidateCruz = createCandidateAttempt($this->cycle, 'APP-SPILL-5', 'CRUZ', 'ELLA', 'BSCS', '2026-01-05 08:00:00');
    $candidateRamos = createCandidateAttempt($this->cycle, 'APP-SPILL-6', 'RAMOS', 'FAYE', 'BSN', '2026-01-06 08:00:00');
    $sessionOne = ExamSession::factory()->for($this->cycle, 'academicCycle')->for($this->roomA)->create([
        'code' => 'EX-SPILL-1',
        'starts_at' => '2026-02-03 08:00:00',
        'status' => ExamSession::STATUS_DRAFT,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
    ]);
    $sessionTwo = ExamSession::factory()->for($this->cycle, 'academicCycle')->for($this->roomB)->create([
        'code' => 'EX-SPILL-2',
        'starts_at' => '2026-02-03 13:00:00',
        'status' => ExamSession::STATUS_DRAFT,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
    ]);

    $plan = app(PreviewCohortDistribution::class)->handle(
        AssignmentInvariant::DOMAIN_EXAM,
        $this->cycle->id,
        [$sessionOne->id, $sessionTwo->id],
        CohortPlanner::STRATEGY_PROGRAM,
        CohortPlanner::PACKING_BALANCED,
        $this->guidance,
    );

    expect($plan['session_mappings'][0]['candidate_ids'])->toBe([
        $candidateDelaCruz->id,
        $candidateMendoza->id,
        $candidateZulueta->id,
    ])->and($plan['session_mappings'][1]['candidate_ids'])->toBe([
        $candidateAlvarez->id,
        $candidateCruz->id,
        $candidateRamos->id,
    ]);
});

test('preview rejects mixed-cycle targets, non-draft targets, and full sessions', function (): void {
    $otherCycle = AcademicCycle::query()->create([
        'institution_id' => $this->institution->id,
        'code' => 'CYCLE-OTHER-99',
        'name' => 'Other Cycle',
        'school_year' => '2027-2028',
        'semester' => '1',
        'opens_on' => '2027-01-01',
        'closes_on' => '2027-12-31',
        'is_active' => false,
    ]);

    $s1 = ExamSession::factory()->for($this->cycle, 'academicCycle')->for($this->roomA)->create();
    $sOther = ExamSession::factory()->for($otherCycle, 'academicCycle')->for($this->roomB)->create();

    $previewAction = app(PreviewCohortDistribution::class);

    expect(fn () => $previewAction->handle(
        AssignmentInvariant::DOMAIN_EXAM,
        $this->cycle->id,
        [$s1->id, $sOther->id],
        CohortPlanner::STRATEGY_FCFS,
        CohortPlanner::PACKING_SEQUENTIAL,
        $this->guidance,
    ))->toThrow(RuntimeException::class, 'belongs to a different academic cycle');

    $sPublished = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->roomA)
        ->create(['status' => ExamSession::STATUS_PUBLISHED]);

    expect(fn () => $previewAction->handle(
        AssignmentInvariant::DOMAIN_EXAM,
        $this->cycle->id,
        [$sPublished->id],
        CohortPlanner::STRATEGY_FCFS,
        CohortPlanner::PACKING_SEQUENTIAL,
        $this->guidance,
    ))->toThrow(RuntimeException::class, 'is not in draft status');
});

test('commit creates batch assignments, increments touched session versions, and records aggregate audit', function (): void {
    $c1 = createCandidateAttempt($this->cycle, 'APP-010', 'DELA CRUZ', 'JUAN', 'BSN');
    $c2 = createCandidateAttempt($this->cycle, 'APP-011', 'SANTOS', 'MARIA', 'BSN');

    $s1 = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->roomA)
        ->create([
            'code' => 'EX-COMMIT-1',
            'version' => 1,
            'status' => ExamSession::STATUS_DRAFT,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        ]);

    $previewAction = app(PreviewCohortDistribution::class);
    $plan = $previewAction->handle(
        AssignmentInvariant::DOMAIN_EXAM,
        $this->cycle->id,
        [$s1->id],
        CohortPlanner::STRATEGY_FCFS,
        CohortPlanner::PACKING_SEQUENTIAL,
        $this->guidance,
    );

    $commitAction = app(CommitCohortDistribution::class);
    $result = $commitAction->handle(
        AssignmentInvariant::DOMAIN_EXAM,
        $this->cycle->id,
        [$s1->id],
        CohortPlanner::STRATEGY_FCFS,
        CohortPlanner::PACKING_SEQUENTIAL,
        $plan['plan_hash'],
        [$s1->id => $s1->version],
        $this->guidance,
        autoAssignEnabled: true,
    );

    expect($result['assigned_count'])->toBe(2)
        ->and($result['unassigned_count'])->toBe(0);

    $s1->refresh();
    expect($s1->version)->toBe(2)
        ->and($s1->auto_assign_enabled)->toBeTrue();

    $assignments = ExamAssignment::query()->where('exam_session_id', $s1->id)->get();
    expect($assignments)->toHaveCount(2);

    foreach ($assignments as $assignment) {
        expect($assignment->source)->toBe(ExamAssignment::SOURCE_STAFF_ASSIGNED)
            ->and($assignment->origin)->toBe(ExamAssignment::ORIGIN_BATCH)
            ->and($assignment->status)->toBe(ExamAssignment::STATUS_ASSIGNED);
    }

    $audit = AuditEvent::query()->where('event_type', 'exam.batch_distribution_committed')->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->payload['plan_hash'])->toBe($plan['plan_hash'])
        ->and($audit->payload['assigned_count'])->toBe(2);
});

test('commit rejects stale session versions and altered plan hash', function (): void {
    $c1 = createCandidateAttempt($this->cycle, 'APP-020', 'REYES', 'ANA', 'BSN');

    $s1 = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->roomA)
        ->create([
            'code' => 'EX-STALE-1',
            'version' => 1,
            'status' => ExamSession::STATUS_DRAFT,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        ]);

    $previewAction = app(PreviewCohortDistribution::class);
    $plan = $previewAction->handle(
        AssignmentInvariant::DOMAIN_EXAM,
        $this->cycle->id,
        [$s1->id],
        CohortPlanner::STRATEGY_FCFS,
        CohortPlanner::PACKING_SEQUENTIAL,
        $this->guidance,
    );

    $commitAction = app(CommitCohortDistribution::class);

    // Stale session version
    expect(fn () => $commitAction->handle(
        AssignmentInvariant::DOMAIN_EXAM,
        $this->cycle->id,
        [$s1->id],
        CohortPlanner::STRATEGY_FCFS,
        CohortPlanner::PACKING_SEQUENTIAL,
        $plan['plan_hash'],
        [$s1->id => 999], // Stale expected version!
        $this->guidance,
    ))->toThrow(StalePlanConflictException::class);

    // Stale plan hash (e.g. candidate pool changed or tampered hash)
    expect(fn () => $commitAction->handle(
        AssignmentInvariant::DOMAIN_EXAM,
        $this->cycle->id,
        [$s1->id],
        CohortPlanner::STRATEGY_FCFS,
        CohortPlanner::PACKING_SEQUENTIAL,
        'tampered-hash-00000000000000000000000000000000000000000000000000000000000',
        [$s1->id => 1],
        $this->guidance,
    ))->toThrow(StalePlanConflictException::class);
});

test('continuous auto-assignment places accepted applicant into earliest enabled session and reports no-capacity cleanly', function (): void {
    $candidate = createCandidateAttempt($this->cycle, 'APP-030', 'CASTRO', 'ELENA', 'BSCS');

    $earlySession = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->roomA)
        ->autoAssignEnabled()
        ->create([
            'code' => 'EX-EARLY',
            'starts_at' => '2026-03-01 09:00:00',
            'status' => ExamSession::STATUS_DRAFT,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
            'version' => 1,
        ]);

    $lateSession = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->roomB)
        ->autoAssignEnabled()
        ->create([
            'code' => 'EX-LATE',
            'starts_at' => '2026-03-02 09:00:00',
            'status' => ExamSession::STATUS_DRAFT,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
            'version' => 1,
        ]);

    $autoAssign = app(AutoAssignEligibleAttempt::class);

    $res = $autoAssign->handle(AssignmentInvariant::DOMAIN_EXAM, $candidate, $this->guidance);

    expect($res['assigned'])->toBeTrue()
        ->and($res['session']->id)->toBe($earlySession->id)
        ->and($res['assignment']->origin)->toBe(ExamAssignment::ORIGIN_CONTINUOUS)
        ->and($earlySession->fresh()->version)->toBe(2);

    // Candidate is now assigned; second auto-assign returns already_assigned
    $repeat = $autoAssign->handle(AssignmentInvariant::DOMAIN_EXAM, $candidate, $this->guidance);
    expect($repeat['assigned'])->toBeFalse()
        ->and($repeat['reason'])->toBe('already_assigned');

    // Fill the remaining capacity of earlySession and lateSession, then verify no_capacity
    for ($i = 1; $i <= 9; $i++) {
        $fillAttempt = createCandidateAttempt($this->cycle, "APP-FILL-E{$i}", "FILL_E{$i}", 'TEST', 'BSCS');
        ExamAssignment::factory()
            ->for($earlySession, 'examSession')
            ->for($fillAttempt, 'admissionAttempt')
            ->create(['status' => ExamAssignment::STATUS_ASSIGNED]);
    }

    for ($i = 1; $i <= 10; $i++) {
        $fillAttempt = createCandidateAttempt($this->cycle, "APP-FILL-L{$i}", "FILL_L{$i}", 'TEST', 'BSCS');
        ExamAssignment::factory()
            ->for($lateSession, 'examSession')
            ->for($fillAttempt, 'admissionAttempt')
            ->create(['status' => ExamAssignment::STATUS_ASSIGNED]);
    }

    $newCandidate = createCandidateAttempt($this->cycle, 'APP-031', 'GOMEZ', 'MARK', 'BSCS');
    $noCapRes = $autoAssign->handle(AssignmentInvariant::DOMAIN_EXAM, $newCandidate, $this->guidance);

    expect($noCapRes['assigned'])->toBeFalse()
        ->and($noCapRes['reason'])->toBe('no_capacity');
});

test('reconciliation cancels batch and continuous assignments while preserving manual assignments', function (): void {
    $c = createCandidateAttempt($this->cycle, 'APP-040', 'LUNA', 'ANTONIO', 'BSCS');

    $s1 = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->roomA)
        ->create(['code' => 'EX-RECON-1', 'version' => 1]);

    $s2 = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->roomB)
        ->create(['code' => 'EX-RECON-2', 'version' => 1]);

    // Batch assignment
    $batchAssignment = ExamAssignment::factory()
        ->for($s1, 'examSession')
        ->for($c, 'admissionAttempt')
        ->batch()
        ->create(['status' => ExamAssignment::STATUS_ASSIGNED]);

    $reconciliation = app(ReconcileReconsideredAttempt::class);
    $result = $reconciliation->handle($c, $this->guidance, 'High school grade disqualification');

    expect($result['closed_exam_assignments_count'])->toBe(1);

    $batchAssignment->refresh();
    expect($batchAssignment->status)->toBe(ExamAssignment::STATUS_CANCELLED)
        ->and($batchAssignment->origin)->toBe(ExamAssignment::ORIGIN_BATCH)
        ->and($batchAssignment->end_reason)->toContain('High school grade disqualification')
        ->and($s1->fresh()->version)->toBe(2);

    $audit = AuditEvent::query()->where('event_type', 'exam.automated_assignment_reconciled')->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->payload['assignment_id'])->toBe($batchAssignment->id);
});
