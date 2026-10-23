<?php

use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Assessment\GradingSession;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Release\ResultDocument;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Models\StaffProfile;
use App\Models\User;
use App\ResultSheet\Actions\ConfirmPhysicalPrintAction;
use App\Scheduling\CandidatePoolQuery;
use App\Scheduling\CohortPlanner;
use Database\Seeders\CohortDistributionDemoSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->institution = Institution::query()->create([
        'code' => 'HTTP-INST-DIST',
        'name' => 'HTTP Distribution Institution',
    ]);

    $this->cycle = AcademicCycle::query()->create([
        'institution_id' => $this->institution->id,
        'code' => 'CYCLE-HTTP-2026',
        'name' => 'Cycle HTTP 2026',
        'school_year' => '2026-2027',
        'semester' => '1',
        'opens_on' => '2026-01-01',
        'closes_on' => '2026-12-31',
        'is_active' => true,
    ]);

    $this->room = Room::factory()->for($this->institution)->create([
        'code' => 'RM-HTTP-101',
        'capacity' => 20,
        'is_active' => true,
    ]);

    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, 'web');
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN, 'web');
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, 'web');

    $this->authorizedUser = User::factory()->create();
    $this->authorizedUser->givePermissionTo([
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN,
        CapabilityCatalog::GUIDANCE_RESULTS_RELEASE,
    ]);
    StaffProfile::query()->create([
        'user_id' => $this->authorizedUser->id,
        'institution_id' => $this->institution->id,
        'office' => 'guidance',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    StaffAuthenticationState::query()->create([
        'user_id' => $this->authorizedUser->id,
        'session_version' => 1,
        'two_factor_generation' => (string) Str::uuid(),
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'must_change_password' => false,
        'mfa_required' => false,
    ]);

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

function createHttpCandidate(AcademicCycle $cycle, string $refCode, string $lastName, string $firstName, string $program): AdmissionAttempt
{
    $identity = ApplicantIdentity::query()->create([
        'institution_id' => $cycle->institution_id,
        'normalized_key' => 'ID-HTTP-'.$refCode,
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
        'accepted_at' => now()->subDays(2),
        'version' => 1,
    ]);
}

test('preview and commit endpoints strictly enforce domain capabilities', function (): void {
    $this->actingAs($this->unauthorizedUser);

    $this->postJson(route('exams.sessions.auto-distribute.preview'), [
        'academic_cycle_id' => $this->cycle->id,
        'target_session_ids' => [1],
        'sort_strategy' => CohortPlanner::STRATEGY_FCFS,
        'packing_strategy' => CohortPlanner::PACKING_SEQUENTIAL,
    ])->assertForbidden();

    $this->postJson(route('exams.sessions.auto-distribute.commit'), [
        'academic_cycle_id' => $this->cycle->id,
        'target_session_ids' => [1],
        'sort_strategy' => CohortPlanner::STRATEGY_FCFS,
        'packing_strategy' => CohortPlanner::PACKING_SEQUENTIAL,
        'expected_plan_hash' => str_repeat('a', 64),
        'expected_session_versions' => [1 => 1],
    ])->assertForbidden();

    $this->postJson(route('consultation.sessions.auto-distribute.preview'), [
        'academic_cycle_id' => $this->cycle->id,
        'target_session_ids' => [1],
        'sort_strategy' => CohortPlanner::STRATEGY_FCFS,
        'packing_strategy' => CohortPlanner::PACKING_SEQUENTIAL,
    ])->assertForbidden();

    $this->postJson(route('consultation.sessions.auto-distribute.commit'), [
        'academic_cycle_id' => $this->cycle->id,
        'target_session_ids' => [1],
        'sort_strategy' => CohortPlanner::STRATEGY_FCFS,
        'packing_strategy' => CohortPlanner::PACKING_SEQUENTIAL,
        'expected_plan_hash' => str_repeat('a', 64),
        'expected_session_versions' => [1 => 1],
    ])->assertForbidden();
});

test('exam distribution HTTP flow previews deterministically and commits assignments atomically', function (): void {
    $c1 = createHttpCandidate($this->cycle, 'APP-H01', 'DELA CRUZ', 'JUAN', 'BSN');
    $c2 = createHttpCandidate($this->cycle, 'APP-H02', 'SANTOS', 'MARIA', 'BSN');

    $session = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'EX-HTTP-SESS-1',
            'version' => 1,
            'status' => ExamSession::STATUS_DRAFT,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        ]);

    $this->actingAs($this->authorizedUser);

    // Preview
    $previewResponse = $this->postJson(route('exams.sessions.auto-distribute.preview'), [
        'academic_cycle_id' => $this->cycle->id,
        'target_session_ids' => [$session->id],
        'sort_strategy' => CohortPlanner::STRATEGY_FCFS,
        'packing_strategy' => CohortPlanner::PACKING_SEQUENTIAL,
    ])->assertOk();

    $plan = $previewResponse->json();
    expect($plan['plan_hash'])->toHaveLength(64)
        ->and($plan['assignable_count'])->toBe(2)
        ->and($plan['session_mappings'][0]['allocated_count'])->toBe(2);

    // Commit
    $commitResponse = $this->postJson(route('exams.sessions.auto-distribute.commit'), [
        'academic_cycle_id' => $this->cycle->id,
        'target_session_ids' => [$session->id],
        'sort_strategy' => CohortPlanner::STRATEGY_FCFS,
        'packing_strategy' => CohortPlanner::PACKING_SEQUENTIAL,
        'expected_plan_hash' => $plan['plan_hash'],
        'expected_session_versions' => [$session->id => $session->version],
        'auto_assign_enabled' => true,
    ])->assertOk();

    expect($commitResponse->json('assigned_count'))->toBe(2);

    $session->refresh();
    expect($session->version)->toBe(2)
        ->and($session->auto_assign_enabled)->toBeTrue();

    expect(ExamAssignment::query()->where('exam_session_id', $session->id)->count())->toBe(2);
});

