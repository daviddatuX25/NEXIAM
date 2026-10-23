<?php

namespace Tests\Unit\DocumentTemplate;

use App\DocumentTemplate\Services\DynamicAssetResolutionService;

use function file_exists;
use function getimagesizefromstring;
use function imagecreatetruecolor;
use function imagedestroy;
use function imagefilledrectangle;
use function imagepng;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

test('it renders verification QR codes as PNG bytes', function (): void {
    $png = (new DynamicAssetResolutionService)->renderVerificationQr('https://nexiam.test/verify/123');

    expect($png)->toStartWith("\x89PNG\r\n\x1a\n");
});

test('it renders the photo affix fallback at the requested pixel dimensions', function (): void {
    $png = (new DynamicAssetResolutionService)->renderPhotoAffixFallback(914400, 457200);
    $image = getimagesizefromstring($png);

    expect($png)->toStartWith("\x89PNG\r\n\x1a\n")
        ->and($image[0])->toBe(300)
        ->and($image[1])->toBe(150);
});

test('it falls back when the candidate photo path is null or missing', function (): void {
    $service = new DynamicAssetResolutionService;
    $fallback = $service->renderPhotoAffixFallback();

    expect($service->resolveCandidatePhoto(null))->toBe($fallback)
        ->and($service->resolveCandidatePhoto(sys_get_temp_dir().'/missing-candidate-photo.png'))->toBe($fallback);
});

test('it crops and resizes a candidate photo to the requested dimensions', function (): void {
    $service = new DynamicAssetResolutionService;
    $path = tempnam(sys_get_temp_dir(), 'candidate-photo-');
    $source = imagecreatetruecolor(120, 90);
    imagefilledrectangle($source, 0, 0, 119, 89, 0x285078);
    imagepng($source, $path);
    imagedestroy($source);

    try {
        $png = $service->resolveCandidatePhoto($path, 914400, 457200);
        $image = getimagesizefromstring($png);

        expect($png)->toStartWith("\x89PNG\r\n\x1a\n")
            ->and($image[0])->toBe(300)
            ->and($image[1])->toBe(150);
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

test('it converts EMUs and pixels using the requested DPI', function (): void {
    $service = new DynamicAssetResolutionService;

    expect($service->emuToPixels(1828800))->toBe(600)
        ->and($service->emuToPixels(914400, 150))->toBe(150)
        ->and($service->pixelsToEmu(600))->toBe(1828800)
        ->and($service->pixelsToEmu(150, 150))->toBe(914400);
});
