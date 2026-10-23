<?php

namespace App\ResultSheet\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;
use App\ResultSheet\Rendering\ResultSheetRenderer;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
use App\ResultSheet\Support\ResultSheetTemplateAnalyzer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class ActivateResultSheetTemplate
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly ResultSheetTemplateAnalyzer $analyzer,
        private readonly ResultSheetRenderer $renderer,
        private readonly ResultSheetPlaceholderService $placeholders,
    ) {}

    public function handle(ResultSheetTemplate $template, User $actor): ResultSheetTemplate
    {
        $this->verifyTemplate($template);

        return DB::transaction(function () use ($template, $actor): ResultSheetTemplate {
            ResultSheetTemplate::query()->where('is_active', true)->update(['is_active' => false]);

            $template->update(['is_active' => true]);

            $this->recordAuditEvent->handle('result_template.activated', [
                'template_id' => $template->id,
                'name' => $template->name,
            ], (string) $actor->id);

            return $template->fresh();
        });
    }

    private function verifyTemplate(ResultSheetTemplate $template): void
    {
        if ($template->mode === ResultSheetTemplate::MODE_DOCX) {
            $this->verifyDocxTemplate($template);

            return;
        }

        if ($template->isBuilder()) {
            $this->verifyBuilderTemplate($template);
        }
    }

    private function verifyBuilderTemplate(ResultSheetTemplate $template): void
    {
        $document = is_array($template->builder_json) ? $template->builder_json : null;

        if ($document === null) {
            throw new RuntimeException('builder_invalid:Builder document missing.');
        }

        $sampleData = $this->placeholders->sampleData();
        $scoreRowCount = count(is_array($sampleData['scores'] ?? null) ? $sampleData['scores'] : []);
        $diagnostics = ResultSheetBuilderSchema::diagnostics($document, $scoreRowCount);

        if ($diagnostics !== []) {
            throw new RuntimeException('builder_invalid:'.json_encode($diagnostics, JSON_THROW_ON_ERROR));
        }

        try {
            $pdf = $this->renderer->renderForApplicants($template, [$sampleData]);

            if (empty($pdf) || ! str_starts_with($pdf, '%PDF')) {
                throw new RuntimeException('template_pdf_verification_failed');
            }
        } catch (Throwable $e) {
            throw new RuntimeException('template_pdf_verification_failed', 0, $e);
        }
    }

    private function verifyDocxTemplate(ResultSheetTemplate $template): void
    {
        if ($template->document_path === null || $template->document_path === '') {
            throw new RuntimeException('Template document missing.');
        }

        if (! Storage::disk('local')->exists($template->document_path)) {
            throw new RuntimeException('Template document missing.');
        }

        $sourcePath = Storage::disk('local')->path($template->document_path);

        if (! is_file($sourcePath)) {
            throw new RuntimeException('Template document missing.');
        }

        if ($template->document_checksum === null || $template->document_checksum === '') {
            throw new RuntimeException('Template document integrity failed.');
        }

        $actualChecksum = hash_file('sha256', $sourcePath);

        if ($actualChecksum === false || ! hash_equals((string) $template->document_checksum, $actualChecksum)) {
            throw new RuntimeException('Template document integrity failed.');
        }

        try {
            $analysis = $this->analyzer->analyze($template);
        } catch (Throwable $e) {
            throw new RuntimeException('Template placeholders invalid.', 0, $e);
        }

        if (! ($analysis['valid'] ?? false) || ! empty($analysis['extra'])) {
            throw new RuntimeException('Template placeholders invalid.');
        }

        $sampleData = $this->placeholders->sampleData();
        $isDual = in_array($template->logical_unit, [
            ResultSheetTemplate::LOGICAL_HALF_A4,
            ResultSheetTemplate::LOGICAL_HALF_LEGAL,
            ResultSheetTemplate::LOGICAL_HALF_LETTER,
        ], true);

        $applicants = $isDual ? [$sampleData, $sampleData] : [$sampleData];

        try {
            $pdf = $this->renderer->renderForApplicants($template, $applicants);

            if (empty($pdf) || ! str_starts_with($pdf, '%PDF')) {
                throw new RuntimeException('Template PDF verification failed.');
            }
        } catch (Throwable $e) {
            throw new RuntimeException('Template PDF verification failed.', 0, $e);
        }
    }
}
