<?php

namespace App\Services;

use App\ValueObjects\RenderResult;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\Response;

class ResultSheetPdfService
{
    public function __construct(
        protected PrintTemplateCssService $cssService
    ) {}

    public function inline(RenderResult $result, string $filename = 'result-sheet.pdf'): Response
    {
        return $this->builder($result)->inline($filename)->toResponse(request());
    }

    public function download(RenderResult $result, string $filename = 'result-sheet.pdf'): Response
    {
        return $this->builder($result)->download($filename)->toResponse(request());
    }

    /**
     * @param  string[]  $sheetsHtml
     */
    public function bulkInline(array $sheetsHtml, RenderResult $meta, string $filename = 'result-sheets.pdf', int $copies = 1): Response
    {
        return $this->builder($meta, $this->combineSheets($sheetsHtml, $copies))
            ->inline($filename)
            ->toResponse(request());
    }

    /**
     * @param  string[]  $sheetsHtml
     */
    public function bulkDownload(array $sheetsHtml, RenderResult $meta, string $filename = 'result-sheets.pdf', int $copies = 1): Response
    {
        return $this->builder($meta, $this->combineSheets($sheetsHtml, $copies))
            ->download($filename)
            ->toResponse(request());
    }

    /**
     * @param  string[]  $sheetsHtml
     */
    public function generateBulkPdfContent(array $sheetsHtml, RenderResult $meta, int $copies = 1): string
    {
        return $this->builder($meta, $this->combineSheets($sheetsHtml, $copies))->content();
    }

    /**
     * @return array{width: int, height: int}
     */
    public function previewDimensions(RenderResult $result): array
    {
        return $result->pageDimensions();
    }

    private function builder(RenderResult $meta, ?string $html = null): PdfBuilder
    {
        $isBulk = $html !== null;
        $html ??= $meta->html;

        if ($meta->watermarkText !== null) {
            $html = $this->injectWatermark($html, $meta->watermarkText);
        }

        if ($isBulk) {
            $html = $this->cssService->wrapBulkForPdf($html, $meta->paperSize, $meta->orientation);
        }

        $builder = Pdf::html($html)
            ->format($meta->paperSize)
            ->margins(0, 0, 0, 0);

        if ($meta->orientation === 'landscape') {
            $builder->landscape();
        }

        return $builder;
    }

    /**
     * @param  string[]  $sheetsHtml
     */
    private function combineSheets(array $sheetsHtml, int $copies = 1): string
    {
        $expanded = [];
        foreach ($sheetsHtml as $sheet) {
            for ($i = 0; $i < $copies; $i++) {
                $expanded[] = $sheet;
            }
        }

        return implode('<div style="page-break-after: always;"></div>', $expanded);
    }

    private function injectWatermark(string $html, string $text): string
    {
        $overlay = '<div class="watermark-overlay"><span>'.htmlspecialchars($text).'</span></div>';
        $css = '<style>.watermark-overlay{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-45deg);font-size:4rem;color:rgba(200,200,200,0.4);pointer-events:none;z-index:9999;white-space:nowrap;user-select:none;}</style>';

        return $css.$overlay.$html;
    }
}
