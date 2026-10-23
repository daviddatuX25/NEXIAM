<?php

declare(strict_types=1);

namespace App\Ai\Orchestration;

final readonly class TurnPlan
{
    public function __construct(
        public TurnLane $lane,
        public string $activeSkill,
        public array $ambientReadCapabilities = [],
        public bool $requiresPolicyCitations = false,
        public ?string $clarificationPrompt = null,
    ) {}
}
