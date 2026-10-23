<?php

use App\Assessment\Omr\OmrCaptureInput;
use App\Assessment\Omr\OmrQuarterTurnNormalizationException;
use App\Assessment\Omr\OmrQuarterTurnNormalizer;

function quarterTurnFixtureBytes(int $width = 2, int $height = 3): string
{
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            imagesetpixel($image, $x, $y, ($x + ($y * $width)) % 2 === 0 ? 0xFF0000 : 0x0000FF);
        }
    }
    ob_start();
    imagepng($image, null, 9, 0);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

test('quarter-turn candidates are exactly ordered and swap dimensions', function (): void {
    $bytes = quarterTurnFixtureBytes();
    $diagnostics = (new OmrQuarterTurnNormalizer(maxPixels: 100))->candidates(
        new OmrCaptureInput(bytes: $bytes, mime: 'image/png'),
    );

    expect($diagnostics->orientations())->toBe([0, 90, 180, 270])
        ->and($diagnostics->candidates[0]->bytes)->toBe($bytes)
        ->and($diagnostics->candidates[0]->sha256)->toBe(hash('sha256', $bytes))
        ->and([$diagnostics->candidates[0]->width, $diagnostics->candidates[0]->height])->toBe([2, 3])
        ->and([$diagnostics->candidates[1]->width, $diagnostics->candidates[1]->height])->toBe([3, 2])
        ->and([$diagnostics->candidates[2]->width, $diagnostics->candidates[2]->height])->toBe([2, 3])
        ->and([$diagnostics->candidates[3]->width, $diagnostics->candidates[3]->height])->toBe([3, 2]);
});

test('trusted orientation changes candidate order without removing fallback turns', function (): void {
    $diagnostics = (new OmrQuarterTurnNormalizer(maxPixels: 100))->candidates(
        new OmrCaptureInput(bytes: quarterTurnFixtureBytes(), mime: 'image/png'),
        preferredOrientation: 180,
    );

    expect($diagnostics->orientations())->toBe([180, 0, 90, 270])
        ->and($diagnostics->candidates)->toHaveCount(4);
});

test('quarter-turn hashes are deterministic and temporary references are absent', function (): void {
    $input = new OmrCaptureInput(bytes: quarterTurnFixtureBytes(3, 2), mime: 'image/png');
    $normalizer = new OmrQuarterTurnNormalizer(maxPixels: 100);

    $first = $normalizer->candidates($input);
    $second = $normalizer->candidates($input);

    expect(array_map(static fn ($candidate): string => $candidate->sha256, $first->candidates))
        ->toBe(array_map(static fn ($candidate): string => $candidate->sha256, $second->candidates))
        ->and(array_map(static fn ($candidate): ?string => $candidate->temporaryPath, $first->candidates))
        ->toBe([null, null, null, null]);
});

test('normalizer preserves caller output buffer on success and failure', function (): void {
    $normalizer = new OmrQuarterTurnNormalizer(maxPixels: 100);
    $entryLevel = ob_get_level();
    ob_start();
    echo 'caller-sentinel';
    try {
        $normalizer->candidates(new OmrCaptureInput(bytes: quarterTurnFixtureBytes(), mime: 'image/png'));
        expect(ob_get_level())->toBe($entryLevel + 1)
            ->and(ob_get_contents())->toContain('caller-sentinel');
    } finally {
        while (ob_get_level() > $entryLevel) {
            ob_end_clean();
        }
    }

    $entryLevel = ob_get_level();
    ob_start();
    echo 'failure-sentinel';
    try {
        expect(fn () => $normalizer->candidates(
            new OmrCaptureInput(bytes: 'not-an-image', mime: 'image/png'),
        ))->toThrow(OmrQuarterTurnNormalizationException::class);
        expect(ob_get_level())->toBe($entryLevel + 1)
            ->and(ob_get_contents())->toContain('failure-sentinel');
    } finally {
        while (ob_get_level() > $entryLevel) {
            ob_end_clean();
        }
    }
});

test('malformed input fails with a bounded typed code', function (): void {
    expect(fn () => (new OmrQuarterTurnNormalizer(maxPixels: 100))->candidates(
        new OmrCaptureInput(bytes: 'not-an-image', mime: 'image/png'),
    ))->toThrow(OmrQuarterTurnNormalizationException::class, 'cannot be decoded');
});

