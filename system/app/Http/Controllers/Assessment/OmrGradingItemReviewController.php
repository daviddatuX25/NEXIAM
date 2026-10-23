<?php

namespace App\Http\Controllers\Assessment;

use App\Assessment\Actions\Omr\RecordOmrItemReviewDecision;
use App\Assessment\Omr\Review\GradingOmrReviewContextResolver;
use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Assessment\Support\ScoringSessionContextResolver;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\RecordOmrGradingItemReviewRequest;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrItemReviewRevision;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class OmrGradingItemReviewController extends Controller
{
    public function store(
        RecordOmrGradingItemReviewRequest $request,
        ExamSession $examSession,
        int $sheet,
        string $item,
        GradingOmrReviewContextResolver $resolver,
        RecordOmrItemReviewDecision $action,
        CapabilityAuthorizer $authorizer,
        ScoringSessionContextResolver $contexts,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $gradingSession = $contexts->resolveForRead(
            $examSession,
            $actor,
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
        );
        abort_if($gradingSession === null, 404);

        $context = $resolver->resolve($sheet, $item);
        $decision = $authorizer->decide($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR, $context->resourceScope);
        if (! $decision->allowed) {
            abort($decision->reason === 'scope_mismatch' ? 404 : 403);
        }

        $sheetModel = OmrCaptureSheet::query()
            ->whereKey($sheet)
            ->whereHas('batch', function (Builder $query) use ($gradingSession): void {
                $query->where('grading_session_id', $gradingSession->getKey());
            })
            ->firstOrFail();

        try {
            $payload = $request->validated();
            $result = $action->handle(
                context: $context,
                decision: OmrReviewDecision::from((string) $payload['decision']),
                expectedParentVersion: (int) $payload['expected_parent_version'],
                expectedItemRevision: (int) $payload['expected_item_revision'],
                reviewedEvidenceHash: (string) $payload['reviewed_machine_evidence_hash'],
                idempotencyKey: (string) $payload['idempotency_key'],
                actor: $actor,
                selectedOption: $payload['selected_option'] ?? null,
                reasonCode: $payload['reason_code'] ?? null,
                note: $payload['note'] ?? null,
            );
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (RuntimeException $exception) {
            return $this->failure($exception);
        }

        return response()->json([
            'outcome' => 'succeeded',
            'receipt' => $result->toSafeArray(),
            'review' => $this->reviewProjection($result->revision),
        ]);
    }

    /** @return array<string, mixed> */
    private function reviewProjection(OmrItemReviewRevision $revision): array
    {
        $reviewer = $revision->reviewedBy;

        return [
            'revision' => (int) $revision->revision,
            'decision' => $revision->decision instanceof OmrReviewDecision
                ? $revision->decision->value
                : (string) $revision->decision,
            'selected_option' => $revision->selected_option,
            'reason_code' => $revision->reason_code,
            'note' => $revision->note,
            'reviewer' => $reviewer === null ? null : [
                'id' => (int) $reviewer->getKey(),
                'display_name' => (string) $reviewer->name,
            ],
            'reviewed_at' => $revision->created_at?->toIso8601String(),
            'evidence_hash' => (string) $revision->reviewed_machine_evidence_hash,
        ];
    }

    private function failure(RuntimeException $exception): JsonResponse
    {
        $message = $exception->getMessage();
        if ($message === 'omr_review_denied') {
            abort(404);
        }
        $code = preg_replace('/[^a-z0-9_]+/i', '_', strtolower(trim($message))) ?: 'omr_review_failed';
        $status = in_array($code, [
            'omr_review_context_changed',
            'omr_review_option_set_stale',
            'omr_review_evidence_hash_mismatch',
            'omr_review_parent_version_stale',
            'omr_review_item_revision_stale',
            'omr_review_parent_closed',
            'omr_review_receipt_invalid',
            'omr_review_idempotency_conflict',
        ], true) ? 409 : 422;

        return response()->json([
            'outcome' => 'failed',
            'code' => $code,
        ], $status);
    }
}
