<?php

namespace App\Application\Capabilities\Projection;

use App\Application\Capabilities\CapabilityDefinition;

/**
 * Channel projection seam (AD-2). For readiness read, http_page and nexi_safe are identical.
 */
final class ChannelProjector
{
    /**
     * @param  array<string, mixed>  $authoritative
     * @return array<string, mixed>
     */
    public function project(CapabilityDefinition $definition, string $channel, array $authoritative): array
    {
        if (! in_array($channel, $definition->channels, true)) {
            throw new \InvalidArgumentException("Unsupported channel [{$channel}] for {$definition->id()}");
        }

        // OpenSpec 2: read projections are identity for http_page and nexi_safe.
        return $authoritative;
    }
}
