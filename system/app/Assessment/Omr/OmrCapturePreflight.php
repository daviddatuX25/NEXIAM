<?php

namespace App\Assessment\Omr;

/**
 * Read-only capture preflight outcome (ordinary capture page, tasks 10.7/10.18).
 *
 * Marker batches: `validated` only when every gate passes (four quadrant
 * matches, registration, frame dimensions); otherwise `rejected` with a typed
 * code, corrective detail, and an orientation hint. Scan (CropPage) batches:
 * `mode=scan`, `outcome=not_applicable` — the normal upload/recognition path
 * stays authoritative, camera preflight never pretends validation passed.
 * Confidence/calibration stays unqualified everywhere; nothing is mutated.
 */
final readonly class OmrCapturePreflight
{
    public const MODE_MARKER = 'marker';
    public const MODE_SCAN = 'scan';

    public const OUTCOME_VALIDATED = 'validated';
    public const OUTCOME_REJECTED = 'rejected';
    public const OUTCOME_NOT_APPLICABLE = 'not_applicable';

    /**
     * @param  array<string, float>|null  $markerMatches  quarter_N => score
     * @param  array<string, mixed>|null  $gates  {marker_count, registration, dimensions, shifts}
     * @param  array{width: int, height: int}|null  $normalizedDimensions
     */
    public function __construct(
        public string $mode,
        public string $outcome,
        public ?string $code = null,
        public ?string $detail = null,
        public ?string $orientationHint = null,
        public ?array $markerMatches = null,
        public ?array $gates = null,
        public ?array $normalizedDimensions = null,
        public bool $submitAllowed = false,
        public ?float $preprocessMs = null,
        public ?float $preflightMs = null,
    ) {
    }

    public static function scan(string $detail): self
    {
        return new self(
            mode: self::MODE_SCAN,
            outcome: self::OUTCOME_NOT_APPLICABLE,
            detail: $detail,
        );
    }

    public static function rejected(string $code, string $detail, ?string $orientationHint = null, ?array $markerMatches = null): self
    {
        return new self(
            mode: self::MODE_MARKER,
            outcome: self::OUTCOME_REJECTED,
            code: $code,
            detail: $detail,
            orientationHint: $orientationHint,
            markerMatches: $markerMatches,
            gates: [
                'marker_count' => 'fail',
                'registration' => 'fail',
                'dimensions' => 'fail',
                'shifts' => 'not_run',
            ],
        );
    }
}
