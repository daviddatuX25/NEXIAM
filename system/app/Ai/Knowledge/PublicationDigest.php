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
            'source_key' => $version->source?->key,
            'version' => $version->version,
            'title' => $version->title,
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
