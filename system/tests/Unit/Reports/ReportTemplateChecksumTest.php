<?php

use App\Models\Reports\ReportTemplate;

function reportTemplateForChecksum(array $attributes): ReportTemplate
{
    $template = new ReportTemplate;
    $template->forceFill(array_merge([
        'surface_key' => 'passing_reports',
        'mode' => 'html',
        'source_kind' => 'html',
        'builder_schema_version' => null,
        'compiler_version' => null,
        'builder_json' => null,
        'compiled_html' => '<main>Report</main>',
        'content' => null,
        'document_checksum' => null,
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'name' => 'Template',
        'is_active' => false,
        'lock_version' => 1,
    ], $attributes));

    return $template;
}

test('HTML source checksum is stable and ignores display and concurrency state', function (): void {
    $template = reportTemplateForChecksum([]);
    $unchanged = reportTemplateForChecksum([
        'name' => 'Renamed template',
        'is_active' => true,
        'lock_version' => 9,
        'updated_at' => now()->addDay(),
    ]);

    expect($template->sourceChecksum())->toBe($template->sourceChecksum())
        ->and($unchanged->sourceChecksum())->toBe($template->sourceChecksum())
        ->and(reportTemplateForChecksum(['compiled_html' => '<main>Changed report</main>'])->sourceChecksum())
        ->not->toBe($template->sourceChecksum());
});

test('builder checksum is stable across object key ordering and changes with builder inputs and versions', function (): void {
    $first = reportTemplateForChecksum([
        'source_kind' => 'builder',
        'builder_schema_version' => 2,
        'compiler_version' => 'compiler-4',
        'builder_json' => ['page' => ['orientation' => 'portrait', 'size' => 'a4'], 'components' => [['type' => 'text', 'text' => 'Hello']]],
        'compiled_html' => '<main>Hello</main>',
    ]);
    $sameRenderedInput = reportTemplateForChecksum([
        'source_kind' => 'builder',
        'builder_schema_version' => 2,
        'compiler_version' => 'compiler-4',
        'builder_json' => ['components' => [['text' => 'Hello', 'type' => 'text']], 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
        'compiled_html' => '<main>Hello</main>',
    ]);

    expect($first->sourceChecksum())->toBe($sameRenderedInput->sourceChecksum())
        ->and(reportTemplateForChecksum(['source_kind' => 'builder', 'builder_schema_version' => 3, 'builder_json' => ['x' => 1]])->sourceChecksum())
        ->not->toBe(reportTemplateForChecksum(['source_kind' => 'builder', 'builder_schema_version' => 2, 'builder_json' => ['x' => 1]])->sourceChecksum())
        ->and(reportTemplateForChecksum(['source_kind' => 'builder', 'compiler_version' => 'compiler-5', 'builder_json' => ['x' => 1]])->sourceChecksum())
        ->not->toBe(reportTemplateForChecksum(['source_kind' => 'builder', 'compiler_version' => 'compiler-4', 'builder_json' => ['x' => 1]])->sourceChecksum())
        ->and(reportTemplateForChecksum(['source_kind' => 'builder', 'builder_json' => ['x' => 2]])->sourceChecksum())
        ->not->toBe(reportTemplateForChecksum(['source_kind' => 'builder', 'builder_json' => ['x' => 1]])->sourceChecksum());
});

test('DOCX checksum includes document identity and changes with paper layout', function (): void {
    $template = reportTemplateForChecksum([
        'mode' => 'docx',
        'source_kind' => 'docx',
        'compiled_html' => null,
        'document_checksum' => str_repeat('a', 64),
    ]);

    expect(reportTemplateForChecksum([
        'mode' => 'docx',
        'source_kind' => 'docx',
        'compiled_html' => null,
        'document_checksum' => str_repeat('a', 64),
    ])->sourceChecksum())->toBe($template->sourceChecksum())
        ->and(reportTemplateForChecksum([
            'mode' => 'docx',
            'source_kind' => 'docx',
            'compiled_html' => null,
            'document_checksum' => str_repeat('b', 64),
        ])->sourceChecksum())->not->toBe($template->sourceChecksum())
        ->and(reportTemplateForChecksum(['paper_size' => 'letter'])->sourceChecksum())->not->toBe(reportTemplateForChecksum([])->sourceChecksum())
        ->and(reportTemplateForChecksum(['orientation' => 'landscape'])->sourceChecksum())->not->toBe(reportTemplateForChecksum([])->sourceChecksum());
});
