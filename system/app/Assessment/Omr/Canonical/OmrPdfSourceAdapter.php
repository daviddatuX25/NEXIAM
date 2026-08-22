<?php

namespace App\Assessment\Omr\Canonical;

final class OmrPdfSourceAdapter extends AbstractOmrSourceAdapter
{
    public function __construct(
        OmrSourcePreflight $preflightService,
        private readonly OmrPdfPageRasterizer $rasterizer,
    ) {
        parent::__construct($preflightService);
    }

    public function kind(): string
    {
        return 'pdf';
    }

    public function convert(string $sourcePath, array $context = []): OmrSourceIngestionResult
    {
        $preflight = $this->preflight($sourcePath, $context);
        $pageSizes = (array) ($preflight->metadata['page_sizes_mm'] ?? []);
        $firstSize = (array) ($pageSizes[0] ?? []);
        foreach ($pageSizes as $index => $size) {
            if ($firstSize !== [] && (($size['width_mm'] ?? null) !== ($firstSize['width_mm'] ?? null) || ($size['height_mm'] ?? null) !== ($firstSize['height_mm'] ?? null))) {
                throw new OmrSourcePreflightException('mixed_page_sizes', 'PDF contains mixed page sizes.', ['pages' => [$index + 1]]);
            }
        }
        $pages = $this->rasterizer->rasterize($sourcePath, [
            'width_mm' => $context['width_mm'] ?? ($firstSize['width_mm'] ?? null),
            'height_mm' => $context['height_mm'] ?? ($firstSize['height_mm'] ?? null),
            'ppi' => (int) config('omr.canonical.ppi', 300),
        ]);
        $this->assertUniformPages($pages);
        $this->lastResult = new OmrSourceIngestionResult(
            kind: $this->kind(),
            sourcePath: $sourcePath,
            sourceSha256: $preflight->sha256,
            converterVersion: 'poppler-pdftoppm',
            settingsHash: $this->settingsHash(['ppi' => config('omr.canonical.ppi', 300), 'page_sizes_mm' => $pageSizes]),
            pages: $pages,
            metadata: ['page_count' => count($pages), 'page_sizes_mm' => $pageSizes],
        );

        return $this->lastResult;
    }

    /** @param list<array<string, mixed>> $pages */
    private function assertUniformPages(array $pages): void
    {
        $first = $pages[0] ?? null;
        if (! is_array($first)) {
            return;
        }
        foreach ($pages as $page) {
            if (($page['width_mm'] ?? null) !== ($first['width_mm'] ?? null)
                || ($page['height_mm'] ?? null) !== ($first['height_mm'] ?? null)) {
                throw new OmrSourcePreflightException('mixed_page_sizes', 'PDF contains mixed page sizes.', [
                    'page_no' => $page['page_no'] ?? null,
                ]);
            }
        }
    }
}
