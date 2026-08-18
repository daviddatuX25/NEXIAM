<?php

namespace App\ResultSheet\Rendering;

use App\Models\ResultSheet\ResultSheetTemplate;
use App\Printing\PrintRenderer;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
use RuntimeException;

/**
 * Renders one result sheet (HTML or DOCX/ODT template) to PDF bytes for one
 * or two applicants (dual-slot). Runs inside queue workers only — nothing in
 * the render path executes during an HTTP request.
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
    public function renderForApplicants(ResultSheetTemplate $template, array $applicants): string
    {
        if ($template->mode === ResultSheetTemplate::MODE_DOCX) {
            return $this->renderDocument($template, $applicants);
        }

        return $this->renderHtml($template, $applicants);
    }

    /**
     * @param  array<int, array<string, mixed>>  $applicants
     */
    private function renderHtml(ResultSheetTemplate $template, array $applicants): string
    {
        $replacements = $this->placeholders->buildReplacements($applicants);
        $isDual = $this->isDual($template);

        $html = $isDual
            ? $this->css->wrapDualForPdf(
                $this->placeholders->renderRaw((string) $template->content, $this->slotReplacements($replacements, '')),
                $this->placeholders->renderRaw((string) $template->content, $this->slotReplacements($replacements, '_2')),
                $template->paper_size,
                $template->orientation,
            )
            : $this->css->wrapForPdf(
                $this->placeholders->renderRaw((string) $template->content, $replacements),
                $template->paper_size,
                $template->orientation,
            );

        $html = $this->injectWatermark($html, $template->watermark_text);

        return $this->pdfRenderer->render($html, $this->paperOptions($template));
    }

    /**
     * @param  array<int, array<string, mixed>>  $applicants
     */
    private function renderDocument(ResultSheetTemplate $template, array $applicants): string
    {
        if (! $this->converter->isAvailable()) {
            throw new RuntimeException('libreoffice_unavailable');
        }

        $sourcePath = storage_path('app/private/'.$template->document_path);

        if (! is_file($sourcePath)) {
            throw new RuntimeException('Template document file not found on disk.');
        }

        // One document fill carries both slots (dual templates use _2 keys).
        $filled = $this->filler->fill($sourcePath, $this->placeholders->buildDocxReplacements($applicants));

        try {
            return $this->converter->convert($filled);
        } finally {
            if (is_file($filled)) {
                @unlink($filled);
            }
        }
    }

    /**
     * @param  array<string, string>  $replacements
     * @return array<string, string>
     */
    private function slotReplacements(array $replacements, string $suffix): array
    {
        if ($suffix === '') {
            return $replacements;
        }

        $slot = [];

        foreach ($replacements as $key => $value) {
            if (str_ends_with($key, $suffix)) {
                $slot[substr($key, 0, -strlen($suffix))] = $value;
            }
        }

        return $slot;
    }

    private function isDual(ResultSheetTemplate $template): bool
    {
        return in_array($template->logical_unit, [
            ResultSheetTemplate::LOGICAL_HALF_A4,
            ResultSheetTemplate::LOGICAL_HALF_LEGAL,
            ResultSheetTemplate::LOGICAL_HALF_LETTER,
        ], true);
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
