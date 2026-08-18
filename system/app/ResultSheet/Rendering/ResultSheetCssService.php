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

    public function wrapForPdf(string $rawHtml, string $paperSize, string $orientation): string
    {
        $dimensions = config('result-sheet.paper_sizes.'.$paperSize, ['width_mm' => 210, 'height_mm' => 297]);

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .$this->css($dimensions['width_mm'], $dimensions['height_mm'], $orientation)
            .'</style></head><body>'.$rawHtml.'</body></html>';
    }

    public function wrapDualForPdf(string $html1, string $html2, string $paperSize, string $orientation): string
    {
        $dimensions = config('result-sheet.paper_sizes.'.$paperSize, ['width_mm' => 210, 'height_mm' => 297]);

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .$this->css($dimensions['width_mm'], $dimensions['height_mm'], $orientation)
            .'</style></head><body>'
            .'<div class="print-template--dual"><div class="print-template--half">'.$html1.'</div>'
            .'<div class="print-template--half">'.$html2.'</div></div></body></html>';
    }

    private function css(int $widthMm, int $heightMm, string $orientation): string
    {
        $w = $orientation === 'landscape' ? $heightMm : $widthMm;
        $h = $orientation === 'landscape' ? $widthMm : $heightMm;

        return <<<CSS
        @page { size: {$w}mm {$h}mm; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #111; margin: 0; }
        .print-template { width: 100%; }
        .print-template table { width: 100%; border-collapse: collapse; }
        .print-template th, .print-template td { border: 1px solid #333; padding: 4px 6px; text-align: left; }
        .print-template .text-center { text-align: center; }
        .print-template h1, .print-template h2, .print-template h3 { margin: 4px 0; }
        .print-template--dual { display: flex; }
        .print-template--half { width: 50%; padding: 0 4mm; border-right: 1px dashed #999; }
        .print-template--half:last-child { border-right: none; }
        .watermark { position: fixed; opacity: 0.12; font-size: 48pt; transform: rotate(-30deg); }
        CSS;
    }
}
