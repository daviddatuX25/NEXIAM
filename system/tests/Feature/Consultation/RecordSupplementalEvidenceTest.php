<?php

use App\AdmissionEngine\DTOs\AdmissionInputPayload;
use App\Assessment\Actions\ApproveTestInstrumentVersion;
use App\Assessment\Support\TestInstrumentDraftWriter;
use App\Assessment\Support\TestInstrumentProfileValidator;
use App\Consultation\Actions\RecordSupplementalEvidence;
use App\Consultation\Queries\ConsultationAdmissionSupportQuery;
use App\Consultation\Support\ConsultationScoreProjection;
use App\Examination\Actions\BindAssessmentAdministration;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Admission\AdmissionSupportSnapshot;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\AssessmentResultSet;
use App\Models\Assessment\AssessmentScoringRun;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentMetricInput;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Database\Seeders\AdmissionBenchmarkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/../../Support/AdmissionModelFixtures.php';
require_once __DIR__.'/../../Support/TestKitHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'admission_engine.tabfm.institutional_use_approved' => true,
        'admission_engine.tabfm.hosted_use_approved' => true,
        'admission_engine.tabfm.package_version' => '9.1.0',
        'admission_engine.tabfm.checkpoint_id' => 'Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt',
        'admission_engine.tabfm.checkpoint_sha256' => '1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88',
    ]);
});

/** @return array{version: TestInstrumentVersion, metric: TestInstrumentMetric, area: AptitudeArea, sum_metric: TestInstrumentMetric|null} */
function createSupplementalScoreProfile(
    AdmissionAttempt $attempt,
    User $actor,
    bool $withSum = false,
    bool $withFormula = false,
): array {
    $area = AptitudeArea::query()->where('code', 'LOGIC')->where('is_active', true)->firstOrFail();
    $instrument = TestInstrument::query()->create([
        'institution_id' => $attempt->institution_id,
        'code' => 'SUPPLEMENTAL-'.$attempt->id,
    ]);
    $instrument->forceFill(['is_synthetic' => true])->save();

    $subtestSpecs = match (true) {
        $withSum => [
            ['code' => 'SUPPLEMENTAL_SUBTEST', 'name' => 'Supplemental subtest', 'count' => 10],
            ['code' => 'A_SUBTEST', 'name' => 'Component A subtest', 'count' => 30],
            ['code' => 'B_SUBTEST', 'name' => 'Component B subtest', 'count' => 40],
        ],
        $withFormula => [
            ['code' => 'C_SUBTEST', 'name' => 'Component C subtest', 'count' => 4],
            ['code' => 'D_SUBTEST', 'name' => 'Component D subtest', 'count' => 6],
            ['code' => 'B_SUBTEST', 'name' => 'Component B subtest', 'count' => 15],
        ],
        default => [['code' => 'SUPPLEMENTAL_SUBTEST', 'name' => 'Supplemental subtest', 'count' => 10]],
    };

    $subtests = [];
    foreach ($subtestSpecs as $subtest) {
        $items = [];
        for ($position = 1; $position <= $subtest['count']; $position++) {
            $items[] = [
                'item_code' => $subtest['code'].'_'.$position,
                'local_number' => (string) $position,
                'display_order' => $position,
                'is_scored' => true,
                'option_tokens' => ['A', 'B'],
            ];
        }
        $subtests[] = [
            'code' => $subtest['code'],
            'name' => $subtest['name'],
            'display_order' => count($subtests) + 1,
            'response_mode' => 'keyed_items',
            'item_order_policy' => 'fixed',
            'items' => $items,
        ];
    }

    $inputContract = [
        'allowed_sources' => ['import', 'manual'],
        'required_resources' => [],
        'source_requirements' => [
            'import' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
            'manual' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
        ],
    ];
    $mainMetric = [
        'code' => 'SUPPLEMENTAL_RAW',
        'name' => 'Supplemental aptitude metric',
        'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
        'subtest_code' => 'SUPPLEMENTAL_SUBTEST',
        'unit' => 'count',
        'min_raw_value' => null,
        'max_raw_value' => null,
        'precision_scale' => 0,
        'is_required' => true,
        'required_output_scales' => ['percentile_rank', 'stanine', 'category'],
        'procedure_reference' => null,
        'construct_aptitude_area_id' => (int) $area->id,
        'input_codes' => [],
        'input_contract_json' => $inputContract,
    ];
    if ($withFormula) {
        $mainMetric['rule_kind'] = TestInstrumentMetric::RULE_FORMULA_RAW_METRICS;
        $mainMetric['subtest_code'] = null;
        $mainMetric['input_codes'] = ['A_RAW', 'B_RAW'];
        $mainMetric['input_contract_json'] = $inputContract;
        $mainMetric['formula_expression'] = '0.20*A_RAW+0.80*B_RAW';
    }
    $metrics = [$mainMetric];
    if ($withSum) {
        foreach ([
            ['code' => 'A_RAW', 'name' => 'Component A', 'subtest_code' => 'A_SUBTEST'],
            ['code' => 'B_RAW', 'name' => 'Component B', 'subtest_code' => 'B_SUBTEST'],
        ] as $component) {
            $metrics[] = [
                ...$component,
                'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
                'unit' => 'count',
                'min_raw_value' => null,
                'max_raw_value' => null,
                'precision_scale' => 0,
                'is_required' => true,
                'required_output_scales' => ['percentile_rank'],
                'procedure_reference' => null,
                'construct_aptitude_area_id' => null,
                'input_codes' => [],
                'input_contract_json' => $inputContract,
            ];
        }
        $metrics[] = [
            'code' => 'SUM_RAW',
            'name' => 'Summed aptitude metric',
            'rule_kind' => TestInstrumentMetric::RULE_SUM_RAW_METRICS,
            'subtest_code' => null,
            'unit' => 'count',
            'min_raw_value' => null,
            'max_raw_value' => null,
            'precision_scale' => 0,
            'is_required' => true,
            'required_output_scales' => ['percentile_rank'],
            'procedure_reference' => null,
            'construct_aptitude_area_id' => null,
            'input_codes' => ['A_RAW', 'B_RAW'],
            'input_contract_json' => $inputContract,
        ];
    } elseif ($withFormula) {
        foreach ([
            ['code' => 'C_RAW', 'name' => 'Component C', 'subtest_code' => 'C_SUBTEST'],
            ['code' => 'D_RAW', 'name' => 'Component D', 'subtest_code' => 'D_SUBTEST'],
            ['code' => 'B_RAW', 'name' => 'Component B', 'subtest_code' => 'B_SUBTEST'],
        ] as $component) {
            $metrics[] = [
                ...$component,
                'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
                'unit' => 'count',
                'min_raw_value' => null,
                'max_raw_value' => null,
                'precision_scale' => 0,
                'is_required' => true,
                'required_output_scales' => ['percentile_rank'],
                'procedure_reference' => null,
                'construct_aptitude_area_id' => null,
                'input_codes' => [],
                'input_contract_json' => $inputContract,
            ];
        }
        $metrics[] = [
            'code' => 'A_RAW',
            'name' => 'Component A',
            'rule_kind' => TestInstrumentMetric::RULE_SUM_RAW_METRICS,
            'subtest_code' => null,
            'unit' => 'count',
            'min_raw_value' => null,
            'max_raw_value' => null,
            'precision_scale' => 0,
            'is_required' => true,
            'required_output_scales' => ['percentile_rank'],
            'procedure_reference' => null,
            'construct_aptitude_area_id' => null,
            'input_codes' => ['C_RAW', 'D_RAW'],
            'input_contract_json' => $inputContract,
        ];
    }

    $payload = [
        'profile' => [
            'code' => 'SUPPLEMENTAL-'.$attempt->id,
            'display_name' => 'Synthetic supplemental test profile',
            'publisher_name' => 'Synthetic software fixture',
            'form_code' => 'SUPPLEMENTAL-A',
            'edition' => 'Synthetic test fixture',
            'language' => 'Test-only',
            'source_reference' => 'test-fixture://supplemental-profile',
            'permitted_use_reference' => 'test-fixture://supplemental-use',
            'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER,
            'administration_procedure_reference' => 'Synthetic direct manual test procedure',
        ],
        'subtests' => $subtests,
        'metrics' => $metrics,
    ];
    $version = new TestInstrumentVersion([
        'test_instrument_id' => $instrument->id,
        'version_number' => 1,
        'status' => TestInstrumentVersion::STATUS_DRAFT,
        'lock_version' => 1,
        'created_by_user_id' => $actor->id,
    ]);
    app(TestInstrumentDraftWriter::class)->save(
        $version,
        app(TestInstrumentProfileValidator::class)->normalize($payload),
    );
    $version = app(ApproveTestInstrumentVersion::class)->handle(
        $actor,
        (int) $instrument->id,
        (int) $version->id,
        (int) $version->lock_version,
        (string) Str::uuid(),
        sourceReviewAttestation: true,
        isDemo: true,
    );

    return [
        'version' => $version,
        'metric' => $version->metrics()->where('code', 'SUPPLEMENTAL_RAW')->firstOrFail(),
        'area' => $area,
        'sum_metric' => $withSum ? $version->metrics()->where('code', 'SUM_RAW')->firstOrFail() : null,
    ];
}

