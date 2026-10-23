<?php

declare(strict_types=1);

namespace App\Support\Media;

use GdImage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ImageProcessor
{
    private string $currentErrorField = 'image';

    public function __construct(private readonly ImageExifOrientation $orientation) {}

    public function process(RasterInput $input, ImagePolicy $policy, string $errorField = 'image'): SanitizedImage
    {
        $this->currentErrorField = $errorField;
        $bytes = $input->bytes();
        if ($input->size() > $policy->maxBytes) {
            $this->reject('The image exceeds the permitted file size.');
        }

        $info = @getimagesizefromstring($bytes);
        $mime = is_array($info) ? ($info['mime'] ?? null) : null;
        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);

        if (! is_string($mime) || ! in_array($mime, $policy->allowedMimes, true)) {
            $this->reject('Choose a valid JPEG, PNG, or WebP image.');
        }
        if ($width < 1 || $height < 1
            || $width > $policy->maxInputDimension || $height > $policy->maxInputDimension
            || $width * $height > $policy->maxInputPixels) {
            $this->reject('The image dimensions exceed the permitted limits.');
        }
        if (! function_exists('imagecreatefromstring')) {
            $this->reject('Image processing is unavailable. Please try again later.');
        }

        $source = @imagecreatefromstring($bytes);
        if (! $source instanceof GdImage) {
            $this->reject('The image could not be decoded.');
        }
        imagealphablending($source, false);
        imagesavealpha($source, true);

        /** @var list<GdImage> $images */
        $images = [$source];
        $bufferLevel = ob_get_level();

        try {
            $exifOrientation = $mime === 'image/jpeg' ? $this->orientation->fromJpegBytes($bytes) : 1;
            $image = $this->orient($source, $exifOrientation, $images);
            [$outputWidth, $outputHeight, $cropX, $cropY, $cropSize] = $this->dimensions($image, $policy);

            if ($cropSize !== null || $outputWidth !== imagesx($image) || $outputHeight !== imagesy($image)) {
                $canvas = imagecreatetruecolor($outputWidth, $outputHeight);
                if (! $canvas instanceof GdImage) {
                    $this->reject('The image could not be transformed safely.');
                }
                $images[] = $canvas;
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                imagefill($canvas, 0, 0, $transparent);

                $sourceWidth = $cropSize ?? imagesx($image);
                $sourceHeight = $cropSize ?? imagesy($image);
                if (! imagecopyresampled(
                    $canvas,
                    $image,
                    0,
                    0,
                    $cropX,
                    $cropY,
                    $outputWidth,
                    $outputHeight,
                    $sourceWidth,
                    $sourceHeight,
                )) {
                    $this->reject('The image could not be transformed safely.');
                }
                $image = $canvas;
            }

            imagealphablending($image, false);
            imagesavealpha($image, true);
            $format = $policy->outputFormat === 'match_input' ? $this->formatForMime($mime) : $policy->outputFormat;
            if ($format === 'jpeg') {
                $image = $this->flattenTransparency($image, $images);
            }
            $outputMime = $this->mimeForFormat($format);

            ob_start();
            try {
                $encoded = match ($format) {
                    'png' => function_exists('imagepng') ? @imagepng($image, null, max(0, min(9, $policy->quality))) : false,
                    'webp' => function_exists('imagewebp') ? @imagewebp($image, null, max(0, min(100, $policy->quality))) : false,
                    'jpeg' => function_exists('imagejpeg') ? @imagejpeg($image, null, max(0, min(100, $policy->quality))) : false,
                    default => false,
                };
                $output = ob_get_contents();
            } finally {
                ob_end_clean();
            }

            if ($encoded !== true || ! is_string($output) || $output === '') {
                $this->reject('The image could not be safely re-encoded.');
            }

            $outputInfo = @getimagesizefromstring($output);
            if (! is_array($outputInfo) || ($outputInfo['mime'] ?? null) !== $outputMime) {
                $this->reject('The processed image could not be verified.');
            }

            return new SanitizedImage(
                bytes: $output,
                mime: $outputMime,
                extension: $format === 'jpeg' ? 'jpg' : $format,
                width: (int) $outputInfo[0],
                height: (int) $outputInfo[1],
                byteSize: strlen($output),
                sha256: hash('sha256', $output),
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            $this->reject('The image could not be processed safely.');
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            foreach ($images as $resource) {
                imagedestroy($resource);
            }
        }
    }

    /** @return array{int, int, int, int, int|null} */
    private function dimensions(GdImage $image, ImagePolicy $policy): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        if ($policy->mode === 'original') {
            return [$width, $height, 0, 0, null];
        }

        if ($policy->mode === 'square_crop') {
            if (($policy->targetWidth ?? 0) < 1 || ($policy->targetHeight ?? 0) < 1) {
                $this->reject('The image processing policy has invalid crop dimensions.');
            }
            $size = min($width, $height);

            return [$policy->targetWidth, $policy->targetHeight, intdiv($width - $size, 2), intdiv($height - $size, 2), $size];
        }

        if ($policy->mode !== 'fit') {
            $this->reject('The image processing policy has an unsupported mode.');
        }

        $maxWidth = $policy->targetWidth ?? $width;
        $maxHeight = $policy->targetHeight ?? $height;
        if ($maxWidth < 1 || $maxHeight < 1) {
            $this->reject('The image processing policy has invalid fit dimensions.');
        }
        $scale = min(1.0, $maxWidth / $width, $maxHeight / $height);

        return [max(1, (int) floor($width * $scale)), max(1, (int) floor($height * $scale)), 0, 0, null];
    }

    /** @param list<GdImage> $images */
    private function orient(GdImage $image, int $orientation, array &$images): GdImage
    {
        if ($orientation === 1) {
            return $image;
        }

        $flip = match ($orientation) {
            2, 5, 7 => IMG_FLIP_HORIZONTAL,
            4 => IMG_FLIP_VERTICAL,
            default => null,
        };
        if ($flip !== null && ! imageflip($image, $flip)) {
            $this->reject('The image orientation could not be normalized.');
        }

        $angle = match ($orientation) {
            3 => 180,
            5, 8 => 90,
            6, 7 => -90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }

        $background = imagecolorallocatealpha($image, 0, 0, 0, 127);
        $rotated = imagerotate($image, $angle, $background);
        if (! $rotated instanceof GdImage) {
            $this->reject('The image orientation could not be normalized.');
        }
        $images[] = $rotated;
        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);

        return $rotated;
    }

    /** @param list<GdImage> $images */
    private function flattenTransparency(GdImage $image, array &$images): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $background = imagecreatetruecolor($width, $height);
        if (! $background instanceof GdImage) {
            $this->reject('The image could not be transformed safely.');
        }
        $images[] = $background;
        imagealphablending($background, true);
        $white = imagecolorallocate($background, 255, 255, 255);
        imagefill($background, 0, 0, $white);
        imagecopy($background, $image, 0, 0, 0, 0, $width, $height);

        return $background;
    }

    private function formatForMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpeg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'png',
        };
    }

    private function mimeForFormat(string $format): string
    {
        return match ($format) {
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => $this->reject('The image processing policy has an unsupported output format.'),
        };
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages([$this->currentErrorField => $message]);
    }
}
