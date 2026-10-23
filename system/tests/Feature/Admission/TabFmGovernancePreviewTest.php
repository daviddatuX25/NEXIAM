<?php

use App\AdmissionEngine\DTOs\AdmissionInputPayload;
use App\AdmissionEngine\ProgramBenchmarkPreviewQuery;
use App\AdmissionEngine\Providers\TabFmPairwiseProvider;
use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\Consultation\Support\ConsultationScoreProjection;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkDraft;
use App\Models\Admission\AdmissionBenchmarkRevision;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Admission\AdmissionSupportSnapshot;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use Database\Seeders\AdmissionBenchmarkSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/../../Support/AdmissionModelFixtures.php';

beforeEach(function (): void {
    config([
        'admission_engine.tabfm.institutional_use_approved' => true,
        'admission_engine.tabfm.hosted_use_approved' => true,
        'admission_engine.tabfm.package_version' => '9.1.0',
        'admission_engine.tabfm.checkpoint_id' => 'Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt',
        'admission_engine.tabfm.checkpoint_sha256' => '1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88',
    ]);
    seedE2Foundation();
});

function tabFmPreviewInput(AcademicCycle $cycle, Institution $institution): AdmissionInputPayload
{
    return new AdmissionInputPayload(
        attemptId: 0,
        institutionId: (int) $institution->id,
        academicCycleId: (int) $cycle->id,
        gwa: 91.5,
        aptitudePercentiles: ['LOGIC' => 82.0, 'PROFILE_QUANT' => 86.0, 'PROFILE_VERBAL' => 80.0, 'SPATIAL' => 80.0, 'MEMORY' => 80.0, 'LANGUAGE' => 80.0],
    );
}

function tabFmDraftContent(AdmissionBenchmarkSet $set, Program $program): array
{
    $content = ['tiers' => []];
    foreach ($set->exemplars()->where('program_id', $program->id)->with('values.feature')->get() as $exemplar) {
        $values = [];
        foreach ($exemplar->values as $value) {
            $feature = $value->feature;
            $values[$feature->feature_key] = $value->is_missing
                ? null
                : ($feature->logical_type === 'categorical' ? $value->categorical_value : (float) $value->numeric_value);
        }
        foreach ($set->features->where('is_required', true) as $required) {
            if (($values[$required->feature_key] ?? null) === null) {
                $values[$required->feature_key] = $required->logical_type === 'categorical' ? 'STEM' : 70.0;
            }
        }
        ksort($values);
        $content['tiers'][(string) $exemplar->tier_level] = ['values' => $values];
    }

    return $content;
}

function createTabFmDraft(User $actor, AdmissionBenchmarkSet $set, Program $program, AcademicCycle $cycle): AdmissionBenchmarkRevision
{
    $draft = AdmissionBenchmarkDraft::query()->create([
        'institution_id' => $program->institution_id,
        'program_id' => $program->id,
        'academic_cycle_id' => $cycle->id,
        'base_benchmark_set_id' => $set->id,
        'feature_schema_digest' => $set->computedSchemaDigest(),
        'feature_schema_json' => BenchmarkInputSchema::schemaForSet($set),
        'current_version' => 1,
    ]);

    return AdmissionBenchmarkRevision::query()->create([
        'draft_id' => $draft->id,
        'version' => 1,
        'actor_id' => $actor->id,
        'content' => tabFmDraftContent($set, $program),
        'reason' => 'Synthetic preview fixture',
        'command_receipt' => 'preview-test-'.$draft->id,
        'base_benchmark_set_id' => $set->id,
        'feature_schema_digest' => $set->computedSchemaDigest(),
        'feature_schema_json' => BenchmarkInputSchema::schemaForSet($set),
    ]);

}

