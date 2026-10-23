<?php

namespace App\Assessment\Omr\Diagnostics;

use function imageellipse;
use function imagefilledellipse;
use function imagesx;
use function imagesy;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function min;
use function round;

/**
 * Shared canonical bubble shading engine (OpenSpec 35.18/35.19).
 *
 * Sourced strictly from the review manifest's canonical `fields` geometry.
 * Owns manifest field indexing, typed validation and bounds checks, paper
 * inset conversion, and deterministic centered human-like proportional solid fills.
 */
final class OmrManifestBubbleShader
{
    public const RENDERER_VERSION = 'v4';

    public const FILL_RATIO = 0.88;

    public const PRIMITIVE_DARK = 'dark';

    public const PRIMITIVE_FAINT = 'faint';

    public const PRIMITIVE_PARTIAL_SMALL = 'partial_small';

    public const PRIMITIVE_OFF_CENTER = 'off_center';

    public const PRIMITIVE_OVERFILLED = 'overfilled';

    public const PRIMITIVE_CHECK_CROSS = 'check_cross';

    public const PRIMITIVE_ERASED = 'erased';

    public const PRIMITIVE_SMUDGED = 'smudged';

    public const PRIMITIVE_STRAY = 'stray';

    public const PRIMITIVE_DOUBLE = 'double';

    public const PRIMITIVE_TRIPLE = 'triple';

    public const PRIMITIVE_BETWEEN_BUBBLE = 'between_bubble';

    /** @var list<string> */
    public const PRIMITIVES = [
        self::PRIMITIVE_DARK,
        self::PRIMITIVE_FAINT,
        self::PRIMITIVE_PARTIAL_SMALL,
        self::PRIMITIVE_OFF_CENTER,
        self::PRIMITIVE_OVERFILLED,
        self::PRIMITIVE_CHECK_CROSS,
        self::PRIMITIVE_ERASED,
        self::PRIMITIVE_SMUDGED,
        self::PRIMITIVE_STRAY,
        self::PRIMITIVE_DOUBLE,
        self::PRIMITIVE_TRIPLE,
        self::PRIMITIVE_BETWEEN_BUBBLE,
    ];

    /**
     * Build and validate the single canonical geometry index from the review
     * manifest fields array.
     *
     * @param  array<string, mixed>|list<array<string, mixed>>  $reviewManifestOrFields  either the full review manifest or its fields list
     * @return array<int, array<string, array<string, array{x: int, y: int, w: int, h: int}>>>
     */
    public function index(array $reviewManifestOrFields): array
    {
        $fields = isset($reviewManifestOrFields['fields']) && is_array($reviewManifestOrFields['fields'])
            ? $reviewManifestOrFields['fields']
            : $reviewManifestOrFields;

        if (! is_array($fields)) {
            throw new OmrDiagnosticTargetException(
                'challenge_geometry_invalid',
                'The review manifest declares no valid fields array.'
            );
        }

        $indexMap = [];
        foreach ($fields as $i => $field) {
            if (! is_array($field)) {
                throw new OmrDiagnosticTargetException(
                    'challenge_geometry_invalid',
                    "Manifest field entry at index {$i} is not an array."
                );
            }

            if (! isset($field['page']) || ! is_int($field['page']) || $field['page'] < 1) {
                throw new OmrDiagnosticTargetException(
                    'challenge_geometry_invalid',
                    "Manifest field entry at index {$i} declares an invalid page."
                );
            }
            $pageNo = $field['page'];

            if (! isset($field['label']) || ! is_string($field['label']) || $field['label'] === '') {
                throw new OmrDiagnosticTargetException(
                    'challenge_geometry_invalid',
                    "Manifest field entry at index {$i} on page {$pageNo} declares an invalid label."
                );
            }
            $label = $field['label'];

            if (! isset($field['option']) || ! is_string($field['option']) || $field['option'] === '') {
                throw new OmrDiagnosticTargetException(
                    'challenge_geometry_invalid',
                    "Manifest field entry at index {$i} on page {$pageNo} declares an invalid option."
                );
            }
            $option = $field['option'];

            if (! isset($field['pixel']) || ! is_array($field['pixel'])) {
                throw new OmrDiagnosticTargetException(
                    'challenge_geometry_invalid',
                    "Manifest field '{$label}' option '{$option}' on page {$pageNo} declares no pixel geometry."
                );
            }
            $pixel = $field['pixel'];

            if (! isset($pixel['x'], $pixel['y'], $pixel['w'], $pixel['h'])
                || ! is_numeric($pixel['x']) || ! is_numeric($pixel['y'])
                || ! is_numeric($pixel['w']) || ! is_numeric($pixel['h'])) {
                throw new OmrDiagnosticTargetException(
                    'challenge_geometry_invalid',
                    "Manifest field '{$label}' option '{$option}' on page {$pageNo} declares non-numeric pixel geometry."
                );
            }

            $x = (int) $pixel['x'];
            $y = (int) $pixel['y'];
            $w = (int) $pixel['w'];
            $h = (int) $pixel['h'];

            if ($x < 0 || $y < 0 || $w <= 0 || $h <= 0 || min($w, $h) < 6) {
                throw new OmrDiagnosticTargetException(
                    'challenge_geometry_invalid',
                    "Manifest field '{$label}' option '{$option}' on page {$pageNo} declares degenerate or negative pixel geometry: x={$x}, y={$y}, w={$w}, h={$h}."
                );
            }

            if (isset($indexMap[$pageNo][$label][$option])) {
                throw new OmrDiagnosticTargetException(
                    'challenge_geometry_duplicate',
                    "Duplicate manifest field geometry for page {$pageNo}, label '{$label}', option '{$option}'."
                );
            }

            $indexMap[$pageNo][$label][$option] = [
                'x' => $x,
                'y' => $y,
                'w' => $w,
                'h' => $h,
            ];
        }

        return $indexMap;
    }

