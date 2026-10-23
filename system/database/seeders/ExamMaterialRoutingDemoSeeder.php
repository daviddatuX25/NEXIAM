<?php

namespace Database\Seeders;

use App\Assessment\Actions\Omr\CreateBookletDraft;
use App\Assessment\Actions\Omr\FinalizeBookletVersion;
use App\Assessment\Actions\Omr\GenerateBookletSets;
use App\Assessment\Actions\Omr\SaveAuthoredBookletVersion;
use App\Assessment\Actions\Omr\SaveBookletSetKeys;
use App\Assessment\Actions\Omr\SetBookletSetsMode;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class ExamMaterialRoutingDemoSeeder extends Seeder
{
    public const BOOKLET_CODE = 'DEMO-ROUTED-MATERIALS';

    public function run(): void
    {
        $institution = Institution::current();
        $allowMultipleSets = $institution->usesMultiSetBooklets();
        $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $existing = OmrQuestionBookletVersion::query()
            ->whereHas('booklet', fn ($query) => $query->where('code', self::BOOKLET_CODE))
            ->first();

        if ($existing?->finalized_at === null
            && ! ($existing?->allow_multiple_sets && ! $allowMultipleSets)) {
            $booklet = $existing ?? app(CreateBookletDraft::class)->handle(
                'Routed synthetic assessment',
                OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
                $actor,
                code: self::BOOKLET_CODE,
                isSynthetic: true,
            );
            if ($existing === null) {
                $sectionUid = (string) Str::uuid();
                $items = [];
                foreach (['A', 'B', 'C', 'D'] as $position => $token) {
                    $items[] = [
                        'booklet_item_uid' => (string) Str::uuid(),
                        'booklet_section_uid' => $sectionUid,
                        'section_name' => 'Synthetic reasoning',
                        'section_display_order' => 1,
                        'section_expected_count' => 4,
                        'option_tokens' => ['A', 'B', 'C', 'D'],
                        'key_required' => true,
                        'authored_position' => $position + 1,
                        'correct_option_code' => null,
                        'content' => [],
                    ];
                }
                $booklet = app(SaveAuthoredBookletVersion::class)->handle($booklet, $items, $actor, $booklet->lock_version);
            }
            if ($allowMultipleSets && ! $booklet->allow_multiple_sets) {
                $booklet = app(SetBookletSetsMode::class)->handle($booklet, $actor, true, $booklet->lock_version, false);
            }
            $booklet = app(GenerateBookletSets::class)->handle($booklet, $actor, $booklet->lock_version);
            foreach ($booklet->allowedSetCodes() as $setCode) {
                $set = $booklet->sets()->where('set_code', $setCode)->firstOrFail();
                $keys = $set->items()->orderBy('global_position')->get()->map(fn ($row, int $index): array => [
                    'booklet_item_uid' => $row->booklet_item_uid,
                    'booklet_section_uid' => $row->booklet_section_uid,
                    'global_position' => (int) $row->global_position,
                    'selected_token' => ['A', 'B', 'C', 'D'][$index],
                ])->all();
                $booklet = $booklet->fresh();
                app(SaveBookletSetKeys::class)->handle($booklet, $setCode, $keys, $actor, $booklet->lock_version);
            }
            app(FinalizeBookletVersion::class)->handle($booklet->fresh(), $actor, $booklet->fresh()->lock_version);
        }

        foreach (Room::query()->where('is_active', true)->get() as $room) {
            if ($room->default_seating_arrangement_id !== null) {
                continue;
            }
            $arrangement = SeatingArrangement::query()
                ->where('is_active', true)
                ->where('rows', '<=', $room->capacity)
                ->orderBy('id')
                ->first();
            if ($arrangement !== null) {
                $room->update(['default_seating_arrangement_id' => $arrangement->id]);
            }
        }
    }
}
