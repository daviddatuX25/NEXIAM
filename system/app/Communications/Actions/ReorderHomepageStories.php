<?php

declare(strict_types=1);

namespace App\Communications\Actions;

use App\Communications\HomepageAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Communications\PublicHomepageSection;
use App\Models\Communications\PublicHomepageStory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ReorderHomepageStories
{
    public function __construct(
        private readonly HomepageAuthorizer $authorizer,
        private readonly RecordAuditEvent $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data): void
    {
        $validated = Validator::make($data, [
            'story_ids' => ['required', 'array', 'min:1'],
            'story_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ])->validate();
        $institutionId = $this->authorizer->assertCanManage($actor);
        DB::transaction(function () use ($actor, $validated, $institutionId): void {
            $section = PublicHomepageSection::query()->where('institution_id', $institutionId)
                ->where('key', 'campus_stories')->lockForUpdate()->firstOrFail();
            $ids = array_map('intval', $validated['story_ids']);
            $allStoryIds = PublicHomepageStory::query()->where('institution_id', $institutionId)
                ->where('section_id', $section->id)->orderBy('id')->lockForUpdate()->pluck('id')
                ->map(static fn ($id): int => (int) $id)->all();
            if (count($allStoryIds) !== count($ids) || array_diff($allStoryIds, $ids) !== []) {
                throw ValidationException::withMessages(['story_ids' => 'The selected stories changed. Reload and try again.']);
            }
            foreach ($ids as $order => $storyId) {
                PublicHomepageStory::query()->whereKey($storyId)->update(['sort_order' => $order + 1, 'updated_by' => $actor->id]);
            }
            $section->increment('version');
            $this->audit->handle('communications.homepage_stories_reordered', [
                'institution_id' => $institutionId, 'story_ids' => $ids, 'version' => $section->fresh()->version,
            ], (string) $actor->id);
        });
    }
}
