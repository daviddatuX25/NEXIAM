<?php

declare(strict_types=1);

namespace App\Ai\Knowledge;

use App\Models\Ai\KnowledgeSourceVersion;

final class FaqPolicyDependencyValidator
{
    public function isEligible(KnowledgeSourceVersion $version): bool
    {
        return $this->status($version) === 'current';
    }

    public function status(KnowledgeSourceVersion $version): string
    {
        $dependencies = $version->policyDependencies()->with('policy')->get();

        foreach ($dependencies as $dependency) {
            $policy = $dependency->policy;
            if ($policy === null || $policy->status !== 'published'
                || (int) $policy->published_revision_id !== (int) $dependency->public_policy_revision_id) {
                return 'needs_review';
            }
        }

        return 'current';
    }
}
