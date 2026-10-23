<?php

namespace App\Reports\Surfaces;

use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\DocumentTemplate\Enums\LayoutParadigm;
use App\Reports\Queries\ApplicationsCensusDataQuery;

final class ApplicationsCensusSurface implements DocumentSurfaceInterface
{
    public function __construct(private readonly ApplicationsCensusDataQuery $dataQuery) {}

    public function surfaceKey(): string
    {
        return 'applications_census';
    }

    public function title(): string
    {
        return 'Applications Summary Census';
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
                'description' => 'Institution identity and census title.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['title' => $this->title(), 'subtitle' => '{{academic_cycle}}'],
                'inspector_fields' => ['title', 'subtitle', 'align'],
            ],
            'kpi_grid' => [
                'label' => 'Application metrics',
                'description' => 'Total, accepted, pending, and dismissed applicant totals.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['metrics' => $this->kpiMetrics()],
                'inspector_fields' => ['metrics'],
            ],
            'data_table' => [
                'label' => 'Applicants table',
                'description' => 'Repeating rows of applicant biographical and admission details.',
                'default_w' => 186,
                'default_h' => 96,
                'default_props' => ['repeat_key' => 'applications', 'columns' => $this->columns()],
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
                ['id' => 'data_table-1', 'type' => 'data_table', 'x' => 0, 'y' => 56, 'w' => $fullWidth, 'h' => 96, 'props' => ['repeat_key' => 'applications', 'columns' => $this->columns()]],
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
            ['key' => 'reference_code', 'label' => 'Reference'],
            ['key' => 'applicant_name', 'label' => 'Applicant Name'],
            ['key' => 'sex', 'label' => 'Sex'],
            ['key' => 'strand', 'label' => 'Strand'],
            ['key' => 'gwa', 'label' => 'GWA'],
            ['key' => 'first_choice', 'label' => '1st Choice'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'submitted_at', 'label' => 'Submitted'],
        ];
    }

    /** @return list<array{label: string, binding: string}> */
    private function kpiMetrics(): array
    {
        return [
            ['label' => 'Total Applications', 'binding' => 'total_applications'],
            ['label' => 'Accepted', 'binding' => 'accepted_count'],
            ['label' => 'Pending Review', 'binding' => 'pending_count'],
            ['label' => 'Needs Correction', 'binding' => 'needs_correction_count'],
        ];
    }

    /** @return array<string, mixed> */
    public function placeholderCatalog(): array
    {
        $placeholders = [
            'institution_name', 'institution_code', 'academic_cycle', 'report_date',
            'total_applications', 'accepted_count', 'pending_count', 'needs_correction_count', 'dismissed_count',
            'applications.row_no', 'applications.reference_code', 'applications.applicant_name',
            'applications.sex', 'applications.birthdate', 'applications.strand', 'applications.gwa',
            'applications.school', 'applications.first_choice', 'applications.second_choice',
            'applications.status', 'applications.submitted_at',
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
            'total_applications' => 250,
            'accepted_count' => 180,
            'pending_count' => 50,
            'needs_correction_count' => 15,
            'dismissed_count' => 5,
            'applications' => [
                ['row_no' => 1, 'reference_code' => 'AY26-00001', 'applicant_name' => 'Amara Santos', 'sex' => 'Female', 'birthdate' => '2007-04-12', 'strand' => 'STEM', 'gwa' => '1.75', 'school' => 'Ilocos Sur National HS', 'first_choice' => 'BSN', 'second_choice' => 'BSIT', 'status' => 'Accepted', 'submitted_at' => '2026-08-12'],
                ['row_no' => 2, 'reference_code' => 'AY26-00002', 'applicant_name' => 'Gabriel Reyes', 'sex' => 'Male', 'birthdate' => '2007-06-21', 'strand' => 'STEM', 'gwa' => '1.82', 'school' => 'Candon City High School', 'first_choice' => 'BSIT', 'second_choice' => 'BSCS', 'status' => 'Accepted', 'submitted_at' => '2026-08-14'],
                ['row_no' => 3, 'reference_code' => 'AY26-00003', 'applicant_name' => 'Isabella Cruz', 'sex' => 'Female', 'birthdate' => '2006-11-05', 'strand' => 'HUMSS', 'gwa' => '1.91', 'school' => 'Tagudin National HS', 'first_choice' => 'BSED', 'second_choice' => 'BEED', 'status' => 'Pending', 'submitted_at' => '2026-08-17'],
            ],
            'prepared_by' => 'Admissions Clerk',
            'verified_by' => 'Admissions Officer',
            'approved_by' => 'University Registrar',
        ];
    }

    public function repeatingRowKey(): ?string
    {
        return 'applications';
    }

    /** @return list<string> */
    public function repeatingRegions(): array
    {
        return ['applications'];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function resolveData(array $filters): array
    {
        return $this->dataQuery->handle($filters);
    }
}
