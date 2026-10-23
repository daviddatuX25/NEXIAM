<?php

use App\Admission\Actions\AcceptAdmissionAttempts;
use App\Assessment\Actions\RecordScores;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CreateDirectAssessmentCohort;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Release\ResultDocument;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Release\Actions\PrepareResultDocument;
use App\ResultSheet\Actions\ConfirmPhysicalPrintAction;
use App\Scheduling\Actions\AutoAssignEligibleAttempt;
use App\Scheduling\AssignmentInvariant;
use App\Scheduling\CohortPlanner;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

require_once __DIR__.'/../../Support/NormConversionFixtures.php';

beforeEach(function (): void {
    $this->institution = Institution::query()->create([
        'code' => 'INST-AUTO-FUNC',
        'name' => 'Auto-Assign Functional Institution',
    ]);

    $this->cycle = AcademicCycle::query()->create([
        'institution_id' => $this->institution->id,
        'code' => 'CYCLE-AF-2026',
        'name' => 'Cycle Auto-Assign 2026',
        'school_year' => '2026-2027',
        'semester' => '1',
        'opens_on' => '2026-01-01',
        'closes_on' => '2026-12-31',
        'is_active' => true,
    ]);

    $this->room = Room::factory()->for($this->institution)->create([
        'code' => 'RM-AF-101',
        'capacity' => 10,
        'is_active' => true,
    ]);

    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, 'web');
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN, 'web');
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, 'web');
    Permission::findOrCreate(CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT, 'web');
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, 'web');
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_MANUAL, 'web');
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_RESULTS_PREPARE, 'web');

    $this->guidanceUser = User::factory()->create();
    $this->guidanceUser->givePermissionTo([
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN,
        CapabilityCatalog::GUIDANCE_RESULTS_RELEASE,
        CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT,
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
        CapabilityCatalog::GUIDANCE_RESULTS_PREPARE,
    ]);

    StaffProfile::query()->create([
        'user_id' => $this->guidanceUser->id,
        'institution_id' => $this->institution->id,
        'office' => 'guidance',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);

    StaffAuthenticationState::query()->firstOrCreate(
        ['user_id' => $this->guidanceUser->id],
        [
            'session_version' => 1,
            'two_factor_generation' => 1,
            'activation_completed_at' => now(),
            'password_set_at' => now(),
            'must_change_password' => false,
            'mfa_required' => false,
        ]
    );

    $this->unauthorizedUser = User::factory()->create();
    StaffProfile::query()->create([
        'user_id' => $this->unauthorizedUser->id,
        'institution_id' => $this->institution->id,
        'office' => 'registrar',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    StaffAuthenticationState::query()->create([
        'user_id' => $this->unauthorizedUser->id,
        'session_version' => 1,
        'two_factor_generation' => 'unauthorized-fixture',
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'must_change_password' => false,
        'mfa_required' => false,
    ]);
});

function actingAsStaffUser(TestCase $test, User $user): TestCase
{
    $state = StaffAuthenticationState::query()->firstOrCreate(
        ['user_id' => $user->id],
        [
            'session_version' => 1,
            'two_factor_generation' => 1,
            'activation_completed_at' => now(),
            'password_set_at' => now(),
            'must_change_password' => false,
            'mfa_required' => false,
        ]
    );

    return $test->actingAs($user)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
    ]);
}

function createFunctionalCandidate(
    AcademicCycle $cycle,
    string $refCode,
    string $status = AdmissionAttempt::STATUS_ACCEPTED
): AdmissionAttempt {
    $identity = ApplicantIdentity::query()->create([
        'institution_id' => $cycle->institution_id,
        'normalized_key' => 'ID-FUNC-'.$refCode,
        'first_name' => 'FIRST_'.$refCode,
        'last_name' => 'LAST_'.$refCode,
        'birthdate' => '2005-01-01',
        'sex' => 'female',
        'email' => strtolower($refCode).'@example.test',
    ]);

    return AdmissionAttempt::query()->create([
        'applicant_identity_id' => $identity->id,
        'institution_id' => $cycle->institution_id,
        'academic_cycle_id' => $cycle->id,
        'reference_code' => $refCode,
        'status' => $status,
        'preferred_program_code' => 'BSCS',
        'accepted_at' => $status === AdmissionAttempt::STATUS_ACCEPTED ? now()->subDay() : null,
        'version' => 1,
    ]);
}

