<?php

namespace App\Assessment\Omr\Canonical;

use App\Printing\DomPdfPrintRenderer;
use RuntimeException;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;

final class OmrHtmlSourceAdapter extends AbstractOmrSourceAdapter
{
    public function __construct(
        OmrSourcePreflight $preflightService,
        private readonly DomPdfPrintRenderer $renderer,
        private readonly OmrPdfPageRasterizer $rasterizer,
    ) {
        parent::__construct($preflightService);
    }

    public function kind(): string
    {
        return 'html';
    }

    public function convert(string $sourcePath, array $context = []): OmrSourceIngestionResult
    {
        $preflight = $this->preflight($sourcePath, $context);
        $widthMm = (float) ($context['width_mm'] ?? 216);
        $heightMm = (float) ($context['height_mm'] ?? 330);
        $orientation = $widthMm > $heightMm ? 'landscape' : 'portrait';
        $pdfBytes = $this->renderer->render(
            (string) ($preflight->metadata['sanitized_html'] ?? file_get_contents($sourcePath)),
            ['format' => 'custom', 'width_mm' => $widthMm, 'height_mm' => $heightMm, 'orientation' => $orientation],
        );
        $pdfPath = tempnam(sys_get_temp_dir(), 'nexiam-omr-html-');
        if ($pdfPath === false || file_put_contents($pdfPath, $pdfBytes) === false) {
            throw new RuntimeException('html_pdf_temp_write_failed');
        }
        $pages = $this->rasterizer->rasterize($pdfPath, [
            'width_mm' => $widthMm,
            'height_mm' => $heightMm,
            'ppi' => (int) config('omr.canonical.ppi', 300),
        ]);
        $this->lastResult = new OmrSourceIngestionResult(
            kind: $this->kind(),
            sourcePath: $sourcePath,
            sourceSha256: $preflight->sha256,
            converterVersion: 'dompdf-'.(string) config('dompdf.version', 'pinned'),
            settingsHash: $this->settingsHash(['width_mm' => $widthMm, 'height_mm' => $heightMm, 'orientation' => $orientation]),
            pages: $pages,
            metadata: [
                'pdf_path' => $pdfPath,
                'page_count' => count($pages),
                'overlay_slots' => $preflight->metadata['overlay_slots'] ?? [],
                'physical_page' => ['width_mm' => $widthMm, 'height_mm' => $heightMm, 'orientation' => $orientation],
            ],
        );

        return $this->lastResult;
    }
}
