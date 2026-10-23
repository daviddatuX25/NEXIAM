<?php

use App\Assessment\Support\AssessmentRawEvaluator;
use InvalidArgumentException;

final class AssessmentRawEvaluatorTestFixture
{
    public static function contract(): array
    {
        return [
            'profile_version_id' => 4,
            'content_sha256' => str_repeat('a', 64),
            'items' => [
                [
                    'item_id' => 101,
                    'subtest_id' => 11,
                    'is_scored' => true,
                    'option_tokens' => ['A', 'B'],
                ],
            ],
            'bindings' => [
                [
                    'page_no' => 1,
                    'native_label' => '01',
                    'global_position' => 1,
                    'sheet_item_id' => 901,
                    'item_id' => 101,
                    'subtest_id' => 11,
                    'option_tokens' => ['A', 'B'],
                    'correct_token' => 'A',
                ],
            ],
            'metrics' => [
                [
                    'metric_id' => 10,
                    'code' => 'RAW_A',
                    'rule_kind' => 'correct_item_count',
                    'subtest_id' => 11,
                    'unit' => 'count',
                    'min_raw_value' => '0.000000',
                    'max_raw_value' => '1.000000',
                    'precision_scale' => 0,
                    'is_required' => true,
                    'input_metric_ids' => [],
                    'procedure_reference' => null,
                ],
            ],
        ];
    }

    public static function observations(?string $token = 'A'): array
    {
        return [
            ['page_no' => 1, 'native_label' => '01', 'token' => $token],
        ];
    }

    public static function evaluate(array $contract, array $observations, array $recordedMeasures = []): array
    {
        return (new AssessmentRawEvaluator)->evaluate($contract, $observations, $recordedMeasures);
    }
}

test('pins a stable raw scoring kernel version for material identity', function (): void {
    expect(AssessmentRawEvaluator::KERNEL_VERSION)->toBe('nexiam.assessment.raw-evaluator.v1');
});

