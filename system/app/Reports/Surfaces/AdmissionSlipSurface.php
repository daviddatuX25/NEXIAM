<?php

namespace App\Reports\Surfaces;

use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\DocumentTemplate\Enums\LayoutParadigm;
use App\DocumentTemplate\Services\DynamicAssetResolutionService;
use App\ResultSheet\Support\TemplateShortValue;

final class AdmissionSlipSurface implements DocumentSurfaceInterface
{
    public function surfaceKey(): string
    {
        return 'admission_slip';
    }

    public function title(): string
    {
        return 'Applicant QR Pass';
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
                'description' => 'Institution identity and admission slip title.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['title' => $this->title(), 'subtitle' => '{{reference_code}}'],
                'inspector_fields' => ['title', 'subtitle', 'align'],
            ],
            'field' => [
                'label' => 'Applicant field',
                'description' => 'A labeled applicant or examination detail.',
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
                'description' => 'Applicant or permit verification QR code.',
                'default_w' => 25,
                'default_h' => 25,
                'default_props' => ['binding' => 'verification_qr', 'alt' => 'Verification QR', 'error_correction' => 'M'],
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
        $copyHeight = ($dimensions['height_mm'] - 24 - 8) / 2;
        $copyScale = min(1, $copyHeight / 132.5);
        $qrSize = min(38, $copyHeight * 0.28);
        $qrX = ($fullWidth - $qrSize) / 2;
        $qrTop = $copyHeight * 0.53;
        $copy = static function (string $copyLabel) use ($copyHeight, $copyScale): string {
            return '<section style="height:'.$copyHeight.'mm;position:relative;padding:'.(2 * $copyScale).'mm 0;font-family:Arial,Helvetica,sans-serif;color:#182a3a;">'
                .'<div style="text-align:center;padding:0 0 '.(3 * $copyScale).'mm;border-bottom:1px solid #dbe4ec;">'
                .'<div style="font-size:'.(15 * $copyScale).'pt;font-weight:700;color:#173f63;">{{institution_name}}</div>'
                .'<div style="font-size:'.(9 * $copyScale).'pt;color:#607386;margin-top:'.(1 * $copyScale).'mm;">{{academic_cycle}}</div>'
                .'<div style="font-size:'.(18 * $copyScale).'pt;font-weight:700;letter-spacing:.3px;margin-top:'.(2 * $copyScale).'mm;">Applicant QR Pass</div>'
                .'<div style="font-size:'.(8 * $copyScale).'pt;font-weight:700;letter-spacing:1.2px;color:#607386;margin-top:'.(1 * $copyScale).'mm;">[ '.$copyLabel.' ]</div></div>'
                .'<table style="width:100%;border-collapse:collapse;text-align:left;margin:'.(5 * $copyScale).'mm 0 0;"><tbody>'
                .'<tr><td style="width:50%;padding:'.(2 * $copyScale).'mm;border:1px solid #dbe4ec;background:#f7f9fb;"><div style="font-size:'.(7 * $copyScale).'pt;color:#718296;">APPLICANT NAME</div><div style="font-size:'.(10 * $copyScale).'pt;font-weight:700;margin-top:.5mm;">{{applicant_name}}</div></td>'
                .'<td style="width:50%;padding:'.(2 * $copyScale).'mm;border:1px solid #dbe4ec;background:#f7f9fb;"><div style="font-size:'.(7 * $copyScale).'pt;color:#718296;">REFERENCE CODE</div><div style="font-size:'.(10 * $copyScale).'pt;font-weight:700;color:#173f63;margin-top:.5mm;">{{reference_code}}</div></td></tr>'
                .'<tr><td style="width:50%;padding:'.(2 * $copyScale).'mm;border:1px solid #dbe4ec;background:#f7f9fb;"><div style="font-size:'.(7 * $copyScale).'pt;color:#718296;">TARGET PROGRAM</div><div style="font-size:'.(9 * $copyScale).'pt;font-weight:600;margin-top:.5mm;">{{program_code}}</div></td>'
                .'<td style="width:50%;padding:'.(2 * $copyScale).'mm;border:1px solid #dbe4ec;background:#f7f9fb;"><div style="font-size:'.(7 * $copyScale).'pt;color:#718296;">STATUS</div><div style="font-size:'.(9 * $copyScale).'pt;font-weight:600;margin-top:.5mm;">{{admission_status}}</div></td></tr></tbody></table>'
                .'<div style="position:absolute;left:0;right:0;bottom:'.(6 * $copyScale).'mm;text-align:center;padding-top:'.(2 * $copyScale).'mm;border-top:1px solid #edf1f5;font-size:'.(8 * $copyScale).'pt;line-height:1.35;color:#52677a;">Show this QR when requested by university staff at the admissions office, testing center, or release desk.</div>'
                .'</section>';
        };

        return [
            'schema_version' => 1,
            'layout_paradigm' => LayoutParadigm::Absolute->value,
            'page' => [
                'paper_size' => $paperSize,
                'orientation' => $orientation,
                ...$dimensions,
                'margin_mm' => 12,
            ],
            'component_templates' => [
                'text' => [
                    'html' => '<div data-applicant-qr-pass-version="3" style="font-family:Arial,Helvetica,sans-serif;color:#182a3a;">'
                        .$copy('APPLICANT COPY')
                        .'<div style="height:8mm;text-align:center;font-size:9pt;font-weight:700;letter-spacing:.6px;color:#52677a;">---------------- ✂ CUT HERE ----------------</div>'
                        .$copy('OFFICE / BACKUP COPY')
                        .'</div>',
                ],
            ],
            'components' => [
                ['id' => 'applicant-qr-pass-two-copies', 'type' => 'text', 'x' => 0, 'y' => 0, 'w' => $fullWidth, 'h' => $dimensions['height_mm'] - 24, 'props' => ['text' => '', 'spacing' => ['padding_mm' => 0]]],
                ['id' => 'applicant-copy-qr', 'type' => 'dynamic_qr', 'x' => $qrX, 'y' => $qrTop, 'w' => $qrSize, 'h' => $qrSize, 'props' => ['binding' => 'applicant_locator_qr', 'alt' => 'Applicant copy QR code', 'error_correction' => 'M']],
                ['id' => 'office-copy-qr', 'type' => 'dynamic_qr', 'x' => $qrX, 'y' => $copyHeight + 8 + $qrTop, 'w' => $qrSize, 'h' => $qrSize, 'props' => ['binding' => 'applicant_locator_qr', 'alt' => 'Office backup copy QR code', 'error_correction' => 'M']],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function placeholderCatalog(): array
    {
        $required = [
            'institution_name', 'academic_cycle', 'reference_code', 'examinee_number', 'sequence_number', 'applicant_name',
            'program_code', 'admission_status', 'applicant_locator_qr', 'page_i', 'page_all', 'pg_i', 'pg_all',
        ];
        $optional = [
            'last_school_enrolled', 'last_school_short', 'school_short', 'strand_or_prev_course', 'strand_or_prev_course_short', 'history_short', 'strand',
        ];

        return ['required' => $required, 'recommended' => [], 'all' => [...$required, ...$optional]];
    }

    /** @return array<string, mixed> */
    public function sampleContext(): array
    {
        $sampleQr = 'data:image/png;base64,'.base64_encode(
            (new DynamicAssetResolutionService)->renderVerificationQr('https://nexiam.test/verify/SAMPLE-APP-001', 400, 4),
        );

        return [
            'institution_name' => 'NEXIAM Institute of Technology',
            'academic_cycle' => 'Academic Year 2026–2027 · First Semester',
            'academic_cycle_name' => 'Academic Year 2026–2027',
            'applicant_name' => 'Sample Applicant',
            'first_name' => 'Sample',
            'family_name' => 'Applicant',
            'reference_code' => 'AY26-00001',
            ...TemplateShortValue::fields('Ilocos Sur College', 'Humanities and Social Sciences'),
            'strand' => 'Humanities and Social Sciences',
            'strand_or_prev_course' => 'Humanities and Social Sciences',
            'applicant_reference' => 'AY26-00001',
            'examinee_number' => '00001',
            'sequence_number' => '1',
            'program_code' => 'BSIT',
            'admission_status' => 'Admitted',
            'applicant_locator_qr' => $sampleQr,
            'verification_qr' => $sampleQr,
            'qr_code_svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="240"></svg>',
            'qr' => 'SAMPLE-APP-001',
            'verification_code' => 'SAMPLE-APP-001',
            'report_date' => 'October 14, 2026',
            'generated_at' => 'October 14, 2026',
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
