<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\BookletAuthorization;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function hash;
use function json_encode;

final class SetBookletSetsMode
{
    public function __construct(
        private readonly BookletAuthorization $authorization,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        OmrQuestionBookletVersion $booklet,
        User $actor,
        bool $allowMultipleSets,
        int $expectedLockVersion,
        bool $confirmDiscardAlternates,
    ): OmrQuestionBookletVersion {
        $this->authorization->assertManage($actor);

        return DB::transaction(function () use ($booklet, $actor, $allowMultipleSets, $expectedLockVersion, $confirmDiscardAlternates): OmrQuestionBookletVersion {
            $institution = Institution::query()->lockForUpdate()->findOrFail(Institution::current()->id);
            $booklet = OmrQuestionBookletVersion::query()->lockForUpdate()->findOrFail($booklet->id);

            if (! $booklet->isDraft()) {
                throw new RuntimeException('Only draft Booklets can change Set mode.');
            }
            if ((int) $booklet->lock_version !== $expectedLockVersion) {
                throw new RuntimeException('This Booklet changed elsewhere. Reload it before changing Set mode.');
            }
            if ($allowMultipleSets && ! $institution->usesMultiSetBooklets()) {
                throw new RuntimeException('Alternate Booklet Sets are not enabled for this institution.');
            }
            if ((bool) $booklet->allow_multiple_sets === $allowMultipleSets) {
                return $booklet->fresh(['booklet', 'templateVersion', 'items', 'sets.items']);
            }

            $alternateSets = $booklet->sets()
                ->whereIn('set_code', ['B', 'C', 'D'])
                ->orderBy('set_code')
                ->lockForUpdate()
                ->withCount('items')
                ->get();
            $populatedAlternates = $alternateSets->filter(fn (OmrBookletSet $set): bool => (int) $set->items_count > 0);

            if (! $allowMultipleSets) {
                $nonDraftAlternates = $alternateSets->filter(fn (OmrBookletSet $set): bool => ! $set->isDraft());
                if ($nonDraftAlternates->isNotEmpty()) {
                    $codes = $nonDraftAlternates->pluck('set_code')->implode(', ');
                    throw new RuntimeException("Cannot remove non-draft alternate Sets {$codes}.");
                }
            }

            if (! $allowMultipleSets && $populatedAlternates->isNotEmpty() && ! $confirmDiscardAlternates) {
                $codes = $populatedAlternates->pluck('set_code')->implode(', ');
                throw new RuntimeException("Confirm removal of populated alternate Sets {$codes} before switching to Set A only.");
            }

            $removedSets = [];
            if (! $allowMultipleSets) {
                foreach ($alternateSets as $set) {
                    $removedSets[] = [
                        'set_id' => (int) $set->id,
                        'set_code' => (string) $set->set_code,
                        'item_count' => (int) $set->items_count,
                        'answer_key_hash' => (string) $set->answer_key_hash,
                        'order_hash' => (string) $set->order_hash,
                    ];
                    $set->items()->delete();
                    $set->delete();
                }
            }

            $booklet->forceFill([
                'allow_multiple_sets' => $allowMultipleSets,
                'answer_key_hash' => hash('sha256', json_encode([
                    'mode' => $booklet->authoring_mode,
                    'allow_multiple_sets' => $allowMultipleSets,
                    'sets' => $booklet->sets()->orderBy('set_code')->get()->mapWithKeys(
                        fn (OmrBookletSet $set): array => [$set->set_code => $set->answer_key_hash],
                    )->all(),
                ], JSON_THROW_ON_ERROR)),
                'lock_version' => (int) $booklet->lock_version + 1,
            ])->save();

            $this->recordAuditEvent->handle('grading.booklet_sets_mode_changed', [
                'booklet_version_id' => (int) $booklet->id,
                'allow_multiple_sets' => $allowMultipleSets,
                'removed_alternate_sets' => $removedSets,
                'lock_version' => (int) $booklet->lock_version,
            ], (string) $actor->id);

            return $booklet->fresh(['booklet', 'templateVersion', 'items', 'sets.items']);
        });
    }
}
