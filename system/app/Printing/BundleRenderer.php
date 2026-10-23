<?php

namespace App\Printing;

use App\Assessment\Omr\Fiducial\OmrFiducial;
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
            'a4' => ['width' => 210.0, 'height' => 297.0, 'name' => 'a4'],
            'long' => ['width' => 216.0, 'height' => 330.0, 'name' => 'long'],
            'legal' => ['width' => 216.0, 'height' => 356.0, 'name' => 'legal'],
            'letter' => ['width' => 216.0, 'height' => 279.0, 'name' => 'letter'],
            default => throw new RuntimeException("Unsupported paper size: {$name}."),
        };
    }

    public function paper(): array
    {
        return $this->paper;
    }

    /**
     * Project render-only identity boxes into normalized review coordinates.
     * The calculation intentionally follows the print renderer's EDQ and
     * legacy placement rules so crops use the same physical frame as the PDF.
     *
     * @return array<int, array{name: array{x: float, y: float, w: float, h: float}|null}>
     */
    public function reviewOverlayRegions(array $bundleTemplate, array $overlay): array
    {
        $regions = [];
        $pages = $this->pagesOf($bundleTemplate);
        $bubbleDiameter = (float) (($bundleTemplate['bubbleDimensions'] ?? [28, 28])[0] ?? 28);

        foreach ($pages as $index => $page) {
            $pageNo = $index + 1;
            $markers = is_array($page['markers'] ?? null) ? $page['markers'] : null;
            [$pageMmW, $pageMmH] = $this->assertPaperMatch($page['pageDimensions'], $pageNo, $markers);
            $headerTop = 9.0;
            foreach ((array) ($overlay['header_lines'] ?? []) as $headerLine) {
                if (trim((string) $headerLine) !== '') {
                    $headerTop += 5.0;
                }
            }
            $headerTop += 5.0;
            foreach ((array) ($overlay['per_page'] ?? []) as $pageNote) {
                if ((int) ($pageNote['page_no'] ?? 0) === $pageNo && trim((string) ($pageNote['note'] ?? '')) !== '') {
                    $headerTop += 5.0;
                    break;
                }
            }

            $inset = round((80 / self::PPI) * 25.4, 2);
            $markerInsetPx = $markers !== null ? (int) ($markers['inset'] ?? 0) : 0;
            $fieldBlocks = (array) ($page['fieldBlocks'] ?? []);
            $box = null;

            $frameMarginMm = $markers !== null ? round($markerInsetPx / self::PPI * 25.4, 2) : $inset;
            $markerExclusionMm = 0.0;
            if ($markers !== null) {
                $markerExclusionMm = round(($markerInsetPx + intdiv((int) ($markers['size'] ?? 121), 2) + (int) ($markers['quiet_zone'] ?? 40)) / self::PPI * 25.4, 2);
            }
            $headerRightMm = $markers !== null ? round($pageMmW - $markerExclusionMm, 2) : $pageMmW;

            if (($overlay['edq_header'] ?? false) === true) {
                if (trim((string) ($overlay['edq_header_label'] ?? 'EDQ / HEADER ZONE')) !== '') {
                    $headerTop += 4.0;
                }

                $layout = is_array($overlay['edq_layout'] ?? null) ? $overlay['edq_layout'] : [];
                $columns = max(1, min(3, (int) ($layout['columns'] ?? 3)));
                $renderColumns = $columns;
                $gapMm = max(2.0, min(10.0, (float) ($layout['gap_mm'] ?? 4.0)));
                $gridLeftMm = $inset;
                if (($layout['reserve_native_identity'] ?? false) === true) {
                    $nativeRightPx = 0.0;
                    foreach ($fieldBlocks as $fieldBlock) {
                        if ((string) ($fieldBlock['fieldType'] ?? '') !== 'QTYPE_INT') {
                            continue;
                        }
                        $labels = [];
                        foreach ((array) ($fieldBlock['fieldLabels'] ?? []) as $declaration) {
                            $labels = [...$labels, ...NativeBundle::expandLabel((string) $declaration)];
                        }
                        $origin = (array) ($fieldBlock['origin'] ?? [0, 0]);
                        $labelGap = (float) ($fieldBlock['labelsGap'] ?? 0);
                        $nativeRightPx = max($nativeRightPx, (float) ($origin[0] ?? 0) + $markerInsetPx + max(0, count($labels) - 1) * $labelGap + $bubbleDiameter);
                    }
                    if ($nativeRightPx > 0) {
                        $renderColumns = max(1, $columns - 1);
                        $gridLeftMm = min($pageMmW - $inset, round($nativeRightPx / self::PPI * 25.4 + 4, 2));
                    }
                }
                $usableWidthMm = ($headerRightMm > 0.0 ? $headerRightMm : $pageMmW) - $inset - $gridLeftMm;
                $cellWidthMm = ($usableWidthMm - ($renderColumns - 1) * $gapMm) / $renderColumns;
                $items = [];
                foreach ([
                    ['kind' => 'text', 'lines' => $overlay['exam_information_lines'] ?? []],
                    ['kind' => 'text', 'lines' => $overlay['instructions_lines'] ?? []],
                ] as $item) {
                    $lines = array_values(array_filter(
                        array_map(static fn (mixed $line): string => trim((string) $line), (array) $item['lines']),
                        static fn (string $line): bool => $line !== '',
                    ));
                    if ($lines !== []) {
                        $items[] = ['kind' => 'text', 'lines' => $lines];
                    }
                }
                $items[] = ['kind' => 'identity', 'lines' => []];
                $rowHeights = [];
                foreach (array_chunk($items, $renderColumns) as $row) {
                    $rowHeights[] = max(array_map(
                        static fn (array $item): float => $item['kind'] === 'identity' ? 30.5 : 3.5 + count($item['lines']) * 3.5,
                        $row,
                    ));
                }
                $identityIndex = count($items) - 1;
                $rowIndex = intdiv($identityIndex, $renderColumns);
                $columnIndex = $identityIndex % $renderColumns;
                $rowTop = $headerTop + 2.0;
                for ($row = 0; $row < $rowIndex; $row++) {
                    $rowTop += $rowHeights[$row] + $gapMm;
                }
                $box = [
                    'x_mm' => round($gridLeftMm + $columnIndex * ($cellWidthMm + $gapMm), 2),
                    'y_mm' => round($rowTop + 7.0, 2),
                    'w_mm' => round($cellWidthMm, 2),
                    'h_mm' => 8.0,
                ];
            } else {
                $firstBlockTopMm = null;
                foreach ($fieldBlocks as $fieldBlock) {
                    $originY = (float) (($fieldBlock['origin'] ?? [0, 99999])[1] ?? 99999) + $markerInsetPx;
                    $labelTop = $originY - 9 * self::PPI / 72 - 8;
                    $blockTop = $labelTop * 25.4 / self::PPI;
                    $firstBlockTopMm = $firstBlockTopMm === null ? $blockTop : min($firstBlockTopMm, $blockTop);
                }
                $nameBoxBottom = $firstBlockTopMm !== null ? round(max(0, $firstBlockTopMm - (8 / self::PPI * 25.4)), 2) : null;
                $nameBoxTop = $nameBoxBottom !== null ? round($nameBoxBottom - 8, 2) : null;
                $topPlacementSafe = $nameBoxTop !== null && $nameBoxTop >= $headerTop + 1;
                $nameBoxLeft = $inset + 2;
                $nameBoxWidthMm = 80.0;
                if (! $topPlacementSafe || $bubbleDiameter <= 40.0) {
                    $nameBoxWidthMm = 50.0;
                    $nameBoxLeft = round($pageMmW - $inset - $nameBoxWidthMm - 2, 2);
                    $nameBoxTop = round(max($headerTop + 2, $nameBoxTop ?? ($inset + 2)), 2);
                }
                // Marker pages: the render-only name stays in the central band
                // between the top markers (mirrors renderHtml placement).
                if ($markers !== null) {
                    $nameBoxLeft = $markerExclusionMm + 2;
                    $nameBoxWidthMm = max(50.0, round($pageMmW - $markerExclusionMm - $nameBoxLeft - 2, 2));
                    $nameBoxTop = round(max($headerTop + 2, $markerExclusionMm + 2), 2);
                }
                $box = [
                    'x_mm' => $nameBoxLeft,
                    'y_mm' => round(($nameBoxTop ?? ($inset + 2)) + 3.5, 2),
                    'w_mm' => $nameBoxWidthMm,
                    'h_mm' => 8.0,
                ];
            }

            $regions[$pageNo] = [
                'name' => $box === null ? null : [
                    'x' => round($box['x_mm'] / $pageMmW, 8),
                    'y' => round($box['y_mm'] / $pageMmH, 8),
                    'w' => round($box['w_mm'] / $pageMmW, 8),
                    'h' => round($box['h_mm'] / $pageMmH, 8),
                ],
            ];
        }

        return $regions;
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
            // Diameter is the complete printed mark envelope; the outline must
            // not expand the native OMR rectangle or make adjacent marks touch.
            .'.bubble{box-sizing:border-box;position:absolute;border:0.35mm solid #000;border-radius:50%;}'
            .'.label{position:absolute;font-size:9pt;text-align:center;}'
            .'.lbl-left{position:absolute;font-size:8pt;text-align:right;}'
            .'</style></head><body>';

        foreach ($pages as $index => $page) {
            $dims = $page['pageDimensions'];
            $markers = is_array($page['markers'] ?? null) ? $page['markers'] : null;
            [$pageMmW, $pageMmH] = $this->assertPaperMatch($dims, $index + 1, $markers, false);
            $total = count($pages);
            $pageNo = $index + 1;
            // 0.2mm breathing room avoids a rounding-driven 0.003pt overflow
            // that would spill an empty page between sheets.
            $sheetHeight = $pageMmH - 0.2;
            $html .= '<div class="sheet" style="width:'.$pageMmW.'mm;height:'.$sheetHeight.'mm;">';
            $inset = round((80 / self::PPI) * 25.4, 2); // 80px geometry margin in mm
            // Marker-bearing pages: block origins live in the fiducial-center
            // frame; printing shifts them by the inset ring, and the four
            // fiducials are drawn at the frame corners on the paper.
            $markerInsetPx = $markers !== null ? (int) ($markers['inset'] ?? 0) : 0;
            // Marker pages: the fiducial-center ring and the exclusion band
            // (marker full rectangle + quiet zone) bound all render-only
            // header content so it never lands on a marker or the paper edge.
            $frameMarginMm = $markers !== null ? round($markerInsetPx / self::PPI * 25.4, 2) : $inset;
            $markerExclusionMm = 0.0;
            if ($markers !== null) {
                $markerHalfPx = intdiv((int) ($markers['size'] ?? 121), 2);
                $quietPx = (int) ($markers['quiet_zone'] ?? 40);
                $markerExclusionMm = round(($markerInsetPx + $markerHalfPx + $quietPx) / self::PPI * 25.4, 2);
            }
            // Frame is emitted after header geometry is known.

            $title = trim((string) ($overlay['title'] ?? ''));
            $title = $title !== '' ? $title : 'SYNTHETIC NEXIAM OMR ANSWER SHEET';
            $html .= '<div class="label" style="left:0;top:4mm;width:'.$pageMmW.'mm;font-size:11pt;font-weight:bold;">'.e($title).'</div>';

            $headerTop = 9;
            foreach ((array) ($overlay['header_lines'] ?? []) as $headerLine) {
                $line = trim((string) $headerLine);
                if ($line === '') {
                    continue;
                }
                $html .= '<div class="label" style="left:0;top:'.$headerTop.'mm;width:'.$pageMmW.'mm;font-weight:bold;">'.e($line).'</div>';
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
                $html .= '<div class="label" style="left:0;top:'.$headerTop.'mm;width:'.$pageMmW.'mm;font-size:8pt;line-height:3mm;height:3mm;">'.e($perPageNote).'</div>';
                $headerTop += 5;
            }

            $isEdqHeader = (bool) ($overlay['edq_header'] ?? false);
            if ($isEdqHeader) {
                $edqLabel = trim((string) ($overlay['edq_header_label'] ?? 'EDQ / HEADER ZONE'));
                if ($edqLabel !== '') {
                    $html .= '<div class="label" style="left:'.$inset.'mm;top:'.$headerTop.'mm;width:70mm;text-align:left;font-size:8pt;font-weight:bold;">'.e($edqLabel).'</div>';
                    $headerTop += 4;
                }

                $edqGrid = $this->renderEdqGrid(
                    $overlay,
                    $pageMmW,
                    $inset,
                    $headerTop + 2,
                    (array) ($page['fieldBlocks'] ?? []),
                    (float) ($bubble[0] ?? 40),
                    $markerInsetPx,
                    $markers !== null ? round($pageMmW - $markerExclusionMm, 2) : $pageMmW,
                    $markers !== null ? $markerExclusionMm : 0.0,
                );
                $html .= $edqGrid['html'];
                $headerTop = $edqGrid['bottom_mm'];
            }

            $firstBlockTopMm = null;
            $firstAnswerLabelTopMm = null;
            foreach ((array) ($page['fieldBlocks'] ?? []) as $block) {
                $originY = (float) (($block['origin'] ?? [0, 99999])[1] ?? 99999) + $markerInsetPx;
                $labelTop = $originY - 9 * self::PPI / 72 - 8;
                $blockTop = $labelTop * 25.4 / self::PPI;
                $firstBlockTopMm = $firstBlockTopMm === null ? $blockTop : min($firstBlockTopMm, $blockTop);
                if ((string) ($block['fieldType'] ?? '') !== 'QTYPE_INT') {
                    $firstAnswerLabelTopMm = $firstAnswerLabelTopMm === null ? $blockTop : min($firstAnswerLabelTopMm, $blockTop);
                }
            }

            if ($isEdqHeader && $firstAnswerLabelTopMm !== null && $headerTop + 1 >= $firstAnswerLabelTopMm) {
                throw new RuntimeException(sprintf(
                    'EDQ header layout reaches the first answer block at %.2fmm; choose more columns or shorten the header.',
                    $firstAnswerLabelTopMm,
                ));
            }

            if (! $isEdqHeader) {
                $nameBoxLabel = trim((string) ($overlay['name_box_label'] ?? ''));
                $nameBoxLabel = $nameBoxLabel !== '' ? $nameBoxLabel : 'NAME (PRINT):';

                // Prefer the gap above the first field block. If the header stack
                // fills that gap, or compact identity labels occupy it, use the
                // unused upper-right band instead of suppressing the manual fallback.
                $nameBoxBottom = $firstBlockTopMm !== null ? round(max(0, $firstBlockTopMm - (8 / self::PPI * 25.4)), 2) : null;
                $nameBoxTop = $nameBoxBottom !== null ? round($nameBoxBottom - 8, 2) : null;
                $topPlacementSafe = $nameBoxTop !== null && $nameBoxTop >= $headerTop + 1;
                $nameBoxLeft = $inset + 2;
                $nameBoxWidthMm = 80.0;

                if (! $topPlacementSafe || (float) ($bubble[0] ?? 0) <= 40.0) {
                    $nameBoxWidthMm = 50.0;
                    $nameBoxLeft = round($pageMmW - $inset - $nameBoxWidthMm - 2, 2);
                    $nameBoxTop = round(max($headerTop + 2, $nameBoxTop ?? ($inset + 2)), 2);
                }

                // Marker pages: the render-only header stays in the central band
                // between the top markers — never inside either marker's full
                // rectangle plus quiet zone.
                if ($markers !== null) {
                    $nameBoxLeft = $markerExclusionMm + 2;
                    $nameBoxWidthMm = max(50.0, round($pageMmW - $markerExclusionMm - $nameBoxLeft - 2, 2));
                    $nameBoxTop = round(max($headerTop + 2, $markerExclusionMm + 2), 2);
                }

                $html .= '<div class="label" style="left:'.$nameBoxLeft.'mm;top:'.$nameBoxTop.'mm;width:'.$nameBoxWidthMm.'mm;text-align:left;font-weight:bold;">'.e($nameBoxLabel).'</div>';
                $html .= '<div style="position:absolute;left:'.$nameBoxLeft.'mm;top:'.($nameBoxTop + 3.5).'mm;width:'.$nameBoxWidthMm.'mm;height:8mm;border:0.3mm solid #000;"></div>';
            }

            // Keep the frame below the header stack and above the first field labels.
            // Marker-bearing pages draw NO decorative frame: the fiducial-center
            // ring is where the four bullseye markers sit, and a border line
            // through the marker centers/quiet zone would corrupt registration.
            $frameTopMm = $isEdqHeader ? $frameMarginMm : max($frameMarginMm, $headerTop + 1);
            if (! $isEdqHeader && $firstBlockTopMm !== null) {
                $frameTopMm = min($frameTopMm, $firstBlockTopMm - 0.5);
            }
            $frameTopMm = max($frameMarginMm, round($frameTopMm, 2));
            $frameHeightMm = max(0, round($pageMmH - $frameMarginMm - $frameTopMm, 2));
            if (($overlay['show_frame'] ?? true) !== false && $markers === null) {
                $html .= '<div style="position:absolute;left:'.$frameMarginMm.'mm;top:'.$frameTopMm.'mm;width:'.round($pageMmW - 2 * $frameMarginMm, 2).'mm;height:'.$frameHeightMm.'mm;border:0.7mm solid #000;"></div>';
            }

            // The four bullseye markers print at the frame corners using
            // OMRChecker's OWN pinned asset (alignment fiducial, not a
            // template ID), inside the normal printable band.
            if ($markers !== null) {
                $markerBytes = OmrFiducial::assetBytes();
                $markerUri = 'data:image/jpeg;base64,'.base64_encode($markerBytes);
                $markerSize = (int) ($markers['size'] ?? 121);
                $markerMm = round($markerSize / self::PPI * 25.4, 2);
                foreach ((array) ($markers['centers'] ?? []) as $center) {
                    $leftMm = round(((int) ($center[0] ?? 0) - intdiv($markerSize, 2)) / self::PPI * 25.4, 2);
                    $topMm = round(((int) ($center[1] ?? 0) - intdiv($markerSize, 2)) / self::PPI * 25.4, 2);
                    $html .= '<img src="'.$markerUri.'" style="position:absolute;left:'.$leftMm.'mm;top:'.$topMm.'mm;width:'.$markerMm.'mm;height:'.$markerMm.'mm;"/>';
                }
            }

            // Footer lines stack up from the calibration strip, always inside
            // the border frame and never below the last field block.
            $footerAnchor = $pageMmH - $frameMarginMm - 6;
            foreach ((array) ($overlay['footer_lines'] ?? []) as $index => $footerLine) {
                $html .= '<div class="label" style="left:0;top:'.($footerAnchor - ($index + 1) * 5).'mm;width:'.$pageMmW.'mm;font-size:8pt;">'.e((string) $footerLine).'</div>';
            }
            foreach ((array) ($page['fieldBlocks'] ?? []) as $block) {
                $origin = $block['origin'];
                $originX = (int) ($origin[0] ?? 0) + $markerInsetPx;
                $originY = (int) ($origin[1] ?? 0) + $markerInsetPx;
                $bubbleGap = (float) $block['bubblesGap'];
                $labelGap = (float) $block['labelsGap'];
                $horizontal = (string) ($block['direction'] ?? 'vertical') !== 'vertical';
                $values = $this->blockValues($block);
                $labels = [];
                foreach ((array) ($block['fieldLabels'] ?? []) as $decl) {
                    $labels = [...$labels, ...NativeBundle::expandLabel((string) $decl)];
                }

                $isIdentity = (string) ($block['fieldType'] ?? '') === 'QTYPE_INT';

                if (! $horizontal) {
                    // Native vertical grids place declared labels across x and
                    // option values down y. Identity and vertical question grids
                    // share coordinates; only their printed label text differs.
                    foreach ($labels as $col => $label) {
                        foreach ($values as $row => $value) {
                            $x = $originX + $col * $labelGap;
                            $y = $originY + $row * $bubbleGap;
                            $html .= $this->bubbleHtml($x, $y, $bubble[0], $pageMmW, $pageMmH);
                            if ($row === 0) {
                                $topLabel = $isIdentity ? (string) ($col + 1) : (string) $this->questionNo($label, $col);
                                $html .= $this->labelHtml($x + $bubble[0] / 2, $originY, $topLabel, $pageMmW);
                            }
                            if ($col === 0) {
                                $html .= $this->labelLeftHtml($originX - 3, $y + $bubble[1] / 2, (string) $value, $pageMmW);
                            }
                        }
                    }

                    continue;
                }

                // question block (horizontal): options across x, one row per label down y
                // Marker pages: a bold area heading above the column (in the
                // solver-reserved heading band, clear of the top markers).
                if ($markers !== null && preg_match('/^([A-Z0-9_]+)_Q_/', (string) ($block['fieldLabels'][0] ?? ''), $areaMatch) === 1) {
                    $bandPx = $bubbleGap * 3 + $bubble[0];
                    $headingLeftMm = round(($originX - 2) / self::PPI * 25.4, 2);
                    $headingTopMm = round(($originY - 74) / self::PPI * 25.4, 2);
                    $headingWidthMm = round(($bandPx + 4) / self::PPI * 25.4, 2);
                    $html .= '<div class="label" style="left:'.$headingLeftMm.'mm;top:'.$headingTopMm.'mm;width:'.$headingWidthMm.'mm;font-size:9pt;font-weight:bold;">'.e($areaMatch[1]).'</div>';
                }
                foreach ($values as $opt => $value) {
                    $x = $originX + $opt * $bubbleGap;
                    $html .= $this->labelHtml($x + $bubble[0] / 2, $originY, (string) $value, $pageMmW);
                }
                foreach ($labels as $row => $label) {
                    $y = $originY + $row * $labelGap;
                    $qno = $this->questionNo($label, $row);
                    $html .= $this->labelLeftHtml($originX - 3.5, $y + $bubble[1] / 2, (string) $qno, $pageMmW);
                    foreach ($values as $opt => $value) {
                        $x = $originX + $opt * $bubbleGap;
                        $html .= $this->bubbleHtml($x, $y, $bubble[0], $pageMmW, $pageMmH);
                    }
                }
            }

            // Legacy overlays keep the calibration strip; compact EDQ profiles
            // can explicitly leave the lower margin blank.
            if (($overlay['show_calibration'] ?? true) !== false) {
                $html .= '<div class="label" style="left:0;top:'.($pageMmH - $frameMarginMm + 1.5).'mm;width:'.$pageMmW.'mm;font-size:8pt;">Print at 100% scale · do NOT fit to page · calibration bar = 100mm</div>';
                $html .= '<div style="position:absolute;left:'.($pageMmW / 2 - 50).'mm;top:'.($pageMmH - $frameMarginMm + 5.2).'mm;width:100mm;height:2.5mm;background:#000;"></div>';
            }
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

    /** @return array{html: string, bottom_mm: float} */
    private function renderEdqGrid(array $overlay, float $pageMmW, float $inset, float $topMm, array $fieldBlocks, float $bubbleDiameter, int $markerInsetPx = 0, float $headerRightMm = 0.0, float $headerLeftMm = 0.0): array
    {
        $layout = is_array($overlay['edq_layout'] ?? null) ? $overlay['edq_layout'] : [];
        $columns = max(1, min(3, (int) ($layout['columns'] ?? 3)));
        $reserveNativeIdentity = ($layout['reserve_native_identity'] ?? false) === true;
        $renderColumns = $columns;
        $gapMm = max(2.0, min(10.0, (float) ($layout['gap_mm'] ?? 4.0)));
        $gridLeftMm = $inset;
        if ($reserveNativeIdentity) {
            $nativeRightPx = 0.0;
            foreach ($fieldBlocks as $block) {
                if ((string) ($block['fieldType'] ?? '') !== 'QTYPE_INT') {
                    continue;
                }
                $labels = [];
                foreach ((array) ($block['fieldLabels'] ?? []) as $decl) {
                    $labels = [...$labels, ...NativeBundle::expandLabel((string) $decl)];
                }
                $origin = (array) ($block['origin'] ?? [0, 0]);
                $labelGap = (float) ($block['labelsGap'] ?? 0);
                $nativeRightPx = max($nativeRightPx, (float) ($origin[0] ?? 0) + $markerInsetPx + max(0, count($labels) - 1) * $labelGap + $bubbleDiameter);
            }
            if ($nativeRightPx > 0) {
                $renderColumns = max(1, $columns - 1);
                $gridLeftMm = min($pageMmW - $inset, round($nativeRightPx / self::PPI * 25.4 + 4, 2));
            }
        }
        if ($headerLeftMm > 0.0) {
            $gridLeftMm = max($gridLeftMm, $headerLeftMm);
        }
        $usableWidthMm = ($headerRightMm > 0.0 ? $headerRightMm : $pageMmW) - $inset - $gridLeftMm;
        $cellWidthMm = ($usableWidthMm - ($renderColumns - 1) * $gapMm) / $renderColumns;
        $items = [];

        foreach ([
            ['kind' => 'text', 'label' => 'EXAM INFORMATION', 'lines' => $overlay['exam_information_lines'] ?? []],
            ['kind' => 'text', 'label' => 'INSTRUCTIONS', 'lines' => $overlay['instructions_lines'] ?? []],
        ] as $item) {
            $lines = array_values(array_filter(
                array_map(static fn (mixed $line): string => trim((string) $line), (array) $item['lines']),
                static fn (string $line): bool => $line !== '',
            ));
            if ($lines !== []) {
                $items[] = array_merge($item, ['lines' => $lines]);
            }
        }
        $items[] = ['kind' => 'identity', 'label' => 'IDENTITY', 'lines' => []];

        $rowHeights = [];
        foreach (array_chunk($items, $renderColumns) as $row) {
            $rowHeights[] = max(array_map(
                static fn (array $item): float => $item['kind'] === 'identity' ? 30.5 : 3.5 + count($item['lines']) * 3.5,
                $row,
            ));
        }

        $html = '';
        $itemIndex = 0;
        $rowTop = $topMm;
        foreach ($rowHeights as $rowHeight) {
            foreach (array_slice($items, $itemIndex, $renderColumns) as $column => $item) {
                $leftMm = round($gridLeftMm + $column * ($cellWidthMm + $gapMm), 2);
                $cellWidth = round($cellWidthMm, 2);
                $labelStyle = 'left:'.$leftMm.'mm;top:'.$rowTop.'mm;width:'.$cellWidth.'mm;text-align:left;font-size:7.5pt;font-weight:bold;';
                $html .= '<div class="label" style="'.$labelStyle.'">'.e($item['label']).'</div>';

                if ($item['kind'] === 'identity') {
                    $nameLabel = trim((string) ($overlay['name_box_label'] ?? 'NAME (PRINT):'));
                    $nameLabel = $nameLabel !== '' ? $nameLabel : 'NAME (PRINT):';
                    $birthdateLabel = trim((string) ($overlay['birthdate_label'] ?? 'BIRTHDATE:'));
                    $birthdatePlaceholder = trim((string) ($overlay['birthdate_placeholder'] ?? 'MMDDYYYY'));
                    $html .= '<div class="label" style="left:'.$leftMm.'mm;top:'.($rowTop + 3.5).'mm;width:'.$cellWidth.'mm;text-align:left;font-size:7.5pt;font-weight:bold;">'.e($nameLabel).'</div>';
                    $html .= '<div style="position:absolute;left:'.$leftMm.'mm;top:'.($rowTop + 7).'mm;width:'.$cellWidth.'mm;height:8mm;border:0.3mm solid #000;box-sizing:border-box;"></div>';
                    $html .= '<div class="label" style="left:'.$leftMm.'mm;top:'.($rowTop + 18.5).'mm;width:'.$cellWidth.'mm;text-align:left;font-size:7.5pt;font-weight:bold;">'.e($birthdateLabel).'</div>';
                    $html .= '<div style="position:absolute;left:'.$leftMm.'mm;top:'.($rowTop + 22).'mm;width:'.$cellWidth.'mm;height:8mm;border:0.3mm solid #000;box-sizing:border-box;"></div>';
                    $html .= '<div class="label" style="left:'.$leftMm.'mm;top:'.($rowTop + 23.5).'mm;width:'.$cellWidth.'mm;font-size:7pt;color:#555;">'.e($birthdatePlaceholder).'</div>';
                } else {
                    foreach ($item['lines'] as $lineIndex => $line) {
                        $html .= '<div class="label" style="left:'.$leftMm.'mm;top:'.($rowTop + 3.5 + $lineIndex * 3.5).'mm;width:'.$cellWidth.'mm;text-align:left;font-size:7.5pt;">'.e($line).'</div>';
                    }
                }
            }
            $itemIndex += $renderColumns;
            $rowTop += $rowHeight + $gapMm;
        }

        return ['html' => $html, 'bottom_mm' => round($rowTop - $gapMm, 2)];
    }

    private function bubbleHtml(float $x, float $y, float $diameter, float $pageW, float $pageH): string
    {
        $mm = fn (float $px): float => round($px / self::PPI * 25.4, 2);

        return '<div class="bubble" style="left:'.$mm($x).'mm;top:'.$mm($y).'mm;width:'.$mm($diameter).'mm;height:'.$mm($diameter).'mm;"></div>';
    }

    private function labelHtml(float $centerX, float $originY, string $text, float $pageW): string
    {
        $mm = fn (float $px): float => round($px / self::PPI * 25.4, 2);
        $fontSizePt = 9.0;
        $labelHeightMm = $fontSizePt * 25.4 / 72;
        $topPx = $originY - $fontSizePt * self::PPI / 72 - 8;

        return '<div class="label" style="left:'.($mm($centerX) - 3).'mm;top:'.$mm($topPx).'mm;width:6mm;height:'.$labelHeightMm.'mm;line-height:'.$labelHeightMm.'mm;">'.e($text).'</div>';
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

    /**
     * @param  array{0: int, 1: int}|mixed  $dims  frame (pageDimensions) in px
     * @param  array<string, mixed>|null  $markers  page marker metadata (physical paper = frame + 2×inset)
     * @return array{0: float, 1: float}
     */
    private function assertPaperMatch(mixed $dims, int $pageNo, ?array $markers = null, bool $allowStandardPaper = true): array
    {
        if ($markers !== null && is_array($markers['paper'] ?? null)) {
            if (isset($markers['paper']['width_mm'], $markers['paper']['height_mm'])) {
                $widthMm = round((float) $markers['paper']['width_mm'], 1);
                $heightMm = round((float) $markers['paper']['height_mm'], 1);
            } else {
                $widthMm = round(((float) $markers['paper'][0] / self::PPI) * 25.4, 1);
                $heightMm = round(((float) $markers['paper'][1] / self::PPI) * 25.4, 1);
            }
        } else {
            $widthMm = round(((float) $dims[0] / self::PPI) * 25.4, 1);
            $heightMm = round(((float) $dims[1] / self::PPI) * 25.4, 1);
        }

        if (abs($widthMm - $this->paper['width']) <= 2.0 && abs($heightMm - $this->paper['height']) <= 2.0) {
            return [$this->paper['width'], $this->paper['height']];
        }

        if ($allowStandardPaper) {
            foreach (['a4', 'long', 'legal', 'letter'] as $standard) {
                $paper = self::paperFor($standard);
                if (abs($widthMm - $paper['width']) <= 2.0 && abs($heightMm - $paper['height']) <= 2.0) {
                    return [$paper['width'], $paper['height']];
                }
            }
        }

        throw new RuntimeException(sprintf(
            'Page %d geometry (%s×%smm) does not match configured %s paper (%s×%smm); geometry is never scaled.',
            $pageNo, $widthMm, $heightMm, $this->paper['name'], $this->paper['width'], $this->paper['height'],
        ));
    }
}
