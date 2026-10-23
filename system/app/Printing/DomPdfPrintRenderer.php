<?php

namespace App\Printing;

use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
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

        if (str_contains($html, 'data-nexiam-page-footer=')) {
            $pdf->render();
            $markers = $this->paginationMarkers($pdf->getDomPDF());
            if ($markers !== []) {
                $pdf->getDomPDF()->getCanvas()->page_script(
                    static function (int $pageNumber, int $pageCount, $canvas, FontMetrics $fontMetrics) use ($markers): void {
                        foreach ($markers as $marker) {
                            $text = $marker['field'] === 'page_i'
                                ? (string) $pageNumber
                                : (string) $pageCount;
                            $font = $fontMetrics->getFont($marker['font_family'], $marker['font_weight']);
                            if ($font === null) {
                                continue;
                            }

                            $placeholderWidth = $fontMetrics->getTextWidth('000', $font, $marker['font_size']);
                            $textWidth = $fontMetrics->getTextWidth($text, $font, $marker['font_size']);
                            $x = $marker['x'] + max(0.0, $placeholderWidth - $textWidth);
                            $canvas->text($x, $marker['y'], $text, $font, $marker['font_size'], $marker['color']);
                        }
                    },
                );
            }
        }

        $output = (string) $pdf->output();

        if ($output === '') {
            throw new RuntimeException('The PDF renderer produced no output.');
        }

        return $output;
    }

    /**
     * @return list<array{field: string, x: float, y: float, font_family: string, font_weight: string, font_size: float, color: array<mixed>}>
     */
    private function paginationMarkers(Dompdf $dompdf): array
    {
        $tree = $dompdf->getTree();
        if ($tree === null) {
            return [];
        }

        $markers = [];
        foreach ($tree->get_frames() as $frame) {
            $node = $frame->get_node();
            if (! ($node instanceof \DOMText) || ! ($node->parentNode instanceof \DOMElement)) {
                continue;
            }

            $field = $node->parentNode->getAttribute('data-nexiam-page-footer');
            if (! in_array($field, ['page_i', 'page_all'], true)) {
                continue;
            }

            $position = $frame->get_position();
            $style = $frame->get_style();
            $fontFamily = (string) $style->font_family;
            $fontWeight = (string) $style->font_weight;
            $fontSize = (float) $style->font_size;
            $color = $style->color;
            if (! is_array($position) || ! isset($position[0], $position[1]) || ! is_array($color)) {
                continue;
            }

            $markers[] = [
                'field' => $field,
                'x' => (float) $position[0],
                'y' => (float) $position[1],
                'font_family' => $fontFamily,
                'font_weight' => $fontWeight,
                'font_size' => $fontSize,
                'color' => $color,
            ];
        }

        return $markers;
    }
}
