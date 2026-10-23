<?php

declare(strict_types=1);

namespace App\Communications\Actions;

use App\Communications\HomepageAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Communications\PublicHomepageMedia;
use App\Models\Communications\PublicHomepageSection;
use App\Models\Communications\PublicHomepageStory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SaveHomepageStory
{
    public function __construct(
        private readonly HomepageAuthorizer $authorizer,
        private readonly RecordAuditEvent $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data, ?PublicHomepageStory $story = null): PublicHomepageStory
    {
        $validated = Validator::make($data, [
            'eyebrow' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:4000'],
            'image_id' => ['nullable', 'integer', 'min:1'],
            'image_alt' => ['nullable', 'string', 'max:255'],
            'cta_label' => ['nullable', 'string', 'max:120'],
            'cta_href' => ['nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/|\/(?!\/))[^\s]*$/i'],
            'align' => ['required', 'in:left,right'],
        ])->validate();

        $institutionId = $this->authorizer->assertCanManage($actor);

        return DB::transaction(function () use ($actor, $validated, $institutionId, $story): PublicHomepageStory {
            $section = PublicHomepageSection::query()->where('institution_id', $institutionId)
                ->where('key', 'campus_stories')->lockForUpdate()->first();
            if ($section === null) {
                $section = PublicHomepageSection::query()->create([
                    'institution_id' => $institutionId,
                    'key' => 'campus_stories',
                    'title' => 'Campus life',
                    'is_active' => true,
                    'version' => 1,
                ]);
            }
            if ($story !== null) {
                $freshStory = PublicHomepageStory::query()->whereKey($story->getKey())
                    ->where('institution_id', $institutionId)->where('section_id', $section->id)
                    ->lockForUpdate()->firstOrFail();
            } else {
                $freshStory = new PublicHomepageStory([
                    'institution_id' => $institutionId,
                    'section_id' => $section->id,
                    'sort_order' => (int) PublicHomepageStory::query()->where('section_id', $section->id)->max('sort_order') + 1,
                    'created_by' => $actor->id,
                    'is_active' => true,
                ]);
            }
            $imageId = $validated['image_id'] ?? null;
            if ($imageId !== null && ! PublicHomepageMedia::query()->whereKey($imageId)
                ->where('institution_id', $institutionId)->exists()) {
                throw ValidationException::withMessages(['image_id' => 'Choose an image staged for this institution.']);
            }
            $freshStory->fill([
                'eyebrow' => $validated['eyebrow'] ?? null,
                'title' => trim($validated['title']),
                'body' => trim($validated['body']),
                'image_id' => $imageId,
                'image_alt' => $validated['image_alt'] ?? null,
                'image_source' => $imageId === null ? $freshStory->image_source : null,
                'cta_label' => $validated['cta_label'] ?? null,
                'cta_href' => $validated['cta_href'] ?? null,
                'align' => $validated['align'],
                'updated_by' => $actor->id,
            ])->save();
            $section->increment('version');
            $this->audit->handle('communications.homepage_story_saved', [
                'institution_id' => $institutionId, 'section_id' => $section->id,
                'story_id' => $freshStory->id, 'version' => $section->fresh()->version,
            ], (string) $actor->id);

            return $freshStory->fresh(['image']);
        });
    }
}
