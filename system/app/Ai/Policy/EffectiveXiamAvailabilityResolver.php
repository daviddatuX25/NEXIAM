<?php

namespace App\Ai\Policy;

use App\Ai\Context\PageCapabilityPack;
use App\Ai\Surface\SurfaceProfile;

/**
 * Deny-dominant effective availability for the foundation cut (no provider).
 *
 * Page packs are relevance lenses, not cages: a mountable SurfaceProfile keeps
 * the dock available without a registered pack. Pack mismatch / disabled flags
 * still hide. Actor capabilities (later Kernel) gate what tools may run.
 */
final class EffectiveXiamAvailabilityResolver
{
    public const AVAILABILITIES = [
        'hidden',
        'inquiry_only',
        'assistant_available',
        'assistant_active',
        'degraded',
        'quota_exhausted',
        'suspended',
    ];

    public function resolve(
        ?SurfaceProfile $profile,
        ?PageCapabilityPack $pack,
        bool $enabled,
        string $configMode,
    ): string {
        if (! $enabled || $configMode === 'disabled') {
            return 'hidden';
        }

        if ($profile === null || ! $profile->mountable) {
            return 'hidden';
        }

        if ($configMode === 'suspended') {
            return 'suspended';
        }

        // Pack belongs to another surface — do not show under this profile.
        if ($pack !== null && $pack->surface !== $profile->key) {
            return 'hidden';
        }

        // Null pack = unregistered route: keep degraded Inquiry chrome.
        // Fail-closed means "do not inherit another page's tools/pack", not "hide Xiam".
        // Foundation cut: no provider → always degraded (not live inquiry_only).
        return 'degraded';
    }
}
