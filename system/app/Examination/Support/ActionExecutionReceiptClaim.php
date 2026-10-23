<?php

namespace App\Examination\Support;

final readonly class ActionExecutionReceiptClaim
{
    /**
     * @param  array<string, mixed>  $safeResult
     */
    public function __construct(
        public string $executionId,
        public bool $replayed,
        public array $safeResult = [],
    ) {}
}
