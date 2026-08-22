<?php

namespace App\Assessment\Omr\Canonical;

interface OmrSourceAdapter
{
    public function kind(): string;

    /** @param array<string, mixed> $context */
    public function preflight(string $sourcePath, array $context = []): OmrSourcePreflightResult;

    /** @param array<string, mixed> $context */
    public function convert(string $sourcePath, array $context = []): OmrSourceIngestionResult;

    public function result(): ?OmrSourceIngestionResult;
}
