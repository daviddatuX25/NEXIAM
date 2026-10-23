<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Assessment\Actions\AcceptAssessmentRawResults;
use App\Assessment\Actions\ApproveAssessmentNormVersion;
use App\Assessment\Actions\ClearScores;
use App\Assessment\Actions\ConvertAssessmentResults;
use App\Assessment\Actions\FinalizeGradingSession;
use App\Assessment\Actions\RecordScores;
use App\Assessment\Actions\SaveAssessmentNormDraft;
use App\Assessment\Queries\AssessmentResultQuery;
use App\Assessment\Support\TestInstrumentProfileValidator;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Support\ConsultationScoreProjection;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\AssessmentResultSet;
use App\Models\Assessment\AssessmentScoringRun;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\AuditEvent;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
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
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
    ];

    foreach ($capabilities as $capability) {
        Permission::findOrCreate($capability, 'web');
    }

    $this->guidance->givePermissionTo($capabilities);
});

function createResultSetApprovedProfileVersion(TestCase $test, User $actor, string $code, bool $withFormula = false, string $formulaExpression = '0.50*RAW_A'): TestInstrumentVersion
{
    $payload = [
        'request_id' => (string) Str::uuid(),
        'profile' => [
            'code' => $code,
            'display_name' => "Approved test profile {$code}",
            'publisher_name' => 'Test Publisher',
            'form_code' => 'FORM-A',
            'edition' => '1st',
            'language' => 'English',
            'source_reference' => 'test-source-ref',
            'permitted_use_reference' => 'test-perm-ref',
            'material_requirement' => 'no_paper',
            'administration_procedure_reference' => 'Direct manual intake test procedure',
        ],
        'subtests' => [
            [
                'code' => 'SUB_A',
                'name' => 'Subtest A',
                'display_order' => 1,
                'response_mode' => 'keyed_items',
                'item_order_policy' => 'fixed',
                'items' => [
                    [
                        'item_code' => 'SUB_A_1',
                        'local_number' => '1',
                        'display_order' => 1,
                        'is_scored' => true,
                        'option_tokens' => ['A', 'B'],
                    ],
                    [
                        'item_code' => 'SUB_A_2',
                        'local_number' => '2',
                        'display_order' => 2,
                        'is_scored' => true,
                        'option_tokens' => ['A', 'B'],
                    ],
                ],
            ],
        ],
        'metrics' => [
            [
                'code' => 'RAW_A',
                'name' => 'Raw A metric',
                'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
                'subtest_code' => 'SUB_A',
                'unit' => 'count',
                'min_raw_value' => null,
                'max_raw_value' => null,
                'precision_scale' => 0,
                'is_required' => true,
                'required_output_scales' => ['percentile_rank', 'stanine'],
                'procedure_reference' => null,
                'construct_aptitude_area_id' => null,
                'input_codes' => [],
                'input_contract_json' => [
                    'allowed_sources' => ['manual', 'import'],
                    'required_resources' => [],
                    'source_requirements' => [
                        'manual' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
                        'import' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
                    ],
                ],
            ],
        ],
    ];
    if ($withFormula) {
        $payload['metrics'][0]['item_codes'] = ['SUB_A_1'];
        $payload['metrics'][] = [
            'code' => 'WEIGHTED_RAW',
            'name' => 'Weighted raw score',
            'rule_kind' => TestInstrumentMetric::RULE_FORMULA_RAW_METRICS,
            'subtest_code' => null,
            'unit' => 'count',
            'min_raw_value' => null,
            'max_raw_value' => null,
            'precision_scale' => 2,
            'is_required' => true,
            'required_output_scales' => ['percentile_rank'],
            'procedure_reference' => null,
            'construct_aptitude_area_id' => null,
            'input_codes' => ['RAW_A'],
            'formula_expression' => $formulaExpression,
            'input_contract_json' => [
                'allowed_sources' => ['manual', 'import'],
                'required_resources' => [],
                'source_requirements' => [
                    'manual' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
                    'import' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
                ],
            ],
        ];
    }

    actingAsAdmissionSupportStaff($test, $actor)
        ->post(route('setup.test-instruments.store'), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $instrument = TestInstrument::query()->where('code', $code)->firstOrFail();
    $draft = $instrument->draftVersion()->firstOrFail();
    if ($withFormula) {
        actingAsAdmissionSupportStaff($test, $actor)
            ->get(route('setup.test-instruments.edit', ['instrument' => $instrument->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('version.metrics.0.item_codes', ['SUB_A_1'])
                ->where('version.metrics.1.formula_expression', $formulaExpression)
                ->where('version.metrics.1.formula_algorithm_identity', 'nexiam.raw-formula.v1'));
    }

    actingAsAdmissionSupportStaff($test, $actor)
        ->post(route('setup.test-instruments.versions.approve', [
            'instrument' => $instrument->id,
            'version' => $draft->id,
        ]), [
            'request_id' => (string) Str::uuid(),
            'expected_revision' => $draft->lock_version,
            'source_review_attestation' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    return $draft->fresh();
}

function createResultSetApprovedNormVersion(User $actor, string $code): AssessmentNormPackageVersion
{
    $intervals = [
        ['min' => 0, 'max' => 1, 'percentile_rank' => 30, 'stanine' => 4],
        ['min' => 2, 'max' => 3, 'percentile_rank' => 75, 'stanine' => 6],
    ];

    $draft = app(SaveAssessmentNormDraft::class)->handle(
        actor: $actor,
        package: null,
        version: null,
        expectedRevision: null,
        requestId: (string) Str::uuid(),
        input: [
            'code' => $code,
            'display_name' => "Approved Norm Package {$code}",
            'publisher_name' => 'Standard Norms Publisher',
            'form_code' => 'FORM-A',
            'edition' => '1st',
            'language' => 'English',
            'applicable_population' => 'Grade 12 Applicants',
            'source_reference' => 'test-norm-source-ref',
            'permitted_use_reference' => 'test-norm-permitted-ref',
            'is_synthetic' => false,
            'tables' => [
                [
                    'metric_code' => 'RAW_A',
                    'output_scale' => 'percentile_rank',
                    'intervals' => $intervals,
                ],
                [
                    'metric_code' => 'RAW_A',
                    'output_scale' => 'stanine',
                    'intervals' => $intervals,
                ],
            ],
        ],
    );

    return app(ApproveAssessmentNormVersion::class)->handle(
        actor: $actor,
        package: $draft->norm_package_id,
        version: $draft->id,
        expectedRevision: $draft->lock_version,
        requestId: (string) Str::uuid(),
        attestationOrPayload: true,
    );
}

function createResultSetFormulaNormVersion(User $actor, string $code, bool $integerGridGap = false): AssessmentNormPackageVersion
{
    $weightedIntervals = $integerGridGap
        ? [
            ['min_score' => '0', 'max_score' => '1', 'output_value' => '50'],
            ['min_score' => '2', 'max_score' => '3', 'output_value' => '60'],
        ]
        : [['min_score' => '0', 'max_score' => '0.5', 'output_value' => '60']];
    $draft = app(SaveAssessmentNormDraft::class)->handle(
        actor: $actor,
        package: null,
        version: null,
        expectedRevision: null,
        requestId: (string) Str::uuid(),
        input: [
            'code' => $code,
            'display_name' => "Synthetic formula norm {$code}",
            'publisher_name' => 'Test fixture',
            'form_code' => 'FORM-A',
            'edition' => 'Test',
            'language' => 'Test',
            'applicable_population' => 'Synthetic',
            'source_reference' => 'test-fixture://formula-norm',
            'permitted_use_reference' => 'test-fixture://formula-norm-use',
            'tables' => [
                [
                    'metric_code' => 'RAW_A',
                    'output_scale' => 'percentile_rank',
                    'intervals' => [['min_score' => '0', 'max_score' => '1', 'output_value' => '50']],
                ],
                [
                    'metric_code' => 'RAW_A',
                    'output_scale' => 'stanine',
                    'intervals' => [['min_score' => '0', 'max_score' => '1', 'output_value' => '4']],
                ],
                [
                    'metric_code' => 'WEIGHTED_RAW',
                    'output_scale' => 'percentile_rank',
                    'intervals' => $weightedIntervals,
                ],
            ],
        ],
    );

    return app(ApproveAssessmentNormVersion::class)->handle(
        actor: $actor,
        package: $draft->norm_package_id,
        version: $draft->id,
        expectedRevision: $draft->lock_version,
        requestId: (string) Str::uuid(),
        attestationOrPayload: true,
    );
}

/**
 * @return array{
 *     attempt: AdmissionAttempt,
 *     gradingSession: GradingSession,
 *     administration: AssessmentAdministration,
 *     assignment: ExamAssignment
 * }
 */
function createResultSetDirectAdministration(
    TestCase $test,
    User $actor,
    TestInstrumentVersion $profileVersion,
    string $email,
): array {
    $submittedAttempt = submitSyntheticApplicant($email, 'Norm Conversion Attempt')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $attempt = app(AcceptAdmissionAttempt::class)->handle($submittedAttempt, $registrar, 1);
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $kitVersion = createTestActiveKitVersion($actor, $profileVersion);
    $label = 'Direct Norm Assessment '.Str::random(8);

    actingAsAdmissionSupportStaff($test, $actor)
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
    $assignment = ExamAssignment::query()->where('exam_session_id', $session->id)->firstOrFail();
    $gradingSession = GradingSession::query()->where('exam_session_id', $session->id)->firstOrFail();
    $administration = AssessmentAdministration::query()
        ->where('exam_assignment_id', $assignment->id)
        ->active()
        ->firstOrFail();

    return [
        'attempt' => $attempt,
        'gradingSession' => $gradingSession,
        'administration' => $administration,
        'assignment' => $assignment,
    ];
}

test('FinalizeGradingSession creates immutable AssessmentResultSet rows for profile-bound examinees when ready_to_finalize', function (): void {
    $profileVersion = createResultSetApprovedProfileVersion($this, $this->guidance, 'PROF-FIN-1');
    $normVersion = createResultSetApprovedNormVersion($this->guidance, 'NORM-PKG-FIN-1');
    $fixture = createResultSetDirectAdministration($this, $this->guidance, $profileVersion, 'fin-examinee-1@example.test');

    $attempt = $fixture['attempt'];
    $gradingSession = $fixture['gradingSession'];
    $administration = $fixture['administration'];

    app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['RAW_A' => 2],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: (string) Str::uuid(),
    );

    expect($administration->fresh()->binding_state)->toBe(AssessmentAdministration::STATE_AWAITING_NORM_CONVERSION);

    app(ConvertAssessmentResults::class)->handle(
        $administration,
        $normVersion,
        $this->guidance,
    );

    expect($administration->fresh()->binding_state)->toBe(AssessmentAdministration::STATE_READY_TO_FINALIZE);

    $finalizedSession = app(FinalizeGradingSession::class)->handle($gradingSession, $this->guidance);

    expect($finalizedSession->status)->toBe(GradingSession::STATUS_FINALIZED);

    $resultSet = AssessmentResultSet::query()
        ->where('administration_id', $administration->id)
        ->first();

    expect($resultSet)->not->toBeNull()
        ->and($resultSet->status)->toBe(AssessmentResultSet::STATUS_FINALIZED)
        ->and($resultSet->grading_session_id)->toBe($gradingSession->id)
        ->and($resultSet->finalized_by_user_id)->toBe($this->guidance->id)
        ->and($resultSet->finalized_at)->not->toBeNull();

    expect($administration->fresh()->binding_state)->toBe(AssessmentAdministration::STATE_FINALIZED);

    expect(AuditEvent::query()
        ->where('event_type', 'assessment.result_set_finalized')
        ->where('payload->administration_id', $administration->id)
        ->where('payload->result_set_id', $resultSet->id)
        ->exists()
    )->toBeTrue();
});

test('formula acceptance rejects direct posts and freezes exact formula and item-scope provenance at finalization', function (): void {
    $legacyProfile = createResultSetApprovedProfileVersion($this, $this->guidance, 'PROF-FORMULA-LEGACY');
    $validator = new TestInstrumentProfileValidator;
    $legacyNormalized = $validator->normalizeVersion($legacyProfile);
    expect(array_key_exists('formula_expression', $legacyNormalized['metrics'][0]))->toBeFalse()
        ->and($validator->digest($legacyNormalized))->toBe($legacyProfile->content_sha256);

    $profile = createResultSetApprovedProfileVersion($this, $this->guidance, 'PROF-FORMULA-FINAL', true);
    $formulaNormalized = $validator->normalizeVersion($profile);
    expect($validator->digest($formulaNormalized))->toBe($profile->content_sha256)
        ->and($formulaNormalized['metrics'][1]['formula_algorithm_identity'])->toBe('nexiam.raw-formula.v1')
        ->and($formulaNormalized['metrics'][0]['item_codes'])->toBe(['SUB_A_1']);
    $persistedFormula = $profile->metrics()->where('code', 'WEIGHTED_RAW')->firstOrFail();
    expect($persistedFormula->formula_expression)->toBe('0.50*RAW_A')
        ->and($persistedFormula->formula_ast_json)->toBe($formulaNormalized['metrics'][1]['formula_ast_json'])
        ->and($profile->lock_version)->toBeGreaterThan(1);
    $norm = createResultSetFormulaNormVersion($this->guidance, 'NORM-FORMULA-FINAL');
    $fixture = createResultSetDirectAdministration($this, $this->guidance, $profile, 'formula-final@example.test');
    $administration = $fixture['administration'];

    expect(fn () => app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['WEIGHTED_RAW' => '0.50'],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: 'formula-direct-post',
    ))->toThrow(ValidationException::class);

    app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['RAW_A' => 1],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: 'formula-first-input',
    );
    $firstFormulaRevision = $administration->rawRevisions()
        ->where('metric_code', 'WEIGHTED_RAW')
        ->where('is_current', true)
        ->firstOrFail();
    $firstDirectRevision = $administration->rawRevisions()
        ->where('metric_code', 'RAW_A')
        ->where('is_current', true)
        ->firstOrFail();
    expect(BigDecimal::of($firstFormulaRevision->raw_value)->compareTo('0.5'))->toBe(0)
        ->and($firstFormulaRevision->provenance_json['formula_snapshot']['components'][0]['revision_id'])->toBe($firstDirectRevision->id)
        ->and($firstDirectRevision->provenance_json['item_scope']['item_codes'])->toBe(['SUB_A_1']);

    app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['RAW_A' => 0],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: 'formula-zero-correction',
    );
    $formulaRevision = $administration->rawRevisions()
        ->where('metric_code', 'WEIGHTED_RAW')
        ->where('is_current', true)
        ->firstOrFail();
    expect($firstFormulaRevision->fresh()->is_current)->toBeFalse()
        ->and(BigDecimal::of($formulaRevision->raw_value)->isZero())->toBeTrue()
        ->and(BigDecimal::of($formulaRevision->provenance_json['formula_snapshot']['components'][0]['raw_value'])->isZero())->toBeTrue();

    app(ConvertAssessmentResults::class)->handle($administration, $norm, $this->guidance);
    app(FinalizeGradingSession::class)->handle($fixture['gradingSession'], $this->guidance);
    $resultSet = AssessmentResultSet::query()->where('administration_id', $administration->id)->firstOrFail();
    $frozenFormula = $resultSet->raw_revision_vector_json['WEIGHTED_RAW'];
    $frozenDirect = $resultSet->raw_revision_vector_json['RAW_A'];
    expect($frozenFormula['provenance']['formula_snapshot']['expression'])->toBe('0.50*RAW_A')
        ->and($frozenFormula['provenance']['formula_snapshot']['algorithm_identity'])->toBe('nexiam.raw-formula.v1')
        ->and($frozenFormula['provenance']['component_revision_ids'])->toBe([$frozenDirect['id']])
        ->and(BigDecimal::of($frozenFormula['provenance']['formula_snapshot']['components'][0]['raw_value'])->isZero())->toBeTrue()
        ->and($frozenDirect['provenance']['item_scope']['item_codes'])->toBe(['SUB_A_1'])
        ->and($resultSet->conversion_revision_vector_json['source'])->toBe('norm_converted');
});

test('conversion rejects an approved integer norm table with a gap on a formula metric decimal grid', function (): void {
    $profile = createResultSetApprovedProfileVersion(
        $this,
        $this->guidance,
        'PROF-FORMULA-NORM-GRID',
        true,
        '1.50*RAW_A',
    );
    $norm = createResultSetFormulaNormVersion(
        $this->guidance,
        'NORM-FORMULA-INTEGER-GRID',
        true,
    );
    $fixture = createResultSetDirectAdministration($this, $this->guidance, $profile, 'formula-grid@example.test');
    $administration = $fixture['administration'];
    app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['RAW_A' => 1],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: 'formula-grid-input',
    );

    expect(BigDecimal::of((string) $administration->rawRevisions()
        ->where('metric_code', 'WEIGHTED_RAW')
        ->where('is_current', true)
        ->value('raw_value'))->compareTo('1.50'))->toBe(0)
        ->and($norm->status)->toBe(AssessmentNormPackageVersion::STATUS_APPROVED)
        ->and(fn () => app(ConvertAssessmentResults::class)->handle($administration, $norm, $this->guidance))
        ->toThrow(RuntimeException::class, 'assessment_norm_grid_incompatible');
});
test('AssessmentResultQuery projects finalized status and exact typed outputs when AssessmentResultSet exists', function (): void {
    $profileVersion = createResultSetApprovedProfileVersion($this, $this->guidance, 'PROF-FIN-2');
    $normVersion = createResultSetApprovedNormVersion($this->guidance, 'NORM-PKG-FIN-2');
    $fixture = createResultSetDirectAdministration($this, $this->guidance, $profileVersion, 'fin-examinee-2@example.test');

    $attempt = $fixture['attempt'];
    $gradingSession = $fixture['gradingSession'];
    $administration = $fixture['administration'];

    app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['RAW_A' => 2],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: (string) Str::uuid(),
    );

    app(ConvertAssessmentResults::class)->handle(
        $administration,
        $normVersion,
        $this->guidance,
    );

    app(FinalizeGradingSession::class)->handle($gradingSession, $this->guidance);

    $resultSet = AssessmentResultSet::query()
        ->where('administration_id', $administration->id)
        ->firstOrFail();

    $query = app(AssessmentResultQuery::class);

    $projection = $query->forAttempt($attempt);

    expect($projection['status'])->toBe('finalized')
        ->and($projection['provenance'])->toBe('profile_bound')
        ->and($projection['administration_id'])->toBe($administration->id)
        ->and($projection['metrics'])->not->toBeEmpty();

    $metric = collect($projection['metrics'])->firstWhere('code', 'RAW_A');
    expect($metric)->not->toBeNull()
        ->and($metric['status'])->toBe('finalized')
        ->and($metric['raw_score'])->toBe(2.0);

    // Exact typed outputs match the finalized conversion revision vector
    $conversionVector = $resultSet->conversion_revision_vector_json;
    expect($conversionVector)->toHaveKey('typed_outputs');
    $typedOutputs = $conversionVector['typed_outputs']['RAW_A'];

    $rankProjection = $query->forAttempt($attempt, 'percentile_rank');
    $rankMetric = collect($rankProjection['metrics'])->firstWhere('code', 'RAW_A');
    expect($rankMetric['converted_score'])->toBe($typedOutputs['percentile_rank'])
        ->and($rankMetric['converted_score'])->toBe('75');

    $stanineProjection = $query->forAttempt($attempt, 'stanine');
    $stanineMetric = collect($stanineProjection['metrics'])->firstWhere('code', 'RAW_A');
    expect($stanineMetric['converted_score'])->toBe($typedOutputs['stanine'])
        ->and($stanineMetric['converted_score'])->toBe('6');
});

