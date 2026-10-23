<?php

use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\Examination\ProctorDuty;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\AuditEvent;
use App\Models\Release\ResultDocument;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Testing\PracticeFixtureSet;
use App\Testing\PracticeWorkspace;
use App\Testing\Queries\PracticeCaseHandoffs;
use Illuminate\Support\Collection;

/** @param list<string> $capabilities */
function caseHandoffStaff(string $office, array $capabilities = []): User
{
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $user = User::factory()->create();
    StaffProfile::query()->create([
        'user_id' => $user->id, 'institution_id' => $cycle->institution_id,
        'office' => $office, 'status' => 'active', 'active_cycle_code' => $cycle->code,
        'program_codes' => $office === 'program' ? ['BSIT'] : [],
    ]);
    $user->givePermissionTo($capabilities);

    return $user;
}

/** @return array{set: PracticeFixtureSet, attempt: AdmissionAttempt, guidance: User, registrar: User} */
function caseHandoffFixture(array $guidanceCapabilities = [], array $registrarCapabilities = []): array
{
    seedE2Foundation();
    $guidance = caseHandoffStaff('guidance', $guidanceCapabilities);
    $registrar = caseHandoffStaff('registrar', $registrarCapabilities);
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $set = PracticeFixtureSet::query()->create([
        'label' => 'Case handoff rehearsal', 'institution_id' => $cycle->institution_id,
        'academic_cycle_id' => $cycle->id, 'scenario' => 'scoring-results', 'purpose' => 'demo',
        'created_by_user_id' => $guidance->id,
        'actor_bindings' => ['guidance' => (int) $guidance->id, 'registrar' => (int) $registrar->id],
        'material_snapshot' => [], 'credentials' => [],
    ]);
    $attempt = app(PracticeWorkspace::class)->run($set, fn () => submitSyntheticApplicant()['attempt']);

    return compact('set', 'attempt', 'guidance', 'registrar');
}

/** @return array{session: ExamSession, assignment: ExamAssignment, grading: GradingSession} */
function caseHandoffExam(array $fixture, bool $submitted = true): array
{
    $set = $fixture['set'];
    $session = app(PracticeWorkspace::class)->run($set, fn () => ExamSession::factory()->create([
        'academic_cycle_id' => $set->academic_cycle_id, 'status' => ExamSession::STATUS_PUBLISHED,
        'starts_at' => now()->addDays(2), 'effective_end_at' => now()->addDays(2)->addHours(2),
    ]));
    $assignment = ExamAssignment::factory()->create([
        'exam_session_id' => $session->id, 'admission_attempt_id' => $fixture['attempt']->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
    ]);
    ExamAttendanceRecord::query()->create([
        'exam_assignment_id' => $assignment->id, 'recorded_by_user_id' => $fixture['guidance']->id,
        'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT, 'recorded_at' => now(),
        'idempotency_key' => 'handoff-attendance-'.$assignment->id,
    ]);
    if ($submitted) {
        ExamSubmissionRecord::query()->create([
            'exam_assignment_id' => $assignment->id, 'recorded_by_user_id' => $fixture['guidance']->id,
            'submitted_at' => now(), 'idempotency_key' => 'handoff-submission-'.$assignment->id,
        ]);
    }
    $grading = GradingSession::query()->create([
        'exam_session_id' => $session->id, 'status' => GradingSession::STATUS_OPEN,
        'opened_at' => now(), 'opened_by_user_id' => $fixture['guidance']->id,
    ]);

    return compact('session', 'assignment', 'grading');
}

function caseHandoffRows(array $fixture): Collection
{
    return collect(app(PracticeCaseHandoffs::class)->for($fixture['set'], $fixture['attempt']))->keyBy('key');
}

test('handoffs fail closed for foreign ownership retired sets and inactive cycles', function (string $boundary): void {
    $fixture = caseHandoffFixture([CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE], [CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW]);
    if ($boundary === 'identity') {
        $foreign = $fixture['set']->replicate();
        $foreign->save();
        $fixture['set'] = $foreign;
    } elseif ($boundary === 'retired') {
        $fixture['set']->update(['status' => 'retired']);
    } else {
        $fixture['set']->academicCycle->update(['is_active' => false]);
    }
    $rows = caseHandoffRows($fixture);
    expect($rows)->toHaveCount(11);
    foreach ($rows as $row) {
        expect($row['available'])->toBeFalse()->and($row['url'])->toBeNull();
    }
    expect(app(PracticeWorkspace::class)->currentId())->toBeNull();
})->with(['identity', 'retired', 'inactive-cycle']);

