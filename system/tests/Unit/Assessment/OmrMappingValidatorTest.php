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
