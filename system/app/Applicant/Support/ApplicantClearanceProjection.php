<?php

namespace App\Applicant\Support;

use App\Admission\Queries\AttemptRequirementChecklistQuery;
use App\Admission\Support\ApplicantScope;
use App\Admission\Support\AttemptProgramFacts;
use App\Consultation\Support\AttemptResultReadiness;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Admission\AdmissionDocumentCorrectionRequest;
use App\Models\Admission\EnrollmentSheetHandoffReceipt;
use App\Models\Admission\EnrollmentStamp;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class ApplicantClearanceProjection
{
    public function __construct(private readonly AttemptRequirementChecklistQuery $checklist) {}

    /**
     * @return array{
     *     requirements: array<string, mixed>,
     *     correction_requests: list<array{recorded_at: string, items: list<array{label: string, applicant_instruction: string}>}>,
     *     status: 'not_ready'|'ready'|'confirmed'|'revoked',
     *     latest_receipt: array{receipt_number: string, status: string, confirmed_at: ?string, revoked_at: ?string}|null,
     *     historical_sheet_issue: array{receipt_number: string, issued_at: ?string, belongs_to_latest_clearance: bool}|null
     * }
     */
    public function for(AdmissionAttempt $attempt, User $user): array
    {
        if (! ApplicantScope::attemptBelongsToUser($attempt, $user)) {
            throw (new ModelNotFoundException)->setModel(AdmissionAttempt::class, [$attempt->getKey()]);
        }

        $requirements = $this->checklist->forApplicant($attempt, $user);
        $correctionRequests = AdmissionDocumentCorrectionRequest::applicantProjectionFor($attempt)['requests'];
        $currentCorrections = [];
        foreach ($correctionRequests as $request) {
            if ($request['status'] !== 'current') {
                continue;
            }
            $items = array_values(array_map(
                static fn (array $item): array => [
                    'label' => $item['label'],
                    'applicant_instruction' => $item['applicant_instruction'],
                ],
                array_filter($request['items'], static fn (array $item): bool => $item['status'] === 'current'),
            ));
            if ($items !== []) {
                $currentCorrections[] = [
                    'recorded_at' => $request['recorded_at'],
                    'items' => $items,
                ];
            }
        }
        $latestReceipt = AdmissionClearanceReceipt::query()
            ->where('admission_attempt_id', $attempt->id)
            ->orderByDesc('id')
            ->first();
        $sheetIssue = EnrollmentSheetHandoffReceipt::query()
            ->where('admission_attempt_id', $attempt->id)
            ->orderByDesc('id')
            ->first();

        $status = match ($latestReceipt?->status) {
            AdmissionClearanceReceipt::STATUS_CONFIRMED => 'confirmed',
            AdmissionClearanceReceipt::STATUS_REVOKED => 'revoked',
            default => $this->isReady($attempt, $requirements) ? 'ready' : 'not_ready',
        };

        return [
            'requirements' => $requirements,
            'correction_requests' => $currentCorrections,
            'status' => $status,
            'latest_receipt' => $latestReceipt === null ? null : [
                'receipt_number' => (string) $latestReceipt->receipt_number,
                'status' => (string) $latestReceipt->status,
                'confirmed_at' => $latestReceipt->confirmed_at?->toIso8601String(),
                'revoked_at' => $latestReceipt->revoked_at?->toIso8601String(),
            ],
            'historical_sheet_issue' => $sheetIssue === null ? null : [
                'receipt_number' => (string) $sheetIssue->receipt_number,
                'issued_at' => $sheetIssue->issued_at?->toIso8601String(),
                'belongs_to_latest_clearance' => $latestReceipt !== null
                    && (int) $sheetIssue->admission_clearance_receipt_id === (int) $latestReceipt->id,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $requirements
     */
    private function isReady(AdmissionAttempt $attempt, array $requirements): bool
    {
        $stamp = EnrollmentStamp::query()
            ->where('admission_attempt_id', $attempt->id)
            ->orderByDesc('id')
            ->first();
        $programId = $attempt->program_id
            ?? ($stamp !== null && $stamp->isActive() ? $stamp->provisional_program_id : null)
            ?? AttemptProgramFacts::currentSelection($attempt)?->program_id;

        return AttemptProgramFacts::isAccepted($attempt)
            && AttemptResultReadiness::isReady($attempt)
            && AttemptProgramFacts::isAdvised($attempt)
            && (bool) ($requirements['all_mandatory_verified'] ?? false)
            && ! AttemptProgramFacts::hasOpenFollowUp($attempt)
            && $programId !== null;
    }
}
