<?php

namespace App\Assessment\Omr;

/**
 * Read-only layout-verification outcome (builder Test capture, tasks 10.5/10.6).
 *
 * Carries the worker diagnostics: clean aligned raster, `draw_template_layout`
 * overlay raster, per-bubble means, marker match scores, field-block shifts,
 * and the NEXIAM-computed gates. `outcome` is `succeeded` or `failed`; a
 * failure carries a typed code (e.g. `marker_missing`, `marker_mismatch`,
 * `sheet_partial`, `marker_versions_only`) with corrective detail. Never
 * carries answer proposals or confidence.
 */
final readonly class OmrLayoutVerificationResult
{
    public const OUTCOME_SUCCEEDED = 'succeeded';
    public const OUTCOME_FAILED = 'failed';

    /**
     * @param  array<string, mixed>|null  $alignedImage  {mime, width, height, hash}
     * @param  array<string, mixed>|null  $overlayImage  {mime, width, height, hash}
     * @param  array<string, float>|null  $markerMatches  quarter_N => match score
     * @param  list<array<string, mixed>>|null  $fieldBlockShifts
     * @param  array<string, list<array{label: string, option: string, mean: float|null}>>|null  $bubbleMeans
     * @param  list<array<string, mixed>>|null  $itemEvidence
     * @param  array<string, mixed>|null  $gates  {marker_count, registration, dimensions, shifts, unsafe_blocks}
     */
    public function __construct(
        public string $outcome,
        public ?string $code = null,
        public ?string $detail = null,
        public ?string $alignedBytes = null,
        public ?array $alignedImage = null,
        public ?string $overlayBytes = null,
        public ?array $overlayImage = null,
        public ?array $markerMatches = null,
        public ?array $fieldBlockShifts = null,
        public ?array $bubbleMeans = null,
        public ?array $gates = null,
        public ?float $preprocessMs = null,
        public ?float $verificationMs = null,
        public ?array $itemEvidence = null,
    ) {
    }

    public static function failed(string $code, string $detail): self
    {
        return new self(self::OUTCOME_FAILED, $code, $detail);
    }

    public function succeeded(): bool
    {
        return $this->outcome === self::OUTCOME_SUCCEEDED;
    }
}
