<?php

use App\Notifications\Templates\Email\NoticeEmailSchema;
use App\Notifications\Templates\Email\NoticeEmailSchemaValidationException;

test('default blueprint is valid and passes assertion', function (): void {
    $default = NoticeEmailSchema::defaultBlueprint();

    expect(NoticeEmailSchema::isValid($default))->toBeTrue();
    expect(NoticeEmailSchema::diagnostics($default))->toBeEmpty();

    $validated = NoticeEmailSchema::validate($default);
    expect($validated)->toBe($default);
});

test('comprehensive valid blueprint with headings, lists, tables, images, dividers, and spacers passes', function (): void {
    $blueprint = [
        'schema_version' => 'notice-email-1',
        'layout' => 'flow',
        'blocks' => [
            [
                'type' => 'heading',
                'level' => 1,
                'align' => 'center',
                'runs' => [
                    ['text' => 'Welcome to ', 'bold' => true],
                    ['text' => '{{institution_name}}', 'color' => '#1d4ed8'],
                ],
            ],
            [
                'type' => 'paragraph',
                'align' => 'left',
                'runs' => [
                    ['text' => 'Hello ', 'italic' => true],
                    ['text' => '{{applicant_name}}', 'bold' => true, 'underline' => true],
                    ['text' => ', your application is received.'],
                ],
            ],
            [
                'type' => 'divider',
                'color' => '#e5e7eb',
            ],
            [
                'type' => 'image',
                'asset_id' => '01923456-789a-7bcd-ef01-23456789abcd',
                'alt' => 'Campus banner',
                'width' => 600,
            ],
            [
                'type' => 'spacer',
                'height' => 16,
            ],
            [
                'type' => 'list',
                'ordered' => true,
                'items' => [
                    'Review your schedule',
                    ['runs' => [['text' => 'Bring required documents']]],
                    ['type' => 'paragraph', 'text' => 'Arrive 30 minutes early'],
                ],
            ],
            [
                'type' => 'table',
                'headers' => ['Subject', 'Date', 'Room'],
                'rows' => [
                    ['English', '{{exam_date}}', '{{room_name}}'],
                    ['Math', '{{exam_date}}', '{{room_name}}'],
                ],
            ],
            [
                'type' => 'paragraph',
                'runs' => [
                    [
                        'text' => 'Click here to access portal',
                        'link' => 'https://portal.institution.edu',
                    ],
                ],
            ],
        ],
    ];

    expect(NoticeEmailSchema::isValid($blueprint))->toBeTrue();
    expect(NoticeEmailSchema::diagnostics($blueprint))->toBeEmpty();
});

test('rejects invalid schema_version and layout', function (): void {
    $blueprint = [
        'schema_version' => 'wrong-version',
        'layout' => 'absolute',
        'blocks' => [],
    ];

    $diagnostics = NoticeEmailSchema::diagnostics($blueprint);
    $codes = array_column($diagnostics, 'code');

    expect($codes)->toContain('invalid_schema_version');
    expect($codes)->toContain('invalid_layout');

    expect(fn () => NoticeEmailSchema::assertValid($blueprint))
        ->toThrow(NoticeEmailSchemaValidationException::class);
});

test('rejects non-array blocks', function (): void {
    $blueprint = [
        'schema_version' => 'notice-email-1',
        'layout' => 'flow',
        'blocks' => 'not-an-array',
    ];

    $diagnostics = NoticeEmailSchema::diagnostics($blueprint);
    expect(array_column($diagnostics, 'code'))->toContain('blocks_not_array');
});

test('rejects oversized JSON exceeding 256 KiB', function (): void {
    $blueprint = [
        'schema_version' => 'notice-email-1',
        'layout' => 'flow',
        'blocks' => [
            [
                'type' => 'paragraph',
                'text' => str_repeat('A', 270000),
            ],
        ],
    ];

    $diagnostics = NoticeEmailSchema::diagnostics($blueprint);
    $codes = array_column($diagnostics, 'code');

    expect($codes)->toContain('max_json_bytes_exceeded');
    expect($codes)->toContain('max_text_characters_exceeded');
});

