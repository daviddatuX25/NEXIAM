<?php

use App\Assessment\Omr\Canonical\OmrMappingValidator;

function omrValidMappingBlock(array $overrides = []): array
{
    return [
        'name' => 'Questions',
        'page_no' => 1,
        'field_type' => 'QTYPE_MCQ4',
        'label_prefix' => 'Q_',
        'rows' => 4,
        'options' => 4,
        'origin_x' => 200,
        'origin_y' => 300,
        'width' => 500,
        'height' => 300,
        'option_pitch_px' => 80,
        'row_pitch_px' => 70,
        'bubble_width_px' => 50,
        'bubble_height_px' => 50,
        ...$overrides,
    ];
}

test('mapping validator emits pinned fieldBlocks for regular geometry', function (): void {
    $result = app(OmrMappingValidator::class)->validate([
        'page_width' => 2551,
        'page_height' => 3898,
        'page_count' => 1,
        'blocks' => [omrValidMappingBlock()],
    ]);

    expect($result['valid'])->toBeTrue()
        ->and($result['field_blocks']['Questions']['fieldType'])->toBe('QTYPE_MCQ4')
        ->and($result['field_blocks']['Questions']['origin'])->toBe([200, 300])
        ->and($result['field_blocks']['Questions']['fieldLabels'])->toHaveCount(4);
});
test('mapping validator accepts paired comparison bubble_values and emits bubbleValues without fieldType', function (): void {
    $bubbleValues = ['A-B', 'A-C', 'A-D', 'B-C', 'B-D', 'C-D'];
    $result = app(OmrMappingValidator::class)->validate([
        'page_width' => 2551,
        'page_height' => 3898,
        'page_count' => 1,
        'blocks' => [omrValidMappingBlock([
            'options' => 6,
            'bubble_values' => $bubbleValues,
        ])],
    ]);

    expect($result['valid'])->toBeTrue()
        ->and(array_key_exists('fieldType', $result['field_blocks']['Questions']))->toBeFalse()
        ->and($result['field_blocks']['Questions']['bubbleValues'])->toBe($bubbleValues);
});

test('mapping validator accepts True/False 2-option bubble_values', function (): void {
    $result = app(OmrMappingValidator::class)->validate([
        'page_width' => 2551,
        'page_height' => 3898,
        'page_count' => 1,
        'blocks' => [omrValidMappingBlock([
            'options' => 2,
            'bubble_values' => ['TRUE', 'FALSE'],
        ])],
    ]);

    expect($result['valid'])->toBeTrue();
});

test('mapping validator rejects duplicate or empty or over-length bubble_values', function (): void {
    $validate = function (array $bubbleValues): array {
        return app(OmrMappingValidator::class)->validate([
            'page_width' => 2551,
            'page_height' => 3898,
            'page_count' => 1,
            'blocks' => [omrValidMappingBlock([
                'options' => count($bubbleValues),
                'bubble_values' => $bubbleValues,
            ])],
        ]);
    };

    $duplicate = $validate(['A', 'A']);
    $empty = $validate(['']);
    $overLength = $validate([str_repeat('X', 33)]);
    $tooMany = $validate(array_map(fn (int $index): string => "OPTION_{$index}", range(1, 11)));

    expect($duplicate['valid'])->toBeFalse()
        ->and(array_column($duplicate['errors'], 'code'))->toContain('option_set_unsupported')
        ->and($empty['valid'])->toBeFalse()
        ->and($overLength['valid'])->toBeFalse()
        ->and($tooMany['valid'])->toBeFalse();
});

test('declared inventory accepts paired option tokens and rejects mismatching tokens', function (): void {
    $options = ['A-B', 'A-C', 'A-D', 'B-C', 'B-D', 'C-D'];
    $mapping = [
        'page_width' => 2551,
        'page_height' => 3898,
        'page_count' => 1,
        'blocks' => [omrValidMappingBlock([
            'labels' => ['LOGIC_Q_1'],
            'rows' => 1,
            'options' => 6,
            'bubble_values' => $options,
        ])],
        'declared_inventory' => [[
            'page_no' => 1,
            'area_code' => 'LOGIC',
            'question_count' => 1,
            'option_labels' => $options,
        ]],
        'identity' => [
            'method' => 'write_in_manual',
            'regions' => [
                ['page_no' => 1, 'x' => 1200, 'y' => 300, 'w' => 300, 'h' => 80],
            ],
        ],
    ];

    $valid = app(OmrMappingValidator::class)->validate($mapping, ['require_declaration' => true]);
    $mapping['blocks'][0]['bubble_values'] = ['A-B', 'A-C', 'A-D', 'B-C', 'B-D', 'C-E'];
    $mismatch = app(OmrMappingValidator::class)->validate($mapping, ['require_declaration' => true]);

    expect($valid['valid'])->toBeTrue()
        ->and($mismatch['valid'])->toBeFalse()
        ->and(array_column($mismatch['errors'], 'code'))->toContain('inventory_option_mismatch');
});

