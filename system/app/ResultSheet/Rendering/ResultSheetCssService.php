<?php

namespace App\ResultSheet\Rendering;

/**
 * Print CSS wrapper for result sheets (legacy PrintTemplateCssService shape).
 * Paper size maps to fixed mm dimensions; the sheet prints 1:1 — the renderer
 * never scales authored geometry.
 */
final class ResultSheetCssService
{
    public function wrap(string $rawHtml): string
    {
        return '<div class="print-template">'.$rawHtml.'</div>';
    }

    public function wrapForPdf(string $rawHtml, string $paperSize, string $orientation, float $marginMm = 12): string
    {
        $dimensions = config('result-sheet.paper_sizes.'.$paperSize, ['width_mm' => 210, 'height_mm' => 297]);
        $marginMm = max(0, min(30, $marginMm));

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .$this->css($dimensions['width_mm'], $dimensions['height_mm'], $orientation, $marginMm)
            .'</style></head><body>'.$rawHtml.'</body></html>';
    }

    private function css(int $widthMm, int $heightMm, string $orientation, float $marginMm): string
    {
        $w = $orientation === 'landscape' ? $heightMm : $widthMm;
        $h = $orientation === 'landscape' ? $widthMm : $heightMm;

        return <<<CSS
        @page { size: {$w}mm {$h}mm; margin: {$marginMm}mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #111; margin: 0; }
        .print-template { width: 100%; }
        .print-template table { width: 100%; border-collapse: collapse; }
        .print-template th, .print-template td { border: 1px solid #333; padding: 4px 6px; text-align: left; }
        .print-template .text-center { text-align: center; }
        .print-template h1, .print-template h2, .print-template h3 { margin: 4px 0; }
        .watermark { position: fixed; opacity: 0.12; font-size: 48pt; transform: rotate(-30deg); }
        CSS;
    }
}
