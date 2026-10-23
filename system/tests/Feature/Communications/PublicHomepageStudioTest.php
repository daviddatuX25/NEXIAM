<?php

use App\Models\Communications\PublicHomepageSection;
use App\Models\Communications\PublicHomepageStory;
use App\Models\Foundation\Institution;
use App\Models\User;
use Database\Seeders\PublicHomepageSeeder;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

test('public homepage seeder provides hero and campus stories rendered on the public home', function (): void {
    seedE2Foundation();
    app(PublicHomepageSeeder::class)->run();

    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $hero = PublicHomepageSection::query()->where('institution_id', $institution->id)->where('key', 'hero')->firstOrFail();
    $storiesSection = PublicHomepageSection::query()->where('institution_id', $institution->id)->where('key', 'campus_stories')->firstOrFail();
    $stories = PublicHomepageStory::query()->where('section_id', $storiesSection->id)->orderBy('sort_order')->get();

    expect($stories)->toHaveCount(2);

    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Home')
        ->where('hero.title', $hero->title)
        ->has('stories', 2)
        ->where('stories.0.title', $stories[0]->title)
        ->where('stories.1.title', $stories[1]->title));
});

test('admin lead can manage public homepage content while other offices are denied', function (): void {
    seedE2Foundation();
    app(PublicHomepageSeeder::class)->run();

    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $hero = PublicHomepageSection::query()->where('institution_id', $institution->id)->where('key', 'hero')->firstOrFail();
    $storiesSection = PublicHomepageSection::query()->where('institution_id', $institution->id)->where('key', 'campus_stories')->firstOrFail();
    $stories = PublicHomepageStory::query()->where('section_id', $storiesSection->id)->orderBy('sort_order')->get();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();

    $this->actingAs($admin)->get('/communications/homepage')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Communications/Homepage/Index')
        ->where('hero.title', $hero->title)
        ->has('stories', 2));

    $this->actingAs($admin)->put('/communications/homepage/hero', [
        'eyebrow' => 'Welcome',
        'title' => 'A refreshed campus welcome',
        'lede' => 'Discover our learning community.',
        'badge_text' => 'Admissions open',
        'expected_version' => $hero->version,
    ])->assertRedirect();

    $this->actingAs($admin)->post('/communications/homepage/stories', [
        'eyebrow' => 'Student life',
        'title' => 'A new campus story',
        'body' => 'Students learn and build community together.',
        'cta_label' => 'Explore programs',
        'cta_href' => '/programs',
        'align' => 'left',
    ])->assertRedirect();
    $newStory = PublicHomepageStory::query()->where('section_id', $storiesSection->id)
        ->where('title', 'A new campus story')->firstOrFail();

    $this->actingAs($admin)->patch("/communications/homepage/stories/{$stories[0]->id}/toggle-active")
        ->assertRedirect();
    $this->actingAs($admin)->put('/communications/homepage/stories/order', [
        'story_ids' => [$newStory->id, $stories[1]->id, $stories[0]->id],
    ])->assertRedirect();

    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Home')
        ->where('hero.title', 'A refreshed campus welcome')
        ->has('stories', 2)
        ->where('stories.0.title', 'A new campus story')
        ->where('stories.1.title', $stories[1]->title)
        ->where('stories.0.body', 'Students learn and build community together.'));

    foreach (['registrar.clerk@nexiam.test', 'guidance.counselor@nexiam.test'] as $email) {
        $actor = User::query()->where('email', $email)->firstOrFail();
        $this->actingAs($actor)->get('/communications/homepage')->assertForbidden();
    }
});

test('admin lead can upload and stage an image for stories and hero', function (): void {
    seedE2Foundation();
    app(PublicHomepageSeeder::class)->run();

    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();

    $file = UploadedFile::fake()->image('story-photo.jpg', 800, 600);
    $response = $this->actingAs($admin)->post('/communications/homepage/images', [
        'image' => $file,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'media' => ['id', 'src', 'alt', 'source', 'width', 'height'],
        ]);

    $mediaId = $response->json('media.id');
    expect($mediaId)->toBeGreaterThan(0);

    $this->assertDatabaseHas('public_homepage_media', [
        'id' => $mediaId,
        'institution_id' => $institution->id,
        'status' => 'ready',
    ]);

    // Create story with staged image
    $this->actingAs($admin)->post('/communications/homepage/stories', [
        'eyebrow' => 'Campus life',
        'title' => 'Story with image',
        'body' => 'Story body text.',
        'image_id' => $mediaId,
        'image_alt' => 'Campus photography',
        'align' => 'right',
    ])->assertRedirect();

    $story = PublicHomepageStory::query()->where('title', 'Story with image')->firstOrFail();
    expect($story->image_id)->toBe($mediaId)
        ->and($story->image_alt)->toBe('Campus photography');

    // Verify private preview image access for staff
    $this->actingAs($admin)->get("/communications/homepage/images/{$mediaId}")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');

    // Staged image for hero
    $heroFile = UploadedFile::fake()->image('hero-photo.png', 1200, 800);
    $heroImgResponse = $this->actingAs($admin)->post('/communications/homepage/images', [
        'image' => $heroFile,
    ]);
    $heroImgResponse->assertStatus(201);
    $heroMediaId = $heroImgResponse->json('media.id');

    $hero = PublicHomepageSection::query()->where('institution_id', $institution->id)->where('key', 'hero')->firstOrFail();
    $this->actingAs($admin)->put('/communications/homepage/hero', [
        'eyebrow' => 'Welcome',
        'title' => 'Updated hero title',
        'lede' => 'Updated hero lede text.',
        'image_id' => $heroMediaId,
        'image_alt' => 'Hero banner graphic',
        'expected_version' => $hero->version,
    ])->assertRedirect();

    expect($hero->fresh()->image_id)->toBe($heroMediaId);
});

test('staging image rejects invalid and unauthorized uploads', function (): void {
    seedE2Foundation();
    app(PublicHomepageSeeder::class)->run();

    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    // Not a file
    $this->actingAs($admin)->postJson('/communications/homepage/images', [
        'image' => 'not-a-file',
    ])->assertStatus(422)->assertJsonValidationErrors(['image']);

    // Text file instead of image
    $textFile = UploadedFile::fake()->create('document.txt', 10, 'text/plain');
    $this->actingAs($admin)->postJson('/communications/homepage/images', [
        'image' => $textFile,
    ])->assertStatus(422)->assertJsonValidationErrors(['image']);

    // Unauthorized user
    $imageFile = UploadedFile::fake()->image('valid.jpg', 400, 300);
    $this->actingAs($clerk)->postJson('/communications/homepage/images', [
        'image' => $imageFile,
    ])->assertForbidden();
});
