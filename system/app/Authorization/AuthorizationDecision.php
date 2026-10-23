<?php

namespace App\Authorization;

final readonly class AuthorizationDecision
{
    public function __construct(
        public bool $allowed,
        public string $reason,
        public ?string $capability = null,
    ) {}

    public static function allow(string $capability): self
    {
        return new self(true, 'allowed', $capability);
    }

    public static function deny(string $reason, ?string $capability = null): self
    {
        return new self(false, $reason, $capability);
    }
}
