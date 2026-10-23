<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\Review\GradingOmrEffectiveAnswerProjector;
use App\Assessment\Omr\Review\GradingOmrReviewContextResolver;
use App\Assessment\Omr\Review\OmrReviewContext;
use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Assessment\Omr\Review\OmrReviewDecisionResult;
use App\Assessment\Omr\Review\OmrReviewDecisionValidator;
use App\Assessment\Omr\Review\VerificationOmrReviewContextResolver;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrItemReviewRevision;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationItemEvidence;
use App\Models\Assessment\OmrTemplateVerificationMethod;
use App\Models\Assessment\OmrTemplateVerificationMethodPage;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Appends one immutable human decision over one server-resolved OMR item.
 *
 * The parent row is the serialization boundary for all item decisions. The
 * context is resolved once by the host and then resolved again after the
 * parent and latest revision are locked, so no caller-provided option,
 * evidence hash, or item identity can become authoritative by itself.
 */
final class RecordOmrItemReviewDecision
{
    public const COMMAND_NAME = 'omr.item_review.record';

    public const AUDIT_EVENT = 'omr.item_review_recorded';

    private const RECEIPT_LEASE_MINUTES = 5;

    private readonly GradingOmrEffectiveAnswerProjector $projector;

    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly VerificationOmrReviewContextResolver $verificationResolver,
        private readonly GradingOmrReviewContextResolver $gradingResolver,
        ?GradingOmrEffectiveAnswerProjector $projector = null,
    ) {
        $this->projector = $projector ?? app(GradingOmrEffectiveAnswerProjector::class);
    }

    /**
     * @throws RuntimeException when authorization, lifecycle, concurrency,
     *                          evidence, decision, or idempotency checks fail
     */
    public function handle(
        OmrReviewContext $context,
        OmrReviewDecision $decision,
        int $expectedParentVersion,
        int $expectedItemRevision,
        string $reviewedEvidenceHash,
        string $idempotencyKey,
        User $actor,
        ?string $selectedOption = null,
        ?string $reasonCode = null,
        ?string $note = null,
    ): OmrReviewDecisionResult {
        $selectedOption = $this->nullableString($selectedOption);
        $reasonCode = $this->nullableString($reasonCode);
        $note = $this->nullableString($note);
        $reviewedEvidenceHash = strtolower(trim($reviewedEvidenceHash));
        $idempotencyKey = trim($idempotencyKey);

        $this->assertInputShape(
            $expectedParentVersion,
            $expectedItemRevision,
            $reviewedEvidenceHash,
            $idempotencyKey,
        );
        $this->assertAuthorized($actor, $context);

        $payloadFingerprint = $this->payloadFingerprint(
            $context,
            $decision,
            $expectedParentVersion,
            $expectedItemRevision,
            $reviewedEvidenceHash,
            $selectedOption,
            $reasonCode,
            $note,
        );
        $aggregate = $this->receiptAggregate($context);

        return DB::transaction(function () use (
            $context,
            $decision,
            $expectedParentVersion,
            $expectedItemRevision,
            $reviewedEvidenceHash,
            $idempotencyKey,
            $actor,
            $selectedOption,
            $reasonCode,
            $note,
            $payloadFingerprint,
            $aggregate,
        ): OmrReviewDecisionResult {
            $parent = $this->lockParent($context);
            $latest = $this->lockLatestRevision($context);
            $receipt = $this->lockReceipt($actor, $aggregate, $idempotencyKey);

            if ($receipt !== null) {
                $this->assertReceiptFingerprint($receipt, $payloadFingerprint);

                if ((string) $receipt->status === 'succeeded') {
                    return $this->resultFromReceipt($receipt, $context, $actor);
                }

                $lease = $receipt->lease_expires_at;
                if ((string) $receipt->status === 'started'
                    && $lease !== null
                    && (string) $lease !== ''
                    && strtotime((string) $lease) > time()) {
                    throw new RuntimeException('omr_review_idempotency_in_progress');
                }

                $receiptId = (int) $receipt->id;
                $correlationId = Str::isUuid((string) $receipt->correlation_id)
                    ? (string) $receipt->correlation_id
                    : (string) Str::uuid();
                $executionToken = (string) Str::uuid();
                DB::table('omr_command_receipts')->where('id', $receiptId)->update([
                    'status' => 'started',
                    'execution_token' => $executionToken,
                    'lease_expires_at' => now()->addMinutes(self::RECEIPT_LEASE_MINUTES),
                    'correlation_id' => $correlationId,
                    'updated_at' => now(),
                ]);
            } else {
                $receiptId = (int) DB::table('omr_command_receipts')->insertGetId([
                    'actor_id' => (int) $actor->getKey(),
                    'command_name' => self::COMMAND_NAME,
                    'aggregate_type' => $aggregate['type'],
                    'aggregate_id' => $aggregate['id'],
                    'idempotency_key' => $idempotencyKey,
                    'payload_fingerprint' => $payloadFingerprint,
                    'status' => 'started',
                    'execution_token' => (string) Str::uuid(),
                    'lease_expires_at' => now()->addMinutes(self::RECEIPT_LEASE_MINUTES),
                    'safe_result_json' => null,
                    'correlation_id' => $correlationId = (string) Str::uuid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $current = $this->resolveLockedContext($context, $parent);
            $this->assertAuthorized($actor, $current);
            $this->assertCurrentContext(
                $context,
                $current,
                $latest,
                $expectedParentVersion,
                $expectedItemRevision,
                $reviewedEvidenceHash,
            );

            [$selectedOption, $reasonCode, $note] = $this->validatedDecision(
                $current,
                $decision,
                $selectedOption,
                $reasonCode,
                $note,
            );

            $nextParentVersion = $current->parentVersion + 1;
            $nextItemRevision = $current->itemRevision + 1;
            $revision = OmrItemReviewRevision::query()->create([
                'verification_item_evidence_id' => $current->verificationItemEvidenceId,
                'capture_sheet_id' => $current->isGrading() ? $current->parentId : null,
                'item_key' => $current->itemKey,
                'revision' => $nextItemRevision,
                'supersedes_revision_id' => $latest?->getKey(),
                'decision' => $decision,
                'selected_option' => $selectedOption,
                'reason_code' => $reasonCode,
                'note' => $note,
                'reviewed_machine_evidence_hash' => $reviewedEvidenceHash,
                'parent_version' => $nextParentVersion,
                'reviewed_by_user_id' => (int) $actor->getKey(),
                'command_name' => self::COMMAND_NAME,
                'idempotency_key' => $idempotencyKey,
                'correlation_id' => $correlationId,
            ]);

            $this->incrementParentVersion($current->parent, $nextParentVersion, $current->isVerification());
            if ($current->isGrading() && $current->parent instanceof OmrCaptureSheet) {
                $this->projector->reconcileAndRegenerate($current->parent, $current->itemKey, $decision, $actor);
            }

            $result = new OmrReviewDecisionResult(
                revision: $revision,
                parentVersion: $nextParentVersion,
                itemRevision: $nextItemRevision,
                correlationId: $correlationId,
            );

            $this->recordAuditEvent->handle(
                self::AUDIT_EVENT,
                [
                    'context_type' => $current->type,
                    'parent_id' => $current->parentId,
                    'item_key' => $current->itemKey,
                    'revision_id' => (int) $revision->getKey(),
                    'revision' => $nextItemRevision,
                    'decision' => $decision->value,
                    'selected_option' => $selectedOption,
                    'reason_code' => $reasonCode,
                    'reviewed_machine_evidence_hash' => $reviewedEvidenceHash,
                    'prior_parent_version' => $expectedParentVersion,
                    'parent_version' => $nextParentVersion,
                    'prior_item_revision' => $expectedItemRevision,
                    'item_revision' => $nextItemRevision,
                    'note_present' => $note !== null,
                    'correlation_id' => $correlationId,
                ],
                (string) $actor->getKey(),
            );

            DB::table('omr_command_receipts')->where('id', $receiptId)->update([
                'status' => 'succeeded',
                'lease_expires_at' => null,
                'safe_result_json' => json_encode($result->toSafeArray(), JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

            return $result;
        });
    }

    private function assertInputShape(
        int $expectedParentVersion,
        int $expectedItemRevision,
        string $reviewedEvidenceHash,
        string $idempotencyKey,
    ): void {
        if ($expectedParentVersion < 0) {
            throw new RuntimeException('omr_review_parent_version_invalid');
        }
        if ($expectedItemRevision < 0) {
            throw new RuntimeException('omr_review_item_revision_invalid');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $reviewedEvidenceHash) !== 1) {
            throw new RuntimeException('omr_review_evidence_hash_invalid');
        }
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 255) {
            throw new RuntimeException('omr_review_idempotency_key_invalid');
        }
    }

    private function assertAuthorized(User $actor, OmrReviewContext $context): void
    {
        $decision = $this->authorizer->decide($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR, $context->resourceScope);
        if (! $decision->allowed) {
            if ($decision->reason === 'scope_mismatch') {
                throw new ModelNotFoundException('Resource out of scope.');
            }
            throw new RuntimeException('omr_review_denied');
        }
    }

    /**
     * @return array{type:string,id:string}
     */
    private function receiptAggregate(OmrReviewContext $context): array
    {
        $id = $context->parentId.':'.$context->itemKey;
        if (strlen($id) > 100) {
            $id = hash('sha256', json_encode($context->aggregateIdentity(), JSON_THROW_ON_ERROR));
        }

        return ['type' => $context->type, 'id' => $id];
    }

    private function lockReceipt(User $actor, array $aggregate, string $idempotencyKey): ?object
    {
        return DB::table('omr_command_receipts')
            ->where('actor_id', (int) $actor->getKey())
            ->where('command_name', self::COMMAND_NAME)
            ->where('aggregate_type', $aggregate['type'])
            ->where('aggregate_id', $aggregate['id'])
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();
    }

    private function assertReceiptFingerprint(object $receipt, string $payloadFingerprint): void
    {
        if (! hash_equals((string) $receipt->payload_fingerprint, $payloadFingerprint)) {
            throw new RuntimeException('omr_review_idempotency_conflict');
        }
    }

    private function resultFromReceipt(object $receipt, OmrReviewContext $context, User $actor): OmrReviewDecisionResult
    {
        $safeResult = json_decode((string) $receipt->safe_result_json, true);
        if (! is_array($safeResult)) {
            throw new RuntimeException('omr_review_receipt_invalid');
        }

        $expectedContextType = $context->type;
        $expectedParentId = $context->isVerification()
            ? (int) $context->verificationItemEvidenceId
            : $context->parentId;
        if (($safeResult['context_type'] ?? null) !== $expectedContextType
            || (int) ($safeResult['parent_id'] ?? 0) !== $expectedParentId
            || (string) ($safeResult['item_key'] ?? '') !== $context->itemKey) {
            throw new RuntimeException('omr_review_receipt_invalid');
        }

        $revisionId = (int) ($safeResult['revision_id'] ?? 0);
        $revision = OmrItemReviewRevision::query()->find($revisionId);
        if (! $revision instanceof OmrItemReviewRevision
            || (int) $revision->reviewed_by_user_id !== (int) $actor->getKey()
            || (string) $revision->item_key !== $context->itemKey
            || ($context->isVerification()
                ? (int) $revision->verification_item_evidence_id !== (int) $context->verificationItemEvidenceId
                : (int) $revision->capture_sheet_id !== $context->parentId)) {
            throw new RuntimeException('omr_review_receipt_invalid');
        }

        $correlationId = (string) ($safeResult['correlation_id'] ?? '');
        if (! Str::isUuid($correlationId)) {
            throw new RuntimeException('omr_review_receipt_invalid');
        }

        return new OmrReviewDecisionResult(
            revision: $revision,
            parentVersion: (int) ($safeResult['parent_version'] ?? 0),
            itemRevision: (int) ($safeResult['revision'] ?? 0),
            correlationId: $correlationId,
        );
    }

    private function lockParent(OmrReviewContext $context): OmrTemplateVerificationCapture|OmrCaptureSheet
    {
        if ($context->isVerification()) {
            $runId = (int) ($context->resourceScope['verification_run_id'] ?? 0);
            if ($runId < 1) {
                throw new RuntimeException('omr_review_context_changed');
            }
            $run = OmrTemplateVerificationRun::query()->lockForUpdate()->findOrFail($runId);
            $candidate = OmrTemplateVerificationCapture::query()->findOrFail($context->parentId);
            if ((int) $candidate->run_id !== (int) $run->getKey()) {
                throw new RuntimeException('omr_review_context_changed');
            }
            OmrTemplateVerificationMethod::query()
                ->whereKey((int) $candidate->method_id)
                ->where('run_id', (int) $candidate->run_id)
                ->lockForUpdate()
                ->firstOrFail();
            OmrTemplateVerificationMethodPage::query()
                ->where('method_id', (int) $candidate->method_id)
                ->where('page_no', (int) $candidate->page_no)
                ->lockForUpdate()
                ->firstOrFail();

            return OmrTemplateVerificationCapture::query()
                ->lockForUpdate()
                ->findOrFail($context->parentId);
        }

        return OmrCaptureSheet::query()
            ->lockForUpdate()
            ->findOrFail($context->parentId);
    }

    private function lockLatestRevision(OmrReviewContext $context): ?OmrItemReviewRevision
    {
        $query = OmrItemReviewRevision::query()->orderByDesc('revision')->lockForUpdate();

        if ($context->isVerification()) {
            return $query
                ->forVerificationItem((int) $context->verificationItemEvidenceId)
                ->where('item_key', $context->itemKey)
                ->first();
        }

        return $query
            ->forGradingItem($context->parentId, $context->itemKey)
            ->first();
    }

    private function resolveLockedContext(
        OmrReviewContext $context,
        OmrTemplateVerificationCapture|OmrCaptureSheet $parent,
    ): OmrReviewContext {
        if ($context->isVerification()) {
            if (! $parent instanceof OmrTemplateVerificationCapture || $context->verificationItemEvidenceId === null) {
                throw new RuntimeException('omr_review_context_changed');
            }
            $parent->loadMissing('run.draft', 'run.templateVersion');

            $item = OmrTemplateVerificationItemEvidence::query()
                ->whereKey($context->verificationItemEvidenceId)
                ->where('capture_id', $parent->getKey())
                ->first();
            if (! $item instanceof OmrTemplateVerificationItemEvidence) {
                throw (new ModelNotFoundException)->setModel(
                    OmrTemplateVerificationItemEvidence::class,
                    [(string) $context->verificationItemEvidenceId],
                );
            }

            return $this->verificationResolver->resolveLocked($parent, $item);
        }

        if (! $parent instanceof OmrCaptureSheet) {
            throw new RuntimeException('omr_review_context_changed');
        }

        $parent->loadMissing([
            'batch.gradingSession.examSession.academicCycle',
            'batch.bookletVersion.questions.aptitudeArea',
            'batch.bookletVersion.questions.templateQuestion.options',
        ]);

        return $this->gradingResolver->resolveLocked($parent, $context->itemKey);
    }

    private function assertCurrentContext(
        OmrReviewContext $initial,
        OmrReviewContext $current,
        ?OmrItemReviewRevision $latest,
        int $expectedParentVersion,
        int $expectedItemRevision,
        string $reviewedEvidenceHash,
    ): void {
        if ($current->type !== $initial->type
            || $current->parentId !== $initial->parentId
            || $current->itemKey !== $initial->itemKey) {
            throw new RuntimeException('omr_review_context_changed');
        }
        if ($current->optionSet !== $initial->optionSet) {
            throw new RuntimeException('omr_review_option_set_stale');
        }
        if (! $current->reviewable || $current->lifecycle !== 'reviewable') {
            throw new RuntimeException('omr_review_not_reviewable');
        }
        if ($current->parentVersion !== $expectedParentVersion) {
            throw new RuntimeException('omr_review_parent_version_stale');
        }
        if ($current->itemRevision !== $expectedItemRevision
            || ($latest?->revision ?? 0) !== $expectedItemRevision) {
            throw new RuntimeException('omr_review_item_revision_stale');
        }
        if (! hash_equals(strtolower($current->evidenceHash), $reviewedEvidenceHash)) {
            throw new RuntimeException('omr_review_evidence_hash_mismatch');
        }
    }

    /**
     * @return array{0:string|null,1:string|null,2:string|null}
     */
    private function validatedDecision(
        OmrReviewContext $context,
        OmrReviewDecision $decision,
        ?string $selectedOption,
        ?string $reasonCode,
        ?string $note,
    ): array {
        return OmrReviewDecisionValidator::validate(
            $context->machineState,
            $context->machineSelectedOption,
            $context->optionSet,
            $decision,
            $selectedOption,
            $reasonCode,
            $note,
        );
    }

    private function incrementParentVersion(
        Model $parent,
        int $nextVersion,
        bool $verification,
    ): void {
        $field = $verification ? 'lock_version' : 'version';
        $parent->forceFill([$field => $nextVersion])->saveOrFail();
    }

    private function payloadFingerprint(
        OmrReviewContext $context,
        OmrReviewDecision $decision,
        int $expectedParentVersion,
        int $expectedItemRevision,
        string $reviewedEvidenceHash,
        ?string $selectedOption,
        ?string $reasonCode,
        ?string $note,
    ): string {
        return hash('sha256', json_encode([
            'context' => $context->aggregateIdentity(),
            'decision' => $decision->value,
            'selected_option' => $selectedOption,
            'reason_code' => $reasonCode,
            'note' => $note,
            'expected_parent_version' => $expectedParentVersion,
            'expected_item_revision' => $expectedItemRevision,
            'reviewed_machine_evidence_hash' => $reviewedEvidenceHash,
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
