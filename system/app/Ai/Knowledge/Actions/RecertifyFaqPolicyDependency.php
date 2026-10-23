<?php

declare(strict_types=1);

namespace App\Ai\Knowledge\Actions;

use App\Ai\Knowledge\KnowledgeSourceAccess;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourcePolicyDependency;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\Communications\PublicPolicyRevision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use RuntimeException;

final class RecertifyFaqPolicyDependency
{
    public function __construct(private readonly KnowledgeSourceAccess $access) {}

    public function handle(
        User $actor,
        KnowledgeSource $source,
        KnowledgeSourceVersion $version,
        PublicPolicyRevision $currentRevision,
    ): KnowledgeSourcePolicyDependency {
        abort_unless((string) $version->knowledge_source_id === (string) $source->getKey(), 404);

        $actorScope = $actor->actorScope();
        if ($actorScope->institutionId !== (int) $source->institution_id
            || ! $this->access->isOfficeHead($actor, $source->owning_office)) {
            throw new AuthorizationException;
        }

        $currentRevision = PublicPolicyRevision::query()
            ->with('policy')
            ->findOrFail($currentRevision->getKey());
        if ((int) $currentRevision->institution_id !== (int) $source->institution_id) {
            throw new RuntimeException('faq_policy_revision_institution_mismatch');
        }

        $policy = $currentRevision->policy;
        if ($policy === null || $policy->status !== 'published'
            || (int) $policy->published_revision_id !== (int) $currentRevision->id) {
            throw new RuntimeException('faq_policy_revision_not_currently_published');
        }

        $dependency = KnowledgeSourcePolicyDependency::query()->firstOrNew([
            'knowledge_source_version_id' => $version->getKey(),
            'public_policy_id' => $policy->getKey(),
        ]);
        if (! $dependency->exists) {
            $dependency->id = (string) Str::uuid();
            $dependency->dependency_type = 'governing';
        }
        $dependency->fill([
            'public_policy_revision_id' => $currentRevision->getKey(),
            'policy_digest_at_link' => hash('sha256', $currentRevision->title."\n".$currentRevision->content),
            'linked_by' => $actor->getKey(),
            'linked_at' => now(),
        ])->save();

        if ($version->status === KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED) {
            $version->forceFill(['status' => KnowledgeSourceVersion::STATUS_APPROVED])->save();
        }

        return $dependency;
    }
}