test('scores exact source items across permuted multi-page destinations and preserves practice provenance', function (): void {
    $contract = AssessmentRawEvaluatorTestFixture::contract();
    $contract['items'] = [
        ['item_id' => 701, 'subtest_id' => 10, 'is_scored' => true, 'option_tokens' => ['A-B', 'a-b', 'C/D']],
        ['item_id' => 702, 'subtest_id' => 10, 'is_scored' => true, 'option_tokens' => ['0', '00']],
        ['item_id' => 703, 'subtest_id' => 10, 'is_scored' => true, 'option_tokens' => ['TRUE', 'FALSE']],
        ['item_id' => 704, 'subtest_id' => 10, 'is_scored' => false, 'option_tokens' => ['PRACTICE-A', 'PRACTICE-B']],
        ['item_id' => 801, 'subtest_id' => 20, 'is_scored' => true, 'option_tokens' => ['RED+BLUE', 'RED+GREEN']],
    ];
    $contract['bindings'] = [
        [
            'page_no' => 1, 'native_label' => '1', 'global_position' => 1, 'sheet_item_id' => 900,
            'item_id' => 702, 'subtest_id' => 10, 'option_tokens' => ['0', '00'], 'correct_token' => '00',
        ],
        [
            'page_no' => 1, 'native_label' => '2', 'global_position' => 2, 'sheet_item_id' => 901,
            'item_id' => 701, 'subtest_id' => 10, 'option_tokens' => ['A-B', 'a-b', 'C/D'], 'correct_token' => 'A-B',
        ],
        [
            'page_no' => 2, 'native_label' => '1', 'global_position' => 3, 'sheet_item_id' => 902,
            'item_id' => 703, 'subtest_id' => 10, 'option_tokens' => ['TRUE', 'FALSE'], 'correct_token' => 'FALSE',
        ],
        [
            'page_no' => 2, 'native_label' => '2', 'global_position' => 4, 'sheet_item_id' => 903,
            'item_id' => 704, 'subtest_id' => 10, 'option_tokens' => ['PRACTICE-A', 'PRACTICE-B'], 'correct_token' => null,
        ],
        [
            'page_no' => 2, 'native_label' => '3', 'global_position' => 5, 'sheet_item_id' => 904,
            'item_id' => 801, 'subtest_id' => 20, 'option_tokens' => ['RED+BLUE', 'RED+GREEN'], 'correct_token' => 'RED+BLUE',
        ],
    ];
    $contract['metrics'] = [
        [
            'metric_id' => 31, 'code' => 'VERBAL_RAW', 'rule_kind' => 'correct_item_count', 'subtest_id' => 10,
            'unit' => 'count', 'min_raw_value' => '0.000000', 'max_raw_value' => '3.000000', 'precision_scale' => 0,
            'is_required' => true, 'input_metric_ids' => [], 'procedure_reference' => null,
        ],
        [
            'metric_id' => 32, 'code' => 'NUMERIC_RAW', 'rule_kind' => 'correct_item_count', 'subtest_id' => 20,
            'unit' => 'count', 'min_raw_value' => '0.000000', 'max_raw_value' => '1.000000', 'precision_scale' => 0,
            'is_required' => true, 'input_metric_ids' => [], 'procedure_reference' => null,
        ],
        [
            'metric_id' => 33, 'code' => 'TOTAL_RAW', 'rule_kind' => 'sum_raw_metrics', 'subtest_id' => null,
            'unit' => 'count', 'min_raw_value' => '0.000000', 'max_raw_value' => '4.000000', 'precision_scale' => 0,
            'is_required' => true, 'input_metric_ids' => [31, 32], 'procedure_reference' => null,
        ],
        [
            'metric_id' => 34, 'code' => 'ELAPSED', 'rule_kind' => 'recorded_measure', 'subtest_id' => 30,
            'unit' => 'seconds', 'min_raw_value' => '0.000000', 'max_raw_value' => '20.000000', 'precision_scale' => 2,
            'is_required' => true, 'input_metric_ids' => [], 'procedure_reference' => 'timing-v2',
        ],
    ];

    $result = AssessmentRawEvaluatorTestFixture::evaluate(
        $contract,
        [
            ['page_no' => 1, 'native_label' => '1', 'token' => '00'],
            ['page_no' => 1, 'native_label' => '2', 'token' => 'a-b'],
            ['page_no' => 2, 'native_label' => '1', 'token' => null],
            ['page_no' => 2, 'native_label' => '2', 'token' => 'PRACTICE-B'],
            ['page_no' => 2, 'native_label' => '3', 'token' => 'RED+BLUE'],
        ],
        [['metric_id' => 34, 'value' => '3.5', 'procedure_reference' => 'timing-v2']],
    );

    expect($result['metric_values'])->toBe([
        ['metric_id' => 31, 'value' => '1', 'input_metric_ids' => []],
        ['metric_id' => 32, 'value' => '1', 'input_metric_ids' => []],
        ['metric_id' => 33, 'value' => '2', 'input_metric_ids' => [31, 32]],
        ['metric_id' => 34, 'value' => '3.50', 'input_metric_ids' => []],
    ])->and($result['item_results'])->toBe([
        ['item_id' => 702, 'page_no' => 1, 'native_label' => '1', 'is_scored' => true, 'correct' => true, 'contribution' => 1],
        ['item_id' => 701, 'page_no' => 1, 'native_label' => '2', 'is_scored' => true, 'correct' => false, 'contribution' => 0],
        ['item_id' => 703, 'page_no' => 2, 'native_label' => '1', 'is_scored' => true, 'correct' => false, 'contribution' => 0],
        ['item_id' => 704, 'page_no' => 2, 'native_label' => '2', 'is_scored' => false, 'correct' => false, 'contribution' => 0],
        ['item_id' => 801, 'page_no' => 2, 'native_label' => '3', 'is_scored' => true, 'correct' => true, 'contribution' => 1],
    ]);
});

test('returns a scored zero only after every mapped response is confirmed', function (): void {
    $result = AssessmentRawEvaluatorTestFixture::evaluate(
        AssessmentRawEvaluatorTestFixture::contract(),
        AssessmentRawEvaluatorTestFixture::observations('B'),
    );

    expect($result['metric_values'])->toBe([
        ['metric_id' => 10, 'value' => '0', 'input_metric_ids' => []],
    ])->and($result['item_results'][0]['correct'])->toBeFalse()
        ->and($result['item_results'][0]['contribution'])->toBe(0);
});