function completeFunctionalLegacyScoringEvidence(AdmissionAttempt $candidate, ExamSession $session, User $actor): void
{
    $assignment = ExamAssignment::query()->where('exam_session_id', $session->id)
        ->where('admission_attempt_id', $candidate->id)->firstOrFail();
    $assignment->attendanceRecord()->create([
        'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
        'recorded_by_user_id' => $actor->id,
        'recorded_at' => now()->subHours(3),
        'idempotency_key' => 'functional-legacy-attendance-'.$assignment->id,
    ]);
    $assignment->submissionRecord()->create([
        'recorded_by_user_id' => $actor->id,
        'submitted_at' => now()->subHours(3),
        'idempotency_key' => 'functional-legacy-submission-'.$assignment->id,
    ]);
    $area = AptitudeArea::query()->firstOrCreate(['code' => 'SYN-FUNC-LEGACY'], [
        'name' => 'Synthetic historical count metric',
        'version' => 1,
        'scoring_method' => 'count_correct',
        'max_items' => 10,
        'is_active' => true,
    ]);
    ScoreRecord::query()->create([
        'grading_session_id' => $session->gradingSession->id,
        'admission_attempt_id' => $candidate->id,
        'aptitude_area_id' => $area->id,
        'score' => 8,
        'converted_score' => '8',
        'source' => ScoreRecord::SOURCE_MANUAL,
        'recorded_by_user_id' => $actor->id,
        'idempotency_key' => 'functional-legacy-score-'.$assignment->id,
    ]);
}

test('prepared-only examinees are excluded from continuous consultation assignment', function (string $printState): void {
    $candidate = createFunctionalCandidate($this->cycle, 'APP-PREPARED-ONLY');
    $examSession = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create(['status' => ExamSession::STATUS_COMPLETED]);

    ExamAssignment::factory()
        ->for($examSession, 'examSession')
        ->for($candidate, 'admissionAttempt')
        ->create(['status' => ExamAssignment::STATUS_ASSIGNED]);

    $grading = GradingSession::query()->create([
        'exam_session_id' => $examSession->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subDay(),
        'finalized_at' => now()->subHours(2),
    ]);
    completeFunctionalLegacyScoringEvidence($candidate, $examSession, $this->guidanceUser);

    $document = ResultDocument::query()->create([
        'admission_attempt_id' => $candidate->id,
        'template_version' => 'test-v1',
        'checksum' => str_repeat('c', 64),
        'status' => ResultDocument::STATUS_READY,
        'storage_path' => 'results/prepared-only.html',
    ]);
    $candidate->forceFill(['current_result_document_id' => $document->id])->save();

    if ($printState !== 'prepared_only') {
        if ($printState === 'unfrozen_legacy_scoring') {
            $grading->update(['status' => GradingSession::STATUS_OPEN, 'finalized_at' => null]);
        }
        $receipt = app(ConfirmPhysicalPrintAction::class)->confirmPrint($document, $this->guidanceUser, 'eligibility-print');
        if ($printState === 'corrected') {
            app(ConfirmPhysicalPrintAction::class)->correctPrintConfirmation($receipt, $this->guidanceUser, 'Paper was not printed', 'eligibility-correction');
        } elseif ($printState === 'predecessor') {
            $successor = $document->replicate(['checksum']);
            $successor->fill(['checksum' => str_repeat('d', 64), 'predecessor_id' => $document->id, 'version' => 2])->save();
            $candidate->forceFill(['current_result_document_id' => $successor->id])->save();
        }
    }

    $session = ConsultationSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'CS-PREPARED-ONLY',
            'status' => ConsultationSession::STATUS_DRAFT,
            'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
            'auto_assign_enabled' => true,
        ]);

    $result = app(AutoAssignEligibleAttempt::class)->handle(
        AssignmentInvariant::DOMAIN_CONSULTATION,
        $candidate,
        $this->guidanceUser,
    );

    expect($result['assigned'])->toBeFalse()
        ->and($result['reason'])->toBe('ineligible')
        ->and($session->assignments()->exists())->toBeFalse();
})->with(['prepared_only', 'unfrozen_legacy_scoring', 'corrected', 'predecessor']);

