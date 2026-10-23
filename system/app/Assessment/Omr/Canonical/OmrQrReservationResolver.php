<?php

declare(strict_types=1);

namespace App\Assessment\Omr\Canonical;

use App\Assessment\Omr\Diagnostics\OmrDiagnosticTarget;

/**
 * Single canonical authority for resolving QR reservation boxes and QR placement
 * across Physical Challenge rendering, Digital Proof generation, and Blank Instance printing.
 */
final class OmrQrReservationResolver
{
    public const DEFAULT_PPI = 300.0;

    public const MM_PER_INCH = 25.4;

    /**
     * Resolve QR bounding box and element placement in pixels.
     *
     * @param  array<string, mixed>|OmrDiagnosticTarget|null  $source  Review manifest, artifact set, draft, target, or overlay
     * @param  int  $pageWidth  Page width in pixels (default 2480 for A4 at 300 PPI)
     * @param  int  $pageHeight  Page height in pixels (default 3508 for A4 at 300 PPI)
     * @param  array{left?: int, top?: int, right?: int, bottom?: int}|null  $insetsPx
     * @param  float  $ppi  Pixels per inch (default 300.0)
     * @return array{
     *     x: int,
     *     y: int,
     *     width: int,
     *     height: int,
     *     qr_x: int,
     *     qr_y: int,
     *     qr_size: int,
     *     display_code_x: int,
     *     display_code_y: int,
     *     has_reservation: bool,
     * }
     */
    public static function resolvePx(
        array|OmrDiagnosticTarget|null $source,
        int $pageWidth = 2480,
        int $pageHeight = 3508,
        ?array $insetsPx = null,
        float $ppi = self::DEFAULT_PPI,
    ): array {
        $rawBox = self::extractReservationBox($source, $ppi);

        if ($rawBox !== null) {
            $boxX = max(0, min($pageWidth - 10, (int) round($rawBox['x'])));
            $boxY = max(0, min($pageHeight - 10, (int) round($rawBox['y'])));
            $boxW = max(20, min($pageWidth - $boxX, (int) round($rawBox['width'])));
            $boxH = max(20, min($pageHeight - $boxY, (int) round($rawBox['height'])));

            // The authored box is the QR matrix itself: preserve its top-left
            // anchor and requested physical size exactly. Human-readable text
            // sits below the box and never shrinks or recenters the matrix.
            $qrSize = max(20, min($boxW, $boxH));
            $qrX = $boxX;
            $qrY = $boxY;
            $displayCodeX = $boxX;
            $displayCodeY = min(
                $pageHeight - max(8, (int) round(2.4 * $ppi / self::MM_PER_INCH)),
                $boxY + $qrSize + max(2, (int) round(0.6 * $ppi / self::MM_PER_INCH)),
            );

            return [
                'x' => $boxX,
                'y' => $boxY,
                'width' => $boxW,
                'height' => $boxH,
                'qr_x' => $qrX,
                'qr_y' => $qrY,
                'qr_size' => $qrSize,
                'display_code_x' => $displayCodeX,
                'display_code_y' => $displayCodeY,
                'has_reservation' => true,
            ];
        }

        // Safe margin-bounded default (top header zone)
        $insets = $insetsPx ?? self::extractInsetsPx($source) ?? [
            'left' => (int) round(12.0 * $ppi / self::MM_PER_INCH),
            'top' => (int) round(12.0 * $ppi / self::MM_PER_INCH),
            'right' => (int) round(12.0 * $ppi / self::MM_PER_INCH),
            'bottom' => (int) round(12.0 * $ppi / self::MM_PER_INCH),
        ];

        $left = (int) ($insets['left'] ?? 48) + 12;
        $right = $pageWidth - (int) ($insets['right'] ?? 48) - 12;
        $top = (int) ($insets['top'] ?? 48) + 12;
        $bottom = $pageHeight - (int) ($insets['bottom'] ?? 48) - 12;

        $qrSize = min(240, intdiv($pageWidth, 6));
        $qrX = max($left, min(intdiv($pageWidth - $qrSize, 2), $right - $qrSize));
        $qrY = max($top, min($top + 20, $bottom - $qrSize));
        $displayCodeY = min($pageHeight - 12, $qrY + $qrSize + 8);

        return [
            'x' => $qrX,
            'y' => $qrY,
            'width' => $qrSize,
            'height' => ($displayCodeY - $qrY) + 14,
            'qr_x' => $qrX,
            'qr_y' => $qrY,
            'qr_size' => $qrSize,
            'display_code_x' => $qrX,
            'display_code_y' => $displayCodeY,
            'has_reservation' => false,
        ];
    }

