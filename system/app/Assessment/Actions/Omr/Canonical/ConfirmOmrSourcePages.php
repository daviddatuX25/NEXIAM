<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ConfirmOmrSourcePages
{
    public function __construct(
        private readonly OmrAuthoringDraftService $drafts,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /** @param list<array{page_no:int,decision:string,reason?:string|null}> $pages */
    public function handle(OmrTemplateAuthoringDraft $draft, User $actor, array $pages, int $expectedLockVersion): OmrTemplateAuthoringDraft
    {
        $this->drafts->assertOwner($draft, $actor);
        if ($draft->stage !== OmrTemplateAuthoringDraft::STAGE_DRAFT) {
            throw new OmrAuthoringDraftException('page_confirmation_stage_invalid', 'Pages can only be confirmed on a draft awaiting confirmation.');
        }

        return DB::transaction(function () use ($draft, $actor, $pages, $expectedLockVersion): OmrTemplateAuthoringDraft {
            $locked = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draft->id);
            if ((int) $locked->lock_version !== $expectedLockVersion) {
                throw new OmrAuthoringDraftException('concurrency_conflict', 'Draft changed since it was loaded.', ['lock_version' => $locked->lock_version]);
            }
            $authoring = (array) $locked->authoring_json;
            $existing = collect((array) ($authoring['pages'] ?? []))->keyBy('page_no');
            $submitted = collect($pages)->keyBy('page_no');
            if ($existing->count() !== (int) $locked->requested_page_count) {
                throw new OmrAuthoringDraftException('uploaded_page_count_mismatch', "The uploaded document contains {$existing->count()} pages; this draft expects {$locked->requested_page_count}.");
            }
            if ($existing->count() === 0 || $existing->keys()->sort()->values()->all() !== $submitted->keys()->sort()->values()->all()) {
                throw new OmrAuthoringDraftException('page_confirmation_incomplete', 'Every ingested page must have one confirmation decision.');
            }

            $allConfirmed = true;
            foreach ($existing as $pageNo => $page) {
                $decision = $submitted->get($pageNo);
                if ($decision['decision'] === 'rejected' && empty($decision['reason'])) {
                    throw new OmrAuthoringDraftException('page_rejection_reason_required', "Page {$pageNo} requires a staff rejection reason.");
                }
                $confirmed = $decision['decision'] === 'confirmed';
                $allConfirmed = $allConfirmed && $confirmed;
                $existing[$pageNo] = [
                    ...$page,
                    'confirmed' => $confirmed,
                    'rejection_reason' => $confirmed ? null : $decision['reason'],
                    'classification_actor_id' => $actor->id,
                    'classified_at' => now()->toIso8601String(),
                ];
            }
            $authoring['pages'] = $existing->values()->sortBy('page_no')->values()->all();
            $locked->forceFill([
                'authoring_json' => $authoring,
                'stage' => $allConfirmed ? OmrTemplateAuthoringDraft::STAGE_SOURCE_CONFIRMED : OmrTemplateAuthoringDraft::STAGE_DRAFT,
                'lock_version' => (int) $locked->lock_version + 1,
            ])->save();
            $result = $locked->fresh();

            $this->recordAuditEvent->handle('omr.canonical.source_pages_classified', [
                'draft_id' => $result->id,
                'page_decisions' => $result->authoring_json['pages'] ?? [],
                'all_confirmed' => $allConfirmed,
            ], (string) $actor->id);

            return $result;
        });
    }
}