test('commit returns 409 conflict when session version is stale', function (): void {
    $c1 = createHttpCandidate($this->cycle, 'APP-H03', 'REYES', 'ANA', 'BSCS');

    $session = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'EX-HTTP-STALE-1',
            'version' => 1,
            'status' => ExamSession::STATUS_DRAFT,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        ]);

    $this->actingAs($this->authorizedUser);

    $previewResponse = $this->postJson(route('exams.sessions.auto-distribute.preview'), [
        'academic_cycle_id' => $this->cycle->id,
        'target_session_ids' => [$session->id],
        'sort_strategy' => CohortPlanner::STRATEGY_FCFS,
        'packing_strategy' => CohortPlanner::PACKING_SEQUENTIAL,
    ])->assertOk();

    $plan = $previewResponse->json();

    // Bump session version behind its back
    $session->increment('version');

    $conflictResponse = $this->postJson(route('exams.sessions.auto-distribute.commit'), [
        'academic_cycle_id' => $this->cycle->id,
        'target_session_ids' => [$session->id],
        'sort_strategy' => CohortPlanner::STRATEGY_FCFS,
        'packing_strategy' => CohortPlanner::PACKING_SEQUENTIAL,
        'expected_plan_hash' => $plan['plan_hash'],
        'expected_session_versions' => [$session->id => 1], // Old version!
    ])->assertStatus(409);

    expect($conflictResponse->json('error'))->toBe('stale_plan');
});

test('consultation distribution excludes prepared-only examinees from preview', function (): void {
    $candidate = createHttpCandidate($this->cycle, 'APP-CONS-01', 'BAUTISTA', 'CLARA', 'BSN');

    $examSession = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create(['status' => ExamSession::STATUS_COMPLETED]);

    ExamAssignment::factory()
        ->for($examSession, 'examSession')
        ->for($candidate, 'admissionAttempt')
        ->create(['status' => ExamAssignment::STATUS_ASSIGNED]);

    GradingSession::query()->create([
        'exam_session_id' => $examSession->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subDay(),
        'finalized_at' => now()->subHours(5),
    ]);

    $document = ResultDocument::query()->create([
        'admission_attempt_id' => $candidate->id,
        'template_version' => 'test-v1',
        'checksum' => str_repeat('b', 64),
        'status' => ResultDocument::STATUS_READY,
        'storage_path' => 'results/test.html',
    ]);
    $candidate->forceFill(['current_result_document_id' => $document->id])->save();

    $consSession = ConsultationSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'CS-HTTP-1',
            'version' => 1,
            'status' => ConsultationSession::STATUS_DRAFT,
            'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        ]);

    $this->actingAs($this->authorizedUser);

    $previewResponse = $this->postJson(route('consultation.sessions.auto-distribute.preview'), [
        'academic_cycle_id' => $this->cycle->id,
        'target_session_ids' => [$consSession->id],
        'sort_strategy' => CohortPlanner::STRATEGY_FCFS,
        'packing_strategy' => CohortPlanner::PACKING_SEQUENTIAL,
    ])->assertOk();

    expect($previewResponse->json('assignable_count'))->toBe(0)
        ->and(ConsultationAssignment::query()->where('consultation_session_id', $consSession->id)->exists())->toBeFalse();
});

