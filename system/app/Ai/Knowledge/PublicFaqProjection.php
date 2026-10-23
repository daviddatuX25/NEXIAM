<?php

declare(strict_types=1);

namespace App\Ai\Knowledge;

use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceVersion;

final class PublicFaqProjection
{
    /** @return array{id: string, slug: string, category: string, question: string, answer: string, office_code: string, office_label: string, governing_policy: array{title: string, slug: string, revision_label: string}|null, updated_at: string} */
    public function toItem(KnowledgeSource $source, KnowledgeSourceVersion $version): array
    {
        $dependency = $version->policyDependencies->first();
        $policy = $dependency?->policy;
        $revision = $dependency?->revision;
        $office = (string) $source->owning_office;

        return [
            'id' => (string) $source->id,
            'slug' => (string) $source->key,
            'category' => (string) ($source->category ?? 'admissions'),
            'question' => (string) ($version->question ?? $version->title),
            'answer' => (string) ($version->answer ?? $version->body_text),
            'office_code' => $office,
            'office_label' => match ($office) {
                'admin' => 'Admin Office',
                'registrar' => 'Registrar Office',
                'guidance' => 'Guidance Office',
                default => 'Office',
            },
            'governing_policy' => $policy !== null && $revision !== null ? [
                'title' => (string) $revision->title,
                'slug' => (string) $policy->id,
                'revision_label' => 'Rev. v'.$revision->revision,
            ] : null,
            'updated_at' => $version->updated_at?->toISOString() ?? $version->created_at?->toISOString() ?? '',
        ];
    }
}
