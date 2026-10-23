<?php

namespace App\Support\Media;

use GdImage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ImageThumbnailScaler
{
    private const MAX_INPUT_BYTES = 25 * 1024 * 1024;

    private const MAX_OUTPUT_BYTES = 1024 * 1024;

    private const MAX_OUTPUT_WIDTH = 240;

    private const WEBP_QUALITY = 75;

    private const FALLBACK_MAX_WIDTH = 5000;

    private const FALLBACK_MAX_HEIGHT = 5000;

    private const FALLBACK_MAX_PIXELS = 20_000_000;

    private const ERROR_INVALID_INPUT = 1101;

    private const ERROR_UNSUPPORTED_IMAGE = 1102;

    private const ERROR_MIME_MISMATCH = 1103;

    private const ERROR_IMAGE_BOUNDS = 1104;

    private const ERROR_DECODE_FAILED = 1105;

    private const ERROR_TRANSFORM_FAILED = 1106;

    private const ERROR_ENCODE_FAILED = 1107;

    private const ERROR_OUTPUT_TOO_LARGE = 1108;

    public function __construct(
        private readonly ImageExifOrientation $exifOrientation = new ImageExifOrientation,
        private readonly int $maxOutputBytes = self::MAX_OUTPUT_BYTES,
    ) {
        if ($maxOutputBytes < 1) {
            throw new InvalidArgumentException('The maximum image output size must be at least one byte.');
        }
    }

    /**
     * Decode and re-encode a content-verified raster image using the fixed thumbnail policy.
     *
     * @throws RuntimeException With a stable code and source-independent message on rejection.
     */
    public function scale(string $imageBytes, string $declaredMime): string
    {
        if ($imageBytes === '' || strlen($imageBytes) > self::MAX_INPUT_BYTES) {
            throw new RuntimeException('Image input is empty or exceeds the permitted byte limit.', self::ERROR_INVALID_INPUT);
        }

        $imageInfo = @getimagesizefromstring($imageBytes);
        $detectedMime = is_array($imageInfo) ? ($imageInfo['mime'] ?? null) : null;

        if (! in_array($detectedMime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('Image content is corrupt or uses an unsupported format.', self::ERROR_UNSUPPORTED_IMAGE);
        }

        if ($detectedMime !== $declaredMime) {
            throw new RuntimeException('Declared image MIME type does not match image content.', self::ERROR_MIME_MISMATCH);
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        $orientation = $detectedMime === 'image/jpeg'
            ? $this->exifOrientation->fromJpegBytes($imageBytes)
            : 1;
        $orientedWidth = in_array($orientation, [5, 6, 7, 8], true) ? $height : $width;
        $orientedHeight = in_array($orientation, [5, 6, 7, 8], true) ? $width : $height;
        $maxWidth = $this->configuredLimit('omr.upload.max_width', self::FALLBACK_MAX_WIDTH);
        $maxHeight = $this->configuredLimit('omr.upload.max_height', self::FALLBACK_MAX_HEIGHT);
        $maxPixels = $this->configuredLimit('omr.upload.max_pixels', self::FALLBACK_MAX_PIXELS);

        if ($width < 1 || $height < 1 || $width > $maxWidth || $height > $maxHeight
            || $orientedWidth > $maxWidth || $orientedHeight > $maxHeight || $width * $height > $maxPixels) {
            throw new RuntimeException('Image dimensions exceed the permitted limits.', self::ERROR_IMAGE_BOUNDS);
        }

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            throw new RuntimeException('Image processing is unavailable.', self::ERROR_DECODE_FAILED);
        }

        $images = [];
        $image = @imagecreatefromstring($imageBytes);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('Image content could not be decoded.', self::ERROR_DECODE_FAILED);
        }

        $images[] = $image;

        try {
            if ($detectedMime === 'image/jpeg') {
                $image = $this->orient($image, $orientation, $images);
            }

            $orientedWidth = imagesx($image);
            $orientedHeight = imagesy($image);
            $outputWidth = min(self::MAX_OUTPUT_WIDTH, $orientedWidth);
            $outputHeight = max(1, (int) round($orientedHeight * ($outputWidth / $orientedWidth)));
            $canvas = imagecreatetruecolor($outputWidth, $outputHeight);

            if (! $canvas instanceof GdImage) {
                throw new RuntimeException('Image processing failed.', self::ERROR_TRANSFORM_FAILED);
            }

            $images[] = $canvas;
            imagealphablending($canvas, true);
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);

            if (! imagecopyresampled($canvas, $image, 0, 0, 0, 0, $outputWidth, $outputHeight, $orientedWidth, $orientedHeight)) {
                throw new RuntimeException('Image processing failed.', self::ERROR_TRANSFORM_FAILED);
            }

            ob_start();
            try {
                $encoded = @imagewebp($canvas, null, self::WEBP_QUALITY);
                $webpBytes = ob_get_contents();
            } finally {
                ob_end_clean();
            }
            if (! $encoded || ! is_string($webpBytes) || $webpBytes === '') {
                throw new RuntimeException('Image output could not be encoded.', self::ERROR_ENCODE_FAILED);
            }
            if (strlen($webpBytes) > $this->maxOutputBytes) {
                throw new RuntimeException('Image output exceeds the permitted byte limit.', self::ERROR_OUTPUT_TOO_LARGE);
            }

            return $webpBytes;
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new RuntimeException('Image processing failed.', self::ERROR_TRANSFORM_FAILED);
        } finally {
            foreach ($images as $resource) {
                imagedestroy($resource);
            }
        }
    }

    private function configuredLimit(string $key, int $fallback): int
    {
        $configured = config($key, $fallback);

        return is_numeric($configured) && (int) $configured > 0
            ? (int) $configured
            : $fallback;
    }

    /** @param list<GdImage> $images */
    private function orient(GdImage $image, int $orientation, array &$images): GdImage
    {
        if ($orientation === 2 || $orientation === 4) {
            if (! imageflip($image, $orientation === 2 ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL)) {
                throw new RuntimeException('Image orientation could not be applied.', self::ERROR_TRANSFORM_FAILED);
            }

            return $image;
        }

        if (! in_array($orientation, [3, 5, 6, 7, 8], true)) {
            return $image;
        }

        $angle = match ($orientation) {
            3 => 180,
            5, 6, 7 => -90,
            8 => 90,
        };
        $background = imagecolorallocatealpha($image, 255, 255, 255, 127);
        $rotated = imagerotate($image, $angle, $background);

        if (! $rotated instanceof GdImage) {
            throw new RuntimeException('Image orientation could not be applied.', self::ERROR_TRANSFORM_FAILED);
        }

        $images[] = $rotated;

        if (in_array($orientation, [5, 7], true)) {
            $flipMode = $orientation === 5 ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL;

            if (! imageflip($rotated, $flipMode)) {
                throw new RuntimeException('Image orientation could not be applied.', self::ERROR_TRANSFORM_FAILED);
            }
        }

        return $rotated;
    }
}