test('consultation distribution includes finalized examinees after their current document is physically printed', function (): void {
    $candidate = createHttpCandidate($this->cycle, 'APP-CONS-PRINTED', 'BAUTISTA', 'CLARA', 'BSN');
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
        'finalized_at' => now()->subHours(5),
    ]);
    $document = ResultDocument::query()->create([
        'admission_attempt_id' => $candidate->id,
        'template_version' => 'test-v1',
        'checksum' => str_repeat('d', 64),
        'status' => ResultDocument::STATUS_READY,
        'storage_path' => 'results/printed.html',
    ]);
    $candidate->forceFill(['current_result_document_id' => $document->id])->save();
    $receipt = app(ConfirmPhysicalPrintAction::class)->confirmPrint($document, $this->authorizedUser, 'http-printed');

    $consSession = ConsultationSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'CS-HTTP-PRINTED',
            'version' => 1,
            'status' => ConsultationSession::STATUS_DRAFT,
            'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        ]);

    $this->actingAs($this->authorizedUser);
    $previewResponse = $this->postJson(route('consultation.sessions.auto-distribute.preview'), [
        'academic_cycle_id' => $this->cycle->id,
        'target_session_ids' => [$consSession->id],
        'sort_strategy' => CohortPlanner::STRATEGY_FCFS,
        'packing_strategy' => CohortPlanner::PACKING_SEQUENTIAL,
    ])->assertOk()->assertJsonPath('assignable_count', 1);
    $plan = $previewResponse->json();

    $replacementReceipt = app(ConfirmPhysicalPrintAction::class)->confirmPrint(
        $document,
        $this->authorizedUser,
        'http-reprint',
    );
    $this->postJson(route('consultation.sessions.auto-distribute.commit'), [
        'academic_cycle_id' => $this->cycle->id,
        'target_session_ids' => [$consSession->id],
        'sort_strategy' => CohortPlanner::STRATEGY_FCFS,
        'packing_strategy' => CohortPlanner::PACKING_SEQUENTIAL,
        'expected_plan_hash' => $plan['plan_hash'],
        'expected_session_versions' => [$consSession->id => $consSession->version],
    ])->assertStatus(409);

    $replacementPlan = $this->postJson(route('consultation.sessions.auto-distribute.preview'), [
        'academic_cycle_id' => $this->cycle->id,
        'target_session_ids' => [$consSession->id],
        'sort_strategy' => CohortPlanner::STRATEGY_FCFS,
        'packing_strategy' => CohortPlanner::PACKING_SEQUENTIAL,
    ])->assertOk()->assertJsonPath('assignable_count', 1)->json();

    app(ConfirmPhysicalPrintAction::class)->correctPrintConfirmation(
        $replacementReceipt,
        $this->authorizedUser,
        'Test correction invalidates print evidence',
        'http-corrected',
    );
    $this->postJson(route('consultation.sessions.auto-distribute.commit'), [
        'academic_cycle_id' => $this->cycle->id,
        'target_session_ids' => [$consSession->id],
        'sort_strategy' => CohortPlanner::STRATEGY_FCFS,
        'packing_strategy' => CohortPlanner::PACKING_SEQUENTIAL,
        'expected_plan_hash' => $replacementPlan['plan_hash'],
        'expected_session_versions' => [$consSession->id => $consSession->version],
    ])->assertStatus(409);

    $this->postJson(route('consultation.sessions.auto-distribute.preview'), [
        'academic_cycle_id' => $this->cycle->id,
        'target_session_ids' => [$consSession->id],
        'sort_strategy' => CohortPlanner::STRATEGY_FCFS,
        'packing_strategy' => CohortPlanner::PACKING_SEQUENTIAL,
    ])->assertOk()->assertJsonPath('assignable_count', 0);

    expect(ResultDocumentPrintReceipt::query()->whereKey($receipt->id)->value('is_current'))->toBeFalse()
        ->and(ResultDocumentPrintReceipt::query()->whereKey($replacementReceipt->id)->value('is_current'))->toBeFalse();

    $action = app(ConfirmPhysicalPrintAction::class);
    $action->confirmPrint($document, $this->authorizedUser, 'http-restored');
    $successor = $document->replicate(['checksum']);
    $successor->fill(['checksum' => str_repeat('e', 64), 'predecessor_id' => $document->id, 'version' => 2])->save();
    $candidate->forceFill(['current_result_document_id' => $successor->id])->save();

    $this->postJson(route('consultation.sessions.auto-distribute.preview'), [
        'academic_cycle_id' => $this->cycle->id,
        'target_session_ids' => [$consSession->id],
        'sort_strategy' => CohortPlanner::STRATEGY_FCFS,
        'packing_strategy' => CohortPlanner::PACKING_SEQUENTIAL,
    ])->assertOk()->assertJsonPath('assignable_count', 0);

    $action->confirmPrint($successor, $this->authorizedUser, 'http-successor');
    $printedPlan = $this->postJson(route('consultation.sessions.auto-distribute.preview'), [
        'academic_cycle_id' => $this->cycle->id,
        'target_session_ids' => [$consSession->id],
        'sort_strategy' => CohortPlanner::STRATEGY_FCFS,
        'packing_strategy' => CohortPlanner::PACKING_SEQUENTIAL,
    ])->assertOk()->assertJsonPath('assignable_count', 1)->json();
    $this->postJson(route('consultation.sessions.auto-distribute.commit'), [
        'academic_cycle_id' => $this->cycle->id,
        'target_session_ids' => [$consSession->id],
        'sort_strategy' => CohortPlanner::STRATEGY_FCFS,
        'packing_strategy' => CohortPlanner::PACKING_SEQUENTIAL,
        'expected_plan_hash' => $printedPlan['plan_hash'],
        'expected_session_versions' => [$consSession->id => $consSession->version],
    ])->assertOk()->assertJsonPath('assigned_count', 1);
    $assignment = $consSession->assignments()->sole();
    expect($assignment->admission_attempt_id)->toBe($candidate->id)
        ->and($assignment->origin)->toBe(ConsultationAssignment::ORIGIN_BATCH);
});