test('mapping validator preserves explicit integer bubble positions and checks their actual geometry', function (): void {
    $positions = [];
    for ($row = 0; $row < 4; $row++) {
        $positions[] = [
            [200 + $row * 2, 300 + $row * 70],
            [280 + $row * 2, 304 + $row * 70],
            [360 + $row * 2, 308 + $row * 70],
            [440 + $row * 2, 312 + $row * 70],
        ];
    }
    $mapping = [
        'page_width' => 2551,
        'page_height' => 3898,
        'page_count' => 1,
        'blocks' => [omrValidMappingBlock(['bubble_positions' => $positions])],
    ];

    $valid = app(OmrMappingValidator::class)->validate($mapping);
    $overlappingPositions = $positions;
    $overlappingPositions[0][1] = $positions[0][0];
    $overlapping = app(OmrMappingValidator::class)->validate([
        ...$mapping,
        'blocks' => [omrValidMappingBlock(['bubble_positions' => $overlappingPositions])],
    ]);
    $tooClosePositions = $positions;
    $tooClosePositions[0][1] = [255, 300];
    $tooClose = app(OmrMappingValidator::class)->validate([
        ...$mapping,
        'blocks' => [omrValidMappingBlock(['bubble_positions' => $tooClosePositions])],
    ]);
    $boundaryPositions = $positions;
    $boundaryPositions[0][3] = [2501, 300];
    $boundary = app(OmrMappingValidator::class)->validate([
        ...$mapping,
        'blocks' => [omrValidMappingBlock(['bubble_positions' => $boundaryPositions])],
    ]);
    $unsafe = app(OmrMappingValidator::class)->validate($mapping, [
        'marker_zones' => [['page_no' => 1, 'x' => 300, 'y' => 400, 'w' => 50, 'h' => 50]],
    ]);
    $malformed = app(OmrMappingValidator::class)->validate([
        ...$mapping,
        'blocks' => [omrValidMappingBlock(['bubble_positions' => array_slice($positions, 1)])],
    ]);

    expect($valid['valid'])->toBeTrue()
        ->and($valid['field_blocks']['Questions']['bubblePositions'])->toBe($positions)
        ->and($overlapping['valid'])->toBeFalse()
        ->and(array_column($overlapping['errors'], 'code'))->toContain('bubble_positions_overlap')
        ->and($tooClose['valid'])->toBeFalse()
        ->and(array_column($tooClose['errors'], 'code'))->toContain('bubble_positions_too_close')
        ->and($boundary['valid'])->toBeFalse()
        ->and(array_column($boundary['errors'], 'code'))->toContain('out_of_bounds')
        ->and($unsafe['valid'])->toBeFalse()
        ->and(array_column($unsafe['errors'], 'code'))->toContain('region_collision')
        ->and($malformed['valid'])->toBeFalse()
        ->and(array_column($malformed['errors'], 'code'))->toContain('bubble_positions_invalid');

});

test('mapping validator blocks under-sized bubbles, tight pitch, and overfull rows', function (): void {
    $result = app(OmrMappingValidator::class)->validate([
        'page_width' => 2551,
        'page_height' => 3898,
        'page_count' => 1,
        'blocks' => [omrValidMappingBlock([
            'width' => 50,
            'bubble_width_px' => 20,
            'bubble_height_px' => 20,
            'option_pitch_px' => 21,
        ])],
    ]);
    $codes = array_column($result['errors'], 'code');

    expect($result['valid'])->toBeFalse()
        ->and($codes)->toContain('bubble_too_small')
        ->and($codes)->toContain('pitch_too_tight')
        ->and($codes)->toContain('explicit_split_required');
});

