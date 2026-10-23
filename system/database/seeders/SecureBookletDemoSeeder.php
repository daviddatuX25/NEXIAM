<?php

namespace Database\Seeders;

use App\Assessment\Actions\Omr\CreateBookletDraft;
use App\Assessment\Actions\Omr\FinalizeBookletVersion;
use App\Assessment\Actions\Omr\GenerateBookletSets;
use App\Assessment\Actions\Omr\SaveAuthoredBookletVersion;
use App\Assessment\Actions\Omr\SaveBookletSetKeys;
use App\Assessment\Actions\Omr\SetBookletSetsMode;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Foundation\Institution;
use App\Models\Foundation\SeatingArrangement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class SecureBookletDemoSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

        SeatingArrangement::query()->updateOrCreate(
            ['normalized_name' => 'demo uniform a-only layout'],
            [
                'name' => 'Demo uniform A-only layout',
                'rows' => 5,
                'columns' => 6,
                'booklet_pattern' => SeatingArrangement::PATTERN_UNIFORM,
                'availability_mode' => SeatingArrangement::AVAILABILITY_ALL_ROOMS,
                'is_active' => true,
                'lock_version' => 1,
            ],
        )->rooms()->detach();

        $keys = ['A', 'B', 'C', 'D', 'A', 'B'];
        $this->seedBooklet('DEMO-KEYS-SINGLE-A-PARTIAL', 'Synthetic single Set A — partial keys', $actor, [
            ...array_slice($keys, 0, 5),
            null,
        ], false, false);
        $this->seedBooklet('DEMO-KEYS-SINGLE-A-COMPLETE', 'Synthetic single Set A — complete keys', $actor, $keys, false, true);

        if (Institution::current()->usesMultiSetBooklets()) {
            $this->seedBooklet('DEMO-KEYS-MULTI-SET', 'Synthetic multi-set A–D keys', $actor, $keys, true, false);
        }
    }

    /**
     * @param  list<string|null>  $keys
     */
    private function seedBooklet(
        string $code,
        string $name,
        User $actor,
        array $keys,
        bool $multipleSets,
        bool $finalized,
    ): void {
        if (OmrQuestionBooklet::query()->where('code', $code)->exists()) {
            return;
        }

        $sectionUid = (string) Str::uuid();
        $items = [];
        foreach ($keys as $index => $key) {
            $items[] = [
                'booklet_item_uid' => (string) Str::uuid(),
                'booklet_section_uid' => $sectionUid,
                'section_name' => 'Synthetic reasoning',
                'section_display_order' => 1,
                'section_expected_count' => count($keys),
                'option_tokens' => ['A', 'B', 'C', 'D'],
                'key_required' => true,
                'authored_position' => $index + 1,
                'correct_option_code' => null,
                'content' => [],
            ];
        }

        $booklet = app(CreateBookletDraft::class)->handle(
            $name,
            OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
            $actor,
            code: $code,
            isSynthetic: true,
        );
        $booklet = app(SaveAuthoredBookletVersion::class)->handle($booklet, $items, $actor, $booklet->lock_version);
        if ($multipleSets) {
            $booklet = app(SetBookletSetsMode::class)->handle($booklet, $actor, true, $booklet->lock_version, false);
        }
        $booklet = app(GenerateBookletSets::class)->handle($booklet, $actor, $booklet->lock_version);

        foreach ($booklet->allowedSetCodes() as $setCode) {
            $set = $booklet->sets()->where('set_code', $setCode)->firstOrFail();
            $rows = $set->items()->orderBy('global_position')->get()->map(fn ($row, int $index): array => [
                'booklet_item_uid' => $row->booklet_item_uid,
                'booklet_section_uid' => $row->booklet_section_uid,
                'global_position' => (int) $row->global_position,
                'selected_token' => $keys[$index] ?? null,
            ])->all();
            $booklet = $booklet->fresh();
            app(SaveBookletSetKeys::class)->handle($booklet, $setCode, $rows, $actor, $booklet->lock_version);
            $booklet->refresh();
        }

        if ($finalized) {
            app(FinalizeBookletVersion::class)->handle($booklet->fresh(), $actor, $booklet->lock_version);
        }
    }
}
