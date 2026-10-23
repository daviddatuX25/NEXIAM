<?php

declare(strict_types=1);

namespace App\Release\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Foundation\AuditEvent;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use App\Release\Contracts\ResultDeliveryLedgerInterface;
use App\Release\Support\ConsultationPortalCopyEligibility;
use App\Release\Support\ResultDocumentPdf;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DeliverResultAction implements ResultDeliveryLedgerInterface
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $notificationDispatcher,
        private readonly ConsultationPortalCopyEligibility $portalCopyEligibility,
        private readonly ResultDocumentPdf $resultDocumentPdf,
    ) {}

    public function deliverPhysical(
        AdmissionAttempt $attempt,
        ResultDocument $document,
        User $counselor,
        string $idempotencyKey,
        string $printReceiptId,
        string $consultationEncounterId,
    ): ResultRelease {
        $this->authorize($counselor);

        return DB::transaction(function () use ($attempt, $document, $counselor, $idempotencyKey, $printReceiptId, $consultationEncounterId): ResultRelease {
            $attempt = AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $encounter = ConsultationEncounter::query()
                ->whereKey($consultationEncounterId)
                ->where('admission_attempt_id', $attempt->id)
                ->whereIn('status', [
                    ConsultationEncounter::STATUS_OUTCOME_RECORDED,
                    ConsultationEncounter::STATUS_CLOSED_WITH_OUTCOME,
                ])
                ->lockForUpdate()
                ->first();
            if ($encounter === null) {
                throw new RuntimeException('A valid completed consultation encounter is required for physical handover.');
            }
            $consultationDecision = ConsultationDecision::query()
                ->where('admission_attempt_id', $attempt->id)
                ->latest('id')
                ->lockForUpdate()
                ->first();
            if ($consultationDecision === null) {
                throw new RuntimeException('The applicant must have a recorded consultation outcome before physical handover.');
            }

            $document = ResultDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $existing = $this->findIdempotentRelease($idempotencyKey, $attempt, $document, 'physical');
            if ($existing !== null) {
                return $existing;
            }

            $this->assertCurrentReadyDocument($attempt, $document);

            $printReceipt = ResultDocumentPrintReceipt::query()
                ->whereKey($printReceiptId)
                ->where('admission_attempt_id', $attempt->id)
                ->where('result_document_id', $document->id)
                ->where('operation', 'confirmed')
                ->where('is_current', true)
                ->lockForUpdate()
                ->first();
            if ($printReceipt === null) {
                throw new RuntimeException('The result document has no current physical print confirmation.');
            }

            $release = $this->createRelease(
                $attempt,
                $document,
                $counselor,
                $idempotencyKey,
                'physical',
                $encounter->medium,
                $printReceipt->id,
                $consultationDecision->id,
                $encounter->id,
                true,
            );

            return $release;
        });
    }

    public function issuePortal(
        AdmissionAttempt $attempt,
        ResultDocument $document,
        User $actor,
        string $idempotencyKey,
        ?int $consultationDecisionId = null,
        ?int $consultationEncounterId = null,
    ): ResultRelease {
        $this->authorize($actor);

        if (
            $consultationDecisionId !== null
            && ! $this->authorizer->decideFresh($actor, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, [
                'institution_id' => $attempt->institution_id,
                'office' => 'guidance',
            ])->allowed
        ) {
            throw new RuntimeException('The result release capability is not granted for this institution.');
        }

        $createdDeliveries = collect();
        $release = DB::transaction(function () use ($attempt, $document, $actor, $idempotencyKey, $consultationDecisionId, $consultationEncounterId, &$createdDeliveries): ResultRelease {
            $attempt = AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $document = ResultDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $existing = $this->findIdempotentRelease($idempotencyKey, $attempt, $document, 'portal');
            if ($existing !== null) {
                return $existing;
            }
            $this->assertCurrentReadyDocument($attempt, $document);

            $pdf = $document->artifact_manifest['pdf'] ?? null;
            if (
                ! is_array($pdf)
                || ($pdf['status'] ?? null) !== 'ready'
                || ! is_string($pdf['path'] ?? null)
                || $pdf['path'] === ''
                || ! is_int($pdf['size'] ?? null)
                || $pdf['size'] < 1
                || ! is_string($pdf['sha256'] ?? null)
                || preg_match('/^[a-f0-9]{64}$/', $pdf['sha256']) !== 1
            ) {
                throw new RuntimeException('A ready, checksummed PDF artifact is required for portal issuance.');
            }

            if (($consultationDecisionId === null) !== ($consultationEncounterId === null)) {
                throw new RuntimeException('Both consultation decision and encounter provenance are required.');
            }
            if ($consultationDecisionId !== null && $consultationEncounterId !== null) {
                $decision = ConsultationDecision::query()
                    ->whereKey($consultationDecisionId)
                    ->where('admission_attempt_id', $attempt->id)
                    ->lockForUpdate()
                    ->first();
                $encounter = ConsultationEncounter::query()
                    ->whereKey($consultationEncounterId)
                    ->where('admission_attempt_id', $attempt->id)
                    ->lockForUpdate()
                    ->first();
                if (
                    $decision === null
                    || $encounter === null
                    || ! $this->portalCopyEligibility->isEligible($attempt, $decision, $encounter, $document, $actor)
                ) {
                    throw new RuntimeException('The confirmed F2F decision is no longer eligible for portal issuance.');
                }
            }
            if ($consultationDecisionId !== null) {
                try {
                    $this->resultDocumentPdf->read($document);
                } catch (RuntimeException $exception) {
                    throw new RuntimeException('A valid current result PDF is required for automatic portal issuance.', previous: $exception);
                }
            }

            $release = $this->createRelease(
                $attempt,
                $document,
                $actor,
                $idempotencyKey,
                'portal',
                'online',
                consultationDecisionId: $consultationDecisionId,
                consultationEncounterId: $consultationEncounterId,
            );
            $recipient = NotificationRecipients::applicantFor($attempt);
            if ($recipient !== null) {
                $intent = NotificationIntent::v1(
                    eventType: 'result.digital_issued.v1',
                    templateKey: 'result.digital_issued',
                    payload: [
                        'institution_id' => $attempt->institution_id,
                        'domain' => 'results',
                        'canonical_slug' => 'digital-issued',
                        'subject' => 'Your digital result is available',
                        'body' => 'Your official result document is available in the Applicant Portal.',
                        'action_label' => 'View Results',
                        'action_url' => route('applicant.results.show'),
                        'attempt_id' => $attempt->id,
                        'release_id' => $release->id,
                        'identity_id' => $recipient->id,
                        'event_context' => [
                            'application_no' => $attempt->reference_code,
                            'applicant_name' => $attempt->identity?->fullName() ?? '',
                            'institution_name' => $attempt->identity?->institution?->name ?? '',
                        ],
                    ],
                    channels: [NotificationDelivery::CHANNEL_IN_APP, NotificationDelivery::CHANNEL_MAIL],
                );
                $createdDeliveries = $this->notificationDispatcher->dispatch(
                    $intent,
                    hash('sha256', "result.digital_issued:{$release->id}"),
                    [$recipient],
                    $intent->channels,
                );
            }

            return $release;
        });

        NotificationIntentDispatcher::enqueueAfterCommit($createdDeliveries);

        return $release;
    }

    public function deliver(
        AdmissionAttempt $attempt,
        string $channel,
        User $actor,
        string $idempotencyKey,
        ?string $printReceiptId = null,
        ?string $consultationEncounterId = null,
    ): ResultRelease {
        $document = ResultDocument::query()->findOrFail($attempt->current_result_document_id);

        return match ($channel) {
            'physical' => $this->deliverPhysical(
                $attempt,
                $document,
                $actor,
                $idempotencyKey,
                $printReceiptId ?? throw new RuntimeException('A current print receipt is required for physical handover.'),
                $consultationEncounterId ?? throw new RuntimeException('A completed consultation encounter is required for physical handover.'),
            ),
            'portal' => $this->issuePortal($attempt, $document, $actor, $idempotencyKey),
            'email' => throw new RuntimeException('Direct result-sheet email delivery is not authorized; issue the authenticated portal link instead.'),
            default => throw new RuntimeException('Invalid result delivery channel.'),
        };
    }

    public function retract(
        ResultRelease $release,
        User $actor,
        string $reason,
        string $idempotencyKey,
    ): ResultRelease {
        $this->authorize($actor);
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('A reason is required to retract result availability.');
        }

        return DB::transaction(function () use ($release, $actor, $reason, $idempotencyKey): ResultRelease {
            $release = ResultRelease::query()->lockForUpdate()->findOrFail($release->id);
            $existingEvent = AuditEvent::query()
                ->where('event_type', 'result.delivery_retracted')
                ->where('payload->idempotency_key', $idempotencyKey)
                ->first();
            if ($existingEvent !== null) {
                if ((int) ($existingEvent->payload['release_id'] ?? 0) !== (int) $release->id) {
                    throw new RuntimeException('The retraction idempotency key is already in use.');
                }

                return $release;
            }

            if ($release->channel === 'physical') {
                throw new RuntimeException('A completed physical handover cannot be retracted.');
            }
            if (! $release->is_current || $release->unreleased_at !== null) {
                throw new RuntimeException('The result issuance is not currently active.');
            }

            $release->update([
                'is_current' => false,
                'unreleased_at' => now(),
                'unrelease_reason' => $reason,
            ]);
            $this->recordAuditEvent->handle('result.delivery_retracted', [
                'release_id' => $release->id,
                'attempt_id' => $release->admission_attempt_id,
                'document_id' => $release->result_document_id,
                'channel' => $release->channel,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
            ], (string) $actor->id);

            return $release->fresh();
        });
    }

    private function createRelease(
        AdmissionAttempt $attempt,
        ResultDocument $document,
        User $actor,
        string $idempotencyKey,
        string $channel,
        string $mode,
        ?int $printReceiptId = null,
        ?int $consultationDecisionId = null,
        ?int $consultationEncounterId = null,
        bool $physicalHandover = false,
    ): ResultRelease {
        $channelRelease = ResultRelease::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('result_document_id', $document->id)
            ->where('channel', $channel)
            ->where('is_current', true)
            ->first();
        if ($channelRelease !== null) {
            throw new RuntimeException('The current result document has already been issued through this channel.');
        }

        $release = new ResultRelease([
            'admission_attempt_id' => $attempt->id,
            'result_document_id' => $document->id,
            'released_by_user_id' => $actor->id,
            'release_version' => (int) ResultRelease::query()->where('admission_attempt_id', $attempt->id)->max('release_version') + 1,
            'is_current' => true,
            'released_at' => now(),
            'unreleased_at' => null,
            'unrelease_reason' => null,
            'idempotency_key' => $idempotencyKey,
            'mode' => $mode,
            'channel' => $channel,
            'print_receipt_id' => $printReceiptId,
            'consultation_decision_id' => $consultationDecisionId,
            'physical_handover_at' => $physicalHandover ? now() : null,
            'handover_counselor_user_id' => $physicalHandover ? $actor->id : null,
        ]);
        $release->consultation_encounter_id = $consultationEncounterId;
        $release->save();

        $this->recordAuditEvent->handle('result.delivery_issued', [
            'release_id' => $release->id,
            'attempt_id' => $attempt->id,
            'document_id' => $document->id,
            'channel' => $channel,
            'print_receipt_id' => $printReceiptId,
            'consultation_decision_id' => $consultationDecisionId,
            'consultation_encounter_id' => $consultationEncounterId,
        ], (string) $actor->id);

        return $release;
    }

    private function assertCurrentReadyDocument(AdmissionAttempt $attempt, ResultDocument $document): void
    {
        if (
            (int) $document->admission_attempt_id !== (int) $attempt->id
            || (int) $attempt->current_result_document_id !== (int) $document->id
            || $document->status !== ResultDocument::STATUS_READY
        ) {
            throw new RuntimeException('Only the current ready result document can be issued.');
        }
    }

    private function findIdempotentRelease(
        string $idempotencyKey,
        AdmissionAttempt $attempt,
        ResultDocument $document,
        string $channel,
    ): ?ResultRelease {
        $existing = ResultRelease::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
        if ($existing === null) {
            return null;
        }

        if (
            (int) $existing->admission_attempt_id !== (int) $attempt->id
            || (int) $existing->result_document_id !== (int) $document->id
            || $existing->channel !== $channel
        ) {
            throw new RuntimeException('The result delivery idempotency key was already used for another operation.');
        }

        return $existing;
    }

    private function authorize(User $actor): void
    {
        if (! $this->authorizer->decide($actor, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, [])->allowed) {
            throw new RuntimeException('The result release capability is required to issue result documents.');
        }
    }
}
