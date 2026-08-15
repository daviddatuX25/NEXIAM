<?php

namespace App\Printing;

use App\Assessment\Omr\NativeBundle;
use RuntimeException;

/**
 * Draws any bundle version (or arbitrary draft geometry) into print-ready
 * HTML at physical millimetre coordinates. The ONLY visual source: letters
 * are centred on bubble centres by construction, margins and paper size are
 * config, and geometry is NEVER scaled — a paper mismatch refuses with a
 * typed error.
 */
final class BundleRenderer
{
    /** Pixels-per-inch of the bundle coordinate space. */
    public const PPI = 242;

    /** @var array{width: float, height: float, name: string} */
    private array $paper;

    public function __construct()
    {
        $this->paper = $this->paperFor((string) config('print.paper', 'long'));
    }

    /** @return array{width: float, height: float, name: string} */
    public static function paperFor(string $name): array
    {
        return match ($name) {
            'long' => ['width' => 216.0, 'height' => 330.0, 'name' => 'long'],
            'legal' => ['width' => 216.0, 'height' => 356.0, 'name' => 'legal'],
            default => throw new RuntimeException("Unsupported paper size: {$name}."),
        };
    }

    public function paper(): array
    {
        return $this->paper;
    }

    /**
     * @param  array<string, mixed>  $bundleTemplate  top-level bundle template (pages or single page)
     * @param  array<string, mixed>  $options  {orientation?: string, overlay?: array<string, mixed>|null}
     */
    public function renderHtml(array $bundleTemplate, array $options = []): string
    {
        $overlay = is_array($options['overlay'] ?? null) ? $options['overlay'] : [];
        $pages = $this->pagesOf($bundleTemplate);
        $bubble = $bundleTemplate['bubbleDimensions'] ?? [28, 28];
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .'@page{margin:0;}'
            .'body{margin:0;padding:0;font-family:DejaVu Sans,sans-serif;}'
            .'.sheet{position:relative;overflow:hidden;page-break-after:always;}'
            .'.sheet:last-child{page-break-after:auto;}'
            .'.bubble{position:absolute;border:0.35mm solid #000;border-radius:50%;}'
            .'.label{position:absolute;font-size:9pt;text-align:center;}'
            .'.lbl-left{position:absolute;font-size:8pt;text-align:right;}'
            .'</style></head><body>';

        foreach ($pages as $index => $page) {
            $dims = $page['pageDimensions'];
            [$pageMmW, $pageMmH] = $this->assertPaperMatch($dims, $index + 1);
            $total = count($pages);
            $pageNo = $index + 1;
            // 0.2mm breathing room avoids a rounding-driven 0.003pt overflow
            // that would spill an empty page between sheets.
            $sheetHeight = $pageMmH - 0.2;
            $html .= '<div class="sheet" style="width:'.$pageMmW.'mm;height:'.$sheetHeight.'mm;">';
            $inset = round((80 / self::PPI) * 25.4, 2); // 80px geometry margin in mm
            $html .= '<div style="position:absolute;left:'.$inset.'mm;top:'.$inset.'mm;width:'.round($pageMmW - 2 * $inset, 2).'mm;height:'.round($pageMmH - 2 * $inset, 2).'mm;border:0.7mm solid #000;"></div>';

            $title = trim((string) ($overlay['title'] ?? 'SYNTHETIC NEXIAM OMR ANSWER SHEET'));
            $html .= '<div class="label" style="left:0;top:4mm;width:'.$pageMmW.'mm;font-size:11pt;font-weight:bold;">'.e($title).'</div>';

            $headerTop = 9;
            foreach ((array) ($overlay['header_lines'] ?? []) as $headerLine) {
                $html .= '<div class="label" style="left:0;top:'.$headerTop.'mm;width:'.$pageMmW.'mm;font-weight:bold;">'.e((string) $headerLine).'</div>';
                $headerTop += 5;
            }
            $html .= '<div class="label" style="left:0;top:'.$headerTop.'mm;width:'.$pageMmW.'mm;font-weight:bold;">PAGE '.$pageNo.' OF '.$total.'</div>';
            $headerTop += 5;

            $perPageNote = null;
            foreach ((array) ($overlay['per_page'] ?? []) as $pageNote) {
                if ((int) ($pageNote['page_no'] ?? 0) === $pageNo && trim((string) ($pageNote['note'] ?? '')) !== '') {
                    $perPageNote = trim((string) $pageNote['note']);
                    break;
                }
            }
            if ($perPageNote !== null) {
                $html .= '<div class="label" style="left:0;top:'.$headerTop.'mm;width:'.$pageMmW.'mm;">'.e($perPageNote).'</div>';
                $headerTop += 5;
            }

            $nameBoxLabel = trim((string) ($overlay['name_box_label'] ?? 'NAME (PRINT):'));

            // Name box must never collide with the first field block (whose
            // digit/option labels sit 5.5mm above the bubbles) or the frame:
            // bottom 2mm above the topmost label, and hidden when the header
            // stack leaves no clean gap.
            $firstBlockTopMm = null;
            foreach ((array) ($page['fieldBlocks'] ?? []) as $block) {
                $blockTop = (float) (($block['origin'] ?? [0, 99999])[1] ?? 99999) * 25.4 / self::PPI;
                $firstBlockTopMm = $firstBlockTopMm === null ? $blockTop : min($firstBlockTopMm, $blockTop);
            }
            $nameBoxBottom = $firstBlockTopMm !== null ? max(0, $firstBlockTopMm - 7.5) : null;
            $nameBoxTop = $nameBoxBottom !== null ? $nameBoxBottom - 8 : null;
            if ($nameBoxTop !== null && $nameBoxTop >= $headerTop + 1) {
                $nameBoxLeft = $inset + 2;
                $html .= '<div class="label" style="left:'.$nameBoxLeft.'mm;top:'.$nameBoxTop.'mm;width:60mm;text-align:left;font-weight:bold;">'.e($nameBoxLabel).'</div>';
                $html .= '<div style="position:absolute;left:'.$nameBoxLeft.'mm;top:'.($nameBoxTop + 3.5).'mm;width:80mm;height:8mm;border:0.3mm solid #000;"></div>';
            }

            // Footer lines stack up from the calibration strip, always inside
            // the border frame and never below the last field block.
            $footerAnchor = $pageMmH - $inset - 4;
            foreach ((array) ($overlay['footer_lines'] ?? []) as $index => $footerLine) {
                $html .= '<div class="label" style="left:0;top:'.($footerAnchor - ($index + 1) * 5).'mm;width:'.$pageMmW.'mm;font-size:8pt;">'.e((string) $footerLine).'</div>';
            }
            foreach ((array) ($page['fieldBlocks'] ?? []) as $block) {
                $origin = $block['origin'];
                $bubbleGap = (float) $block['bubblesGap'];
                $labelGap = (float) $block['labelsGap'];
                $horizontal = (string) ($block['direction'] ?? 'vertical') !== 'vertical';
                $values = $this->blockValues($block);
                $labels = [];
                foreach ((array) ($block['fieldLabels'] ?? []) as $decl) {
                    $labels = [...$labels, ...NativeBundle::expandLabel((string) $decl)];
                }

                $isIdentity = str_starts_with($labels[0] ?? '', 'APPLICANT_NUMBER_');

                if ($isIdentity) {
                    // vertical: labels (digit positions) across x, values (0..9) down y
                    foreach ($labels as $col => $label) {
                        foreach ($values as $row => $value) {
                            $x = $origin[0] + $col * $labelGap;
                            $y = $origin[1] + $row * $bubbleGap;
                            $html .= $this->bubbleHtml($x, $y, $bubble[0], $pageMmW, $pageMmH);
                            if ($row === 0) {
                                $html .= $this->labelHtml($x + $bubble[0] / 2, $origin[1] - 5.5, (string) ($col + 1), $pageMmW);
                            }
                            if ($col === 0) {
                                $html .= $this->labelLeftHtml($origin[0] - 3, $y + $bubble[1] / 2, (string) $value, $pageMmW);
                            }
                        }
                    }
                    continue;
                }

                // question block (horizontal): options across x, one row per label down y
                foreach ($values as $opt => $value) {
                    $x = $origin[0] + $opt * $bubbleGap;
                    $html .= $this->labelHtml($x + $bubble[0] / 2, $origin[1] - 5.5, (string) $value, $pageMmW);
                }
                foreach ($labels as $row => $label) {
                    $y = $origin[1] + $row * $labelGap;
                    $qno = $this->questionNo($label, $row);
                    $html .= $this->labelLeftHtml($origin[0] - 3.5, $y + $bubble[1] / 2, (string) $qno, $pageMmW);
                    foreach ($values as $opt => $value) {
                        $x = $origin[0] + $opt * $bubbleGap;
                        $html .= $this->bubbleHtml($x, $y, $bubble[0], $pageMmW, $pageMmH);
                    }
                }
            }

            // Calibration strip lives in the bottom margin (below the border
            // frame) so it never collides with answer bubbles or footers.
            $html .= '<div class="label" style="left:0;top:'.($pageMmH - $inset + 1.5).'mm;width:'.$pageMmW.'mm;font-size:8pt;">Print at 100% scale · do NOT fit to page · calibration bar = 100mm</div>';
            $html .= '<div style="position:absolute;left:'.($pageMmW / 2 - 50).'mm;top:'.($pageMmH - $inset + 5.2).'mm;width:100mm;height:2.5mm;background:#000;"></div>';
            $html .= '</div>';
        }

        return $html.'</body></html>';
    }

