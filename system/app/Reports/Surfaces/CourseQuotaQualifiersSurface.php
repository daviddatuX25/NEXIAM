<?php

namespace App\Reports\Surfaces;

use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\DocumentTemplate\Enums\LayoutParadigm;
use App\Reports\Queries\CourseQuotaQualifiersDataQuery;

final class CourseQuotaQualifiersSurface implements DocumentSurfaceInterface
{
    public function __construct(private readonly CourseQuotaQualifiersDataQuery $dataQuery) {}

    public function surfaceKey(): string
    {
        return 'course_quota_qualifiers';
    }

    public function title(): string
    {
        return 'Pre-Enrollment Qualifiers & Quota Status';
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
                'description' => 'Institution identity and qualifier report title.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['title' => $this->title(), 'subtitle' => '{{academic_cycle}}'],
                'inspector_fields' => ['title', 'subtitle', 'align'],
            ],
            'kpi_grid' => [
                'label' => 'Quota summary',
                'description' => 'Quota, accepted applicants, open slots, and fill rate.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['metrics' => $this->quotaMetrics()],
                'inspector_fields' => ['metrics'],
            ],
            'data_table' => [
                'label' => 'Accepted qualifiers table',
                'description' => 'Repeating rows of applicants accepted for pre-enrollment.',
                'default_w' => 186,
                'default_h' => 96,
                'default_props' => ['repeat_key' => 'qualifiers', 'columns' => $this->qualifierColumns()],
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
                'default_props' => ['text' => 'Generated on {{report_date}}', 'align' => 'center'],
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
                ['id' => 'kpi_grid-1', 'type' => 'kpi_grid', 'x' => 0, 'y' => 28, 'w' => $fullWidth, 'h' => 24, 'props' => ['metrics' => $this->defaultQuotaMetrics()]],
                ['id' => 'data_table-1', 'type' => 'data_table', 'x' => 0, 'y' => 56, 'w' => $fullWidth, 'h' => 96, 'props' => ['repeat_key' => 'qualifiers', 'columns' => $this->qualifierColumns()]],
                ['id' => 'signature-1', 'type' => 'signature', 'x' => 0, 'y' => 160, 'w' => 56, 'h' => 22, 'props' => ['label' => 'Prepared by', 'binding' => 'prepared_by']],
                ['id' => 'footer-1', 'type' => 'footer', 'x' => 0, 'y' => 190, 'w' => $fullWidth, 'h' => 8, 'props' => ['text' => 'Generated on {{report_date}}', 'align' => 'center']],
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function qualifierColumns(): array
    {
        return [
            ['key' => 'row_no', 'label' => '#'],
            ['key' => 'reference_code', 'label' => 'Reference'],
            ['key' => 'applicant_name', 'label' => 'Applicant'],
            ['key' => 'strand', 'label' => 'Strand'],
            ['key' => 'gwa', 'label' => 'GWA'],
            ['key' => 'program_code', 'label' => 'Program'],
            ['key' => 'admission_status', 'label' => 'Status'],
            ['key' => 'accepted_at', 'label' => 'Accepted'],
        ];
    }

    /**
     * @return list<array{label: string, binding: string}>
     */
    private function quotaMetrics(): array
    {
        return [
            ['label' => 'Target Quota', 'binding' => 'target_quota'],
            ['label' => 'Accepted Count', 'binding' => 'accepted_count'],
            ['label' => 'Committed Reservations', 'binding' => 'committed_reservations'],
            ['label' => 'Remaining Slots', 'binding' => 'remaining_slots'],
            ['label' => 'Remaining Quota', 'binding' => 'remaining_quota'],
            ['label' => 'Fill Rate', 'binding' => 'fill_rate'],
            ['label' => 'Committed Fill Rate', 'binding' => 'committed_fill_rate'],
        ];
    }

    /**
     * @return list<array{label: string, binding: string}>
     */
    private function defaultQuotaMetrics(): array
    {
        return [
            ['label' => 'Target Quota', 'binding' => 'target_quota'],
            ['label' => 'Accepted Count', 'binding' => 'accepted_count'],
            ['label' => 'Remaining Slots', 'binding' => 'remaining_slots'],
            ['label' => 'Fill Rate', 'binding' => 'fill_rate'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function placeholderCatalog(): array
    {
        $placeholders = [
            'institution_name', 'institution_code', 'academic_cycle', 'report_date',
            'target_quota', 'accepted_count', 'committed_reservations', 'remaining_slots',
            'remaining_quota', 'fill_rate', 'committed_fill_rate',
            'qualifiers.row_no', 'qualifiers.reference_code', 'qualifiers.applicant_name',
            'qualifiers.strand', 'qualifiers.gwa', 'qualifiers.program_code',
            'qualifiers.admission_status', 'qualifiers.accepted_at',
            'prepared_by', 'verified_by', 'approved_by',
        ];

        return [
            'required' => $placeholders,
            'recommended' => [],
            'all' => $placeholders,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sampleContext(): array
    {
        return [
            'institution_name' => 'NEXIAM Institute of Technology',
            'institution_code' => 'SYN-INST-001',
            'academic_cycle' => 'Academic Year 2026–2027 · First Semester',
            'report_date' => '2026-09-30',
            'target_quota' => 120,
            'accepted_count' => 84,
            'committed_reservations' => 80,
            'remaining_slots' => 36,
            'remaining_quota' => 40,
            'fill_rate' => '70.00%',
            'committed_fill_rate' => '66.67%',
            'qualifiers' => [
                ['row_no' => 1, 'reference_code' => 'AY26-00001', 'applicant_name' => 'Amara Santos', 'strand' => 'STEM', 'gwa' => '1.75', 'program_code' => 'BSN', 'admission_status' => 'Accepted', 'accepted_at' => '2026-08-12'],
                ['row_no' => 2, 'reference_code' => 'AY26-00002', 'applicant_name' => 'Gabriel Reyes', 'strand' => 'STEM', 'gwa' => '1.82', 'program_code' => 'BSIT', 'admission_status' => 'Accepted', 'accepted_at' => '2026-08-14'],
                ['row_no' => 3, 'reference_code' => 'AY26-00003', 'applicant_name' => 'Isabella Cruz', 'strand' => 'HUMSS', 'gwa' => '1.91', 'program_code' => 'BSED', 'admission_status' => 'Accepted', 'accepted_at' => '2026-08-17'],
                ['row_no' => 4, 'reference_code' => 'AY26-00004', 'applicant_name' => 'Mateo Garcia', 'strand' => 'ABM', 'gwa' => '2.05', 'program_code' => 'BSBA', 'admission_status' => 'Accepted', 'accepted_at' => '2026-08-19'],
                ['row_no' => 5, 'reference_code' => 'AY26-00005', 'applicant_name' => 'Sofia Mendoza', 'strand' => 'STEM', 'gwa' => '1.68', 'program_code' => 'BSCRIM', 'admission_status' => 'Accepted', 'accepted_at' => '2026-08-22'],
            ],
            'prepared_by' => 'Registrar Clerk',
            'verified_by' => 'Admissions Officer',
            'approved_by' => 'Campus Registrar',
        ];
    }

    public function repeatingRowKey(): ?string
    {
        return 'qualifiers';
    }

    /**
     * @return list<string>
     */
    public function repeatingRegions(): array
    {
        return ['qualifiers'];
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
