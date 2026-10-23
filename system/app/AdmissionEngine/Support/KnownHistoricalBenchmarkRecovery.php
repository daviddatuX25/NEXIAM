<?php

namespace App\AdmissionEngine\Support;

use App\Foundation\Support\TagudinUndergraduateCatalog;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkExemplar;
use App\Models\Admission\AdmissionBenchmarkFeature;
use App\Models\Admission\AdmissionBenchmarkRecovery;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Program;
use Illuminate\Support\Collection;
use RuntimeException;

/** The one approved historical TabFM shape; this is not an integrity bypass for modern sets. */
final class KnownHistoricalBenchmarkRecovery
{
    public function __construct(private TagudinUndergraduateCatalog $catalog) {}

    private const OLD_TIER_LABELS = [
        1 => 'Honors High',
        2 => 'Honors Low',
        3 => 'Regular',
        4 => 'Cutoff',
        5 => 'Below Cutoff',
    ];

    private const APTITUDE_KEYS = ['VERBAL', 'QUANT', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'LOGIC', 'SPATIAL', 'MEMORY', 'LANGUAGE'];

    /** @return array{tiers: array<string, array{values: array<string, mixed>}>} */
    public function mappedContent(AdmissionBenchmarkSet $set, int $programId): array
    {
        $set->loadMissing(['features', 'exemplars.values.feature']);
        $valuesByOldTier = [];
        foreach ($set->exemplars->where('program_id', $programId) as $exemplar) {
            $values = [];
            foreach ($exemplar->values as $value) {
                $feature = $value->feature;
                $values[$feature->feature_key] = $value->is_missing
                    ? null
                    : ($feature->logical_type === 'categorical' ? $value->categorical_value : (float) $value->numeric_value);
            }
            ksort($values, SORT_STRING);
            $valuesByOldTier[(int) $exemplar->tier_level] = $values;
        }
        $featureKeys = $set->features->pluck('feature_key')->all();
        $content = [];
        foreach ([1 => 1, 2 => 3, 3 => 4, 5 => 5] as $newTier => $oldTier) {
            $content[(string) $newTier] = ['values' => array_replace(array_fill_keys($featureKeys, null), $valuesByOldTier[$oldTier] ?? [])];
            ksort($content[(string) $newTier]['values'], SORT_STRING);
        }
        $content['4'] = ['values' => array_fill_keys($featureKeys, null)];
        ksort($content['4']['values'], SORT_STRING);
        ksort($content, SORT_NUMERIC);

        return ['tiers' => $content];
    }

