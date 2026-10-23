<?php

declare(strict_types=1);

use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceConsensusSignature;
use App\Models\Ai\KnowledgeSourcePolicyDependency;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\Communications\PublicPolicy;
use App\Models\Communications\PublicPolicyRevision;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

beforeEach(function (): void {
    seedE2Foundation();
});

function createKnowledgeFaqPersistenceFixture(): array
{
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $user = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $source = KnowledgeSource::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'faq-persistence-'.Str::uuid(),
        'title' => 'Campus FAQ',
        'source_class' => KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
        'audience' => KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY,
        'purpose' => 'policy_inquiry',
        'classification' => KnowledgeSource::CLASSIFICATION_PUBLIC_INSTITUTIONAL,
        'institution_id' => $institution->id,
        'owning_office' => 'admin',
        'category' => 'campus',
        'owner_user_id' => (string) $user->id,
    ]);
    $version = KnowledgeSourceVersion::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_id' => $source->id,
        'version' => 1,
        'status' => KnowledgeSourceVersion::STATUS_DRAFT,
        'title' => 'Campus FAQ v1',
        'body_text' => 'Campus FAQ answer.',
        'content_checksum' => hash('sha256', 'Campus FAQ answer.'),
        'created_by' => (string) $user->id,
    ]);

    return [$institution, $user, $source, $version];
}

test('knowledge sources persist Admin ownership and campus category', function (): void {
    [, , $source] = createKnowledgeFaqPersistenceFixture();

    expect($source->fresh()->owning_office)->toBe('admin')
        ->and($source->fresh()->category)->toBe('campus');
});

test('consensus signatures are unique per FAQ version and office', function (): void {
    [, $user, , $version] = createKnowledgeFaqPersistenceFixture();
    $attributes = [
        'id' => (string) Str::uuid(),
        'knowledge_source_version_id' => $version->id,
        'office' => 'registrar',
        'signer_id' => $user->id,
        'signed_digest' => hash('sha256', 'reviewed FAQ'),
        'reviewed_version' => $version->version,
        'signed_at' => now(),
    ];

    $signature = KnowledgeSourceConsensusSignature::query()->create($attributes);

    expect($signature->knowledge_source_version_id)->toBe($version->id)
        ->and($signature->office)->toBe('registrar');
    expect(fn () => KnowledgeSourceConsensusSignature::query()->create([
        ...$attributes,
        'id' => (string) Str::uuid(),
    ]))->toThrow(QueryException::class);
});

test('policy dependencies retain exact policy revision and are unique per FAQ version and policy', function (): void {
    [$institution, $user, , $version] = createKnowledgeFaqPersistenceFixture();
    $policy = PublicPolicy::query()->create([
        'institution_id' => $institution->id,
        'publisher_key' => 'registrar',
        'publisher_label' => 'Registrar',
        'title' => 'Campus Policy',
        'content' => 'Campus policy content.',
        'revision' => 1,
        'status' => 'published',
        'created_by' => $user->id,
    ]);
    $revision = PublicPolicyRevision::query()->create([
        'public_policy_id' => $policy->id,
        'revision' => 1,
        'institution_id' => $institution->id,
        'publisher_key' => 'registrar',
        'publisher_label' => 'Registrar',
        'title' => 'Campus Policy',
        'content' => 'Campus policy content.',
        'state' => 'published',
        'actor_id' => $user->id,
        'actor_name' => $user->name,
        'created_at' => now(),
    ]);
    $attributes = [
        'id' => (string) Str::uuid(),
        'knowledge_source_version_id' => $version->id,
        'public_policy_id' => $policy->id,
        'public_policy_revision_id' => $revision->id,
        'dependency_type' => 'governs',
        'policy_digest_at_link' => $revision->contentDigest(),
        'linked_by' => $user->id,
        'linked_at' => now(),
    ];

    $dependency = KnowledgeSourcePolicyDependency::query()->create($attributes);

    expect($dependency->public_policy_id)->toBe($policy->id)
        ->and($dependency->public_policy_revision_id)->toBe($revision->id);
    expect(fn () => KnowledgeSourcePolicyDependency::query()->create([
        ...$attributes,
        'id' => (string) Str::uuid(),
    ]))->toThrow(QueryException::class);
});

test('FAQ versions navigate signatures, policy dependencies, and their source versions', function (): void {
    [$institution, $user, $source, $version] = createKnowledgeFaqPersistenceFixture();
    $policy = PublicPolicy::query()->create([
        'institution_id' => $institution->id,
        'publisher_key' => 'registrar',
        'publisher_label' => 'Registrar',
        'title' => 'Campus Policy',
        'content' => 'Campus policy content.',
        'revision' => 1,
        'status' => 'published',
        'created_by' => $user->id,
    ]);
    $revision = PublicPolicyRevision::query()->create([
        'public_policy_id' => $policy->id,
        'revision' => 1,
        'institution_id' => $institution->id,
        'publisher_key' => 'registrar',
        'publisher_label' => 'Registrar',
        'title' => 'Campus Policy',
        'content' => 'Campus policy content.',
        'state' => 'published',
        'actor_id' => $user->id,
        'actor_name' => $user->name,
        'created_at' => now(),
    ]);
    $signature = KnowledgeSourceConsensusSignature::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_version_id' => $version->id,
        'office' => 'registrar',
        'signer_id' => $user->id,
        'signed_digest' => hash('sha256', 'reviewed FAQ'),
        'reviewed_version' => $version->version,
        'signed_at' => now(),
    ]);
    $dependency = KnowledgeSourcePolicyDependency::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_version_id' => $version->id,
        'public_policy_id' => $policy->id,
        'public_policy_revision_id' => $revision->id,
        'dependency_type' => 'governs',
        'policy_digest_at_link' => $revision->contentDigest(),
        'linked_by' => $user->id,
        'linked_at' => now(),
    ]);

    expect($version->fresh()->consensusSignatures->modelKeys())->toContain($signature->getKey())
        ->and($version->fresh()->policyDependencies->modelKeys())->toContain($dependency->getKey())
        ->and($source->fresh()->versions->modelKeys())->toContain($version->getKey());
});
