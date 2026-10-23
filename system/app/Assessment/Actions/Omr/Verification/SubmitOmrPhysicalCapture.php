<?php

namespace App\Assessment\Actions\Omr\Verification;

use App\Admission\Support\CommandIdempotency;
use App\Assessment\Omr\Canonical\OmrPdfPageRasterizer;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticEvidence;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTargetException;
use App\Assessment\Omr\Diagnostics\OmrProofPlan;
use App\Assessment\Omr\OmrCaptureAnalysisRequest;
use App\Assessment\Omr\OmrCaptureInput;
use App\Assessment\Omr\OmrCapturePreflight;
use App\Assessment\Omr\OmrDetectedItem;
use App\Assessment\Omr\Verification\OmrPhysicalAssuranceCoordinator;
use App\Assessment\Omr\Verification\OmrPhysicalQrOrientation;
use App\Assessment\Omr\Verification\OmrVerificationRunService;
use App\Assessment\Omr\Verification\OmrVerificationTargetLoader;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Foundation\Operation;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

use function count;
use function file_get_contents;
use function file_put_contents;
use function hash;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Shared physical capture submission (OpenSpec 35.28, AD-1/AD-5/AD-8).
 *
 * One action accepts JPEG/PNG/WebP camera/upload captures AND scanned PDFs
 * (rasterized through the existing bounded pipeline), enforces the source
 * preflight limits, verifies the calibration-purpose QR when one is
 * readable, rebuilds the pinned target/plan from the run row, runs the
 * shared {@see OmrTemplateDiagnosticRunner}, persists forensic/aligned/
 * overlay assets and normalized evidence per attempt, compares the detected
 * vector EXACTLY to the plan, and transitions
 * `analyzing_capture → awaiting_capture|needs_review|passed|failed`.
 *
 * Nothing here fabricates a pass: a hard worker/layout failure fails the
 * run; an ambiguous/missing answer or a failing gate parks the run in
 * `needs_review` for human review; only an EXACT vector recovery with
 * passing gates advances a page — and the run passes only when every page
 * has passed.
 */
final class SubmitOmrPhysicalCapture
{
    public const CAPTURE_SOURCES = ['camera', 'upload'];

    public function __construct(
        private readonly OmrVerificationTargetLoader $targets,
        private readonly CommandIdempotency $idempotency,
        private readonly OmrCapturePreflight $capturePreflight,
        private readonly OmrPdfPageRasterizer $rasterizer,
        private readonly OmrTemplateAssetStore $assets,
        private readonly OmrPhysicalAssuranceCoordinator $coordinator,
        private readonly OmrPhysicalQrOrientation $qrOrientation,
        private readonly OmrVerificationRunService $runs,
    ) {}

