<?php

declare(strict_types=1);

if ($argc !== 4) {
    exit(2);
}

[, $sourcePath, $outputPath, $optionsJson] = $argv;
$rawOptions = is_file($optionsJson) ? (file_get_contents($optionsJson) ?: '') : $optionsJson;
$options = is_string($rawOptions) ? json_decode($rawOptions, true) : null;
$transform = is_array($options['transform'] ?? null) ? $options['transform'] : [];
$variant = (string) ($options['variant'] ?? $transform['kind'] ?? $transform['condition'] ?? '');
$allowed = [
    'perspective', 'rotation', 'uneven_light', 'uneven_lighting', 'blur', 'glare',
    'low_resolution', 'partial_page', 'missing_marker', 'wrong_marker',
    'phone', 'low_contrast', 'shadow', 'clean', 'clean_scanner', 'identity',
    'rotation_perspective', 'lighting_contrast', 'blur_shadow',
    'mild_shear', 'cropped_page', 'background', 'aspect_mismatch', 'ambiguous_boundary',
];
if (! is_array($options) || ! in_array($variant, $allowed, true)) {
    exit(3);
}

// Severity axis (task 35.43 characterization): `mild` is the perturbation
// set proven (pinned fixture evidence) to preserve exact answer recovery;
// `severe` is the aggressive parameterization the policy table expects to
// produce typed rejection. Recognition math is untouched: this script only
// mutates the INPUT image, never any detector threshold.
$severity = (string) ($options['severity'] ?? 'mild');
if (! in_array($severity, ['mild', 'severe'], true)) {
    exit(3);
}
$severe = $severity === 'severe';

$image = @imagecreatefrompng($sourcePath);
if ($image === false) {
    exit(4);
}
// GD keeps the source image's colour model and blending flag. Normalize both
// before any transform so the same decoded pixels produce the same variant
// regardless of whether the source PNG is palette, grayscale, or RGB.
if (! imageistruecolor($image) && ! imagepalettetotruecolor($image)) {
    imagedestroy($image);
    exit(4);
}
imagealphablending($image, true);
imagesavealpha($image, false);
$width = imagesx($image);
$height = imagesy($image);
$white = imagecolorallocate($image, 255, 255, 255);
$registrationMode = (string) ($options['registration_mode'] ?? 'fiducial_anchored');
$paperBounds = is_array($options['paper_bounds'] ?? null) ? $options['paper_bounds'] : [];
$paperX = $paperBounds['x'] ?? null;
$paperY = $paperBounds['y'] ?? null;
$paperWidth = $paperBounds['width'] ?? null;
$paperHeight = $paperBounds['height'] ?? null;
$documentVariants = ['mild_shear', 'cropped_page', 'background', 'aspect_mismatch', 'ambiguous_boundary'];
$paperBoundsValid = is_int($paperX) && is_int($paperY) && is_int($paperWidth) && is_int($paperHeight)
    && $paperX >= 0 && $paperY >= 0 && $paperWidth > 0 && $paperHeight > 0
    && $paperX + $paperWidth <= $width && $paperY + $paperHeight <= $height;
if (in_array($variant, $documentVariants, true)
    && ($registrationMode !== 'document_bound' || ! $paperBoundsValid)) {
    imagedestroy($image);
    exit(3);
}
$gray = imagecolorallocate($image, 128, 128, 128);

$applyBlur = static function (GdImage $img, bool $isSevere, ?float $radius = null): void {
    $passes = $radius !== null ? max(1, (int) round($radius * 2)) : ($isSevere ? 10 : 1);
    for ($i = 0; $i < $passes; $i++) {
        $ok = $isSevere
            ? imagefilter($img, IMG_FILTER_SMOOTH, -6)
            : imagefilter($img, IMG_FILTER_GAUSSIAN_BLUR);
        if (! $ok) {
            exit(6);
        }
    }
};

