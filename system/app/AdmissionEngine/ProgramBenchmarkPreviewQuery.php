<?php

namespace App\AdmissionEngine;

use App\AdmissionEngine\DTOs\AdmissionInputPayload;
use App\AdmissionEngine\DTOs\EngineEvaluationContext;
use App\AdmissionEngine\DTOs\EngineEvaluationResult;
use App\AdmissionEngine\Providers\TabFmPairwiseProvider;
use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\AdmissionEngine\Support\BenchmarkRangeAuthoring;
use App\AdmissionEngine\Support\KnownHistoricalBenchmarkRecovery;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkDraft;
use App\Models\Admission\AdmissionBenchmarkFeature;
use App\Models\Admission\AdmissionBenchmarkRecovery;
use App\Models\Admission\AdmissionBenchmarkRevision;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ProgramBenchmarkPreviewQuery
{
    public function __construct(
        private readonly GovernanceAuthorizer $authorizer,
        private readonly TabFmPairwiseProvider $provider,
        private readonly KnownHistoricalBenchmarkRecovery $recovery,
    ) {}

    public function handle(
        User $actor,
        Program $program,
        AcademicCycle $cycle,
        AdmissionInputPayload $input,
        string $source = 'active',
        ?int $revisionId = null,
        ?string $requestId = null,
    ): EngineEvaluationResult {
        $this->authorizer->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_PREVIEW, $program, $cycle);
        $program = Program::query()->findOrFail($program->getKey());
        $cycle = AcademicCycle::query()->findOrFail($cycle->getKey());
        $this->authorizer->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_PREVIEW, $program, $cycle);
        if (
            (int) $input->institutionId !== (int) $program->institution_id
            || (int) $input->academicCycleId !== (int) $cycle->id
            || (int) $cycle->institution_id !== (int) $program->institution_id
        ) {
            throw new AuthorizationException('The preview input must match the authorized program and academic cycle.');
        }

        $requestId = $requestId !== null && trim($requestId) !== '' ? $requestId : (string) Str::uuid();

        return match ($source) {
            'active' => $this->evaluateActive($program, $cycle, $input, $requestId, $revisionId),
            'draft' => $this->evaluateDraft($program, $cycle, $input, $requestId, $revisionId),
            default => throw new InvalidArgumentException('Preview source must be active or draft.'),
        };
    }

    private function evaluateActive(
        Program $program,
        AcademicCycle $cycle,
        AdmissionInputPayload $input,
        string $requestId,
        ?int $revisionId,
    ): EngineEvaluationResult {
        if ($revisionId !== null) {
            throw new InvalidArgumentException('An active benchmark preview cannot select a draft revision.');
        }
        $activation = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
        if ($activation === null) {
            return $this->standby($program, $cycle, $input, $requestId, 'No active benchmark set is available for this academic cycle.');
        }
        if ((int) $activation->institution_id !== (int) $program->institution_id
            || (int) $activation->academic_cycle_id !== (int) $cycle->id) {
            throw new AuthorizationException('The active benchmark activation is outside the authorized program and cycle.');
        }
        $set = AdmissionBenchmarkSet::query()
            ->with(['features', 'exemplars.program', 'exemplars.values.feature'])
            ->whereKey($activation->benchmark_set_id)
            ->firstOrFail();
        if ((int) $set->institution_id !== (int) $program->institution_id) {
            throw new AuthorizationException('The active benchmark set is outside the authorized program institution.');
        }
        if ($this->recovery->isKnownHistoricalSet($set)) {
            return $this->standby($program, $cycle, $input, $requestId, 'The current historical benchmark requires explicit recovery and review before preview.');
        }
        if (! hash_equals((string) $set->schema_sha256, $set->computedSchemaDigest())
            || ! hash_equals((string) $set->content_sha256, $set->computedContentDigest())) {
            throw new InvalidArgumentException('The active benchmark failed its canonical schema or content integrity check.');
        }

        return $this->provider->evaluateActiveProgram($input, $activation, $program, $requestId);
    }

    private function standby(Program $program, AcademicCycle $cycle, AdmissionInputPayload $input, string $requestId, string $message): EngineEvaluationResult
    {
        $context = new EngineEvaluationContext(
            source: 'active',
            benchmarkActivationId: null,
            benchmarkSetId: null,
            benchmarkRevisionId: null,
            benchmarkDraftId: null,
            benchmarkVersion: null,
            schemaDigest: null,
            contentDigest: null,
            inputDigest: $input->digest(),
            requestId: $requestId,
            institutionId: (int) $program->institution_id,
            academicCycleId: (int) $cycle->id,
            programId: (int) $program->id,
        );

        return new EngineEvaluationResult(
            'tabfm_pairwise',
            'tabfm_pairwise',
            'standby',
            (string) config('admission_engine.tabfm.version'),
            null,
            null,
            now()->toIso8601String(),
            [],
            [],
            $message,
            evaluationContext: $context,
        );
    }

    private function evaluateDraft(
        Program $program,
        AcademicCycle $cycle,
        AdmissionInputPayload $input,
        string $requestId,
        ?int $revisionId,
    ): EngineEvaluationResult {
        $draft = AdmissionBenchmarkDraft::query()
            ->where('institution_id', $program->institution_id)
            ->where('program_id', $program->id)
            ->where('academic_cycle_id', $cycle->id)
            ->firstOrFail();
        $revision = $revisionId === null
            ? $draft->currentRevision()
            : $draft->revisions()->whereKey($revisionId)->first();
        if (! $revision instanceof AdmissionBenchmarkRevision) {
            throw (new ModelNotFoundException)->setModel(AdmissionBenchmarkRevision::class, [$revisionId]);
        }
        $legacyUnpinned = $revision->base_benchmark_set_id === null && $revision->feature_schema_digest === null;
        $baseSetId = $legacyUnpinned ? $draft->base_benchmark_set_id : $revision->base_benchmark_set_id;
        $pinnedSchemaDigest = $legacyUnpinned ? $draft->feature_schema_digest : $revision->feature_schema_digest;
        $baseSet = $baseSetId === null
            ? null
            : AdmissionBenchmarkSet::query()
                ->with('features')
                ->where('institution_id', $program->institution_id)
                ->where('status', 'published')
                ->whereKey($baseSetId)
                ->first();
        if (! $baseSet instanceof AdmissionBenchmarkSet) {
            throw new InvalidArgumentException('The selected draft has no available published benchmark base.');
        }
        if ((int) $draft->academic_cycle_id !== (int) $input->academicCycleId) {
            throw new AuthorizationException('The selected draft does not match the preview cycle.');
        }
        $recovery = $draft->recovery_id === null
            ? null
            : AdmissionBenchmarkRecovery::query()->whereKey($draft->recovery_id)->firstOrFail();
        $revisionSchema = BenchmarkInputSchema::schemaForDraft($draft, $revision);
        $schemaDigest = BenchmarkInputSchema::digest($revisionSchema);
        $baseSchema = BenchmarkInputSchema::schemaForSet($baseSet);
        if ($recovery !== null) {
            $baseSet = $this->recovery->assertUnchanged($recovery, $cycle);
            $canonical = BenchmarkInputSchema::isCanonicalFeatureSchema($revisionSchema);
            $sourceSchemaDigest = $canonical ? $schemaDigest : $baseSet->computedSchemaDigest();
            $sourceFeatureSchemaMatches = $canonical || hash_equals(BenchmarkInputSchema::digest($baseSchema), $schemaDigest);
            if (! in_array((int) $program->id, $this->recovery->retainedProgramIds($recovery, $baseSet), true)
                || (int) $recovery->source_benchmark_set_id !== (int) $baseSetId
                || ! hash_equals((string) $pinnedSchemaDigest, $sourceSchemaDigest)
                || ($draft->feature_schema_digest !== null && ! hash_equals((string) $draft->feature_schema_digest, $sourceSchemaDigest))
                || ($revision->feature_schema_digest !== null && ! hash_equals((string) $revision->feature_schema_digest, $sourceSchemaDigest))
                || ! $sourceFeatureSchemaMatches) {
                throw new InvalidArgumentException('The selected recovery draft feature schema changed or no longer matches its frozen historical source.');
            }
        } else {
            if ($pinnedSchemaDigest === null || ! hash_equals((string) $pinnedSchemaDigest, $schemaDigest)
                || ($draft->feature_schema_digest !== null && ! hash_equals((string) $draft->feature_schema_digest, $schemaDigest))
                || ($revision->feature_schema_digest !== null && ! hash_equals((string) $revision->feature_schema_digest, $schemaDigest))) {
                throw new InvalidArgumentException('The selected draft revision does not match its pinned feature schema.');
            }
            if (! hash_equals((string) $baseSet->schema_sha256, $baseSet->computedSchemaDigest())) {
                throw new InvalidArgumentException('The published benchmark base schema changed after draft review.');
            }
            if (! hash_equals((string) $baseSet->content_sha256, $baseSet->computedContentDigest())) {
                throw new InvalidArgumentException('The published benchmark base content changed after draft review.');
            }
            if (! BenchmarkInputSchema::isCompatibleSuccessor($baseSchema, $revisionSchema, (int) $program->institution_id)) {
                throw new InvalidArgumentException('The selected draft schema is not a reviewed compatible successor to its intact published base.');
            }
        }
        if (! BenchmarkInputSchema::isCanonicalFeatureSchema($revisionSchema)) {
            return $this->provider->evaluateDraft($input, $draft, $revision, $baseSet, $program, $requestId, $recovery?->id);
        }
        BenchmarkRangeAuthoring::assertConsumable($revision->content, collect($revisionSchema)->pluck('logical_type', 'feature_key')->all());
        $evaluationDraft = clone $draft;
        $evaluationDraft->forceFill([
            'base_benchmark_set_id' => $baseSet->id,
            'feature_schema_digest' => $schemaDigest,
            'feature_schema_json' => $revisionSchema,
        ]);
        $schemaFeatures = new EloquentCollection(array_map(static function (array $definition, int $position): AdmissionBenchmarkFeature {
            $feature = new AdmissionBenchmarkFeature;
            $feature->setRawAttributes([
                'id' => $position + 1,
                'feature_key' => $definition['feature_key'],
                'label' => $definition['label'],
                'logical_type' => $definition['logical_type'],
                'aptitude_area_id' => $definition['aptitude_area_id'] ?? null,
                'is_required' => (bool) $definition['is_required'],
                'position' => $definition['position'] ?? $position + 1,
                'source_definition_json' => $definition['source_definition'] ?? null,
            ], true);

            return $feature;
        }, $revisionSchema, array_keys($revisionSchema)));
        $incomplete = $this->recovery->incompleteContent($revision->content, $schemaFeatures);
        if ($incomplete['missing_cells'] !== [] || $incomplete['negative_anchor_missing']) {
            throw new InvalidArgumentException($recovery !== null
                ? 'Recovery draft preview requires all required cells and a non-zero tier 5 negative anchor.'
                : 'Draft preview requires every model-required benchmark cell and a non-zero tier 5 negative anchor.');
        }

        return $this->provider->evaluateDraft($input, $evaluationDraft, $revision, $baseSet, $program, $requestId, $recovery?->id);
    }
}
