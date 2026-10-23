<?php

namespace App\Examination\Support;

final readonly class ActionExecutionResult
{
    /**
     * @param  array<string, mixed>  $safeResult
     */
    public function __construct(
        public array $safeResult,
        public bool $replayed,
    ) {}
}