    public function isKnownHistoricalSet(AdmissionBenchmarkSet $set): bool
    {
        $set->loadMissing(['features', 'exemplars.program', 'exemplars.values.feature']);
        if ($set->status !== 'published'
            || ! preg_match('/^[a-f0-9]{64}$/', (string) $set->schema_sha256)
            || ! preg_match('/^[a-f0-9]{64}$/', (string) $set->content_sha256)
            || hash_equals((string) $set->schema_sha256, $set->computedSchemaDigest())
            || $set->features->count() !== 19) {
            return false;
        }

        $features = $set->features->keyBy('feature_key');
        $expectedKeys = [
            ...self::APTITUDE_KEYS,
            'gwa', 'shs_strand', 'shs_math', 'shs_sci', 'shs_eng',
            'riasec_r', 'riasec_i', 'riasec_a', 'riasec_s', 'riasec_e', 'riasec_c',
        ];
        if ($features->keys()->sort()->values()->all() !== collect($expectedKeys)->sort()->values()->all()) {
            return false;
        }

        $areas = AptitudeArea::query()->whereIn('code', self::APTITUDE_KEYS)->get()->keyBy('code');
        if ($areas->count() !== count(self::APTITUDE_KEYS)) {
            return false;
        }
        foreach (self::APTITUDE_KEYS as $key) {
            $feature = $features->get($key);
            $area = $areas->get($key);
            if ($feature === null || $area === null
                || $feature->logical_type !== 'numeric'
                || $feature->label !== $area->name
                || (int) $feature->aptitude_area_id !== (int) $area->id) {
                return false;
            }
        }
        foreach ([
            'gwa' => ['General Weighted Average', 'numeric'],
            'shs_strand' => ['Senior High School Strand', 'categorical'],
            'shs_math' => ['Senior High School Mathematics', 'numeric'],
            'shs_sci' => ['Senior High School Science', 'numeric'],
            'shs_eng' => ['Senior High School English', 'numeric'],
            'riasec_r' => ['RIASEC Realistic', 'binary'],
            'riasec_i' => ['RIASEC Investigative', 'binary'],
            'riasec_a' => ['RIASEC Artistic', 'binary'],
            'riasec_s' => ['RIASEC Social', 'binary'],
            'riasec_e' => ['RIASEC Enterprising', 'binary'],
            'riasec_c' => ['RIASEC Conventional', 'binary'],
        ] as $key => [$label, $type]) {
            $feature = $features->get($key);
            if ($feature === null || $feature->label !== $label || $feature->logical_type !== $type || $feature->aptitude_area_id !== null) {
                return false;
            }
        }

        $oldSchema = $set->features->map(static fn (AdmissionBenchmarkFeature $feature): array => [
            $feature->feature_key,
            $feature->logical_type,
            $feature->aptitude_area_id === null ? null : (int) $feature->aptitude_area_id,
        ])->all();
        if (! hash_equals((string) $set->schema_sha256, hash('sha256', json_encode($oldSchema, JSON_THROW_ON_ERROR)))) {
            return false;
        }

        if ($set->exemplars->isEmpty()) {
            return false;
        }
        $rowsByProgram = $set->exemplars->groupBy('program_id');
        foreach ($rowsByProgram as $rows) {
            if ($rows->count() !== 5 || $rows->pluck('tier_level')->map(static fn ($tier): int => (int) $tier)->sort()->values()->all() !== [1, 2, 3, 4, 5]) {
                return false;
            }
            foreach ($rows as $row) {
                if ($row->tier_label !== self::OLD_TIER_LABELS[(int) $row->tier_level]
                    || (int) $row->target_class !== ((int) $row->tier_level === 5 ? 0 : 1)
                    || (int) $row->program->institution_id !== (int) $set->institution_id) {
                    return false;
                }
                if ($row->values->count() !== 19 || $row->values->pluck('benchmark_feature_id')->unique()->count() !== 19) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function sourceSnapshot(AdmissionBenchmarkSet $set): array
    {
        $set->loadMissing(['features', 'exemplars.program', 'exemplars.values.feature']);
        $features = $set->features->sortBy('position')->map(static function (AdmissionBenchmarkFeature $feature): array {
            $snapshot = [
                'id' => (int) $feature->id,
                'position' => (int) $feature->position,
                'feature_key' => (string) $feature->feature_key,
                'label' => (string) $feature->label,
                'logical_type' => (string) $feature->logical_type,
                'aptitude_area_id' => $feature->aptitude_area_id === null ? null : (int) $feature->aptitude_area_id,
                'is_required' => (bool) $feature->is_required,
                'created_at' => $feature->getRawOriginal('created_at'),
                'updated_at' => $feature->getRawOriginal('updated_at'),
            ];
            if (is_array($feature->source_definition_json)) {
                $snapshot['source_definition_json'] = $feature->source_definition_json;
            }

            return $snapshot;
        })->values()->all();
        $exemplars = $set->exemplars->sortBy([['program_id', 'asc'], ['tier_level', 'asc'], ['id', 'asc']])->map(static function (AdmissionBenchmarkExemplar $exemplar): array {
            return [
                'id' => (int) $exemplar->id,
                'program_id' => (int) $exemplar->program_id,
                'program' => [
                    'institution_id' => (int) $exemplar->program->institution_id,
                    'code' => (string) $exemplar->program->code,
                    'name' => (string) $exemplar->program->name,
                    'is_active' => (bool) $exemplar->program->is_active,
                    'updated_at' => $exemplar->program->getRawOriginal('updated_at'),
                ],
                'tier_level' => (int) $exemplar->tier_level,
                'tier_label' => (string) $exemplar->tier_label,
                'target_class' => (int) $exemplar->target_class,
                'created_at' => $exemplar->getRawOriginal('created_at'),
                'updated_at' => $exemplar->getRawOriginal('updated_at'),
                'values' => $exemplar->values->sortBy([['benchmark_feature_id', 'asc'], ['id', 'asc']])->map(static fn ($value): array => [
                    'id' => (int) $value->id,
                    'benchmark_feature_id' => (int) $value->benchmark_feature_id,
                    'is_missing' => (bool) $value->is_missing,
                    'numeric_value' => $value->getRawOriginal('numeric_value'),
                    'categorical_value' => $value->getRawOriginal('categorical_value'),
                    'created_at' => $value->getRawOriginal('created_at'),
                    'updated_at' => $value->getRawOriginal('updated_at'),
                ])->values()->all(),
            ];
        })->values()->all();

        return [
            'set' => [
                'id' => (int) $set->id,
                'institution_id' => (int) $set->institution_id,
                'version' => (int) $set->version,
                'status' => (string) $set->status,
                'name' => (string) $set->name,
                'change_reason' => $set->change_reason,
                'content_sha256' => (string) $set->content_sha256,
                'schema_sha256' => (string) $set->schema_sha256,
                'predecessor_id' => $set->predecessor_id === null ? null : (int) $set->predecessor_id,
                'publication_manifest_json' => $set->getRawOriginal('publication_manifest_json'),
                'raw_ranges_json' => $set->getRawOriginal('raw_ranges_json'),
                'created_by_user_id' => $set->created_by_user_id === null ? null : (int) $set->created_by_user_id,
                'published_by_user_id' => $set->published_by_user_id === null ? null : (int) $set->published_by_user_id,
                'published_at' => $set->getRawOriginal('published_at'),
                'idempotency_key' => $set->idempotency_key,
                'import_payload_sha256' => $set->import_payload_sha256,
                'lock_version' => $set->lock_version === null ? null : (int) $set->lock_version,
                'created_at' => $set->getRawOriginal('created_at'),
                'updated_at' => $set->getRawOriginal('updated_at'),
            ],
            'features' => $features,
            'exemplars' => $exemplars,
        ];
    }

    /** @return array<string, mixed> */
    public function activationSnapshot(AdmissionBenchmarkActivation $activation): array
    {
        return [
            'id' => (int) $activation->id,
            'institution_id' => (int) $activation->institution_id,
            'academic_cycle_id' => (int) $activation->academic_cycle_id,
            'benchmark_set_id' => (int) $activation->benchmark_set_id,
            'sequence' => (int) $activation->sequence,
            'activated_by_user_id' => $activation->activated_by_user_id === null ? null : (int) $activation->activated_by_user_id,
            'idempotency_key' => $activation->idempotency_key,
            'idempotency_payload_sha256' => $activation->idempotency_payload_sha256,
            'expected_current_activation_id' => $activation->expected_current_activation_id === null ? null : (int) $activation->expected_current_activation_id,
            'activated_at' => $activation->getRawOriginal('activated_at'),
            'superseded_at' => $activation->getRawOriginal('superseded_at'),
            'created_at' => $activation->getRawOriginal('created_at'),
            'updated_at' => $activation->getRawOriginal('updated_at'),
        ];
    }

    /** @param array<string, mixed> $snapshot */
    public function digest(array $snapshot): string
    {
        return hash('sha256', json_encode($this->canonicalize($snapshot), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $nested) {
            $value[$key] = $this->canonicalize($nested);
        }
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }

    /**
     * @param  array{tiers?: array<string, array{values?: array<string, mixed>}>}  $content
     * @param  Collection<int, AdmissionBenchmarkFeature>  $features
     * @return array{missing_cells: list<array{tier:int, feature_key:string}>, negative_anchor_missing: bool}
     */
    public function incompleteContent(array $content, Collection $features): array
    {
        $missing = [];
        foreach (range(1, 5) as $tier) {
            $values = $content['tiers'][(string) $tier]['values'] ?? [];
            foreach ($features->where('is_required', true) as $feature) {
                if (! array_key_exists($feature->feature_key, $values) || $values[$feature->feature_key] === null || $values[$feature->feature_key] === '') {
                    $missing[] = ['tier' => $tier, 'feature_key' => (string) $feature->feature_key];
                }
            }
        }
        $negativeValues = $content['tiers']['5']['values'] ?? [];
        $negativeAnchorMissing = ! collect($negativeValues)->contains(static fn ($value): bool => $value !== null
            && $value !== ''
            && $value !== false
            && ((is_string($value) && ! is_numeric($value)) || (is_numeric($value) && is_finite((float) $value) && (float) $value !== 0.0)));

        return ['missing_cells' => $missing, 'negative_anchor_missing' => $negativeAnchorMissing];
    }

    public function assertUnchanged(AdmissionBenchmarkRecovery $recovery, AcademicCycle $cycle): AdmissionBenchmarkSet
    {
        $source = AdmissionBenchmarkSet::query()
            ->with(['features', 'exemplars.program', 'exemplars.values.feature'])
            ->where('institution_id', $recovery->institution_id)
            ->whereKey($recovery->source_benchmark_set_id)
            ->first();
        $activation = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
        if ($source === null
            || (int) $cycle->institution_id !== (int) $recovery->institution_id
            || (int) $recovery->academic_cycle_id !== (int) $cycle->id
            || ! $this->isKnownHistoricalSet($source)
            || ! hash_equals((string) $recovery->source_content_sha256, (string) $source->content_sha256)
            || ! hash_equals((string) $recovery->source_schema_sha256, (string) $source->schema_sha256)
            || ! hash_equals((string) $recovery->current_schema_sha256, $source->computedSchemaDigest())
            || ! hash_equals((string) $recovery->source_snapshot_sha256, $this->digest($this->sourceSnapshot($source)))
            || ! is_array($recovery->source_snapshot_json)
            || ! hash_equals((string) $recovery->source_snapshot_sha256, $this->digest($recovery->source_snapshot_json))
            || $activation === null
            || ! is_array($recovery->source_activation_snapshot_json)
            || ! hash_equals((string) $recovery->source_activation_sha256, $this->digest($this->activationSnapshot($activation)))
            || ! hash_equals((string) $recovery->source_activation_sha256, $this->digest($recovery->source_activation_snapshot_json))
            || (int) $recovery->source_activation_id !== (int) $activation?->id
            || (int) $recovery->source_activation_sequence !== (int) $activation?->sequence
            || (int) $activation?->benchmark_set_id !== (int) $source->id
            || (int) $activation?->institution_id !== (int) $recovery->institution_id
            || (int) $activation?->academic_cycle_id !== (int) $cycle->id) {
            throw new RuntimeException('Historical benchmark source snapshot, schema, or current activation changed; recovery is no longer safe.');
        }

        return $source;
    }

    public function assertCutoverSourceIntact(AdmissionBenchmarkSet $source, AcademicCycle $cycle, bool $allowUnsnapshotted = false): void
    {
        if ((int) $source->institution_id !== (int) $cycle->institution_id) {
            throw new RuntimeException('The benchmark source is outside the current institution.');
        }
        if ($this->isKnownHistoricalSet($source)) {
            $record = AdmissionBenchmarkRecovery::query()
                ->where('institution_id', $source->institution_id)
                ->where('source_benchmark_set_id', $source->id)
                ->where('academic_cycle_id', $cycle->id)->first();
            if ($record !== null) {
                $this->assertUnchanged($record, $cycle);
            } elseif (! $allowUnsnapshotted) {
                throw new RuntimeException('The historical source requires an explicit frozen recovery review.');
            }

            return;
        }
        if (! hash_equals((string) $source->schema_sha256, $source->computedSchemaDigest())
            || ! hash_equals((string) $source->content_sha256, $source->computedContentDigest())) {
            throw new RuntimeException('The benchmark source failed its immutable integrity check.');
        }
    }

    /** @return list<int> */
    public function sourceProgramIds(AdmissionBenchmarkSet $set): array
    {
        return $set->exemplars->pluck('program_id')->map(static fn ($id): int => (int) $id)->unique()->sort()->values()->all();
    }

    /** @return list<int> */
    public function retainedProgramIds(AdmissionBenchmarkRecovery $recovery, AdmissionBenchmarkSet $source): array
    {
        $sourceIds = $this->sourceProgramIds($source);
        $retiredIds = array_column($this->retiredSourceProgramManifest($recovery, $source), 'program_id');
        $reviewedTargets = $recovery->programReviews()
            ->orderBy('program_id')
            ->pluck('program_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $programIds = array_values(array_unique([...array_diff($sourceIds, $retiredIds), ...$reviewedTargets]));
        sort($programIds);

        return $programIds;
    }

    /**
     * @return array{
     *     source_set_id:int,
     *     source_snapshot_sha256:string,
     *     source_program_ids:list<int>,
     *     retained_program_ids:list<int>,
     *     retired_programs:list<array{program_id:int,code:string,source_set_id:int,source_content_sha256:string,provenance:array{sources:list<string>,accessed_on:string,basis:string}}>,
     *     registered_targets:list<array{review_id:int,program_id:int,actor_id:int,reason:string,idempotency_key:string,created_at:string}>
     * }
     */
    public function reviewManifest(AdmissionBenchmarkRecovery $recovery, AdmissionBenchmarkSet $source): array
    {
        return [
            'source_set_id' => (int) $recovery->source_benchmark_set_id,
            'source_snapshot_sha256' => (string) $recovery->source_snapshot_sha256,
            'source_program_ids' => $this->sourceProgramIds($source),
            'retained_program_ids' => $this->retainedProgramIds($recovery, $source),
            'retired_programs' => $this->retiredSourceProgramManifest($recovery, $source),
            'registered_targets' => $recovery->programReviews()
                ->orderBy('program_id')
                ->get()
                ->map(static fn ($review): array => [
                    'review_id' => (int) $review->id,
                    'program_id' => (int) $review->program_id,
                    'actor_id' => (int) $review->registered_by_user_id,
                    'reason' => (string) $review->reason,
                    'idempotency_key' => (string) $review->idempotency_key,
                    'created_at' => $review->created_at->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * @return list<array{program_id:int,code:string,source_set_id:int,source_content_sha256:string,provenance:array{sources:list<string>,accessed_on:string,basis:string}}>
     */
    private function retiredSourceProgramManifest(AdmissionBenchmarkRecovery $recovery, AdmissionBenchmarkSet $source): array
    {
        $codes = $this->catalog->retiredCodes();
        $sourceIds = $this->sourceProgramIds($source);

        return Program::query()
            ->where('institution_id', $recovery->institution_id)
            ->whereIn('id', $sourceIds)
            ->whereIn('code', $codes)
            ->orderBy('id')
            ->get(['id', 'code'])
            ->map(fn (Program $program): array => [
                'program_id' => (int) $program->id,
                'code' => $program->code,
                'source_set_id' => (int) $source->id,
                'source_content_sha256' => (string) $source->content_sha256,
                'provenance' => $this->catalog->retirementProvenance(),
            ])
            ->all();
    }
}
