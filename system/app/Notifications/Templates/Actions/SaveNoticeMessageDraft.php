<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Actions;

use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\User;
use App\Notifications\Templates\CommunicationTemplateMutation;
use App\Notifications\Templates\NoticeMessageState;
use Illuminate\Support\Facades\Validator;

final class SaveNoticeMessageDraft
{
    public function __construct(
        private readonly CommunicationTemplateMutation $mutation,
        private readonly NoticeMessageState $state,
        private readonly NoticeDocumentWorkflow $documents,
    ) {}

    public function save(CommunicationTemplate $template, array $input, User $actor): array
    {
        Validator::make(['input' => $input], [
            'input' => ['required', 'array:state,base_version,family_revision,lock_version,idempotency_key,draft_id,source_version'],
            'input.state' => ['required', 'array'],
            'input.source_version' => ['sometimes', 'integer', 'min:1'],
        ])->validate();

        return $this->mutation->run($template, $input, $actor, 'message_save_draft', ($input['lock_version'] ?? null) === null ? 201 : 200,
            function (CommunicationTemplate $family, ?CommunicationTemplateDraft $draft) use ($input, $actor): array {
                $messageInput = $input['state'];
                $state = $this->state->validate($family, $messageInput);
                $this->guardHistoricalConversion($family, $draft, $input, $state);
                if ($draft !== null) {
                    $this->documents->preserveLegacyDraft($family, $draft, $actor);
                }
                $rendererVersion = NoticeMessageState::NEW_EMAIL_RENDERER;
                $attributes = [...$state, 'communication_template_id' => $family->id, 'base_version' => $family->active_version,
                    'lock_version' => ($draft?->lock_version ?? 0) + 1, 'stage' => 3, 'content_schema_version' => 'notice-content-3',
                    'email_renderer_version' => $rendererVersion, 'document_mode' => 'none', 'document_asset_id' => null,
                    'builder_json' => null, 'paper_size' => null, 'orientation' => null, 'document_source_drafts' => null,
                    'import_provenance' => null, 'content_sha256' => $this->state->contentDigest($family, $state, $rendererVersion),
                    'updated_by_user_id' => $actor->id, 'email_subject' => '', 'email_body' => '',
                    'in_app_title' => '', 'in_app_body' => ''];
                if ($draft === null) {
                    $draft = CommunicationTemplateDraft::query()->create($attributes);
                } else {
                    $draft->update($attributes);
                }
                $draft->setRelation('template', $family);

                $familyUrl = route('setup.notifications.'.$family->domain.'.templates.studio', ['slug' => $family->slug]);

                return ['draft' => ['id' => $draft->id, 'lock_version' => $draft->lock_version, 'state' => $state,
                    'updated_at' => $draft->updated_at?->toIso8601String(), 'endpoints' => ['load' => route('setup.notifications.templates.editor-state',
                        ['domain' => $family->domain, 'slug' => $family->slug]), 'update' => $familyUrl.'/drafts/'.$draft->id,
                        'discard' => $familyUrl.'/drafts/'.$draft->id]], 'content_schema_version' => 'notice-content-3',
                    'email_renderer_version' => $rendererVersion, 'content_sha256' => $draft->content_sha256,
                    'output_digests' => ['email_sha256' => $this->state->emailDigest($family, $state, $rendererVersion),
                        'portal_sha256' => $this->state->portalDigest($state, $rendererVersion),
                        'binding_sha256' => $this->state->bindingDigest($family, $state)]];
            });
    }

    private function guardHistoricalConversion(
        CommunicationTemplate $family,
        ?CommunicationTemplateDraft $draft,
        array $input,
        array $state,
    ): void {
        if ($draft !== null) {
            abort_unless(! isset($input['source_version']), 409, 'Resume or discard the current message draft before copying a historical version.');
            $source = $draft;
        } elseif (isset($input['source_version'])) {
            $source = $family->versions()->where('version', (int) $input['source_version'])->firstOrFail();
        } else {
            $source = $family->versions()->where('version', $family->active_version)->firstOrFail();
        }

        $this->state->assertHistoricalConversionReady($source, $family);
    }
}
