<?php

namespace App\Assessment\Actions\Omr;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function count;

/**
 * Copies a booklet version's questions into a fresh draft version of the same
 * booklet so staff can edit the key without retyping it. The source version
 * is untouched; the copy is draft-only and audited.
 */
final class CopyBookletVersionToDraft
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    public function handle(OmrQuestionBookletVersion $source, User $actor): OmrQuestionBookletVersion
    {
        return DB::transaction(function () use ($source, $actor): OmrQuestionBookletVersion {
            $booklet = $source->booklet()->lockForUpdate()->firstOrFail();

            $existingDraft = $booklet->versions()
                ->where('status', OmrQuestionBookletVersion::STATUS_DRAFT)
                ->first();
            if ($existingDraft !== null) {
                throw new RuntimeException('A draft already exists for this booklet. Edit that draft instead of copying again.');
            }

            $copy = OmrQuestionBookletVersion::query()->create([
                'booklet_id' => $booklet->id,
                'version' => ((int) $booklet->versions()->max('version')) + 1,
                'status' => OmrQuestionBookletVersion::STATUS_DRAFT,
                'answer_key_hash' => (string) $source->answer_key_hash,
            ]);

            foreach ($source->questions()->get() as $question) {
                $copy->questions()->create([
                    'aptitude_area_id' => $question->aptitude_area_id,
                    'question_position' => $question->question_position,
                    'correct_option_code' => $question->correct_option_code,
                ]);
            }

            $this->recordAuditEvent->handle('grading.omr_booklet_copied', [
                'booklet_id' => $booklet->id,
                'source_version_id' => $source->id,
                'new_version_id' => $copy->id,
                'question_count' => count($copy->questions()->get()),
            ], (string) $actor->id);

            return $copy;
        });
    }
}
