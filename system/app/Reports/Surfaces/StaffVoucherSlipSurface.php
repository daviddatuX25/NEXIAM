<?php

namespace App\Reports\Surfaces;

use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\DocumentTemplate\Enums\LayoutParadigm;
use App\DocumentTemplate\Services\DynamicAssetResolutionService;

final class StaffVoucherSlipSurface implements DocumentSurfaceInterface
{
    public function surfaceKey(): string
    {
        return 'staff_voucher_slip';
    }

    public function title(): string
    {
        return 'Staff Access Voucher Slip';
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
                'label' => 'Voucher header',
                'description' => 'Institution identity and credential slip title.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['title' => $this->title(), 'subtitle' => '{{public_reference}}'],
                'inspector_fields' => ['title', 'subtitle', 'align'],
            ],
            'field' => [
                'label' => 'Staff field',
                'description' => 'A labeled staff recipient or credential detail.',
                'default_w' => 186,
                'default_h' => 10,
                'default_props' => ['label' => 'Staff Name', 'binding' => 'staff_name'],
                'inspector_fields' => ['label', 'binding'],
            ],
            'text' => [
                'label' => 'Text',
                'description' => 'Static or placeholder-bound security and instructions text.',
                'default_w' => 186,
                'default_h' => 10,
                'default_props' => ['text' => ''],
                'inspector_fields' => ['text', 'align'],
            ],
            'dynamic_qr' => [
                'label' => 'Activation QR',
                'description' => 'Cryptographic onboarding or reset QR code.',
                'default_w' => 38,
                'default_h' => 38,
                'default_props' => ['binding' => 'voucher_qr', 'alt' => 'Activation QR', 'error_correction' => 'M'],
                'inspector_fields' => ['binding', 'error_correction'],
            ],
            'footer' => [
                'label' => 'Slip footer',
                'description' => 'Security compliance and pagination footer.',
                'default_w' => 186,
                'default_h' => 8,
                'default_props' => ['text' => 'Confidential · Single-Use Credential', 'align' => 'center'],
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
        $copyHeight = ($dimensions['height_mm'] - 24 - 12) / 2;
        $qrSize = min(42, $copyHeight * 0.32);
        $qrX = ($fullWidth - $qrSize) / 2;
        $qrTop = $copyHeight * 0.44;

        $recipientSlip = '<section style="height:'.$copyHeight.'mm;position:relative;padding:3mm 0;font-family:Arial,Helvetica,sans-serif;color:#182a3a;box-sizing:border-box;">'
            .'<div style="text-align:center;padding:0 0 3mm;border-bottom:2px solid #173f63;">'
            .'<div style="font-size:15pt;font-weight:700;color:#173f63;">{{institution_name}}</div>'
            .'<div style="font-size:9pt;color:#607386;margin-top:1mm;">HUMAN RESOURCES &amp; PERSONNEL ADMINISTRATION</div>'
            .'<div style="font-size:16pt;font-weight:700;letter-spacing:.3px;margin-top:2mm;color:#111827;">Official Staff Access Voucher</div>'
            .'<div style="display:inline-block;margin-top:1.5mm;padding:2px 8px;border-radius:4px;background:#fef3c7;color:#92400e;font-size:8.5pt;font-weight:700;">{{validity_badge}}</div>'
            .'</div>'
            .'<table style="width:100%;border-collapse:collapse;text-align:left;margin:3.5mm 0 0;"><tbody>'
            .'<tr>'
            .'<td style="width:50%;padding:2.5mm;border:1px solid #dbe4ec;background:#f7f9fb;"><div style="font-size:7.5pt;color:#718296;font-weight:600;">STAFF RECIPIENT</div><div style="font-size:10.5pt;font-weight:700;margin-top:1mm;">{{staff_name}}</div><div style="font-size:8.5pt;color:#607386;font-family:monospace;margin-top:0.5mm;">{{staff_email}}</div></td>'
            .'<td style="width:50%;padding:2.5mm;border:1px solid #dbe4ec;background:#f7f9fb;"><div style="font-size:7.5pt;color:#718296;font-weight:600;">OFFICE &amp; ROLE</div><div style="font-size:10pt;font-weight:700;color:#173f63;margin-top:1mm;">{{office_label}}</div><div style="font-size:8.5pt;color:#607386;margin-top:0.5mm;">{{staff_role}}</div></td>'
            .'</tr>'
            .'<tr>'
            .'<td style="width:50%;padding:2.5mm;border:1px solid #dbe4ec;background:#f7f9fb;"><div style="font-size:7.5pt;color:#718296;font-weight:600;">VOUCHER REFERENCE</div><div style="font-size:11pt;font-weight:700;color:#173f63;font-family:monospace;margin-top:1mm;">{{public_reference}}</div></td>'
            .'<td style="width:50%;padding:2.5mm;border:1px solid #dbe4ec;background:#f7f9fb;"><div style="font-size:7.5pt;color:#718296;font-weight:600;">FALLBACK PIN CODE</div><div style="font-size:13pt;font-weight:700;color:#0f766e;font-family:monospace;letter-spacing:1px;margin-top:1mm;">{{fallback_code}}</div></td>'
            .'</tr>'
            .'</tbody></table>'
            .'<div style="position:absolute;left:0;right:0;bottom:3mm;text-align:center;padding-top:2mm;border-top:1px solid #edf1f5;font-size:8pt;line-height:1.4;color:#52677a;">'
            .'Scan this QR code or navigate to <strong>{{activation_url}}</strong> to complete your password and two-factor authentication setup. '
            .'This voucher burns immediately upon use.'
            .'</div>'
            .'</section>';

        $officeCopy = '<section style="height:'.$copyHeight.'mm;position:relative;padding:3mm 0;font-family:Arial,Helvetica,sans-serif;color:#182a3a;box-sizing:border-box;">'
            .'<div style="text-align:center;padding:0 0 2mm;border-bottom:1px dashed #94a3b8;">'
            .'<span style="font-size:8pt;font-weight:700;letter-spacing:1.5px;color:#64748b;text-transform:uppercase;">✂ Tear Line — Human Resources Office Acknowledgment Copy</span>'
            .'</div>'
            .'<div style="margin-top:3mm;text-align:center;">'
            .'<div style="font-size:12pt;font-weight:700;color:#173f63;">{{institution_name}} — Personnel Hand-off Receipt</div>'
            .'<div style="font-size:8.5pt;color:#64748b;margin-top:1mm;">Reference: <strong style="font-family:monospace;">{{public_reference}}</strong> · Purpose: <strong>{{purpose_label}}</strong> · Validity: <strong>{{validity_badge}}</strong></div>'
            .'</div>'
            .'<table style="width:100%;border-collapse:collapse;margin:4mm 0 0;font-size:8.5pt;"><tbody>'
            .'<tr>'
            .'<td style="width:50%;padding:3mm;border:1px solid #dbe4ec;"><strong>Staff Member:</strong> {{staff_name}}<br><span style="color:#64748b;">Office: {{office_label}}</span></td>'
            .'<td style="width:50%;padding:3mm;border:1px solid #dbe4ec;"><strong>Issuing Officer:</strong> {{issued_by_name}}<br><span style="color:#64748b;">Issued Date: {{issued_at}}</span></td>'
            .'</tr>'
            .'</tbody></table>'
            .'<div style="margin-top:6mm;display:flex;justify-content:space-between;gap:20px;">'
            .'<div style="width:48%;border-top:1px solid #334155;padding-top:1.5mm;font-size:8pt;text-align:center;color:#475569;">Recipient Signature &amp; Date Received</div>'
            .'<div style="width:48%;border-top:1px solid #334155;padding-top:1.5mm;font-size:8pt;text-align:center;color:#475569;">HR Issuing Officer Signature</div>'
            .'</div>'
            .'<div style="position:absolute;left:0;right:0;bottom:2mm;text-align:center;font-size:7.5pt;color:#94a3b8;">'
            .'NEXIAM Personnel Security · Keep this signed acknowledgment on file in the HR office.'
            .'</div>'
            .'</section>';

        $separator = '<div style="height:8mm;line-height:8mm;text-align:center;color:#94a3b8;font-size:8pt;border-top:1px dashed #cbd5e1;border-bottom:1px dashed #cbd5e1;margin:2mm 0;background:#f8fafc;">'
            .'CONFIDENTIAL CREDENTIAL DOCUMENT · FOR AUTHORIZED PERSONNEL USE ONLY'
            .'</div>';

        return [
            'schema_version' => 1,
            'layout_paradigm' => LayoutParadigm::Absolute->value,
            'page' => [
                'paper_size' => $paperSize,
                'orientation' => $orientation,
                'width_mm' => $dimensions['width_mm'],
                'height_mm' => $dimensions['height_mm'],
                'margin_mm' => 12,
            ],
            'component_templates' => [
                'text' => [
                    'html' => '<div style="font-family:Arial,Helvetica,sans-serif;color:#182a3a;">'
                        .$recipientSlip
                        .$separator
                        .$officeCopy
                        .'</div>',
                ],
            ],
            'components' => [
                [
                    'id' => 'staff-voucher-two-copies',
                    'type' => 'text',
                    'x' => 0,
                    'y' => 0,
                    'w' => $fullWidth,
                    'h' => $dimensions['height_mm'] - 24,
                    'props' => ['text' => '', 'spacing' => ['padding_mm' => 0]],
                ],
                [
                    'id' => 'recipient-voucher-qr',
                    'type' => 'dynamic_qr',
                    'x' => $qrX,
                    'y' => $qrTop,
                    'w' => $qrSize,
                    'h' => $qrSize,
                    'props' => [
                        'binding' => 'voucher_qr',
                        'alt' => 'Activation QR code',
                        'error_correction' => 'M',
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function placeholderCatalog(): array
    {
        $placeholders = [
            'institution_name', 'office_label', 'staff_name', 'staff_email', 'staff_role',
            'public_reference', 'fallback_code', 'validity_badge', 'purpose_label',
            'activation_url', 'issued_at', 'issued_by_name', 'voucher_qr',
            'page_i', 'page_all',
        ];

        return ['required' => $placeholders, 'recommended' => [], 'all' => $placeholders];
    }

    /** @return array<string, mixed> */
    public function sampleContext(): array
    {
        $sampleQr = 'data:image/png;base64,'.base64_encode(
            (new DynamicAssetResolutionService)->renderVerificationQr('https://nexiam.test/staff/activate#v=SAMPLE-VCH1&t=sampletoken', 400, 4),
        );

        return [
            'institution_name' => 'NEXIAM Institute of Technology',
            'office_label' => 'Human Resources & Personnel',
            'staff_name' => 'Dr. Maria Santos',
            'staff_email' => 'm.santos@nexiam.test',
            'staff_role' => 'Guidance Counselor',
            'public_reference' => 'SAMPLE-VCH1',
            'fallback_code' => 'H7K2-9P4W',
            'validity_badge' => 'No calendar expiry · Single-use · Revocable',
            'purpose_label' => 'Account Activation',
            'activation_url' => 'https://nexiam.test/staff/activate',
            'issued_at' => now()->format('F j, Y'),
            'issued_by_name' => 'Campus HR Administrator',
            'voucher_qr' => $sampleQr,
            'page_i' => '1',
            'page_all' => '1',
        ];
    }

    /** @return array<string, mixed> */
    public function groupedGridBlueprint(string $paperSize = 'a4', string $orientation = 'portrait', int $cardCount = 4, float $marginMm = 12): array
    {
        $dimensions = match ($paperSize) {
            'legal' => ['width_mm' => 216, 'height_mm' => 356],
            'letter' => ['width_mm' => 216, 'height_mm' => 279],
            default => ['width_mm' => 210, 'height_mm' => 297],
        };
        if ($orientation === 'landscape') {
            $dimensions = ['width_mm' => $dimensions['height_mm'], 'height_mm' => $dimensions['width_mm']];
        }
        $width = $dimensions['width_mm'] - ($marginMm * 2);
        $height = $dimensions['height_mm'] - ($marginMm * 2) - 10;
        $cardCount = max(1, min(4, $cardCount));
        $cards = '';
        for ($index = 0; $index < $cardCount; $index++) {
            if ($index % 2 === 0) {
                $cards .= '<tr>';
            }
            $cards .= '<td><div class="voucher-card">'
                .'<div class="voucher-heading"><strong>{{card_'.$index.'_institution}}</strong><span>{{card_'.$index.'_purpose}}</span></div>'
                .'<div class="card-main"><div class="identity"><strong>{{card_'.$index.'_name}}</strong><span>{{card_'.$index.'_email}}</span><span>{{card_'.$index.'_office}}</span><span>{{card_'.$index.'_role}}</span></div>'
                .'<span class="voucher-qr" data-qr="{{card_'.$index.'_qr}}"></span></div>'
                .'<div class="credentials"><span>Reference <strong>{{card_'.$index.'_reference}}</strong></span><span>Fallback <strong>{{card_'.$index.'_fallback}}</strong></span></div>'
                .'<div class="voucher-footer">{{card_'.$index.'_expiry}} · Single-use and revocable. Scan to start setup. Keep this voucher private.</div>'
                .'</div></td>';
            if ($index % 2 === 1 || $index === $cardCount - 1) {
                if ($cardCount % 2 === 1 && $index === $cardCount - 1) {
                    $cards .= '<td></td>';
                }
                $cards .= '</tr>';
            }
        }
        $blueprint = $this->defaultBlueprint($paperSize, $orientation);
        $blueprint['layout_paradigm'] = LayoutParadigm::Flow->value;
        $blueprint['component_templates']['text']['html'] = '<table class="voucher-grid"><tbody>'.$cards.'</tbody></table>';
        $blueprint['components'] = [[
            'id' => 'staff-voucher-grouped-grid',
            'type' => 'text',
            'x' => 0,
            'y' => 0,
            'w' => $width,
            'h' => $height,
            'props' => ['text' => '', 'spacing' => ['padding_mm' => 0]],
        ]];

        return $blueprint;
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

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function resolveData(array $filters): array
    {
        return [];
    }
}
