<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\BookletAuthorization;
use App\Assessment\Omr\Queries\BookletContractQuery;
use App\Assessment\Support\MaterialOwnerAccess;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrBookletItem;
use App\Models\Assessment\OmrBookletSection;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

use function array_key_exists;
use function hash;
use function is_array;
use function json_encode;
use function sprintf;
use function strlen;

final class SaveAuthoredBookletVersion
{
    public function __construct(
        private readonly BookletAuthorization $authorization,
        private readonly MaterialOwnerAccess $materialOwnerAccess,
        private readonly BookletContractQuery $contract,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * Save secure authored questions, while retaining the historical key-only
     * signature used by legacy Pair/capture setup callers.
     *
     * @param  array<int, array<string, mixed>>|string  $items
     * @param  User|array<int, array<string, mixed>>  $actor
     */
    public function handle(
        OmrQuestionBookletVersion|string $booklet,
        array|string $items,
        User|array $actor,
        User|int|null $expectedLockVersion = null,
        bool $complete = false,
    ): OmrQuestionBookletVersion {
        if (is_string($booklet)) {
            if (! is_string($items) || ! is_array($actor) || ! $expectedLockVersion instanceof User) {
                throw new RuntimeException('Invalid legacy booklet authoring arguments.');
            }

            return $this->handleLegacy($booklet, $items, $actor, $expectedLockVersion);
        }

        if (! is_array($items) || ! $actor instanceof User) {
            throw new RuntimeException('Invalid secure booklet authoring arguments.');
        }

        return $this->handleSecure(
            $booklet,
            $items,
            $actor,
            is_int($expectedLockVersion) ? $expectedLockVersion : null,
            $complete,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function handleSecure(
        OmrQuestionBookletVersion $booklet,
        array $items,
        User $actor,
        ?int $expectedLockVersion,
        bool $complete,
    ): OmrQuestionBookletVersion {
        $this->authorization->assertManage($actor);
        $institutionId = $this->materialOwnerAccess->institutionId($actor, CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE);

        return DB::transaction(function () use ($booklet, $items, $actor, $expectedLockVersion, $complete, $institutionId): OmrQuestionBookletVersion {
            $booklet = OmrQuestionBookletVersion::query()->with('booklet')->lockForUpdate()->findOrFail($booklet->id);
            if ((int) $booklet->booklet->institution_id !== $institutionId) {
                throw new RuntimeException('The Booklet belongs to another institution.');
            }
            $this->assertDraftAndVersion($booklet, $expectedLockVersion);
            $booklet->loadMissing('items', 'sets.items');

            if ($booklet->authoring_mode === OmrQuestionBookletVersion::AUTHORING_MODE_STORED_QUESTIONS) {
                $this->authorization->assertQuestionContent($actor);
                $this->saveStoredQuestions($booklet, $items);
            } elseif ($booklet->authoring_mode === OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY) {
                $this->saveAnswerKeys($booklet, $items);
            } else {
                throw new RuntimeException('Choose a supported Booklet authoring mode.');
            }
            $booklet->load(['items.section', 'templateVersion']);
            $this->contract->validateItems($booklet, $complete);
            $booklet->update([
                'content_hash' => $this->contentHash($booklet),
                'answer_key_hash' => $this->answerKeyHash($booklet),
                'lock_version' => (int) $booklet->lock_version + 1,
            ]);

            $this->recordAuditEvent->handle('grading.booklet_authored_saved', [
                'booklet_version_id' => $booklet->id,
                'item_count' => $booklet->items->count(),
                'lock_version' => $booklet->lock_version,
            ], (string) $actor->id);

            return $booklet->fresh(['booklet', 'items.section']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function handleLegacy(string $code, string $name, array $items, User $actor): OmrQuestionBookletVersion
    {
        $this->authorization->assertManage($actor);
        $institutionId = $this->materialOwnerAccess->institutionId($actor, CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE);

        $code = strtoupper(trim($code));
        $name = trim($name);

        return DB::transaction(function () use ($code, $name, $items, $actor, $institutionId): OmrQuestionBookletVersion {
            $normalized = [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    throw new RuntimeException('Each booklet question must be an object.');
                }
                $position = (int) ($item['position'] ?? 0);
                $areaId = (int) ($item['area_id'] ?? 0);
                $option = strtoupper((string) ($item['correct_option_code'] ?? ''));
                if ($position < 1 || $areaId < 1 || $option === '' || strlen($option) > 32) {
                    throw new RuntimeException('Each booklet question needs a valid position, aptitude area, and option.');
                }
                if (array_key_exists($position, $normalized)) {
                    throw new RuntimeException('Booklet question positions must be unique.');
                }
                $normalized[$position] = [
                    'area_id' => $areaId,
                    'correct_option_code' => $option,
                ];
            }

            $positions = array_keys($normalized);
            sort($positions);
            if ($positions !== range(1, count($positions))) {
                throw new RuntimeException('Booklet question positions must be contiguous from 1.');
            }

            $booklet = OmrQuestionBooklet::query()->firstOrCreate(
                ['institution_id' => $institutionId, 'code' => $code],
                ['name' => $name, 'is_synthetic' => true],
            );
            if (! (bool) $booklet->is_synthetic) {
                throw new RuntimeException('Synthetic authoring cannot relabel an institutional Booklet.');
            }
            $draft = $booklet->versions()->where('status', OmrQuestionBookletVersion::STATUS_DRAFT)->first();
            if ($draft !== null && $draft->authoring_mode !== null) {
                throw new RuntimeException('A secure Booklet draft must be edited from the Exam Materials workspace.');
            }
            $draft ??= $booklet->versions()->create([
                'version' => ((int) $booklet->versions()->max('version')) + 1,
                'status' => OmrQuestionBookletVersion::STATUS_DRAFT,
                'authoring_mode' => null,
                'allow_multiple_sets' => false,
                'lock_version' => 1,
                'answer_key_hash' => '',
            ]);

            $draft->questions()->delete();
            foreach ($normalized as $position => $item) {
                $draft->questions()->create([
                    'aptitude_area_id' => $item['area_id'],
                    'question_position' => $position,
                    'correct_option_code' => $item['correct_option_code'],
                ]);
            }

            $keyLines = [];
            foreach ($positions as $position) {
                $keyLines[] = sprintf(
                    '%d|%d|%s',
                    $position,
                    $normalized[$position]['area_id'],
                    $normalized[$position]['correct_option_code'],
                );
            }
            $draft->update([
                'answer_key_hash' => hash('sha256', implode("\n", $keyLines)),
                'lock_version' => (int) $draft->lock_version + 1,
            ]);

            $this->recordAuditEvent->handle('grading.omr_booklet_saved', [
                'booklet_id' => $booklet->id,
                'booklet_version_id' => $draft->id,
                'question_count' => count($normalized),
            ], (string) $actor->id);

            return $draft->fresh(['booklet', 'questions']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function saveStoredQuestions(OmrQuestionBookletVersion $booklet, array $items): void
    {
        $this->persistItems($booklet, $items, true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function saveAnswerKeys(OmrQuestionBookletVersion $booklet, array $items): void
    {
        $this->persistItems($booklet, $items, false);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function persistItems(OmrQuestionBookletVersion $booklet, array $items, bool $storedQuestions): void
    {
        $existing = $booklet->items->keyBy('id');
        $kept = [];
        $sections = [];
        foreach ($items as &$item) {
            if (! is_array($item)) {
                throw new RuntimeException('Each booklet item must be an object.');
            }
            $sectionUid = (string) ($item['booklet_section_uid'] ?? '');
            $sectionName = trim((string) ($item['section_name'] ?? ''));
            $tokens = $item['option_tokens'] ?? null;
            if ($sectionUid === '' || ! Str::isUuid($sectionUid)) {
                $sectionUid = '00000000-0000-4000-8000-000000000001';
                $item['booklet_section_uid'] = $sectionUid;
            }
            if ($sectionName === '') {
                $sectionName = 'General';
            }
            if (! is_array($tokens) || $tokens === []) {
                $tokens = ['A', 'B', 'C', 'D'];
            }
            $tokens = array_values($tokens);
            if (count(array_unique($tokens, SORT_REGULAR)) !== count($tokens)
                || collect($tokens)->contains(fn ($token): bool => ! is_string($token) || trim($token) === '' || strlen($token) > 32)) {
                throw new RuntimeException('Booklet response tokens must be non-empty unique literal strings.');
            }
            $displayOrder = (int) ($item['section_display_order'] ?? 1);
            $expectedCount = (int) ($item['section_expected_count'] ?? count($items));
            if ($displayOrder < 1) {
                $displayOrder = 1;
            }
            if ($expectedCount < 1) {
                $expectedCount = count($items);
            }
            $section = [
                'name' => $sectionName,
                'display_order' => $displayOrder,
                'expected_count' => $expectedCount,
                'option_tokens' => $tokens,
            ];
            if (isset($sections[$sectionUid]) && $sections[$sectionUid] !== $section) {
                throw new RuntimeException('A Booklet section must keep one name, order, expected count, and literal option domain within a save.');
            }
            $sections[$sectionUid] = $section;
        }

        $sectionRowsByUid = [];
        foreach ($sections as $sectionUid => $section) {
            $sectionRowsByUid[$sectionUid] = OmrBookletSection::query()->updateOrCreate(
                ['booklet_version_id' => $booklet->id, 'booklet_section_uid' => $sectionUid],
                $section,
            );
        }

        unset($item);
        $booklet->items()->update([
            'authored_position' => DB::raw('-authored_position'),
        ]);

        foreach ($items as $item) {
            $record = array_key_exists('id', $item) ? $existing->get((int) $item['id']) : null;
            if (array_key_exists('id', $item) && $record === null) {
                throw new RuntimeException('A booklet item does not belong to this draft.');
            }
            $position = (int) ($item['authored_position'] ?? 0);
            $sectionUid = (string) $item['booklet_section_uid'];
            $section = $sections[$sectionUid];
            $tokens = $section['option_tokens'];
            $keyRequired = (bool) ($item['key_required'] ?? true);
            $correctOption = $storedQuestions ? (string) ($item['correct_option_code'] ?? '') : '';
            if ($position < 1
                || ($keyRequired && $storedQuestions && ($correctOption === '' || ! in_array($correctOption, $tokens, true)))
                || (! $keyRequired && $correctOption !== '')) {
                throw new RuntimeException('Each Booklet item needs a valid position and an allowed literal key state.');
            }
            $content = $storedQuestions ? $this->questionContent($item) : [];
            $itemUid = (string) ($item['booklet_item_uid'] ?? $record?->booklet_item_uid ?? (string) Str::uuid());
            if (! Str::isUuid($itemUid) || ($record !== null && $record->booklet_item_uid !== null && $itemUid !== $record->booklet_item_uid)) {
                throw new RuntimeException('A Booklet item UID must remain stable while editing that item.');
            }
            $contentHash = hash('sha256', json_encode([
                'booklet_item_uid' => $itemUid,
                'booklet_section_uid' => $sectionUid,
                'position' => $position,
                'key_required' => $keyRequired,
                'option_tokens' => $tokens,
                'content' => $content,
                'correct_option_code' => $storedQuestions && $keyRequired ? $correctOption : null,
            ], JSON_THROW_ON_ERROR));
            $attributes = [
                'booklet_section_uid' => $sectionUid,
                'booklet_section_id' => (int) $sectionRowsByUid[$sectionUid]->id,
                'booklet_item_uid' => $itemUid,
                'aptitude_area_id' => null,
                'test_instrument_item_id' => null,
                'authored_position' => $position,
                'option_tokens' => $tokens,
                'key_required' => $keyRequired,
                'encrypted_content' => $content,
                'correct_option_code' => $storedQuestions && $keyRequired ? $correctOption : null,
                'content_hash' => $contentHash,
            ];
            if ($record !== null) {
                $record->update($attributes);
            } else {
                $record = $booklet->items()->create($attributes);
            }
            $kept[] = $record->id;
        }

        $booklet->items()
            ->when($kept !== [], fn ($query) => $query->whereNotIn('id', $kept))
            ->when($kept === [], fn ($query) => $query)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function questionContent(array $item): array
    {
        $content = $item['content'] ?? [
            'prompt' => $item['prompt'] ?? null,
            'choices' => $item['choices'] ?? [],
        ];
        if (! is_array($content) || ! array_key_exists('prompt', $content) || ! is_array($content['choices'] ?? null)) {
            throw new RuntimeException('Stored questions require prompt and choices content.');
        }

        return [
            'prompt' => (string) $content['prompt'],
            'choices' => $content['choices'],
        ];
    }

    private function contentHash(OmrQuestionBookletVersion $booklet): string
    {
        return hash('sha256', json_encode($booklet->items
            ->sortBy(fn (OmrBookletItem $item): string => sprintf('%08d:%08d', $item->section?->display_order ?? 0, $item->authored_position))
            ->map(fn (OmrBookletItem $item): array => [
                'booklet_item_uid' => $item->booklet_item_uid,
                'booklet_section_uid' => $item->booklet_section_uid,
                'position' => $item->authored_position,
                'key_required' => (bool) $item->key_required,
                'option_tokens' => $item->option_tokens,
                'content_hash' => $item->content_hash,
            ])->values()->all(), JSON_THROW_ON_ERROR));
    }

    private function answerKeyHash(OmrQuestionBookletVersion $booklet): string
    {
        return hash('sha256', json_encode($booklet->items
            ->sortBy(fn (OmrBookletItem $item): string => sprintf('%08d:%08d', $item->section?->display_order ?? 0, $item->authored_position))
            ->map(fn (OmrBookletItem $item): array => [
                'booklet_item_uid' => $item->booklet_item_uid,
                'booklet_section_uid' => $item->booklet_section_uid,
                'key_required' => (bool) $item->key_required,
                'correct_option_code' => $item->correct_option_code,
            ])->values()->all(), JSON_THROW_ON_ERROR));
    }

    private function assertDraftAndVersion(OmrQuestionBookletVersion $booklet, ?int $expectedLockVersion): void
    {
        if (! $booklet->isDraft()) {
            throw new RuntimeException('Finalized Booklets cannot be edited.');
        }
        if ($expectedLockVersion !== null && (int) $booklet->lock_version !== $expectedLockVersion) {
            throw new RuntimeException('This booklet changed elsewhere. Reload it before saving.');
        }
    }
}
