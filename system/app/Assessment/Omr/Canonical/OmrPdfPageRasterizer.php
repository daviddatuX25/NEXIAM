<?php

namespace App\Assessment\Omr\Canonical;

use Symfony\Component\Process\Process;
use RuntimeException;

use function file_exists;
use function filesize;
use function getimagesize;
use function glob;
use function hash_file;
use function is_array;
use function is_file;
use function mkdir;
use function sys_get_temp_dir;
use function tempnam;

final class OmrPdfPageRasterizer
{
    /**
     * @param array{width_mm?:float|null,height_mm?:float|null,ppi?:int} $context
     * @return list<array{page_no:int,path:string,mime:string,bytes:int,sha256:string,width:int,height:int,width_mm:float|null,height_mm:float|null,orientation:string,metadata:array<string,mixed>}>
     */
    public function rasterize(string $pdfPath, array $context = []): array
    {
        if (! is_file($pdfPath)) {
            throw new RuntimeException('pdf_not_found');
        }
        $directory = sys_get_temp_dir().'/nexiam-omr-raster-'.bin2hex(random_bytes(8));
        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('raster_temp_unavailable');
        }
        $prefix = $directory.'/page';
        $ppi = (int) ($context['ppi'] ?? 300);
        $process = new Process(['pdftoppm', '-png', '-r', (string) $ppi, $pdfPath, $prefix]);
        $process->setTimeout((float) config('omr.canonical.limits.conversion_timeout_seconds_per_page', 300));
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('pdf_rasterization_failed: '.$process->getErrorOutput());
        }

        $files = glob($prefix.'-*.png') ?: glob($prefix.'.png') ?: [];
        sort($files, SORT_NATURAL);
        if ($files === []) {
            throw new RuntimeException('pdf_rasterization_empty');
        }
        $pages = [];
        foreach ($files as $index => $path) {
            $dimensions = @getimagesize($path);
            if (! is_array($dimensions)) {
                throw new RuntimeException('raster_dimensions_unavailable');
            }
            $widthMm = isset($context['width_mm']) ? (float) $context['width_mm'] : null;
            $heightMm = isset($context['height_mm']) ? (float) $context['height_mm'] : null;
            $pages[] = [
                'page_no' => $index + 1,
                'path' => $path,
                'mime' => 'image/png',
                'bytes' => (int) filesize($path),
                'sha256' => (string) hash_file('sha256', $path),
                'width' => (int) $dimensions[0],
                'height' => (int) $dimensions[1],
                'width_mm' => $widthMm,
                'height_mm' => $heightMm,
                'orientation' => $dimensions[0] >= $dimensions[1] ? 'landscape' : 'portrait',
                'metadata' => ['ppi' => $ppi],
            ];
        }

        return $pages;
    }
}
