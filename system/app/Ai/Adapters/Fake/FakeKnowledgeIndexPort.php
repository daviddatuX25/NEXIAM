<?php

namespace App\Ai\Adapters\Fake;

use App\Ai\Adapters\Local\LocalKnowledgeIndexPort;
use App\Ai\Contracts\KnowledgeIndexPort;

/**
 * Test alias — same local lexical contract (OpenSpec 6). Prefer LocalKnowledgeIndexPort in DI.
 */
final class FakeKnowledgeIndexPort implements KnowledgeIndexPort
{
    public function __construct(private readonly LocalKnowledgeIndexPort $inner = new LocalKnowledgeIndexPort) {}

    public function upsert(array $document): void
    {
        $this->inner->upsert($document);
    }

    public function remove(string $versionId): void
    {
        $this->inner->remove($versionId);
    }

    public function retrieve(string $query, int $limit = 5, array $filters = []): array
    {
        return $this->inner->retrieve($query, $limit, $filters);
    }

    public function rebuildFromActive(): int
    {
        return $this->inner->rebuildFromActive();
    }
}
