<?php

namespace App\Assessment\Omr\Canonical;

use InvalidArgumentException;

use function array_values;
use function config;
use function count;
use function is_array;
use function is_numeric;
use function min;
use function round;
use function sort;

/**
 * Deterministic physical/page geometry for canonical OMR authoring.
 *
 * Canonical pixels are derived from physical millimetres at exactly 300 PPI.
 * Source geometry is normalized from the actual rendered page dimensions; no
 * renderer-labelled DPI ratio is used.
 */
final class OmrPageDimensionMath
{
    public const ALIGN_TOP_LEFT = 'top_left';

    public const ALIGN_TOP_CENTER = 'top_center';

    public const ALIGN_TOP_RIGHT = 'top_right';

    public const ALIGN_CENTER_LEFT = 'center_left';

    public const ALIGN_CENTER = 'center';

    public const ALIGN_CENTER_RIGHT = 'center_right';

    public const ALIGN_BOTTOM_LEFT = 'bottom_left';

    public const ALIGN_BOTTOM_CENTER = 'bottom_center';

    public const ALIGN_BOTTOM_RIGHT = 'bottom_right';

    public const ALIGNMENTS = [
        self::ALIGN_TOP_LEFT,
        self::ALIGN_TOP_CENTER,
        self::ALIGN_TOP_RIGHT,
        self::ALIGN_CENTER_LEFT,
        self::ALIGN_CENTER,
        self::ALIGN_CENTER_RIGHT,
        self::ALIGN_BOTTOM_LEFT,
        self::ALIGN_BOTTOM_CENTER,
        self::ALIGN_BOTTOM_RIGHT,
    ];

    public static function pixelsFromMillimetres(float|int $millimetres, ?float $ppi = null): int
    {
        self::assertFiniteNonNegative($millimetres, 'millimetres');

        return (int) round((float) $millimetres * ($ppi ?? self::ppi()) / 25.4);
    }

    public static function millimetresFromPixels(float|int $pixels, ?float $ppi = null): float
    {
        self::assertFiniteNonNegative($pixels, 'pixels');

        return (float) $pixels * 25.4 / ($ppi ?? self::ppi());
    }

    /**
     * @param  array{width:int|float,height:int|float}  $oldPage
     * @param  array{width:int|float,height:int|float}  $newPage
     * @param  array{x:int|float,y:int|float,w:int|float,h:int|float}  $rect
     * @return array{x:int,y:int,w:int,h:int}
     */
    public static function normalizedRect(array $rect, array $oldPage, array $newPage): array
    {
        $oldWidth = self::positive($oldPage['width'] ?? null, 'old page width');
        $oldHeight = self::positive($oldPage['height'] ?? null, 'old page height');
        $newWidth = self::positive($newPage['width'] ?? null, 'new page width');
        $newHeight = self::positive($newPage['height'] ?? null, 'new page height');

        $x = self::nonNegative($rect['x'] ?? null, 'rect x');
        $y = self::nonNegative($rect['y'] ?? null, 'rect y');
        $w = self::positive($rect['w'] ?? null, 'rect width');
        $h = self::positive($rect['h'] ?? null, 'rect height');

        return [
            'x' => (int) round($x / $oldWidth * $newWidth),
            'y' => (int) round($y / $oldHeight * $newHeight),
            'w' => (int) round($w / $oldWidth * $newWidth),
            'h' => (int) round($h / $oldHeight * $newHeight),
        ];
    }

