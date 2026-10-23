<?php

namespace App\Reports\Surfaces;

use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\DocumentTemplate\Enums\LayoutParadigm;
use App\Reports\Queries\PassingReportDataQuery;

final class PassingReportSurface implements DocumentSurfaceInterface
{
    public function __construct(private readonly PassingReportDataQuery $dataQuery) {}

    public function surfaceKey(): string
    {
        return 'passing_report';
    }

    public function title(): string
    {
        return 'Passing Report';
    }

    public function layoutParadigm(): LayoutParadigm
    {
        return LayoutParadigm::Flow;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function componentCatalog(): array
    {
        return [
            'header' => [
                'label' => 'Report header',
                'description' => 'Institution identity and passing report title.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['title' => 'Passing Report', 'subtitle' => '{{academic_cycle}}'],
                'inspector_fields' => ['title', 'subtitle', 'align'],
            ],
            'kpi_grid' => [
                'label' => 'Summary metrics',
                'description' => 'Examinee count, passes, and passing rate.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['items' => ['total_examinees', 'total_passed', 'passing_rate']],
                'inspector_fields' => ['items'],
            ],
            'data_table' => [
                'label' => 'Passers table',
                'description' => 'Repeating rows of applicants who met the cutoff.',
                'default_w' => 186,
                'default_h' => 96,
                'default_props' => ['repeat_key' => 'passers', 'columns' => $this->passerColumns()],
                'inspector_fields' => ['repeat_key', 'columns'],
            ],
            'signature' => [
                'label' => 'Signature',
                'description' => 'Named report preparer, reviewer, or approver.',
                'default_w' => 56,
                'default_h' => 22,
                'default_props' => ['label' => 'Prepared by', 'binding' => 'prepared_by'],
                'inspector_fields' => ['label', 'binding'],
            ],
            'text' => [
                'label' => 'Text',
                'description' => 'Static or placeholder-bound report text.',
                'default_w' => 186,
                'default_h' => 10,
                'default_props' => ['text' => ''],
                'inspector_fields' => ['text', 'align'],
            ],
            'note' => [
                'label' => 'Note',
                'description' => 'Supplementary report note.',
                'default_w' => 186,
                'default_h' => 14,
                'default_props' => ['text' => ''],
                'inspector_fields' => ['text'],
            ],
            'footer' => [
                'label' => 'Footer',
                'description' => 'Report footer text.',
                'default_w' => 186,
                'default_h' => 8,
                'default_props' => ['text' => ''],
                'inspector_fields' => ['text', 'align'],
            ],
            'divider' => [
                'label' => 'Divider',
                'description' => 'Horizontal section divider.',
                'default_w' => 186,
                'default_h' => 2,
                'default_props' => [],
                'inspector_fields' => ['style'],
            ],
            'spacer' => [
                'label' => 'Spacer',
                'description' => 'Vertical space between report sections.',
                'default_w' => 186,
                'default_h' => 8,
                'default_props' => [],
                'inspector_fields' => [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
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
        $columns = $this->passerColumns();
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
                ['id' => 'header-1', 'type' => 'header', 'x' => 0, 'y' => 0, 'w' => $fullWidth, 'h' => 24, 'props' => ['title' => 'Passing Report', 'subtitle' => '{{academic_cycle}}']],
                ['id' => 'kpi_grid-1', 'type' => 'kpi_grid', 'x' => 0, 'y' => 28, 'w' => $fullWidth, 'h' => 24, 'props' => ['items' => ['total_examinees', 'total_passed', 'passing_rate']]],
                ['id' => 'data_table-1', 'type' => 'data_table', 'x' => 0, 'y' => 56, 'w' => $fullWidth, 'h' => 96, 'props' => ['repeat_key' => 'passers', 'columns' => $columns]],
                ['id' => 'signature-1', 'type' => 'signature', 'x' => 0, 'y' => 160, 'w' => 56, 'h' => 22, 'props' => ['label' => 'Prepared by', 'binding' => 'prepared_by']],
                ['id' => 'footer-1', 'type' => 'footer', 'x' => 0, 'y' => 190, 'w' => $fullWidth, 'h' => 8, 'props' => ['text' => 'Generated on {{report_date}}', 'align' => 'center']],
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function passerColumns(): array
    {
        return [
            ['key' => 'row_no', 'label' => '#'],
            ['key' => 'reference_code', 'label' => 'Reference'],
            ['key' => 'applicant_name', 'label' => 'Applicant'],
            ['key' => 'program_code', 'label' => 'Program'],
            ['key' => 'composite_score', 'label' => 'Score'],
            ['key' => 'stanine', 'label' => 'Stanine'],
            ['key' => 'rating', 'label' => 'Rating'],
            ['key' => 'remarks', 'label' => 'Remarks'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function placeholderCatalog(): array
    {
        return [
            'required' => [
                'institution_name', 'institution_code', 'academic_cycle', 'report_date',
                'cutoff_score', 'total_examinees', 'total_passed', 'passing_rate',
                'passers.row_no', 'passers.reference_code', 'passers.applicant_name',
                'passers.sex', 'passers.strand', 'passers.program_code',
                'passers.composite_score', 'passers.stanine', 'passers.rating', 'passers.remarks',
                'prepared_by', 'reviewed_by', 'approved_by',
            ],
            'recommended' => [],
            'all' => [
                'institution_name', 'institution_code', 'academic_cycle', 'report_date',
                'cutoff_score', 'total_examinees', 'total_passed', 'passing_rate',
                'passers.row_no', 'passers.reference_code', 'passers.applicant_name',
                'passers.sex', 'passers.strand', 'passers.program_code',
                'passers.composite_score', 'passers.stanine', 'passers.rating', 'passers.remarks',
                'prepared_by', 'reviewed_by', 'approved_by',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sampleContext(): array
    {
        $passers = [
            ['row_no' => 1, 'reference_code' => 'AY26-00001', 'applicant_name' => 'Amara Santos', 'sex' => 'female', 'strand' => 'STEM', 'program_code' => 'BSN', 'composite_score' => 94.25, 'stanine' => 9, 'rating' => 'Superior', 'remarks' => 'Passed'],
            ['row_no' => 2, 'reference_code' => 'AY26-00002', 'applicant_name' => 'Gabriel Reyes', 'sex' => 'male', 'strand' => 'STEM', 'program_code' => 'BSIT', 'composite_score' => 88.50, 'stanine' => 8, 'rating' => 'Above Average', 'remarks' => 'Passed'],
            ['row_no' => 3, 'reference_code' => 'AY26-00003', 'applicant_name' => 'Isabella Cruz', 'sex' => 'female', 'strand' => 'HUMSS', 'program_code' => 'BSED', 'composite_score' => 82.75, 'stanine' => 7, 'rating' => 'Above Average', 'remarks' => 'Passed'],
            ['row_no' => 4, 'reference_code' => 'AY26-00004', 'applicant_name' => 'Mateo Garcia', 'sex' => 'male', 'strand' => 'ABM', 'program_code' => 'BSBA', 'composite_score' => 78.00, 'stanine' => 6, 'rating' => 'Average', 'remarks' => 'Passed'],
            ['row_no' => 5, 'reference_code' => 'AY26-00005', 'applicant_name' => 'Sofia Mendoza', 'sex' => 'female', 'strand' => 'STEM', 'program_code' => 'BSCRIM', 'composite_score' => 75.25, 'stanine' => 5, 'rating' => 'Average', 'remarks' => 'Passed'],
        ];

        return [
            'institution_name' => 'NEXIAM Institute of Technology',
            'institution_code' => 'SYN-INST-001',
            'academic_cycle' => 'Academic Year 2026–2027 · First Semester',
            'report_date' => '2026-09-30',
            'cutoff_score' => 75.0,
            'total_examinees' => 8,
            'total_passed' => 5,
            'passing_rate' => '62.50%',
            'passers' => $passers,
            'prepared_by' => 'Admissions Records Officer',
            'reviewed_by' => 'Guidance Counselor',
            'approved_by' => 'Registrar',
        ];
    }

    public function repeatingRowKey(): ?string
    {
        return 'passers';
    }

    /**
     * @return list<string>
     */
    public function repeatingRegions(): array
    {
        return ['passers'];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function resolveData(array $filters): array
    {
        return $this->dataQuery->handle($filters);
    }
}