test('confirming the current physical print continuously assigns a finalized attempt once across retry', function (): void {
    $candidate = createFunctionalCandidate($this->cycle, 'APP-PRINT-AUTO');
    $examSession = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create(['status' => ExamSession::STATUS_COMPLETED]);
    ExamAssignment::factory()->for($examSession, 'examSession')
        ->for($candidate, 'admissionAttempt')->create(['status' => ExamAssignment::STATUS_ASSIGNED]);
    GradingSession::query()->create([
        'exam_session_id' => $examSession->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subDay(),
        'finalized_at' => now()->subHours(2),
    ]);
    completeFunctionalLegacyScoringEvidence($candidate, $examSession, $this->guidanceUser);
    $document = ResultDocument::query()->create([
        'admission_attempt_id' => $candidate->id,
        'template_version' => 'test-v1',
        'checksum' => str_repeat('e', 64),
        'status' => ResultDocument::STATUS_READY,
        'storage_path' => 'results/print-auto.html',
    ]);
    $candidate->forceFill(['current_result_document_id' => $document->id])->save();

    $session = ConsultationSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'CS-PRINT-AUTO',
            'status' => ConsultationSession::STATUS_DRAFT,
            'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
            'auto_assign_enabled' => true,
        ]);
    $action = app(ConfirmPhysicalPrintAction::class);
    $key = 'print-auto';

    expect(function () use ($action, $document, $key, $session): void {
        DB::transaction(function () use ($action, $document, $key, $session): void {
            $action->confirmPrint($document, $this->guidanceUser, $key);
            expect($session->assignments()->exists())->toBeFalse();
            throw new RuntimeException('Rollback physical print');
        });
    })->toThrow(RuntimeException::class, 'Rollback physical print');
    expect($document->fresh()->admissionAttempt->current_result_document_id)->toBe($document->id)
        ->and($session->assignments()->exists())->toBeFalse();

    $receipt = DB::transaction(function () use ($action, $document, $key, $session): ResultDocumentPrintReceipt {
        $receipt = $action->confirmPrint($document, $this->guidanceUser, $key);
        expect($session->assignments()->exists())->toBeFalse();

        return $receipt;
    });
    $retriedReceipt = $action->confirmPrint($document, $this->guidanceUser, $key);

    expect($receipt->id)->toBe($retriedReceipt->id)
        ->and($session->assignments()->where('admission_attempt_id', $candidate->id)->count())->toBe(1);
});

test('physical printing assigns an individually prepared applicant after commit while batch grading remains open', function (): void {
    $candidate = createFunctionalCandidate($this->cycle, 'APP-PRINT-FIRST');
    $profile = createTestApprovedProfileVersion($this, $this->guidanceUser, 'FUNCTIONAL-INDIVIDUAL-PRINT');
    $kit = createTestActiveKitVersion($this->guidanceUser, $profile);
    $created = app(CreateDirectAssessmentCohort::class)->handle(
        cycle: $this->cycle,
        attemptIds: [$candidate->id],
        actor: $this->guidanceUser,
        label: 'Synthetic open-batch physical print trigger',
        kitVersion: $kit,
    );
    $grading = $created['session']->gradingSession;
    app(RecordScores::class)->handle(
        gradingSession: $grading,
        attempt: $candidate,
        scores: [[
            'metric_code' => 'RAW_A',
            'typed_outputs' => ['percentile_rank' => '82', 'stanine' => '7'],
            'external_provenance' => 'Synthetic software-test final; no institutional result claim.',
        ]],
        actor: $this->guidanceUser,
        idempotencyKey: 'functional-individual-final',
        scoringMode: 'manual_converted',
        testKitVersionId: (int) $kit->id,
    );
    ResultSheetTemplate::query()->create([
        'name' => 'Synthetic functional result',
        'mode' => ResultSheetTemplate::MODE_HTML,
        'paper_size' => 'a4', 'orientation' => 'portrait', 'logical_unit' => 'full',
        'content' => '<p>{{applicant_name}}</p><table><tbody class="scores-rows-placeholder"></tbody></table>',
        'is_active' => true,
    ]);
    $document = app(PrepareResultDocument::class)->handle($candidate->fresh(), $this->guidanceUser);
    $session = ConsultationSession::factory()->for($this->cycle, 'academicCycle')->for($this->room)
        ->create(['auto_assign_enabled' => true]);

    DB::transaction(function () use ($document, $grading, $session): void {
        app(ConfirmPhysicalPrintAction::class)->confirmPrint($document, $this->guidanceUser, 'print-first-confirmation');
        expect($grading->fresh()->status)->toBe(GradingSession::STATUS_OPEN)
            ->and($session->assignments()->exists())->toBeFalse();
    });

    $assigned = $session->assignments()->sole();
    expect($assigned->admission_attempt_id)->toBe($candidate->id)
        ->and($assigned->origin)->toBe(ConsultationAssignment::ORIGIN_CONTINUOUS)
        ->and($grading->fresh()->status)->toBe(GradingSession::STATUS_OPEN);
});

