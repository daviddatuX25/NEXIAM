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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BulkVerifyAdmissionAttemptDocuments
{
    public function __construct(
        private readonly AttemptRequirementChecklistBoundary $boundary,
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly EnsureAttemptRequirementChecklist $ensureChecklist,
    ) {}

    /**
     * @param  array<int, array{requirement_item_id?: ?int, intake_requirement_id?: ?int, expected_latest_verification_id: ?int, internal_remarks?: ?string}>  $items
     * @return Collection<int, AdmissionAttemptDocumentVerification>
     */
    public function handle(
        AdmissionAttempt $attempt,
        ?int $requirementSetId,
        array $items,
        User $actor,
        int $expectedVersion,
        string $idempotencyKey,
    ): Collection {
        if (empty($items)) {
            throw ValidationException::withMessages(['items' => 'Select at least one requirement to verify.']);
        }

        if (trim($idempotencyKey) === '' || mb_strlen($idempotencyKey) > 128) {
            throw ValidationException::withMessages(['idempotency_key' => 'A valid idempotency key is required.']);
        }

        return DB::transaction(function () use (
            $attempt,
            $requirementSetId,
            $items,
            $actor,
            $expectedVersion,
            $idempotencyKey,
        ): Collection {
            $lockedAttempt = $this->boundary->lockAttempt((int) $attempt->getKey());
            $freshActor = $this->boundary->authorizeFresh($actor, $lockedAttempt, CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY);

            if ($requirementSetId === null) {
                $ensuredSet = $this->ensureChecklist->handle($lockedAttempt, $freshActor);
                if ($ensuredSet === null) {
                    throw new RuntimeException('No active document requirements apply to this applicant track.');
                }
                $requirementSetId = (int) $ensuredSet->getKey();
            }

            $set = $this->boundary->currentSet($lockedAttempt, $requirementSetId);

            // Resolve and validate all items
            $resolvedItems = [];
            foreach ($items as $index => $itemSpec) {
                $reqItemId = isset($itemSpec['requirement_item_id']) ? (int) $itemSpec['requirement_item_id'] : null;
                $intakeReqId = isset($itemSpec['intake_requirement_id']) ? (int) $itemSpec['intake_requirement_id'] : null;

                if ($reqItemId === null && $intakeReqId !== null) {
                    $matched = $set->items->first(
                        fn (AdmissionAttemptRequirementItem $cand): bool => (int) $cand->intake_requirement_id === $intakeReqId
                    );
                    if ($matched === null) {
                        throw (new ModelNotFoundException)->setModel(
                            AdmissionAttemptRequirementItem::class,
                            [$intakeReqId],
                        );
                    }
                    $reqItemId = (int) $matched->getKey();
                }

                if ($reqItemId === null) {
                    throw ValidationException::withMessages(["items.{$index}.requirement_item_id" => 'Requirement item ID is required.']);
                }

                $ownedItem = $this->boundary->ownedItem($lockedAttempt, $requirementSetId, $reqItemId);
                $expectedLatestId = isset($itemSpec['expected_latest_verification_id']) && $itemSpec['expected_latest_verification_id'] !== null
                    ? (int) $itemSpec['expected_latest_verification_id']
                    : null;

                $resolvedItems[] = [
                    'item' => $ownedItem,
                    'expected_latest_id' => $expectedLatestId,
                    'internal_remarks' => isset($itemSpec['internal_remarks'])
                        ? $this->boundary->normalizeText($itemSpec['internal_remarks'])
                        : null,
                ];
            }

            // Deterministic sort for idempotency payload
            usort($resolvedItems, fn ($a, $b) => $a['item']->getKey() <=> $b['item']->getKey());

            $operation = $this->idempotency->claim(
                $freshActor,
                'admission.documents.bulk-verify',
                'attempt:'.$lockedAttempt->getKey(),
                [
                    'attempt_id' => (int) $lockedAttempt->getKey(),
                    'expected_version' => $expectedVersion,
                    'requirement_set_id' => $requirementSetId,
                    'item_ids' => array_map(fn ($r) => (int) $r['item']->getKey(), $resolvedItems),
                ],
                $idempotencyKey,
            );

            $receiptIds = $operation->payload['receipt_verification_ids'] ?? null;
            if (is_array($receiptIds) && ! empty($receiptIds)) {
                return AdmissionAttemptDocumentVerification::query()
                    ->whereIn('id', $receiptIds)
                    ->where('admission_attempt_id', $lockedAttempt->getKey())
                    ->get();
            }

            if ((int) $lockedAttempt->version !== $expectedVersion) {
                throw new RuntimeException('The admission attempt changed. Refresh and review the checklist before saving.');
            }

            $this->boundary->assertMutable($lockedAttempt);

            // Verify that no item inspection has changed
            foreach ($resolvedItems as &$resolved) {
                $latestVerification = $this->boundary->latestVerification($lockedAttempt, $resolved['item']->getKey());
                if ($latestVerification?->getKey() !== $resolved['expected_latest_id']) {
                    throw new RuntimeException("The document inspection for '{$resolved['item']->label}' changed. Refresh and review before saving.");
                }
                $resolved['latest_verification'] = $latestVerification;
            }
            unset($resolved);
            $createdVerifications = new Collection;
            $createdIds = [];

            foreach ($resolvedItems as $resolved) {
                /** @var AdmissionAttemptRequirementItem $item */
                $item = $resolved['item'];
                $latest = $resolved['latest_verification'];
                $internalRemarks = $resolved['internal_remarks'];

                $verification = AdmissionAttemptDocumentVerification::query()->create([
                    'admission_attempt_id' => $lockedAttempt->getKey(),
                    'requirement_item_id' => $item->getKey(),
                    'status' => AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
                    'verified_by_user_id' => $freshActor->getKey(),
                    'verified_at' => now(),
                    'applicant_instruction' => null,
                    'internal_remarks' => $internalRemarks,
                    'predecessor_id' => $latest?->getKey(),
                    'idempotency_key' => hash('sha256', $operation->idempotency_key.':'.$item->getKey()),
                ]);

                $this->recordAuditEvent->handle('admission.document_verified', [
                    'attempt_id' => (int) $lockedAttempt->getKey(),
                    'requirement_set_id' => (int) $requirementSetId,
                    'requirement_item_id' => (int) $item->getKey(),
                    'item_code' => (string) $item->code,
                    'status' => AdmissionAttemptDocumentVerification::STATUS_VERIFIED,
                    'verification_id' => (int) $verification->getKey(),
                    'attempt_version' => ((int) $lockedAttempt->version) + 1,
                    'bulk' => true,
                ], (string) $freshActor->getKey());

                $createdVerifications->push($verification);
                $createdIds[] = (int) $verification->getKey();
            }

            $lockedAttempt->forceFill(['version' => ((int) $lockedAttempt->version) + 1])->save();
            $operation->forceFill([
                'payload' => [
                    ...$operation->payload,
                    'receipt_verification_ids' => $createdIds,
                ],
            ])->save();

            return $createdVerifications;
        });
    }
}