test('Registrar read access remains usable with missing milestones and never implies Guidance access', function (): void {
    $fixture = caseHandoffFixture([], [CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW, CapabilityCatalog::REGISTRAR_ENROLLMENT_ELIGIBILITY]);
    $rows = caseHandoffRows($fixture);
    expect($rows['review']['url'])->toBe(route('admissions.show', ['attempt' => $fixture['attempt']->id, 'practice_set' => $fixture['set']->id], false))
        ->and($rows['enrollment']['available'])->toBeTrue()
        ->and($rows['enrollment']['reason'])->toContain('Missing milestones:')
        ->and($rows['enrollment']['url'])->toBe(route('registrar.enrollment-eligibility.show', ['attempt' => $fixture['attempt']->id, 'practice_set' => $fixture['set']->id], false))
        ->and($rows['consultation']['url'])->toBeNull()->and($rows['academic']['url'])->toBeNull();
    $fixture['registrar']->staffProfile->update(['active_cycle_code' => 'FOREIGN-CYCLE']);
    $changed = caseHandoffRows($fixture);
    expect($changed['review']['url'])->toBeNull()->and($changed['enrollment']['url'])->toBeNull();
});

test('unassigned case scheduling uses only its actual owned prepared stage session', function (): void {
    $fixture = caseHandoffFixture([CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE]);
    $set = $fixture['set'];
    $session = app(PracticeWorkspace::class)->run($set, fn () => ExamSession::factory()->create([
        'academic_cycle_id' => $set->academic_cycle_id,
        'status' => ExamSession::STATUS_DRAFT,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
    ]));
    $set->update(['material_snapshot' => ['stages' => ['reservation' => [
        'attempt_ids' => [$fixture['attempt']->id], 'session_id' => $session->id,
    ]]]]);
    $rows = caseHandoffRows($fixture);
    expect($rows['scheduling']['url'])->toBe(route('exams.sessions.show', [
        'session' => $session->id, 'practice_set' => $set->id,
    ], false))->and($rows['scheduling']['session_id'])->toBe($session->id)
        ->and($rows['scheduling']['reason'])->toContain('not assigned')
        ->and($rows['monitoring']['url'])->toBeNull()->and($rows['grading']['url'])->toBeNull();
    $set->update(['material_snapshot' => ['stages' => ['reservation' => [
        'attempt_ids' => [], 'session_id' => $session->id,
    ]]]]);
    expect(caseHandoffRows($fixture)['scheduling']['url'])->toBe(route('exams.sessions.create', ['practice_set' => $set->id], false));
    $set->update(['material_snapshot' => ['stages' => ['reservation' => [
        'attempt_ids' => [$fixture['attempt']->id], 'session_id' => $session->id,
    ]]]]);
    $foreignSession = ExamSession::factory()->create(['academic_cycle_id' => $set->academic_cycle_id]);
    $set->update(['material_snapshot' => ['stages' => ['reservation' => [
        'attempt_ids' => [$fixture['attempt']->id], 'session_id' => $foreignSession->id,
    ]]]]);
    expect(caseHandoffRows($fixture)['scheduling']['url'])->toBe(route('exams.sessions.create', ['practice_set' => $set->id], false));
});

test('manual scoring and ordinary OMR have separate current grants and exact roster routes', function (): void {
    $fixture = caseHandoffFixture([CapabilityCatalog::GUIDANCE_SCORING_MANUAL]);
    $exam = caseHandoffExam($fixture);
    $rows = caseHandoffRows($fixture);
    expect($rows['grading']['available'])->toBeTrue()
        ->and($rows['grading']['url'])->toBe(route('grading.sessions.attempts.scores', [
            'gradingSession' => $exam['grading']->id, 'attempt' => $fixture['attempt']->id, 'practice_set' => $fixture['set']->id,
        ], false))->and($rows['omr']['url'])->toBeNull();
    $fixture['guidance']->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $exam['grading']->update(['status' => GradingSession::STATUS_FINALIZED]);
    $finalized = caseHandoffRows($fixture);
    expect($finalized['omr']['url'])->toBe(route('scoring.sessions.omr', [
        'examSession' => $exam['grading']->exam_session_id, 'practice_set' => $fixture['set']->id,
    ], false))->and($finalized['grading']['reason'])->toContain('finalized');
    $exam['assignment']->attendanceRecord->update(['outcome' => ExamAttendanceRecord::OUTCOME_ABSENT]);
    $absent = caseHandoffRows($fixture);
    expect($absent['grading']['url'])->toBeNull()->and($absent['omr']['url'])->toBeNull();
});

