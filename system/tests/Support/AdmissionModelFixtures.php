<?php

use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\Assessment\Actions\ApproveTestInstrumentVersion;
use App\Assessment\Support\TestInstrumentDraftWriter;
use App\Assessment\Support\TestInstrumentProfileValidator;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentResultSet;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

require_once __DIR__.'/CaseMessagingHelpers.php';
require_once __DIR__.'/TestKitHelpers.php';

/** @return array{assignment: ExamAssignment, administration: AssessmentAdministration, result_set: AssessmentResultSet} */
function createTabFmGovernanceTypedPercentileSitting(AdmissionAttempt $attempt, User $actor): array
{
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, 'web');
    if (! $actor->can(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)) {
        $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
    }
    $areas = AptitudeArea::query()->where('is_active', true)
        ->whereIn('code', BenchmarkInputSchema::canonicalAptitudeCodes())->orderBy('id')->get();
    $inputContract = [
        'allowed_sources' => ['import', 'manual'],
        'required_resources' => [],
        'source_requirements' => [
            'import' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
            'manual' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
        ],
    ];
    $subtestsPayload = [];
    $metricsPayload = [];
    foreach ($areas->values() as $idx => $area) {
        $subCode = 'SUB_'.$area->code;
        $subtestsPayload[] = [
            'code' => $subCode, 'name' => 'Subtest '.$area->name, 'display_order' => $idx + 1,
            'response_mode' => 'keyed_items', 'item_order_policy' => 'fixed',
            'items' => [
                ['item_code' => $subCode.'_1', 'local_number' => '1', 'display_order' => 1, 'is_scored' => true, 'option_tokens' => ['A', 'B']],
            ],
        ];
        $metricsPayload[] = [
            'code' => 'SYNTHETIC_'.$area->code,
            'name' => 'Synthetic '.$area->name.' percentile',
            'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
            'subtest_code' => $subCode,
            'unit' => 'count', 'min_raw_value' => null, 'max_raw_value' => null,
            'precision_scale' => 0, 'is_required' => true,
            'required_output_scales' => ['percentile_rank'],
            'procedure_reference' => null,
            'construct_aptitude_area_id' => (int) $area->id,
            'input_codes' => [],
            'input_contract_json' => $inputContract,
        ];
    }
    $payload = [
        'profile' => [
            'code' => 'TABFM-PREVIEW-'.$attempt->id,
            'display_name' => 'Synthetic finalized percentile preview',
            'publisher_name' => 'Synthetic software fixture',
            'form_code' => 'FORM-TABFM',
            'edition' => 'Synthetic test fixture',
            'language' => 'Test-only',
            'source_reference' => 'test-fixture://tabfm-preview-source',
            'permitted_use_reference' => 'test-fixture://tabfm-preview-permission',
            'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER,
            'administration_procedure_reference' => 'Synthetic finalized percentile preview fixture procedure.',
        ],
        'subtests' => $subtestsPayload,
        'metrics' => $metricsPayload,
    ];
    $instrument = new TestInstrument(['institution_id' => $attempt->institution_id, 'code' => 'TABFM-PREVIEW-'.$attempt->id]);
    $instrument->forceFill(['is_synthetic' => true])->save();
    $draft = new TestInstrumentVersion([
        'test_instrument_id' => $instrument->id, 'version_number' => 1,
        'status' => TestInstrumentVersion::STATUS_DRAFT, 'lock_version' => 1, 'created_by_user_id' => $actor->id,
    ]);
    app(TestInstrumentDraftWriter::class)->save(
        $draft,
        app(TestInstrumentProfileValidator::class)->normalize($payload),
    );
    $version = app(ApproveTestInstrumentVersion::class)->handle(
        $actor, (int) $instrument->id, (int) $draft->id, (int) $draft->lock_version,
        (string) Str::uuid(), sourceReviewAttestation: true, isDemo: true,
    );
    $kitVersion = testKitSetupOnlyFixture($actor, $version)['version'];
    $kitVersion->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'activated_at' => now(),
        'activated_by_user_id' => $actor->id,
        'revision' => (int) $kitVersion->revision + 1,
    ])->save();

    $metrics = $version->metrics()->orderBy('code')->get();
    $requiredMetrics = $metrics->map(static fn (TestInstrumentMetric $m): array => [
        'code' => $m->code,
        'name' => $m->name,
        'rule_kind' => $m->rule_kind,
        'unit' => $m->unit,
        'min_raw_value' => $m->min_raw_value === null ? null : sprintf('%.6f', (float) $m->min_raw_value),
        'max_raw_value' => $m->max_raw_value === null ? null : sprintf('%.6f', (float) $m->max_raw_value),
        'precision_scale' => (int) $m->precision_scale,
        'required_output_scales' => $m->required_output_scales,
        'input_contract_json' => $m->input_contract_json,
    ])->values()->all();

    $assignment = ExamAssignment::query()->active()->where('admission_attempt_id', $attempt->id)->with('examSession')->firstOrFail();
    $session = $assignment->examSession;
    $session->forceFill([
        'test_instrument_version_id' => $version->id,
        'test_kit_version_id' => $kitVersion->id,
    ])->save();
    $grading = GradingSession::query()->where('exam_session_id', $session->id)->firstOrFail();
    $latest = AssessmentAdministration::query()->where('exam_assignment_id', $assignment->id)->orderByDesc('binding_revision')->first();
    if ($latest !== null && $latest->superseded_at === null) {
        $latest->forceFill(['superseded_at' => now()])->save();
    }
    $administration = AssessmentAdministration::query()->create([
        'exam_assignment_id' => $assignment->id, 'admission_attempt_id' => $attempt->id,
        'binding_kind' => AssessmentAdministration::KIND_DIRECT_MANUAL,
        'binding_revision' => (int) ($latest?->binding_revision ?? 0) + 1,
        'test_instrument_version_id' => $version->id,
        'test_kit_version_id' => $kitVersion->id,
        'manifest_sha256' => $kitVersion->manifest_sha256,
        'procedure_snapshot_json' => [
            'content_sha256' => $version->content_sha256,
            'administration_procedure_reference' => $version->administration_procedure_reference,
            'material_requirement' => $version->material_requirement,
            'manifest_schema_version' => (int) $kitVersion->manifest_schema_version,
            'kit_manifest_sha256' => (string) $kitVersion->manifest_sha256,
        ],
        'required_metric_set' => $requiredMetrics,
        'binding_state' => AssessmentAdministration::STATE_AWAITING_NORM_CONVERSION,
        'bound_at' => now()->subMinutes(10),
    ]);
    $typed = [];
    $raw = [];
    foreach ($metrics as $metric) {
        $typed[$metric->code] = ['percentile_rank' => '76'];
        $raw[$metric->code] = ['raw_value' => '4.000000', 'source' => 'synthetic-finalized-fixture', 'status' => 'valid'];
    }
    $result = AssessmentResultSet::query()->create([
        'administration_id' => $administration->id, 'grading_session_id' => $grading->id,
        'exam_session_id' => $session->id, 'raw_revision_vector_json' => $raw,
        'conversion_revision_vector_json' => ['status' => 'valid', 'typed_outputs' => $typed, ...$typed],
        'status' => AssessmentResultSet::STATUS_FINALIZED,
        'finalized_by_user_id' => $actor->id, 'finalized_at' => now(),
    ]);

    return ['assignment' => $assignment, 'administration' => $administration, 'result_set' => $result];
}

/** @return array{AdmissionAttempt, User, User} */
function buildCanonicalAdmissionSupportAttempt(TestCase $test, string $email): array
{
    [$attempt, $applicant, $guidance] = buildMessagingReadyAttempt($test, $email);
    createTabFmGovernanceTypedPercentileSitting($attempt, $guidance);

    return [$attempt, $applicant, $guidance];
}
