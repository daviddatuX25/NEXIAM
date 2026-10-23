<?php

namespace App\AdmissionEngine\Providers;

use App\AdmissionEngine\Contracts\AdmissionEngineProvider;
use App\AdmissionEngine\DTOs\AdmissionInputPayload;
use App\AdmissionEngine\DTOs\EngineEvaluationContext;
use App\AdmissionEngine\DTOs\EngineEvaluationResult;
use App\AdmissionEngine\DTOs\EngineManifest;
use App\AdmissionEngine\DTOs\ProgramCompatibilityItem;
use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\AdmissionEngine\Support\KnownHistoricalBenchmarkRecovery;
use App\AdmissionEngine\Support\TabFmProvenance;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkDraft;
use App\Models\Admission\AdmissionBenchmarkExemplar;
use App\Models\Admission\AdmissionBenchmarkExemplarValue;
use App\Models\Admission\AdmissionBenchmarkFeature;
use App\Models\Admission\AdmissionBenchmarkRevision;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class TabFmPairwiseProvider implements AdmissionEngineProvider
{
    public function __construct(private readonly KnownHistoricalBenchmarkRecovery $recovery) {}

    /**
     * @var list<array{key: string, label: string, source_key: string}>
     */
    private const RADAR_AXES = [
        ['key' => 'logic', 'label' => 'Logic', 'source_key' => 'LOGIC'],
        ['key' => 'verbal', 'label' => 'Verbal', 'source_key' => 'PROFILE_VERBAL'],
        ['key' => 'numerical', 'label' => 'Numerical', 'source_key' => 'PROFILE_QUANT'],
        ['key' => 'spatial', 'label' => 'Spatial', 'source_key' => 'SPATIAL'],
        ['key' => 'memory', 'label' => 'Memory', 'source_key' => 'MEMORY'],
        ['key' => 'language', 'label' => 'Language', 'source_key' => 'LANGUAGE'],
    ];

    public function identifier(): string
    {
        return 'tabfm_pairwise';
    }

    public function slot(): string
    {
        return 'tabfm_pairwise';
    }

    public function manifest(?int $academicCycleId = null): EngineManifest
    {
        $cycleId = $academicCycleId ?? AcademicCycle::active()?->id;
        $isActive = $cycleId !== null && AdmissionBenchmarkActivation::currentForCycle($cycleId) !== null;
        $httpDriverConfigured = config('admission_engine.tabfm.driver') === 'http';
        $inferenceApproved = TabFmProvenance::hostedUseIsApproved();
        $inferenceReady = $httpDriverConfigured && $inferenceApproved;

        return new EngineManifest(
            identifier: $this->identifier(),
            slot: $this->slot(),
            displayName: 'TabFM Course Match Index',
            description: 'Neural pairwise compatibility against the selected benchmark; outputs are advisory and depend on an approved, pinned model.',
            engineVersion: (string) config('admission_engine.tabfm.version', 'tabfm-pairwise-v1'),
            runtimeStatus: $inferenceReady ? 'configured' : 'unavailable',
            cycleReadiness: ! $inferenceReady ? 'unavailable' : ($isActive ? 'active' : 'needs_benchmark'),
            statusBadge: ! $httpDriverConfigured
                ? 'Unavailable · TabPFN not configured'
                : (! $inferenceApproved
                    ? 'Unavailable · model approvals and exact pins required'
                    : ($isActive ? 'Active benchmark · Model readiness unverified' : 'Needs Benchmark Set')),
            requiredArtifacts: ['Reviewed benchmark set', 'Approved pinned TabPFN checkpoint'],
            supportedInputs: ['GWA', 'Typed aptitude outputs from approved instruments'],
            isConfigurable: true,
        );
    }

    public function canEvaluate(AdmissionInputPayload $input): bool
    {
        $set = $this->activation($input)?->benchmarkSet;

        return $set !== null && $this->isUsableActiveSet($set);
    }

    public function evaluate(AdmissionInputPayload $input): EngineEvaluationResult
    {
        $activation = $this->activation($input);
        $set = $activation?->benchmarkSet;
        $context = new EngineEvaluationContext(
            source: 'active',
            benchmarkActivationId: $activation?->id,
            benchmarkSetId: $set?->id,
            benchmarkRevisionId: null,
            benchmarkDraftId: null,
            benchmarkVersion: $set === null ? null : 'v'.$set->version,
            schemaDigest: $set?->schema_sha256,
            contentDigest: $set?->content_sha256,
            inputDigest: $input->digest(),
            requestId: (string) Str::uuid(),
            institutionId: $input->institutionId,
            academicCycleId: $input->academicCycleId,
        );
        if ($set === null) {
            return new EngineEvaluationResult(
                $this->identifier(),
                $this->slot(),
                'standby',
                'unavailable',
                null,
                null,
                now()->toIso8601String(),
                [],
                [],
                'No active benchmark set is available for this academic cycle.',
                'unavailable',
                'no_active_benchmark',
                $context,
            );
        }

        return $this->evaluateAtActivation($input, $activation);
    }

    public function evaluateAtActivation(
        AdmissionInputPayload $input,
        AdmissionBenchmarkActivation $activation,
    ): EngineEvaluationResult {
        if ((int) $activation->institution_id !== $input->institutionId
            || (int) $activation->academic_cycle_id !== $input->academicCycleId) {
            throw new InvalidArgumentException('The benchmark activation does not match the input institution and academic cycle.');
        }
        $activation->loadMissing(['benchmarkSet.features', 'benchmarkSet.exemplars.program', 'benchmarkSet.exemplars.values']);
        $set = $activation->benchmarkSet;
        if ($set === null || (int) $set->institution_id !== $input->institutionId) {
            throw new InvalidArgumentException('The selected benchmark set is unavailable for this input.');
        }
        $this->assertUsableActiveSet($set);
        $context = new EngineEvaluationContext(
            source: 'active',
            benchmarkActivationId: $activation->id,
            benchmarkSetId: $set->id,
            benchmarkRevisionId: null,
            benchmarkDraftId: null,
            benchmarkVersion: 'v'.$set->version,
            schemaDigest: $set->schema_sha256,
            contentDigest: $set->content_sha256,
            inputDigest: $input->digest(),
            requestId: (string) Str::uuid(),
            institutionId: $input->institutionId,
            academicCycleId: $input->academicCycleId,
        );

        return $this->evaluateSet($input, $set, $context);
    }

    public function evaluateActiveProgram(
        AdmissionInputPayload $input,
        AdmissionBenchmarkActivation $activation,
        Program $program,
        string $requestId,
    ): EngineEvaluationResult {

        if ((int) $activation->academic_cycle_id !== $input->academicCycleId) {
            throw new InvalidArgumentException('The active benchmark activation does not match the selected cycle.');
        }
        $canonicalSet = AdmissionBenchmarkSet::query()
            ->with(['features', 'exemplars.program', 'exemplars.values.feature'])
            ->whereKey($activation->benchmark_set_id)
            ->firstOrFail();
        $this->assertUsableActiveSet($canonicalSet);
        $set = $activation->benchmarkSet()->with([
            'features',
            'exemplars' => static fn ($query) => $query->where('program_id', $program->id)->with(['program', 'values']),
        ])->firstOrFail();
        if ((int) $set->institution_id !== (int) $program->institution_id) {
            throw new InvalidArgumentException('The active benchmark set is outside the selected program institution.');
        }
        $set->setRelation('exemplars', new EloquentCollection($set->exemplars->values()));
        $context = new EngineEvaluationContext(
            source: 'active',
            benchmarkActivationId: $activation->id,
            benchmarkSetId: $set->id,
            benchmarkRevisionId: null,
            benchmarkDraftId: null,
            benchmarkVersion: 'v'.$set->version,
            schemaDigest: $set->schema_sha256,
            contentDigest: $set->content_sha256,
            inputDigest: $input->digest(),
            requestId: $requestId,
            institutionId: (int) $program->institution_id,
            academicCycleId: (int) $activation->academic_cycle_id,
            programId: (int) $program->id,
        );

        return $this->evaluateSet($input, $set, $context);
    }

    public function evaluateDraft(
        AdmissionInputPayload $input,
        AdmissionBenchmarkDraft $draft,
        AdmissionBenchmarkRevision $revision,
        AdmissionBenchmarkSet $baseSet,
        Program $program,
        string $requestId,
        ?int $recoveryId = null,
    ): EngineEvaluationResult {
        if (
            (int) $draft->institution_id !== $input->institutionId
            || (int) $draft->program_id !== (int) $program->id
            || (int) $draft->academic_cycle_id !== $input->academicCycleId
            || (int) $revision->draft_id !== (int) $draft->id
            || (int) $draft->base_benchmark_set_id !== (int) $baseSet->id
        ) {
            throw new InvalidArgumentException('The selected benchmark draft revision does not match its authorized evaluation context.');
        }
        $definitions = BenchmarkInputSchema::schemaForDraft($draft, $revision);
        $features = new EloquentCollection;
        foreach ($definitions as $position => $definition) {
            $feature = new AdmissionBenchmarkFeature;
            $attributes = $definition;
            $attributes['source_definition_json'] = $definition['source_definition'] ?? null;
            unset($attributes['source_definition'], $attributes['binding_id']);
            $feature->forceFill($attributes);
            $feature->id = $position + 1;
            $features->push($feature);
        }
        $featureMap = $features->keyBy('feature_key');
        $exemplars = [];
        $tiers = $revision->content['tiers'] ?? null;
        if (! is_array($tiers)) {
            throw new InvalidArgumentException('The selected benchmark draft revision has invalid tier content.');
        }
        foreach ([1, 2, 3, 4, 5] as $tier) {
            $tierContent = $tiers[(string) $tier] ?? $tiers[$tier] ?? null;
            if (! is_array($tierContent) || ! is_array($tierContent['values'] ?? null)) {
                throw new InvalidArgumentException('The selected benchmark draft revision must contain five complete tiers.');
            }
            $exemplar = new AdmissionBenchmarkExemplar([
                'tier_level' => $tier,
                'tier_label' => match ($tier) {
                    1 => 'Honors',
                    2 => 'Regular',
                    3 => 'Cutoff',
                    4 => 'Cross-Strand',
                    default => 'Negative',
                },
                'target_class' => $tier === 5 ? 0 : 1,
            ]);
            $exemplar->setRelation('program', $program);
            $values = [];
            foreach ($tierContent['values'] as $featureKey => $value) {
                $feature = $featureMap->get($featureKey);
                if ($feature === null) {
                    throw new InvalidArgumentException('The selected benchmark draft revision contains a feature outside its pinned schema.');
                }
                $missing = $value === null;
                $numeric = $feature->logical_type === 'numeric' || $feature->logical_type === 'binary';
                $values[] = new AdmissionBenchmarkExemplarValue([
                    'benchmark_feature_id' => $feature->id,
                    'is_missing' => $missing,
                    'numeric_value' => ! $missing && $numeric ? (float) $value : null,
                    'categorical_value' => ! $missing && ! $numeric ? (string) $value : null,
                ]);
            }
            $exemplar->setRelation('values', new EloquentCollection($values));
            $exemplars[] = $exemplar;
        }
        $set = new AdmissionBenchmarkSet([
            'id' => $baseSet->id,
            'version' => $baseSet->version,
            'name' => $baseSet->name.' · Draft r'.$revision->version,
            'schema_sha256' => $draft->feature_schema_digest,
            'content_sha256' => hash('sha256', json_encode($revision->content, JSON_THROW_ON_ERROR)),
        ]);
        $set->setRelation('features', $features);
        $set->setRelation('exemplars', new EloquentCollection($exemplars));
        $context = new EngineEvaluationContext(
            source: 'draft',
            benchmarkActivationId: null,
            benchmarkSetId: $baseSet->id,
            benchmarkRevisionId: $revision->id,
            benchmarkDraftId: $draft->id,
            benchmarkVersion: 'r'.$revision->version,
            schemaDigest: $draft->feature_schema_digest,
            contentDigest: $set->content_sha256,
            inputDigest: $input->digest(),
            requestId: $requestId,
            institutionId: (int) $draft->institution_id,
            academicCycleId: (int) $draft->academic_cycle_id,
            benchmarkRecoveryId: $recoveryId,
            programId: (int) $program->id,
        );

        return $this->evaluateSet($input, $set, $context);
    }

    private function evaluateSet(
        AdmissionInputPayload $input,
        AdmissionBenchmarkSet $set,
        EngineEvaluationContext $context,
    ): EngineEvaluationResult {
        if ($set->exists) {
            $set->loadMissing('features.aptitudeArea', 'exemplars.values');
        } else {
            $set->features->loadMissing('aptitudeArea');
        }
        if (! BenchmarkInputSchema::isCanonicalFeatureSchema(BenchmarkInputSchema::schemaForSet($set))) {
            return $this->unavailableResult($set, $context, [], 'noncanonical_feature_schema');
        }
        $featureMap = $set->features->keyBy('feature_key');
        $applicant = array_intersect_key($this->applicantValues($input), $featureMap->all());
        $coverage = $this->coverage($applicant, $featureMap);
        $programsByCode = [];

        foreach ($set->exemplars->groupBy('program_id') as $exemplars) {
            /** @var AdmissionBenchmarkExemplar|null $first */
            $first = $exemplars->first();
            $program = $first?->program;
            if ($program === null || trim((string) $program->code) === '' || trim((string) $program->name) === '') {
                return $this->unavailableResult($set, $context, $coverage, 'invalid_program_manifest');
            }

            $programCode = (string) $program->code;
            if (array_key_exists($programCode, $programsByCode)) {
                return $this->unavailableResult($set, $context, $coverage, 'duplicate_program_identity');
            }

            $tiers = $exemplars->keyBy('tier_level');
            if (! $tiers->has(3)) {
                return $this->unavailableResult($set, $context, $coverage, 'tier3_missing');
            }

            $programsByCode[$programCode] = [
                'name' => (string) $program->name,
                'tiers' => $tiers,
            ];
        }

        if ($programsByCode === []) {
            return $this->unavailableResult($set, $context, $coverage, 'invalid_program_manifest');
        }

        if (! TabFmProvenance::hostedUseIsApproved()) {
            return $this->unavailableResult($set, $context, $coverage, 'inference_not_approved');
        }

        if (config('admission_engine.tabfm.driver') !== 'http') {
            return $this->unavailableResult($set, $context, $coverage, 'unsupported_driver');
        }

        $remote = $this->dispatchHttp($set->exemplars, $applicant, $featureMap, $set, $programsByCode);
        if ($remote['status'] !== 'success') {
            return $this->unavailableResult($set, $context, $coverage, $remote['reason']);
        }

        $recommendations = $remote['recommendations'];
        usort($recommendations, static fn (array $left, array $right): int => ($right['index'] <=> $left['index']) ?: ($left['code'] <=> $right['code']));
        $items = [];
        foreach ($recommendations as $position => $recommendation) {
            $tiers = $programsByCode[$recommendation['code']]['tiers'];
            $passedCutoff = $this->passesCutoff($applicant, $tiers->get(3), $featureMap);
            $score = $recommendation['index'];
            $band = $score >= 80 ? 'high_match' : ($score >= 68 ? 'moderate_match' : 'below_threshold');

            $items[] = new ProgramCompatibilityItem(
                $position + 1,
                $recommendation['code'],
                $recommendation['name'],
                $score,
                $band,
                $passedCutoff,
                $recommendation['drivers'],
                $this->buildRadarComparison($input, $tiers, $featureMap),
            );
        }

        $modalityCoverage = array_replace($coverage, $remote['modality_coverage']);
        $modalityCoverage['inference_provenance'] = $remote['provenance'];

        return new EngineEvaluationResult(
            $this->identifier(),
            $this->slot(),
            'evaluated',
            $remote['engine_version'],
            $context->benchmarkVersion,
            $set->name,
            now()->toIso8601String(),
            $modalityCoverage,
            $items,
            'Advisory in-context benchmark compatibility indices generated. Models advise — humans decide.',
            'tabpfn_pytorch',
            null,
            $context,
        );
    }

    /**
     * @param  array<string, mixed>  $coverage
     */
    private function unavailableResult(
        AdmissionBenchmarkSet $set,
        EngineEvaluationContext $context,
        array $coverage,
        string $reason,
    ): EngineEvaluationResult {
        $message = $reason === 'inference_not_approved'
            ? 'TabFM inference is unavailable because institutional and hosted-use approvals and exact model pins are required. No model evaluation was run; counselors proceed with standard evaluation.'
            : 'TabFM neural inference is unavailable. Counselors proceed with standard evaluation.';

        return new EngineEvaluationResult(
            $this->identifier(),
            $this->slot(),
            'unavailable',
            'unavailable',
            $context->benchmarkVersion,
            $set->name,
            now()->toIso8601String(),
            $coverage,
            [],
            $message,
            'unavailable',
            $reason,
            $context,
        );
    }

    private function isUsableActiveSet(AdmissionBenchmarkSet $set): bool
    {
        return ! $this->recovery->isKnownHistoricalSet($set)
            && hash_equals((string) $set->schema_sha256, $set->computedSchemaDigest())
            && hash_equals((string) $set->content_sha256, $set->computedContentDigest());
    }

    private function assertUsableActiveSet(AdmissionBenchmarkSet $set): void
    {
        if ($this->recovery->isKnownHistoricalSet($set)) {
            throw new InvalidArgumentException('The active historical benchmark requires explicit recovery and review before evaluation.');
        }
        if (! hash_equals((string) $set->schema_sha256, $set->computedSchemaDigest())
            || ! hash_equals((string) $set->content_sha256, $set->computedContentDigest())) {
            throw new InvalidArgumentException('The active benchmark failed its canonical schema or content integrity check.');
        }
    }

    private function activation(AdmissionInputPayload $input): ?AdmissionBenchmarkActivation
    {
        return AdmissionBenchmarkActivation::currentForCycle($input->academicCycleId)
            ?->load(['benchmarkSet.features', 'benchmarkSet.exemplars.program', 'benchmarkSet.exemplars.values']);
    }

    /** @return array<string, mixed> */
    private function applicantValues(AdmissionInputPayload $input): array
    {
        $values = ['gwa' => $input->gwa];
        foreach ($input->aptitudePercentiles as $key => $value) {
            $values[$key] = $value;
        }

        return $values;
    }

    /** @param array<string, mixed> $applicant
     * @param  Collection<string, AdmissionBenchmarkFeature>  $featureMap
     * @return array<string, mixed>
     */
    private function coverage(array $applicant, Collection $featureMap): array
    {
        $features = [];
        foreach ($featureMap as $key => $feature) {
            $features[$key] = array_key_exists($key, $applicant) && $applicant[$key] !== null;
        }

        return [
            'gwa' => $features['gwa'] ?? false,
            'aptitudes' => count(array_filter(array_intersect_key($features, array_flip(['LOGIC', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'SPATIAL', 'MEMORY', 'LANGUAGE'])))),
            'features' => $features,
        ];
    }

    /** @param array<string, mixed> $applicant
     * @param  Collection<string, AdmissionBenchmarkFeature>  $featureMap
     */
    private function passesCutoff(array $applicant, ?AdmissionBenchmarkExemplar $cutoff, Collection $featureMap): bool
    {
        if ($cutoff === null) {
            return true;
        }
        $cells = $cutoff->values->keyBy('benchmark_feature_id');
        foreach (['gwa', 'PROFILE_QUANT', 'LOGIC', 'PROFILE_VERBAL'] as $key) {
            $value = $applicant[$key] ?? null;
            $feature = $featureMap->get($key);
            $cell = $feature === null ? null : $cells->get($feature->id);
            if ($value !== null && $cell !== null && ! $cell->is_missing && is_numeric($cell->numeric_value) && (float) $value < (float) $cell->numeric_value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  EloquentCollection<int, AdmissionBenchmarkExemplar>  $exemplars
     * @param  array<string, mixed>  $applicant
     * @param  Collection<string, AdmissionBenchmarkFeature>  $featureMap
     * @param  array<string, array{name: string, tiers: Collection<int, AdmissionBenchmarkExemplar>}>  $programsByCode
     * @return array{status: 'success', engine_version: string, provenance: array{package: string, package_version: string, checkpoint_identity: string, checkpoint_sha256: string}, modality_coverage: array<string, mixed>, recommendations: list<array{code: string, name: string, index: float, drivers: list<string>}>}|array{status: 'unavailable', reason: string}
     */
    private function dispatchHttp(
        EloquentCollection $exemplars,
        array $applicant,
        Collection $featureMap,
        AdmissionBenchmarkSet $set,
        array $programsByCode,
    ): array {
        $endpoint = rtrim((string) config('admission_engine.tabfm.endpoint'), '/');
        $featureDefinitions = array_map(static function (array $definition) use ($applicant): array {
            if (($definition['feature_key'] ?? null) === 'gwa' && ($applicant['gwa'] ?? null) === null) {
                $definition['is_required'] = false;
            }

            return $definition;
        }, app(BenchmarkInputSchema::class)->featureDefinitions($set));
        $definitionsByKey = collect($featureDefinitions)->keyBy('feature_key');

        try {
            $response = Http::connectTimeout((float) config('admission_engine.tabfm.timeout_seconds'))
                ->timeout((float) config('admission_engine.tabfm.timeout_seconds'))
                ->post($endpoint.'/inference/tabpfn', [
                    'context_exemplars' => $exemplars->map(static function (AdmissionBenchmarkExemplar $exemplar) use ($featureMap): array {
                        $values = [];
                        foreach ($exemplar->values as $value) {
                            $feature = $featureMap->firstWhere('id', $value->benchmark_feature_id);
                            if ($feature !== null) {
                                $values[$feature->feature_key] = $value->is_missing ? null : match ($feature->logical_type) {
                                    'binary' => $value->numeric_value === null ? null : (int) $value->numeric_value,
                                    'numeric' => $value->numeric_value === null ? null : (float) $value->numeric_value,
                                    default => $value->categorical_value,
                                };
                            }
                        }

                        return [
                            'program_code' => $exemplar->program?->code,
                            'program_name' => $exemplar->program?->name,
                            'tier_level' => $exemplar->tier_level,
                            'target_class' => $exemplar->target_class,
                            'values' => $values,
                        ];
                    })->all(),
                    'query_applicant' => $applicant,
                    'feature_definitions' => $featureDefinitions,
                ]);
            if (! $response->successful()) {
                return ['status' => 'unavailable', 'reason' => 'sidecar_unavailable'];
            }

            $data = $response->json();
        } catch (Throwable) {
            return ['status' => 'unavailable', 'reason' => 'sidecar_unavailable'];
        }

        if (! is_array($data) || array_is_list($data)) {
            return ['status' => 'unavailable', 'reason' => 'invalid_adapter_response'];
        }

        $engineVersion = $data['engine_version'] ?? null;
        $provenance = $data['provenance'] ?? null;
        $packageVersion = is_array($provenance) ? ($provenance['package_version'] ?? null) : null;
        $checkpointIdentity = is_array($provenance) ? ($provenance['checkpoint_identity'] ?? null) : null;
        $checkpointSha256 = is_array($provenance) ? ($provenance['checkpoint_sha256'] ?? null) : null;
        if (($data['status'] ?? null) !== 'evaluated'
            || ($data['inference_backend'] ?? null) !== 'tabpfn_pytorch'
            || ! array_key_exists('fallback_reason', $data)
            || $data['fallback_reason'] !== null
            || ! is_array($provenance)
            || array_is_list($provenance)
            || ($provenance['package'] ?? null) !== 'tabpfn'
            || ! is_string($engineVersion)
            || trim($engineVersion) === ''
            || ! is_string($packageVersion)
            || ! is_string($checkpointIdentity)
            || trim($checkpointIdentity) === ''
            || ! is_string($checkpointSha256)
            || preg_match('/^[a-f0-9]{64}$/D', $checkpointSha256) !== 1
            || ! TabFmProvenance::matchesApproved($provenance, $engineVersion)) {
            return [
                'status' => 'unavailable',
                'reason' => ($data['fallback_reason'] ?? null) !== null ? 'inference_fallback' : 'invalid_inference_provenance',
            ];
        }

        $executionTime = $data['execution_time_ms'] ?? null;
        $modalityCoverage = $data['modality_coverage'] ?? null;
        $featureCoverage = is_array($modalityCoverage) ? ($modalityCoverage['feature_coverage'] ?? null) : null;
        $missingFeatures = is_array($modalityCoverage) ? ($modalityCoverage['missing_features'] ?? null) : null;
        $missingOptionalFeatures = is_array($modalityCoverage) ? ($modalityCoverage['missing_optional_features'] ?? null) : null;
        $featureProvenance = $data['feature_provenance'] ?? null;
        if ((! is_int($executionTime) && ! is_float($executionTime))
            || ! is_finite((float) $executionTime)
            || $executionTime < 0
            || ! is_array($modalityCoverage)
            || array_is_list($modalityCoverage)
            || ! is_int($modalityCoverage['available_features'] ?? null)
            || $modalityCoverage['available_features'] < 0
            || $modalityCoverage['available_features'] > count($featureMap)
            || ! is_int($modalityCoverage['total_features'] ?? null)
            || $modalityCoverage['total_features'] !== count($featureMap)
            || ! is_array($featureCoverage)
            || array_is_list($featureCoverage)
            || array_diff_key($featureMap->all(), $featureCoverage) !== []
            || array_diff_key($featureCoverage, $featureMap->all()) !== []
            || ! is_array($missingFeatures)
            || ! array_is_list($missingFeatures)
            || ! is_array($missingOptionalFeatures)
            || ! array_is_list($missingOptionalFeatures)
            || ! is_array($featureProvenance)
            || ! array_is_list($featureProvenance)
            || count($featureProvenance) !== count($featureDefinitions)) {
            return ['status' => 'unavailable', 'reason' => 'invalid_adapter_response'];
        }

        $expectedMissingFeatures = [];
        $expectedMissingOptionalFeatures = [];
        foreach ($featureMap as $featureKey => $feature) {
            $available = array_key_exists($featureKey, $applicant) && $applicant[$featureKey] !== null;
            if (! $available) {
                $expectedMissingFeatures[] = $featureKey;
                $effectiveRequired = (bool) ($definitionsByKey->get($featureKey)['is_required'] ?? $feature->is_required);
                if (! $effectiveRequired) {
                    $expectedMissingOptionalFeatures[] = $featureKey;
                }
            }

            $reportedCoverage = $featureCoverage[$featureKey] ?? null;
            if (! is_array($reportedCoverage)
                || array_is_list($reportedCoverage)
                || ($reportedCoverage['available'] ?? null) !== $available
                || ($reportedCoverage['required'] ?? null) !== (bool) ($definitionsByKey->get($featureKey)['is_required'] ?? $feature->is_required)) {
                return ['status' => 'unavailable', 'reason' => 'invalid_adapter_response'];
            }
        }

        $actualMissingFeatures = $missingFeatures;
        $actualMissingOptionalFeatures = $missingOptionalFeatures;
        sort($expectedMissingFeatures);
        sort($expectedMissingOptionalFeatures);
        sort($actualMissingFeatures);
        sort($actualMissingOptionalFeatures);
        if ($modalityCoverage['available_features'] !== count($featureMap) - count($expectedMissingFeatures)
            || $actualMissingFeatures !== $expectedMissingFeatures
            || $actualMissingOptionalFeatures !== $expectedMissingOptionalFeatures) {
            return ['status' => 'unavailable', 'reason' => 'invalid_adapter_response'];
        }

        $seenFeatureProvenance = [];
        foreach ($featureProvenance as $feature) {
            if (! is_array($feature)
                || array_is_list($feature)
                || ! is_string($feature['feature_key'] ?? null)
                || ! $featureMap->has($feature['feature_key'])
                || isset($seenFeatureProvenance[$feature['feature_key']])) {
                return ['status' => 'unavailable', 'reason' => 'invalid_adapter_response'];
            }
            $seenFeatureProvenance[$feature['feature_key']] = true;
        }
        if (count($seenFeatureProvenance) !== count($featureMap)) {
            return ['status' => 'unavailable', 'reason' => 'invalid_adapter_response'];
        }

        $recommendations = $data['recommendations'] ?? null;
        if (! is_array($recommendations)
            || ! array_is_list($recommendations)
            || count($recommendations) !== count($programsByCode)) {
            return ['status' => 'unavailable', 'reason' => 'invalid_adapter_response'];
        }

        $normalizedRecommendations = [];
        $seenPrograms = [];
        foreach ($recommendations as $position => $recommendation) {
            if (! is_array($recommendation)
                || array_is_list($recommendation)
                || ! is_int($recommendation['rank'] ?? null)
                || $recommendation['rank'] !== $position + 1
                || ! is_string($recommendation['program_code'] ?? null)
                || ! array_key_exists($recommendation['program_code'], $programsByCode)
                || isset($seenPrograms[$recommendation['program_code']])
                || ! is_string($recommendation['program_name'] ?? null)
                || $recommendation['program_name'] !== $programsByCode[$recommendation['program_code']]['name']) {
                return ['status' => 'unavailable', 'reason' => 'invalid_adapter_response'];
            }

            $score = $recommendation['compatibility_index'] ?? null;
            if ((! is_int($score) && ! is_float($score))
                || ! is_finite((float) $score)
                || $score < 0
                || $score > 100) {
                return ['status' => 'unavailable', 'reason' => 'invalid_adapter_response'];
            }

            $normalizedScore = round((float) $score, 2);
            $band = $normalizedScore >= 80.0 ? 'high_match' : ($normalizedScore >= 68.0 ? 'moderate_match' : 'below_threshold');
            $drivers = $recommendation['drivers'] ?? null;
            if (($recommendation['band'] ?? null) !== $band
                || ! is_array($drivers)
                || ! array_is_list($drivers)) {
                return ['status' => 'unavailable', 'reason' => 'invalid_adapter_response'];
            }
            foreach ($drivers as $driver) {
                if (! is_string($driver)) {
                    return ['status' => 'unavailable', 'reason' => 'invalid_adapter_response'];
                }
            }

            $programCode = $recommendation['program_code'];
            $seenPrograms[$programCode] = true;
            $normalizedRecommendations[] = [
                'code' => $programCode,
                'name' => $recommendation['program_name'],
                'index' => $normalizedScore,
                'drivers' => $drivers,
            ];
        }

        if (count($seenPrograms) !== count($programsByCode)) {
            return ['status' => 'unavailable', 'reason' => 'invalid_adapter_response'];
        }

        return [
            'status' => 'success',
            'engine_version' => $engineVersion,
            'provenance' => $provenance,
            'modality_coverage' => $modalityCoverage,
            'recommendations' => $normalizedRecommendations,
        ];
    }

    /**
     * @param  Collection<int, AdmissionBenchmarkExemplar>  $tiers
     * @param  Collection<string, AdmissionBenchmarkFeature>  $featureMap
     * @return array<string, mixed>|null
     */
    private function buildRadarComparison(
        AdmissionInputPayload $input,
        Collection $tiers,
        Collection $featureMap,
    ): ?array {
        $features = [];
        foreach (self::RADAR_AXES as $axis) {
            $feature = $featureMap->get($axis['source_key']);
            if ($feature === null) {
                continue;
            }

            $sourceDefinition = $feature->source_definition_json;
            $scale = is_array($sourceDefinition) ? ($sourceDefinition['scale'] ?? null) : null;
            $normVersion = is_array($sourceDefinition) ? ($sourceDefinition['norm_version'] ?? null) : null;
            if ((string) $feature->feature_key !== $axis['source_key']
                || $feature->aptitudeArea === null
                || (string) $feature->aptitudeArea->code !== $axis['source_key']
                || ! is_array($sourceDefinition)
                || ($sourceDefinition['source_code'] ?? null) !== $axis['source_key']
                || ! in_array($scale, ['percentile', 'percentile_rank'], true)
                || ! is_string($normVersion)
                || trim($normVersion) === '') {
                return null;
            }

            $features[$axis['key']] = $feature;
        }

        if ($features === []) {
            return null;
        }

        $studentValues = [];
        foreach (self::RADAR_AXES as $axis) {
            $studentScale = $input->aptitudeScales[$axis['source_key']] ?? null;
            if (! in_array($studentScale, ['percentile', 'percentile_rank'], true)) {
                $studentValues[$axis['key']] = null;

                continue;
            }

            $value = $input->aptitudePercentiles[$axis['source_key']] ?? null;
            $studentValues[$axis['key']] = is_numeric($value) && is_finite((float) $value)
                ? (float) $value
                : null;
        }

        $profiles = [];
        foreach (['cutoff' => 3, 'regular' => 2, 'honors' => 1] as $profile => $tierLevel) {
            $exemplar = $tiers->get($tierLevel);
            $cells = $exemplar?->values->keyBy('benchmark_feature_id') ?? collect();
            $values = [];

            foreach (self::RADAR_AXES as $axis) {
                $feature = $features[$axis['key']] ?? null;
                $cell = $feature === null ? null : $cells->get($feature->id);
                $value = $cell === null || $cell->is_missing ? null : $cell->numeric_value;
                $values[$axis['key']] = is_numeric($value) && is_finite((float) $value)
                    ? (float) $value
                    : null;
            }

            $profiles[$profile] = ['tier' => $tierLevel, 'values' => $values];
        }

        $hasComparableValues = false;
        foreach (self::RADAR_AXES as $axis) {
            if ($studentValues[$axis['key']] === null) {
                continue;
            }
            foreach ($profiles as $profile) {
                if ($profile['values'][$axis['key']] !== null) {
                    $hasComparableValues = true;
                    break 2;
                }
            }
        }

        if (! $hasComparableValues) {
            return null;
        }

        return [
            'version' => 1,
            'scale' => ['kind' => 'percentile', 'min' => 0, 'max' => 100],
            'axes' => self::RADAR_AXES,
            'student' => ['values' => $studentValues],
            'profiles' => $profiles,
        ];
    }
}
