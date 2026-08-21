<?php

namespace App\Assessment\Omr\Canonical;

use App\Models\Assessment\OmrTemplateAuthoringDraft;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use RuntimeException;

use function array_map;
use function config;
use function file_put_contents;
use function hash;
use function imagealphablending;
use function imagecolorallocate;
use function imagecopyresampled;
use function imagecreatefromstring;
use function imagecreatetruecolor;
use function imagefill;
use function imagepng;
use function imagesavealpha;
use function imagesx;
use function imagesy;
use function is_array;
use function is_string;
use function json_encode;
use function tempnam;
use function unlink;

final class OmrCanonicalCompositionException extends RuntimeException
{
    /** @param array<string, mixed> $context */
    public function __construct(public readonly string $errorCode, string $message, public readonly array $context = [])
    {
        parent::__construct($errorCode.': '.$message);
    }
}

final class OmrCanonicalComposer
{
    /**
     * @param array<string, mixed> $settings
     */
    public function compose(OmrTemplateAuthoringDraft $draft, array $settings): OmrCanonicalCompositionResult
    {
        $draft->loadMissing('markerProfile');
        $authoring = (array) $draft->authoring_json;
        $pages = array_values((array) ($authoring['pages'] ?? []));

        $isParametric = ($draft->source_kind === OmrTemplateAuthoringDraft::SOURCE_PRESET)
            || (($settings['parametric'] ?? false) === true);

        if (! $isParametric) {
            if ($draft->stage !== OmrTemplateAuthoringDraft::STAGE_SOURCE_CONFIRMED) {
                throw new OmrCanonicalCompositionException('page_not_confirmed', 'All source pages must be confirmed before composition.');
            }
            if ($pages === []) {
                throw new OmrCanonicalCompositionException('page_not_confirmed', 'The draft has no intermediate pages.');
            }
            foreach ($pages as $page) {
                if (($page['confirmed'] ?? false) !== true) {
                    throw new OmrCanonicalCompositionException('page_not_confirmed', 'Every intermediate page must be confirmed.', ['page_no' => $page['page_no'] ?? null]);
                }
            }
        }

        $paperWidthMm = (float) ($settings['paper_width_mm'] ?? 216);
        $paperHeightMm = (float) ($settings['paper_height_mm'] ?? 330);
        OmrPageDimensionMath::assertPaperBounds($paperWidthMm, $paperHeightMm);
        $canvasWidth = OmrPageDimensionMath::pixelsFromMillimetres($paperWidthMm);
        $canvasHeight = OmrPageDimensionMath::pixelsFromMillimetres($paperHeightMm);
        OmrPageDimensionMath::assertPixelBudget($canvasWidth, $canvasHeight);
        $normalizedSettings = $this->normalizeSettings($settings);
        if (! $isParametric) {
            $this->assertUniformPhysicalPages($pages, $paperWidthMm, $paperHeightMm);
        }

        $rasterPages = [];
        $temporaryPngs = [];
        try {
            if ($isParametric) {
                $pageCount = max(1, min(20, (int) ($settings['page_count'] ?? 1)));
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
                    $white = imagecolorallocate($canvas, 255, 255, 255);
                    imagealphablending($canvas, true);
                    imagefill($canvas, 0, 0, $white);
                    $markerMetadata = $this->drawMarkers($canvas, $draft->markerProfile, $normalizedSettings, $canvasWidth, $canvasHeight);
                    imagesavealpha($canvas, false);
                    ob_start();
                    imagepng($canvas, null, 6);
                    $pngBytes = (string) ob_get_clean();
                    $pngPath = tempnam(sys_get_temp_dir(), 'nexiam-canonical-page-');
                    if ($pngPath === false || file_put_contents($pngPath, $pngBytes) === false) {
                        throw new OmrCanonicalCompositionException('raster_write_failed', 'Canonical page raster could not be written.');
                    }
                    $temporaryPngs[] = $pngPath;
                    $rasterPages[] = [
                        'page_no' => $pageNo,
                        'bytes' => $pngBytes,
                        'mime' => 'image/png',
                        'width' => $canvasWidth,
                        'height' => $canvasHeight,
                        'sha256' => hash('sha256', $pngBytes),
                        'metadata' => [
                            'placement' => [
                                'x' => 0,
                                'y' => 0,
                                'width' => $canvasWidth,
                                'height' => $canvasHeight,
                                'source_width' => $canvasWidth,
                                'source_height' => $canvasHeight,
                            ],
                            'markers' => $markerMetadata,
                            'paper_width_mm' => $paperWidthMm,
                            'paper_height_mm' => $paperHeightMm,
                            'parametric' => true,
                        ],
                    ];
                }
            } else {
                foreach ($pages as $page) {
                    $asset = $draft->assets()->whereKey((int) ($page['asset_id'] ?? 0))->first();
                    if ($asset === null) {
                        throw new OmrCanonicalCompositionException('page_asset_missing', 'Intermediate page asset is missing.', ['page_no' => $page['page_no'] ?? null]);
                    }
                    $bytes = Storage::disk($asset->disk)->get($asset->path);
                    if (! is_string($bytes) || hash('sha256', $bytes) !== $asset->sha256) {
                        throw new OmrCanonicalCompositionException('asset_integrity_failure', 'Intermediate page asset failed its SHA-256 check.', ['page_no' => $page['page_no'] ?? null]);
                    }
                    $source = @imagecreatefromstring($bytes);
                    if ($source === false) {
                        throw new OmrCanonicalCompositionException('page_image_invalid', 'Intermediate page is not a readable raster.', ['page_no' => $page['page_no'] ?? null]);
                    }
                    $sourceWidth = imagesx($source);
                    $sourceHeight = imagesy($source);
                    $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
                    $white = imagecolorallocate($canvas, 255, 255, 255);
                    imagealphablending($canvas, true);
                    imagefill($canvas, 0, 0, $white);
                    $placement = OmrPageDimensionMath::place(
                        ['width' => $sourceWidth, 'height' => $sourceHeight],
                        ['width' => $canvasWidth, 'height' => $canvasHeight],
                        $normalizedSettings['content_margin_px'],
                        $normalizedSettings['alignment'],
                        $normalizedSettings['offset_x_px'],
                        $normalizedSettings['offset_y_px'],
                        (string) ($draft->source_kind ?? 'default'),
                        isset($page['metadata']['source_dpi']) ? (float) $page['metadata']['source_dpi'] : (float) ($settings['source_dpi'] ?? 300),
                    );
                    imagecopyresampled($canvas, $source, $placement['x'], $placement['y'], 0, 0, $placement['width'], $placement['height'], $sourceWidth, $sourceHeight);
                    $markerMetadata = $this->drawMarkers($canvas, $draft->markerProfile, $normalizedSettings, $canvasWidth, $canvasHeight);
                    imagesavealpha($canvas, false);
                    ob_start();
                    imagepng($canvas, null, 6);
                    $pngBytes = (string) ob_get_clean();
                    $pngPath = tempnam(sys_get_temp_dir(), 'nexiam-canonical-page-');
                    if ($pngPath === false || file_put_contents($pngPath, $pngBytes) === false) {
                        throw new OmrCanonicalCompositionException('raster_write_failed', 'Canonical page raster could not be written.');
                    }
                    $temporaryPngs[] = $pngPath;
                    $rasterPages[] = [
                        'page_no' => (int) $page['page_no'],
                        'bytes' => $pngBytes,
                        'mime' => 'image/png',
                        'width' => $canvasWidth,
                        'height' => $canvasHeight,
                        'sha256' => hash('sha256', $pngBytes),
                        'metadata' => [
                            'placement' => $placement,
                            'markers' => $markerMetadata,
                            'paper_width_mm' => $paperWidthMm,
                            'paper_height_mm' => $paperHeightMm,
                        ],
                    ];
                }
            }
            $pdfBytes = $this->pdf($temporaryPngs, $paperWidthMm, $paperHeightMm);
        } finally {
            foreach ($temporaryPngs as $path) {
                @unlink($path);
            }
        }

