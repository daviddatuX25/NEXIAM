<?php

namespace App\Assessment\Omr\Canonical;

use App\Assessment\Omr\NativeBundle;
use RuntimeException;

use function array_values;
use function base64_encode;
use function count;
use function htmlspecialchars;
use function is_array;
use function preg_match;
use function round;

/**
 * Renders the native bundle geometry at the physical page boundary.
 *
 * This renderer consumes only native fieldBlocks plus explicit visual metadata
 * carried by the canonical definition. It never recalculates bubble origins.
 */
final class CanonicalNativePageRenderer
{
    public const PPI = 300;

    /** @param array<string,mixed> $template */
    public function renderHtml(array $template, ?string $markerBytes = null): string
    {
        $pages = $this->pagesOf($template);
        $components = is_array($template['visualComponents'] ?? null) ? $template['visualComponents'] : [];
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .'@page{margin:0;}*{box-sizing:border-box;}html,body{margin:0;padding:0;background:#fff;}body{font-family:DejaVu Sans,sans-serif;}'
            .'.sheet{position:relative;overflow:hidden;page-break-after:always;background:#fff;}'
            .'.sheet:last-child{page-break-after:auto;}'
            .'.bubble{position:absolute;border:.28mm solid #000;border-radius:50%;background:#fff;}'
            .'.text{position:absolute;overflow:hidden;line-height:1.15;}'
            .'.section-title{position:absolute;overflow:hidden;border:.25mm solid #111;background:#111;color:#fff;border-radius:.6mm;text-align:center;font-weight:800;text-transform:uppercase;}'
            .'.field{position:absolute;overflow:hidden;border:.25mm solid #334155;background:#fff;}'
            .'.option{position:absolute;text-align:center;font:700 6pt DejaVu Sans,sans-serif;}'
            .'.question{position:absolute;text-align:right;font:700 6pt DejaVu Sans,sans-serif;}'
            .'.marker{position:absolute;}'
            .'.signature-line{position:absolute;border-bottom:.3mm solid #334155;}'
            .'</style></head><body>';

        foreach ($pages as $index => $page) {
            $pageNo = $index + 1;
            $dims = (array) ($page['pageDimensions'] ?? [0, 0]);
            $markers = is_array($page['markers'] ?? null) ? $page['markers'] : [];
            $paper = is_array($markers['paper'] ?? null) ? $markers['paper'] : [];
            $paperWidthMm = (float) ($paper['width_mm'] ?? OmrPageDimensionMath::millimetresFromPixels((int) ($dims[0] ?? 0)));
            $paperHeightMm = (float) ($paper['height_mm'] ?? OmrPageDimensionMath::millimetresFromPixels((int) ($dims[1] ?? 0)));
            $insets = $this->insets($markers);
            $html .= '<div class="sheet" style="width:'.$paperWidthMm.'mm;height:'.$paperHeightMm.'mm;">';
            $html .= $this->markerHtml($markers, $markerBytes, $insets);
            $html .= $this->componentHtml($components, $pageNo);
            $html .= $this->fieldBlocksHtml((array) ($page['fieldBlocks'] ?? []), $components, $insets, $paperWidthMm, $paperHeightMm);
            $html .= '</div>';
        }

        return $html.'</body></html>';
    }

