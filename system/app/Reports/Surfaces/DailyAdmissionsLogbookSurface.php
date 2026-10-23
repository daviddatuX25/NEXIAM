<?php

namespace App\Reports\Surfaces;

use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\DocumentTemplate\Enums\LayoutParadigm;
use App\Reports\Queries\DailyAdmissionsLogbookDataQuery;

final class DailyAdmissionsLogbookSurface implements DocumentSurfaceInterface
{
    public function __construct(private readonly DailyAdmissionsLogbookDataQuery $dataQuery) {}

    public function surfaceKey(): string
    {
        return 'daily_admissions_logbook';
    }

    public function title(): string
    {
        return 'Daily Admissions Counter Logbook';
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
                'description' => 'Institution identity and daily admissions logbook title.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['title' => $this->title(), 'subtitle' => '{{academic_cycle}} · Intake Date: {{log_date}}'],
                'inspector_fields' => ['title', 'subtitle', 'align'],
            ],
            'kpi_grid' => [
                'label' => 'Daily counter KPIs',
                'description' => 'Total applicants logged today, accepted, pending, and needs correction.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['metrics' => $this->kpiMetrics()],
                'inspector_fields' => ['metrics'],
            ],
            'data_table' => [
                'label' => 'Admissions log table',
                'description' => 'Repeating entries of applicants arriving or processed today.',
                'default_w' => 186,
                'default_h' => 96,
                'default_props' => ['repeat_key' => 'admissions_log', 'columns' => $this->columns()],
                'inspector_fields' => ['repeat_key', 'columns'],
            ],
            'signature' => [
                'label' => 'Signature',
                'description' => 'Named report preparer, verifier, or approver.',
                'default_w' => 56,
                'default_h' => 22,
                'default_props' => ['label' => 'Receiving Officer', 'binding' => 'prepared_by'],
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
                ['id' => 'header-1', 'type' => 'header', 'x' => 0, 'y' => 0, 'w' => $fullWidth, 'h' => 24, 'props' => ['title' => $this->title(), 'subtitle' => '{{academic_cycle}} · Intake Date: {{log_date}}']],
                ['id' => 'kpi_grid-1', 'type' => 'kpi_grid', 'x' => 0, 'y' => 28, 'w' => $fullWidth, 'h' => 24, 'props' => ['metrics' => $this->kpiMetrics()]],
                ['id' => 'data_table-1', 'type' => 'data_table', 'x' => 0, 'y' => 56, 'w' => $fullWidth, 'h' => 96, 'props' => ['repeat_key' => 'admissions_log', 'columns' => $this->columns()]],
                ['id' => 'signature-1', 'type' => 'signature', 'x' => 0, 'y' => 160, 'w' => 56, 'h' => 22, 'props' => ['label' => 'Receiving Officer', 'binding' => 'prepared_by']],
                ['id' => 'footer-1', 'type' => 'footer', 'x' => 0, 'y' => 190, 'w' => $fullWidth, 'h' => 8, 'props' => ['text' => 'Generated on {{report_date}} · Page {{page_i}} of {{page_all}}', 'align' => 'center']],
            ],
        ];
    }

    /** @return list<array{key: string, label: string}> */
    private function columns(): array
    {
        return [
            ['key' => 'row_no', 'label' => '#'],
            ['key' => 'time_in', 'label' => 'Time In'],
            ['key' => 'reference_code', 'label' => 'Reference'],
            ['key' => 'applicant_name', 'label' => 'Applicant Name'],
            ['key' => 'sex', 'label' => 'Sex'],
            ['key' => 'strand', 'label' => 'Strand'],
            ['key' => 'preferred_program', 'label' => 'Target Program'],
            ['key' => 'transaction_status', 'label' => 'Status / Disposition'],
            ['key' => 'attending_officer', 'label' => 'Attending Desk'],
        ];
    }

    /** @return list<array{label: string, binding: string}> */
    private function kpiMetrics(): array
    {
        return [
            ['label' => 'Intake Today', 'binding' => 'total_intake_today'],
            ['label' => 'Accepted', 'binding' => 'accepted_today'],
            ['label' => 'Pending Review', 'binding' => 'pending_today'],
            ['label' => 'Needs Correction', 'binding' => 'needs_correction_today'],
        ];
    }

    /** @return array<string, mixed> */
    public function placeholderCatalog(): array
    {
        $placeholders = [
            'institution_name', 'institution_code', 'academic_cycle', 'report_date', 'log_date',
            'total_intake_today', 'accepted_today', 'pending_today', 'needs_correction_today',
            'admissions_log.row_no', 'admissions_log.time_in', 'admissions_log.reference_code',
            'admissions_log.applicant_name', 'admissions_log.sex', 'admissions_log.strand',
            'admissions_log.preferred_program', 'admissions_log.school', 'admissions_log.transaction_status',
            'admissions_log.attending_officer',
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
            'log_date' => '2026-10-14',
            'total_intake_today' => 35,
            'accepted_today' => 28,
            'pending_today' => 5,
            'needs_correction_today' => 2,
            'admissions_log' => [
                ['row_no' => 1, 'time_in' => '8:15 AM', 'reference_code' => 'AY26-00001', 'applicant_name' => 'Amara Santos', 'sex' => 'Female', 'strand' => 'STEM', 'preferred_program' => 'BSN', 'school' => 'Ilocos Sur National HS', 'transaction_status' => 'Accepted', 'attending_officer' => 'Admissions Desk'],
                ['row_no' => 2, 'time_in' => '8:30 AM', 'reference_code' => 'AY26-00002', 'applicant_name' => 'Gabriel Reyes', 'sex' => 'Male', 'strand' => 'STEM', 'preferred_program' => 'BSIT', 'school' => 'Candon City HS', 'transaction_status' => 'Accepted', 'attending_officer' => 'Admissions Desk'],
                ['row_no' => 3, 'time_in' => '8:45 AM', 'reference_code' => 'AY26-00003', 'applicant_name' => 'Isabella Cruz', 'sex' => 'Female', 'strand' => 'HUMSS', 'preferred_program' => 'BSED', 'school' => 'Tagudin National HS', 'transaction_status' => 'Needs Correction', 'attending_officer' => 'Admissions Desk'],
            ],
            'prepared_by' => 'Receiving Clerk',
            'verified_by' => 'Admissions Officer',
            'approved_by' => 'University Registrar',
        ];
    }

    public function repeatingRowKey(): ?string
    {
        return 'admissions_log';
    }

    /** @return list<string> */
    public function repeatingRegions(): array
    {
        return ['admissions_log'];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function resolveData(array $filters): array
    {
        return $this->dataQuery->handle($filters);
    }
}
