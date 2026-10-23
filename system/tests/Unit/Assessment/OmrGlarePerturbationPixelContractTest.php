<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * PIXEL-CONTRACT TEST (Digital Verification Glare Perturbation):
 *
 * Asserts that mild glare models a recoverable specular additive reflection
 * retaining local graphite contrast across covered bubbles, while severe glare
 * completely whitens the page and fiducials producing typed rejection.
 */
function runOmrVariant(string $sourcePath, string $outputPath, array $options): Process
{
    $process = new Process([
        PHP_BINARY,
        '-d',
        'memory_limit=512M',
        base_path('bin/omr-variant.php'),
        $sourcePath,
        $outputPath,
        json_encode($options, JSON_THROW_ON_ERROR),
    ]);
    $process->run();

    return $process;
}

test('mild glare retains measurable contrast for dark marked bubble inside glare mask', function (): void {
    $width = 800;
    $height = 1000;
    $image = imagecreatetruecolor($width, $height);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $white);

    // Draw a dark marked bubble at the glare mask center (0.52 * width, 0.46 * height)
    $cx = (int) round($width * 0.52);
    $cy = (int) round($height * 0.46);
    imagefilledellipse($image, $cx, $cy, 24, 24, $black);

    // Draw a nearby unmarked region on white paper
    $unmarkedX = $cx + 40;
    $unmarkedY = $cy;

    $tempSource = tempnam(sys_get_temp_dir(), 'omr_glare_src_').'.png';
    $tempOutput = tempnam(sys_get_temp_dir(), 'omr_glare_out_').'.png';
    imagepng($image, $tempSource);
    imagedestroy($image);

    try {
        $process = runOmrVariant($tempSource, $tempOutput, ['variant' => 'glare', 'severity' => 'mild']);
        expect($process->isSuccessful())->toBeTrue();

        $resultImage = imagecreatefrompng($tempOutput);
        expect($resultImage)->not->toBeFalse();

        // Sample marked center pixel vs unmarked paper pixel
        $markedRgb = imagecolorat($resultImage, $cx, $cy);
        $markedR = ($markedRgb >> 16) & 0xFF;
        $markedG = ($markedRgb >> 8) & 0xFF;
        $markedB = $markedRgb & 0xFF;
        $markedMean = ($markedR + $markedG + $markedB) / 3.0;

        $unmarkedRgb = imagecolorat($resultImage, $unmarkedX, $unmarkedY);
        $unmarkedR = ($unmarkedRgb >> 16) & 0xFF;
        $unmarkedG = ($unmarkedRgb >> 8) & 0xFF;
        $unmarkedB = $unmarkedRgb & 0xFF;
        $unmarkedMean = ($unmarkedR + $unmarkedG + $unmarkedB) / 3.0;

        $contrast = $unmarkedMean - $markedMean;

        // Mild glare must preserve measurable contrast (> 50 intensity levels)
        // and must NOT erase marked graphite pixels to pure white (255).
        expect($markedMean)->toBeLessThan(200.0)
            ->and($contrast)->toBeGreaterThan(50.0)
            ->and($unmarkedMean)->toBeGreaterThan(240.0);

        imagedestroy($resultImage);
    } finally {
        @unlink($tempSource);
        @unlink($tempOutput);
    }
});

