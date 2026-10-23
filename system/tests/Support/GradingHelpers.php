<?php

use App\Assessment\Actions\Omr\CreateBookletDraft;
use App\Assessment\Actions\Omr\FinalizeBookletVersion;
use App\Assessment\Actions\Omr\SaveBookletSetKeys;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\RecordExamSubmission;
use App\Examination\Actions\StartExamSession;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignmentSeatAllocation;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionMaterialSet;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Examination\ExamSessionSeat;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use Tests\TestCase;

require_once __DIR__.'/TestKitHelpers.php';
require_once __DIR__.'/TestKitSoftwareAcceptanceFixtures.php';

/**
 * Build an ordinary session-only grading fixture and return its session roster.
 *
 * @return array{guidance: User, registrar: User, session: ExamSession, attempts: array<int, AdmissionAttempt>, grading: ?GradingSession}
 */
function buildGradingFixture(TestCase $test, int $applicantCount = 1, bool $close = true): array
{
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::query()->findOrFail($guidance->staffProfile->institution_id);
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $attempts = [];

    for ($i = 0; $i < $applicantCount; $i++) {
        $result = submitSyntheticApplicant('grading.'.uniqid().'@example.test', 'Grading '.$i);
        $attempt = $result['attempt'];
        $test->actingAs($registrar)
            ->post(route('admissions.accept', $attempt), ['expected_version' => 1])
            ->assertRedirect();
        $attempts[] = $attempt;
    }

    $session = app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        'EX-GRAD-'.uniqid(),
        now()->subHours(2)->toDateTimeString(),
        now()->addMinutes(30)->toDateTimeString(),
        $guidance,
        ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
    );

    foreach ($attempts as $attempt) {
        app(AssignApplicantToExamSession::class)->handle($session, $attempt, $guidance);
    }

    $session = app(PublishExamSession::class)->handle($session->fresh(), $guidance, $session->fresh()->version)->fresh(['assignments']);
    $grading = null;

    if ($close) {
        $session = app(StartExamSession::class)->handle($session->fresh(), $guidance, $session->fresh()->version)->fresh(['assignments']);
        $attendanceAction = app(RecordExamAttendance::class);
        $submissionAction = app(RecordExamSubmission::class);
        foreach ($session->assignments as $assignment) {
            $attendanceAction->handle($assignment, $guidance, ExamAttendanceRecord::OUTCOME_PRESENT, 'att-grading-'.$assignment->id);
            $submissionAction->handle($assignment, $guidance, 'sub-grading-'.$assignment->id);
        }

        $test->actingAs($guidance)
            ->post(route('exams.sessions.close', $session), ['expected_version' => $session->version])
            ->assertRedirect();
        $grading = GradingSession::query()->where('exam_session_id', $session->id)->firstOrFail();
    }

    return compact('guidance', 'registrar', 'session', 'attempts', 'grading');
}

/**
 * Build a scoring fixture with exact independently authored paper composition.
 *
 * Publication remains logistics-only. This fixture explicitly creates historical
 * material/seat rows from the saved Kit solely for existing OMR consumers.
 *
 * @return array<string, mixed>
 */
function buildPaperGradingFixture(TestCase $test, int $applicantCount = 1, bool $close = true): array
{
    return buildPaperGradingFixtureInternal($test, $applicantCount, $close);
}

/**
 * @return array<string, mixed>
 */
