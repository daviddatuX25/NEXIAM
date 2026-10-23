<?php

namespace App\Assessment\Omr;

use InvalidArgumentException;

use function in_array;

/** One deterministic, normalized quarter-turn image candidate. */
final readonly class OmrQuarterTurnCandidate
{
    public const ORIENTATIONS = [0, 90, 180, 270];

    public function __construct(
        public int $orientation,
        public string $bytes,
        public string $mime,
        public string $sha256,
        public int $width,
        public int $height,
        /** @var array<string, mixed> */
        public array $diagnostics = [],
        public ?string $temporaryPath = null,
    ) {
        if (! in_array($orientation, self::ORIENTATIONS, true)) {
            throw new InvalidArgumentException('OMR orientation must be one of 0, 90, 180, or 270 degrees.');
        }
    }

    public function degrees(): int
    {
        return $this->orientation;
    }

    public function normalizedBytes(): string
    {
        return $this->bytes;
    }

    public function hash(): string
    {
        return $this->sha256;
    }

    /** @return array{width: int, height: int} */
    public function dimensions(): array
    {
        return ['width' => $this->width, 'height' => $this->height];
    }

    public function isOriginal(): bool
    {
        return $this->orientation === 0;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'orientation' => $this->orientation,
            'mime' => $this->mime,
            'sha256' => $this->sha256,
            'width' => $this->width,
            'height' => $this->height,
            'diagnostics' => $this->diagnostics,
            'temporary_path' => $this->temporaryPath,
        ];
    }
}
