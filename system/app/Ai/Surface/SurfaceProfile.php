<?php

namespace App\Ai\Surface;

/**
 * Immutable catalog entry for a Nexi surface profile.
 *
 * @phpstan-type SurfaceProfileArray array{
 *     key: string,
 *     version: int,
 *     default_availability: string,
 *     allowed_modes: list<string>,
 *     mountable: bool
 * }
 */
final readonly class SurfaceProfile
{
    /**
     * @param  list<string>  $allowedModes
     */
    public function __construct(
        public SurfaceProfileKey $key,
        public int $version,
        public string $defaultAvailability,
        public array $allowedModes,
        public bool $mountable,
    ) {}

    /**
     * @return SurfaceProfileArray
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key->value,
            'version' => $this->version,
            'default_availability' => $this->defaultAvailability,
            'allowed_modes' => $this->allowedModes,
            'mountable' => $this->mountable,
        ];
    }
}