/** @return array{assignment: ExamAssignment, session: ExamSession, grading: GradingSession, administration: AssessmentAdministration, result_set: ?AssessmentResultSet, metric: TestInstrumentMetric} */
function createSupplementalBoundSitting(
    AdmissionAttempt $attempt,
    User $actor,
    TestInstrumentVersion $version,
    TestInstrumentMetric $metric,
    array $typedOutputs = ['percentile_rank' => '74'],
    bool $gradingFinalized = true,
    bool $withResultSet = true,
): array {
    ExamAssignment::query()
        ->active()
        ->where('admission_attempt_id', $attempt->id)
        ->update(['status' => ExamAssignment::STATUS_REMOVED]);

    $session = ExamSession::factory()->create([
        'academic_cycle_id' => $attempt->academic_cycle_id,
        'status' => 'completed',
    ]);
    $session->forceFill(['test_instrument_version_id' => $version->id])->save();
    $assignment = ExamAssignment::factory()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
    ]);
    $grading = GradingSession::query()->create([
        'exam_session_id' => $session->id,
        'status' => $gradingFinalized ? GradingSession::STATUS_FINALIZED : GradingSession::STATUS_OPEN,
        'version' => 1,
        'opened_at' => now()->subHour(),
        'finalized_at' => $gradingFinalized ? now() : null,
    ]);
    $administration = createSupplementalBoundAdministration($assignment, $actor, $version);
    $resultSet = $gradingFinalized && $withResultSet
        ? createSupplementalFinalizedResultSet($administration, $grading, $actor, $metric, $typedOutputs)
        : null;

    return [
        'assignment' => $assignment,
        'session' => $session,
        'grading' => $grading,
        'administration' => $administration,
        'result_set' => $resultSet,
        'metric' => $metric,
    ];
}

function createSupplementalBoundAdministration(
    ExamAssignment $assignment,
    User $actor,
    TestInstrumentVersion $version,
): AssessmentAdministration {
    $kitVersion = testKitSetupOnlyFixture($actor, $version)['version'];
    $kitVersion->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'activated_at' => now(),
        'activated_by_user_id' => $actor->id,
        'revision' => (int) $kitVersion->revision + 1,
    ])->save();
    $assignment->examSession()->firstOrFail()->forceFill([
        'test_kit_version_id' => $kitVersion->id,
        'test_instrument_version_id' => $version->id,
    ])->save();

    return app(BindAssessmentAdministration::class)->handle($assignment, $kitVersion, $actor, 'manual');
}

/**
 * @param  array<string, mixed>  $provenance
 */
function createSupplementalRetainedRawRevision(
    AssessmentAdministration $administration,
    User $actor,
    TestInstrumentMetric $metric,
    string $rawValue,
    string $source,
    array $provenance = [],
): AssessmentRawResultRevision {
    $run = AssessmentScoringRun::query()->create([
        'administration_id' => $administration->id,
        'run_uuid' => (string) Str::uuid(),
        'source' => $source === AssessmentRawResultRevision::SOURCE_DERIVED_COMPOSITE
            ? AssessmentScoringRun::SOURCE_MANUAL
            : $source,
        'actor_user_id' => $actor->id,
        'idempotency_key' => (string) Str::uuid(),
        'created_at' => now(),
    ]);

    return AssessmentRawResultRevision::query()->create([
        'administration_id' => $administration->id,
        'scoring_run_id' => $run->id,
        'metric_id' => $metric->id,
        'metric_code' => $metric->code,
        'revision_no' => 1,
        'raw_value' => $rawValue,
        'source' => $source,
        'status' => AssessmentRawResultRevision::STATUS_VALID,
        'provenance_json' => $provenance,
        'is_current' => true,
        'superseded_at' => null,
        'created_at' => now(),
    ]);
}

function createSupplementalFinalizedResultSet(
    AssessmentAdministration $administration,
    GradingSession $grading,
    User $actor,
    TestInstrumentMetric $metric,
    array $typedOutputs,
    ?Carbon $finalizedAt = null,
): AssessmentResultSet {
    $outputsByMetric = [$metric->code => $typedOutputs];

    return AssessmentResultSet::query()->create([
        'administration_id' => $administration->id,
        'grading_session_id' => $grading->id,
        'exam_session_id' => $grading->exam_session_id,
        'raw_revision_vector_json' => [
            $metric->code => ['raw_value' => '4.000000', 'source' => 'manual', 'status' => 'valid'],
        ],
        'conversion_revision_vector_json' => [
            'status' => 'valid',
            'typed_outputs' => $outputsByMetric,
            ...$outputsByMetric,
        ],
        'status' => AssessmentResultSet::STATUS_FINALIZED,
        'finalized_by_user_id' => $actor->id,
        'finalized_at' => $finalizedAt ?? now(),
    ]);
}

/** @param array<string, mixed> $values @return array<string, mixed> */
function supplementalEvidenceRequest(AdmissionAttempt $attempt, array $values): array
{
    $activation = AdmissionBenchmarkActivation::currentForCycle((int) $attempt->academic_cycle_id);
    $set = $activation === null ? null : AdmissionBenchmarkSet::query()->with('features')->find($activation->benchmark_set_id);

    if ($activation === null || ! $set instanceof AdmissionBenchmarkSet) {
        throw new RuntimeException('Supplemental evidence tests require an in-use synthetic benchmark.');
    }

    return array_merge([
        'benchmark_activation_id' => (int) $activation->id,
        'schema_digest' => (string) $set->schema_sha256,
        'evidence_source' => 'examinee_statement',
    ], $values);
}