    /** @param array<string,mixed> $template */
    public function renderReferenceSvg(array $template, ?string $markerBytes = null): string
    {
        $pages = $this->pagesOf($template);
        $page = $pages[0] ?? null;
        if (! is_array($page)) {
            throw new RuntimeException('canonical_reference_page_missing');
        }
        $dims = (array) ($page['pageDimensions'] ?? [0, 0]);
        $markers = is_array($page['markers'] ?? null) ? $page['markers'] : [];
        $paper = is_array($markers['paper'] ?? null) ? $markers['paper'] : [];
        $paperWidth = (float) ($paper['width_mm'] ?? OmrPageDimensionMath::millimetresFromPixels((int) ($dims[0] ?? 0)));
        $paperHeight = (float) ($paper['height_mm'] ?? OmrPageDimensionMath::millimetresFromPixels((int) ($dims[1] ?? 0)));
        $insets = $this->insets($markers);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="'.$paperWidth.'mm" height="'.$paperHeight.'mm" viewBox="0 0 '.$paperWidth.' '.$paperHeight.'">';
        $svg .= '<rect width="100%" height="100%" fill="#fff"/>';
        if ($markerBytes !== null) {
            $uri = 'data:image/jpeg;base64,'.base64_encode($markerBytes);
            $sizeMm = $this->pxToMm((int) ($markers['size'] ?? 0));
            foreach ((array) ($markers['centers'] ?? []) as $center) {
                $x = $insets['left'] + $this->pxToMm((int) ($center[0] ?? 0)) - $sizeMm / 2;
                $y = $insets['top'] + $this->pxToMm((int) ($center[1] ?? 0)) - $sizeMm / 2;
                $svg .= '<image x="'.$x.'" y="'.$y.'" width="'.$sizeMm.'mm" height="'.$sizeMm.'mm" href="'.$uri.'" xlink:href="'.$uri.'"/>';
            }
        }
        foreach ((array) ($page['fieldBlocks'] ?? []) as $name => $block) {
            if (! is_array($block)) {
                continue;
            }
            $origin = (array) ($block['origin'] ?? [0, 0]);
            $x = $insets['left'] + $this->pxToMm((int) ($origin[0] ?? 0));
            $y = $insets['top'] + $this->pxToMm((int) ($origin[1] ?? 0));
            $bubble = (array) ($block['bubbleDimensions'] ?? [0, 0]);
            $bubbleMm = $this->pxToMm((int) ($bubble[0] ?? 0));
            $values = $this->values($block);
            $labels = [];
            foreach ((array) ($block['fieldLabels'] ?? []) as $declaration) {
                $labels = [...$labels, ...NativeBundle::expandLabel((string) $declaration)];
            }
            $vertical = (string) ($block['direction'] ?? 'horizontal') === 'vertical';
            $bubbleGapMm = $this->pxToMm((int) ($block['bubblesGap'] ?? 0));
            $labelGapMm = $this->pxToMm((int) ($block['labelsGap'] ?? 0));
            foreach ($labels as $labelIndex => $label) {
                foreach ($values as $valueIndex => $value) {
                    $bx = $vertical ? $x + $labelIndex * $labelGapMm : $x + $valueIndex * $bubbleGapMm;
                    $by = $vertical ? $y + $valueIndex * $bubbleGapMm : $y + $labelIndex * $labelGapMm;
                    $svg .= '<circle cx="'.($bx + $bubbleMm / 2).'" cy="'.($by + $bubbleMm / 2).'" r="'.($bubbleMm / 2).'" fill="#fff" stroke="#000" stroke-width="0.28"/>';
                }
            }
        }
        return $svg.'</svg>';
    }

    /** @param list<array<string,mixed>> $components */
    private function componentHtml(array $components, int $pageNo): string
    {
        $html = '';
        foreach ($components as $component) {
            if (! is_array($component) || isset($component['page_no']) && (int) $component['page_no'] !== $pageNo) {
                continue;
            }
            $type = (string) ($component['type'] ?? '');
            if (in_array($type, ['roll', 'mcq', 'tf', 'likert', 'custom'], true)) {
                continue;
            }
            $x = (float) ($component['posX'] ?? 0);
            $y = (float) ($component['posY'] ?? 0);
            $w = max(1.0, (float) ($component['w_mm'] ?? 1));
            $h = max(1.0, (float) ($component['h_mm'] ?? 1));
            $typography = (array) ($component['typography'] ?? []);
            $fontSize = max(5.0, min(16.0, (float) ($typography['fontSizePt'] ?? 7.0)));
            $lineHeight = max(1.0, min(1.5, (float) ($typography['lineHeight'] ?? 1.15)));
            $style = 'left:'.$x.'mm;top:'.$y.'mm;width:'.$w.'mm;height:'.$h.'mm;font-size:'.$fontSize.'pt;line-height:'.$lineHeight.';';
            $label = $this->escape((string) ($component['label'] ?? ''));
            $content = $this->escape((string) ($component['content'] ?? $component['text'] ?? ''));
            if ($type === 'header') {
                $title = $this->escape((string) ($component['title'] ?? ''));
                $subtitle = $this->escape((string) ($component['subtitle'] ?? ''));
                $html .= '<div class="text" style="'.$style.'font-weight:800;">'.$title.'<div style="font-size:'.max(5.0, $fontSize * .55).'pt;font-weight:600;line-height:1.1;">'.$subtitle.'</div></div>';
            } elseif ($type === 'instructions') {
                $html .= '<div class="field" style="'.$style.'padding:1mm;"><strong>'.($label !== '' ? $label : 'INSTRUCTIONS').':</strong> '.$content.'</div>';
            } elseif (in_array($type, ['candidate_name', 'birthdate', 'room_seat', 'custom_input'], true)) {
                $padding = 2.0;
                $gap = 1.5;
                $labelHeight = max(2.5, $fontSize * 25.4 / 72 * $lineHeight);
                $inputTop = $y + $padding + $labelHeight + $gap;
                $inputHeight = max(4.0, $h - ($padding * 2) - $labelHeight - $gap);
                $innerWidth = max(4.0, $w - ($padding * 2));
                $html .= '<div class="text" style="left:'.($x + $padding).'mm;top:'.($y + $padding).'mm;width:'.$innerWidth.'mm;height:'.$labelHeight.'mm;font-size:'.$fontSize.'pt;line-height:'.$lineHeight.';font-weight:700;">'.$label.'</div>'
                    .'<div class="field" style="left:'.($x + $padding).'mm;top:'.$inputTop.'mm;width:'.$innerWidth.'mm;height:'.$inputHeight.'mm;"></div>';
            } elseif ($type === 'signature') {
                $title = $this->escape((string) ($component['title'] ?? 'SIGNATURE'));
                $html .= '<div class="text" style="'.$style.'font-size:7pt;font-weight:800;">'.$title.'</div><div class="signature-line" style="left:'.$x.'mm;top:'.($y + max(4.0, $h - 5)).'mm;width:'.$w.'mm;"></div>';
            } elseif ($type === 'custom_text') {
                $html .= '<div class="text" style="'.$style.'">'.$content.'</div>';
            }
        }

        return $html;
    }

