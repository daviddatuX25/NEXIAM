<?php

declare(strict_types=1);

namespace App\Assessment\Omr\Diagnostics;

use App\Assessment\Omr\Canonical\OmrPageDimensionMath;
use App\Assessment\Omr\Canonical\OmrQrReservationResolver;
use App\Assessment\Omr\OmrQrMode;

use function array_is_list;
use function config;
use function count;
use function is_array;
use function is_numeric;
use function max;
use function round;

/**
 * Deterministic proof-only calibration QR placement. Production reservation
 * resolution is used only to identify an existing signed QR obstacle; its
 * fallback position is never used for diagnostic QR placement.
 */
final class OmrProofQrReservationPlanner
{
    private const QR_SIZE_MM = 25.0;

    private const RESERVATION_HEIGHT_MM = 31.0;

    private const PAPER_MARGIN_MM = 6.0;

    private const COLLISION_GAP_MM = 1.0;

    private const SEARCH_STEP_MM = 2.5;

    /** @return list<array{page_no:int,x_mm:float,y_mm:float,width_mm:float,height_mm:float,qr_x_mm:float,qr_y_mm:float,qr_size_mm:float,display_code_x_mm:float,display_code_y_mm:float,paper_width_mm:float,paper_height_mm:float}> */
    public function plan(OmrDiagnosticTarget $target): array
    {
        $pages = $target->compiledPages();
        if (! array_is_list($pages) || $pages === [] || count($pages) !== $target->pageCount()) {
            throw new OmrDiagnosticTargetException('diagnostic_page_policy_mismatch', 'Proof QR placement requires the exact ordered compiled page list.');
        }

        $manifest = $target->reviewManifest();
        $ppi = (float) config('omr.canonical.ppi', 300);
        $placements = [];
        foreach ($pages as $index => $page) {
            $pageNo = $index + 1;
            if (! is_array($page) || (int) ($page['page_no'] ?? 0) !== $pageNo) {
                throw new OmrDiagnosticTargetException('diagnostic_page_policy_mismatch', 'Proof QR placement requires contiguous compiled pages beginning at Page 1.');
            }
            $paper = $target->paperDimensionsMm($pageNo);
            $paperWidthMm = (float) ($paper['width_mm'] ?? 0);
            $paperHeightMm = (float) ($paper['height_mm'] ?? 0);
            if ($paperWidthMm <= self::QR_SIZE_MM + 2 * self::PAPER_MARGIN_MM
                || $paperHeightMm <= self::RESERVATION_HEIGHT_MM + 2 * self::PAPER_MARGIN_MM) {
                throw new OmrDiagnosticTargetException('diagnostic_qr_reservation_missing', "Page {$pageNo} is too small for a safe calibration QR reservation.");
            }

            $insets = $this->insets($manifest);
            $obstacles = $this->fieldObstacles($manifest, $pageNo, $insets, $ppi);
            $obstacles = [...$obstacles, ...$this->componentObstacles($target->visualComponents($pageNo))];
            $nativePage = $target->nativeTemplate($pageNo);
            $obstacles = [...$obstacles, ...$this->markerObstacles($nativePage, $insets, $ppi)];

            if ($target->qrMode() === OmrQrMode::Signed) {
                $pageWidthPx = OmrPageDimensionMath::pixelsFromMillimetres($paperWidthMm, $ppi);
                $pageHeightPx = OmrPageDimensionMath::pixelsFromMillimetres($paperHeightMm, $ppi);
                $productionQr = OmrQrReservationResolver::resolvePx($target, $pageWidthPx, $pageHeightPx, $insets, $ppi);
                $captionBottom = $productionQr['display_code_y'] + 16;
                $captionRight = $productionQr['display_code_x'] + max(1, (int) round(strlen((string) ($manifest['production_qr_display_code'] ?? '')) * 8));
                $obstacles[] = $this->pixelsToMillimetres([
                    'x' => $productionQr['x'],
                    'y' => $productionQr['y'],
                    'w' => max($productionQr['width'], $captionRight - $productionQr['x']),
                    'h' => max($productionQr['height'], $captionBottom - $productionQr['y']),
                ], $ppi);
            } elseif (isset($manifest['qr_reservation'])) {
                throw new OmrDiagnosticTargetException('diagnostic_qr_mode_mismatch', 'A QR-disabled paper target cannot expose production QR geometry.');
            }

            $position = $this->findPosition($paperWidthMm, $paperHeightMm, $obstacles);
            if ($position === null) {
                throw new OmrDiagnosticTargetException('diagnostic_qr_reservation_missing', "No collision-free calibration QR reservation exists on page {$pageNo}.");
            }
            [$x, $y] = $position;
            $placements[] = [
                'page_no' => $pageNo,
                'x_mm' => round($x, 2),
                'y_mm' => round($y, 2),
                'width_mm' => self::QR_SIZE_MM,
                'height_mm' => self::RESERVATION_HEIGHT_MM,
                'qr_x_mm' => round($x, 2),
                'qr_y_mm' => round($y, 2),
                'qr_size_mm' => self::QR_SIZE_MM,
                'display_code_x_mm' => round($x, 2),
                'display_code_y_mm' => round($y + self::QR_SIZE_MM + 2.0, 2),
                'paper_width_mm' => $paperWidthMm,
                'paper_height_mm' => $paperHeightMm,
            ];
        }

        return $placements;
    }