/** Synthetic frozen consumer-contract fixture; provenance does not evidence a real model run. */
function createRecordedSupplementalSnapshot(
    AdmissionAttempt $attempt,
    User $actor,
    ?array $assessmentContext = null,
    ?string $inputDigest = null,
    string $backend = 'tabpfn_pytorch',
): AdmissionSupportSnapshot {
    $activation = AdmissionBenchmarkActivation::currentForCycle((int) $attempt->academic_cycle_id);
    $set = AdmissionBenchmarkSet::query()->with('features')->findOrFail($activation->benchmark_set_id);
    $projection = app(ConsultationScoreProjection::class)->forAttempt($attempt);
    $input = AdmissionInputPayload::fromAttempt(
        $attempt,
        ConsultationScoreProjection::admissionInputSeries($projection),
    );

    return AdmissionSupportSnapshot::query()->create([
        'admission_attempt_id' => $attempt->id,
        'academic_cycle_id' => $attempt->academic_cycle_id,
        'version' => ((int) AdmissionSupportSnapshot::query()->where('admission_attempt_id', $attempt->id)->max('version')) + 1,
        'engine_slot' => 'tabfm_pairwise',
        'engine_version' => '9.1.0',
        'benchmark_activation_id' => $activation->id,
        'benchmark_hash' => $set->content_sha256,
        'input_digest' => $inputDigest ?? $input->digest(),
        'payload' => [
            'slot' => 'tabfm_pairwise',
            'status' => 'evaluated',
            'engine_version' => '9.1.0',
            'benchmark_version' => 'recorded-benchmark-version',
            'benchmark_name' => 'Recorded benchmark',
            'evaluated_at' => now()->subDay()->toIso8601String(),
            'inference_backend' => $backend,
            'fallback_reason' => null,
            'modality_coverage' => [
                'inference_provenance' => [
                    'package' => 'tabpfn',
                    'package_version' => '9.1.0',
                    'checkpoint_identity' => 'Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt',
                    'checkpoint_sha256' => '1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88',
                ],
            ],
            'recommendations' => [['program_code' => 'BSN', 'rank' => 1, 'compatibility_index' => 82.0]],
            'applicant_radar' => ['realistic' => 0.8],
            'evaluation_context' => [
                'source' => 'activation',
                'benchmark_activation_id' => (int) $activation->id,
                'benchmark_set_id' => (int) $set->id,
                'benchmark_revision_id' => null,
                'benchmark_draft_id' => null,
                'benchmark_version' => 'v'.$set->version,
                'schema_digest' => (string) $set->schema_sha256,
                'content_digest' => (string) $set->content_sha256,
                'input_digest' => $inputDigest ?? $input->digest(),
                'request_id' => 'test-request',
                'institution_id' => (int) $attempt->institution_id,
                'academic_cycle_id' => (int) $attempt->academic_cycle_id,
                'program_id' => null,
                'benchmark_recovery_id' => null,
            ],
            'assessment_context' => $assessmentContext ?? $projection['assessment_context'],
        ],
        'trigger_event' => 'supplemental_evidence_recorded',
        'recorded_by_user_id' => $actor->id,
    ]);
}

test('supplemental subject-grade input is rejected without rewriting recorded historical evidence', function (): void {
    [$attempt, , $counselor] = buildMessagingReadyAttempt($this, 'supp-evidence@example.test');
    (new AdmissionBenchmarkSeeder)->run();
    $historicalEvidence = [
        'subject_grades' => ['shs_math' => 81.5, 'shs_sci' => 82],
        'evidence_source' => 'legacy_record',
        'recorded_at' => '2026-01-01T00:00:00+00:00',
    ];
    $payload = is_array($attempt->payload) ? $attempt->payload : [];
    $payload['supplemental_evidence'] = $historicalEvidence;
    $attempt->forceFill(['payload' => $payload])->save();
    Permission::findOrCreate('guidance.consultation.decide');
    $counselor->givePermissionTo('guidance.consultation.decide');

    $response = actingAsAdmissionSupportStaff($this, $counselor)
        ->post(route('consultation.evidence.store', ['attempt' => $attempt->id]), supplementalEvidenceRequest($attempt, [
            'subject_grades' => ['shs_math' => 94.5],
            'vocational_activities' => ['hardware_tech'],
            'activities_reviewed' => true,
            'evidence_source' => 'examinee_statement',
            'idempotency_key' => 'test-supp-evidence-grade-rejected',
        ]));

    $response->assertSessionHasErrors(['subject_grades']);
    expect(AdmissionSupportSnapshot::latestForAttempt($attempt->id))->toBeNull()
        ->and($attempt->fresh()->payload['supplemental_evidence'])->toBe($historicalEvidence);
});

test('supplemental subject evidence fails closed on undeclared fields, precision and invalid source', function (): void {
    [$attempt, , $counselor] = buildMessagingReadyAttempt($this, 'supp-val@example.test');
    (new AdmissionBenchmarkSeeder)->run();
    Permission::findOrCreate('guidance.consultation.decide');
    $counselor->givePermissionTo('guidance.consultation.decide');

    $precision = actingAsAdmissionSupportStaff($this, $counselor)->post(route('consultation.evidence.store', ['attempt' => $attempt->id]), supplementalEvidenceRequest($attempt, [
        'subject_grades' => ['shs_math' => 88.123],
        'idempotency_key' => 'test-supp-val-precision',
    ]));
    $precision->assertSessionHasErrors(['subject_grades']);

    $unknown = actingAsAdmissionSupportStaff($this, $counselor)->post(route('consultation.evidence.store', ['attempt' => $attempt->id]), supplementalEvidenceRequest($attempt, [
        'subject_grades' => ['unregistered_biology' => 88],
        'idempotency_key' => 'test-supp-val-unknown',
    ]));
    $unknown->assertSessionHasErrors(['subject_grades']);

    $source = actingAsAdmissionSupportStaff($this, $counselor)->post(route('consultation.evidence.store', ['attempt' => $attempt->id]), supplementalEvidenceRequest($attempt, [
        'evidence_source' => 'invented_source',
        'idempotency_key' => 'test-supp-val-source',
    ]));
    $source->assertSessionHasErrors(['evidence_source']);
    expect(AdmissionSupportSnapshot::latestForAttempt($attempt->id))->toBeNull();
});

test('recording supplemental evidence rejects unprivileged actors', function (): void {
    [$attempt] = buildMessagingReadyAttempt($this, 'supp-auth@example.test');
    (new AdmissionBenchmarkSeeder)->run();

    $unprivileged = User::factory()->create();
    $response = $this->actingAs($unprivileged)->withSession(['auth.surface' => null, 'auth.session_version' => null])
        ->post(route('consultation.evidence.store', ['attempt' => $attempt->id]), supplementalEvidenceRequest($attempt, [
            'subject_grades' => ['shs_math' => 90.0],
            'idempotency_key' => 'test-supp-auth-1',
        ]));
    $response->assertForbidden();
});

test('idempotent supplemental evidence replay returns its original snapshot without duplicating version', function (): void {
    [$attempt, , $counselor] = buildCanonicalAdmissionSupportAttempt($this, 'supp-idem@example.test');
    (new AdmissionBenchmarkSeeder)->run();
    Permission::findOrCreate('guidance.consultation.decide');
    $counselor->givePermissionTo('guidance.consultation.decide');

    $payload = supplementalEvidenceRequest($attempt, [
        'idempotency_key' => 'idem-key-constant',
    ]);
    actingAsAdmissionSupportStaff($this, $counselor)->post(route('consultation.evidence.store', ['attempt' => $attempt->id]), $payload)->assertRedirect();
    $firstSnapshot = AdmissionSupportSnapshot::latestForAttempt($attempt->id);
    expect(AdmissionSupportSnapshot::where('admission_attempt_id', $attempt->id)->count())->toBe(1);

    actingAsAdmissionSupportStaff($this, $counselor)->post(route('consultation.evidence.store', ['attempt' => $attempt->id]), $payload)->assertRedirect();
    expect(AdmissionSupportSnapshot::where('admission_attempt_id', $attempt->id)->count())->toBe(1)
        ->and(AdmissionSupportSnapshot::latestForAttempt($attempt->id)->id)->toBe($firstSnapshot->id);
});
test('consultation evidence records GWA and counselor notes for the applicant projection', function (): void {
    [$attempt, , $counselor] = buildCanonicalAdmissionSupportAttempt($this, 'supp-gwa@example.test');
    (new AdmissionBenchmarkSeeder)->run();
    config(['admission_engine.tabfm.driver' => 'disabled']);
    Permission::findOrCreate('guidance.consultation.decide');
    $counselor->givePermissionTo('guidance.consultation.decide');

    $payload = supplementalEvidenceRequest($attempt, [
        'gwa' => 88.50,
        'counselor_notes' => 'Verified against the applicant-submitted Form 138.',
        'idempotency_key' => 'supp-gwa-and-notes',
    ]);
    unset($payload['evidence_source']);

    actingAsAdmissionSupportStaff($this, $counselor)
        ->post(route('consultation.evidence.store', ['attempt' => $attempt->id]), $payload)
        ->assertRedirect();

    $attempt = $attempt->fresh();
    $support = app(ConsultationAdmissionSupportQuery::class)->for($attempt);
    expect((float) $attempt->gwa)->toBe(88.50)
        ->and($attempt->payload['supplemental_evidence']['gwa'])->toBe(88.5)
        ->and($attempt->payload['supplemental_evidence']['counselor_notes'])->toBe('Verified against the applicant-submitted Form 138.')
        ->and($support['applicant_evidence']['gwa'])->toBe(88.5);
});

