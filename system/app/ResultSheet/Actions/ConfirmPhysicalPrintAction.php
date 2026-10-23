<?php

declare(strict_types=1);

namespace App\ResultSheet\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Printing\PrintJobItem;
use App\Models\Release\ResultDocument;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use App\ResultSheet\Contracts\PhysicalPrintReceiptInterface;
use App\Scheduling\Actions\AutoAssignEligibleAttempt;
use App\Scheduling\AssignmentInvariant;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class ConfirmPhysicalPrintAction implements PhysicalPrintReceiptInterface
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $notificationDispatcher,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly AutoAssignEligibleAttempt $autoAssign,
    ) {}

    public function confirmPrint(
        ResultDocument $document,
        User $actor,
        string $idempotencyKey,
        ?string $jobItemId = null,
    ): ResultDocumentPrintReceipt {
        $createdDeliveries = collect();
        $academicCycleId = (int) AdmissionAttempt::query()->whereKey($document->admission_attempt_id)->value('academic_cycle_id');
        $receipt = $this->planningMutex->run(AssignmentPlanningMutex::DOMAIN_CONSULTATION, $academicCycleId, function () use ($document, $actor, $idempotencyKey, $jobItemId, &$createdDeliveries): ResultDocumentPrintReceipt {
            $attempt = AdmissionAttempt::query()->whereKey($document->admission_attempt_id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, (int) $attempt->institution_id);
            $document = ResultDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $existing = ResultDocumentPrintReceipt::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();

            if ($existing !== null) {
                if (
                    (int) $existing->result_document_id !== (int) $document->id
                    || (int) $existing->confirmed_by_user_id !== (int) $actor->id
                    || $existing->operation !== 'confirmed'
                ) {
                    throw new RuntimeException('The print idempotency key was already used for another operation.');
                }

                return $existing;
            }

            if ($document->admission_attempt_id !== $attempt->id || $document->status !== ResultDocument::STATUS_READY) {
                throw new RuntimeException('Only a ready result document can be confirmed as printed.');
            }

            if ($attempt->current_result_document_id === null) {
                $attempt->current_result_document_id = $document->id;
                $attempt->save();
            } elseif ((int) $attempt->current_result_document_id !== (int) $document->id) {
                throw new RuntimeException('Only the current result document can be confirmed as printed.');
            }

            if ($jobItemId !== null) {
                $jobItem = PrintJobItem::query()->whereKey($jobItemId)->first();
                if (
                    $jobItem === null
                    || (int) $jobItem->result_document_id !== (int) $document->id
                    || (int) $jobItem->admission_attempt_id !== (int) $attempt->id
                    || $jobItem->status !== PrintJobItem::STATUS_READY
                ) {
                    throw new RuntimeException('The print job item does not contain a ready artifact for this document.');
                }
            }

            ResultDocumentPrintReceipt::query()
                ->where('result_document_id', $document->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $receipt = ResultDocumentPrintReceipt::query()->create([
                'institution_id' => $attempt->institution_id,
                'admission_attempt_id' => $attempt->id,
                'result_document_id' => $document->id,
                'confirmed_by_user_id' => $actor->id,
                'confirmed_at' => now(),
                'job_item_id' => $jobItemId,
                'operation' => 'confirmed',
                'evidence_revision' => (int) ResultDocumentPrintReceipt::query()
                    ->where('result_document_id', $document->id)
                    ->max('evidence_revision') + 1,
                'is_current' => true,
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->recordAuditEvent->handle('result_document.print_confirmed', [
                'receipt_id' => $receipt->id,
                'attempt_id' => $attempt->id,
                'document_id' => $document->id,
                'job_item_id' => $jobItemId,
            ], (string) $actor->id);

            $recipient = NotificationRecipients::applicantFor($attempt);
            if ($recipient !== null) {
                $intent = NotificationIntent::v1(
                    eventType: 'result.paper_ready.v1',
                    templateKey: 'result.paper_ready',
                    payload: [
                        'institution_id' => $attempt->institution_id,
                        'domain' => 'results',
                        'canonical_slug' => 'paper-ready',
                        'subject' => 'Your printed result is ready',
                        'body' => 'Your printed result is ready for collection during your consultation.',
                        'action_label' => 'View Consultation Info',
                        'action_url' => route('applicant.portal'),
                        'attempt_id' => $attempt->id,
                        'document_id' => $document->id,
                        'identity_id' => $recipient->id,
                        'event_context' => [
                            'application_no' => $attempt->reference_code,
                            'applicant_name' => $attempt->identity?->fullName() ?? '',
                            'institution_name' => $attempt->identity?->institution?->name ?? '',
                        ],
                    ],
                    channels: [NotificationDelivery::CHANNEL_IN_APP],
                );
                $createdDeliveries = $this->notificationDispatcher->dispatch(
                    $intent,
                    hash('sha256', "result.paper_ready:{$attempt->id}:{$document->id}"),
                    [$recipient],
                    $intent->channels,
                );
            }

            return $receipt;
        });

        NotificationIntentDispatcher::enqueueAfterCommit($createdDeliveries);
        DB::afterCommit(function () use ($receipt, $actor): void {
            try {
                $attempt = AdmissionAttempt::query()->findOrFail($receipt->admission_attempt_id);
                $this->autoAssign->handle(AssignmentInvariant::DOMAIN_CONSULTATION, $attempt, $actor);
            } catch (\Throwable $exception) {
                Log::error('Consultation auto-assign post-print confirmation failed: '.$exception->getMessage(), ['exception' => $exception]);
            }
        });

        return $receipt;
    }

    public function correctPrintConfirmation(
        ResultDocumentPrintReceipt $receipt,
        User $actor,
        string $reason,
        string $idempotencyKey,
    ): ResultDocumentPrintReceipt {
        $reason = trim($reason);

        if ($reason === '') {
            throw new RuntimeException('A reason is required to correct a print confirmation.');
        }

        $academicCycleId = (int) AdmissionAttempt::query()->whereKey($receipt->admission_attempt_id)->value('academic_cycle_id');

        return $this->planningMutex->run(AssignmentPlanningMutex::DOMAIN_CONSULTATION, $academicCycleId, function () use ($receipt, $actor, $reason, $idempotencyKey): ResultDocumentPrintReceipt {
            $attempt = AdmissionAttempt::query()->whereKey($receipt->admission_attempt_id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, (int) $attempt->institution_id);
            $document = ResultDocument::query()->whereKey($receipt->result_document_id)->lockForUpdate()->firstOrFail();
            $existing = ResultDocumentPrintReceipt::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();

            if ($existing !== null) {
                if (
                    (int) $existing->correction_predecessor_id !== (int) $receipt->id
                    || (int) $existing->confirmed_by_user_id !== (int) $actor->id
                    || $existing->operation !== 'corrected'
                ) {
                    throw new RuntimeException('The print correction idempotency key was already used for another operation.');
                }

                return $existing;
            }

            $receipt = ResultDocumentPrintReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            if (
                (int) $receipt->result_document_id !== (int) $document->id
                || (int) $receipt->admission_attempt_id !== (int) $attempt->id
                || ! $receipt->is_current
                || $receipt->operation !== 'confirmed'
            ) {
                throw new RuntimeException('Only the current print confirmation can be corrected.');
            }

            $receipt->update(['is_current' => false]);
            $correction = ResultDocumentPrintReceipt::query()->create([
                'institution_id' => $attempt->institution_id,
                'admission_attempt_id' => $attempt->id,
                'result_document_id' => $document->id,
                'confirmed_by_user_id' => $actor->id,
                'confirmed_at' => now(),
                'operation' => 'corrected',
                'correction_reason' => $reason,
                'correction_predecessor_id' => $receipt->id,
                'evidence_revision' => (int) ResultDocumentPrintReceipt::query()
                    ->where('result_document_id', $document->id)
                    ->max('evidence_revision') + 1,
                'is_current' => false,
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->recordAuditEvent->handle('result_document.print_confirmation_corrected', [
                'receipt_id' => $correction->id,
                'predecessor_receipt_id' => $receipt->id,
                'attempt_id' => $attempt->id,
                'document_id' => $document->id,
                'reason' => $reason,
            ], (string) $actor->id);

            return $correction;
        });
    }

    private function authorize(User $actor, int $institutionId): void
    {
        $canManage = $this->authorizer->allowsAny($actor, [
            CapabilityCatalog::GUIDANCE_RESULTS_RELEASE,
            CapabilityCatalog::GUIDANCE_RESULTS_PREPARE,
        ], ['institution_id' => $institutionId]);
        if (! $canManage) {
            throw new RuntimeException('The result release or result prepare capability is required to manage physical print receipts.');
        }
    }
}