test('confirmed physical print remains recorded when continuous consultation has no capacity', function (): void {
    $candidate = createFunctionalCandidate($this->cycle, 'APP-PRINT-NO-CAPACITY');
    $examSession = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create(['status' => ExamSession::STATUS_COMPLETED]);
    ExamAssignment::factory()->for($examSession, 'examSession')
        ->for($candidate, 'admissionAttempt')->create(['status' => ExamAssignment::STATUS_ASSIGNED]);
    GradingSession::query()->create([
        'exam_session_id' => $examSession->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subDay(),
        'finalized_at' => now()->subHours(2),
    ]);
    completeFunctionalLegacyScoringEvidence($candidate, $examSession, $this->guidanceUser);
    $document = ResultDocument::query()->create([
        'admission_attempt_id' => $candidate->id,
        'template_version' => 'test-v1',
        'checksum' => str_repeat('f', 64),
        'status' => ResultDocument::STATUS_READY,
        'storage_path' => 'results/print-no-capacity.html',
    ]);
    $candidate->forceFill(['current_result_document_id' => $document->id])->save();

    $smallRoom = Room::factory()->for($this->institution)->create([
        'code' => 'RM-PRINT-NO-CAPACITY',
        'capacity' => 1,
        'is_active' => true,
    ]);
    $session = ConsultationSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($smallRoom)
        ->create([
            'code' => 'CS-PRINT-NO-CAPACITY',
            'status' => ConsultationSession::STATUS_DRAFT,
            'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
            'auto_assign_enabled' => true,
        ]);
    $occupant = createFunctionalCandidate($this->cycle, 'APP-PRINT-OCCUPANT');
    $session->assignments()->create([
        'admission_attempt_id' => $occupant->id,
        'status' => ConsultationAssignment::STATUS_ASSIGNED,
        'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
        'origin' => ConsultationAssignment::ORIGIN_MANUAL,
        'version' => 1,
    ]);

    $receipt = app(ConfirmPhysicalPrintAction::class)->confirmPrint(
        $document,
        $this->guidanceUser,
        'print-no-capacity',
    );

    $allocation = app(AutoAssignEligibleAttempt::class)->handle(
        AssignmentInvariant::DOMAIN_CONSULTATION, $candidate->fresh(), $this->guidanceUser,
    );
    expect($receipt->is_current)->toBeTrue()
        ->and($allocation['reason'])->toBe('no_capacity')
        ->and($session->assignments()->where('admission_attempt_id', $candidate->id)->exists())->toBeFalse();
});