test('GWA and counselor notes save when no benchmark activation is bound and normalize reversed vs 1-highest scales equally', function (): void {
    [$attempt, , $counselor] = buildMessagingReadyAttempt($this, 'supp-gwa-no-benchmark@example.test');
    Permission::findOrCreate('guidance.consultation.decide');
    $counselor->givePermissionTo('guidance.consultation.decide');

    actingAsAdmissionSupportStaff($this, $counselor)
        ->post(route('consultation.evidence.store', ['attempt' => $attempt->id]), [
            'gwa' => 4.75,
            'gwa_scale' => 'gpa_5_highest',
            'counselor_notes' => 'Transferee TOR on 5.00-highest scale.',
            'idempotency_key' => 'supp-gwa-5-highest',
        ])
        ->assertRedirect();

    $attempt = $attempt->fresh();
    $support = app(ConsultationAdmissionSupportQuery::class)->for($attempt);
    expect((float) $attempt->gwa)->toBe(95.13)
        ->and($support['applicant_evidence']['gwa'])->toBe(95.13)
        ->and($support['applicant_evidence']['gwa_raw'])->toBe(4.75)
        ->and($support['applicant_evidence']['gwa_scale'])->toBe('gpa_5_highest')
        ->and($support['applicant_evidence']['gwa_is_converted'])->toBeTrue()
        ->and(AdmissionSupportSnapshot::latestForAttempt($attempt->id))->toBeNull();
});

test('score projection uses the active sitting and its current administration and finalized result', function (): void {
    [$attempt, , $counselor] = buildMessagingReadyAttempt($this, 'supp-score-identities@example.test');
    $profile = createSupplementalScoreProfile($attempt, $counselor);

    $previousAssignment = ExamAssignment::query()
        ->where('admission_attempt_id', $attempt->id)
        ->orderBy('id')
        ->firstOrFail();
    $previousSession = $previousAssignment->examSession()->firstOrFail();
    $previousGrading = GradingSession::query()
        ->where('exam_session_id', $previousSession->id)
        ->firstOrFail();
    $previousSession->forceFill(['test_instrument_version_id' => $profile['version']->id])->save();
    $previousAdministration = createSupplementalBoundAdministration(
        $previousAssignment,
        $counselor,
        $profile['version'],
    );
    createSupplementalFinalizedResultSet(
        $previousAdministration,
        $previousGrading,
        $counselor,
        $profile['metric'],
        ['percentile_rank' => '99', 'stanine' => '9'],
        now()->addDays(10),
    );
    $previousAssignment->update(['status' => ExamAssignment::STATUS_REMOVED]);

    $current = createSupplementalBoundSitting(
        $attempt,
        $counselor,
        $profile['version'],
        $profile['metric'],
        ['percentile_rank' => '73.5', 'stanine' => '5', 'category' => 'Upper band'],
        true,
    );

    $projection = app(ConsultationScoreProjection::class)->forAttempt($attempt);
    $entry = collect($projection['series'])->firstWhere('area_code', $profile['area']->code);

    expect($projection['selected_assignment_id'])->toBe($current['assignment']->id)
        ->and($projection['selected_administration_id'])->toBe($current['administration']->id)
        ->and($projection['selected_grading_session_id'])->toBe($current['grading']->id)
        ->and($entry)->not->toBeNull()
        ->and($entry['score'])->toBe(73.5)
        ->and($entry['percentile'])->toBe(73.5)
        ->and($entry['raw_score'])->toBe('4.000000')
        ->and($entry['scale'])->toBe('percentile_rank')
        ->and($entry['percentile_scale'])->toBe('percentile_rank')
        ->and($entry['converted_label'])->toBe('Upper band')
        ->and($entry['source'])->toBe('manual');

    $releasedProfile = app(ConsultationAdmissionSupportQuery::class)->releasedScoreProfile($attempt);
    $releasedEntry = collect($releasedProfile['series'])->firstWhere('area_code', $profile['area']->code);
    expect($releasedEntry)->toEqual($entry);

    $currentResultSet = $current['result_set'];
    foreach (['101', '1e309'] as $invalidPercentile) {
        $conversionVector = $currentResultSet->fresh()->conversion_revision_vector_json;
        $conversionVector['typed_outputs'][$profile['metric']->code]['percentile_rank'] = $invalidPercentile;
        $conversionVector[$profile['metric']->code]['percentile_rank'] = $invalidPercentile;
        $currentResultSet->forceFill(['conversion_revision_vector_json' => $conversionVector])->save();

        $invalidEntry = collect(app(ConsultationScoreProjection::class)->forAttempt($attempt)['series'])
            ->firstWhere('area_code', $profile['area']->code);
        expect($invalidEntry['percentile'])->toBeNull()
            ->and($invalidEntry['available'])->toBeFalse();
    }

    $conversionVector = $currentResultSet->fresh()->conversion_revision_vector_json;
    unset(
        $conversionVector['typed_outputs'][$profile['metric']->code]['percentile_rank'],
        $conversionVector[$profile['metric']->code]['percentile_rank'],
    );
    $conversionVector['typed_outputs'][$profile['metric']->code]['percentile'] = '65';
    $conversionVector[$profile['metric']->code]['percentile'] = '65';
    $currentResultSet->forceFill(['conversion_revision_vector_json' => $conversionVector])->save();
    $aliasEntry = collect(app(ConsultationScoreProjection::class)->forAttempt($attempt)['series'])
        ->firstWhere('area_code', $profile['area']->code);
    expect($aliasEntry['percentile'])->toBe(65.0)
        ->and($aliasEntry['scale'])->toBe('percentile')
        ->and($aliasEntry['percentile_scale'])->toBe('percentile');
});

