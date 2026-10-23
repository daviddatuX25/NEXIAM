<?php

use App\DocumentTemplate\Enums\LayoutParadigm;
use App\Models\Foundation\AcademicCycle;
use App\Reports\Queries\CourseQuotaQualifiersDataQuery;
use App\Reports\Surfaces\CourseQuotaQualifiersSurface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function courseQuotaQualifiersSurface(): CourseQuotaQualifiersSurface
{
    return new CourseQuotaQualifiersSurface(new CourseQuotaQualifiersDataQuery);
}

test('course quota qualifiers surface exposes its stable identity and repeating region', function (): void {
    $surface = courseQuotaQualifiersSurface();

    expect($surface->surfaceKey())->toBe('course_quota_qualifiers')
        ->and($surface->title())->toBe('Pre-Enrollment Qualifiers & Quota Status')
        ->and($surface->layoutParadigm())->toBe(LayoutParadigm::Flow)
        ->and($surface->repeatingRowKey())->toBe('qualifiers')
        ->and($surface->repeatingRegions())->toBe(['qualifiers']);
});

test('course quota qualifiers surface catalogs its placeholders and flow blueprint', function (): void {
    $surface = courseQuotaQualifiersSurface();
    $catalog = $surface->componentCatalog();
    $blueprint = $surface->defaultBlueprint('letter', 'landscape');

    expect(array_keys($catalog))->toBe([
        'header', 'kpi_grid', 'data_table', 'signature', 'text', 'note', 'footer', 'divider', 'spacer',
    ]);
    foreach ($catalog as $entry) {
        expect($entry)->toHaveKeys(['label', 'description', 'default_w', 'default_h', 'default_props', 'inspector_fields']);
    }
    expect($blueprint['schema_version'])->toBe(1)
        ->and($blueprint['layout_paradigm'])->toBe('flow')
        ->and($blueprint['page'])->toMatchArray(['paper_size' => 'letter', 'orientation' => 'landscape', 'width_mm' => 279, 'height_mm' => 216])
        ->and(array_column($blueprint['components'], 'id'))->toBe([
            'header-1', 'kpi_grid-1', 'data_table-1', 'signature-1', 'footer-1',
        ])
        ->and($blueprint['components'][1]['props']['metrics'])->toBe([
            ['label' => 'Target Quota', 'binding' => 'target_quota'],
            ['label' => 'Accepted Count', 'binding' => 'accepted_count'],
            ['label' => 'Remaining Slots', 'binding' => 'remaining_slots'],
            ['label' => 'Fill Rate', 'binding' => 'fill_rate'],
        ])
        ->and($blueprint['components'][2]['props']['repeat_key'])->toBe('qualifiers')
        ->and(array_column($blueprint['components'][2]['props']['columns'], 'key'))->toBe([
            'row_no', 'reference_code', 'applicant_name', 'strand', 'gwa', 'program_code', 'admission_status', 'accepted_at',
        ]);
});

test('course quota qualifiers sample context has five rows matching its row placeholder catalog', function (): void {
    $surface = courseQuotaQualifiersSurface();
    $context = $surface->sampleContext();
    $catalogRowKeys = array_values(array_map(
        static fn (string $placeholder): string => substr($placeholder, strlen('qualifiers.')),
        array_filter($surface->placeholderCatalog()['required'], static fn (string $placeholder): bool => str_starts_with($placeholder, 'qualifiers.')),
    ));

    expect($context['qualifiers'])->toHaveCount(5)
        ->and(array_keys($context['qualifiers'][0]))->toBe($catalogRowKeys)
        ->and($context['target_quota'])->toBe(120)
        ->and($context['accepted_count'])->toBe(84)
        ->and($context['remaining_slots'])->toBe(36)
        ->and($context['fill_rate'])->toBe('70.00%')
        ->and($surface->placeholderCatalog()['all'])->toBe($surface->placeholderCatalog()['required']);
});

test('course quota qualifiers surface resolves its sample context contract', function (): void {
    seedE2Foundation();
    $surface = courseQuotaQualifiersSurface();
    $report = $surface->resolveData(['academic_cycle_id' => AcademicCycle::active()?->id]);

    expect(array_keys($report))->toBe(array_keys($surface->sampleContext()));
});
