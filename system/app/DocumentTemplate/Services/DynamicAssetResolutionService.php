<?php

namespace App\DocumentTemplate\Services;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Throwable;

use function exif_imagetype;
use function file_get_contents;
use function floor;
use function imagecolorallocate;
use function imagecopyresampled;
use function imagecreatefromstring;
use function imagecreatetruecolor;
use function imagedestroy;
use function imagefilledrectangle;
use function imagefontheight;
use function imagefontwidth;
use function imageline;
use function imagepng;
use function imagesetstyle;
use function imagestring;
use function imagesx;
use function imagesy;
use function is_file;
use function max;
use function ob_get_clean;
use function ob_start;
use function round;
use function strlen;

final class DynamicAssetResolutionService
{
    public const EMU_PER_INCH = 914400;

    public const DEFAULT_DPI = 300;

    public function renderVerificationQr(string $url, int $size = 600, int $margin = 10): string
    {
        $qrCode = new QrCode(data: $url, size: $size, margin: $margin);

        return (new PngWriter)->write($qrCode)->getString();
    }

    public function resolveCandidatePhoto(?string $path, int $widthEmu = 1828800, int $heightEmu = 1828800): string
    {
        if ($path === null || ! is_file($path) || ! file_exists($path)) {
            return $this->renderPhotoAffixFallback($widthEmu, $heightEmu);
        }

        try {
            if (@exif_imagetype($path) === false) {
                return $this->renderPhotoAffixFallback($widthEmu, $heightEmu);
            }

            $bytes = @file_get_contents($path);
            $source = $bytes === false ? false : @imagecreatefromstring($bytes);
        } catch (Throwable) {
            return $this->renderPhotoAffixFallback($widthEmu, $heightEmu);
        }

        if ($source === false) {
            return $this->renderPhotoAffixFallback($widthEmu, $heightEmu);
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $targetWidth = max(1, $this->emuToPixels($widthEmu));
        $targetHeight = max(1, $this->emuToPixels($heightEmu));
        $sourceRatio = $sourceWidth / $sourceHeight;
        $targetRatio = $targetWidth / $targetHeight;

        if ($sourceRatio > $targetRatio) {
            $cropWidth = (int) round($sourceHeight * $targetRatio);
            $sourceX = (int) floor(($sourceWidth - $cropWidth) / 2);
            $sourceY = 0;
        } else {
            $cropHeight = (int) round($sourceWidth / $targetRatio);
            $sourceX = 0;
            $sourceY = (int) floor(($sourceHeight - $cropHeight) / 2);
            $cropWidth = $sourceWidth;
        }

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled(
            $resized,
            $source,
            0,
            0,
            $sourceX,
            $sourceY,
            $targetWidth,
            $targetHeight,
            $cropWidth,
            $sourceRatio > $targetRatio ? $sourceHeight : $cropHeight,
        );

        imagedestroy($source);

        return $this->encodePng($resized);
    }

    public function renderPhotoAffixFallback(int $widthEmu = 1828800, int $heightEmu = 1828800): string
    {
        $width = max(1, $this->emuToPixels($widthEmu));
        $height = max(1, $this->emuToPixels($heightEmu));
        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $gray = imagecolorallocate($image, 156, 163, 175);
        $text = imagecolorallocate($image, 75, 85, 99);

        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $white);
        imagesetstyle($image, [$gray, $gray, $gray, $gray, $white, $white]);
        imageline($image, 2, 2, $width - 3, 2, IMG_COLOR_STYLED);
        imageline($image, 2, $height - 3, $width - 3, $height - 3, IMG_COLOR_STYLED);
        imageline($image, 2, 2, 2, $height - 3, IMG_COLOR_STYLED);
        imageline($image, $width - 3, 2, $width - 3, $height - 3, IMG_COLOR_STYLED);

        $font = 5;
        $label = 'AFFIX 2X2 PHOTO HERE';
        $subtext = 'CANDIDATE ID';
        $labelX = max(3, (int) (($width - imagefontwidth($font) * strlen($label)) / 2));
        $subtextX = max(3, (int) (($width - imagefontwidth($font) * strlen($subtext)) / 2));
        $labelY = max(3, (int) (($height - imagefontheight($font) * 2 - 8) / 2));

        imagestring($image, $font, $labelX, $labelY, $label, $text);
        imagestring($image, $font, $subtextX, $labelY + imagefontheight($font) + 8, $subtext, $gray);

        return $this->encodePng($image);
    }

    public function emuToPixels(int $emu, int $dpi = self::DEFAULT_DPI): int
    {
        return (int) round(($emu / self::EMU_PER_INCH) * $dpi);
    }

    public function pixelsToEmu(int $pixels, int $dpi = self::DEFAULT_DPI): int
    {
        return (int) round(($pixels / $dpi) * self::EMU_PER_INCH);
    }

    private function encodePng(\GdImage $image): string
    {
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes === false ? '' : $bytes;
    }
}