test('authorized guidance counselor can toggle auto-assign on exam and consultation draft staff-only sessions and unauthorized access is rejected', function (): void {
    $examSession = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'EX-TOGGLE-FUNC-1',
            'status' => ExamSession::STATUS_DRAFT,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
            'auto_assign_enabled' => false,
            'version' => 1,
        ]);

    $consSession = ConsultationSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'CS-TOGGLE-FUNC-1',
            'status' => ConsultationSession::STATUS_DRAFT,
            'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
            'auto_assign_enabled' => false,
            'version' => 1,
        ]);

    // 1. Unauthorized user receives 403
    $this->actingAs($this->unauthorizedUser)
        ->patch(route('exams.sessions.toggle-auto-assign', $examSession))
        ->assertForbidden();

    $this->actingAs($this->unauthorizedUser)
        ->patch(route('consultation.sessions.toggle-auto-assign', $consSession))
        ->assertForbidden();

    // 2. Authorized user toggles exam session from false to true and true to false
    actingAsStaffUser($this, $this->guidanceUser)
        ->from('/exams/sessions')
        ->patch(route('exams.sessions.toggle-auto-assign', $examSession))
        ->assertRedirect('/exams/sessions')
        ->assertSessionHas('status', 'Auto-assign updated.');

    $examSession->refresh();
    expect($examSession->auto_assign_enabled)->toBeTrue()
        ->and($examSession->version)->toBe(2);

    actingAsStaffUser($this, $this->guidanceUser)
        ->from('/exams/sessions')
        ->patch(route('exams.sessions.toggle-auto-assign', $examSession))
        ->assertRedirect('/exams/sessions')
        ->assertSessionHas('status', 'Auto-assign updated.');

    $examSession->refresh();
    expect($examSession->auto_assign_enabled)->toBeFalse()
        ->and($examSession->version)->toBe(3);

    // 3. Authorized user toggles consultation session from false to true and true to false
    actingAsStaffUser($this, $this->guidanceUser)
        ->from('/consultation/sessions')
        ->patch(route('consultation.sessions.toggle-auto-assign', $consSession))
        ->assertRedirect('/consultation/sessions')
        ->assertSessionHas('status', 'Auto-assign updated.');

    $consSession->refresh();
    expect($consSession->auto_assign_enabled)->toBeTrue()
        ->and($consSession->version)->toBe(2);

    actingAsStaffUser($this, $this->guidanceUser)
        ->from('/consultation/sessions')
        ->patch(route('consultation.sessions.toggle-auto-assign', $consSession))
        ->assertRedirect('/consultation/sessions')
        ->assertSessionHas('status', 'Auto-assign updated.');

    $consSession->refresh();
    expect($consSession->auto_assign_enabled)->toBeFalse()
        ->and($consSession->version)->toBe(3);

    // 4. Non-draft or non-staff_only exam session cannot be toggled
    $publishedExam = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'EX-PUB-NO-TOGGLE',
            'status' => ExamSession::STATUS_PUBLISHED,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
            'auto_assign_enabled' => false,
        ]);

    actingAsStaffUser($this, $this->guidanceUser)
        ->from('/exams/sessions')
        ->patch(route('exams.sessions.toggle-auto-assign', $publishedExam))
        ->assertRedirect('/exams/sessions')
        ->assertSessionHasErrors(['auto_assign']);

    expect($publishedExam->fresh()->auto_assign_enabled)->toBeFalse();

    $openExam = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'EX-OPEN-NO-TOGGLE',
            'status' => ExamSession::STATUS_DRAFT,
            'booking_mode' => ExamSession::BOOKING_OPEN_FOR_RESERVATION,
            'auto_assign_enabled' => false,
        ]);

    actingAsStaffUser($this, $this->guidanceUser)
        ->from('/exams/sessions')
        ->patch(route('exams.sessions.toggle-auto-assign', $openExam))
        ->assertRedirect('/exams/sessions')
        ->assertSessionHasErrors(['auto_assign']);

    expect($openExam->fresh()->auto_assign_enabled)->toBeFalse();

    // 5. Non-draft consultation session cannot be toggled
    $completedCons = ConsultationSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'CS-COMP-NO-TOGGLE',
            'status' => ConsultationSession::STATUS_COMPLETED,
            'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
            'auto_assign_enabled' => false,
        ]);

    actingAsStaffUser($this, $this->guidanceUser)
        ->from('/consultation/sessions')
        ->patch(route('consultation.sessions.toggle-auto-assign', $completedCons))
        ->assertRedirect('/consultation/sessions')
        ->assertSessionHasErrors(['auto_assign']);

    expect($completedCons->fresh()->auto_assign_enabled)->toBeFalse();
});