test('scheduling index pages return distribution metadata in props', function (): void {
    $this->actingAs($this->authorizedUser);

    $examRes = $this->get(route('exams.sessions.index'))->assertOk();
    expect($examRes->viewData('page')['props']['distribution'])->toHaveKeys([
        'pool_count',
        'eligible_capacity',
        'eligible_sessions_count',
        'active_cycle_id',
    ]);

    $consRes = $this->get(route('consultation.sessions.index'))->assertOk();
    expect($consRes->viewData('page')['props']['distribution'])->toHaveKeys([
        'pool_count',
        'eligible_capacity',
        'eligible_sessions_count',
        'active_cycle_id',
    ]);
});

test('cohort distribution demo seeder keeps prepared-only consultation attempts out of the pool', function (): void {
    $seeder = new CohortDistributionDemoSeeder;
    $seeder->run();

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    // 10 unassigned exam candidates
    $examPool = app(CandidatePoolQuery::class)->forExam($cycle->id, (int) $cycle->institution_id);
    expect($examPool->count())->toBeGreaterThanOrEqual(10);

    // These synthetic candidates have no fabricated physical-print evidence.
    $consultationPool = app(CandidatePoolQuery::class)->forConsultation($cycle->id, (int) $cycle->institution_id);
    expect($consultationPool)->toBeEmpty();

    // Target sessions
    expect(ExamSession::query()->where('code', 'EX-DIST-001')->value('auto_assign_enabled'))->toBeTrue()
        ->and(ExamSession::query()->where('code', 'EX-DIST-002')->value('auto_assign_enabled'))->toBeFalse()
        ->and(ConsultationSession::query()->where('code', 'CS-DIST-001')->value('auto_assign_enabled'))->toBeTrue();
});

