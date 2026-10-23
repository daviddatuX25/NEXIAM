<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\BookletAuthorization;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function count;

final class CopyBookletVersionToDraft
{
    public function __construct(
        private readonly BookletAuthorization $authorization,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(OmrQuestionBookletVersion $source, User $actor): OmrQuestionBookletVersion
    {
        $this->authorization->assertManage($actor);
        if ($source->items()->exists()) {
            $this->authorization->assertQuestionContent($actor);
        }

        return DB::transaction(function () use ($source, $actor): OmrQuestionBookletVersion {
            $booklet = $source->booklet()->lockForUpdate()->firstOrFail();
            $source = OmrQuestionBookletVersion::query()->lockForUpdate()->findOrFail($source->id);
            $source->load(['sections', 'items.section', 'sets.items.bookletItem']);
            if ($source->sections->isEmpty()
                || $source->items->contains(fn ($item): bool => ! is_string($item->booklet_item_uid) || ! is_string($item->booklet_section_uid))) {
                throw new RuntimeException('This retained Booklet version predates intrinsic identities and cannot be copied without an explicit migration decision.');
            }

            if ($booklet->versions()->where('status', OmrQuestionBookletVersion::STATUS_DRAFT)->exists()) {
                throw new RuntimeException('A draft already exists for this Booklet. Edit that draft instead of copying again.');
            }

            $copy = OmrQuestionBookletVersion::query()->create([
                'booklet_id' => $booklet->id,
                'template_version_id' => null,
                'test_instrument_version_id' => null,
                'version' => ((int) $booklet->versions()->max('version')) + 1,
                'status' => OmrQuestionBookletVersion::STATUS_DRAFT,
                'authoring_mode' => $source->authoring_mode,
                'allow_multiple_sets' => $source->allow_multiple_sets,
                'lock_version' => 1,
                'answer_key_hash' => (string) $source->answer_key_hash,
                'content_hash' => (string) $source->content_hash,
            ]);

            $sectionsByUid = [];
            foreach ($source->sections as $section) {
                $sectionsByUid[$section->booklet_section_uid] = $copy->sections()->create([
                    'booklet_section_uid' => $section->booklet_section_uid,
                    'name' => $section->name,
                    'display_order' => $section->display_order,
                    'expected_count' => $section->expected_count,
                    'option_tokens' => $section->option_tokens,
                ]);
            }

            $itemMap = [];
            foreach ($source->items as $item) {
                $section = $sectionsByUid[$item->booklet_section_uid] ?? null;
                if ($section === null) {
                    throw new RuntimeException('The source Booklet item does not belong to an intrinsic section in the same version.');
                }
                $itemMap[$item->id] = $copy->items()->create([
                    'booklet_section_id' => $section->id,
                    'booklet_section_uid' => $item->booklet_section_uid,
                    'booklet_item_uid' => $item->booklet_item_uid,
                    'aptitude_area_id' => null,
                    'test_instrument_item_id' => null,
                    'authored_position' => $item->authored_position,
                    'option_tokens' => $item->option_tokens,
                    'key_required' => $item->key_required,
                    'encrypted_content' => $item->encrypted_content,
                    'correct_option_code' => $item->correct_option_code,
                    'content_hash' => $item->content_hash,
                ]);
            }

            foreach ($source->sets as $set) {
                $setCopy = $copy->sets()->create([
                    'set_code' => $set->set_code,
                    'status' => OmrBookletSet::STATUS_DRAFT,
                    'generation_version' => $set->generation_version,
                    'generation_seed' => $set->generation_seed,
                    'source_content_hash' => $set->source_content_hash,
                    'order_hash' => $set->order_hash,
                    'answer_key_hash' => $set->answer_key_hash,
                    'diagnostics_json' => $set->diagnostics_json,
                ]);
                foreach ($set->items as $setItem) {
                    $copiedItem = $setItem->booklet_item_id === null ? null : ($itemMap[$setItem->booklet_item_id] ?? null);
                    if ($copiedItem === null || $copiedItem->booklet_item_uid !== $setItem->booklet_item_uid) {
                        throw new RuntimeException('A source Set occurrence does not match the copied intrinsic item identity.');
                    }
                    $setCopy->items()->create([
                        'booklet_item_id' => $copiedItem->id,
                        'booklet_version_id' => $copy->id,
                        'booklet_item_uid' => $copiedItem->booklet_item_uid,
                        'booklet_section_uid' => $copiedItem->booklet_section_uid,
                        'aptitude_area_id' => null,
                        'test_instrument_item_id' => null,
                        'area_position' => $setItem->area_position,
                        'global_position' => $setItem->global_position,
                        'correct_option_code' => $setItem->correct_option_code,
                        'content_hash' => $setItem->content_hash,
                    ]);
                }
            }

            $this->recordAuditEvent->handle('grading.omr_booklet_copied', [
                'booklet_id' => $booklet->id,
                'source_version_id' => $source->id,
                'new_version_id' => $copy->id,
                'item_count' => count($itemMap),
            ], (string) $actor->id);

            return $copy->fresh(['booklet', 'sections', 'items.section', 'sets.items.bookletItem']);
        });
    }
}
