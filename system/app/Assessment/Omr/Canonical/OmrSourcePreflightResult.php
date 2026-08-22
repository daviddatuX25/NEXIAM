<?php

namespace App\Assessment\Omr\Canonical;

final readonly class OmrSourcePreflightResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $kind,
        public string $path,
        public string $mime,
        public int $bytes,
        public string $sha256,
        public array $metadata = [],
    ) {
    }
}
