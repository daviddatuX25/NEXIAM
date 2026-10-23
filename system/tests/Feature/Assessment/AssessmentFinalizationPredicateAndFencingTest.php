<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Assessment\Actions\AcceptAssessmentRawResults;
use App\Assessment\Actions\FinalizeGradingSession;
use App\Assessment\Queries\AssessmentResultQuery;
use App\Assessment\Support\AssessmentAdministrationFinalizationPredicate;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

require_once __DIR__.'/../../Support/TestKitHelpers.php';
require_once __DIR__.'/../../Support/NormConversionFixtures.php';

beforeEach(function (): void {
    $this->withoutVite();
    seedE2Foundation();

    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $capabilities = [
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
        CapabilityCatalog::GUIDANCE_BOOKLET_CONTENT,
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
    ];

    foreach ($capabilities as $capability) {
        Permission::findOrCreate($capability, 'web');
    }

    $this->guidance->givePermissionTo($capabilities);
});

function testFinalizationPredicateProfilePayload(string $code): array
{
    return [
        'request_id' => (string) Str::uuid(),
        'profile' => [
            'code' => $code,
            'display_name' => 'Approved test profile version for predicate fencing',
            'publisher_name' => 'Isolated software test fixture',
            'form_code' => 'PRED-FORM',
            'edition' => 'Test-only source fixture',
            'language' => 'Test-only',
            'source_reference' => 'test-fixture://pred-source',
            'permitted_use_reference' => 'test-fixture://pred-permission',
            'material_requirement' => 'no_paper',
            'administration_procedure_reference' => 'Direct manual intake test procedure',
        ],
        'subtests' => [[
            'code' => 'SECTION_A',
            'name' => 'Section A',
            'display_order' => 1,
            'response_mode' => 'keyed_items',
            'item_order_policy' => 'fixed',
            'items' => [[
                'item_code' => 'ITEM_A',
                'local_number' => '1',
                'display_order' => 1,
                'is_scored' => true,
                'option_tokens' => ['A', 'B'],
            ]],
        ]],
        'metrics' => [[
            'code' => 'RAW_A',
            'name' => 'Section A count',
            'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
            'subtest_code' => 'SECTION_A',
            'unit' => 'count',
            'min_raw_value' => null,
            'max_raw_value' => null,
            'precision_scale' => 0,
            'is_required' => true,
            'required_output_scales' => ['percentile_rank'],
            'procedure_reference' => null,
            'construct_aptitude_area_id' => null,
            'input_codes' => [],
            'input_contract_json' => [
                'allowed_sources' => ['manual'],
                'required_resources' => [],
                'source_requirements' => [
                    'manual' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
                ],
            ],
        ]],
    ];
}

function testFinalizationPredicateApprovedProfile(TestCase $test, User $actor, string $code): TestInstrumentVersion
{
    $payload = testFinalizationPredicateProfilePayload($code);
    actingAsAdmissionSupportStaff($test, $actor)
        ->post(route('setup.test-instruments.store'), $payload)
        ->assertRedirect();

    $instrument = TestInstrument::query()->where('code', $code)->firstOrFail();
    $draft = $instrument->draftVersion()->firstOrFail();

    actingAsAdmissionSupportStaff($test, $actor)
        ->post(route('setup.test-instruments.versions.approve', [
            'instrument' => $instrument->id,
            'version' => $draft->id,
        ]), [
            'request_id' => (string) Str::uuid(),
            'expected_revision' => $draft->lock_version,
            'source_review_attestation' => true,
        ])
        ->assertRedirect();

    return $draft->fresh();
}

function testFinalizationPredicateAcceptedAttempt(User $actor, string $email): AdmissionAttempt
{
    $submission = submitSyntheticApplicant($email, 'Predicate');

    return app(AcceptAdmissionAttempt::class)->handle($submission['attempt'], $actor, 1);
}

