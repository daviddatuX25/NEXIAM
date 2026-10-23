<?php

declare(strict_types=1);

namespace App\ResultSheet\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Records physical print confirmations and corrections as immutable receipts.
 */
final class MarkResultSheetsPrinted
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly ConfirmPhysicalPrintAction $confirmPhysicalPrintAction,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * @param  list<int>  $documentIds
     */
    public function handle(
        User $actor,
        array $documentIds,
        bool $printed,
        ?string $reason = null,
        ?string $idempotencyKey = null,
    ): int {
        $canMark = $this->authorizer->allowsAny($actor, [
            CapabilityCatalog::GUIDANCE_RESULTS_RELEASE,
            CapabilityCatalog::GUIDANCE_RESULTS_PREPARE,
        ], []);
        if (! $canMark) {
            throw new RuntimeException('The result release or result prepare capability is required to manage printed status.');
        }

        $documentIds = array_values(array_unique(array_map('intval', $documentIds)));
        if ($documentIds === []) {
            return 0;
        }

        $documents = ResultDocument::query()->whereIn('id', $documentIds)->get()->keyBy('id');
        if ($documents->count() !== count($documentIds)) {
            throw new RuntimeException('One or more selected result documents do not exist.');
        }

        if (! $printed && trim((string) $reason) === '') {
            $reason = 'Unmarked by staff';
        }

        $reason = $reason ?: 'Unmarked by staff';

        $idempotencyKey ??= (string) Str::uuid();
        $processed = 0;

        foreach ($documentIds as $documentId) {
            $document = $documents->get($documentId);
            $receiptKey = hash('sha256', $idempotencyKey.'|'.$documentId.'|'.($printed ? 'confirmed' : 'corrected'));
            $existingReceipt = ResultDocumentPrintReceipt::query()
                ->where('idempotency_key', $receiptKey)
                ->first();
            if ($existingReceipt !== null) {
                if (
                    (int) $existingReceipt->result_document_id !== $documentId
                    || (int) $existingReceipt->confirmed_by_user_id !== (int) $actor->id
                    || $existingReceipt->operation !== ($printed ? 'confirmed' : 'corrected')
                ) {
                    throw new RuntimeException('The print command key was already used for another document or operation.');
                }

                $processed++;

                continue;
            }

            if ($printed) {
                $this->confirmPhysicalPrintAction->confirmPrint($document, $actor, $receiptKey);
                $processed++;

                continue;
            }

            $receipt = ResultDocumentPrintReceipt::query()
                ->where('result_document_id', $documentId)
                ->where('is_current', true)
                ->where('operation', 'confirmed')
                ->latest('id')
                ->first();

            if ($receipt !== null) {
                $this->confirmPhysicalPrintAction->correctPrintConfirmation($receipt, $actor, (string) $reason, $receiptKey);
                $processed++;
            }
        }
        $admissionAttemptIds = $documents
            ->pluck('admission_attempt_id')
            ->filter()
            ->unique()
            ->values();
        $releaseQuery = ResultRelease::query()
            ->whereIn('result_document_id', $documentIds)
            ->orWhereIn('admission_attempt_id', $admissionAttemptIds);
        $releaseQuery->update(['result_printed_at' => $printed ? now() : null]);

        foreach ($releaseQuery->get() as $release) {
            $this->recordAuditEvent->handle('release.result_printed_toggled', [
                'release_id' => $release->id,
                'printed' => $printed,
            ], (string) $actor->id);
        }

        return $processed;
    }
}
