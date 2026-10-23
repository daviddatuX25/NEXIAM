<?php

namespace App\Consultation\Support;

final readonly class PreflightResult
{
    /**
     * @param  list<array{key: string, label: string, status: string, detail?: string, note?: string}>  $checks
     * @param  list<array{key: string, label: string, before: mixed, after: mixed}>  $diff
     */
    public function __construct(
        public string $outcome,
        public array $checks,
        public array $diff,
    ) {}
}
