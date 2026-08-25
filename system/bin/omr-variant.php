<?php

declare(strict_types=1);

if ($argc !== 4) {
    exit(2);
}

[, $sourcePath, $outputPath, $optionsJson] = $argv;
$options = json_decode($optionsJson, true);
$variant = (string) ($options['variant'] ?? '');
$allowed = ['perspective', 'rotation', 'uneven_light', 'blur', 'glare', 'low_resolution', 'partial_page', 'missing_marker', 'wrong_marker'];
if (! is_array($options) || ! in_array($variant, $allowed, true)) {
    exit(3);
}

$image = @imagecreatefrompng($sourcePath);
if ($image === false) {
    exit(4);
}
$width = imagesx($image);
$height = imagesy($image);
$white = imagecolorallocate($image, 255, 255, 255);

if ($variant === 'blur') {
    $wash = imagecolorallocatealpha($image, 128, 128, 128, 72);
    imagefilledellipse($image, (int) round($width * .52), (int) round($height * .48), max(32, (int) round($width * .78)), max(32, (int) round($height * .78)), $wash);
    imagefilledellipse($image, (int) round($width * .52), (int) round($height * .48), max(32, (int) round($width * .58)), max(32, (int) round($height * .58)), $wash);
} elseif ($variant === 'uneven_light') {
    $shadow = imagecolorallocatealpha($image, 0, 0, 0, 70);
    imagefilledellipse($image, (int) round($width * .64), (int) round($height * .58), max(32, (int) round($width * .72)), max(32, (int) round($height * .62)), $shadow);
} elseif ($variant === 'glare') {
    imagefilledellipse($image, (int) round($width * .52), (int) round($height * .46), max(32, (int) round($width * .72)), max(32, (int) round($height * .28)), $white);
} elseif ($variant === 'low_resolution') {
    $smallWidth = max(64, (int) round($width * .22));
    $smallHeight = max(64, (int) round($height * .22));
    $small = imagecreatetruecolor($smallWidth, $smallHeight);
    imagecopyresampled($small, $image, 0, 0, 0, 0, $smallWidth, $smallHeight, $width, $height);
    imagecopyresampled($image, $small, 0, 0, 0, 0, $width, $height, $smallWidth, $smallHeight);
    imagedestroy($small);
} elseif ($variant === 'rotation' || $variant === 'perspective') {
    $angle = $variant === 'rotation' ? 9 : 14;
    $smallWidth = max(128, (int) round($width * .24));
    $smallHeight = max(128, (int) round($height * .24));
    $small = imagecreatetruecolor($smallWidth, $smallHeight);
    imagecopyresampled($small, $image, 0, 0, 0, 0, $smallWidth, $smallHeight, $width, $height);
    $rotated = imagerotate($small, $angle, 255);
    if ($rotated !== false) {
        imagecopyresampled($image, $rotated, 0, 0, 0, 0, $width, $height, imagesx($rotated), imagesy($rotated));
        imagedestroy($rotated);
    }
    imagedestroy($small);
} elseif ($variant === 'partial_page') {
    imagefilledrectangle($image, (int) ($width * .68), (int) ($height * .68), $width, $height, $white);
} elseif ($variant === 'missing_marker' || $variant === 'wrong_marker') {
    $markers = (array) ($options['markers'] ?? []);
    $insets = (array) ($options['insets_px'] ?? []);
    $size = (int) ($markers['size'] ?? 0);
    $quiet = (int) ($markers['quiet_zone'] ?? 0);
    $half = $size + $quiet;
    $fill = imagecolorallocate($image, $variant === 'wrong_marker' ? 0 : 255, $variant === 'wrong_marker' ? 0 : 255, $variant === 'wrong_marker' ? 0 : 255);
    foreach ((array) ($markers['centers'] ?? []) as $center) {
        $cx = (int) ($insets['left'] ?? 0) + (int) ($center[0] ?? 0);
        $cy = (int) ($insets['top'] ?? 0) + (int) ($center[1] ?? 0);
        imagefilledrectangle($image, $cx - $half, $cy - $half, $cx + $half, $cy + $half, $fill);
        if ($variant === 'wrong_marker') {
            $small = imagecolorallocate($image, 0, 0, 0);
            imagefilledrectangle($image, $cx - intdiv(max(8, $size), 4), $cy - intdiv(max(8, $size), 4), $cx + intdiv(max(8, $size), 4), $cy + intdiv(max(8, $size), 4), $small);
        }
    }
}

imagesavealpha($image, false);
$success = imagepng($image, $outputPath, 6);
imagedestroy($image);
exit($success ? 0 : 5);
