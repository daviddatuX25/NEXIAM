<?php

declare(strict_types=1);

namespace App\Assessment\Support;

use App\Models\Assessment\TestInstrumentMetric;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final class AssessmentRawEvaluator
{
    public const KERNEL_VERSION = 'nexiam.assessment.raw-evaluator.v1';

    private const RULE_CORRECT_ITEM_COUNT = 'correct_item_count';

    private const RULE_RECORDED_MEASURE = 'recorded_measure';

    private const RULE_SUM_RAW_METRICS = 'sum_raw_metrics';

    private const RULE_FORMULA_RAW_METRICS = 'formula_raw_metrics';

    private const CONTRACT_INVALID = 'assessment_raw_contract_invalid';

    private const OBSERVATIONS_INVALID = 'assessment_raw_observations_invalid';

    private const MEASURES_INVALID = 'assessment_raw_measures_invalid';

    public function __construct(private readonly RawMetricFormulaEngine $formulaEngine = new RawMetricFormulaEngine) {}

    /**
     * Evaluate the exact profile-scoped item map and raw metric contract.
     *
     * A complete evaluation returns metric values in contract order and item results in physical
     * global-position order. Metric values are decimal strings rendered at each metric's declared
     * scale. Optional measures and sums whose optional inputs are absent are omitted, never
     * zero-filled. Practice items retain `is_scored: false`, `correct: false`, and a zero
     * contribution because they deliberately have no answer key.
     *
     * @param  array<string, mixed>  $contract
     * @param  array<mixed>  $observations  `token` must be present; null means a confirmed blank only.
     * @param  array<mixed>  $recordedMeasures
     * @return array{
     *     metric_values: list<array{metric_id: int, value: string, input_metric_ids: list<int>}>,
     *     item_results: list<array{item_id: int, page_no: int, native_label: string, is_scored: bool, correct: bool, contribution: int}>
     * }
     *
     * @throws InvalidArgumentException When the contract, map, observations, or recorded measures are incomplete, ambiguous, unsupported, or inconsistent.
     */
    public function evaluate(array $contract, array $observations, array $recordedMeasures): array
    {
        $contractKeys = array_diff(array_keys($contract), ['geometry']);
        $this->assertExactKeys(array_intersect_key($contract, array_flip($contractKeys)), ['profile_version_id', 'content_sha256', 'items', 'bindings', 'metrics'], self::CONTRACT_INVALID);
        if (! $this->isPositiveInteger($contract['profile_version_id'])
            || ! is_string($contract['content_sha256'])
            || ! preg_match('/^[a-f0-9]{64}$/D', $contract['content_sha256'])) {
            $this->reject(self::CONTRACT_INVALID);
        }

        $itemsById = $this->indexItems($contract['items']);
        $bindings = $this->indexBindings($contract['bindings'], $itemsById);
        [$metricsById, $metricOrder] = $this->indexMetrics($contract['metrics'], $itemsById);
        $this->validateMetricGraphs($metricsById);

        $observedTokensByItem = $this->indexObservations($observations, $bindings);
        $measureValuesByMetric = $this->indexRecordedMeasures($recordedMeasures, $metricsById);
        [$itemResults, $correctCountsBySubtest] = $this->buildItemResults($bindings, $itemsById, $observedTokensByItem);
        $correctItemIds = [];
        foreach ($itemResults as $itemResult) {
            if ($itemResult['is_scored'] && $itemResult['correct']) {
                $correctItemIds[$itemResult['item_id']] = true;
            }
        }

        $evaluatedValuesByMetric = [];
        foreach ($metricOrder as $metricId) {
            $this->evaluateMetric(
                $metricId,
                $metricsById,
                $correctCountsBySubtest,
                $correctItemIds,
                $measureValuesByMetric,
                $evaluatedValuesByMetric,
            );
        }

        $metricValues = [];
        foreach ($metricOrder as $metricId) {
            $value = $evaluatedValuesByMetric[$metricId];
            if ($value === null) {
                continue;
            }

            $metricValues[] = [
                'metric_id' => $metricId,
                'value' => $this->formatAtScale($value, $metricsById[$metricId]['precision_scale']),
                'input_metric_ids' => $metricsById[$metricId]['input_metric_ids'],
            ];
        }

        return [
            'metric_values' => $metricValues,
            'item_results' => $itemResults,
        ];
    }

    /**
     * @return list<mixed>
     */
    private function requireList(mixed $rows, string $error): array
    {
        if (! is_array($rows) || ! array_is_list($rows)) {
            $this->reject($error);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function assertExactKeys(array $row, array $keys, string $error): void
    {
        if (count($row) !== count($keys)) {
            $this->reject($error);
        }

        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                $this->reject($error);
            }
        }

        foreach (array_keys($row) as $key) {
            if (! in_array((string) $key, $keys, true)) {
                $this->reject($error);
            }
        }
    }

    private function isPositiveInteger(mixed $value): bool
    {
        return is_int($value) && $value > 0;
    }

    private function hasExactToken(string $token, array $tokens): bool
    {
        return in_array($token, $tokens, true);
    }

    /**
     * @return array<int, array{item_id: int, subtest_id: int, is_scored: bool, option_tokens: list<string>}>
     */
    private function indexItems(mixed $rows): array
    {
        $rows = $this->requireList($rows, self::CONTRACT_INVALID);
        $itemsById = [];

        foreach ($rows as $item) {
            if (! is_array($item)) {
                $this->reject(self::CONTRACT_INVALID);
            }
            $this->assertExactKeys($item, ['item_id', 'subtest_id', 'is_scored', 'option_tokens'], self::CONTRACT_INVALID);

            $itemId = $item['item_id'];
            $subtestId = $item['subtest_id'];
            $optionTokens = $item['option_tokens'];
            if (! $this->isPositiveInteger($itemId)
                || ! $this->isPositiveInteger($subtestId)
                || ! is_bool($item['is_scored'])
                || ! is_array($optionTokens)
                || ! array_is_list($optionTokens)
                || count($optionTokens) < 2
                || isset($itemsById[$itemId])) {
                $this->reject(self::CONTRACT_INVALID);
            }

            $seenTokens = [];
            foreach ($optionTokens as $token) {
                if (! is_string($token) || $token === '' || isset($seenTokens[$token])) {
                    $this->reject(self::CONTRACT_INVALID);
                }
                $seenTokens[$token] = true;
            }

            $itemsById[$itemId] = [
                'item_id' => $itemId,
                'subtest_id' => $subtestId,
                'is_scored' => $item['is_scored'],
                'option_tokens' => $optionTokens,
            ];
        }

        return $itemsById;
    }

    /**
     * @param  array<int, array{item_id: int, subtest_id: int, is_scored: bool, option_tokens: list<string>}>  $itemsById
     * @return list<array{page_no: int, native_label: string, global_position: int, sheet_item_id: int, item_id: int, subtest_id: int, option_tokens: list<string>, correct_token: ?string}>
     */
    private function indexBindings(mixed $rows, array $itemsById): array
    {
        $rows = $this->requireList($rows, self::CONTRACT_INVALID);
        $bindings = [];
        $itemsBySourceId = [];
        $sheetItemIds = [];
        $globalPositions = [];
        $destinations = [];

        foreach ($rows as $binding) {
            if (! is_array($binding)) {
                $this->reject(self::CONTRACT_INVALID);
            }
            $this->assertExactKeys($binding, [
                'page_no', 'native_label', 'global_position', 'sheet_item_id', 'item_id',
                'subtest_id', 'option_tokens', 'correct_token',
            ], self::CONTRACT_INVALID);

            $pageNo = $binding['page_no'];
            $nativeLabel = $binding['native_label'];
            $globalPosition = $binding['global_position'];
            $sheetItemId = $binding['sheet_item_id'];
            $itemId = $binding['item_id'];
            $subtestId = $binding['subtest_id'];
            $optionTokens = $binding['option_tokens'];
            if (! $this->isPositiveInteger($pageNo)
                || ! is_string($nativeLabel)
                || $nativeLabel === ''
                || ! $this->isPositiveInteger($globalPosition)
                || ! $this->isPositiveInteger($sheetItemId)
                || ! $this->isPositiveInteger($itemId)
                || ! $this->isPositiveInteger($subtestId)
                || ! is_array($optionTokens)
                || ! array_is_list($optionTokens)
                || isset($itemsBySourceId[$itemId])
                || isset($sheetItemIds[$sheetItemId])
                || isset($globalPositions[$globalPosition])
                || isset($destinations[$pageNo][$nativeLabel])) {
                $this->reject(self::CONTRACT_INVALID);
            }

            $item = $itemsById[$itemId] ?? null;
            if ($item === null
                || $item['subtest_id'] !== $subtestId
                || $item['option_tokens'] !== $optionTokens) {
                $this->reject(self::CONTRACT_INVALID);
            }

            $correctToken = $binding['correct_token'];
            if ($item['is_scored']) {
                if (! is_string($correctToken) || ! $this->hasExactToken($correctToken, $optionTokens)) {
                    $this->reject(self::CONTRACT_INVALID);
                }
            } elseif ($correctToken !== null) {
                $this->reject(self::CONTRACT_INVALID);
            }

            $normalizedBinding = [
                'page_no' => $pageNo,
                'native_label' => $nativeLabel,
                'global_position' => $globalPosition,
                'sheet_item_id' => $sheetItemId,
                'item_id' => $itemId,
                'subtest_id' => $subtestId,
                'option_tokens' => $optionTokens,
                'correct_token' => $correctToken,
            ];
            $bindings[] = $normalizedBinding;
            $itemsBySourceId[$itemId] = true;
            $sheetItemIds[$sheetItemId] = true;
            $globalPositions[$globalPosition] = true;
            $destinations[$pageNo][$nativeLabel] = true;
        }

        if (count($bindings) !== count($itemsById)) {
            $this->reject(self::CONTRACT_INVALID);
        }

        return $bindings;
    }

    /**
     * @param  list<array{page_no: int, native_label: string, global_position: int, sheet_item_id: int, item_id: int, subtest_id: int, option_tokens: list<string>, correct_token: ?string}>  $bindings
     * @return array<int, ?string>
     */
    private function indexObservations(mixed $rows, array $bindings): array
    {
        $rows = $this->requireList($rows, self::OBSERVATIONS_INVALID);
        $bindingsByDestination = [];
        foreach ($bindings as $binding) {
            $bindingsByDestination[$binding['page_no']][$binding['native_label']] = $binding;
        }

        $observedTokensByItem = [];
        $observedCount = 0;
        foreach ($rows as $observation) {
            if (! is_array($observation)) {
                $this->reject(self::OBSERVATIONS_INVALID);
            }
            $this->assertExactKeys($observation, ['page_no', 'native_label', 'token'], self::OBSERVATIONS_INVALID);

            $pageNo = $observation['page_no'];
            $nativeLabel = $observation['native_label'];
            $token = $observation['token'];
            if (! $this->isPositiveInteger($pageNo)
                || ! is_string($nativeLabel)
                || $nativeLabel === ''
                || ($token !== null && (! is_string($token) || $token === ''))
                || isset($observedDestinations[$pageNo][$nativeLabel])) {
                $this->reject(self::OBSERVATIONS_INVALID);
            }

            $binding = $bindingsByDestination[$pageNo][$nativeLabel] ?? null;
            if ($binding === null
                || ($token !== null && ! $this->hasExactToken($token, $binding['option_tokens']))) {
                $this->reject(self::OBSERVATIONS_INVALID);
            }

            $observedTokensByItem[$binding['item_id']] = $token;
            $observedDestinations[$pageNo][$nativeLabel] = true;
            $observedCount++;
        }

        if ($observedCount !== count($bindings)) {
            $this->reject(self::OBSERVATIONS_INVALID);
        }

        foreach ($bindings as $binding) {
            if (! array_key_exists($binding['item_id'], $observedTokensByItem)) {
                $this->reject(self::OBSERVATIONS_INVALID);
            }
        }

        return $observedTokensByItem;
    }

    /**
     * @param  array<int, array<string, mixed>>  $itemsById
     * @return array{0: array<int, array<string, mixed>>, 1: list<int>}
     */
    private function indexMetrics(mixed $rows, array $itemsById): array
    {
        $rows = $this->requireList($rows, self::CONTRACT_INVALID);
        if ($rows === []) {
            $this->reject(self::CONTRACT_INVALID);
        }

        $metricsById = [];
        $metricOrder = [];
        $codes = [];
        foreach ($rows as $metric) {
            if (! is_array($metric)) {
                $this->reject(self::CONTRACT_INVALID);
            }
            $metricKeys = [
                'metric_id', 'code', 'rule_kind', 'subtest_id', 'unit', 'min_raw_value',
                'max_raw_value', 'precision_scale', 'is_required', 'input_metric_ids', 'procedure_reference',
            ];
            foreach (['formula_expression', 'formula_ast_json', 'formula_algorithm_identity', 'item_codes', 'item_ids'] as $optionalKey) {
                if (array_key_exists($optionalKey, $metric)) {
                    $metricKeys[] = $optionalKey;
                }
            }
            $this->assertExactKeys($metric, $metricKeys, self::CONTRACT_INVALID);

            $metricId = $metric['metric_id'];
            $code = $metric['code'];
            $ruleKind = $metric['rule_kind'];
            $subtestId = $metric['subtest_id'];
            $unit = $metric['unit'];
            $scale = $metric['precision_scale'];
            $inputMetricIds = $metric['input_metric_ids'];
            $procedureReference = $metric['procedure_reference'];
            if (! $this->isPositiveInteger($metricId)
                || ! is_string($code)
                || trim($code) === ''
                || isset($codes[$code])
                || isset($metricsById[$metricId])
                || ! in_array($ruleKind, [self::RULE_CORRECT_ITEM_COUNT, self::RULE_RECORDED_MEASURE, self::RULE_SUM_RAW_METRICS, self::RULE_FORMULA_RAW_METRICS], true)
                || ($subtestId !== null && ! $this->isPositiveInteger($subtestId))
                || ! is_string($unit)
                || trim($unit) === ''
                || ! is_int($scale)
                || $scale < 0
                || $scale > 6
                || ! is_bool($metric['is_required'])
                || ! is_array($inputMetricIds)
                || ! array_is_list($inputMetricIds)
                || ($procedureReference !== null && ! is_string($procedureReference))) {
                $this->reject(self::CONTRACT_INVALID);
            }

            $seenInputMetricIds = [];
            foreach ($inputMetricIds as $inputMetricId) {
                if (! $this->isPositiveInteger($inputMetricId) || isset($seenInputMetricIds[$inputMetricId])) {
                    $this->reject(self::CONTRACT_INVALID);
                }
                $seenInputMetricIds[$inputMetricId] = true;
            }

            $minimum = $this->parseDecimal($metric['min_raw_value'], self::CONTRACT_INVALID);
            $maximum = $this->parseDecimal($metric['max_raw_value'], self::CONTRACT_INVALID);
            if ($minimum->compareTo($maximum) > 0
                || $this->significantScale($metric['min_raw_value']) > $scale
                || $this->significantScale($metric['max_raw_value']) > $scale) {
                $this->reject(self::CONTRACT_INVALID);
            }

            $selectedItemIds = null;
            if ($ruleKind === self::RULE_SUM_RAW_METRICS) {
                if ($subtestId !== null || $procedureReference !== null || count($inputMetricIds) < 2) {
                    $this->reject(self::CONTRACT_INVALID);
                }
            } elseif ($ruleKind === self::RULE_FORMULA_RAW_METRICS) {
                if ($subtestId !== null || $procedureReference !== null || $inputMetricIds === []
                    || ! is_string($metric['formula_expression'] ?? null)
                    || ! is_array($metric['formula_ast_json'] ?? null)
                    || ($metric['formula_algorithm_identity'] ?? null) !== TestInstrumentMetric::FORMULA_ALGORITHM_IDENTITY
                    || array_key_exists('item_codes', $metric)
                    || array_key_exists('item_ids', $metric)) {
                    $this->reject(self::CONTRACT_INVALID);
                }
            } elseif ($ruleKind === self::RULE_CORRECT_ITEM_COUNT) {
                if ($subtestId === null || $procedureReference !== null || $inputMetricIds !== [] || $unit !== 'count' || $scale !== 0) {
                    $this->reject(self::CONTRACT_INVALID);
                }
                if (array_key_exists('item_codes', $metric) !== array_key_exists('item_ids', $metric)) {
                    $this->reject(self::CONTRACT_INVALID);
                }
                if (array_key_exists('item_codes', $metric)) {
                    $codesList = $metric['item_codes'];
                    $selectedItemIds = $metric['item_ids'];
                    if (! is_array($codesList) || ! array_is_list($codesList) || $codesList === []
                        || ! is_array($selectedItemIds) || ! array_is_list($selectedItemIds)
                        || count($codesList) !== count($selectedItemIds)) {
                        $this->reject(self::CONTRACT_INVALID);
                    }
                    $seenItemIds = [];
                    foreach ($selectedItemIds as $selectedId) {
                        $item = $itemsById[$selectedId] ?? null;
                        if (! $this->isPositiveInteger($selectedId) || isset($seenItemIds[$selectedId])
                            || $item === null || $item['subtest_id'] !== $subtestId || ! $item['is_scored']) {
                            $this->reject(self::CONTRACT_INVALID);
                        }
                        $seenItemIds[$selectedId] = true;
                    }
                }
                $scoredItemCount = $selectedItemIds === null ? 0 : count($selectedItemIds);
                if ($selectedItemIds === null) {
                    foreach ($itemsById as $item) {
                        if ($item['subtest_id'] === $subtestId && $item['is_scored']) {
                            $scoredItemCount++;
                        }
                    }
                }
                if ($scoredItemCount < 1 || $minimum->compareTo('0') !== 0
                    || $maximum->compareTo((string) $scoredItemCount) !== 0) {
                    $this->reject(self::CONTRACT_INVALID);
                }
            } else {
                if ($subtestId === null || $procedureReference === null || trim($procedureReference) === '' || $inputMetricIds !== []) {
                    $this->reject(self::CONTRACT_INVALID);
                }
            }

            $metricsById[$metricId] = [
                'metric_id' => $metricId,
                'code' => $code,
                'rule_kind' => $ruleKind,
                'subtest_id' => $subtestId,
                'unit' => $unit,
                'min_raw_value' => $metric['min_raw_value'],
                'max_raw_value' => $metric['max_raw_value'],
                'min_decimal' => $minimum,
                'max_decimal' => $maximum,
                'precision_scale' => $scale,
                'is_required' => $metric['is_required'],
                'input_metric_ids' => $inputMetricIds,
                'procedure_reference' => $procedureReference,
            ];
            if ($ruleKind === self::RULE_FORMULA_RAW_METRICS) {
                $metricsById[$metricId]['formula_expression'] = $metric['formula_expression'];
                $metricsById[$metricId]['formula_ast_json'] = $metric['formula_ast_json'];
                $metricsById[$metricId]['formula_algorithm_identity'] = $metric['formula_algorithm_identity'];
            }
            if ($selectedItemIds !== null) {
                $metricsById[$metricId]['item_codes'] = $metric['item_codes'];
                $metricsById[$metricId]['item_ids'] = $selectedItemIds;
            }
            $metricOrder[] = $metricId;
            $codes[$code] = true;
        }

        return [$metricsById, $metricOrder];
    }

    /**
     * @param  array<int, array<string, mixed>>  $metricsById
     */
    private function validateMetricGraphs(array $metricsById): void
    {
        $states = [];
        foreach (array_keys($metricsById) as $metricId) {
            $this->validateMetricGraph((int) $metricId, $metricsById, $states);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $metricsById
     * @param  array<int, int>  $states
     */
    private function validateMetricGraph(int $metricId, array $metricsById, array &$states): void
    {
        if (($states[$metricId] ?? 0) === 2) {
            return;
        }
        if (($states[$metricId] ?? 0) === 1) {
            $this->reject(self::CONTRACT_INVALID);
        }

        $states[$metricId] = 1;
        $metric = $metricsById[$metricId];
        if ($metric['rule_kind'] === self::RULE_SUM_RAW_METRICS) {
            $minimums = [];
            $maximums = [];
            foreach ($metric['input_metric_ids'] as $inputMetricId) {
                if (! isset($metricsById[$inputMetricId])) {
                    $this->reject(self::CONTRACT_INVALID);
                }

                $input = $metricsById[$inputMetricId];
                if ($input['unit'] !== $metric['unit']
                    || $input['precision_scale'] !== $metric['precision_scale']) {
                    $this->reject(self::CONTRACT_INVALID);
                }

                $this->validateMetricGraph($inputMetricId, $metricsById, $states);
                $minimums[] = $input['min_decimal'];
                $maximums[] = $input['max_decimal'];
            }

            if ($this->sumDecimals($minimums)->compareTo($metric['min_decimal']) !== 0
                || $this->sumDecimals($maximums)->compareTo($metric['max_decimal']) !== 0) {
                $this->reject(self::CONTRACT_INVALID);
            }
        } elseif ($metric['rule_kind'] === self::RULE_FORMULA_RAW_METRICS) {
            $ranges = [];
            $valuesByCode = [];
            foreach ($metric['input_metric_ids'] as $inputMetricId) {
                $input = $metricsById[$inputMetricId] ?? null;
                if ($input === null || $input['unit'] !== $metric['unit'] || $input['min_raw_value'] === null || $input['max_raw_value'] === null) {
                    $this->reject(self::CONTRACT_INVALID);
                }
                $this->validateMetricGraph($inputMetricId, $metricsById, $states);
                $ranges[$input['code']] = ['min' => $input['min_raw_value'], 'max' => $input['max_raw_value']];
                $valuesByCode[$input['code']] = $input;
            }
            try {
                $expectedAst = $this->formulaEngine->compile($metric['formula_expression'], array_keys($valuesByCode));
                $variables = $this->formulaEngine->variables($metric['formula_ast_json']);
                $expectedVariables = array_keys($valuesByCode);
                sort($expectedVariables, SORT_STRING);
                if ($expectedAst !== $metric['formula_ast_json'] || $variables !== $expectedVariables) {
                    $this->reject(self::CONTRACT_INVALID);
                }
                $bounds = $this->formulaEngine->bounds($metric['formula_ast_json'], $ranges, $metric['precision_scale']);
            } catch (InvalidArgumentException) {
                $this->reject(self::CONTRACT_INVALID);
            }
            if (BigDecimal::of($bounds['min'])->compareTo($metric['min_decimal']) !== 0
                || BigDecimal::of($bounds['max'])->compareTo($metric['max_decimal']) !== 0) {
                $this->reject(self::CONTRACT_INVALID);
            }
        }

        $states[$metricId] = 2;
    }

    /**
     * @param  array<int, array<string, mixed>>  $metricsById
     * @return array<int, BigDecimal>
     */
    private function indexRecordedMeasures(mixed $rows, array $metricsById): array
    {
        $rows = $this->requireList($rows, self::MEASURES_INVALID);
        $measuresByMetric = [];
        foreach ($rows as $measure) {
            if (! is_array($measure)) {
                $this->reject(self::MEASURES_INVALID);
            }
            $this->assertExactKeys($measure, ['metric_id', 'value', 'procedure_reference'], self::MEASURES_INVALID);

            $metricId = $measure['metric_id'];
            if (! $this->isPositiveInteger($metricId)
                || isset($measuresByMetric[$metricId])
                || ! isset($metricsById[$metricId])
                || $metricsById[$metricId]['rule_kind'] !== self::RULE_RECORDED_MEASURE
                || ! is_string($measure['value'])
                || ! is_string($measure['procedure_reference'])
                || $measure['procedure_reference'] !== $metricsById[$metricId]['procedure_reference']) {
                $this->reject(self::MEASURES_INVALID);
            }

            $value = $this->parseDecimal($measure['value'], self::MEASURES_INVALID);
            $metric = $metricsById[$metricId];
            if ($value->getScale() > $metric['precision_scale']
                || $value->compareTo($metric['min_decimal']) < 0
                || $value->compareTo($metric['max_decimal']) > 0) {
                $this->reject(self::MEASURES_INVALID);
            }

            $measuresByMetric[$metricId] = $value;
        }

        foreach ($metricsById as $metricId => $metric) {
            if ($metric['rule_kind'] === self::RULE_RECORDED_MEASURE
                && $metric['is_required']
                && ! array_key_exists($metricId, $measuresByMetric)) {
                $this->reject(self::MEASURES_INVALID);
            }
        }

        return $measuresByMetric;
    }

    private function parseDecimal(mixed $value, string $error): BigDecimal
    {
        if (! is_string($value)
            || ! preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D', $value)) {
            $this->reject($error);
        }

        try {
            $decimal = BigDecimal::of($value);
        } catch (NumberFormatException) {
            $this->reject($error);
        }

        if ($decimal->getScale() > 6 || $this->integerDigits($decimal) > 12) {
            $this->reject($error);
        }

        return $decimal;
    }

    private function significantScale(string $value): int
    {
        $fraction = explode('.', $value, 2)[1] ?? '';

        return strlen(rtrim($fraction, '0'));
    }

    private function integerDigits(BigDecimal $decimal): int
    {
        $integer = explode('.', (string) $decimal, 2)[0];
        $integer = ltrim($integer, '-');

        return strlen(ltrim($integer, '0'));
    }

    /**
     * @param  list<BigDecimal>  $values
     */
    private function sumDecimals(array $values): BigDecimal
    {
        $sum = BigDecimal::zero();
        foreach ($values as $value) {
            $sum = $sum->plus($value);
        }

        return $sum;
    }

    /**
     * @param  list<array{page_no: int, native_label: string, global_position: int, sheet_item_id: int, item_id: int, subtest_id: int, option_tokens: list<string>, correct_token: ?string}>  $bindings
     * @param  array<int, array{item_id: int, subtest_id: int, is_scored: bool, option_tokens: list<string>}>  $itemsById
     * @param  array<int, ?string>  $observedTokensByItem
     * @return array{0: list<array{item_id: int, page_no: int, native_label: string, is_scored: bool, correct: bool, contribution: int}>, 1: array<int, int>}
     */
    private function buildItemResults(array $bindings, array $itemsById, array $observedTokensByItem): array
    {
        usort($bindings, static fn (array $left, array $right): int => $left['global_position'] <=> $right['global_position']);
        $itemResults = [];
        $correctCountsBySubtest = [];

        foreach ($bindings as $binding) {
            $item = $itemsById[$binding['item_id']];
            $observedToken = $observedTokensByItem[$binding['item_id']];
            $isCorrect = $item['is_scored']
                && $observedToken !== null
                && $observedToken === $binding['correct_token'];
            $contribution = $isCorrect ? 1 : 0;
            if ($isCorrect) {
                $subtestId = $item['subtest_id'];
                $correctCountsBySubtest[$subtestId] = ($correctCountsBySubtest[$subtestId] ?? 0) + 1;
            }

            $itemResults[] = [
                'item_id' => $item['item_id'],
                'page_no' => $binding['page_no'],
                'native_label' => $binding['native_label'],
                'is_scored' => $item['is_scored'],
                'correct' => $isCorrect,
                'contribution' => $contribution,
            ];
        }

        return [$itemResults, $correctCountsBySubtest];
    }

    /**
     * @param  array<int, array<string, mixed>>  $metricsById
     * @param  array<int, int>  $correctCountsBySubtest
     * @param  array<int, BigDecimal>  $measureValuesByMetric
     * @param  array<int, ?BigDecimal>  $evaluatedValuesByMetric
     */
    private function evaluateMetric(
        int $metricId,
        array $metricsById,
        array $correctCountsBySubtest,
        array $correctItemIds,
        array $measureValuesByMetric,
        array &$evaluatedValuesByMetric,
    ): ?BigDecimal {
        if (array_key_exists($metricId, $evaluatedValuesByMetric)) {
            return $evaluatedValuesByMetric[$metricId];
        }

        $metric = $metricsById[$metricId];
        if ($metric['rule_kind'] === self::RULE_CORRECT_ITEM_COUNT) {
            if (isset($metric['item_ids'])) {
                $count = 0;
                foreach ($metric['item_ids'] as $itemId) {
                    $count += isset($correctItemIds[$itemId]) ? 1 : 0;
                }
                $value = BigDecimal::of((string) $count);
            } else {
                $value = BigDecimal::of((string) ($correctCountsBySubtest[$metric['subtest_id']] ?? 0));
            }
        } elseif ($metric['rule_kind'] === self::RULE_RECORDED_MEASURE) {
            $value = $measureValuesByMetric[$metricId] ?? null;
            if ($value === null && $metric['is_required']) {
                $this->reject(self::MEASURES_INVALID);
            }
        } else {
            $inputs = [];
            $inputValuesByCode = [];
            $allInputsAvailable = true;
            foreach ($metric['input_metric_ids'] as $inputMetricId) {
                $inputValue = $this->evaluateMetric(
                    $inputMetricId,
                    $metricsById,
                    $correctCountsBySubtest,
                    $correctItemIds,
                    $measureValuesByMetric,
                    $evaluatedValuesByMetric,
                );
                if ($inputValue === null) {
                    $allInputsAvailable = false;
                    break;
                }
                $inputs[] = $inputValue;
                $inputValuesByCode[$metricsById[$inputMetricId]['code']] = (string) $inputValue;
            }

            if (! $allInputsAvailable) {
                if ($metric['is_required']) {
                    $this->reject(self::MEASURES_INVALID);
                }
                $evaluatedValuesByMetric[$metricId] = null;

                return null;
            }

            $value = $metric['rule_kind'] === self::RULE_FORMULA_RAW_METRICS
                ? BigDecimal::of($this->formulaEngine->evaluate($metric['formula_ast_json'], $inputValuesByCode, $metric['precision_scale']))
                : $this->sumDecimals($inputs);
        }

        if ($value !== null
            && ($value->getScale() > $metric['precision_scale']
                || $value->compareTo($metric['min_decimal']) < 0
                || $value->compareTo($metric['max_decimal']) > 0)) {
            $this->reject($metric['rule_kind'] === self::RULE_RECORDED_MEASURE ? self::MEASURES_INVALID : self::CONTRACT_INVALID);
        }

        $evaluatedValuesByMetric[$metricId] = $value;

        return $value;
    }

    private function formatAtScale(BigDecimal $value, int $scale): string
    {
        return (string) $value->toScale($scale, RoundingMode::Unnecessary);
    }

    private function reject(string $reason): never
    {
        throw new InvalidArgumentException($reason);
    }
}
