<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Actions;

use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\User;
use App\Notifications\Templates\CommunicationTemplateMutation;
use App\Notifications\Templates\CommunicationTemplateRegistry;
use App\Notifications\Templates\NoticeDocumentPin;
use App\Notifications\Templates\NoticeMessageOutputReceipt;
use App\Notifications\Templates\NoticeMessageState;
use Illuminate\Support\Facades\DB;

final class CheckNoticeMessageAttachment
{
    public function __construct(private readonly CommunicationTemplateMutation $mutation, private readonly NoticeMessageState $state,
        private readonly NoticeDocumentPin $pins, private readonly NoticeMessageOutputReceipt $receipts) {}

    public function execute(CommunicationTemplate $template, array $input, User $actor): array
    {
        return DB::transaction(function () use ($template, $input, $actor): array {
            $family = CommunicationTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $this->mutation->authorize($family, $actor);
            abort_unless(CommunicationTemplateRegistry::authoringMode($family->domain, $family->slug) === 'email_pdf', 404);
            $draft = CommunicationTemplateDraft::query()->where('communication_template_id', $family->id)->lockForUpdate()->first();
            $this->mutation->guards($family, $draft, $input);
            abort_unless($draft?->content_schema_version === 'notice-content-3', 422, 'Save the message before checking its attachment.');
            $id = $input['document_revision_id'] === null ? null : (int) $input['document_revision_id'];
            abort_unless($id === $draft->document_revision_id, 422, 'Save the selected document first.');
            $version = $this->pins->selected($family, $id);
            $digest = $this->state->bindingDigest($family, $this->state->persisted($draft));
            abort_unless(hash_equals($digest, $input['binding_sha256']), 422, 'The attachment selection changed.');

            return ['binding_sha256' => $digest, 'attachment_receipt' => $this->receipts->issue($family, $draft, $actor, 'attachment'),
                'document' => $version === null ? null : ['id' => (int) $version->id, 'version' => $version->version,
                    'definition_sha256' => $version->definition_sha256]];
        });
    }
}
