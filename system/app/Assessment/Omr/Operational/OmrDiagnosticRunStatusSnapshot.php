<?php

declare(strict_types=1);

namespace App\Assessment\Omr\Operational;

use App\Assessment\Support\TestKitCurrentEvidence;
use App\Assessment\Support\TestKitMockSession;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrCameraCommand;
use App\Models\Assessment\OmrCameraFrame;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticItemEvidence;
use App\Models\Assessment\OmrDiagnosticReviewRevision;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function max;
use function preg_replace;
use function str_contains;
use function strtolower;

/**
 * Projects one diagnostic run into the shared, browser-safe workbench
 * contract. The projection is deliberately built from the diagnostic
 * namespace only; production batches, sheets, claims, and score receipts are
 * not reachable from this snapshot.
 */
final class OmrDiagnosticRunStatusSnapshot
{
    private readonly TestKitMockSession $mockSessions;

    public function __construct(
        private readonly OmrDiagnosticComparisonProjector $comparisonProjector,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly TestKitCurrentEvidence $currentEvidence,
        ?TestKitMockSession $mockSessions = null,
    ) {
        $this->mockSessions = $mockSessions ?? app(TestKitMockSession::class);
    }

    /**
     * Assert actor/run authority before any diagnostic state is projected.
     */
    public function assertCanProject(OmrDiagnosticRun $run, User $actor): void
    {
        if ((string) $run->execution_kind !== OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC) {
            throw new RuntimeException('omr_diagnostic_run_execution_kind_invalid');
        }

        if ((int) $run->revision < 1) {
            throw new RuntimeException('omr_diagnostic_run_revision_invalid');
        }

        $actor->loadMissing('staffProfile');
        if ($actor->staffProfile === null || ! $actor->staffProfile->isActive()) {
            throw new RuntimeException('omr_diagnostic_owner_unavailable');
        }
        if ((int) $run->created_by_user_id !== (int) $actor->id) {
            throw new RuntimeException('omr_diagnostic_owner_mismatch');
        }

        $run->loadMissing('academicCycle');
        $cycle = $run->academicCycle;
        $scope = $actor->actorScope();
        if ($cycle === null
            || (int) $cycle->id !== (int) $run->academic_cycle_id
            || (int) $cycle->institution_id !== (int) $run->institution_id
            || $scope->institutionId === null
            || $scope->institutionId !== (int) $run->institution_id
            || $scope->activeCycleCode === null
            || (string) $scope->activeCycleCode !== (string) $cycle->code) {
            throw new RuntimeException('omr_diagnostic_scope_mismatch');
        }

        $decision = $this->authorizer->decide($actor, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, [
            'office' => 'guidance',
            'institution_id' => (int) $run->institution_id,
            'cycle' => (string) $cycle->code,
        ]);
        if (! $decision->allowed) {
            throw new RuntimeException('omr_diagnostic_capability_'.$decision->reason);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function forRun(OmrDiagnosticRun $run, User $actor): array
    {
        $this->assertCanProject($run, $actor);
        $revision = (int) $run->revision;

        $run->loadMissing(['captures.evidenceItems.reviewRevisions']);
        $captures = $run->captures->sortBy('id')->values();
        $activeCaptures = $captures->reject(fn (OmrDiagnosticCapture $capture): bool => $capture->failure_code === 'discarded_by_operator')->values();
        $currentEvidence = is_array($run->acceptance_plan_json) && $run->acceptance_plan_json !== []
            ? $this->currentEvidence->forRun($run)
            : null;
        if ($currentEvidence !== null) {
            $selectedCaptures = $currentEvidence['captures']
                ->reject(fn (OmrDiagnosticCapture $capture): bool => $capture->failure_code === 'discarded_by_operator')
                ->sortBy('id')
                ->values();
            $selectedCaptureIds = $selectedCaptures
                ->mapWithKeys(static fn (OmrDiagnosticCapture $capture): array => [(int) $capture->id => true])
                ->all();
        } elseif ($run->hasMockContext()) {
            $selectedCaptureIds = array_fill_keys($this->mockSessions->currentCaptureIds($run), true);
            $selectedCaptures = $activeCaptures
                ->filter(static fn (OmrDiagnosticCapture $capture): bool => isset($selectedCaptureIds[(int) $capture->id]))
                ->values();
        } else {
            $selectedCaptures = $activeCaptures;
            $selectedCaptureIds = null;
        }
        $hasActiveCapture = $activeCaptures->contains(static fn (OmrDiagnosticCapture $capture): bool => in_array(
            $capture->status,
            [
                OmrDiagnosticCapture::STATUS_RECEIVED,
                OmrDiagnosticCapture::STATUS_QUEUED,
                OmrDiagnosticCapture::STATUS_PROCESSING,
            ],
            true,
        ));
        $cameraStageCutoff = now();
        $workbenchKey = hash('sha256', 'diagnostic:'.$run->public_id);
        $hasUnresolvedCaptureOperation = OmrCameraCommand::query()
            ->where('workbench_key', $workbenchKey)
            ->where('kind', 'CAPTURE_RECEIPT')
            ->whereNull('domain_receipt_json->action')
            ->whereIn('domain_outcome', ['reserved', 'uncertain'])
            ->exists();
        $hasUnresolvedCameraAcquisition = OmrCameraCommand::query()
            ->where('workbench_key', $workbenchKey)
            ->where('kind', 'CAPTURE')
            ->whereNull('device_outcome')
            ->where('execution_expires_at', '>', $cameraStageCutoff)
            ->exists()
            || OmrCameraFrame::query()
                ->whereHas('pairing', fn ($pairing) => $pairing->where('diagnostic_run_id', (int) $run->id))
                ->where(function ($query) use ($cameraStageCutoff): void {
                    $query->where('state', OmrCameraFrame::STATE_FINALIZING)
                        ->orWhere(function ($activeFrame) use ($cameraStageCutoff): void {
                            $activeFrame
                                ->whereIn('state', [
                                    OmrCameraFrame::STATE_ACQUIRED,
                                    OmrCameraFrame::STATE_UPLOADING,
                                    OmrCameraFrame::STATE_STAGED,
                                ])
                                ->where('upload_expires_at', '>', $cameraStageCutoff);
                        });
                })
                ->exists();
        $hasInFlightCapture = $hasActiveCapture || $hasUnresolvedCaptureOperation;
        $currentEvidenceIsValid = ($currentEvidence === null || $currentEvidence['errors'] === [])
            && ! $hasUnresolvedCaptureOperation;
        $latestCaptures = $currentEvidence !== null || $run->hasMockContext()
            ? $selectedCaptures
            : $this->latestCaptures($activeCaptures);
        $latestCapture = $latestCaptures->last();
        $comparison = $latestCapture instanceof OmrDiagnosticCapture
            ? $this->comparisonProjector->project($latestCapture)
            : null;
        $captureRows = $activeCaptures
            ->map(fn (OmrDiagnosticCapture $capture): array => $this->captureRow($capture))
            ->values()
            ->all();
        $evidenceRows = $activeCaptures
            ->flatMap(fn (OmrDiagnosticCapture $capture): Collection => $capture->evidenceItems
                ->sortBy('id')
                ->map(fn (OmrDiagnosticItemEvidence $item): array => $this->evidenceRow($item)))
            ->values()
            ->all();
        $currentEvidenceRows = $selectedCaptureIds === null
            ? $evidenceRows
            : array_values(array_filter(
                $evidenceRows,
                static fn (array $row): bool => isset($selectedCaptureIds[(int) ($row['capture_id'] ?? 0)]),
            ));
        $recoveryRows = $this->recoveryRows($latestCaptures, $selectedCaptureIds);
        foreach ((array) ($currentEvidence['errors'] ?? []) as $error) {
            if (($error['code'] ?? null) === 'capture_discarded_by_operator') {
                $captureId = (int) ($error['capture_id'] ?? 0);
                $recoveryRows[] = [
                    'owner' => 'OMR diagnostic operator',
                    'capture_id' => $captureId,
                    'status' => 'discarded',
                    'blocked' => true,
                    'code' => 'capture_discarded_by_operator',
                    'support_code' => sprintf('OMR-DIAG-%d-CAPTURE_DISCARDED', $captureId),
                    'message' => 'This attempt was removed from the current page selection.',
                    'stage' => OmrWorkbenchProjection::PHASE_RECOVERY,
                    'recovery' => 'Capture a replacement page using the current server-issued target.',
                    'primary_action' => OmrWorkbenchCommand::CaptureStart->value,
                ];
            }
        }
        $phase = $this->phase($run, $recoveryRows);
        $commands = $this->commands(
            $run,
            $selectedCaptures,
            $currentEvidenceRows,
            $recoveryRows,
            $currentEvidenceIsValid,
            $hasInFlightCapture,
            $hasUnresolvedCameraAcquisition,
        );
        $progress = $this->progress($selectedCaptures);
        if ($hasInFlightCapture) {
            $progress['known'] = false;
        }
        $mockSummary = $run->hasMockContext() ? $this->mockSessions->summarize($run) : null;
        $mockConfig = $run->hasMockContext()
            ? [
                'instance_count' => (int) ($run->mock_manifest_json['config']['instance_count'] ?? 1),
                'pattern_mode' => (string) ($run->mock_manifest_json['config']['pattern_mode'] ?? 'guided_mix'),
            ]
            : null;
        $captureTarget = $run->hasMockContext() && $run->status !== OmrDiagnosticRun::STATUS_CANCELLED
            ? $this->mockSessions->captureTarget($run)
            : null;

        $projection = new OmrWorkbenchProjection(
            workbenchId: 'diagnostic:'.(string) $run->public_id,
            executionKind: OmrExecutionKind::Diagnostic,
            revision: $revision,
            phase: $phase,
            context: [
                'execution_kind' => OmrExecutionKind::Diagnostic->value,
                'actor_id' => (int) $actor->getAuthIdentifier(),
                'run_id' => (int) $run->getKey(),
                'public_id' => (string) $run->public_id,
                'institution_id' => (int) $run->institution_id,
                'academic_cycle_id' => (int) $run->academic_cycle_id,
                'template_version_id' => (int) $run->template_version_id,
                'booklet_version_id' => (int) $run->booklet_version_id,
                'declared_set_code' => (string) $run->declared_set_code,
                'materials_url' => '/setup/exam-materials/test-kits',
                'back_url' => '/setup/exam-materials/test-kits',
                'status' => (string) $run->status,
                'completed_at' => $run->completed_at?->toIso8601String(),
                'raw_phase' => (string) $run->phase,
                'diagnostic_purpose' => (string) $run->diagnostic_purpose,
                'mock_manifest_sha256' => $run->mock_manifest_sha256,
                'mock_config' => $mockConfig,
                'capture_target' => $captureTarget,
                'mock_summary' => $mockSummary,
                'mock_progress' => $mockSummary === null ? null : [
                    'expected_instances' => $mockSummary['expected_instance_count'] ?? 0,
                    'completed_instances' => $mockSummary['completed_instance_count'] ?? 0,
                    'expected_pages' => $mockSummary['expected_page_count'] ?? 0,
                    'captured_pages' => $mockSummary['captured_page_count'] ?? 0,
                ],
                'progress' => $progress,
                'capabilities' => $this->capabilities(
                    $run,
                    $selectedCaptures,
                    $currentEvidenceRows,
                    $recoveryRows,
                    $currentEvidenceIsValid,
                    $hasInFlightCapture,
                ),
            ],
            sheets: $captureRows,
            evidence: [
                'items' => $evidenceRows,
                'captures' => $captures
                    ->map(fn (OmrDiagnosticCapture $capture): array => [
                        'capture_id' => (int) $capture->getKey(),
                        'evidence' => $this->captureEvidence($capture),
                        'evidence_hash' => $capture->evidence_hash,
                    ])
                    ->values()
                    ->all(),
                'comparison' => $comparison,
            ],
            recovery: [
                'items' => $recoveryRows,
                'count' => count($recoveryRows),
            ],
            completion: $this->completion($run, $selectedCaptures),
            commands: $commands,
        );

        return $projection->toSafeArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function captureRow(OmrDiagnosticCapture $capture): array
    {
        return [
            'id' => (int) $capture->getKey(),
            'client_capture_id' => (string) $capture->client_capture_id,
            'status' => (string) $capture->status,
            'version' => max(0, (int) $capture->version),
            'stage' => $this->captureStage($capture),
            'assets' => [
                'input' => $this->assetRow($capture->input_asset_id, $capture->input_asset_hash),
                'aligned' => $this->assetRow($capture->aligned_asset_id, $capture->aligned_asset_hash),
                'overlay' => $this->assetRow($capture->overlay_asset_id, $capture->overlay_asset_hash),
            ],
            'evidence' => $this->captureEvidence($capture),
            'evidence_hash' => $capture->evidence_hash,
            'failure' => $capture->failure_code === null && $capture->status !== OmrDiagnosticCapture::STATUS_FAILED
                ? null
                : $this->failureProjection($capture),
            'captured_at' => $capture->captured_at?->toIso8601String(),
            'completed_at' => $capture->completed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function evidenceRow(OmrDiagnosticItemEvidence $item): array
    {
        return [
            'id' => (int) $item->getKey(),
            'capture_id' => (int) $item->diagnostic_capture_id,
            'item_key' => (string) $item->item_key,
            'page_no' => (int) $item->page_no,
            'area_code' => $item->area_code,
            'question_no' => $item->question_no === null ? null : (int) $item->question_no,
            'option_code' => $item->option_code,
            'option_evidence' => $this->withoutSensitiveTruth($item->option_evidence_json),
            'machine_proposal' => $this->withoutSensitiveTruth($item->machine_proposal_json),
            'machine_confidence' => $item->machine_confidence === null ? null : (float) $item->machine_confidence,
            'evidence_hash' => (string) $item->evidence_hash,
            'evidence_version' => (string) $item->evidence_version,
            'reviews' => $item->reviewRevisions
                ->sortBy('revision')
                ->map(fn (OmrDiagnosticReviewRevision $revision): array => $this->reviewRow($revision))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewRow(OmrDiagnosticReviewRevision $revision): array
    {
        return $this->withoutSensitiveTruth($revision->toSafeArray());
    }

    /**
     * @param  Collection<int, OmrDiagnosticCapture>  $captures
     * @return list<array<string, mixed>>
     */
    private function recoveryRows(Collection $captures, ?array $currentCaptureIds = null): array
    {
        return $captures
            ->filter(fn (OmrDiagnosticCapture $capture): bool => ($currentCaptureIds === null
                    || isset($currentCaptureIds[(int) $capture->getKey()]))
                && ($capture->failure_code !== null
                    || $capture->status === OmrDiagnosticCapture::STATUS_FAILED))
            ->map(function (OmrDiagnosticCapture $capture): array {
                $failure = $this->failureProjection($capture);

                return [
                    'owner' => 'OMR diagnostic operator',
                    'capture_id' => (int) $capture->getKey(),
                    'status' => (string) $capture->status,
                    'blocked' => true,
                    'code' => $failure['code'],
                    'support_code' => sprintf(
                        'OMR-DIAG-%d-%s',
                        $capture->getKey(),
                        strtoupper((string) ($failure['code'] ?? 'REVIEW')),
                    ),
                    'message' => $failure['message'],
                    'stage' => $failure['stage'],
                    'recovery' => $failure['recovery'],
                    'primary_action' => $capture->status === OmrDiagnosticCapture::STATUS_FAILED
                        ? OmrWorkbenchCommand::CaptureRetry->value
                        : OmrWorkbenchCommand::ReviewSave->value,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Project only a domain-safe diagnosis supported by the stored capture
     * status and code. Worker details are deliberately not returned to clients.
     *
     * @return array{code: string, message: string, stage: string, recovery: string}
     */
    private function failureProjection(OmrDiagnosticCapture $capture): array
    {
        $code = (string) ($capture->failure_code ?: 'diagnostic_review_required');

        if ($code === 'analysis_failed') {
            return [
                'code' => $code,
                'message' => 'The capture was received, but analysis did not complete.',
                'stage' => OmrWorkbenchProjection::PHASE_PROCESSING,
                'recovery' => 'Retry this capture. If the problem repeats, share the support code with an administrator.',
            ];
        }

        if ($code === 'omr_diagnostic_evidence_persistence_failed') {
            return [
                'code' => $code,
                'message' => 'Analysis finished, but its evidence could not be saved.',
                'stage' => OmrWorkbenchProjection::PHASE_RECOVERY,
                'recovery' => 'Review the run status before retrying this capture.',
            ];
        }

        if ($code === 'document_quad_not_found') {
            return [
                'code' => $code,
                'message' => 'The page boundary could not be found.',
                'stage' => 'registration',
                'recovery' => 'Show all four sheet corners against a contrasting background and hold the camera square before retaking the page.',
            ];
        }

        if ($code === 'document_content_missing') {
            return [
                'code' => $code,
                'message' => 'The captured page content could not be verified.',
                'stage' => 'registration',
                'recovery' => 'Check that this is the answer sheet selected for this run and include the complete printed page before retaking it.',
            ];
        }

        if (in_array($code, [
            'marker_missing',
            'marker_mismatch',
            'sheet_misaligned',
            'orientation_mismatch',
            'dimensions_mismatch',
            'layout_invalid',
            'field_block_shift',
        ], true)) {
            return [
                'code' => $code,
                'message' => 'The capture reached layout verification and needs review.',
                'stage' => OmrWorkbenchProjection::PHASE_REVIEW,
                'recovery' => 'Review alignment and framing before retrying.',
            ];
        }

        return [
            'code' => $code,
            'message' => $capture->status === OmrDiagnosticCapture::STATUS_FAILED
                ? 'The capture could not be completed safely.'
                : 'The capture is waiting for operator review.',
            'stage' => $capture->status === OmrDiagnosticCapture::STATUS_FAILED
                ? OmrWorkbenchProjection::PHASE_RECOVERY
                : OmrWorkbenchProjection::PHASE_REVIEW,
            'recovery' => $capture->status === OmrDiagnosticCapture::STATUS_FAILED
                ? 'Review the run status before retrying this capture.'
                : 'Review the capture evidence before continuing.',
        ];
    }

    private function captureEvidence(OmrDiagnosticCapture $capture): mixed
    {
        $evidence = $this->withoutSensitiveTruth($capture->evidence_json);
        if ($capture->status !== OmrDiagnosticCapture::STATUS_FAILED || ! is_array($evidence)) {
            return $evidence;
        }

        return $this->withoutFailureDetails($evidence);
    }

    /**
     * @param  array<string|int, mixed>  $value
     * @return array<string|int, mixed>
     */
    private function withoutFailureDetails(array $value): array
    {
        $safe = [];
        foreach ($value as $key => $child) {
            if (is_string($key) && in_array(strtolower($key), ['detail', 'failure_detail', 'technical_detail'], true)) {
                continue;
            }
            $safe[$key] = is_array($child) ? $this->withoutFailureDetails($child) : $child;
        }

        return $safe;
    }

    /**
     * @param  Collection<int, OmrDiagnosticCapture>  $captures
     * @param  list<array<string, mixed>>  $evidenceRows
     * @param  list<array<string, mixed>>  $recoveryRows
     */
    private function commands(
        OmrDiagnosticRun $run,
        Collection $captures,
        array $evidenceRows,
        array $recoveryRows,
        bool $currentEvidenceIsValid = true,
        bool $hasInFlightCapture = false,
        bool $hasUnresolvedCameraAcquisition = false,
    ): array {
        if ($run->isTerminal()) {
            return [];
        }

        $commands = [];
        $captureActive = $hasInFlightCapture || $captures->contains(static fn (OmrDiagnosticCapture $capture): bool => in_array(
            $capture->status,
            [OmrDiagnosticCapture::STATUS_RECEIVED, OmrDiagnosticCapture::STATUS_QUEUED, OmrDiagnosticCapture::STATUS_PROCESSING],
            true,
        ));
        if ($run->hasMockContext()) {
            $hasGrantedTarget = OmrCameraCommand::query()
                ->where('workbench_key', hash('sha256', 'diagnostic:'.$run->public_id))
                ->where('kind', 'CAPTURE_TARGET_GRANT')
                ->where('domain_outcome', 'granted')
                ->exists();
            if (! $captureActive && ($hasGrantedTarget || $this->mockSessions->captureTarget($run) !== null)) {
                $commands[] = OmrWorkbenchCommand::CaptureStart;
            }
        } elseif (! $captureActive && ($captures->isEmpty() || $run->status === OmrDiagnosticRun::STATUS_OPEN)) {
            $commands[] = OmrWorkbenchCommand::CaptureStart;
        }
        if (! $captureActive && $captures->contains(fn (OmrDiagnosticCapture $capture): bool => $capture->status === OmrDiagnosticCapture::STATUS_FAILED)) {
            $commands[] = OmrWorkbenchCommand::CaptureRetry;
        }
        if (! $captureActive && $evidenceRows !== [] && $run->status !== OmrDiagnosticRun::STATUS_COMPLETED) {
            $commands[] = OmrWorkbenchCommand::ReviewSave;
        }
        if ($run->hasMockContext()) {
            $summary = $this->mockSessions->summarize($run);
            if ($currentEvidenceIsValid && ! $captureActive && $captures->isNotEmpty()) {
                $commands[] = OmrWorkbenchCommand::DiagnosticCompare;
            }
            if ($currentEvidenceIsValid
                && ! $captureActive
                && (int) ($summary['completed_instance_count'] ?? 0) === (int) ($summary['expected_instance_count'] ?? -1)
                && (int) ($summary['captured_page_count'] ?? 0) === (int) ($summary['expected_page_count'] ?? -1)) {
                $commands[] = OmrWorkbenchCommand::DiagnosticComplete;
            }
        } else {
            $latestCaptures = $this->latestCaptures($captures);
            $hasEffectiveAnswers = $this->hasReviewedEvidence($latestCaptures);
            if ($currentEvidenceIsValid && ! $captureActive && $hasEffectiveAnswers && $recoveryRows === []) {
                $commands[] = OmrWorkbenchCommand::DiagnosticCompare;
            }
            $hasComparison = $latestCaptures->contains(
                fn (OmrDiagnosticCapture $capture): bool => $this->isStableCapture($capture)
                    && is_array($capture->projected_score_json)
                    && $capture->projected_score_json !== [],
            );
            if ($currentEvidenceIsValid && ! $captureActive && $hasComparison && $recoveryRows === []) {
                $commands[] = OmrWorkbenchCommand::DiagnosticComplete;
            }
        }
        if ($run->hasMockContext() && ! $captureActive && ! $hasUnresolvedCameraAcquisition) {
            $commands[] = OmrWorkbenchCommand::DiagnosticCancel;
        }

        return array_values($commands);
    }

    /**
     * @param  Collection<int, OmrDiagnosticCapture>  $captures
     */
    private function hasReviewedEvidence(Collection $captures): bool
    {
        if ($captures->contains(fn (OmrDiagnosticCapture $capture): bool => ! $this->isStableCapture($capture))) {
            return false;
        }

        if ($captures->contains(fn (OmrDiagnosticCapture $capture): bool => is_array($capture->effective_answers_json)
            && $capture->effective_answers_json !== [])) {
            return true;
        }

        $hasEvidence = false;
        foreach ($captures as $capture) {
            foreach ($capture->evidenceItems as $item) {
                $hasEvidence = true;
                if ($item->reviewRevisions->isEmpty()) {
                    return false;
                }
            }
        }

        return $hasEvidence;
    }

    /**
     * Match the comparison action's stable-attempt rule by retaining only the
     * latest capture for each input asset.
     *
     * @param  Collection<int, OmrDiagnosticCapture>  $captures
     * @return Collection<int, OmrDiagnosticCapture>
     */
    private function latestCaptures(Collection $captures): Collection
    {
        return $captures
            ->groupBy(static fn (OmrDiagnosticCapture $capture): string => (string) $capture->input_asset_id)
            ->map(static fn (Collection $attempts): OmrDiagnosticCapture => $attempts->last())
            ->sortBy('id')
            ->values();
    }

    private function isStableCapture(OmrDiagnosticCapture $capture): bool
    {
        return in_array($capture->status, [
            OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
            OmrDiagnosticCapture::STATUS_REVIEWED,
            OmrDiagnosticCapture::STATUS_COMPLETED,
        ], true);
    }

    /**
     * @param  Collection<int, OmrDiagnosticCapture>  $captures
     * @param  list<array<string, mixed>>  $evidenceRows
     * @param  list<array<string, mixed>>  $recoveryRows
     * @return list<string>
     */
    private function capabilities(
        OmrDiagnosticRun $run,
        Collection $captures,
        array $evidenceRows,
        array $recoveryRows,
        bool $currentEvidenceIsValid = true,
        bool $hasInFlightCapture = false,
    ): array {
        if ($run->isTerminal()) {
            return [];
        }

        $capabilities = $hasInFlightCapture ? [] : ['capture'];
        if (! $hasInFlightCapture && $evidenceRows !== []) {
            $capabilities[] = 'review';
        }
        $latestCaptures = $this->latestCaptures($captures);
        if (! $hasInFlightCapture
            && $currentEvidenceIsValid
            && $this->hasReviewedEvidence($latestCaptures)
            && $recoveryRows === []) {
            $capabilities[] = 'diagnostic_compare';
        }
        if (! $hasInFlightCapture
            && $currentEvidenceIsValid
            && $latestCaptures->contains(fn (OmrDiagnosticCapture $capture): bool => $this->isStableCapture($capture)
                && is_array($capture->projected_score_json)
                && $capture->projected_score_json !== [])
            && $recoveryRows === []) {
            $capabilities[] = 'diagnostic_complete';
        }

        return $capabilities;
    }

    /**
     * @param  Collection<int, OmrDiagnosticCapture>  $captures
     * @return array{completed: int, total: int, known: bool}
     */
    private function progress(Collection $captures): array
    {
        $total = $captures->count();
        $completed = $captures
            ->filter(fn (OmrDiagnosticCapture $capture): bool => in_array($capture->status, [
                OmrDiagnosticCapture::STATUS_COMPLETED,
                OmrDiagnosticCapture::STATUS_FAILED,
            ], true))
            ->count();

        return [
            'completed' => $completed,
            'total' => $total,
            'known' => $total > 0,
        ];
    }

    private function phase(OmrDiagnosticRun $run, array $recoveryRows): string
    {
        if ($run->status === OmrDiagnosticRun::STATUS_FAILED) {
            return OmrWorkbenchProjection::PHASE_TERMINAL;
        }
        if ($run->status === OmrDiagnosticRun::STATUS_COMPLETED || $run->phase === OmrDiagnosticRun::PHASE_COMPLETE) {
            return OmrWorkbenchProjection::PHASE_COMPLETE;
        }
        if ($recoveryRows !== [] && $run->status === OmrDiagnosticRun::STATUS_NEEDS_REVIEW) {
            return OmrWorkbenchProjection::PHASE_RECOVERY;
        }

        return match ($run->phase) {
            OmrDiagnosticRun::PHASE_CAPTURE => OmrWorkbenchProjection::PHASE_CAPTURE,
            OmrDiagnosticRun::PHASE_PROCESSING => OmrWorkbenchProjection::PHASE_PROCESSING,
            OmrDiagnosticRun::PHASE_EVIDENCE => OmrWorkbenchProjection::PHASE_EVIDENCE,
            OmrDiagnosticRun::PHASE_REVIEW => OmrWorkbenchProjection::PHASE_REVIEW,
            OmrDiagnosticRun::PHASE_RECOVERY => OmrWorkbenchProjection::PHASE_RECOVERY,
            OmrDiagnosticRun::PHASE_COMPLETE => OmrWorkbenchProjection::PHASE_COMPLETE,
            default => OmrWorkbenchProjection::PHASE_CAPTURE,
        };
    }

    private function captureStage(OmrDiagnosticCapture $capture): string
    {
        return match ($capture->status) {
            OmrDiagnosticCapture::STATUS_RECEIVED => OmrWorkbenchProjection::PHASE_CAPTURE,
            OmrDiagnosticCapture::STATUS_QUEUED,
            OmrDiagnosticCapture::STATUS_PROCESSING => OmrWorkbenchProjection::PHASE_PROCESSING,
            OmrDiagnosticCapture::STATUS_NEEDS_REVIEW => OmrWorkbenchProjection::PHASE_REVIEW,
            OmrDiagnosticCapture::STATUS_REVIEWED => OmrWorkbenchProjection::PHASE_COMPLETION,
            OmrDiagnosticCapture::STATUS_COMPLETED => OmrWorkbenchProjection::PHASE_COMPLETE,
            OmrDiagnosticCapture::STATUS_FAILED => OmrWorkbenchProjection::PHASE_TERMINAL,
            default => OmrWorkbenchProjection::PHASE_RECOVERY,
        };
    }

    /**
     * @return array<string, int|string|null>
     */
    private function assetRow(mixed $id, mixed $hash): array
    {
        return [
            'id' => $id === null ? null : (int) $id,
            'hash' => $hash === null ? null : (string) $hash,
        ];
    }

    /**
     * @param  Collection<int, OmrDiagnosticCapture>  $captures
     * @return array<string, mixed>|null
     */
    private function completion(OmrDiagnosticRun $run, Collection $captures): ?array
    {
        if (! $run->isTerminal()) {
            return null;
        }

        $projectedScores = $captures
            ->map(fn (OmrDiagnosticCapture $capture): mixed => $this->projectedScoreSummary($capture->projected_score_json))
            ->filter(fn (mixed $score): bool => is_array($score) && $score !== [])
            ->values()
            ->all();

        $isCancelled = $run->status === OmrDiagnosticRun::STATUS_CANCELLED;

        return [
            'kind' => $isCancelled ? 'diagnostic_cancellation' : 'diagnostic_outcome',
            'label' => $isCancelled ? 'Cancelled; evidence retained' : 'Projected / unofficial',
            'status' => (string) $run->status,
            'official' => false,
            'run_id' => (int) $run->getKey(),
            'capture_count' => $captures->count(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'outcome' => $isCancelled
                ? 'cancelled'
                : ($run->status === OmrDiagnosticRun::STATUS_COMPLETED ? 'recorded' : 'blocked'),
            ...($isCancelled ? [] : [
                'projected_score_summary' => [
                    'capture_count' => count($projectedScores),
                    'scores' => $projectedScores,
                ],
            ]),
        ];
    }

    /** @return array<string, mixed>|null */
    private function projectedScoreSummary(mixed $score): ?array
    {
        if (! is_array($score) || $score === []) {
            return null;
        }

        $summary = $this->withoutSensitiveTruth($score);
        if (! is_array($summary)) {
            return null;
        }

        foreach (['items', 'per_item', 'per_item_scores', 'answers', 'effective_answers', 'detected_answers', 'effective'] as $key) {
            unset($summary[$key]);
        }
        if (is_array($summary['comparison'] ?? null)) {
            unset($summary['comparison']['items'], $summary['comparison']['extras']);
        }

        return $summary;
    }

    /**
     * Remove answer-key, expected-vector, and qualification material before
     * values cross the workbench projection boundary.
     */
    private function withoutSensitiveTruth(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $safe = [];
        foreach ($value as $key => $child) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                continue;
            }
            $safe[$key] = $this->withoutSensitiveTruth($child);
        }

        return $safe;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = preg_replace('/[^a-z0-9]+/', '', strtolower($key));
        if (! is_string($normalized) || $normalized === '') {
            return false;
        }

        foreach (['answerkey', 'correctanswer', 'correctoption', 'expected', 'qualification'] as $part) {
            if (str_contains($normalized, $part)) {
                return true;
            }
        }

        return false;
    }
}
