<?php

namespace App\Ai\Proposals;

final readonly class SafetyRequirement
{
    public function __construct(
        public string $tier,
        public int $ttlMinutes,
        public ?string $confirmationPhrase,
        public bool $requiresReauth,
    ) {}
}