    /**
     * @return array{run: OmrTemplateVerificationRun, capture: OmrTemplateVerificationCapture, operation: Operation}
     */
    public function handle(
        OmrTemplateVerificationRun $run,
        User $actor,
        string $bytes,
        string $mime,
        string $captureSource,
        int $pageNo = 1,
        ?string $qrPayload = null,
        ?string $methodName = null,
        ?int $challengeId = null,
        ?string $targetHash = null,
        ?string $planHash = null,
        ?string $expectedVectorHash = null,
        ?string $medium = null,
        ?string $idempotencyKey = null,
        ?bool $autoSelect = null,
        ?string $cropPolicy = 'auto_document_quad',
    ): array {
        $methodName = is_string($methodName) ? trim($methodName) : '';
        if (! in_array($methodName, OmrProofPlan::METHODS, true) || $challengeId === null || $challengeId < 1) {
            throw new RuntimeException('verification_method_challenge_required: A physical capture must name its method and challenge.');
        }
        if ($methodName === OmrProofPlan::METHOD_HAND_SHADED && ($medium === null || trim($medium) === '')) {
            $medium = OmrTemplateVerificationCapture::MEDIUM_HB_2_PENCIL;
        }
        $key = trim((string) $idempotencyKey);
        if ($key === '') {
            throw new RuntimeException('idempotency_key_required: A physical capture requires an idempotency key.');
        }
        if (! in_array($run->mode, [
            OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
            OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
        ], true)) {
            throw new RuntimeException('capture_mode_invalid: Physical captures are accepted only for challenge runs.');
        }
        $challenge = $run->challenges()->whereKey($challengeId)->first();
        if ($challenge === null) {
            throw new RuntimeException('wrong_challenge: The challenge does not belong to this run.');
        }
        if ((string) $challenge->method !== $methodName) {
            throw new RuntimeException('challenge_method_mismatch: The challenge belongs to a different physical method.');
        }

        $inputHash = hash('sha256', $bytes);
        try {
            $operation = $this->idempotency->claim(
                $actor,
                'omr.canonical.physical_capture',
                'run:'.$run->id,
                [
                    'run_id' => (int) $run->id,
                    'method' => $methodName,
                    'challenge_id' => (int) $challengeId,
                    'page_no' => $pageNo,
                    'input_hash' => $inputHash,
                    'capture_source' => $captureSource,
                    'medium' => $medium,
                    'target_hash' => $targetHash,
                    'plan_hash' => $planHash,
                    'expected_vector_hash' => $expectedVectorHash,
                    'crop_policy' => $cropPolicy ?? 'auto_document_quad',
                ],
                $key,
            );
        } catch (RuntimeException $exception) {
            throw new RuntimeException('idempotency_key_conflict: '.$exception->getMessage());
        }

        $savedResult = is_array($operation->payload['result'] ?? null) ? $operation->payload['result'] : null;
        if (! $operation->wasRecentlyCreated && $savedResult !== null) {
            $capture = OmrTemplateVerificationCapture::query()->find((int) ($savedResult['capture_id'] ?? 0));
            if ($capture !== null) {
                return ['run' => $capture->run()->firstOrFail()->fresh(), 'capture' => $capture->fresh(), 'operation' => $operation];
            }
            $operation->forceFill([
                'status' => Operation::STATUS_FAILED,
                'last_error' => 'idempotency_result_stale',
                'finished_at' => now(),
            ])->save();
            throw new RuntimeException('idempotency_result_stale');
        }
        if (! $operation->wasRecentlyCreated && $operation->status === Operation::STATUS_PROCESSING) {
            throw new RuntimeException('idempotency_in_progress: The original capture request is still being analyzed.');
        }
        $operation->forceFill([
            'status' => Operation::STATUS_PROCESSING,
            'attempts' => (int) $operation->attempts + 1,
            'last_error' => null,
            'finished_at' => null,
        ])->save();

        try {
            // Resolve the persisted target before reserving capture bytes.
            // This loader-only read does not materialize candidate/PDF bytes.
            $this->targets->loadPhysical($run, $methodName);
            $this->qrOrientation->assertPayload($qrPayload, (string) $challenge->challenge_code, $pageNo);
            $claimed = $this->coordinator->beginCapture(
                run: $run,
                actor: $actor,
                methodName: $methodName,
                challengeId: (int) $challengeId,
                pageNo: $pageNo,
                bytes: $bytes,
                mime: $mime,
                captureSource: $captureSource,
                medium: $medium,
                targetHash: $targetHash,
                planHash: $planHash,
                expectedVectorHash: $expectedVectorHash,
                idempotencyKey: $key,
                autoSelect: $autoSelect ?? false,
                operation: $operation,
                cropPolicy: $cropPolicy,
            );
            $capture = $claimed['capture'];
        } catch (\Throwable $exception) {
            $operation->forceFill([
                'status' => Operation::STATUS_FAILED,
                'last_error' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();
            throw $exception;
        }

        // The queue dispatch occurs after the active-attempt transaction has
        // committed. Sync queues complete before this method returns; real
        // workers leave the durable attempt in `active`/`analyzing`.
        if ($capture->attempt_state !== OmrTemplateVerificationCapture::ATTEMPT_ACTIVE) {
            return ['run' => $capture->run()->firstOrFail()->fresh(), 'capture' => $capture->fresh(), 'operation' => $operation->fresh()];
        }

        return [
            'run' => $capture->run()->firstOrFail()->fresh(),
            'capture' => $capture->fresh(),
            'operation' => $operation->fresh(),
        ];
    }

    /** Analyze one active attempt; called by AnalyzeOmrTemplateVerificationCaptureJob. */
    public function analyzeAttempt(int $captureId, ?bool $autoSelect = null): void
    {
        $capture = OmrTemplateVerificationCapture::query()
            ->with(['run', 'challenge', 'inputAsset', 'capturedBy'])
            ->find($captureId);
        if ($capture === null || $capture->attempt_state !== OmrTemplateVerificationCapture::ATTEMPT_ACTIVE) {
            return;
        }
        $run = $capture->run;
        $actor = $capture->capturedBy;
        if (! $run instanceof OmrTemplateVerificationRun || ! $actor instanceof User) {
            return;
        }
        $storedAutoSelect = is_array($capture->summary)
            && (bool) ($capture->summary['auto_select'] ?? false);
        $autoSelect = $storedAutoSelect || ($autoSelect ?? false);
        $createdEvidenceAssets = [];
        try {
            $inputAsset = $capture->inputAsset;
            if ($inputAsset === null) {
                throw new RuntimeException('capture_input_asset_missing');
            }
            $bytes = Storage::disk($inputAsset->disk)->get($inputAsset->path);
            if (! is_string($bytes) || hash('sha256', $bytes) !== $inputAsset->sha256) {
                throw new RuntimeException('capture_input_asset_corrupt');
            }
            $loaded = $this->targets->loadPhysical($run, (string) $capture->method);
            $target = $loaded['target'];
            $plan = $loaded['plan'];
            $method = $loaded['method'];
            $expectedPageCount = max(1, (int) ($method->required_page_count ?? count($plan->pageInstructions)));
            $this->updateWorkerPhase($run, OmrTemplateVerificationRun::PHASE_PREFLIGHT, $capture);
            $analysisBytes = $this->preflight($bytes, (string) ($inputAsset->mime ?: 'image/png'), (int) $capture->page_no, $expectedPageCount);
            $analysisMime = strtolower((string) ($inputAsset->mime ?: 'image/png')) === 'application/pdf' ? 'image/png' : strtolower((string) ($inputAsset->mime ?: 'image/png'));
            $prepared = $this->coordinator->analyze(new OmrCaptureAnalysisRequest(
                capture: new OmrCaptureInput(
                    bytes: $analysisBytes,
                    mime: $analysisMime,
                    pageCount: 1,
                    originalHash: hash('sha256', $bytes),
                    cropPolicy: is_array($capture->summary) ? ($capture->summary['crop_policy'] ?? 'auto_document_quad') : 'auto_document_quad',
                ),
                target: $target,
                plan: $plan,
                pageNo: (int) $capture->page_no,
                expectedVectorHash: (string) $capture->expected_vector_hash,
                challengeCode: (string) ($capture->challenge?->challenge_code ?? ''),
                onPhase: function (string $phaseCode) use ($run, $capture): void {
                    if ($phaseCode !== OmrTemplateVerificationRun::PHASE_PREFLIGHT) {
                        $this->updateWorkerPhase($run, $phaseCode, $capture);
                    }
                },
            ));
            $evidence = $prepared->asDiagnosticEvidence();
            [$detected, $vectorMatch] = $this->detectedComparison($evidence, $plan->expectedVectorForPage((int) $capture->page_no));
            unset($detected);
            $this->updateWorkerPhase($run, OmrTemplateVerificationRun::PHASE_EVIDENCE_PERSISTENCE, $capture);
            $owner = $this->assetOwner($run);
            $alignedEvidence = $this->evidenceAsset($evidence, 'aligned');
            $overlayEvidence = $this->evidenceAsset($evidence, 'overlay');
            $alignedAsset = $alignedEvidence !== null
                ? $this->putCaptureAsset($owner, $run, $alignedEvidence, (int) $capture->page_no, 'aligned', null)
                : null;
            $overlayAsset = $overlayEvidence !== null
                ? $this->putCaptureAsset($owner, $run, $overlayEvidence, (int) $capture->page_no, 'overlay', null)
                : null;
            foreach ([$alignedAsset, $overlayAsset] as $evidenceAsset) {
                if ($evidenceAsset?->wasRecentlyCreated) {
                    $createdEvidenceAssets[] = $evidenceAsset;
                }
            }
            $this->coordinator->completeCapture(
                capture: $capture,
                prepared: $prepared,
                evidence: $evidence,
                actor: $actor,
                vectorMatch: $vectorMatch,
                alignedAsset: $alignedAsset,
                overlayAsset: $overlayAsset,
                captureSource: is_array($capture->summary) ? ($capture->summary['capture_source'] ?? null) : null,
                autoSelect: $autoSelect,
            );
            $this->finalizeCaptureOperation($capture, Operation::STATUS_SUCCEEDED);
        } catch (OmrDiagnosticTargetException $exception) {
            $this->cleanupEvidenceAssets($createdEvidenceAssets);
            $this->coordinator->failCapture($capture, $actor, $exception->errorCode, 'The capture target changed after submission; the retained attempt can be retried.');
            $this->finalizeCaptureOperation($capture, Operation::STATUS_FAILED, $exception->getMessage());
        } catch (\Throwable $exception) {
            $this->cleanupEvidenceAssets($createdEvidenceAssets);
            $this->coordinator->failCapture($capture, $actor, $this->safeCode($exception), 'The capture analysis failed before finishing; the retained attempt can be retried.');
            $this->finalizeCaptureOperation($capture, Operation::STATUS_FAILED, $exception->getMessage());
        }
    }

    /**
     * Validate capture resources through the shared preflight, then rasterize
     * only the requested PDF page through the existing PDF adapter.
     */
    private function preflight(string $bytes, string $mime, int $pageNo, int $expectedPageCount): string
    {
        $result = $this->capturePreflight->validate(new OmrCaptureInput(
            bytes: $bytes,
            mime: $mime,
            pageNo: $pageNo,
            expectedPageCount: max(1, $expectedPageCount),
        ));
        if (! $result->succeeded()) {
            throw new RuntimeException($result->code.': '.$result->detail);
        }

        if (strtolower(trim($mime)) !== 'application/pdf') {
            return $bytes;
        }

        $pdfPath = tempnam(sys_get_temp_dir(), 'nexiam-capture-pdf-');
        if ($pdfPath === false || file_put_contents($pdfPath, $bytes) === false) {
            if ($pdfPath !== false) {
                @unlink($pdfPath);
            }
            throw new RuntimeException('capture_pdf_write_failed');
        }
        try {
            $pages = $this->rasterizer->rasterize($pdfPath, ['ppi' => (int) config('omr.canonical.ppi', 300)]);
        } finally {
            @unlink($pdfPath);
        }
        $page = $pages[$pageNo - 1] ?? null;
        if (! is_array($page)) {
            throw new RuntimeException('capture_pdf_raster_failed');
        }
        $png = (string) file_get_contents((string) $page['path']);
        if ($png === '') {
            throw new RuntimeException('capture_pdf_raster_failed');
        }

        $rasterResult = $this->capturePreflight->validate(new OmrCaptureInput(
            bytes: $png,
            mime: 'image/png',
        ));
        if (! $rasterResult->succeeded()) {
            throw new RuntimeException($rasterResult->code.': '.$rasterResult->detail);
        }

        return $png;
    }

    /**
     * Exact detected-vs-plan comparison over the normalized evidence items:
     * every expected label on this page must be recovered with the exact
     * declared option.
     *
     * @param  array<string, string>  $expected
     * @return array{0: array<string, string>, 1: bool}
     */
    private function detectedComparison(OmrDiagnosticEvidence $evidence, array $expected): array
    {
        $detected = [];
        $unresolvedObservation = false;
        foreach ($evidence->items as $item) {
            if (! is_array($item)) {
                if ($expected === []) {
                    $unresolvedObservation = true;
                }

                continue;
            }
            $label = (string) ($item['native_label'] ?? '');
            $state = (string) (($item['detected']['state'] ?? null) ?: '');
            $selected = $item['detected']['selected_option'] ?? null;
            if ($label === '') {
                if ($expected === []) {
                    $unresolvedObservation = true;
                }

                continue;
            }
            if (array_key_exists($label, $expected)) {
                if ($state === OmrDetectedItem::STATE_MARKED && $selected !== null) {
                    $detected[$label] = (string) $selected;
                } else {
                    $unresolvedObservation = true;
                }

                continue;
            }
            if ($expected === [] && ($selected !== null || $state !== OmrDetectedItem::STATE_BLANK)) {
                $unresolvedObservation = true;
            }
        }
        $match = ! $unresolvedObservation && count($detected) === count($expected);
        if ($match) {
            foreach ($expected as $label => $option) {
                if (! array_key_exists($label, $detected) || $detected[$label] !== $option) {
                    $match = false;
                    break;
                }
            }
        }

        return [$detected, $match];
    }

    /**
     * @return array{bytes:string,mime:string,width:int,height:int}|null
     */
    private function evidenceAsset(OmrDiagnosticEvidence $evidence, string $name): ?array
    {
        $asset = $evidence->assets[$name] ?? null;
        if (! is_array($asset) || ! is_string($asset['bytes'] ?? null)) {
            return null;
        }
        if (! is_int($asset['width'] ?? null) || $asset['width'] < 1
            || ! is_int($asset['height'] ?? null) || $asset['height'] < 1) {
            throw new RuntimeException('capture_evidence_dimensions_missing');
        }

        return [
            'bytes' => $asset['bytes'],
            'mime' => is_string($asset['mime'] ?? null) ? $asset['mime'] : 'image/png',
            'width' => $asset['width'],
            'height' => $asset['height'],
        ];
    }

    /**
     * @return array{version: OmrAnswerSheetTemplateVersion|null, draft: OmrTemplateAuthoringDraft|null}
     */
    private function assetOwner(OmrTemplateVerificationRun $run): array
    {
        if ($run->isDraftTargeted()) {
            $draft = OmrTemplateAuthoringDraft::query()->find($run->draft_id);
            if ($draft === null) {
                throw new OmrAuthoringDraftException('draft_missing', 'The candidate draft for this run is no longer available.');
            }

            return ['version' => null, 'draft' => $draft];
        }
        $version = OmrAnswerSheetTemplateVersion::query()->find($run->template_version_id);
        if ($version === null) {
            throw new OmrAuthoringDraftException('compiled_version_missing', 'The compiled template version for this run is no longer available.');
        }

        return ['version' => $version, 'draft' => null];
    }

    /**
     * @param  array{version: OmrAnswerSheetTemplateVersion|null, draft: OmrTemplateAuthoringDraft|null}  $owner
     * @param  array{bytes:string,mime:string,width:int,height:int}  $image
     */
    private function putCaptureAsset(
        array $owner,
        OmrTemplateVerificationRun $run,
        array $image,
        int $pageNo,
        string $role,
        ?string $captureSource,
    ): OmrTemplateAsset {
        $metadata = [
            'run_id' => $run->id,
            'attempt' => $run->attempt,
            'challenge_code' => $run->challenge_code,
            'role' => $role,
            'width' => $image['width'],
            'height' => $image['height'],
        ];
        if ($captureSource !== null) {
            $metadata['capture_source'] = $captureSource;
        }
        if ($owner['draft'] !== null) {
            return $this->assets->putDraftAsset($owner['draft'], OmrTemplateAsset::KIND_EVIDENCE, $image['bytes'], $image['mime'], $pageNo, $metadata);
        }

        return $this->assets->putVersionAsset($owner['version'], OmrTemplateAsset::KIND_EVIDENCE, $image['bytes'], $image['mime'], $pageNo, $metadata);
    }

    private function finalizeCaptureOperation(OmrTemplateVerificationCapture $capture, string $status, ?string $lastError = null): void
    {
        $operationId = is_array($capture->summary) ? (string) ($capture->summary['operation_id'] ?? '') : '';
        if ($operationId === '') {
            return;
        }
        $operation = Operation::query()->find($operationId);
        if ($operation === null) {
            return;
        }
        $operation->forceFill([
            'status' => $status,
            'last_error' => $lastError,
            'finished_at' => now(),
        ])->save();
    }

    /** @param list<OmrTemplateAsset> $assets */
    private function cleanupEvidenceAssets(array $assets): void
    {
        foreach ($assets as $asset) {
            Storage::disk($asset->disk)->delete($asset->path);
        }
    }

    private function safeCode(\Throwable $exception): string
    {
        if ($exception instanceof OmrDiagnosticTargetException) {
            return $exception->errorCode;
        }

        return 'capture_analysis_failed';
    }

    private function updateWorkerPhase(OmrTemplateVerificationRun $run, string $phaseCode, OmrTemplateVerificationCapture $capture): void
    {
        $phaseDetail = array_merge((array) ($run->phase_detail ?? []), [
            'phase_code' => $phaseCode,
            'stage' => $phaseCode,
            'method' => (string) $capture->method,
            'page_no' => (int) $capture->page_no,
            'capture_id' => (int) $capture->id,
            'attempt_no' => (int) $capture->attempt_no,
            'crop_policy' => (string) ($capture->summary['crop_policy'] ?? OmrPhysicalAssuranceCoordinator::CROP_AUTO_DOCUMENT_QUAD),
        ]);
        $this->runs->syncPhysicalWorkerPhase($run->id, $capture->id, OmrTemplateVerificationRun::STATUS_ANALYZING_CAPTURE, [
            'phase_detail' => $phaseDetail,
            'last_heartbeat_at' => now(),
        ]);
    }
}