test('sums compatible recorded metrics exactly and evaluates dependencies independent of declaration order', function (): void {
    $contract = AssessmentRawEvaluatorTestFixture::contract();
    $contract['items'] = [];
    $contract['bindings'] = [];
    $contract['metrics'] = [
        [
            'metric_id' => 30, 'code' => 'TOTAL_SECONDS', 'rule_kind' => 'sum_raw_metrics', 'subtest_id' => null,
            'unit' => 'seconds', 'min_raw_value' => '0.000000', 'max_raw_value' => '200.000000', 'precision_scale' => 2,
            'is_required' => true, 'input_metric_ids' => [10, 20], 'procedure_reference' => null,
        ],
        [
            'metric_id' => 10, 'code' => 'PART_A', 'rule_kind' => 'recorded_measure', 'subtest_id' => 41,
            'unit' => 'seconds', 'min_raw_value' => '0.000000', 'max_raw_value' => '100.000000', 'precision_scale' => 2,
            'is_required' => true, 'input_metric_ids' => [], 'procedure_reference' => 'clock-a',
        ],
        [
            'metric_id' => 20, 'code' => 'PART_B', 'rule_kind' => 'recorded_measure', 'subtest_id' => 42,
            'unit' => 'seconds', 'min_raw_value' => '0.000000', 'max_raw_value' => '100.000000', 'precision_scale' => 2,
            'is_required' => true, 'input_metric_ids' => [], 'procedure_reference' => 'clock-b',
        ],
    ];

    $result = AssessmentRawEvaluatorTestFixture::evaluate(
        $contract,
        [],
        [
            ['metric_id' => 10, 'value' => '0.10', 'procedure_reference' => 'clock-a'],
            ['metric_id' => 20, 'value' => '0.20', 'procedure_reference' => 'clock-b'],
        ],
    );

    expect($result['metric_values'])->toBe([
        ['metric_id' => 30, 'value' => '0.30', 'input_metric_ids' => [10, 20]],
        ['metric_id' => 10, 'value' => '0.10', 'input_metric_ids' => []],
        ['metric_id' => 20, 'value' => '0.20', 'input_metric_ids' => []],
    ])->and($result['item_results'])->toBe([]);
});

test('omits absent optional measures instead of zero-filling them', function (): void {
    $contract = AssessmentRawEvaluatorTestFixture::contract();
    $contract['metrics'][] = [
        'metric_id' => 11,
        'code' => 'OPTIONAL_TIME',
        'rule_kind' => 'recorded_measure',
        'subtest_id' => 12,
        'unit' => 'seconds',
        'min_raw_value' => '0.000000',
        'max_raw_value' => '9.000000',
        'precision_scale' => 2,
        'is_required' => false,
        'input_metric_ids' => [],
        'procedure_reference' => 'optional-clock',
    ];

    $result = AssessmentRawEvaluatorTestFixture::evaluate(
        $contract,
        AssessmentRawEvaluatorTestFixture::observations(),
    );

    expect($result['metric_values'])->toBe([
        ['metric_id' => 10, 'value' => '1', 'input_metric_ids' => []],
    ]);
});

test('rejects incomplete, duplicate, extra, unresolved, and non-exact observations', function (): void {
    $contract = AssessmentRawEvaluatorTestFixture::contract();
    $evaluator = new AssessmentRawEvaluator;
    $invalidObservations = [
        [],
        [
            ['page_no' => 1, 'native_label' => '01', 'token' => 'A'],
            ['page_no' => 1, 'native_label' => '01', 'token' => 'A'],
        ],
        [['page_no' => 2, 'native_label' => '01', 'token' => 'A']],
        [['page_no' => 1, 'native_label' => '01']],
        [['page_no' => 1, 'native_label' => '01', 'token' => 'a']],
        [['page_no' => 1, 'native_label' => '01', 'token' => 'A', 'status' => 'unresolved']],
    ];

    foreach ($invalidObservations as $observations) {
        expect(fn () => $evaluator->evaluate($contract, $observations, []))
            ->toThrow(InvalidArgumentException::class);
    }
});

