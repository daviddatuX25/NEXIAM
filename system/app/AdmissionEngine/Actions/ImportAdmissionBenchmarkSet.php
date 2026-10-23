<?php

namespace App\AdmissionEngine\Actions;

use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\AdmissionEngine\Support\BenchmarkPublicationManifest;
use App\AdmissionEngine\Support\BenchmarkRangeAuthoring;
use App\AdmissionEngine\Support\KnownHistoricalBenchmarkRecovery;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Foundation\Support\TagudinUndergraduateCatalog;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkDraft;
use App\Models\Admission\AdmissionBenchmarkExemplar;
use App\Models\Admission\AdmissionBenchmarkExemplarValue;
use App\Models\Admission\AdmissionBenchmarkFeature;
use App\Models\Admission\AdmissionBenchmarkRecovery;
use App\Models\Admission\AdmissionBenchmarkRevision;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final readonly class ImportAdmissionBenchmarkSet
{
    public function __construct(
        private RecordAuditEvent $recordAuditEvent,
        private GovernanceAuthorizer $authorization,
        private KnownHistoricalBenchmarkRecovery $recovery,
        private TagudinUndergraduateCatalog $catalog,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handle(Institution $institution, AcademicCycle $cycle, BenchmarkExecutionContext $context, array $payload, string $idempotencyKey): AdmissionBenchmarkSet
    {
        $context->assertInstitution($institution);
        if ((int) $cycle->institution_id !== (int) $institution->id || $idempotencyKey === '' || mb_strlen($idempotencyKey) > 128) {
            throw new InvalidArgumentException('Benchmark import requires a matching academic cycle and idempotency key.');
        }
        $reason = trim((string) ($payload['change_reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('Benchmark import requires a non-empty attributable change reason.');
        }
        $payload['change_reason'] = $reason;
        $hasPublicationMetadata = array_key_exists('predecessor_id', $payload)
            || array_key_exists('publication_manifest', $payload)
            || array_key_exists('expected_base_content_sha256', $payload)
            || array_key_exists('expected_schema_digest', $payload)
            || array_key_exists('recovery', $payload)
            || array_key_exists('feature_schema', $payload)
            || array_key_exists('schema_cutover', $payload)
            || array_key_exists('expected_predecessor_schema_digest', $payload);
        if ($hasPublicationMetadata !== $context->isReviewedPublication($institution, $cycle)) {
            throw new InvalidArgumentException('Publication predecessor and manifest metadata are server-owned by the reviewed publication Action.');
        }
        $programDefinitions = $payload['programs'] ?? [];
        $rawRanges = $payload['raw_ranges'] ?? null;
        $sourceExemplars = $payload['synthesized_exemplars'] ?? null;
        if (! is_array($programDefinitions)) {
            throw new InvalidArgumentException('Benchmark programs must be an array.');
        }
        if (is_array($sourceExemplars)) {
            foreach ($sourceExemplars as $source) {
                if (is_array($source) && isset($source['program_code'], $source['program_name'])) {
                    $programDefinitions[] = ['code' => $source['program_code'], 'name' => $source['program_name']];
                }
            }
        }
        $exemplars = is_array($rawRanges)
            ? $this->synthesizeRawRanges($rawRanges)
            : (is_array($sourceExemplars) ? $this->normalizeExemplars($sourceExemplars) : null);
        if (! is_array($exemplars) || $exemplars === []) {
            throw new InvalidArgumentException('Benchmark payload must include raw_ranges or synthesized_exemplars.');
        }
        $featureDefinitions = isset($payload['feature_schema'])
            ? $this->reviewedFeatureDefinitions($institution, $cycle, $payload)
            : $this->featureDefinitions();
        $schema = array_map(static function (array $feature): array {
            $definition = [$feature['feature_key'], $feature['label'], $feature['logical_type'], $feature['aptitude_area_id'], $feature['is_required']];
            if (is_array($feature['source_definition_json'] ?? null)) {
                $definition[] = $feature['source_definition_json'];
            }

            return $definition;
        }, $featureDefinitions);
        $exemplars = $this->validateExemplars($exemplars, $featureDefinitions);

        $canonicalPayload = $this->canonicalizePayload($payload);
        $requestDigest = hash('sha256', json_encode(['institution_id' => $institution->id, 'cycle_id' => $cycle->id, 'payload' => $canonicalPayload], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

        return DB::transaction(function () use ($institution, $cycle, $context, $payload, $idempotencyKey, $requestDigest, $programDefinitions, $rawRanges, $exemplars, $featureDefinitions, $schema): AdmissionBenchmarkSet {
            Institution::query()->whereKey($institution->id)->lockForUpdate()->firstOrFail();
            $freshCycle = AcademicCycle::query()->whereKey($cycle->id)->firstOrFail();
            if ((int) $freshCycle->institution_id !== (int) $institution->id) {
                throw new InvalidArgumentException('Benchmark cycle institution changed.');
            }
            $programsByCode = [];
            $codes = array_values(array_unique(array_map(static fn (array $exemplar): string => (string) ($exemplar['program_code'] ?? ''), $exemplars)));
            foreach ($codes as $code) {
                if ($code === '') {
                    throw new InvalidArgumentException('Every benchmark exemplar must reference a program.');
                }
                $program = Program::query()->where('institution_id', $institution->id)->where('code', $code)->first();
                if ($program === null || ! $program->is_active) {
                    throw new InvalidArgumentException('Benchmark imports may reference only existing active programs in the institution.');
                }
                $manifestEntry = $payload['publication_manifest'][(string) $program->id] ?? null;
                $isChangedPublicationProgram = $context->isReviewedPublication($institution, $freshCycle)
                    && is_array($manifestEntry)
                    && array_key_exists('revision_id', $manifestEntry);
                if (! $context->isReviewedPublication($institution, $freshCycle) && ! $program->is_admission_offered) {
                    throw new InvalidArgumentException('New benchmark imports may reference only currently offered programs.');
                }
                if (! $context->trusted && ($isChangedPublicationProgram || ! $context->isReviewedPublication($institution, $freshCycle))) {
                    $actor = $context->actor ?? throw new InvalidArgumentException('Human benchmark publication requires an authenticated actor.');
                    $this->authorization->authorize($actor, CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH, $program, $freshCycle, true);
                }
                $programsByCode[$code] = $program;
            }
            foreach ($programDefinitions as $definition) {
                if (is_array($definition) && isset($definition['code']) && ! isset($programsByCode[(string) $definition['code']])) {
                    throw new InvalidArgumentException('Benchmark import includes a program without exemplar rows.');
                }
            }
            $storedKey = ($context->actor?->id ?? 'trusted-'.$context->purpose).':'.$institution->id.':'.$idempotencyKey;
            if (($existing = AdmissionBenchmarkSet::query()->where('idempotency_key', $storedKey)->first()) !== null) {
                if (! hash_equals((string) $existing->import_payload_sha256, $requestDigest)) {
                    throw new RuntimeException('Benchmark import idempotency key was reused with different content.');
                }

                return $existing;
            }
            if ($context->isReviewedPublication($institution, $freshCycle)) {
                if (isset($payload['recovery'])) {
                    $this->validateReviewedRecoveryPublication($institution, $freshCycle, $payload, $programsByCode, $exemplars, $schema);
                } else {
                    $this->validateReviewedPublication($institution, $freshCycle, $payload, $programsByCode, $exemplars, $schema, $featureDefinitions);
                }
            }
            if (isset($payload['expected_schema_digest']) && ! hash_equals((string) $payload['expected_schema_digest'], hash('sha256', json_encode($schema, JSON_THROW_ON_ERROR)))) {
                throw new RuntimeException('Benchmark feature schema changed after review.');
            }
            $latest = (int) (AdmissionBenchmarkSet::query()->where('institution_id', $institution->id)->orderByDesc('version')->lockForUpdate()->value('version') ?? 0);
            $canonical = json_encode(['features' => $schema, 'exemplars' => $exemplars], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
            $set = AdmissionBenchmarkSet::query()->create([
                'institution_id' => $institution->id,
                'version' => $latest + 1,
                'status' => 'draft',
                'name' => (string) ($payload['name'] ?? 'Admission Benchmark Set'),
                'change_reason' => $payload['change_reason'] ?? null,
                'schema_sha256' => hash('sha256', json_encode($schema, JSON_THROW_ON_ERROR)),
                'content_sha256' => hash('sha256', $canonical),
                'predecessor_id' => $payload['predecessor_id'] ?? null,
                'publication_manifest_json' => $payload['publication_manifest'] ?? null,
                'raw_ranges_json' => $rawRanges,
                'created_by_user_id' => $context->actor?->id,
                'published_by_user_id' => $context->actor?->id,
                'published_at' => null,
                'idempotency_key' => $storedKey,
                'import_payload_sha256' => $requestDigest,
            ]);
            $featureModels = [];
            foreach ($featureDefinitions as $position => $definition) {
                $featureModels[$definition['feature_key']] = $set->features()->create($definition + ['position' => $position + 1]);
            }
            foreach ($exemplars as $source) {
                if (! is_array($source) || ! isset($source['program_code'])) {
                    continue;
                }
                $program = $programsByCode[(string) $source['program_code']] ?? null;
                if ($program === null) {
                    continue;
                }
                $tier = (int) ($source['tier_level'] ?? 0);
                if ($tier < 1 || $tier > 5) {
                    throw new InvalidArgumentException('Benchmark exemplar tier_level must be between 1 and 5.');
                }
                $values = is_array($source['values'] ?? null) ? $source['values'] : [];
                $exemplar = $set->exemplars()->create([
                    'program_id' => $program->id,
                    'tier_level' => $tier,
                    'tier_label' => (string) ($source['tier_label'] ?? $this->tierLabel($tier)),
                    'target_class' => $tier === 5 ? 0 : 1,
                ]);
                foreach ($featureModels as $key => $feature) {
                    $value = $values[$key] ?? null;
                    $missing = $value === null || $value === '';
                    AdmissionBenchmarkExemplarValue::query()->create([
                        'exemplar_id' => $exemplar->id,
                        'benchmark_feature_id' => $feature->id,
                        'is_missing' => $missing,
                        'numeric_value' => ! $missing && $feature->logical_type !== 'categorical' && is_numeric($value) ? (float) $value : null,
                        'categorical_value' => ! $missing && $feature->logical_type === 'categorical' ? mb_substr((string) $value, 0, 64) : null,
                    ]);
                }
            }
            $set = $set->load(['features', 'exemplars.program', 'exemplars.values.feature']);
            $set->forceFill([
                'status' => 'published',
                'content_sha256' => $set->computedContentDigest(),
                'published_at' => now(),
            ])->save();
            $this->recordAuditEvent->handle(isset($payload['publication_manifest']) ? 'admission_benchmark.published' : 'admission_benchmark.imported', [
                'benchmark_set_id' => $set->id,
                'institution_id' => $institution->id,
                'version' => $set->version,
                'predecessor_id' => $set->predecessor_id,
                'content_sha256' => $set->content_sha256,
                'schema_sha256' => $set->schema_sha256,
                'publication_manifest' => $set->publication_manifest_json,
                'change_reason' => $set->change_reason,
                'idempotency_key' => $set->idempotency_key,
                'program_count' => count($programsByCode),
            ], (string) ($context->actor?->id ?? 'trusted:'.$context->purpose));

            return $set->load(['features', 'exemplars.values']);
        });
    }

    /** @return list<array{feature_key:string,label:string,logical_type:string,aptitude_area_id:?int,is_required:bool,source_definition_json:?array<string,mixed>}> */
    private function featureDefinitions(): array
    {
        return BenchmarkInputSchema::canonicalFeatureDefinitions();
    }

    /** @param array<string,mixed> $payload @return list<array<string,mixed>> */
    private function reviewedFeatureDefinitions(Institution $institution, AcademicCycle $cycle, array $payload): array
    {
        if (! is_array($payload['feature_schema'] ?? null)
            || ! array_is_list($payload['feature_schema'])
            || ($payload['schema_cutover'] ?? false) !== true
            || (int) ($payload['predecessor_id'] ?? 0) < 1
            || (int) $cycle->institution_id !== (int) $institution->id) {
            throw new InvalidArgumentException('Reviewed schema changes require an explicit canonical cutover and exact predecessor.');
        }

        $base = AdmissionBenchmarkSet::query()
            ->with('features')
            ->where('institution_id', $institution->id)
            ->whereKey($payload['predecessor_id'])
            ->where('status', 'published')
            ->firstOrFail();
        $this->recovery->assertCutoverSourceIntact($base, $cycle);
        if (! hash_equals((string) $base->schema_sha256, (string) ($payload['expected_predecessor_schema_digest'] ?? ''))
            || BenchmarkInputSchema::isCanonicalFeatureSchema(BenchmarkInputSchema::schemaForSet($base))) {
            throw new RuntimeException('The exact noncanonical predecessor changed or is already cut over.');
        }

        BenchmarkInputSchema::assertCanonicalFeatureSchema($payload['feature_schema']);
        $definitions = BenchmarkInputSchema::canonicalFeatureDefinitions();
        $candidateDefinitions = array_map(static fn (array $definition, int $position): array => [
            'feature_key' => $definition['feature_key'],
            'label' => $definition['label'],
            'logical_type' => $definition['logical_type'],
            'aptitude_area_id' => $definition['aptitude_area_id'],
            'is_required' => $definition['is_required'],
            'position' => $position + 1,
            'source_definition' => $definition['source_definition_json'],
        ], $definitions, array_keys($definitions));
        if (json_encode($candidateDefinitions, JSON_THROW_ON_ERROR) !== json_encode($payload['feature_schema'], JSON_THROW_ON_ERROR)) {
            throw new InvalidArgumentException('Cutover target must equal the server-owned canonical aptitude-plus-GWA schema.');
        }

        return $definitions;
    }

    /** @param array<string, mixed> $rawRanges
     * @return list<array<string, mixed>>
     */
    private function synthesizeRawRanges(array $rawRanges): array
    {
        $result = [];
        $scoreKeys = [
            'gwa' => 'gwa',
            'LOGIC' => 'gen',
            'PROFILE_VERBAL' => 'verb',
            'PROFILE_QUANT' => 'num',
            'SPATIAL' => 'spat',
            'MEMORY' => 'perc',
            'LANGUAGE' => 'man',
        ];
        foreach ($rawRanges as $programCode => $ranges) {
            if (! is_array($ranges)) {
                continue;
            }
            if (array_intersect(array_keys($ranges), ['subjects', 'strands', 'riasec', 'sub_math', 'sub_sci', 'sub_eng']) !== []) {
                throw new InvalidArgumentException('Subject grades, strand and career interests are not model-range inputs.');
            }
            for ($tier = 1; $tier <= 5; $tier++) {
                $group = match ($tier) {
                    1, 2 => 'honors',
                    3 => 'regular',
                    default => 'cutoff',
                };
                $values = [];
                foreach ($scoreKeys as $featureKey => $rangeKey) {
                    $range = $ranges[$group][$rangeKey] ?? null;
                    if ($range === null) {
                        continue;
                    }
                    [$minimum, $maximum] = $this->bounds($range);
                    $values[$featureKey] = match ($tier) {
                        1 => $maximum,
                        2 => $minimum,
                        3 => ($minimum + $maximum) / 2,
                        4 => $minimum,
                        default => max(0, $minimum - max(2, ($maximum - $minimum) * 0.2)),
                    };
                }
                $result[] = [
                    'program_code' => (string) $programCode,
                    'tier_level' => $tier,
                    'tier_label' => $this->tierLabel($tier),
                    'target_class' => $tier === 5 ? 0 : 1,
                    'values' => $values,
                ];
            }
        }

        return $result;
    }

    /** @param list<array<string, mixed>> $exemplars
     * @return list<array<string, mixed>>
     */
    private function normalizeExemplars(array $exemplars): array
    {
        $featureAliases = [
            'gen_ability' => 'LOGIC',
            'verbal_apt' => 'PROFILE_VERBAL',
            'num_apt' => 'PROFILE_QUANT',
            'spatial_apt' => 'SPATIAL',
            'percept_apt' => 'MEMORY',
            'manual_dext' => 'LANGUAGE',
        ];
        $result = [];
        foreach ($exemplars as $source) {
            if (! is_array($source)) {
                continue;
            }
            $values = is_array($source['values'] ?? null) ? $source['values'] : $source;
            foreach ($featureAliases as $sourceKey => $featureKey) {
                if (array_key_exists($sourceKey, $values)) {
                    $values[$featureKey] = $values[$sourceKey];
                }
            }
            $label = mb_strtolower((string) ($source['tier_label'] ?? ''));
            $tier = match (true) {
                isset($source['tier_level']) && is_numeric($source['tier_level']) => (int) $source['tier_level'],
                (int) ($source['target_class'] ?? $source['target'] ?? 1) === 0 => 5,
                str_contains($label, 'honors') || str_contains($label, 'high') || str_contains($label, 'priority') => 1,
                str_contains($label, 'regular') || str_contains($label, 'solid') => 2,
                str_contains($label, 'cutoff') || str_contains($label, 'threshold') || str_contains($label, 'minimum') => 3,
                str_contains($label, 'cross') || str_contains($label, 'non-aligned') || str_contains($label, 'non-traditional') => 4,
                default => 0,
            };
            $result[] = [
                'program_code' => (string) ($source['program_code'] ?? ''),
                'program_name' => (string) ($source['program_name'] ?? $source['program_code'] ?? ''),
                'tier_level' => $tier,
                'tier_label' => (string) ($source['tier_label'] ?? $this->tierLabel($tier)),
                'target_class' => $tier === 5 ? 0 : 1,
                'values' => $values,
            ];
        }

        return $result;
    }

    /** @param mixed $value @return mixed */
    private function canonicalizePayload(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $nested) {
            $value[$key] = $this->canonicalizePayload($nested);
        }
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }

    /**
     * @param  array<string, Program>  $programsByCode
     * @param  list<array<string, mixed>>  $exemplars
     * @param  list<array<string, mixed>>  $schema
     * @param  list<array<string, mixed>>  $featureDefinitions
     */
    private function validateReviewedPublication(Institution $institution, AcademicCycle $cycle, array $payload, array $programsByCode, array $exemplars, array $schema, array $featureDefinitions): void
    {
        if (! isset($payload['predecessor_id'], $payload['publication_manifest']) || ! is_array($payload['publication_manifest'])) {
            throw new InvalidArgumentException('Reviewed publication requires a full server-owned revision manifest and predecessor.');
        }
        $base = AdmissionBenchmarkSet::query()->with('features')->whereKey($payload['predecessor_id'])
            ->where('institution_id', $institution->id)
            ->where('status', 'published')
            ->first();
        if ($base === null
            || (int) AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)?->benchmark_set_id !== (int) $base->id
            || ! hash_equals((string) $base->content_sha256, (string) ($payload['expected_base_content_sha256'] ?? ''))) {
            throw new RuntimeException('Reviewed publication predecessor is not the current base.');
        }
        $this->recovery->assertCutoverSourceIntact($base, $cycle);
        $targetSchemaDigest = hash('sha256', json_encode($schema, JSON_THROW_ON_ERROR));
        $targetFeatures = collect($featureDefinitions)->map(static function (array $definition): AdmissionBenchmarkFeature {
            $feature = new AdmissionBenchmarkFeature;
            $feature->setRawAttributes([
                'feature_key' => $definition['feature_key'],
                'label' => $definition['label'],
                'logical_type' => $definition['logical_type'],
                'is_required' => $definition['is_required'],
                'source_definition_json' => is_array($definition['source_definition_json'] ?? null)
                    ? json_encode($definition['source_definition_json'], JSON_THROW_ON_ERROR)
                    : null,
            ], true);

            return $feature;
        });

        $manifest = $payload['publication_manifest'];
        $retiredPrograms = $manifest['retired_programs'] ?? [];
        unset($manifest['retired_programs']);
        if (! is_array($retiredPrograms) || ! array_is_list($retiredPrograms)) {
            throw new InvalidArgumentException('Retired program provenance must be a list in the reviewed publication manifest.');
        }
        $expectedProgramIds = array_map(static fn (Program $program): int => (int) $program->id, array_values($programsByCode));
        $manifestProgramIds = array_map('intval', array_keys($manifest));
        sort($expectedProgramIds);
        sort($manifestProgramIds);
        if ($manifestProgramIds !== $expectedProgramIds) {
            throw new InvalidArgumentException('Reviewed publication manifest must cover each included program exactly once.');
        }
        if (($payload['schema_cutover'] ?? false) === true) {
            $record = AdmissionBenchmarkRecovery::query()->where('source_benchmark_set_id', $base->id)->where('academic_cycle_id', $cycle->id)->first();
            if ($manifestProgramIds !== BenchmarkPublicationManifest::retainedProgramIds($base, $this->catalog, $record)) {
                throw new InvalidArgumentException('A schema cutover must include every retained program exactly once.');
            }
        }
        $this->assertRetiredManifest($institution, $base, $retiredPrograms);
        $exemplarsByProgram = [];
        foreach ($exemplars as $exemplar) {
            $code = (string) $exemplar['program_code'];
            $exemplarsByProgram[(int) $programsByCode[$code]->id][(int) $exemplar['tier_level']] = $exemplar['values'];
        }

        foreach ($manifest as $programId => $entry) {
            if (! is_array($entry) || (int) ($entry['source_set_id'] ?? 0) !== (int) $base->id
                || ! hash_equals((string) $base->content_sha256, (string) ($entry['source_content_sha256'] ?? ''))) {
                throw new InvalidArgumentException('Reviewed publication manifest contains an unverified predecessor reference.');
            }
            $draft = AdmissionBenchmarkDraft::query()
                ->where('institution_id', $institution->id)
                ->where('program_id', (int) $programId)
                ->where('academic_cycle_id', $cycle->id)
                ->first();
            if (array_key_exists('revision_id', $entry)) {
                if (array_diff(array_keys($entry), ['revision_id', 'revision_version', 'source_set_id', 'source_content_sha256']) !== [] || $draft === null
                    || (int) $draft->base_benchmark_set_id !== (int) $base->id
                    || ! hash_equals((string) $draft->feature_schema_digest, $targetSchemaDigest)) {
                    throw new InvalidArgumentException('Reviewed publication revision is outside the pinned program/cycle/base/schema scope.');
                }
                $revision = AdmissionBenchmarkRevision::query()
                    ->whereKey($entry['revision_id'])
                    ->where('draft_id', $draft->id)
                    ->where('version', $entry['revision_version'])
                    ->where('version', $draft->current_version)
                    ->first();
                if ($revision === null) {
                    throw new RuntimeException('Reviewed publication revision is no longer current.');
                }
                BenchmarkRangeAuthoring::assertConsumable($revision->content, collect($featureDefinitions)->pluck('logical_type', 'feature_key')->all());
                $incomplete = $this->recovery->incompleteContent($revision->content, new Collection($targetFeatures->all()));
                if ($incomplete['missing_cells'] !== [] || $incomplete['negative_anchor_missing']) {
                    $missing = array_map(static fn (array $cell): string => 'tier '.$cell['tier'].' '.$cell['feature_key'], $incomplete['missing_cells']);
                    if ($incomplete['negative_anchor_missing']) {
                        $missing[] = 'tier 5 non-zero negative anchor';
                    }
                    throw new InvalidArgumentException('Reviewed publication revision is incomplete: '.implode(', ', $missing).'.');
                }
                foreach (range(1, 5) as $tier) {
                    $revisionCells = $this->canonicalPublicationCells($revision->content['tiers'][(string) $tier]['values'] ?? null, $featureDefinitions);
                    $publishedCells = $this->canonicalPublicationCells($exemplarsByProgram[(int) $programId][$tier] ?? null, $featureDefinitions);
                    if ($revisionCells !== $publishedCells) {
                        throw new RuntimeException('Published cells do not match the exact reviewed revision content.');
                    }
                }
            } else {
                if (array_keys($entry) !== ['source_set_id', 'source_content_sha256']) {
                    throw new InvalidArgumentException('Unchanged base-program manifest entry is invalid.');
                }
                $baseExemplars = AdmissionBenchmarkExemplar::query()
                    ->where('benchmark_set_id', $base->id)
                    ->where('program_id', (int) $programId)
                    ->with('values.feature')
                    ->orderBy('tier_level')
                    ->get();
                if ($baseExemplars->count() !== 5) {
                    throw new InvalidArgumentException('An unchanged program must exist as a complete five-tier member of the predecessor set.');
                }
                foreach ($baseExemplars as $baseExemplar) {
                    $baseValues = array_fill_keys(array_column($featureDefinitions, 'feature_key'), null);
                    foreach ($baseExemplar->values as $value) {
                        $baseValues[$value->feature->feature_key] = $value->is_missing
                            ? null
                            : ($value->feature->logical_type === 'categorical' ? $value->categorical_value : (float) $value->numeric_value);
                    }
                    ksort($baseValues);
                    if ($this->canonicalPublicationCells($baseValues, $featureDefinitions) !== $this->canonicalPublicationCells($exemplarsByProgram[(int) $programId][(int) $baseExemplar->tier_level] ?? null, $featureDefinitions)) {
                        throw new RuntimeException('Unchanged program content does not match its pinned predecessor exemplar.');
                    }
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $retiredPrograms
     */
    private function assertRetiredManifest(Institution $institution, AdmissionBenchmarkSet $base, array $retiredPrograms): void
    {
        $retiredCodes = $this->catalog->retiredCodes();
        $sourcePrograms = AdmissionBenchmarkExemplar::query()
            ->where('benchmark_set_id', $base->id)
            ->with('program')
            ->get()
            ->groupBy('program_id');
        $expectedIds = $sourcePrograms
            ->filter(static fn ($exemplars): bool => in_array($exemplars->first()?->program?->code, $retiredCodes, true))
            ->keys()
            ->map(static fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();
        $actualIds = array_map(static fn (array $entry): int => (int) ($entry['program_id'] ?? 0), $retiredPrograms);
        sort($actualIds);
        if ($actualIds !== $expectedIds) {
            throw new InvalidArgumentException('Reviewed publication must explicitly account for every retired source program and no other program.');
        }

        $provenance = $this->catalog->retirementProvenance();
        foreach ($retiredPrograms as $entry) {
            if (array_diff(array_keys($entry), ['program_id', 'code', 'source_set_id', 'source_content_sha256', 'provenance']) !== []
                || array_diff(['program_id', 'code', 'source_set_id', 'source_content_sha256', 'provenance'], array_keys($entry)) !== []) {
                throw new InvalidArgumentException('Retired program provenance entry has an unsupported shape.');
            }
            $programId = (int) $entry['program_id'];
            $sourceRows = $sourcePrograms->get($programId);
            $program = Program::query()->whereKey($programId)->where('institution_id', $institution->id)->first();
            if ($sourceRows === null
                || $sourceRows->count() !== 5
                || $program === null
                || ! in_array($program->code, $retiredCodes, true)
                || $program->is_admission_offered
                || $entry['code'] !== $program->code
                || (int) $entry['source_set_id'] !== (int) $base->id
                || ! hash_equals((string) $base->content_sha256, (string) $entry['source_content_sha256'])
                || $entry['provenance'] !== $provenance) {
                throw new InvalidArgumentException('Retired program provenance does not match the intact reviewed source and approved catalog disposition.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, Program>  $programsByCode
     * @param  list<array<string, mixed>>  $exemplars
     * @param  list<array<string, mixed>>  $schema
     */
    private function validateReviewedRecoveryPublication(
        Institution $institution,
        AcademicCycle $cycle,
        array $payload,
        array $programsByCode,
        array $exemplars,
        array $schema,
    ): void {
        $metadata = $payload['recovery'];
        if (! is_array($metadata)
            || array_diff(array_keys($metadata), ['recovery_id', 'source_set_id', 'source_snapshot_digest', 'source_activation_id']) !== []
            || array_diff(['recovery_id', 'source_set_id', 'source_snapshot_digest', 'source_activation_id'], array_keys($metadata)) !== []
            || (int) ($payload['predecessor_id'] ?? 0) !== (int) $metadata['source_set_id']) {
            throw new InvalidArgumentException('Recovery publication metadata is incomplete or invalid.');
        }

        $recovery = AdmissionBenchmarkRecovery::query()
            ->whereKey($metadata['recovery_id'])
            ->where('institution_id', $institution->id)
            ->where('academic_cycle_id', $cycle->id)
            ->where('source_benchmark_set_id', $metadata['source_set_id'])
            ->lockForUpdate()
            ->first();
        if ($recovery === null
            || ! hash_equals((string) $recovery->source_snapshot_sha256, (string) $metadata['source_snapshot_digest'])
            || (int) $recovery->source_activation_id !== (int) $metadata['source_activation_id']
            || ! hash_equals((string) $recovery->source_content_sha256, (string) ($payload['expected_base_content_sha256'] ?? ''))) {
            throw new RuntimeException('Recovery publication source identity changed after review.');
        }
        $source = $this->recovery->assertUnchanged($recovery, $cycle);
        $schemaDigest = hash('sha256', json_encode($schema, JSON_THROW_ON_ERROR));
        if (! hash_equals((string) $recovery->current_schema_sha256, $schemaDigest)
            || ! hash_equals((string) $recovery->current_schema_sha256, $source->computedSchemaDigest())) {
            throw new RuntimeException('Recovery publication feature schema changed after review.');
        }

        $reviewManifest = $this->recovery->reviewManifest($recovery, $source);
        $expectedProgramIds = $reviewManifest['retained_program_ids'];
        $manifest = $payload['publication_manifest'] ?? null;
        if (! is_array($manifest) || ! isset($payload['predecessor_id'])
            || ! hash_equals((string) $source->content_sha256, (string) ($payload['expected_base_content_sha256'] ?? ''))) {
            throw new InvalidArgumentException('Recovery publication requires its exact historical source and full manifest.');
        }
        $retiredPrograms = $manifest['retired_programs'] ?? [];
        unset($manifest['retired_programs']);
        if (! is_array($retiredPrograms) || ! array_is_list($retiredPrograms)) {
            throw new InvalidArgumentException('Historical retirement provenance must be an explicit list.');
        }
        $manifestProgramIds = array_map('intval', array_keys($manifest));
        sort($manifestProgramIds);
        sort($expectedProgramIds);
        $payloadProgramIds = array_map(static fn (Program $program): int => (int) $program->id, array_values($programsByCode));
        sort($payloadProgramIds);
        if ($manifestProgramIds !== $expectedProgramIds || $payloadProgramIds !== $expectedProgramIds) {
            throw new InvalidArgumentException('Recovery publication must exactly cover all retained source and explicitly reviewed offered programs.');
        }
        $this->assertRetiredManifest($institution, $source, $retiredPrograms);
        $exemplarsByProgram = [];
        foreach ($exemplars as $exemplar) {
            $program = $programsByCode[(string) ($exemplar['program_code'] ?? '')] ?? null;
            if (! $program instanceof Program) {
                throw new InvalidArgumentException('Recovery publication contains an unknown retained program.');
            }
            $exemplarsByProgram[(int) $program->id][(int) ($exemplar['tier_level'] ?? 0)] = $exemplar;
        }

        $drafts = AdmissionBenchmarkDraft::query()
            ->where('recovery_id', $recovery->id)
            ->where('institution_id', $institution->id)
            ->where('academic_cycle_id', $cycle->id)
            ->whereIn('program_id', $expectedProgramIds)
            ->where('current_version', '>', 0)
            ->lockForUpdate()
            ->get()
            ->keyBy('program_id');
        if ($drafts->keys()->map(static fn ($id): int => (int) $id)->sort()->values()->all() !== $expectedProgramIds) {
            throw new InvalidArgumentException('Every retained source or explicitly reviewed target program requires a current draft revision.');
        }
        $allowedManifestFields = [
            'revision_id',
            'revision_version',
            'source_set_id',
            'source_content_sha256',
            'source_snapshot_digest',
            'source_activation_id',
            'recovery_id',
        ];
        foreach ($expectedProgramIds as $programId) {
            $entry = $manifest[(string) $programId] ?? $manifest[$programId] ?? null;
            $draft = $drafts->get($programId);
            $program = Program::query()->where('institution_id', $institution->id)->find($programId);
            if (! is_array($entry)
                || array_diff(array_keys($entry), $allowedManifestFields) !== []
                || array_diff($allowedManifestFields, array_keys($entry)) !== []
                || $draft === null
                || $program === null
                || (int) $draft->base_benchmark_set_id !== (int) $source->id
                || (int) $draft->recovery_id !== (int) $recovery->id
                || ! hash_equals((string) $draft->feature_schema_digest, (string) $recovery->current_schema_sha256)
                || (int) ($entry['source_set_id'] ?? 0) !== (int) $source->id
                || (int) ($entry['source_activation_id'] ?? 0) !== (int) $recovery->source_activation_id
                || (int) ($entry['recovery_id'] ?? 0) !== (int) $recovery->id
                || ! hash_equals((string) $source->content_sha256, (string) ($entry['source_content_sha256'] ?? ''))
                || ! hash_equals((string) $recovery->source_snapshot_sha256, (string) ($entry['source_snapshot_digest'] ?? ''))) {
                throw new InvalidArgumentException('Recovery publication manifest contains an unverified source or draft reference.');
            }
            $revision = AdmissionBenchmarkRevision::query()
                ->whereKey($entry['revision_id'])
                ->where('draft_id', $draft->id)
                ->where('version', $entry['revision_version'])
                ->where('version', $draft->current_version)
                ->first();
            if ($revision === null
                || (int) $revision->base_benchmark_set_id !== (int) $source->id
                || ! hash_equals((string) $revision->feature_schema_digest, (string) $recovery->current_schema_sha256)) {
                throw new RuntimeException('A recovery revision changed or is no longer the exact current reviewed version.');
            }
            $incomplete = $this->recovery->incompleteContent($revision->content, $source->features);
            if ($incomplete['missing_cells'] !== [] || $incomplete['negative_anchor_missing']) {
                $missing = array_map(static fn (array $cell): string => 'tier '.$cell['tier'].' '.$cell['feature_key'], $incomplete['missing_cells']);
                if ($incomplete['negative_anchor_missing']) {
                    $missing[] = 'tier 5 non-zero negative anchor';
                }
                throw new InvalidArgumentException('Recovery publication is incomplete for '.$program->code.': '.implode(', ', $missing).'.');
            }
            $tiers = $exemplarsByProgram[$programId] ?? [];
            if (array_keys($tiers) !== [1, 2, 3, 4, 5]) {
                ksort($tiers);
            }
            if (array_keys($tiers) !== [1, 2, 3, 4, 5]) {
                throw new InvalidArgumentException('Recovery publication must include exactly five current-tier rows for '.$program->code.'.');
            }
            foreach (range(1, 5) as $tier) {
                $exemplar = $tiers[$tier];
                $tierLabels = [1 => 'Honors', 2 => 'Regular', 3 => 'Cutoff', 4 => 'Cross-Strand', 5 => 'Negative'];
                $revisionCells = $this->canonicalPublicationCells($revision->content['tiers'][(string) $tier]['values'] ?? null);
                $publishedCells = $this->canonicalPublicationCells($exemplar['values'] ?? null);
                if (($exemplar['tier_label'] ?? null) !== $tierLabels[$tier]
                    || (int) ($exemplar['target_class'] ?? -1) !== ($tier === 5 ? 0 : 1)
                    || $revisionCells === null
                    || $revisionCells !== $publishedCells) {
                    throw new RuntimeException('Published recovery cells do not match the exact reviewed revision for '.$program->code.'.');
                }
            }
        }
    }

    /**
     * @return array<string, int|float|string|null>|null
     */
    private function canonicalPublicationCells(mixed $cells, ?array $features = null): ?array
    {
        if (! is_array($cells)) {
            return null;
        }
        $types = [];
        foreach ($features ?? $this->featureDefinitions() as $feature) {
            $types[$feature['feature_key']] = $feature['logical_type'];
        }
        if (array_diff(array_keys($cells), array_keys($types)) !== [] || array_diff(array_keys($types), array_keys($cells)) !== []) {
            return null;
        }
        $canonical = [];
        foreach ($types as $key => $type) {
            $value = $cells[$key];
            if ($value === null) {
                $canonical[$key] = null;
            } elseif ($type === 'numeric' && is_numeric($value) && is_finite((float) $value)) {
                $canonical[$key] = (float) $value;
            } elseif ($type === 'binary' && in_array($value, [0, 1, 0.0, 1.0, false, true, '0', '1'], true)) {
                $canonical[$key] = (int) (bool) $value;
            } elseif ($type === 'categorical' && is_string($value)) {
                $canonical[$key] = $value;
            } else {
                return null;
            }
        }
        ksort($canonical, SORT_STRING);

        return $canonical;
    }

    private function validateExemplars(array $exemplars, array $features): array
    {
        $types = [];
        foreach ($features as $feature) {
            $types[$feature['feature_key']] = $feature['logical_type'];
        }
        $tiersByProgram = [];
        foreach ($exemplars as $index => $exemplar) {
            $programCode = (string) ($exemplar['program_code'] ?? '');
            $tier = (int) ($exemplar['tier_level'] ?? 0);
            if ($programCode === '' || $tier < 1 || $tier > 5 || isset($tiersByProgram[$programCode][$tier])) {
                throw new InvalidArgumentException('Every benchmark program must have one exemplar for each of five tiers.');
            }
            $tiersByProgram[$programCode][$tier] = true;
            $sourceValues = is_array($exemplar['values'] ?? null) ? $exemplar['values'] : [];
            if (array_diff(array_keys($sourceValues), array_keys($types)) !== []) {
                throw new InvalidArgumentException('Benchmark exemplar contains a feature outside the aptitude and GWA schema.');
            }
            $values = [];
            foreach ($types as $key => $type) {
                $value = $sourceValues[$key] ?? null;
                if ($value === '') {
                    $value = null;
                }
                if ($type === 'numeric' && $value !== null && ! $this->isPersistableNumeric($value)) {
                    throw new InvalidArgumentException('Benchmark value for '.$key.' cannot be represented by numeric storage.');
                }
                $valid = $value === null || match ($type) {
                    'numeric' => is_numeric($value) && is_finite((float) $value) && abs((float) $value) <= 9999.99,
                    'binary' => in_array($value, [0, 1, 0.0, 1.0, '0', '1'], true),
                    'categorical' => is_string($value) && mb_strlen($value) <= 64,
                    default => false,
                };
                if (! $valid) {
                    throw new InvalidArgumentException('Benchmark value for '.$key.' does not match its feature type.');
                }
                $values[$key] = $type === 'binary' && $value !== null ? (int) (bool) $value : $value;
            }
            ksort($values);
            $exemplars[$index]['values'] = $values;
        }

        foreach ($tiersByProgram as $code => $tiers) {
            if (array_keys($tiers) !== [1, 2, 3, 4, 5]) {
                ksort($tiers);
            }
            if (array_keys($tiers) !== [1, 2, 3, 4, 5]) {
                throw new InvalidArgumentException('Benchmark program '.$code.' must define all five tiers.');
            }
            foreach ($exemplars as $exemplar) {
                if ($exemplar['program_code'] === $code && (int) $exemplar['tier_level'] === 5) {
                    $hasAnchor = collect($exemplar['values'])->contains(static fn ($value): bool => $value !== null
                        && $value !== false
                        && $value !== ''
                        && ((is_string($value) && ! is_numeric($value)) || (is_numeric($value) && (float) $value !== 0.0)));
                    if (! $hasAnchor) {
                        throw new InvalidArgumentException('Benchmark program '.$code.' tier 5 requires a negative anchor.');
                    }
                }
            }
        }

        return $exemplars;
    }

    private function isPersistableNumeric(mixed $value): bool
    {
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            return false;
        }

        return (float) number_format((float) $value, 2, '.', '') === (float) $value;
    }

    /** @param mixed $range @return array{float,float} */
    private function bounds(mixed $range): array
    {
        if (is_array($range)) {
            $minimum = $range['min'] ?? $range[0] ?? null;
            $maximum = $range['max'] ?? $range[1] ?? $minimum;
            if (is_numeric($minimum) && is_numeric($maximum)) {
                return [(float) $minimum, (float) $maximum];
            }
        }
        if (is_numeric($range)) {
            return [(float) $range, (float) $range];
        }

        return [0.0, 0.0];
    }

    private function tierLabel(int $tier): string
    {
        return [1 => 'Honors', 2 => 'Regular', 3 => 'Cutoff', 4 => 'Cross-Strand', 5 => 'Negative'][$tier] ?? "Tier {$tier}";
    }
}