    /**
     * Resolve single bubble geometry from an index.
     *
     * @param  array<int, array<string, array<string, array{x: int, y: int, w: int, h: int}>>>  $indexMap
     * @return array{x: int, y: int, w: int, h: int}
     */
    public function resolve(array $indexMap, int $pageNo, string $label, string $option, bool $isCalibration = false): array
    {
        $geometry = $indexMap[$pageNo][$label][$option] ?? null;
        if ($geometry === null) {
            $context = $isCalibration ? 'calibration ' : '';
            throw new OmrDiagnosticTargetException(
                'challenge_geometry_missing',
                "Manifest geometry missing for page {$pageNo}, {$context}label '{$label}', option '{$option}'."
            );
        }

        return $geometry;
    }

    /**
     * Shade a resolved bubble onto a GD page image with paper coordinate conversion,
     * bounds validation against the image dimensions, and centered proportional fill.
     *
     * @param  array{x: int, y: int, w: int, h: int}  $geometry
     * @param  array<string, int>  $insetsPx
     */
    public function shade(
        \GdImage $image,
        array $geometry,
        int $color,
        array $insetsPx,
        string $label,
        string $option,
        int $pageNo,
    ): void {
        $paperX = $geometry['x'] + (int) ($insetsPx['left'] ?? 0);
        $paperY = $geometry['y'] + (int) ($insetsPx['top'] ?? 0);
        $boxW = $geometry['w'];
        $boxH = $geometry['h'];

        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        if ($paperX < 0 || $paperY < 0 || ($paperX + $boxW) > $imageWidth || ($paperY + $boxH) > $imageHeight) {
            throw new OmrDiagnosticTargetException(
                'challenge_geometry_out_of_bounds',
                "Manifest field '{$label}' option '{$option}' on page {$pageNo} resolved to out-of-bounds paper coordinates: x={$paperX}, y={$paperY}, w={$boxW}, h={$boxH} for image size {$imageWidth}x{$imageHeight}."
            );
        }

        $fillW = max(1, (int) round($boxW * self::FILL_RATIO));
        $fillH = max(1, (int) round($boxH * self::FILL_RATIO));
        $cx = (int) round($paperX + $boxW / 2.0);
        $cy = (int) round($paperY + $boxH / 2.0);

        imagefilledellipse($image, $cx, $cy, $fillW, $fillH, $color);
        imageellipse($image, $cx, $cy, $fillW, $fillH, $color);
    }

