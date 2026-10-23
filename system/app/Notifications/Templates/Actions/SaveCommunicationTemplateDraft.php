<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Actions;

use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\User;
use App\Notifications\Templates\CommunicationTemplateMutation;
use App\Notifications\Templates\CommunicationTemplatePayload;
use App\Notifications\Templates\CommunicationTemplateState;
use Illuminate\Support\Facades\Validator;

class SaveCommunicationTemplateDraft
{
    public function __construct(private readonly CommunicationTemplateMutation $mutation, private readonly CommunicationTemplateState $state) {}

    public function save(CommunicationTemplate $template, array $data, User $actor): array
    {
        return $this->mutation->run($template, $data, $actor, 'save_draft', ($data['lock_version'] ?? null) === null ? 201 : 200,
            function (CommunicationTemplate $locked, ?CommunicationTemplateDraft $draft) use ($data, $actor): array {
                Validator::make(['data' => $data], ['data' => ['required', 'array:state,stage,base_version,family_revision,lock_version,idempotency_key,draft_id,source_version']])->validate();
                Validator::make($data, ['state' => ['required', 'array'], 'stage' => ['required', 'integer', 'between:1,3'],
                    'source_version' => ['sometimes', 'integer', 'min:1']])->validate();
                $state = $data['state'];
                $provenance = null;
                $historical = null;
                if (isset($data['source_version'])) {
                    abort_unless($draft === null, 422, 'Begin from a version only when no draft exists.');
                    $version = $locked->versions()->where('version', $data['source_version'])->firstOrFail();
                    if ($version->content_schema_version !== 'notice-content-2' || $version->email_blueprint === null) {
                        $historical = $version;
                    }
                    $provenance = $version->import_provenance;
                    abort_unless(CommunicationTemplatePayload::hash($state['import_provenance'] ?? null)
                        === CommunicationTemplatePayload::hash($provenance),
                        422, 'The source version import provenance must match its immutable server-owned record.');
                    if ($provenance !== null && CommunicationTemplateState::emailDigest($state['email_blueprint'] ?? [])
                        !== CommunicationTemplateState::emailDigest($version->email_blueprint ?? [])) {
                        $provenance['review_acknowledgment'] = null;
                    }
                }
                $saved = $this->state->persist($locked, $draft, $state, (int) $data['stage'], $actor, $provenance);
                if ($historical !== null) {
                    $saved->update(['email_subject' => $historical->email_subject, 'email_body' => $historical->email_body,
                        'in_app_title' => $historical->in_app_title, 'in_app_body' => $historical->in_app_body]);
                }

                return ['draft' => CommunicationTemplateState::draft($saved), 'content_sha256' => $saved->content_sha256];
            });
    }
}
