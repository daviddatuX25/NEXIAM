<?php

namespace App\Assessment\Omr\Canonical;

use App\ResultSheet\Rendering\DocxToPdfConverter;
use RuntimeException;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;

final class OmrDocxSourceAdapter extends AbstractOmrSourceAdapter
{
    public function __construct(
        OmrSourcePreflight $preflightService,
        private readonly DocxToPdfConverter $converter,
        private readonly OmrPdfPageRasterizer $rasterizer,
    ) {
        parent::__construct($preflightService);
    }

    public function kind(): string
    {
        return 'docx';
    }

    public function convert(string $sourcePath, array $context = []): OmrSourceIngestionResult
    {
        $preflight = $this->preflight($sourcePath, $context);
        $pdfBytes = $this->converter->convert($sourcePath);
        $pdfPath = tempnam(sys_get_temp_dir(), 'nexiam-omr-docx-');
        if ($pdfPath === false || file_put_contents($pdfPath, $pdfBytes) === false) {
            throw new RuntimeException('docx_pdf_temp_write_failed');
        }
        $pages = $this->rasterizer->rasterize($pdfPath, [
            'width_mm' => $context['width_mm'] ?? null,
            'height_mm' => $context['height_mm'] ?? null,
            'ppi' => (int) config('omr.canonical.ppi', 300),
        ]);
        $this->lastResult = new OmrSourceIngestionResult(
            kind: $this->kind(),
            sourcePath: $sourcePath,
            sourceSha256: $preflight->sha256,
            converterVersion: 'libreoffice',
            settingsHash: $this->settingsHash(['width_mm' => $context['width_mm'] ?? null, 'height_mm' => $context['height_mm'] ?? null]),
            pages: $pages,
            metadata: ['pdf_path' => $pdfPath],
        );

        return $this->lastResult;
    }
}