/** Synthetic HTTP response for consumer-contract coverage, not real-model evidence. */
function tabFmGovernanceNeuralSuccessFixture(array $request, ?callable $scoreForProgram = null): array
{
    $programs = collect($request['context_exemplars'])
        ->unique('program_code')
        ->sortBy('program_code')
        ->values();
    $featureDefinitions = $request['feature_definitions'];
    $applicant = $request['query_applicant'];
    $featureCoverage = [];
    $missingFeatures = [];
    $missingOptionalFeatures = [];
    foreach ($featureDefinitions as $definition) {
        $featureKey = $definition['feature_key'];
        $available = array_key_exists($featureKey, $applicant) && $applicant[$featureKey] !== null;
        $required = (bool) $definition['is_required'];
        $featureCoverage[$featureKey] = ['available' => $available, 'required' => $required];
        if (! $available) {
            $missingFeatures[] = $featureKey;
            if (! $required) {
                $missingOptionalFeatures[] = $featureKey;
            }
        }
    }
    $recommendations = [];
    foreach ($programs as $program) {
        $score = $scoreForProgram === null ? 91 : $scoreForProgram($program, $request);
        $recommendations[] = [
            'rank' => count($recommendations) + 1,
            'program_code' => $program['program_code'],
            'program_name' => $program['program_name'],
            'compatibility_index' => $score,
            'band' => $score >= 80 ? 'high_match' : ($score >= 68 ? 'moderate_match' : 'below_threshold'),
            'drivers' => ['Synthetic neural success fixture'],
        ];
    }
    $packageVersion = '9.1.0';

    return [
        'status' => 'evaluated',
        'engine_version' => $packageVersion,
        'inference_backend' => 'tabpfn_pytorch',
        'fallback_reason' => null,
        'provenance' => [
            'package' => 'tabpfn',
            'package_version' => $packageVersion,
            'checkpoint_identity' => 'Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt',
            'checkpoint_sha256' => '1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88',
        ],
        'execution_time_ms' => 1.0,
        'modality_coverage' => [
            'available_features' => count($featureDefinitions) - count($missingFeatures),
            'total_features' => count($featureDefinitions),
            'missing_optional_features' => $missingOptionalFeatures,
            'missing_features' => $missingFeatures,
            'feature_coverage' => $featureCoverage,
        ],
        'feature_provenance' => array_map(static fn (array $definition): array => array_diff_key($definition, ['scale' => true]), $featureDefinitions),
        'recommendations' => $recommendations,
    ];
}

