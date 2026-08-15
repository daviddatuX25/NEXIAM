<?php

namespace App\Ai\Knowledge;

/**
 * @phpstan-type Hit array{version_id: string, source_id: string, source_key?: string, source_title?: string, excerpt: string, score: float, audience?: string}
 */
final readonly class KnowledgeRouterResult
{
    /**
     * @param  list<Hit>  $hits
     */
    public function __construct(
        public string $decisionId,
        public string $outcome, // support | no_support
        public array $hits,
        public ?string $reason = null,
    ) {}

    public function hasSupport(): bool
    {
        return $this->outcome === 'support' && $this->hits !== [];
    }
}
