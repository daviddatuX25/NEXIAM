<?php

namespace App\AdmissionEngine\DTOs;

final readonly class EngineEvaluationResult
{
    /**
     * @param  array<string, mixed>  $modalityCoverage
     * @param  list<ProgramCompatibilityItem>  $recommendations
     */
    public function __construct(
        public string $providerId,
        public string $slot,
        public string $status,
        public string $engineVersion,
        public ?string $benchmarkVersion,
        public ?string $benchmarkName,
        public string $evaluatedAt,
        public array $modalityCoverage,
        public array $recommendations,
        public string $message,
        public string $inferenceBackend = 'not_reported',
        public ?string $fallbackReason = null,
        public ?EngineEvaluationContext $evaluationContext = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider_id' => $this->providerId,
            'slot' => $this->slot,
            'status' => $this->status,
            'engine_version' => $this->engineVersion,
            'benchmark_version' => $this->benchmarkVersion,
            'benchmark_name' => $this->benchmarkName,
            'evaluated_at' => $this->evaluatedAt,
            'modality_coverage' => $this->modalityCoverage,
            'recommendations' => array_map(static fn (ProgramCompatibilityItem $item): array => $item->toArray(), $this->recommendations),
            'message' => $this->message,
            'inference_backend' => $this->inferenceBackend,
            'fallback_reason' => $this->fallbackReason,
            'evaluation_context' => $this->evaluationContext?->toArray(),
        ];
    }
}