    /**
     * Return deterministic GD-independent drawing operations for one mark.
     *
     * Coordinates are in paper image space (before insets). Every operation
     * is clipped to the supplied bubble geometry, except between-bubble marks,
     * which are clipped by the caller's image bounds after joining two
     * canonical bubbles.
     *
     * @param  array{x: int|float, y: int|float, w: int|float, h: int|float}  $geometry
     * @param  array<string, mixed>  $parameters
     * @return array{primitive: string, geometry: array{x:int,y:int,w:int,h:int}, operations: list<array<string,mixed>>}
     */
    public function primitive(string $primitive, array $geometry, array $parameters = []): array
    {
        $geometry = $this->normalizedGeometry($geometry);
        $primitive = $this->normalizePrimitive($primitive);
        $x = $geometry['x'];
        $y = $geometry['y'];
        $w = $geometry['w'];
        $h = $geometry['h'];
        $cx = (int) round($x + ($w / 2));
        $cy = (int) round($y + ($h / 2));
        $small = max(1, (int) round(min($w, $h) * 0.18));
        $ellipse = static fn (int $centerX, int $centerY, int $width, int $height, bool $filled = true, ?int $alpha = null): array => [
            'type' => 'ellipse',
            'cx' => $centerX,
            'cy' => $centerY,
            'w' => max(1, $width),
            'h' => max(1, $height),
            'filled' => $filled,
            'alpha' => $alpha,
        ];
        $line = static fn (int $x1, int $y1, int $x2, int $y2): array => [
            'type' => 'line',
            'x1' => $x1,
            'y1' => $y1,
            'x2' => $x2,
        ];
        $operations = [];

        switch ($primitive) {
            case self::PRIMITIVE_DARK:
                $operations[] = $ellipse($cx, $cy, (int) round($w * (float) ($parameters['fill_ratio'] ?? self::FILL_RATIO)), (int) round($h * (float) ($parameters['fill_ratio'] ?? self::FILL_RATIO)));
                break;
            case self::PRIMITIVE_FAINT:
                $operations[] = $ellipse($cx, $cy, (int) round($w * (float) ($parameters['fill_ratio'] ?? 0.64)), (int) round($h * (float) ($parameters['fill_ratio'] ?? 0.64)), true, (int) ($parameters['alpha'] ?? 100));
                break;
            case self::PRIMITIVE_PARTIAL_SMALL:
                $partialW = (int) round($w * (float) ($parameters['fill_ratio'] ?? 0.48));
                $partialH = (int) round($h * (float) ($parameters['fill_ratio'] ?? 0.48));
                $operations[] = $ellipse($cx, $cy, $partialW, $partialH, true, isset($parameters['alpha']) ? (int) $parameters['alpha'] : null);
                $operations[] = [
                    'type' => 'arc',
                    'cx' => $cx,
                    'cy' => $cy,
                    'w' => min($w, max(1, (int) round($w * 0.8))),
                    'h' => min($h, max(1, (int) round($h * 0.8))),
                    'start' => 180,
                    'end' => 330,
                    'alpha' => isset($parameters['alpha']) ? (int) $parameters['alpha'] : null,
                ];
                break;
            case self::PRIMITIVE_OFF_CENTER:
                $dx = (int) round($w * (float) ($parameters['dx_ratio'] ?? 0.18));
                $dy = (int) round($h * (float) ($parameters['dy_ratio'] ?? -0.12));
                $markW = max(1, min($w, (int) round($w * (float) ($parameters['fill_ratio'] ?? 0.7))));
                $markH = max(1, min($h, (int) round($h * (float) ($parameters['fill_ratio'] ?? 0.7))));
                $offsetCx = $this->clamp($cx + $dx, (int) ceil($x + ($markW / 2)), (int) floor($x + $w - ($markW / 2)));
                $offsetCy = $this->clamp($cy + $dy, (int) ceil($y + ($markH / 2)), (int) floor($y + $h - ($markH / 2)));
                $operations[] = $ellipse($offsetCx, $offsetCy, $markW, $markH);
                break;
            case self::PRIMITIVE_OVERFILLED:
                $operations[] = $ellipse($cx, $cy, min($w, (int) round($w * (float) ($parameters['fill_ratio'] ?? 0.98))), min($h, (int) round($h * (float) ($parameters['fill_ratio'] ?? 0.98))));
                break;
            case self::PRIMITIVE_CHECK_CROSS:
                $inset = max(1, (int) round(min($w, $h) * (float) ($parameters['inset_ratio'] ?? 0.18)));
                $operations[] = $line((int) $x + $inset, (int) $y + $inset, (int) ($x + $w) - $inset, (int) ($y + $h) - $inset);
                $operations[] = $line((int) ($x + $w) - $inset, (int) $y + $inset, (int) $x + $inset, (int) ($y + $h) - $inset);
                break;
            case self::PRIMITIVE_ERASED:
                $operations[] = $ellipse($cx, $cy, (int) round($w * (float) ($parameters['fill_ratio'] ?? 0.58)), (int) round($h * (float) ($parameters['fill_ratio'] ?? 0.58)), true, (int) ($parameters['alpha'] ?? 150));
                $operations[] = [
                    'type' => 'arc',
                    'cx' => $cx,
                    'cy' => $cy,
                    'w' => min($w, (int) round($w * 0.86)),
                    'h' => min($h, (int) round($h * 0.86)),
                    'start' => 20,
                    'end' => 150,
                    'alpha' => (int) ($parameters['residual_alpha'] ?? 110),
                ];
                break;
            case self::PRIMITIVE_SMUDGED:
                $count = max(2, min(9, (int) ($parameters['blob_count'] ?? 5)));
                $spread = (float) ($parameters['spread_ratio'] ?? 0.18);
                for ($index = 0; $index < $count; $index++) {
                    $phase = $index * 2.399963229728653;
                    $blobCx = $this->clamp(
                        (int) round($cx + cos($phase) * $w * $spread),
                        (int) ceil($x + ($small / 2)),
                        (int) floor($x + $w - ($small / 2)),
                    );
                    $blobCy = $this->clamp(
                        (int) round($cy + sin($phase) * $h * $spread),
                        (int) ceil($y + ($small / 2)),
                        (int) floor($y + $h - ($small / 2)),
                    );
                    $operations[] = $ellipse($blobCx, $blobCy, $small, $small, true, (int) ($parameters['alpha'] ?? 100));
                }
                break;
            case self::PRIMITIVE_STRAY:
                $dotW = max(1, min($w, (int) round($w * (float) ($parameters['dot_ratio'] ?? 0.18))));
                $dotH = max(1, min($h, (int) round($h * (float) ($parameters['dot_ratio'] ?? 0.18))));
                $offset = (float) ($parameters['offset_ratio'] ?? 0.28);
                $dotCx = $this->clamp((int) round($cx + $w * $offset), (int) ceil($x + ($dotW / 2)), (int) floor($x + $w - ($dotW / 2)));
                $dotCy = $this->clamp((int) round($cy - $h * $offset), (int) ceil($y + ($dotH / 2)), (int) floor($y + $h - ($dotH / 2)));
                $operations[] = $ellipse($dotCx, $dotCy, $dotW, $dotH);
                break;
            case self::PRIMITIVE_DOUBLE:
            case self::PRIMITIVE_TRIPLE:
                $marks = $primitive === self::PRIMITIVE_DOUBLE ? 2 : 3;
                $step = max(1, (int) round($w * 0.2));
                for ($index = 0; $index < $marks; $index++) {
                    $offset = ($index - (($marks - 1) / 2)) * $step;
                    $operations[] = $ellipse((int) round($cx + $offset), $cy, (int) round($w * 0.48), (int) round($h * 0.48));
                }
                break;
            case self::PRIMITIVE_BETWEEN_BUBBLE:
                if (! is_array($parameters['neighbor'] ?? null)) {
                    throw new OmrDiagnosticTargetException('challenge_geometry_invalid', 'between_bubble requires a neighboring canonical bubble geometry.');
                }
                $neighbor = $this->normalizedGeometry($parameters['neighbor']);
                $neighborCx = (int) round($neighbor['x'] + ($neighbor['w'] / 2));
                $neighborCy = (int) round($neighbor['y'] + ($neighbor['h'] / 2));
                $betweenCx = (int) round(($cx + $neighborCx) / 2);
                $betweenCy = (int) round(($cy + $neighborCy) / 2);
                $operations[] = $ellipse($betweenCx, $betweenCy, max(1, (int) round(min($w, $neighbor['w']) * 0.3)), max(1, (int) round(min($h, $neighbor['h']) * 0.3)), true, isset($parameters['alpha']) ? (int) $parameters['alpha'] : null);
                break;
        }

        return [
            'primitive' => $primitive,
            'geometry' => $geometry,
            'operations' => $operations,
        ];
    }

