<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Actions;

use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\Notifications\CommunicationTemplateVersion;
use App\Models\User;
use App\Notifications\Templates\CommunicationTemplateMutation;
use App\Notifications\Templates\CommunicationTemplatePreviewReceipt;
use App\Notifications\Templates\CommunicationTemplateState;
use Illuminate\Support\Facades\Validator;

class PublishCommunicationTemplateVersion
{
    public function __construct(private readonly CommunicationTemplateMutation $mutation, private readonly CommunicationTemplateState $state,
        private readonly CommunicationTemplatePreviewReceipt $previews) {}

    public function publish(CommunicationTemplate $template, array $data, User $actor): array
    {
        return $this->mutation->run($template, $data, $actor, 'publish', 200,
            function (CommunicationTemplate $locked, ?CommunicationTemplateDraft $draft) use ($data, $actor): array {
                abort_unless($draft !== null, 422, 'A saved draft is required.');
                Validator::make(['data' => $data], ['data' => ['required',
                    'array:base_version,family_revision,lock_version,idempotency_key,content_sha256,preview_receipts,draft_id']])->validate();
                Validator::make($data, ['content_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D'],
                    'preview_receipts' => ['required', 'array:email,portal,print']])->validate();
                abort_unless(hash_equals((string) $draft->content_sha256, $data['content_sha256']), 422, 'The saved content fingerprint changed.');
                $this->state->assertPublishable($locked, $draft, $actor);
                $views = ['email', 'portal'];
                if ($draft->document_mode !== 'none') {
                    $views[] = 'print';
                }
                foreach ($views as $view) {
                    abort_unless(is_string($data['preview_receipts'][$view] ?? null), 422, 'A current '.$view.' Check is required.');
                    $this->previews->verify($locked, $draft, $actor, $view, $data['preview_receipts'][$view]);
                }
                $fields = CommunicationTemplateState::state($draft);
                unset($fields['document_source_drafts']);
                $fields['import_provenance'] = CommunicationTemplateState::publishedProvenance($fields['import_provenance']);
                $next = (int) $locked->versions()->max('version') + 1;
                $version = CommunicationTemplateVersion::query()->create([
                    ...$fields, 'communication_template_id' => $locked->id, 'version' => $next,
                    'content_schema_version' => 'notice-content-2', 'email_renderer_version' => $draft->email_renderer_version,
                    'content_sha256' => $draft->content_sha256, 'created_by_user_id' => $actor->id,
                    'email_subject' => $draft->subject, 'email_body' => '', 'in_app_title' => $draft->subject, 'in_app_body' => $draft->body,
                ]);
                $locked->update(['active_version' => $next]);
                $draft->delete();

                return ['version' => ['id' => $version->id, 'version' => $version->version, 'content_sha256' => $version->content_sha256],
                    'draft' => null, 'return_url' => route('setup.notifications.'.$locked->domain.'.templates.studio', ['slug' => $locked->slug])];
            });
    }
}
