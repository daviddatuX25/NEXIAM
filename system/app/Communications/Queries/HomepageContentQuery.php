<?php

declare(strict_types=1);

namespace App\Communications\Queries;

use App\Models\Communications\PublicHomepageMedia;
use App\Models\Communications\PublicHomepageSection;
use App\Models\Communications\PublicHomepageStory;
use Illuminate\Support\Facades\Schema;

final class HomepageContentQuery
{
    /** @return array{hero: array<string, mixed>, stories: list<array<string, mixed>>} */
    public function publicContent(int $institutionId): array
    {
        if (! Schema::hasTable('public_homepage_sections') || ! Schema::hasTable('public_homepage_stories')) {
            return $this->configuredContent();
        }

        if (! PublicHomepageSection::query()->where('institution_id', $institutionId)->exists()) {
            return $this->configuredContent();
        }

        $hero = PublicHomepageSection::query()
            ->with('image')
            ->where('institution_id', $institutionId)
            ->where('key', 'hero')
            ->where('is_active', true)
            ->first();
        $storiesSection = PublicHomepageSection::query()
            ->where('institution_id', $institutionId)
            ->where('key', 'campus_stories')
            ->where('is_active', true)
            ->first();

        $stories = $storiesSection === null ? [] : PublicHomepageStory::query()
            ->with('image')
            ->where('institution_id', $institutionId)
            ->where('section_id', $storiesSection->id)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (PublicHomepageStory $story): array => $this->story($story))
            ->all();

        return [
            'hero' => $hero === null ? [
                'eyebrow' => null, 'title' => '', 'lede' => '', 'badge_text' => null,
                'image' => ['src' => null, 'alt' => null, 'source' => null],
            ] : $this->hero($hero),
            'stories' => $stories,
        ];
    }

    /** @return array<string, mixed> */
    public function studio(int $institutionId): array
    {
        $sections = PublicHomepageSection::query()->with('image')
            ->where('institution_id', $institutionId)->get()->keyBy('key');
        $stories = PublicHomepageStory::query()->with('image')
            ->where('institution_id', $institutionId)->whereHas('section', fn ($query) => $query->where('key', 'campus_stories'))
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (PublicHomepageStory $story): array => [
                ...$this->story($story, true),
                'is_active' => (bool) $story->is_active,
                'seed_key' => $story->seed_key,
            ])->all();

        $hero = $sections->get('hero');
        $storiesSection = $sections->get('campus_stories');

        return [
            'hero' => $hero === null ? $this->configuredContent()['hero'] : [
                ...$this->hero($hero, true), 'id' => (int) $hero->id, 'version' => (int) $hero->version,
                'is_active' => (bool) $hero->is_active,
            ],
            'stories' => $stories,
            'stories_section' => $storiesSection === null ? ['version' => 1, 'is_active' => true] : [
                'id' => (int) $storiesSection->id, 'version' => (int) $storiesSection->version,
                'is_active' => (bool) $storiesSection->is_active,
            ],
        ];
    }

    /** @return array{hero: array<string, mixed>, stories: list<array<string, mixed>>} */
    private function configuredContent(): array
    {
        $hero = (array) config('public-site.hero', []);
        $hero['badge_text'] = null;
        $stories = array_map(function (array $story): array {
            return ['id' => null, ...$story];
        }, (array) config('public-site.stories', []));

        return ['hero' => $hero, 'stories' => $stories];
    }

    /** @return array<string, mixed> */
    private function hero(PublicHomepageSection $section, bool $private = false): array
    {
        return [
            'id' => (int) $section->id,
            'image_id' => $section->image_id === null ? null : (int) $section->image_id,
            'eyebrow' => $section->eyebrow,
            'title' => $section->title,
            'lede' => $section->body,
            'badge_text' => $section->badge_text,
            'image' => $this->imageData($section->image, $section->seed_image_src, $section->image_alt, $section->image_source, $private),
        ];
    }

    /** @return array<string, mixed> */
    private function story(PublicHomepageStory $story, bool $private = false): array
    {
        return [
            'id' => (int) $story->id,
            'image_id' => $story->image_id === null ? null : (int) $story->image_id,
            'eyebrow' => $story->eyebrow,
            'title' => $story->title,
            'body' => $story->body,
            'image' => $this->imageData($story->image, $story->seed_image_src, $story->image_alt, $story->image_source, $private),
            'cta_label' => $story->cta_label,
            'cta_href' => $story->cta_href,
            'align' => $story->align,
        ];
    }

    /** @return array{src: string|null, alt: string|null, source: string|null} */
    private function imageData(?PublicHomepageMedia $media, ?string $seedSrc, ?string $alt, ?string $source, bool $private = false): array
    {
        return [
            'src' => $media === null ? $seedSrc : ($private ? '/communications/homepage/images/'.$media->id : '/homepage/images/'.$media->id),
            'alt' => $alt,
            'source' => $source,
        ];
    }
}
