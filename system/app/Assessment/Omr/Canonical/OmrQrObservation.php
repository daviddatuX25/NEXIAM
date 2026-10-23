<?php

namespace App\Assessment\Omr\Canonical;

final readonly class OmrQrObservation
{
    /**
     * @param  list<array{0: float, 1: float}>  $corners
     */
    public function __construct(
        public ?string $payload,
        public array $corners = [],
        public ?float $topEdgeAngle = null,
        public ?float $quarterTurnResidual = null,
        public ?int $correctionQuarterTurn = null,
    ) {
        if (! in_array($correctionQuarterTurn, [null, 0, 90, 180, 270], true)) {
            throw new \InvalidArgumentException('QR correction must be a quarter-turn.');
        }
        if ($corners !== [] && count($corners) !== 4) {
            throw new \InvalidArgumentException('QR geometry must contain four ordered corners.');
        }
    }

    public function found(): bool
    {
        return $this->payload !== null;
    }
}
