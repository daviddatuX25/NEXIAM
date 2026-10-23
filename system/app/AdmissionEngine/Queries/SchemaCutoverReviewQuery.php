<?php

namespace App\AdmissionEngine\Queries;

use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\AdmissionEngine\Support\BenchmarkPublicationManifest;
use App\AdmissionEngine\Support\BenchmarkRangeAuthoring;
use App\AdmissionEngine\Support\KnownHistoricalBenchmarkRecovery;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Foundation\Support\TagudinUndergraduateCatalog;
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

final readonly class SchemaCutoverReviewQuery
{
    public function __construct(
        private GovernanceAuthorizer $authorization,
        private KnownHistoricalBenchmarkRecovery $recovery,
        private TagudinUndergraduateCatalog $catalog,
    ) {}

    /** @return array<string, mixed> */
    public function handle(User $actor, Program $program, AcademicCycle $cycle): array
    {
        $this->authorization->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH, $program, $cycle, true);
        if ((int) $program->institution_id !== (int) $cycle->institution_id) {
            return ['authorized' => false, 'ready' => false, 'programs' => [], 'revision_ids_by_program' => [], 'retained_program_ids' => []];
        }
        $activation = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
        $base = $activation === null
            ? null
            : AdmissionBenchmarkSet::query()->with(['features', 'exemplars.program'])->whereKey($activation->benchmark_set_id)->first();
        if ($base === null || (int) $base->institution_id !== (int) $program->institution_id) {
            return ['authorized' => true, 'ready' => false, 'programs' => [], 'revision_ids_by_program' => [], 'retained_program_ids' => []];
        }
        $baseSchema = BenchmarkInputSchema::schemaForSet($base);
        if (BenchmarkInputSchema::isCanonicalFeatureSchema($baseSchema)) {
            return ['authorized' => true, 'ready' => false, 'programs' => [], 'revision_ids_by_program' => [], 'retained_program_ids' => []];
        }
        try {
            $this->recovery->assertCutoverSourceIntact($base, $cycle, true);
        } catch (\RuntimeException $failure) {
            return ['authorized' => true, 'ready' => false, 'programs' => [], 'revision_ids_by_program' => [], 'retained_program_ids' => [], 'source_problem' => $failure->getMessage()];
        }
        $record = AdmissionBenchmarkRecovery::query()->where('source_benchmark_set_id', $base->id)->where('academic_cycle_id', $cycle->id)->first();
        $retainedIds = BenchmarkPublicationManifest::retainedProgramIds($base, $this->catalog, $record);
        $programs = Program::query()->where('institution_id', $program->institution_id)->whereIn('id', $retainedIds)->orderBy('id')->get();
        if ($programs->count() !== count($retainedIds)) {
            return ['authorized' => true, 'ready' => false, 'programs' => [], 'revision_ids_by_program' => [], 'retained_program_ids' => []];
        }
        foreach ($programs as $retainedProgram) {
            try {
                $this->authorization->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH, $retainedProgram, $cycle, true);
            } catch (AuthorizationException) {
                return ['authorized' => false, 'ready' => false, 'programs' => [], 'revision_ids_by_program' => [], 'retained_program_ids' => []];
            }
        }

        $drafts = AdmissionBenchmarkDraft::query()
            ->where('institution_id', $program->institution_id)
            ->where('academic_cycle_id', $cycle->id)
            ->whereIn('program_id', $retainedIds)
            ->with('revisions')
            ->get()
            ->keyBy('program_id');
        $canonicalDefinitions = BenchmarkInputSchema::canonicalFeatureDefinitions();
        $canonicalSchema = array_map(static fn (array $definition, int $position): array => [
            'feature_key' => $definition['feature_key'],
            'label' => $definition['label'],
            'logical_type' => $definition['logical_type'],
            'aptitude_area_id' => $definition['aptitude_area_id'],
            'is_required' => $definition['is_required'],
            'position' => $position + 1,
            'source_definition' => $definition['source_definition_json'],
        ], $canonicalDefinitions, array_keys($canonicalDefinitions));
        $features = new EloquentCollection(array_map(static function (array $definition): AdmissionBenchmarkFeature {
            $feature = new AdmissionBenchmarkFeature;
            $feature->setRawAttributes([
                'feature_key' => $definition['feature_key'],
                'logical_type' => $definition['logical_type'],
                'aptitude_area_id' => $definition['aptitude_area_id'],
                'position' => $definition['position'],
                'is_required' => $definition['is_required'],
                'source_definition_json' => $definition['source_definition'],
            ], true);

            return $feature;
        }, $canonicalSchema));
        $programRows = [];
        $manifest = [];
        $allReady = true;
        foreach ($programs as $retainedProgram) {
            $draft = $drafts->get($retainedProgram->id);
            $revision = $draft instanceof AdmissionBenchmarkDraft
                ? $draft->revisions->firstWhere('version', $draft->current_version)
                : null;
            $revisionSchema = $revision instanceof AdmissionBenchmarkRevision
                ? BenchmarkInputSchema::schemaForDraft($draft, $revision)
                : [];
            $schemaMatches = $revision instanceof AdmissionBenchmarkRevision
                && BenchmarkInputSchema::isCanonicalFeatureSchema($revisionSchema)
                && hash_equals(BenchmarkInputSchema::digest($revisionSchema), (string) $revision->feature_schema_digest)
                && hash_equals(BenchmarkInputSchema::digest($revisionSchema), (string) $draft->feature_schema_digest)
                && (int) $draft->base_benchmark_set_id === (int) $base->id;
            $missingCells = [];
            $negativeAnchorMissing = false;
            $authoringValid = false;
            if ($schemaMatches) {
                try {
                    BenchmarkRangeAuthoring::assertConsumable($revision->content, collect($canonicalSchema)->pluck('logical_type', 'feature_key')->all());
                    $incomplete = $this->recovery->incompleteContent($revision->content, $features);
                    $missingCells = array_map(static fn (array $cell): string => 'tier '.$cell['tier'].' '.$cell['feature_key'], $incomplete['missing_cells']);
                    $negativeAnchorMissing = $incomplete['negative_anchor_missing'];
                    $authoringValid = true;
                } catch (\InvalidArgumentException) {
                    $authoringValid = false;
                }
            }
            $ready = $schemaMatches && $authoringValid && $missingCells === [] && ! $negativeAnchorMissing && $retainedProgram->is_admission_offered;
            $allReady = $allReady && $ready;
            $revisionId = $schemaMatches ? (int) $revision->id : null;
            if ($revisionId !== null) {
                $manifest[(string) $retainedProgram->id] = $revisionId;
            }
            $programRows[] = [
                'program_id' => (int) $retainedProgram->id,
                'code' => (string) $retainedProgram->code,
                'draft_version' => (int) ($draft?->current_version ?? 0),
                'revision_id' => $revisionId,
                'cutover_required' => ! $schemaMatches,
                'ready' => $ready,
                'missing_cells' => $missingCells,
                'negative_anchor_missing' => $negativeAnchorMissing,
            ];
        }

        return [
            'authorized' => true,
            'ready' => $allReady && count($manifest) === count($retainedIds),
            'source_set_id' => (int) $base->id,
            'source_content_sha256' => (string) $base->content_sha256,
            'expected_predecessor_schema_digest' => (string) $base->schema_sha256,
            'retained_program_ids' => $retainedIds,
            'revision_ids_by_program' => $manifest,
            'programs' => $programRows,
        ];
    }
}
