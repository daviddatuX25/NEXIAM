<?php

declare(strict_types=1);

namespace App\Notifications\Templates;

use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\User;
use App\Notifications\Templates\Email\NoticeEmailAssetVerifier;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Crypt;

final class CommunicationTemplatePreviewReceipt
{
    public function __construct(private readonly CommunicationTemplateState $state, private readonly NoticeEmailAssetVerifier $assets) {}

    public function issue(CommunicationTemplate $template, CommunicationTemplateDraft $draft, User $actor, string $view, string $projectionSha256): string
    {
        return Crypt::encryptString(CommunicationTemplatePayload::json([
            ...$this->binding($template, $draft, $actor, $view), 'projection_sha256' => $projectionSha256,
        ]));
    }

    public function verify(CommunicationTemplate $template, CommunicationTemplateDraft $draft, User $actor, string $view, string $receipt): void
    {
        try {
            $payload = json_decode(Crypt::decryptString($receipt), true, 16, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException $exception) {
            $this->stale();
        }
        if (! is_array($payload) || ! preg_match('/^[a-f0-9]{64}$/D', (string) ($payload['projection_sha256'] ?? ''))) {
            $this->stale();
        }
        unset($payload['projection_sha256']);
        if (CommunicationTemplatePayload::hash($payload) !== CommunicationTemplatePayload::hash($this->binding($template, $draft, $actor, $view))) {
            $this->stale();
        }
    }

    private function binding(CommunicationTemplate $template, CommunicationTemplateDraft $draft, User $actor, string $view): array
    {
        $this->state->validate($template, CommunicationTemplateState::state($draft));
        try {
            $this->assets->verify((int) $template->institution_id, (string) $template->id, $draft->email_blueprint ?? []);
        } catch (\RuntimeException) {
            $this->stale();
        }

        return ['actor_id' => (string) $actor->id, 'institution_id' => (int) $template->institution_id,
            'family_id' => (string) $template->id, 'draft_id' => (string) $draft->id,
            'active_version' => $template->active_version, 'family_revision' => $template->family_revision,
            'lock_version' => $draft->lock_version, 'content_sha256' => $this->state->digest($template, $draft),
            'content_schema_version' => $draft->content_schema_version,
            'email_schema_version' => $draft->email_blueprint['schema_version'] ?? null,
            'email_renderer_version' => $draft->email_renderer_version, 'view' => $view];
    }

    private function stale(): never
    {
        throw new HttpResponseException(response()->json(['error' => 'preview_stale', 'message' => 'Check this current saved notice again.'], 422));
    }
}
