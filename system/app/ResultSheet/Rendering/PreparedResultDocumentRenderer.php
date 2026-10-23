<?php

declare(strict_types=1);

namespace App\ResultSheet\Rendering;

use App\Models\Release\ResultDocument;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Release\Support\ResultDocumentPdf;
use RuntimeException;

final class PreparedResultDocumentRenderer
{
    public function __construct(
        private readonly ResultDocumentPdf $documents,
        private readonly ResultSheetRenderer $renderer,
    ) {}

    /**
     * @return array{pdf: string, docx: string|null}
     */
    public function render(ResultDocument $document): array
    {
        if (! $this->documents->canPrepare($document)) {
            throw new RuntimeException('The prepared result document has no valid frozen template source.');
        }

        $snapshot = is_array($document->snapshot_payload_json) ? $document->snapshot_payload_json : [];
        if (! is_array($snapshot['scores'] ?? null)) {
            throw new RuntimeException('The prepared result document has no frozen score payload.');
        }
        $metadata = $snapshot['_template'];
        $source = $snapshot['_template_source'];
        $bytes = base64_decode($source['bytes_base64'], true);
        if (! is_string($bytes)) {
            throw new RuntimeException('The prepared result document has no valid frozen template source.');
        }

        $applicantData = $snapshot;
        unset($applicantData['_template'], $applicantData['_template_source'], $applicantData['_assessment_result_set_id']);
        $template = new ResultSheetTemplate;
        $template->forceFill([
            'mode' => $metadata['mode'],
            'source_kind' => $metadata['source_kind'] ?? null,
            'content' => $metadata['mode'] === ResultSheetTemplate::MODE_HTML ? $bytes : '',
            'paper_size' => $metadata['paper_size'] ?? ResultSheetTemplate::PAPER_A4,
            'orientation' => $metadata['orientation'] ?? ResultSheetTemplate::ORIENTATION_PORTRAIT,
            'watermark_text' => $metadata['watermark_text'] ?? null,
            'builder_json' => $metadata['builder_json'] ?? null,
        ]);

        $temporaryPath = null;
        try {
            if ($metadata['mode'] === ResultSheetTemplate::MODE_DOCX) {
                $temporaryDirectory = storage_path('app/private/result-document-sources');
                if (! is_dir($temporaryDirectory) && ! mkdir($temporaryDirectory, 0700, true) && ! is_dir($temporaryDirectory)) {
                    throw new RuntimeException('Unable to prepare frozen result template source.');
                }
                $temporaryPath = $temporaryDirectory.'/'.bin2hex(random_bytes(16)).'.docx';
                if (file_put_contents($temporaryPath, $bytes, LOCK_EX) === false) {
                    throw new RuntimeException('Unable to write frozen result template source.');
                }
                $template->document_path = 'result-document-sources/'.basename($temporaryPath);
            }

            $artifacts = $this->renderer->renderItem($template, [$applicantData]);
            if (! str_starts_with($artifacts['pdf'], '%PDF-')) {
                throw new RuntimeException('The result renderer did not produce a valid PDF.');
            }

            return $artifacts;
        } finally {
            if ($temporaryPath !== null && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }
}
