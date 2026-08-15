<?php

namespace App\Ai\Surface;

/**
 * Code-owned SurfaceProfile catalog + deny-on-ambiguity precedence.
 */
final class SurfaceProfileCatalog
{
    public const VERSION = 1;

    /**
     * @return list<SurfaceProfileKey>
     */
    public static function precedence(): array
    {
        return [
            SurfaceProfileKey::BreakGlass,
            SurfaceProfileKey::Operations,
            SurfaceProfileKey::KnowledgeAdmin,
            SurfaceProfileKey::ProctorSession,
            SurfaceProfileKey::Applicant,
            SurfaceProfileKey::Staff,
        ];
    }

    public function get(SurfaceProfileKey $key): SurfaceProfile
    {
        return match ($key) {
            SurfaceProfileKey::Staff => new SurfaceProfile(
                key: SurfaceProfileKey::Staff,
                version: self::VERSION,
                defaultAvailability: 'degraded',
                allowedModes: ['inquiry', 'assistant'],
                mountable: true,
            ),
            SurfaceProfileKey::Applicant => new SurfaceProfile(
                key: SurfaceProfileKey::Applicant,
                version: self::VERSION,
                defaultAvailability: 'degraded',
                allowedModes: ['inquiry'],
                mountable: true,
            ),
            SurfaceProfileKey::ProctorSession => new SurfaceProfile(
                key: SurfaceProfileKey::ProctorSession,
                version: self::VERSION,
                defaultAvailability: 'hidden',
                allowedModes: ['inquiry'],
                mountable: false,
            ),
            SurfaceProfileKey::KnowledgeAdmin => new SurfaceProfile(
                key: SurfaceProfileKey::KnowledgeAdmin,
                version: self::VERSION,
                defaultAvailability: 'hidden',
                allowedModes: ['inquiry'],
                mountable: false,
            ),
            SurfaceProfileKey::Operations => new SurfaceProfile(
                key: SurfaceProfileKey::Operations,
                version: self::VERSION,
                defaultAvailability: 'hidden',
                allowedModes: ['inquiry'],
                mountable: false,
            ),
            SurfaceProfileKey::BreakGlass => new SurfaceProfile(
                key: SurfaceProfileKey::BreakGlass,
                version: self::VERSION,
                defaultAvailability: 'hidden',
                allowedModes: [],
                mountable: false,
            ),
        };
    }

    /**
     * @return list<SurfaceProfile>
     */
    public function all(): array
    {
        return array_map(
            fn (SurfaceProfileKey $key): SurfaceProfile => $this->get($key),
            SurfaceProfileKey::cases(),
        );
    }
}