    /** @param array<string,mixed> $blocks @param list<array<string,mixed>> $components */
    private function fieldBlocksHtml(array $blocks, array $components, array $insets, float $pageWidthMm, float $pageHeightMm): string
    {
        $componentById = [];
        foreach ($components as $component) {
            if (is_array($component) && isset($component['id'])) {
                $componentById[(string) $component['id']] = $component;
            }
        }
        $html = '';
        foreach ($blocks as $name => $block) {
            if (! is_array($block)) {
                continue;
            }
            $origin = (array) ($block['origin'] ?? [0, 0]);
            $originX = $insets['left'] + $this->pxToMm((int) ($origin[0] ?? 0));
            $originY = $insets['top'] + $this->pxToMm((int) ($origin[1] ?? 0));
            $bubble = (array) ($block['bubbleDimensions'] ?? [0, 0]);
            $bubbleWidth = (int) ($bubble[0] ?? 0);
            $bubbleHeight = (int) ($bubble[1] ?? $bubbleWidth);
            $bubbleMm = $this->pxToMm($bubbleWidth);
            $bubbleGapMm = $this->pxToMm((int) ($block['bubblesGap'] ?? 0));
            $labelGapMm = $this->pxToMm((int) ($block['labelsGap'] ?? 0));
            $values = $this->values($block);
            $labels = [];
            foreach ((array) ($block['fieldLabels'] ?? []) as $declaration) {
                $labels = [...$labels, ...NativeBundle::expandLabel((string) $declaration)];
            }
            $vertical = (string) ($block['direction'] ?? 'horizontal') === 'vertical';
            $componentId = (string) preg_replace('/_col\d+$/', '', (string) $name);
            $component = $componentById[(string) $name] ?? $componentById[$componentId] ?? null;
            $showHeader = ($block['showSectionHeader'] ?? true) === true;
            $displayLabel = trim((string) ($block['displayLabel'] ?? ($component['subjectName'] ?? $component['name'] ?? $name)));
            if ($showHeader && $displayLabel !== '') {
                $headerX = max(0.0, $originX - ($vertical ? 6.4 : 5.0));
                $headerY = max(0.0, $originY - ($vertical ? 14.0 : 8.0));
                $headerW = ($vertical ? 6.4 : 5.0) + $bubbleMm + max(0, count($vertical ? $labels : $values) - 1) * ($vertical ? $labelGapMm : $bubbleGapMm);
                if ($vertical) {
                    $html .= '<div class="text" style="left:'.$headerX.'mm;top:'.$headerY.'mm;width:'.$headerW.'mm;height:4mm;font-size:6pt;font-weight:800;text-align:center;">'.$this->escape($displayLabel).'</div>';
                } else {
                    $html .= '<div class="section-title" style="left:'.$headerX.'mm;top:'.$headerY.'mm;width:'.$headerW.'mm;height:3.5mm;font-size:5pt;line-height:3.5mm;">'.$this->escape($displayLabel).'</div>';
                }
            }
            if ($vertical) {
                foreach ($labels as $col => $label) {
                    foreach ($values as $row => $value) {
                        $x = $originX + $col * $labelGapMm;
                        $y = $originY + $row * $bubbleGapMm;
                        $html .= $this->bubble($x, $y, $bubbleMm);
                        if ($row === 0) {
                            $html .= '<div class="option" style="left:'.($x - .8).'mm;top:'.max(0.0, $y - 4.5).'mm;width:'.($bubbleMm + 1.6).'mm;">'.($vertical && str_starts_with((string) $label, 'APPLICANT_NUMBER_') ? $col + 1 : $this->questionNumber((string) $label, $col)).'</div>';
                        }
                        if ($col === 0) {
                            $html .= '<div class="question" style="left:'.max(0.0, $x - 5).'mm;top:'.($y + $bubbleMm / 2 - 1.8).'mm;width:4mm;">'.$this->escape((string) $value).'</div>';
                        }
                    }
                }
                continue;
            }
            foreach ($values as $option => $value) {
                $x = $originX + $option * $bubbleGapMm;
                $html .= '<div class="option" style="left:'.($x - .8).'mm;top:'.max(0.0, $originY - 4.5).'mm;width:'.($bubbleMm + 1.6).'mm;">'.$this->escape((string) $value).'</div>';
            }
            foreach ($labels as $row => $label) {
                $y = $originY + $row * $labelGapMm;
                $html .= '<div class="question" style="left:'.max(0.0, $originX - 5).'mm;top:'.($y + $bubbleMm / 2 - 1.8).'mm;width:4mm;">'.$this->escape((string) $this->questionNumber((string) $label, $row)).'</div>';
                foreach ($values as $option => $value) {
                    $html .= $this->bubble($originX + $option * $bubbleGapMm, $y, $bubbleMm);
                }
            }
        }

        return $html;
    }

