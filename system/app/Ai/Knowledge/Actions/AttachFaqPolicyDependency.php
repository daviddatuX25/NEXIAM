<?php

declare(strict_types=1);

namespace App\Ai\Knowledge\Actions;

use App\Ai\Knowledge\KnowledgeSourceAccess;
use App\Models\Ai\KnowledgeSourcePolicyDependency;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\Communications\PublicPolicyRevision;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class AttachFaqPolicyDependency
{
    public function __construct(private readonly KnowledgeSourceAccess $access) {}

    public function handle(
        User $actor,
        KnowledgeSourceVersion $version,
        PublicPolicyRevision $revision,
        string $dependencyType = 'governing',
    ): KnowledgeSourcePolicyDependency {
        $version->loadMissing('source');
        $source = $version->source;
        abort_unless($source !== null, 404);

        $this->access->requireManageSource($actor, $source);

        $currentRevision = PublicPolicyRevision::query()
            ->with('policy')
            ->findOrFail($revision->getKey());
        if ((int) $currentRevision->institution_id !== (int) $source->institution_id) {
            throw ValidationException::withMessages([
                'policy_revision' => 'The policy revision must belong to the FAQ institution.',
            ]);
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
        }
        $dependency->fill([
            'public_policy_revision_id' => $currentRevision->getKey(),
            'dependency_type' => $dependencyType,
            'policy_digest_at_link' => hash('sha256', $currentRevision->title."\n".$currentRevision->content),
            'linked_by' => $actor->getKey(),
            'linked_at' => now(),
        ])->save();

        return $dependency;
    }
}
