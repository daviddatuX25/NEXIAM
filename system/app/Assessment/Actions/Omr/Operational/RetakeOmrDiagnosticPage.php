<?php

declare(strict_types=1);

namespace App\Assessment\Actions\Omr\Operational;

use App\Assessment\Actions\Omr\Camera\OmrCameraFrameFinalization;
use App\Assessment\Actions\Omr\Camera\OmrCameraFrameReservation;
use App\Assessment\Actions\Omr\CaptureOmrPendingPage;
use App\Assessment\Omr\OmrClientQuad;
use App\Assessment\Omr\OmrSourceImage;
use App\Assessment\Omr\Operational\OmrDiagnosticRunStatusSnapshot;
use App\Assessment\Support\TestKitMockSession;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticItemEvidence;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrPendingCapturePageEvidence;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Appends one QR-disabled diagnostic page attempt and bridges it into the
 * diagnostic evidence namespace without creating production claim records.
 */
final class RetakeOmrDiagnosticPage
{
    public function __construct(
        private readonly CaptureOmrPendingPage $capturePendingPage,
        private readonly CaptureOmrDiagnosticRun $captureDiagnosticRun,
        private readonly OmrDiagnosticRunStatusSnapshot $snapshot,
        private readonly SensitiveAssetStore $assets,
        private readonly RecordAuditEvent $audit,
        private readonly OmrCameraFrameFinalization $cameraFrameFinalization,
    ) {}

