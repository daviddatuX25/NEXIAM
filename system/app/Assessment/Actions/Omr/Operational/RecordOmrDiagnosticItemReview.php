<?php

declare(strict_types=1);

namespace App\Assessment\Actions\Omr\Operational;

use App\Assessment\Omr\Operational\OmrDiagnosticEffectiveAnswerProjector;
use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Assessment\Omr\Review\OmrReviewDecisionValidator;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticItemEvidence;
use App\Models\Assessment\OmrDiagnosticReviewRevision;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

use function hash;
use function hash_equals;
use function in_array;
use function is_array;
use function is_numeric;
use function json_decode;
use function json_encode;
use function preg_match;
use function strtolower;
use function trim;

/**
 * Append one immutable human interpretation of diagnostic machine evidence.
 *
 * Run, capture, evidence, and latest-review rows are the serialization
 * boundary. The caller's versions and evidence hash are checked only after
 * those rows are locked, so a concurrent stale review cannot become current.
 */
final class RecordOmrDiagnosticItemReview
{
    public const COMMAND_NAME = 'omr.diagnostic.item_review.record';

    public const AUDIT_EVENT = 'omr.diagnostic.item_review_recorded';

    private const RECEIPT_LEASE_MINUTES = 5;

    private const AGGREGATE_TYPE = 'omr_diagnostic_item_evidence';

    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly OmrDiagnosticEffectiveAnswerProjector $projector,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException when authorization, lifecycle, concurrency,
     *                          evidence, decision, or idempotency checks fail
     */
    public function handle(
        OmrDiagnosticItemEvidence $itemEvidence,
        OmrReviewDecision $decision,
        int $expectedRunRevision,
        int $expectedCaptureVersion,
        int $expectedItemRevision,
        int $expectedEvidenceVersion,
        string $reviewedEvidenceHash,
        string $idempotencyKey,
        User $actor,
        ?string $finalOption = null,
        ?string $reason = null,
        ?string $note = null,
    ): array {
        $reviewedEvidenceHash = strtolower(trim($reviewedEvidenceHash));
        $idempotencyKey = trim($idempotencyKey);
        $finalOption = $this->nullableString($finalOption);
        $reason = $this->nullableString($reason);
        $note = $this->nullableString($note);
        if ($finalOption !== null && mb_strlen($finalOption) > 32) {
            throw new RuntimeException('omr_diagnostic_review_option_invalid');
        }

        $this->assertInputShape(
            $expectedRunRevision,
            $expectedCaptureVersion,
            $expectedItemRevision,
            $expectedEvidenceVersion,
            $reviewedEvidenceHash,
            $idempotencyKey,
        );

        $itemEvidence->loadMissing('capture.run');
        $initialCapture = $itemEvidence->capture;
        $initialRun = $initialCapture?->run;
        if (! $initialCapture instanceof OmrDiagnosticCapture || ! $initialRun instanceof OmrDiagnosticRun) {
            throw new ModelNotFoundException('Diagnostic evidence is no longer available.');
        }
        $this->assertDiagnosticRun($initialRun);
        $this->assertAuthorized($actor, $initialRun);

        $payloadFingerprint = $this->payloadFingerprint(
            $itemEvidence,
            $decision,
            $expectedRunRevision,
            $expectedCaptureVersion,
            $expectedItemRevision,
            $expectedEvidenceVersion,
            $reviewedEvidenceHash,
            $finalOption,
            $reason,
            $note,
        );

        return DB::transaction(function () use (
            $itemEvidence,
            $decision,
            $expectedRunRevision,
            $expectedCaptureVersion,
            $expectedItemRevision,
            $expectedEvidenceVersion,
            $reviewedEvidenceHash,
            $idempotencyKey,
            $actor,
            $finalOption,
            $reason,
            $note,
            $payloadFingerprint,
        ): array {
            // Lock in parent-to-child order. Every diagnostic mutation uses the
            // same order, preventing two item reviews from deadlocking.
            $initialRunId = (int) ($itemEvidence->capture?->diagnostic_run_id ?? 0);
            $run = OmrDiagnosticRun::query()->lockForUpdate()->findOrFail($initialRunId);
            $capture = OmrDiagnosticCapture::query()
                ->lockForUpdate()
                ->findOrFail((int) $itemEvidence->diagnostic_capture_id);
            $item = OmrDiagnosticItemEvidence::query()
                ->lockForUpdate()
                ->findOrFail((int) $itemEvidence->getKey());

            $this->assertDiagnosticRun($run);
            if ((int) $capture->diagnostic_run_id !== (int) $run->getKey()
                || (int) $item->diagnostic_capture_id !== (int) $capture->getKey()) {
                throw new RuntimeException('omr_diagnostic_review_context_changed');
            }
            $this->assertAuthorized($actor, $run);
            if ($run->status === OmrDiagnosticRun::STATUS_CANCELLED) {
                throw new RuntimeException('omr_diagnostic_run_not_reviewable');
            }

            $latest = OmrDiagnosticReviewRevision::query()
                ->where('diagnostic_item_evidence_id', (int) $item->getKey())
                ->orderByDesc('revision')
                ->lockForUpdate()
                ->first();
            $receipt = $this->lockReceipt($actor, (int) $item->getKey(), $idempotencyKey);

            if ($receipt !== null) {
                $this->assertReceiptFingerprint($receipt, $payloadFingerprint);
                if ((string) $receipt->status === 'succeeded') {
                    return $this->resultFromReceipt($receipt, $run, $capture, $item, $actor);
                }

                if ((string) $receipt->status === 'started'
                    && $receipt->lease_expires_at !== null
                    && strtotime((string) $receipt->lease_expires_at) > time()) {
                    throw new RuntimeException('omr_diagnostic_review_idempotency_in_progress');
                }

                $receiptId = (int) $receipt->id;
                $correlationId = Str::isUuid((string) $receipt->correlation_id)
                    ? (string) $receipt->correlation_id
                    : (string) Str::uuid();
                DB::table('omr_command_receipts')->where('id', $receiptId)->update([
                    'status' => 'started',
                    'execution_token' => (string) Str::uuid(),
                    'lease_expires_at' => now()->addMinutes(self::RECEIPT_LEASE_MINUTES),
                    'correlation_id' => $correlationId,
                    'updated_at' => now(),
                ]);
            } else {
                $correlationId = (string) Str::uuid();
                $receiptId = (int) DB::table('omr_command_receipts')->insertGetId([
                    'actor_id' => (int) $actor->getKey(),
                    'command_name' => self::COMMAND_NAME,
                    'aggregate_type' => self::AGGREGATE_TYPE,
                    'aggregate_id' => (string) $item->getKey(),
                    'idempotency_key' => $idempotencyKey,
                    'payload_fingerprint' => $payloadFingerprint,
                    'status' => 'started',
                    'execution_token' => (string) Str::uuid(),
                    'lease_expires_at' => now()->addMinutes(self::RECEIPT_LEASE_MINUTES),
                    'safe_result_json' => null,
                    'correlation_id' => $correlationId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->assertCurrentContext(
                $run,
                $capture,
                $item,
                $latest,
                $expectedRunRevision,
                $expectedCaptureVersion,
                $expectedItemRevision,
                $expectedEvidenceVersion,
                $reviewedEvidenceHash,
            );
            [$finalOption, $reason, $note] = $this->validatedDecision(
                $item,
                $decision,
                $finalOption,
                $reason,
                $note,
            );

            $nextRunRevision = (int) $run->revision + 1;
            $nextCaptureVersion = (int) $capture->version + 1;
            $nextItemRevision = (int) ($latest?->revision ?? 0) + 1;
            $revision = OmrDiagnosticReviewRevision::query()->create([
                'diagnostic_item_evidence_id' => (int) $item->getKey(),
                'revision' => $nextItemRevision,
                'parent_revision' => $nextCaptureVersion,
                'decision' => $decision,
                'final_option' => $finalOption,
                'reason' => $reason,
                'note' => $note,
                'reviewed_evidence_hash' => $reviewedEvidenceHash,
                'actor_id' => (int) $actor->getKey(),
                'idempotency_key' => $idempotencyKey,
            ]);

            $capture->forceFill([
                'status' => OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
                'version' => $nextCaptureVersion,
                'projected_score_json' => null,
            ])->saveOrFail();
            $run->forceFill([
                'status' => OmrDiagnosticRun::STATUS_NEEDS_REVIEW,
                'phase' => OmrDiagnosticRun::PHASE_REVIEW,
                'revision' => $nextRunRevision,
            ])->saveOrFail();

            $effective = $this->projector->project($capture);
            $result = [
                'outcome' => 'succeeded',
                'run_id' => (int) $run->getKey(),
                'capture_id' => (int) $capture->getKey(),
                'item_evidence_id' => (int) $item->getKey(),
                'item_key' => (string) $item->item_key,
                'revision' => $nextItemRevision,
                'run_revision' => $nextRunRevision,
                'capture_version' => $nextCaptureVersion,
                'item_revision' => $nextItemRevision,
                'reviewed_evidence_hash' => $reviewedEvidenceHash,
                'review' => $revision->toSafeArray(),
                'effective' => $effective,
                'receipt' => [
                    'command_name' => self::COMMAND_NAME,
                    'aggregate_type' => self::AGGREGATE_TYPE,
                    'aggregate_id' => (string) $item->getKey(),
                    'revision_id' => (int) $revision->getKey(),
                    'revision' => $nextItemRevision,
                    'item_revision' => $nextItemRevision,
                    'parent_revision' => $nextCaptureVersion,
                    'run_revision' => $nextRunRevision,
                    'capture_version' => $nextCaptureVersion,
                    'correlation_id' => $correlationId,
                ],
            ];

            $this->recordAuditEvent->handle(
                self::AUDIT_EVENT,
                [
                    'run_id' => (int) $run->getKey(),
                    'capture_id' => (int) $capture->getKey(),
                    'item_evidence_id' => (int) $item->getKey(),
                    'item_key' => (string) $item->item_key,
                    'revision_id' => (int) $revision->getKey(),
                    'decision' => $decision->value,
                    'final_option' => $finalOption,
                    'reason_present' => $reason !== null,
                    'note_present' => $note !== null,
                    'reviewed_evidence_hash' => $reviewedEvidenceHash,
                    'prior_run_revision' => $expectedRunRevision,
                    'run_revision' => $nextRunRevision,
                    'prior_capture_version' => $expectedCaptureVersion,
                    'capture_version' => $nextCaptureVersion,
                    'prior_item_revision' => $expectedItemRevision,
                    'item_revision' => $nextItemRevision,
                    'evidence_version' => $expectedEvidenceVersion,
                    'correlation_id' => $correlationId,
                ],
                (string) $actor->getKey(),
            );

            DB::table('omr_command_receipts')->where('id', $receiptId)->update([
                'status' => 'succeeded',
                'lease_expires_at' => null,
                'safe_result_json' => json_encode($result, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

            return $result;
        });
    }

    private function assertInputShape(
        int $expectedRunRevision,
        int $expectedCaptureVersion,
        int $expectedItemRevision,
        int $expectedEvidenceVersion,
        string $reviewedEvidenceHash,
        string $idempotencyKey,
    ): void {
        if ($expectedRunRevision < 1) {
            throw new RuntimeException('omr_diagnostic_run_revision_invalid');
        }
        if ($expectedCaptureVersion < 0) {
            throw new RuntimeException('omr_diagnostic_capture_version_invalid');
        }
        if ($expectedItemRevision < 0) {
            throw new RuntimeException('omr_diagnostic_item_revision_invalid');
        }
        if ($expectedEvidenceVersion < 1) {
            throw new RuntimeException('omr_diagnostic_evidence_version_invalid');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $reviewedEvidenceHash) !== 1) {
            throw new RuntimeException('omr_diagnostic_evidence_hash_invalid');
        }
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 255) {
            throw new RuntimeException('omr_diagnostic_review_idempotency_key_invalid');
        }
    }

    private function assertDiagnosticRun(OmrDiagnosticRun $run): void
    {
        if ((string) $run->execution_kind !== OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC) {
            throw new RuntimeException('omr_diagnostic_execution_kind_invalid');
        }
    }

    private function assertAuthorized(User $actor, OmrDiagnosticRun $run): void
    {
        if ((int) $run->created_by_user_id !== (int) $actor->getKey()) {
            throw new RuntimeException('omr_diagnostic_owner_mismatch');
        }

        $scope = ['institution_id' => (int) $run->institution_id];
        $run->loadMissing('academicCycle');
        $cycle = $run->academicCycle;
        if ($cycle !== null && trim((string) $cycle->code) !== '') {
            $scope['cycle'] = (string) $cycle->code;
        }

        $decision = $this->authorizer->decide(
            $actor,
            CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
            $scope,
        );
        if (! $decision->allowed) {
            if ($decision->reason === 'scope_mismatch') {
                throw new ModelNotFoundException('Diagnostic run is out of scope.');
            }
            throw new RuntimeException('omr_diagnostic_review_denied');
        }
    }

    private function assertCurrentContext(
        OmrDiagnosticRun $run,
        OmrDiagnosticCapture $capture,
        OmrDiagnosticItemEvidence $item,
        ?OmrDiagnosticReviewRevision $latest,
        int $expectedRunRevision,
        int $expectedCaptureVersion,
        int $expectedItemRevision,
        int $expectedEvidenceVersion,
        string $reviewedEvidenceHash,
    ): void {
        if ($run->isTerminal()) {
            throw new RuntimeException('omr_diagnostic_run_not_reviewable');
        }
        if ((int) $run->revision !== $expectedRunRevision) {
            throw new RuntimeException('omr_diagnostic_run_revision_stale');
        }
        if ((int) $capture->version !== $expectedCaptureVersion) {
            throw new RuntimeException('omr_diagnostic_capture_version_stale');
        }
        if ((int) ($latest?->revision ?? 0) !== $expectedItemRevision) {
            throw new RuntimeException('omr_diagnostic_item_revision_stale');
        }
        if ($this->evidenceVersion($item) !== $expectedEvidenceVersion) {
            throw new RuntimeException('omr_diagnostic_evidence_version_stale');
        }
        if (! hash_equals(strtolower(trim((string) $item->evidence_hash)), $reviewedEvidenceHash)) {
            throw new RuntimeException('omr_diagnostic_evidence_hash_mismatch');
        }
        if (! in_array($capture->status, [
            OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
            OmrDiagnosticCapture::STATUS_REVIEWED,
        ], true)) {
            throw new RuntimeException('omr_diagnostic_capture_not_reviewable');
        }
    }

    /**
     * @return array{0:string|null,1:string|null,2:string|null}
     */
    private function validatedDecision(
        OmrDiagnosticItemEvidence $item,
        OmrReviewDecision $decision,
        ?string $finalOption,
        ?string $reason,
        ?string $note,
    ): array {
        $machine = $this->machineProposal($item);

        return OmrReviewDecisionValidator::validate(
            $machine['state'],
            $machine['selected_option'],
            $this->declaredOptions($item),
            $decision,
            $finalOption,
            $reason,
            $note,
            'omr_diagnostic_review',
        );
    }

    /**
     * @return array{state:string, selected_option:string|null}
     */
    private function machineProposal(OmrDiagnosticItemEvidence $item): array
    {
        $proposal = is_array($item->machine_proposal_json) ? $item->machine_proposal_json : [];
        $detected = is_array($proposal['detected'] ?? null) ? $proposal['detected'] : [];
        $state = strtolower(trim((string) ($proposal['state'] ?? $proposal['status'] ?? $detected['state'] ?? '')));
        $selected = $proposal['selected_option'] ?? ($detected['selected_option'] ?? null);
        if (! is_string($selected) && ! is_numeric($selected)) {
            $selected = $item->option_code;
        }
        $selected = $this->nullableString($selected !== null ? (string) $selected : null);
        if ($state === '' && $selected !== null) {
            $state = 'marked';
        }
        if (! in_array($state, ['marked', 'blank', 'ambiguous', 'unreadable', 'unresolved'], true)) {
            $state = $selected !== null ? 'marked' : 'unresolved';
        }
        if ($state !== 'marked') {
            $selected = null;
        }

        return ['state' => $state, 'selected_option' => $selected];
    }

    /**
     * @return list<string>
     */
    private function declaredOptions(OmrDiagnosticItemEvidence $item): array
    {
        $values = [];
        $proposal = is_array($item->machine_proposal_json) ? $item->machine_proposal_json : [];
        foreach (['options', 'option_codes', 'observed_options'] as $field) {
            $candidate = $proposal[$field] ?? null;
            if (! is_array($candidate)) {
                continue;
            }
            foreach ($candidate as $option) {
                if (is_string($option) || is_numeric($option)) {
                    $value = $this->nullableString((string) $option);
                    if ($value !== null) {
                        $values[] = $value;
                    }
                } elseif (is_array($option)) {
                    $code = $option['code'] ?? ($option['option'] ?? null);
                    if (is_string($code) || is_numeric($code)) {
                        $value = $this->nullableString((string) $code);
                        if ($value !== null) {
                            $values[] = $value;
                        }
                    }
                }
            }
        }
        $optionEvidence = is_array($item->option_evidence_json) ? $item->option_evidence_json : [];
        foreach ($optionEvidence as $option) {
            if (is_array($option)) {
                $code = $option['code'] ?? ($option['option'] ?? null);
                if (is_string($code) || is_numeric($code)) {
                    $value = $this->nullableString((string) $code);
                    if ($value !== null) {
                        $values[] = $value;
                    }
                }
            }
        }

        return array_values(array_unique($values));
    }

    private function evidenceVersion(OmrDiagnosticItemEvidence $item): int
    {
        $value = trim((string) $item->evidence_version);
        if (preg_match('/^v?(\d+)$/i', $value, $matches) !== 1 || (int) $matches[1] < 1) {
            throw new RuntimeException('omr_diagnostic_evidence_version_invalid');
        }

        return (int) $matches[1];
    }

    private function lockReceipt(User $actor, int $itemId, string $idempotencyKey): ?object
    {
        return DB::table('omr_command_receipts')
            ->where('actor_id', (int) $actor->getKey())
            ->where('command_name', self::COMMAND_NAME)
            ->where('aggregate_type', self::AGGREGATE_TYPE)
            ->where('aggregate_id', (string) $itemId)
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();
    }

    private function assertReceiptFingerprint(object $receipt, string $payloadFingerprint): void
    {
        if (! hash_equals((string) $receipt->payload_fingerprint, $payloadFingerprint)) {
            throw new RuntimeException('omr_diagnostic_review_idempotency_conflict');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resultFromReceipt(
        object $receipt,
        OmrDiagnosticRun $run,
        OmrDiagnosticCapture $capture,
        OmrDiagnosticItemEvidence $item,
        User $actor,
    ): array {
        $result = json_decode((string) $receipt->safe_result_json, true);
        if (! is_array($result)
            || (int) ($result['run_id'] ?? 0) !== (int) $run->getKey()
            || (int) ($result['capture_id'] ?? 0) !== (int) $capture->getKey()
            || (int) ($result['item_evidence_id'] ?? 0) !== (int) $item->getKey()
            || (string) ($result['item_key'] ?? '') !== (string) $item->item_key) {
            throw new RuntimeException('omr_diagnostic_review_receipt_invalid');
        }
        $reviewId = (int) data_get($result, 'receipt.revision_id', 0);
        $review = $reviewId > 0 ? OmrDiagnosticReviewRevision::query()->find($reviewId) : null;
        if (! $review instanceof OmrDiagnosticReviewRevision
            || (int) $review->diagnostic_item_evidence_id !== (int) $item->getKey()
            || (int) $review->actor_id !== (int) $actor->getKey()) {
            throw new RuntimeException('omr_diagnostic_review_receipt_invalid');
        }

        return $result;
    }

    private function payloadFingerprint(
        OmrDiagnosticItemEvidence $item,
        OmrReviewDecision $decision,
        int $expectedRunRevision,
        int $expectedCaptureVersion,
        int $expectedItemRevision,
        int $expectedEvidenceVersion,
        string $reviewedEvidenceHash,
        ?string $finalOption,
        ?string $reason,
        ?string $note,
    ): string {
        return hash('sha256', json_encode([
            'item_evidence_id' => (int) $item->getKey(),
            'decision' => $decision->value,
            'final_option' => $finalOption,
            'reason' => $reason,
            'note' => $note,
            'expected_run_revision' => $expectedRunRevision,
            'expected_capture_version' => $expectedCaptureVersion,
            'expected_item_revision' => $expectedItemRevision,
            'expected_evidence_version' => $expectedEvidenceVersion,
            'reviewed_evidence_hash' => $reviewedEvidenceHash,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function nullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