    /**
     * Draw one deterministic primitive. The argument order accepts both
     * `geometry, primitive` and `primitive, geometry` to keep the primitive
     * helper ergonomic without introducing another geometry representation.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function shadePrimitive(
        \GdImage $image,
        array|string $geometryOrPrimitive,
        array|string $primitiveOrGeometry,
        int $color,
        array $insetsPx = [],
        array $parameters = [],
        string $label = 'scenario',
        string $option = '',
        int $pageNo = 1,
    ): void {
        if (is_string($geometryOrPrimitive) && is_array($primitiveOrGeometry)) {
            $primitive = $geometryOrPrimitive;
            $geometry = $primitiveOrGeometry;
        } elseif (is_array($geometryOrPrimitive) && is_string($primitiveOrGeometry)) {
            $geometry = $geometryOrPrimitive;
            $primitive = $primitiveOrGeometry;
        } else {
            throw new OmrDiagnosticTargetException('challenge_geometry_invalid', 'A primitive requires one geometry array and one primitive name.');
        }
        $geometry = $this->normalizedGeometry($geometry);
        $paperX = $geometry['x'] + (int) ($insetsPx['left'] ?? 0);
        $paperY = $geometry['y'] + (int) ($insetsPx['top'] ?? 0);
        if ($paperX < 0 || $paperY < 0 || $paperX + $geometry['w'] > imagesx($image) || $paperY + $geometry['h'] > imagesy($image)) {
            throw new OmrDiagnosticTargetException(
                'challenge_geometry_out_of_bounds',
                "Manifest field '{$label}' option '{$option}' on page {$pageNo} resolved to out-of-bounds paper coordinates."
            );
        }
        $operations = $this->primitive($primitive, $geometry, $parameters)['operations'];
        $left = (int) ($insetsPx['left'] ?? 0);
        $top = (int) ($insetsPx['top'] ?? 0);
        foreach ($operations as $operation) {
            $this->assertOperationBounds($operation, imagesx($image), imagesy($image), $label, $option, $pageNo, $left, $top);
            $this->drawOperation($image, $operation, $color, $left, $top);
        }
    }

    /**
     * Shade a scenario's declared candidate options from the canonical index.
     *
     * @param  array<int, array<string, array<string, array{x:int,y:int,w:int,h:int}>>>  $indexMap
     * @param  array<string, mixed>  $scenario
     * @param  array<string, int>  $insetsPx
     */
    public function shadeScenario(
        \GdImage $image,
        array $indexMap,
        int $pageNo,
        array $scenario,
        array $insetsPx,
        int $color,
    ): void {
        $item = is_array($scenario['item'] ?? null) ? $scenario['item'] : [];
        $label = (string) ($item['label'] ?? $scenario['label'] ?? '');
        $scenarioPage = (int) ($item['page_no'] ?? $scenario['page'] ?? $pageNo);
        if ($scenarioPage !== $pageNo || $label === '') {
            throw new OmrDiagnosticTargetException('challenge_geometry_invalid', 'Scenario item identity does not match the rendered page.');
        }
        $mark = is_array($scenario['mark'] ?? null) ? $scenario['mark'] : [];
        $answer = is_array($scenario['answer'] ?? null) ? $scenario['answer'] : [];
        $style = (string) ($mark['style'] ?? $scenario['mark_style'] ?? self::PRIMITIVE_DARK);
        $primitive = match ($style) {
            'normal_dark' => self::PRIMITIVE_DARK,
            'light_faint_threshold' => self::PRIMITIVE_FAINT,
            default => $style,
        };
        $parameters = is_array($mark['parameters'] ?? null) ? $mark['parameters'] : [];
        $condition = (string) ($answer['condition'] ?? $scenario['answer_condition'] ?? '');
        $candidates = $answer['candidates'] ?? $scenario['candidates'] ?? [];
        if (! is_array($candidates)) {
            throw new OmrDiagnosticTargetException('challenge_geometry_invalid', 'Scenario candidates must be an ordered list.');
        }
        $candidates = array_values(array_map('strval', $candidates));
        if ($condition === 'blank' || $candidates === []) {
            return;
        }
        if ($condition === 'between_bubble' || $primitive === self::PRIMITIVE_BETWEEN_BUBBLE) {
            if (count($candidates) < 2) {
                throw new OmrDiagnosticTargetException('challenge_geometry_invalid', 'between_bubble scenarios require two candidate options.');
            }
            $first = $this->resolve($indexMap, $pageNo, $label, $candidates[0]);
            $second = $this->resolve($indexMap, $pageNo, $label, $candidates[1]);
            $this->shadePrimitive($image, $first, self::PRIMITIVE_BETWEEN_BUBBLE, $color, $insetsPx, [...$parameters, 'neighbor' => $second], $label, implode('|', [$candidates[0], $candidates[1]]), $pageNo);

            return;
        }
        foreach ($candidates as $candidate) {
            $geometry = $this->resolve($indexMap, $pageNo, $label, $candidate);
            $candidatePrimitive = $primitive;
            if ($condition === 'double_with_expected' && count($candidates) >= 2) {
                $candidatePrimitive = self::PRIMITIVE_DARK;
            }
            if ($condition === 'double_without_expected' && count($candidates) >= 2) {
                $candidatePrimitive = self::PRIMITIVE_DARK;
            }
            if ($condition === 'triple' && count($candidates) >= 3) {
                $candidatePrimitive = self::PRIMITIVE_DARK;
            }
            $this->shadePrimitive($image, $geometry, $candidatePrimitive, $color, $insetsPx, $parameters, $label, $candidate, $pageNo);
        }
    }

