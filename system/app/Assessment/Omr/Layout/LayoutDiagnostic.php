<?php

namespace App\Assessment\Omr\Layout;

/**
 * Typed layout diagnostic with authoring context.
 */
final readonly class LayoutDiagnostic
{
    public function __construct(
        public string $code,
        public string $message,
        public array $context = [],
    ) {
    }

    public function toArray(): array
    {
        return ['code' => $this->code, 'message' => $this->message, 'context' => $this->context];
    }
}
