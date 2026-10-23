<?php

namespace App\Admission\Actions;

use App\Admission\Support\AttemptRequirementChecklistBoundary;
use App\Admission\Support\CommandIdempotency;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptDocumentVerification;
use App\Models\Admission\AdmissionAttemptRequirementItem;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class VerifyAdmissionAttemptDocument
{
    public function __construct(
        private readonly AttemptRequirementChecklistBoundary $boundary,
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly EnsureAttemptRequirementChecklist $ensureChecklist,
    ) {}

    public function handle(
        AdmissionAttempt $attempt,
        ?int $requirementSetId,
        ?int $requirementItemId,
        ?int $expectedLatestVerificationId,
        string $status,
        User $actor,
        int $expectedVersion,
        ?string $applicantInstruction,
        ?string $internalRemarks,
        string $idempotencyKey,
        ?int $intakeRequirementId = null,
    ): AdmissionAttemptDocumentVerification {
        $validStatuses = [
            AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
            AdmissionAttemptDocumentVerification::STATUS_DEFICIENT,
            AdmissionAttemptDocumentVerification::STATUS_MISSING,
        ];

        if (! in_array($status, $validStatuses, true)) {
            throw new InvalidArgumentException('Invalid verification status.');
        }

        $applicantInstruction = $this->boundary->normalizeText($applicantInstruction);
        $internalRemarks = $this->boundary->normalizeText($internalRemarks);
        if ($status === AdmissionAttemptDocumentVerification::STATUS_VERIFIED) {
            $applicantInstruction = null;
        }
        if (mb_strlen((string) $applicantInstruction) > 2000) {
            throw ValidationException::withMessages(['applicant_instruction' => 'The applicant instruction may not exceed 2,000 characters.']);
        }

        if (mb_strlen((string) $internalRemarks) > 2000) {
            throw ValidationException::withMessages(['internal_remarks' => 'Internal remarks may not exceed 2,000 characters.']);
        }

        if ($status === AdmissionAttemptDocumentVerification::STATUS_DEFICIENT && $applicantInstruction === null) {
            throw ValidationException::withMessages(['applicant_instruction' => 'Provide an actionable instruction for the applicant before recording a deficiency.']);
        }

        if (trim($idempotencyKey) === '' || mb_strlen($idempotencyKey) > 128) {
            throw ValidationException::withMessages(['idempotency_key' => 'A valid idempotency key is required.']);
        }

        return DB::transaction(function () use (
            $attempt,
            $requirementSetId,
            $requirementItemId,
            $intakeRequirementId,
            $expectedLatestVerificationId,
            $status,
            $actor,
            $expectedVersion,
            $applicantInstruction,
            $internalRemarks,
            $idempotencyKey,
        ): AdmissionAttemptDocumentVerification {
            $lockedAttempt = $this->boundary->lockAttempt((int) $attempt->getKey());
            $freshActor = $this->boundary->authorizeFresh($actor, $lockedAttempt, CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY);
            if ($requirementSetId === null || $requirementItemId === null) {
                if ((int) $lockedAttempt->version !== $expectedVersion) {
                    throw new RuntimeException('The admission attempt changed. Refresh and review the checklist before saving.');
                }
                $this->boundary->assertMutable($lockedAttempt);
                $ensuredSet = $this->ensureChecklist->handle($lockedAttempt, $freshActor);
                if ($ensuredSet === null) {
                    throw new RuntimeException('No active document requirements apply to this applicant track.');
                }
                $requirementSetId = (int) $ensuredSet->getKey();
                $item = $ensuredSet->items->first(fn (AdmissionAttemptRequirementItem $candidate): bool => $intakeRequirementId !== null
                        ? (int) $candidate->intake_requirement_id === $intakeRequirementId
                        : (int) $candidate->getKey() === $requirementItemId
                );
                if ($item === null) {
                    throw (new ModelNotFoundException)->setModel(
                        AdmissionAttemptRequirementItem::class,
                        [$intakeRequirementId ?? $requirementItemId],
                    );
                }
                $requirementItemId = (int) $item->getKey();
            }

            $item = $this->boundary->ownedItem($lockedAttempt, $requirementSetId, $requirementItemId);
            $operation = $this->idempotency->claim(
                $freshActor,
                'admission.documents.verify',
                'attempt:'.$lockedAttempt->getKey(),
                [
                    'attempt_id' => (int) $lockedAttempt->getKey(),
                    'expected_version' => $expectedVersion,
                    'requirement_set_id' => $requirementSetId,
                    'requirement_item_id' => $requirementItemId,
                    'expected_latest_verification_id' => $expectedLatestVerificationId,
                    'status' => $status,
                    'applicant_instruction' => $applicantInstruction,
                    'internal_remarks' => $internalRemarks,
                ],
                $idempotencyKey,
            );

            $receiptId = $operation->payload['receipt_verification_id'] ?? null;
            if ($receiptId !== null) {
                return AdmissionAttemptDocumentVerification::query()
                    ->whereKey($receiptId)
                    ->where('admission_attempt_id', $lockedAttempt->getKey())
                    ->where('requirement_item_id', $item->getKey())
                    ->whereHas('requirementItem', fn ($query) => $query->where('requirement_set_id', $requirementSetId))
                    ->firstOrFail();
            }

            if ((int) $lockedAttempt->version !== $expectedVersion) {
                throw new RuntimeException('The admission attempt changed. Refresh and review the checklist before saving.');
            }

            $this->boundary->assertMutable($lockedAttempt);
            $this->boundary->currentSet($lockedAttempt, $requirementSetId);
            $latestVerification = $this->boundary->latestVerification($lockedAttempt, $item->getKey());

            if ($latestVerification?->getKey() !== $expectedLatestVerificationId) {
                throw new RuntimeException('The document inspection changed. Refresh and review before saving.');
            }

            $verification = AdmissionAttemptDocumentVerification::query()->create([
                'admission_attempt_id' => $lockedAttempt->getKey(),
                'requirement_item_id' => $item->getKey(),
                'status' => $status,
                'verified_by_user_id' => $freshActor->getKey(),
                'verified_at' => now(),
                'applicant_instruction' => $applicantInstruction,
                'internal_remarks' => $internalRemarks,
                'predecessor_id' => $latestVerification?->getKey(),
                'idempotency_key' => hash('sha256', $operation->idempotency_key),
            ]);

            $lockedAttempt->forceFill(['version' => ((int) $lockedAttempt->version) + 1])->save();
            $operation->forceFill(['payload' => [...$operation->payload, 'receipt_verification_id' => $verification->getKey()]])->save();
            $this->recordAuditEvent->handle('admission.document_verified', [
                'attempt_id' => (int) $lockedAttempt->getKey(),
                'requirement_set_id' => (int) $requirementSetId,
                'requirement_item_id' => (int) $item->getKey(),
                'item_code' => (string) $item->code,
                'status' => $status,
                'verification_id' => (int) $verification->getKey(),
                'attempt_version' => (int) $lockedAttempt->version,
            ], (string) $freshActor->getKey());

            return $verification;
        });
    }
}