    /**
     * Resolve QR bounding box and element placement in millimetres.
     *
     * @param  array<string, mixed>|OmrDiagnosticTarget|null  $source
     * @param  float  $paperWidthMm  Paper width in mm (default 210.0 for A4)
     * @param  float  $paperHeightMm  Paper height in mm (default 297.0 for A4)
     * @param  float  $ppi  Reference PPI
     * @return array{
     *     x: float,
     *     y: float,
     *     width: float,
     *     height: float,
     *     qr_x: float,
     *     qr_y: float,
     *     qr_size: float,
     *     display_code_x: float,
     *     display_code_y: float,
     *     has_reservation: bool,
     * }
     */
    public static function resolveMm(
        array|OmrDiagnosticTarget|null $source,
        float $paperWidthMm = 210.0,
        float $paperHeightMm = 297.0,
        float $ppi = self::DEFAULT_PPI,
    ): array {
        $rawBox = self::extractReservationBox($source, $ppi);

        if ($rawBox !== null) {
            $pxToMm = self::MM_PER_INCH / $ppi;
            $boxXMm = $rawBox['x'] * $pxToMm;
            $boxYMm = $rawBox['y'] * $pxToMm;
            $boxWMm = $rawBox['width'] * $pxToMm;
            $boxHMm = $rawBox['height'] * $pxToMm;

            $boxXMm = max(0.0, min($paperWidthMm - 2.0, $boxXMm));
            $boxYMm = max(0.0, min($paperHeightMm - 2.0, $boxYMm));
            $boxWMm = max(5.0, min($paperWidthMm - $boxXMm, $boxWMm));
            $boxHMm = max(5.0, min($paperHeightMm - $boxYMm, $boxHMm));

            // Match pixel rendering: the authored dimensions describe the
            // QR matrix, not a larger card containing padding and a caption.
            $qrSizeMm = max(5.0, min($boxWMm, $boxHMm));
            $qrXMm = $boxXMm;
            $qrYMm = $boxYMm;
            $displayCodeYMm = min($paperHeightMm - 2.4, $boxYMm + $qrSizeMm + 0.6);

            return [
                'x' => round($boxXMm, 2),
                'y' => round($boxYMm, 2),
                'width' => round($boxWMm, 2),
                'height' => round($boxHMm, 2),
                'qr_x' => round($qrXMm, 2),
                'qr_y' => round($qrYMm, 2),
                'qr_size' => round($qrSizeMm, 2),
                'display_code_x' => round($qrXMm, 2),
                'display_code_y' => round($displayCodeYMm, 2),
                'has_reservation' => true,
            ];
        }

        $defaultSize = min(28.0, $paperWidthMm / 7.0);

        return [
            'x' => round($paperWidthMm - $defaultSize - 12.0, 2),
            'y' => 10.0,
            'width' => round($defaultSize, 2),
            'height' => round($defaultSize + 6.0, 2),
            'qr_x' => round($paperWidthMm - $defaultSize - 12.0, 2),
            'qr_y' => 10.0,
            'qr_size' => round($defaultSize, 2),
            'display_code_x' => round($paperWidthMm - $defaultSize - 12.0, 2),
            'display_code_y' => round(10.0 + $defaultSize + 2.0, 2),
            'has_reservation' => false,
        ];
    }

