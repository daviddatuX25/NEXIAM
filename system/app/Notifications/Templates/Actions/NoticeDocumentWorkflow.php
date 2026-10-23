<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Actions;

use App\DocumentTemplate\Rendering\ComponentRendererRegistry;
use App\DocumentTemplate\Rendering\DocumentBlueprintCompiler;
use App\DocumentTemplate\Services\DocumentThumbnailService;
use App\DocumentTemplate\Support\MacroScanner;
use App\DocumentTemplate\Validation\DocumentBlueprintValidator;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Notifications\RenderNoticeDocumentDraftThumbnailJob;
use App\Jobs\Notifications\RenderNoticeDocumentThumbnailJob;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\Notifications\CommunicationTemplateReceipt;
use App\Models\Notifications\NoticeDocumentDraft;
use App\Models\Notifications\NoticeDocumentVersion;
use App\Models\User;
use App\Notifications\Services\NoticeDocumentAttachmentCompiler;
use App\Notifications\Support\CommunicationTemplateScope;
use App\Notifications\Templates\CommunicationTemplatePayload;
use App\Notifications\Templates\CommunicationTemplateRegistry;
use App\Notifications\Templates\NoticePrintContext;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;
use App\ResultSheet\Data\TemplateFieldAliasCatalog;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class NoticeDocumentWorkflow
{
    private const DRAFTS = 'notice-document-drafts';

    private const DOCUMENTS = 'notice-documents';

    public function __construct(
        private readonly CommunicationTemplateScope $scope,
        private readonly RecordAuditEvent $audit,
        private readonly StoreCommunicationTemplateDocument $sources,
        private readonly NoticeDocumentAttachmentCompiler $compiler,
        private readonly DocumentThumbnailService $thumbnails,
    ) {}

    /** @param array<string, mixed> $state */
    public function renderDigest(CommunicationTemplate $family, array $state): string
    {
        $builderJson = $state['builder_json'] ?? null;
        if (($state['source_kind'] ?? null) === 'builder' && is_array($builderJson)) {
            $builderJson = ResultSheetBuilderSchema::normalize($builderJson);
        }
        $canonicalState = [
            'source_kind' => $state['source_kind'] ?? null,
            'document_asset_id' => $state['document_asset_id'] ?? null,
            'builder_json' => $builderJson,
            'paper_size' => $state['paper_size'] ?? null,
            'orientation' => $state['orientation'] ?? null,
        ];
        $sourcePins = $this->sourcePins($family, $canonicalState);
        $verifiedPins = [
            ...$sourcePins,
            'paper_size' => $canonicalState['paper_size'],
            'orientation' => $canonicalState['orientation'],
            'catalog' => NoticePrintContext::catalog($family),
            'compiler' => 'notice-document-compiler-v1',
            'renderer' => 'notice-document-renderer-v1',
            'synthetic_context' => 'notice-document-synthetic-v1',
        ];
        if (array_key_exists('pins', $state)) {
            abort_unless(is_array($state['pins'])
                && hash_equals(CommunicationTemplatePayload::hash($verifiedPins), CommunicationTemplatePayload::hash($state['pins'])),
                422, 'notice_document_pins_changed');
        }

        return CommunicationTemplatePayload::hash([
            'state' => $canonicalState,
            'source_sha256' => $sourcePins['source_checksum'],
            'image_sha256s' => $sourcePins['image_sha256s'],
            'paper_size' => $verifiedPins['paper_size'],
            'orientation' => $verifiedPins['orientation'],
            'catalog' => $verifiedPins['catalog'],
            'compiler' => $verifiedPins['compiler'],
            'renderer' => $verifiedPins['renderer'],
            'synthetic_context' => $verifiedPins['synthetic_context'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{source_checksum: string|null, image_sha256s: array<string, string>}
     */
    private function sourcePins(CommunicationTemplate $family, array $state): array
    {
        $sourceChecksum = null;
        if (($state['source_kind'] ?? null) === 'docx' && is_string($state['document_asset_id'] ?? null)) {
            $sourceChecksum = $this->sources->verified(
                $family,
                $state['document_asset_id'],
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            )->checksum;
        }

        $imageChecksums = [];
        if (($state['source_kind'] ?? null) === 'builder' && is_array($state['builder_json'] ?? null)) {
            $builder = ResultSheetBuilderSchema::normalize($state['builder_json']);
            foreach (is_array($builder['components'] ?? null) ? $builder['components'] : [] as $component) {
                if (! is_array($component) || ($component['type'] ?? null) !== 'image') {
                    continue;
                }
                $props = is_array($component['props'] ?? null) ? $component['props'] : [];
                if (! array_key_exists('asset_id', $props)) {
                    continue;
                }
                $assetId = $props['asset_id'];
                abort_unless(is_string($assetId) && Str::isUuid($assetId), 422, 'notice_document_image_reference_invalid');
                $document = $this->sources->verified($family, $assetId);
                abort_unless(in_array($document->mime_type, ['image/png', 'image/jpeg'], true), 422, 'notice_document_image_type_invalid');
                $imageChecksums[$assetId] = $document->checksum;
            }
        }
        ksort($imageChecksums);

        return ['source_checksum' => $sourceChecksum, 'image_sha256s' => $imageChecksums];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function save(CommunicationTemplate $family, array $data, User $actor, ?int $draftId = null): array
    {
        return $this->mutate($family, $data, $actor, $draftId === null ? 'draft_create' : 'draft_update', $draftId, function (CommunicationTemplate $locked, ?NoticeDocumentDraft $draft) use ($data): array {
            $state = $data['state'];
            if ($draft !== null && is_array($draft->state)) {
                foreach (['legacy_source_drafts', 'legacy_import_provenance', 'legacy_selected_document_revision_id', 'legacy_message_source'] as $legacyField) {
                    if (array_key_exists($legacyField, $draft->state)) {
                        $state[$legacyField] = $draft->state[$legacyField];
                    }
                }
            }
            if (($state['source_kind'] ?? null) === 'builder' && is_array($state['builder_json'] ?? null)) {
                $state['builder_json'] = $this->canonicalBlueprint($locked, $state['builder_json']);
            }
            $this->validateState($locked, $state, allowIncomplete: true);
            $locked->increment('document_revision');
            if ($draft === null) {
                $draft = NoticeDocumentDraft::query()->create([
                    'communication_template_id' => $locked->id,
                    'base_version' => (int) ($locked->documentVersions()->max('version') ?? 0),
                    'document_revision' => $locked->document_revision,
                    'lock_version' => 1,
                    'stage' => (int) ($data['stage'] ?? 1),
                    'state' => $state,
                ]);
            } else {
                $draft->update([
                    'state' => $state,
                    'stage' => (int) ($data['stage'] ?? $draft->stage),
                    'lock_version' => $draft->lock_version + 1,
                    'document_revision' => $locked->document_revision,
                ]);
                $draft->refresh();
            }
            $render = $this->renderDigest($locked, $state);

            return [
                'document_draft' => $this->draftDto($draft, $render),
                'document_revision' => (int) $locked->document_revision,
                'latest_ready_version' => $this->latestDto($locked),
                'render_sha256' => $render,
            ];
        }, $draftId === null ? 201 : 200);
    }

    public function preserveLegacyDraft(CommunicationTemplate $family, CommunicationTemplateDraft $legacy, User $actor): void
    {
        if ($legacy->content_schema_version === 'notice-content-3') {
            return;
        }

        $legacySource = [
            'draft_id' => (string) $legacy->id,
            'content_schema_version' => $legacy->content_schema_version,
            'email_renderer_version' => $legacy->email_renderer_version,
            'stage' => $legacy->stage,
            'base_version' => $legacy->base_version,
            'subject' => $legacy->subject,
            'body' => $legacy->body,
            'action_label' => $legacy->action_label,
            'action_route' => $legacy->action_route,
            'email_blueprint' => $legacy->email_blueprint,
            'email_subject' => $legacy->email_subject,
            'email_body' => $legacy->email_body,
            'in_app_title' => $legacy->in_app_title,
            'in_app_body' => $legacy->in_app_body,
            'document_mode' => $legacy->document_mode,
            'document_asset_id' => $legacy->document_asset_id,
            'document_revision_id' => $legacy->document_revision_id,
            'notice_document_version_id' => $legacy->notice_document_version_id,
            'builder_json' => $legacy->builder_json,
            'paper_size' => $legacy->paper_size,
            'orientation' => $legacy->orientation,
            'document_source_drafts' => $legacy->document_source_drafts,
            'import_provenance' => $legacy->import_provenance,
            'content_sha256' => $legacy->content_sha256,
        ];
        $snapshot = ['schema' => 'notice-legacy-draft-snapshot-1', 'source' => $legacySource];
        $snapshotHash = CommunicationTemplatePayload::hash($snapshot);
        $snapshotReceipt = CommunicationTemplateReceipt::query()
            ->where('institution_id', $family->institution_id)
            ->where('domain', $family->domain)
            ->where('canonical_slug', $family->slug)
            ->where('operation', 'legacy_draft_snapshot')
            ->where('idempotency_key', 'legacy-draft-'.$legacy->id)
            ->lockForUpdate()
            ->first();
        if ($snapshotReceipt !== null) {
            abort_unless(hash_equals($snapshotReceipt->payload_sha256, $snapshotHash),
                409, 'The retained legacy message snapshot differs from its original. Review before editing.');
        } else {
            CommunicationTemplateReceipt::query()->create([
                'institution_id' => $family->institution_id,
                'domain' => $family->domain,
                'canonical_slug' => $family->slug,
                'operation' => 'legacy_draft_snapshot',
                'idempotency_key' => 'legacy-draft-'.$legacy->id,
                'payload_sha256' => $snapshotHash,
                'response_payload' => $snapshot,
                'created_by_user_id' => $actor->id,
            ]);
        }

        $mode = $legacy->document_mode;
        if (! in_array($mode, ['docx', 'builder'], true)) {
            return;
        }
        abort_unless(CommunicationTemplateRegistry::supportsPdf($family->domain, $family->slug, $family->event_key),
            422, 'Review the legacy print source before editing; this family has no independent PDF workflow.');
        $sourceId = $mode === 'docx' ? $legacy->document_asset_id : null;
        $builder = $mode === 'builder' ? $legacy->builder_json : null;
        $layoutIsSupported = in_array($legacy->paper_size, ['a4', 'letter', 'legal'], true)
            && in_array($legacy->orientation, ['portrait', 'landscape'], true);
        $sourceIsSupported = $mode === 'docx'
            ? is_string($sourceId) && Str::isUuid($sourceId)
            : is_array($builder);
        abort_unless($layoutIsSupported && $sourceIsSupported,
            422, 'Review the legacy print source before editing; its layout or source cannot be mapped safely. The original draft remains unchanged.');
        if ($mode === 'docx') {
            $this->sources->verified(
                $family,
                $sourceId,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            );
        }

        if (NoticeDocumentDraft::query()->where('communication_template_id', $family->id)->exists()) {
            abort(422, 'Review the existing independent document draft before converting this legacy message. The original draft remains unchanged.');
        }

        $mappedState = [
            'source_kind' => $mode,
            'document_asset_id' => $sourceId,
            'builder_json' => $builder,
            'paper_size' => $legacy->paper_size,
            'orientation' => $legacy->orientation,
            'legacy_source_drafts' => $legacy->document_source_drafts,
            'legacy_import_provenance' => $legacy->import_provenance,
            'legacy_selected_document_revision_id' => $legacy->document_revision_id,
            'legacy_message_source' => $legacySource,
        ];
        try {
            $this->renderDigest($family, $mappedState);
        } catch (HttpResponseException) {
            abort(422, 'Review the legacy print source before editing; its source references cannot be mapped safely. The original draft remains unchanged.');
        }
        $family->increment('document_revision');
        NoticeDocumentDraft::query()->create([
            'communication_template_id' => $family->id,
            'base_version' => (int) ($family->documentVersions()->max('version') ?? 0),
            'document_revision' => (int) $family->document_revision,
            'lock_version' => 1,
            'stage' => (int) $legacy->stage,
            'state' => $mappedState,
        ]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function discard(CommunicationTemplate $family, array $data, User $actor, int $draftId): array
    {
        return $this->mutate($family, $data, $actor, 'draft_discard', $draftId, function (CommunicationTemplate $locked, ?NoticeDocumentDraft $draft): array {
            abort_if($draft === null, 404);
            $draft->delete();
            $locked->increment('document_revision');

            return [
                'document_draft' => null,
                'document_revision' => (int) $locked->document_revision,
                'latest_ready_version' => $this->latestDto($locked),
            ];
        }, 200);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function upload(CommunicationTemplate $family, UploadedFile $file, array $data, User $actor): array
    {
        $draftId = (int) $data['document_draft_id'];
        $sourcePath = null;
        $data['document'] = $file;

        try {
            return $this->mutate($family, $data, $actor, 'document_upload', $draftId, function (CommunicationTemplate $locked, ?NoticeDocumentDraft $draft) use ($file, $actor, &$sourcePath): array {
                abort_if($draft === null, 404);
                $source = $this->sources->store($locked, $file, $actor);
                $sourcePath = $source->storage_path;
                $state = $draft->state;
                $state['source_kind'] = 'docx';
                $state['document_asset_id'] = $source->id;
                $state['builder_json'] = null;
                $diagnostics = $this->validateState($locked, $state, allowIncomplete: true);
                $locked->increment('document_revision');
                $draft->update([
                    'state' => $state,
                    'lock_version' => $draft->lock_version + 1,
                    'document_revision' => $locked->document_revision,
                ]);
                $draft->refresh();
                $render = $this->renderDigest($locked, $state);

                return [
                    'document_draft' => $this->draftDto($draft, $render),
                    'document_revision' => (int) $locked->document_revision,
                    'latest_ready_version' => $this->latestDto($locked),
                    'render_sha256' => $render,
                    'source' => [
                        'asset_id' => $source->id,
                        'sha256' => $source->checksum,
                        'filename' => $source->original_name,
                        'mime' => $source->mime_type,
                    ],
                    'diagnostics' => $diagnostics,
                ];
            }, 200);
        } catch (Throwable $exception) {
            if ($sourcePath !== null) {
                Storage::disk('local')->delete($sourcePath);
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function storeImage(CommunicationTemplate $family, UploadedFile $file, array $data, User $actor): array
    {
        $draftId = (int) $data['document_draft_id'];
        $sourcePath = null;
        $data['image'] = $file;

        try {
            return $this->mutate($family, $data, $actor, 'image_upload', $draftId, function (CommunicationTemplate $locked, ?NoticeDocumentDraft $draft) use ($file, $actor, &$sourcePath): array {
                abort_if($draft === null, 404);
                $source = $this->sources->storeImage($locked, $file, $actor);
                $sourcePath = $source->storage_path;
                $raw = Storage::disk('local')->get($source->storage_path);
                abort_unless(is_string($raw), 500, 'Image bytes unavailable.');
                $info = @getimagesizefromstring($raw);
                $width = $info[0] ?? 0;
                $height = $info[1] ?? 0;
                $previewUrl = route('setup.notifications.templates.document.images.show', [
                    'domain' => $locked->domain,
                    'slug' => $locked->slug,
                    'asset' => $source->id,
                ], false);

                return [
                    'asset' => [
                        'id' => $source->id,
                        'sha256' => $source->checksum,
                        'filename' => $source->original_name,
                        'mime' => $source->mime_type,
                        'width' => $width,
                        'height' => $height,
                        'url' => $previewUrl,
                    ],
                    'preview_url' => $previewUrl,
                    'document_revision' => (int) $locked->document_revision,
                    'lock_version' => $draft->lock_version,
                    'base_version' => $draft->base_version,
                ];
            }, 201);
        } catch (Throwable $exception) {
            if ($sourcePath !== null) {
                Storage::disk('local')->delete($sourcePath);
            }

            throw $exception;
        }
    }

    public function imageBytes(CommunicationTemplate $family, string $assetId, User $actor): string
    {
        $this->authorize($family, $actor);
        abort_unless(Str::isUuid($assetId), 404);
        $doc = $this->sources->verified($family, $assetId);
        abort_unless(in_array($doc->mime_type, ['image/png', 'image/jpeg'], true), 404);
        $bytes = Storage::disk('local')->get($doc->storage_path);
        abort_unless(is_string($bytes), 404);

        return $bytes;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function preview(CommunicationTemplate $family, array $data, User $actor): array
    {
        $this->authorize($family, $actor);
        $locked = CommunicationTemplate::query()->whereKey($family->id)->firstOrFail();
        $draft = NoticeDocumentDraft::query()->where('communication_template_id', $locked->id)->firstOrFail();
        $this->guards($locked, $draft, $data, null);
        $render = $this->renderDigest($locked, $draft->state);
        abort_unless(hash_equals($render, $data['render_sha256']), 409, 'document_render_stale');
        $diagnostics = $this->validateState($locked, $draft->state, allowIncomplete: false);
        $pdfPath = $this->compile($locked, $draft->state);
        try {
            $bytes = file_get_contents($pdfPath);
            abort_unless(is_string($bytes) && str_starts_with($bytes, '%PDF-'), 422, 'notice_pdf_invalid');
            abort_if(preg_match('#/(?:JavaScript|JS|Launch|EmbeddedFile|RichMedia|XFA)\b#', $bytes) === 1, 422, 'notice_pdf_active_content');
            $sha = hash('sha256', $bytes);

            return DB::transaction(function () use ($family, $actor, $locked, $draft, $render, $sha, $bytes, $diagnostics): array {
                $freshFamily = CommunicationTemplate::query()->whereKey($family->id)->lockForUpdate()->firstOrFail();
                $freshDraft = NoticeDocumentDraft::query()->where('communication_template_id', $family->id)->lockForUpdate()->firstOrFail();
                $this->authorize($freshFamily, $actor);
                abort_unless(
                    (int) $freshFamily->document_revision === (int) $locked->document_revision
                    && (int) $freshDraft->lock_version === (int) $draft->lock_version
                    && hash_equals($this->renderDigest($freshFamily, $freshDraft->state), $render),
                    409,
                    'document_render_stale'
                );

                $receipt = Crypt::encryptString(json_encode([
                    'family_id' => $freshFamily->id,
                    'institution_id' => $freshFamily->institution_id,
                    'draft_id' => $freshDraft->id,
                    'actor_id' => $actor->id,
                    'render_sha256' => $render,
                    'pdf_sha256' => $sha,
                    'document_revision' => $freshFamily->document_revision,
                ], JSON_THROW_ON_ERROR));

                return [
                    'render_sha256' => $render,
                    'pdf' => ['pdf_base64' => base64_encode($bytes), 'sha256' => $sha],
                    'preview_receipt' => $receipt,
                    'diagnostics' => $diagnostics,
                ];
            });
        } finally {
            @unlink($pdfPath);
            @rmdir(dirname($pdfPath));
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createVersion(CommunicationTemplate $family, array $data, User $actor): array
    {
        return $this->mutate($family, $data, $actor, 'version_create', null, function (CommunicationTemplate $locked, ?NoticeDocumentDraft $draft) use ($data, $actor): array {
            abort_if($draft === null, 409, 'document_draft_missing');
            $render = $this->renderDigest($locked, $draft->state);
            abort_unless(hash_equals($render, $data['render_sha256']), 409, 'document_render_stale');
            $this->validateState($locked, $draft->state, allowIncomplete: false);
            $this->verifiedProof($data['preview_receipt'], $locked, $draft, $actor, $render);

            $sourcePins = $this->sourcePins($locked, $draft->state);
            $versionNumber = ((int) ($locked->documentVersions()->max('version') ?? 0)) + 1;
            $definition = $draft->state;
            if (($definition['source_kind'] ?? null) === 'builder' && is_array($definition['builder_json'] ?? null)) {
                $definition['builder_json'] = $this->canonicalBlueprint($locked, $definition['builder_json']);
            }
            $definition['pins'] = [
                ...$sourcePins,
                'paper_size' => $draft->state['paper_size'] ?? 'a4',
                'orientation' => $draft->state['orientation'] ?? 'portrait',
                'catalog' => NoticePrintContext::catalog($locked),
                'compiler' => 'notice-document-compiler-v1',
                'renderer' => 'notice-document-renderer-v1',
                'synthetic_context' => 'notice-document-synthetic-v1',
            ];
            abort_unless(hash_equals($render, $this->renderDigest($locked, $definition)), 409, 'document_render_stale');

            $version = NoticeDocumentVersion::query()->create([
                'communication_template_id' => $locked->id,
                'version' => $versionNumber,
                'state' => $definition,
                'render_sha256' => $render,
                'definition_sha256' => CommunicationTemplatePayload::hash($definition),
                'proof_receipt' => $data['preview_receipt'],
                'created_by_user_id' => $actor->id,
            ]);
            $draft->delete();
            $locked->increment('document_revision');

            DB::afterCommit(function () use ($version, $render): void {
                RenderNoticeDocumentThumbnailJob::dispatch((int) $version->id, $render);
            });

            return [
                'document_version' => $this->versionDto($version),
                'document_revision' => (int) $locked->document_revision,
                'document_draft' => null,
                'return_url' => '/setup/notifications/'.$locked->domain.'/templates/'.$locked->slug.'/edit',
            ];
        }, 201);
    }

    public function pdf(CommunicationTemplate $family, NoticeDocumentVersion $version, User $actor): string
    {
        $this->authorize($family, $actor);
        abort_unless((string) $version->communication_template_id === (string) $family->id, 404);
        $proof = $this->proofData($version->proof_receipt);
        abort_unless(($proof['render_sha256'] ?? null) === $version->render_sha256, 404);
        abort_unless(hash_equals($version->render_sha256, $this->renderDigest($family, $version->state)), 404, 'notice_document_source_changed');

        $path = $this->pdfForState($family, $version->state);
        try {
            abort_unless(hash_equals($version->render_sha256, $this->renderDigest($family, $version->state)), 404, 'notice_document_source_changed');
            $this->authorize($family, $actor);

            return $path;
        } catch (Throwable $exception) {
            @unlink($path);
            @rmdir(dirname($path));

            throw $exception;
        }
    }

    public function thumbnail(CommunicationTemplate $family, NoticeDocumentVersion $version, User $actor): ?string
    {
        $this->authorize($family, $actor);
        abort_unless((string) $version->communication_template_id === (string) $family->id, 404);
        abort_unless(hash_equals($version->render_sha256, $this->renderDigest($family, $version->state)), 404, 'notice_document_source_changed');
        $bytes = $this->thumbnails->read(self::DOCUMENTS, (int) $version->id, $this->thumbnails->fingerprint($version->render_sha256));
        if ($bytes === null) {
            RenderNoticeDocumentThumbnailJob::dispatch((int) $version->id, $version->render_sha256);
        }

        return $bytes;
    }

    public function draftThumbnail(CommunicationTemplate $family, NoticeDocumentDraft $draft, User $actor): ?string
    {
        $this->authorize($family, $actor);
        abort_unless((string) $draft->communication_template_id === (string) $family->id, 404);
        $render = $this->renderDigest($family, $draft->state);
        $bytes = $this->thumbnails->read(self::DRAFTS, (int) $draft->id, $this->thumbnails->fingerprint($render));
        if ($bytes === null) {
            RenderNoticeDocumentDraftThumbnailJob::dispatch((int) $draft->id, $render);
        }

        return $bytes;
    }

    public function warmThumbnail(NoticeDocumentVersion $version): void
    {
        $family = $version->template()->firstOrFail();
        $render = $this->renderDigest($family, $version->state);
        abort_unless(hash_equals($version->render_sha256, $render), 422, 'notice_document_source_changed');
        $this->validateState($family, $version->state, allowIncomplete: false);
        $pdf = $this->pdfForState($family, $version->state);
        try {
            $bytes = file_get_contents($pdf);
            $current = NoticeDocumentVersion::query()->find($version->id);
            $currentFamily = $current?->template()->first();
            if ($current === null || $currentFamily === null
                || ! hash_equals($current->render_sha256, $render)
                || ! hash_equals($render, $this->renderDigest($currentFamily, $current->state))
                || ! is_string($bytes) || ! str_starts_with($bytes, '%PDF-')) {
                return;
            }
            $this->thumbnails->store(self::DOCUMENTS, (int) $version->id, $render, $bytes);
        } finally {
            @unlink($pdf);
            @rmdir(dirname($pdf));
        }
    }

    public function warmDraftThumbnail(NoticeDocumentDraft $draft): void
    {
        $draftId = (int) $draft->id;
        $lockVersion = (int) $draft->lock_version;
        $documentRevision = (int) $draft->document_revision;
        $state = $draft->state;
        $family = $draft->template()->firstOrFail();
        $render = $this->renderDigest($family, $state);
        $this->validateState($family, $state, allowIncomplete: false);
        $pdf = $this->pdfForState($family, $state);
        try {
            $bytes = file_get_contents($pdf);
            $currentDraft = NoticeDocumentDraft::query()->whereKey($draftId)->first();
            if ($currentDraft === null
                || (int) $currentDraft->lock_version !== $lockVersion
                || (int) $currentDraft->document_revision !== $documentRevision) {
                return;
            }
            $currentFamily = $currentDraft->template()->first();
            if ($currentFamily === null
                || (int) $currentFamily->document_revision !== $documentRevision
                || ! hash_equals($render, $this->renderDigest($currentFamily, $currentDraft->state))
                || ! is_string($bytes)
                || ! str_starts_with($bytes, '%PDF-')) {
                return;
            }
            $this->thumbnails->store(self::DRAFTS, $draftId, $render, $bytes);
        } finally {
            @unlink($pdf);
            @rmdir(dirname($pdf));
        }
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    private function validateState(CommunicationTemplate $family, array $state, bool $allowIncomplete): array
    {
        abort_unless(in_array($state['source_kind'] ?? null, ['builder', 'docx'], true)
            && in_array($state['paper_size'] ?? null, ['a4', 'letter', 'legal'], true)
            && in_array($state['orientation'] ?? null, ['portrait', 'landscape'], true), 422, 'notice_document_state_invalid');
        abort_if(($state['source_kind'] === 'builder' && ($state['document_asset_id'] ?? null) !== null)
            || ($state['source_kind'] === 'docx' && ($state['builder_json'] ?? null) !== null), 422, 'notice_document_source_ambiguous');
        if (($state['source_kind'] ?? null) === 'builder') {
            $blueprint = $state['builder_json'] ?? null;
            if (! is_array($blueprint)) {
                abort_if(! $allowIncomplete, 422, 'notice_document_source_empty');

                return ['valid' => false, 'missing_required' => NoticePrintContext::catalog($family)['required']];
            }
            $blueprint = $this->canonicalBlueprint($family, $blueprint);
            $this->validateBuilderImages($family, $blueprint, $allowIncomplete);
            $catalog = NoticePrintContext::catalog($family);
            $analysis = NoticePrintContext::analyzeBuilder($blueprint);
            $availableValues = $this->syntheticContext($family, ($state['source_kind'] ?? null) === 'docx');
            $visible = $this->visibleBuilderContent($this->resolveBuilderImages($family, $blueprint), $availableValues);
            $bindings = array_map(TemplateFieldAliasCatalog::canonical(...), $visible['bindings']);
            $missing = array_values(array_diff($catalog['required'], $bindings));
            $missingContext = array_values(array_filter($analysis['bindings'], static fn (string $key): bool => ! array_key_exists($key, $availableValues)));
            foreach ($visible['bindings'] as $key) {
                if (in_array(TemplateFieldAliasCatalog::canonical($key), $catalog['required'], true)
                    && preg_match('/[^\s\p{Z}]/u', strip_tags((string) ($availableValues[$key] ?? ''))) !== 1) {
                    $missingContext[] = $key;
                }
            }

            if (! $allowIncomplete) {
                abort_unless($visible['has_printable_content'], 422, 'notice_document_source_empty');
                abort_unless($analysis['unresolved'] === [] && $missing === [], 422, 'notice_document_required_fields_missing');
                abort_unless(array_diff($analysis['bindings'], $catalog['all']) === [], 422, 'notice_document_unknown_binding');
                abort_unless($missingContext === [], 422, 'notice_document_context_unresolved');
            }

            return [
                'valid' => $visible['has_printable_content'] && $missing === [] && $missingContext === [],
                'missing_required' => $missing,
                'unknown' => array_values(array_diff($analysis['bindings'], $catalog['all'])),
                'unresolved' => $analysis['unresolved'],
                'missing_context' => $missingContext,
            ];
        }
        if (($state['source_kind'] ?? null) === 'docx' && is_string($state['document_asset_id'] ?? null)) {
            $document = $this->sources->verified($family, $state['document_asset_id'], 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            $path = Storage::disk('local')->path($document->storage_path);
            $analysis = app(NoticePrintContext::class)->analyzeDocx($family, $path);
            if (! $allowIncomplete) {
                abort_unless($analysis['has_printable_content'], 422, 'notice_document_source_empty');
                abort_unless($analysis['valid'], 422, 'notice_document_required_fields_missing');
            }

            return $analysis;
        }
        abort_if(! $allowIncomplete, 422, 'notice_document_source_empty');

        return ['valid' => false, 'missing_required' => NoticePrintContext::catalog($family)['required']];
    }

    /** @param array<string, mixed> $blueprint @return array<string, mixed> */
    private function canonicalBlueprint(CommunicationTemplate $family, array $blueprint): array
    {
        $errors = app(DocumentBlueprintValidator::class)->validate($blueprint, NoticePrintContext::catalog($family)['all']);
        abort_if($errors !== [], 422, 'notice_document_blueprint_invalid');
        $canonical = ResultSheetBuilderSchema::normalize($blueprint);
        foreach ($blueprint['components'] ?? [] as $index => $component) {
            abort_unless(is_array($component), 422, 'notice_document_blueprint_invalid');
            abort_if(isset($component['style']), 422, 'notice_document_style_invalid');
            $type = (string) ($component['type'] ?? '');
            $raw = is_array($component['props'] ?? null) ? $component['props'] : [];
            abort_if(isset($raw['style']), 422, 'notice_document_style_invalid');
            $normalized = $canonical['components'][$index]['props'];
            $groups = [
                'typography' => ResultSheetBuilderSchema::typographyDefaults($type),
                'appearance' => ResultSheetBuilderSchema::appearanceDefaults($type),
                'spacing' => ResultSheetBuilderSchema::spacingDefaults($type),
            ];
            foreach ($groups as $group => $allowed) {
                if (array_key_exists($group, $raw)) {
                    $this->validateRawStyle($raw[$group], $normalized[$group], $allowed);
                }
            }
            if (array_key_exists('text_styles', $raw)) {
                abort_unless(is_array($raw['text_styles']), 422, 'notice_document_style_invalid');
                foreach ($raw['text_styles'] as $part => $style) {
                    abort_unless(in_array($part, ResultSheetBuilderSchema::textPartOptions($type), true), 422, 'notice_document_style_invalid');
                    $this->validateRawStyle($style, $normalized['text_styles'][$part], ResultSheetBuilderSchema::textPartDefaults($type, $part));
                }
            }
            $flatStyleMap = [
                'font_family' => ['typography', 'font_family'],
                'font_size' => ['typography', 'font_size_pt'],
                'font_size_pt' => ['typography', 'font_size_pt'],
                'font_weight' => ['typography', 'font_weight'],
                'color' => ['typography', 'text_color'],
                'text_color' => ['typography', 'text_color'],
                'text_align' => ['typography', 'text_align'],
                'line_height' => ['typography', 'line_height'],
                'letter_spacing_px' => ['typography', 'letter_spacing_px'],
                'background' => ['appearance', 'background_color'],
                'background_color' => ['appearance', 'background_color'],
                'border_color' => ['appearance', 'border_color'],
                'border_style' => ['appearance', 'border_style'],
                'border_width_px' => ['appearance', 'border_width_px'],
                'display' => null,
                'visibility' => null,
                'opacity' => null,
            ];
            foreach ($flatStyleMap as $key => $target) {
                if (array_key_exists($key, $raw)) {
                    abort_if($target === null, 422, 'notice_document_style_invalid');
                    [$group, $targetKey] = $target;
                    $this->validateRawStyle([$targetKey => $raw[$key]], [$targetKey => $normalized[$group][$targetKey]], [$targetKey => $normalized[$group][$targetKey]]);
                }
            }
        }

        return $canonical;
    }

    /** @param array<string, mixed> $normalized @param array<string, mixed> $allowed */
    private function validateRawStyle(mixed $raw, array $normalized, array $allowed): void
    {
        abort_unless(is_array($raw) && array_diff(array_keys($raw), array_keys($allowed)) === [], 422, 'notice_document_style_invalid');
        foreach ($raw as $key => $value) {
            abort_if(is_string($value) && preg_match('/[;{}]|\b(?:display|visibility|opacity|font-size)\s*:/i', $value) === 1, 422, 'notice_document_style_invalid');
            $safe = $normalized[$key];
            $matches = is_int($safe) || is_float($safe)
                ? is_numeric($value) && is_finite((float) $value) && (float) $value === (float) $safe
                : is_string($value) && trim($value) === $safe;
            abort_unless($matches, 422, 'notice_document_style_invalid');
        }
    }

    /** @return array<string, mixed> */
    private function syntheticContext(CommunicationTemplate $family, bool $forDocx = false): array
    {
        if (! NoticePrintContext::isOfficialScorecard($family)) {
            $context = NoticePrintContext::sampleContext($family);
            $values = is_array($context['values'] ?? null) ? $context['values'] : $context;
            foreach (TemplateFieldAliasCatalog::aliases() as $alias => $canonical) {
                if (isset($values[$canonical]) && ! isset($values[$alias])) {
                    $values[$alias] = $values[$canonical];
                }
            }

            return $values;
        }
        $service = app(ResultSheetPlaceholderService::class);
        $applicant = $service->sampleData();
        $applicant['name'] = 'Synthetic scorecard applicant';
        $applicant['reference'] = 'SYNTHETIC-PRINT';
        $values = $service->buildReplacements([$applicant]);
        $values['institution_name'] = 'Synthetic institution';
        foreach (TemplateFieldAliasCatalog::aliases() as $alias => $canonical) {
            if (isset($values[$canonical]) && ! isset($values[$alias])) {
                $values[$alias] = $values[$canonical];
            }
        }
        if ($forDocx) {
            $rows = implode("\n", array_map(static fn (array $score): string => (string) $score['domain'].': '.(string) $score['raw'].' — '.(string) $score['converted'], $applicant['scores']));
            foreach ($values as $key => $value) {
                if (TemplateFieldAliasCatalog::canonical($key) === 'scores_rows') {
                    $values[$key] = $rows;
                }
            }
        }

        return $values;
    }

    /**
     * Inspect instantiated visible source rather than counting unfilled or
     * invisible macros as content. Raster images do not need extracted text.
     *
     * @param  array<string, mixed>  $blueprint
     * @param  array<string, mixed>  $values
     * @return array{bindings: list<string>, has_printable_content: bool}
     */
    private function visibleBuilderContent(array $blueprint, array $values): array
    {
        $html = (new DocumentBlueprintCompiler(ComponentRendererRegistry::withDefaults()))->compile($blueprint);
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($dom);
        $bindings = [];
        $printable = false;
        foreach ($xpath->query('//body//text()[not(ancestor::script) and not(ancestor::style)] | //body//img') as $node) {
            $element = $node instanceof DOMElement ? $node : $node->parentNode;
            if (! $element instanceof DOMElement || ! $this->isVisibleElement($element, $node instanceof DOMElement)) {
                continue;
            }
            if ($node instanceof DOMElement) {
                $printable = $printable || preg_match('#^data:image/(?:png|jpeg);base64,#i', $node->getAttribute('src')) === 1;

                continue;
            }
            $text = $node->textContent;
            foreach (MacroScanner::scan($text)['found'] as $binding) {
                $bindings[$binding] = $binding;
            }
            $text = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/u',
                static fn (array $match): string => strip_tags((string) ($values[$match[1]] ?? '')), $text) ?? $text;
            $printable = $printable || preg_match('/[^\s\p{Z}]/u', $text) === 1;
        }

        return ['bindings' => array_values($bindings), 'has_printable_content' => $printable];
    }

    private function isVisibleElement(DOMElement $element, bool $isImage): bool
    {
        $color = null;
        $background = null;
        for ($current = $element; $current instanceof DOMElement; $current = $current->parentNode) {
            if ($current->hasAttribute('hidden')) {
                return false;
            }
            $styles = [];
            foreach (explode(';', strtolower($current->getAttribute('style'))) as $declaration) {
                $pair = explode(':', $declaration, 2);
                if (count($pair) === 2) {
                    $styles[trim($pair[0])] = trim(str_replace('!important', '', $pair[1]));
                }
            }
            if (($styles['display'] ?? '') === 'none'
                || in_array($styles['visibility'] ?? '', ['hidden', 'collapse'], true)
                || (isset($styles['opacity']) && (float) $styles['opacity'] <= 0)
                || (! $isImage && isset($styles['font-size']) && (float) $styles['font-size'] <= 0)
                || (isset($styles['width']) && is_numeric(rtrim($styles['width'], 'pxm% ')) && (float) $styles['width'] <= 0)
                || (isset($styles['height']) && is_numeric(rtrim($styles['height'], 'pxm% ')) && (float) $styles['height'] <= 0)) {
                return false;
            }
            $color ??= $styles['color'] ?? null;
            if ($background === null && isset($styles['background-color'])
                && ! $this->transparentColor($styles['background-color'])) {
                $background = $styles['background-color'];
            }
        }
        if ($isImage) {
            return true;
        }
        $effectiveColor = $color ?? '#0f172a';

        return ! $this->transparentColor($effectiveColor) && $this->opaqueColor($effectiveColor) !== $this->opaqueColor($background ?? '#ffffff');
    }

    private function transparentColor(string $color): bool
    {
        return $color === 'transparent'
            || preg_match('/^#(?:[a-f0-9]{3}0|[a-f0-9]{6}00)$/iD', $color) === 1
            || preg_match('/^rgba\([^)]*,\s*0(?:\.0+)?\s*\)$/iD', $color) === 1
            || preg_match('/^hsla\([^)]*,\s*0(?:\.0+)?\s*\)$/iD', $color) === 1;
    }

    private function opaqueColor(string $color): string
    {
        if (preg_match('/^#([a-f0-9])([a-f0-9])([a-f0-9])(?:[a-f0-9])?$/iD', $color, $matches) === 1) {
            return '#'.$matches[1].$matches[1].$matches[2].$matches[2].$matches[3].$matches[3];
        }

        return substr($color, 0, 7);
    }

    /** @param array<string, mixed> $blueprint */
    private function validateBuilderImages(CommunicationTemplate $family, array $blueprint, bool $allowIncomplete): void
    {
        $components = $blueprint['components'] ?? [];
        if (! is_array($components)) {
            return;
        }
        $imageCount = 0;
        foreach ($components as $component) {
            if (! is_array($component) || ($component['type'] ?? '') !== 'image') {
                continue;
            }
            $imageCount++;
            abort_if($imageCount > 20, 422, 'notice_document_image_limit_exceeded');
            $props = $component['props'] ?? [];
            $assetId = $props['asset_id'] ?? null;
            $src = (string) ($props['src'] ?? '');
            abort_if(preg_match('#^https?://#i', $src) === 1, 422, 'notice_document_image_network_forbidden');
            abort_if(preg_match('#^data:image/webp#i', $src) === 1, 422, 'notice_document_image_webp_forbidden');
            if (is_string($assetId) && Str::isUuid($assetId)) {
                $doc = $this->sources->verified($family, $assetId);
                abort_unless(in_array($doc->mime_type, ['image/png', 'image/jpeg'], true), 422, 'notice_document_image_type_invalid');

                continue;
            }
            if ($src === '') {
                abort_if(! $allowIncomplete, 422, 'notice_document_image_empty');

                continue;
            }
            abort_unless(strlen($src) <= 2796226
                && preg_match('#^data:image/(png|jpeg);base64,#i', $src, $mime) === 1, 422, 'notice_document_image_format_invalid');
            $raw = base64_decode(substr($src, strpos($src, ',') + 1), true);
            abort_unless(is_string($raw) && strlen($raw) <= 2097152, 422, 'notice_document_image_size_exceeded');
            $info = @getimagesizefromstring($raw);
            abort_unless(is_array($info) && ($info['mime'] ?? null) === 'image/'.strtolower($mime[1])
                && (new \finfo(FILEINFO_MIME_TYPE))->buffer($raw) === $info['mime'], 422, 'notice_document_image_signature_invalid');
            $width = $info[0] ?? 0;
            $height = $info[1] ?? 0;
            abort_unless($width > 0 && $height > 0 && ($width * $height) <= 20000000, 422, 'notice_document_image_dimensions_exceeded');
        }
    }

    /** @param array<string, mixed> $blueprint @return array<string, mixed> */
    private function resolveBuilderImages(CommunicationTemplate $family, array $blueprint): array
    {
        $components = $blueprint['components'] ?? [];
        if (! is_array($components)) {
            return $blueprint;
        }
        $resolved = $components;
        foreach ($resolved as $i => $component) {
            if (! is_array($component) || ($component['type'] ?? '') !== 'image') {
                continue;
            }
            $props = $component['props'] ?? [];
            $assetId = $props['asset_id'] ?? null;
            if (is_string($assetId) && Str::isUuid($assetId)) {
                $doc = $this->sources->verified($family, $assetId);
                $bytes = Storage::disk('local')->get($doc->storage_path);
                if (is_string($bytes)) {
                    $props['src'] = 'data:'.$doc->mime_type.';base64,'.base64_encode($bytes);
                    $resolved[$i]['props'] = $props;
                }
            }
        }
        $blueprint['components'] = $resolved;

        return $blueprint;
    }

    /** @param array<string, mixed> $state */
    private function compile(CommunicationTemplate $family, array $state): string
    {
        $replacements = $this->syntheticContext($family, $state['source_kind'] === 'docx');
        $officialRows = NoticePrintContext::isOfficialScorecard($family);
        try {
            if ($state['source_kind'] === 'docx') {
                $source = $this->sources->verified($family, (string) $state['document_asset_id'], 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                $path = Storage::disk('local')->path($source->storage_path);

                return $this->compiler->compileNoticePdf($path, $replacements, 'notice.pdf', $source->checksum);
            }
            $blueprint = $this->resolveBuilderImages($family, $this->canonicalBlueprint($family, $state['builder_json']));

            return $this->compiler->compileBuilderPdf($blueprint, $state['paper_size'], $state['orientation'], 'notice.pdf', null, $replacements, $officialRows);
        } catch (\RuntimeException $exception) {
            abort_if($exception->getMessage() === 'libreoffice_unavailable', 503, 'notice_document_converter_unavailable');
            throw $exception;
        }
    }

    private function pdfForState(CommunicationTemplate $family, array $state): string
    {
        return $this->compile($family, $state);
    }

    private function verifiedProof(string $encrypted, CommunicationTemplate $family, NoticeDocumentDraft $draft, User $actor, string $render): array
    {
        $proof = $this->proofData($encrypted);
        abort_unless(
            ($proof['family_id'] ?? null) === $family->id
            && (int) ($proof['institution_id'] ?? 0) === (int) $family->institution_id
            && (int) ($proof['draft_id'] ?? 0) === (int) $draft->id
            && (string) ($proof['actor_id'] ?? '') === (string) $actor->id
            && hash_equals((string) ($proof['render_sha256'] ?? ''), $render)
            && is_string($proof['pdf_sha256'] ?? null)
            && preg_match('/\A[a-f0-9]{64}\z/D', $proof['pdf_sha256']) === 1
            && (int) ($proof['document_revision'] ?? 0) === (int) $family->document_revision,
            422,
            'notice_document_proof_stale'
        );

        return $proof;
    }

    /** @return array<string, mixed> */
    private function proofData(string $receipt): array
    {
        try {
            return json_decode(Crypt::decryptString($receipt), true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            abort(422, 'notice_document_proof_invalid');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  callable(CommunicationTemplate, ?NoticeDocumentDraft):array<string,mixed>  $callback
     * @return array<string, mixed>
     */
    private function mutate(CommunicationTemplate $family, array $data, User $actor, string $operation, ?int $draftId, callable $callback, int $status): array
    {
        return DB::transaction(function () use ($family, $data, $actor, $operation, $draftId, $callback, $status): array {
            $locked = CommunicationTemplate::query()->whereKey($family->id)->lockForUpdate()->firstOrFail();
            $this->authorize($locked, $actor);

            $payload = $data;
            unset($payload['idempotency_key']);
            if (isset($payload['document']) && $payload['document'] instanceof UploadedFile) {
                $payload['document_sha256'] = hash_file('sha256', $payload['document']->getRealPath());
                unset($payload['document']);
            }
            if (isset($payload['image']) && $payload['image'] instanceof UploadedFile) {
                $payload['image_sha256'] = hash_file('sha256', $payload['image']->getRealPath());
                unset($payload['image']);
            }
            foreach (['base_version', 'document_revision', 'lock_version', 'stage', 'document_draft_id', 'draft_id'] as $num) {
                if (isset($payload[$num]) && is_numeric($payload[$num])) {
                    $payload[$num] = (int) $payload[$num];
                }
            }

            $hash = CommunicationTemplatePayload::hash([
                'actor_id' => $actor->id,
                'family_id' => $locked->id,
                'operation' => $operation,
                'draft_id' => $draftId,
                'institution_id' => (int) $locked->institution_id,
                'payload' => $payload,
            ]);

            $receipt = DB::table('notice_document_receipts')
                ->where('institution_id', $locked->institution_id)
                ->where('domain', $locked->domain)
                ->where('canonical_slug', $locked->slug)
                ->where('operation', $operation)
                ->where('idempotency_key', $data['idempotency_key'])
                ->lockForUpdate()
                ->first();

            if ($receipt !== null) {
                abort_unless(hash_equals($receipt->payload_sha256, $hash), 409, 'idempotency_payload_conflict');

                return [...json_decode($receipt->response_payload, true, 32, JSON_THROW_ON_ERROR), 'replayed' => true];
            }

            $draft = NoticeDocumentDraft::query()->where('communication_template_id', $locked->id)->lockForUpdate()->first();
            $this->guards($locked, $draft, $data, $draftId);

            $result = $callback($locked, $draft);
            $operationId = (string) Str::uuid();
            $result = [
                ...$result,
                'operation_id' => $operationId,
                'replayed' => false,
                '_status' => $status,
            ];

            $this->audit->handle('notice_document.'.$operation, [
                'family_id' => $locked->id,
                'institution_id' => $locked->institution_id,
                'document_revision' => $locked->document_revision,
                'operation_id' => $operationId,
                'render_sha256' => $result['render_sha256'] ?? null,
                'source_asset_id' => $result['source']['asset_id'] ?? $result['asset']['id'] ?? null,
            ], (string) $actor->id);

            DB::table('notice_document_receipts')->insert([
                'institution_id' => $locked->institution_id,
                'domain' => $locked->domain,
                'canonical_slug' => $locked->slug,
                'operation' => $operation,
                'idempotency_key' => $data['idempotency_key'],
                'payload_sha256' => $hash,
                'response_payload' => json_encode($result, JSON_THROW_ON_ERROR),
                'created_by_user_id' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $result;
        });
    }

    private function guards(CommunicationTemplate $family, ?NoticeDocumentDraft $draft, array $data, ?int $draftId): void
    {
        if (
            (int) $data['base_version'] !== (int) ($family->documentVersions()->max('version') ?? 0)
            || (int) $data['document_revision'] !== (int) $family->document_revision
            || (($draft === null) !== ($data['lock_version'] === null))
            || ($draft !== null && (int) $data['lock_version'] !== $draft->lock_version)
            || ($draftId !== null && (int) ($draft?->id ?? 0) !== $draftId)
            || ($draft !== null && (int) $draft->base_version !== (int) $data['base_version'])
            || ($draft !== null && (int) $draft->document_revision !== (int) $family->document_revision)
        ) {
            throw new HttpResponseException(response()->json([
                'error' => 'document_conflict',
                'document_revision' => $family->document_revision,
                'current' => [
                    'base_version' => (int) ($family->documentVersions()->max('version') ?? 0),
                    'document_revision' => $family->document_revision,
                    'draft_id' => $draft?->id,
                    'lock_version' => $draft?->lock_version,
                ],
            ], 409));
        }
    }

    private function authorize(CommunicationTemplate $family, User $actor): void
    {
        $actor = User::query()->findOrFail($actor->id);
        $current = CommunicationTemplate::query()->findOrFail($family->id);
        abort_unless(
            (string) $current->id === (string) $family->id
            && (int) $current->institution_id === (int) $family->institution_id
            && $current->domain === $family->domain
            && $current->slug === $family->slug
            && $current->event_key === $family->event_key,
            404
        );
        abort_unless(
            CommunicationTemplateRegistry::supportsPdf($family->domain, $family->slug, $family->event_key)
            || in_array([$family->domain, $family->event_key], [
                ['admissions', 'admission.accepted.v1'],
                ['examination', 'exam.session_published.v1'],
                ['results', 'result.released.online.v1'],
            ], true),
            404
        );
        abort_if(CommunicationTemplateRegistry::authoringMode($family->domain, $family->slug) === 'builtin', 403);
        $institution = $this->scope->authorizeFullSource($actor, $family->domain);
        abort_unless((int) $institution->id === (int) $family->institution_id, 404);
    }

    /** @return array<string, mixed> */
    private function draftDto(NoticeDocumentDraft $draft, string $render): array
    {
        return [
            'id' => (int) $draft->id,
            'stage' => $draft->stage,
            'state' => $draft->state,
            'lock_version' => $draft->lock_version,
            'base_version' => $draft->base_version,
            'render_sha256' => $render,
        ];
    }

    /** @return array<string, mixed>|null */
    private function latestDto(CommunicationTemplate $family): ?array
    {
        $version = $family->documentVersions()->orderByDesc('version')->first();

        return $version === null ? null : $this->versionDto($version);
    }

    /** @return array<string, mixed> */
    private function versionDto(NoticeDocumentVersion $version): array
    {
        return [
            'id' => (int) $version->id,
            'version' => $version->version,
            'definition_sha256' => $version->definition_sha256,
            'render_sha256' => $version->render_sha256,
        ];
    }
}
