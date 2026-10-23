<?php

namespace Database\Seeders;

use App\DocumentTemplate\DocumentSurfaceRegistry;
use App\DocumentTemplate\Rendering\ComponentRendererRegistry;
use App\DocumentTemplate\Rendering\DocumentBlueprintCompiler;
use App\Models\Reports\ReportTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RepairApplicantQrPassDefaultSeeder extends Seeder
{
    private const LEGACY_DEFAULT_NAMES = [
        'Standard Applicant Examination Permit & Admission Slip (System Default)',
        'Standard Applicant QR Pass (System Default)',
    ];

    private const LEGACY_COMPILED_HTML_MD5 = [
        'Standard Applicant Examination Permit & Admission Slip (System Default)' => '2dd20217efea7a4cfa4a158e87fd833d',
        'Standard Applicant QR Pass (System Default)' => '6582eba11ee3e8303827f7d64083776a',
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $template = ReportTemplate::query()
                ->where('surface_key', 'admission_slip')
                ->whereIn('name', self::LEGACY_DEFAULT_NAMES)
                ->whereNull('institution_id')
                ->where('is_active', true)
                ->where('source_kind', ReportTemplate::SOURCE_BUILDER)
                ->whereNull('created_by_user_id')
                ->where('paper_size', 'a4')
                ->where('orientation', 'portrait')
                ->lockForUpdate()
                ->first();

            if ($template === null
                || $template->builder_json !== $this->legacyBlueprint()
                || ! hash_equals(
                    self::LEGACY_COMPILED_HTML_MD5[$template->name] ?? '',
                    md5((string) $template->compiled_html),
                )) {
                throw new RuntimeException('The active applicant QR pass is not the untouched generated default; no template was changed.');
            }

            $surface = DocumentSurfaceRegistry::resolve('admission_slip');
            if ($surface === null) {
                throw new RuntimeException('The admission slip surface could not be resolved.');
            }

            $blueprint = $surface->defaultBlueprint((string) $template->paper_size, (string) $template->orientation);
            $compiledHtml = (new DocumentBlueprintCompiler(ComponentRendererRegistry::withDefaults()))->compile($blueprint);
            $template->update([
                'builder_json' => $blueprint,
                'compiled_html' => $compiledHtml,
                'content' => $compiledHtml,
                'compiler_version' => 'document-blueprint-1',
            ]);
        });
    }

    /** @return array<string, mixed> */
    private function legacyBlueprint(): array
    {
        $copy = static function (string $copyLabel): string {
            return '<section style="height:127mm;position:relative;padding:2mm 0;font-family:Arial,Helvetica,sans-serif;color:#182a3a;">'
                .'<div style="text-align:center;padding:0 0 4mm;border-bottom:1px solid #dbe4ec;">'
                .'<div style="font-size:15pt;font-weight:700;color:#173f63;">{{institution_name}}</div>'
                .'<div style="font-size:9pt;color:#607386;margin-top:1mm;">{{academic_cycle}}</div>'
                .'<div style="font-size:18pt;font-weight:700;letter-spacing:.3px;margin-top:3mm;">Applicant QR Pass</div>'
                .'<div style="font-size:8pt;font-weight:700;letter-spacing:1.2px;color:#607386;margin-top:1.5mm;">[ '.$copyLabel.' ]</div></div>'
                .'<table style="width:100%;border-collapse:collapse;text-align:left;margin:7mm 0 0;"><tbody>'
                .'<tr><td style="width:50%;padding:3mm;border:1px solid #dbe4ec;background:#f7f9fb;"><div style="font-size:7pt;color:#718296;">APPLICANT NAME</div><div style="font-size:10pt;font-weight:700;margin-top:1mm;">{{applicant_name}}</div></td>'
                .'<td style="width:50%;padding:3mm;border:1px solid #dbe4ec;background:#f7f9fb;"><div style="font-size:7pt;color:#718296;">REFERENCE CODE</div><div style="font-size:10pt;font-weight:700;color:#173f63;margin-top:1mm;">{{reference_code}}</div></td></tr>'
                .'<tr><td style="width:50%;padding:3mm;border:1px solid #dbe4ec;background:#f7f9fb;"><div style="font-size:7pt;color:#718296;">TARGET PROGRAM</div><div style="font-size:9pt;font-weight:600;margin-top:1mm;">{{program_code}}</div></td>'
                .'<td style="width:50%;padding:3mm;border:1px solid #dbe4ec;background:#f7f9fb;"><div style="font-size:7pt;color:#718296;">STATUS</div><div style="font-size:9pt;font-weight:600;margin-top:1mm;">{{admission_status}}</div></td></tr></tbody></table>'
                .'<div style="position:absolute;left:0;right:0;top:60mm;text-align:center;"><div style="display:inline-block;width:38mm;height:38mm;border:1px solid #dbe4ec;border-radius:2mm;background:#fff;"></div></div>'
                .'<div style="position:absolute;left:0;right:0;bottom:8mm;text-align:center;padding-top:3mm;border-top:1px solid #edf1f5;font-size:8pt;line-height:1.45;color:#52677a;">Show this QR when requested by university staff at the admissions office, testing center, or consultation desk.</div>'
                .'</section>';
        };

        return [
            'schema_version' => 1,
            'layout_paradigm' => 'absolute',
            'page' => ['paper_size' => 'a4', 'orientation' => 'portrait', 'width_mm' => 210, 'height_mm' => 297, 'margin_mm' => 12],
            'component_templates' => [
                'text' => [
                    'html' => '<div data-applicant-qr-pass-version="2" style="font-family:Arial,Helvetica,sans-serif;color:#182a3a;">'
                        .$copy('APPLICANT COPY')
                        .'<div style="height:8mm;text-align:center;font-size:9pt;font-weight:700;letter-spacing:.6px;color:#52677a;">---------------- ✂ CUT HERE ----------------</div>'
                        .$copy('OFFICE / BACKUP COPY')
                        .'</div>',
                ],
            ],
            'components' => [
                ['id' => 'applicant-qr-pass-two-copies', 'type' => 'text', 'x' => 0, 'y' => 0, 'w' => 186, 'h' => 273, 'props' => ['text' => '']],
                ['id' => 'applicant-copy-qr', 'type' => 'dynamic_qr', 'x' => 74, 'y' => 70, 'w' => 38, 'h' => 38, 'props' => ['binding' => 'applicant_locator_qr', 'alt' => 'Applicant copy QR code', 'error_correction' => 'M']],
                ['id' => 'office-copy-qr', 'type' => 'dynamic_qr', 'x' => 74, 'y' => 204, 'w' => 38, 'h' => 38, 'props' => ['binding' => 'applicant_locator_qr', 'alt' => 'Office backup copy QR code', 'error_correction' => 'M']],
            ],
        ];
    }
}
