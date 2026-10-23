<?php

namespace App\Reports\Surfaces;

use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\DocumentTemplate\Enums\LayoutParadigm;
use App\Reports\Queries\FeederHighSchoolOriginDataQuery;

final class FeederHighSchoolOriginSurface implements DocumentSurfaceInterface
{
    public function __construct(private readonly FeederHighSchoolOriginDataQuery $dataQuery) {}

    public function surfaceKey(): string
    {
        return 'feeder_high_school_origin';
    }

    public function title(): string
    {
        return 'Feeder High School & Geographic Origin Census';
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
                'description' => 'Institution identity and feeder school census title.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['title' => $this->title(), 'subtitle' => '{{academic_cycle}}'],
                'inspector_fields' => ['title', 'subtitle', 'align'],
            ],
            'kpi_grid' => [
                'label' => 'Geographic KPIs',
                'description' => 'Total schools, total applicants, top province, and top feeder school.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['metrics' => $this->kpiMetrics()],
                'inspector_fields' => ['metrics'],
            ],
            'data_table' => [
                'label' => 'Feeder schools table',
                'description' => 'Repeating rows of high schools and geographic distribution.',
                'default_w' => 186,
                'default_h' => 96,
                'default_props' => ['repeat_key' => 'feeder_schools', 'columns' => $this->columns()],
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
                ['id' => 'data_table-1', 'type' => 'data_table', 'x' => 0, 'y' => 56, 'w' => $fullWidth, 'h' => 96, 'props' => ['repeat_key' => 'feeder_schools', 'columns' => $this->columns()]],
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
            ['key' => 'school_name', 'label' => 'Senior High / Feeder School'],
            ['key' => 'city', 'label' => 'Municipality / City'],
            ['key' => 'province', 'label' => 'Province'],
            ['key' => 'applicant_count', 'label' => 'Applicants'],
            ['key' => 'accepted_count', 'label' => 'Accepted'],
            ['key' => 'conversion_rate', 'label' => 'Yield Rate'],
        ];
    }

    /** @return list<array{label: string, binding: string}> */
    private function kpiMetrics(): array
    {
        return [
            ['label' => 'Total Feeder Schools', 'binding' => 'total_schools'],
            ['label' => 'Total Applicants', 'binding' => 'total_applicants'],
            ['label' => 'Top Origin Province', 'binding' => 'top_province'],
            ['label' => 'Leading Feeder School', 'binding' => 'top_school'],
        ];
    }

    /** @return array<string, mixed> */
    public function placeholderCatalog(): array
    {
        $placeholders = [
            'institution_name', 'institution_code', 'academic_cycle', 'report_date',
            'total_schools', 'total_applicants', 'top_province', 'top_school',
            'feeder_schools.row_no', 'feeder_schools.school_name', 'feeder_schools.city',
            'feeder_schools.province', 'feeder_schools.applicant_count',
            'feeder_schools.accepted_count', 'feeder_schools.conversion_rate',
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
            'total_schools' => 45,
            'total_applicants' => 380,
            'top_province' => 'Ilocos Sur',
            'top_school' => 'Ilocos Sur National High School',
            'feeder_schools' => [
                ['row_no' => 1, 'school_name' => 'Ilocos Sur National High School', 'city' => 'Vigan City', 'province' => 'Ilocos Sur', 'applicant_count' => 120, 'accepted_count' => 95, 'conversion_rate' => '79.2%'],
                ['row_no' => 2, 'school_name' => 'Candon National High School', 'city' => 'Candon City', 'province' => 'Ilocos Sur', 'applicant_count' => 85, 'accepted_count' => 64, 'conversion_rate' => '75.3%'],
                ['row_no' => 3, 'school_name' => 'Tagudin National High School', 'city' => 'Tagudin', 'province' => 'Ilocos Sur', 'applicant_count' => 45, 'accepted_count' => 32, 'conversion_rate' => '71.1%'],
            ],
            'prepared_by' => 'Admissions Clerk',
            'verified_by' => 'Admissions Officer',
            'approved_by' => 'University Registrar',
        ];
    }

    public function repeatingRowKey(): ?string
    {
        return 'feeder_schools';
    }

    /** @return list<string> */
    public function repeatingRegions(): array
    {
        return ['feeder_schools'];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function resolveData(array $filters): array
    {
        return $this->dataQuery->handle($filters);
    }
}
