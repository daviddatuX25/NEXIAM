<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateOmrDraftPages
{
    public function __construct(
        private readonly OmrAuthoringDraftService $drafts,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * @param  list<int>  $order
     */
    public function handle(
        OmrTemplateAuthoringDraft $draft,
        User $actor,
        string $operation,
        int $expectedLockVersion,
        ?int $pageNo = null,
        array $order = [],
    ): OmrTemplateAuthoringDraft {
        $this->drafts->assertOwner($draft, $actor);

        return DB::transaction(function () use ($draft, $actor, $operation, $expectedLockVersion, $pageNo, $order): OmrTemplateAuthoringDraft {
            $locked = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draft->id);
            if ((int) $locked->lock_version !== $expectedLockVersion) {
                throw new OmrAuthoringDraftException('concurrency_conflict', 'Draft changed since it was loaded.', ['lock_version' => $locked->lock_version]);
            }
            if (! in_array($locked->stage, [
                OmrTemplateAuthoringDraft::STAGE_DRAFT,
                OmrTemplateAuthoringDraft::STAGE_SOURCE_CONFIRMED,
                OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED,
            ], true)) {
                throw new OmrAuthoringDraftException('page_structure_locked', 'Page structure can only change before recognition mapping begins.');
            }

            $authoring = (array) $locked->authoring_json;
            if ($operation === 'add' && ((array) data_get($authoring, 'mapping.field_blocks', []) !== [] || ($authoring['verification'] ?? null) !== null)) {
                throw new OmrAuthoringDraftException('page_structure_locked', 'Reset mapping and verification before changing page structure.');
            }

            $pages = collect((array) ($authoring['pages'] ?? []));
            if ($pages->isEmpty()) {
                $pages = collect(range(1, (int) $locked->requested_page_count))->map(
                    static fn (int $number): array => ['page_no' => $number, 'kind' => 'blank', 'confirmed' => true],
                );
            }
            $pages = $pages->sortBy('page_no')->values();
            $oldOrder = $pages->pluck('page_no')->map(static fn (mixed $number): int => (int) $number)->all();

            if ($operation === 'add') {
                if ($pages->count() >= 4) {
                    throw new OmrAuthoringDraftException('page_count_limit', 'Answer sheets may contain at most four pages.');
                }
                $pages->push(['page_no' => $pages->count() + 1, 'kind' => 'blank', 'confirmed' => true]);
            } elseif ($operation === 'remove') {
                if ($pages->count() <= 1) {
                    throw new OmrAuthoringDraftException('page_count_minimum', 'Answer sheets must retain at least one page.');
                }
                if ($pageNo === null || ! in_array($pageNo, $oldOrder, true)) {
                    throw new OmrAuthoringDraftException('page_not_found', 'The selected page does not exist.');
                }
                $pages = $pages->reject(static fn (array $page): bool => (int) $page['page_no'] === $pageNo)->values();
            } elseif ($operation === 'reorder') {
                $normalizedOrder = array_values(array_map('intval', $order));
                $expectedOrder = $oldOrder;
                sort($normalizedOrder);
                sort($expectedOrder);
                if ($normalizedOrder !== $expectedOrder || count(array_unique($order)) !== count($oldOrder)) {
                    throw new OmrAuthoringDraftException('page_order_invalid', 'Page order must contain every current page exactly once.');
                }
                $byPage = $pages->keyBy('page_no');
                $pages = collect($order)->map(static fn (mixed $number): array => (array) $byPage->get((int) $number));
            } else {
                throw new OmrAuthoringDraftException('page_operation_invalid', 'Page operation is not supported.');
            }

            $pageNumberMap = [];
            $pages = $pages->values()->map(function (array $page, int $index) use (&$pageNumberMap): array {
                $oldPageNo = (int) ($page['page_no'] ?? $index + 1);
                $newPageNo = $index + 1;
                $pageNumberMap[$oldPageNo] = $newPageNo;

                return [...$page, 'page_no' => $newPageNo];
            });

            $studio = (array) ($authoring['studio'] ?? []);
            foreach (['components', 'sections'] as $collection) {
                if (! isset($studio[$collection]) || ! is_array($studio[$collection])) {
                    continue;
                }
                $studio[$collection] = collect($studio[$collection])
                    ->filter(static fn (mixed $item): bool => is_array($item) && isset($pageNumberMap[(int) ($item['page_no'] ?? 1)]))
                    ->map(static fn (array $item): array => [...$item, 'page_no' => $pageNumberMap[(int) ($item['page_no'] ?? 1)]])
                    ->values()
                    ->all();
            }
            if (isset($studio['identity']) && is_array($studio['identity'])) {
                foreach (['regions', 'write_in_boxes'] as $collection) {
                    if (! isset($studio['identity'][$collection]) || ! is_array($studio['identity'][$collection])) {
                        continue;
                    }

                    $studio['identity'][$collection] = collect($studio['identity'][$collection])
                        ->filter(static fn (mixed $item): bool => is_array($item) && isset($pageNumberMap[(int) ($item['page_no'] ?? 1)]))
                        ->map(static fn (array $item): array => [...$item, 'page_no' => $pageNumberMap[(int) ($item['page_no'] ?? 1)]])
                        ->values()
                        ->all();
                }
            }
            if (isset($studio['document_fields']) && is_array($studio['document_fields'])) {
                $studio['document_fields'] = collect($studio['document_fields'])
                    ->filter(static fn (mixed $field): bool => is_array($field) && isset($pageNumberMap[(int) ($field['page_no'] ?? 1)]))
                    ->map(static fn (array $field): array => [...$field, 'page_no' => $pageNumberMap[(int) ($field['page_no'] ?? 1)]])
                    ->values()
                    ->all();
            }
            $studio['active_page_no'] = min((int) ($studio['active_page_no'] ?? 1), $pages->count());

            $authoring['pages'] = $pages->all();
            if (isset($authoring['source']['metadata']) && is_array($authoring['source']['metadata'])) {
                $authoring['source']['metadata']['original_asset_ids'] = collect($pages)
                    ->filter(static fn (array $p): bool => (int) ($p['original_asset_id'] ?? 0) > 0)
                    ->mapWithKeys(static fn (array $p): array => [(int) $p['page_no'] => (int) $p['original_asset_id']])
                    ->all();
                $firstOriginal = collect($pages)->first(static fn (array $p): bool => (int) ($p['original_asset_id'] ?? 0) > 0);
                if ($firstOriginal !== null) {
                    $authoring['source']['metadata']['original_asset_id'] = (int) $firstOriginal['original_asset_id'];
                }
            }
            $authoring['studio'] = $studio;
            $authoring['mapping'] = null;
            $authoring['verification'] = null;
            $authoring['canonical'] = null;
            $authoring['mapping_dirty'] = true;
            $authoring['invalidations'] = [
                'page_structure_changed' => true,
                'stale_mapping' => true,
                'materialization_invalidated' => true,
                'verification_invalidated' => true,
            ];

            $allConfirmed = $pages->every(static fn (array $page): bool => ($page['confirmed'] ?? false) === true);
            $nextStage = $allConfirmed
                ? (in_array($locked->source_kind, [OmrTemplateAuthoringDraft::SOURCE_HTML, OmrTemplateAuthoringDraft::SOURCE_PRESET], true)
                    ? OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED
                    : OmrTemplateAuthoringDraft::STAGE_SOURCE_CONFIRMED)
                : OmrTemplateAuthoringDraft::STAGE_DRAFT;

            $locked->forceFill([
                'requested_page_count' => $pages->count(),
                'authoring_json' => $authoring,
                'stage' => $nextStage,
                'lock_version' => $expectedLockVersion + 1,
            ])->save();
            $saved = $locked->fresh();

            $this->recordAuditEvent->handle('omr.canonical.pages_updated', [
                'draft_id' => $saved->id,
                'operation' => $operation,
                'removed_page_no' => $operation === 'remove' ? $pageNo : null,
                'previous_order' => $oldOrder,
                'page_order' => collect($saved->authoring_json['pages'] ?? [])->pluck('page_no')->all(),
                'requested_page_count' => $saved->requested_page_count,
                'lock_version' => $saved->lock_version,
            ], (string) $actor->id);

            return $saved;
        });
    }
}
