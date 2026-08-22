<?php

namespace App\Assessment\Omr\Canonical;

use App\Printing\RenderBundlePdf;
use RuntimeException;

final class OmrPresetSourceAdapter extends AbstractOmrSourceAdapter
{
    public function __construct(
        OmrSourcePreflight $preflightService,
        private readonly OmrPdfPageRasterizer $rasterizer,
        private readonly RenderBundlePdf $renderer,
    ) {
        parent::__construct($preflightService);
    }

    public function kind(): string
    {
        return 'preset';
    }

    public function convert(string $sourcePath, array $context = []): OmrSourceIngestionResult
    {
        $preflight = $this->preflight($sourcePath, $context);
        $source = json_decode((string) file_get_contents($sourcePath), true, 512, JSON_THROW_ON_ERROR);
        $firstPage = is_array($source['pages'][0] ?? null) ? $source['pages'][0] : $source;
        $declaredDimensions = (array) ($firstPage['pageDimensions'] ?? $source['pageDimensions'] ?? []);
        $widthMm = (float) ($context['width_mm'] ?? 216);
        $heightMm = (float) ($context['height_mm'] ?? 330);
        $newPage = $context['new_page'] ?? [
            'width' => $context['width_px'] ?? OmrPageDimensionMath::pixelsFromMillimetres($widthMm),
            'height' => $context['height_px'] ?? OmrPageDimensionMath::pixelsFromMillimetres($heightMm),
        ];
        if (! isset($declaredDimensions[0], $declaredDimensions[1], $newPage['width'], $newPage['height'])) {
            throw new RuntimeException('preset_page_dimensions_missing');
        }

        $pdfPath = $context['pdf_path'] ?? null;
        if (! is_string($pdfPath) || $pdfPath === '') {
            $pdfBytes = $this->renderer->renderTemplate($source);
            $pdfPath = tempnam(sys_get_temp_dir(), 'nexiam-omr-preset-');
            if ($pdfPath === false || file_put_contents($pdfPath, $pdfBytes) === false) {
                throw new RuntimeException('preset_rendered_pdf_write_failed');
            }
        }
        $measuredPages = $this->rasterizer->rasterize($pdfPath, [
            'width_mm' => $widthMm,
            'height_mm' => $heightMm,
            'ppi' => 242,
        ]);
        $measuredPage = $measuredPages[0] ?? null;
        if (! is_array($measuredPage)) {
            throw new RuntimeException('preset_rendered_pdf_measurement_failed');
        }
        $oldPage = $context['old_page'] ?? [
            'width' => $measuredPage['width'],
            'height' => $measuredPage['height'],
        ];
        $declaredPage = ['width' => (int) $declaredDimensions[0], 'height' => (int) $declaredDimensions[1]];
        $normalizedToRendered = OmrPageDimensionMath::transformFieldBlocks((array) ($firstPage['fieldBlocks'] ?? []), $declaredPage, $oldPage);
        $fieldBlocks = OmrPageDimensionMath::transformFieldBlocks($normalizedToRendered, $oldPage, $newPage);
        $this->assertNormalizedParity((array) ($firstPage['fieldBlocks'] ?? []), $fieldBlocks, $declaredPage, $newPage);
        $pages = $this->rasterizer->rasterize($pdfPath, [
            'width_mm' => $widthMm,
            'height_mm' => $heightMm,
            'ppi' => (int) config('omr.canonical.ppi', 300),
        ]);
        $this->lastResult = new OmrSourceIngestionResult(
            kind: $this->kind(),
            sourcePath: $sourcePath,
            sourceSha256: $preflight->sha256,
            converterVersion: 'preset-normalized-transform-v1',
            settingsHash: $this->settingsHash(['old_page' => $oldPage, 'new_page' => $newPage]),
            pages: $pages,
            metadata: [
                'pdf_path' => $pdfPath,
                'old_page' => $oldPage,
                'new_page' => $newPage,
                'field_blocks' => $fieldBlocks,
                'source_renderer_label' => $context['renderer_label'] ?? null,
                'physical_transform' => 'actual-page-dimensions-normalized',
            ],
        );

        return $this->lastResult;
    }

    /** @param array<string,mixed> $sourceBlocks @param array<string,mixed> $targetBlocks @param array{width:int|float,height:int|float} $sourcePage @param array{width:int|float,height:int|float} $targetPage */
    private function assertNormalizedParity(array $sourceBlocks, array $targetBlocks, array $sourcePage, array $targetPage): void
    {
        foreach ($sourceBlocks as $name => $source) {
            $target = $targetBlocks[$name] ?? null;
            if (! is_array($source) || ! is_array($target) || ! is_array($source['origin'] ?? null) || ! is_array($target['origin'] ?? null)) {
                throw new RuntimeException('preset_semantic_parity_failed');
            }
            $expectedX = (float) $source['origin'][0] / (float) $sourcePage['width'];
            $expectedY = (float) $source['origin'][1] / (float) $sourcePage['height'];
            $actualX = (float) $target['origin'][0] / (float) $targetPage['width'];
            $actualY = (float) $target['origin'][1] / (float) $targetPage['height'];
            if (abs($expectedX - $actualX) > 1 / (float) $targetPage['width']
                || abs($expectedY - $actualY) > 1 / (float) $targetPage['height']) {
                throw new RuntimeException('preset_semantic_parity_failed');
            }
            if (($source['fieldLabels'] ?? null) !== ($target['fieldLabels'] ?? null)
                || ($source['fieldType'] ?? null) !== ($target['fieldType'] ?? null)) {
                throw new RuntimeException('preset_semantic_parity_failed');
            }
        }
    }
}