test('exam session toggle auto assign flips state and enforces authorization and draft staff-only guards', function (): void {
    $session = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'EX-TOGGLE-1',
            'version' => 1,
            'status' => ExamSession::STATUS_DRAFT,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
            'auto_assign_enabled' => false,
        ]);

    // Unauthorized user receives 403
    $this->actingAs($this->unauthorizedUser)
        ->patch(route('exams.sessions.toggle-auto-assign', $session))
        ->assertForbidden();

    // Authorized user toggles from false to true
    $this->actingAs($this->authorizedUser)
        ->from('/exams/sessions')
        ->patch(route('exams.sessions.toggle-auto-assign', $session))
        ->assertRedirect('/exams/sessions')
        ->assertSessionHas('status', 'Auto-assign updated.');

    $session->refresh();
    expect($session->auto_assign_enabled)->toBeTrue()
        ->and($session->version)->toBe(2);

    // Authorized user toggles from true to false
    $this->actingAs($this->authorizedUser)
        ->from('/exams/sessions')
        ->patch(route('exams.sessions.toggle-auto-assign', $session))
        ->assertRedirect('/exams/sessions')
        ->assertSessionHas('status', 'Auto-assign updated.');

    $session->refresh();
    expect($session->auto_assign_enabled)->toBeFalse()
        ->and($session->version)->toBe(3);

    // Non-draft session cannot be toggled
    $publishedSession = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'EX-TOGGLE-PUB',
            'version' => 1,
            'status' => ExamSession::STATUS_PUBLISHED,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
            'auto_assign_enabled' => false,
        ]);

    $this->actingAs($this->authorizedUser)
        ->from('/exams/sessions')
        ->patch(route('exams.sessions.toggle-auto-assign', $publishedSession))
        ->assertRedirect('/exams/sessions')
        ->assertSessionHasErrors(['auto_assign']);

    expect($publishedSession->fresh()->auto_assign_enabled)->toBeFalse();

    // Non-staff_only session cannot be toggled
    $openSession = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'EX-TOGGLE-OPEN',
            'version' => 1,
            'status' => ExamSession::STATUS_DRAFT,
            'booking_mode' => ExamSession::BOOKING_OPEN_FOR_RESERVATION,
            'auto_assign_enabled' => false,
        ]);

    $this->actingAs($this->authorizedUser)
        ->from('/exams/sessions')
        ->patch(route('exams.sessions.toggle-auto-assign', $openSession))
        ->assertRedirect('/exams/sessions')
        ->assertSessionHasErrors(['auto_assign']);

    expect($openSession->fresh()->auto_assign_enabled)->toBeFalse();
});

test('consultation session toggle auto assign flips state and enforces authorization and draft staff-only guards', function (): void {
    $consSession = ConsultationSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'CS-TOGGLE-1',
            'version' => 1,
            'status' => ConsultationSession::STATUS_DRAFT,
            'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
            'auto_assign_enabled' => false,
        ]);

    // Unauthorized user receives 403
    $this->actingAs($this->unauthorizedUser)
        ->patch(route('consultation.sessions.toggle-auto-assign', $consSession))
        ->assertForbidden();

    // Authorized user toggles from false to true
    $this->actingAs($this->authorizedUser)
        ->from('/consultation/sessions')
        ->patch(route('consultation.sessions.toggle-auto-assign', $consSession))
        ->assertRedirect('/consultation/sessions')
        ->assertSessionHas('status', 'Auto-assign updated.');

    $consSession->refresh();
    expect($consSession->auto_assign_enabled)->toBeTrue()
        ->and($consSession->version)->toBe(2);

    // Authorized user toggles from true to false
    $this->actingAs($this->authorizedUser)
        ->from('/consultation/sessions')
        ->patch(route('consultation.sessions.toggle-auto-assign', $consSession))
        ->assertRedirect('/consultation/sessions')
        ->assertSessionHas('status', 'Auto-assign updated.');

    $consSession->refresh();
    expect($consSession->auto_assign_enabled)->toBeFalse()
        ->and($consSession->version)->toBe(3);

    // Non-draft consultation session cannot be toggled
    $completedSession = ConsultationSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'CS-TOGGLE-COMPLETED',
            'version' => 1,
            'status' => ConsultationSession::STATUS_COMPLETED,
            'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
            'auto_assign_enabled' => false,
        ]);

    $this->actingAs($this->authorizedUser)
        ->from('/consultation/sessions')
        ->patch(route('consultation.sessions.toggle-auto-assign', $completedSession))
        ->assertRedirect('/consultation/sessions')
        ->assertSessionHasErrors(['auto_assign']);

    expect($completedSession->fresh()->auto_assign_enabled)->toBeFalse();
});

test('committing cohort distribution with auto_assign_enabled false preserves enabled target sessions', function (): void {
    $c1 = createHttpCandidate($this->cycle, 'APP-H-DIS-1', 'SANTOS', 'ANA', 'BSN');

    $session = ExamSession::factory()
        ->for($this->cycle, 'academicCycle')
        ->for($this->room)
        ->create([
            'code' => 'EX-HTTP-DIS-1',
            'version' => 1,
            'status' => ExamSession::STATUS_DRAFT,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
            'auto_assign_enabled' => true,
        ]);

    $this->actingAs($this->authorizedUser);

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

    expect($commitResponse->json('assigned_count'))->toBe(1);

    expect($session->auto_assign_enabled)->toBeTrue();
});