    /**
     * Transform an OMRChecker fieldBlocks map from actual source pixels to
     * canonical pixels while preserving its semantic shape.
     *
     * @param  array<string, mixed>  $fieldBlocks
     * @param  array{width:int|float,height:int|float}  $oldPage
     * @param  array{width:int|float,height:int|float}  $newPage
     * @return array<string, mixed>
     */
    public static function transformFieldBlocks(array $fieldBlocks, array $oldPage, array $newPage): array
    {
        $oldWidth = self::positive($oldPage['width'] ?? null, 'old page width');
        $oldHeight = self::positive($oldPage['height'] ?? null, 'old page height');
        $newWidth = self::positive($newPage['width'] ?? null, 'new page width');
        $newHeight = self::positive($newPage['height'] ?? null, 'new page height');
        $scaleX = $newWidth / $oldWidth;
        $scaleY = $newHeight / $oldHeight;

        $transformed = $fieldBlocks;
        foreach ($transformed as $name => $block) {
            if (! is_array($block)) {
                continue;
            }

            if (isset($block['origin']) && is_array($block['origin']) && count($block['origin']) === 2) {
                $block['origin'] = [
                    (int) round((float) $block['origin'][0] * $scaleX),
                    (int) round((float) $block['origin'][1] * $scaleY),
                ];
            }

            $sourceBubbleDimensions = $block['bubbleDimensions'] ?? null;
            if (isset($block['bubbleDimensions']) && is_array($block['bubbleDimensions']) && count($block['bubbleDimensions']) === 2) {
                $block['bubbleDimensions'] = [
                    (int) round((float) $block['bubbleDimensions'][0] * $scaleX),
                    (int) round((float) $block['bubbleDimensions'][1] * $scaleY),
                ];
            }
            if (array_key_exists('bubblePositions', $block)) {
                if (! is_array($block['bubblePositions']) || ! array_is_list($block['bubblePositions'])
                    || ! is_array($sourceBubbleDimensions) || count($sourceBubbleDimensions) !== 2
                    || (int) $sourceBubbleDimensions[0] <= 0 || (int) $sourceBubbleDimensions[1] <= 0) {
                    throw new InvalidArgumentException('Explicit bubble positions require ordered rows and positive bubble dimensions.');
                }
                $bubbleDimensions = $block['bubbleDimensions'];
                foreach ($block['bubblePositions'] as $rowIndex => $row) {
                    if (! is_array($row) || ! array_is_list($row)) {
                        throw new InvalidArgumentException('Explicit bubble positions must be ordered option rows.');
                    }
                    foreach ($row as $optionIndex => $position) {
                        if (! is_array($position) || ! array_is_list($position) || count($position) !== 2
                            || ! is_int($position[0] ?? null) || ! is_int($position[1] ?? null)) {
                            throw new InvalidArgumentException('Explicit bubble positions must contain integer pixel pairs.');
                        }
                        if ($position[0] < 0 || $position[1] < 0
                            || $oldWidth < $position[0] + (int) $sourceBubbleDimensions[0]
                            || $oldHeight < $position[1] + (int) $sourceBubbleDimensions[1]) {
                            throw new InvalidArgumentException('Explicit bubble positions lie outside the source page.');
                        }
                        $x = (int) round($position[0] * $scaleX);
                        $y = (int) round($position[1] * $scaleY);
                        if ($x < 0 || $y < 0
                            || $x + (int) $bubbleDimensions[0] > $newWidth
                            || $y + (int) $bubbleDimensions[1] > $newHeight) {
                            throw new InvalidArgumentException('Transformed explicit bubble positions lie outside the target page.');
                        }
                        $block['bubblePositions'][$rowIndex][$optionIndex] = [$x, $y];
                    }
                }
            }

            $direction = $block['direction'] ?? 'horizontal';
            $bubbleScale = $direction === 'vertical' ? $scaleY : $scaleX;
            $labelScale = $direction === 'vertical' ? $scaleX : $scaleY;
            foreach (['bubblesGap', 'bubbleGap'] as $key) {
                if (isset($block[$key]) && is_numeric($block[$key])) {
                    $block[$key] = (int) round((float) $block[$key] * $bubbleScale);
                }
            }
            foreach (['labelsGap', 'labelGap'] as $key) {
                if (isset($block[$key]) && is_numeric($block[$key])) {
                    $block[$key] = (int) round((float) $block[$key] * $labelScale);
                }
            }

            $transformed[$name] = $block;
        }

        return $transformed;
    }

