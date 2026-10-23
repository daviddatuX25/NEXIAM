<?php

declare(strict_types=1);

namespace App\Notifications\Templates;

use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\Notifications\CommunicationTemplateVersion;
use App\Notifications\Templates\Actions\StoreCommunicationTemplateDocument;
use App\Notifications\Templates\Email\NoticeEmailAssetVerifier;
use App\Notifications\Templates\Email\NoticeEmailSchema;
use Illuminate\Support\Facades\Route;

final class NoticeMessageState
{
    public const FIELDS = ['subject', 'body', 'action_label', 'action_route', 'email_blueprint', 'document_revision_id'];

    public const NEW_EMAIL_RENDERER = 'notice-message-email-2';

    public function __construct(
        private readonly StoreCommunicationTemplateDocument $documents,
        private readonly NoticeDocumentPin $pins,
        private readonly NoticeHistoricalMessageProjection $historical,
    ) {}

    public function validate(CommunicationTemplate $family, array $state): array
    {
        $state['document_revision_id'] ??= null;
        abort_unless(array_diff(array_keys($state), self::FIELDS) === [] && array_diff(self::FIELDS, array_keys($state)) === [], 422, 'Message state must contain only message fields.');
        validator($state, [
            'subject' => ['present', 'nullable', 'string', 'max:255'],
            'body' => ['present', 'nullable', 'string', 'max:500'],
            'action_label' => ['present', 'nullable', 'string', 'max:64'],
            'action_route' => ['present', 'nullable', 'string', 'max:128'],
            'email_blueprint' => ['present', 'nullable', 'array'],
            'document_revision_id' => ['present', 'nullable', 'integer', 'min:1'],
        ])->validate();
        $allowedTokens = CommunicationTemplateState::tokens($family);
        foreach (['subject', 'body'] as $field) {
            preg_match_all('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/', (string) ($state[$field] ?? ''), $matches);
            abort_unless(array_diff($matches[1], $allowedTokens) === [], 422, 'Message contains a token that is not allowed for this family.');
        }
        $canonicalRoute = self::canonicalActionRoute($family);
        if ($state['action_route'] === null) {
            abort_unless($state['action_label'] === null, 422, 'A disabled notice action cannot retain a label.');
        } else {
            abort_unless($canonicalRoute !== null && $state['action_route'] === $canonicalRoute
                && $state['action_label'] !== null && trim($state['action_label']) !== '', 422, 'Use the canonical action for this notice family.');
            $route = Route::getRoutes()->getByName($canonicalRoute);
            abort_unless($route !== null && in_array('GET', $route->methods(), true) && $route->parameterNames() === [],
                422, 'Use the canonical action for this notice family.');
        }
        if ($state['email_blueprint'] !== null) {
            NoticeEmailSchema::assertValid($state['email_blueprint'], CommunicationTemplateState::tokens($family));
            $this->assets($family, $state['email_blueprint']);
        }
        $state['document_revision_id'] = $state['document_revision_id'] === null ? null : (int) $state['document_revision_id'];
        $this->pins->selected($family, $state['document_revision_id']);

        return $state;
    }

    public function assertHistoricalConversionReady(
        CommunicationTemplateDraft|CommunicationTemplateVersion $source,
        CommunicationTemplate $family,
    ): void {
        if ($source->content_schema_version === 'notice-content-3') {
            return;
        }

        $review = $this->historical->project($source, $family)['conversion_review'];
        abort_unless($review === null || $review['status'] === 'ready', 422,
            'This historical message has unresolved conversion blockers and cannot be saved.');
    }

    public function emailDigest(CommunicationTemplate $family, array $state, string $rendererVersion = self::NEW_EMAIL_RENDERER): string
    {
        $assetMetadata = [];
        foreach (NoticeEmailAssetVerifier::assetIds($state['email_blueprint'] ?? []) as $id) {
            $asset = $this->documents->metadata($family, $id);
            $assetMetadata[$id] = ['sha256' => $asset->checksum, 'mime' => $asset->mime_type];
        }
        if ($rendererVersion === 'notice-email-1') {
            return CommunicationTemplatePayload::hash(['schema' => 'notice-content-3', 'email_schema' => 'notice-email-1',
                'renderer' => 'notice-email-1', 'blueprint' => $state['email_blueprint'], 'subject' => $state['subject'],
                'action_label' => $state['action_label'], 'action_route' => $state['action_route'], 'assets' => $assetMetadata,
                'context' => 'notice-synthetic-context-1']);
        }

        return CommunicationTemplatePayload::hash(['schema' => 'notice-content-3', 'email_schema' => 'notice-email-1',
            'renderer' => $rendererVersion, 'blueprint' => $state['email_blueprint'], 'subject' => $state['subject'],
            'action' => self::action($state), 'assets' => $assetMetadata, 'context' => 'notice-synthetic-context-1']);
    }

    public function portalDigest(array $state, string $rendererVersion = self::NEW_EMAIL_RENDERER): string
    {
        if ($rendererVersion === 'notice-email-1') {
            return CommunicationTemplatePayload::hash(['schema' => 'notice-content-3', 'renderer' => 'notice-email-1',
                'subject' => $state['subject'], 'body' => $state['body'], 'action_label' => $state['action_label'],
                'action_route' => $state['action_route'], 'context' => 'notice-synthetic-context-1']);
        }

        return CommunicationTemplatePayload::hash(['schema' => 'notice-content-3', 'renderer' => $rendererVersion,
            'subject' => $state['subject'], 'body' => $state['body'], 'action' => self::action($state),
            'context' => 'notice-synthetic-context-1']);

    }

    public function contentDigest(CommunicationTemplate $family, array $state, string $rendererVersion = self::NEW_EMAIL_RENDERER): string
    {
        return CommunicationTemplatePayload::hash(['content_schema_version' => 'notice-content-3', 'email_renderer_version' => $rendererVersion,
            'state' => $state, 'email_sha256' => $this->emailDigest($family, $state, $rendererVersion),
            'portal_sha256' => $this->portalDigest($state, $rendererVersion)]);
    }

    public static function canonicalActionRoute(CommunicationTemplate $family): ?string
    {
        $slot = CommunicationTemplateRegistry::findBySlug($family->domain, $family->slug);
        $route = $slot['default_action_route'] ?? null;

        return is_string($route) && $route !== '' ? $route : null;
    }

    /**
     * @return array{enabled:bool,label:?string,route:?string}
     */
    public static function action(array $state): array
    {
        $route = $state['action_route'] ?? null;

        return ['enabled' => $route !== null, 'label' => $state['action_label'] ?? null, 'route' => $route];
    }

    public function bindingDigest(CommunicationTemplate $family, array $state): string
    {
        return CommunicationTemplatePayload::hash($this->pins->binding($family, $state['document_revision_id'] ?? null));
    }

    public function persisted(CommunicationTemplateDraft|CommunicationTemplateVersion $source): array
    {
        $state = [];
        foreach (self::FIELDS as $field) {
            $state[$field] = $source->{$field};
        }

        return $state;
    }

    public function rendererVersion(CommunicationTemplateDraft|CommunicationTemplateVersion $source): string
    {
        return (string) $source->email_renderer_version;
    }

    private function assets(CommunicationTemplate $family, array $blueprint): void
    {
        foreach (NoticeEmailAssetVerifier::assetIds($blueprint) as $id) {
            abort_unless(in_array($this->documents->verified($family, $id)->mime_type, ['image/png', 'image/jpeg'], true), 422, 'Email requires an owned raster image.');
        }
    }
}