    /** @return array<string, mixed> */
    public function renderPdf(NativeBundle $bundle, PrintRenderer $renderer): string
    {
        $paper = $this->paper;

        return $renderer->render($this->renderHtml($bundle->template), [
            'format' => 'custom',
            'orientation' => 'portrait',
            'width_mm' => $paper['width'],
            'height_mm' => $paper['height'],
        ]);
    }

    private function bubbleHtml(float $x, float $y, float $diameter, float $pageW, float $pageH): string
    {
        $mm = fn (float $px): float => round($px / self::PPI * 25.4, 2);

        return '<div class="bubble" style="left:'.$mm($x).'mm;top:'.$mm($y).'mm;width:'.$mm($diameter).'mm;height:'.$mm($diameter).'mm;"></div>';
    }

    private function labelHtml(float $centerX, float $topY, string $text, float $pageW): string
    {
        $mm = fn (float $px): float => round($px / self::PPI * 25.4, 2);

        return '<div class="label" style="left:'.($mm($centerX) - 3).'mm;top:'.$mm($topY).'mm;width:6mm;">'.e($text).'</div>';
    }

    private function labelLeftHtml(float $rightX, float $centerY, string $text, float $pageW): string
    {
        $mm = fn (float $px): float => round($px / self::PPI * 25.4, 2);

        return '<div class="lbl-left" style="left:'.($mm($rightX) - 5).'mm;top:'.($mm($centerY) - 2).'mm;width:5mm;">'.e($text).'</div>';
    }

