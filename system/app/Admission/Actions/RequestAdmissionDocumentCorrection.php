<?php

namespace App\Admission\Actions;

use App\Admission\Exceptions\DocumentCorrectionRequestRejected;
use App\Admission\Support\AttemptRequirementChecklistBoundary;
use App\Admission\Support\CommandIdempotency;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptDocumentVerification;
use App\Models\Admission\AdmissionAttemptRequirementSet;
use App\Models\Admission\AdmissionDocumentCorrectionRequest as DocumentCorrectionRequest;
use App\Models\Admission\ApplicantIdentity;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use App\Notifications\Policies\AdmissionNotificationPolicy;
use App\Notifications\Templates\AdmissionExaminationNoticeContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class RequestAdmissionDocumentCorrection
{
    public function __construct(
        private readonly AttemptRequirementChecklistBoundary $boundary,
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly NotificationIntentDispatcher $dispatcher,
    ) {}

    /**
     * @param  array{expected_version:int,requirement_set_id:int,items:list<array{requirement_item_id:int,expected_latest_verification_id:?int,applicant_instruction:string}>,overall_note:?string,idempotency_key:string}  $input
     * @return array{receipt:DocumentCorrectionRequest,delivery_failed:bool}
     */
    public function handle(AdmissionAttempt $attempt, User $actor, array $input): array
    {
        $normalized = $this->normalize($input);
        $createdDeliveries = collect();

        $request = DB::transaction(function () use ($attempt, $actor, $normalized, &$createdDeliveries): DocumentCorrectionRequest {
            $this->boundary->authorizeFresh($actor, $attempt, CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY);
            $lockedAttempt = $this->boundary->lockAttempt((int) $attempt->getKey());
            $freshActor = $this->boundary->authorizeFresh($actor, $lockedAttempt, CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY);
            $set = AdmissionAttemptRequirementSet::query()
                ->whereKey($normalized['requirement_set_id'])
                ->where('admission_attempt_id', $lockedAttempt->getKey())
                ->firstOrFail();

            $ownedItems = [];
            foreach ($normalized['items'] as $item) {
                $ownedItems[$item['requirement_item_id']] = $this->boundary->ownedItem(
                    $lockedAttempt,
                    $normalized['requirement_set_id'],
                    $item['requirement_item_id'],
                );
            }

            try {
                $operation = $this->idempotency->claim(
                    $freshActor,
                    'admission.document-correction-request',
                    'attempt:'.$lockedAttempt->getKey(),
                    $this->commandPayload($normalized),
                    $normalized['idempotency_key'],
                );
            } catch (RuntimeException $exception) {
                throw new DocumentCorrectionRequestRejected($exception->getMessage(), previous: $exception);
            }
            $operation->refresh();
            $existingRequestId = $operation->payload['document_correction_request_id'] ?? null;
            if ($existingRequestId !== null) {
                return DocumentCorrectionRequest::query()
                    ->whereKey($existingRequestId)
                    ->where('admission_attempt_id', $lockedAttempt->getKey())
                    ->with('items')
                    ->firstOrFail();
            }
            try {
                $set = $this->boundary->currentSet($lockedAttempt, $normalized['requirement_set_id']);
            } catch (ModelNotFoundException) {
                throw new DocumentCorrectionRequestRejected('The document requirement set is no longer current. Refresh and review the checklist.');
            } catch (RuntimeException $exception) {
                throw new DocumentCorrectionRequestRejected($exception->getMessage(), previous: $exception);
            }

            if ((int) $lockedAttempt->version !== $normalized['expected_version']) {
                throw new DocumentCorrectionRequestRejected('Stale admission attempt version; refresh and review the document request.');
            }
            try {
                $this->boundary->assertMutable($lockedAttempt);
            } catch (RuntimeException $exception) {
                throw new DocumentCorrectionRequestRejected($exception->getMessage(), previous: $exception);
            }
            if (! $set->is_current || $set->applicant_track !== $this->boundary->currentTrack($lockedAttempt)) {
                throw new DocumentCorrectionRequestRejected('The document requirement set is no longer current. Refresh and review the checklist.');
            }

            foreach ($normalized['items'] as $item) {
                $ownedItem = $ownedItems[$item['requirement_item_id']];
                $latest = $this->boundary->latestVerification($lockedAttempt, $item['requirement_item_id']);
                if (($latest === null ? null : (int) $latest->getKey()) !== $item['expected_latest_verification_id']) {
                    throw new DocumentCorrectionRequestRejected('Document inspection changed; refresh and review the request.');
                }
                if ($latest === null || ! in_array($latest->status, [
                    AdmissionAttemptDocumentVerification::STATUS_MISSING,
                    AdmissionAttemptDocumentVerification::STATUS_DEFICIENT,
                ], true)) {
                    throw new DocumentCorrectionRequestRejected('Only current Missing or Deficient documents can be requested.');
                }
                if ((int) $ownedItem->requirement_set_id !== (int) $set->getKey()) {
                    throw new DocumentCorrectionRequestRejected('Document requirement item does not belong to the selected set.');
                }
            }
            $recipient = NotificationRecipients::applicantFor($lockedAttempt);
            if (! $recipient instanceof ApplicantIdentity) {
                throw new DocumentCorrectionRequestRejected('Applicant identity is unavailable for this document request.');
            }
            $recordedVersion = (int) $lockedAttempt->version + 1;
            $lockedAttempt->version = $recordedVersion;
            $lockedAttempt->save();

            $request = DocumentCorrectionRequest::query()->create([
                'admission_attempt_id' => $lockedAttempt->getKey(),
                'requirement_set_id' => $set->getKey(),
                'institution_id' => $lockedAttempt->institution_id,
                'recorded_version' => $recordedVersion,
                'academic_cycle_id' => $lockedAttempt->academic_cycle_id,
                'practice_fixture_set_id' => $lockedAttempt->practice_fixture_set_id,
                'requested_by_user_id' => $freshActor->getKey(),
                'expected_version' => $normalized['expected_version'],
                'idempotency_key' => $normalized['idempotency_key'],
                'overall_note' => $normalized['overall_note'],
                'recorded_at' => now(),
            ]);

            foreach ($normalized['items'] as $item) {
                $ownedItem = $ownedItems[$item['requirement_item_id']];
                $latest = $this->boundary->latestVerification($lockedAttempt, $item['requirement_item_id']);
                $request->items()->create([
                    'requirement_item_id' => $ownedItem->getKey(),
                    'expected_latest_verification_id' => $item['expected_latest_verification_id'],
                    'label' => $ownedItem->label,
                    'observed_status' => $latest->status,
                    'applicant_instruction' => $item['applicant_instruction'],
                ]);
            }

            $operation->payload = [...$operation->payload, 'document_correction_request_id' => $request->getKey()];
            $operation->save();
            $this->recordAuditEvent->handle('admission.document_correction_requested', [
                'attempt_id' => $lockedAttempt->getKey(),
                'document_correction_request_id' => $request->getKey(),
                'requirement_set_id' => $set->getKey(),
                'requirement_item_ids' => array_column($normalized['items'], 'requirement_item_id'),
                'item_count' => count($normalized['items']),
                'recorded_version' => $lockedAttempt->version,
            ], (string) $freshActor->getKey());

            if ($recipient instanceof ApplicantIdentity) {
                $policy = AdmissionNotificationPolicy::fromStorage($lockedAttempt->academicCycle?->admission_notification_policy);
                $channels = [NotificationDelivery::CHANNEL_IN_APP];
                if ($policy->applicantStatusEmailEnabled) {
                    $channels[] = NotificationDelivery::CHANNEL_MAIL;
                }
                $snapshot = $request->load('items')->items->map(static fn ($item): array => [
                    'requirement_item_id' => (int) $item->requirement_item_id,
                    'label' => $item->label,
                    'observed_status' => $item->observed_status,
                    'expected_latest_verification_id' => $item->expected_latest_verification_id === null
                        ? null : (int) $item->expected_latest_verification_id,
                    'applicant_instruction' => $item->applicant_instruction,
                ])->values()->all();
                $requestSnapshot = [
                    'document_correction_request_id' => (int) $request->getKey(),
                    'attempt_id' => (int) $lockedAttempt->getKey(),
                    'institution_id' => (int) $request->institution_id,
                    'academic_cycle_id' => (int) $request->academic_cycle_id,
                    'practice_fixture_set_id' => $request->practice_fixture_set_id,
                    'requirement_set_id' => (int) $request->requirement_set_id,
                    'recorded_version' => (int) $request->recorded_version,
                    'overall_note' => $request->overall_note,
                    'items' => $snapshot,
                ];
                $intent = NotificationIntent::v1(
                    eventType: 'admission.document_correction_requested.v1',
                    templateKey: 'admission.document_correction_requested',
                    payload: [
                        ...AdmissionExaminationNoticeContext::admissionPayload($lockedAttempt, 'document-correction-requested'),
                        'subject' => 'Documents need your attention',
                        'body' => $this->notificationBody($snapshot, $request->overall_note),
                        'action_label' => 'Review document requests',
                        'action_url' => AdmissionExaminationNoticeContext::url('applicant.portal'),
                        'attempt_id' => (int) $lockedAttempt->getKey(),
                        'academic_cycle_id' => (int) $lockedAttempt->academic_cycle_id,
                        'document_correction_request_id' => (int) $request->getKey(),
                        'document_request_snapshot' => $requestSnapshot,
                        'practice_fixture_set_id' => $lockedAttempt->practice_fixture_set_id,
                    ],
                    channels: $channels,
                );
                $createdDeliveries = $this->dispatcher->dispatch(
                    $intent,
                    hash('sha256', 'admission.document_correction_requested:'.$request->getKey()),
                    [$recipient],
                    $channels,
                );
            }

            return $request->load('items');
        });

        $deliveryFailed = false;
        if ($createdDeliveries->isNotEmpty()) {
            try {
                NotificationIntentDispatcher::enqueueAfterCommit($createdDeliveries);
            } catch (Throwable) {
                // The request and delivery rows are committed; report the receipt, not a rejection.
                $deliveryFailed = true;
            }
        }

        return ['receipt' => $request, 'delivery_failed' => $deliveryFailed];
    }

    /** @param array<string,mixed> $input
     * @return array{expected_version:int,requirement_set_id:int,items:list<array{requirement_item_id:int,expected_latest_verification_id:?int,applicant_instruction:string}>,overall_note:?string,idempotency_key:string}
     */
    private function normalize(array $input): array
    {
        if (array_diff(array_keys($input), ['expected_version', 'requirement_set_id', 'items', 'overall_note', 'idempotency_key']) !== []) {
            throw new DocumentCorrectionRequestRejected('Document correction requests do not accept recipient, channel, status, or private remark fields.');
        }
        $items = $input['items'] ?? null;
        if (! is_array($items) || ! array_is_list($items) || $items === []) {
            throw new DocumentCorrectionRequestRejected('Select at least one Missing or Deficient document.');
        }
        $normalizedItems = [];
        $seen = [];
        foreach ($items as $item) {
            if (! is_array($item)
                || array_diff(array_keys($item), ['requirement_item_id', 'expected_latest_verification_id', 'applicant_instruction']) !== []
                || ! isset($item['requirement_item_id'])
                || ! array_key_exists('expected_latest_verification_id', $item)) {
                throw new DocumentCorrectionRequestRejected('Each document request must pin an item and its latest inspection.');
            }
            $id = filter_var($item['requirement_item_id'], FILTER_VALIDATE_INT);
            if ($id === false || $id < 1 || isset($seen[$id])) {
                throw new DocumentCorrectionRequestRejected('Document request items must be distinct valid checklist items.');
            }
            $id = (int) $id;
            $seen[$id] = true;
            $instruction = $this->normalizeText($item['applicant_instruction'] ?? null, true);
            $latestId = $item['expected_latest_verification_id'] === null
                ? null : filter_var($item['expected_latest_verification_id'], FILTER_VALIDATE_INT);
            if ($latestId !== null && ($latestId === false || $latestId < 1)) {
                throw new DocumentCorrectionRequestRejected('Expected inspection ID must be positive or null.');
            }
            $normalizedItems[] = [
                'requirement_item_id' => $id,
                'expected_latest_verification_id' => $latestId,
                'applicant_instruction' => $instruction,
            ];
        }
        usort($normalizedItems, static fn (array $left, array $right): int => $left['requirement_item_id'] <=> $right['requirement_item_id']);

        $keyValue = $input['idempotency_key'] ?? null;
        $key = is_string($keyValue) ? trim($keyValue) : '';
        if ($key === '' || mb_strlen($key) > 128) {
            throw new DocumentCorrectionRequestRejected('A valid idempotency key is required.');
        }
        $expectedVersion = filter_var($input['expected_version'] ?? null, FILTER_VALIDATE_INT);
        $requirementSetId = filter_var($input['requirement_set_id'] ?? null, FILTER_VALIDATE_INT);
        if ($expectedVersion === false || $expectedVersion < 0 || $requirementSetId === false || $requirementSetId < 1) {
            throw new DocumentCorrectionRequestRejected('A valid checklist revision and admission version are required.');
        }
        $overallNote = $this->normalizeText($input['overall_note'] ?? null, false);

        return [
            'expected_version' => (int) $expectedVersion,
            'requirement_set_id' => (int) $requirementSetId,
            'items' => $normalizedItems,
            'overall_note' => $overallNote === '' ? null : $overallNote,
            'idempotency_key' => $key,
        ];
    }

    private function normalizeText(mixed $value, bool $required): string
    {
        if ($value !== null && ! is_string($value)) {
            throw new DocumentCorrectionRequestRejected('Document request text must be plain text.');
        }
        $text = is_string($value) ? trim(preg_replace('/\s+/u', ' ', $value) ?? '') : '';
        if ($required && $text === '') {
            throw new DocumentCorrectionRequestRejected('Applicant instructions are required for each selected document.');
        }
        if (mb_strlen($text) > 2000) {
            throw new DocumentCorrectionRequestRejected('Document request text may not exceed 2,000 characters.');
        }

        return $text;
    }

    /** @param array<string,mixed> $normalized
     * @return array<string,mixed>
     */
    private function commandPayload(array $normalized): array
    {
        return [
            'expected_version' => $normalized['expected_version'],
            'requirement_set_id' => $normalized['requirement_set_id'],
            'items' => $normalized['items'],
            'overall_note' => $normalized['overall_note'],
        ];
    }

    /** @param list<array{label:string,applicant_instruction:string}> $items */
    private function notificationBody(array $items, ?string $overallNote): string
    {
        $lines = array_map(static fn (array $item): string => $item['label'].': '.$item['applicant_instruction'], $items);
        if ($overallNote !== null && $overallNote !== '') {
            array_unshift($lines, $overallNote);
        }

        return implode("\n\n", $lines);
    }
}
