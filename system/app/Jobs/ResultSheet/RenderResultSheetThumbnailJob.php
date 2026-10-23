<?php

namespace App\Jobs\ResultSheet;

use App\DocumentTemplate\Services\DocumentThumbnailService;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\ResultSheet\Rendering\ResultSheetRenderer;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class RenderResultSheetThumbnailJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $templateId,
        public readonly string $sourceChecksum,
        public readonly string $fingerprint,
    ) {}

    public function uniqueId(): string
    {
        return sprintf('result-sheet-thumb:%d:%s', $this->templateId, $this->fingerprint);
    }

    public function handle(
        DocumentThumbnailService $thumbnailService,
        ResultSheetRenderer $renderer,
        ResultSheetPlaceholderService $placeholders,
    ): void {
        try {
            $template = ResultSheetTemplate::query()->find($this->templateId);
            if ($template === null || $template->printChecksum() !== $this->sourceChecksum) {
                return;
            }

            if ($thumbnailService->read('result_sheet', $this->templateId, $this->fingerprint) !== null) {
                return;
            }

            $sampleData = $placeholders->sampleData();
            $pdfBytes = $renderer->renderForApplicants($template, [$sampleData], timeoutSeconds: 12.0);

            if ($template->fresh()?->printChecksum() !== $this->sourceChecksum) {
                return;
            }

            $thumbnailService->store('result_sheet', $this->templateId, $this->sourceChecksum, $pdfBytes);
        } catch (Throwable) {
            // Thumbnail failures are non-critical and must not retry indefinitely.
        }
    }
}
