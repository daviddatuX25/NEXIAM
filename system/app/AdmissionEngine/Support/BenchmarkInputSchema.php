<?php

namespace App\AdmissionEngine\Support;

use App\Models\Admission\AdmissionBenchmarkDraft;
use App\Models\Admission\AdmissionBenchmarkFeature;
use App\Models\Admission\AdmissionBenchmarkRevision;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Admission\AdmissionBenchmarkSubjectBinding;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\IntakeGradeField;
use InvalidArgumentException;

final class BenchmarkInputSchema
{
    /** @var list<string> */
    private const CANONICAL_FEATURE_KEYS = [
        'LOGIC',
        'PROFILE_VERBAL',
        'PROFILE_QUANT',
        'SPATIAL',
        'MEMORY',
        'LANGUAGE',
        'gwa',
    ];

    /** @return list<string> */
    public static function canonicalAptitudeCodes(): array
    {
        return array_slice(self::CANONICAL_FEATURE_KEYS, 0, -1);
    }

    /**
     * @return list<string>
     */
    public static function constructAliases(string $code): array
    {
        return match (strtoupper(trim($code))) {
            'VERBAL', 'PROFILE_VERBAL', 'VOCABULARY_RAW' => ['VERBAL', 'PROFILE_VERBAL'],
            'QUANT', 'PROFILE_QUANT', 'COMPUTATION_RAW' => ['QUANT', 'PROFILE_QUANT'],
            'LOGIC', 'GENERAL_ABILITY_RAW' => ['LOGIC'],
            'SPATIAL', 'SPATIAL_RAW' => ['SPATIAL'],
            'MEMORY', 'WORD_COMP_RAW' => ['MEMORY'],
            'LANGUAGE', 'MANUAL_DEXTERITY_RAW' => ['LANGUAGE'],
            default => [],
        };

    }

    public static function canonicalConstructCode(string $code): ?string
    {
        return match (strtoupper(trim($code))) {
            'VERBAL', 'PROFILE_VERBAL', 'VOCABULARY_RAW' => 'PROFILE_VERBAL',
            'QUANT', 'PROFILE_QUANT', 'COMPUTATION_RAW' => 'PROFILE_QUANT',
            'LOGIC', 'GENERAL_ABILITY_RAW' => 'LOGIC',
            'SPATIAL', 'SPATIAL_RAW' => 'SPATIAL',
            'MEMORY', 'WORD_COMP_RAW' => 'MEMORY',
            'LANGUAGE', 'MANUAL_DEXTERITY_RAW' => 'LANGUAGE',
            default => null,
        };
    }

    /** @return list<array<string, mixed>> */
    public static function canonicalSchema(): array
    {
        $definitions = self::canonicalFeatureDefinitions();

        return array_map(static fn (array $definition, int $position): array => [
            'feature_key' => $definition['feature_key'],
            'label' => $definition['label'],
            'logical_type' => $definition['logical_type'],
            'aptitude_area_id' => $definition['aptitude_area_id'],
            'is_required' => $definition['is_required'],
            'position' => $position + 1,
            'source_definition' => $definition['source_definition_json'],
        ], $definitions, array_keys($definitions));
    }

    /** @param array<string, mixed> $previous @return array<string, mixed> */
    public static function canonicalDraftContent(array $previous): array
    {
        $keys = array_fill_keys(self::CANONICAL_FEATURE_KEYS, null);
        $tiers = [];
        foreach (range(1, 5) as $tier) {
            $tiers[(string) $tier] = ['values' => array_replace($keys, array_intersect_key($previous['tiers'][(string) $tier]['values'] ?? [], $keys))];
        }
        $old = $previous['authoring'] ?? [];
        $ranges = [];
        foreach (['honors', 'regular', 'cutoff'] as $group) {
            $ranges[$group] = array_intersect_key($old['ranges'][$group] ?? [], $keys);
        }

        return ['tiers' => $tiers, 'authoring' => [
            'version' => 2, 'ranges' => $ranges,
            'preferred_strands' => $old['preferred_strands'] ?? [],
            'interests' => $old['interests'] ?? [],
            'interest_activities' => $old['interest_activities'] ?? [],
            'activities_reviewed' => $old['activities_reviewed'] ?? false,
        ]];
    }

