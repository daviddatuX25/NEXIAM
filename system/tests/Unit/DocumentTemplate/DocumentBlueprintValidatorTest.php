<?php

use App\DocumentTemplate\Validation\DocumentBlueprintValidator;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;

beforeEach(function (): void {
    $this->validator = new DocumentBlueprintValidator;
});

test('absolute blueprints report printable overflow and overlapping components', function (): void {
    $overflow = [
        'layout_paradigm' => 'absolute',
        'components' => [
            ResultSheetBuilderSchema::component('data_table', 0, 0, 180, 600, [
                'columns' => [['label' => 'Name', 'binding' => 'first_name']],
            ], 'table'),
        ],
    ];
    $overlap = [
        'layout_paradigm' => 'absolute',
        'components' => [
            ResultSheetBuilderSchema::component('text', 0, 0, 50, 20, [], 'first'),
            ResultSheetBuilderSchema::component('text', 25, 10, 50, 20, [], 'second'),
        ],
    ];

    expect(array_column($this->validator->validate($overflow), 'code'))->toContain('overflow')
        ->and(array_column($this->validator->validate($overlap), 'code'))->toContain('overlap');
});

test('flow blueprints allow multi-page tables and vertically stacked blocks sharing a y coordinate', function (): void {
    $document = [
        'layout_paradigm' => 'flow',
        'components' => [
            ResultSheetBuilderSchema::component('data_table', 0, 0, 180, 600, [
                'columns' => [
                    ['label' => 'Applicant', 'binding' => 'first_name'],
                    ['label' => 'Course', 'binding' => 'course_applied_code'],
                ],
            ], 'table'),
            ResultSheetBuilderSchema::component('kpi_grid', 0, 0, 180, 600, [
                'columns' => [['label' => 'Applicants', 'binding' => 'applicant_reference']],
            ], 'kpis'),
        ],
    ];

    expect($this->validator->validate($document))->toBe([]);
});
