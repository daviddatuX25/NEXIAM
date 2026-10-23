<?php

use App\DocumentTemplate\Enums\LayoutParadigm;
use App\Models\Foundation\AcademicCycle;
use App\Reports\Queries\PassingReportDataQuery;
use App\Reports\Surfaces\PassingReportSurface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
function passingReportSurface(): PassingReportSurface
{
    return new PassingReportSurface(new PassingReportDataQuery);
}

test('passing report surface exposes its stable identity and repeating row', function (): void {
    $surface = passingReportSurface();

    expect($surface->surfaceKey())->toBe('passing_report')
        ->and($surface->title())->toBe('Passing Report')
        ->and($surface->layoutParadigm())->toBe(LayoutParadigm::Flow)
        ->and($surface->repeatingRowKey())->toBe('passers')
        ->and($surface->repeatingRegions())->toBe(['passers']);
});

test('passing report surface exposes the modular catalog and flow blueprint', function (): void {
    $surface = passingReportSurface();
    $catalog = $surface->componentCatalog();
    $blueprint = $surface->defaultBlueprint();

    expect(array_keys($catalog))->toBe([
        'header', 'kpi_grid', 'data_table', 'signature', 'text', 'note', 'footer', 'divider', 'spacer',
    ]);
    foreach ($catalog as $entry) {
        expect($entry)->toHaveKeys(['label', 'description', 'default_w', 'default_h', 'default_props', 'inspector_fields']);
    }
    expect($blueprint['schema_version'])->toBe(1)
        ->and($blueprint['layout_paradigm'])->toBe('flow')
        ->and($blueprint['page'])->toHaveKeys(['paper_size', 'orientation', 'width_mm', 'height_mm', 'margin_mm'])
        ->and(array_column($blueprint['components'], 'id'))->toBe([
            'header-1', 'kpi_grid-1', 'data_table-1', 'signature-1', 'footer-1',
        ])
        ->and($blueprint['components'][2]['props']['repeat_key'])->toBe('passers')
        ->and(array_column($blueprint['components'][2]['props']['columns'], 'key'))->toBe([
            'row_no', 'reference_code', 'applicant_name', 'program_code',
            'composite_score', 'stanine', 'rating', 'remarks',
        ]);
});

test('placeholder catalog defines institution, summary, passer, and signatory tags', function (): void {
    $catalog = passingReportSurface()->placeholderCatalog();

    expect($catalog['required'])->toContain(
        'institution_name', 'institution_code', 'academic_cycle', 'report_date',
        'cutoff_score', 'total_examinees', 'total_passed', 'passing_rate',
        'passers.row_no', 'passers.reference_code', 'passers.applicant_name',
        'passers.sex', 'passers.strand', 'passers.program_code', 'passers.composite_score',
        'passers.stanine', 'passers.rating', 'passers.remarks',
        'prepared_by', 'reviewed_by', 'approved_by',
    )
        ->and($catalog['all'])->toBe($catalog['required']);
});

test('sample context has realistic five-row passer data matching catalog row keys', function (): void {
    $surface = passingReportSurface();
    $context = $surface->sampleContext();
    $rowKeys = array_keys($context['passers'][0]);
    $catalogRowKeys = array_values(array_map(
        static fn (string $placeholder): string => substr($placeholder, strlen('passers.')),
        array_filter($surface->placeholderCatalog()['required'], static fn (string $placeholder): bool => str_starts_with($placeholder, 'passers.')),
    ));

    expect($context['passers'])->toHaveCount(5)
        ->and($rowKeys)->toBe($catalogRowKeys)
        ->and($context['total_passed'])->toBe(5)
        ->and($context['passers'][0]['reference_code'])->toStartWith('NOA-')
        ->and($context['passers'][0]['stanine'])->toBeGreaterThanOrEqual(1)
        ->and($context['passers'][0]['stanine'])->toBeLessThanOrEqual(9)
        ->and($context['passers'][0]['rating'])->not->toBeEmpty();
});
test('surface resolve data matches its sample context key contract', function (): void {
    seedE2Foundation();
    $surface = passingReportSurface();
    $report = $surface->resolveData(['academic_cycle_id' => AcademicCycle::active()?->id]);

    expect(array_keys($report))->toBe(array_keys($surface->sampleContext()));
});
