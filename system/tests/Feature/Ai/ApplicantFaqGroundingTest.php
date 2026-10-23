<?php

declare(strict_types=1);

use App\Ai\Context\InvocationContext;
use App\Ai\Knowledge\EvidenceBundle;
use App\Ai\Knowledge\GroundingRouter;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function (): void {
    seedE2Foundation();
});

/** @return array{KnowledgeSource, KnowledgeSourceVersion} */
function createApplicantGroundingFaq(Institution $institution, array $overrides = []): array
{
    $owner = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $source = KnowledgeSource::query()->create(array_merge([
        'id' => (string) Str::uuid(),
        'key' => 'applicant-grounding-'.Str::uuid(),
        'title' => 'Applicant admission FAQ',
        'source_class' => KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
        'audience' => KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY,
        'purpose' => 'policy_inquiry',
        'classification' => KnowledgeSource::CLASSIFICATION_PUBLIC_INSTITUTIONAL,
        'institution_id' => $institution->id,
        'owning_office' => 'registrar',
        'category' => KnowledgeSource::CATEGORY_ADMISSIONS,
        'owner_user_id' => (string) $owner->id,
    ], $overrides['source'] ?? []));
    $answer = $overrides['answer'] ?? 'Submit the required application documents.';
    $version = KnowledgeSourceVersion::query()->create(array_merge([
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
    ], $overrides['version'] ?? []));

    return [$source, $version];
}

function applicantGroundingContext(User $applicant, int $institutionId): InvocationContext
{
    return new InvocationContext(
        actorId: (string) $applicant->getKey(),
        actorType: 'applicant',
        institutionId: $institutionId,
        surfaceProfile: 'applicant.inquiries',
        mode: 'inquiry',
        contextEpoch: 'faq-grounding-test',
        purpose: 'policy_inquiry',
        correlationId: (string) Str::uuid(),
    );
}

function createGroundingApplicant(): User
{
    return User::query()->create([
        'name' => 'FAQ Applicant',
        'email' => 'faq-applicant-'.Str::uuid().'@example.test',
        'password' => bcrypt('password'),
    ]);
}

test('applicant grounding returns active published applicant-inquiry FAQs for their institution', function (): void {
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    [$source] = createApplicantGroundingFaq($institution);
    $applicant = createGroundingApplicant();

    $bundle = app(GroundingRouter::class)->resolve('How do I apply?', applicantGroundingContext($applicant, (int) $institution->id), EvidenceBundle::SOURCE_PUBLIC_FAQ);

    expect($bundle->hasSupport())->toBeTrue()
        ->and($bundle->citations)->toHaveCount(1)
        ->and($bundle->citations[0]['source_id'])->toBe((string) $source->id)
        ->and($bundle->citations[0]['title'])->toBe('How do I apply?');
});

test('applicant grounding excludes staff-only and cross-office staff FAQs', function (): void {
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    createApplicantGroundingFaq($institution, ['source' => [
        'audience' => KnowledgeSource::AUDIENCE_STAFF_ONLY,
        'classification' => KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL,
    ]]);
    createApplicantGroundingFaq($institution, ['source' => [
        'audience' => KnowledgeSource::AUDIENCE_CROSS_OFFICE_STAFF,
        'classification' => KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL,
    ]]);
    $applicant = createGroundingApplicant();

    $bundle = app(GroundingRouter::class)->resolve('How do I apply?', applicantGroundingContext($applicant, (int) $institution->id), EvidenceBundle::SOURCE_PUBLIC_FAQ);

    expect($bundle->citations)->toBeEmpty()
        ->and($bundle->reason)->toBe('no_support');
});

test('foreign-institution applicant receives no FAQ citations', function (): void {
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $foreignInstitution = Institution::query()->where('id', '<>', $institution->id)->first() ?? Institution::query()->create([
        'code' => 'SYN-INST-999',
        'name' => 'Foreign Test University',
        'status' => 'active',
    ]);
    createApplicantGroundingFaq($institution);
    $applicant = createGroundingApplicant();

    $bundle = app(GroundingRouter::class)->resolve('How do I apply?', applicantGroundingContext($applicant, (int) $foreignInstitution->id), EvidenceBundle::SOURCE_PUBLIC_FAQ);

    expect($bundle->citations)->toBeEmpty();
});

test('policy-invalidated needs-review FAQ is excluded from applicant grounding', function (): void {
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    [, $version] = createApplicantGroundingFaq($institution, ['version' => [
        'status' => KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED,
    ]]);
    $applicant = createGroundingApplicant();

    $bundle = app(GroundingRouter::class)->resolve('How do I apply?', applicantGroundingContext($applicant, (int) $institution->id), EvidenceBundle::SOURCE_PUBLIC_FAQ);

    expect($version->fresh()->status)->toBe(KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED)
        ->and($bundle->citations)->toBeEmpty();
});

test('applicant FAQ citations use the canonical verified FAQ locator', function (): void {
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    [$source] = createApplicantGroundingFaq($institution);
    $applicant = createGroundingApplicant();

    $bundle = app(GroundingRouter::class)->resolve('How do I apply?', applicantGroundingContext($applicant, (int) $institution->id), EvidenceBundle::SOURCE_PUBLIC_FAQ);

    expect($bundle->citations)->toHaveCount(1)
        ->and($bundle->citations[0]['locator'])->toBe('/faqs#'.$source->key);
});

test('applicant receives FAQ grounding without staff roles or capabilities', function (): void {
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    createApplicantGroundingFaq($institution);
    $applicant = createGroundingApplicant();

    $bundle = app(GroundingRouter::class)->resolve('How do I apply?', applicantGroundingContext($applicant, (int) $institution->id), EvidenceBundle::SOURCE_PUBLIC_FAQ);

    expect($applicant->staffProfile()->exists())->toBeFalse()
        ->and($bundle->hasSupport())->toBeTrue()
        ->and($bundle->citations)->toHaveCount(1);
});