test('advising uses frozen formula components and distinct metric and construct names', function (): void {
    [$attempt, , $counselor] = buildMessagingReadyAttempt($this, 'supp-frozen-formula@example.test');
    $profile = createSupplementalScoreProfile($attempt, $counselor, withFormula: true);
    $current = createSupplementalBoundSitting(
        $attempt,
        $counselor,
        $profile['version'],
        $profile['metric'],
        ['percentile_rank' => '81', 'stanine' => '5', 'category' => 'Upper band'],
    );

    expect($profile['metric']->rule_kind)->toBe(TestInstrumentMetric::RULE_FORMULA_RAW_METRICS)
        ->and($profile['metric']->formula_expression)->toBe('0.20*A_RAW+0.80*B_RAW')
        ->and($current['administration']->test_kit_version_id)->not->toBeNull()
        ->and((int) $current['administration']->test_instrument_version_id)->toBe((int) $profile['version']->id);

    $rawVector = [
        'C_RAW' => ['id' => 401, 'raw_value' => '4.000000', 'source' => 'manual', 'status' => 'valid'],
        'D_RAW' => ['id' => 402, 'raw_value' => '6.000000', 'source' => 'import', 'status' => 'valid'],
        'A_RAW' => [
            'id' => 501,
            'raw_value' => '10.000000',
            'source' => 'derived_composite',
            'status' => 'valid',
            'provenance' => [
                'rule_kind' => 'sum_raw_metrics',
                'component_metric_codes' => ['C_RAW', 'D_RAW'],
                'component_revision_ids' => [401, 402],
            ],
        ],
        'B_RAW' => ['id' => 502, 'raw_value' => '15.000000', 'source' => 'import', 'status' => 'valid'],
        $profile['metric']->code => [
            'revision_id' => 503,
            'raw_value' => '14.000000',
            'source' => 'derived_composite',
            'status' => 'valid',
            'provenance' => [
                'rule_kind' => 'formula_raw_metrics',
                'formula_snapshot' => [
                    'expression' => '0.20*A_RAW+0.80*B_RAW',
                    'algorithm_identity' => 'nexiam.raw-formula.v1',
                    'precision_scale' => 0,
                    'min_raw_value' => '0.000000',
                    'max_raw_value' => '14.000000',
                    'components' => [
                        ['metric_code' => 'A_RAW', 'revision_id' => 501, 'raw_value' => '10.000000', 'source' => 'derived_composite'],
                        ['metric_code' => 'B_RAW', 'revision_id' => 502, 'raw_value' => '15.000000', 'source' => 'import'],
                    ],
                ],
            ],
        ],
    ];
    $current['result_set']->forceFill(['raw_revision_vector_json' => $rawVector])->save();

    $projection = app(ConsultationScoreProjection::class)->forAttempt($attempt);
    $detail = collect($projection['assessment_detail']['subtests'])->firstWhere('code', $profile['metric']->code);

    expect($projection['assessment_detail']['capture_mode'])->toBe('mixed')
        ->and($detail['metric_kind'])->toBe('derived')
        ->and($detail['metric_name'])->toBe('Supplemental aptitude metric')
        ->and($detail['construct_name'])->toBe($profile['area']->name)
        ->and($detail['raw_score'])->toBe('14.000000')
        ->and($detail['raw_min_score'])->toBe('0.000000')
        ->and($detail['raw_max_score'])->toBe('14.000000')
        ->and($detail['formula_expression'])->toBe('0.20*A_RAW+0.80*B_RAW')
        ->and($detail['formula_substitution'])->toBe('0.20*10.000000+0.80*15.000000')
        ->and($detail['derivation'])->toContain('81 PR')
        ->and($detail['derivation'])->toContain('Stanine 5')
        ->and($detail['derivation'])->toContain('Upper band')
        ->and($detail['components'])->toBe([
            ['code' => 'A_RAW', 'name' => 'Component A', 'raw_value' => '10.000000', 'source' => 'Manually entered, Imported'],
            ['code' => 'B_RAW', 'name' => 'Component B', 'raw_value' => '15.000000', 'source' => 'Imported'],
        ])
        ->and($detail)->not->toHaveKey('item_codes')
        ->and($detail)->not->toHaveKey('formula_ast_json');
});

test('historical sums show a denominator only when frozen components match the pinned sum definition', function (): void {
    [$attempt, , $counselor] = buildMessagingReadyAttempt($this, 'supp-historical-sum-denominator@example.test');
    $profile = createSupplementalScoreProfile($attempt, $counselor, true);
    $sumMetric = $profile['sum_metric'];
    expect($sumMetric)->toBeInstanceOf(TestInstrumentMetric::class);
    $current = createSupplementalBoundSitting(
        $attempt,
        $counselor,
        $profile['version'],
        $sumMetric,
        ['percentile_rank' => '78'],
    );

    expect($current['administration']->test_kit_version_id)->not->toBeNull()
        ->and($sumMetric->inputs->map(static fn (TestInstrumentMetricInput $input): ?string => $input->inputMetric?->code)->all())
        ->toBe(['A_RAW', 'B_RAW']);
    $metricsByCode = $profile['version']->metrics()->get()->keyBy('code');
    $componentARevision = createSupplementalRetainedRawRevision(
        $current['administration'],
        $counselor,
        $metricsByCode->get('A_RAW'),
        '10.000000',
        AssessmentRawResultRevision::SOURCE_MANUAL,
    );
    $componentBRevision = createSupplementalRetainedRawRevision(
        $current['administration'],
        $counselor,
        $metricsByCode->get('B_RAW'),
        '15.000000',
        AssessmentRawResultRevision::SOURCE_IMPORT,
    );
    $sumRevision = createSupplementalRetainedRawRevision(
        $current['administration'],
        $counselor,
        $sumMetric,
        '25.000000',
        AssessmentRawResultRevision::SOURCE_DERIVED_COMPOSITE,
        [
            'rule_kind' => 'sum_raw_metrics',
            'component_metric_codes' => ['A_RAW', 'B_RAW'],
            'component_revision_ids' => [$componentARevision->id, $componentBRevision->id],
        ],
    );
    $legacyVector = [];
    foreach ([$componentARevision, $componentBRevision, $sumRevision] as $revision) {
        $legacyVector[$revision->metric_code] = [
            'id' => $revision->id,
            'metric_id' => $revision->metric_id,
            'metric_code' => $revision->metric_code,
            'revision_no' => $revision->revision_no,
            'raw_value' => (string) $revision->raw_value,
            'source' => $revision->source,
            'status' => $revision->status,
        ];
    }
    $current['result_set']->forceFill(['raw_revision_vector_json' => $legacyVector])->save();

    $projection = app(ConsultationScoreProjection::class)->forAttempt($attempt);
    $detail = collect($projection['assessment_detail']['subtests'])->firstWhere('code', $sumMetric->code);

    expect($detail['raw_score'])->toBe('25.000000')
        ->and($detail['raw_min_score'])->toBeNull()
        ->and($detail['raw_max_score'])->toBe('70.000000')
        ->and($detail['formula_expression'])->toBe('A_RAW + B_RAW')
        ->and($detail['formula_substitution'])->toBe('10.000000 + 15.000000')
        ->and($detail['components'])->toHaveCount(2);

    $mismatchedVector = $legacyVector;
    $mismatchedVector[$sumMetric->code]['metric_id'] = $metricsByCode->get('A_RAW')->id;
    $current['result_set']->forceFill(['raw_revision_vector_json' => $mismatchedVector])->save();
    $mismatchedDetail = collect(app(ConsultationScoreProjection::class)->forAttempt($attempt)['assessment_detail']['subtests'])
        ->firstWhere('code', $sumMetric->code);

    expect($mismatchedDetail['formula_expression'])->toBeNull()
        ->and($mismatchedDetail['components'])->toBe([])
        ->and($mismatchedDetail['raw_max_score'])->toBeNull();
});

test('historical sums with missing frozen component evidence remain unavailable', function (): void {
    [$attempt, , $counselor] = buildMessagingReadyAttempt($this, 'supp-historical-lineage-missing@example.test');
    $profile = createSupplementalScoreProfile($attempt, $counselor, true);
    $sumMetric = $profile['sum_metric'];
    $current = createSupplementalBoundSitting(
        $attempt,
        $counselor,
        $profile['version'],
        $sumMetric,
        ['percentile_rank' => '69'],
    );
    $current['result_set']->forceFill([
        'raw_revision_vector_json' => [
            $sumMetric->code => [
                'id' => 600,
                'metric_id' => $sumMetric->id,
                'metric_code' => $sumMetric->code,
                'revision_no' => 1,
                'raw_value' => '40.000000',
                'source' => 'derived_composite',
                'status' => 'valid',
            ],
        ],
    ])->save();

    $projection = app(ConsultationScoreProjection::class)->forAttempt($attempt);
    $detail = collect($projection['assessment_detail']['subtests'])->firstWhere('code', $sumMetric->code);

    expect($detail['metric_kind'])->toBe('derived')
        ->and($detail['formula_expression'])->toBeNull()
        ->and($detail['raw_max_score'])->toBeNull()
        ->and($detail['components'])->toBe([])
        ->and($detail['capture_source'])->toBe('Derived from raw metrics');
});

