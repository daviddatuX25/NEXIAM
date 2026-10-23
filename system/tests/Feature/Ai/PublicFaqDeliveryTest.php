<?php

declare(strict_types=1);

use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function (): void {
    seedE2Foundation();
});

/** @return array{KnowledgeSource, KnowledgeSourceVersion} */
function createPublicFaqDeliveryFixture(array $overrides = []): array
{
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $owner = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $attributes = array_merge([
        'id' => (string) Str::uuid(),
        'key' => 'public-faq-'.Str::uuid(),
        'title' => 'Applicant admission FAQ',
        'source_class' => KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
        'audience' => KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY,
        'purpose' => 'policy_inquiry',
        'classification' => KnowledgeSource::CLASSIFICATION_PUBLIC_INSTITUTIONAL,
        'institution_id' => $institution->id,
        'owning_office' => 'registrar',
        'category' => KnowledgeSource::CATEGORY_ADMISSIONS,
        'owner_user_id' => (string) $owner->id,
    ], $overrides);
    $source = KnowledgeSource::query()->create($attributes);
    $answer = 'Submit the required application documents.';
    $version = KnowledgeSourceVersion::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_id' => $source->id,
        'version' => 1,
        'status' => KnowledgeSourceVersion::STATUS_ACTIVE,
        'title' => $source->title,
        'question' => 'How do I apply?',
        'answer' => $answer,
        'body_text' => $answer,
        'content_checksum' => hash('sha256', $answer),
        'created_by' => (string) $owner->id,
        'approved_by' => (string) $owner->id,
        'approved_at' => now(),
        'published_at' => now(),
    ]);

    return [$source, $version];
}

test('guest can view the public FAQ page', function (): void {
    createPublicFaqDeliveryFixture();

    $this->get('/faqs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Faqs')
            ->has('faqs')
            ->where('faqs.0.question', 'How do I apply?'));
});

test('public FAQ delivery includes only active applicant-inquiry content', function (): void {
    createPublicFaqDeliveryFixture();
    createPublicFaqDeliveryFixture(['audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY]);
    [$draft, $draftVersion] = createPublicFaqDeliveryFixture();
    $draftVersion->update(['status' => KnowledgeSourceVersion::STATUS_DRAFT]);

    $response = $this->get('/faqs')->assertOk();
    $faqs = $response->viewData('page')['props']['faqs'] ?? [];

    expect(collect($faqs)->pluck('question')->all())->toBe(['How do I apply?']);
});

test('public FAQ delivery excludes policy-invalidated FAQs', function (): void {
    createPublicFaqDeliveryFixture();
    [$source, $version] = createPublicFaqDeliveryFixture();
    $version->update(['status' => KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED]);

    $response = $this->get('/faqs')->assertOk();
    $faqs = $response->viewData('page')['props']['faqs'] ?? [];

    expect(collect($faqs)->pluck('id')->all())->toHaveCount(1)
        ->and(collect($faqs)->pluck('id')->all())->not->toContain($source->id);
});

test('public FAQs can be filtered by category and search', function (): void {
    createPublicFaqDeliveryFixture();
    [$otherCategory] = createPublicFaqDeliveryFixture(['category' => KnowledgeSource::CATEGORY_CAMPUS]);

    $this->get('/faqs?category=admissions&search=apply')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Faqs')
            ->has('faqs', 1)
            ->where('faqs.0.category', 'admissions')
            ->where('faqs.0.question', 'How do I apply?'));

    expect($otherCategory->category)->toBe(KnowledgeSource::CATEGORY_CAMPUS);
});

test('authenticated applicant receives FAQs on admission support', function (): void {
    createPublicFaqDeliveryFixture();
    $applicant = User::query()->create([
        'name' => 'FAQ Applicant',
        'email' => 'faq-applicant@example.test',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($applicant)
        ->get('/applicant/admission-support')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('faqs')
            ->where('faqs.0.question', 'How do I apply?'));
});
