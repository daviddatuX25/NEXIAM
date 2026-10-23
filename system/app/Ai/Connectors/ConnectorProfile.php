<?php

namespace App\Ai\Connectors;

use InvalidArgumentException;

final readonly class ConnectorProfile
{
    /**
     * @param  list<string>  $allowedHosts
     */
    public function __construct(
        public string $id,
        public string $driver,
        public string $baseUrl,
        public array $allowedHosts,
        public string $region,
    ) {
        if ($this->id === '' || $this->driver === '' || $this->baseUrl === '' || $this->allowedHosts === []) {
            throw new InvalidArgumentException('Connector profile is incomplete.');
        }
    }

    /**
     * @return array{id: string, driver: string, base_url: string, allowed_hosts: list<string>, region: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'driver' => $this->driver,
            'base_url' => $this->baseUrl,
            'allowed_hosts' => $this->allowedHosts,
            'region' => $this->region,
        ];
    }
}