test('severe glare completely erases marked bubbles and whiten fiducials for typed rejection', function (): void {
    $width = 800;
    $height = 1000;
    $image = imagecreatetruecolor($width, $height);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $white);

    // Draw dark marked bubble and top-left fiducial simulation
    $cx = (int) round($width * 0.52);
    $cy = (int) round($height * 0.46);
    imagefilledellipse($image, $cx, $cy, 24, 24, $black);
    imagefilledrectangle($image, 20, 20, 60, 60, $black);

    $tempSource = tempnam(sys_get_temp_dir(), 'omr_glare_src_').'.png';
    $tempOutput = tempnam(sys_get_temp_dir(), 'omr_glare_out_').'.png';
    imagepng($image, $tempSource);
    imagedestroy($image);

    try {
        $process = runOmrVariant($tempSource, $tempOutput, ['variant' => 'glare', 'severity' => 'severe']);
        expect($process->isSuccessful())->toBeTrue();

        $resultImage = imagecreatefrompng($tempOutput);
        expect($resultImage)->not->toBeFalse();

        // Sample center and fiducial area
        $markedRgb = imagecolorat($resultImage, $cx, $cy);
        $fiducialRgb = imagecolorat($resultImage, 40, 40);

        $markedMean = ((($markedRgb >> 16) & 0xFF) + (($markedRgb >> 8) & 0xFF) + ($markedRgb & 0xFF)) / 3.0;
        $fiducialMean = ((($fiducialRgb >> 16) & 0xFF) + (($fiducialRgb >> 8) & 0xFF) + ($fiducialRgb & 0xFF)) / 3.0;

        // Severe glare must erase everything under it to pure white
        expect($markedMean)->toBe(255.0)
            ->and($fiducialMean)->toBe(255.0);

        imagedestroy($resultImage);
    } finally {
        @unlink($tempSource);
        @unlink($tempOutput);
    }
});

test('mild glare handles palette-indexed PNG source images correctly', function (): void {
    $width = 400;
    $height = 500;
    // Create palette (indexed) image
    $image = imagecreate($width, $height);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);

    $cx = (int) round($width * 0.52);
    $cy = (int) round($height * 0.46);
    imagefilledellipse($image, $cx, $cy, 20, 20, $black);

    $tempSource = tempnam(sys_get_temp_dir(), 'omr_glare_palette_src_').'.png';
    $tempOutput = tempnam(sys_get_temp_dir(), 'omr_glare_palette_out_').'.png';
    imagepng($image, $tempSource);
    imagedestroy($image);

    try {
        $process = runOmrVariant($tempSource, $tempOutput, ['variant' => 'glare', 'severity' => 'mild']);
        expect($process->isSuccessful())->toBeTrue();

        $resultImage = imagecreatefrompng($tempOutput);
        expect($resultImage)->not->toBeFalse()
            ->and(imageistruecolor($resultImage))->toBeTrue();

        $markedRgb = imagecolorat($resultImage, $cx, $cy);
        $markedMean = ((($markedRgb >> 16) & 0xFF) + (($markedRgb >> 8) & 0xFF) + ($markedRgb & 0xFF)) / 3.0;

        expect($markedMean)->toBeLessThan(200.0);

        imagedestroy($resultImage);
    } finally {
        @unlink($tempSource);
        @unlink($tempOutput);
    }
});

test('mild uneven light is byte-deterministic across palette and RGB PNG inputs', function (): void {
    $width = 96;
    $height = 96;
    $sources = [];
    $outputs = [];

    foreach ([false, true] as $palette) {
        $image = $palette ? imagecreate($width, $height) : imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $white);
        imagefilledrectangle($image, 4, 4, 12, 12, $black);
        $source = tempnam(sys_get_temp_dir(), 'omr_uneven_source_').'.png';
        $output = tempnam(sys_get_temp_dir(), 'omr_uneven_output_').'.png';
        imagepng($image, $source);
        imagedestroy($image);
        $sources[] = $source;
        $outputs[] = $output;
    }

    try {
        foreach ($sources as $index => $source) {
            $process = runOmrVariant($source, $outputs[$index], ['variant' => 'uneven_light', 'severity' => 'mild']);
            expect($process->isSuccessful())->toBeTrue();
        }

        $first = file_get_contents($outputs[0]);
        $second = file_get_contents($outputs[1]);
        expect($first)->toBeString()->not->toBeEmpty()
            ->and($second)->toBe($first);

        $image = imagecreatefrompng($outputs[0]);
        $center = imagecolorat($image, (int) round($width * 0.64), (int) round($height * 0.58));
        $centerMean = ((($center >> 16) & 0xFF) + (($center >> 8) & 0xFF) + ($center & 0xFF)) / 3.0;
        expect(imageistruecolor($image))->toBeTrue()
            ->and($centerMean)->toBeGreaterThan(0.0)
            ->and($centerMean)->toBeLessThan(255.0);
        imagedestroy($image);
    } finally {
        foreach (array_merge($sources, $outputs) as $path) {
            @unlink($path);
        }
    }
});
