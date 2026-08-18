<?php

namespace App\Release\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Release\ResultDocument;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\ResultSheet\Data\ResultSheetDataProvider;
use App\ResultSheet\Rendering\ResultSheetCssService;
use App\ResultSheet\Support\DocxTextExtractor;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Produces the template-backed, checksummed, privately stored ResultDocument
 * that ReleaseResult consumes. The active result-sheet template drives the
 * artifact (HTML content filled with the applicant's released scoring data;
 * DOCX templates produce a filled text preview). A document is only marked
 * ready after successful generation — failures throw and never fake-ready.
 */
class PrepareResultDocument
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly ResultSheetDataProvider $dataProvider,
        private readonly ResultSheetPlaceholderService $placeholders,
        private readonly ResultSheetCssService $css,
        private readonly DocxTextExtractor $extractor,
    ) {}

    public function handle(AdmissionAttempt $attempt, User $actor, string $templateVersion = 'e2-v1'): ResultDocument
    {
        return DB::transaction(function () use ($attempt, $actor): ResultDocument {
            $template = ResultSheetTemplate::active();

            if ($template === null) {
                throw new RuntimeException('No active result sheet template.');
            }

            $data = $this->dataProvider->forAttempt($attempt);
            $replacements = $this->placeholders->buildReplacements([$data]);

            if ($template->mode === ResultSheetTemplate::MODE_DOCX && $template->document_path !== null) {
                $sourcePath = Storage::disk('local')->path($template->document_path);

                if (! is_file($sourcePath)) {
                    throw new RuntimeException('Template document file not found on disk.');
                }

                $text = $this->extractor->text($sourcePath);
                $html = $this->placeholders->renderRaw('<pre class="docx-preview">'.e($text).'</pre>', $replacements);
            } else {
                $html = $this->placeholders->renderRaw((string) $template->content, $replacements);
            }

            $html = $this->css->wrapForPdf($html, $template->paper_size, $template->orientation);

            $checksum = hash('sha256', $html);
            $path = 'results/'.$attempt->id.'/'.$checksum.'.html';

            Storage::disk('local')->put($path, $html);

            $document = ResultDocument::query()->create([
                'admission_attempt_id' => $attempt->id,
                'template_version' => 'template:'.$template->id,
                'checksum' => $checksum,
                'status' => ResultDocument::STATUS_READY,
                'storage_path' => $path,
                'preview_path' => $path,
            ]);

            $this->recordAuditEvent->handle('release.document_prepared', [
                'document_id' => $document->id,
                'attempt_id' => $attempt->id,
                'template_id' => $template->id,
                'checksum' => $checksum,
            ], (string) $actor->id);

            return $document;
        });
    }
}