    private function bubble(float $x, float $y, float $diameterMm): string
    {
        return '<div class="bubble" style="left:'.$x.'mm;top:'.$y.'mm;width:'.$diameterMm.'mm;height:'.$diameterMm.'mm;"></div>';
    }

    /** @param array<string,mixed> $markers @return array{top:float,right:float,bottom:float,left:float} */
    private function insets(array $markers): array
    {
        $insets = is_array($markers['insets_mm'] ?? null) ? $markers['insets_mm'] : [];

        return [
            'top' => (float) ($insets['top'] ?? $markers['inset_mm'] ?? $this->pxToMm((int) ($markers['inset'] ?? 0))),
            'right' => (float) ($insets['right'] ?? $markers['inset_mm'] ?? $this->pxToMm((int) ($markers['inset'] ?? 0))),
            'bottom' => (float) ($insets['bottom'] ?? $markers['inset_mm'] ?? $this->pxToMm((int) ($markers['inset'] ?? 0))),
            'left' => (float) ($insets['left'] ?? $markers['inset_mm'] ?? $this->pxToMm((int) ($markers['inset'] ?? 0))),
        ];
    }

    /** @param array<string,mixed> $markers */
    private function markerHtml(array $markers, ?string $markerBytes, array $insets): string
    {
        if ($markerBytes === null || ! is_array($markers['centers'] ?? null)) {
            return '';
        }
        $uri = 'data:image/jpeg;base64,'.base64_encode($markerBytes);
        $sizeMm = $this->pxToMm((int) ($markers['size'] ?? 0));
        $html = '';
        foreach ($markers['centers'] as $center) {
            $x = $insets['left'] + $this->pxToMm((int) ($center[0] ?? 0)) - $sizeMm / 2;
            $y = $insets['top'] + $this->pxToMm((int) ($center[1] ?? 0)) - $sizeMm / 2;
            $html .= '<img class="marker" src="'.$uri.'" style="left:'.$x.'mm;top:'.$y.'mm;width:'.$sizeMm.'mm;height:'.$sizeMm.'mm;"/>';
        }

        return $html;
    }

    /** @return list<string> */
    private function values(array $block): array
    {
        if (is_array($block['bubbleValues'] ?? null)) {
            return array_values(array_map('strval', $block['bubbleValues']));
        }

        return match ((string) ($block['fieldType'] ?? '')) {
            'QTYPE_INT' => ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            'QTYPE_MCQ5' => ['A', 'B', 'C', 'D', 'E'],
            default => ['A', 'B', 'C', 'D'],
        };
    }

    private function questionNumber(string $label, int $fallback): int
    {
        return preg_match('/(\d+)$/', $label, $matches) === 1 ? (int) $matches[1] : $fallback + 1;
    }

    /** @return list<array<string,mixed>> */
    private function pagesOf(array $template): array
    {
        if (is_array($template['pages'] ?? null) && $template['pages'] !== []) {
            return array_values($template['pages']);
        }

        return [[
            'page_no' => 1,
            'pageDimensions' => $template['pageDimensions'] ?? [],
            'fieldBlocks' => $template['fieldBlocks'] ?? [],
            'markers' => $template['markers'] ?? [],
        ]];
    }

    private function pxToMm(int $pixels): float
    {
        return round($pixels * 25.4 / self::PPI, 4);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
