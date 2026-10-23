<?php

declare(strict_types=1);

namespace App\Assessment\Omr;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrBlankInstancePage;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAssignmentSeatAllocation;
use App\Models\Examination\ExamSessionMaterialSet;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Examination\ExamSessionSeat;
use App\Models\Examination\ExamSubmissionRecord;

final readonly class OmrCaptureResolution
{
    /**
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public OmrResolutionOutcome $outcome,
        public ?OmrResolutionFailure $failure = null,
        public ?string $applicantNumber = null,
        public ?AdmissionAttempt $attempt = null,
        public ?ExamAssignment $assignment = null,
        public ?ExamSubmissionRecord $submission = null,
        public ?ExamAssignmentSeatAllocation $seatAllocation = null,
        public ?ExamSessionSeat $sessionSeat = null,
        public ?ExamSessionMaterialSnapshot $snapshot = null,
        public ?ExamSessionMaterialSet $materialSet = null,
        public ?OmrBlankInstance $blankInstance = null,
        public ?OmrBlankInstancePage $blankPage = null,
        public ?string $observedSetCode = null,
        public array $evidence = [],
    ) {}

    public static function resolved(
        ?string $applicantNumber,
        AdmissionAttempt $attempt,
        ExamAssignment $assignment,
        ExamSubmissionRecord $submission,
        ExamAssignmentSeatAllocation $seatAllocation,
        ExamSessionSeat $sessionSeat,
        ExamSessionMaterialSnapshot $snapshot,
        ExamSessionMaterialSet $materialSet,
        ?OmrBlankInstance $blankInstance = null,
        ?OmrBlankInstancePage $blankPage = null,
        ?string $observedSetCode = null,
        array $evidence = [],
    ): self {
        return new self(
            outcome: OmrResolutionOutcome::Resolved,
            applicantNumber: $applicantNumber,
            attempt: $attempt,
            assignment: $assignment,
            submission: $submission,
            seatAllocation: $seatAllocation,
            sessionSeat: $sessionSeat,
            snapshot: $snapshot,
            materialSet: $materialSet,
            blankInstance: $blankInstance,
            blankPage: $blankPage,
            observedSetCode: $observedSetCode,
            evidence: $evidence,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function failed(
        OmrResolutionOutcome $outcome,
        string $code,
        string $message,
        array $context = [],
        ?string $applicantNumber = null,
        ?OmrBlankInstance $blankInstance = null,
        ?OmrBlankInstancePage $blankPage = null,
        ?string $observedSetCode = null,
    ): self {
        $failure = new OmrResolutionFailure($outcome, $code, $message, $context);

        return new self(
            outcome: $outcome,
            failure: $failure,
            applicantNumber: $applicantNumber,
            blankInstance: $blankInstance,
            blankPage: $blankPage,
            observedSetCode: $observedSetCode,
            evidence: $context,
        );
    }

    public function isResolved(): bool
    {
        return $this->outcome === OmrResolutionOutcome::Resolved;
    }

    public function isFailure(): bool
    {
        return ! $this->isResolved();
    }

    public function failureCode(): ?string
    {
        return $this->failure?->code;
    }

    /**
     * Deliberately excludes answer-key content. The hidden key is available
     * only to server-side scoring projection through materialSet.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome->value,
            'failure' => $this->failure?->toArray(),
            'applicant_number' => $this->applicantNumber,
            'attempt_id' => $this->attempt?->id,
            'assignment_id' => $this->assignment?->id,
            'submission_id' => $this->submission?->id,
            'seat_allocation_id' => $this->seatAllocation?->id,
            'session_seat_id' => $this->sessionSeat?->id,
            'snapshot_id' => $this->snapshot?->id,
            'snapshot_revision' => $this->snapshot?->revision,
            'material_set_id' => $this->materialSet?->id,
            'set_code' => $this->materialSet?->set_code,
            'blank_instance_id' => $this->blankInstance?->id,
            'blank_page_id' => $this->blankPage?->id,
            'blank_page_no' => $this->blankPage?->page_no,
            'observed_set_code' => $this->observedSetCode,
            'evidence' => $this->evidence,
        ];
    }
}
