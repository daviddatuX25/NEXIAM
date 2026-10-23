<?php

namespace App\Reports\Surfaces;

use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\DocumentTemplate\Enums\LayoutParadigm;
use App\Reports\Queries\StaffAccomplishmentDataQuery;

final class StaffAccomplishmentSurface implements DocumentSurfaceInterface
{
    public function __construct(private readonly StaffAccomplishmentDataQuery $dataQuery) {}

    public function surfaceKey(): string
    {
        return 'staff_accomplishment_report';
    }

    public function title(): string
    {
        return 'Universal Staff Accomplishment & Operational Activity Audit';
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
                'description' => 'Institution identity and staff accomplishment audit title.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['title' => $this->title(), 'subtitle' => '{{academic_cycle}} · {{reporting_period}}'],
                'inspector_fields' => ['title', 'subtitle', 'align'],
            ],
            'kpi_grid' => [
                'label' => 'Accomplishment KPIs',
                'description' => 'Milestones tracked, operations volume, and audit events logged.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['metrics' => $this->kpiMetrics()],
                'inspector_fields' => ['metrics'],
            ],
            'data_table' => [
                'label' => 'Accomplishments table',
                'description' => 'Repeating rows of operational milestones, volumes, and yields.',
                'default_w' => 186,
                'default_h' => 96,
                'default_props' => ['repeat_key' => 'accomplishments', 'columns' => $this->columns()],
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
                ['id' => 'header-1', 'type' => 'header', 'x' => 0, 'y' => 0, 'w' => $fullWidth, 'h' => 24, 'props' => ['title' => $this->title(), 'subtitle' => '{{academic_cycle}} · {{reporting_period}}']],
                ['id' => 'kpi_grid-1', 'type' => 'kpi_grid', 'x' => 0, 'y' => 28, 'w' => $fullWidth, 'h' => 24, 'props' => ['metrics' => $this->kpiMetrics()]],
                ['id' => 'data_table-1', 'type' => 'data_table', 'x' => 0, 'y' => 56, 'w' => $fullWidth, 'h' => 96, 'props' => ['repeat_key' => 'accomplishments', 'columns' => $this->columns()]],
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
            ['key' => 'office', 'label' => 'Office / Unit'],
            ['key' => 'activity_milestone', 'label' => 'Strategic Milestone Activity'],
            ['key' => 'standard_metric', 'label' => 'Performance Indicator'],
            ['key' => 'volume_processed', 'label' => 'Processed Volume'],
            ['key' => 'completion_status', 'label' => 'Status'],
            ['key' => 'benchmark_yield', 'label' => 'Target Yield'],
        ];
    }

    /** @return list<array{label: string, binding: string}> */
    private function kpiMetrics(): array
    {
        return [
            ['label' => 'Tracked Milestones', 'binding' => 'total_milestones'],
            ['label' => 'Total Operations Volume', 'binding' => 'total_operations_volume'],
            ['label' => 'Audit Events Verified', 'binding' => 'total_audit_events'],
            ['label' => 'Reporting Period', 'binding' => 'reporting_period'],
        ];
    }

    /** @return array<string, mixed> */
    public function placeholderCatalog(): array
    {
        $placeholders = [
            'institution_name', 'institution_code', 'academic_cycle', 'report_date',
            'total_milestones', 'total_operations_volume', 'total_audit_events', 'reporting_period',
            'accomplishments.row_no', 'accomplishments.office', 'accomplishments.activity_milestone',
            'accomplishments.standard_metric', 'accomplishments.volume_processed',
            'accomplishments.completion_status', 'accomplishments.benchmark_yield',
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
            'total_milestones' => 5,
            'total_operations_volume' => 1420,
            'total_audit_events' => 5800,
            'reporting_period' => 'Academic Year 2026–2027',
            'accomplishments' => [
                ['row_no' => 1, 'office' => 'Registrar Office', 'activity_milestone' => 'Application Intake & Dossier Processing', 'standard_metric' => 'Total Applications Received', 'volume_processed' => 500, 'completion_status' => 'Completed', 'benchmark_yield' => '100%'],
                ['row_no' => 2, 'office' => 'Registrar Office', 'activity_milestone' => 'Verification & Requirement Endorsement', 'standard_metric' => 'Qualified & Accepted Applicants', 'volume_processed' => 420, 'completion_status' => 'Completed', 'benchmark_yield' => '84.0%'],
                ['row_no' => 3, 'office' => 'Guidance & Testing', 'activity_milestone' => 'Institutional Exam Administration', 'standard_metric' => 'Present Examinees Tested', 'volume_processed' => 400, 'completion_status' => 'Verified', 'benchmark_yield' => '95.2%'],
            ],
            'prepared_by' => 'Administrative Officer',
            'verified_by' => 'Head of Human Resources / Admin',
            'approved_by' => 'University President',
        ];
    }

    public function repeatingRowKey(): ?string
    {
        return 'accomplishments';
    }

    /** @return list<string> */
    public function repeatingRegions(): array
    {
        return ['accomplishments'];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function resolveData(array $filters): array
    {
        return $this->dataQuery->handle($filters);
    }
}
