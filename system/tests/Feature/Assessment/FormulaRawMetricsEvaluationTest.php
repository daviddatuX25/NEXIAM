<?php

declare(strict_types=1);

use App\Assessment\Support\AssessmentRawEvaluator;
use App\Assessment\Support\RawMetricFormulaEngine;

it('evaluates selected item subsets and formulas without inventing item observations', function (): void {
    $engine = new RawMetricFormulaEngine;
    $ast = $engine->compile('ROUND(0.20*A_CORE_RAW+0.80*B_RAW,2)', ['A_CORE_RAW', 'B_RAW']);
    $bounds = $engine->bounds($ast, [
        'A_CORE_RAW' => ['min' => '0', 'max' => '2'],
        'B_RAW' => ['min' => '0', 'max' => '2'],
    ], 2);
    $items = [];
    $bindings = [];
    $observations = [];
    foreach ([
        [11, 101, 1, 'A', 'B'],
        [12, 102, 2, 'A', 'B'],
        [13, 103, 3, 'A', 'B'],
        [14, 201, 4, 'A', 'B'],
        [15, 202, 5, 'A', 'B'],
    ] as [$id, $subtestId, $position, $correct, $wrong]) {
        $items[] = [
            'item_id' => $id,
            'subtest_id' => $subtestId < 200 ? 1 : 2,
            'is_scored' => true,
            'option_tokens' => ['A', 'B'],
        ];
        $bindings[] = [
            'page_no' => 1,
            'native_label' => 'Q'.$position,
            'global_position' => $position,
            'sheet_item_id' => 200 + $position,
            'item_id' => $id,
            'subtest_id' => $subtestId < 200 ? 1 : 2,
            'option_tokens' => ['A', 'B'],
            'correct_token' => $correct,
        ];
        $observations[] = [
            'page_no' => 1,
            'native_label' => 'Q'.$position,
            'token' => in_array($id, [11, 13, 14, 15], true) ? 'A' : 'B',
        ];
    }
    $contract = [
        'profile_version_id' => 7,
        'content_sha256' => str_repeat('a', 64),
        'items' => $items,
        'bindings' => $bindings,
        'metrics' => [
            [
                'metric_id' => 31,
                'code' => 'A_RAW',
                'rule_kind' => 'correct_item_count',
                'subtest_id' => 1,
                'unit' => 'count',
                'min_raw_value' => '0.000000',
                'max_raw_value' => '3.000000',
                'precision_scale' => 0,
                'is_required' => true,
                'input_metric_ids' => [],
                'procedure_reference' => null,
            ],
            [
                'metric_id' => 32,
                'code' => 'A_CORE_RAW',
                'rule_kind' => 'correct_item_count',
                'subtest_id' => 1,
                'unit' => 'count',
                'min_raw_value' => '0.000000',
                'max_raw_value' => '2.000000',
                'precision_scale' => 0,
                'is_required' => true,
                'input_metric_ids' => [],
                'procedure_reference' => null,
                'item_codes' => ['A_ITEM_1', 'A_ITEM_2'],
                'item_ids' => [11, 12],
            ],
            [
                'metric_id' => 33,
                'code' => 'B_RAW',
                'rule_kind' => 'correct_item_count',
                'subtest_id' => 2,
                'unit' => 'count',
                'min_raw_value' => '0.000000',
                'max_raw_value' => '2.000000',
                'precision_scale' => 0,
                'is_required' => true,
                'input_metric_ids' => [],
                'procedure_reference' => null,
            ],
            [
                'metric_id' => 34,
                'code' => 'WEIGHTED_RAW',
                'rule_kind' => 'formula_raw_metrics',
                'subtest_id' => null,
                'unit' => 'count',
                'min_raw_value' => $bounds['min'],
                'max_raw_value' => $bounds['max'],
                'precision_scale' => 2,
                'is_required' => true,
                'input_metric_ids' => [32, 33],
                'procedure_reference' => null,
                'formula_expression' => 'ROUND(0.20*A_CORE_RAW+0.80*B_RAW,2)',
                'formula_ast_json' => $ast,
                'formula_algorithm_identity' => 'nexiam.raw-formula.v1',
            ],
        ],
    ];

    $result = (new AssessmentRawEvaluator($engine))->evaluate($contract, $observations, []);
    $values = collect($result['metric_values'])->keyBy('metric_id');

    expect($values[31]['value'])->toBe('2')
        ->and($values[32]['value'])->toBe('1')
        ->and($values[33]['value'])->toBe('2')
        ->and($values[34]['value'])->toBe('1.80')
        ->and(count($result['item_results']))->toBe(5)
        ->and(collect($result['item_results'])->where('correct', true)->count())->toBe(4);
});
