<?php

namespace App\Reports\Surfaces;

use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\DocumentTemplate\Enums\LayoutParadigm;
use App\Reports\Queries\CollegeProgramQuotaSummaryDataQuery;

final class CollegeProgramQuotaSummarySurface implements DocumentSurfaceInterface
{
    public function __construct(private readonly CollegeProgramQuotaSummaryDataQuery $dataQuery) {}

    public function surfaceKey(): string
    {
        return 'college_program_quota_summary';
    }

    public function title(): string
    {
        return 'College Program Quota & Enrollment Summary';
    }

    public function layoutParadigm(): LayoutParadigm
    {
        return LayoutParadigm::Flow;
    }

    /** @return array<string, array<string, mixed>> */
    public function componentCatalog(): array
    {
        return [
            'header' => [
                'label' => 'Report header',
                'description' => 'Institution identity and college quota summary title.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['title' => $this->title(), 'subtitle' => '{{academic_cycle}}'],
                'inspector_fields' => ['title', 'subtitle', 'align'],
            ],
            'kpi_grid' => [
                'label' => 'Quota KPIs',
                'description' => 'Total programs, planned quota, placed candidates, and overall fill rate.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['metrics' => $this->kpiMetrics()],
                'inspector_fields' => ['metrics'],
            ],
            'data_table' => [
                'label' => 'Programs table',
                'description' => 'Repeating rows of colleges, degree programs, quotas, first choices, and placed counts.',
                'default_w' => 186,
                'default_h' => 96,
                'default_props' => ['repeat_key' => 'programs', 'columns' => $this->columns()],
                'inspector_fields' => ['repeat_key', 'columns'],
            ],
            'signature' => [
                'label' => 'Signature',
                'description' => 'Named report preparer, verifier, or approver.',
                'default_w' => 56,
                'default_h' => 22,
                'default_props' => ['label' => 'Prepared by', 'binding' => 'prepared_by'],
                'inspector_fields' => ['label', 'binding'],
            ],
            'footer' => [
                'label' => 'Footer',
                'description' => 'Report footer text with timestamp and pagination.',
                'default_w' => 186,
                'default_h' => 8,
                'default_props' => ['text' => 'Generated on {{report_date}} · Page {{page_i}} of {{page_all}}', 'align' => 'center'],
                'inspector_fields' => ['text', 'align'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function defaultBlueprint(string $paperSize = 'a4', string $orientation = 'portrait'): array
    {
        $paperSize = in_array($paperSize, ['a4', 'legal', 'letter'], true) ? $paperSize : 'a4';
        $orientation = in_array($orientation, ['portrait', 'landscape'], true) ? $orientation : 'portrait';
        $dimensions = match ($paperSize) {
            'legal' => ['width_mm' => 216, 'height_mm' => 356],
            'letter' => ['width_mm' => 216, 'height_mm' => 279],
            default => ['width_mm' => 210, 'height_mm' => 297],
        };
        if ($orientation === 'landscape') {
            $dimensions = ['width_mm' => $dimensions['height_mm'], 'height_mm' => $dimensions['width_mm']];
        }
        $fullWidth = $dimensions['width_mm'] - 24;

        return [
            'schema_version' => 1,
            'layout_paradigm' => 'flow',
            'page' => [
                'paper_size' => $paperSize,
                'orientation' => $orientation,
                'width_mm' => $dimensions['width_mm'],
                'height_mm' => $dimensions['height_mm'],
                'margin_mm' => 12,
            ],
            'components' => [
                ['id' => 'header-1', 'type' => 'header', 'x' => 0, 'y' => 0, 'w' => $fullWidth, 'h' => 24, 'props' => ['title' => $this->title(), 'subtitle' => '{{academic_cycle}}']],
                ['id' => 'kpi_grid-1', 'type' => 'kpi_grid', 'x' => 0, 'y' => 28, 'w' => $fullWidth, 'h' => 24, 'props' => ['metrics' => $this->kpiMetrics()]],
                ['id' => 'data_table-1', 'type' => 'data_table', 'x' => 0, 'y' => 56, 'w' => $fullWidth, 'h' => 96, 'props' => ['repeat_key' => 'programs', 'columns' => $this->columns()]],
                ['id' => 'signature-1', 'type' => 'signature', 'x' => 0, 'y' => 160, 'w' => 56, 'h' => 22, 'props' => ['label' => 'Prepared by', 'binding' => 'prepared_by']],
                ['id' => 'footer-1', 'type' => 'footer', 'x' => 0, 'y' => 190, 'w' => $fullWidth, 'h' => 8, 'props' => ['text' => 'Generated on {{report_date}} · Page {{page_i}} of {{page_all}}', 'align' => 'center']],
            ],
        ];
    }

    /** @return list<array{key: string, label: string}> */
    private function columns(): array
    {
        return [
            ['key' => 'row_no', 'label' => '#'],
            ['key' => 'college_code', 'label' => 'College'],
            ['key' => 'program_code', 'label' => 'Program Code'],
            ['key' => 'program_name', 'label' => 'Degree Program'],
            ['key' => 'planned_quota', 'label' => 'Planned Quota'],
            ['key' => 'first_choice_demand', 'label' => '1st Choice'],
            ['key' => 'placed_count', 'label' => 'Placed'],
            ['key' => 'open_slots', 'label' => 'Open Slots'],
            ['key' => 'fill_rate', 'label' => 'Fill %'],
        ];
    }

    /** @return list<array{label: string, binding: string}> */
    private function kpiMetrics(): array
    {
        return [
            ['label' => 'Active Programs', 'binding' => 'total_programs'],
            ['label' => 'Total Planned Quota', 'binding' => 'total_planned_quota'],
            ['label' => 'Placed Candidates', 'binding' => 'total_placed_candidates'],
            ['label' => 'Overall Fill Rate', 'binding' => 'overall_fill_rate'],
        ];
    }

    /** @return array<string, mixed> */
    public function placeholderCatalog(): array
    {
        $placeholders = [
            'institution_name', 'institution_code', 'academic_cycle', 'report_date',
            'total_programs', 'total_planned_quota', 'total_placed_candidates', 'total_first_choice_demand', 'overall_fill_rate',
            'programs.row_no', 'programs.college_code', 'programs.program_code', 'programs.program_name',
            'programs.planned_quota', 'programs.first_choice_demand', 'programs.placed_count',
            'programs.open_slots', 'programs.fill_rate',
            'prepared_by', 'verified_by', 'approved_by',
        ];

        return ['required' => $placeholders, 'recommended' => [], 'all' => $placeholders];
    }

    /** @return array<string, mixed> */
    public function sampleContext(): array
    {
        return [
            'institution_name' => 'NEXIAM Institute of Technology',
            'institution_code' => 'SYN-INST-001',
            'academic_cycle' => 'Academic Year 2026–2027 · First Semester',
            'report_date' => '2026-10-14',
            'total_programs' => 6,
            'total_planned_quota' => 300,
            'total_placed_candidates' => 245,
            'total_first_choice_demand' => 380,
            'overall_fill_rate' => '81.7%',
            'programs' => [
                ['row_no' => 1, 'college_code' => 'CCS', 'program_code' => 'BSIT', 'program_name' => 'BS Information Technology', 'planned_quota' => 60, 'first_choice_demand' => 95, 'placed_count' => 60, 'open_slots' => 0, 'fill_rate' => '100.0%'],
                ['row_no' => 2, 'college_code' => 'CCS', 'program_code' => 'BSCS', 'program_name' => 'BS Computer Science', 'planned_quota' => 45, 'first_choice_demand' => 60, 'placed_count' => 42, 'open_slots' => 3, 'fill_rate' => '93.3%'],
                ['row_no' => 3, 'college_code' => 'COE', 'program_code' => 'BSED', 'program_name' => 'Bachelor of Secondary Education', 'planned_quota' => 50, 'first_choice_demand' => 55, 'placed_count' => 48, 'open_slots' => 2, 'fill_rate' => '96.0%'],
            ],
            'prepared_by' => 'Dean / Department Chair',
            'verified_by' => 'Academic Affairs Coordinator',
            'approved_by' => 'Vice President for Academic Affairs',
        ];
    }

    public function repeatingRowKey(): ?string
    {
        return 'programs';
    }

    /** @return list<string> */
    public function repeatingRegions(): array
    {
        return ['programs'];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function resolveData(array $filters): array
    {
        return $this->dataQuery->handle($filters);
    }
}
