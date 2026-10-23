<?php

declare(strict_types=1);

namespace App\Auth\Support;

use Carbon\CarbonImmutable;

final readonly class StaffAuthenticationProof
{
    public function __construct(
        public int $userId,
        public int $sessionVersion,
        public string $factorGeneration,
        public ?string $recoveryGeneration,
        public string $purpose,
        public string $primaryMethod,
        public string $source,
        public ?string $providerSubject = null,
        public ?string $attemptId = null,
        public ?CarbonImmutable $interactiveAuthenticatedAt = null,
    ) {}
}