test('profile-bound grading session fails finalization with awaiting_norm_conversion when required conversions are missing', function (): void {
    $profile = testFinalizationPredicateApprovedProfile($this, $this->guidance, 'PRED-NORM-PROFILE');
    $kitVersion = createTestActiveKitVersion($this->guidance, $profile);
    $attempt = testFinalizationPredicateAcceptedAttempt($this->guidance, 'pred-bound@example.test');
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $label = 'PRED-BOUND-SESSION';

    actingAsAdmissionSupportStaff($this, $this->guidance)
        ->post(route('exams.direct-assessment.store'), [
            'academic_cycle_id' => $cycle->id,
            'applicant_ids' => [$attempt->id],
            'label' => $label,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'test_kit_version_id' => $kitVersion->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $session = ExamSession::query()->where('label', $label)->firstOrFail();
    $gradingSession = GradingSession::query()->where('exam_session_id', $session->id)->firstOrFail();

    $administration = AssessmentAdministration::query()
        ->where('admission_attempt_id', $attempt->id)
        ->active()
        ->sole();
    app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['RAW_A' => 1],
        source: 'manual',
        actor: $this->guidance,
        idempotencyKey: 'score-predicate-'.$attempt->id,
    );

    $predicate = app(AssessmentAdministrationFinalizationPredicate::class);
    $result = $predicate->evaluate($gradingSession);

    expect($result['ok'])->toBeFalse()
        ->and($result['state'])->toBe('awaiting_norm_conversion')
        ->and($result['reason'])->toContain('norm conversion');

    expect(fn () => app(FinalizeGradingSession::class)->handle($gradingSession, $this->guidance))
        ->toThrow(RuntimeException::class, 'norm conversion');
});

test('historical session without profile binding delegates to area-based score check', function (): void {
    $attempt = testFinalizationPredicateAcceptedAttempt($this->guidance, 'pred-hist@example.test');
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $session = ExamSession::factory()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'PRED-HISTORICAL-SESSION',
        'status' => ExamSession::STATUS_IN_PROGRESS,
        'test_instrument_version_id' => null,
        'starts_at' => now()->subMinutes(15),
        'effective_end_at' => now()->addHour(),
    ]);
    $assignment = ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'assigned_by_user_id' => $this->guidance->id,
        'assigned_at' => now(),
    ]);
    ExamAttendanceRecord::query()->create([
        'exam_assignment_id' => $assignment->id,
        'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
        'recorded_by_user_id' => $this->guidance->id,
        'recorded_at' => now(),
        'idempotency_key' => 'att-hist-'.$assignment->id,
    ]);
    ExamSubmissionRecord::query()->create([
        'exam_assignment_id' => $assignment->id,
        'recorded_by_user_id' => $this->guidance->id,
        'submitted_at' => now(),
        'idempotency_key' => 'sub-hist-'.$assignment->id,
    ]);

    $gradingSession = GradingSession::query()->firstOrCreate(
        ['exam_session_id' => $session->id],
        [
            'status' => GradingSession::STATUS_OPEN,
            'opened_at' => now(),
            'opened_by_user_id' => $this->guidance->id,
            'version' => 1,
        ],
    );

    $predicate = app(AssessmentAdministrationFinalizationPredicate::class);
    $result = $predicate->evaluate($gradingSession);

    expect($result['ok'])->toBeFalse();
});

test('AssessmentResultQuery returns legacy untyped projection without invented percentiles when attempt has no profile binding', function (): void {
    $attempt = testFinalizationPredicateAcceptedAttempt($this->guidance, 'pred-legacy-proj@example.test');
    $area = AptitudeArea::query()->where('is_active', true)->firstOrFail();

    $gradingSession = GradingSession::query()->create([
        'exam_session_id' => ExamSession::factory()->create([
            'academic_cycle_id' => $attempt->academic_cycle_id,
            'status' => ExamSession::STATUS_COMPLETED,
        ])->id,
        'status' => GradingSession::STATUS_OPEN,
        'opened_at' => now(),
        'opened_by_user_id' => $this->guidance->id,
        'version' => 1,
    ]);

    ScoreRecord::query()->create([
        'grading_session_id' => $gradingSession->id,
        'admission_attempt_id' => $attempt->id,
        'aptitude_area_id' => $area->id,
        'score' => 85,
        'converted_score' => null,
        'idempotency_key' => 'legacy-record-'.uniqid(),
        'source' => ScoreRecord::SOURCE_MANUAL,
        'recorded_by_user_id' => $this->guidance->id,
    ]);

    $query = app(AssessmentResultQuery::class);
    $projection = $query->forAttempt($attempt);

    expect($projection['provenance'])->toBe('legacy_untyped')
        ->and($projection['metrics'])->not->toBeEmpty()
        ->and($projection['metrics'][0]['percentile'])->toBeNull();
});