test('converted-only finalized results do not invent raw formula evidence', function (): void {
    [$attempt, , $counselor] = buildMessagingReadyAttempt($this, 'supp-converted-only@example.test');
    $profile = createSupplementalScoreProfile($attempt, $counselor);
    $current = createSupplementalBoundSitting(
        $attempt,
        $counselor,
        $profile['version'],
        $profile['metric'],
        ['percentile_rank' => '77'],
    );

    $conversionVector = $current['result_set']->conversion_revision_vector_json;
    $conversionVector['source'] = 'manual_converted';
    $current['result_set']->forceFill([
        'raw_revision_vector_json' => [],
        'conversion_revision_vector_json' => $conversionVector,
    ])->save();

    $projection = app(ConsultationScoreProjection::class)->forAttempt($attempt);
    $detail = collect($projection['assessment_detail']['subtests'])->firstWhere('code', $profile['metric']->code);

    expect($detail['raw_score'])->toBeNull()
        ->and($detail['formula_expression'])->toBeNull()
        ->and($detail['components'])->toBe([])
        ->and($detail['capture_source'])->toBe('External final (not calculated locally)')
        ->and($detail['percentile'])->toBe(77.0)
        ->and($projection['assessment_detail']['capture_mode'])->toBe('manual_converted');
});
test('manual-converted origin remains external when a raw profile also has a raw vector', function (): void {
    [$attempt, , $counselor] = buildMessagingReadyAttempt($this, 'supp-raw-profile-external-origin@example.test');
    $profile = createSupplementalScoreProfile($attempt, $counselor);
    $current = createSupplementalBoundSitting(
        $attempt,
        $counselor,
        $profile['version'],
        $profile['metric'],
        ['percentile_rank' => '77'],
    );
    $conversionVector = $current['result_set']->conversion_revision_vector_json;
    $conversionVector['source'] = 'manual_converted';
    $conversionVector['provenance'] = [
        'external_provenance' => [$profile['metric']->code => 'Synthetic source reference reserved for staff.'],
    ];
    $current['result_set']->forceFill([
        'raw_revision_vector_json' => [
            $profile['metric']->code => [
                'raw_value' => '14.000000',
                'source' => 'manual',
                'provenance_json' => [],
            ],
        ],
        'conversion_revision_vector_json' => $conversionVector,
    ])->save();

    $projection = app(ConsultationScoreProjection::class)->forAttempt($attempt);
    $detail = collect($projection['assessment_detail']['subtests'])->firstWhere('code', $profile['metric']->code);

    expect($detail['raw_score'])->not->toBeNull()
        ->and($detail['capture_source'])->toBe('External final (not calculated locally)')
        ->and($detail['external_source_reference'])->toBe('Synthetic source reference reserved for staff.')
        ->and($projection['assessment_detail']['capture_mode'])->toBe('manual_converted')
        ->and(collect($projection['series'])->firstWhere('area_code', $profile['area']->code)['source'])->toBe('manual_converted');
});

test('bound score projection does not borrow legacy results or superseded scores when work is unfinalized or has an invalid percentile', function (): void {
    [$attempt, , $counselor] = buildMessagingReadyAttempt($this, 'supp-score-unfinalized@example.test');
    $profile = createSupplementalScoreProfile($attempt, $counselor);
    $current = createSupplementalBoundSitting(
        $attempt,
        $counselor,
        $profile['version'],
        $profile['metric'],
        [],
        false,
        false,
    );

    $unfinalizedEntry = collect(app(ConsultationScoreProjection::class)->forAttempt($attempt)['series'])
        ->firstWhere('area_code', $profile['area']->code);
    expect($unfinalizedEntry['percentile'])->toBeNull()
        ->and($unfinalizedEntry['score'])->toBeNull()
        ->and($unfinalizedEntry['raw_score'])->toBeNull()
        ->and($unfinalizedEntry['available'])->toBeFalse();

    $current['grading']->update([
        'status' => GradingSession::STATUS_FINALIZED,
        'finalized_at' => now(),
    ]);
    createSupplementalFinalizedResultSet(
        $current['administration'],
        $current['grading']->fresh(),
        $counselor,
        $profile['metric'],
        ['percentile_rank' => '101'],
    );

    $untypedEntry = collect(app(ConsultationScoreProjection::class)->forAttempt($attempt)['series'])
        ->firstWhere('area_code', $profile['area']->code);
    expect($untypedEntry['percentile'])->toBeNull()
        ->and($untypedEntry['score'])->toBeNull()
        ->and($untypedEntry['raw_score'])->toBe('4.000000')
        ->and($untypedEntry['scale'])->toBeNull()
        ->and($untypedEntry['percentile_scale'])->toBeNull()
        ->and($untypedEntry['source'])->toBe('manual')
        ->and($untypedEntry['available'])->toBeFalse();
});

test('advisory activities do not enter the aptitude-plus-GWA model digest', function (): void {
    [$attempt, , $counselor] = buildMessagingReadyAttempt($this, 'supp-score-subject-update@example.test');
    (new AdmissionBenchmarkSeeder)->run();
    $profile = createSupplementalScoreProfile($attempt, $counselor);
    createSupplementalBoundSitting($attempt, $counselor, $profile['version'], $profile['metric'], ['percentile_rank' => '84']);
    Permission::findOrCreate('guidance.consultation.decide');
    $counselor->givePermissionTo('guidance.consultation.decide');

    $scoreProjection = app(ConsultationScoreProjection::class);
    $initialInput = AdmissionInputPayload::fromAttempt(
        $attempt,
        ConsultationScoreProjection::admissionInputSeries($scoreProjection->forAttempt($attempt)),
    );
    $responses = [];
    foreach ([['hardware_tech'], ['software_algorithms']] as $index => $activities) {
        $responses[] = actingAsAdmissionSupportStaff($this, $counselor)
            ->post(route('consultation.evidence.store', ['attempt' => $attempt->id]), supplementalEvidenceRequest($attempt, [
                'vocational_activities' => $activities,
                'activities_reviewed' => true,
                'idempotency_key' => 'supp-score-advisory-'.$index,
            ]))
            ->assertRedirect();
    }

    $snapshots = AdmissionSupportSnapshot::query()->where('admission_attempt_id', $attempt->id)->orderBy('version')->get();
    $currentInput = AdmissionInputPayload::fromAttempt(
        $attempt->fresh(),
        ConsultationScoreProjection::admissionInputSeries($scoreProjection->forAttempt($attempt->fresh())),
    );

    expect($snapshots)->toHaveCount(2)
        ->and($initialInput->aptitudePercentiles)->toBe($currentInput->aptitudePercentiles)
        ->and($initialInput->digest())->toBe($currentInput->digest())
        ->and($snapshots[0]->input_digest)->toBe($snapshots[1]->input_digest)
        ->and($snapshots[0]->payload['applicant_evidence'])->not->toHaveKey('subject_grades')
        ->and($snapshots[0]->payload['applicant_evidence'])->not->toHaveKey('strand');
});
test('a recorded snapshot is stale on a new open sitting and workspace reads do not rewrite it', function (): void {
    $this->withoutVite();
    config(['inertia.ssr.enabled' => false]);
    [$attempt, , $counselor] = buildMessagingReadyAttempt($this, 'supp-open-retake@example.test');
    (new AdmissionBenchmarkSeeder)->run();
    $profile = createSupplementalScoreProfile($attempt, $counselor);
    $current = createSupplementalBoundSitting($attempt, $counselor, $profile['version'], $profile['metric']);
    $snapshot = createRecordedSupplementalSnapshot($attempt, $counselor);
    Permission::findOrCreate('guidance.consultation.decide');
    $counselor->givePermissionTo('guidance.consultation.decide');

    createSupplementalBoundSitting(
        $attempt,
        $counselor,
        $profile['version'],
        $profile['metric'],
        [],
        false,
        false,
    );
    $snapshotCount = AdmissionSupportSnapshot::query()->where('admission_attempt_id', $attempt->id)->count();
    $attemptPayload = $attempt->fresh()->payload;
    $recordedPayload = $snapshot->fresh()->payload;

    actingAsAdmissionSupportStaff($this, $counselor)
        ->get(route('admission-support.show', $attempt))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('support.models.0.source_kind', 'recorded')
            ->where('support.models.0.current_applicable', false)
            ->where('support.models.0.status', 'result_not_ready')
            ->where('support.models.0.evaluated', false)
            ->where('support.models.0.recommendations', [])
            ->where('support.models.0.recorded_result.payload.applicant_radar.realistic', 0.8));

    expect(AdmissionSupportSnapshot::query()->where('admission_attempt_id', $attempt->id)->count())->toBe($snapshotCount)
        ->and($snapshot->fresh()->payload)->toBe($recordedPayload)
        ->and($attempt->fresh()->payload)->toBe($attemptPayload)
        ->and($current['result_set'])->not->toBeNull();
});