    /**
     * @param  array{width:int|float,height:int|float}  $source
     * @param  array{width:int|float,height:int|float}  $canvas
     * @param  array{left?:int|float,right?:int|float,top?:int|float,bottom?:int|float}  $padding
     * @return array{scale:float,width:int,height:int,effective_dpi:float}
     */
    public static function scaleForPlacement(
        array $source,
        array $canvas,
        array $padding = [],
        string $sourceKind = 'default',
        ?float $sourceDpi = null,
    ): array {
        $sourceWidth = self::positive($source['width'] ?? null, 'source width');
        $sourceHeight = self::positive($source['height'] ?? null, 'source height');
        $canvasWidth = self::positive($canvas['width'] ?? null, 'canvas width');
        $canvasHeight = self::positive($canvas['height'] ?? null, 'canvas height');
        $left = self::nonNegative($padding['left'] ?? 0, 'left padding');
        $right = self::nonNegative($padding['right'] ?? 0, 'right padding');
        $top = self::nonNegative($padding['top'] ?? 0, 'top padding');
        $bottom = self::nonNegative($padding['bottom'] ?? 0, 'bottom padding');

        $availableWidth = $canvasWidth - $left - $right;
        $availableHeight = $canvasHeight - $top - $bottom;
        if ($availableWidth <= 0 || $availableHeight <= 0) {
            throw new InvalidArgumentException('Placement padding leaves no usable canvas.');
        }

        $scale = min($availableWidth / $sourceWidth, $availableHeight / $sourceHeight);
        $dpi = $sourceDpi ?? self::ppi();
        self::positive($dpi, 'source DPI');
        $effectiveDpi = $dpi * $scale;
        $floor = self::sourceDpiFloor($sourceKind);

        if ($scale > 1.0 && $effectiveDpi < $floor) {
            throw new InvalidArgumentException(sprintf(
                'insufficient_resolution: effective DPI %.3f is below %.3f for %s.',
                $effectiveDpi,
                $floor,
                $sourceKind,
            ));
        }

        return [
            'scale' => $scale,
            'width' => (int) round($sourceWidth * $scale),
            'height' => (int) round($sourceHeight * $scale),
            'effective_dpi' => $effectiveDpi,
        ];
    }

    /**
     * @param  array{width:int|float,height:int|float}  $source
     * @param  array{width:int|float,height:int|float}  $canvas
     * @param  array{left?:int|float,right?:int|float,top?:int|float,bottom?:int|float}  $padding
     * @return array{scale:float,x:int,y:int,width:int,height:int,effective_dpi:float,alignment:string}
     */
    public static function place(
        array $source,
        array $canvas,
        array $padding = [],
        string $alignment = self::ALIGN_CENTER,
        int|float $offsetX = 0,
        int|float $offsetY = 0,
        string $sourceKind = 'default',
        ?float $sourceDpi = null,
    ): array {
        if (! in_array($alignment, self::ALIGNMENTS, true)) {
            throw new InvalidArgumentException("Unsupported 9-point alignment: {$alignment}.");
        }

        $placement = self::scaleForPlacement($source, $canvas, $padding, $sourceKind, $sourceDpi);
        $left = self::nonNegative($padding['left'] ?? 0, 'left padding');
        $right = self::nonNegative($padding['right'] ?? 0, 'right padding');
        $top = self::nonNegative($padding['top'] ?? 0, 'top padding');
        $bottom = self::nonNegative($padding['bottom'] ?? 0, 'bottom padding');
        $availableWidth = (float) $canvas['width'] - $left - $right;
        $availableHeight = (float) $canvas['height'] - $top - $bottom;
        [$anchorX, $anchorY] = self::alignmentAnchor($alignment);
        $freeWidth = $availableWidth - $placement['width'];
        $freeHeight = $availableHeight - $placement['height'];
        $x = $left + $freeWidth * $anchorX + (float) $offsetX;
        $y = $top + $freeHeight * $anchorY + (float) $offsetY;

        if ($x < $left || $y < $top || $x + $placement['width'] > $canvas['width'] - $right || $y + $placement['height'] > $canvas['height'] - $bottom) {
            throw new InvalidArgumentException('Placement offset would move source outside the white-padded canvas.');
        }

        return [
            'scale' => $placement['scale'],
            'x' => (int) round($x),
            'y' => (int) round($y),
            'width' => $placement['width'],
            'height' => $placement['height'],
            'effective_dpi' => $placement['effective_dpi'],
            'alignment' => $alignment,
        ];
    }

