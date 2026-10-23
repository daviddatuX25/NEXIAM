<?php

use App\DocumentTemplate\Rendering\ComponentRendererRegistry;
use App\DocumentTemplate\Rendering\DocumentBlueprintCompiler;

test('it compiles absolute document blueprints using positioned components', function (): void {
    $compiler = new DocumentBlueprintCompiler(ComponentRendererRegistry::withDefaults());
    $html = $compiler->compile([
        'layout_paradigm' => 'absolute',
        'page' => ['width_mm' => 210, 'height_mm' => 297, 'margin_mm' => 10],
        'components' => [[
            'type' => 'text',
            'x' => 5,
            'y' => 7,
            'w' => 80,
            'h' => 12,
            'props' => ['text' => 'Absolute content'],
        ]],
    ]);

    expect($html)
        ->toContain('<div class="print-template result-sheet-builder" style="position:relative;width:190mm;min-height:277mm;margin:0 auto;">')
        ->toContain('position:absolute;box-sizing:border-box;left:5mm;top:7mm;width:80mm;min-height:12mm;');
});

test('it compiles report components in normal flow with repeating table headers', function (): void {
    $compiler = new DocumentBlueprintCompiler(ComponentRendererRegistry::withDefaults());
    $html = $compiler->compile([
        'layout_paradigm' => 'flow',
        'page' => ['width_mm' => 210, 'height_mm' => 297, 'margin_mm' => 10],
        'components' => [
            [
                'type' => 'kpi_grid',
                'props' => [
                    'metrics' => [
                        ['label' => 'Total Examinees', 'binding' => 'total_examinees'],
                        ['label' => 'Total Passed', 'binding' => 'total_passed'],
                    ],
                ],
            ],
            [
                'type' => 'data_table',
                'props' => [
                    'repeat_key' => 'applicants',
                    'columns' => [
                        ['key' => 'name', 'label' => 'Applicant Name'],
                        ['key' => 'score', 'label' => 'Score'],
                    ],
                ],
            ],
        ],
    ]);

    expect($html)
        ->toContain('<div class="print-template document-flow-builder" style="position:relative;width:190mm;margin:0 auto;display:block;">')
        ->toContain('display:table-header-group')
        ->toContain('Total Examinees')
        ->toContain('{{total_examinees}}')
        ->toContain('<section class="report-data-table"')
        ->toContain('Applicant Name')
        ->toContain('{{applicants.name}}')
        ->toContain('page-break-inside:avoid;')
        ->not->toContain('position:absolute');
});
