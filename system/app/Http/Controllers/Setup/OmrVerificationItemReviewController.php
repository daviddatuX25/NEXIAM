<?php

namespace App\Http\Controllers\Setup;

use App\Assessment\Actions\Omr\RecordOmrItemReviewDecision;
use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Assessment\Omr\Review\VerificationOmrReviewContextResolver;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Omr\Canonical\RecordOmrVerificationItemReviewRequest;
use App\Models\Assessment\OmrItemReviewRevision;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationItemEvidence;
use App\Models\Assessment\OmrTemplateVerificationMethod;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class OmrVerificationItemReviewController extends Controller
{
    public function store(
        RecordOmrVerificationItemReviewRequest $request,
        OmrTemplateVerificationRun $run,
        int $capture,
        int $item,
        VerificationOmrReviewContextResolver $resolver,
        RecordOmrItemReviewDecision $action,
        CapabilityAuthorizer $authorizer,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $this->assertParentChain($run, $actor);

        $captureModel = OmrTemplateVerificationCapture::query()
            ->whereKey($capture)
            ->where('run_id', $run->getKey())
            ->firstOrFail();

        abort_unless($captureModel->method_id !== null, 404);
        abort_unless(
            OmrTemplateVerificationMethod::query()
                ->whereKey((int) $captureModel->method_id)
                ->where('run_id', $run->getKey())
                ->exists(),
            404,
        );

        $itemModel = OmrTemplateVerificationItemEvidence::query()
            ->whereKey($item)
            ->where('capture_id', $captureModel->getKey())
            ->firstOrFail();

        try {
            $context = $resolver->resolveLocked($captureModel, $itemModel);
            $decision = $authorizer->decide($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR, $context->resourceScope);
            if (! $decision->allowed) {
                if ($decision->reason === 'scope_mismatch') {
                    throw new ModelNotFoundException('Resource out of scope.');
                }
                throw new RuntimeException('omr_review_denied');
            }

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

    private function assertParentChain(OmrTemplateVerificationRun $run, User $actor): void
    {
        $run->loadMissing(['draft.template', 'templateVersion.template']);
        $draft = $run->draft;
        $version = $run->templateVersion;

        if ($run->draft_id !== null) {
            abort_unless($draft !== null && (int) $draft->owner_user_id === (int) $actor->getKey(), 404);
            if ($draft->template_id !== null) {
                abort_unless($draft->template !== null, 404);
            }
            if ($run->template_version_id !== null) {
                abort_unless(
                    $version !== null
                        && $version->template !== null
                        && (int) $draft->compiled_version_id === (int) $version->getKey()
                        && (int) $draft->template_id === (int) $version->template_id,
                    404,
                );
            }

            return;
        }
        abort_unless($version !== null && $version->template !== null, 404);

        $run->loadMissing('startedBy.staffProfile');
        $actorInstitution = $actor->actorScope()->institutionId;
        $ownerInstitution = $run->startedBy?->actorScope()->institutionId;
        if ($actorInstitution !== null && $ownerInstitution !== null && $actorInstitution !== $ownerInstitution) {
            abort(404);
        }
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
        $code = preg_replace('/[^a-z0-9_]+/i', '_', strtolower(trim($exception->getMessage()))) ?: 'omr_review_failed';
        if ($code === 'omr_review_denied') {
            $status = 403;
        } elseif (in_array($code, [
            'omr_review_context_changed',
            'omr_review_option_set_stale',
            'omr_review_parent_version_stale',
            'omr_review_item_revision_stale',
            'omr_review_evidence_hash_mismatch',
            'omr_review_idempotency_conflict',
            'omr_review_idempotency_in_progress',
            'omr_review_receipt_invalid',
            'omr_review_not_reviewable',
            'omr_review_machine_evidence_ambiguous',
            'omr_review_machine_evidence_invalid',
            'omr_review_machine_evidence_missing',
        ], true)) {
            $status = 409;
        } else {
            $status = 422;
        }

        return response()->json([
            'outcome' => 'failed',
            'code' => $code,
        ], $status);
    }
}
