<?php

namespace App\Examination\Support;

final readonly class PreflightResult
{
    /**
     * @param  list<array{key: string, label: string, status: string, detail: string}>  $checks
     * @param  list<array{key: string, label: string, before: string, after: string}>  $diff
     */
    public function __construct(
        public string $outcome,
        public array $checks,
        public array $diff,
    ) {}
}