test('Post-finalization correction without approved D-043 policy remains blocked', function (): void {
    $profileVersion = createResultSetApprovedProfileVersion($this, $this->guidance, 'PROF-FIN-3');
    $normVersion = createResultSetApprovedNormVersion($this->guidance, 'NORM-PKG-FIN-3');
    $fixture = createResultSetDirectAdministration($this, $this->guidance, $profileVersion, 'fin-examinee-3@example.test');

    $attempt = $fixture['attempt'];
    $gradingSession = $fixture['gradingSession'];
    $administration = $fixture['administration'];

    app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['RAW_A' => 2],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: (string) Str::uuid(),
    );

    app(ConvertAssessmentResults::class)->handle(
        $administration,
        $normVersion,
        $this->guidance,
    );

    app(FinalizeGradingSession::class)->handle($gradingSession, $this->guidance);

    expect($administration->fresh()->binding_state)->toBe(AssessmentAdministration::STATE_FINALIZED);

    $resultSetBefore = AssessmentResultSet::query()
        ->where('administration_id', $administration->id)
        ->firstOrFail();

    // Direct score recording/correction on the finalized session is blocked
    expect(fn () => app(RecordScores::class)->handle(
        gradingSession: $gradingSession,
        attempt: $attempt,
        scores: [['code' => 'RAW_A', 'score' => 3]],
        actor: $this->guidance,
        idempotencyKey: (string) Str::uuid(),
    ))->toThrow(RuntimeException::class, 'Grading session is finalized.');

    // Clearing scores on the finalized session is blocked
    expect(fn () => app(ClearScores::class)->handle(
        gradingSession: $gradingSession,
        attempt: $attempt,
        actor: $this->guidance,
    ))->toThrow(RuntimeException::class, 'Grading session is finalized.');

    // HTTP update route blocks correction
    actingAsAdmissionSupportStaff($this, $this->guidance)
        ->put(route('grading.sessions.attempts.scores.update', [$gradingSession, $attempt]), [
            'scores' => [['metric_code' => 'RAW_A', 'score' => 3]],
            'idempotency_key' => 'blocked-corr-'.Str::uuid(),
        ])
        ->assertSessionHasErrors('scores');

    // HTTP destroy route blocks score clearing
    actingAsAdmissionSupportStaff($this, $this->guidance)
        ->delete(route('grading.sessions.attempts.scores.destroy', [$gradingSession, $attempt]), [
            'expected_version' => (int) $gradingSession->fresh()->version,
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertSessionHasErrors('scores');

    // Administration binding_state and AssessmentResultSet remain unchanged
    expect($administration->fresh()->binding_state)->toBe(AssessmentAdministration::STATE_FINALIZED);

    $resultSetAfter = AssessmentResultSet::query()
        ->where('administration_id', $administration->id)
        ->firstOrFail();

    expect($resultSetAfter->id)->toBe($resultSetBefore->id)
        ->and($resultSetAfter->status)->toBe(AssessmentResultSet::STATUS_FINALIZED)
        ->and($resultSetAfter->raw_revision_vector_json['RAW_A']['raw_value'])->toBe('2.000000')
        ->and($resultSetAfter->conversion_revision_vector_json)->toEqual($resultSetBefore->conversion_revision_vector_json);
});

test('a selected unscored sitting never borrows frozen or legacy scores from another sitting', function (): void {
    $profile = createResultSetApprovedProfileVersion($this, $this->guidance, 'EXACT-SITTING-PROFILE');
    $norm = createResultSetApprovedNormVersion($this->guidance, 'EXACT-SITTING-NORM');
    $first = createResultSetDirectAdministration($this, $this->guidance, $profile, 'exact-sitting@example.test');
    app(AcceptAssessmentRawResults::class)->handle(
        administration: $first['administration'], metricValues: ['RAW_A' => 2],
        source: AssessmentScoringRun::SOURCE_MANUAL, actor: $this->guidance,
        idempotencyKey: (string) Str::uuid(),
    );
    app(ConvertAssessmentResults::class)->handle($first['administration'], $norm, $this->guidance);
    app(FinalizeGradingSession::class)->handle($first['gradingSession'], $this->guidance);
    $frozen = AssessmentResultSet::query()->where('administration_id', $first['administration']->id)->sole();
    $frozenBefore = $frozen->getAttributes();
    ScoreRecord::query()->create([
        'grading_session_id' => $first['gradingSession']->id,
        'admission_attempt_id' => $first['attempt']->id,
        'aptitude_area_id' => AptitudeArea::query()->where('is_active', true)->value('id'),
        'score' => '99', 'converted_score' => '99',
        'source' => 'manual', 'recorded_by_user_id' => $this->guidance->id,
        'idempotency_key' => 'retained-legacy-sitting',
    ]);
    // Two retained sittings are a query fixture, not permission to conduct a new retake.
    $first['assignment']->forceFill([
        'status' => ExamAssignment::STATUS_REMOVED, 'ended_at' => now(),
        'end_reason' => 'Historical sitting closed',
    ])->save();
    $originalSession = $first['gradingSession']->examSession;
    $selectedSession = ExamSession::query()->create([
        'academic_cycle_id' => $originalSession->academic_cycle_id,
        'room_id' => $originalSession->room_id,
        'code' => 'EXACT-SECOND-SITTING',
        'status' => ExamSession::STATUS_COMPLETED,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->subHour(), 'effective_end_at' => now(),
        'closed_at' => now(), 'version' => 1,
    ]);
    ExamAssignment::query()->create([
        'exam_session_id' => $selectedSession->id,
        'admission_attempt_id' => $first['attempt']->id,
        'status' => ExamAssignment::STATUS_ASSIGNED, 'version' => 1,
    ]);
    $selectedGrading = new GradingSession([
        'exam_session_id' => $selectedSession->id,
        'status' => GradingSession::STATUS_OPEN, 'version' => 1, 'opened_at' => now(),
    ]);
    $selectedGrading->forceFill(['id' => 99001])->save();
    $query = app(AssessmentResultQuery::class);
    $selected = $query->forGradingSession($selectedGrading, 'percentile_rank')[$first['attempt']->id];
    $selectedForAttempt = $query->forAttempt($first['attempt'], 'percentile_rank');
    $consultationProjection = app(ConsultationScoreProjection::class)->forAttempt($first['attempt']);
    expect($selected['administration_id'])->toBeNull()
        ->and($selected['status'])->toBe('unscored')
        ->and($selected['metrics'])->toBe([])
        ->and($selectedForAttempt['status'])->toBe('unscored')
        ->and($selectedForAttempt['metrics'])->toBe([])
        ->and(collect($consultationProjection['series'])->contains('available', true))->toBeFalse();
    $retained = $query->forGradingSession($first['gradingSession']->fresh(), 'percentile_rank')[$first['attempt']->id];
    expect($retained['administration_id'])->toBe($first['administration']->id)
        ->and($retained['status'])->toBe('finalized')
        ->and(collect($retained['metrics'])->firstWhere('code', 'RAW_A')['converted_score'])->toBe('75')
        ->and($frozen->fresh()->getAttributes())->toBe($frozenBefore);
    $this->withoutVite();
    actingAsAdmissionSupportStaff($this, $this->guidance)
        ->get(route('scoring.sessions.show', ['examSession' => $selectedSession->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('session.exam_session_id', $selectedSession->id)
            ->where('session.status', GradingSession::STATUS_OPEN));
    actingAsAdmissionSupportStaff($this, $this->guidance)
        ->get(route('grading.sessions.show', ['gradingSession' => $selectedGrading->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('session.exam_session_id', $selectedSession->id));
    actingAsAdmissionSupportStaff($this, $this->guidance)
        ->get(route('scoring.sessions.show', ['examSession' => $selectedGrading->id]))
        ->assertNotFound();
});