test('rejects incorrect recorded-measure procedure, missing required input, duplicates, extras, floats, bounds, and scale', function (): void {
    $contract = AssessmentRawEvaluatorTestFixture::contract();
    $contract['metrics'][] = [
        'metric_id' => 11,
        'code' => 'TIME',
        'rule_kind' => 'recorded_measure',
        'subtest_id' => 12,
        'unit' => 'seconds',
        'min_raw_value' => '0.000000',
        'max_raw_value' => '2.000000',
        'precision_scale' => 2,
        'is_required' => true,
        'input_metric_ids' => [],
        'procedure_reference' => 'clock-v1',
    ];
    $valid = ['metric_id' => 11, 'value' => '1.25', 'procedure_reference' => 'clock-v1'];
    $evaluator = new AssessmentRawEvaluator;
    $invalidMeasures = [
        [],
        [$valid, $valid],
        [['metric_id' => 99, 'value' => '1', 'procedure_reference' => 'clock-v1']],
        [['metric_id' => 11, 'value' => 1.25, 'procedure_reference' => 'clock-v1']],
        [['metric_id' => 11, 'value' => '1.25', 'procedure_reference' => 'clock-v2']],
        [['metric_id' => 11, 'value' => '2.01', 'procedure_reference' => 'clock-v1']],
        [['metric_id' => 11, 'value' => '1.250', 'procedure_reference' => 'clock-v1']],
    ];

    foreach ($invalidMeasures as $measures) {
        expect(fn () => $evaluator->evaluate($contract, AssessmentRawEvaluatorTestFixture::observations(), $measures))
            ->toThrow(InvalidArgumentException::class);
    }
});

test('rejects unsupported rules, invalid metric ids, and out-of-inventory correct-count bounds', function (): void {
    $evaluator = new AssessmentRawEvaluator;
    foreach ([0, -1, '10', true] as $invalidMetricId) {
        $contract = AssessmentRawEvaluatorTestFixture::contract();
        $contract['metrics'][0]['metric_id'] = $invalidMetricId;
        expect(fn () => $evaluator->evaluate($contract, AssessmentRawEvaluatorTestFixture::observations(), []))
            ->toThrow(InvalidArgumentException::class);
    }

    $unsupported = AssessmentRawEvaluatorTestFixture::contract();
    $unsupported['metrics'][0]['rule_kind'] = 'weighted_average';
    expect(fn () => $evaluator->evaluate($unsupported, AssessmentRawEvaluatorTestFixture::observations(), []))
        ->toThrow(InvalidArgumentException::class);

    $incorrectBounds = AssessmentRawEvaluatorTestFixture::contract();
    $incorrectBounds['metrics'][0]['max_raw_value'] = '2.000000';
    expect(fn () => $evaluator->evaluate($incorrectBounds, AssessmentRawEvaluatorTestFixture::observations(), []))
        ->toThrow(InvalidArgumentException::class);

    $maximumId = AssessmentRawEvaluatorTestFixture::contract();
    $maximumId['metrics'][0]['metric_id'] = PHP_INT_MAX;
    $result = $evaluator->evaluate($maximumId, AssessmentRawEvaluatorTestFixture::observations(), []);
    expect($result['metric_values'][0]['metric_id'])->toBe(PHP_INT_MAX);
});

