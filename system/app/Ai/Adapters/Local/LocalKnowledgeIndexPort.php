<?php

namespace App\Ai\Adapters\Local;

use App\Ai\Contracts\KnowledgeIndexPort;
use App\Models\Ai\KnowledgeIndexEntry;
use App\Models\Ai\KnowledgeSourceVersion;
use Illuminate\Support\Str;

/**
 * Selected local index contract for OpenSpec 6 — lexical retrieve over active corpus.
 * (pgvector deferred; no Mixedbread authority.)
 */
final class LocalKnowledgeIndexPort implements KnowledgeIndexPort
{
    public function upsert(array $document): void
    {
        $existing = KnowledgeIndexEntry::query()
            ->where('knowledge_source_version_id', $document['version_id'])
            ->first();

        if ($existing !== null) {
            $existing->forceFill([
                'knowledge_source_id' => $document['source_id'],
                'status' => 'active',
                'search_text' => $document['text'],
                'meta' => $document['meta'] ?? [],
            ])->save();

            return;
        }

        KnowledgeIndexEntry::query()->create([
            'id' => (string) Str::uuid(),
            'knowledge_source_version_id' => $document['version_id'],
            'knowledge_source_id' => $document['source_id'],
            'status' => 'active',
            'search_text' => $document['text'],
            'meta' => $document['meta'] ?? [],
        ]);
    }

    public function remove(string $versionId): void
    {
        KnowledgeIndexEntry::query()
            ->where('knowledge_source_version_id', $versionId)
            ->update(['status' => 'removed']);
    }

    public function retrieve(string $query, int $limit = 5, array $filters = []): array
    {
        $needle = mb_strtolower(trim($query));
        if ($needle === '') {
            return [];
        }

        $audiences = $filters['audiences'] ?? null;
        $institutionId = $filters['institution_id'] ?? null;
        $purpose = $filters['purpose'] ?? null;

        $entries = KnowledgeIndexEntry::query()
            ->with(['version.source'])
            ->where('status', 'active')
            ->whereHas('version', function ($q) use ($audiences, $institutionId, $purpose): void {
                $q->where('status', KnowledgeSourceVersion::STATUS_ACTIVE)
                    ->whereHas('source', function ($source) use ($audiences, $institutionId, $purpose): void {
                        if (is_array($audiences) && $audiences !== []) {
                            $source->whereIn('audience', $audiences);
                        }
                        if ($institutionId !== null) {
                            $source->where(function ($inner) use ($institutionId): void {
                                $inner->whereNull('institution_id')
                                    ->orWhere('institution_id', $institutionId);
                            });
                        }
                        if (is_string($purpose) && $purpose !== '') {
                            $source->where('purpose', $purpose);
                        }
                    });
            })
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        $tokens = preg_split('/\s+/', $needle) ?: [];
        $tokens = array_values(array_filter($tokens, fn ($t) => mb_strlen($t) >= 3));
        if ($tokens === []) {
            $tokens = [$needle];
        }

        $hits = [];
        foreach ($entries as $entry) {
            $hay = mb_strtolower($entry->search_text);
            $matched = false;
            foreach ($tokens as $token) {
                if (str_contains($hay, $token)) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                continue;
            }

            $source = $entry->version?->source;
            $hits[] = [
                'version_id' => $entry->knowledge_source_version_id,
                'source_id' => $entry->knowledge_source_id,
                'source_key' => $source?->key,
                'source_title' => $source?->title ?? $entry->version?->title,
                'excerpt' => mb_substr($entry->search_text, 0, 240),
                'score' => 1.0,
                'audience' => $source?->audience,
            ];
            if (count($hits) >= $limit) {
                break;
            }
        }

        return $hits;
    }

    public function rebuildFromActive(): int
    {
        KnowledgeIndexEntry::query()->delete();
        $count = 0;
        KnowledgeSourceVersion::query()
            ->where('status', KnowledgeSourceVersion::STATUS_ACTIVE)
            ->each(function (KnowledgeSourceVersion $version) use (&$count): void {
                $text = (string) ($version->extraction_text ?: $version->body_text);
                $this->upsert([
                    'version_id' => $version->id,
                    'source_id' => $version->knowledge_source_id,
                    'text' => $text,
                    'meta' => ['rebuilt' => true],
                ]);
                $count++;
            });

        return $count;
    }
}
