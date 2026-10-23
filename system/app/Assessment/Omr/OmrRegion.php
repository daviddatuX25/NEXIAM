<?php

namespace App\Assessment\Omr;

/**
 * A normalized sheet region (0..1 coordinate space). Providers report regions
 * in normalized coordinates so geometry stays resolution-independent.
 */
final readonly class OmrRegion
{
    public function __construct(
        public float $x,
        public float $y,
        public float $w,
        public float $h,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            (float) ($data['x'] ?? 0),
            (float) ($data['y'] ?? 0),
            (float) ($data['w'] ?? 0),
            (float) ($data['h'] ?? 0),
        );
    }

    public function isFiniteAndNormalized(): bool
    {
        return is_finite($this->x)
            && is_finite($this->y)
            && is_finite($this->w)
            && is_finite($this->h)
            && $this->x >= 0.0
            && $this->y >= 0.0
            && $this->w > 0.0
            && $this->h > 0.0
            && $this->x + $this->w <= 1.0000001
            && $this->y + $this->h <= 1.0000001;
    }

    /**
     * @return array{x: float, y: float, w: float, h: float}
     */
    public function toArray(): array
    {
        return ['x' => $this->x, 'y' => $this->y, 'w' => $this->w, 'h' => $this->h];
    }
}