    public static function effectiveDpi(int|float $pixelWidth, int|float $pixelHeight, int|float $physicalWidthMm, int|float $physicalHeightMm): float
    {
        $width = self::positive($physicalWidthMm, 'physical width');
        $height = self::positive($physicalHeightMm, 'physical height');
        self::positive($pixelWidth, 'pixel width');
        self::positive($pixelHeight, 'pixel height');

        return min((float) $pixelWidth / ($width / 25.4), (float) $pixelHeight / ($height / 25.4));
    }

    public static function assertPixelBudget(int|float $width, int|float $height, ?int $budget = null): void
    {
        $width = self::positive($width, 'pixel width');
        $height = self::positive($height, 'pixel height');
        $budget ??= (int) config('omr.canonical.limits.max_pixels_per_page', 20_000_000);
        if ($width * $height > $budget) {
            throw new InvalidArgumentException(sprintf('pixel_budget_exceeded: %d pixels exceeds %d.', $width * $height, $budget));
        }
    }

    public static function assertPaperBounds(float|int $widthMm, float|int $heightMm): void
    {
        $width = self::positive($widthMm, 'paper width');
        $height = self::positive($heightMm, 'paper height');
        $min = array_values((array) config('omr.canonical.paper_mm.min', [127.0, 178.0]));
        $max = array_values((array) config('omr.canonical.paper_mm.max', [216.0, 356.0]));
        sort($min);
        sort($max);
        $dimensions = [$width, $height];
        sort($dimensions);

        if ($dimensions[0] < $min[0] || $dimensions[1] < $min[1] || $dimensions[0] > $max[0] || $dimensions[1] > $max[1]) {
            throw new InvalidArgumentException(sprintf('paper_out_of_bounds: %.3f×%.3f mm.', $width, $height));
        }
    }

    private static function ppi(): float
    {
        return (float) config('omr.canonical.ppi', 300);
    }

    private static function sourceDpiFloor(string $sourceKind): float
    {
        return (float) config(
            'omr.canonical.source_dpi_floor.'.$sourceKind,
            config('omr.canonical.source_dpi_floor.default', 150),
        );
    }

    /** @return array{0:float,1:float} */
    private static function alignmentAnchor(string $alignment): array
    {
        return match ($alignment) {
            self::ALIGN_TOP_LEFT => [0.0, 0.0],
            self::ALIGN_TOP_CENTER => [0.5, 0.0],
            self::ALIGN_TOP_RIGHT => [1.0, 0.0],
            self::ALIGN_CENTER_LEFT => [0.0, 0.5],
            self::ALIGN_CENTER => [0.5, 0.5],
            self::ALIGN_CENTER_RIGHT => [1.0, 0.5],
            self::ALIGN_BOTTOM_LEFT => [0.0, 1.0],
            self::ALIGN_BOTTOM_CENTER => [0.5, 1.0],
            self::ALIGN_BOTTOM_RIGHT => [1.0, 1.0],
        };
    }

    private static function positive(mixed $value, string $name): float
    {
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value <= 0) {
            throw new InvalidArgumentException("{$name} must be positive.");
        }

        return (float) $value;
    }

    private static function nonNegative(mixed $value, string $name): float
    {
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0) {
            throw new InvalidArgumentException("{$name} must be non-negative.");
        }

        return (float) $value;
    }

    private static function assertFiniteNonNegative(mixed $value, string $name): void
    {
        self::nonNegative($value, $name);
    }
}