$applyUnevenLight = static function (GdImage $img, int $w, int $h, bool $isSevere, ?float $gradient = null): void {
    $alphaVal = $gradient !== null ? max(20, min(120, (int) round($gradient * 255))) : ($isSevere ? 127 : 80);
    $shadow = imagecolorallocatealpha($img, 0, 0, 0, $alphaVal);
    imagefilledellipse($img, (int) round($w * .64), (int) round($h * .58), max(32, (int) round($w * ($isSevere ? .86 : .56))), max(32, (int) round($h * ($isSevere ? .8 : .48))), $shadow);
};

$applyLowContrast = static function (GdImage $img, bool $isSevere, ?float $contrast = null): void {
    $level = $contrast !== null ? (int) round((1.0 - $contrast) * -60) : ($isSevere ? -45 : -18);
    imagefilter($img, IMG_FILTER_CONTRAST, $level);
};

$applyShadow = static function (GdImage $img, int $w, int $h, bool $isSevere, ?float $opacity = null): void {
    $alpha = $opacity !== null ? max(10, min(120, (int) round((1.0 - $opacity) * 127))) : ($isSevere ? 60 : 95);
    $shadow = imagecolorallocatealpha($img, 30, 30, 30, $alpha);
    imagefilledrectangle($img, 0, (int) round($h * .48), $w, $h, $shadow);
};

$applyRotation = static function (GdImage $img, int $w, int $h, bool $isSevere, ?float $customAngle = null): void {
    $angle = $customAngle !== null ? (int) round($customAngle) : ($isSevere ? 45 : 9);
    if ($angle === 0) {
        return;
    }
    $smallWidth = max(128, (int) round($w * .24));
    $smallHeight = max(128, (int) round($h * .24));
    $small = imagecreatetruecolor($smallWidth, $smallHeight);
    imagecopyresampled($small, $img, 0, 0, 0, 0, $smallWidth, $smallHeight, $w, $h);
    $rotated = imagerotate($small, $angle, 255);
    if ($rotated !== false) {
        imagecopyresampled($img, $rotated, 0, 0, 0, 0, $w, $h, imagesx($rotated), imagesy($rotated));
        imagedestroy($rotated);
    }
    imagedestroy($small);
};

