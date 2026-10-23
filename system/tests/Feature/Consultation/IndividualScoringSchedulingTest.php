<?php

use App\Assessment\Actions\ClearScores;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Actions\AssignApplicantToConsultationSession;
use App\Consultation\Actions\ReserveConsultationSlot;
use App\Consultation\Queries\ConsultationSessionDetailQuery;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Room;
use App\Models\Release\ResultDocument;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\Release\Actions\PrepareResultDocument;
use App\ResultSheet\Actions\ConfirmPhysicalPrintAction;
use App\Scheduling\Actions\AutoAssignEligibleAttempt;
use App\Scheduling\Actions\CommitCohortDistribution;
use App\Scheduling\Actions\PreviewCohortDistribution;
use App\Scheduling\AssignmentInvariant;
use App\Scheduling\CohortPlanner;
use App\Scheduling\Exceptions\StalePlanConflictException;
use App\Scheduling\Queries\SchedulingWaitlistQuery;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/../../Support/IndividualReleaseReadinessFixtures.php';

beforeEach(function (): void {
    Storage::fake('local');
    seedE2Foundation();
    $this->staff = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_RESULTS_PREPARE, 'web');
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN, 'web');
    $this->staff->givePermissionTo([
        CapabilityCatalog::GUIDANCE_RESULTS_PREPARE,
        CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN,
    ]);
    $this->fixture = individualReleaseReadinessFixture($this, $this->staff, 'schedule');
    recordIndividualReleaseExternalFinal($this->fixture, 'complete', $this->staff);
});

test('a completely scored applicant can be manually scheduled before the open batch closes while an incomplete applicant remains blocked', function (): void {
    $exam = $this->fixture['session'];
    $room = Room::query()->findOrFail($exam->room_id);
    $consultationSession = ConsultationSession::query()->create([
        'academic_cycle_id' => $exam->academic_cycle_id,
        'room_id' => $room->id,
        'code' => 'REL-IND-'.uniqid(),
        'status' => ConsultationSession::STATUS_DRAFT,
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'scheduled_at' => now()->addDays(4),
        'version' => 1,
    ]);

    expect($this->fixture['grading_session']->status)->not->toBe('finalized');

    $assignment = app(AssignApplicantToConsultationSession::class)->handle(
        $consultationSession,
        $this->fixture['attempts']['complete'],
        $this->staff,
    );

    expect($assignment->admission_attempt_id)->toBe($this->fixture['attempts']['complete']->id)
        ->and(ConsultationAssignment::query()->where('admission_attempt_id', $this->fixture['attempts']['incomplete']->id)->count())->toBe(0);

    expect(fn () => app(AssignApplicantToConsultationSession::class)->handle(
        $consultationSession->fresh(),
        $this->fixture['attempts']['incomplete'],
        $this->staff,
    ))->toThrow(RuntimeException::class, 'complete individual scoring');
});

test('consultation waitlist read bucket includes individual scoring without a prepared document', function (): void {
    $attempt = $this->fixture['attempts']['complete'];
    expect($attempt->current_result_document_id)->toBeNull();

    $waitlist = app(SchedulingWaitlistQuery::class)->forDomain(
        'consultation',
        (int) $attempt->institution_id,
        (int) $attempt->academic_cycle_id,
        ['readiness' => 'ready'],
    );

    expect(collect($waitlist['applicants']['data'])->pluck('attempt_id')->all())->toContain((int) $attempt->id)
        ->and($waitlist['counts']['ready'])->toBe(1)
        ->and($waitlist['counts']['not_ready'])->toBe(1)
        ->and($waitlist['counts']['blockers']['awaiting_exam_scoring'])->toBe(1);
});
test('an individually scored applicant can self-book before document preparation', function (): void {
    $exam = $this->fixture['session'];
    $session = ConsultationSession::query()->create([
        'academic_cycle_id' => $exam->academic_cycle_id,
        'room_id' => $exam->room_id,
        'code' => 'REL-BOOK-'.uniqid(),
        'status' => ConsultationSession::STATUS_DRAFT,
        'booking_mode' => ConsultationSession::BOOKING_OPEN_FOR_RESERVATION,
        'scheduled_at' => now()->addDays(4),
        'version' => 1,
    ]);
    $attempt = $this->fixture['attempts']['complete'];

    $assignment = app(ReserveConsultationSlot::class)->handle(
        $session,
        $attempt,
        $this->staff,
        'individual-book-'.$attempt->id,
    );

    expect($attempt->current_result_document_id)->toBeNull()
        ->and($assignment->admission_attempt_id)->toBe($attempt->id);
});

