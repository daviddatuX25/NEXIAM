<?php

namespace App\Assessment\Omr\Verification;

use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationRun;

final class OmrDigitalVerificationMatch
{
    /**
     * @param  list<array{page_no: int, asset_id: int, sha256: string}>  $proofAssets
     * @param  array<string, mixed>  $resultSummary
     */
    public function __construct(
        public readonly OmrTemplateVerificationRun $run,
        public readonly string $artifactHash,
        public readonly string $proofHash,
        public readonly string $expectedVectorHash,
        public readonly string $cleanVectorHash,
        public readonly string $matrixHash,
        public readonly string $verificationHash,
        public readonly array $resultSummary,
        public readonly array $proofAssets,
    ) {}

    /**
     * Rebuild a canonical verification payload from durable run, summary, and verified proof assets.
     *
     * @return array<string, mixed>
     */
    public function rebuildVerificationPayload(?OmrTemplateAuthoringDraft $draft = null): array
    {
        return [
            'status' => 'passed',
            'artifact_hash' => $this->artifactHash,
            'proof_hash' => $this->proofHash,
            'proof_assets' => $this->proofAssets,
            'matrix' => $this->resultSummary,
            'hashes' => [
                'digital_verification' => $this->verificationHash,
                'matrix_hash' => $this->matrixHash,
                'proof_hash' => $this->proofHash,
                'expected_vector_hash' => $this->expectedVectorHash,
                'clean_vector_hash' => $this->cleanVectorHash,
                'run_id' => (string) $this->run->id,
            ],
            'threshold_status' => 'Unqualified',
            'run_id' => $this->run->id,
        ];
    }
}
