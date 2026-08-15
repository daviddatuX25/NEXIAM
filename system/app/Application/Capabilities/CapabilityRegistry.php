<?php

namespace App\Application\Capabilities;

use RuntimeException;

final class CapabilityRegistry
{
    /** @var array<string, CapabilityDefinition> */
    private array $byId = [];

    /**
     * @param  list<CapabilityDefinition>  $definitions
     */
    public function __construct(array $definitions)
    {
        foreach ($definitions as $definition) {
            $id = $definition->id();
            if (isset($this->byId[$id])) {
                throw new RuntimeException("Duplicate capability registration: {$id}");
            }
            $this->byId[$id] = $definition;
        }
    }

    public function get(string $key, int $version): ?CapabilityDefinition
    {
        return $this->byId[$key.'@'.$version] ?? null;
    }

    public function getById(string $id): ?CapabilityDefinition
    {
        return $this->byId[$id] ?? null;
    }

    /**
     * @return list<CapabilityDefinition>
     */
    public function all(): array
    {
        return array_values($this->byId);
    }
}