test('clearing unfrozen scores preserves a held appointment and exposes its scoring blocker', function (): void {
    $exam = $this->fixture['session'];
    $session = ConsultationSession::query()->create([
        'academic_cycle_id' => $exam->academic_cycle_id,
        'room_id' => $exam->room_id,
        'code' => 'REL-HOLD-'.uniqid(),
        'status' => ConsultationSession::STATUS_DRAFT,
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'scheduled_at' => now()->addDays(4),
        'version' => 1,
    ]);
    $attempt = $this->fixture['attempts']['complete'];
    $assignment = app(AssignApplicantToConsultationSession::class)->handle($session, $attempt, $this->staff);

    app(ClearScores::class)->handle($this->fixture['grading_session'], $attempt, $this->staff);

    expect(ConsultationAssignment::query()->whereKey($assignment->id)->value('status'))->toBe(ConsultationAssignment::STATUS_ASSIGNED);
    $roster = app(ConsultationSessionDetailQuery::class)->forShow($session->fresh());
    expect($roster['assigned'][0]['scoring_blocker'])->not->toBeNull();

    recordIndividualReleaseExternalFinal($this->fixture, 'complete', $this->staff);
    $repairedRoster = app(ConsultationSessionDetailQuery::class)->forShow($session->fresh());
    expect($repairedRoster['assigned'][0]['scoring_blocker'])->toBeNull()
        ->and($assignment->fresh()->status)->toBe(ConsultationAssignment::STATUS_ASSIGNED)
        ->and($attempt->fresh()->current_result_document_id)->toBeNull()
        ->and($this->fixture['grading_session']->fresh()->status)->toBe(GradingSession::STATUS_OPEN);
});
test('current frozen and physically printed applicant is auto-assigned while the grading batch remains open', function (): void {
    $exam = $this->fixture['session'];
    $attempt = $this->fixture['attempts']['complete'];
    ResultSheetTemplate::query()->create([
        'name' => 'Individual automation result sheet',
        'mode' => ResultSheetTemplate::MODE_HTML,
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'content' => '<h1>{{applicant_name}}</h1><table><tbody class="scores-rows-placeholder"></tbody></table>',
        'is_active' => true,
    ]);
    $session = ConsultationSession::query()->create([
        'academic_cycle_id' => $exam->academic_cycle_id,
        'room_id' => $exam->room_id,
        'code' => 'REL-AUTO-'.uniqid(),
        'status' => ConsultationSession::STATUS_DRAFT,
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'auto_assign_enabled' => false,
        'scheduled_at' => now()->addDays(4),
        'version' => 1,
    ]);

    $document = app(PrepareResultDocument::class)->handle($attempt, $this->staff);
    $unprinted = app(AutoAssignEligibleAttempt::class)->handle(
        AssignmentInvariant::DOMAIN_CONSULTATION,
        $attempt->fresh(),
        $this->staff,
    );
    expect($unprinted['assigned'])->toBeFalse()
        ->and($unprinted['reason'])->toBe('ineligible')
        ->and($session->assignments()->exists())->toBeFalse();
    $receipt = app(ConfirmPhysicalPrintAction::class)->confirmPrint(
        $document,
        $this->staff,
        'individual-printed-auto-'.$attempt->id,
    );
    $session->update(['auto_assign_enabled' => true]);
    $result = app(AutoAssignEligibleAttempt::class)->handle(
        AssignmentInvariant::DOMAIN_CONSULTATION,
        $attempt->fresh(),
        $this->staff,
    );
    $assignment = ConsultationAssignment::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('consultation_session_id', $session->id)
        ->first();

    expect($result['assigned'])->toBeTrue();

    expect($this->fixture['grading_session']->fresh()->status)->not->toBe('finalized')
        ->and($document->fresh()->snapshot_payload_json['_assessment_result_set_id'])->toBeGreaterThan(0)
        ->and($receipt->is_current)->toBeTrue()
        ->and($assignment)->not->toBeNull();
});