test('authorized preview evaluates active and saved draft through the same fake-backed provider without state writes', function (): void {
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $program = Program::query()->where('institution_id', $institution->id)->where('code', 'BSIT')->firstOrFail();
    (new AdmissionBenchmarkSeeder)->run();
    $set = AdmissionBenchmarkSet::query()->where('institution_id', $institution->id)->firstOrFail();
    $revision = createTabFmDraft($actor, $set, $program, $cycle);
    $input = tabFmPreviewInput($cycle, $institution);
    $activationId = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)?->id;
    $activationCount = AdmissionBenchmarkActivation::query()->count();
    $revisionCount = AdmissionBenchmarkRevision::query()->count();
    $setCount = AdmissionBenchmarkSet::query()->count();

    $allProgramCodes = $set->exemplars()
        ->with('program')
        ->get()
        ->pluck('program.code')
        ->unique()
        ->sort()
        ->values()
        ->all();
    $submittedProgramCodes = [];
    config(['admission_engine.tabfm.driver' => 'http', 'admission_engine.tabfm.endpoint' => 'http://tabfm-inference:8101']);
    Http::preventStrayRequests();
    Http::fake([
        'http://tabfm-inference:8101/inference/tabpfn' => function (Request $request) use (&$submittedProgramCodes) {
            $payload = $request->data();
            $submittedProgramCodes[] = collect($payload['context_exemplars'])
                ->pluck('program_code')
                ->unique()
                ->sort()
                ->values()
                ->all();

            return Http::response(tabFmGovernanceNeuralSuccessFixture($payload), 200);
        },
    ]);

    $query = app(ProgramBenchmarkPreviewQuery::class);
    $active = $query->handle($actor, $program, $cycle, $input, 'active', requestId: 'active-preview-request');
    $draft = $query->handle($actor, $program, $cycle, $input, 'draft', $revision->id, 'draft-preview-request');
    $providerResult = app(TabFmPairwiseProvider::class)->evaluate($input);

    expect($active->status)->toBe('evaluated')
        ->and($active->engineVersion)->toBe('9.1.0')
        ->and($active->recommendations[0]->programCode)->toBe('BSIT')
        ->and($active->inferenceBackend)->toBe('tabpfn_pytorch')
        ->and($active->evaluationContext?->source)->toBe('active')
        ->and($active->evaluationContext?->benchmarkActivationId)->toBe($activationId)
        ->and($active->evaluationContext?->inputDigest)->toBe($input->digest())
        ->and($active->evaluationContext?->benchmarkSetId)->toBe($set->id)
        ->and($active->evaluationContext?->contentDigest)->toBe($set->content_sha256)
        ->and($active->evaluationContext?->schemaDigest)->toBe($set->schema_sha256)
        ->and($active->evaluationContext?->requestId)->toBe('active-preview-request')
        ->and($draft->status)->toBe('evaluated')
        ->and($draft->engineVersion)->toBe('9.1.0')
        ->and($draft->inferenceBackend)->toBe('tabpfn_pytorch')
        ->and($draft->recommendations[0]->programCode)->toBe('BSIT')
        ->and($draft->recommendations[0]->compatibilityIndex)->toBe($active->recommendations[0]->compatibilityIndex)
        ->and($draft->evaluationContext?->source)->toBe('draft')
        ->and($draft->evaluationContext?->benchmarkActivationId)->toBeNull()
        ->and($draft->evaluationContext?->benchmarkRevisionId)->toBe($revision->id)
        ->and($draft->evaluationContext?->benchmarkDraftId)->toBe($revision->draft_id)
        ->and($draft->evaluationContext?->contentDigest)->toBe(hash('sha256', json_encode($revision->content, JSON_THROW_ON_ERROR)))
        ->and($draft->evaluationContext?->requestId)->toBe('draft-preview-request')
        ->and($submittedProgramCodes)->toBe([[$program->code], [$program->code], $allProgramCodes])
        ->and(collect($providerResult->recommendations)->pluck('programCode')->all())->toBe($allProgramCodes)
        ->and($providerResult->status)->toBe('evaluated')
        ->and($providerResult->engineVersion)->toBe('9.1.0')
        ->and($providerResult->inferenceBackend)->toBe('tabpfn_pytorch')
        ->and(AdmissionBenchmarkActivation::query()->count())->toBe($activationCount)
        ->and(AdmissionBenchmarkRevision::query()->count())->toBe($revisionCount)
        ->and(AdmissionBenchmarkSet::query()->count())->toBe($setCount);

    Http::assertSentCount(3);
    Http::assertSent(fn ($request): bool => $request->url() === 'http://tabfm-inference:8101/inference/tabpfn'
        && collect($request['context_exemplars'])->pluck('program_code')->unique()->all() === ['BSIT']);
});

test('unavailable neural HTTP inference fails closed without heuristic results', function (): void {
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $program = Program::query()->where('institution_id', $institution->id)->where('code', 'BSIT')->firstOrFail();
    (new AdmissionBenchmarkSeeder)->run();

    config(['admission_engine.tabfm.driver' => 'http', 'admission_engine.tabfm.endpoint' => 'http://tabfm-inference:8101']);
    Http::preventStrayRequests();
    Http::fake([
        'http://tabfm-inference:8101/inference/tabpfn' => Http::response([], 503),
    ]);

    $result = app(ProgramBenchmarkPreviewQuery::class)->handle(
        $actor,
        $program,
        $cycle,
        tabFmPreviewInput($cycle, $institution),
        'active',
        requestId: 'unavailable-neural-preview',
    );

    expect($result->status)->toBe('unavailable')
        ->and($result->inferenceBackend)->toBe('unavailable')
        ->and($result->recommendations)->toBe([])
        ->and($result->evaluationContext?->programId)->toBe((int) $program->id)
        ->and($result->evaluationContext?->requestId)->toBe('unavailable-neural-preview');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://tabfm-inference:8101/inference/tabpfn'
        && collect($request['context_exemplars'])->pluck('program_code')->unique()->values()->all() === [$program->code]);
});
test('subject-grade schemas are not canonical live preview schemas', function (): void {
    $schema = array_map(static fn (array $definition, int $position): array => [
        'feature_key' => $definition['feature_key'],
        'label' => $definition['label'],
        'logical_type' => $definition['logical_type'],
        'aptitude_area_id' => $definition['aptitude_area_id'],
        'is_required' => $definition['is_required'],
        'position' => $position + 1,
        'source_definition' => $definition['source_definition_json'],
    ], BenchmarkInputSchema::canonicalFeatureDefinitions(), array_keys(BenchmarkInputSchema::canonicalFeatureDefinitions()));
    $broadSchema = [...$schema, [
        'feature_key' => 'biology_grade',
        'label' => 'Biology',
        'logical_type' => 'numeric',
        'aptitude_area_id' => null,
        'is_required' => false,
        'position' => count($schema) + 1,
        'source_definition' => ['source_id' => 'old-biology', 'source_code' => 'BIOLOGY', 'source_version' => 'old'],
    ]];

    expect(BenchmarkInputSchema::isCanonicalFeatureSchema($schema))->toBeTrue()
        ->and(BenchmarkInputSchema::isCanonicalFeatureSchema($broadSchema))->toBeFalse()
        ->and(fn () => BenchmarkInputSchema::assertCanonicalFeatureSchema($broadSchema))
        ->toThrow(InvalidArgumentException::class, 'aptitude measures and GWA');
});

