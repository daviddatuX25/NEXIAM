<?php

declare(strict_types=1);

namespace App\Notifications\Templates;

use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Crypt;

final class NoticeMessageOutputReceipt
{
    public function __construct(private readonly NoticeMessageState $state) {}

    public function issue(CommunicationTemplate $family, CommunicationTemplateDraft $draft, User $actor, string $view): string
    {
        $state = $this->state->persisted($draft);
        $rendererVersion = $this->state->rendererVersion($draft);
        $digest = $this->digest($family, $state, $view, $rendererVersion);

        return Crypt::encryptString(CommunicationTemplatePayload::json($this->binding($family, $draft, $actor, $view, $digest, $state)));
    }

    public function verify(CommunicationTemplate $family, CommunicationTemplateDraft $draft, User $actor, string $view, string $receipt, ?string $expectedDigest = null): void
    {
        try {
            $payload = json_decode(Crypt::decryptString($receipt), true, 16, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $this->stale();
        }
        $state = $this->state->persisted($draft);
        $rendererVersion = $this->state->rendererVersion($draft);
        $digest = $this->digest($family, $state, $view, $rendererVersion);
        if (! is_array($payload) || ! hash_equals($digest, (string) ($payload['output_sha256'] ?? ''))
            || ($expectedDigest !== null && ! hash_equals($digest, $expectedDigest))
            || CommunicationTemplatePayload::hash($payload) !== CommunicationTemplatePayload::hash($this->binding($family, $draft, $actor, $view, $digest, $state))) {
            $this->stale();
        }
    }

    private function digest(CommunicationTemplate $family, array $state, string $view, string $rendererVersion): string
    {
        return match ($view) {
            'email' => $this->state->emailDigest($family, $state, $rendererVersion),
            'portal' => $this->state->portalDigest($state, $rendererVersion),
            'attachment' => $this->state->bindingDigest($family, $state),
            default => throw new \InvalidArgumentException('Unknown message output.'),
        };
    }

    private function binding(CommunicationTemplate $family, CommunicationTemplateDraft $draft, User $actor, string $view, string $digest,
        array $state): array
    {
        if ($view === 'attachment') {
            return ['actor_id' => (string) $actor->id, 'institution_id' => (int) $family->institution_id,
                'family_id' => $family->id, 'view' => $view, 'document_revision_id' => $draft->document_revision_id,
                'output_sha256' => $digest, 'schema' => 'notice-attachment-binding-1'];
        }
        $binding = ['actor_id' => (string) $actor->id, 'institution_id' => (int) $family->institution_id, 'family_id' => (string) $family->id,
            'draft_id' => (string) $draft->id, 'base_version' => (int) $family->active_version, 'view' => $view,
            'output_sha256' => $digest, 'schema' => 'notice-content-3', 'renderer' => $this->state->rendererVersion($draft)];
        if ($this->state->rendererVersion($draft) === NoticeMessageState::NEW_EMAIL_RENDERER) {
            $binding['action'] = NoticeMessageState::action($state);
        }

        return $binding;
    }

    private function stale(): never
    {
        throw new HttpResponseException(response()->json(['error' => 'preview_stale', 'message' => 'Check this current message output again.'], 422));
    }
}
