<?php

namespace App\Assessment\Omr\Canonical;

use App\Assessment\Omr\NativeBundle;

use function array_keys;
use function count;
use function is_array;
use function max;
use function round;

/**
 * Shared Canonical mapping guide geometry helper (OpenSpec omr-evidence-visual-review).
 *
 * Converts native OMRChecker template field blocks and paper insets into
 * physical millimetre mapping guides and bubble overlays for both fresh
 * diagnostic preview and persisted authoring workflow projections.
 */
final class OmrCanonicalMappingGuideGeometry
{
    /**
     * Resolve native template field blocks and insets into physical mm mapping guides.
     *
     * @param  array<string, mixed>  $template
     * @param  array<string, mixed>  $insetsPx
     * @return list<array{id:string,x_mm:float,y_mm:float,w_mm:float,h_mm:float,bubbles:list<array{x_mm:float,y_mm:float,w_mm:float,h_mm:float}>}>
     */
    public static function resolve(array $template, array $insetsPx): array
    {
        $guides = [];
        $toMm = static fn (int|float $pixels): float => round($pixels * 25.4 / 300, 3);
        $fieldBlocks = (array) ($template['pages'][0]['fieldBlocks'] ?? $template['fieldBlocks'] ?? []);

        foreach ($fieldBlocks as $id => $block) {
            if (! is_array($block)) {
                continue;
            }
            $origin = (array) ($block['origin'] ?? [0, 0]);
            $bubble = (array) ($block['bubbleDimensions'] ?? [0, 0]);
            $labels = [];
            foreach ((array) ($block['fieldLabels'] ?? []) as $fieldLabel) {
                $labels = [...$labels, ...NativeBundle::expandLabel((string) $fieldLabel)];
            }
            $values = is_array($block['bubbleValues'] ?? null)
                ? count($block['bubbleValues'])
                : match ((string) ($block['fieldType'] ?? '')) {
                    'QTYPE_INT' => 10,
                    'QTYPE_MCQ5' => 5,
                    'QTYPE_TF' => 2,
                    default => 4,
                };
            $vertical = (string) ($block['direction'] ?? 'horizontal') === 'vertical';
            $bubblesGap = (int) ($block['bubblesGap'] ?? 0);
            $labelsGap = (int) ($block['labelsGap'] ?? 0);
            $explicitPositions = array_key_exists('bubblePositions', $block);
            $positions = $block['bubblePositions'] ?? null;
            if ($explicitPositions && ! is_array($positions)) {
                throw new \RuntimeException('canonical_explicit_bubble_position_invalid');
            }
            $width = $vertical
                ? (int) ($bubble[0] ?? 0) + max(0, count($labels) - 1) * $labelsGap
                : (int) ($bubble[0] ?? 0) + max(0, $values - 1) * $bubblesGap;
            $height = $vertical
                ? (int) ($bubble[1] ?? 0) + max(0, $values - 1) * $bubblesGap
                : (int) ($bubble[1] ?? 0) + max(0, count($labels) - 1) * $labelsGap;
            $paperOriginX = (int) ($origin[0] ?? 0) + (int) ($insetsPx['left'] ?? 0);
            $paperOriginY = (int) ($origin[1] ?? 0) + (int) ($insetsPx['top'] ?? 0);
            $bubbleGuides = [];
            $minX = PHP_INT_MAX;
            $minY = PHP_INT_MAX;
            $maxX = PHP_INT_MIN;
            $maxY = PHP_INT_MIN;
            foreach (array_keys($labels) as $labelIndex) {
                for ($valueIndex = 0; $valueIndex < $values; $valueIndex++) {
                    if ($explicitPositions) {
                        $position = $positions[$labelIndex][$valueIndex] ?? null;
                        if (! is_array($position) || count($position) !== 2 || ! is_int($position[0] ?? null) || ! is_int($position[1] ?? null)) {
                            throw new \RuntimeException('canonical_explicit_bubble_position_invalid');
                        }
                        $x = (int) ($insetsPx['left'] ?? 0) + $position[0];
                        $y = (int) ($insetsPx['top'] ?? 0) + $position[1];
                    } else {
                        $x = $paperOriginX + ($vertical ? $labelIndex * $labelsGap : $valueIndex * $bubblesGap);
                        $y = $paperOriginY + ($vertical ? $valueIndex * $bubblesGap : $labelIndex * $labelsGap);
                    }
                    $minX = min($minX, $x);
                    $minY = min($minY, $y);
                    $maxX = max($maxX, $x + (int) ($bubble[0] ?? 0));
                    $maxY = max($maxY, $y + (int) ($bubble[1] ?? 0));
                    $bubbleGuides[] = [
                        'x_mm' => $toMm($x),
                        'y_mm' => $toMm($y),
                        'w_mm' => $toMm((int) ($bubble[0] ?? 0)),
                        'h_mm' => $toMm((int) ($bubble[1] ?? 0)),
                    ];
                }
            }
            if ($explicitPositions && $bubbleGuides !== []) {
                $paperOriginX = $minX;
                $paperOriginY = $minY;
                $width = $maxX - $minX;
                $height = $maxY - $minY;
            }
            $guides[] = [
                'id' => (string) $id,
                'x_mm' => $toMm($paperOriginX),
                'y_mm' => $toMm($paperOriginY),
                'w_mm' => $toMm($width),
                'h_mm' => $toMm($height),
                'bubbles' => $bubbleGuides,
            ];
        }

        return $guides;
    }
}
