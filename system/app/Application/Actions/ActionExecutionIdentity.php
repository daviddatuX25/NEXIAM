<?php

declare(strict_types=1);

namespace App\Application\Actions;

final readonly class ActionExecutionIdentity
{
    public function __construct(
        public string $executionId,
        public string $capabilityId,
        public string $canonicalDigest,
    ) {}
}
