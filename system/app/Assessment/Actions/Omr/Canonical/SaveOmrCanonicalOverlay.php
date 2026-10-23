<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrOverlaySpec;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SaveOmrCanonicalOverlay
{
    public function __construct(
        private readonly OmrOverlaySpec $spec,
        private readonly OmrAuthoringDraftService $drafts,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /** @param array<string, mixed> $overlay */
    public function handle(OmrTemplateAuthoringDraft $draft, User $actor, array $overlay, int $expectedLockVersion): OmrTemplateAuthoringDraft
    {
        $this->drafts->assertOwner($draft, $actor);
        if (! in_array($draft->stage, [OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED, OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE, OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED], true)) {
            throw new \RuntimeException('overlay_stage_invalid');
        }
        $validated = $this->spec->validate($overlay);

        return DB::transaction(function () use ($draft, $actor, $validated, $expectedLockVersion): OmrTemplateAuthoringDraft {
            $locked = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draft->id);
            if ((int) $locked->lock_version !== $expectedLockVersion) {
                throw new \RuntimeException('concurrency_conflict');
            }
            if ($locked->stage === OmrTemplateAuthoringDraft::STAGE_COMPILED) {
                throw new \RuntimeException('draft_read_only');
            }
            $authoring = (array) $locked->authoring_json;
            $authoring['overlay'] = $validated;
            $authoring['mapping'] = null;
            $authoring['verification'] = null;
            $authoring['invalidations'] = ['stale_mapping' => true, 'verification_invalidated' => true];
            $locked->forceFill([
                'authoring_json' => $authoring,
                'lock_version' => $expectedLockVersion + 1,
                'stage' => OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED,
            ])->save();
            $saved = $locked->fresh();
            $this->recordAuditEvent->handle('omr.canonical.overlay_saved', [
                'draft_id' => $saved->id,
                'overlay_hash' => $validated['hash'],
                'stale_mapping' => true,
                'verification_invalidated' => true,
            ], (string) $actor->id);

            return $saved;
        });
    }
}