test('rejects duplicate or cross-unit sum inputs and cyclic raw metric dependencies', function (): void {
    $evaluator = new AssessmentRawEvaluator;
    $base = AssessmentRawEvaluatorTestFixture::contract();

    $duplicateInputs = $base;
    $duplicateInputs['metrics'][] = [
        'metric_id' => 20, 'code' => 'BAD_SUM', 'rule_kind' => 'sum_raw_metrics', 'subtest_id' => null,
        'unit' => 'count', 'min_raw_value' => '0.000000', 'max_raw_value' => '2.000000', 'precision_scale' => 0,
        'is_required' => true, 'input_metric_ids' => [10, 10], 'procedure_reference' => null,
    ];
    expect(fn () => $evaluator->evaluate($duplicateInputs, AssessmentRawEvaluatorTestFixture::observations(), []))
        ->toThrow(InvalidArgumentException::class);

    $incompatibleUnits = $base;
    $incompatibleUnits['metrics'][] = [
        'metric_id' => 20, 'code' => 'TIME', 'rule_kind' => 'recorded_measure', 'subtest_id' => 12,
        'unit' => 'seconds', 'min_raw_value' => '0.000000', 'max_raw_value' => '2.000000', 'precision_scale' => 2,
        'is_required' => true, 'input_metric_ids' => [], 'procedure_reference' => 'clock-v1',
    ];
    $incompatibleUnits['metrics'][] = [
        'metric_id' => 30, 'code' => 'BAD_MIXED_SUM', 'rule_kind' => 'sum_raw_metrics', 'subtest_id' => null,
        'unit' => 'count', 'min_raw_value' => '0.000000', 'max_raw_value' => '3.000000', 'precision_scale' => 0,
        'is_required' => true, 'input_metric_ids' => [10, 20], 'procedure_reference' => null,
    ];
    expect(fn () => $evaluator->evaluate($incompatibleUnits, AssessmentRawEvaluatorTestFixture::observations(), [
        ['metric_id' => 20, 'value' => '1', 'procedure_reference' => 'clock-v1'],
    ]))->toThrow(InvalidArgumentException::class);

    $cyclic = $base;
    $cyclic['metrics'] = [
        $base['metrics'][0],
        [
            'metric_id' => 20, 'code' => 'CYCLE_A', 'rule_kind' => 'sum_raw_metrics', 'subtest_id' => null,
            'unit' => 'count', 'min_raw_value' => '0.000000', 'max_raw_value' => '2.000000', 'precision_scale' => 0,
            'is_required' => true, 'input_metric_ids' => [21, 10], 'procedure_reference' => null,
        ],
        [
            'metric_id' => 21, 'code' => 'CYCLE_B', 'rule_kind' => 'sum_raw_metrics', 'subtest_id' => null,
            'unit' => 'count', 'min_raw_value' => '0.000000', 'max_raw_value' => '2.000000', 'precision_scale' => 0,
            'is_required' => true, 'input_metric_ids' => [20, 10], 'procedure_reference' => null,
        ],
    ];
    expect(fn () => $evaluator->evaluate($cyclic, AssessmentRawEvaluatorTestFixture::observations(), []))
        ->toThrow(InvalidArgumentException::class);
});

test('requires exact scored and practice key provenance and complete item-map geometry', function (): void {
    $evaluator = new AssessmentRawEvaluator;
    $contract = AssessmentRawEvaluatorTestFixture::contract();

    $missingKey = $contract;
    $missingKey['bindings'][0]['correct_token'] = null;
    expect(fn () => $evaluator->evaluate($missingKey, AssessmentRawEvaluatorTestFixture::observations(), []))
        ->toThrow(InvalidArgumentException::class);

    $badPracticeKey = $contract;
    $badPracticeKey['items'][0]['is_scored'] = false;
    $badPracticeKey['bindings'][0]['correct_token'] = 'A';
    expect(fn () => $evaluator->evaluate($badPracticeKey, AssessmentRawEvaluatorTestFixture::observations(), []))
        ->toThrow(InvalidArgumentException::class);

    $tokenGeometryMismatch = $contract;
    $tokenGeometryMismatch['bindings'][0]['option_tokens'] = ['B', 'A'];
    expect(fn () => $evaluator->evaluate($tokenGeometryMismatch, AssessmentRawEvaluatorTestFixture::observations(), []))
        ->toThrow(InvalidArgumentException::class);

    $duplicateSource = $contract;
    $duplicateSource['items'][] = [
        'item_id' => 102,
        'subtest_id' => 11,
        'is_scored' => true,
        'option_tokens' => ['A', 'B'],
    ];
    expect(fn () => $evaluator->evaluate($duplicateSource, AssessmentRawEvaluatorTestFixture::observations(), []))
        ->toThrow(InvalidArgumentException::class);
});