    /**
     * Extract raw reservation box normalized to pixels at given PPI.
     *
     * @param  array<string, mixed>|OmrDiagnosticTarget|null  $source
     * @return array{x: float, y: float, width: float, height: float}|null
     */
    public static function extractReservationBox(mixed $source, float $ppi = self::DEFAULT_PPI): ?array
    {
        if ($source instanceof OmrDiagnosticTarget) {
            $manifest = $source->reviewManifest();
            $resolved = self::extractReservationBox($manifest, $ppi);
            if ($resolved !== null) {
                return $resolved;
            }
            $config = $source->nativeConfig();
            $resolvedFromConfig = self::extractReservationBox($config, $ppi);
            if ($resolvedFromConfig !== null) {
                return $resolvedFromConfig;
            }
            if (method_exists($source, 'nativeTemplate')) {
                $template = $source->nativeTemplate(1);
                $resolvedFromTemplate = self::extractReservationBox($template, $ppi);
                if ($resolvedFromTemplate !== null) {
                    return $resolvedFromTemplate;
                }
            }

            return null;
        }

        if (! is_array($source)) {
            return null;
        }

        $mmToPx = $ppi / self::MM_PER_INCH;

        // 1. reviewManifest['qr_reservation'] or review_manifest['qr_reservation']
        $qrReservation = $source['qr_reservation'] ?? $source['review_manifest']['qr_reservation'] ?? $source['reviewManifest']['qr_reservation'] ?? null;
        if (is_array($qrReservation)) {
            return self::normalizeBoxToPx($qrReservation, $mmToPx);
        }

        // 2. overlay['boxes'] with tag in ['blank_instance_qr', 'instance_qr', 'qr'] or direct overlay box
        $overlay = $source['overlay'] ?? $source['authoring']['overlay'] ?? $source['definition']['overlay'] ?? null;
        if (is_array($overlay)) {
            $boxes = $overlay['boxes'] ?? [];
            if (is_array($boxes)) {
                foreach ($boxes as $box) {
                    if (is_array($box)) {
                        $tag = (string) ($box['tag'] ?? '');
                        $type = (string) ($box['type'] ?? '');
                        if (in_array($tag, ['blank_instance_qr', 'instance_qr', 'qr'], true) || $type === 'qr') {
                            return self::normalizeBoxToPx($box, $mmToPx);
                        }
                    }
                }
            } elseif (isset($overlay['x'], $overlay['y'], $overlay['width'])) {
                return self::normalizeBoxToPx($overlay, $mmToPx);
            }
        }

        // 2b. Root boxes array
        if (isset($source['boxes']) && is_array($source['boxes'])) {
            foreach ($source['boxes'] as $box) {
                if (is_array($box)) {
                    $tag = (string) ($box['tag'] ?? '');
                    $type = (string) ($box['type'] ?? '');
                    if (in_array($tag, ['blank_instance_qr', 'instance_qr', 'qr'], true) || $type === 'qr') {
                        return self::normalizeBoxToPx($box, $mmToPx);
                    }
                }
            }
        }

        // 3. visualComponents / visual_components / components with type === 'qr'
        $components = $source['visualComponents'] ?? $source['visual_components'] ?? $source['components'] ?? $source['studio']['components'] ?? $source['template']['visualComponents'] ?? null;
        if (is_array($components)) {
            foreach ($components as $component) {
                if (is_array($component) && ($component['type'] ?? '') === 'qr') {
                    return self::normalizeBoxToPx($component, $mmToPx);
                }
            }
        }

        // 4. Check nested artifact_set
        if (isset($source['artifact_set']) && is_array($source['artifact_set'])) {
            $nested = self::extractReservationBox($source['artifact_set'], $ppi);
            if ($nested !== null) {
                return $nested;
            }
        }

        // 5. Check nested definition
        if (isset($source['definition']) && is_array($source['definition'])) {
            $nested = self::extractReservationBox($source['definition'], $ppi);
            if ($nested !== null) {
                return $nested;
            }
        }

        // 6. Direct qr coordinates in source
        if (isset($source['qr']) && is_array($source['qr']) && ($source['qr']['visible'] ?? true)) {
            return self::normalizeBoxToPx($source['qr'], $mmToPx);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $box
     * @return array{x: float, y: float, width: float, height: float}
     */
    private static function normalizeBoxToPx(array $box, float $mmToPx): array
    {
        // Support multiple attribute naming conventions
        $x = null;
        $y = null;
        $w = null;
        $h = null;

        // Check explicit mm keys first
        if (isset($box['x_mm']) || isset($box['xMm']) || isset($box['posX'])) {
            $xMm = (float) ($box['x_mm'] ?? $box['xMm'] ?? $box['posX'] ?? 0);
            $yMm = (float) ($box['y_mm'] ?? $box['yMm'] ?? $box['posY'] ?? 0);
            $wMm = (float) ($box['w_mm'] ?? $box['wMm'] ?? $box['size_mm'] ?? $box['sizeMm'] ?? $box['width_mm'] ?? 0);
            $hMm = (float) ($box['h_mm'] ?? $box['hMm'] ?? $box['size_mm'] ?? $box['sizeMm'] ?? $box['height_mm'] ?? $wMm);

            $x = $xMm * $mmToPx;
            $y = $yMm * $mmToPx;
            $w = $wMm * $mmToPx;
            $h = $hMm * $mmToPx;
        } elseif (isset($box['x'], $box['y'])) {
            $rawX = (float) $box['x'];
            $rawY = (float) $box['y'];
            $rawW = (float) ($box['width'] ?? $box['w'] ?? $box['size'] ?? 0);
            $rawH = (float) ($box['height'] ?? $box['h'] ?? $box['size'] ?? $rawW);

            // Detect whether values are in mm (< 100 on page) or pixels (>= 100)
            if ($rawX <= 210.0 && $rawY <= 297.0 && $rawW <= 210.0 && $rawH <= 297.0 && ($box['unit'] ?? '') === 'mm') {
                $x = $rawX * $mmToPx;
                $y = $rawY * $mmToPx;
                $w = $rawW * $mmToPx;
                $h = $rawH * $mmToPx;
            } else {
                $x = $rawX;
                $y = $rawY;
                $w = $rawW;
                $h = $rawH;
            }
        }

        $x = $x !== null ? (float) $x : 0.0;
        $y = $y !== null ? (float) $y : 0.0;
        $w = $w !== null ? (float) $w : 0.0;
        $h = $h !== null ? (float) $h : $w;

        return [
            'x' => $x,
            'y' => $y,
            'width' => $w,
            'height' => $h,
        ];
    }

    /**
     * @param  array<string, mixed>|OmrDiagnosticTarget|null  $source
     * @return array{left: int, top: int, right: int, bottom: int}|null
     */
    private static function extractInsetsPx(mixed $source): ?array
    {
        if ($source instanceof OmrDiagnosticTarget) {
            $manifest = $source->reviewManifest();
            if (isset($manifest['insets_px']) && is_array($manifest['insets_px'])) {
                return $manifest['insets_px'];
            }

            return null;
        }

        if (! is_array($source)) {
            return null;
        }

        $insets = $source['insets_px'] ?? $source['definition']['insets_px'] ?? $source['review_manifest']['insets_px'] ?? null;
        if (is_array($insets) && isset($insets['left'], $insets['top'], $insets['right'], $insets['bottom'])) {
            return [
                'left' => (int) $insets['left'],
                'top' => (int) $insets['top'],
                'right' => (int) $insets['right'],
                'bottom' => (int) $insets['bottom'],
            ];
        }

        return null;
    }
}
