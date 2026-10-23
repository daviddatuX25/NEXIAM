<?php

namespace App\AdmissionEngine\DTOs;

final readonly class EngineManifest
{
    /**
     * @param  list<string>  $requiredArtifacts
     * @param  list<string>  $supportedInputs
     */
    public function __construct(
        public string $identifier,
        public string $slot,
        public string $displayName,
        public string $description,
        public string $engineVersion,
        public string $runtimeStatus,
        public string $cycleReadiness,
        public string $statusBadge,
        public array $requiredArtifacts,
        public array $supportedInputs,
        public bool $isConfigurable = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'slot' => $this->slot,
            'display_name' => $this->displayName,
            'description' => $this->description,
            'engine_version' => $this->engineVersion,
            'runtime_status' => $this->runtimeStatus,
            'cycle_readiness' => $this->cycleReadiness,
            'status_badge' => $this->statusBadge,
            'required_artifacts' => $this->requiredArtifacts,
            'supported_inputs' => $this->supportedInputs,
            'is_configurable' => $this->isConfigurable,
        ];
    }
}