if ($variant === 'clean' || $variant === 'clean_scanner' || $variant === 'identity') {
    // Identity transform: source unchanged
} elseif ($variant === 'blur') {
    $radius = isset($transform['radius']) ? (float) $transform['radius'] : null;
    $applyBlur($image, $severe, $radius);
} elseif ($variant === 'uneven_light' || $variant === 'uneven_lighting') {
    $gradient = isset($transform['gradient']) ? (float) $transform['gradient'] : null;
    $applyUnevenLight($image, $width, $height, $severe, $gradient);
} elseif ($variant === 'low_contrast') {
    $contrast = isset($transform['contrast']) ? (float) $transform['contrast'] : null;
    $applyLowContrast($image, $severe, $contrast);
} elseif ($variant === 'shadow') {
    $opacity = isset($transform['opacity']) ? (float) $transform['opacity'] : null;
    $applyShadow($image, $width, $height, $severe, $opacity);
} elseif ($variant === 'phone') {
    $ratio = isset($transform['downsample_ratio']) ? (float) $transform['downsample_ratio'] : ($severe ? .18 : .88);
    $phoneWidth = max(64, (int) round($width * $ratio));
    $phoneHeight = max(64, (int) round($height * $ratio));
    $small = imagecreatetruecolor($phoneWidth, $phoneHeight);
    imagecopyresampled($small, $image, 0, 0, 0, 0, $phoneWidth, $phoneHeight, $width, $height);
    imagecopyresampled($image, $small, 0, 0, 0, 0, $width, $height, $phoneWidth, $phoneHeight);
    imagedestroy($small);
    imagefilter($image, IMG_FILTER_CONTRAST, -8);
} elseif ($variant === 'mild_shear') {
    $matrix = [1.0, 0.0, 0.03, 1.0, -0.015 * $height, 0.0];
    imagesavealpha($image, true);
    $warped = imageaffine($image, $matrix, ['x' => 0, 'y' => 0, 'width' => $width, 'height' => $height]);
    if ($warped === false) {
        imagedestroy($image);
        exit(6);
    }
    $canvas = imagecreatetruecolor($width, $height);
    $canvasGray = imagecolorallocate($canvas, 128, 128, 128);
    imagefilledrectangle($canvas, 0, 0, $width, $height, $canvasGray);
    imagealphablending($canvas, true);
    imagecopy($canvas, $warped, 0, 0, 0, 0, $width, $height);
    imagedestroy($warped);
    imagedestroy($image);
    $image = $canvas;
} elseif ($variant === 'rotation' && $registrationMode === 'document_bound') {
    $angle = isset($transform['degrees']) ? (float) $transform['degrees'] : 3.0;
    $rotated = imagerotate($image, $angle, $gray);
    if ($rotated === false) {
        imagedestroy($image);
        exit(6);
    }
    imagedestroy($image);
    $image = $rotated;
    $width = imagesx($image);
    $height = imagesy($image);
} elseif ($variant === 'rotation') {
    $angle = isset($transform['degrees']) ? (float) $transform['degrees'] : null;
    $applyRotation($image, $width, $height, $severe, $angle);
} elseif ($variant === 'perspective') {
    $angle = isset($transform['degrees']) ? (float) $transform['degrees'] : ($severe ? 30 : 14);
    $smallWidth = max(128, (int) round($width * .24));
    $smallHeight = max(128, (int) round($height * .24));
    $small = imagecreatetruecolor($smallWidth, $smallHeight);
    imagecopyresampled($small, $image, 0, 0, 0, 0, $smallWidth, $smallHeight, $width, $height);
    $rotated = imagerotate($small, (int) round($angle), 255);
    if ($rotated !== false) {
        imagecopyresampled($image, $rotated, 0, 0, 0, 0, $width, $height, imagesx($rotated), imagesy($rotated));
        imagedestroy($rotated);
    }
    imagedestroy($small);
} elseif ($variant === 'rotation_perspective') {
    $angle = isset($transform['degrees']) ? (float) $transform['degrees'] : ($severe ? 25 : 8);
    $applyRotation($image, $width, $height, $severe, $angle);
} elseif ($variant === 'lighting_contrast') {
    $gradient = isset($transform['gradient']) ? (float) $transform['gradient'] : null;
    $contrast = isset($transform['contrast']) ? (float) $transform['contrast'] : null;
    $applyUnevenLight($image, $width, $height, $severe, $gradient);
    $applyLowContrast($image, $severe, $contrast);
} elseif ($variant === 'blur_shadow') {
    $radius = isset($transform['radius']) ? (float) $transform['radius'] : null;
    $opacity = isset($transform['opacity']) ? (float) $transform['opacity'] : null;
    $applyBlur($image, $severe, $radius);
    $applyShadow($image, $width, $height, $severe, $opacity);
} elseif ($variant === 'glare') {
    if ($severe) {
        imagefilledellipse($image, (int) round($width * .52), (int) round($height * .46), max(32, (int) round($width * 1.3)), max(32, (int) round($height * 1.3)), $white);
    } else {
        imagealphablending($image, true);
        $glare = imagecolorallocatealpha($image, 255, 255, 255, 96);
        imagefilledellipse($image, (int) round($width * .52), (int) round($height * .46), max(32, (int) round($width * .62)), max(32, (int) round($height * .24)), $glare);
    }
} elseif ($variant === 'low_resolution') {
    $scale = $severe ? .04 : .22;
    $smallWidth = max(64, (int) round($width * $scale));
    $smallHeight = max(64, (int) round($height * $scale));
    $small = imagecreatetruecolor($smallWidth, $smallHeight);
    imagecopyresampled($small, $image, 0, 0, 0, 0, $smallWidth, $smallHeight, $width, $height);
    imagecopyresampled($image, $small, 0, 0, 0, 0, $width, $height, $smallWidth, $smallHeight);
    imagedestroy($small);
} elseif ($variant === 'partial_page') {
    $fraction = isset($transform['visible_fraction']) ? (float) $transform['visible_fraction'] : .68;
    $cropX = (int) round($width * max(0.1, min(0.95, $fraction)));
    $cropY = (int) round($height * max(0.1, min(0.95, $fraction)));
    imagefilledrectangle($image, $cropX, $cropY, $width, $height, $white);
} elseif ($variant === 'cropped_page') {
    $cropX = min($width - 1, $paperX + (int) round($paperWidth * .30));
    $cropY = min($height - 1, $paperY + (int) round($paperHeight * .10));
    $cropped = imagecrop($image, [
        'x' => $cropX,
        'y' => $cropY,
        'width' => $width - $cropX,
        'height' => $height - $cropY,
    ]);
    if ($cropped === false) {
        imagedestroy($image);
        exit(6);
    }
    imagedestroy($image);
    $image = $cropped;
    $width = imagesx($image);
    $height = imagesy($image);
} elseif ($variant === 'background') {
    $paper = imagecreatetruecolor($paperWidth, $paperHeight);
    imagecopy($paper, $image, 0, 0, $paperX, $paperY, $paperWidth, $paperHeight);
    imagefilledrectangle($image, 0, 0, $width, $height, $white);
    imagecopy($image, $paper, $paperX, $paperY, 0, 0, $paperWidth, $paperHeight);
    imagedestroy($paper);
} elseif ($variant === 'aspect_mismatch') {
    $newWidth = (int) round($width * 1.45);
    $stretched = imagecreatetruecolor($newWidth, $height);
    $stretchedGray = imagecolorallocate($stretched, 128, 128, 128);
    imagefilledrectangle($stretched, 0, 0, $newWidth, $height, $stretchedGray);
    imagecopyresampled($stretched, $image, 0, 0, 0, 0, $newWidth, $height, $width, $height);
    imagedestroy($image);
    $image = $stretched;
    $width = $newWidth;
} elseif ($variant === 'ambiguous_boundary') {
    $gap = max(24, $paperX);
    $canvasWidth = 2 * $paperWidth + 3 * $gap;
    $canvasHeight = $paperHeight + 2 * $paperY;
    $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
    $canvasGray = imagecolorallocate($canvas, 128, 128, 128);
    imagefilledrectangle($canvas, 0, 0, $canvasWidth, $canvasHeight, $canvasGray);
    imagecopy($canvas, $image, $gap, $paperY, $paperX, $paperY, $paperWidth, $paperHeight);
    imagecopy($canvas, $image, $paperWidth + 2 * $gap, $paperY, $paperX, $paperY, $paperWidth, $paperHeight);
    imagedestroy($image);
    $image = $canvas;
    $width = $canvasWidth;
    $height = $canvasHeight;
} elseif ($variant === 'missing_marker' || $variant === 'wrong_marker') {
    $markers = (array) ($options['markers'] ?? []);
    $insets = (array) ($options['insets_px'] ?? []);
    $size = (int) ($markers['size'] ?? 0);
    $quiet = (int) ($markers['quiet_zone'] ?? 0);
    $severe = ($options['severity'] ?? 'mild') === 'severe';
    $half = $size + $quiet + ($severe ? max(8, $size) : 0);
    $wrongMarker = $variant === 'wrong_marker';
    $fillValue = $wrongMarker && ! $severe ? 0 : 255;
    $fill = imagecolorallocate($image, $fillValue, $fillValue, $fillValue);
    foreach ((array) ($markers['centers'] ?? []) as $center) {
        $cx = (int) ($insets['left'] ?? 0) + (int) ($center[0] ?? 0);
        $cy = (int) ($insets['top'] ?? 0) + (int) ($center[1] ?? 0);
        imagefilledrectangle($image, $cx - $half, $cy - $half, $cx + $half, $cy + $half, $fill);
        if ($wrongMarker) {
            $small = imagecolorallocate($image, 0, 0, 0);
            $innerHalf = $severe ? max(8, $size) : intdiv(max(8, $size), 4);
            imagefilledrectangle($image, $cx - $innerHalf, $cy - $innerHalf, $cx + $innerHalf, $cy + $innerHalf, $small);
        }
    }
}

imagesavealpha($image, false);
$success = imagepng($image, $outputPath, 6);
imagedestroy($image);
exit($success ? 0 : 5);
