<?php

namespace App\Admission\Actions;

use App\Admission\Support\AttemptRequirementChecklistBoundary;
use App\Admission\Support\CommandIdempotency;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\Templates\AdmissionClearanceNotificationTemplate;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RevokeAdmissionClearance
{
    public function __construct(
        private readonly AttemptRequirementChecklistBoundary $boundary,
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
    ) {}

    public function handle(
        AdmissionAttempt $attempt,
        User $actor,
        int $expectedVersion,
        string $reason,
        string $idempotencyKey,
    ): AdmissionClearanceReceipt {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255) {
            throw new RuntimeException('A reason of at most 255 characters is required to reopen admission clearance.');
        }

        if (trim($idempotencyKey) === '' || mb_strlen($idempotencyKey) > 128) {
            throw new RuntimeException('A valid idempotency key is required to reopen admission clearance.');
        }

        return $this->planningMutex->run(AssignmentPlanningMutex::DOMAIN_QUOTA, (int) $attempt->academic_cycle_id, fn (): AdmissionClearanceReceipt => DB::transaction(function () use ($attempt, $actor, $expectedVersion, $reason, $idempotencyKey): AdmissionClearanceReceipt {
            $lockedAttempt = $this->boundary->lockAttempt((int) $attempt->getKey());
            $freshActor = $this->boundary->authorizeFresh($actor, $lockedAttempt, CapabilityCatalog::REGISTRAR_ADMISSION_CLEARANCE_REOPEN);
            $operation = $this->idempotency->claim(
                $freshActor,
                'admission.clearance.reopen',
                'attempt:'.$lockedAttempt->getKey(),
                [
                    'attempt_id' => (int) $lockedAttempt->getKey(),
                    'expected_version' => $expectedVersion,
                    'reason' => $reason,
                ],
                $idempotencyKey,
            );

            $receiptId = $operation->payload['receipt_clearance_id'] ?? null;
            if ($receiptId !== null) {
                return AdmissionClearanceReceipt::query()
                    ->whereKey($receiptId)
                    ->where('admission_attempt_id', $lockedAttempt->getKey())
                    ->firstOrFail();
            }

            if ((int) $lockedAttempt->version !== $expectedVersion) {
                throw new RuntimeException('The admission attempt changed. Refresh and review before reopening clearance.');
            }

            $confirmedReceipt = AdmissionClearanceReceipt::query()
                ->where('admission_attempt_id', $lockedAttempt->getKey())
                ->latest('id')
                ->first();

            if ($confirmedReceipt === null || $confirmedReceipt->status !== AdmissionClearanceReceipt::STATUS_CONFIRMED) {
                throw new RuntimeException('No active confirmed admission clearance found to reopen.');
            }

            $revokedReceipt = AdmissionClearanceReceipt::query()->create([
                'admission_attempt_id' => $lockedAttempt->getKey(),
                'program_id' => $confirmedReceipt->program_id,
                'receipt_number' => 'CLR-'.uniqid(),
                'status' => AdmissionClearanceReceipt::STATUS_REVOKED,
                'evidence_vector' => $confirmedReceipt->evidence_vector,
                'confirmed_by_user_id' => $confirmedReceipt->confirmed_by_user_id,
                'confirmed_at' => $confirmedReceipt->confirmed_at,
                'revoked_by_user_id' => $freshActor->getKey(),
                'revoked_at' => now(),
                'revocation_reason' => $reason,
                'idempotency_key' => hash('sha256', $operation->idempotency_key),
                'predecessor_receipt_id' => $confirmedReceipt->getKey(),
            ]);

            $lockedAttempt->forceFill(['version' => ((int) $lockedAttempt->version) + 1])->save();
            $operation->forceFill(['payload' => [...$operation->payload, 'receipt_clearance_id' => $revokedReceipt->getKey()]])->save();
            $this->recordAuditEvent->handle('admission.clearance.revoked', [
                'receipt_id' => $revokedReceipt->getKey(),
                'predecessor_receipt_id' => $confirmedReceipt->getKey(),
                'receipt_number' => $revokedReceipt->receipt_number,
                'attempt_id' => (int) $lockedAttempt->getKey(),
                'program_id' => (int) $revokedReceipt->program_id,
                'status' => $revokedReceipt->status,
                'revoked_at' => $revokedReceipt->revoked_at?->toIso8601String(),
                'revocation_reason' => $reason,
                'attempt_version' => (int) $lockedAttempt->version,
            ], (string) $freshActor->getKey());
            NotificationDelivery::query()
                ->where('event_type', AdmissionClearanceNotificationTemplate::EVENT_KEY)
                ->where('recipient_type', ApplicantIdentity::class)
                ->where(function (Builder $query) use ($lockedAttempt): void {
                    $query->where('payload->attempt_id', (int) $lockedAttempt->id)
                        ->orWhere('payload->event_resource->attempt_id', (int) $lockedAttempt->id);
                })
                ->whereIn('status', [
                    NotificationDelivery::STATUS_PENDING,
                    NotificationDelivery::STATUS_RETRYING,
                    NotificationDelivery::STATUS_PROCESSING,
                ])
                ->update([
                    'status' => NotificationDelivery::STATUS_SUPERSEDED,
                    'claim_token' => null,
                    'lease_expires_at' => null,
                    'next_attempt_at' => null,
                ]);

            return $revokedReceipt;
        }));
    }
}