    /** @param array<string, mixed> $geometry @return array{x:int,y:int,w:int,h:int} */
    private function normalizedGeometry(array $geometry): array
    {
        foreach (['x', 'y', 'w', 'h'] as $key) {
            if (! isset($geometry[$key]) || ! is_numeric($geometry[$key]) || ! is_finite((float) $geometry[$key])) {
                throw new OmrDiagnosticTargetException('challenge_geometry_invalid', 'Primitive geometry must contain finite numeric x, y, w, and h values.');
            }
        }
        $normalized = [
            'x' => (int) round((float) $geometry['x']),
            'y' => (int) round((float) $geometry['y']),
            'w' => (int) round((float) $geometry['w']),
            'h' => (int) round((float) $geometry['h']),
        ];
        if ($normalized['x'] < 0 || $normalized['y'] < 0 || $normalized['w'] < 6 || $normalized['h'] < 6) {
            throw new OmrDiagnosticTargetException('challenge_geometry_invalid', 'Primitive geometry must be positive and at least six pixels wide/high.');
        }

        return $normalized;
    }

    private function normalizePrimitive(string $primitive): string
    {
        if (! in_array($primitive, self::PRIMITIVES, true)) {
            throw new OmrDiagnosticTargetException('challenge_primitive_invalid', "Unknown deterministic bubble primitive '{$primitive}'.");
        }

        return $primitive;
    }

