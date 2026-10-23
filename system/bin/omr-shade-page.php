<?php

declare(strict_types=1);

use App\Assessment\Omr\Canonical\OmrQrReservationResolver;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTargetException;
use App\Assessment\Omr\Diagnostics\OmrManifestBubbleShader;

require_once dirname(__DIR__).'/vendor/autoload.php';

if ($argc !== 4) {
    fwrite(STDERR, "Usage: omr-shade-page.php <sourcePath> <outputPath> <optionsPath>\n");
    exit(2);
}

[, $sourcePath, $outputPath, $optionsPath] = $argv;

if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
    fwrite(STDERR, "source_file_missing: {$sourcePath}\n");
    exit(3);
}

if (! is_file($optionsPath) || ! is_readable($optionsPath)) {
    fwrite(STDERR, "options_file_missing: {$optionsPath}\n");
    exit(3);
}

$rawOptions = file_get_contents($optionsPath);
if ($rawOptions === false) {
    fwrite(STDERR, "options_read_failed\n");
    exit(3);
}

try {
    $options = json_decode($rawOptions, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, 'options_json_invalid: '.$e->getMessage()."\n");
    exit(3);
}

if (! is_array($options)) {
    fwrite(STDERR, "options_not_array\n");
    exit(3);
}

$pageNo = (int) ($options['page_no'] ?? 1);
$geometryIndex = (array) ($options['geometry_index'] ?? []);
$expectedVector = (array) ($options['expected_vector'] ?? []);
$calibrationDigits = (array) ($options['calibration_digits'] ?? []);
$scenarios = (array) ($options['scenarios'] ?? []);
$insetsPx = (array) ($options['insets_px'] ?? []);
$qrPath = isset($options['qr_path']) ? (string) $options['qr_path'] : null;
$displayCode = (string) ($options['display_code'] ?? '');

$image = @imagecreatefrompng($sourcePath);
if ($image === false) {
    $sourceBytes = file_get_contents($sourcePath);
    if ($sourceBytes !== false) {
        $image = @imagecreatefromstring($sourceBytes);
    }
}

if ($image === false) {
    fwrite(STDERR, "source_image_create_failed\n");
    exit(4);
}

if (! imageistruecolor($image) && ! imagepalettetotruecolor($image)) {
    imagedestroy($image);
    fwrite(STDERR, "image_not_truecolor\n");
    exit(4);
}

try {
    $black = imagecolorallocate($image, 0, 0, 0);
    $shader = new OmrManifestBubbleShader;
    if ($scenarios === []) {
        $shader->shadePage($image, $geometryIndex, $pageNo, $expectedVector, $calibrationDigits, $insetsPx, $black);
    } else {
        $shader->shadePage($image, $geometryIndex, $pageNo, [], $calibrationDigits, $insetsPx, $black);
        foreach ($scenarios as $scenario) {
            if (is_array($scenario)) {
                $shader->shadeScenario($image, $geometryIndex, $pageNo, $scenario, $insetsPx, $black);
            }
        }
    }

    if ($qrPath !== null && is_file($qrPath)) {
        $qrImage = @imagecreatefrompng($qrPath);
        if ($qrImage === false) {
            $qrBytes = file_get_contents($qrPath);
            if ($qrBytes !== false) {
                $qrImage = @imagecreatefromstring($qrBytes);
            }
        }
        if ($qrImage === false) {
            fwrite(STDERR, "qr_image_create_failed\n");
            exit(5);
        }
        try {
            $reviewManifest = (array) ($options['review_manifest'] ?? $options['reviewManifest'] ?? []);
            $placement = OmrQrReservationResolver::resolvePx(
                $reviewManifest !== [] ? $reviewManifest : $options,
                imagesx($image),
                imagesy($image),
                $insetsPx,
            );
            $qrSize = $placement['qr_size'];
            $qrX = $placement['qr_x'];
            $qrY = $placement['qr_y'];
            $displayCodeX = $placement['display_code_x'];
            $displayCodeY = $placement['display_code_y'];
            imagecopyresampled($image, $qrImage, $qrX, $qrY, 0, 0, $qrSize, $qrSize, imagesx($qrImage), imagesy($qrImage));
            if ($displayCode !== '') {
                imagestring($image, 3, $displayCodeX, $displayCodeY, $displayCode, $black);
            }
        } finally {
            imagedestroy($qrImage);
        }
    }

    imagesavealpha($image, false);
    $saved = imagepng($image, $outputPath, 6);
    if (! $saved) {
        fwrite(STDERR, "output_png_save_failed\n");
        exit(6);
    }
} catch (OmrDiagnosticTargetException $e) {
    fwrite(STDERR, 'render_target_exception: '.$e->errorCode.': '.$e->getMessage()."\n");
    exit(7);
} catch (Throwable $e) {
    fwrite(STDERR, 'render_exception: '.$e->getMessage()."\n");
    exit(8);
} finally {
    imagedestroy($image);
}

exit(0);