test('preview rejects bad scope, mismatched input, and stale draft schema before inference', function (): void {
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $program = Program::query()->where('institution_id', $institution->id)->where('code', 'BSIT')->firstOrFail();
    (new AdmissionBenchmarkSeeder)->run();
    $set = AdmissionBenchmarkSet::query()->where('institution_id', $institution->id)->firstOrFail();
    $revision = createTabFmDraft($actor, $set, $program, $cycle);
    AdmissionBenchmarkDraft::query()->whereKey($revision->draft_id)->update(['feature_schema_digest' => str_repeat('0', 64)]);
    Http::preventStrayRequests();

    expect(fn () => app(ProgramBenchmarkPreviewQuery::class)->handle(
        User::factory()->create(), $program, $cycle, tabFmPreviewInput($cycle, $institution), 'draft', $revision->id,
    ))->toThrow(AuthorizationException::class);

    $wrongInput = tabFmPreviewInput($cycle, $institution);
    $wrongInput = new AdmissionInputPayload(
        attemptId: $wrongInput->attemptId,
        institutionId: $wrongInput->institutionId,
        academicCycleId: $wrongInput->academicCycleId + 1,
        gwa: $wrongInput->gwa,
        aptitudePercentiles: $wrongInput->aptitudePercentiles,
    );
    expect(fn () => app(ProgramBenchmarkPreviewQuery::class)->handle($actor, $program, $cycle, $wrongInput, 'draft', $revision->id))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(ProgramBenchmarkPreviewQuery::class)->handle(
        $actor, $program, $cycle, tabFmPreviewInput($cycle, $institution), 'draft', $revision->id,
    ))->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

test('preview authorization and input binding fail before querying benchmark exemplar data', function (): void {
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $program = Program::query()->where('institution_id', $institution->id)->where('code', 'BSIT')->firstOrFail();
    $benchmarkQueries = [];
    DB::listen(function ($query) use (&$benchmarkQueries): void {
        if (preg_match('/admission_benchmark_(sets|features|exemplars|exemplar_values|drafts|revisions)/', $query->sql) === 1) {
            $benchmarkQueries[] = $query->sql;
        }
    });

    $unprivileged = User::factory()->create();
    expect(fn () => app(ProgramBenchmarkPreviewQuery::class)->handle(
        $unprivileged, $program, $cycle, tabFmPreviewInput($cycle, $institution), 'active',
    ))->toThrow(AuthorizationException::class);
    $wrongInput = tabFmPreviewInput($cycle, $institution);
    $wrongInput = new AdmissionInputPayload(
        attemptId: $wrongInput->attemptId,
        institutionId: $wrongInput->institutionId + 1,
        academicCycleId: $wrongInput->academicCycleId,
        aptitudePercentiles: $wrongInput->aptitudePercentiles,
        gwa: $wrongInput->gwa,
    );
    expect(fn () => app(ProgramBenchmarkPreviewQuery::class)->handle($actor, $program, $cycle, $wrongInput, 'active'))
        ->toThrow(AuthorizationException::class)
        ->and($benchmarkQueries)->toBeEmpty();
});
test('supplemental snapshot retains the activation resolved before a concurrent activation change', function (): void {
    [$attempt, , $actor] = buildMessagingReadyAttempt($this, 'tabfmp01@example.test');
    (new AdmissionBenchmarkSeeder)->run();
    Permission::findOrCreate('guidance.consultation.decide');
    $actor->givePermissionTo('guidance.consultation.decide');
    createTabFmGovernanceTypedPercentileSitting($attempt, $actor);
    $admissionStateBefore = $attempt->only([
        'status',
        'gwa',
        'strand',
        'program_preference_1_code',
        'program_preference_2_code',
    ]);
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $institution = Institution::query()->findOrFail($attempt->institution_id);
    $activation = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
    expect($activation)->not->toBeNull();
    $activationId = $activation->id;
    $set = AdmissionBenchmarkSet::query()->findOrFail($activation->benchmark_set_id);
    $contentDigest = $set->content_sha256;

    $allProgramCodes = $set->exemplars()
        ->with('program')
        ->get()
        ->pluck('program.code')
        ->unique()
        ->sort()
        ->values()
        ->all();
    $submittedProgramCodes = [];
    config(['admission_engine.tabfm.driver' => 'http', 'admission_engine.tabfm.endpoint' => 'http://tabfm-inference:8101']);
    Http::preventStrayRequests();
    Http::fake([
        'http://tabfm-inference:8101/inference/tabpfn' => function (Request $request) use ($activation, $actor, $cycle, $institution, $set, &$submittedProgramCodes) {
            $activation->forceFill(['superseded_at' => now()])->save();
            AdmissionBenchmarkActivation::query()->create([
                'institution_id' => $institution->id,
                'academic_cycle_id' => $cycle->id,
                'benchmark_set_id' => $set->id,
                'sequence' => (int) $activation->sequence + 1,
                'activated_by_user_id' => $actor->id,
                'idempotency_key' => 'tabfm-provenance-race-activation',
                'activated_at' => now(),
            ]);
            $payload = $request->data();
            $submittedProgramCodes[] = collect($payload['context_exemplars'])
                ->pluck('program_code')
                ->unique()
                ->sort()
                ->values()
                ->all();

            return Http::response(tabFmGovernanceNeuralSuccessFixture($payload), 200);
        },
    ]);

    actingAsAdmissionSupportStaff($this, $actor)
        ->post(route('consultation.evidence.store', ['attempt' => $attempt->id]), [
            'evidence_source' => 'examinee_statement',
            'vocational_activities' => [],
            'activities_reviewed' => false,
            'counselor_notes' => null,
            'benchmark_activation_id' => (int) $activationId,
            'schema_digest' => $set->schema_sha256,
            'idempotency_key' => 'tabfm-provenance-race-evidence',
        ])
        ->assertRedirect();

    $attemptAfterEvidence = $attempt->fresh();
    $expectedInput = AdmissionInputPayload::fromAttempt(
        $attemptAfterEvidence,
        ConsultationScoreProjection::admissionInputSeries(app(ConsultationScoreProjection::class)->forAttempt($attemptAfterEvidence)),
    );
    $snapshot = AdmissionSupportSnapshot::latestForAttempt($attempt->id);
    expect($snapshot)->not->toBeNull()
        ->and($snapshot->payload['status'])->toBe('evaluated')
        ->and($snapshot->benchmark_activation_id)->toBe($activationId)
        ->and($snapshot->benchmark_hash)->toBe($contentDigest)
        ->and($snapshot->payload['evaluation_context']['benchmark_activation_id'])->toBe($activationId)
        ->and($snapshot->input_digest)->toBe($expectedInput->digest())
        ->and($snapshot->payload['evaluation_context']['input_digest'])->toBe($snapshot->input_digest)
        ->and(AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)?->id)->not->toBe($activationId)
        ->and($attemptAfterEvidence->only(array_keys($admissionStateBefore)))->toBe($admissionStateBefore);
    expect($submittedProgramCodes)->toBe([$allProgramCodes]);
    Http::assertSentCount(1);
});
