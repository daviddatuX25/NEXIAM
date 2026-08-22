<?php

namespace App\Assessment\Omr\Canonical;

use RuntimeException;

use function file_get_contents;
use function file_put_contents;
use function getimagesize;
use function imagealphablending;
use function imagecolorallocate;
use function imagecopy;
use function imagecreatefromstring;
use function imagecreatetruecolor;
use function imageflip;
use function imagepng;
use function imagerotate;
use function imagesavealpha;
use function imagesx;
use function imagesy;
use function is_resource;
use function max;
use function sys_get_temp_dir;
use function tempnam;

final class OmrImageSourceAdapter extends AbstractOmrSourceAdapter
{
    public function __construct(OmrSourcePreflight $preflightService)
    {
        parent::__construct($preflightService);
    }

    public function kind(): string
    {
        return 'image';
    }

    public function convert(string $sourcePath, array $context = []): OmrSourceIngestionResult
    {
        $preflight = $this->preflight($sourcePath, $context);
        if (in_array(strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION)), ['tif', 'tiff'], true)) {
            throw new OmrSourcePreflightException('multipage_tiff_unsupported', 'TIFF sources are rejected in canonical v1; upload one flattened page image.');
        }
        if (($context['authoring_photo'] ?? false) === true && ($context['rectification_confirmed'] ?? false) !== true) {
            throw new OmrSourcePreflightException('quad_confirmation_required', 'Authoring photos require staff-confirmed quadrilateral rectification.');
        }
        $bytes = (string) file_get_contents($sourcePath);
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            throw new RuntimeException('image_decode_failed');
        }
        $normalized = $this->flattenAndOrient($source, $sourcePath);
        $path = tempnam(sys_get_temp_dir(), 'nexiam-omr-image-');
        if ($path === false || ! imagepng($normalized, $path, 6)) {
            throw new RuntimeException('image_normalization_failed');
        }
        $width = imagesx($normalized);
        $height = imagesy($normalized);
        $sourceDpi = isset($context['source_dpi']) ? (float) $context['source_dpi'] : null;
        $widthMm = isset($context['width_mm']) ? (float) $context['width_mm'] : ($sourceDpi !== null ? $width / $sourceDpi * 25.4 : null);
        $heightMm = isset($context['height_mm']) ? (float) $context['height_mm'] : ($sourceDpi !== null ? $height / $sourceDpi * 25.4 : null);
        $page = [
            'page_no' => 1,
            'path' => $path,
            'mime' => 'image/png',
            'bytes' => (int) filesize($path),
            'sha256' => (string) hash_file('sha256', $path),
            'width' => $width,
            'height' => $height,
            'width_mm' => $widthMm,
            'height_mm' => $heightMm,
            'orientation' => $width >= $height ? 'landscape' : 'portrait',
            'metadata' => [
                'source_mime' => $preflight->mime,
                'source_dpi' => $sourceDpi,
                'alpha_flattened' => true,
                'exif_orientation_applied' => true,
            ],
        ];
        $this->lastResult = new OmrSourceIngestionResult(
            kind: $this->kind(),
            sourcePath: $sourcePath,
            sourceSha256: $preflight->sha256,
            converterVersion: 'gd-normalize-v1',
            settingsHash: $this->settingsHash(['source_dpi' => $sourceDpi, 'width_mm' => $widthMm, 'height_mm' => $heightMm]),
            pages: [$page],
            metadata: ['authoring_photo' => (bool) ($context['authoring_photo'] ?? false)],
        );

        return $this->lastResult;
    }

    private function flattenAndOrient(object $source, string $path): object
    {
        $orientation = 1;
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $orientation = (int) ($exif['Orientation'] ?? 1);
        }
        $rotated = match ($orientation) {
            3 => imagerotate($source, 180, 0),
            6 => imagerotate($source, -90, 0),
            8 => imagerotate($source, 90, 0),
            default => $source,
        };
        if ($orientation === 2 || $orientation === 4 || $orientation === 5 || $orientation === 7) {
            imageflip($rotated, $orientation === 2 || $orientation === 5 ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
        }
        $width = imagesx($rotated);
        $height = imagesy($rotated);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagealphablending($canvas, true);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $rotated, 0, 0, 0, 0, $width, $height);
        imagesavealpha($canvas, false);

        return $canvas;
    }
}
