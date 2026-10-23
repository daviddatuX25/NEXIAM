<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\BookletAuthorization;
use App\Assessment\Omr\Queries\BookletContractQuery;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrBookletSection;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

use function hash;
use function json_encode;
use function strtoupper;
use function trim;

final class SaveBookletSetKeys
{
    public function __construct(
        private readonly BookletAuthorization $authorization,
        private readonly BookletContractQuery $contract,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function handle(
        OmrQuestionBookletVersion $booklet,
        string $setCode,
        array $items,
        User $actor,
        ?int $expectedLockVersion = null,
        bool $complete = false,
    ): OmrBookletSet {
        $this->authorization->assertManage($actor);
        $setCode = strtoupper(trim($setCode));
        if (! in_array($setCode, OmrBookletSet::SET_CODES, true)) {
            throw new RuntimeException('Set code must be A, B, C, or D.');
        }

        return DB::transaction(function () use ($booklet, $setCode, $items, $actor, $expectedLockVersion, $complete): OmrBookletSet {
            $institution = Institution::query()->lockForUpdate()->findOrFail(Institution::current()->id);
            $booklet = OmrQuestionBookletVersion::query()->lockForUpdate()->findOrFail($booklet->id);
            if (! $booklet->isDraft()) {
                throw new RuntimeException('Only draft Booklets accept Set keys.');
            }
            if ($expectedLockVersion !== null && (int) $booklet->lock_version !== $expectedLockVersion) {
                throw new RuntimeException('This booklet changed elsewhere. Reload it before saving Set keys.');
            }
            if ($setCode !== 'A' && ! $institution->usesMultiSetBooklets()) {
                throw new RuntimeException('Alternate Booklet Sets are not enabled for this institution.');
            }
            if (! in_array($setCode, $booklet->allowedSetCodes(), true)) {
                throw new RuntimeException('This Booklet version does not allow Set '.$setCode.'.');
            }

            $booklet->load(['items.section', 'sets.items']);
            $orderedItems = $booklet->items
                ->sortBy(fn ($item): string => sprintf('%08d:%08d', $item->section?->display_order ?? 0, $item->authored_position))
                ->values();
            if ($orderedItems->isEmpty()) {
                if ($items !== [] && $booklet->authoring_mode === OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY) {
                    $sectionUid = (string) Str::uuid();
                    $section = OmrBookletSection::query()->create([
                        'booklet_version_id' => $booklet->id,
                        'booklet_section_uid' => $sectionUid,
                        'name' => 'General',
                        'display_order' => 1,
                        'expected_count' => count($items),
                        'option_tokens' => ['A', 'B', 'C', 'D'],
                    ]);
                    foreach ($items as $idx => $inp) {
                        $itemUid = (string) Str::uuid();
                        $pos = (int) ($inp['authored_position'] ?? $inp['area_position'] ?? $inp['global_position'] ?? $idx + 1);
                        $booklet->items()->create([
                            'booklet_version_id' => $booklet->id,
                            'booklet_section_id' => $section->id,
                            'booklet_section_uid' => $sectionUid,
                            'booklet_item_uid' => $itemUid,
                            'authored_position' => $pos,
                            'option_tokens' => ['A', 'B', 'C', 'D'],
                            'key_required' => true,
                            'encrypted_content' => [],
                            'content_hash' => hash('sha256', (string) $pos),
                        ]);
                    }
                    $booklet->load(['items.section', 'sets.items']);
                    $orderedItems = $booklet->items
                        ->sortBy(fn ($item): string => sprintf('%08d:%08d', $item->section?->display_order ?? 0, $item->authored_position))
                        ->values();
                } else {
                    throw new RuntimeException('Create intrinsic Booklet items before entering Set keys.');
                }
            }
            $provided = [];
            foreach ($items as $index => $input) {
                $item = null;
                if (is_array($input) && is_string($input['booklet_item_uid'] ?? null)) {
                    $item = $orderedItems->firstWhere('booklet_item_uid', $input['booklet_item_uid']);
                } elseif (is_array($input) && isset($input['booklet_item_id'])) {
                    $item = $orderedItems->firstWhere('id', (int) $input['booklet_item_id']);
                } elseif (is_array($input) && isset($orderedItems[$index])) {
                    $item = $orderedItems[$index];
                }
                if ($item === null || isset($provided[$item->id]) || (int) ($input['global_position'] ?? $index + 1) !== $index + 1) {
                    throw new RuntimeException('Set keys must preserve unique intrinsic item identity and authored order.');
                }
                $provided[$item->id] = true;
                $token = $input['selected_token'] ?? $input['correct_option_code'] ?? null;
                $token = $token === null || $token === '' ? null : (string) $token;
                if ($item->key_required) {
                    if ($token !== null && ! in_array($token, $item->option_tokens ?? [], true)) {
                        throw new RuntimeException('A Set key token is outside that item’s exact literal response domain.');
                    }
                    if ($complete && $token === null) {
                        throw new RuntimeException('Every required Booklet item needs a Set key.');
                    }
                    if ($setCode === 'A'
                        && $booklet->authoring_mode === OmrQuestionBookletVersion::AUTHORING_MODE_STORED_QUESTIONS
                        && $token !== $item->correct_option_code) {
                        throw new RuntimeException('Set A must retain each stored Booklet item key.');
                    }
                } elseif ($token !== null) {
                    throw new RuntimeException('An explicitly keyless practice item cannot receive a Set key.');
                }
                $provided[$item->id] = ['item' => $item, 'token' => $token];
            }
            if (count($provided) !== $orderedItems->count()) {
                throw new RuntimeException('Set key payload must preserve every intrinsic Booklet item occurrence.');
            }
            if ($booklet->content_hash === null || $booklet->content_hash === '') {
                $contentHash = hash('sha256', json_encode([
                    'booklet_id' => (int) $booklet->booklet_id,
                    'version' => (int) $booklet->version,
                    'mode' => $booklet->authoring_mode,
                    'items' => $orderedItems->map(fn ($item): array => [
                        'uid' => $item->booklet_item_uid,
                        'position' => $item->authored_position,
                        'tokens' => $item->option_tokens,
                    ])->all(),
                ], JSON_THROW_ON_ERROR));
                $booklet->content_hash = $contentHash;
                $booklet->save();
            }

            $set = $booklet->sets()->where('set_code', $setCode)->first();
            if ($set?->isFinalized()) {
                throw new RuntimeException('Finalized Sets cannot be edited.');
            }
            $set ??= $booklet->sets()->create([
                'set_code' => $setCode,
                'status' => OmrBookletSet::STATUS_DRAFT,
                'generation_version' => 'booklet-intrinsic-v1',
                'source_content_hash' => $booklet->content_hash,
                'diagnostics_json' => [],
            ]);
            $set->items()->delete();

            $order = [];
            $keys = [];
            foreach ($orderedItems as $index => $item) {
                $token = $provided[$item->id]['token'];
                $position = $index + 1;
                $set->items()->create([
                    'booklet_item_id' => $item->id,
                    'booklet_version_id' => $booklet->id,
                    'booklet_item_uid' => $item->booklet_item_uid,
                    'booklet_section_uid' => $item->booklet_section_uid,
                    'aptitude_area_id' => null,
                    'test_instrument_item_id' => null,
                    'area_position' => $item->authored_position,
                    'global_position' => $position,
                    'correct_option_code' => $token,
                    'content_hash' => $item->content_hash,
                ]);
                $order[] = ['booklet_item_uid' => $item->booklet_item_uid, 'booklet_section_uid' => $item->booklet_section_uid, 'global_position' => $position];
                $keys[] = ['booklet_item_uid' => $item->booklet_item_uid, 'key_required' => (bool) $item->key_required, 'correct_option_code' => $token];
            }

            $set->load('items');
            $this->contract->validateSet($booklet, $set, $complete);
            $set->update([
                'answer_key_hash' => hash('sha256', json_encode($keys, JSON_THROW_ON_ERROR)),
                'order_hash' => hash('sha256', json_encode($order, JSON_THROW_ON_ERROR)),
                'source_content_hash' => $booklet->content_hash,
            ]);
            $booklet->update([
                'answer_key_hash' => hash('sha256', json_encode([
                    'mode' => $booklet->authoring_mode,
                    'allow_multiple_sets' => (bool) $booklet->allow_multiple_sets,
                    'sets' => $booklet->sets()->orderBy('set_code')->get()->mapWithKeys(
                        fn (OmrBookletSet $row): array => [$row->set_code => $row->answer_key_hash],
                    )->all(),
                ], JSON_THROW_ON_ERROR)),
                'lock_version' => (int) $booklet->lock_version + 1,
            ]);
            $this->recordAuditEvent->handle('grading.booklet_set_keys_saved', [
                'booklet_version_id' => $booklet->id,
                'set_code' => $setCode,
                'answer_key_hash' => $set->answer_key_hash,
                'lock_version' => $booklet->lock_version,
            ], (string) $actor->id);

            return $set->fresh(['items.bookletItem']);
        });
    }
}