    /** @param array<string,mixed> $manifest @return array{left:int,top:int,right:int,bottom:int} */
    private function insets(array $manifest): array
    {
        $insets = is_array($manifest['insets_px'] ?? null) ? $manifest['insets_px'] : [];

        return [
            'left' => max(0, (int) ($insets['left'] ?? 0)),
            'top' => max(0, (int) ($insets['top'] ?? 0)),
            'right' => max(0, (int) ($insets['right'] ?? 0)),
            'bottom' => max(0, (int) ($insets['bottom'] ?? 0)),
        ];
    }

    /** @param array<string,mixed> $manifest @param array{left:int,top:int,right:int,bottom:int} $insets @return list<array{x:float,y:float,w:float,h:float}> */
    private function fieldObstacles(array $manifest, int $pageNo, array $insets, float $ppi): array
    {
        $fields = $manifest['fields'] ?? null;
        if (! is_array($fields) || ! array_is_list($fields)) {
            throw new OmrDiagnosticTargetException('diagnostic_collision_geometry_missing', 'Proof QR placement requires mapped field geometry for collision checks.');
        }
        $obstacles = [];
        foreach ($fields as $field) {
            if (! is_array($field) || ! is_int($field['page'] ?? null)) {
                throw new OmrDiagnosticTargetException('diagnostic_collision_geometry_invalid', 'Mapped fields must carry an explicit page number.');
            }
            if ((int) $field['page'] !== $pageNo) {
                continue;
            }
            $pixel = $field['pixel'] ?? null;
            if (! is_array($pixel)
                || ! is_numeric($pixel['x'] ?? null)
                || ! is_numeric($pixel['y'] ?? null)
                || ! is_numeric($pixel['w'] ?? null)
                || ! is_numeric($pixel['h'] ?? null)
                || (float) $pixel['w'] <= 0
                || (float) $pixel['h'] <= 0) {
                throw new OmrDiagnosticTargetException('diagnostic_collision_geometry_invalid', 'Mapped field collision geometry must be a positive pixel rectangle.');
            }
            $obstacles[] = $this->pixelsToMillimetres([
                'x' => (float) $pixel['x'] + $insets['left'],
                'y' => (float) $pixel['y'] + $insets['top'],
                'w' => (float) $pixel['w'],
                'h' => (float) $pixel['h'],
            ], $ppi);
        }
        if ($obstacles === []) {
            throw new OmrDiagnosticTargetException('diagnostic_collision_geometry_missing', "Page {$pageNo} has no mapped field geometry to verify.");
        }

        return $obstacles;
    }

    /** @param list<array<string,mixed>> $components @return list<array{x:float,y:float,w:float,h:float}> */
    private function componentObstacles(array $components): array
    {
        $obstacles = [];
        foreach ($components as $component) {
            if (! is_array($component)
                || ! is_numeric($component['posX'] ?? null)
                || ! is_numeric($component['posY'] ?? null)
                || ! is_numeric($component['w_mm'] ?? null)
                || ! is_numeric($component['h_mm'] ?? null)
                || (float) $component['w_mm'] <= 0
                || (float) $component['h_mm'] <= 0) {
                throw new OmrDiagnosticTargetException('diagnostic_collision_geometry_invalid', 'Every visual component requires positive paper-coordinate bounds for QR collision checks.');
            }
            $obstacles[] = [
                'x' => (float) $component['posX'],
                'y' => (float) $component['posY'],
                'w' => (float) $component['w_mm'],
                'h' => (float) $component['h_mm'],
            ];
        }

        return $obstacles;
    }

