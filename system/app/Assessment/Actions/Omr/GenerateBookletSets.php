<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\BookletAuthorization;
use App\Assessment\Omr\Queries\BookletContractQuery;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function hash;
use function json_encode;

final class GenerateBookletSets
{
    public function __construct(
        private readonly BookletAuthorization $authorization,
        private readonly BookletContractQuery $contract,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        OmrQuestionBookletVersion $booklet,
        User $actor,
        ?int $expectedLockVersion = null,
        string $seed = 'booklet-intrinsic-v1',
    ): OmrQuestionBookletVersion {
        $this->authorization->assertManage($actor);

        return DB::transaction(function () use ($booklet, $actor, $expectedLockVersion, $seed): OmrQuestionBookletVersion {
            $institution = Institution::query()->lockForUpdate()->findOrFail(Institution::current()->id);
            $booklet = OmrQuestionBookletVersion::query()->lockForUpdate()->findOrFail($booklet->id);
            if (! $booklet->isDraft()) {
                throw new RuntimeException('Finalized Booklets cannot regenerate Sets.');
            }
            if ($expectedLockVersion !== null && (int) $booklet->lock_version !== $expectedLockVersion) {
                throw new RuntimeException('This booklet changed elsewhere. Reload it before generating Sets.');
            }
            if ($booklet->allow_multiple_sets && ! $institution->usesMultiSetBooklets()) {
                throw new RuntimeException('Alternate Booklet Sets are not enabled for this institution.');
            }
            if ($booklet->authoring_mode === OmrQuestionBookletVersion::AUTHORING_MODE_STORED_QUESTIONS) {
                $this->authorization->assertQuestionContent($actor);
            }

            $booklet->load(['items.section', 'sets.items']);
            $this->contract->validateItems($booklet, true);
            if ($booklet->sets->contains(fn (OmrBookletSet $set): bool => $set->isFinalized())) {
                throw new RuntimeException('Finalized Sets cannot be regenerated.');
            }

            $items = $booklet->items
                ->sortBy(fn ($item): string => sprintf('%08d:%08d', $item->section?->display_order ?? 0, $item->authored_position))
                ->values();
            foreach ($booklet->sets as $set) {
                $set->items()->delete();
            }
            $booklet->sets()->delete();
            foreach ($booklet->allowedSetCodes() as $setCode) {
                $set = $booklet->sets()->create([
                    'set_code' => $setCode,
                    'status' => OmrBookletSet::STATUS_DRAFT,
                    'generation_version' => 'booklet-intrinsic-v1',
                    'generation_seed' => $seed,
                    'source_content_hash' => $booklet->content_hash,
                    'diagnostics_json' => [],
                ]);
                $order = [];
                $keyRows = [];
                foreach ($items as $index => $item) {
                    $token = $booklet->authoring_mode === OmrQuestionBookletVersion::AUTHORING_MODE_STORED_QUESTIONS && $item->key_required
                        ? $item->correct_option_code
                        : null;
                    $set->items()->create([
                        'booklet_item_id' => $item->id,
                        'booklet_version_id' => $booklet->id,
                        'booklet_item_uid' => $item->booklet_item_uid,
                        'booklet_section_uid' => $item->booklet_section_uid,
                        'aptitude_area_id' => null,
                        'test_instrument_item_id' => null,
                        'area_position' => $item->authored_position,
                        'global_position' => $index + 1,
                        'correct_option_code' => $token,
                        'content_hash' => $item->content_hash,
                    ]);
                    $order[] = [
                        'booklet_item_uid' => $item->booklet_item_uid,
                        'booklet_section_uid' => $item->booklet_section_uid,
                        'global_position' => $index + 1,
                    ];
                    $keyRows[] = [
                        'booklet_item_uid' => $item->booklet_item_uid,
                        'key_required' => (bool) $item->key_required,
                        'correct_option_code' => $token,
                    ];
                }
                $set->update([
                    'order_hash' => hash('sha256', json_encode($order, JSON_THROW_ON_ERROR)),
                    'answer_key_hash' => hash('sha256', json_encode($keyRows, JSON_THROW_ON_ERROR)),
                ]);
            }

            $booklet->update([
                'answer_key_hash' => hash('sha256', json_encode([
                    'mode' => $booklet->authoring_mode,
                    'allow_multiple_sets' => (bool) $booklet->allow_multiple_sets,
                    'sets' => $booklet->sets()->orderBy('set_code')->get()->mapWithKeys(
                        fn (OmrBookletSet $set): array => [$set->set_code => $set->answer_key_hash],
                    )->all(),
                ], JSON_THROW_ON_ERROR)),
                'lock_version' => (int) $booklet->lock_version + 1,
            ]);
            $this->recordAuditEvent->handle('grading.booklet_sets_generated', [
                'booklet_version_id' => $booklet->id,
                'set_codes' => $booklet->allowedSetCodes(),
                'generation_seed' => $seed,
                'source_content_hash' => $booklet->content_hash,
                'lock_version' => $booklet->lock_version,
            ], (string) $actor->id);

            return $booklet->fresh(['booklet', 'items.section', 'sets.items']);
        });
    }
}