    private function clamp(int $value, int $minimum, int $maximum): int
    {
        return max($minimum, min($maximum, $value));
    }

    /** @param array<string, mixed> $operation */
    private function assertOperationBounds(array $operation, int $imageWidth, int $imageHeight, string $label, string $option, int $pageNo, int $left, int $top): void
    {
        $coordinates = match ($operation['type'] ?? null) {
            'ellipse', 'arc' => [
                (float) $operation['cx'] - ((float) $operation['w'] / 2) + $left,
                (float) $operation['cy'] - ((float) $operation['h'] / 2) + $top,
                (float) $operation['cx'] + ((float) $operation['w'] / 2) + $left,
                (float) $operation['cy'] + ((float) $operation['h'] / 2) + $top,
            ],
            'line' => [
                min((int) $operation['x1'], (int) $operation['x2']) + $left,
                min((int) $operation['y1'], (int) $operation['y2']) + $top,
                max((int) $operation['x1'], (int) $operation['x2']) + $left,
                max((int) $operation['y1'], (int) $operation['y2']) + $top,
            ],
            default => throw new OmrDiagnosticTargetException('challenge_primitive_invalid', 'Unknown primitive operation type.'),
        };
        if ($coordinates[0] < 0 || $coordinates[1] < 0 || $coordinates[2] >= $imageWidth || $coordinates[3] >= $imageHeight) {
            throw new OmrDiagnosticTargetException(
                'challenge_geometry_out_of_bounds',
                "Primitive for field '{$label}' option '{$option}' on page {$pageNo} exceeds the image bounds."
            );
        }
    }