test('automation rejects a current ready document pinned to another individual result set', function (): void {
    $exam = $this->fixture['session'];
    $attempt = $this->fixture['attempts']['complete'];
    ResultSheetTemplate::query()->create([
        'name' => 'Individual stale automation result sheet',
        'mode' => ResultSheetTemplate::MODE_HTML,
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'content' => '<h1>{{applicant_name}}</h1><table><tbody class="scores-rows-placeholder"></tbody></table>',
        'is_active' => true,
    ]);
    $document = app(PrepareResultDocument::class)->handle($attempt, $this->staff);
    app(ConfirmPhysicalPrintAction::class)->confirmPrint(
        $document,
        $this->staff,
        'individual-stale-auto-'.$attempt->id,
    );
    $document->refresh();
    $document->snapshot_payload_json = array_merge($document->snapshot_payload_json, [
        '_assessment_result_set_id' => -1,
    ]);
    $document->save();
    $session = ConsultationSession::query()->create([
        'academic_cycle_id' => $exam->academic_cycle_id,
        'room_id' => $exam->room_id,
        'code' => 'REL-STALE-'.uniqid(),
        'status' => ConsultationSession::STATUS_DRAFT,
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'auto_assign_enabled' => true,
        'scheduled_at' => now()->addDays(4),
        'version' => 1,
    ]);

    $result = app(AutoAssignEligibleAttempt::class)->handle(
        AssignmentInvariant::DOMAIN_CONSULTATION,
        $attempt->fresh(),
        $this->staff,
    );

    expect($result['assigned'])->toBeFalse()
        ->and($result['reason'])->toBe('ineligible')
        ->and($session->assignments()->exists())->toBeFalse();
});
test('a reviewed automation plan is rejected when the pinned current document changes', function (): void {
    $exam = $this->fixture['session'];
    $attempt = $this->fixture['attempts']['complete'];
    ResultSheetTemplate::query()->create([
        'name' => 'Individual stale plan result sheet',
        'mode' => ResultSheetTemplate::MODE_HTML,
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'content' => '<h1>{{applicant_name}}</h1><table><tbody class="scores-rows-placeholder"></tbody></table>',
        'is_active' => true,
    ]);
    $document = app(PrepareResultDocument::class)->handle($attempt, $this->staff);
    app(ConfirmPhysicalPrintAction::class)->confirmPrint(
        $document,
        $this->staff,
        'individual-stale-plan-print-'.$attempt->id,
    );
    $session = ConsultationSession::query()->create([
        'academic_cycle_id' => $exam->academic_cycle_id,
        'room_id' => $exam->room_id,
        'code' => 'REL-PLAN-'.uniqid(),
        'status' => ConsultationSession::STATUS_DRAFT,
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'scheduled_at' => now()->addDays(4),
        'version' => 1,
    ]);
    $plan = app(PreviewCohortDistribution::class)->handle(
        AssignmentInvariant::DOMAIN_CONSULTATION,
        (int) $exam->academic_cycle_id,
        [(int) $session->id],
        CohortPlanner::STRATEGY_FCFS,
        CohortPlanner::PACKING_SEQUENTIAL,
        $this->staff,
    );
    $document->update(['checksum' => str_repeat('f', 64)]);

    expect(fn () => app(CommitCohortDistribution::class)->handle(
        AssignmentInvariant::DOMAIN_CONSULTATION,
        (int) $exam->academic_cycle_id,
        [(int) $session->id],
        CohortPlanner::STRATEGY_FCFS,
        CohortPlanner::PACKING_SEQUENTIAL,
        $plan['plan_hash'],
        [(int) $session->id => (int) $session->version],
        $this->staff,
    ))->toThrow(StalePlanConflictException::class);

    expect($session->assignments()->exists())->toBeFalse()
        ->and($this->fixture['grading_session']->fresh()->status)->not->toBe('finalized');
});
test('complete finalized legacy ledger evidence remains eligible for printed automation without a fabricated result set', function (): void {
    $cycle = $this->fixture['session']->academicCycle;
    $room = Room::query()->findOrFail($this->fixture['session']->room_id);
    $referenceCode = 'LEGACY-AUTO-'.uniqid();
    $identity = ApplicantIdentity::query()->create([
        'institution_id' => $cycle->institution_id,
        'normalized_key' => strtolower($referenceCode),
        'first_name' => 'Legacy',
        'last_name' => 'Scoring',
        'birthdate' => '2005-01-01',
        'sex' => 'unspecified',
        'email' => strtolower($referenceCode).'@example.test',
    ]);
    $attempt = AdmissionAttempt::query()->create([
        'applicant_identity_id' => $identity->id,
        'institution_id' => $cycle->institution_id,
        'academic_cycle_id' => $cycle->id,
        'reference_code' => $referenceCode,
        'status' => AdmissionAttempt::STATUS_ACCEPTED,
        'accepted_at' => now(),
        'payload' => ['first_name' => 'Legacy', 'last_name' => 'Scoring'],
        'version' => 1,
    ]);
    $examSession = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-LEGACY-'.uniqid(),
        'status' => ExamSession::STATUS_COMPLETED,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        'starts_at' => now()->subDays(5),
        'effective_end_at' => now()->subDays(5)->addHours(2),
        'closed_at' => now()->subDays(5),
        'version' => 1,
        'lock_version' => 1,
    ]);
    $examAssignment = ExamAssignment::query()->create([
        'exam_session_id' => $examSession->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
        'assigned_at' => now()->subDays(5),
        'assigned_by_user_id' => $this->staff->id,
    ]);
    ExamAttendanceRecord::query()->create([
        'exam_assignment_id' => $examAssignment->id,
        'recorded_by_user_id' => $this->staff->id,
        'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
        'recorded_at' => now()->subDays(5),
        'idempotency_key' => 'legacy-auto-attendance-'.$examAssignment->id,
    ]);
    ExamSubmissionRecord::query()->create([
        'exam_assignment_id' => $examAssignment->id,
        'recorded_by_user_id' => $this->staff->id,
        'submitted_at' => now()->subDays(5),
        'idempotency_key' => 'legacy-auto-submission-'.$examAssignment->id,
    ]);
    $grading = GradingSession::query()->create([
        'exam_session_id' => $examSession->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subDays(4),
        'finalized_at' => now()->subDays(3),
        'finalized_by_user_id' => $this->staff->id,
        'version' => 1,
    ]);
    foreach (AptitudeArea::query()->where('is_active', true)->with('conversionRows')->get() as $area) {
        $score = (int) floor((int) $area->max_items * 0.8);
        ScoreRecord::query()->create([
            'grading_session_id' => $grading->id,
            'admission_attempt_id' => $attempt->id,
            'aptitude_area_id' => $area->id,
            'score' => $score,
            'converted_score' => $area->conversionRows->firstWhere('min_score', '<=', $score)?->output_value,
            'source' => ScoreRecord::SOURCE_MANUAL,
            'recorded_by_user_id' => $this->staff->id,
            'idempotency_key' => 'legacy-auto-score-'.$examAssignment->id.'-'.$area->id,
        ]);
    }
    $document = ResultDocument::query()->create([
        'admission_attempt_id' => $attempt->id,
        'template_version' => 'legacy-v1',
        'checksum' => hash('sha256', 'legacy-prepared-'.$attempt->id),
        'status' => ResultDocument::STATUS_READY,
        'storage_path' => 'results/legacy-'.$attempt->id.'.html',
        'version' => 1,
        'snapshot_payload_json' => ['legacy_ledger' => true],
    ]);
    $attempt->forceFill(['current_result_document_id' => $document->id])->save();
    $receipt = app(ConfirmPhysicalPrintAction::class)->confirmPrint(
        $document,
        $this->staff,
        'legacy-auto-print-'.$attempt->id,
    );
    $session = ConsultationSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'REL-LEGACY-'.uniqid(),
        'status' => ConsultationSession::STATUS_DRAFT,
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'auto_assign_enabled' => true,
        'scheduled_at' => now()->addDays(4),
        'version' => 1,
    ]);

    $plan = app(PreviewCohortDistribution::class)->handle(
        AssignmentInvariant::DOMAIN_CONSULTATION,
        (int) $cycle->id,
        [(int) $session->id],
        CohortPlanner::STRATEGY_FCFS,
        CohortPlanner::PACKING_SEQUENTIAL,
        $this->staff,
    );
    AptitudeArea::query()->where('is_active', true)->firstOrFail()->increment('max_items');
    expect(fn () => app(CommitCohortDistribution::class)->handle(
        AssignmentInvariant::DOMAIN_CONSULTATION,
        (int) $cycle->id,
        [(int) $session->id],
        CohortPlanner::STRATEGY_FCFS,
        CohortPlanner::PACKING_SEQUENTIAL,
        $plan['plan_hash'],
        [(int) $session->id => (int) $session->version],
        $this->staff,
    ))->toThrow(StalePlanConflictException::class);

    $details = app(AssignmentInvariant::class)->consultationResultReadyDetails($attempt->fresh());
    $allocation = app(AutoAssignEligibleAttempt::class)->handle(
        AssignmentInvariant::DOMAIN_CONSULTATION,
        $attempt->fresh(),
        $this->staff,
    );

    expect($details['score_evidence_type'])->toBe('legacy_ledger')
        ->and($details['result_set_id'])->toBeNull()
        ->and($receipt->is_current)->toBeTrue()
        ->and($allocation['assigned'])->toBeTrue()
        ->and($session->assignments()->where('admission_attempt_id', $attempt->id)->exists())->toBeTrue();
});

test('the session ready pool serializes the first eligible applicant as a list after earlier incomplete applicants', function (): void {
    $this->withoutVite();
    app(ClearScores::class)->handle($this->fixture['grading_session'], $this->fixture['attempts']['complete'], $this->staff);
    recordIndividualReleaseExternalFinal($this->fixture, 'incomplete', $this->staff);
    $exam = $this->fixture['session'];
    $session = ConsultationSession::query()->create([
        'academic_cycle_id' => $exam->academic_cycle_id,
        'room_id' => $exam->room_id,
        'code' => 'REL-READY-LIST',
        'status' => ConsultationSession::STATUS_DRAFT,
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'scheduled_at' => now()->addDays(4),
        'version' => 1,
    ]);

    $this->actingAs($this->staff)->get(route('consultation.sessions.show', $session))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('ready_pool', 1)
            ->where('ready_pool.0.id', $this->fixture['attempts']['incomplete']->id));
});
