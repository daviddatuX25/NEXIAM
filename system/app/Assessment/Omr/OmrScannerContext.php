<?php

namespace App\Assessment\Omr;

use App\Models\Assessment\GradingSession;

/**
 * Server-owned scanner routing decision.
 *
 * @param  list<array{id: int, exam_code: string, room_name: string, monitoring_url: string}>  $candidates
 */
final readonly class OmrScannerContext
{
    public function __construct(
        public OmrScannerContextOutcome $outcome,
        public ?GradingSession $gradingSession,
        public array $candidates = [],
        public ?string $resolutionCode = null,
    ) {}

    public static function routeBound(GradingSession $gradingSession): self
    {
        return new self(OmrScannerContextOutcome::RouteBound, $gradingSession);
    }

    public static function autoResolved(GradingSession $gradingSession): self
    {
        return new self(OmrScannerContextOutcome::AutoResolved, $gradingSession);
    }

    /**
     * @param  list<array{id: int, exam_code: string, room_name: string, monitoring_url: string}>  $candidates
     */
    public static function selectionRequired(array $candidates, string $resolutionCode): self
    {
        return new self(
            OmrScannerContextOutcome::SelectionRequired,
            null,
            $candidates,
            $resolutionCode,
        );
    }
}