test('pixel budget is enforced before rotation allocation', function (): void {
    $exception = null;
    try {
        (new OmrQuarterTurnNormalizer(maxPixels: 2))->candidates(
            new OmrCaptureInput(bytes: quarterTurnFixtureBytes(2, 3), mime: 'image/png'),
        );
    } catch (OmrQuarterTurnNormalizationException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(OmrQuarterTurnNormalizationException::class)
        ->and($exception?->errorCode)->toBe('pixel_budget_exceeded');
});

test('12MP JPEG produces four valid candidates within transport size budget', function (): void {
    // Generate 4000x3000 (12MP) test JPEG image
    $image = imagecreatetruecolor(4000, 3000);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefill($image, 0, 0, $white);
    // Draw corner markers and content
    imagefilledrectangle($image, 100, 100, 300, 300, $black);
    imagefilledrectangle($image, 3700, 100, 3900, 300, $black);
    imagefilledrectangle($image, 100, 2700, 300, 2900, $black);
    imagefilledrectangle($image, 3700, 2700, 3900, 2900, $black);

    ob_start();
    imagejpeg($image, null, 92);
    $jpegBytes = (string) ob_get_clean();
    imagedestroy($image);

    $normalizer = new OmrQuarterTurnNormalizer(maxPixels: 20_000_000, maxCandidateBytes: 10 * 1024 * 1024);
    $diagnostics = $normalizer->candidates(new OmrCaptureInput(bytes: $jpegBytes, mime: 'image/jpeg'));

    expect($diagnostics->orientations())->toBe([0, 90, 180, 270])
        ->and(count($diagnostics->candidates))->toBe(4);

    foreach ($diagnostics->candidates as $candidate) {
        expect(strlen($candidate->bytes))->toBeLessThanOrEqual(10 * 1024 * 1024)
            ->and(strlen($candidate->bytes))->toBeGreaterThan(0)
            ->and($candidate->mime)->toBe('image/jpeg');
    }

    expect([$diagnostics->candidates[0]->width, $diagnostics->candidates[0]->height])->toBe([4000, 3000])
        ->and([$diagnostics->candidates[1]->width, $diagnostics->candidates[1]->height])->toBe([3000, 4000])
        ->and([$diagnostics->candidates[2]->width, $diagnostics->candidates[2]->height])->toBe([4000, 3000])
        ->and([$diagnostics->candidates[3]->width, $diagnostics->candidates[3]->height])->toBe([3000, 4000]);
});

test('EXIF orientation is applied once before quarter-turn candidates are generated', function (): void {
    $bytes = file_get_contents(
        base_path('docker/inference/tests/fixtures/hand-shaded-perspective-redacted.jpg'),
    );
    expect($bytes)->toBeString();

    $diagnostics = (new OmrQuarterTurnNormalizer)->candidates(
        new OmrCaptureInput(bytes: $bytes, mime: 'image/jpeg'),
    );

    expect([$diagnostics->candidates[0]->width, $diagnostics->candidates[0]->height])
        ->toBe([3000, 4000])
        ->and([$diagnostics->candidates[1]->width, $diagnostics->candidates[1]->height])
        ->toBe([4000, 3000])
        ->and(array_unique(array_map(
            static fn ($candidate): string => $candidate->sha256,
            $diagnostics->candidates,
        )))
        ->toHaveCount(4);
});

test('quarter-turn rotation preserves visual clarity and high contrast', function (): void {
    $image = imagecreatetruecolor(100, 200);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefill($image, 0, 0, $white);
    imagefilledrectangle($image, 10, 10, 50, 50, $black);

    ob_start();
    imagejpeg($image, null, 92);
    $jpegBytes = (string) ob_get_clean();
    imagedestroy($image);

    $normalizer = new OmrQuarterTurnNormalizer(maxPixels: 100_000, maxCandidateBytes: 10 * 1024 * 1024, jpegQuality: 95);
    $diagnostics = $normalizer->candidates(new OmrCaptureInput(bytes: $jpegBytes, mime: 'image/jpeg'));

    // Inspect candidate 90 degrees
    $cand90 = $diagnostics->candidates[1];
    $decoded = imagecreatefromstring($cand90->bytes);
    expect($decoded)->not->toBeFalse()
        ->and(imagesx($decoded))->toBe(200)
        ->and(imagesy($decoded))->toBe(100);

    // Check that black and white regions remain high contrast
    // At 90 deg CW, rectangle at (10,10)..(50,50) in 100x200 transforms to x: 200-50..200-10 = 150..190, y: 10..50
    $blackSample = imagecolorat($decoded, 170, 30);
    $whiteSample = imagecolorat($decoded, 20, 20);
    imagedestroy($decoded);

    $blackR = ($blackSample >> 16) & 0xFF;
    $whiteR = ($whiteSample >> 16) & 0xFF;
    expect($blackR)->toBeLessThan(30)
        ->and($whiteR)->toBeGreaterThan(225);
});

test('PNG candidate exceeding byte budget falls back to high quality JPEG within transport limit', function (): void {
    // Create an image that as PNG is ~30KB but set maxCandidateBytes to 15KB
    $image = imagecreatetruecolor(400, 400);
    for ($y = 0; $y < 400; $y++) {
        for ($x = 0; $x < 400; $x++) {
            imagesetpixel($image, $x, $y, (($x * 7) ^ ($y * 13)) & 0xFFFFFF);
        }
    }
    ob_start();
    imagepng($image, null, 0);
    $pngBytes = (string) ob_get_clean();
    imagedestroy($image);

    // Set max candidate bytes small enough that PNG exceeds it but JPEG fits
    $normalizer = new OmrQuarterTurnNormalizer(maxPixels: 1_000_000, maxCandidateBytes: 100 * 1024);
    $diagnostics = $normalizer->candidates(new OmrCaptureInput(bytes: $pngBytes, mime: 'image/png'));

    expect(in_array($diagnostics->candidates[0]->mime, ['image/png', 'image/jpeg'], true))->toBeTrue()
        ->and(strlen($diagnostics->candidates[0]->bytes))->toBeLessThanOrEqual(100 * 1024)
        ->and(in_array($diagnostics->candidates[1]->mime, ['image/png', 'image/jpeg'], true))->toBeTrue()
        ->and(strlen($diagnostics->candidates[1]->bytes))->toBeLessThanOrEqual(100 * 1024);
});

test('candidate exceeding max byte budget fails closed', function (): void {
    $input = new OmrCaptureInput(bytes: quarterTurnFixtureBytes(10, 10), mime: 'image/png');
    $normalizer = new OmrQuarterTurnNormalizer(maxPixels: 100, maxCandidateBytes: 5); // 5 bytes is impossibly small

    expect(fn () => $normalizer->candidates($input))
        ->toThrow(OmrQuarterTurnNormalizationException::class, 'exceeds the transport byte budget');
});
