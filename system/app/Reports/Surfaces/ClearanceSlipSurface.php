<?php

namespace App\Reports\Surfaces;

use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\DocumentTemplate\Enums\LayoutParadigm;
use App\ResultSheet\Support\TemplateShortValue;

final class ClearanceSlipSurface implements DocumentSurfaceInterface
{
    public function surfaceKey(): string
    {
        return 'clearance_slip';
    }

    public function title(): string
    {
        return 'Official Enrollment Clearance Slip';
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
                'label' => 'Slip header',
                'description' => 'Institution identity and official clearance title.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['title' => $this->title(), 'subtitle' => '{{reference_code}}'],
                'inspector_fields' => ['title', 'subtitle', 'align'],
            ],
            'field' => [
                'label' => 'Applicant field',
                'description' => 'A labeled applicant, receipt, or clearance detail.',
                'default_w' => 186,
                'default_h' => 10,
                'default_props' => ['label' => 'Applicant Name', 'binding' => 'applicant_name'],
                'inspector_fields' => ['label', 'binding'],
            ],
            'text' => [
                'label' => 'Text',
                'description' => 'Static or placeholder-bound slip text.',
                'default_w' => 186,
                'default_h' => 10,
                'default_props' => ['text' => ''],
                'inspector_fields' => ['text', 'align'],
            ],
            'dynamic_qr' => [
                'label' => 'Verification QR',
                'description' => 'Clearance receipt verification QR code.',
                'default_w' => 25,
                'default_h' => 25,
                'default_props' => ['binding' => 'verification_qr', 'alt' => 'Clearance verification QR', 'error_correction' => 'M'],
                'inspector_fields' => ['binding', 'error_correction'],
            ],
            'footer' => [
                'label' => 'Slip footer',
                'description' => 'Footer text with renderer-owned pagination.',
                'default_w' => 186,
                'default_h' => 8,
                'default_props' => ['text' => '{{page_i}} / {{page_all}}', 'align' => 'center'],
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
            'layout_paradigm' => LayoutParadigm::Flow->value,
            'page' => [
                'paper_size' => $paperSize,
                'orientation' => $orientation,
                ...$dimensions,
                'margin_mm' => 12,
            ],
            'components' => [
                ['id' => 'header-1', 'type' => 'header', 'x' => 0, 'y' => 0, 'w' => $fullWidth, 'h' => 24, 'props' => ['title' => $this->title(), 'subtitle' => '{{academic_cycle}}']],
                ['id' => 'applicant-1', 'type' => 'field', 'x' => 0, 'y' => 28, 'w' => $fullWidth, 'h' => 10, 'props' => ['label' => 'Applicant Name', 'binding' => 'applicant_name']],
                ['id' => 'reference-1', 'type' => 'field', 'x' => 0, 'y' => 40, 'w' => $fullWidth, 'h' => 10, 'props' => ['label' => 'Reference Code', 'binding' => 'reference_code']],
                ['id' => 'program-1', 'type' => 'field', 'x' => 0, 'y' => 52, 'w' => $fullWidth, 'h' => 10, 'props' => ['label' => 'Program Code', 'binding' => 'program_code']],
                ['id' => 'status-1', 'type' => 'field', 'x' => 0, 'y' => 64, 'w' => $fullWidth, 'h' => 10, 'props' => ['label' => 'Clearance Status', 'binding' => 'clearance_status']],
                ['id' => 'receipt-1', 'type' => 'field', 'x' => 0, 'y' => 76, 'w' => $fullWidth, 'h' => 10, 'props' => ['label' => 'Receipt Number', 'binding' => 'receipt_number']],
                ['id' => 'confirmed-1', 'type' => 'field', 'x' => 0, 'y' => 88, 'w' => $fullWidth, 'h' => 10, 'props' => ['label' => 'Confirmed At', 'binding' => 'confirmed_at']],
                ['id' => 'verified-1', 'type' => 'field', 'x' => 0, 'y' => 100, 'w' => $fullWidth, 'h' => 10, 'props' => ['label' => 'Verified By', 'binding' => 'verified_by']],
                ['id' => 'footer-1', 'type' => 'footer', 'x' => 0, 'y' => 190, 'w' => $fullWidth, 'h' => 8, 'props' => ['text' => 'Issued {{report_date}} · {{page_i}} / {{page_all}}', 'align' => 'center']],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function placeholderCatalog(): array
    {
        $required = [
            'institution_name', 'academic_cycle', 'report_date', 'reference_code', 'examinee_number', 'sequence_number', 'applicant_name',
            'program_code', 'clearance_status', 'receipt_number', 'confirmed_at', 'verified_by',
            'verification_qr', 'page_i', 'page_all', 'pg_i', 'pg_all',
        ];
        $optional = [
            'last_school_enrolled', 'last_school_short', 'school_short', 'strand_or_prev_course', 'strand_or_prev_course_short', 'history_short', 'strand',
        ];

        return ['required' => $required, 'recommended' => [], 'all' => [...$required, ...$optional]];
    }

    /** @return array<string, mixed> */
    public function sampleContext(): array
    {
        return [
            'institution_name' => 'NEXIAM Institute of Technology',
            'academic_cycle' => 'Academic Year 2026–2027 · First Semester',
            'report_date' => 'October 12, 2026',
            'reference_code' => 'AY26-00001',
            'applicant_reference' => 'AY26-00001',
            'examinee_number' => '00001',
            'sequence_number' => '1',
            ...TemplateShortValue::fields('Ilocos Sur College', 'Humanities and Social Sciences'),
            'strand' => 'Humanities and Social Sciences',
            'strand_or_prev_course' => 'Humanities and Social Sciences',
            'applicant_name' => 'Sample Applicant',
            'program_code' => 'BSIT',
            'clearance_status' => 'Confirmed',
            'receipt_number' => 'CLR-SAMPLE-001',
            'confirmed_at' => 'October 10, 2026 at 2:30 PM',
            'verified_by' => 'Registrar Clerk',
            'verification_qr' => '',
            'page_i' => '1',
            'page_all' => '1',
            'pg_i' => '1',
            'pg_all' => '1',
        ];
    }

    public function repeatingRowKey(): ?string
    {
        return null;
    }

    /** @return list<string> */
    public function repeatingRegions(): array
    {
        return [];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function resolveData(array $filters): array
    {
        return [];
    }
}