function buildPaperGradingFixtureInternal(
    TestCase $test,
    int $applicantCount,
    bool $close,
    bool $requireCurrentOmrProof = false,
    ?array $kitFixture = null,
): array {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::query()->findOrFail($guidance->staffProfile->institution_id);
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $kit = $kitFixture === null
        ? testKitAcceptanceFixture($guidance, $institution)
        : $kitFixture['fixture'];
    $acceptedKit = null;
    if ($requireCurrentOmrProof) {
        $acceptedKit = $kitFixture === null
            ? testKitAcceptedSoftwareFixture($kit, $guidance, $institution)
            : $kitFixture;
        $kit = $acceptedKit['fixture'];
    }
    $receiptId = $acceptedKit === null ? null : (int) $acceptedKit['receipt']->id;
    $kitVersion = $kit['version'];
    $sheet = $kit['sheet'];
    $booklet = $kit['booklet'];
    $bookletSet = $kit['set'];
    $pairs = OmrTemplateBookletPair::query()
        ->where('template_version_id', $sheet->id)
        ->where('booklet_version_id', $booklet->id)
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->get();
    if ($pairs->count() > 1) {
        throw new RuntimeException('The saved Kit fixture has ambiguous active sheet/Booklet pair identity.');
    }
    $templateBookletPair = $pairs->first() ?? OmrTemplateBookletPair::query()->create([
        'template_version_id' => $sheet->id,
        'booklet_version_id' => $booklet->id,
        'status' => OmrTemplateBookletPair::STATUS_ACTIVE,
        'approved_by_user_id' => $guidance->id,
        'approved_at' => now(),
    ]);
    $attempts = [];

    for ($i = 0; $i < $applicantCount; $i++) {
        $result = submitSyntheticApplicant('paper-grading.'.uniqid().'@example.test', 'Paper Grading '.$i);
        $attempt = $result['attempt'];
        $test->actingAs($registrar)
            ->post(route('admissions.accept', $attempt), ['expected_version' => 1])
            ->assertRedirect();
        $attempts[] = $attempt;
    }

    $session = app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        'EX-PAPER-GRAD-'.uniqid(),
        now()->subHours(2)->toDateTimeString(),
        now()->addMinutes(30)->toDateTimeString(),
        $guidance,
        ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        $booklet,
    );

    foreach ($attempts as $attempt) {
        app(AssignApplicantToExamSession::class)->handle($session, $attempt, $guidance);
    }

    // This is retained material-owned history, not a new logistics-generation publication.
    $session->forceFill([
        'status' => ExamSession::STATUS_PUBLISHED,
        'published_at' => now()->subDay(),
        'version' => $session->version + 1,
    ])->save();
    $session = $session->fresh(['assignments']);
    $paperMaterialHash = hash('sha256', implode(':', [
        (string) $sheet->id,
        (string) $booklet->id,
        (string) $bookletSet->id,
        (string) $bookletSet->order_hash,
        (string) $bookletSet->answer_key_hash,
    ]));
    $snapshot = ExamSessionMaterialSnapshot::query()->create([
        'exam_session_id' => $session->id,
        'revision' => 1,
        'is_current' => true,
        'template_version_id' => $sheet->id,
        'booklet_version_id' => $booklet->id,
        'test_instrument_version_id' => $receiptId === null ? null : $kit['profile']->id,
        'test_kit_version_id' => $receiptId === null ? null : $kitVersion->id,
        'manifest_sha256' => $receiptId === null ? null : $kitVersion->manifest_sha256,
        'acceptance_receipt_id' => $receiptId,
        'scoring_policy_identity' => $receiptId === null
            ? 'historical-test-fixture:paper-only'
            : 'historical-test-fixture:'.$kitVersion->manifest_sha256,
        'material_hash' => $receiptId === null ? $paperMaterialHash : $kitVersion->manifest_sha256,
        'published_by_user_id' => $guidance->id,
        'published_at' => now(),
    ]);
    $materialSet = ExamSessionMaterialSet::query()->create([
        'snapshot_id' => $snapshot->id,
        'booklet_set_id' => $bookletSet->id,
        'set_code' => $bookletSet->set_code,
        'order_hash' => $bookletSet->order_hash,
        'answer_key_hash' => $bookletSet->answer_key_hash,
        'answer_key_json' => $bookletSet->answerKeyByPosition(),
    ]);
    $requiredMetricSet = $kit['profile']->metrics
        ->where('is_required', true)
        ->values()
        ->map(fn ($metric): array => [
            'code' => $metric->code,
            'name' => $metric->name,
            'rule_kind' => $metric->rule_kind,
            'unit' => $metric->unit,
            'min_raw_value' => $metric->min_raw_value,
            'max_raw_value' => $metric->max_raw_value,
            'precision_scale' => $metric->precision_scale,
            'required_output_scales' => $metric->required_output_scales,
        ])
        ->all();
    $procedureSnapshot = [
        'material_requirement' => $kit['profile']->material_requirement,
        'administration_procedure_reference' => $kit['profile']->administration_procedure_reference,
        'content_sha256' => $kit['profile']->content_sha256,
    ];
    $historicalSeatsByAssignment = [];
    $historicalAdministrationsByAssignment = [];
    foreach ($session->assignments as $index => $assignment) {
        $seat = ExamSessionSeat::query()->create([
            'material_snapshot_id' => $snapshot->id,
            'ordinal' => $index + 1,
            'row_number' => 1,
            'column_number' => $index + 1,
            'seat_label' => 'HIST-'.($index + 1),
            'booklet_set_id' => $bookletSet->id,
        ]);
        $allocation = ExamAssignmentSeatAllocation::query()
            ->where('exam_assignment_id', $assignment->id)
            ->whereNull('effective_to')
            ->first();
        $allocationData = [
            'exam_session_seat_id' => $seat->id,
            'reason' => 'historical_test_fixture',
            'assigned_by_user_id' => $guidance->id,
        ];
        if ($allocation === null) {
            ExamAssignmentSeatAllocation::query()->create([
                'exam_assignment_id' => $assignment->id,
                ...$allocationData,
                'effective_from' => now(),
            ]);
        } else {
            $allocation->update($allocationData);
        }
        $historicalSeatsByAssignment[$assignment->id] = $seat;
        if ($receiptId !== null) {
            $historicalAdministrationsByAssignment[$assignment->id] = AssessmentAdministration::query()->create([
                'exam_assignment_id' => $assignment->id,
                'admission_attempt_id' => $assignment->admission_attempt_id,
                'binding_kind' => AssessmentAdministration::KIND_SCHEDULED_MATERIAL,
                'binding_revision' => 1,
                'test_kit_version_id' => $kitVersion->id,
                'manifest_sha256' => $kitVersion->manifest_sha256,
                'acceptance_receipt_id' => $receiptId,
                'procedure_snapshot_json' => $procedureSnapshot,
                'test_instrument_version_id' => $kit['profile']->id,
                'material_snapshot_id' => $snapshot->id,
                'exam_session_seat_id' => $seat->id,
                'material_set_id' => $materialSet->id,
                'booklet_set_id' => $bookletSet->id,
                'required_metric_set' => $requiredMetricSet,
                'binding_state' => AssessmentAdministration::STATE_AWAITING_ITEM_MAP,
                'bound_by_user_id' => $guidance->id,
                'bound_at' => now(),
            ]);
        }
    }

    $grading = null;
    if ($close) {
        $session = app(StartExamSession::class)->handle($session->fresh(), $guidance, $session->fresh()->version)->fresh(['assignments']);
        $attendanceAction = app(RecordExamAttendance::class);
        $submissionAction = app(RecordExamSubmission::class);
        foreach ($session->assignments as $assignment) {
            $attendanceAction->handle($assignment, $guidance, ExamAttendanceRecord::OUTCOME_PRESENT, 'att-paper-grading-'.$assignment->id);
            $submissionAction->handle($assignment, $guidance, 'sub-paper-grading-'.$assignment->id);
        }
        $test->actingAs($guidance)
            ->post(route('exams.sessions.close', $session), ['expected_version' => $session->version])
            ->assertRedirect();
        $grading = GradingSession::query()->where('exam_session_id', $session->id)->firstOrFail();
    }

    return [
        'guidance' => $guidance,
        'registrar' => $registrar,
        'session' => $session,
        'attempts' => $attempts,
        'grading' => $grading,
        'kit' => $kit['kit'],
        'kitVersion' => $kitVersion,
        'sheet' => $sheet,
        'booklet' => $booklet,
        'bookletSet' => $bookletSet,
        'templateBookletPair' => $templateBookletPair,
        'formalProfile' => $kit['profile'],
        'compositionRows' => $kit['composition_rows'],
        'componentVector' => $kit['component_vector'],
        'acceptanceReceipt' => $acceptedKit['receipt'] ?? null,
        'historicalSnapshot' => $snapshot,
        'historicalMaterialSet' => $materialSet,
        'historicalSeatsByAssignment' => $historicalSeatsByAssignment,
        'historicalAdministrationsByAssignment' => $historicalAdministrationsByAssignment,
    ];
}