test('committing cohort distribution with auto_assign_enabled false preserves enabled target sessions', function (): void {
    $candidate = createFunctionalCandidate($this->cycle, 'APP-COMM-FALSE-1');

    $session = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'EX-COMM-DIS-1',
            'status' => ExamSession::STATUS_DRAFT,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
            'auto_assign_enabled' => true,
            'version' => 1,
        ]);

    actingAsStaffUser($this, $this->guidanceUser);

    $previewResponse = $this->postJson(route('exams.sessions.auto-distribute.preview'), [
        'academic_cycle_id' => $this->cycle->id,
        'target_session_ids' => [$session->id],
        'sort_strategy' => CohortPlanner::STRATEGY_FCFS,
        'packing_strategy' => CohortPlanner::PACKING_SEQUENTIAL,
    ])->assertOk();

    $plan = $previewResponse->json();

    $commitResponse = $this->postJson(route('exams.sessions.auto-distribute.commit'), [
        'academic_cycle_id' => $this->cycle->id,
        'target_session_ids' => [$session->id],
        'sort_strategy' => CohortPlanner::STRATEGY_FCFS,
        'packing_strategy' => CohortPlanner::PACKING_SEQUENTIAL,
        'expected_plan_hash' => $plan['plan_hash'],
        'expected_session_versions' => [$session->id => $session->version],
        'auto_assign_enabled' => false,
    ])->assertOk();

    expect($commitResponse->json('assigned_count'))->toBe(1)
        ->and($commitResponse->json('session_mappings.0.session_id'))->toBe($session->id)
        ->and($commitResponse->json('session_mappings.0.allocated_count'))->toBe(1);

    $session->refresh();
    expect($session->auto_assign_enabled)->toBeTrue()
        ->and($session->version)->toBe(2);
});

test('auto-assign does not send notifications to applicants when no sessions are enabled or when capacity is exhausted', function (): void {
    $candidate = createFunctionalCandidate($this->cycle, 'APP-NOTIF-01');

    // Case 1: An attempt is accepted, but no sessions have auto_assign_enabled = true
    $disabledSession = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'EX-NOTIF-DIS',
            'status' => ExamSession::STATUS_DRAFT,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
            'auto_assign_enabled' => false,
            'version' => 1,
        ]);

    $autoAssign = app(AutoAssignEligibleAttempt::class);
    $resultNoEnabled = $autoAssign->handle(AssignmentInvariant::DOMAIN_EXAM, $candidate, $this->guidanceUser);

    expect($resultNoEnabled['assigned'])->toBeFalse()
        ->and($resultNoEnabled['reason'])->toBe('no_capacity');

    // Assert: No scheduling notifications were dispatched for applicant or system
    expect(NotificationDelivery::query()->where('user_id', $candidate->applicant_identity_id)->count())->toBe(0)
        ->and(NotificationDelivery::query()->where('event_type', 'like', 'scheduling.%')->count())->toBe(0);

    // Case 2: Sessions have auto_assign_enabled = true, but are FULL
    $smallRoom = Room::factory()->for($this->institution)->create([
        'code' => 'RM-SMALL-2',
        'capacity' => 2,
        'is_active' => true,
    ]);

    $fullSession = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($smallRoom)
        ->create([
            'code' => 'EX-FULL-AUTO-1',
            'status' => ExamSession::STATUS_DRAFT,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
            'auto_assign_enabled' => true,
            'version' => 1,
        ]);

    // Fill the 2 slots
    for ($i = 1; $i <= 2; $i++) {
        $fillAttempt = createFunctionalCandidate($this->cycle, "APP-FILL-NOTIF-{$i}");
        ExamAssignment::factory()
            ->for($fullSession, 'examSession')
            ->for($fillAttempt, 'admissionAttempt')
            ->create(['status' => ExamAssignment::STATUS_ASSIGNED]);
    }

    $candidateTwo = createFunctionalCandidate($this->cycle, 'APP-NOTIF-02');
    $resultFull = $autoAssign->handle(AssignmentInvariant::DOMAIN_EXAM, $candidateTwo, $this->guidanceUser);

    expect($resultFull['assigned'])->toBeFalse()
        ->and($resultFull['reason'])->toBe('no_capacity');

    // Assert: Applicant receives no notification
    $applicantDeliveries = NotificationDelivery::query()
        ->where('recipient_type', 'applicant_identity')
        ->where('recipient_id', (string) $candidateTwo->applicant_identity_id)
        ->get();
    expect($applicantDeliveries)->toBeEmpty();

    // Assert: Operational intent is dispatched to Guidance staff, not applicant
    $staffDeliveries = NotificationDelivery::query()
        ->where('event_type', 'scheduling.exam.operational_no_capacity.v1')
        ->get();

    expect($staffDeliveries->isNotEmpty())->toBeTrue();
    foreach ($staffDeliveries as $delivery) {
        expect((int) $delivery->recipient_id)->toBe($this->guidanceUser->id);
    }
});

