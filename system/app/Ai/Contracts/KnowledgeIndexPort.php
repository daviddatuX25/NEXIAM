<?php

namespace App\Ai\Contracts;

/**
 * Derived knowledge index port (AD-13/AD-25). No vendor SDK types.
 */
interface KnowledgeIndexPort
{
    /**
     * @param  array{version_id: string, source_id: string, text: string, meta?: array<string, mixed>}  $document
     */
    public function upsert(array $document): void;

    public function remove(string $versionId): void;

    /**
     * @param  array{audiences?: list<string>, institution_id?: int|null, purpose?: string|null}  $filters
     * @return list<array{version_id: string, source_id: string, source_key?: string, source_title?: string, excerpt: string, score: float, audience?: string}>
     */
    public function retrieve(string $query, int $limit = 5, array $filters = []): array;

    public function rebuildFromActive(): int;
}
