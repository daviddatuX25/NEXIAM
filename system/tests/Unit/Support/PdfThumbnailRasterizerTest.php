<?php

use App\Support\Media\PdfThumbnailRasterizer;
use FPDF;
use RuntimeException;

function pdfThumbnailFixture(int $pageCount = 2, string $orientation = 'P', string|array $size = 'A4'): string
{
    $pdf = new FPDF;
    for ($page = 1; $page <= $pageCount; $page++) {
        $pdf->AddPage($orientation, $size);
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Text(20, 20, 'Thumbnail page '.$page);
    }

    return (string) $pdf->Output('S');
}

function pdfThumbnailTempDirectories(): array
{
    return glob(sys_get_temp_dir().'/nexiam-pdf-thumbnail-*', GLOB_ONLYDIR) ?: [];
}

test('it rasterizes only the first page of portrait, landscape, and tall PDFs proportionally', function (): void {
    $fixtures = [
        ['P', 'A4', 210 / 297],
        ['L', 'A4', 297 / 210],
        ['P', [60, 400], 60 / 400],
    ];

    foreach ($fixtures as [$orientation, $size, $expectedAspectRatio]) {
        $before = pdfThumbnailTempDirectories();
        $png = (new PdfThumbnailRasterizer)->rasterizeFirstPage(pdfThumbnailFixture(2, $orientation, $size));

        $dimensions = getimagesizefromstring($png);
        expect($dimensions)->toBeArray()
            ->and($dimensions['mime'])->toBe('image/png')
            ->and($dimensions[0])->toBeLessThanOrEqual(240)
            ->and($dimensions[1])->toBeLessThanOrEqual(480)
            ->and(abs(($dimensions[0] / $dimensions[1]) - $expectedAspectRatio))->toBeLessThan(0.01)
            ->and(pdfThumbnailTempDirectories())->toBe($before);
    }
});

test('it rejects malformed PDF input and cleans temporary files', function (): void {
    $before = pdfThumbnailTempDirectories();

    expect(fn () => (new PdfThumbnailRasterizer)->rasterizeFirstPage('not a PDF'))
        ->toThrow(RuntimeException::class);

    expect(pdfThumbnailTempDirectories())->toBe($before);
});

test('it rejects oversized raster output and cleans temporary files', function (): void {
    $directory = sys_get_temp_dir().'/pdf-thumbnail-fake-poppler-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    $executable = $directory.'/pdftoppm';
    file_put_contents($executable, <<<'SH'
#!/bin/sh
for argument do
    prefix="$argument"
done
dd if=/dev/zero of="${prefix}-1.png" bs=1048577 count=1 2>/dev/null
SH);
    chmod($executable, 0700);

    $originalPath = getenv('PATH');
    putenv('PATH='.$directory.':'.$originalPath);
    $before = pdfThumbnailTempDirectories();

    try {
        expect(fn () => (new PdfThumbnailRasterizer)->rasterizeFirstPage(pdfThumbnailFixture(1)))
            ->toThrow(RuntimeException::class, 'pdf_thumbnail_output_too_large');
        expect(pdfThumbnailTempDirectories())->toBe($before);
    } finally {
        putenv('PATH='.$originalPath);
        @unlink($executable);
        @rmdir($directory);
    }
});