    /** @return array{set: OmrPendingCaptureSet, run: OmrDiagnosticRun, capture: OmrDiagnosticCapture, evidence: OmrPendingCapturePageEvidence} */
    public function handle(
        OmrPendingCaptureSet $set,
        User $actor,
        int $expectedRunRevision,
        int $expectedSetRevision,
        int $pageNo,
        string $clientCaptureId,
        string $bytes,
        string $mime,
        ?string $replacementReason = null,
        ?array $clientQuad = null,
        ?string $caseId = null,
        string $cropPolicy = 'auto_document_quad',
        ?OmrCameraFrameReservation $cameraReservation = null,
        ?int $expectedSupersedesId = null,
    ): array {
        if (! in_array($cropPolicy, ['auto_document_quad', 'keep_original'], true)) {
            throw new RuntimeException('omr_capture_set_crop_policy_invalid');
        }
        $run = OmrDiagnosticRun::query()->findOrFail($set->diagnostic_run_id);
        $this->snapshot->assertCanProject($run, $actor);
        if ($set->production_batch_id !== null || $set->diagnostic_run_id !== $run->id) {
            throw new RuntimeException('omr_diagnostic_page_namespace_mismatch');
        }
        if ($run->hasMockContext()
            && ($caseId === null || $caseId !== $set->mock_instance_key)) {
            throw new RuntimeException('omr_mock_capture_target_stale');
        }
        $existingCapture = OmrDiagnosticCapture::query()
            ->where('diagnostic_run_id', $run->id)
            ->where('client_capture_id', $clientCaptureId)
            ->first();
        $pendingEvidenceReplay = OmrPendingCapturePageEvidence::query()
            ->where('set_id', $set->id)
            ->where('client_capture_id', $clientCaptureId)
            ->where('page_no', $pageNo)
            ->first();
        if ($existingCapture !== null && $pendingEvidenceReplay === null) {
            throw new RuntimeException('omr_diagnostic_page_idempotency_conflict');
        }
        $isReplay = $pendingEvidenceReplay !== null;
        if (! $isReplay && (int) $run->revision !== $expectedRunRevision) {
            throw new RuntimeException('omr_capture_set_run_stale');
        }
        if (! $isReplay && (int) $set->fresh()->revision !== $expectedSetRevision) {
            throw new RuntimeException('omr_capture_set_revision_stale');
        }
        if ($run->isTerminal() || $run->sealed_at !== null) {
            throw new RuntimeException('omr_capture_set_context_closed');
        }
        if (! $isReplay && ($run->status !== OmrDiagnosticRun::STATUS_OPEN || $run->phase !== OmrDiagnosticRun::PHASE_CAPTURE)) {
            $run->forceFill([
                'status' => OmrDiagnosticRun::STATUS_OPEN,
                'phase' => OmrDiagnosticRun::PHASE_CAPTURE,
                'completed_at' => null,
            ])->saveOrFail();
        }

        $oldEvidence = OmrPendingCapturePageEvidence::query()
            ->where('set_id', $set->id)
            ->where('page_no', $pageNo)
            ->orderByDesc('id')
            ->first();
        $normalizedMime = strtolower(trim($mime));
        $normalizedReplacementReason = $replacementReason === null ? null : trim($replacementReason);
        if ($pendingEvidenceReplay !== null) {
            $replayQuad = null;
            if ($clientQuad !== null) {
                try {
                    $sourceImage = OmrSourceImage::fromBytes($bytes);
                    $replayQuad = OmrClientQuad::fromArray($clientQuad);
                    if (! $replayQuad->isBoundTo($sourceImage, $pageNo)) {
                        throw new RuntimeException('omr_capture_set_client_quad_source_mismatch');
                    }
                    $replayQuad = $replayQuad->toArray();
                } catch (\Throwable $exception) {
                    throw new RuntimeException('omr_capture_set_idempotency_conflict', previous: $exception);
                }
            }
            if (! hash_equals((string) $pendingEvidenceReplay->input_hash, hash('sha256', $bytes))
                || (string) $pendingEvidenceReplay->mime_type !== $normalizedMime
                || (string) $pendingEvidenceReplay->crop_policy !== $cropPolicy
                || $pendingEvidenceReplay->replacement_reason !== $normalizedReplacementReason
                || (int) ($pendingEvidenceReplay->supersedes_id ?? 0) !== (int) ($expectedSupersedesId ?? 0)
                || $pendingEvidenceReplay->client_quad_json !== $replayQuad) {
                throw new RuntimeException('omr_capture_set_idempotency_conflict');
            }
            $evidence = $pendingEvidenceReplay;
        } else {
            if ($oldEvidence !== null && $replacementReason === null) {
                throw new RuntimeException('omr_capture_set_replacement_reason_required');
            }
            $this->capturePendingPage->handle(
                $set,
                $actor,
                $expectedSetRevision,
                $pageNo,
                $clientCaptureId,
                $bytes,
                $normalizedMime,
                $oldEvidence !== null ? $normalizedReplacementReason : null,
                $clientQuad,
                $cropPolicy,
                $cameraReservation,
                $expectedSupersedesId,
            );
            $evidence = OmrPendingCapturePageEvidence::query()
                ->where('set_id', $set->id)
                ->where('client_capture_id', $clientCaptureId)
                ->firstOrFail();
        }

        $supersededEvidence = $evidence->supersedes_id === null
            ? null
            : OmrPendingCapturePageEvidence::query()->findOrFail($evidence->supersedes_id);
        $source = $this->assets->read($evidence->asset_path);
        if (! hash_equals((string) $evidence->input_hash, hash('sha256', $source))) {
            throw new RuntimeException('omr_capture_set_input_integrity_failed');
        }
        if ($existingCapture !== null) {
            if (! hash_equals((string) $existingCapture->input_asset_hash, (string) $evidence->input_hash)) {
                throw new RuntimeException('omr_diagnostic_page_idempotency_conflict');
            }
            $captureEvidence = is_array($existingCapture->evidence_json) ? $existingCapture->evidence_json : [];
            $existingLink = is_array($captureEvidence['pending_page_link'] ?? null) ? $captureEvidence['pending_page_link'] : null;
            if ($existingLink !== null
                && ((int) ($existingLink['pending_page_evidence_id'] ?? 0) !== (int) $evidence->id
                    || ! hash_equals((string) ($existingLink['input_hash'] ?? ''), (string) $evidence->input_hash)
                    || (int) ($existingLink['capture_set_id'] ?? 0) !== (int) $set->id)) {
                throw new RuntimeException('omr_diagnostic_page_idempotency_conflict');
            }
            if ($existingCapture->status === OmrDiagnosticCapture::STATUS_PROCESSING) {
                $result = $this->captureDiagnosticRun->handle(
                    $run,
                    $source,
                    (string) $evidence->mime_type,
                    $clientCaptureId,
                    $actor,
                    $expectedRunRevision,
                    'pending-page:'.$set->id.':'.$evidence->id,
                    (int) $evidence->page_no,
                    is_array($evidence->client_quad_json) ? $evidence->client_quad_json : null,
                    $caseId,
                    $run->hasMockContext() ? $expectedRunRevision : null,
                    $cropPolicy,
                );
                $postCaptureRun = $result['run'];
                $capture = $result['capture'];
            } else {
                if (in_array($existingCapture->status, [
                    OmrDiagnosticCapture::STATUS_QUEUED,
                ], true)) {
                    throw new RuntimeException('omr_diagnostic_capture_active');
                }
                $capture = $existingCapture;
                $postCaptureRun = OmrDiagnosticRun::query()->findOrFail($run->id);
            }
        } else {
            $run = OmrDiagnosticRun::query()->findOrFail($run->id);
            $result = $this->captureDiagnosticRun->handle(
                $run,
                $source,
                (string) $evidence->mime_type,
                $clientCaptureId,
                $actor,
                (int) $run->revision,
                'pending-page:'.$set->id.':'.$evidence->id,
                (int) $evidence->page_no,
                is_array($evidence->client_quad_json) ? $evidence->client_quad_json : null,
                $caseId,
                $run->hasMockContext() ? $expectedRunRevision : null,
                $cropPolicy,
            );
            $postCaptureRun = $result['run'];
            $capture = $result['capture'];
        }

        return DB::transaction(function () use ($run, $set, $evidence, $capture, $actor, $supersededEvidence, $caseId, $postCaptureRun, $cameraReservation): array {
            $lockedRun = OmrDiagnosticRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($lockedRun->isTerminal() || $lockedRun->sealed_at !== null) {
                throw new RuntimeException('omr_capture_set_context_closed');
            }
            $lockedSet = OmrPendingCaptureSet::query()->lockForUpdate()->findOrFail($set->id);
            if ((int) $lockedRun->revision !== (int) $postCaptureRun->revision) {
                throw new RuntimeException('omr_capture_set_run_stale');
            }
            if ((int) $lockedSet->diagnostic_run_id !== (int) $lockedRun->id
                || $lockedSet->production_batch_id !== null
                || (int) $evidence->set_id !== (int) $lockedSet->id) {
                throw new RuntimeException('omr_diagnostic_page_namespace_mismatch');
            }

            $latestSetEvidence = OmrPendingCapturePageEvidence::query()
                ->where('set_id', (int) $lockedSet->id)
                ->where('page_no', (int) $evidence->page_no)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            if ($latestSetEvidence === null || (int) $latestSetEvidence->id !== (int) $evidence->id) {
                throw new RuntimeException('omr_capture_set_page_superseded');
            }
            $capture = OmrDiagnosticCapture::query()->lockForUpdate()->findOrFail($capture->id);
            $linked = is_array($capture->evidence_json) ? $capture->evidence_json : [];
            $existingLink = is_array($linked['pending_page_link'] ?? null) ? $linked['pending_page_link'] : null;
            if ($existingLink !== null
                && ((int) ($existingLink['capture_set_id'] ?? 0) !== (int) $lockedSet->id
                    || (int) ($existingLink['pending_page_evidence_id'] ?? 0) !== (int) $evidence->id
                    || (int) ($existingLink['page_no'] ?? 0) !== (int) $evidence->page_no
                    || ! hash_equals((string) ($existingLink['input_hash'] ?? ''), (string) $evidence->input_hash)
                    || (int) ($existingLink['supersedes_pending_page_evidence_id'] ?? 0) !== (int) ($supersededEvidence?->id ?? 0))) {
                throw new RuntimeException('omr_diagnostic_page_idempotency_conflict');
            }
            if ($existingLink === null) {
                $preLinkHash = (string) ($linked['evidence_hash'] ?? ($capture->evidence_hash ?? ''));
                $linked['pending_page_link'] = [
                    'capture_set_id' => (int) $lockedSet->id,
                    'pending_page_evidence_id' => (int) $evidence->id,
                    'page_no' => (int) $evidence->page_no,
                    'input_hash' => (string) $evidence->input_hash,
                    'diagnostic_evidence_hash' => $preLinkHash,
                    'supersedes_pending_page_evidence_id' => $supersededEvidence === null ? null : (int) $supersededEvidence->id,
                ];
                $evidenceJson = json_encode($linked, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                $capture->forceFill([
                    'evidence_json' => $linked,
                    'evidence_hash' => hash('sha256', $evidenceJson),
                ])->saveOrFail();
            }

            $plan = $lockedRun->acceptance_plan_json;
            if (is_array($plan) || ($caseId !== null && trim($caseId) !== '')) {
                $priorAttempt = null;
                if ($supersededEvidence !== null) {
                    $priorAttempt = DB::table('test_kit_diagnostic_page_attempts')
                        ->where('diagnostic_run_id', (int) $lockedRun->id)
                        ->where('pending_page_evidence_id', (int) $supersededEvidence->id)
                        ->first();
                }
                $resolvedCaseId = $this->resolveCaseId($lockedRun, (int) $evidence->page_no, $caseId, $priorAttempt);
                $itemBindings = $this->resolveItemBindings($lockedRun, (int) $evidence->page_no, $resolvedCaseId, $capture, $priorAttempt);

                $existingSelection = DB::table('test_kit_diagnostic_page_selections')
                    ->where('diagnostic_run_id', (int) $lockedRun->id)
                    ->where('case_id', $resolvedCaseId)
                    ->where('page_no', (int) $evidence->page_no)
                    ->lockForUpdate()
                    ->first();

                $predecessorAttemptId = $priorAttempt !== null
                    ? (int) $priorAttempt->id
                    : ($existingSelection !== null ? (int) $existingSelection->selected_attempt_id : null);

                $existingAttempt = DB::table('test_kit_diagnostic_page_attempts')
                    ->where('diagnostic_run_id', (int) $lockedRun->id)
                    ->where('pending_page_evidence_id', (int) $evidence->id)
                    ->lockForUpdate()
                    ->first();
                if ($existingAttempt !== null) {
                    if ((int) $existingAttempt->diagnostic_capture_id !== (int) $capture->id
                        || (int) $existingAttempt->pending_set_id !== (int) $lockedSet->id
                        || ! hash_equals((string) $existingAttempt->input_sha256, (string) $evidence->input_hash)
                        || (string) $existingAttempt->case_id !== $resolvedCaseId) {
                        throw new RuntimeException('omr_diagnostic_page_idempotency_conflict');
                    }
                    $attemptId = (int) $existingAttempt->id;
                } else {
                    $predecessorAttemptId = $priorAttempt !== null
                        ? (int) $priorAttempt->id
                        : ($existingSelection !== null ? (int) $existingSelection->selected_attempt_id : null);
                    $attemptId = (int) DB::table('test_kit_diagnostic_page_attempts')->insertGetId([
                        'diagnostic_run_id' => (int) $lockedRun->id,
                        'case_id' => $resolvedCaseId,
                        'page_no' => (int) $evidence->page_no,
                        'diagnostic_capture_id' => (int) $capture->id,
                        'pending_set_id' => (int) $lockedSet->id,
                        'pending_page_evidence_id' => (int) $evidence->id,
                        'input_sha256' => (string) $evidence->input_hash,
                        'predecessor_attempt_id' => $predecessorAttemptId,
                        'item_bindings_json' => json_encode($itemBindings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                        'created_by_user_id' => (int) $actor->id,
                        'created_at' => now(),
                    ]);
                }

                if ($existingSelection !== null
                    && (int) $existingSelection->selected_attempt_id !== $attemptId) {
                    DB::table('test_kit_diagnostic_page_selections')
                        ->where('id', (int) $existingSelection->id)
                        ->update([
                            'selected_attempt_id' => $attemptId,
                            'revision' => (int) $existingSelection->revision + 1,
                            'updated_at' => now(),
                        ]);
                } elseif ($existingSelection === null) {
                    DB::table('test_kit_diagnostic_page_selections')->insert([
                        'diagnostic_run_id' => (int) $lockedRun->id,
                        'case_id' => $resolvedCaseId,
                        'page_no' => (int) $evidence->page_no,
                        'selected_attempt_id' => $attemptId,
                        'revision' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            if ($supersededEvidence !== null) {
                $oldCapture = OmrDiagnosticCapture::query()
                    ->where('diagnostic_run_id', $lockedRun->id)
                    ->where('client_capture_id', $supersededEvidence->client_capture_id)
                    ->lockForUpdate()
                    ->first();
                if ($oldCapture !== null && $oldCapture->failure_code !== 'discarded_by_operator') {
                    $oldCapture->forceFill($oldCapture->status === OmrDiagnosticCapture::STATUS_FAILED
                        ? [
                            'projected_score_json' => null,
                            'version' => (int) $oldCapture->version + 1,
                        ]
                        : [
                            'projected_score_json' => null,
                            'status' => OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
                            'version' => (int) $oldCapture->version + 1,
                        ])->saveOrFail();
                }
            }

            OmrDiagnosticCapture::query()
                ->where('diagnostic_run_id', $lockedRun->id)
                ->whereNotNull('projected_score_json')
                ->where(function ($query): void {
                    $query->whereNull('failure_code')
                        ->orWhere('failure_code', '!=', 'discarded_by_operator');
                })
                ->lockForUpdate()
                ->get()
                ->each(static function (OmrDiagnosticCapture $prior): void {
                    $prior->forceFill($prior->status === OmrDiagnosticCapture::STATUS_FAILED
                        ? [
                            'projected_score_json' => null,
                            'version' => (int) $prior->version + 1,
                        ]
                        : [
                            'projected_score_json' => null,
                            'status' => OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
                            'completed_at' => null,
                            'version' => (int) $prior->version + 1,
                        ])->saveOrFail();
                });
            $lockedRun->forceFill([
                'status' => OmrDiagnosticRun::STATUS_OPEN,
                'phase' => OmrDiagnosticRun::PHASE_CAPTURE,
                'completed_at' => null,
                'revision' => (int) $lockedRun->revision + 1,
            ])->saveOrFail();
            $this->audit->handle('omr.diagnostic.pending_page_bridged', [
                'run_id' => (int) $lockedRun->id,
                'capture_set_id' => (int) $lockedSet->id,
                'pending_page_evidence_id' => (int) $evidence->id,
                'diagnostic_capture_id' => (int) $capture->id,
                'page_no' => (int) $evidence->page_no,
                'input_hash' => (string) $evidence->input_hash,
                'diagnostic_evidence_hash' => (string) $capture->evidence_hash,
                'supersedes_pending_page_evidence_id' => $supersededEvidence === null ? null : (int) $supersededEvidence->id,
                'run_revision' => (int) $lockedRun->revision,
            ], (string) $actor->id);
            if ($cameraReservation !== null && ! $cameraReservation->isReplay()) {
                $this->cameraFrameFinalization->commitAccepted($cameraReservation, [
                    'operation_id' => (string) $cameraReservation->operation->operation_id,
                    'target_key' => (string) ($cameraReservation->operation->target_json['targetKey']
                        ?? $cameraReservation->operation->target_json['target_key'] ?? ''),
                    'status' => 'accepted',
                    'capture_id' => (int) $lockedSet->id,
                    'page_id' => (int) $evidence->id,
                    'pending_capture_id' => (string) $lockedSet->public_id,
                    'pending_capture_revision' => (int) $lockedSet->revision,
                    'diagnostic_capture_id' => (int) $capture->id,
                    'page_no' => (int) $evidence->page_no,
                    'client_capture_id' => (string) $evidence->client_capture_id,
                    'revision' => (int) $lockedSet->revision,
                ]);
            }

            return [
                'set' => $lockedSet->fresh(),
                'run' => $lockedRun->fresh(),
                'capture' => $capture->fresh(),
                'evidence' => $evidence,
            ];
        });
    }

    /**
     * Append a diagnostic successor only while the exact source remains current
     * in an open, unadopted diagnostic set.
     *
     * @return array{set: OmrPendingCaptureSet, run: OmrDiagnosticRun, capture: OmrDiagnosticCapture, evidence: OmrPendingCapturePageEvidence}
     */
    public function handleRedo(
        OmrDiagnosticRun $run,
        OmrPendingCaptureSet $sourceSet,
        OmrPendingCapturePageEvidence $sourceEvidence,
        User $actor,
        int $expectedRunRevision,
        int $expectedSetRevision,
        int $pageNo,
        string $clientCaptureId,
        string $bytes,
        string $mime,
        string $replacementReason,
        ?array $clientQuad = null,
        string $cropPolicy = 'auto_document_quad',
        ?OmrCameraFrameReservation $cameraReservation = null,
    ): array {
        $reason = trim($replacementReason);
        if ($reason === '') {
            throw new RuntimeException('omr_capture_set_replacement_reason_required');
        }
        if ((int) $sourceEvidence->set_id !== (int) $sourceSet->id
            || (int) $sourceEvidence->page_no !== $pageNo
            || $sourceSet->adopted_sheet_id !== null
            || $sourceSet->production_batch_id !== null
            || (int) $sourceSet->diagnostic_run_id !== (int) $run->id) {
            throw new RuntimeException('omr_diagnostic_page_namespace_mismatch');
        }
        $replay = OmrPendingCapturePageEvidence::query()
            ->where('set_id', (int) $sourceSet->id)
            ->where('client_capture_id', $clientCaptureId)
            ->first();
        if ($replay !== null) {
            if ((int) $replay->page_no !== $pageNo
                || (int) $replay->supersedes_id !== (int) $sourceEvidence->id
                || $replay->replacement_reason !== $reason) {
                throw new RuntimeException('omr_capture_set_idempotency_conflict');
            }
        } else {
            $currentSource = OmrPendingCapturePageEvidence::query()
                ->where('set_id', (int) $sourceSet->id)
                ->where('page_no', $pageNo)
                ->orderByDesc('id')
                ->first();
            if ($currentSource === null || (int) $currentSource->id !== (int) $sourceEvidence->id) {
                throw new RuntimeException('omr_capture_set_page_superseded');
            }
        }

        return $this->handle(
            $sourceSet,
            $actor,
            $expectedRunRevision,
            $expectedSetRevision,
            $pageNo,
            $clientCaptureId,
            $bytes,
            $mime,
            $reason,
            $clientQuad,
            $sourceSet->mock_instance_key,
            $cropPolicy,
            $cameraReservation,
            (int) $sourceEvidence->id,
        );
    }

    private function resolveCaseId(
        OmrDiagnosticRun $run,
        int $pageNo,
        ?string $caseId,
        ?object $priorAttempt,
    ): string {
        if ($priorAttempt !== null && is_string($priorAttempt->case_id) && $priorAttempt->case_id !== '') {
            return (string) $priorAttempt->case_id;
        }

        $plan = $run->acceptance_plan_json;
        if (! is_array($plan) || ! isset($plan['cases']) || ! is_array($plan['cases']) || $plan['cases'] === []) {
            if ($caseId !== null && trim($caseId) !== '') {
                return trim($caseId);
            }

            throw new RuntimeException('omr_diagnostic_acceptance_case_required');
        }

        $validCases = [];
        foreach ($plan['cases'] as $case) {
            if (isset($case['case_id']) && is_string($case['case_id'])) {
                $validCases[$case['case_id']] = $case;
            }
        }

        if ($caseId !== null && trim($caseId) !== '') {
            $trimmed = trim($caseId);
            if (! isset($validCases[$trimmed])) {
                throw new RuntimeException('omr_diagnostic_acceptance_case_invalid');
            }

            return $trimmed;
        }

        if (count($validCases) === 1) {
            return (string) array_key_first($validCases);
        }

        throw new RuntimeException('omr_diagnostic_acceptance_case_required');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveItemBindings(
        OmrDiagnosticRun $run,
        int $pageNo,
        string $caseId,
        OmrDiagnosticCapture $capture,
        ?object $priorAttempt,
    ): array {
        if ($run->hasMockContext()) {
            return app(TestKitMockSession::class)->itemBindingsForPage($run, $pageNo);
        }
        $plan = $run->acceptance_plan_json;
        if (! is_array($plan)) {
            throw new RuntimeException('omr_diagnostic_acceptance_plan_missing');
        }

        $bindings = $plan['item_bindings'] ?? null;
        if (! is_array($bindings) || $bindings === []) {
            throw new RuntimeException('omr_diagnostic_acceptance_bindings_missing');
        }

        $pageBindings = array_values(array_filter(
            $bindings,
            static fn (array $b): bool => (int) ($b['page_no'] ?? 0) === $pageNo
        ));
        if ($pageBindings === []) {
            throw new RuntimeException('omr_diagnostic_acceptance_bindings_missing');
        }

        $itemRows = OmrDiagnosticItemEvidence::query()
            ->where('diagnostic_capture_id', (int) $capture->id)
            ->where('page_no', $pageNo)
            ->get();

        $itemsByLabel = [];
        foreach ($itemRows as $itemRow) {
            $itemsByLabel[(string) $itemRow->item_key] = $itemRow;
        }

        $priorBindingsByLabel = [];
        if ($priorAttempt !== null) {
            $priorRaw = $priorAttempt->item_bindings_json ?? $priorAttempt->mapped_item_identity_json ?? null;
            $priorDecoded = is_array($priorRaw) ? $priorRaw : json_decode((string) $priorRaw, true);
            if (is_array($priorDecoded)) {
                foreach ($priorDecoded as $pb) {
                    if (is_array($pb) && isset($pb['native_label'], $pb['item_key'])) {
                        $priorBindingsByLabel[(string) $pb['native_label']] = (string) $pb['item_key'];
                    }
                }
            }
        }

        $mappedBindings = [];
        foreach ($pageBindings as $binding) {
            $label = (string) ($binding['native_label'] ?? '');
            $matchedItem = $itemsByLabel[$label] ?? null;
            if ($matchedItem === null) {
                foreach ($itemRows as $itemRow) {
                    if ($itemRow->item_key === $caseId.'-'.$label || str_ends_with((string) $itemRow->item_key, '-'.$label)) {
                        $matchedItem = $itemRow;
                        break;
                    }
                }
            }
            $itemKey = $matchedItem !== null
                ? (string) $matchedItem->item_key
                : ($priorBindingsByLabel[$label] ?? $label);
            $mappedBindings[] = array_replace($binding, ['item_key' => $itemKey]);
        }

        return $mappedBindings;
    }
}
