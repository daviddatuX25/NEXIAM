<?php

namespace App\Support\Media;

use RuntimeException;
use Symfony\Component\Process\Process;

use function chmod;
use function file_get_contents;
use function file_put_contents;
use function filesize;
use function getimagesizefromstring;
use function glob;
use function is_array;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function rmdir;
use function str_starts_with;
use function strlen;
use function sys_get_temp_dir;
use function unlink;
use function usleep;

final class PdfThumbnailRasterizer
{
    private const MAX_INPUT_BYTES = 25 * 1024 * 1024;

    private const MAX_OUTPUT_BYTES = 1024 * 1024;

    private const MAX_PROCESS_SECONDS = 10.0;

    private const MAX_WIDTH = 240;

    private const MAX_INTERMEDIATE_EDGE = 480;

    private const MAX_INTERMEDIATE_PIXELS = 230_400;

    private const MAX_OUTPUT_PIXELS = 115_200;

    public function rasterizeFirstPage(string $pdfBytes): string
    {
        if ($pdfBytes === '' || strlen($pdfBytes) > self::MAX_INPUT_BYTES) {
            throw new RuntimeException('pdf_thumbnail_input_invalid');
        }

        $directory = sys_get_temp_dir().'/nexiam-pdf-thumbnail-'.bin2hex(random_bytes(16));

        $createdDirectory = false;
        try {
            if (! mkdir($directory, 0700)) {
                throw new RuntimeException('pdf_thumbnail_temp_unavailable');
            }
            $createdDirectory = true;
            $pdfPath = $directory.'/source.pdf';
            $outputPrefix = $directory.'/page';
            if (file_put_contents($pdfPath, $pdfBytes, LOCK_EX) !== strlen($pdfBytes)) {
                throw new RuntimeException('pdf_thumbnail_temp_unavailable');
            }
            chmod($pdfPath, 0600);

            $process = new Process([
                'pdftoppm',
                '-q',
                '-png',
                '-r', '150',
                '-f', '1',
                '-l', '1',
                '-scale-to', (string) self::MAX_INTERMEDIATE_EDGE,
                $pdfPath,
                $outputPrefix,
            ]);
            $process->setTimeout(self::MAX_PROCESS_SECONDS);
            $process->disableOutput();
            $pngPath = $outputPrefix.'-1.png';
            $process->start();
            while ($process->isRunning()) {
                if (is_file($pngPath)) {
                    $outputSize = filesize($pngPath);
                    if ($outputSize !== false && $outputSize > self::MAX_OUTPUT_BYTES) {
                        $process->stop(0.1);
                        throw new RuntimeException('pdf_thumbnail_output_too_large');
                    }
                }

                $process->checkTimeout();
                usleep(10_000);
            }
            $process->wait();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('pdf_thumbnail_rasterization_failed');
            }

            if (! is_file($pngPath)) {
                throw new RuntimeException('pdf_thumbnail_output_missing');
            }

            $outputSize = filesize($pngPath);
            if ($outputSize === false || $outputSize > self::MAX_OUTPUT_BYTES) {
                throw new RuntimeException('pdf_thumbnail_output_too_large');
            }

            $intermediateBytes = file_get_contents($pngPath);
            if (! is_string($intermediateBytes)
                || ! str_starts_with($intermediateBytes, "\x89PNG\r\n\x1a\n")
                || strlen($intermediateBytes) > self::MAX_OUTPUT_BYTES) {
                throw new RuntimeException('pdf_thumbnail_output_invalid');
            }

            $dimensions = @getimagesizefromstring($intermediateBytes);
            $width = (int) ($dimensions[0] ?? 0);
            $height = (int) ($dimensions[1] ?? 0);
            if (! is_array($dimensions)
                || ($dimensions['mime'] ?? null) !== 'image/png'
                || $width < 1
                || $width > self::MAX_INTERMEDIATE_EDGE
                || $height < 1
                || $height > self::MAX_INTERMEDIATE_EDGE
                || $width * $height > self::MAX_INTERMEDIATE_PIXELS) {
                throw new RuntimeException('pdf_thumbnail_output_invalid');
            }

            $source = @imagecreatefromstring($intermediateBytes);
            if ($source === false) {
                throw new RuntimeException('pdf_thumbnail_output_invalid');
            }

            $target = null;
            try {
                $targetWidth = min($width, self::MAX_WIDTH);
                $targetHeight = max(1, (int) round($height * ($targetWidth / $width)));
                $target = imagecreatetruecolor($targetWidth, $targetHeight);
                if ($target === false
                    || ! imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height)) {
                    throw new RuntimeException('pdf_thumbnail_output_invalid');
                }

                ob_start();
                $encoded = imagepng($target);
                $pngBytes = ob_get_clean();
                if (! $encoded || ! is_string($pngBytes)
                    || ! str_starts_with($pngBytes, "\x89PNG\r\n\x1a\n")
                    || strlen($pngBytes) > self::MAX_OUTPUT_BYTES) {
                    throw new RuntimeException('pdf_thumbnail_output_invalid');
                }

                $finalDimensions = @getimagesizefromstring($pngBytes);
                $finalWidth = (int) ($finalDimensions[0] ?? 0);
                $finalHeight = (int) ($finalDimensions[1] ?? 0);
                if (! is_array($finalDimensions)
                    || ($finalDimensions['mime'] ?? null) !== 'image/png'
                    || $finalWidth < 1
                    || $finalWidth > self::MAX_WIDTH
                    || $finalHeight < 1
                    || $finalHeight > self::MAX_INTERMEDIATE_EDGE
                    || $finalWidth * $finalHeight > self::MAX_OUTPUT_PIXELS) {
                    throw new RuntimeException('pdf_thumbnail_output_invalid');
                }

                return $pngBytes;
            } finally {
                imagedestroy($source);
                if ($target !== null && $target !== false) {
                    imagedestroy($target);
                }
            }
        } finally {
            if ($createdDirectory && is_dir($directory)) {
                foreach (glob($directory.'/*') ?: [] as $path) {
                    if (is_file($path)) {
                        @unlink($path);
                    }
                }
                @rmdir($directory);
            }
        }
    }
}
