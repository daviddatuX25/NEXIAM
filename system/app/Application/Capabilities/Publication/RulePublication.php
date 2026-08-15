<?php

namespace App\Application\Capabilities\Publication;

/**
 * Minimal RulePublication compatibility fixture (AD-21 seam for reads).
 */
final readonly class RulePublication
{
    /**
     * @param  array<string, string>  $compatibleDigests  binding => digest
     */
    public function __construct(
        public string $version,
        public array $compatibleDigests,
    ) {}

    public function allows(?string $publicationBinding): bool
    {
        if ($publicationBinding === null) {
            return true;
        }

        return array_key_exists($publicationBinding, $this->compatibleDigests);
    }
}
