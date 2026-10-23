<?php

namespace App\ResultSheet\Rendering;

use App\Models\Printing\PrintJob;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\ResultSheet\Pipeline\PreviewResultSheetJob;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Single preview seam for result sheets.
 * Guarantees "preview == print" fidelity:
 * - HTML templates render synchronous PDF bytes via DomPDF
 * - DOCX/ODT templates dispatch an ephemeral preview PrintJob (is_preview = true) and embed the resulting PDF.
 */
class ResultSheetPreviewRenderer
{
    public function __construct(
        private readonly ResultSheetRenderer $sheetRenderer,
        private readonly ResultSheetPlaceholderService $placeholders,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $applicants
     * @return array{mode: string, type: string, pdf_bytes?: string, job_id?: int, download_url?: string}
     */
    public function renderPreview(
        ResultSheetTemplate $template,
        array $applicants,
        ?User $actor = null,
        bool $useSampleData = false,
    ): array {
        if ($template->mode === ResultSheetTemplate::MODE_HTML) {
            $data = $applicants[0] ?? ($useSampleData ? $this->placeholders->sampleData() : []);
            $bytes = $this->sheetRenderer->renderForApplicants($template, [$data]);

            return [
                'mode' => ResultSheetTemplate::MODE_HTML,
                'type' => 'pdf_bytes',
                'pdf_bytes' => $bytes,
            ];
        }

        // DOCX/ODT mode: copy the source before crossing the queue boundary.
        $sourcePath = $template->document_path;
        $disk = Storage::disk('local');

        if ($sourcePath === null || ! $disk->exists($sourcePath)) {
            throw new RuntimeException('template_document_missing');
        }

        try {
            $sourceBytes = (string) $disk->get($sourcePath);
        } catch (Throwable $exception) {
            throw new RuntimeException('template_document_missing', 0, $exception);
        }

        $sourceChecksum = hash('sha256', $sourceBytes);
        $extension = strtolower((string) ($template->document_extension ?: pathinfo($sourcePath, PATHINFO_EXTENSION)));

        if (! in_array($extension, ['docx', 'odt'], true)) {
            throw new RuntimeException('template_document_extension_invalid');
        }

        $previewPath = $this->previewSourcePath($extension);
        if (! $disk->put($previewPath, $sourceBytes)) {
            $disk->delete($previewPath);
            throw new RuntimeException('preview_source_copy_failed');
        }

        $storedTemplateId = $template->exists && $template->getKey() !== null
            ? (int) $template->getKey()
            : null;
        $cleanupPaths = [$previewPath];

        if ($storedTemplateId === null) {
            $cleanupPaths[] = $sourcePath;
        }

        $templateLookup = $storedTemplateId === null ? null : [
            'template_id' => $storedTemplateId,
            'document_path' => $sourcePath,
            'document_checksum' => $template->document_checksum,
            'template_checksum' => $template->printChecksum(),
        ];
        $data = $applicants[0] ?? ($useSampleData ? $this->placeholders->sampleData() : []);

        try {
            $job = PrintJob::query()->create([
                'artifact_type' => PrintJob::ARTIFACT_RESULT_SHEET,
                'is_preview' => true,
                'scope_type' => 'preview',
                'scope_id' => 0,
                'requested_by_user_id' => $actor?->id,
                'pinned_input_json' => [
                    'template_id' => $storedTemplateId,
                    'template_lookup' => $templateLookup,
                    'is_preview' => true,
                    'source_path' => $previewPath,
                    'source_checksum' => $sourceChecksum,
                    'source_extension' => $extension,
                    'original_source_path' => $sourcePath,
                    'cleanup_source' => true,
                    'cleanup_source_paths' => $cleanupPaths,
                    'applicant_data' => $data,
                    'template_snapshot' => $this->templateSnapshot(
                        $template,
                        $storedTemplateId,
                        $previewPath,
                        $sourceChecksum,
                        $extension,
                    ),
                ],
                'progress_total' => 1,
                'progress_done' => 0,
            ]);

            PreviewResultSheetJob::dispatch($job->id);
        } catch (Throwable $exception) {
            foreach ($cleanupPaths as $cleanupPath) {
                $disk->delete($cleanupPath);
            }

            throw $exception;
        }

        return [
            'mode' => ResultSheetTemplate::MODE_DOCX,
            'type' => 'preview_job',
            'job_id' => $job->id,
            'download_url' => route('printing.jobs.download', $job),
        ];
    }

    private function previewSourcePath(string $extension): string
    {
        return 'result-sheet-templates/preview-'.Str::uuid().'.'.$extension;
    }

    /**
     * @return array<string, mixed>
     */
    private function templateSnapshot(
        ResultSheetTemplate $template,
        ?int $templateId,
        string $previewPath,
        string $sourceChecksum,
        string $extension,
    ): array {
        return [
            'id' => $templateId,
            'name' => $template->name,
            'mode' => $template->mode,
            'source_kind' => $template->source_kind,
            'builder_schema_version' => $template->builder_schema_version,
            'builder_json' => $template->builder_json,
            'compiled_html' => $template->compiled_html,
            'compiler_version' => $template->compiler_version,
            'paper_size' => $template->paper_size,
            'orientation' => $template->orientation,
            'logical_unit' => $template->logical_unit,
            'content' => $template->content,
            'watermark_text' => $template->watermark_text,
            'document_path' => $previewPath,
            'document_checksum' => $sourceChecksum,
            'document_extension' => $extension,
            'lock_version' => $template->lock_version,
            'is_active' => $template->is_active,
        ];
    }
}
