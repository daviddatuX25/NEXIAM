<?php

namespace App\Assessment\Support;

use App\Models\Assessment\TestInstrumentItem;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentMetricInput;
use App\Models\Assessment\TestInstrumentSubtest;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Foundation\AptitudeArea;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

final class TestInstrumentProfileValidator
{
    public function __construct(private readonly RawMetricFormulaEngine $formulaEngine = new RawMetricFormulaEngine) {}

    /**
     * Rebuild the approved-content payload from persisted draft rows and verify
     * derived bounds have not drifted from their inputs.
     *
     * @return array{profile: array<string, string|null>, subtests: list<array<string, mixed>>, metrics: list<array<string, mixed>>}
     */
    public function normalizeVersion(TestInstrumentVersion $version): array
    {
        $version->loadMissing([
            'instrument',
            'subtests.items',
            'metrics.subtest',
            'metrics.inputs.inputMetric',
        ]);

        $normalized = $this->normalize([
            'profile' => [
                'code' => $version->instrument->code,
                'display_name' => $version->display_name,
                'publisher_name' => $version->publisher_name,
                'form_code' => $version->form_code,
                'edition' => $version->edition,
                'language' => $version->language,
                'source_reference' => $version->source_reference,
                'permitted_use_reference' => $version->permitted_use_reference,
                'material_requirement' => $version->material_requirement,
                'administration_procedure_reference' => $version->administration_procedure_reference,
            ],
            'subtests' => $version->subtests->map(static fn (TestInstrumentSubtest $subtest): array => [
                'code' => $subtest->code,
                'name' => $subtest->name,
                'display_order' => (int) $subtest->display_order,
                'response_mode' => $subtest->response_mode,
                'item_order_policy' => $subtest->item_order_policy,
                'items' => $subtest->items->map(static fn (TestInstrumentItem $item): array => [
                    'item_code' => $item->item_code,
                    'local_number' => $item->local_number,
                    'display_order' => (int) $item->display_order,
                    'is_scored' => (bool) $item->is_scored,
                    'option_tokens' => $item->option_tokens ?? [],
                ])->all(),
            ])->all(),
            'metrics' => $version->metrics->map(static function (TestInstrumentMetric $metric): array {
                $normalizedMetric = [
                    'code' => $metric->code,
                    'name' => $metric->name,
                    'rule_kind' => $metric->rule_kind,
                    'subtest_code' => $metric->subtest?->code,
                    'unit' => $metric->unit,
                    'min_raw_value' => $metric->min_raw_value,
                    'max_raw_value' => $metric->max_raw_value,
                    'precision_scale' => $metric->precision_scale === null ? null : (int) $metric->precision_scale,
                    'is_required' => (bool) $metric->is_required,
                    'required_output_scales' => $metric->required_output_scales ?? [],
                    'procedure_reference' => $metric->procedure_reference,
                    'construct_aptitude_area_id' => $metric->construct_aptitude_area_id === null
                        ? null
                        : (int) $metric->construct_aptitude_area_id,
                    'input_codes' => $metric->inputs
                        ->map(static fn (TestInstrumentMetricInput $input): ?string => $input->inputMetric?->code)
                        ->filter(static fn (?string $code): bool => $code !== null)
                        ->values()
                        ->all(),
                ];
                if ($metric->input_contract_json !== null) {
                    $normalizedMetric['input_contract_json'] = $metric->input_contract_json;
                }
                if ($metric->formula_expression !== null) {
                    $normalizedMetric['formula_expression'] = (string) $metric->formula_expression;
                    $normalizedMetric['formula_ast_json'] = $metric->formula_ast_json;
                }
                if ($metric->item_codes_json !== null) {
                    $normalizedMetric['item_codes'] = $metric->item_codes_json;
                }

                return $normalizedMetric;
            })->all(),
        ]);

        $normalizedMetrics = [];
        foreach ($normalized['metrics'] as $metric) {
            $normalizedMetrics[$metric['code']] = $metric;
        }

        foreach ($version->metrics as $metric) {
            $expected = $normalizedMetrics[$metric->code] ?? null;
            if ($expected === null
                || $metric->unit !== $expected['unit']
                || ($metric->precision_scale === null ? null : (int) $metric->precision_scale) !== $expected['precision_scale']
                || ! $this->sameDecimal($metric->min_raw_value, $expected['min_raw_value'])
                || ! $this->sameDecimal($metric->max_raw_value, $expected['max_raw_value'])
                || ($metric->formula_expression !== null
                    && (($expected['formula_expression'] ?? null) !== $metric->formula_expression
                        || ($expected['formula_ast_json'] ?? null) !== $metric->formula_ast_json))
                || ($metric->item_codes_json !== null && ($expected['item_codes'] ?? null) !== $metric->item_codes_json)) {
                $this->fail('metrics', 'Stored metric bounds, formula, item scope, or precision do not match their declared inputs. Review the draft before approval.');
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     profile: array<string, string|null>,
     *     subtests: list<array<string, mixed>>,
     *     metrics: list<array<string, mixed>>
     * }
     */
    public function normalize(array $input): array
    {
        $profileInput = is_array($input['profile'] ?? null) ? $input['profile'] : [];
        $code = strtoupper(trim((string) ($profileInput['code'] ?? '')));

        if (! preg_match('/^[A-Z][A-Z0-9_-]{0,63}$/', $code)) {
            $this->fail('profile.code', 'Use a stable code beginning with a letter and containing only letters, numbers, hyphens, or underscores.');
        }

        $profile = [
            'code' => $code,
            'display_name' => $this->requiredText($profileInput['display_name'] ?? null, 'profile.display_name'),
            'publisher_name' => $this->optionalText($profileInput['publisher_name'] ?? null),
            'form_code' => $this->optionalText($profileInput['form_code'] ?? null),
            'edition' => $this->optionalText($profileInput['edition'] ?? null),
            'language' => $this->optionalText($profileInput['language'] ?? null),
            'source_reference' => $this->optionalText($profileInput['source_reference'] ?? null),
            'permitted_use_reference' => $this->optionalText($profileInput['permitted_use_reference'] ?? null),
            'material_requirement' => $this->normalizeMaterialRequirement($profileInput['material_requirement'] ?? null),
            'administration_procedure_reference' => $this->boundedOptionalText(
                $profileInput['administration_procedure_reference'] ?? null,
                'profile.administration_procedure_reference',
                500,
            ),
        ];
        $subtestInput = $input['subtests'] ?? [];
        $metricInput = $input['metrics'] ?? [];
        if (! is_array($subtestInput) || ! is_array($metricInput)) {
            $this->fail('profile', 'Subtests and metrics must be structured lists.');
        }

        $subtests = $this->normalizeSubtests(array_values($subtestInput));
        $metrics = $this->normalizeMetrics(array_values($metricInput), $subtests);
        $metricIndexes = [];

        foreach ($metrics as $index => $metric) {
            if (isset($metricIndexes[$metric['code']])) {
                $this->fail("metrics.{$index}.code", 'Metric codes must be unique within this version.');
            }

            $metricIndexes[$metric['code']] = $index;
        }

        $finalMetrics = array_filter($metrics, static fn (array $metric): bool => $metric['rule_kind'] === TestInstrumentMetric::RULE_EXTERNAL_FINAL);
        if ($finalMetrics !== []) {
            if (count($finalMetrics) !== count($metrics) || $subtests !== []) {
                $this->fail('metrics', 'Final-only definitions cannot contain raw metrics or subtests. Use a separate definition.');
            }
            $constructIds = array_column($metrics, 'construct_aptitude_area_id');
            if (count(array_unique($constructIds)) !== count($constructIds)) {
                $this->fail('metrics', 'Each final aptitude must bind a distinct construct.');
            }
        }
        $this->validateMetricReferences($metrics, $metricIndexes);
        $this->deriveMetricBounds($metrics, $metricIndexes);
        foreach ($metrics as $index => &$metric) {
            if (array_key_exists('input_contract_json', $metric) && $metric['input_contract_json'] !== null) {
                $metric['input_contract_json'] = $this->normalizeInputContract(
                    $metric['input_contract_json'],
                    (string) ($metric['unit'] ?? ''),
                    $metric['rule_kind'],
                    "metrics.{$index}.input_contract_json",
                );
            } else {
                unset($metric['input_contract_json']);
            }
        }

        unset($metric);
        $this->validateOmrMetricDeclarations($metrics, $subtests);

        return [
            'profile' => $profile,
            'subtests' => $subtests,
            'metrics' => $metrics,
        ];
    }

    /**
     * @return array{
     *     allowed_sources: list<string>,
     *     required_resources: list<string>,
     *     source_requirements: array<string, array{unit: string, identity_requirements: list<string>}>
     * }
     */
    public function normalizeInputContract(
        mixed $contract,
        string $metricUnit,
        string $ruleKind,
        string $field = 'metrics.input_contract_json',
    ): array {
        if (! is_array($contract) || array_is_list($contract)
            || array_diff(array_keys($contract), ['allowed_sources', 'required_resources', 'source_requirements']) !== []
            || array_diff(['allowed_sources', 'required_resources', 'source_requirements'], array_keys($contract)) !== []) {
            $this->fail($field, 'Declare exactly allowed_sources, required_resources, and source_requirements.');
        }

        $allowedSources = $this->normalizeClosedStringList(
            $contract['allowed_sources'],
            ['manual', 'import', 'omr'],
            "{$field}.allowed_sources",
        );
        if ($allowedSources === []) {
            $this->fail("{$field}.allowed_sources", 'At least one permitted acquisition source is required.');
        }
        $requiredResources = $this->normalizeClosedStringList(
            $contract['required_resources'],
            ['answer_sheet', 'booklet_form', 'key', 'physical_procedure'],
            "{$field}.required_resources",
        );
        if ($ruleKind === TestInstrumentMetric::RULE_EXTERNAL_FINAL
            && ($requiredResources !== []
                || array_diff($allowedSources, ['manual', 'import']) !== []
                || array_diff(['manual', 'import'], $allowedSources) !== [])) {
            $this->fail($field, 'Final-only aptitudes declare manual and import sources without scoring-paper resources or OMR.');
        }

        $sourceRequirements = $contract['source_requirements'];
        if (! is_array($sourceRequirements) || array_is_list($sourceRequirements)) {
            $this->fail("{$field}.source_requirements", 'Source requirements must be keyed by each allowed source.');
        }
        $requirementSources = array_keys($sourceRequirements);
        sort($requirementSources, SORT_STRING);
        if ($requirementSources !== $allowedSources) {
            $this->fail("{$field}.source_requirements", 'Declare exactly one source requirement for every allowed source.');
        }

        $metricUnit = $ruleKind === TestInstrumentMetric::RULE_EXTERNAL_FINAL ? 'percentile_rank' : trim($metricUnit);
        if ($metricUnit === '') {
            $this->fail("{$field}.source_requirements", 'A declared input contract requires a resolved metric unit.');
        }

        $normalizedSourceRequirements = [];
        foreach ($allowedSources as $source) {
            $requirement = $sourceRequirements[$source];
            if (! is_array($requirement) || array_is_list($requirement)
                || array_diff(array_keys($requirement), ['unit', 'identity_requirements']) !== []
                || array_diff(['unit', 'identity_requirements'], array_keys($requirement)) !== []) {
                $this->fail("{$field}.source_requirements.{$source}", 'Each source requirement must declare exactly unit and identity_requirements.');
            }

            $unit = $this->optionalText($requirement['unit']);
            if ($unit === null || $unit !== $metricUnit) {
                $this->fail("{$field}.source_requirements.{$source}.unit", 'The source unit must exactly match the metric unit.');
            }
            $identities = $this->normalizeClosedStringList(
                $requirement['identity_requirements'],
                ['administration', 'answer_sheet', 'booklet_form', 'key', 'metric', 'physical_procedure'],
                "{$field}.source_requirements.{$source}.identity_requirements",
            );
            if (! in_array('administration', $identities, true) || ! in_array('metric', $identities, true)) {
                $this->fail("{$field}.source_requirements.{$source}.identity_requirements", 'Every source must bind the exact administration and metric.');
            }
            if ($ruleKind === TestInstrumentMetric::RULE_EXTERNAL_FINAL && count($identities) !== 2) {
                $this->fail("{$field}.source_requirements.{$source}", 'Final-only sources bind only the exact administration and metric.');
            }
            foreach ($requiredResources as $resource) {
                if (! in_array($resource, $identities, true)) {
                    $this->fail("{$field}.source_requirements.{$source}.identity_requirements", 'Every source must bind each applicable required resource.');
                }
            }
            if (in_array('key', $requiredResources, true) && ! in_array('booklet_form', $requiredResources, true)) {
                $this->fail("{$field}.required_resources", 'A required key must be bound to its exact Booklet form.');
            }
            if ($source === 'omr') {
                if (! in_array($ruleKind, [
                    TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
                    TestInstrumentMetric::RULE_SUM_RAW_METRICS,
                    TestInstrumentMetric::RULE_FORMULA_RAW_METRICS,
                ], true)) {
                    $this->fail("{$field}.allowed_sources", 'OMR supports keyed-item metrics and their raw derived formulas.');
                }
                foreach (['answer_sheet', 'booklet_form', 'key'] as $resource) {
                    if (! in_array($resource, $requiredResources, true)
                        || ! in_array($resource, $identities, true)) {
                        $this->fail("{$field}.source_requirements.omr", 'OMR requires exact Answer Sheet, Booklet form, and key identity.');
                    }
                }
            }

            $normalizedSourceRequirements[$source] = [
                'unit' => $unit,
                'identity_requirements' => $identities,
            ];
        }

        ksort($normalizedSourceRequirements, SORT_STRING);

        return [
            'allowed_sources' => $allowedSources,
            'required_resources' => $requiredResources,
            'source_requirements' => $normalizedSourceRequirements,
        ];
    }

    /**
     * Require declarations on all metrics, including synthetic software versions.
     * Retained NULL contracts remain readable historical data only.
     *
     * @param  array{profile: array<string, string|null>, subtests: list<array<string, mixed>>, metrics: list<array<string, mixed>>}  $normalized
     * @param  array<string, mixed>  $profile
     */
    public function assertMetricInputContractsApprovable(array $normalized, array $profile): void
    {
        if ($normalized['metrics'] === []) {
            $this->fail('metrics', 'At least one declared input metric is required before approval.');
        }
        $metricsByCode = [];
        foreach ($normalized['metrics'] as $metric) {
            $metricsByCode[$metric['code']] = $metric;
        }
        $subtestsByCode = [];
        foreach ($normalized['subtests'] as $subtest) {
            $subtestsByCode[$subtest['code']] = $subtest;
        }
        $requiredResources = [];
        foreach ($normalized['metrics'] as $index => $metric) {
            $contract = $metric['input_contract_json'] ?? null;
            if (! is_array($contract)) {
                $this->fail("metrics.{$index}.input_contract_json", 'Every metric requires an explicit source and resource declaration before approval.');
            }
            if ($contract['required_resources'] === []
                && (trim((string) ($profile['source_reference'] ?? '')) === ''
                    || trim((string) ($profile['administration_procedure_reference'] ?? '')) === '')) {
                $this->fail("metrics.{$index}.input_contract_json", 'A resourceless metric requires explicit source and administration procedure references.');
            }
            if (in_array('physical_procedure', $contract['required_resources'], true)
                && trim((string) ($profile['administration_procedure_reference'] ?? '')) === '') {
                $this->fail("metrics.{$index}.input_contract_json", 'A physical-procedure resource requires an exact administration procedure reference.');
            }
            if (in_array('omr', $contract['allowed_sources'], true)) {
                if ($metric['rule_kind'] === TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT) {
                    $subtest = $subtestsByCode[$metric['subtest_code']] ?? null;
                    if (($subtest['response_mode'] ?? null) !== 'keyed_items') {
                        $this->fail("metrics.{$index}.input_contract_json.allowed_sources", 'OMR item counts require a keyed-choice subtest.');
                    }
                } elseif (in_array($metric['rule_kind'], [
                    TestInstrumentMetric::RULE_SUM_RAW_METRICS,
                    TestInstrumentMetric::RULE_FORMULA_RAW_METRICS,
                ], true) && ! $this->hasOmrChoiceDependency($metric, $metricsByCode, $subtestsByCode)) {
                    $this->fail("metrics.{$index}.input_contract_json.allowed_sources", 'An OMR-derived metric requires at least one OMR-capable keyed-item input.');
                }
            }
            foreach ($contract['required_resources'] as $resource) {
                $requiredResources[$resource] = true;
            }
        }

        $expectedRequirement = $requiredResources === []
            ? TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER
            : TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED;
        if (($profile['material_requirement'] ?? null) !== $expectedRequirement) {
            $this->fail('profile.material_requirement', 'The compatibility applicability field must agree with declared metric resources.');
        }
        if ($this->isFinalOnlyDefinition($normalized)) {
            $this->assertFinalOnlyDefinition($normalized, $profile);
        }

    }

    /** @param array<string, mixed> $normalized */
    private function isFinalOnlyDefinition(array $normalized): bool
    {
        return $normalized['metrics'] !== []
            && array_filter($normalized['metrics'], static fn (array $metric): bool => $metric['rule_kind'] !== TestInstrumentMetric::RULE_EXTERNAL_FINAL) === [];
    }

    /** @param array<string, mixed> $normalized @param array<string, mixed> $profile */
    private function assertFinalOnlyDefinition(array $normalized, array $profile): void
    {
        if ($normalized['subtests'] !== [] || count($normalized['metrics']) !== 6) {
            $this->fail('metrics', 'A final-only definition requires six explicitly selected aptitudes and no subtests.');
        }
        foreach (['source_reference', 'permitted_use_reference', 'administration_procedure_reference'] as $field) {
            if (trim((string) ($profile[$field] ?? '')) === '') {
                $this->fail("profile.{$field}", 'Record the external score authority, permitted use and intake procedure before approval.');
            }
        }
        $constructIds = array_column($normalized['metrics'], 'construct_aptitude_area_id');
        if (count(array_unique($constructIds)) !== 6
            || AptitudeArea::query()->whereIn('id', $constructIds)->where('is_active', true)->count() !== 6) {
            $this->fail('metrics', 'Select six distinct active aptitude constructs for final-only scoring.');
        }
    }

    /**
     * @param  array{profile: array<string, string|null>, subtests: list<array<string, mixed>>, metrics: list<array<string, mixed>>}  $normalized
     */
    public function digest(array $normalized): string
    {
        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $profile */
    public function assertApprovable(array $normalized, array $profile): void
    {
        if (! in_array($profile['material_requirement'] ?? null, [
            TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED,
            TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER,
        ], true)) {
            $this->fail('profile.material_requirement', 'Declare paper-backed or no-paper applicability before approval.');
        }

        if (trim((string) ($profile['administration_procedure_reference'] ?? '')) === '') {
            $this->fail('profile.administration_procedure_reference', 'An administration procedure reference is required before approval.');
        }
        if ($this->isFinalOnlyDefinition($normalized)) {
            $this->assertFinalOnlyDefinition($normalized, $profile);

            return;
        }

        foreach ([
            'publisher_name' => 'Publisher identity is required before approval.',
            'form_code' => 'The exact form code is required before approval.',
            'edition' => 'The source edition is required before approval.',
            'language' => 'The source language is required before approval.',
            'source_reference' => 'Record the official source reference before approval.',
            'permitted_use_reference' => 'Record the permitted-use or digitization authority before approval.',
        ] as $field => $message) {
            if (trim((string) ($profile[$field] ?? '')) === '') {
                $this->fail("profile.{$field}", $message);
            }
        }

        $subtests = $normalized['subtests'];
        $metrics = $normalized['metrics'];

        if ($subtests === []) {
            $this->fail('subtests', 'At least one test section/subtest is required before approval.');
        }

        if ($metrics === []) {
            $this->fail('metrics', 'At least one raw metric is required before approval.');
        }

        $subtestsByCode = [];
        foreach ($subtests as $index => $subtest) {
            $subtestsByCode[$subtest['code']] = $subtest;

            if ($subtest['response_mode'] === 'keyed_items') {
                $scoredItems = array_values(array_filter(
                    $subtest['items'],
                    static fn (array $item): bool => $item['is_scored'],
                ));

                if ($scoredItems === []) {
                    $this->fail("subtests.{$index}.items", 'A keyed subtest requires at least one scored item.');
                }

                foreach ($subtest['items'] as $itemIndex => $item) {
                    $optionCount = count($item['option_tokens']);
                    if ($optionCount < 2 || $optionCount > 10) {
                        $this->fail("subtests.{$index}.items.{$itemIndex}.option_tokens", 'Every keyed item requires two to ten exact response tokens.');
                    }
                }
            }

            if ($subtest['response_mode'] === 'recorded_measure'
                && $subtest['items'] !== []) {
                $this->fail("subtests.{$index}.items", 'Recorded-measure sections cannot contain fabricated answer items.');
            }
        }

        $requiredMetrics = array_values(array_filter(
            $metrics,
            static fn (array $metric): bool => $metric['is_required'],
        ));

        if ($requiredMetrics === []) {
            $this->fail('metrics', 'At least one required raw metric is required before approval.');
        }

        $metricsBySubtest = [];
        foreach ($metrics as $index => $metric) {
            if ($metric['is_required'] && $metric['required_output_scales'] === []) {
                $this->fail("metrics.{$index}.required_output_scales", 'Required metrics must declare their source-defined output scales.');
            }

            if ($metric['unit'] === null || $metric['precision_scale'] === null
                || $metric['min_raw_value'] === null || $metric['max_raw_value'] === null) {
                $this->fail("metrics.{$index}", 'Metric unit, precision, and source-defined bounds are required before approval.');
            }

            if ($metric['rule_kind'] === TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT) {
                $subtest = $subtestsByCode[$metric['subtest_code']] ?? null;
                if ($subtest === null || $subtest['response_mode'] !== 'keyed_items') {
                    $this->fail("metrics.{$index}.subtest_code", 'Correct-item counts must reference a keyed test section/subtest.');
                }

                $scoredCount = ($metric['item_codes'] ?? null) === null
                    ? count(array_filter(
                        $subtest['items'],
                        static fn (array $item): bool => $item['is_scored'],
                    ))
                    : count($metric['item_codes']);
                if ($scoredCount < 1
                    || BigDecimal::of($metric['min_raw_value'])->compareTo('0') !== 0
                    || BigDecimal::of($metric['max_raw_value'])->compareTo((string) $scoredCount) !== 0
                    || $metric['precision_scale'] !== 0
                    || $metric['unit'] !== 'count') {
                    $this->fail("metrics.{$index}", 'Correct-item count bounds, unit, and precision must match the exact scored item inventory.');
                }
            } elseif ($metric['rule_kind'] === TestInstrumentMetric::RULE_SUM_RAW_METRICS) {
                if (count($metric['input_codes']) < 2) {
                    $this->fail("metrics.{$index}.input_codes", 'A raw sum requires at least two distinct same-version raw inputs.');
                }
            } elseif ($metric['rule_kind'] === TestInstrumentMetric::RULE_RECORDED_MEASURE) {
                $subtest = $subtestsByCode[$metric['subtest_code']] ?? null;
                if ($subtest === null || $subtest['response_mode'] !== 'recorded_measure') {
                    $this->fail("metrics.{$index}.subtest_code", 'Recorded measures must reference a recorded-measure section/subtest.');
                }
                if (trim((string) ($metric['procedure_reference'] ?? '')) === '') {
                    $this->fail("metrics.{$index}.procedure_reference", 'The approved measurement procedure reference is required.');
                }
            }

            if ($metric['subtest_code'] !== null) {
                $metricsBySubtest[$metric['subtest_code']][] = $metric;
            }
        }

        foreach ($subtests as $index => $subtest) {
            if (($metricsBySubtest[$subtest['code']] ?? []) === []) {
                $this->fail("subtests.{$index}", 'Every administered test section/subtest must define its raw metric.');
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $subtests
     * @return list<array<string, mixed>>
     */
    private function normalizeSubtests(array $subtests): array
    {
        $normalized = [];
        $codes = [];
        $orders = [];

        foreach ($subtests as $index => $subtest) {
            if (! is_array($subtest)) {
                $this->fail("subtests.{$index}", 'Each test section/subtest must be a structured record.');
            }

            $code = strtoupper(trim((string) ($subtest['code'] ?? '')));
            if (! preg_match('/^[A-Z][A-Z0-9_-]{0,31}$/', $code)) {
                $this->fail("subtests.{$index}.code", 'Use a stable subtest code beginning with a letter.');
            }
            if (isset($codes[$code])) {
                $this->fail("subtests.{$index}.code", 'Subtest codes must be unique within this version.');
            }
            $codes[$code] = true;

            $displayOrder = (int) ($subtest['display_order'] ?? 0);
            if ($displayOrder < 1 || isset($orders[$displayOrder])) {
                $this->fail("subtests.{$index}.display_order", 'Subtest display order must be a unique positive integer.');
            }
            $orders[$displayOrder] = true;

            $responseMode = (string) ($subtest['response_mode'] ?? '');
            if (! in_array($responseMode, ['keyed_items', 'recorded_measure'], true)) {
                $this->fail("subtests.{$index}.response_mode", 'Choose a supported keyed-item or recorded-measure section.');
            }

            $orderPolicy = (string) ($subtest['item_order_policy'] ?? 'fixed');
            if (! in_array($orderPolicy, ['fixed', 'within_subtest_permutable'], true)) {
                $this->fail("subtests.{$index}.item_order_policy", 'Item order must be fixed or explicitly permutable within this subtest.');
            }

            $items = $this->normalizeItems(array_values($subtest['items'] ?? []), $index);
            if ($responseMode === 'recorded_measure' && $items !== []) {
                $this->fail("subtests.{$index}.items", 'Recorded-measure sections cannot contain item rows.');
            }

            $normalized[] = [
                'code' => $code,
                'name' => $this->requiredText($subtest['name'] ?? null, "subtests.{$index}.name"),
                'display_order' => $displayOrder,
                'response_mode' => $responseMode,
                'item_order_policy' => $orderPolicy,
                'items' => $items,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeItems(array $items, int $subtestIndex): array
    {
        $normalized = [];
        $codes = [];
        $localNumbers = [];
        $orders = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                $this->fail("subtests.{$subtestIndex}.items.{$index}", 'Each item must be a structured inventory record.');
            }

            $itemCode = strtoupper(trim((string) ($item['item_code'] ?? '')));
            if (! preg_match('/^[A-Z][A-Z0-9_-]{0,63}$/', $itemCode)) {
                $this->fail("subtests.{$subtestIndex}.items.{$index}.item_code", 'Use a stable item code beginning with a letter.');
            }
            if (isset($codes[$itemCode])) {
                $this->fail("subtests.{$subtestIndex}.items.{$index}.item_code", 'Item codes must be unique within a subtest.');
            }
            $codes[$itemCode] = true;

            $localNumber = trim((string) ($item['local_number'] ?? ''));
            if ($localNumber === '' || mb_strlen($localNumber) > 32 || isset($localNumbers[$localNumber])) {
                $this->fail("subtests.{$subtestIndex}.items.{$index}.local_number", 'Printed item numbers must be non-empty and unique within this subtest.');
            }
            $localNumbers[$localNumber] = true;

            $displayOrder = (int) ($item['display_order'] ?? 0);
            if ($displayOrder < 1 || isset($orders[$displayOrder])) {
                $this->fail("subtests.{$subtestIndex}.items.{$index}.display_order", 'Item display order must be a unique positive integer within its subtest.');
            }
            $orders[$displayOrder] = true;

            $tokens = [];
            foreach (array_values($item['option_tokens'] ?? []) as $tokenIndex => $token) {
                $token = trim((string) $token);
                if ($token === '' || mb_strlen($token) > 32 || in_array($token, $tokens, true)) {
                    $this->fail("subtests.{$subtestIndex}.items.{$index}.option_tokens.{$tokenIndex}", 'Response tokens must be non-empty, unique, and at most 32 characters.');
                }
                $tokens[] = $token;
            }
            if (count($tokens) > 10) {
                $this->fail("subtests.{$subtestIndex}.items.{$index}.option_tokens", 'An item may have no more than ten response tokens.');
            }

            $normalized[] = [
                'item_code' => $itemCode,
                'local_number' => $localNumber,
                'display_order' => $displayOrder,
                'is_scored' => (bool) ($item['is_scored'] ?? true),
                'option_tokens' => $tokens,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $metrics
     * @param  list<array<string, mixed>>  $subtests
     * @return list<array<string, mixed>>
     */
    private function normalizeMetrics(array $metrics, array $subtests): array
    {
        $subtestCodes = array_fill_keys(array_column($subtests, 'code'), true);
        $normalized = [];
        $codes = [];

        foreach ($metrics as $index => $metric) {
            if (! is_array($metric)) {
                $this->fail("metrics.{$index}", 'Each raw metric must be a structured record.');
            }

            $code = strtoupper(trim((string) ($metric['code'] ?? '')));
            if (! preg_match('/^[A-Z][A-Z0-9_-]{0,31}$/', $code)) {
                $this->fail("metrics.{$index}.code", 'Use a stable metric code beginning with a letter.');
            }
            if (isset($codes[$code])) {
                $this->fail("metrics.{$index}.code", 'Metric codes must be unique within this version.');
            }
            $codes[$code] = true;

            $ruleKind = (string) ($metric['rule_kind'] ?? '');
            if (! in_array($ruleKind, [
                TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
                TestInstrumentMetric::RULE_SUM_RAW_METRICS,
                TestInstrumentMetric::RULE_FORMULA_RAW_METRICS,
                TestInstrumentMetric::RULE_RECORDED_MEASURE,
                TestInstrumentMetric::RULE_EXTERNAL_FINAL,
            ], true)) {
                $this->fail("metrics.{$index}.rule_kind", 'Choose a supported item-count, sum, formula, or recorded-measure rule.');
            }

            $subtestCode = $this->optionalCode($metric['subtest_code'] ?? null);
            if ($subtestCode !== null && ! isset($subtestCodes[$subtestCode])) {
                $this->fail("metrics.{$index}.subtest_code", 'Metric section must reference a subtest in this version.');
            }

            $precisionScale = $metric['precision_scale'] ?? null;
            if ($precisionScale !== null && ((int) $precisionScale < 0 || (int) $precisionScale > 6)) {
                $this->fail("metrics.{$index}.precision_scale", 'Precision must be an explicit scale from zero to six places.');
            }
            $precisionScale = $precisionScale === null || $precisionScale === '' ? null : (int) $precisionScale;

            $inputCodes = [];
            foreach (array_values($metric['input_codes'] ?? []) as $inputIndex => $inputCode) {
                $normalizedInputCode = strtoupper(trim((string) $inputCode));
                if (! preg_match('/^[A-Z][A-Z0-9_-]{0,31}$/', $normalizedInputCode)
                    || in_array($normalizedInputCode, $inputCodes, true)) {
                    $this->fail("metrics.{$index}.input_codes.{$inputIndex}", 'Raw metric inputs must be valid, distinct metric codes.');
                }
                $inputCodes[] = $normalizedInputCode;
            }

            if (! in_array($ruleKind, [
                TestInstrumentMetric::RULE_SUM_RAW_METRICS,
                TestInstrumentMetric::RULE_FORMULA_RAW_METRICS,
            ], true) && $inputCodes !== []) {
                $this->fail("metrics.{$index}.input_codes", 'Only derived metrics may reference other raw metrics.');
            }
            if ($ruleKind === TestInstrumentMetric::RULE_SUM_RAW_METRICS && count($inputCodes) === 1) {
                $this->fail("metrics.{$index}.input_codes", 'A raw sum requires at least two distinct inputs.');
            }

            $unit = $this->optionalText($metric['unit'] ?? null);
            $min = null;
            $max = null;
            $itemCodes = null;
            if (array_key_exists('item_codes', $metric) && $metric['item_codes'] !== null) {
                if ($ruleKind !== TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT || ! is_array($metric['item_codes'])) {
                    $this->fail("metrics.{$index}.item_codes", 'An item subset is available only for direct keyed-item counts.');
                }
                $itemCodes = array_values(array_unique(array_map(
                    static fn (mixed $itemCode): string => strtoupper(trim((string) $itemCode)),
                    $metric['item_codes'],
                )));
                $subtest = $subtestCode === null ? null : $this->findSubtest($subtests, $subtestCode);
                $scoredCodes = array_column(array_values(array_filter($subtest['items'], static fn (array $item): bool => $item['is_scored'])), 'item_code');
                if ($itemCodes === [] || count($itemCodes) !== count($metric['item_codes']) || array_diff($itemCodes, $scoredCodes) !== []) {
                    $this->fail("metrics.{$index}.item_codes", 'Select one or more distinct scored items from this version’s keyed subtest.');
                }
            }

            if ($ruleKind === TestInstrumentMetric::RULE_EXTERNAL_FINAL) {
                if ($subtestCode !== null || $inputCodes !== [] || $unit !== null
                    || ($metric['min_raw_value'] ?? null) !== null
                    || ($metric['max_raw_value'] ?? null) !== null
                    || ! empty($metric['formula_expression'])
                    || ! empty($metric['formula_ast_json'])
                    || ! empty($metric['item_codes'])) {
                    $this->fail("metrics.{$index}", 'Final-only aptitudes do not have subtests, raw values, formulas or item scope.');
                }
                if ($precisionScale === null || ! (bool) ($metric['is_required'] ?? true)
                    || (int) ($metric['construct_aptitude_area_id'] ?? 0) < 1) {
                    $this->fail("metrics.{$index}", 'Final-only aptitudes require a construct, explicit precision and required final score.');
                }
            } elseif ($ruleKind === TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT) {
                if ($subtestCode === null || $inputCodes !== []) {
                    $this->fail("metrics.{$index}", 'Correct-item counts require one keyed subtest and no metric inputs.');
                }
                if ($precisionScale !== null && $precisionScale !== 0) {
                    $this->fail("metrics.{$index}.precision_scale", 'Correct-item counts use integer precision.');
                }
                if ($unit !== null && $unit !== 'count') {
                    $this->fail("metrics.{$index}.unit", 'Correct-item counts use the count unit.');
                }
                $unit = 'count';
                $precisionScale = 0;
                $subtest = $this->findSubtest($subtests, $subtestCode);
                $scoredCodes = array_column(array_values(array_filter($subtest['items'], static fn (array $item): bool => $item['is_scored'])), 'item_code');
                $scoredCount = $itemCodes === null ? count($scoredCodes) : count($itemCodes);
                $min = '0.000000';
                $max = $this->fixedDecimal((string) $scoredCount);
            } elseif ($ruleKind === TestInstrumentMetric::RULE_RECORDED_MEASURE) {
                if ($subtestCode === null || $inputCodes !== []) {
                    $this->fail("metrics.{$index}", 'Recorded measures require one recorded-measure subtest and no metric inputs.');
                }
                $min = $this->decimal($metric['min_raw_value'] ?? null, "metrics.{$index}.min_raw_value", $precisionScale);
                $max = $this->decimal($metric['max_raw_value'] ?? null, "metrics.{$index}.max_raw_value", $precisionScale);
                $this->assertPairedBounds($min, $max, $index);
                if ($min !== null && BigDecimal::of($min)->compareTo(BigDecimal::of($max)) > 0) {
                    $this->fail("metrics.{$index}.min_raw_value", 'Minimum raw value must not exceed the maximum.');
                }
            } else {
                if ($subtestCode !== null) {
                    $this->fail("metrics.{$index}.subtest_code", 'Derived metrics do not have a physical subtest.');
                }
                if ($ruleKind === TestInstrumentMetric::RULE_FORMULA_RAW_METRICS) {
                    if ($inputCodes === [] || $precisionScale === null || $unit === null) {
                        $this->fail("metrics.{$index}", 'Formula metrics require declared inputs, unit, and output precision.');
                    }
                    foreach ($inputCodes as $inputCode) {
                        if (! preg_match('/^[A-Z][A-Z0-9_]{0,31}$/', $inputCode)) {
                            $this->fail("metrics.{$index}.input_codes", "Metric code {$inputCode} cannot be bound in formulas because hyphens are ambiguous with subtraction.");
                        }
                    }
                    $expression = trim((string) ($metric['formula_expression'] ?? ''));
                    try {
                        $ast = $this->formulaEngine->compile($expression, $inputCodes);
                    } catch (\InvalidArgumentException $exception) {
                        $this->fail("metrics.{$index}.formula_expression", $exception->getMessage());
                    }
                    $variables = $this->formulaEngine->variables($ast);
                    $sortedInputs = $inputCodes;
                    sort($sortedInputs, SORT_STRING);
                    if ($variables !== $sortedInputs) {
                        $this->fail("metrics.{$index}.formula_expression", 'Formula variables must match the declared inputs exactly; unused inputs are not accepted.');
                    }
                    $metric['formula_expression'] = $expression;
                    $metric['formula_ast_json'] = $ast;
                } elseif ($inputCodes === []) {
                    $unit = null;
                    $precisionScale = null;
                }
            }

            $outputs = [];
            foreach (array_values($metric['required_output_scales'] ?? []) as $outputIndex => $outputScale) {
                $outputScale = (string) $outputScale;
                if (! in_array($outputScale, TestInstrumentMetric::OUTPUT_SCALES, true)
                    || in_array($outputScale, $outputs, true)) {
                    $this->fail("metrics.{$index}.required_output_scales.{$outputIndex}", 'Output scales must be unique supported names.');
                }
                $outputs[] = $outputScale;
            }
            if ($ruleKind === TestInstrumentMetric::RULE_EXTERNAL_FINAL && $outputs !== ['percentile_rank']) {
                $this->fail("metrics.{$index}.required_output_scales", 'Final-only aptitude scores must explicitly use percentile rank.');
            }

            $normalizedMetric = [
                'code' => $code,
                'name' => $this->requiredText($metric['name'] ?? null, "metrics.{$index}.name"),
                'rule_kind' => $ruleKind,
                'subtest_code' => $subtestCode,
                'unit' => $unit,
                'min_raw_value' => $min,
                'max_raw_value' => $max,
                'precision_scale' => $precisionScale,
                'is_required' => (bool) ($metric['is_required'] ?? true),
                'required_output_scales' => $outputs,
                'procedure_reference' => $this->optionalText($metric['procedure_reference'] ?? null),
                'construct_aptitude_area_id' => ($metric['construct_aptitude_area_id'] ?? null) === ''
                    ? null
                    : (isset($metric['construct_aptitude_area_id']) ? (int) $metric['construct_aptitude_area_id'] : null),
                'input_codes' => $inputCodes,
                'input_contract_json' => $metric['input_contract_json'] ?? null,
            ];
            if ($ruleKind === TestInstrumentMetric::RULE_FORMULA_RAW_METRICS) {
                $normalizedMetric['formula_expression'] = $metric['formula_expression'];
                $normalizedMetric['formula_ast_json'] = $metric['formula_ast_json'];
                $normalizedMetric['formula_algorithm_identity'] = TestInstrumentMetric::FORMULA_ALGORITHM_IDENTITY;
            }
            if ($itemCodes !== null) {
                $normalizedMetric['item_codes'] = $itemCodes;
            }
            $normalized[] = $normalizedMetric;
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $metrics
     * @param  array<string, int>  $metricIndexes
     */
    private function validateMetricReferences(array $metrics, array $metricIndexes): void
    {
        foreach ($metrics as $index => $metric) {
            foreach ($metric['input_codes'] as $inputCode) {
                if (! isset($metricIndexes[$inputCode])) {
                    $this->fail("metrics.{$index}.input_codes", 'Every derived input must reference a metric in this version.');
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $metrics
     * @param  array<string, int>  $metricIndexes
     */
    private function deriveMetricBounds(array &$metrics, array $metricIndexes): void
    {
        $states = [];
        foreach (array_keys($metricIndexes) as $code) {
            $this->resolveMetric($code, $metrics, $metricIndexes, $states);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $metrics
     * @param  array<string, int>  $metricIndexes
     * @param  array<string, int>  $states
     */
    private function resolveMetric(string $code, array &$metrics, array $metricIndexes, array &$states): void
    {
        if (($states[$code] ?? 0) === 2) {
            return;
        }
        if (($states[$code] ?? 0) === 1) {
            $this->fail('metrics', 'Raw metric dependencies must be acyclic.');
        }

        $states[$code] = 1;
        $index = $metricIndexes[$code];
        $metric = &$metrics[$index];

        if (in_array($metric['rule_kind'], [
            TestInstrumentMetric::RULE_SUM_RAW_METRICS,
            TestInstrumentMetric::RULE_FORMULA_RAW_METRICS,
        ], true) && $metric['input_codes'] !== []) {
            $inputsByCode = [];
            foreach ($metric['input_codes'] as $inputCode) {
                $this->resolveMetric($inputCode, $metrics, $metricIndexes, $states);
                $inputsByCode[$inputCode] = $metrics[$metricIndexes[$inputCode]];
            }
            $inputs = array_values($inputsByCode);
            $units = array_values(array_unique(array_column($inputs, 'unit')));
            if (count($units) !== 1 || $units[0] === null) {
                $this->fail("metrics.{$index}.input_codes", 'Derived metric inputs must use the same declared unit.');
            }

            if ($metric['rule_kind'] === TestInstrumentMetric::RULE_SUM_RAW_METRICS) {
                $scales = array_values(array_unique(array_column($inputs, 'precision_scale')));
                if (count($scales) !== 1 || $scales[0] === null) {
                    $this->fail("metrics.{$index}.input_codes", 'Raw sum inputs must use the same declared precision.');
                }
                if (($metric['unit'] !== null && $metric['unit'] !== $units[0])
                    || ($metric['precision_scale'] !== null && $metric['precision_scale'] !== $scales[0])) {
                    $this->fail("metrics.{$index}", 'A raw sum must preserve its inputs’ unit and precision.');
                }
                $metric['unit'] = $units[0];
                $metric['precision_scale'] = $scales[0];
                $boundsKnown = array_reduce(
                    $inputs,
                    static fn (bool $known, array $input): bool => $known
                        && $input['min_raw_value'] !== null
                        && $input['max_raw_value'] !== null,
                    true,
                );
                if ($boundsKnown) {
                    $minimum = BigDecimal::zero();
                    $maximum = BigDecimal::zero();
                    foreach ($inputs as $input) {
                        $minimum = $minimum->plus((string) $input['min_raw_value']);
                        $maximum = $maximum->plus((string) $input['max_raw_value']);
                    }
                    $metric['min_raw_value'] = $this->fixedDecimal((string) $minimum);
                    $metric['max_raw_value'] = $this->fixedDecimal((string) $maximum);
                }
            } else {
                if ($metric['unit'] !== $units[0]) {
                    $this->fail("metrics.{$index}.unit", 'Formula output must preserve its compatible input unit.');
                }
                $ranges = [];
                foreach ($inputsByCode as $inputCode => $input) {
                    if ($input['min_raw_value'] === null || $input['max_raw_value'] === null) {
                        $this->fail("metrics.{$index}.input_codes", 'Formula inputs require declared finite bounds.');
                    }
                    $ranges[$inputCode] = [
                        'min' => (string) $input['min_raw_value'],
                        'max' => (string) $input['max_raw_value'],
                    ];
                }
                try {
                    $bounds = $this->formulaEngine->bounds($metric['formula_ast_json'], $ranges, $metric['precision_scale']);
                } catch (\InvalidArgumentException $exception) {
                    $this->fail("metrics.{$index}.formula_expression", $exception->getMessage());
                }
                $metric['min_raw_value'] = $this->fixedDecimal($bounds['min']);
                $metric['max_raw_value'] = $this->fixedDecimal($bounds['max']);
            }
        }

        $states[$code] = 2;
        unset($metric);
    }

    /** @param list<array<string, mixed>> $subtests @return array<string, mixed> */
    private function findSubtest(array $subtests, string $code): array
    {
        foreach ($subtests as $subtest) {
            if ($subtest['code'] === $code) {
                return $subtest;
            }
        }

        $this->fail('metrics.subtest_code', 'Metric section must reference a subtest in this version.');
    }

    private function decimal(mixed $value, string $field, ?int $declaredScale): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = trim((string) $value);
        if (! preg_match('/^-?(?:0|[1-9][0-9]*)(?:\\.[0-9]+)?$/D', $text)) {
            $this->fail($field, 'Enter a decimal value without exponent notation.');
        }

        if (str_contains($text, '.')) {
            [$integer, $fraction] = explode('.', $text, 2);
            $fraction = rtrim($fraction, '0');
            $text = $fraction === '' ? $integer : $integer.'.'.$fraction;
        }

        try {
            $decimal = BigDecimal::of($text);
        } catch (NumberFormatException) {
            $this->fail($field, 'Enter a valid decimal value.');
        }

        if ($decimal->getScale() > 6
            || ($declaredScale !== null && $decimal->getScale() > $declaredScale)) {
            $this->fail($field, 'Value precision exceeds the declared metric precision.');
        }

        return $this->fixedDecimal((string) $decimal);
    }

    private function fixedDecimal(string $value): string
    {
        $fixed = (string) BigDecimal::of($value)->toScale(6, RoundingMode::Unnecessary);
        [$integerPart] = explode('.', $fixed, 2);
        $integerDigits = ltrim(ltrim($integerPart, '-'), '0');
        if (strlen($integerDigits) > 12) {
            $this->fail('metrics', 'Raw metric bounds exceed the supported fixed-decimal range.');
        }

        return $fixed;
    }

    private function assertPairedBounds(?string $minimum, ?string $maximum, int $index): void
    {
        if (($minimum === null) !== ($maximum === null)) {
            $this->fail("metrics.{$index}", 'Provide both raw bounds or leave both unavailable until the source is verified.');
        }
    }

    private function optionalCode(mixed $value): ?string
    {
        $code = strtoupper(trim((string) ($value ?? '')));
        if ($code === '') {
            return null;
        }
        if (! preg_match('/^[A-Z][A-Z0-9_-]{0,31}$/', $code)) {
            $this->fail('metrics.subtest_code', 'Use a valid stable subtest code.');
        }

        return $code;
    }

    private function requiredText(mixed $value, string $field): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            $this->fail($field, 'This field is required.');
        }

        return $text;
    }

    private function optionalText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    /**
     * @param  list<array<string, mixed>>  $metrics
     * @param  list<array<string, mixed>>  $subtests
     */
    private function validateOmrMetricDeclarations(array $metrics, array $subtests): void
    {
        $metricsByCode = [];
        foreach ($metrics as $metric) {
            $metricsByCode[$metric['code']] = $metric;
        }
        $subtestsByCode = [];
        foreach ($subtests as $subtest) {
            $subtestsByCode[$subtest['code']] = $subtest;
        }

        foreach ($metrics as $index => $metric) {
            $contract = $metric['input_contract_json'] ?? null;
            if (! is_array($contract) || ! in_array('omr', $contract['allowed_sources'], true)) {
                continue;
            }
            if ($metric['rule_kind'] === TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT) {
                $subtest = $subtestsByCode[$metric['subtest_code']] ?? null;
                if (($subtest['response_mode'] ?? null) !== 'keyed_items') {
                    $this->fail("metrics.{$index}.input_contract_json.allowed_sources", 'OMR item counts require a keyed-choice subtest.');
                }
                $scoredItems = array_values(array_filter(
                    $subtest['items'],
                    static fn (array $item): bool => $item['is_scored'],
                ));
                if ($scoredItems === []) {
                    $this->fail("metrics.{$index}.input_contract_json.allowed_sources", 'OMR item counts require at least one scored choice item.');
                }
                foreach ($subtest['items'] as $item) {
                    if (count($item['option_tokens']) < 2 || count($item['option_tokens']) > 10) {
                        $this->fail("metrics.{$index}.input_contract_json.allowed_sources", 'OMR item counts require two to ten exact response tokens on every keyed item.');
                    }
                }
            } elseif (in_array($metric['rule_kind'], [
                TestInstrumentMetric::RULE_SUM_RAW_METRICS,
                TestInstrumentMetric::RULE_FORMULA_RAW_METRICS,
            ], true) && ! $this->hasOmrChoiceDependency($metric, $metricsByCode, $subtestsByCode)) {
                $this->fail("metrics.{$index}.input_contract_json.allowed_sources", 'An OMR-derived metric requires at least one OMR-capable keyed-item input.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $metric
     * @param  array<string, array<string, mixed>>  $metricsByCode
     * @param  array<string, array<string, mixed>>  $subtestsByCode
     */
    private function hasOmrChoiceDependency(array $metric, array $metricsByCode, array $subtestsByCode): bool
    {
        foreach ($metric['input_codes'] as $inputCode) {
            $input = $metricsByCode[$inputCode] ?? null;
            if ($input === null) {
                continue;
            }

            if ($input['rule_kind'] === TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT) {
                $contract = $input['input_contract_json'] ?? null;
                $subtest = $subtestsByCode[$input['subtest_code']] ?? null;
                if (is_array($contract)
                    && in_array('omr', $contract['allowed_sources'], true)
                    && ($subtest['response_mode'] ?? null) === 'keyed_items') {
                    return true;
                }
            } elseif (in_array($input['rule_kind'], [
                TestInstrumentMetric::RULE_SUM_RAW_METRICS,
                TestInstrumentMetric::RULE_FORMULA_RAW_METRICS,
            ], true) && $this->hasOmrChoiceDependency($input, $metricsByCode, $subtestsByCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $allowedValues
     * @return list<string>
     */
    private function normalizeClosedStringList(mixed $values, array $allowedValues, string $field): array
    {
        if (! is_array($values) || ! array_is_list($values)) {
            $this->fail($field, 'Use a list containing only supported unique declarations.');
        }

        $normalized = [];
        foreach ($values as $index => $value) {
            if (! is_string($value) || ! in_array($value, $allowedValues, true)
                || in_array($value, $normalized, true)) {
                $this->fail("{$field}.{$index}", 'Use a unique value from the closed declaration vocabulary.');
            }
            $normalized[] = $value;
        }

        sort($normalized, SORT_STRING);

        return $normalized;
    }

    private function normalizeMaterialRequirement(mixed $value): string
    {
        $requirement = strtolower(trim((string) ($value ?? '')));
        if ($requirement === '') {
            return TestInstrumentVersion::MATERIAL_REQUIREMENT_UNRESOLVED;
        }

        if (! in_array($requirement, TestInstrumentVersion::MATERIAL_REQUIREMENTS, true)) {
            $this->fail('profile.material_requirement', 'Choose unresolved, paper-backed, or no-paper applicability.');
        }

        return $requirement;
    }

    private function boundedOptionalText(mixed $value, string $field, int $maximumLength): ?string
    {
        $text = $this->optionalText($value);
        if ($text !== null && mb_strlen($text) > $maximumLength) {
            $this->fail($field, "This field may not exceed {$maximumLength} characters.");
        }

        return $text;
    }

    private function sameDecimal(?string $left, ?string $right): bool
    {
        if ($left === null || $right === null) {
            return $left === $right;
        }

        return BigDecimal::of($left)->compareTo(BigDecimal::of($right)) === 0;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