    private function questionNo(string $label, int $row): int
    {
        $parts = explode('_', $label);
        $tail = end($parts);

        return is_numeric($tail) ? (int) $tail : $row + 1;
    }

    /** @return list<string> */
    private function blockValues(array $block): array
    {
        if (isset($block['bubbleValues']) && is_array($block['bubbleValues'])) {
            return array_map('strval', $block['bubbleValues']);
        }

        return match ((string) ($block['fieldType'] ?? '')) {
            'QTYPE_INT' => ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            'QTYPE_MCQ4' => ['A', 'B', 'C', 'D'],
            'QTYPE_MCQ5' => ['A', 'B', 'C', 'D', 'E'],
            default => [],
        };
    }

    /** @param array<string, mixed> $template @return list<array<string, mixed>> */
    private function pagesOf(array $template): array
    {
        $pages = $template['pages'] ?? null;

        if (is_array($pages) && $pages !== []) {
            return array_values($pages);
        }

        return [[
            'page_no' => 1,
            'pageDimensions' => $template['pageDimensions'] ?? [2000, 2828],
            'fieldBlocks' => $template['fieldBlocks'] ?? [],
            'preProcessors' => $template['preProcessors'] ?? [],
        ]];
    }

    /** @param array{0: int, 1: int}|mixed $dims @return array{0: float, 1: float} */
    private function assertPaperMatch(mixed $dims, int $pageNo): array
    {
        $widthMm = round(((float) $dims[0] / self::PPI) * 25.4, 1);
        $heightMm = round(((float) $dims[1] / self::PPI) * 25.4, 1);

        if (abs($widthMm - $this->paper['width']) > 1.0
            || abs($heightMm - $this->paper['height']) > 1.0) {
            throw new RuntimeException(sprintf(
                'Page %d geometry (%s×%smm) does not match configured %s paper (%s×%smm); geometry is never scaled.',
                $pageNo, $widthMm, $heightMm, $this->paper['name'], $this->paper['width'], $this->paper['height'],
            ));
        }

        return [$this->paper['width'], $this->paper['height']];
    }
}