/**
 * Build historical OMR chains after logistics-only publication.
 *
 * @param  array<string, mixed>|null  $kitFixture  Accepted software fixture when current OMR proof is required.
 * @return array<string, mixed>
 */
function buildOmrGradingFixture(TestCase $test, int $applicantCount = 1, bool $close = true): array
{
    return buildPaperGradingFixture($test, $applicantCount, $close);
}

function canonicalGradingBooklet(User $actor): OmrTemplateBookletPair
{
    $template = OmrAnswerSheetTemplateVersion::query()
        ->where('status', OmrAnswerSheetTemplateVersion::STATUS_ACTIVE)
        ->with('areas')
        ->orderByDesc('id')
        ->first();
    if ($template === null) {
        $pairs = OmrTemplateBookletPair::query()
            ->with(['templateVersion', 'bookletVersion'])
            ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
            ->where('approved_by_user_id', $actor->id)
            ->whereHas('templateVersion', fn ($query) => $query->where('status', OmrAnswerSheetTemplateVersion::STATUS_COMPILED))
            ->get();
        if ($pairs->count() !== 1) {
            throw new RuntimeException('An exact unique compiled sheet/Booklet pair is required for this OMR fixture.');
        }

        return $pairs->first();
    }
    $booklet = app(CreateBookletDraft::class)->handle(
        'Grading Fixture '.uniqid(),
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $actor,
        $template,
    );
    $keys = [];
    $globalPosition = 1;
    foreach ($template->areas as $area) {
        for ($areaPosition = 1; $areaPosition <= (int) $area->max_items; $areaPosition++) {
            $keys[] = [
                'aptitude_area_id' => (int) $area->aptitude_area_id,
                'area_position' => $areaPosition,
                'global_position' => $globalPosition,
                'correct_option_code' => ['A', 'B', 'C', 'D'][($globalPosition - 1) % 4],
            ];
            $globalPosition++;
        }
    }
    app(SaveBookletSetKeys::class)->handle($booklet, 'A', $keys, $actor);
    $booklet = app(FinalizeBookletVersion::class)->handle($booklet, $actor);

    return OmrTemplateBookletPair::query()->create([
        'template_version_id' => $template->id,
        'booklet_version_id' => $booklet->id,
        'status' => OmrTemplateBookletPair::STATUS_ACTIVE,
        'approved_by_user_id' => $actor->id,
        'approved_at' => now(),
    ]);
}

/**
 * @return array<int, int>
 */
function activeAreas(): array
{
    return AptitudeArea::query()->where('is_active', true)->orderBy('id')->pluck('id')->all();
}