test('a new finalized sitting with identical values invalidates a snapshot by assessment identity', function (): void {
    [$attempt, , $counselor] = buildMessagingReadyAttempt($this, 'supp-identical-retake@example.test');
    (new AdmissionBenchmarkSeeder)->run();
    $profile = createSupplementalScoreProfile($attempt, $counselor);
    $current = createSupplementalBoundSitting($attempt, $counselor, $profile['version'], $profile['metric']);
    $snapshot = createRecordedSupplementalSnapshot($attempt, $counselor);
    $previousInputDigest = $snapshot->input_digest;
    $previousContext = $snapshot->payload['assessment_context'];
    $newSitting = createSupplementalBoundSitting($attempt, $counselor, $profile['version'], $profile['metric']);
    $replacementAdministration = $newSitting['administration'];
    $replacementResultSet = $newSitting['result_set'];
    $projection = app(ConsultationScoreProjection::class)->forAttempt($attempt);
    $currentInput = AdmissionInputPayload::fromAttempt(
        $attempt,
        ConsultationScoreProjection::admissionInputSeries($projection),
    );

    $result = app(ConsultationAdmissionSupportQuery::class)->for($attempt);
    $model = $result['models'][0];

    expect($replacementResultSet->id)->not->toBe($current['result_set']->id)
        ->and($projection['assessment_context']['administration_id'])->toBe($replacementAdministration->id)
        ->and($projection['assessment_context']['result_set_id'])->toBe($replacementResultSet->id)
        ->and($currentInput->digest())->toBe($previousInputDigest)
        ->and($model['source_kind'])->toBe('recorded')
        ->and($model['status'])->toBe('unavailable')
        ->and($model['evaluated'])->toBeFalse()
        ->and($model['recommendations'])->toBe([])
        ->and($snapshot->fresh()->payload['assessment_context'])->toBe($previousContext);
});

test('changed input invalidates a snapshot while same sitting and input preserve its pinned benchmark result', function (): void {
    Http::preventStrayRequests();
    [$attempt, , $counselor] = buildMessagingReadyAttempt($this, 'supp-input-benchmark@example.test');
    (new AdmissionBenchmarkSeeder)->run();
    $profile = createSupplementalScoreProfile($attempt, $counselor);
    createSupplementalBoundSitting($attempt, $counselor, $profile['version'], $profile['metric']);
    $snapshot = createRecordedSupplementalSnapshot($attempt, $counselor);
    $originalPayload = $snapshot->payload;
    $snapshotCount = AdmissionSupportSnapshot::query()->where('admission_attempt_id', $attempt->id)->count();
    publishSyntheticCompleteBenchmarkSuccessor(
        $counselor,
        AcademicCycle::query()->findOrFail($attempt->academic_cycle_id),
        true,
        'supplemental-recorded-benchmark-successor',
    );
    $pinned = app(ConsultationAdmissionSupportQuery::class)->for($attempt)['models'][0];
    expect($pinned['evaluated'])->toBeTrue()
        ->and($pinned['source_kind'])->toBe('recorded')
        ->and($pinned['current_applicable'])->toBeTrue()
        ->and($pinned['recommendations'][0]['quota']['configured'])->toBeTrue()
        ->and($pinned['recorded_result']['assessment_context'])->toBe($originalPayload['assessment_context'])
        ->and($pinned['recorded_result']['inference_backend'])->toBe($originalPayload['inference_backend'])
        ->and($pinned['recorded_result']['engine_version'])->toBe('9.1.0')
        ->and($pinned['recorded_result']['benchmark_version'])->toBe('recorded-benchmark-version')
        ->and($pinned['recorded_result']['evaluated_at'])->toBe($originalPayload['evaluated_at'])
        ->and($pinned['benchmark_context_changed'])->toBeTrue()
        ->and($pinned['recorded_result']['payload'])->toBe($originalPayload)
        ->and($pinned['benchmark_version'])->toBe('recorded-benchmark-version')
        ->and($snapshot->fresh()->payload)->toBe($originalPayload)
        ->and(AdmissionSupportSnapshot::query()->where('admission_attempt_id', $attempt->id)->count())->toBe($snapshotCount);
    Http::assertNothingSent();

    $attempt->forceFill(['gwa' => 87.5])->save();
    $stale = app(ConsultationAdmissionSupportQuery::class)->for($attempt)['models'][0];
    expect($stale['status'])->toBe('unavailable')
        ->and($stale['evaluated'])->toBeFalse()
        ->and($stale['recommendations'])->toBe([])
        ->and($stale['recorded_result']['payload'])->toBe($originalPayload)
        ->and($snapshot->fresh()->payload)->toBe($originalPayload);

    foreach ([87.6, 87.7, 87.8] as $changedGwa) {
        $ineligibleSnapshot = createRecordedSupplementalSnapshot($attempt->fresh(), $counselor);
        $ineligiblePayload = $ineligibleSnapshot->payload;
        $ineligibleSnapshotCount = AdmissionSupportSnapshot::query()
            ->where('admission_attempt_id', $attempt->id)
            ->count();
        $attempt->forceFill(['gwa' => $changedGwa])->save();
        $ineligible = app(ConsultationAdmissionSupportQuery::class)->for($attempt->fresh())['models'][0];

        expect($ineligible['status'])->toBe('unavailable')
            ->and($ineligible['current_applicable'])->toBeFalse()
            ->and($ineligible['evaluated'])->toBeFalse()
            ->and($ineligible['recommendations'])->toBe([])
            ->and($ineligible['recorded_result']['payload'])->toBe($ineligiblePayload)
            ->and($ineligibleSnapshot->fresh()->payload)->toBe($ineligiblePayload)
            ->and(AdmissionSupportSnapshot::query()->where('admission_attempt_id', $attempt->id)->count())->toBe($ineligibleSnapshotCount);
    }
    Http::assertNothingSent();
});

test('legacy snapshot without assessment context and non-neural snapshot are never current advice', function (): void {
    [$attempt, , $counselor] = buildMessagingReadyAttempt($this, 'supp-snapshot-provenance@example.test');
    (new AdmissionBenchmarkSeeder)->run();
    $profile = createSupplementalScoreProfile($attempt, $counselor);
    createSupplementalBoundSitting($attempt, $counselor, $profile['version'], $profile['metric']);
    $snapshot = createRecordedSupplementalSnapshot($attempt, $counselor);

    $legacyPayload = $snapshot->payload;
    unset($legacyPayload['assessment_context']);
    $snapshot->forceFill(['payload' => $legacyPayload])->save();
    $legacy = app(ConsultationAdmissionSupportQuery::class)->for($attempt)['models'][0];
    expect($legacy['status'])->toBe('unavailable')
        ->and($legacy['evaluated'])->toBeFalse()
        ->and($legacy['recommendations'])->toBe([])
        ->and($legacy['recorded_result']['assessment_context'])->toBeNull();

    $snapshot->forceFill(['payload' => [
        ...$legacyPayload,
        'assessment_context' => app(ConsultationScoreProjection::class)->forAttempt($attempt)['assessment_context'],
        'inference_backend' => 'heuristic',
    ]])->save();
    $nonNeural = app(ConsultationAdmissionSupportQuery::class)->for($attempt)['models'][0];
    expect($nonNeural['status'])->toBe('unavailable')
        ->and($nonNeural['evaluated'])->toBeFalse()
        ->and($nonNeural['recommendations'])->toBe([]);
});
test('assessment context leaves unavailable legacy identities null instead of inventing identifiers', function (): void {
    [$attempt] = buildMessagingReadyAttempt($this, 'supp-no-assessment-context@example.test');
    ExamAssignment::query()
        ->active()
        ->where('admission_attempt_id', $attempt->id)
        ->update(['status' => ExamAssignment::STATUS_REMOVED]);

    expect(app(ConsultationScoreProjection::class)->forAttempt($attempt)['assessment_context'])->toBe([
        'version' => 1,
        'assignment_id' => null,
        'exam_session_id' => null,
        'grading_session_id' => null,
        'administration_id' => null,
        'result_set_id' => null,
    ]);
});
test('assessment context key order does not stale an otherwise current recorded result', function (): void {
    [$attempt, , $counselor] = buildMessagingReadyAttempt($this, 'supp-context-key-order@example.test');
    (new AdmissionBenchmarkSeeder)->run();
    $profile = createSupplementalScoreProfile($attempt, $counselor);
    createSupplementalBoundSitting($attempt, $counselor, $profile['version'], $profile['metric']);
    $snapshot = createRecordedSupplementalSnapshot($attempt, $counselor);
    $payload = $snapshot->payload;
    $payload['assessment_context'] = array_reverse($payload['assessment_context'], true);
    $snapshot->forceFill(['payload' => $payload])->save();

    $model = app(ConsultationAdmissionSupportQuery::class)->for($attempt)['models'][0];

    expect($model['current_applicable'])->toBeTrue()
        ->and($model['evaluated'])->toBeTrue()
        ->and($snapshot->fresh()->payload['assessment_context'])->toBe($payload['assessment_context']);
});

