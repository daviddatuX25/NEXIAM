<?php

declare(strict_types=1);

namespace App\Ai\Knowledge;

use App\Models\Ai\KnowledgeSourcePolicyDependency;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\Communications\PublicPolicy;

final class InvalidateDependentFaqsOnPolicyChange
{
    public function handle(PublicPolicy $policy): int
    {
        $dependencies = KnowledgeSourcePolicyDependency::query()
            ->where('public_policy_id', $policy->getKey());

        if ($policy->status !== 'withdrawn') {
            if ($policy->published_revision_id === null) {
                $dependencies->whereNull('public_policy_revision_id');
            } else {
                $dependencies->where('public_policy_revision_id', '!=', $policy->published_revision_id);
            }
        }

        return KnowledgeSourceVersion::query()
            ->whereIn('status', [
                KnowledgeSourceVersion::STATUS_ACTIVE,
                KnowledgeSourceVersion::STATUS_APPROVED,
            ])
            ->whereIn('id', $dependencies->select('knowledge_source_version_id'))
            ->update(['status' => KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED]);
    }
}
