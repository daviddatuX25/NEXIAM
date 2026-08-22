<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;

final class CreateOmrAuthoringDraft
{
    public function __construct(
        private readonly OmrAuthoringDraftService $drafts,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    /** @param array<string, mixed> $authoring */
    public function handle(User $actor, string $code, string $name, string $sourceKind, array $authoring = []): OmrTemplateAuthoringDraft
    {
        $draft = $this->drafts->create($actor, $code, $name, $sourceKind, $authoring);
        $this->recordAuditEvent->handle('omr.canonical_draft_created', [
            'draft_id' => $draft->id,
            'code' => $draft->code,
            'source_kind' => $draft->source_kind,
        ], (string) $actor->id);

        return $draft;
    }
}
