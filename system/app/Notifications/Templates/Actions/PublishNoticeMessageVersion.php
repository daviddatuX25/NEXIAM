<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Actions;

use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\Notifications\CommunicationTemplateVersion;
use App\Models\Notifications\NoticeDocumentVersion;
use App\Models\User;
use App\Notifications\Templates\CommunicationTemplateMutation;
use App\Notifications\Templates\CommunicationTemplateRegistry;
use App\Notifications\Templates\NoticeMessageOutputReceipt;
use App\Notifications\Templates\NoticeMessageState;
use Illuminate\Support\Facades\Validator;

final class PublishNoticeMessageVersion
{
    public function __construct(private readonly CommunicationTemplateMutation $mutation, private readonly NoticeMessageState $state,
        private readonly NoticeMessageOutputReceipt $receipts) {}

    public function publish(CommunicationTemplate $template, array $input, User $actor): array
    {
        Validator::make(['input' => $input], ['input' => ['required', 'array:base_version,family_revision,lock_version,draft_id,idempotency_key,content_sha256,preview_receipts,document_revision_id'],
            'input.content_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D'],
            'input.document_revision_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'input.preview_receipts' => ['required', 'array:email,portal,attachment'],
            'input.preview_receipts.email' => ['required', 'string', 'max:16384'],
            'input.preview_receipts.portal' => ['required', 'string', 'max:16384'],
            'input.preview_receipts.attachment' => ['sometimes', 'string', 'max:16384']])->validate();

        return $this->mutation->run($template, $input, $actor, 'message_publish', 200,
            function (CommunicationTemplate $family, ?CommunicationTemplateDraft $draft) use ($input, $actor): array {
                abort_unless($draft?->content_schema_version === 'notice-content-3', 422, 'A saved message draft is required.');
                if (array_key_exists('document_revision_id', $input)) {
                    abort_unless(($input['document_revision_id'] === null ? null : (int) $input['document_revision_id'])
                        === ($draft->document_revision_id === null ? null : (int) $draft->document_revision_id), 422, 'The selected document version changed.');
                }
                $message = $this->state->validate($family, $this->state->persisted($draft));
                abort_unless(is_array($message['email_blueprint']) && ($message['email_blueprint']['blocks'] ?? []) !== [], 422, 'A semantic email is required before publication.');
                $rendererVersion = $this->state->rendererVersion($draft);
                $contentDigest = $this->state->contentDigest($family, $message, $rendererVersion);
                abort_unless(hash_equals($contentDigest, $input['content_sha256']), 422, 'The saved message fingerprint changed.');
                foreach (['email', 'portal'] as $view) {
                    $this->receipts->verify($family, $draft, $actor, $view, $input['preview_receipts'][$view]);
                }
                $supportsPdf = CommunicationTemplateRegistry::supportsPdf(
                    $family->domain, $family->slug, $family->event_key,
                );
                $documentVersion = null;
                if ($supportsPdf) {
                    $receipt = $input['preview_receipts']['attachment'] ?? null;
                    abort_unless(is_string($receipt), 422, 'Check the selected attachment or no-attachment choice before publication.');
                    if ($draft->document_revision_id !== null) {
                        $documentVersion = NoticeDocumentVersion::query()
                            ->whereKey($draft->document_revision_id)
                            ->where('communication_template_id', $family->id)->firstOrFail();
                    }
                    $this->receipts->verify($family, $draft, $actor, 'attachment', $receipt);
                } else {
                    abort_if(isset($input['preview_receipts']['attachment']), 422, 'This message has no attachment output.');
                }
                $next = (int) $family->versions()->max('version') + 1;
                $version = CommunicationTemplateVersion::query()->create([...$message, 'communication_template_id' => $family->id,
                    'version' => $next, 'content_schema_version' => 'notice-content-3', 'email_renderer_version' => $rendererVersion,
                    'document_revision_id' => $documentVersion?->id,
                    'orientation' => null, 'document_source_drafts' => null, 'import_provenance' => null,
                    'content_sha256' => $contentDigest, 'created_by_user_id' => $actor->id,
                    'email_subject' => '', 'email_body' => '', 'in_app_title' => '', 'in_app_body' => '']);
                $family->update(['active_version' => $next]);
                $draft->delete();

                return ['version' => ['id' => $version->id, 'version' => $next, 'content_sha256' => $contentDigest,
                    'document_revision_id' => $version->document_revision_id],
                    'draft' => null, 'return_url' => route('setup.notifications.'.$family->domain.'.templates.studio', ['slug' => $family->slug])];
            });
    }
}
