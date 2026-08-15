<?php

namespace App\Application\Capabilities\Evidence;

final readonly class ConstraintEvidence
{
    /**
     * @param  list<string>  $ruleSetRefs
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $outcome,
        public array $ruleSetRefs = [],
        public array $details = [],
    ) {}

    /**
     * @param  list<string>  $ruleSetRefs
     * @param  array<string, mixed>  $details
     */
    public static function pass(array $ruleSetRefs = [], array $details = []): self
    {
        return new self('pass', $ruleSetRefs, $details);
    }

    /**
     * @param  list<string>  $ruleSetRefs
     * @param  array<string, mixed>  $details
     */
    public static function fail(string $reason, array $ruleSetRefs = [], array $details = []): self
    {
        return new self('fail', $ruleSetRefs, array_merge(['reason' => $reason], $details));
    }
}
