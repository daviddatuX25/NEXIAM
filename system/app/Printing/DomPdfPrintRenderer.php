<?php

namespace App\Printing;

use Barryvdh\DomPDF\Facade\Pdf;
use RuntimeException;

/**
 * DomPDF-backed renderer (legacy-proven output; no extra services). Swap
 * behind PrintRenderer for headless Chromium if quality/scale demands it.
 */
final class DomPdfPrintRenderer implements PrintRenderer
{
    public function render(string $html, array $options = []): string
    {
        $pdf = Pdf::loadHTML($html);

        $format = $options['format'] ?? 'a4';
        $orientation = $options['orientation'] ?? 'portrait';

        if ($format !== 'a4') {
            $widthPt = round(((float) ($options['width_mm'] ?? 210) / 25.4) * 72, 2);
            $heightPt = round(((float) ($options['height_mm'] ?? 297) / 25.4) * 72, 2);
            $pdf->setPaper([0, 0, $widthPt, $heightPt], $orientation);
        } else {
            $pdf->setPaper($format, $orientation);
        }

        $pdf->setOptions([
            'margin_top' => 0,
            'margin_right' => 0,
            'margin_bottom' => 0,
            'margin_left' => 0,
            'isHtml5ParserEnabled' => true,
        ]);

        $output = (string) $pdf->output();

        if ($output === '') {
            throw new RuntimeException('The PDF renderer produced no output.');
        }

        return $output;
    }
}
