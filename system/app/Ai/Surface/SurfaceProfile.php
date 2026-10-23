<?php

namespace App\Ai\Surface;

/**
 * Immutable catalog entry for a Xiam surface profile.
 *
 * @phpstan-type SurfaceProfileArray array{
 *     key: string,
 *     version: int,
 *     default_availability: string,
 *     mountable: bool
 * }
 */
final readonly class SurfaceProfile
{
    public function __construct(
        public SurfaceProfileKey $key,
        public int $version,
        public string $defaultAvailability,
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
            'mountable' => $this->mountable,
        ];
    }
}
