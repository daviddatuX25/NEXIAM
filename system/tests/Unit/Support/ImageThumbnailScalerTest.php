<?php

use App\Support\Media\ImageThumbnailScaler;
use InvalidArgumentException;
use RuntimeException;

function thumbnailFixture(string $format, int $width = 80, int $height = 40): string
{
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);
    $red = imagecolorallocatealpha($image, 255, 0, 0, 0);
    imagefilledrectangle($image, 0, 0, max(0, (int) ($width / 2) - 1), $height - 1, $red);

    ob_start();
    match ($format) {
        'jpeg' => imagejpeg($image, null, 90),
        'png' => imagepng($image),
        'webp' => imagewebp($image, null, 90),
    };
    $bytes = ob_get_clean();
    imagedestroy($image);

    if (! is_string($bytes) || $bytes === '') {
        throw new RuntimeException('Unable to create image fixture.');
    }

    return $bytes;
}

function thumbnailWithExifOrientation(string $jpegBytes, int $orientation, string $marker = 'fixture-exif-payload'): string
{
    $tiff = 'II'.pack('v', 42).pack('V', 8).pack('v', 2)
        .pack('v', 0x0112).pack('v', 3).pack('V', 1).pack('v', $orientation)."\0\0"
        .pack('v', 0x010E).pack('v', 2).pack('V', strlen($marker) + 1).pack('V', 38)
        .pack('V', 0).$marker."\0";
    $payload = "Exif\0\0".$tiff;
    $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

    return substr($jpegBytes, 0, 2).$segment.substr($jpegBytes, 2);
}

function thumbnailExceptionCode(ImageThumbnailScaler $scaler, string $bytes, string $mime): int
{
    try {
        $scaler->scale($bytes, $mime);
    } catch (RuntimeException $exception) {
        return $exception->getCode();
    }

    throw new RuntimeException('Expected the image scaler to reject its input.');
}

test('scales JPEG PNG and WebP fixtures into bounded WebP output', function (string $sourceFormat, string $mime): void {
    $output = (new ImageThumbnailScaler)->scale(thumbnailFixture($sourceFormat, 480, 240), $mime);
    $info = getimagesizefromstring($output);

    expect($info)->toBeArray()
        ->and($info['mime'])->toBe('image/webp')
        ->and($info[0])->toBe(240)
        ->and($info[1])->toBe(120);
})->with([
    'jpeg' => ['jpeg', 'image/jpeg'],
    'png' => ['png', 'image/png'],
    'webp' => ['webp', 'image/webp'],
]);

test('rejects WebP output exceeding the injected byte ceiling', function (): void {
    $input = thumbnailFixture('png');

    expect(thumbnailExceptionCode(new ImageThumbnailScaler(maxOutputBytes: 1), $input, 'image/png'))->toBe(1108);
});

test('rejects an output byte ceiling below one', function (): void {
    expect(fn () => new ImageThumbnailScaler(maxOutputBytes: 0))->toThrow(InvalidArgumentException::class);
});

test('never upscales a small image and flattens transparent pixels to white', function (): void {
    $output = (new ImageThumbnailScaler)->scale(thumbnailFixture('png', 12, 6), 'image/png');
    $decoded = imagecreatefromstring($output);

    $pixel = imagecolorat($decoded, 11, 5);

    expect(imagesx($decoded))->toBe(12)
        ->and(imagesy($decoded))->toBe(6)
        ->and(($pixel >> 16) & 0xFF)->toBeGreaterThan(245)
        ->and(($pixel >> 8) & 0xFF)->toBeGreaterThan(245)
        ->and($pixel & 0xFF)->toBeGreaterThan(245);

    imagedestroy($decoded);
});

test('applies JPEG EXIF orientation and strips source EXIF metadata', function (): void {
    $jpegWithOrientation = thumbnailWithExifOrientation(thumbnailFixture('jpeg', 80, 40), 6);
    $output = (new ImageThumbnailScaler)->scale($jpegWithOrientation, 'image/jpeg');
    $info = getimagesizefromstring($output);

    expect($info)->toBeArray()
        ->and($info[0])->toBe(40)
        ->and($info[1])->toBe(80)
        ->and(str_contains($output, "Exif\0\0"))->toBeFalse()
        ->and(str_contains($output, 'fixture-exif-payload'))->toBeFalse();
});

test('rejects empty corrupt unsupported and mismatched MIME inputs with stable safe errors', function (): void {
    $scaler = new ImageThumbnailScaler;

    expect(thumbnailExceptionCode($scaler, '', 'image/jpeg'))->toBe(1101)
        ->and(thumbnailExceptionCode($scaler, 'not an image', 'image/jpeg'))->toBe(1102)
        ->and(thumbnailExceptionCode($scaler, thumbnailFixture('png'), 'image/jpeg'))->toBe(1103);
});

test('rejects inputs exceeding the fixed byte ceiling before decoding', function (): void {
    $oversized = thumbnailFixture('png').str_repeat('x', 25 * 1024 * 1024);

    expect(thumbnailExceptionCode(new ImageThumbnailScaler, $oversized, 'image/png'))->toBe(1101);
});

test('rejects configured width height and pixel bounds before decoding', function (): void {
    config()->set('omr.upload.max_width', 79);
    expect(thumbnailExceptionCode(new ImageThumbnailScaler, thumbnailFixture('png', 80, 40), 'image/png'))->toBe(1104);

    config()->set('omr.upload.max_width', 5000);
    config()->set('omr.upload.max_height', 39);
    expect(thumbnailExceptionCode(new ImageThumbnailScaler, thumbnailFixture('png', 80, 40), 'image/png'))->toBe(1104);

    config()->set('omr.upload.max_height', 5000);
    config()->set('omr.upload.max_pixels', 3199);
    expect(thumbnailExceptionCode(new ImageThumbnailScaler, thumbnailFixture('png', 80, 40), 'image/png'))->toBe(1104);
});
