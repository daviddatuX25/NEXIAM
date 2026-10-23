<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\BookletAuthorization;
use App\Assessment\Omr\Queries\BookletContractQuery;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function trim;

final class FinalizeBookletVersion
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
    ): OmrQuestionBookletVersion {
        $this->authorization->assertManage($actor);

        return DB::transaction(function () use ($booklet, $actor, $expectedLockVersion): OmrQuestionBookletVersion {
            $institution = Institution::query()->lockForUpdate()->findOrFail(Institution::current()->id);
            $booklet = OmrQuestionBookletVersion::query()->lockForUpdate()->findOrFail($booklet->id);
            if (! $booklet->isDraft()) {
                throw new RuntimeException('This Booklet is already finalized.');
            }
            if ($expectedLockVersion !== null && (int) $booklet->lock_version !== $expectedLockVersion) {
                throw new RuntimeException('This Booklet changed elsewhere. Reload it before finalizing.');
            }
            if ($booklet->allow_multiple_sets && ! $institution->usesMultiSetBooklets()) {
                throw new RuntimeException('Cannot finalize multi-set Booklet while alternate Booklet Sets are disabled.');
            }

            $booklet->load(['items.section', 'sets.items.bookletItem', 'sections']);
            if ($booklet->authoring_mode === OmrQuestionBookletVersion::AUTHORING_MODE_STORED_QUESTIONS) {
                $this->authorization->assertQuestionContent($actor);
            }
            $this->contract->validateItems($booklet, true);
            $sets = $this->contract->validateRequiredSets($booklet);
            foreach ($sets as $set) {
                $this->contract->assertSetIntegrity($booklet, $set);
                if (trim((string) $set->source_content_hash) !== trim((string) $booklet->content_hash)) {
                    throw new RuntimeException('A Booklet Set is not pinned to the current intrinsic content revision.');
                }
            }

            if (trim((string) $booklet->content_hash) === '') {
                throw new RuntimeException('A finalized Booklet requires a non-empty intrinsic content hash.');
            }

            $now = Carbon::now();
            foreach ($sets as $set) {
                $set->update([
                    'status' => OmrBookletSet::STATUS_FINALIZED,
                    'finalized_at' => $now,
                ]);
            }
            $booklet->fill([
                'finalized_by_user_id' => $actor->id,
                'finalized_at' => $now,
                'lock_version' => (int) $booklet->lock_version + 1,
            ]);
            $booklet->status = OmrQuestionBookletVersion::STATUS_FINALIZED;
            $booklet->save();
            $this->recordAuditEvent->handle('grading.booklet_finalized', [
                'booklet_version_id' => $booklet->id,
                'content_hash' => $booklet->content_hash,
                'answer_key_hash' => $booklet->answer_key_hash,
                'set_order_hashes' => $sets->mapWithKeys(fn (OmrBookletSet $set): array => [$set->set_code => $set->order_hash])->all(),
                'lock_version' => $booklet->lock_version,
            ], (string) $actor->id);

            return $booklet->fresh(['booklet', 'items.section', 'sections', 'sets.items.bookletItem']);
        });
    }
}