test('recorded results remain history only when their frozen benchmark provenance is unavailable or mismatched', function (): void {
    [$attempt, , $counselor] = buildMessagingReadyAttempt($this, 'supp-frozen-provenance@example.test');
    (new AdmissionBenchmarkSeeder)->run();
    $profile = createSupplementalScoreProfile($attempt, $counselor);
    createSupplementalBoundSitting($attempt, $counselor, $profile['version'], $profile['metric']);
    $snapshot = createRecordedSupplementalSnapshot($attempt, $counselor);
    $originalPayload = $snapshot->payload;

    Http::preventStrayRequests();
    $provenanceMutations = [
        'absent provenance' => static function (array $payload): array {
            unset($payload['modality_coverage']['inference_provenance']);

            return $payload;
        },
        'wrong package' => static function (array $payload): array {
            $payload['modality_coverage']['inference_provenance']['package'] = 'other';

            return $payload;
        },
        'wrong checkpoint identity' => static function (array $payload): array {
            $payload['modality_coverage']['inference_provenance']['checkpoint_identity'] = 'other.ckpt';

            return $payload;
        },
        'wrong checkpoint digest' => static function (array $payload): array {
            $payload['modality_coverage']['inference_provenance']['checkpoint_sha256'] = str_repeat('0', 64);

            return $payload;
        },
        'wrong package version' => static function (array $payload): array {
            $payload['modality_coverage']['inference_provenance']['package_version'] = '9.1.1';

            return $payload;
        },
        'wrong payload engine version' => static function (array $payload): array {
            $payload['engine_version'] = '9.1.1';

            return $payload;
        },
        'missing fallback declaration' => static function (array $payload): array {
            unset($payload['fallback_reason']);

            return $payload;
        },
    ];
    foreach ($provenanceMutations as $mutate) {
        $payload = $mutate($originalPayload);
        $snapshot->forceFill(['payload' => $payload])->save();

        $model = app(ConsultationAdmissionSupportQuery::class)->for($attempt)['models'][0];
        expect($model['current_applicable'])->toBeFalse()
            ->and($model['evaluated'])->toBeFalse()
            ->and($model['recommendations'])->toBe([])
            ->and($snapshot->fresh()->payload)->toBe($payload);
    }

    config(['admission_engine.tabfm.package_version' => '9.1.1']);
    $wrongPinnedPayload = $originalPayload;
    $wrongPinnedPayload['engine_version'] = '9.1.1';
    $wrongPinnedPayload['modality_coverage']['inference_provenance']['package_version'] = '9.1.1';
    $snapshot->forceFill(['payload' => $wrongPinnedPayload, 'engine_version' => '9.1.1'])->save();
    $wrongConfiguredPackage = app(ConsultationAdmissionSupportQuery::class)->for($attempt)['models'][0];
    expect($wrongConfiguredPackage['current_applicable'])->toBeFalse()
        ->and($wrongConfiguredPackage['evaluated'])->toBeFalse()
        ->and($wrongConfiguredPackage['recommendations'])->toBe([])
        ->and($snapshot->fresh()->payload)->toBe($wrongPinnedPayload);
    config(['admission_engine.tabfm.package_version' => '9.1.0']);
    $snapshot->forceFill(['payload' => $originalPayload, 'engine_version' => '9.1.0'])->save();
    $snapshot->forceFill(['payload' => $originalPayload, 'engine_version' => '9.1.1'])->save();
    $rowVersionMismatch = app(ConsultationAdmissionSupportQuery::class)->for($attempt)['models'][0];
    expect($rowVersionMismatch['current_applicable'])->toBeFalse()
        ->and($rowVersionMismatch['evaluated'])->toBeFalse()
        ->and($rowVersionMismatch['recommendations'])->toBe([])
        ->and($snapshot->fresh()->payload)->toBe($originalPayload);
    Http::assertNothingSent();

    $snapshot->forceFill(['payload' => $originalPayload, 'engine_version' => '9.1.0'])->save();

    foreach ([
        ['institution_id' => (int) $attempt->institution_id + 1],
        ['schema_digest' => str_repeat('0', 64)],
    ] as $provenanceChange) {
        $payload = $originalPayload;
        $payload['evaluation_context'] = [...$payload['evaluation_context'], ...$provenanceChange];
        $snapshot->forceFill(['payload' => $payload])->save();

        $model = app(ConsultationAdmissionSupportQuery::class)->for($attempt)['models'][0];
        expect($model['current_applicable'])->toBeFalse()
            ->and($model['evaluated'])->toBeFalse()
            ->and($model['recommendations'])->toBe([])
            ->and($snapshot->fresh()->payload)->toBe($payload);
    }

    $activation = AdmissionBenchmarkActivation::query()->findOrFail($snapshot->benchmark_activation_id);
    $snapshot->forceFill(['payload' => $originalPayload])->save();
    $activation->delete();
    $model = app(ConsultationAdmissionSupportQuery::class)->for($attempt)['models'][0];

    expect($model['current_applicable'])->toBeFalse()
        ->and($model['evaluated'])->toBeFalse()
        ->and($model['recommendations'])->toBe([])
        ->and($snapshot->fresh()->payload)->toBe($originalPayload)
        ->and($snapshot->fresh()->benchmark_activation_id)->toBeNull();
});

test('new advisory evidence keeps legacy subject values visible without admitting them to the model', function (): void {
    [$attempt, , $guidance] = buildCanonicalAdmissionSupportAttempt($this, 'legacy-subject-display@example.test');
    (new AdmissionBenchmarkSeeder)->run();
    config(['admission_engine.tabfm.driver' => 'disabled']);
    Http::preventStrayRequests();
    $payload = $attempt->payload ?? [];
    $payload['supplemental_evidence'] = ['evidence_format_version' => 1, 'math' => 0, 'sci' => 81, 'eng' => 82, 'evidence_source' => 'physical_form_138'];
    $attempt->forceFill(['payload' => $payload])->save();
    $request = supplementalEvidenceRequest($attempt, ['evidence_source' => 'examinee_statement', 'vocational_activities' => ['hardware_tech'], 'activities_reviewed' => true]);
    app(RecordSupplementalEvidence::class)->handle($attempt, $guidance, $request, 'legacy-display-advisory');
    $evidence = app(ConsultationAdmissionSupportQuery::class)->for($attempt->fresh())['models'][0]['applicant_evidence'];
    expect($evidence['subject_grades'])->toBe(['shs_math' => 0, 'shs_sci' => 81, 'shs_eng' => 82])
        ->and($attempt->fresh()->payload['supplemental_evidence']['math'])->toBe(0)
        ->and($attempt->fresh()->payload['supplemental_evidence']['evidence_format_version'])->toBe(2);
    Http::assertNothingSent();
});
