<?php

declare(strict_types=1);

namespace App\Assessment\Actions\Omr\Operational;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrDiagnosticReviewRevision;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\User;
use RuntimeException;

use function in_array;
use function is_array;
use function strtolower;
use function trim;

/**
 * Builds the server-safe diagnostic projection.  The stored canonical
 * evidence remains useful for review, but this boundary deliberately removes
 * expected-answer, score, plan, and qualification-capability fields.
 */
final class OmrDiagnosticEvidenceQuery
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(OmrDiagnosticRun|int|string $run, User $actor): array
    {
        $resolved = $this->resolveRun($run);
        $this->assertActor($resolved, $actor);
        $resolved->loadMissing([
            'templateVersion',
            'bookletVersion',
            'captures' => static function ($query): void {
                $query->orderBy('id');
            },
            'captures.inputAsset',
            'captures.alignedAsset',
            'captures.overlayAsset',
            'captures.evidenceItems' => static function ($query): void {
                $query->stableOrder();
            },
            'captures.evidenceItems.reviewRevisions',
        ]);

        if ($resolved->execution_kind !== OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC) {
            throw new RuntimeException('omr_diagnostic_execution_kind_invalid');
        }

        $captures = [];
        $evidence = [];
        $captureIds = [];
        $evidenceIds = [];
        foreach ($resolved->captures as $capture) {
            $captureIds[] = (int) $capture->id;
            $itemEvidence = [];
            foreach ($capture->evidenceItems as $item) {
                $reviews = $item->reviewRevisions
                    ->sortByDesc(static fn (OmrDiagnosticReviewRevision $revision): int => (int) $revision->revision)
                    ->values();
                $latestReview = $reviews->first();
                $evidenceIds[] = (int) $item->id;
                $itemEvidence[] = [
                    'id' => (int) $item->id,
                    'item_key' => (string) $item->item_key,
                    'page_no' => (int) $item->page_no,
                    'area_code' => $item->area_code,
                    'question_no' => $item->question_no === null ? null : (int) $item->question_no,
                    'option_code' => $item->option_code,
                    'option_evidence' => $this->sanitize($item->option_evidence_json),
                    'machine_proposal' => $this->sanitize($item->machine_proposal_json),
                    'machine_confidence' => $item->machine_confidence === null ? null : (float) $item->machine_confidence,
                    'evidence_hash' => (string) $item->evidence_hash,
                    'evidence_version' => (string) $item->evidence_version,
                    'latest_review' => $latestReview?->toSafeArray(),
                ];
            }

            $safeCaptureEvidence = is_array($capture->evidence_json)
                ? $this->sanitize($capture->evidence_json)
                : null;
            $captureEvidenceIds = array_map(
                static fn (array $item): int => (int) $item['id'],
                $itemEvidence,
            );
            $safeCapture = [
                'id' => (int) $capture->id,
                'client_capture_id' => (string) $capture->client_capture_id,
                'status' => (string) $capture->status,
                'version' => (int) $capture->version,
                'input_asset' => $this->safeAsset($capture->inputAsset),
                'aligned_asset' => $this->safeAsset($capture->alignedAsset),
                'overlay_asset' => $this->safeAsset($capture->overlayAsset),
                'input_asset_hash' => (string) $capture->input_asset_hash,
                'aligned_asset_hash' => $capture->aligned_asset_hash,
                'overlay_asset_hash' => $capture->overlay_asset_hash,
                'evidence_hash' => $capture->evidence_hash,
                'evidence' => $safeCaptureEvidence,
                'detected_answers' => $this->sanitize($capture->detected_answers_json),
                'effective_answers' => $this->sanitize($capture->effective_answers_json),
                'failure_code' => $capture->failure_code,
                'failure_detail' => $capture->failure_detail,
                'captured_at' => $capture->captured_at?->toIso8601String(),
                'completed_at' => $capture->completed_at?->toIso8601String(),
                'items' => $itemEvidence,
                'evidence_ids' => $captureEvidenceIds,
            ];
            $captures[] = $safeCapture;
            $evidence[] = [
                'capture_id' => (int) $capture->id,
                'evidence_hash' => $capture->evidence_hash,
                'normalized' => $safeCaptureEvidence,
                'items' => $itemEvidence,
            ];
        }

        $currentCapture = $captures === [] ? null : $captures[count($captures) - 1];
        $identity = [
            'run_id' => (int) $resolved->id,
            'run_public_id' => (string) $resolved->public_id,
            'revision' => (int) $resolved->revision,
            'capture_id' => $currentCapture['id'] ?? null,
            'capture_ids' => $captureIds,
            'evidence_id' => $evidenceIds[0] ?? null,
            'evidence_ids' => $evidenceIds,
        ];

        return [
            'run' => [
                'id' => (int) $resolved->id,
                'public_id' => (string) $resolved->public_id,
                'execution_kind' => (string) $resolved->execution_kind,
                'revision' => (int) $resolved->revision,
                'status' => (string) $resolved->status,
                'phase' => (string) $resolved->phase,
                'template_version_id' => (int) $resolved->template_version_id,
                'booklet_version_id' => (int) $resolved->booklet_version_id,
                'declared_set_code' => (string) $resolved->declared_set_code,
                'completed_at' => $resolved->completed_at?->toIso8601String(),
            ],
            'current_capture' => $currentCapture,
            'captures' => $captures,
            'evidence' => $evidence,
            'identities' => $identity,
        ];
    }

    private function resolveRun(OmrDiagnosticRun|int|string $run): OmrDiagnosticRun
    {
        if ($run instanceof OmrDiagnosticRun) {
            return $run->exists
                ? $run->fresh(['academicCycle']) ?? $run
                : throw new RuntimeException('omr_diagnostic_run_missing');
        }

        $query = OmrDiagnosticRun::query()->with('academicCycle');
        $resolved = is_int($run)
            ? $query->whereKey($run)->first()
            : $query->where('public_id', (string) $run)->first();

        return $resolved ?? throw new RuntimeException('omr_diagnostic_run_missing');
    }

    private function assertActor(OmrDiagnosticRun $run, User $actor): void
    {
        $actor->loadMissing('staffProfile');
        if ($actor->staffProfile === null || ! $actor->staffProfile->isActive()) {
            throw new RuntimeException('omr_diagnostic_owner_unavailable');
        }
        if ((int) $run->created_by_user_id !== (int) $actor->id) {
            throw new RuntimeException('omr_diagnostic_owner_mismatch');
        }
        if ($run->execution_kind !== OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC) {
            throw new RuntimeException('omr_diagnostic_execution_kind_invalid');
        }

        $scope = $actor->actorScope();
        if ($scope->institutionId === null || $scope->institutionId !== (int) $run->institution_id) {
            throw new RuntimeException('omr_diagnostic_scope_mismatch');
        }
        if ($scope->activeCycleCode === null || (string) $scope->activeCycleCode !== (string) $run->academicCycle?->code) {
            throw new RuntimeException('omr_diagnostic_scope_mismatch');
        }

        $decision = $this->authorizer->decide($actor, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, [
            'office' => 'guidance',
            'institution_id' => (int) $run->institution_id,
            'cycle' => (string) $run->academicCycle?->code,
        ]);
        if (! $decision->allowed) {
            throw new RuntimeException('omr_diagnostic_capability_'.$decision->reason);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function safeAsset(?OmrTemplateAsset $asset): ?array
    {
        if ($asset === null) {
            return null;
        }

        return [
            'id' => (int) $asset->id,
            'kind' => (string) $asset->kind,
            'page_no' => $asset->page_no === null ? null : (int) $asset->page_no,
            'mime' => (string) $asset->mime,
            'bytes' => (int) $asset->bytes,
            'sha256' => (string) $asset->sha256,
        ];
    }

    private function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->forbiddenKey($key)) {
            return null;
        }
        if (! is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $childKey => $child) {
            if (is_string($childKey) && $this->forbiddenKey($childKey)) {
                continue;
            }
            $result[$childKey] = $this->sanitize($child, is_string($childKey) ? $childKey : null);
        }

        return $result;
    }

    private function forbiddenKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', trim($key)));
        $compact = preg_replace('/[^a-z0-9]+/', '', $normalized) ?? '';
        if ($compact === '') {
            return false;
        }

        foreach (['answerkey', 'correctanswer', 'correctoption', 'expected', 'qualification'] as $part) {
            if (str_contains($compact, $part)) {
                return true;
            }
        }
        if (str_contains($compact, 'score')) {
            return true;
        }

        return in_array($normalized, [
            'exact',
            'plan_hash',
            'plan_purpose',
            'vector_match',
            'bytes',
        ], true);
    }
}
