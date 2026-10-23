<?php

namespace App\Assessment\Support;

use App\Models\Assessment\TestInstrumentItem;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentMetricInput;
use App\Models\Assessment\TestInstrumentSubtest;
use App\Models\Assessment\TestInstrumentVersion;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class TestInstrumentDraftWriter
{
    /**
     * @param array{
     *     profile: array<string, string|null>,
     *     subtests: list<array<string, mixed>>,
     *     metrics: list<array<string, mixed>>
     * } $normalized
     */
    public function save(TestInstrumentVersion $version, array $normalized): void
    {
        $profile = $normalized['profile'];
        $version->display_name = $profile['display_name'];
        $version->publisher_name = $profile['publisher_name'];
        $version->form_code = $profile['form_code'];
        $version->edition = $profile['edition'];
        $version->language = $profile['language'];
        $version->source_reference = $profile['source_reference'];
        $version->permitted_use_reference = $profile['permitted_use_reference'];
        $version->material_requirement = $profile['material_requirement'];
        $version->administration_procedure_reference = $profile['administration_procedure_reference'];
        $version->save();

        $this->replaceChildren($version, $normalized);
    }

    /**
     * @param array{
     *     profile: array<string, string|null>,
     *     subtests: list<array<string, mixed>>,
     *     metrics: list<array<string, mixed>>
     * } $normalized
     */
    private function replaceChildren(TestInstrumentVersion $version, array $normalized): void
    {
        $versionId = (int) $version->getKey();
        TestInstrumentMetricInput::query()->where('test_instrument_version_id', $versionId)->delete();
        TestInstrumentItem::query()->where('test_instrument_version_id', $versionId)->delete();
        TestInstrumentMetric::query()->where('test_instrument_version_id', $versionId)->delete();
        TestInstrumentSubtest::query()->where('test_instrument_version_id', $versionId)->delete();

        $subtestsByCode = [];
        foreach ($normalized['subtests'] as $subtestData) {
            $subtest = TestInstrumentSubtest::query()->create([
                'test_instrument_version_id' => $versionId,
                'code' => $subtestData['code'],
                'name' => $subtestData['name'],
                'display_order' => $subtestData['display_order'],
                'response_mode' => $subtestData['response_mode'],
                'item_order_policy' => $subtestData['item_order_policy'],
            ]);
            $subtestsByCode[$subtestData['code']] = $subtest;

            foreach ($subtestData['items'] as $item) {
                TestInstrumentItem::query()->create([
                    'test_instrument_version_id' => $versionId,
                    'test_instrument_subtest_id' => $subtest->id,
                    'item_code' => $item['item_code'],
                    'local_number' => $item['local_number'],
                    'display_order' => $item['display_order'],
                    'is_scored' => $item['is_scored'],
                    'option_tokens' => $item['option_tokens'],
                ]);
            }
        }

        $metricsByCode = [];
        foreach ($normalized['metrics'] as $metricData) {
            $subtest = $metricData['subtest_code'] === null
                ? null
                : ($subtestsByCode[$metricData['subtest_code']] ?? null);
            if ($metricData['subtest_code'] !== null && $subtest === null) {
                throw new ModelNotFoundException('The selected subtest does not belong to this draft version.');
            }

            $metric = TestInstrumentMetric::query()->create([
                'test_instrument_version_id' => $versionId,
                'code' => $metricData['code'],
                'name' => $metricData['name'],
                'rule_kind' => $metricData['rule_kind'],
                'test_instrument_subtest_id' => $subtest?->id,
                'unit' => $metricData['unit'],
                'min_raw_value' => $metricData['min_raw_value'],
                'max_raw_value' => $metricData['max_raw_value'],
                'precision_scale' => $metricData['precision_scale'],
                'is_required' => $metricData['is_required'],
                'required_output_scales' => $metricData['required_output_scales'],
                'procedure_reference' => $metricData['procedure_reference'],
                'construct_aptitude_area_id' => $metricData['construct_aptitude_area_id'],
                'input_contract_json' => $metricData['input_contract_json'] ?? null,
                ...(isset($metricData['formula_expression']) ? ['formula_expression' => $metricData['formula_expression']] : []),
                ...(isset($metricData['formula_ast_json']) ? ['formula_ast_json' => $metricData['formula_ast_json']] : []),
                ...(isset($metricData['item_codes']) ? ['item_codes_json' => $metricData['item_codes']] : []),
            ]);
            $metricsByCode[$metricData['code']] = [$metric, $metricData['input_codes']];
        }

        foreach ($metricsByCode as [$metric, $inputCodes]) {
            foreach ($inputCodes as $position => $inputCode) {
                $inputMetric = $metricsByCode[$inputCode][0] ?? null;
                if (! $inputMetric instanceof TestInstrumentMetric) {
                    throw new ModelNotFoundException('A derived metric input does not belong to this draft version.');
                }

                TestInstrumentMetricInput::query()->create([
                    'test_instrument_version_id' => $versionId,
                    'metric_id' => $metric->id,
                    'input_metric_id' => $inputMetric->id,
                    'position' => $position + 1,
                ]);
            }
        }
    }
}
