<?php

namespace App\Ai\Connectors;

use InvalidArgumentException;
use RuntimeException;

/**
 * Deployment-owned provider profile catalog.
 *
 * Provider requests receive a profile ID, never a caller-owned URL or header
 * map. Redirect following and DNS resolution remain disabled at the adapter.
 */
final class ConnectorCatalog
{
    /** @var array<string, ConnectorProfile> */
    private array $profiles = [];

    /**
     * @param  array<string, array<string, mixed>>  $profiles
     */
    public function __construct(array $profiles = [])
    {
        foreach ($profiles as $id => $profile) {
            if (! is_string($id) || ! is_array($profile)) {
                throw new InvalidArgumentException('Invalid connector profile configuration.');
            }

            $this->profiles[$id] = new ConnectorProfile(
                id: $id,
                driver: (string) ($profile['driver'] ?? ''),
                baseUrl: (string) ($profile['base_url'] ?? ''),
                allowedHosts: array_values(array_filter(
                    $profile['allowed_hosts'] ?? [],
                    static fn (mixed $host): bool => is_string($host) && $host !== '',
                )),
                region: (string) ($profile['region'] ?? ''),
            );

            $this->assertSafeProfile($this->profiles[$id]);
        }
    }

    public function resolve(string $profileId): ConnectorProfile
    {
        return $this->profiles[$profileId] ?? throw new RuntimeException('connector_not_allowlisted');
    }

    /**
     * @return list<ConnectorProfile>
     */
    public function all(): array
    {
        return array_values($this->profiles);
    }

    private function assertSafeProfile(ConnectorProfile $profile): void
    {
        $url = parse_url($profile->baseUrl);
        if (! is_array($url) || ! isset($url['scheme'], $url['host'])) {
            throw new InvalidArgumentException('connector_base_url_invalid');
        }

        if (! in_array(strtolower((string) $url['scheme']), ['https', 'http'], true)) {
            throw new InvalidArgumentException('connector_scheme_forbidden');
        }

        $host = strtolower((string) $url['host']);
        if (! in_array($host, array_map('strtolower', $profile->allowedHosts), true)) {
            throw new InvalidArgumentException('connector_host_not_allowlisted');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            && (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)) {
            throw new InvalidArgumentException('connector_private_target_forbidden');
        }

        if (str_contains($profile->baseUrl, '#') || str_contains($profile->baseUrl, '?')) {
            throw new InvalidArgumentException('connector_url_must_not_carry_request_data');
        }
    }
}