test('mapping validator applies separate physical bubble minimums for uploaded and designed paper', function (): void {
    $validator = app(OmrMappingValidator::class);
    $mappingFor = fn (int $diameter): array => [
        'page_width' => 2551,
        'page_height' => 3898,
        'page_count' => 1,
        'blocks' => [omrValidMappingBlock([
            'bubble_width_px' => $diameter,
            'bubble_height_px' => $diameter,
        ])],
    ];

    $designedTooSmall = $validator->validate($mappingFor(35));
    $designedBelowMinimum = $validator->validate($mappingFor(45));
    $designedAtMinimum = $validator->validate($mappingFor(50));
    $uploadedAtMinimum = $validator->validate($mappingFor(30), ['is_uploaded' => true]);
    $uploadedAboveMinimum = $validator->validate($mappingFor(35), ['source_kind' => 'image']);
    $uploadedLarger = $validator->validate($mappingFor(40), ['is_uploaded' => true]);
    $uploadedTooSmall = $validator->validate($mappingFor(20), ['is_uploaded' => true]);
    $uploadedBelowMinimum = $validator->validate($mappingFor(28), ['source_kind' => 'image']);

    expect(array_column($designedTooSmall['errors'], 'code'))->toContain('bubble_too_small')
        ->and(array_column($designedBelowMinimum['errors'], 'code'))->toContain('bubble_too_small')
        ->and($designedAtMinimum['valid'])->toBeTrue()
        ->and($uploadedAtMinimum['valid'])->toBeTrue()
        ->and($uploadedAboveMinimum['valid'])->toBeTrue()
        ->and($uploadedLarger['valid'])->toBeTrue()
        ->and(array_column($uploadedTooSmall['errors'], 'code'))->toContain('bubble_too_small')
        ->and(array_column($uploadedBelowMinimum['errors'], 'code'))->toContain('bubble_too_small');
});

test('mapping validator keeps the minimum option edge gap for small uploaded bubbles', function (): void {
    $result = app(OmrMappingValidator::class)->validate([
        'page_width' => 2551,
        'page_height' => 3898,
        'page_count' => 1,
        'blocks' => [omrValidMappingBlock([
            'bubble_width_px' => 30,
            'bubble_height_px' => 30,
            'option_pitch_px' => 40,
        ])],
    ], ['is_uploaded' => true]);

    expect($result['valid'])->toBeFalse()
        ->and(array_column($result['errors'], 'code'))->toContain('pitch_too_tight')
        ->and(array_column($result['errors'], 'code'))->not->toContain('bubble_too_small');
});

test('mapping validator never auto-splits duplicate or overlapping blocks', function (): void {
    $result = app(OmrMappingValidator::class)->validate([
        'page_width' => 2551,
        'page_height' => 3898,
        'page_count' => 1,
        'blocks' => [
            omrValidMappingBlock(),
            omrValidMappingBlock(['name' => 'Second', 'label_prefix' => 'Q_', 'origin_x' => 250]),
        ],
    ]);
    $codes = array_column($result['errors'], 'code');

    expect($result['valid'])->toBeFalse()
        ->and($codes)->toContain('duplicate_label')
        ->and($codes)->toContain('block_overlap')
        ->and($result['field_blocks'])->toHaveCount(2);
});

test('mapping validator accepts studio bridge native blocks fixture with exact block counts', function (): void {
    $fixturePath = dirname(__DIR__, 2).'/Fixtures/omr/studio-bridge-native-blocks.json';
    expect(file_exists($fixturePath))->toBeTrue();

    $payload = json_decode((string) file_get_contents($fixturePath), true);
    $result = app(OmrMappingValidator::class)->validate($payload);

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty()
        ->and($result['field_blocks'])->toHaveKeys(['Section_A', 'Section_B', 'Student_ID'])
        ->and($result['field_blocks']['Section_A']['fieldLabels'])->toHaveCount(25)
        ->and($result['field_blocks']['Section_B']['fieldLabels'])->toHaveCount(25)
        ->and($result['field_blocks']['Student_ID']['fieldLabels'])->toHaveCount(6);
});

test('licensed source cannot mistake an incomplete or gapped question inventory for a finished mapping', function (): void {
    $mapping = [
        'page_width' => 2551,
        'page_height' => 3898,
        'page_count' => 1,
        'blocks' => [omrValidMappingBlock([
            'labels' => ['LOGIC_Q_1', 'LOGIC_Q_2', 'LOGIC_Q_4', 'LOGIC_Q_5'],
        ])],
        'declared_inventory' => [[
            'page_no' => 1,
            'area_code' => 'LOGIC',
            'question_count' => 5,
            'option_labels' => ['A', 'B', 'C', 'D'],
        ]],
        'identity' => ['method' => 'write_in_manual', 'regions' => [
            ['page_no' => 1, 'x' => 1200, 'y' => 300, 'w' => 300, 'h' => 80],
        ]],
    ];
    $validator = app(OmrMappingValidator::class);
    $result = $validator->validate($mapping, ['require_declaration' => true]);
    expect($result['valid'])->toBeFalse()
        ->and(array_column($result['errors'], 'code'))->toContain('inventory_incomplete', 'inventory_sequence_invalid');

    $mapping['blocks'][0]['labels'] = ['LOGIC_Q_1', 'LOGIC_Q_2', 'LOGIC_Q_3', 'LOGIC_Q_4'];
    $mapping['declared_inventory'][0]['question_count'] = 4;
    expect($validator->validate($mapping, ['require_declaration' => true])['valid'])->toBeTrue();
    $mapping['identity']['regions'][0]['w'] = 2000;
    expect(array_column($validator->validate($mapping, ['require_declaration' => true])['errors'], 'code'))->toContain('identity_region_invalid');
});

