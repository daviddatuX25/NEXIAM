<?php

declare(strict_types=1);

namespace App\Communications\Actions;

use App\Communications\HomepageAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Communications\PublicHomepageSection;
use App\Models\Communications\PublicHomepageStory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ToggleHomepageStory
{
    public function __construct(
        private readonly HomepageAuthorizer $authorizer,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(User $actor, PublicHomepageStory $story): PublicHomepageStory
    {
        $institutionId = $this->authorizer->assertCanManage($actor);

        return DB::transaction(function () use ($actor, $story, $institutionId): PublicHomepageStory {
            $section = PublicHomepageSection::query()->where('institution_id', $institutionId)
                ->where('key', 'campus_stories')->lockForUpdate()->firstOrFail();
            $freshStory = PublicHomepageStory::query()->whereKey($story->getKey())
                ->where('institution_id', $institutionId)->where('section_id', $section->id)
                ->lockForUpdate()->firstOrFail();
            $freshStory->is_active = ! $freshStory->is_active;
            $freshStory->updated_by = $actor->id;
            $freshStory->save();
            $section->increment('version');
            $this->audit->handle('communications.homepage_story_toggled', [
                'institution_id' => $institutionId, 'story_id' => $freshStory->id,
                'is_active' => (bool) $freshStory->is_active, 'version' => $section->fresh()->version,
            ], (string) $actor->id);

            return $freshStory;
        });
    }
}
