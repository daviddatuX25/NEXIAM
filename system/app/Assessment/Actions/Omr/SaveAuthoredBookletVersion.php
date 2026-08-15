<?php

namespace App\Assessment\Actions\Omr;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletQuestion;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use function array_column;
use function array_keys;
use function count;
use function hash;
use function implode;
use function in_array;
use function json_encode;
use function preg_match;
use function range;
use function sort;
use function trim;

/**
 * Creates or updates a NEXIAM-authored answer booklet as a new immutable
 * version. The key is stored only as a SHA-256 digest on the version row;
 * question rows carry positions and correct options. Activated versions are
 * immutable and edits create the next draft version.
 */
final class SaveAuthoredBookletVersion
{
    public const OPTION_CODES = ['A', 'B', 'C', 'D'];

    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    /**
     * @param  array<int, array{area_id:int, question_no:int, position:int, correct_option_code:string}>  $questions
     */
    public function handle(string $code, string $name, array $questions, User $actor): OmrQuestionBookletVersion
    {
        if (preg_match('/^[A-Z0-9-]{3,32}$/', $code) !== 1) {
            throw new RuntimeException('Booklet codes must be 3–32 characters of A-Z, 0-9, or dashes.');
        }

        if (trim($name) === '') {
            throw new RuntimeException('A booklet name is required.');
        }

        $this->assertQuestions($questions);

        return DB::transaction(function () use ($code, $name, $questions, $actor): OmrQuestionBookletVersion {
            $booklet = OmrQuestionBooklet::query()->firstOrCreate(
                ['code' => $code],
                ['name' => $name],
            );

            $current = $booklet->versions()->orderByDesc('version')->first();
            if ($current !== null && $current->status === OmrQuestionBookletVersion::STATUS_DRAFT) {
                $version = $current;
                $version->questions()->delete();
            } else {
                $version = OmrQuestionBookletVersion::query()->create([
                    'booklet_id' => $booklet->id,
                    'version' => ((int) $booklet->versions()->max('version')) + 1,
                    'status' => OmrQuestionBookletVersion::STATUS_DRAFT,
                    'answer_key_hash' => '',
                ]);
            }

            $version->update([
                'answer_key_hash' => $this->keyDigest($questions),
            ]);

            foreach ($questions as $question) {
                OmrQuestionBookletQuestion::query()->create([
                    'booklet_version_id' => $version->id,
                    'aptitude_area_id' => (int) $question['area_id'],
                    'question_position' => (int) $question['position'],
                    'correct_option_code' => (string) $question['correct_option_code'],
                ]);
            }

            $this->recordAuditEvent->handle('grading.omr_booklet_saved', [
                'booklet_id' => $booklet->id,
                'booklet_version_id' => $version->id,
                'question_count' => count($questions),
                'answer_key_hash' => $version->answer_key_hash,
            ], (string) $actor->id);

            return $version;
        });
    }

    /**
     * @param  array<int, array{area_id:int, question_no:int, position:int, correct_option_code:string}>  $questions
     */
    private function assertQuestions(array $questions): void
    {
        if ($questions === []) {
            throw new RuntimeException('A booklet needs at least one question.');
        }

        $areaIds = [];
        foreach ($questions as $question) {
            $position = (int) ($question['position'] ?? 0);
            if ($position < 1 || $position > 200) {
                throw new RuntimeException('Booklet question positions must be global 1–200.');
            }
            if (! in_array((string) ($question['correct_option_code'] ?? ''), self::OPTION_CODES, true)) {
                throw new RuntimeException("Question at position {$position} needs an A–D correct option.");
            }
            $areaIds[(int) $question['area_id']] = true;
        }

        if (AptitudeArea::query()->whereIn('id', array_keys($areaIds))->count() !== count($areaIds)) {
            throw new RuntimeException('A booklet question references an unknown aptitude area.');
        }

        $positions = array_column($questions, 'position');
        $sorted = $positions;
        sort($sorted);
        if ($sorted !== range(1, count($sorted))) {
            throw new RuntimeException('Booklet question positions must be contiguous global positions starting at 1.');
        }
    }

    /**
     * @param  array<int, array{area_id:int, question_no:int, position:int, correct_option_code:string}>  $questions
     */
    private function keyDigest(array $questions): string
    {
        $rows = [];
        foreach ($questions as $question) {
            $rows[] = implode('|', [
                (int) $question['position'],
                (int) $question['area_id'],
                (string) $question['correct_option_code'],
            ]);
        }

        return hash('sha256', implode("\n", $rows));
    }
}
