<?php

namespace App\Assessment\Omr\Canonical;

use function hash;
use function json_encode;

abstract class AbstractOmrSourceAdapter implements OmrSourceAdapter
{
    protected ?OmrSourceIngestionResult $lastResult = null;

    public function __construct(protected readonly OmrSourcePreflight $preflightService)
    {
    }

    public function preflight(string $sourcePath, array $context = []): OmrSourcePreflightResult
    {
        return $this->preflightService->check($this->kind(), $sourcePath, $context);
    }

    public function result(): ?OmrSourceIngestionResult
    {
        return $this->lastResult;
    }

    /** @param array<string, mixed> $settings */
    protected function settingsHash(array $settings): string
    {
        return hash('sha256', json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
