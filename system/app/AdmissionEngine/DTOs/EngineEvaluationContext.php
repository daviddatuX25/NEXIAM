<?php

namespace App\AdmissionEngine\DTOs;

final readonly class EngineEvaluationContext
{
    public function __construct(
        public string $source,
        public ?int $benchmarkActivationId,
        public ?int $benchmarkSetId,
        public ?int $benchmarkRevisionId,
        public ?int $benchmarkDraftId,
        public ?string $benchmarkVersion,
        public ?string $schemaDigest,
        public ?string $contentDigest,
        public string $inputDigest,
        public string $requestId,
        public ?int $institutionId = null,
        public ?int $academicCycleId = null,
        public ?int $programId = null,
        public ?int $benchmarkRecoveryId = null,
    ) {}

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'benchmark_activation_id' => $this->benchmarkActivationId,
            'benchmark_set_id' => $this->benchmarkSetId,
            'benchmark_revision_id' => $this->benchmarkRevisionId,
            'benchmark_draft_id' => $this->benchmarkDraftId,
            'benchmark_version' => $this->benchmarkVersion,
            'schema_digest' => $this->schemaDigest,
            'content_digest' => $this->contentDigest,
            'input_digest' => $this->inputDigest,
            'request_id' => $this->requestId,
            'institution_id' => $this->institutionId,
            'academic_cycle_id' => $this->academicCycleId,
            'program_id' => $this->programId,
            'benchmark_recovery_id' => $this->benchmarkRecoveryId,
        ];
    }
}
