<?php

namespace App\Application\Capabilities;

/**
 * Immutable code-owned capability registry entry (AD-2).
 *
 * @phpstan-type InputSchema array{type: string, additionalProperties?: bool, properties?: array<string, mixed>, required?: list<string>}
 */
final readonly class CapabilityDefinition
{
    /**
     * @param  list<string>  $applicationCapabilities  GF-21 capability keys
     * @param  InputSchema  $inputSchema
     * @param  list<string>  $channels
     * @param  list<string>  $ruleSetRefs
     */
    public function __construct(
        public string $key,
        public int $version,
        public CapabilityKind $kind,
        public array $applicationCapabilities,
        public string $authorizationMode,
        public array $inputSchema,
        public string $handlerClass,
        public array $channels = ['http_page', 'nexi_safe'],
        public array $ruleSetRefs = [],
        public ?string $publicationBinding = null,
        public string $description = '',
    ) {
        if ($this->key === '' || $this->version < 1) {
            throw new \InvalidArgumentException('Capability key/version invalid.');
        }

        if (! in_array($this->authorizationMode, ['any', 'all'], true)) {
            throw new \InvalidArgumentException('authorizationMode must be any|all.');
        }

        if ($this->applicationCapabilities === []) {
            throw new \InvalidArgumentException('applicationCapabilities required.');
        }

        if (! in_array($this->kind, [CapabilityKind::Read, CapabilityKind::Propose, CapabilityKind::Command], true)) {
            throw new \InvalidArgumentException('Unsupported capability kind.');
        }
    }

    public function id(): string
    {
        return $this->key.'@'.$this->version;
    }
}
