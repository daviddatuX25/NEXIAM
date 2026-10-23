<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Queries;

use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDocument;
use App\Models\Notifications\NoticeDocumentDraft;
use App\Models\User;
use App\Notifications\Support\CommunicationTemplateScope;
use App\Notifications\Templates\Actions\NoticeDocumentWorkflow;
use App\Notifications\Templates\Actions\StoreCommunicationTemplateDocument;
use App\Notifications\Templates\CommunicationTemplateRegistry;
use App\Notifications\Templates\NoticePrintContext;
use App\Notifications\Templates\Surfaces\NoticeTemplateSurface;

final class NoticeDocumentWorkspaceQuery
{
    public function __construct(
        private readonly CommunicationTemplateScope $scope,
        private readonly NoticeDocumentWorkflow $workflow,
    ) {}

    /** @return array<string, mixed> */
    public function editor(User $actor, string $domain, string $slug): array
    {
        $institution = $this->scope->authorizeFullSource($actor, $domain);
        $family = CommunicationTemplate::query()->where('institution_id', $institution->id)->where('domain', $domain)->where('slug', $slug)->firstOrFail();
        $slot = CommunicationTemplateRegistry::findBySlug($domain, $slug);
        abort_unless($slot !== null && CommunicationTemplateRegistry::supportsPdf($domain, $slug, $family->event_key), 404);
        $surface = new NoticeTemplateSurface($domain, $slug);
        $draft = NoticeDocumentDraft::query()->where('communication_template_id', $family->id)->first();
        $versions = $family->documentVersions()->orderByDesc('version')->get();
        $state = $draft?->state ?? ['source_kind' => 'builder', 'document_asset_id' => null,
            'builder_json' => $surface->defaultBlueprint(), 'paper_size' => 'a4', 'orientation' => 'portrait'];
        $render = $draft === null ? null : $this->workflow->renderDigest($family, $draft->state);
        $base = '/setup/notifications/'.$domain.'/templates/'.$slug;
        $composer = route('setup.notifications.templates.edit', ['domain' => $domain, 'slug' => $slug], false);
        $familySlug = ['domain' => $domain, 'slug' => $slug];
        $documentDraft = $draft === null ? null : ['id' => (int) $draft->id, 'stage' => $draft->stage,
            'state' => $draft->state, 'lock_version' => $draft->lock_version, 'base_version' => $draft->base_version,
            'render_sha256' => $render,
            'endpoints' => [
                'thumbnail' => $base.'/document/drafts/'.$draft->id.'/thumbnail',
            ]];
        $ready = $versions->map(fn ($version): array => ['id' => (int) $version->id, 'version' => $version->version,
            'render_sha256' => $version->render_sha256, 'definition_sha256' => $version->definition_sha256,
            'endpoints' => ['pdf' => $base.'/document/versions/'.$version->id.'/pdf',
                'thumbnail' => $base.'/document/versions/'.$version->id.'/thumbnail']])->values()->all();

        $verifiedImageAssets = CommunicationTemplateDocument::query()
            ->where('communication_template_id', $family->id)
            ->where('institution_id', $family->institution_id)
            ->whereIn('mime_type', ['image/png', 'image/jpeg'])
            ->get()
            ->map(function ($doc) use ($family, $familySlug): array {
                $verified = app(StoreCommunicationTemplateDocument::class)->verified($family, (string) $doc->id);

                return [
                    'id' => (string) $verified->id,
                    'sha256' => (string) $verified->checksum,
                    'mime' => (string) $verified->mime_type,
                    'filename' => (string) $verified->original_name,
                    'url' => route('setup.notifications.templates.document.images.show', [...$familySlug, 'asset' => $verified->id], false),
                ];
            })->values()->all();

        return ['family' => ['domain' => $family->domain, 'slug' => $family->slug, 'name' => $family->name, 'supports_pdf' => true],
            'workspace' => [
                'guards' => [
                    'base_version' => (int) ($versions->first()?->version ?? 0),
                    'document_revision' => (int) $family->document_revision,
                    'lock_version' => $draft?->lock_version,
                ],
                'Gd' => [
                    'base_version' => (int) ($versions->first()?->version ?? 0),
                    'document_revision' => (int) $family->document_revision,
                    'lock_version' => $draft?->lock_version,
                ],
                'draft' => $documentDraft, 'ready_versions' => $ready, 'latest_ready_version' => $ready[0] ?? null,
                'verified_images' => $verifiedImageAssets],
            'surface' => $surface->surfaceKey(), 'builderDefaults' => $surface->defaultBlueprint(),
            'builderCatalog' => $surface->componentCatalog(), 'builderCapabilities' => ['physical' => true],
            'placeholderGroups' => NoticePrintContext::placeholderGroups($family), 'sampleContext' => NoticePrintContext::sampleContext($family),
            'endpoints' => ['load' => $base.'/document/editor-state', 'create' => $base.'/document/drafts',
                'update' => $base.'/document/drafts/{draft}', 'discard' => $base.'/document/drafts/{draft}',
                'upload' => $base.'/document/upload', 'image_upload' => $base.'/document/images',
                'image_content' => $base.'/document/images/{asset}',
                'preview' => $base.'/document/preview', 'version' => $base.'/document/versions',
                'candidate_return_url' => $composer],
            'return_url' => $base];
    }

    public function family(User $actor, string $domain, string $slug): CommunicationTemplate
    {
        $institution = $this->scope->authorizeFullSource($actor, $domain);

        return CommunicationTemplate::query()->where('institution_id', $institution->id)->where('domain', $domain)->where('slug', $slug)->firstOrFail();
    }
}