    /** @param array<string,mixed> $page @param array{left:int,top:int,right:int,bottom:int} $insets @return list<array{x:float,y:float,w:float,h:float}> */
    private function markerObstacles(array $page, array $insets, float $ppi): array
    {
        $markers = is_array($page['markers'] ?? null) ? $page['markers'] : [];
        $centers = $markers['centers'] ?? [];
        $size = (int) ($markers['size'] ?? 0);
        if (! is_array($centers) || $centers === [] || $size <= 0) {
            return [];
        }
        $quietZone = max(0, (int) ($markers['quiet_zone'] ?? 0));
        $diameter = $size + 2 * $quietZone;
        $obstacles = [];
        foreach ($centers as $center) {
            if (! is_array($center) || ! is_numeric($center[0] ?? null) || ! is_numeric($center[1] ?? null)) {
                throw new OmrDiagnosticTargetException('diagnostic_collision_geometry_invalid', 'Marker footprints must contain numeric center coordinates.');
            }
            $obstacles[] = $this->pixelsToMillimetres([
                'x' => (float) $center[0] + $insets['left'] - $diameter / 2,
                'y' => (float) $center[1] + $insets['top'] - $diameter / 2,
                'w' => $diameter,
                'h' => $diameter,
            ], $ppi);
        }

        return $obstacles;
    }

    /** @param array{x:int|float,y:int|float,w:int|float,h:int|float} $rectangle @return array{x:float,y:float,w:float,h:float} */
    private function pixelsToMillimetres(array $rectangle, float $ppi): array
    {
        return [
            'x' => (float) $rectangle['x'] * 25.4 / $ppi,
            'y' => (float) $rectangle['y'] * 25.4 / $ppi,
            'w' => OmrPageDimensionMath::millimetresFromPixels($rectangle['w'], $ppi),
            'h' => OmrPageDimensionMath::millimetresFromPixels($rectangle['h'], $ppi),
        ];
    }

    /** @param list<array{x:float,y:float,w:float,h:float}> $obstacles @return array{float,float}|null */
    private function findPosition(float $paperWidthMm, float $paperHeightMm, array $obstacles): ?array
    {
        $rightX = $paperWidthMm - self::PAPER_MARGIN_MM - self::QR_SIZE_MM;
        $bottomY = $paperHeightMm - self::PAPER_MARGIN_MM - self::RESERVATION_HEIGHT_MM;
        $corners = [
            [$rightX, self::PAPER_MARGIN_MM],
            [self::PAPER_MARGIN_MM, self::PAPER_MARGIN_MM],
            [$rightX, $bottomY],
            [self::PAPER_MARGIN_MM, $bottomY],
        ];
        foreach ($corners as [$x, $y]) {
            if (! $this->collides($x, $y, $obstacles)) {
                return [$x, $y];
            }
        }

        $lastY = $paperHeightMm - self::PAPER_MARGIN_MM - self::RESERVATION_HEIGHT_MM;
        $lastX = $paperWidthMm - self::PAPER_MARGIN_MM - self::QR_SIZE_MM;
        for ($y = self::PAPER_MARGIN_MM; $y <= $lastY; $y += self::SEARCH_STEP_MM) {
            for ($x = self::PAPER_MARGIN_MM; $x <= $lastX; $x += self::SEARCH_STEP_MM) {
                if (! $this->collides($x, $y, $obstacles)) {
                    return [$x, $y];
                }
            }
        }

        return null;
    }

    /** @param list<array{x:float,y:float,w:float,h:float}> $obstacles */
    private function collides(float $x, float $y, array $obstacles): bool
    {
        $left = $x - self::COLLISION_GAP_MM;
        $top = $y - self::COLLISION_GAP_MM;
        $right = $x + self::QR_SIZE_MM + self::COLLISION_GAP_MM;
        $bottom = $y + self::RESERVATION_HEIGHT_MM + self::COLLISION_GAP_MM;
        foreach ($obstacles as $obstacle) {
            if ($left < $obstacle['x'] + $obstacle['w']
                && $right > $obstacle['x']
                && $top < $obstacle['y'] + $obstacle['h']
                && $bottom > $obstacle['y']) {
                return true;
            }
        }

        return false;
    }
}
