<?php

namespace App\ResultSheet\Rendering;

use App\Models\ResultSheet\ResultSheetTemplate;
use App\Printing\PrintRenderer;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
use RuntimeException;

/**
 * Renders one result sheet (HTML or DOCX/ODT template) to PDF bytes for one
 * applicant. Runs inside queue workers only — nothing in the render path
 * executes during an HTTP request.
 */
final class ResultSheetRenderer
{
    public function __construct(
        private readonly ResultSheetPlaceholderService $placeholders,
        private readonly ResultSheetCssService $css,
        private readonly PrintRenderer $pdfRenderer,
        private readonly DocxFiller $filler,
        private readonly DocxToPdfConverter $converter,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $applicants
     */
    public function renderForApplicants(ResultSheetTemplate $template, array $applicants, ?float $timeoutSeconds = null): string
    {
        if ($template->mode === ResultSheetTemplate::MODE_DOCX) {
            return $this->renderDocumentItem($template, $applicants, $timeoutSeconds, false)['pdf'];
        }

        return $this->renderHtml($template, $applicants);
    }

    /**
     * @param  array<int, array<string, mixed>>  $applicants
     * @return array{pdf: string, docx: string|null}
     */
    public function renderItem(ResultSheetTemplate $template, array $applicants, ?float $timeoutSeconds = null): array
    {
        if ($template->mode === ResultSheetTemplate::MODE_DOCX) {
            return $this->renderDocumentItem($template, $applicants, $timeoutSeconds, true);
        }

        return ['pdf' => $this->renderHtml($template, $applicants), 'docx' => null];
    }

    /**
     * @param  array<int, array<string, mixed>>  $applicants
     */
    private function renderHtml(ResultSheetTemplate $template, array $applicants): string
    {
        if ($template->isBuilder()) {
            $document = is_array($template->builder_json) ? $template->builder_json : [];
            $scoreRowCount = count(is_array($applicants[0]['scores'] ?? null) ? $applicants[0]['scores'] : []);
            $diagnostics = ResultSheetBuilderSchema::diagnostics($document, $scoreRowCount);
            if ($diagnostics !== []) {
                throw new RuntimeException('builder_invalid:'.json_encode($diagnostics, JSON_THROW_ON_ERROR));
            }
        }

        $replacements = $this->placeholders->buildReplacements($applicants);
        $raw = $this->placeholders->renderRaw((string) $template->content, $replacements);
        $marginMm = (float) ($template->builder_json['page']['margin_mm'] ?? 12);
        $html = $this->css->wrapForPdf($raw, $template->paper_size, $template->orientation, $marginMm);
        $html = $this->injectWatermark($html, $template->watermark_text);

        return $this->pdfRenderer->render($html, $this->paperOptions($template));
    }

    /**
     * @param  array<int, array<string, mixed>>  $applicants
     * @return array{pdf: string, docx: string|null}
     */
    private function renderDocumentItem(
        ResultSheetTemplate $template,
        array $applicants,
        ?float $timeoutSeconds,
        bool $includeDocx,
    ): array {
        if (! $this->converter->isAvailable()) {
            throw new RuntimeException('libreoffice_unavailable');
        }

        $sourcePath = storage_path('app/private/'.$template->document_path);
        if (! is_file($sourcePath)) {
            throw new RuntimeException('Template document file not found on disk.');
        }

        $filled = $this->filler->fill($sourcePath, $this->placeholders->buildDocxReplacements($applicants));

        try {
            return [
                'pdf' => $this->converter->convert($filled, $timeoutSeconds),
                'docx' => $includeDocx ? (string) file_get_contents($filled) : null,
            ];
        } finally {
            if (is_file($filled)) {
                @unlink($filled);
            }
        }
    }

    private function injectWatermark(string $html, ?string $text): string
    {
        if ($text === null || $text === '') {
            return $html;
        }

        $markup = '<div class="watermark">'.e($text).'</div>';

        return str_replace('</body>', $markup.'</body>', $html);
    }

    /**
     * @return array{format: string, orientation: string, width_mm: float, height_mm: float}
     */
    private function paperOptions(ResultSheetTemplate $template): array
    {
        $dimensions = config('result-sheet.paper_sizes.'.$template->paper_size, ['width_mm' => 210.0, 'height_mm' => 297.0]);

        $landscape = $template->orientation === ResultSheetTemplate::ORIENTATION_LANDSCAPE;

        return [
            'format' => 'custom',
            'orientation' => $template->orientation,
            'width_mm' => $landscape ? $dimensions['height_mm'] : $dimensions['width_mm'],
            'height_mm' => $landscape ? $dimensions['width_mm'] : $dimensions['height_mm'],
        ];
    }
}
