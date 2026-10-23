<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Actions;

use App\DocumentTemplate\Import\DocxEmailImporter;
use App\DocumentTemplate\Import\DocxPackageInspectionException;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDocument;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\User;
use App\Notifications\Templates\CommunicationTemplateMutation;
use App\Notifications\Templates\CommunicationTemplatePayload;
use App\Notifications\Templates\CommunicationTemplateState;
use App\Notifications\Templates\NoticePrintContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImportCommunicationTemplateDocument
{
    public function __construct(
        private readonly DocxEmailImporter $emailImporter,
        private readonly StoreCommunicationTemplateDocument $storeDocument,
        private readonly CommunicationTemplateMutation $mutation,
        private readonly CommunicationTemplateState $state,
        private readonly NoticePrintContext $printContext,
    ) {}

    public function handle(CommunicationTemplate $template, UploadedFile $file, User $actor, bool $replaceEmail = false, array $guards = [], string $purpose = 'email'): array
    {
        $this->mutation->authorize($template, $actor);
        abort_unless(in_array($purpose, ['email', 'print'], true)
            && (! isset($guards['purpose']) || $guards['purpose'] === $purpose), 422, 'Invalid Word upload purpose.');
        unset($guards['purpose']);
        if ($purpose === 'print') {
            abort_unless(NoticePrintContext::isOfficialScorecard($template), 422, 'Print-only Word upload is restricted to the online scorecard.');
            abort_if($replaceEmail || (bool) ($guards['replace_email'] ?? false), 422, 'Print-only Word upload cannot replace email.');
            abort_if(array_diff(array_keys($guards), ['base_version', 'family_revision', 'lock_version', 'idempotency_key', 'draft_id', 'replace_email']) !== [], 422, 'Print-only upload accepts source and revision guards, not result values.');
        }
        abort_unless($file->isValid() && is_string($file->getRealPath()), 422, 'Invalid DOCX upload.');
        $checksum = hash_file('sha256', $file->getRealPath());
        abort_unless(is_string($checksum), 422, 'Unable to checksum DOCX upload.');
        $data = [...$guards, 'source_sha256' => $checksum, 'replace_email' => $replaceEmail];
        if ($purpose === 'print') {
            $data['purpose'] = 'print';
        }
        $pending = [];
        $pendingPaths = [];
        try {
            return $this->mutation->run($template, $data, $actor, 'document_imported', 201, function (CommunicationTemplate $locked, ?CommunicationTemplateDraft $draft) use ($file, $actor, $replaceEmail, $purpose, $checksum, &$pending, &$pendingPaths): array {
                $source = $this->storeDocument->store($locked, $file, $actor);
                $pending[] = $source->id;
                $pendingPaths[] = $source->storage_path;
                abort_unless(hash_equals($checksum, $source->checksum), 422, 'Uploaded source changed during import.');
                $source = $this->storeDocument->verified($locked, $source->id, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                if ($purpose === 'print') {
                    return $this->installPrintSource($locked, $draft, $source, $actor);
                }
                $result = $this->emailImporter->import(Storage::disk('local')->path($source->storage_path), storeImage: function (array $image) use ($locked, $actor, &$pending, &$pendingPaths): string {
                    $asset = $this->storeDocument->storeImageBytes($locked, $image['bytes'], $image['mime'], $actor);
                    $pending[] = $asset->id;
                    $pendingPaths[] = $asset->storage_path;

                    return (string) $asset->id;
                }, allowedTokens: CommunicationTemplateState::tokens($locked), requiredTokens: self::requiredTokens($locked));
                abort_unless(hash_equals($source->checksum, $result->inspection->sha256), 422, 'Persisted source changed during import.');
                $report = $result->report;
                unset($report['report_id'], $report['report_sha256']);
                $reportBytes = CommunicationTemplatePayload::json($report);
                $reportAsset = $this->storeDocument->storeReport($locked, $reportBytes, $actor);
                $pending[] = $reportAsset->id;
                $pendingPaths[] = $reportAsset->storage_path;
                $provenance = ['source_asset_id' => (string) $source->id, 'source_sha256' => $source->checksum,
                    'converter_version' => DocxEmailImporter::CONVERTER_VERSION, 'report_id' => (string) $reportAsset->id,
                    'report_sha256' => $reportAsset->checksum, 'mapped_blocks' => $report['mapped_blocks_count'],
                    'findings' => $report['findings'], 'review_acknowledgment' => null,
                    'candidate_email_blueprint' => $result->candidateBlueprint,
                    'candidate_email_blueprint_sha256' => CommunicationTemplateState::emailDigest($result->candidateBlueprint)];
                $initial = $draft ?? $locked->versions()->where('version', $locked->active_version)->firstOrFail();
                $state = $draft === null ? CommunicationTemplateState::draftStateFromVersion($initial) : CommunicationTemplateState::state($draft);
                $hasEmail = is_array($state['email_blueprint']) && ($state['email_blueprint']['blocks'] ?? []) !== [];
                $installed = $replaceEmail || ! $hasEmail;
                if ($installed) {
                    $state['email_blueprint'] = $result->candidateBlueprint;
                }
                // Import provenance and print selection are independent; a reminder never gains a PDF.
                $draft = $this->state->persist($locked, $draft, $state, 2, $actor, $provenance);
                if ($initial !== $draft && ($initial->content_schema_version !== 'notice-content-2' || $initial->email_blueprint === null)) {
                    $draft->update(['email_subject' => $initial->email_subject, 'email_body' => $initial->email_body,
                        'in_app_title' => $initial->in_app_title, 'in_app_body' => $initial->in_app_body]);
                }

                return ['draft' => CommunicationTemplateState::draft($draft), 'content_sha256' => $draft->content_sha256,
                    'import' => ['report_id' => $reportAsset->id, 'report_sha256' => $reportAsset->checksum,
                        'source_asset_id' => $source->id, 'source_sha256' => $source->checksum,
                        'candidate_email_blueprint' => $result->candidateBlueprint, 'requires_review' => true,
                        'findings' => $report['findings'], 'mapped_blocks' => $report['mapped_blocks_count'],
                        'email_blueprint_sha256' => CommunicationTemplateState::emailDigest($draft->email_blueprint), 'installed' => $installed]];
            });
        } catch (Throwable $exception) {
            $this->storeDocument->deletePending($pending);
            foreach ($pendingPaths as $index => $path) {
                if (! CommunicationTemplateDocument::query()->whereKey($pending[$index])->exists()) {
                    Storage::disk('local')->delete($path);
                }
            }
            if ($exception instanceof DocxPackageInspectionException) {
                throw ValidationException::withMessages(['document' => $exception->getMessage()]);
            }
            throw $exception;
        }
    }

    private function installPrintSource(CommunicationTemplate $template, ?CommunicationTemplateDraft $draft, CommunicationTemplateDocument $source, User $actor): array
    {
        $diagnostics = $this->printContext->analyzeDocx($template, Storage::disk('local')->path($source->storage_path));
        if (($diagnostics['valid'] ?? false) !== true) {
            throw new HttpResponseException(response()->json(['error' => 'print_document_invalid',
                'message' => 'Correct the scorecard Word placeholders before selecting this source.',
                'errors' => ['document' => ['The original scorecard has invalid, unknown or missing required print placeholders.']],
                'diagnostics' => $diagnostics], 422));
        }
        $initial = $draft ?? $template->versions()->where('version', $template->active_version)->firstOrFail();
        $state = $draft === null ? CommunicationTemplateState::draftStateFromVersion($initial) : CommunicationTemplateState::state($draft);
        $previousMode = $state['document_mode'];
        if (in_array($previousMode, ['docx', 'builder'], true)) {
            $inactive = $state['document_source_drafts'] ?? [];
            $inactive[$previousMode] = ['document_asset_id' => $state['document_asset_id'], 'builder_json' => $state['builder_json'],
                'paper_size' => $state['paper_size'], 'orientation' => $state['orientation']];
            $state['document_source_drafts'] = $inactive;
        }
        $state['document_mode'] = 'docx';
        $state['document_asset_id'] = (string) $source->id;
        $state['builder_json'] = null;
        $state['paper_size'] = null;
        $state['orientation'] = null;
        $draft = $this->state->persist($template, $draft, $state, $draft?->stage ?? 2, $actor,
            $draft === null ? $state['import_provenance'] : null);
        if ($initial !== $draft && ($initial->content_schema_version !== 'notice-content-2' || $initial->email_blueprint === null)) {
            $draft->update(['email_subject' => $initial->email_subject, 'email_body' => $initial->email_body,
                'in_app_title' => $initial->in_app_title, 'in_app_body' => $initial->in_app_body]);
        }
        $filename = (string) preg_replace('/[\x00-\x1f\x7f]/', '', basename(str_replace('\\', '/', $source->original_name)));

        return ['draft' => CommunicationTemplateState::draft($draft), 'content_sha256' => $draft->content_sha256,
            'source' => ['asset_id' => (string) $source->id, 'sha256' => $source->checksum, 'filename' => $filename,
                'mime' => $source->mime_type, 'purpose' => 'print'], 'diagnostics' => $diagnostics];
    }

    public static function requiredTokens(CommunicationTemplate $template): array
    {
        return [];
    }
}