    /** @param array<string, mixed> $operation */
    private function drawOperation(\GdImage $image, array $operation, int $color, int $left, int $top): void
    {
        $drawColor = $color;
        if (isset($operation['alpha'])) {
            $rgb = imagecolorsforindex($image, $color);
            $drawColor = imagecolorallocatealpha(
                $image,
                (int) ($rgb['red'] ?? 0),
                (int) ($rgb['green'] ?? 0),
                (int) ($rgb['blue'] ?? 0),
                max(0, min(127, (int) $operation['alpha'])),
            );
        }
        if (($operation['type'] ?? null) === 'ellipse') {
            if (($operation['filled'] ?? true) === true) {
                imagefilledellipse($image, (int) $operation['cx'] + $left, (int) $operation['cy'] + $top, (int) $operation['w'], (int) $operation['h'], $drawColor);
            } else {
                imageellipse($image, (int) $operation['cx'] + $left, (int) $operation['cy'] + $top, (int) $operation['w'], (int) $operation['h'], $drawColor);
            }
        } elseif (($operation['type'] ?? null) === 'arc') {
            imagearc($image, (int) $operation['cx'] + $left, (int) $operation['cy'] + $top, (int) $operation['w'], (int) $operation['h'], (int) $operation['start'], (int) $operation['end'], $drawColor);
        } elseif (($operation['type'] ?? null) === 'line') {
            imageline($image, (int) $operation['x1'] + $left, (int) $operation['y1'] + $top, (int) $operation['x2'] + $left, (int) $operation['y2'] + $top, $drawColor);
        }
    }

    /**
     * Shade all planned expected-vector fields and calibration digits for a page.
     *
     * @param  array<int, array<string, array<string, array{x: int, y: int, w: int, h: int}>>>  $indexMap
     * @param  array<string, string>  $expectedVectorForPage
     * @param  array<string, int>  $calibrationDigits
     * @param  array<string, int>  $insetsPx
     */
    public function shadePage(
        \GdImage $image,
        array $indexMap,
        int $pageNo,
        array $expectedVectorForPage,
        array $calibrationDigits,
        array $insetsPx,
        int $color,
    ): void {
        foreach ($expectedVectorForPage as $label => $option) {
            $geometry = $this->resolve($indexMap, $pageNo, (string) $label, (string) $option, false);
            $this->shade($image, $geometry, $color, $insetsPx, (string) $label, (string) $option, $pageNo);
        }

        foreach ($calibrationDigits as $label => $digit) {
            if (! isset($indexMap[$pageNo][$label])) {
                continue;
            }
            $digitOption = (string) $digit;
            $geometry = $this->resolve($indexMap, $pageNo, (string) $label, $digitOption, true);
            $this->shade($image, $geometry, $color, $insetsPx, (string) $label, $digitOption, $pageNo);
        }
    }
}
