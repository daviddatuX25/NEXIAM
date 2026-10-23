<?php

namespace App\Jobs\Reports;

use App\DocumentTemplate\DocumentSurfaceRegistry;
use App\DocumentTemplate\Rendering\DocumentBlueprintCompiler;
use App\DocumentTemplate\Rendering\DocxTemplateFiller;
use App\DocumentTemplate\Rendering\LibreOfficeConverter;
use App\DocumentTemplate\Rendering\ReportHtmlContextFiller;
use App\DocumentTemplate\Services\DocumentThumbnailService;
use App\DocumentTemplate\Support\DocxTextExtractor;
use App\Models\Reports\ReportTemplate;
use App\ResultSheet\Rendering\ResultSheetCssService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RenderReportTemplateThumbnailJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        public readonly string $surfaceKey,
        public readonly int $templateId,
        public readonly string $sourceChecksum,
        public readonly string $fingerprint
    ) {}

    public function uniqueId(): string
    {
        return sprintf('report-template-thumb:%d:%s', $this->templateId, $this->fingerprint);
    }

    public function handle(
        DocumentThumbnailService $thumbnailService,
        DocumentBlueprintCompiler $compiler,
        DocxTemplateFiller $filler,
        LibreOfficeConverter $converter,
        DocxTextExtractor $extractor,
        ReportHtmlContextFiller $htmlFiller
    ): void {
        try {
            $template = ReportTemplate::query()->find($this->templateId);
            if ($template === null || $template->surface_key !== $this->surfaceKey) {
                return;
            }

            if ($template->sourceChecksum() !== $this->sourceChecksum) {
                return;
            }

            if ($thumbnailService->read($this->surfaceKey, $this->templateId, $this->fingerprint) !== null) {
                return;
            }

            $surface = DocumentSurfaceRegistry::resolve($this->surfaceKey);
            if ($surface === null) {
                return;
            }

            if ($template->mode === 'html') {
                $builder = $template->builder_json;
                if (is_array($builder)) {
                    $rawHtml = $compiler->compile($builder);
                } else {
                    $rawHtml = $template->compiled_html ?? $template->content ?? '';
                }

                $context = $surface->sampleContext();
                $filledHtml = $htmlFiller->fill($rawHtml, $context);
                $margin = is_array($builder) ? (float) ($builder['page']['margin_mm'] ?? 12) : 12;
                $wrappedHtml = (new ResultSheetCssService)->wrapForPdf(
                    $filledHtml,
                    $template->paper_size,
                    $template->orientation,
                    $margin
                );
                $pdfBytes = Pdf::loadHTML($wrappedHtml)
                    ->setPaper($template->paper_size, $template->orientation)
                    ->output();
            } elseif ($template->mode === 'docx' && $template->document_path !== null) {
                $sourcePath = storage_path('app/private/'.$template->document_path);
                $filledPath = $filler->fill($sourcePath, $surface->sampleContext(), $surface->repeatingRegions());
                try {
                    $pdfBytes = $converter->convertToPdf($filledPath, timeoutSeconds: 12.0);
                } finally {
                    if (is_file($filledPath)) {
                        unlink($filledPath);
                    }
                }
            } else {
                return;
            }

            if ($template->fresh()?->sourceChecksum() !== $this->sourceChecksum) {
                return;
            }

            $thumbnailService->store($this->surfaceKey, $this->templateId, $this->sourceChecksum, $pdfBytes);
        } catch (Throwable $exception) {
            Log::warning('Report template thumbnail rendering failed.', [
                'template_id' => $this->templateId,
                'surface' => $this->surfaceKey,
                'exception' => $exception,
            ]);
        }
    }
}
