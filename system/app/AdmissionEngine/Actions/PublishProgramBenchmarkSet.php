<?php

namespace App\AdmissionEngine\Actions;

use App\Admission\Support\CommandIdempotency;
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
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final readonly class PublishProgramBenchmarkSet
{
    public function __construct(
        private GovernanceAuthorizer $authorization,
        private CommandIdempotency $idempotency,
        private ImportAdmissionBenchmarkSet $import,
        private KnownHistoricalBenchmarkRecovery $recovery,
        private TagudinUndergraduateCatalog $catalog,
    ) {}

    public function handle(
        User $actor,
        AcademicCycle $cycle,
        array $revisionIdsByProgram,
        string $expectedBaseContentSha256,
        string $reason,
        string $idempotencyKey,
        ?array $recoverySource = null,
        ?array $schemaCutover = null,
    ): AdmissionBenchmarkSet {
        $reason = trim($reason);
        if ($revisionIdsByProgram === [] || $reason === '' || mb_strlen($reason) > 500 || $idempotencyKey === '' || mb_strlen($idempotencyKey) > 116 || ! preg_match('/^[a-f0-9]{64}$/', $expectedBaseContentSha256)) {
            throw new InvalidArgumentException('Publication requires an exact revision manifest, base digest, reason, and idempotency key.');
        }
        if ($recoverySource !== null
            && (array_diff(array_keys($recoverySource), ['source_set_id', 'source_snapshot_digest', 'source_activation_id']) !== []
                || array_diff(['source_set_id', 'source_snapshot_digest', 'source_activation_id'], array_keys($recoverySource)) !== []
                || (int) $recoverySource['source_set_id'] < 1
                || (int) $recoverySource['source_activation_id'] < 1
                || ! preg_match('/^[a-f0-9]{64}$/', (string) $recoverySource['source_snapshot_digest']))) {
            throw new InvalidArgumentException('Recovery publication requires an exact source set, snapshot digest, and activation identity.');
        }
        if ($schemaCutover !== null
            && (array_keys($schemaCutover) !== ['expected_predecessor_schema_digest']
                || ! preg_match('/^[a-f0-9]{64}$/', (string) $schemaCutover['expected_predecessor_schema_digest'])
                || $recoverySource !== null)) {
            throw new InvalidArgumentException('Schema cutover requires only the exact predecessor schema digest and cannot recover history.');
        }
        ksort($revisionIdsByProgram);
        $request = ['cycle_id' => $cycle->id, 'revision_manifest' => $revisionIdsByProgram, 'expected_base_digest' => $expectedBaseContentSha256, 'reason' => $reason];
        if ($recoverySource !== null) {
            $request['recovery_source'] = $recoverySource;
        }
        if ($schemaCutover !== null) {
            $request['schema_cutover'] = $schemaCutover;
        }

        return DB::transaction(function () use ($actor, $cycle, $revisionIdsByProgram, $expectedBaseContentSha256, $reason, $idempotencyKey, $request, $recoverySource, $schemaCutover): AdmissionBenchmarkSet {
            $lockedCycle = AcademicCycle::query()->whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $institution = Institution::query()->whereKey($lockedCycle->institution_id)->firstOrFail();
            if ($recoverySource !== null) {
                return $this->publishHistoricalRecovery($actor, $lockedCycle, $institution, $revisionIdsByProgram, $expectedBaseContentSha256, $reason, $idempotencyKey, $request, $recoverySource);
            }
            $drafts = AdmissionBenchmarkDraft::query()
                ->where('institution_id', $institution->id)
                ->where('academic_cycle_id', $lockedCycle->id)
                ->where('current_version', '>', 0)
                ->orderBy('program_id')
                ->lockForUpdate()
                ->get();
            $changedProgramIds = array_map('intval', array_keys($revisionIdsByProgram));
            $programs = Program::query()
                ->where('institution_id', $institution->id)
                ->whereIn('id', $changedProgramIds)
                ->orderBy('id')
                ->get()
                ->keyBy('id');
            if ($programs->count() !== count(array_unique($changedProgramIds))) {
                throw new InvalidArgumentException('Publication manifest contains a program outside the current institution.');
            }
            foreach ($programs as $program) {
                $this->authorization->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH, $program, $lockedCycle, true);
            }
            $operation = $this->idempotency->claim($actor, 'benchmark.publish', (string) $lockedCycle->id, $request, $idempotencyKey);
            if (isset($operation->payload['benchmark_set_id'])) {
                return AdmissionBenchmarkSet::query()->whereKey($operation->payload['benchmark_set_id'])->firstOrFail();
            }

            $currentBaseId = AdmissionBenchmarkActivation::currentForCycle((int) $lockedCycle->id)?->benchmark_set_id;
            $base = $currentBaseId === null ? null : AdmissionBenchmarkSet::query()->with(['features', 'exemplars.program', 'exemplars.values.feature'])->findOrFail($currentBaseId);
            if ($base === null || ! hash_equals((string) $base->content_sha256, $expectedBaseContentSha256)) {
                throw new RuntimeException('The reviewed benchmark base changed.');
            }
            $this->recovery->assertCutoverSourceIntact($base, $lockedCycle);
            $baseSchemaIsCanonical = BenchmarkInputSchema::isCanonicalFeatureSchema(BenchmarkInputSchema::schemaForSet($base));
            if ($schemaCutover === null && ! $baseSchemaIsCanonical) {
                throw new RuntimeException('The active benchmark uses historical broad inputs; publish an explicitly reviewed canonical schema cutover.');
            }
            if ($schemaCutover !== null
                && ($baseSchemaIsCanonical
                    || ! hash_equals((string) $base->schema_sha256, $schemaCutover['expected_predecessor_schema_digest']))) {
                throw new RuntimeException('The exact broad predecessor changed or is already cut over.');
            }
            $manifestIds = array_map('intval', array_keys($revisionIdsByProgram));
            sort($manifestIds);
            $historicalRecord = AdmissionBenchmarkRecovery::query()->where('source_benchmark_set_id', $base->id)->where('academic_cycle_id', $lockedCycle->id)->first();
            if ($schemaCutover !== null
                && $manifestIds !== BenchmarkPublicationManifest::retainedProgramIds($base, $this->catalog, $historicalRecord)) {
                throw new RuntimeException('A canonical schema cutover requires the full retained-program revision manifest.');
            }
            $expectedProgramIds = $drafts->pluck('program_id')->map(fn ($id): int => (int) $id)->all();
            if (array_diff($manifestIds, $expectedProgramIds) !== []) {
                throw new RuntimeException('Publication manifest references a program without a current draft.');
            }
            $revisions = [];
            $targetSchema = null;
            $targetSchemaDigest = null;
            foreach ($revisionIdsByProgram as $programId => $revisionId) {
                $draft = $drafts->firstWhere('program_id', (int) $programId);
                if ($draft === null) {
                    throw new RuntimeException('The selected program draft is unavailable.');
                }
                $revision = AdmissionBenchmarkRevision::query()->whereKey($revisionId)->where('draft_id', $draft->id)->where('version', $draft->current_version)->first();
                $revisionSchema = $revision === null ? [] : BenchmarkInputSchema::schemaForDraft($draft, $revision);
                $revisionSchemaDigest = BenchmarkInputSchema::digest($revisionSchema);
                if ($revision === null || (int) $draft->base_benchmark_set_id !== (int) $base->id
                    || ! hash_equals((string) $draft->feature_schema_digest, $revisionSchemaDigest)
                    || ! hash_equals((string) $revision->feature_schema_digest, $revisionSchemaDigest)) {
                    throw new RuntimeException('A selected benchmark revision or its pinned base/schema changed before publication.');
                }
                if ($targetSchemaDigest !== null && ! hash_equals($targetSchemaDigest, $revisionSchemaDigest)) {
                    throw new RuntimeException('All selected program revisions must use the same explicitly reviewed feature schema.');
                }
                $targetSchema = $revisionSchema;
                $targetSchemaDigest = $revisionSchemaDigest;
                $revisions[(int) $programId] = $revision;
            }
            $targetSchema ??= $base->features->map(static fn ($feature): array => [
                'feature_key' => (string) $feature->feature_key,
                'label' => (string) $feature->label,
                'logical_type' => (string) $feature->logical_type,
                'aptitude_area_id' => $feature->aptitude_area_id === null ? null : (int) $feature->aptitude_area_id,
                'is_required' => (bool) $feature->is_required,
                'position' => (int) $feature->position,
                'source_definition' => $feature->source_definition_json,
            ])->all();
            $targetSchemaDigest ??= BenchmarkInputSchema::digest($targetSchema);
            $baseSchema = $base->features->map(static fn ($feature): array => [
                'feature_key' => (string) $feature->feature_key,
                'label' => (string) $feature->label,
                'logical_type' => (string) $feature->logical_type,
                'aptitude_area_id' => $feature->aptitude_area_id === null ? null : (int) $feature->aptitude_area_id,
                'is_required' => (bool) $feature->is_required,
                'position' => (int) $feature->position,
                'source_definition' => $feature->source_definition_json,
            ])->all();
            BenchmarkInputSchema::assertCanonicalFeatureSchema($targetSchema);
            if ($schemaCutover === null
                && (count($targetSchema) < count($baseSchema)
                    || ! hash_equals((string) $base->schema_sha256, BenchmarkInputSchema::digest(array_slice($targetSchema, 0, count($baseSchema)))))) {
                throw new RuntimeException('A schema successor must preserve every frozen predecessor feature and its order.');
            }
            $targetFeatures = collect($targetSchema)->map(static function (array $definition): AdmissionBenchmarkFeature {
                $feature = new AdmissionBenchmarkFeature;
                $feature->setRawAttributes([
                    'feature_key' => $definition['feature_key'],
                    'label' => $definition['label'],
                    'logical_type' => $definition['logical_type'],
                    'position' => $definition['position'] ?? 0,
                    'is_required' => $definition['is_required'],
                    'source_definition_json' => $definition['source_definition'] ?? null,
                ], true);

                return $feature;
            });
            $featureTypes = collect($targetSchema)->pluck('logical_type', 'feature_key')->all();
            foreach ($revisions as $revision) {
                BenchmarkRangeAuthoring::assertConsumable($revision->content, $featureTypes);
                $incomplete = $this->recovery->incompleteContent($revision->content, new Collection($targetFeatures->all()));
                if ($incomplete['missing_cells'] !== [] || $incomplete['negative_anchor_missing']) {
                    $missing = array_map(static fn (array $cell): string => 'tier '.$cell['tier'].' '.$cell['feature_key'], $incomplete['missing_cells']);
                    if ($incomplete['negative_anchor_missing']) {
                        $missing[] = 'tier 5 non-zero negative anchor';
                    }
                    throw new RuntimeException('Reviewed publication is incomplete: '.implode(', ', $missing).'.');
                }
            }
            $retiredCodes = $this->catalog->retiredCodes();
            $retiredPrograms = [];
            $retiredTierCounts = [];
            $featureKeys = array_column($targetSchema, 'feature_key');
            $exemplarsByProgram = [];
            foreach ($base->exemplars as $exemplar) {
                if (in_array($exemplar->program->code, $retiredCodes, true)) {
                    $retiredPrograms[(int) $exemplar->program_id] = [
                        'program_id' => (int) $exemplar->program_id,
                        'code' => $exemplar->program->code,
                        'source_set_id' => (int) $base->id,
                        'source_content_sha256' => (string) $base->content_sha256,
                        'provenance' => $this->catalog->retirementProvenance(),
                    ];
                    $retiredTierCounts[(int) $exemplar->program_id] = ($retiredTierCounts[(int) $exemplar->program_id] ?? 0) + 1;

                    continue;
                }
                $values = array_fill_keys($featureKeys, null);
                foreach ($exemplar->values as $value) {
                    $values[$value->feature->feature_key] = $value->is_missing
                        ? null
                        : ($value->feature->logical_type === 'categorical' ? $value->categorical_value : (float) $value->numeric_value);
                }
                $exemplarsByProgram[$exemplar->program_id][] = [
                    'program_code' => $exemplar->program->code,
                    'program_name' => $exemplar->program->name,
                    'tier_level' => (int) $exemplar->tier_level,
                    'tier_label' => $exemplar->tier_label,
                    'values' => $values,
                ];
            }
            foreach ($retiredTierCounts as $programId => $tierCount) {
                if ($tierCount !== 5) {
                    throw new RuntimeException('A retired source program is not a complete historical five-tier member and cannot be silently removed.');
                }
            }
            foreach ($revisions as $programId => $revision) {
                $program = $programs->get($programId);
                if ($program === null || in_array($program->code, $retiredCodes, true) || ! $program->is_admission_offered) {
                    throw new RuntimeException('A programme excluded from the current offering cannot receive a new benchmark revision.');
                }
                $exemplarsByProgram[$programId] = [];
                foreach (range(1, 5) as $tier) {
                    $cells = $revision->content['tiers'][(string) $tier]['values'];
                    $values = array_fill_keys($featureKeys, null);
                    foreach ($cells as $feature => $value) {
                        $values[$feature] = $value;
                    }
                    $exemplarsByProgram[$programId][] = [
                        'program_code' => $program->code,
                        'program_name' => $program->name,
                        'tier_level' => $tier,
                        'tier_label' => [1 => 'Honors', 2 => 'Regular', 3 => 'Cutoff', 4 => 'Cross-Strand', 5 => 'Negative'][$tier],
                        'values' => $values,
                    ];
                }
            }
            foreach ($exemplarsByProgram as $programRows) {
                foreach ($programRows as $row) {
                    foreach ($targetSchema as $definition) {
                        if (($definition['is_required'] ?? false) && ($row['values'][$definition['feature_key']] ?? null) === null) {
                            throw new RuntimeException('A model-required feature must have reviewed values in every included program profile: '.$definition['feature_key'].'.');
                        }
                    }
                }
            }
            $exemplars = array_merge(...array_values($exemplarsByProgram));
            $publicationManifest = [];
            foreach (array_keys($exemplarsByProgram) as $programId) {
                $revision = $revisions[(int) $programId] ?? null;
                $publicationManifest[(string) $programId] = $revision === null
                    ? ['source_set_id' => $base->id, 'source_content_sha256' => $base->content_sha256]
                    : ['revision_id' => $revision->id, 'revision_version' => $revision->version, 'source_set_id' => $base->id, 'source_content_sha256' => $base->content_sha256];
            }
            if ($retiredPrograms !== []) {
                ksort($retiredPrograms);
                $publicationManifest['retired_programs'] = array_values($retiredPrograms);
            }
            ksort($publicationManifest);
            $published = $this->import->handle($institution, $lockedCycle, BenchmarkExecutionContext::reviewedPublication($actor, $institution, $lockedCycle), [
                'name' => 'Admission Benchmark Set from reviewed revision manifest',
                'change_reason' => $reason,
                'predecessor_id' => $base->id,
                'expected_base_content_sha256' => $base->content_sha256,
                'publication_manifest' => $publicationManifest,
                'programs' => Program::query()
                    ->whereIn('id', array_map('intval', array_keys($exemplarsByProgram)))
                    ->orderBy('id')
                    ->get()
                    ->map(fn (Program $program): array => ['code' => $program->code, 'name' => $program->name])
                    ->all(),
                'synthesized_exemplars' => $exemplars,
                ...(hash_equals((string) $base->schema_sha256, $targetSchemaDigest) ? [] : [
                    'feature_schema' => $targetSchema,
                    'schema_cutover' => $schemaCutover !== null,
                    'expected_predecessor_schema_digest' => $schemaCutover['expected_predecessor_schema_digest'] ?? null,
                ]),
                'expected_schema_digest' => $targetSchemaDigest,
            ], 'publication:'.$idempotencyKey);
            $operation->forceFill(['payload' => [...$operation->payload, 'benchmark_set_id' => $published->id]])->save();

            return $published;
        });
    }

    /**
     * @param  array<string, int>  $revisionIdsByProgram
     * @param  array<string, mixed>  $request
     * @param  array{source_set_id:int,source_snapshot_digest:string,source_activation_id:int}  $recoverySource
     */
    private function publishHistoricalRecovery(
        User $actor,
        AcademicCycle $cycle,
        Institution $institution,
        array $revisionIdsByProgram,
        string $expectedBaseContentSha256,
        string $reason,
        string $idempotencyKey,
        array $request,
        array $recoverySource,
    ): AdmissionBenchmarkSet {
        $recovery = AdmissionBenchmarkRecovery::query()
            ->where('institution_id', $institution->id)
            ->where('academic_cycle_id', $cycle->id)
            ->where('source_benchmark_set_id', $recoverySource['source_set_id'])
            ->lockForUpdate()
            ->first();
        if ($recovery === null
            || ! hash_equals((string) $recovery->source_snapshot_sha256, $recoverySource['source_snapshot_digest'])
            || (int) $recovery->source_activation_id !== $recoverySource['source_activation_id']
            || ! hash_equals((string) $recovery->source_content_sha256, $expectedBaseContentSha256)) {
            throw new RuntimeException('Historical recovery source identity changed after review.');
        }
        $retainedProgramIds = array_map('intval', array_keys($revisionIdsByProgram));
        $programs = Program::query()
            ->where('institution_id', $institution->id)->whereIn('id', $retainedProgramIds)->get();
        if ($programs->count() !== count($retainedProgramIds)) {
            throw new RuntimeException('The historical publication scope is no longer available.');
        }
        foreach ($programs as $program) {
            $this->authorization->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH, $program, $cycle, true);
        }

        $operation = $this->idempotency->claim($actor, 'benchmark.publish', (string) $cycle->id, $request, $idempotencyKey);
        if (isset($operation->payload['benchmark_set_id'])) {
            return AdmissionBenchmarkSet::query()->whereKey($operation->payload['benchmark_set_id'])->firstOrFail();
        }

        throw new InvalidArgumentException('New historical-source publications require the aptitude and GWA schema-cutover review.');
    }
}
