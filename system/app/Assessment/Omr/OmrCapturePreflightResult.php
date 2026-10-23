<?php

namespace App\Assessment\Omr;

/**
 * Immutable response from the legacy registration-only worker preflight.
 *
 * This is deliberately separate from {@see OmrCapturePreflight}, whose only
 * responsibility is resource validation for the shared capture kernel.
 */
final readonly class OmrCapturePreflightResult
{
    public const MODE_MARKER = 'marker';

    public const MODE_SCAN = 'scan';

    public const OUTCOME_VALIDATED = 'validated';

    public const OUTCOME_REJECTED = 'rejected';

    public const OUTCOME_NOT_APPLICABLE = 'not_applicable';

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
        public ?string $qrPayload = null,
        public ?float $preprocessMs = null,
        public ?float $preflightMs = null,
        public ?array $rectification = null,
    ) {}

    public static function scan(string $detail): self
    {
        return new self(
            mode: self::MODE_SCAN,
            outcome: self::OUTCOME_NOT_APPLICABLE,
            detail: $detail,
        );
    }

    public static function rejected(string $code, string $detail, ?string $orientationHint = null, ?array $markerMatches = null, ?array $rectification = null): self
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
            rectification: $rectification,
        );
    }
}