        $compositionHash = hash('sha256', json_encode([
            'pages' => array_map(static fn (array $page): array => ['page_no' => $page['page_no'], 'sha256' => $page['sha256']], $rasterPages),
            'settings' => $normalizedSettings,
            'pdf_sha256' => hash('sha256', $pdfBytes),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return new OmrCanonicalCompositionResult($rasterPages, $pdfBytes, $compositionHash, $normalizedSettings);
    }

    /** @return list<array{corner:string,center:array{0:int,1:int},size_px:int}> */
    private function drawMarkers($canvas, $profile, array $settings, int $canvasWidth, int $canvasHeight): array
    {
        if ($profile === null || $profile->size_mm === null || $profile->quiet_zone_mm === null || ! is_file(base_path($profile->asset_path))) {
            return [];
        }
        $marker = @imagecreatefromstring((string) file_get_contents(base_path($profile->asset_path)));
        if ($marker === false) {
            throw new OmrCanonicalCompositionException('marker_asset_invalid', 'Selected marker profile asset is unreadable.');
        }
        $size = OmrPageDimensionMath::pixelsFromMillimetres((float) $profile->size_mm);
        $inset = (array) ($settings['marker_inset_mm'] ?? []);
        $centers = [
            ['corner' => 'top_left', 'center' => [OmrPageDimensionMath::pixelsFromMillimetres((float) ($inset['left'] ?? 12)), OmrPageDimensionMath::pixelsFromMillimetres((float) ($inset['top'] ?? 12))]],
            ['corner' => 'top_right', 'center' => [$canvasWidth - OmrPageDimensionMath::pixelsFromMillimetres((float) ($inset['right'] ?? 12)), OmrPageDimensionMath::pixelsFromMillimetres((float) ($inset['top'] ?? 12))]],
            ['corner' => 'bottom_left', 'center' => [OmrPageDimensionMath::pixelsFromMillimetres((float) ($inset['left'] ?? 12)), $canvasHeight - OmrPageDimensionMath::pixelsFromMillimetres((float) ($inset['bottom'] ?? 14))]],
            ['corner' => 'bottom_right', 'center' => [$canvasWidth - OmrPageDimensionMath::pixelsFromMillimetres((float) ($inset['right'] ?? 12)), $canvasHeight - OmrPageDimensionMath::pixelsFromMillimetres((float) ($inset['bottom'] ?? 14))]],
        ];
        $metadata = [];
        foreach ($centers as $entry) {
            imagecopyresampled($canvas, $marker, $entry['center'][0] - intdiv($size, 2), $entry['center'][1] - intdiv($size, 2), 0, 0, $size, $size, imagesx($marker), imagesy($marker));
            $metadata[] = ['corner' => $entry['corner'], 'center' => $entry['center'], 'size_px' => $size];
        }

        return $metadata;
    }

    /** @param list<array<string, mixed>> $pages */
    private function assertUniformPhysicalPages(array $pages, float $paperWidthMm, float $paperHeightMm): void
    {
        $offending = [];
        foreach ($pages as $page) {
            $width = $page['width_mm'] ?? null;
            $height = $page['height_mm'] ?? null;
            if ($width === null || $height === null) {
                continue;
            }
            $same = abs((float) $width - $paperWidthMm) < 0.01 && abs((float) $height - $paperHeightMm) < 0.01;
            $rotated = abs((float) $width - $paperHeightMm) < 0.01 && abs((float) $height - $paperWidthMm) < 0.01;
            if (! $same && ! $rotated) {
                $offending[] = $page['page_no'] ?? null;
            }
        }
        if ($offending !== []) {
            throw new OmrCanonicalCompositionException('mixed_page_sizes', 'Source pages do not share one physical page size.', ['pages' => $offending]);
        }
    }

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    private function normalizeSettings(array $settings): array
    {
        $margin = $settings['content_margin_mm'] ?? ['top' => 18, 'right' => 15, 'bottom' => 18, 'left' => 15];
        $marginPx = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $marginPx[$side] = OmrPageDimensionMath::pixelsFromMillimetres((float) ($margin[$side] ?? 0));
        }

        return [
            'paper_width_mm' => (float) ($settings['paper_width_mm'] ?? 216),
            'paper_height_mm' => (float) ($settings['paper_height_mm'] ?? 330),
            'printer_safe_margin_mm' => $settings['printer_safe_margin_mm'] ?? ['top' => 5, 'right' => 5, 'bottom' => 5, 'left' => 5],
            'marker_inset_mm' => $settings['marker_inset_mm'] ?? ['top' => 12, 'right' => 12, 'bottom' => 14, 'left' => 12],
            'content_margin_mm' => $margin,
            'content_margin_px' => $marginPx,
            'alignment' => (string) ($settings['alignment'] ?? OmrPageDimensionMath::ALIGN_CENTER),
            'offset_x_px' => (int) ($settings['offset_x_px'] ?? 0),
            'offset_y_px' => (int) ($settings['offset_y_px'] ?? 0),
            'source_dpi' => (float) ($settings['source_dpi'] ?? 300),
        ];
    }

    /** @param list<string> $pngPaths */
    private function pdf(array $pngPaths, float $widthMm, float $heightMm): string
    {
        $pdf = new Fpdi('P', 'mm');
        $pdf->SetAutoPageBreak(false);
        foreach ($pngPaths as $pngPath) {
            $pdf->AddPage('P', [$widthMm, $heightMm]);
            $pdf->Image($pngPath, 0, 0, $widthMm, $heightMm, 'PNG');
        }

        return (string) $pdf->Output('S');
    }
}
