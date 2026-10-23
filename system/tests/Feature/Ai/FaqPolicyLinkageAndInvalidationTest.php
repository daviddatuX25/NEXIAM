<?php

declare(strict_types=1);

use App\Ai\Knowledge\Actions\AttachFaqPolicyDependency;
use App\Ai\Knowledge\Actions\RecertifyFaqPolicyDependency;
use App\Ai\Knowledge\FaqPolicyDependencyValidator;
use App\Communications\Actions\PublishPublicPolicy;
use App\Communications\Actions\SavePublicPolicy;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\Communications\PublicPolicyRevision;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    seedE2Foundation();
});

/** @return array{Institution, User, KnowledgeSource, KnowledgeSourceVersion, PublicPolicyRevision} */
function createFaqPolicyLinkageFixture(): array
{
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $officeHead = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $policy = app(SavePublicPolicy::class)->handle($officeHead, [
        'publisher_key' => 'office:registrar',
        'program_id' => null,
        'request_id' => (string) Str::uuid(),
        'title' => 'Synthetic Registrar policy',
        'content' => 'First approved policy text.',
    ]);
    $policy = app(PublishPublicPolicy::class)->handle($officeHead, $policy, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $policy->revision,
    ]);
    $revision = PublicPolicyRevision::query()->findOrFail((int) $policy->published_revision_id);
    $source = KnowledgeSource::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'faq-policy-linkage-'.Str::uuid(),
        'title' => 'Registrar FAQ',
        'source_class' => KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
        'audience' => KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY,
        'purpose' => 'policy_inquiry',
        'classification' => KnowledgeSource::CLASSIFICATION_PUBLIC_INSTITUTIONAL,
        'institution_id' => $institution->id,
        'owning_office' => 'registrar',
        'category' => 'registrar',
        'owner_user_id' => (string) $officeHead->id,
    ]);
    $version = KnowledgeSourceVersion::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_id' => $source->id,
        'version' => 1,
        'status' => KnowledgeSourceVersion::STATUS_APPROVED,
        'title' => 'Registrar FAQ v1',
        'body_text' => 'Answer grounded in the current Registrar policy.',
        'content_checksum' => hash('sha256', 'Answer grounded in the current Registrar policy.'),
        'created_by' => (string) $officeHead->id,
    ]);

    return [$institution, $officeHead, $source, $version, $revision];
}

test('publishing a successor policy revision invalidates dependent FAQ until office-head recertification', function (): void {
    [, $officeHead, $source, $version, $revisionOne] = createFaqPolicyLinkageFixture();
    app(AttachFaqPolicyDependency::class)->handle($officeHead, $version, $revisionOne);
    $validator = app(FaqPolicyDependencyValidator::class);

    expect($validator->isEligible($version))->toBeTrue()
        ->and($validator->status($version))->toBe('current')
        ->and((int) $version->policyDependencies()->firstOrFail()->public_policy_revision_id)->toBe((int) $revisionOne->getKey());

    $policy = $revisionOne->policy;
    $policy = app(SavePublicPolicy::class)->handle($officeHead, [
        'policy_id' => (int) $policy->getKey(),
        'expected_revision' => (int) $policy->revision,
        'request_id' => (string) Str::uuid(),
        'title' => 'Updated Registrar policy',
        'content' => 'Updated approved policy text.',
    ]);
    $policy = app(PublishPublicPolicy::class)->handle($officeHead, $policy, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $policy->revision,
    ]);
    $revisionTwo = PublicPolicyRevision::query()->findOrFail((int) $policy->published_revision_id);
    $version->refresh();

    expect((int) $revisionTwo->getKey())->not->toBe((int) $revisionOne->getKey())
        ->and($validator->isEligible($version))->toBeFalse()
        ->and($validator->status($version))->toBe('needs_review')
        ->and($version->status)->toBe(KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED);

    app(RecertifyFaqPolicyDependency::class)->handle($officeHead, $source, $version, $revisionTwo);
    $version->refresh();

    expect($validator->isEligible($version))->toBeTrue()
        ->and($validator->status($version))->toBe('current')
        ->and($version->status)->toBe(KnowledgeSourceVersion::STATUS_APPROVED)
        ->and((int) $version->policyDependencies()->firstOrFail()->public_policy_revision_id)->toBe((int) $revisionTwo->getKey());
});

test('attaching a policy revision from a foreign institution is rejected', function (): void {
    [$institution, $officeHead, , $version, $localRevision] = createFaqPolicyLinkageFixture();
    $foreignInstitution = Institution::query()->create([
        'code' => 'FAQ-POLICY-FOREIGN-'.Str::upper(Str::random(8)),
        'name' => 'Foreign FAQ policy institution',
    ]);
    $foreignRevision = PublicPolicyRevision::query()->create([
        'public_policy_id' => $localRevision->public_policy_id,
        'revision' => 999,
        'institution_id' => $foreignInstitution->id,
        'publisher_key' => 'office:registrar',
        'publisher_label' => 'Registrar',
        'title' => 'Foreign policy',
        'content' => 'Foreign policy content.',
        'state' => 'published',
        'actor_id' => $officeHead->id,
        'actor_name' => $officeHead->name,
        'created_at' => now(),
    ]);

    expect((int) $foreignInstitution->getKey())->not->toBe((int) $institution->getKey())
        ->and(fn () => app(AttachFaqPolicyDependency::class)->handle($officeHead, $version, $foreignRevision))
        ->toThrow(ValidationException::class);
});