test('rejects block count exceeding 200', function (): void {
    $blocks = [];
    for ($i = 0; $i < 205; $i++) {
        $blocks[] = [
            'type' => 'paragraph',
            'text' => "Item {$i}",
        ];
    }

    $blueprint = [
        'schema_version' => 'notice-email-1',
        'layout' => 'flow',
        'blocks' => $blocks,
    ];

    $diagnostics = NoticeEmailSchema::diagnostics($blueprint);
    expect(array_column($diagnostics, 'code'))->toContain('max_blocks_exceeded');
});

test('rejects authored text characters exceeding 20000', function (): void {
    $blueprint = [
        'schema_version' => 'notice-email-1',
        'layout' => 'flow',
        'blocks' => [
            [
                'type' => 'paragraph',
                'text' => str_repeat('X', 20005),
            ],
        ],
    ];

    $diagnostics = NoticeEmailSchema::diagnostics($blueprint);
    expect(array_column($diagnostics, 'code'))->toContain('max_text_characters_exceeded');
});

test('rejects nesting depth exceeding 4', function (): void {
    $deepBlock = [
        'type' => 'table',
        'rows' => [
            [
                [
                    'blocks' => [
                        [
                            'type' => 'list',
                            'items' => [
                                [
                                    'type' => 'list',
                                    'items' => [
                                        [
                                            'type' => 'paragraph',
                                            'text' => 'Too deep',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $blueprint = [
        'schema_version' => 'notice-email-1',
        'layout' => 'flow',
        'blocks' => [$deepBlock],
    ];

    $diagnostics = NoticeEmailSchema::diagnostics($blueprint);
    expect(array_column($diagnostics, 'code'))->toContain('max_depth_exceeded');
});

test('rejects unsupported block types', function (): void {
    $blueprint = [
        'schema_version' => 'notice-email-1',
        'layout' => 'flow',
        'blocks' => [
            ['type' => 'video', 'url' => 'https://example.com/video.mp4'],
        ],
    ];

    $diagnostics = NoticeEmailSchema::diagnostics($blueprint);
    expect(array_column($diagnostics, 'code'))->toContain('unsupported_block_type');
});

test('rejects non-rectangular tables', function (): void {
    $blueprint = [
        'schema_version' => 'notice-email-1',
        'layout' => 'flow',
        'blocks' => [
            [
                'type' => 'table',
                'rows' => [
                    ['A', 'B'],
                    ['C', 'D', 'E'],
                ],
            ],
        ],
    ];

    $diagnostics = NoticeEmailSchema::diagnostics($blueprint);
    expect(array_column($diagnostics, 'code'))->toContain('table_not_rectangular');
});

test('rejects nested tables in cells', function (): void {
    $blueprint = [
        'schema_version' => 'notice-email-1',
        'layout' => 'flow',
        'blocks' => [
            [
                'type' => 'table',
                'rows' => [
                    [
                        [
                            'type' => 'table',
                            'rows' => [['Nested']],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $diagnostics = NoticeEmailSchema::diagnostics($blueprint);
    expect(array_column($diagnostics, 'code'))->toContain('nested_table_not_supported');
});

test('rejects external URLs and data URIs in image blocks', function (): void {
    $blueprint = [
        'schema_version' => 'notice-email-1',
        'layout' => 'flow',
        'blocks' => [
            [
                'type' => 'image',
                'asset_id' => 'https://external.test/banner.jpg',
                'alt' => 'Remote image',
                'width' => 400,
            ],
            [
                'type' => 'image',
                'asset_id' => 'valid-id',
                'src' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                'alt' => 'Data URI image',
                'width' => 400,
            ],
        ],
    ];

    $diagnostics = NoticeEmailSchema::diagnostics($blueprint);
    expect(array_column($diagnostics, 'code'))->toContain('image_external_url_rejected');
});

test('rejects image missing required asset_id, alt or width', function (): void {
    $blueprint = [
        'schema_version' => 'notice-email-1',
        'layout' => 'flow',
        'blocks' => [
            [
                'type' => 'image',
            ],
        ],
    ];

    $diagnostics = NoticeEmailSchema::diagnostics($blueprint);
    $codes = array_column($diagnostics, 'code');

    expect($codes)->toContain('image_asset_id_missing');
    expect($codes)->toContain('image_alt_missing');
    expect($codes)->toContain('image_width_missing');
});

test('rejects invalid hex colors in run formatting', function (): void {
    $blueprint = [
        'schema_version' => 'notice-email-1',
        'layout' => 'flow',
        'blocks' => [
            [
                'type' => 'paragraph',
                'runs' => [
                    ['text' => 'Hello', 'color' => 'red'],
                ],
            ],
        ],
    ];

    $diagnostics = NoticeEmailSchema::diagnostics($blueprint);
    expect(array_column($diagnostics, 'code'))->toContain('invalid_hex_color');
});

test('rejects raw script tags in text', function (): void {
    $blueprint = [
        'schema_version' => 'notice-email-1',
        'layout' => 'flow',
        'blocks' => [
            [
                'type' => 'paragraph',
                'text' => 'Hello <script>alert(1)</script> World',
            ],
        ],
    ];

    $diagnostics = NoticeEmailSchema::diagnostics($blueprint);
    expect(array_column($diagnostics, 'code'))->toContain('raw_script_rejected');
});

test('rejects unbalanced token braces', function (): void {
    $blueprint = [
        'schema_version' => 'notice-email-1',
        'layout' => 'flow',
        'blocks' => [
            [
                'type' => 'paragraph',
                'text' => 'Hello {{applicant_name and {{another',
            ],
        ],
    ];

    $diagnostics = NoticeEmailSchema::diagnostics($blueprint);
    expect(array_column($diagnostics, 'code'))->toContain('unbalanced_token_delimiters');
});

test('rejects invalid token names with spaces or illegal characters', function (): void {
    $blueprint = [
        'schema_version' => 'notice-email-1',
        'layout' => 'flow',
        'blocks' => [
            [
                'type' => 'paragraph',
                'text' => 'Hello {{applicant name}} and {{token;drop}}',
            ],
        ],
    ];

    $diagnostics = NoticeEmailSchema::diagnostics($blueprint);
    expect(array_column($diagnostics, 'code'))->toContain('invalid_token_name');
});

test('rejects unsafe URI schemes in link runs', function (): void {
    $blueprint = [
        'schema_version' => 'notice-email-1',
        'layout' => 'flow',
        'blocks' => [
            [
                'type' => 'paragraph',
                'runs' => [
                    ['text' => 'Bad link', 'link' => 'javascript:alert(1)'],
                ],
            ],
        ],
    ];

    $diagnostics = NoticeEmailSchema::diagnostics($blueprint);
    expect(array_column($diagnostics, 'code'))->toContain('unsafe_link_scheme');
});

test('closed schema rejects unknown root block cell and style fields plus malformed typed run content', function (): void {
    foreach ([
        ['html' => '<b>Raw</b>'],
        ['blocks' => [['type' => 'paragraph', 'text' => 'Safe', 'position' => 'absolute']]],
        ['blocks' => [['type' => 'paragraph', 'text' => 'Safe', 'style' => ['css' => 'background:url(https://example.com)']]]],
        ['blocks' => [['type' => 'table', 'rows' => [[['text' => 'A', 'colspan' => 2]]]]]],
        ['blocks' => [['type' => 'paragraph', 'runs' => [['text' => ['nested' => 'unsafe']]]]]],
        ['blocks' => [['type' => 'paragraph', 'text' => 'A', 'runs' => [['text' => 'Competing content']]]]],
    ] as $overrides) {
        $blueprint = array_replace(NoticeEmailSchema::defaultBlueprint(), $overrides);
        expect(NoticeEmailSchema::isValid($blueprint))->toBeFalse();
    }
});

test('server catalog rejects syntactically valid unknown and wrong slot tokens and malformed balanced braces', function (): void {
    foreach (['{{unknown_valid_token}}', '{{score}}', '{{applicant_name}}{{{institution_name}}}'] as $text) {
        $blueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow', 'blocks' => [['type' => 'paragraph', 'text' => $text]]];
        expect(NoticeEmailSchema::isValid($blueprint, ['applicant_name']))->toBeFalse();
    }
    $blueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow', 'blocks' => [['type' => 'paragraph', 'text' => '{{applicant_name}}']]];
    expect(NoticeEmailSchema::isValid($blueprint, ['applicant_name']))->toBeTrue()
        ->and(NoticeEmailSchema::isValid($blueprint, []))->toBeFalse();
});

test('email styles and raster geometry are typed bounded flow values never arbitrary CSS or millimetres', function (): void {
    foreach ([['font_size' => '18px'], ['font_size' => 100], ['font_family' => 'Injected font'],
        ['width' => '1000%'], ['width' => '210mm'], ['padding' => -1], ['align' => 'absolute'], ['color' => '#ffffffff']] as $style) {
        $blueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow',
            'blocks' => [['type' => 'paragraph', 'text' => 'Safe text', 'style' => $style]]];
        expect(NoticeEmailSchema::isValid($blueprint))->toBeFalse();
    }
    $blueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow',
        'blocks' => [['type' => 'paragraph', 'text' => 'Safe text', 'style' => ['font_family' => 'Arial', 'font_size' => 18, 'width' => '100%', 'padding' => 12]]]];
    expect(NoticeEmailSchema::isValid($blueprint))->toBeTrue();
});

test('links reject alternate schemes credentials control characters and allow only typed catalog URL substitution', function (): void {
    foreach (['http://example.com', 'file:///etc/passwd', '//example.com', 'https://user:pass@example.com', 'https://example.com/path with space', '{{applicant_name}}'] as $link) {
        $blueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow',
            'blocks' => [['type' => 'paragraph', 'runs' => [['text' => 'Open', 'link' => $link]]]]];
        expect(NoticeEmailSchema::isValid($blueprint, ['applicant_name', 'portal_url']))->toBeFalse();
    }
    $blueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow',
        'blocks' => [['type' => 'paragraph', 'runs' => [['text' => 'Open', 'link' => '{{portal_url}}']]]]];
    expect(NoticeEmailSchema::isValid($blueprint, ['portal_url']))->toBeTrue();
});

test('all authored semantic shapes count toward text block and raster bounds without truncation', function (): void {
    $images = array_fill(0, 21, ['type' => 'image', 'asset_id' => '01923456-789a-7bcd-ef01-23456789abcd', 'alt' => 'Local image', 'width' => 600]);
    expect(array_column(NoticeEmailSchema::diagnostics(['schema_version' => 'notice-email-1', 'layout' => 'flow', 'blocks' => $images]), 'code'))->toContain('max_images_exceeded');
    $table = ['type' => 'table', 'rows' => array_fill(0, 101, ['Text'])];
    expect(array_column(NoticeEmailSchema::diagnostics(['schema_version' => 'notice-email-1', 'layout' => 'flow', 'blocks' => [$table]]), 'code'))->toContain('max_blocks_exceeded');
});

test('balanced nested token braces are malformed even when the inner token is catalogued', function (): void {
    $blueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow',
        'blocks' => [['type' => 'paragraph', 'text' => '{{{applicant_name}}}']]];
    expect(NoticeEmailSchema::isValid($blueprint, ['applicant_name']))->toBeFalse();
});

test('approved portrait images retain proportional fallback height instead of a false six hundred pixel height cap', function (): void {
    $blueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow', 'blocks' => [
        ['type' => 'image', 'asset_id' => '01923456-789a-7bcd-ef01-23456789abcd', 'alt' => 'Portrait scan', 'width' => 600, 'height' => 1800],
    ]];
    expect(NoticeEmailSchema::isValid($blueprint))->toBeTrue();
    $blueprint['blocks'][0]['height'] = 20000000;
    expect(NoticeEmailSchema::isValid($blueprint))->toBeFalse();
});
