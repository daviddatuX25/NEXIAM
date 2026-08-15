<?php

namespace App\Assessment\Omr;

/**
 * Successful provider observations. The response echoes request identity but
 * contains no answer key, correctness, score, or area total. Real engines use
 * null confidence until a separately qualified calibration exists.
 */
final readonly class OmrDetectionSuccess
{
    /**
     * @param  list<OmrIdentityField>  $identityFields
     * @param  list<OmrDetectedItem>  $items
     * @param  list<OmrDetectionIssue>  $issues
     * @param  list<array<string, mixed>>  $fieldBlockShifts
     * @param  array<string, mixed>|null  $alignedImage
     */
    public function __construct(
        public string $engineName,
        public string $engineVersion,
        public ?string $engineCommit,
        public string $inputHash,
        public string $configHash,
        public ?string $applicantNumberProposal,
        public ?float $applicantNumberConfidence,
        public array $identityFields,
        public array $items,
        public array $issues,
        public ?float $sheetConfidence,
        public string $responseEnvelopeVersion = OmrDetectionRequest::ENVELOPE_VERSION,
        public ?string $runId = null,
        public ?string $correlationId = null,
        public ?string $bookletHash = null,
        public ?string $nativeBundleHash = null,
        public ?string $manifestHash = null,
        public ?string $patchIdentity = null,
        public string $calibrationStatus = 'synthetic',
        public array $fieldBlockShifts = [],
        public ?array $alignedImage = null,
        public ?string $imageMime = null,
        public ?int $imageWidth = null,
        public ?int $imageHeight = null,
        public ?string $birthdateProposal = null,
    ) {
    }
}
