<?php

namespace App\Admission\Actions;

use App\Admission\Support\AdmissionClearanceReadiness;
use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\AttemptRequirementChecklistBoundary;
use App\Admission\Support\CommandIdempotency;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use App\Notifications\Policies\AdmissionNotificationPolicy;
use App\Notifications\Templates\AdmissionClearanceNotificationTemplate;
use App\Notifications\Templates\AdmissionExaminationNoticeContext;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ConfirmAdmissionClearance
{
    public function __construct(
        private readonly AttemptRequirementChecklistBoundary $boundary,
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly NotificationIntentDispatcher $notificationDispatcher,
    ) {}

    public function handle(
        AdmissionAttempt $attempt,
        User $actor,
        int $expectedVersion,
        string $idempotencyKey,
    ): AdmissionClearanceReceipt {
        if (trim($idempotencyKey) === '' || mb_strlen($idempotencyKey) > 128) {
            throw new RuntimeException('A valid idempotency key is required to confirm admission clearance.');
        }

        $createdDeliveries = collect();

        $receipt = $this->planningMutex->run(AssignmentPlanningMutex::DOMAIN_QUOTA, (int) $attempt->academic_cycle_id, fn (): AdmissionClearanceReceipt => DB::transaction(function () use ($attempt, $actor, $expectedVersion, $idempotencyKey, &$createdDeliveries): AdmissionClearanceReceipt {
            $lockedAttempt = $this->boundary->lockAttempt((int) $attempt->getKey());
            $freshActor = $this->boundary->authorizeFresh($actor, $lockedAttempt, CapabilityCatalog::REGISTRAR_ADMISSION_CLEARANCE_CONFIRM);
            $operation = $this->idempotency->claim(
                $freshActor,
                'admission.clearance.confirm',
                'attempt:'.$lockedAttempt->getKey(),
                [
                    'attempt_id' => (int) $lockedAttempt->getKey(),
                    'expected_version' => $expectedVersion,
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
                throw new RuntimeException('The admission attempt changed. Refresh and review before confirming clearance.');
            }

            if (AttemptProgramFacts::isClearanceConfirmed($lockedAttempt)) {
                throw new RuntimeException('Admission clearance is already confirmed for this application.');
            }

            $this->boundary->assertMutable($lockedAttempt);
            $readiness = AdmissionClearanceReadiness::evaluate($lockedAttempt);
            if (! ($readiness['is_ready'] ?? false)) {
                $reasons = implode('; ', $readiness['blocking_reasons'] ?? ['Prerequisites not met']);
                throw new RuntimeException("Admission clearance cannot be confirmed: {$reasons}");
            }

            $programId = $readiness['program_id']
                ?? $lockedAttempt->program_id
                ?? $lockedAttempt->enrollmentStamp?->provisional_program_id;

            if ($programId === null) {
                throw new RuntimeException('Admission clearance cannot be confirmed: No program selected.');
            }

            $predecessor = AdmissionClearanceReceipt::query()
                ->where('admission_attempt_id', $lockedAttempt->getKey())
                ->latest('id')
                ->first();
            $receipt = AdmissionClearanceReceipt::query()->create([
                'admission_attempt_id' => $lockedAttempt->getKey(),
                'program_id' => $programId,
                'receipt_number' => 'CLR-'.uniqid(),
                'status' => AdmissionClearanceReceipt::STATUS_CONFIRMED,
                'evidence_vector' => $readiness['evidence_vector'] ?? [],
                'confirmed_by_user_id' => $freshActor->getKey(),
                'confirmed_at' => now(),
                'idempotency_key' => hash('sha256', $operation->idempotency_key),
                'predecessor_receipt_id' => $predecessor?->getKey(),
            ]);

            $lockedAttempt->forceFill(['version' => ((int) $lockedAttempt->version) + 1])->save();
            $operation->forceFill(['payload' => [...$operation->payload, 'receipt_clearance_id' => $receipt->getKey()]])->save();
            $this->recordAuditEvent->handle('admission.clearance.confirmed', [
                'receipt_id' => $receipt->getKey(),
                'receipt_number' => $receipt->receipt_number,
                'attempt_id' => (int) $lockedAttempt->getKey(),
                'program_id' => (int) $receipt->program_id,
                'status' => $receipt->status,
                'confirmed_at' => $receipt->confirmed_at?->toIso8601String(),
                'attempt_version' => (int) $lockedAttempt->version,
                'predecessor_receipt_id' => $receipt->predecessor_receipt_id,
            ], (string) $freshActor->getKey());
            $recipient = NotificationRecipients::applicantFor($lockedAttempt);
            if ($recipient !== null) {
                $lockedAttempt->loadMissing('academicCycle');
                $cycle = $lockedAttempt->academicCycle;
                $policy = AdmissionNotificationPolicy::fromStorage($cycle?->admission_notification_policy);
                $channels = [NotificationDelivery::CHANNEL_IN_APP];

                if ($policy->applicantStatusEmailEnabled) {
                    $channels[] = NotificationDelivery::CHANNEL_MAIL;
                }

                $intent = NotificationIntent::v1(
                    eventType: AdmissionClearanceNotificationTemplate::EVENT_KEY,
                    templateKey: AdmissionClearanceNotificationTemplate::TEMPLATE_KEY,
                    payload: [
                        ...AdmissionExaminationNoticeContext::admissionPayload($lockedAttempt, AdmissionClearanceNotificationTemplate::SLUG),
                        'subject' => AdmissionClearanceNotificationTemplate::inAppTitle(),
                        'body' => AdmissionClearanceNotificationTemplate::message(),
                        'action_label' => 'View Clearance Details',
                        'action_url' => AdmissionExaminationNoticeContext::url('applicant.admission-clearance'),
                        'attempt_id' => $lockedAttempt->id,
                        'reference_code' => $lockedAttempt->reference_code,
                        'program_id' => (int) $receipt->program_id,
                        'receipt_number' => $receipt->receipt_number,
                    ],
                    channels: $channels,
                );

                $eventId = hash('sha256', "admission.clearance.confirmed:{$lockedAttempt->id}:receipt:{$receipt->id}");
                $createdDeliveries = $this->notificationDispatcher->dispatch(
                    $intent,
                    $eventId,
                    [$recipient],
                    $channels,
                );
            }

            return $receipt;
        }));

        NotificationIntentDispatcher::enqueueAfterCommit($createdDeliveries);

        return $receipt;
    }
}
