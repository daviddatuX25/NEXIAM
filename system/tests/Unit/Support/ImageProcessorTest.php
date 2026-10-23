<?php

declare(strict_types=1);

use App\Support\Media\ImageExifOrientation;
use App\Support\Media\ImagePolicy;
use App\Support\Media\ImageProcessor;
use App\Support\Media\RasterInput;
use Illuminate\Validation\ValidationException;

function processorFixture(string $format, int $width = 80, int $height = 40, bool $transparent = false): string
{
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $background = imagecolorallocatealpha($image, 0, 0, 0, $transparent ? 127 : 0);
    imagefill($image, 0, 0, $background);
    $red = imagecolorallocatealpha($image, 255, 0, 0, 0);
    imagefilledrectangle($image, 0, 0, (int) ($width / 2) - 1, $height - 1, $red);
    if (! $transparent) {
        $blue = imagecolorallocatealpha($image, 0, 0, 255, 0);
        imagefilledrectangle($image, (int) ($width / 2), 0, $width - 1, $height - 1, $blue);
    }

    ob_start();
    match ($format) {
        'jpeg' => imagejpeg($image, null, 95),
        'png' => imagepng($image),
        'webp' => imagewebp($image, null, 95),
    };
    $bytes = ob_get_clean();
    imagedestroy($image);

    if (! is_string($bytes) || $bytes === '') {
        throw new RuntimeException('Unable to create image fixture.');
    }

    return $bytes;
}

function processorExifOrientation(string $jpegBytes, int $orientation): string
{
    $tiff = 'II'.pack('v', 42).pack('V', 8).pack('v', 1)
        .pack('v', 0x0112).pack('v', 3).pack('V', 1).pack('v', $orientation)."\0\0";
    $payload = "Exif\0\0".$tiff;
    $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

    return substr($jpegBytes, 0, 2).$segment.substr($jpegBytes, 2);
}

function processorImagePixel(string $bytes, int $x, int $y): int
{
    $image = imagecreatefromstring($bytes);
    if (! $image instanceof GdImage) {
        throw new RuntimeException('Unable to decode processed image.');
    }
    $pixel = imagecolorat($image, $x, $y);
    imagedestroy($image);

    return $pixel;
}

function processorException(RasterInput $input, ImagePolicy $policy): ValidationException
{
    try {
        (new ImageProcessor(new ImageExifOrientation))->process($input, $policy);
    } catch (ValidationException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected image processing to reject the input.');
}

test('staff avatar policy produces a 256 square PNG', function (): void {
    $image = (new ImageProcessor(new ImageExifOrientation))->process(
        RasterInput::fromBytes(processorFixture('jpeg', 80, 40)),
        ImagePolicy::staffAvatar(),
    );

    expect($image->mime)->toBe('image/png')
        ->and($image->extension)->toBe('png')
        ->and($image->width)->toBe(256)
        ->and($image->height)->toBe(256)
        ->and(hash('sha256', $image->bytes))->toBe($image->sha256)
        ->and($image->byteSize)->toBe(strlen($image->bytes));
});

test('rejects oversized dimensions before attempting image decode', function (): void {
    $bytes = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.pack('N', 4097).pack('N', 1)."\x08\x02\0\0\0".pack('N', 0);
    $exception = processorException(RasterInput::fromBytes($bytes), new ImagePolicy);

    expect($exception->errors())->toHaveKey('image')
        ->and($exception->errors()['image'][0])->toContain('dimensions');
});

test('rejects over-limit byte size before inspecting image contents', function (): void {
    $policy = new ImagePolicy;
    $policy->maxBytes = 2;

    $exception = processorException(RasterInput::fromBytes('not-an-image'), $policy);

    expect($exception->errors()['image'][0])->toContain('file size');
});

test('rejects invalid image data', function (): void {
    $exception = processorException(RasterInput::fromBytes('invalid image data'), new ImagePolicy);

    expect($exception->errors())->toHaveKey('image');
});

test('normalizes JPEG EXIF orientation six clockwise', function (): void {
    $image = (new ImageProcessor(new ImageExifOrientation))->process(
        RasterInput::fromBytes(processorExifOrientation(processorFixture('jpeg', 40, 20), 6)),
        ImagePolicy::homepageImage(),
    );

    expect($image->width)->toBe(20)
        ->and($image->height)->toBe(40);
    $topPixel = processorImagePixel($image->bytes, 10, 5);
    $bottomPixel = processorImagePixel($image->bytes, 10, 35);
    expect(($topPixel >> 16) & 0xFF)->toBeGreaterThan(($topPixel) & 0xFF)
        ->and(($bottomPixel) & 0xFF)->toBeGreaterThan(($bottomPixel >> 16) & 0xFF);
});

test('preserves alpha transparency in PNG and WebP output', function (string $format): void {
    if ($format === 'webp' && ! function_exists('imagewebp')) {
        test()->markTestSkipped('GD WebP encoding is unavailable.');
    }
    $policy = new ImagePolicy;
    $policy->outputFormat = $format;
    $policy->mode = 'original';
    $input = RasterInput::fromBytes(processorFixture('png', 20, 10, true));
    $result = (new ImageProcessor(new ImageExifOrientation))->process($input, $policy);
    $transparentPixel = processorImagePixel($result->bytes, 18, 5);
    $alpha = ($transparentPixel >> 24) & 0x7F;

    expect($alpha)->toBeGreaterThanOrEqual(120);
})->with(['png', 'webp']);
