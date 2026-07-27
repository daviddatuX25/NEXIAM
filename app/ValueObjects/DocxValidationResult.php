<?php

namespace App\ValueObjects;

readonly class DocxValidationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly array $found,
        public readonly array $missing,
        public readonly array $missingOptional,
        public readonly array $extra,
        public readonly array $checks = [],
    ) {}

    /**
     * @return array{valid: bool, found: string[], missing: string[], missingOptional: string[], extra: string[], checks: array<array{label: string, detail: string, status: string}>}
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'found' => $this->found,
            'missing' => $this->missing,
            'missingOptional' => $this->missingOptional,
            'extra' => $this->extra,
            'checks' => $this->checks,
        ];
    }
}
