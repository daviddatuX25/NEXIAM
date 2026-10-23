<?php

namespace App\Ai\Knowledge;

use App\Models\Ai\KnowledgeSourceVersion;

/**
 * Canonical SHA-256 publication digest (AD-25 simplified; RFC8785-shaped payload).
 */
final class PublicationDigest
{
    public function forVersion(KnowledgeSourceVersion $version): string
    {
        $version->loadMissing('fixtures', 'source');
        $payload = [
            'source_id' => $version->knowledge_source_id,
            'source_key' => $version->source?->key,
            'institution_id' => $version->source?->institution_id,
            'owning_office' => $version->source?->owning_office,
            'source_class' => $version->source?->source_class,
            'version_id' => $version->getKey(),
            'version' => $version->version,
            'title' => $version->title,
            'question' => $version->question,
            'answer' => $version->answer,
            'content_checksum' => $version->content_checksum,
            'extraction_digest' => $version->extraction_digest,
            'classification' => $version->source?->classification,
            'audience' => $version->source?->audience,
            'purpose' => $version->source?->purpose,
            'fixtures' => $version->fixtures
                ->sortBy('id')
                ->map(fn ($f) => [
                    'kind' => $f->kind,
                    'query_text' => $f->query_text,
                    'expected_outcome' => $f->expected_outcome,
                    'expected_excerpt' => $f->expected_excerpt,
                ])
                ->values()
                ->all(),
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $json);
    }
}
