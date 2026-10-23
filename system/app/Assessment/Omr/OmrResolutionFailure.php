<?php

declare(strict_types=1);

namespace App\Assessment\Omr;

final readonly class OmrResolutionFailure
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public OmrResolutionOutcome $outcome,
        public string $code,
        public string $message,
        public array $context = [],
    ) {}

    /**
     * @return array{outcome: string, code: string, message: string, context: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome->value,
            'code' => $this->code,
            'message' => $this->message,
            'context' => $this->context,
        ];
    }
}
