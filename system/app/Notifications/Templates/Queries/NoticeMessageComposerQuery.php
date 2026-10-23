<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Queries;

use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\NoticeDocumentVersion;
use App\Models\User;
use App\Notifications\Support\CommunicationTemplateScope;
use App\Notifications\Templates\Actions\RenderCommunicationTemplatePreview;
use App\Notifications\Templates\Actions\StoreCommunicationTemplateDocument;
use App\Notifications\Templates\CommunicationTemplateRegistry;
use App\Notifications\Templates\Email\NoticeEmailAssetVerifier;
use App\Notifications\Templates\NoticeDocumentPin;
use App\Notifications\Templates\NoticeHistoricalMessageProjection;
use App\Notifications\Templates\NoticeMessageState;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class NoticeMessageComposerQuery
{
    public static function supports(string $domain, string $slug): bool
    {
        $slot = CommunicationTemplateRegistry::findBySlug($domain, $slug);

        return $slot !== null && CommunicationTemplateRegistry::authoringMode($domain, $slug) !== 'builtin';
    }

    public function __construct(
        private readonly CommunicationTemplateScope $scope,
        private readonly NoticeMessageState $state,
        private readonly StoreCommunicationTemplateDocument $documents,
        private readonly NoticeHistoricalMessageProjection $historicalProjection,
        private readonly NoticeDocumentPin $documentPins,
        private readonly NoticeTemplateNavigationQuery $navigationQuery,
    ) {}

    public function editor(
        User $actor,
        string $domain,
        string $slug,
        mixed $version = null,
        mixed $sourceVersion = null,
        ?array $documentCandidate = null,
    ): array {
        abort_unless(self::supports($domain, $slug), 404);
        $institution = $this->scope->authorizeFullSource($actor, $domain);
        $family = CommunicationTemplate::query()->where('institution_id', $institution->id)->where('domain', $domain)->where('slug', $slug)->firstOrFail();
        $versionNumber = $this->versionNumber($version);
        $sourceNumber = $this->versionNumber($sourceVersion);
        abort_if($versionNumber !== null && $sourceNumber !== null, 422, 'Choose either inspection or draft copy.');
        $pendingDocumentCandidate = null;
        if ($documentCandidate !== null) {
            abort_unless($version === null && $sourceVersion === null, 404);
            abort_unless(CommunicationTemplateRegistry::supportsPdf($domain, $slug, (string) $family->event_key), 404);
            $candidateId = $this->candidateNumber($documentCandidate['id'] ?? null);
            $candidateVersion = $this->candidateNumber($documentCandidate['version'] ?? null);
            $candidate = $family->documentVersions()->whereKey($candidateId)
                ->where('version', $candidateVersion)->first();
            abort_unless($candidate instanceof NoticeDocumentVersion, 404);
            try {
                $this->documentPins->selected($family, $candidateId, verifyBytes: false);
            } catch (HttpException) {
                abort(404);
            }
            $pendingDocumentCandidate = $this->documentVersionProjection($domain, $slug, $candidate);
        }
        abort_if($sourceNumber !== null && $family->draft()->exists(), 409,
            'Resume or discard the sole current draft before copying a historical message version.');
        $draft = $versionNumber === null ? $family->draft()->first() : null;
        $selected = $versionNumber ?? $sourceNumber;
        $sourceVersion = $selected === null && $draft !== null
            ? $draft
            : $family->versions()->where('version', $selected ?? $family->active_version)->firstOrFail();
        $readOnly = $versionNumber !== null;
        $conversionReview = null;
        if ($sourceVersion->content_schema_version === 'notice-content-3') {
            $internalState = $this->state->persisted($sourceVersion);
        } else {
            $historical = $this->historicalProjection->project($sourceVersion, $family);
            $internalState = $historical['state'];
            $conversionReview = $historical['conversion_review'];
        }
        $supportsPdf = CommunicationTemplateRegistry::supportsPdf($domain, $slug, (string) $family->event_key);
        $familyMetadata = $this->scope->projectMetadata($family);
        $slot = CommunicationTemplateRegistry::findBySlug($domain, $slug);
        abort_unless($slot !== null, 404);
        $selectedDocumentVersionId = $supportsPdf
            ? ($sourceVersion->document_revision_id ?? null)
            : null;
        $state = [...$internalState, 'document_revision_id' => $selectedDocumentVersionId === null ? null : (int) $selectedDocumentVersionId];
        $rendererVersion = $sourceVersion->content_schema_version === 'notice-content-3'
            ? $this->state->rendererVersion($sourceVersion)
            : NoticeMessageState::NEW_EMAIL_RENDERER;
        $assets = [];
        foreach (NoticeEmailAssetVerifier::assetIds($state['email_blueprint'] ?? []) as $assetId) {
            $asset = $this->documents->verified($family, $assetId);
            $image = getimagesizefromstring(Storage::disk('local')->get($asset->storage_path));
            abort_unless(is_array($image), 404, 'The private image is unavailable.');
            $assets[] = ['asset_id' => $asset->id, 'sha256' => $asset->checksum, 'mime' => $asset->mime_type,
                'width' => $image[0], 'height' => $image[1], 'preview_url' => $this->imageUrl($domain, $slug, $asset->id)];
        }
        $catalog = CommunicationTemplateRegistry::findBySlug($domain, $slug)['tokens'] ?? [];
        $endpoints = $this->endpoints($domain, $slug);

        return ['family' => [...$familyMetadata, 'event_key' => (string) $family->event_key,
            'default_action_route' => $slot['default_action_route'], 'default_action_label' => $slot['default_action_label']],
            'state' => $state, 'draft' => ! $readOnly && $draft !== null
                ? ['id' => $draft->id, 'lock_version' => $draft->lock_version, 'state' => $state,
                    'updated_at' => $draft->updated_at?->toIso8601String(),
                    'endpoints' => ['update' => route('setup.notifications.templates.drafts.update',
                        ['domain' => $domain, 'slug' => $slug, 'draft' => $draft->id]),
                        'discard' => route('setup.notifications.templates.drafts.destroy',
                            ['domain' => $domain, 'slug' => $slug, 'draft' => $draft->id])]] : null,
            'read_only' => $readOnly, 'base_version' => $family->active_version, 'content_sha256' => $this->state->contentDigest(
                $family, $internalState, $rendererVersion),
            'output_digests' => ['email_sha256' => $this->state->emailDigest($family, $internalState, $rendererVersion),
                'portal_sha256' => $this->state->portalDigest($internalState, $rendererVersion),
                'binding_sha256' => $this->state->bindingDigest($family, $internalState)],
            'tokens' => collect($catalog)->map(fn (array $token): array => [
                'token' => preg_replace('/^\{\{\s*|\s*\}\}$/', '', (string) $token['token']),
                'label' => $token['label'],
            ])->all(),
            'document_versions' => $supportsPdf
                ? $family->documentVersions()->orderByDesc('version')->get()
                    ->map(fn (NoticeDocumentVersion $version): array => $this->documentVersionProjection($domain, $slug, $version))
                    ->all() : [],
            'pending_document_candidate' => $pendingDocumentCandidate,
            'versions' => $family->versions()->orderByDesc('version')->get(['version', 'created_at'])->map(fn ($version): array => [
                'version' => (int) $version->version,
                'created_at' => $version->created_at?->toIso8601String(),
                'inspect_url' => route('setup.notifications.templates.edit', ['domain' => $domain, 'slug' => $slug, 'version' => $version->version]),
                'copy_state_url' => route('setup.notifications.templates.edit', ['domain' => $domain, 'slug' => $slug, 'source_version' => $version->version]),
            ])->all(),
            'copy_source_version' => $sourceNumber,
            'assets' => $assets, 'sample_context' => RenderCommunicationTemplatePreview::syntheticContext($family),
            'content_schema_version' => (string) $sourceVersion->content_schema_version,
            'email_renderer_version' => (string) $sourceVersion->email_renderer_version,
            'conversion_review' => $conversionReview,
            'navigation' => $this->navigationQuery->forComposer($actor, $domain, $slug),
            'endpoints' => $endpoints];
    }

    private function versionNumber(mixed $version): ?int
    {
        if ($version === null) {
            return null;
        }
        abort_unless((is_int($version) || is_string($version)) && preg_match('/^[1-9][0-9]*$/D', (string) $version)
            && filter_var($version, FILTER_VALIDATE_INT) !== false, 404);

        return (int) $version;
    }

    private function candidateNumber(mixed $value): int
    {
        abort_unless((is_int($value) || is_string($value))
            && preg_match('/^[1-9][0-9]*$/D', (string) $value)
            && filter_var($value, FILTER_VALIDATE_INT) !== false, 422, 'A complete ready document candidate is required.');

        return (int) $value;
    }

    /**
     * @return array{id: int, version: int, definition_sha256: string, render_sha256: string, pdf_url: string, endpoints: array{pdf: string, thumbnail: string}}
     */
    private function documentVersionProjection(string $domain, string $slug, NoticeDocumentVersion $version): array
    {
        $pdfUrl = route('setup.notifications.templates.document.versions.pdf', [
            'domain' => $domain, 'slug' => $slug, 'version' => $version->id,
        ]);

        return [
            'id' => (int) $version->id,
            'version' => (int) $version->version,
            'definition_sha256' => $version->definition_sha256,
            'render_sha256' => $version->render_sha256,
            'pdf_url' => $pdfUrl,
            'endpoints' => [
                'pdf' => $pdfUrl,
                'thumbnail' => route('setup.notifications.templates.document.versions.thumbnail', [
                    'domain' => $domain, 'slug' => $slug, 'version' => $version->id,
                ]),
            ],
        ];
    }

    private function endpoints(string $domain, string $slug): array
    {
        $family = route('setup.notifications.'.$domain.'.templates.studio', ['slug' => $slug]);

        return ['load' => route('setup.notifications.templates.editor-state', compact('domain', 'slug')),
            'create' => $family.'/drafts', 'update' => $family.'/drafts/{draft}', 'discard' => $family.'/drafts/{draft}',
            'preview' => $family.'/preview', 'publish' => $family.'/publish', 'test_mail' => $family.'/test-mail',
            'image_upload' => $family.'/images', 'return_url' => $family,
            ...(CommunicationTemplateRegistry::authoringMode($domain, $slug) === 'email_pdf'
                ? ['attachment_check' => $family.'/attachment-check', 'document_edit' => $family.'/document/edit'] : [])];
    }

    private function imageUrl(string $domain, string $slug, string $asset): string
    {
        return route('setup.notifications.'.$domain.'.templates.studio', ['slug' => $slug]).'/images/'.$asset.'/content';
    }
}