test('applicant with cancelled or inactive exam assignment on session A can be manually assigned to session B', function (): void {
    $sessionA = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'EX-SESS-A',
            'status' => ExamSession::STATUS_DRAFT,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
            'version' => 1,
        ]);

    $sessionB = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'EX-SESS-B',
            'status' => ExamSession::STATUS_DRAFT,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
            'version' => 1,
        ]);

    $candidate = createFunctionalCandidate($this->cycle, 'APP-PIPE-01');

    // Prior assignment on Session A exists but was cancelled / deactivated
    $cancelledAssignment = ExamAssignment::factory()
        ->for($sessionA, 'examSession')
        ->for($candidate, 'admissionAttempt')
        ->create([
            'status' => ExamAssignment::STATUS_CANCELLED,
            'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
            'origin' => ExamAssignment::ORIGIN_MANUAL,
        ]);

    expect($candidate->examAssignments()->count())->toBe(1)
        ->and(ExamAssignment::query()->active()->where('admission_attempt_id', $candidate->id)->exists())->toBeFalse();

    // Assigning to Session B must succeed and NOT throw "Attempt belongs to another exam session"
    $assignAction = app(AssignApplicantToExamSession::class);
    $newAssignment = $assignAction->handle($sessionB, $candidate, $this->guidanceUser);

    expect($newAssignment)->toBeInstanceOf(ExamAssignment::class)
        ->and($newAssignment->exam_session_id)->toBe($sessionB->id)
        ->and($newAssignment->status)->toBe(ExamAssignment::STATUS_ASSIGNED)
        ->and($candidate->examAssignments()->count())->toBe(2);

    expect(ExamAssignment::query()->active()->where('admission_attempt_id', $candidate->id)->count())->toBe(1)
        ->and(ExamAssignment::query()->active()->where('admission_attempt_id', $candidate->id)->value('exam_session_id'))->toBe($sessionB->id);
});

test('bulk acceptance in AcceptAdmissionAttempts triggers continuous auto-assign for accepted attempts into enabled sessions', function (): void {
    $session = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'EX-BULK-AUTO-1',
            'status' => ExamSession::STATUS_DRAFT,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
            'auto_assign_enabled' => true,
            'version' => 1,
        ]);

    $pendingOne = createFunctionalCandidate($this->cycle, 'APP-BULK-01', AdmissionAttempt::STATUS_PENDING);
    $pendingTwo = createFunctionalCandidate($this->cycle, 'APP-BULK-02', AdmissionAttempt::STATUS_PENDING);

    expect($pendingOne->status)->toBe(AdmissionAttempt::STATUS_PENDING)
        ->and($pendingTwo->status)->toBe(AdmissionAttempt::STATUS_PENDING);

    $bulkAccept = app(AcceptAdmissionAttempts::class);
    $result = $bulkAccept->handle(
        [$pendingOne->id, $pendingTwo->id],
        (int) $this->cycle->id,
        $this->guidanceUser
    );

    expect($result['ok'])->toBeTrue()
        ->and($result['accepted_count'])->toBe(2);

    $pendingOne->refresh();
    $pendingTwo->refresh();

    expect($pendingOne->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED)
        ->and($pendingTwo->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED);

    // Continuous auto-assign created ExamAssignment records into the enabled session
    $assignments = ExamAssignment::query()
        ->where('exam_session_id', $session->id)
        ->whereIn('admission_attempt_id', [$pendingOne->id, $pendingTwo->id])
        ->get();

    expect($assignments)->toHaveCount(2);

    foreach ($assignments as $assignment) {
        expect($assignment->origin)->toBe(ExamAssignment::ORIGIN_CONTINUOUS)
            ->and($assignment->status)->toBe(ExamAssignment::STATUS_ASSIGNED);
    }

    $session->refresh();
    expect($session->version)->toBeGreaterThan(1);
});
