<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Communications\PublicHomepageSection;
use App\Models\Communications\PublicHomepageStory;
use App\Models\Foundation\Institution;
use Illuminate\Database\Seeder;

final class PublicHomepageSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->first();
        if ($institution === null) {
            return;
        }

        $hero = (array) config('public-site.hero', []);
        $heroImage = (array) ($hero['image'] ?? []);
        $heroSection = PublicHomepageSection::query()->firstOrCreate(
            ['institution_id' => $institution->id, 'key' => 'hero'],
            [
                'eyebrow' => $hero['eyebrow'] ?? null,
                'title' => $hero['title'] ?? null,
                'body' => $hero['lede'] ?? null,
                'badge_text' => null,
                'image_alt' => $heroImage['alt'] ?? null,
                'seed_image_src' => $heroImage['src'] ?? null,
                'image_source' => $heroImage['source'] ?? null,
                'is_active' => true,
                'version' => 1,
            ],
        );
        $storiesSection = PublicHomepageSection::query()->firstOrCreate(
            ['institution_id' => $institution->id, 'key' => 'campus_stories'],
            ['title' => 'Campus life', 'is_active' => true, 'version' => 1],
        );

        foreach ((array) config('public-site.stories', []) as $index => $content) {
            $image = (array) ($content['image'] ?? []);
            PublicHomepageStory::query()->firstOrCreate(
                ['institution_id' => $institution->id, 'seed_key' => 'campus-story-'.($index + 1)],
                [
                    'section_id' => $storiesSection->id,
                    'eyebrow' => $content['eyebrow'] ?? null,
                    'title' => (string) ($content['title'] ?? ''),
                    'body' => (string) ($content['body'] ?? ''),
                    'image_alt' => $image['alt'] ?? null,
                    'seed_image_src' => $image['src'] ?? null,
                    'image_source' => $image['source'] ?? null,
                    'cta_label' => $content['cta_label'] ?? null,
                    'cta_href' => $content['cta_href'] ?? null,
                    'align' => ($content['align'] ?? 'right') === 'left' ? 'left' : 'right',
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }
}