    /**
     * @param  list<array<string, mixed>>  $schema
     */
    public static function isCanonicalFeatureSchema(array $schema): bool
    {
        if (! array_is_list($schema) || count($schema) !== count(self::CANONICAL_FEATURE_KEYS)) {
            return false;
        }

        foreach (self::CANONICAL_FEATURE_KEYS as $position => $key) {
            $feature = $schema[$position] ?? null;
            if (! is_array($feature)
                || ($feature['feature_key'] ?? null) !== $key
                || ($feature['logical_type'] ?? null) !== 'numeric'
                || ($feature['is_required'] ?? false) !== true
                || ($key !== 'gwa' && (! is_int($feature['aptitude_area_id'] ?? null) || $feature['aptitude_area_id'] < 1))
                || ($key === 'gwa' && ($feature['aptitude_area_id'] ?? null) !== null)) {
                return false;
            }

            if ($key === 'gwa') {
                $source = $feature['source_definition'] ?? null;
                if ($source !== null && (! is_array($source) || ($source['unit'] ?? null) !== 'gwa_scale')) {
                    return false;
                }
            } else {
                $source = $feature['source_definition'] ?? null;
                if (! is_array($source) || ($source['source_code'] ?? null) !== $key) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $schema
     */
    public static function assertCanonicalFeatureSchema(array $schema): void
    {
        if (! self::isCanonicalFeatureSchema($schema)) {
            throw new InvalidArgumentException('New admission benchmark schemas must contain only the approved aptitude measures and GWA.');
        }
    }

    /**
     * @return list<array{feature_key:string,label:string,logical_type:string,aptitude_area_id:?int,is_required:bool,source_definition_json:array<string,mixed>}>
     */
    public static function canonicalFeatureDefinitions(): array
    {
        $featureCodes = array_slice(self::CANONICAL_FEATURE_KEYS, 0, -1);
        $areaCodes = array_values(array_unique(array_merge(...array_map(
            static fn (string $code): array => self::constructAliases($code),
            $featureCodes,
        ))));
        $areas = AptitudeArea::query()
            ->where('is_active', true)
            ->whereIn('code', $areaCodes)
            ->orderBy('id')
            ->get()
            ->sortBy(static fn (AptitudeArea $area): int => (string) $area->code === (self::canonicalConstructCode((string) $area->code) ?? '') ? 1 : 0)
            ->keyBy(static fn (AptitudeArea $area): ?string => self::canonicalConstructCode((string) $area->code));
        $definitions = [];

        foreach (array_slice(self::CANONICAL_FEATURE_KEYS, 0, -1) as $code) {
            $area = $areas->get($code);
            if ($area === null) {
                throw new InvalidArgumentException("Approved aptitude area {$code} is unavailable.");
            }
            $definitions[] = [
                'feature_key' => $code,
                'label' => (string) $area->name,
                'logical_type' => 'numeric',
                'aptitude_area_id' => (int) $area->id,
                'is_required' => true,
                'source_definition_json' => [
                    'source_id' => (string) $area->id,
                    'source_code' => $code,
                    'source_version' => '2026-v1',
                    'scale' => 'percentile',
                    'norm_version' => '2026-v1',
                ],
            ];
        }
        $definitions[] = [
            'feature_key' => 'gwa',
            'label' => 'General Weighted Average',
            'logical_type' => 'numeric',
            'aptitude_area_id' => null,
            'is_required' => true,
            'source_definition_json' => null,
        ];

        return $definitions;
    }

    /** @return array{subjects:list<array{key:string,label:string,logical_type:string,source_id:?int,source_code:string,source_version:?int,unit:?string,min_value:?float,max_value:?float,is_required:bool,position:int,source_kind:string}>,activities:list<array{id:string,label:string,parent:string}>} */
    public static function projection(AdmissionBenchmarkSet $set): array
    {
        $set->loadMissing('features');

        return self::projectionForDefinitions($set->features->all());
    }

    /** @return list<array{feature_key:string,label:string,logical_type:string,is_required:bool,source_definition:?array<string,mixed>}> */
    public static function featureDefinitions(AdmissionBenchmarkSet $set): array
    {
        $set->loadMissing('features');

        return self::definitionsForFeatures($set->features->all());
    }

    /** @return list<array<string,mixed>> */
    public static function schemaForDraft(AdmissionBenchmarkDraft $draft, ?AdmissionBenchmarkRevision $revision = null): array
    {
        if ($revision !== null) {
            if (is_array($revision->feature_schema_json)) {
                return $revision->feature_schema_json;
            }
            $baseSetId = $revision->base_benchmark_set_id;
            if ($baseSetId === null) {
                throw new InvalidArgumentException('The selected historical revision has no pinned feature schema identity.');
            }
            $base = AdmissionBenchmarkSet::query()->with('features')->findOrFail($baseSetId);
            if (! hash_equals((string) $revision->feature_schema_digest, $base->computedSchemaDigest())) {
                throw new InvalidArgumentException('The selected historical revision no longer matches its pinned feature schema.');
            }

            return self::schemaForSet($base);
        }

        if (is_array($draft->feature_schema_json)) {
            return $draft->feature_schema_json;
        }
        if ($draft->base_benchmark_set_id === null) {
            return [];
        }
        $base = AdmissionBenchmarkSet::query()->with('features')->findOrFail($draft->base_benchmark_set_id);

        return self::schemaForSet($base);
    }

    /** @return list<array<string,mixed>> */
    public static function schemaForSet(AdmissionBenchmarkSet $set): array
    {
        $set->loadMissing('features');

        return $set->features->map(static fn (AdmissionBenchmarkFeature $feature): array => [
            'feature_key' => (string) $feature->feature_key,
            'label' => (string) $feature->label,
            'logical_type' => (string) $feature->logical_type,
            'aptitude_area_id' => $feature->aptitude_area_id === null ? null : (int) $feature->aptitude_area_id,
            'is_required' => (bool) $feature->is_required,
            'position' => (int) $feature->position,
            'source_definition' => $feature->source_definition_json,
        ])->all();
    }

    /** @param list<string> $activityIds @return array<string,?int> */
    public static function projectActivities(array $activityIds, bool $reviewed): array
    {
        return RiasecActivityCatalog::project($activityIds, $reviewed);
    }

    /** @return list<array<string,mixed>> */
    public static function availableBindings(int $institutionId): array
    {
        $bindings = AdmissionBenchmarkSubjectBinding::query()
            ->with('sourceField')
            ->where('institution_id', $institutionId)
            ->orderBy('feature_key')
            ->orderByDesc('version')
            ->get()
            ->unique('feature_key');

        return $bindings->filter(static function (AdmissionBenchmarkSubjectBinding $binding): bool {
            $field = $binding->sourceField;
            $snapshot = $binding->source_snapshot_json;

            return $field !== null
                && $field->is_active
                && $field->field_kind === IntakeGradeField::KIND_SUBJECT
                && (int) $field->institution_id === (int) $binding->institution_id
                && $field->updated_at?->toISOString() === ($snapshot['source_updated_at'] ?? null)
                && (string) $field->code === (string) ($snapshot['source_code'] ?? '')
                && (string) $field->label === (string) ($snapshot['source_label'] ?? '')
                && (bool) $field->is_required === (bool) ($snapshot['intake_required'] ?? null)
                && (float) $field->min_value === (float) ($snapshot['min_value'] ?? NAN)
                && (int) $field->sort_order === (int) ($snapshot['source_sort_order'] ?? -1)
                && (float) $field->max_value === (float) ($snapshot['max_value'] ?? NAN);
        })->map(static fn (AdmissionBenchmarkSubjectBinding $binding): array => self::bindingDefinition($binding))->values()->all();
    }

    /** @return list<array{id:int,code:string,label:string,intake_required:bool,min_value:?float,max_value:?float,position:int,source_version:string}> */
    public static function catalogSubjectFields(int $institutionId): array
    {
        return IntakeGradeField::query()
            ->where('institution_id', $institutionId)
            ->where('field_kind', IntakeGradeField::KIND_SUBJECT)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(static fn (IntakeGradeField $field): array => [
                'id' => (int) $field->id,
                'code' => (string) $field->code,
                'label' => (string) $field->label,
                'intake_required' => (bool) $field->is_required,
                'min_value' => $field->min_value === null ? null : (float) $field->min_value,
                'max_value' => $field->max_value === null ? null : (float) $field->max_value,
                'position' => (int) $field->sort_order,
                'source_version' => self::sourceVersion($field),
            ])->all();
    }

    public static function sourceVersion(IntakeGradeField $source): string
    {
        return hash('sha256', json_encode([
            'institution_id' => (int) $source->institution_id,
            'id' => (int) $source->id,
            'code' => (string) $source->code,
            'label' => (string) $source->label,
            'field_kind' => (string) $source->field_kind,
            'is_active' => (bool) $source->is_active,
            'is_required' => (bool) $source->is_required,
            'min_value' => $source->min_value === null ? null : (float) $source->min_value,
            'max_value' => $source->max_value === null ? null : (float) $source->max_value,
        ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    /** @return array<string,mixed> */
    public static function bindingDefinition(AdmissionBenchmarkSubjectBinding $binding, ?int $position = null): array
    {
        return [
            'binding_id' => (int) $binding->id,
            'feature_key' => (string) $binding->feature_key,
            'label' => (string) $binding->label,
            'logical_type' => 'numeric',
            'aptitude_area_id' => null,
            'is_required' => (bool) $binding->is_model_required,
            'position' => $position,
            'source_definition' => [
                'source_id' => (int) $binding->intake_grade_field_id,
                'source_code' => (string) $binding->source_snapshot_json['source_code'],
                'source_version' => (int) $binding->version,
                'source_binding_id' => (int) $binding->id,
                'unit' => (string) $binding->unit,
                'min_value' => $binding->min_value === null ? null : (float) $binding->min_value,
                'max_value' => $binding->max_value === null ? null : (float) $binding->max_value,
                'precision' => 2,
                'intake_required' => (bool) $binding->source_snapshot_json['intake_required'],
                'missing_value' => null,
            ],
        ];
    }

    /** @param list<array<string,mixed>> $schema */
    public static function digest(array $schema): string
    {
        $definitions = [];
        foreach ($schema as $feature) {
            $definition = [
                $feature['feature_key'],
                $feature['label'],
                $feature['logical_type'],
                $feature['aptitude_area_id'] ?? null,
                (bool) $feature['is_required'],
            ];
            if (is_array($feature['source_definition'] ?? null)) {
                $definition[] = $feature['source_definition'];
            }
            $definitions[] = $definition;
        }

        return hash('sha256', json_encode($definitions, JSON_THROW_ON_ERROR));
    }

    /** @param list<array<string,mixed>> $baseSchema @param list<array<string,mixed>> $candidateSchema */
    public static function isCompatibleSuccessor(array $baseSchema, array $candidateSchema, int $institutionId): bool
    {
        if (! self::isCanonicalFeatureSchema($baseSchema) && self::isCanonicalFeatureSchema($candidateSchema)) {
            return true;
        }
        if (count($candidateSchema) < count($baseSchema)
            || count(array_unique(array_column($candidateSchema, 'feature_key'))) !== count($candidateSchema)) {
            return false;
        }
        $prefix = array_slice($candidateSchema, 0, count($baseSchema));
        if (! hash_equals(self::digest($baseSchema), self::digest($prefix))) {
            return false;
        }
        $extensions = array_slice($candidateSchema, count($baseSchema));
        $bindingIds = array_values(array_unique(array_filter(array_map(
            static fn (array $definition): ?int => isset($definition['source_definition']['source_binding_id'])
                ? (int) $definition['source_definition']['source_binding_id']
                : null,
            $extensions,
        ))));
        if ($extensions === []) {
            return true;
        }
        $approved = AdmissionBenchmarkSubjectBinding::query()
            ->where('institution_id', $institutionId)
            ->whereIn('id', $bindingIds)
            ->get()
            ->keyBy('id');
        foreach ($extensions as $definition) {
            if (! isset($definition['source_definition']['source_binding_id'])) {
                return false;
            }
            $bindingId = (int) $definition['source_definition']['source_binding_id'];
            $binding = $approved->get($bindingId);
            if ($binding === null
                || json_encode($definition, JSON_THROW_ON_ERROR) !== json_encode(self::bindingDefinition($binding, $definition['position'] ?? null), JSON_THROW_ON_ERROR)) {
                return false;
            }
        }

        return true;
    }

    /** @return array{subjects:list<array{key:string,label:string,logical_type:string,source_id:?int,source_code:string,source_version:?int,unit:?string,min_value:?float,max_value:?float,is_required:bool,position:int,source_kind:string}>,activities:list<array{id:string,label:string,parent:string}>} */
    public static function projectionForDraft(AdmissionBenchmarkDraft $draft, ?AdmissionBenchmarkRevision $revision = null): array
    {
        $schema = self::schemaForDraft($draft, $revision);
        $features = array_map(static function (array $definition): AdmissionBenchmarkFeature {
            $feature = new AdmissionBenchmarkFeature;
            $feature->setRawAttributes([
                'feature_key' => $definition['feature_key'],
                'aptitude_area_id' => $definition['aptitude_area_id'] ?? null,
                'logical_type' => $definition['logical_type'],
                'position' => $definition['position'] ?? 0,
                'is_required' => $definition['is_required'] ?? false,
                'source_definition_json' => is_array($definition['source_definition'] ?? null)
                    ? json_encode($definition['source_definition'], JSON_THROW_ON_ERROR)
                    : null,
            ], true);

            return $feature;
        }, $schema);

        return self::projectionForDefinitions($features);
    }

    /** @return list<array{feature_key:string,label:string,logical_type:string,is_required:bool,source_definition:?array<string,mixed>}> */
    public static function featureDefinitionsForDraft(AdmissionBenchmarkDraft $draft, ?AdmissionBenchmarkRevision $revision = null): array
    {
        $schema = self::schemaForDraft($draft, $revision);
        if ($schema !== []) {
            return array_map(static fn (array $feature): array => [
                'feature_key' => (string) $feature['feature_key'],
                'label' => (string) $feature['label'],
                'logical_type' => (string) $feature['logical_type'],
                'is_required' => (bool) $feature['is_required'],
                'source_definition' => $feature['source_definition'] ?? null,
            ], $schema);
        }

        return [];
    }

    /** @param array<string,mixed> $values @return array<string,?float> */
    public static function validateValuesForDraft(AdmissionBenchmarkDraft $draft, ?AdmissionBenchmarkRevision $revision, array $values): array
    {
        return self::validateSubjectValues(self::projectionForDraft($draft, $revision)['subjects'], $values);
    }

    /** @param array<string,mixed> $values @return array<string,?float> */
    public static function validateValues(AdmissionBenchmarkSet $set, array $values): array
    {
        return self::validateSubjectValues(self::projection($set)['subjects'], $values);
    }

    /** @param list<array<string,mixed>> $subjects @param array<string,mixed> $values @return array<string,?float> */
    private static function validateSubjectValues(array $subjects, array $values): array
    {
        $definitions = [];
        foreach ($subjects as $subject) {
            $definitions[$subject['key']] = $subject;
        }
        foreach ($values as $key => $value) {
            if (! is_string($key) || ! array_key_exists($key, $definitions)) {
                throw new InvalidArgumentException('Subject input contains a feature not declared by the pinned benchmark schema.');
            }
            if ($value === null) {
                continue;
            }
            if (! is_numeric($value) || ! is_finite((float) $value)) {
                throw new InvalidArgumentException('Subject input must be a finite number or explicitly missing.');
            }
            $number = (float) $value;
            $subject = $definitions[$key];
            if (($subject['min_value'] !== null && $number < $subject['min_value'])
                || ($subject['max_value'] !== null && $number > $subject['max_value'])) {
                throw new InvalidArgumentException('Subject input is outside the approved source definition bounds.');
            }
            $precision = $subject['precision'] ?? 2;
            if ((float) number_format($number, $precision, '.', '') !== $number) {
                throw new InvalidArgumentException('Subject input exceeds the pinned source precision.');
            }
            $values[$key] = $number;
        }

        return $values;
    }

    /** @param list<array<string,mixed>> $schema @param array<string,mixed> $content */
    public static function assertContentSourceBounds(array $schema, array $content): void
    {
        $bounds = [];
        foreach ($schema as $feature) {
            $source = $feature['source_definition'] ?? null;
            if (is_array($source)) {
                $bounds[$feature['feature_key']] = $source;
            }
        }
        foreach ($content['tiers'] as $tier) {
            foreach ($bounds as $key => $source) {
                $value = $tier['values'][$key] ?? null;
                if ($value !== null && (! is_numeric($value) || ! is_finite((float) $value)
                    || (isset($source['min_value']) && (float) $value < (float) $source['min_value'])
                    || (isset($source['max_value']) && (float) $value > (float) $source['max_value'])
                    || (float) number_format((float) $value, (int) ($source['precision'] ?? 2), '.', '') !== (float) $value)) {
                    throw new InvalidArgumentException('Subject benchmark cells must match the pinned source range and precision.');
                }
            }
        }
        foreach (($content['authoring']['ranges'] ?? []) as $ranges) {
            foreach ($ranges as $key => $pair) {
                if (! isset($bounds[$key])) {
                    continue;
                }
                foreach ($pair as $endpoint) {
                    if ($endpoint === null) {
                        continue;
                    }
                    $source = $bounds[$key];
                    if ((isset($source['min_value']) && (float) $endpoint < (float) $source['min_value'])
                        || (isset($source['max_value']) && (float) $endpoint > (float) $source['max_value'])) {
                        throw new InvalidArgumentException('Subject benchmark ranges must remain within the pinned source definition bounds.');
                    }
                }
            }
        }
    }

    /** @param list<AdmissionBenchmarkFeature> $features @return array{subjects:list<array<string,mixed>>,activities:list<array{id:string,label:string,parent:string}>} */
    private static function projectionForDefinitions(array $features): array
    {
        $subjects = [];
        foreach ($features as $feature) {
            if ($feature->aptitude_area_id !== null) {
                continue;
            }
            $source = $feature->source_definition_json;
            $key = (string) $feature->feature_key;
            if (is_array($source) && isset($source['source_id'])) {
                $subjects[] = [
                    'key' => $key,
                    'label' => (string) $feature->label,
                    'logical_type' => (string) $feature->logical_type,
                    'source_id' => (int) $source['source_id'],
                    'source_code' => (string) $source['source_code'],
                    'source_version' => (int) $source['source_version'],
                    'unit' => (string) $source['unit'],
                    'min_value' => $source['min_value'] === null ? null : (float) $source['min_value'],
                    'max_value' => $source['max_value'] === null ? null : (float) $source['max_value'],
                    'precision' => (int) ($source['precision'] ?? 2),
                    'is_required' => (bool) $feature->is_required,
                    'position' => (int) $feature->position,
                    'source_kind' => 'approved_intake_binding',
                ];
            } elseif (in_array($key, ['shs_math', 'shs_sci', 'shs_eng'], true)) {
                $subjects[] = [
                    'key' => $key,
                    'label' => (string) $feature->label,
                    'logical_type' => (string) $feature->logical_type,
                    'source_id' => null,
                    'source_code' => $key,
                    'source_version' => null,
                    'unit' => null,
                    'min_value' => null,
                    'max_value' => null,
                    'precision' => 2,
                    'is_required' => (bool) $feature->is_required,
                    'position' => (int) $feature->position,
                    'source_kind' => 'frozen_legacy',
                ];
            }
        }

        return ['subjects' => $subjects, 'activities' => RiasecActivityCatalog::definitions()];
    }

    /** @param list<AdmissionBenchmarkFeature> $features @return list<array{feature_key:string,label:string,logical_type:string,is_required:bool,source_definition:?array<string,mixed>}> */
    private static function definitionsForFeatures(array $features): array
    {
        return array_map(static fn (AdmissionBenchmarkFeature $feature): array => [
            'feature_key' => (string) $feature->feature_key,
            'label' => (string) $feature->label,
            'logical_type' => (string) $feature->logical_type,
            'is_required' => (bool) $feature->is_required,
            'source_definition' => $feature->source_definition_json,
        ], $features);
    }
}
