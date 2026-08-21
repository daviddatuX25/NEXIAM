<?php

use App\Assessment\Omr\Canonical\OmrOverlayException;
use App\Assessment\Omr\Canonical\OmrOverlaySpec;

test('overlay specification enforces closed tags and fixed overflow', function (): void {
    $spec = app(OmrOverlaySpec::class);
    $result = $spec->validate(['boxes' => [[
        'tag' => 'assessment', 'scope' => 'template', 'x' => 10, 'y' => 10, 'width' => 100, 'height' => 20, 'overflow' => 'truncate',
    ]]]);

    expect($result['hash'])->toHaveLength(64)
        ->and($spec->slotsFromHtml('<div data-omr-overlay="assessment">{{ assessment }}</div>'))->toBe(['assessment'])
        ->and(fn () => $spec->validate(['boxes' => [[
            'tag' => 'applicant_name', 'scope' => 'template', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10,
        ]]]))->toThrow(OmrOverlayException::class, 'tag_forbidden');
});

test('overlay specification accepts expanded tags and preserves sanitized content, name, and font_size', function (): void {
    $spec = app(OmrOverlaySpec::class);
    $result = $spec->validate(['boxes' => [
        [
            'tag' => 'header_title',
            'scope' => 'template',
            'x' => 10,
            'y' => 20,
            'width' => 200,
            'height' => 30,
            'overflow' => 'shrink',
            'font_size' => 16.5,
            'name' => 'Main Exam Header',
            'content' => '<b>EXAMINATION ANSWER SHEET</b>',
        ],
        [
            'tag' => 'instructions_box',
            'scope' => 'template',
            'x' => 10,
            'y' => 60,
            'width' => 180,
            'height' => 40,
            'overflow' => 'wrap',
            'content' => 'Shade bubbles completely with 2B pencil.',
        ],
        [
            'tag' => 'signature_box',
            'scope' => 'template',
            'x' => 120,
            'y' => 250,
            'width' => 80,
            'height' => 30,
            'overflow' => 'truncate',
            'name' => 'Signature',
            'content' => 'Examinee Signature',
        ],
        [
            'tag' => 'metadata_box',
            'scope' => 'template',
            'x' => 10,
            'y' => 250,
            'width' => 80,
            'height' => 30,
            'overflow' => 'truncate',
            'content' => 'ROOM / SEAT NO.',
        ],
        [
            'tag' => 'custom_text',
            'scope' => 'template',
            'x' => 10,
            'y' => 290,
            'width' => 190,
            'height' => 15,
            'overflow' => 'truncate',
            'font_size' => 9.0,
            'content' => 'Confidential - For Official Use Only',
        ],
        [
            'tag' => 'blank_instance_qr',
            'scope' => 'blank_instance',
            'x' => 170,
            'y' => 20,
            'width' => 30,
            'height' => 30,
            'overflow' => 'truncate',
        ],
    ]]);

    expect($result['boxes'])->toHaveCount(6)
        ->and($result['boxes'][0]['tag'])->toBe('header_title')
        ->and($result['boxes'][0]['font_size'])->toBe(16.5)
        ->and($result['boxes'][0]['name'])->toBe('Main Exam Header')
        ->and($result['boxes'][0]['content'])->toBe('EXAMINATION ANSWER SHEET')
        ->and($result['boxes'][1]['content'])->toBe('Shade bubbles completely with 2B pencil.')
        ->and($result['boxes'][5]['tag'])->toBe('blank_instance_qr');
});