test('Guidance consultation read scope does not require completed result actions and honors revocation', function (): void {
    $fixture = caseHandoffFixture([CapabilityCatalog::GUIDANCE_SELECTION_MANAGE]);
    $rows = caseHandoffRows($fixture);
    expect($rows['consultation']['available'])->toBeTrue()
        ->and($rows['consultation']['url'])->toBe(route('admission-support.show', ['attempt' => $fixture['attempt']->id, 'practice_set' => $fixture['set']->id], false))
        ->and($rows['consultation']['reason'])->toContain('Registrar acceptance', 'finalized grading');
    $fixture['guidance']->revokePermissionTo(CapabilityCatalog::GUIDANCE_SELECTION_MANAGE);
    expect(caseHandoffRows($fixture)['consultation']['url'])->toBeNull();
});

test('academic handoff uses selected practice candidate inclusion and summary tier without audited opening', function (): void {
    $fixture = caseHandoffFixture();
    $academic = caseHandoffStaff('program', [CapabilityCatalog::PROGRAM_HEAD_VIEW]);
    $fixture['set']->update(['actor_bindings' => [...$fixture['set']->actor_bindings, 'academic-leader' => (int) $academic->id]]);
    $fixture['attempt']->update(['status' => AdmissionAttempt::STATUS_ACCEPTED]);
    $audits = AuditEvent::query()->count();
    $deliveries = NotificationDelivery::query()->count();
    $row = caseHandoffRows($fixture)['academic'];
    expect($row['available'])->toBeTrue()->and($row['tier'])->toBe('A')
        ->and($row['url'])->toBe(route('admission-support.show', ['attempt' => $fixture['attempt']->id, 'practice_set' => $fixture['set']->id], false))
        ->and(AuditEvent::query()->count())->toBe($audits)->and(NotificationDelivery::query()->count())->toBe($deliveries)
        ->and(app(PracticeWorkspace::class)->currentId())->toBeNull();
    $academic->staffProfile->update(['program_codes' => ['BSN']]);
    expect(caseHandoffRows($fixture)['academic']['url'])->toBeNull();
});

test('historical duty does not grant a monitoring handoff without current exam authority', function (): void {
    $fixture = caseHandoffFixture();
    $exam = caseHandoffExam($fixture, submitted: false);
    $duty = ProctorDuty::query()->create([
        'exam_session_id' => $exam['session']->id, 'user_id' => $fixture['registrar']->id,
        'status' => ProctorDuty::STATUS_ACTIVE, 'assigned_at' => now(), 'idempotency_key' => 'handoff-historical-duty',
    ]);

    $row = caseHandoffRows($fixture)['monitoring'];
    expect($row['available'])->toBeFalse()
        ->and($row['url'])->toBeNull()
        ->and(ProctorDuty::query()->whereKey($duty->id)->exists())->toBeTrue();
});

test('print access targets the current case document without fabricating physical readiness', function (): void {
    $fixture = caseHandoffFixture([CapabilityCatalog::GUIDANCE_RESULTS_RELEASE]);
    $exam = caseHandoffExam($fixture);
    ScoreRecord::query()->create([
        'grading_session_id' => $exam['grading']->id, 'admission_attempt_id' => $fixture['attempt']->id,
        'aptitude_area_id' => AptitudeArea::query()->where('is_active', true)->value('id'),
        'score' => '10', 'idempotency_key' => 'handoff-print-score',
    ]);
    $document = ResultDocument::query()->create([
        'admission_attempt_id' => $fixture['attempt']->id, 'template_version' => 'handoff-test-v1',
        'checksum' => hash('sha256', 'handoff-test-document'), 'status' => ResultDocument::STATUS_READY,
        'storage_path' => 'results/handoff-test.html', 'preview_path' => 'results/handoff-test.html',
    ]);
    $fixture['attempt']->forceFill(['current_result_document_id' => $document->id])->save();
    $before = ResultDocumentPrintReceipt::query()->count();
    $row = caseHandoffRows($fixture)['print'];
    expect($row['available'])->toBeTrue()->and($row['reason'])->toContain('physical print confirmation is missing')
        ->and($row['url'])->toBe(route('release.print.batches.applicant', [
            'gradingSession' => $exam['grading']->id, 'attempt' => $fixture['attempt']->id, 'practice_set' => $fixture['set']->id,
        ], false))->and(ResultDocumentPrintReceipt::query()->count())->toBe($before);
    $fixture['guidance']->revokePermissionTo(CapabilityCatalog::GUIDANCE_RESULTS_RELEASE);
    expect(caseHandoffRows($fixture)['print']['url'])->toBeNull();
});
