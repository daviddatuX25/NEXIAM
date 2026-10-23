<?php

namespace App\Admission\Actions;

use App\Admission\Support\AttemptRequirementChecklistBoundary;
use App\Admission\Support\CommandIdempotency;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptRequirementInternalNote;
use App\Models\Admission\AdmissionAttemptRequirementItem;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RecordRequirementInternalNote
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
        ?int $expectedLatestNoteId,
        User $actor,
        int $expectedVersion,
        ?string $internalRemarks,
        string $idempotencyKey,
        ?int $intakeRequirementId = null,
    ): AdmissionAttemptRequirementInternalNote {
        $internalRemarks = $this->boundary->normalizeText($internalRemarks);

        if (mb_strlen((string) $internalRemarks) > 2000) {
            throw ValidationException::withMessages(['internal_remarks' => 'Internal remarks may not exceed 2,000 characters.']);
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
            $expectedLatestNoteId,
            $actor,
            $expectedVersion,
            $internalRemarks,
            $idempotencyKey,
        ): AdmissionAttemptRequirementInternalNote {
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
                'admission.documents.note',
                'attempt:'.$lockedAttempt->getKey(),
                [
                    'attempt_id' => (int) $lockedAttempt->getKey(),
                    'expected_version' => $expectedVersion,
                    'requirement_set_id' => $requirementSetId,
                    'requirement_item_id' => (int) $item->getKey(),
                    'expected_latest_verification_id' => $expectedLatestVerificationId,
                    'expected_latest_note_id' => $expectedLatestNoteId,
                    'internal_remarks' => $internalRemarks,
                ],
                $idempotencyKey,
            );

            $receiptNoteId = $operation->payload['receipt_note_id'] ?? null;
            if ($receiptNoteId !== null) {
                return AdmissionAttemptRequirementInternalNote::query()
                    ->whereKey($receiptNoteId)
                    ->where('admission_attempt_id', $lockedAttempt->getKey())
                    ->where('requirement_item_id', $item->getKey())
                    ->firstOrFail();
            }

            if ((int) $lockedAttempt->version !== $expectedVersion) {
                throw new RuntimeException('The admission attempt changed. Refresh and review the checklist before saving.');
            }

            $this->boundary->assertMutable($lockedAttempt);
            $this->boundary->currentSet($lockedAttempt, $requirementSetId);
            $latestVerification = $this->boundary->latestVerification($lockedAttempt, (int) $item->getKey());

            if ($latestVerification?->getKey() !== $expectedLatestVerificationId) {
                throw new RuntimeException('The document inspection changed. Refresh and review before saving.');
            }

            $latestNote = AdmissionAttemptRequirementInternalNote::query()
                ->where('admission_attempt_id', $lockedAttempt->getKey())
                ->where('requirement_item_id', $item->getKey())
                ->orderByDesc('id')
                ->first();

            if ($latestNote?->getKey() !== $expectedLatestNoteId) {
                throw new RuntimeException('The internal note changed. Refresh and review before saving.');
            }

            $note = AdmissionAttemptRequirementInternalNote::query()->create([
                'admission_attempt_id' => $lockedAttempt->getKey(),
                'requirement_item_id' => $item->getKey(),
                'recorded_by_user_id' => $freshActor->getKey(),
                'recorded_at' => now(),
                'internal_remarks' => $internalRemarks,
                'predecessor_id' => $latestNote?->getKey(),
                'idempotency_key' => hash('sha256', $operation->idempotency_key),
            ]);

            $lockedAttempt->forceFill(['version' => ((int) $lockedAttempt->version) + 1])->save();
            $operation->forceFill(['payload' => [...$operation->payload, 'receipt_note_id' => $note->getKey()]])->save();
            $this->recordAuditEvent->handle('admission.document_internal_note_recorded', [
                'attempt_id' => (int) $lockedAttempt->getKey(),
                'requirement_set_id' => (int) $requirementSetId,
                'requirement_item_id' => (int) $item->getKey(),
                'item_code' => (string) $item->code,
                'note_id' => (int) $note->getKey(),
                'attempt_version' => (int) $lockedAttempt->version,
            ], (string) $freshActor->getKey());

            return $note;
        });
    }
}