test('licensed source requires ordered Applicant Number positions on every page', function (): void {
    $mapping = [
        'page_width' => 2551, 'page_height' => 3898, 'page_count' => 2,
        'blocks' => [
            omrValidMappingBlock(['name' => 'Logic_1', 'rows' => 1, 'labels' => ['LOGIC_Q_1']]),
            omrValidMappingBlock(['name' => 'Logic_2', 'page_no' => 2, 'rows' => 1, 'labels' => ['LOGIC_Q_2']]),
            omrValidMappingBlock([
                'name' => 'Applicant_Number_1', 'field_type' => 'QTYPE_INT', 'direction' => 'vertical',
                'options' => 10, 'rows' => 10, 'labels' => ['APPLICANT_NUMBER_1', 'APPLICANT_NUMBER_2'],
                'origin_x' => 1000, 'width' => 200, 'height' => 800,
                'option_pitch_px' => 70, 'row_pitch_px' => 60,
            ]),
        ],
        'declared_inventory' => [
            ['page_no' => 1, 'area_code' => 'LOGIC', 'question_count' => 1, 'option_labels' => ['A', 'B', 'C', 'D']],
            ['page_no' => 2, 'area_code' => 'LOGIC', 'question_count' => 1, 'option_labels' => ['A', 'B', 'C', 'D']],
        ],
        'identity' => ['method' => 'shaded_grid', 'digit_count' => 2],
    ];
    $result = app(OmrMappingValidator::class)->validate($mapping, ['require_declaration' => true]);
    expect($result['valid'])->toBeFalse()
        ->and(array_column($result['errors'], 'code'))->toContain('identity_page_missing');
});

test('licensed source normalizes Applicant_Number_Page_N labels and accepts mixed-diameter explicit positions with 1px rounding tolerance', function (): void {
    $mapping = [
        'page_width' => 2480,
        'page_height' => 3508,
        'page_count' => 1,
        'blocks' => [
            omrValidMappingBlock([
                'name' => 'LOGIC_P1_Q1',
                'rows' => 1,
                'labels' => ['LOGIC_Q_1'],
                'bubble_width_px' => 53,
                'bubble_height_px' => 53,
                'option_pitch_px' => 68,
                'bubble_positions' => [[[330, 1311], [399, 1311], [467, 1311], [536, 1311]]],
            ]),
            omrValidMappingBlock([
                'name' => 'QUANT_P1_Q1',
                'rows' => 1,
                'labels' => ['QUANT_Q_1'],
                'bubble_width_px' => 52,
                'bubble_height_px' => 52,
                'option_pitch_px' => 67,
                'bubble_positions' => [[[975, 1312], [1041, 1312], [1108, 1312], [1174, 1312]]],
            ]),
            omrValidMappingBlock([
                'name' => 'Applicant_Number_Page_1',
                'field_type' => 'QTYPE_INT',
                'direction' => 'vertical',
                'options' => 10,
                'rows' => 10,
                'labels' => ['applicant_number_page_1_1', 'applicant_number_page_1_2'],
                'origin_x' => 1759,
                'origin_y' => 516,
                'width' => 200,
                'height' => 700,
                'bubble_width_px' => 51,
                'bubble_height_px' => 51,
                'option_pitch_px' => 68,
                'row_pitch_px' => 67,
            ]),
        ],
        'declared_inventory' => [
            ['page_no' => 1, 'area_code' => 'LOGIC', 'question_count' => 1, 'option_labels' => ['A', 'B', 'C', 'D']],
            ['page_no' => 1, 'area_code' => 'QUANT', 'question_count' => 1, 'option_labels' => ['A', 'B', 'C', 'D']],
        ],
        'identity' => ['method' => 'shaded_grid', 'digit_count' => 2],
    ];

    $result = app(OmrMappingValidator::class)->validate($mapping, ['require_declaration' => true]);
    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty()
        ->and($result['field_blocks']['Applicant_Number_Page_1']['fieldLabels'])->toBe(['APPLICANT_NUMBER_1', 'APPLICANT_NUMBER_2'])
        ->and($result['field_blocks']['LOGIC_P1_Q1']['bubbleDimensions'])->toBe([53, 53])
        ->and($result['field_blocks']['QUANT_P1_Q1']['bubbleDimensions'])->toBe([52, 52]);
});
