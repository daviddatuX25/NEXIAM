<?php

namespace App\Assessment\Omr\Verification;

use App\Assessment\Omr\Canonical\OmrCanonicalGeometryIdentity;
use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticReportService;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrManifestBubbleShader;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationItemEvidence;
use App\Models\Assessment\OmrTemplateVerificationMethod;
use App\Models\Assessment\OmrTemplateVerificationMethodPage;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function array_key_exists;
use function array_map;
use function array_sum;
use function count;
use function hash_equals;
use function in_array;
use function is_array;
use function is_scalar;
use function is_string;
use function max;
use function min;
use function round;
use function str_starts_with;
use function strtolower;
use function trim;
use function usort;

/**
 * Status projection over the durable run (OpenSpec 35.23, AD-6).
 *
 * Reads ONLY the run/evidence rows — never operations, jobs, or browser
 * state. Worker liveness is finalized here (stale heartbeat => failed
 * `worker_timeout`) so a dead worker surfaces a retryable failure instead of
 * a stuck `variant_matrix`.
 */
final class OmrTemplateVerificationStatus
{
    /**
     * User-facing physical capture failures are deliberately bounded here.
     * Provider text remains an observation/technical detail; it never drives
     * client-side copy or recovery decisions.
     *
     * @var array<string, array{what_happened: string, observation: string, recommended_fix: string, phase_code: string}>
     */
    private const FAILURE_CATALOG = [
        'marker_missing' => [
            'what_happened' => 'Corner markers were not found.',
            'observation' => 'The capture did not show all four required corner markers.',
            'recommended_fix' => 'Retake the page with all four corners visible, flat, and unobstructed.',
            'phase_code' => OmrTemplateVerificationRun::PHASE_LAYOUT_VERIFICATION,
        ],
        'marker_mismatch' => [
            'what_happened' => 'Sheet layout verification failed.',
            'observation' => 'The detected corner markers do not match the expected sheet geometry.',
            'recommended_fix' => 'Retake the page square to the camera with all four printed markers visible.',
            'phase_code' => OmrTemplateVerificationRun::PHASE_LAYOUT_VERIFICATION,
        ],
        'sheet_partial' => [
            'what_happened' => 'Only part of the sheet was detected.',
            'observation' => 'The capture showed only a partial set of the required corner markers.',
            'recommended_fix' => 'Retake the full page with every corner marker visible and unobstructed.',
            'phase_code' => OmrTemplateVerificationRun::PHASE_LAYOUT_VERIFICATION,
        ],
        'sheet_misaligned' => [
            'what_happened' => 'Sheet fields were misaligned.',
            'observation' => 'Registered field blocks crossed a native option boundary.',
            'recommended_fix' => 'Retake the page flat and square to the camera, without perspective or curled edges.',
            'phase_code' => OmrTemplateVerificationRun::PHASE_LAYOUT_VERIFICATION,
        ],
        'orientation_mismatch' => [
            'what_happened' => 'The sheet orientation could not be trusted.',
            'observation' => 'The signed challenge orientation disagreed with the marker-valid orientation.',
            'recommended_fix' => 'Retake the page upright with the challenge QR and all corner markers visible.',
            'phase_code' => OmrTemplateVerificationRun::PHASE_ORIENTATION,
        ],
        'rotation_failed' => [
            'what_happened' => 'The sheet orientation could not be resolved.',
            'observation' => 'No safe quarter-turn candidate could be generated for this capture.',
            'recommended_fix' => 'Retake the page as a clear JPEG, PNG, WebP, or single-page PDF.',
            'phase_code' => OmrTemplateVerificationRun::PHASE_ORIENTATION,
        ],
        'capture_preflight_failed' => [
            'what_happened' => 'The image could not be validated.',
            'observation' => 'The capture failed the image format, size, or page-limit checks.',
            'recommended_fix' => 'Retake or upload a clear image within the capture limits.',
            'phase_code' => OmrTemplateVerificationRun::PHASE_PREFLIGHT,
        ],
        'image_decode_failed' => [
            'what_happened' => 'The image could not be decoded.',
            'observation' => 'The verification worker could not read the uploaded image bytes.',
            'recommended_fix' => 'Retake the page or upload a fresh image in a supported format.',
            'phase_code' => OmrTemplateVerificationRun::PHASE_PREFLIGHT,
        ],
        'worker_timeout' => [
            'what_happened' => 'Capture analysis timed out.',
            'observation' => 'The verification worker stopped before finishing the capture.',
            'recommended_fix' => 'Retake the page. If the timeout repeats, ask an administrator to inspect the worker.',
            'phase_code' => OmrTemplateVerificationRun::PHASE_EVIDENCE_PERSISTENCE,
        ],
        'worker_unavailable' => [
            'what_happened' => 'Capture analysis could not reach the worker.',
            'observation' => 'The verification worker was unavailable while this capture was being processed.',
            'recommended_fix' => 'Retake the page after the verification worker is available.',
            'phase_code' => OmrTemplateVerificationRun::PHASE_LAYOUT_VERIFICATION,
        ],
        'analysis_failed' => [
            'what_happened' => 'Capture analysis stopped unexpectedly.',
            'observation' => 'The verification pipeline could not complete this capture safely.',
            'recommended_fix' => 'Retake the page. Ask an administrator to inspect technical details if it repeats.',
            'phase_code' => OmrTemplateVerificationRun::PHASE_RECOGNITION,
        ],
        'capture_analysis_failed' => [
            'what_happened' => 'Capture analysis stopped unexpectedly.',
            'observation' => 'The verification pipeline could not complete this capture safely.',
            'recommended_fix' => 'Retake the page. Ask an administrator to inspect technical details if it repeats.',
            'phase_code' => OmrTemplateVerificationRun::PHASE_RECOGNITION,
        ],
    ];

    public function __construct(
        private readonly OmrVerificationRunService $runs,
        private readonly ?OmrPhysicalAssuranceStatus $physicalAssurance = null,
        private readonly ?CurrentOmrDigitalVerification $digitalVerification = null,
        private readonly ?OmrPhysicalAssuranceCoordinator $physicalCoordinator = null,
        private readonly ?OmrDiagnosticReportService $diagnosticReportService = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(OmrTemplateVerificationRun $run, User $actor): array
    {
        $this->assertAccessible($run, $actor);
        $physical = in_array($run->mode, [
            OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
            OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
        ], true);
        $coordinator = $this->physicalCoordinator ?? app(OmrPhysicalAssuranceCoordinator::class);
        if ($physical) {
            $coordinator->finalizeTimedOutRun($run, $actor);
        } else {
            $this->runs->finalizeTimedOut($run->id);
        }
        $run = $run->fresh();
        if ($run === null) {
            throw new RuntimeException('verification_run_missing');
        }
        if (
            $physical
            && in_array($run->status, [
                OmrTemplateVerificationRun::STATUS_AWAITING_PRINT,
                OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE,
            ], true)
            && $run->methods()
                ->where('method_state', OmrTemplateVerificationMethod::STATE_ANALYZING)
                ->exists()
        ) {
            $run = $coordinator->reconcileRetriedRun($run, $actor);
        }
        $captures = $run->captures()
            ->with(['inputAsset', 'alignedAsset', 'overlayAsset'])
            ->orderBy('id')
            ->get();
        $reviewedCountsByCapture = $this->currentReviewedItemCounts(
            $captures->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all(),
        );
        $methods = $run->methods()
            ->with(['pages.selectedAttempt', 'captures.inputAsset', 'challenge.sheetAsset', 'challenge.guideAsset'])
            ->orderBy('method')
            ->get();
        $challenge = $run->challenges()->with(['sheetAsset', 'guideAsset', 'captures'])->latest('id')->first();

        $total = max(0, (int) $run->progress_total);
        if ($run->mode === OmrTemplateVerificationRun::MODE_DIGITAL) {
            $registrationMode = $run->draft?->registration_mode;
            if ($registrationMode instanceof OmrRegistrationMode) {
                $total = max($total, count(OmrNoGoMatrixRunner::variantKeys(
                    max(1, (int) ($run->draft->requested_page_count ?? 1)),
                    $registrationMode,
                )));
            }
        }
        $done = min($total, max(0, (int) $run->progress_done));
        $phaseDetail = (array) ($run->phase_detail ?? []);
        $phaseCaptureId = (int) ($phaseDetail['capture_id'] ?? 0);
        if ($phaseCaptureId > 0 && $captures->firstWhere('id', $phaseCaptureId)?->isDiscarded()) {
            unset(
                $phaseDetail['capture_id'],
                $phaseDetail['attempt_no'],
                $phaseDetail['page_no'],
                $phaseDetail['failed_phase_code'],
                $phaseDetail['error_code'],
                $phaseDetail['failure_detail'],
            );
        }
        $capturesCount = $captures->count();
        $challengeRecovery = $this->challengeRecovery($run, $challenge, $capturesCount);
        $physicalAssurance = ($this->physicalAssurance ?? app(OmrPhysicalAssuranceStatus::class))->get($run);
        $isRunWideFailure = $coordinator->isRunWideFailure($run);
        $targetStale = $this->hasTargetDrifted($run);
        $digitalVerificationCurrent = $this->digitalVerificationCurrent($run);

        return [
            'run_id' => $run->id,
            'status_url' => route('setup.omr.templates.canonical.verification_runs.status', ['run' => $run->id]),
            'draft_lock_version' => $run->draft_id !== null
                ? (int) ($run->draft?->lock_version ?? 0)
                : null,
            'status' => $run->status,
            'phase' => $run->status,
            'phase_detail' => $phaseDetail,
            'mode' => $run->mode,
            'attempt' => (int) $run->attempt,
            'progress' => [
                'done' => $done,
                'total' => $total,
                'percent' => $total > 0 ? round($done / $total * 100, 1) : 0.0,
            ],
            'current_variant' => $phaseDetail['current_variant'] ?? null,
            'safe_issue' => $run->error_code !== null
                ? ['code' => $run->error_code, 'detail' => $run->safe_error]
                : null,
            'result_summary' => $run->result_summary,
            'available_actions' => $this->availableActions($run, $challenge, $capturesCount, $challengeRecovery),
            'challenge_recovery' => $challengeRecovery,
            'target_current' => ! $targetStale,
            'target_stale' => $targetStale,
            'digital_verification_current' => $digitalVerificationCurrent,
            'physical_assurance' => $physicalAssurance,
            'qualification' => $physicalAssurance,
            'diagnostic_report' => $this->diagnosticReportSummary($run),
            'run_version' => (int) $run->optimistic_version,
            'plan' => [
                'plan_hash' => $run->plan_hash,
                'plan_version' => $run->plan_version,
                'challenge_code' => $run->challenge_code,
                'expected_vector_hash' => $run->expected_vector_hash,
            ],
            'target' => [
                'kind' => $run->draft_id !== null ? OmrDiagnosticTarget::KIND_CANDIDATE : OmrDiagnosticTarget::KIND_COMPILED_VERSION,
                'id' => $run->draft_id ?? $run->template_version_id,
                'hashes' => $run->pinnedTargetHashes(),
            ],
            'timestamps' => [
                'started_at' => $run->started_at?->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
                'last_heartbeat_at' => $run->last_heartbeat_at?->toIso8601String(),
            ],
            'evidence' => $captures->map(fn (OmrTemplateVerificationCapture $capture): array => [
                'capture_id' => $capture->id,
                'attempt' => (int) $capture->attempt_no,
                'page_no' => (int) $capture->page_no,
                'variant' => $capture->variant,
                'expected' => $capture->expected,
                'actual' => $capture->actual,
                'status' => $capture->status,
                'error_code' => $capture->error_code,
                'superseded_by_attempt_id' => $capture->superseded_by_attempt_id,
                'superseded_at' => $capture->superseded_at?->toIso8601String(),
                'input_hash' => $capture->input_hash,
                'input_asset_id' => $capture->input_asset_id,
                'input_asset_sha256' => $capture->inputAsset?->sha256,
                'evidence_hash' => $capture->evidence_hash,
                'expected_vector_hash' => $capture->expected_vector_hash,
                'detected_vector_hash' => $capture->detected_vector_hash,
                'vector_match' => $capture->vector_match,
                'summary' => $capture->method_id === null ? $capture->summary : null,
                'captured_at' => $capture->captured_at?->toIso8601String(),
                'urls' => [
                    'input' => $capture->inputAsset !== null ? $this->assetUrl($run, $capture->inputAsset) : null,
                    'aligned' => $capture->alignedAsset !== null ? $this->assetUrl($run, $capture->alignedAsset) : null,
                    'overlay' => $capture->overlayAsset !== null ? $this->assetUrl($run, $capture->overlayAsset) : null,
                ],
            ])->values()->all(),
            'methods' => $methods->map(function (OmrTemplateVerificationMethod $method) use ($run, $phaseDetail, $targetStale, $isRunWideFailure, $reviewedCountsByCapture, $coordinator): array {
                $methodChallenge = $method->challenge;
                $methodSheet = $methodChallenge?->sheetAsset;
                $methodGuide = $methodChallenge?->guideAsset;
                $guideJson = is_array($methodChallenge?->guide_json) ? $methodChallenge->guide_json : [];
                $mappedPositionsCount = array_sum(array_map(
                    static fn (mixed $count): int => (int) $count,
                    (array) ($guideJson['option_counts'] ?? []),
                ));
                $orderedCaptures = $method->captures->sortByDesc('id')->values();
                $retainedCaptures = $orderedCaptures->reject(
                    static fn (OmrTemplateVerificationCapture $capture): bool => $capture->isDiscarded(),
                )->values();
                $latestCapture = $retainedCaptures->first();
                $latestAttemptId = $latestCapture === null ? null : (int) $latestCapture->id;
                $latestRetainedIdsByPage = $retainedCaptures
                    ->groupBy('page_no')
                    ->map(static fn ($pageCaptures): int => (int) $pageCaptures->sortByDesc('attempt_no')->first()->id)
                    ->all();
                $methodPhaseDetail = $this->methodPhaseDetail($method, $latestCapture, $phaseDetail);
                $methodActions = $this->methodAvailableActions($run, $method, $methodChallenge, $latestCapture, $targetStale);
                $latestProjection = $latestCapture === null ? null : $this->captureVerificationProjection(
                    $latestCapture,
                    $reviewedCountsByCapture[(int) $latestCapture->id] ?? 0,
                );
                $projectedState = $method->method_state;
                if ($projectedState === OmrTemplateVerificationMethod::STATE_NEEDS_REVIEW
                    && ($latestProjection['state'] ?? null) === 'failed') {
                    $projectedState = OmrTemplateVerificationMethod::STATE_FAILED;
                    $methodActions = in_array('review_capture', $methodActions, true) ? ['retake_page'] : $methodActions;
                }

                return [
                    'method' => $method->method,
                    'label' => $method->method === OmrTemplateVerificationMethod::METHOD_HAND_SHADED
                        ? 'Hand-shaded test'
                        : 'Pre-shaded test',
                    'state' => $projectedState,
                    'failure_reason' => $latestProjection['failure_reason'] ?? null,
                    'phase' => $methodPhaseDetail['phase_code'] ?? $method->method_state,
                    'run_status' => $run->status,
                    'status_url' => route('setup.omr.templates.canonical.verification_runs.status', ['run' => $run->id]),
                    'available_actions' => $methodActions,
                    'target_current' => ! $targetStale,
                    'target_stale' => $targetStale,
                    'error_code' => $latestCapture?->error_code ?? $latestCapture?->failure_code,
                    'safe_error' => $latestCapture?->failure_detail,
                    'failure' => $this->failureProjection($method, $latestCapture, $methodPhaseDetail),
                    'target_kind' => $method->target_kind,
                    'target_id' => (int) $method->target_id,
                    'target_version' => $method->target_version,
                    'target_hash' => $method->target_hash,
                    'plan_version' => $method->plan_version,
                    'plan_hash' => $method->plan_hash,
                    'latest_attempt_id' => $latestAttemptId,
                    'required_page_count' => (int) $method->required_page_count,
                    'mapped_positions_count' => $mappedPositionsCount,
                    'phase_detail' => $methodPhaseDetail,
                    'challenge_id' => $method->challenge_id,
                    'print_url' => $methodSheet !== null ? $this->assetUrl($run, $methodSheet) : null,
                    'guide_url' => $methodGuide !== null ? $this->assetUrl($run, $methodGuide) : null,
                    'guide_pdf_url' => $methodGuide?->mime === 'application/pdf' ? $this->assetUrl($run, $methodGuide) : null,
                    'challenge' => $methodChallenge === null ? null : [
                        'challenge_id' => (int) $methodChallenge->id,
                        'method' => $methodChallenge->method,
                        'challenge_code' => $methodChallenge->challenge_code,
                        'mode' => $methodChallenge->mode,
                        'status' => $methodChallenge->status,
                        'plan_hash' => $methodChallenge->plan_hash,
                        'expected_vector_hash' => $methodChallenge->expected_vector_hash,
                        'print_url' => $methodSheet !== null ? $this->assetUrl($run, $methodSheet) : null,
                        'print_mime' => $methodSheet?->mime,
                        'pdf_ready' => $methodSheet?->mime === 'application/pdf',
                        'guide_url' => $methodGuide !== null ? $this->assetUrl($run, $methodGuide) : null,
                        'guide_pdf_url' => $methodGuide?->mime === 'application/pdf' ? $this->assetUrl($run, $methodGuide) : null,
                        'guide' => is_array($methodChallenge->guide_json) ? $methodChallenge->guide_json : null,
                        'sheet_pages' => $this->sheetPages($run, $methodChallenge),
                        'diagnostic' => [
                            'scenario_manifest_hash' => $methodChallenge->scenario_manifest_hash,
                            'coverage_hash' => $methodChallenge->coverage_hash,
                            'coverage' => $methodChallenge->coverage_report,
                            'renderer' => $methodChallenge->renderer_identity,
                            'profile' => $methodChallenge->profile_identity,
                            'geometry_hash' => $methodChallenge->geometry_identity,
                            'source_artifact_hashes' => $methodChallenge->source_artifact_hashes,
                            'transformed_artifact_hashes' => $methodChallenge->transformed_artifact_hashes,
                            'scenario_outcomes' => $methodChallenge->scenario_outcomes,
                        ],
                    ],
                    'pages' => $method->pages->map(fn (OmrTemplateVerificationMethodPage $page): array => [
                        'page_no' => (int) $page->page_no,
                        'required' => (bool) $page->required,
                        'state' => $this->projectedPageState($page, $retainedCaptures),
                        'page_version' => (int) $page->page_version,
                        'selected_attempt_id' => $page->selected_attempt_id,
                        'selected_attempt' => $page->selectedAttempt === null ? null : [
                            'capture_id' => (int) $page->selectedAttempt->id,
                            'attempt_no' => (int) $page->selectedAttempt->attempt_no,
                            'attempt_state' => $page->selectedAttempt->attempt_state,
                            'evidence_hash' => $page->selectedAttempt->evidence_hash,
                            'can_accept_reviewed' => false,
                        ],
                    ])->values()->all(),
                    'attempts' => $retainedCaptures->map(function (OmrTemplateVerificationCapture $capture) use ($method, $methodChallenge, $run, $coordinator, $targetStale, $isRunWideFailure, $latestRetainedIdsByPage, $reviewedCountsByCapture, $latestAttemptId, $methodPhaseDetail): array {
                        $page = $method->pages->firstWhere('page_no', (int) $capture->page_no);
                        $captureSummary = is_array($capture->summary) ? $capture->summary : [];
                        $canAcceptReviewed = $page instanceof OmrTemplateVerificationMethodPage
                            && (int) ($latestRetainedIdsByPage[(int) $capture->page_no] ?? 0) === (int) $capture->id
                            && $run->status !== OmrTemplateVerificationRun::STATUS_PASSED
                            && ! $isRunWideFailure
                            && $methodChallenge?->status !== OmrPhysicalChallenge::STATUS_SUPERSEDED
                            && ! $targetStale
                            && $coordinator->canAcceptReviewedCapture($run, $method, $page, $methodChallenge, $capture);
                        $captureProjection = $this->captureVerificationProjection(
                            $capture,
                            $reviewedCountsByCapture[(int) $capture->id] ?? 0,
                        );
                        if ($canAcceptReviewed) {
                            $captureProjection['needs_review_count'] = 0;
                        }

                        return [
                            'capture_id' => (int) $capture->id,
                            'page_no' => (int) $capture->page_no,
                            'attempt_no' => (int) $capture->attempt_no,
                            'attempt_state' => $capture->attempt_state,
                            'status' => $capture->status,
                            'actual' => $capture->actual,
                            'vector_match' => $capture->vector_match,
                            ...$captureProjection,
                            'phase_detail' => (int) $capture->id === $latestAttemptId ? $methodPhaseDetail : null,
                            'error_code' => $capture->error_code,
                            'failure_code' => $capture->failure_code,
                            'failure_detail' => $capture->failure_detail,
                            'crop_policy' => is_string($captureSummary['crop_policy'] ?? null) ? $captureSummary['crop_policy'] : null,
                            'summary' => null,
                            'evidence_hash' => $capture->evidence_hash,
                            'superseded_by_attempt_id' => $capture->superseded_by_attempt_id,
                            'superseded_at' => $capture->superseded_at?->toIso8601String(),
                            'is_latest' => (int) $capture->id === $latestAttemptId,
                            'is_discarded' => false,
                            'can_discard' => ! $this->isSelectedAttempt($method, $capture)
                                && in_array($capture->attempt_state, [
                                    OmrTemplateVerificationCapture::ATTEMPT_FAILED,
                                    OmrTemplateVerificationCapture::ATTEMPT_PASSED,
                                    OmrTemplateVerificationCapture::ATTEMPT_SUPERSEDED,
                                ], true),
                            'lock_version' => (int) ($capture->lock_version ?? 0),
                            'can_accept_reviewed' => $canAcceptReviewed,
                        ];
                    })->values()->all(),
                    'discarded_attempts' => $orderedCaptures->filter(
                        static fn (OmrTemplateVerificationCapture $capture): bool => $capture->isDiscarded(),
                    )->map(fn (OmrTemplateVerificationCapture $capture): array => [
                        'capture_id' => (int) $capture->id,
                        'page_no' => (int) $capture->page_no,
                        'attempt_no' => (int) $capture->attempt_no,
                        'attempt_state' => $capture->attempt_state,
                        'status' => $capture->status,
                        'actual' => $capture->actual,
                        ...$this->captureVerificationProjection(
                            $capture,
                            $reviewedCountsByCapture[(int) $capture->id] ?? 0,
                        ),
                        'vector_match' => $capture->vector_match,
                        'phase_detail' => null,
                        'error_code' => $capture->error_code,
                        'failure_code' => $capture->failure_code,
                        'failure_detail' => $capture->failure_detail,
                        'crop_policy' => is_array($capture->summary) && is_string($capture->summary['crop_policy'] ?? null)
                            ? $capture->summary['crop_policy']
                            : null,
                        'summary' => null,
                        'evidence_hash' => $capture->evidence_hash,
                        'superseded_by_attempt_id' => $capture->superseded_by_attempt_id,
                        'superseded_at' => $capture->superseded_at?->toIso8601String(),
                        'is_latest' => false,
                        'is_discarded' => true,
                        'can_discard' => false,
                        'can_accept_reviewed' => false,
                        'lock_version' => (int) ($capture->lock_version ?? 0),
                    ])->all(),

                ];
            })->values()->all(),
            'challenge' => $challenge !== null ? [
                'challenge_id' => $challenge->id,
                'method' => $challenge->method,
                'challenge_code' => $challenge->challenge_code,
                'mode' => $challenge->mode,
                'status' => $challenge->status,
                'plan_hash' => $challenge->plan_hash,
                'expected_vector_hash' => $challenge->expected_vector_hash,
                'created_at' => $challenge->created_at?->toIso8601String(),
                'print_url' => $challenge->sheetAsset !== null ? $this->assetUrl($run, $challenge->sheetAsset) : null,
                'print_mime' => $challenge->sheetAsset?->mime,
                'pdf_ready' => $challenge->sheetAsset?->mime === 'application/pdf',
                'guide_url' => $challenge->guideAsset !== null ? $this->assetUrl($run, $challenge->guideAsset) : null,
                'guide_pdf_url' => $challenge->guideAsset !== null && $challenge->guideAsset->mime === 'application/pdf' ? $this->assetUrl($run, $challenge->guideAsset) : null,
                'guide' => is_array($challenge->guide_json) ? $challenge->guide_json : $this->fallbackGuideJson($challenge, $run),
                'sheet_pages' => $this->sheetPages($run, $challenge),
                'renderer_version' => $this->challengeRendererVersion($challenge),
                'reference_current' => $this->isChallengeReferenceCurrent($challenge),
                'diagnostic' => [
                    'scenario_manifest_hash' => $challenge->scenario_manifest_hash,
                    'coverage_hash' => $challenge->coverage_hash,
                    'coverage' => $challenge->coverage_report,
                    'renderer' => $challenge->renderer_identity,
                    'profile' => $challenge->profile_identity,
                    'geometry_hash' => $challenge->geometry_identity,
                    'source_artifact_hashes' => $challenge->source_artifact_hashes,
                    'transformed_artifact_hashes' => $challenge->transformed_artifact_hashes,
                    'scenario_outcomes' => $challenge->scenario_outcomes,
                ],
            ] : null,
        ];
    }

    /**
     * Count current human decisions for machine items that contributed to the
     * persisted observation-review count. One grouped query covers the run.
     *
     * @param  list<int>  $captureIds
     * @return array<int,int>
     */
    private function currentReviewedItemCounts(array $captureIds): array
    {
        if ($captureIds === []) {
            return [];
        }

        $rows = DB::table('omr_template_verification_item_evidence as evidence')
            ->join('omr_item_review_revisions as review', static function (JoinClause $join): void {
                $join->on('review.verification_item_evidence_id', '=', 'evidence.id')
                    ->on('review.item_key', '=', 'evidence.item_id');
            })
            ->whereIn('evidence.capture_id', $captureIds)
            ->where('evidence.group_type', OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA)
            ->where(static function (Builder $query): void {
                $states = [
                    OmrTemplateVerificationItemEvidence::STATE_AMBIGUOUS,
                    OmrTemplateVerificationItemEvidence::STATE_MISMATCH,
                    'unreadable',
                ];
                $query->whereIn('evidence.state', $states)
                    ->orWhereIn('evidence.status', $states)
                    ->orWhere(static function (Builder $missing): void {
                        $missing->where(static function (Builder $state): void {
                            $state->where('evidence.state', OmrTemplateVerificationItemEvidence::STATE_MISSING)
                                ->orWhere('evidence.status', OmrTemplateVerificationItemEvidence::STATE_MISSING);
                        })
                            ->whereNotNull('evidence.expected')
                            ->where('evidence.expected', '<>', '');
                    });
            })
            ->whereIn('review.decision', ['confirm_machine', 'set_option', 'blank'])
            ->whereColumn('review.reviewed_machine_evidence_hash', 'evidence.evidence_hash')
            ->whereNotExists(static function (Builder $newer): void {
                $newer
                    ->selectRaw('1')
                    ->from('omr_item_review_revisions as newer_review')
                    ->whereColumn('newer_review.verification_item_evidence_id', 'review.verification_item_evidence_id')
                    ->whereColumn('newer_review.item_key', 'review.item_key')
                    ->whereColumn('newer_review.revision', '>', 'review.revision');
            })
            ->groupBy('evidence.capture_id')
            ->selectRaw('evidence.capture_id, COUNT(DISTINCT evidence.id) AS reviewed_count')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->capture_id] = (int) $row->reviewed_count;
        }

        return $counts;
    }

    /**
     * @return array{
     *     state:string,
     *     gates_pass:bool|null,
     *     failure_reason:string|null,
     *     needs_review_count:int|null,
     *     machine_needs_review_count:int|null
     * }
     */
    private function captureVerificationProjection(
        OmrTemplateVerificationCapture $capture,
        int $reviewedItemCount = 0,
    ): array {
        $summary = is_array($capture->summary) ? $capture->summary : [];
        $result = (array) ($summary['verification_result'] ?? []);
        $gatesPass = is_bool($result['gates_pass'] ?? null) ? $result['gates_pass'] : null;
        $count = $result['needs_review_count'] ?? ($summary['needs_review_count'] ?? null);
        $count = is_int($count) && $count > 0 ? $count : null;
        $remainingCount = $count === null ? null : max(0, $count - max(0, $reviewedItemCount));
        $reason = $capture->failure_code ?: $capture->error_code;
        $failed = $capture->attempt_state === OmrTemplateVerificationCapture::ATTEMPT_FAILED
            || $capture->status === OmrTemplateVerificationCapture::STATUS_FAIL;
        $review = $failed
            && $capture->attempt_state !== OmrTemplateVerificationCapture::ATTEMPT_PASSED
            && $capture->status !== OmrTemplateVerificationCapture::STATUS_PASS
            && $capture->actual === OmrTemplateVerificationCapture::ACTUAL_ANSWER_VECTOR_MISMATCH
            && $capture->failure_code === 'capture_needs_review'
            && $gatesPass === true
            && $count !== null;
        if ($failed && $capture->actual === 'typed_rejection' && $gatesPass === null) {
            $reason = 'binding_evidence_unrecorded';
        }

        return [
            'state' => $review ? 'needs_review' : ($failed ? 'failed' : (string) $capture->attempt_state),
            'gates_pass' => $gatesPass,
            'failure_reason' => $reason,
            'needs_review_count' => $review ? $remainingCount : null,
            'machine_needs_review_count' => $review ? $count : null,
        ];
    }

    private function projectedPageState(OmrTemplateVerificationMethodPage $page, iterable $captures): string
    {
        if ($page->page_state === OmrTemplateVerificationMethodPage::STATE_NEEDS_REVIEW) {
            foreach ($captures as $capture) {
                if ((int) $capture->page_no === (int) $page->page_no && ! $capture->isSuperseded()) {
                    return $this->captureVerificationProjection($capture)['state'] === 'failed'
                        ? OmrTemplateVerificationMethodPage::STATE_FAILED
                        : (string) $page->page_state;
                }
            }
        }

        return (string) $page->page_state;
    }

    private function isSelectedAttempt(
        OmrTemplateVerificationMethod $method,
        OmrTemplateVerificationCapture $capture,
    ): bool {
        return $method->pages->contains(
            static fn (OmrTemplateVerificationMethodPage $page): bool => (int) $page->selected_attempt_id === (int) $capture->id,
        );
    }

    public function assertAccessible(OmrTemplateVerificationRun $run, User $actor): void
    {
        if ($run->draft_id === null) {
            return; // version-targeted runs are capability-gated by the route middleware
        }
        $draft = $run->draft;
        if ($draft === null || (int) $draft->owner_user_id !== (int) $actor->id) {
            throw new RuntimeException('verification_run_forbidden');
        }
    }

    /**
     * @return array{kind: 'repair_reference'|'restart_rehearsal'|null, action_url: string|null, reason_code: string, detail: string}|null
     */
    public function challengeRecovery(OmrTemplateVerificationRun $run, ?OmrPhysicalChallenge $challenge, int $capturesCount = 0): ?array
    {
        if ($challenge === null || ! in_array($run->mode, [OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL, OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION], true)) {
            return null;
        }
        if ($challenge->status === OmrPhysicalChallenge::STATUS_SUPERSEDED || $run->error_code === 'target_changed_superseded') {
            return null;
        }

        if ($capturesCount > 0 || $challenge->captures()->count() > 0) {
            return [
                'kind' => null,
                'action_url' => null,
                'reason_code' => 'evidence_immutable',
                'detail' => 'Historical captures exist for this challenge; evidence is immutable and cannot be regenerated or restarted.',
            ];
        }

        if (! in_array($run->status, [OmrTemplateVerificationRun::STATUS_AWAITING_PRINT, OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE, OmrTemplateVerificationRun::STATUS_FAILED], true)) {
            return null;
        }

        $targetDrifted = $this->hasTargetDrifted($run);

        if (! $targetDrifted) {
            if (! $this->isChallengeReferenceCurrent($challenge)) {
                return [
                    'kind' => 'repair_reference',
                    'action_url' => route('setup.omr.templates.canonical.verification_runs.challenge.regenerate', ['run' => $run->id]),
                    'reason_code' => 'renderer_outdated',
                    'detail' => 'The physical challenge reference pack is outdated and can be safely repaired in place.',
                ];
            }

            return null;
        }

        // Target drifted
        if ($run->mode === OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL && $run->draft_id !== null) {
            return [
                'kind' => 'restart_rehearsal',
                'action_url' => route('setup.omr.templates.canonical.verification_runs.challenge.restart', ['run' => $run->id]),
                'reason_code' => 'target_changed',
                'detail' => 'The draft geometry has changed since this challenge was created. Restart rehearsal with the updated target.',
            ];
        }

        return [
            'kind' => null,
            'action_url' => null,
            'reason_code' => 'target_changed',
            'detail' => 'The target geometry has drifted from the pinned challenge plan.',
        ];
    }

    /**
     * The digital gate is independently authoritative from a physical run:
     * draft targets use the strict current-run matcher, while compiled
     * targets use the immutable verification hashes carried by the version.
     */
    private function digitalVerificationCurrent(OmrTemplateVerificationRun $run): bool
    {
        if ($run->draft_id !== null) {
            $draft = $run->draft;

            return $draft !== null
                && ($this->digitalVerification ?? app(CurrentOmrDigitalVerification::class))->find($draft) !== null;
        }

        $manifest = is_array($run->templateVersion?->authoring_manifest)
            ? $run->templateVersion->authoring_manifest
            : [];
        $hashes = is_array($manifest['verification_hashes'] ?? null)
            ? $manifest['verification_hashes']
            : [];
        foreach (['digital_verification', 'matrix_hash', 'proof_hash'] as $key) {
            if (! is_string($hashes[$key] ?? null) || trim((string) $hashes[$key]) === '') {
                return false;
            }
        }

        return true;
    }

    public function hasTargetDrifted(OmrTemplateVerificationRun $run): bool
    {
        if ($run->draft_id !== null) {
            $draft = $run->draft;
            if ($draft === null || $draft->isCompiled() || ! ($draft->registration_mode instanceof OmrRegistrationMode)) {
                return true;
            }

            $registrationMode = $draft->registration_mode;
            if ($registrationMode === OmrRegistrationMode::FiducialAnchored
                && ($draft->markerProfile === null
                    || (int) $draft->marker_profile_id !== (int) $draft->markerProfile->id
                    || ! $draft->markerProfile->isQualified())) {
                return true;
            }
            if ($registrationMode === OmrRegistrationMode::DocumentBound
                && ($draft->markerProfile !== null || $draft->marker_profile_id !== null)) {
                return true;
            }

            $authoring = (array) $draft->authoring_json;
            $canonical = (array) ($authoring['canonical'] ?? []);
            $artifactSet = (array) ($canonical['artifact_set'] ?? []);
            $settings = (array) ($canonical['settings'] ?? []);
            $manifest = (array) ($artifactSet['manifest'] ?? []);
            $template = (array) ($artifactSet['template'] ?? []);
            $reviewManifest = (array) ($artifactSet['review_manifest'] ?? []);
            if ($registrationMode === OmrRegistrationMode::FiducialAnchored) {
                $pinnedProfile = (array) (($manifest['marker_profile'] ?? null) ?: []);
                if ((int) ($pinnedProfile['id'] ?? 0) !== (int) $draft->markerProfile->id
                    || (int) ($pinnedProfile['version'] ?? 0) !== (int) $draft->markerProfile->version
                    || ! hash_equals((string) ($pinnedProfile['asset_sha256'] ?? ''), (string) $draft->markerProfile->asset_sha256)
                    || ($pinnedProfile['status'] ?? null) !== OmrMarkerProfile::STATUS_QUALIFIED) {
                    return true;
                }
            }
            foreach ([$artifactSet, $manifest, $template, $reviewManifest] as $representation) {
                if (($representation['registration_mode'] ?? null) !== $registrationMode->value) {
                    return true;
                }
            }
            if ($registrationMode === OmrRegistrationMode::DocumentBound) {
                if (! empty($authoring['marker'])
                    || array_key_exists('marker_inset_mm', $settings)
                    || (int) ($artifactSet['marker_asset_id'] ?? 0) > 0
                    || (string) ($artifactSet['marker_hash'] ?? '') !== ''
                    || $draft->assets()->where('kind', OmrTemplateAsset::KIND_MARKER_PROFILE)->exists()
                    || array_key_exists('marker_profile', $manifest)) {
                    return true;
                }
                foreach ((array) ($template['pages'] ?? []) as $page) {
                    if (is_array($page)
                        && (array_key_exists('markers', $page) || ($page['preProcessors'] ?? []) !== [])) {
                        return true;
                    }
                }
            }
            $currentArtifactHash = (string) ($artifactSet['artifact_hash'] ?? '');
            if ($currentArtifactHash === '') {
                return true;
            }

            try {
                $currentGeometryIdentity = OmrCanonicalGeometryIdentity::forDraft($draft);
            } catch (\Throwable) {
                return true;
            }

            $pinned = $run->pinnedTargetHashes();

            $artifactMatches = hash_equals((string) ($pinned['artifact'] ?? ''), $currentArtifactHash);
            $geometryMatches = hash_equals((string) ($pinned['mapping'] ?? ''), $currentGeometryIdentity);

            return ! ($artifactMatches && $geometryMatches);
        }

        return false;
    }

    /**
     * Project the method-owned worker phase without borrowing a sibling's
     * run-level telemetry. A physical run has one worker phase at a time, but
     * both method rows remain durable and independently actionable.
     *
     * @return array<string, mixed>|null
     */
    private function methodPhaseDetail(
        OmrTemplateVerificationMethod $method,
        ?OmrTemplateVerificationCapture $latestCapture,
        array $runPhaseDetail,
    ): ?array {
        $captureSummary = $latestCapture !== null && is_array($latestCapture->summary)
            ? $latestCapture->summary
            : [];
        $captureContext = $latestCapture === null ? [] : [
            'page_no' => (int) $latestCapture->page_no,
            'capture_id' => (int) $latestCapture->id,
            'attempt_no' => (int) $latestCapture->attempt_no,
            'crop_policy' => is_string($captureSummary['crop_policy'] ?? null) ? $captureSummary['crop_policy'] : null,
        ];

        if ($method->method_state === OmrTemplateVerificationMethod::STATE_ANALYZING) {
            $phaseDetail = ! isset($runPhaseDetail['method'])
                || $runPhaseDetail['method'] === $method->method
                ? $runPhaseDetail
                : [];
            $phaseCode = is_string($phaseDetail['phase_code'] ?? null)
                ? $phaseDetail['phase_code']
                : OmrTemplateVerificationRun::PHASE_QUEUED;

            return [
                ...$phaseDetail,
                'phase_code' => $phaseCode,
                'stage' => is_string($phaseDetail['stage'] ?? null) ? $phaseDetail['stage'] : $phaseCode,
                'method' => $method->method,
                ...$captureContext,
            ];
        }

        if ($method->method_state === OmrTemplateVerificationMethod::STATE_FAILED) {
            $summary = is_array($latestCapture?->summary) ? $latestCapture->summary : [];
            $errorCode = is_string($latestCapture?->error_code) && trim($latestCapture->error_code) !== ''
                ? trim($latestCapture->error_code)
                : (is_string($latestCapture?->failure_code) ? trim($latestCapture->failure_code) : '');
            $failedPhaseCode = is_string($summary['failed_phase_code'] ?? null)
                ? $summary['failed_phase_code']
                : (isset(self::FAILURE_CATALOG[$errorCode]) ? self::FAILURE_CATALOG[$errorCode]['phase_code'] : null);

            return [
                'phase_code' => OmrTemplateVerificationRun::PHASE_FAILED,
                'stage' => OmrTemplateVerificationRun::PHASE_FAILED,
                'method' => $method->method,
                ...$captureContext,
                'failed_phase_code' => $failedPhaseCode,
                'error_code' => $latestCapture?->error_code ?? $latestCapture?->failure_code,
                'failure_detail' => $latestCapture?->failure_detail,
            ];
        }

        if ($method->method_state === OmrTemplateVerificationMethod::STATE_NEEDS_REVIEW) {
            return [
                'phase_code' => OmrTemplateVerificationRun::PHASE_AWAITING_REVIEW,
                'stage' => OmrTemplateVerificationRun::PHASE_AWAITING_REVIEW,
                'method' => $method->method,
                ...$captureContext,
            ];
        }

        if ($method->method_state === OmrTemplateVerificationMethod::STATE_PASSED) {
            return [
                'phase_code' => OmrTemplateVerificationRun::PHASE_PASSED,
                'stage' => OmrTemplateVerificationRun::PHASE_PASSED,
                'method' => $method->method,
                ...$captureContext,
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function failureProjection(
        OmrTemplateVerificationMethod $method,
        ?OmrTemplateVerificationCapture $capture,
        ?array $phaseDetail,
    ): ?array {
        if ($capture === null) {
            return null;
        }
        $projection = $this->captureVerificationProjection($capture);
        if ($method->method_state !== OmrTemplateVerificationMethod::STATE_FAILED && $projection['state'] !== 'failed') {
            return null;
        }

        $rawCode = $projection['failure_reason'];
        $code = is_string($rawCode) && trim($rawCode) !== '' ? trim($rawCode) : 'unknown_failure';
        $catalog = self::FAILURE_CATALOG[$code] ?? null;
        $known = $catalog !== null;
        $workerDetail = is_string($capture->failure_detail) ? trim($capture->failure_detail) : '';
        $observation = $known && in_array($code, [
            'marker_missing',
            'marker_mismatch',
            'sheet_partial',
            'sheet_misaligned',
        ], true) && $workerDetail !== ''
            ? $workerDetail
            : ($catalog['observation'] ?? 'The verification service returned an unrecognized failure; no specific diagnosis is available.');
        $bindingRejected = $capture->actual === OmrTemplateVerificationCapture::ACTUAL_TYPED_REJECTION;
        if ($bindingRejected) {
            $observation = $code === 'binding_evidence_unrecorded'
                ? 'The original capture was rejected, but the failed check was not recorded. Retake this page for a new verification result.'
                : 'Failed verification check: '.$code.'.';
        }

        return [
            'known' => $known,
            'code' => $code,
            'what_happened' => $bindingRejected ? 'Verification checks rejected this capture.' : ($catalog['what_happened'] ?? 'Capture analysis stopped.'),
            'observation' => $observation,
            'recommended_fix' => $catalog['recommended_fix'] ?? 'Retake the page. Ask an administrator to inspect the technical details if the problem repeats.',
            'recovery_action' => 'retake_page',
            'technical_details' => [
                'phase_code' => $phaseDetail['failed_phase_code'] ?? $phaseDetail['phase_code'] ?? null,
                'capture_id' => (int) $capture->id,
                'attempt_no' => (int) $capture->attempt_no,
                'error_code' => $capture->error_code,
                'failure_code' => $capture->failure_code,
                'worker_detail' => $workerDetail !== '' ? $workerDetail : null,
            ],
        ];
    }

    /**
     * Derive continuation actions from only the method's durable child rows.
     *
     * @return list<string>
     */
    private function methodAvailableActions(
        OmrTemplateVerificationRun $run,
        OmrTemplateVerificationMethod $method,
        ?OmrPhysicalChallenge $challenge,
        ?OmrTemplateVerificationCapture $latestCapture,
        bool $targetStale,
    ): array {
        if (
            $targetStale
            || ! in_array($run->mode, [
                OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
                OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
            ], true)
            || in_array($run->status, [
                OmrTemplateVerificationRun::STATUS_FAILED,
                OmrTemplateVerificationRun::STATUS_PASSED,
            ], true)
            || $challenge?->status === OmrPhysicalChallenge::STATUS_SUPERSEDED
        ) {
            return [];
        }

        return match ($method->method_state) {
            OmrTemplateVerificationMethod::STATE_NOT_STARTED => $challenge === null ? ['create_challenge'] : [],
            OmrTemplateVerificationMethod::STATE_CHALLENGE_READY => $challenge === null
                ? []
                : ($challenge->sheetAsset?->mime !== 'application/pdf'
                    ? ['prepare_print', 'print_challenge']
                    : ['print_challenge']),
            OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE => ['submit_capture'],
            OmrTemplateVerificationMethod::STATE_NEEDS_REVIEW => $latestCapture === null ? [] : ['review_capture'],
            OmrTemplateVerificationMethod::STATE_FAILED => $challenge !== null && $method->pages->contains(
                static fn (OmrTemplateVerificationMethodPage $page): bool => $page->required
                    && in_array($page->page_state, [
                        OmrTemplateVerificationMethodPage::STATE_FAILED,
                        OmrTemplateVerificationMethodPage::STATE_NEEDS_REVIEW,
                    ], true),
            ) ? ['retake_page'] : [],
            default => [],
        };
    }

    /**
     * Available challenge/capture actions derived from the run state machine.
     * Digital run phases expose no challenge/capture actions; physical
     * phases advertise exactly the action that owns the next transition.
     * A passed physical challenge already has its pack and cannot expose
     * `create_challenge` again.
     *
     * @param  array{kind: 'repair_reference'|'restart_rehearsal'|null, action_url: string|null, reason_code: string, detail: string}|null  $recovery
     * @return list<string>
     */
    private function availableActions(
        OmrTemplateVerificationRun $run,
        ?OmrPhysicalChallenge $challenge,
        int $capturesCount = 0,
        ?array $recovery = null,
    ): array {
        if ($run->error_code === 'target_changed_superseded' || $challenge?->status === OmrPhysicalChallenge::STATUS_SUPERSEDED) {
            return [];
        }

        if ($recovery !== null) {
            return match ($recovery['kind']) {
                'repair_reference' => ['regenerate_challenge'],
                'restart_rehearsal' => ['restart_challenge'],
                default => [],
            };
        }

        $actions = match ($run->status) {
            OmrTemplateVerificationRun::STATUS_FAILED => ['retry'],
            OmrTemplateVerificationRun::STATUS_PASSED => $run->mode === OmrTemplateVerificationRun::MODE_DIGITAL
                && $run->template_version_id !== null
                && $challenge === null
                ? ['create_challenge']
                : [],
            OmrTemplateVerificationRun::STATUS_AWAITING_PRINT => $challenge !== null && $challenge->sheetAsset?->mime !== 'application/pdf'
                ? ['prepare_print', 'print_challenge']
                : ['print_challenge'],
            OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE => ['submit_capture'],
            OmrTemplateVerificationRun::STATUS_NEEDS_REVIEW => ['review_capture'],
            default => [],
        };

        return $actions;
    }

    public function challengeRendererVersion(?OmrPhysicalChallenge $challenge): ?string
    {
        if ($challenge === null) {
            return null;
        }

        $version = $challenge->sheetAsset?->metadata['renderer_version'] ?? null;
        if ($version !== null && is_string($version) && $version !== '') {
            return $version;
        }

        $firstPage = OmrTemplateAsset::query()
            ->where(function ($query) use ($challenge): void {
                if ($challenge->run?->draft_id !== null) {
                    $query->where('authoring_draft_id', $challenge->run->draft_id);
                } elseif ($challenge->run?->template_version_id !== null) {
                    $query->where('template_version_id', $challenge->run->template_version_id);
                }
            })
            ->where('kind', OmrTemplateAsset::KIND_RASTER_PAGE)
            ->whereJsonContains('metadata->challenge_code', $challenge->challenge_code)
            ->first();

        return $firstPage?->metadata['renderer_version'] ?? null;
    }

    public function isChallengeReferenceCurrent(?OmrPhysicalChallenge $challenge): bool
    {
        if ($challenge === null) {
            return false;
        }
        if ($challenge->renderer_version === 'supplier_original') {
            return true;
        }

        $version = $this->challengeRendererVersion($challenge);
        if ($version !== OmrManifestBubbleShader::RENDERER_VERSION) {
            return false;
        }

        if ($challenge->guide_json === null || ! is_array($challenge->guide_json) || $challenge->guide_json === []) {
            return false;
        }

        if ($challenge->sheet_asset_id === null || $challenge->guide_asset_id === null) {
            return false;
        }

        return true;
    }

    /** @return array<string, mixed>|null */
    private function fallbackGuideJson(?OmrPhysicalChallenge $challenge, OmrTemplateVerificationRun $run): ?array
    {
        if ($challenge === null) {
            return null;
        }

        return [
            'schema_version' => 'v1',
            'mode' => $challenge->mode,
            'challenge_code' => $challenge->challenge_code,
            'sections' => [],
            'instructions' => 'Print at 100% / actual size — do not fit to page.',
            'calibration_disclaimer' => 'Applicant number columns are for calibration only — no applicant is identified.',
            'identity_columns' => [],
        ];
    }

    private function assetUrl(OmrTemplateVerificationRun $run, OmrTemplateAsset $asset, ?string $sha256 = null): string
    {
        $parameters = [
            'run' => $run->id,
            'asset' => $asset->id,
        ];
        if ($sha256 !== null && $sha256 !== '') {
            $parameters['sha256'] = $sha256;
        }

        return route('setup.omr.templates.canonical.verification_runs.assets.show', $parameters);
    }

    /**
     * @return list<array{
     *     page_no: int,
     *     url: string,
     *     mime: string,
     *     sha256?: string,
     *     reference_only?: bool,
     *     width_px?: int,
     *     height_px?: int,
     *     bubbles?: list<array{
     *         label: string,
     *         option: string,
     *         x: int,
     *         y: int,
     *         w: int,
     *         h: int,
     *         shaded: bool
     *     }>
     * }>
     */
    public function sheetPages(OmrTemplateVerificationRun $run, OmrPhysicalChallenge $challenge, ?string $sheetHash = null): array
    {
        $sheetHash ??= (string) ($challenge->sheetAsset?->metadata['sheet_hash'] ?? '');
        $rendererVersion = $this->challengeRendererVersion($challenge);
        $pages = collect();

        if ($sheetHash !== '' && is_string($rendererVersion) && $rendererVersion !== '') {
            $pages = OmrTemplateAsset::query()
                ->where(function ($query) use ($run): void {
                    if ($run->draft_id !== null) {
                        $query->where('authoring_draft_id', $run->draft_id);
                    } elseif ($run->template_version_id !== null) {
                        $query->where('template_version_id', $run->template_version_id);
                    }
                })
                ->where('kind', OmrTemplateAsset::KIND_RASTER_PAGE)
                ->whereJsonContains('metadata->role', 'challenge_sheet_page')
                ->whereJsonContains('metadata->sheet_hash', $sheetHash)
                ->whereJsonContains('metadata->renderer_version', $rendererVersion)
                ->orderBy('page_no')
                ->orderByDesc('id')
                ->get()
                ->unique(static fn (OmrTemplateAsset $asset): int => (int) ($asset->page_no ?? 1))
                ->values();
        }

        if ($pages->isNotEmpty()) {
            return $pages->map(fn (OmrTemplateAsset $a): array => [
                'page_no' => (int) ($a->page_no ?? 1),
                'url' => $this->assetUrl($run, $a),
                'mime' => (string) $a->mime,
            ])->values()->all();
        }

        if (OmrSourcePolicy::sourceKindForRun($run) === OmrSourcePolicy::UPLOADED_ORIGINAL
            || $challenge->renderer_version === 'supplier_original'
        ) {
            $referencePages = $this->referenceSheetPagesForUploadedRun($run, $challenge);
            if (! empty($referencePages)) {
                return $referencePages;
            }
        }

        if ($challenge->sheetAsset !== null
            && str_starts_with((string) $challenge->sheetAsset->mime, 'image/')
        ) {
            $pages = collect([$challenge->sheetAsset]);

            return $pages->map(fn (OmrTemplateAsset $a): array => [
                'page_no' => (int) ($a->page_no ?? 1),
                'url' => $this->assetUrl($run, $a),
                'mime' => (string) $a->mime,
            ])->values()->all();
        }

        return [];
    }

    /**
     * @return list<array{
     *     page_no: int,
     *     url: string,
     *     mime: string,
     *     sha256: string,
     *     reference_only: true,
     *     width_px: int,
     *     height_px: int,
     *     bubbles: list<array{
     *         label: string,
     *         option: string,
     *         x: int,
     *         y: int,
     *         w: int,
     *         h: int,
     *         shaded: bool
     *     }>
     * }>
     */
    private function referenceSheetPagesForUploadedRun(OmrTemplateVerificationRun $run, OmrPhysicalChallenge $challenge): array
    {
        $version = $run->relationLoaded('templateVersion')
            ? $run->getRelation('templateVersion')
            : ($run->template_version_id !== null ? $run->templateVersion()->first() : null);

        $draft = $run->relationLoaded('draft')
            ? $run->getRelation('draft')
            : ($run->draft_id !== null ? $run->draft()->first() : null);

        $artifactSet = $version?->authoring_manifest['artifact_set']
            ?? $draft?->authoring_json['canonical']['artifact_set']
            ?? [];
        if (! is_array($artifactSet)) {
            $artifactSet = [];
        }

        $rasterEntries = is_array($artifactSet['raster_assets'] ?? null)
            ? $artifactSet['raster_assets']
            : [];

        $reviewManifest = $version?->review_manifest_json
            ?? $artifactSet['manifest']
            ?? [];
        if (! is_array($reviewManifest)) {
            $reviewManifest = [];
        }

        $candidateAssets = OmrTemplateAsset::query()
            ->where(function ($query) use ($version, $draft, $run): void {
                $hasCondition = false;
                $versionId = $version?->id ?? $run->template_version_id;
                if ($versionId !== null) {
                    $query->where('template_version_id', $versionId);
                    $hasCondition = true;
                }
                $draftId = $draft?->id ?? $run->draft_id;
                if ($draftId !== null) {
                    if ($hasCondition) {
                        $query->orWhere('authoring_draft_id', $draftId);
                    } else {
                        $query->where('authoring_draft_id', $draftId);
                        $hasCondition = true;
                    }
                }
                if (! $hasCondition) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->where('kind', OmrTemplateAsset::KIND_RASTER_PAGE)
            ->orderByRaw('CASE WHEN template_version_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByDesc('id')
            ->get();

        $scheduled = [];
        $guideJson = is_array($challenge->guide_json) ? $challenge->guide_json : [];

        foreach ((array) ($guideJson['sections'] ?? []) as $section) {
            if (! is_array($section)) {
                continue;
            }
            $secPageNo = (int) ($section['page_no'] ?? $section['page'] ?? 1);
            $items = (array) ($section['items'] ?? $section['labels'] ?? []);
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $fieldLabel = (string) ($item['field'] ?? $item['label'] ?? '');
                if ($fieldLabel === '') {
                    continue;
                }
                $optionsToMark = [];
                if (isset($item['option']) && trim((string) $item['option']) !== '') {
                    $optionsToMark[] = trim((string) $item['option']);
                }
                if (! empty($item['candidates']) && is_array($item['candidates'])) {
                    foreach ($item['candidates'] as $candidate) {
                        if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                            $optionsToMark[] = trim((string) $candidate);
                        }
                    }
                }
                foreach ($optionsToMark as $opt) {
                    $scheduled[$secPageNo][$fieldLabel][$opt] = true;
                }
            }
        }

        foreach ((array) ($guideJson['identity_columns'] ?? []) as $col) {
            if (! is_array($col)) {
                continue;
            }
            $idLabel = (string) ($col['label'] ?? '');
            $idDigit = isset($col['digit']) ? (string) $col['digit'] : '';
            if ($idLabel === '' || $idDigit === '') {
                continue;
            }
            foreach ((array) ($reviewManifest['fields'] ?? []) as $field) {
                if (! is_array($field) || (string) ($field['label'] ?? '') !== $idLabel) {
                    continue;
                }
                $idPageNo = (int) ($field['page'] ?? 1);
                $scheduled[$idPageNo][$idLabel][$idDigit] = true;
                break;
            }
        }

        $pageDimensions = [];
        foreach ((array) ($reviewManifest['pages'] ?? []) as $pageMeta) {
            if (! is_array($pageMeta)) {
                continue;
            }
            $pNo = (int) ($pageMeta['page_no'] ?? 0);
            $dims = $pageMeta['page_dimensions'] ?? [];
            $width = is_array($dims) ? (int) ($dims['width'] ?? 2480) : 2480;
            $height = is_array($dims) ? (int) ($dims['height'] ?? 3508) : 3508;
            $pageDimensions[$pNo] = [
                'width' => $width > 0 ? $width : 2480,
                'height' => $height > 0 ? $height : 3508,
            ];
        }

        $fieldsByPage = [];
        foreach ((array) ($reviewManifest['fields'] ?? []) as $field) {
            if (! is_array($field)) {
                continue;
            }
            $pNo = (int) ($field['page'] ?? 1);
            $fieldsByPage[$pNo][] = $field;
        }

        usort($rasterEntries, static fn (array $a, array $b): int => ((int) ($a['page_no'] ?? 0)) <=> ((int) ($b['page_no'] ?? 0)));

        $result = [];
        foreach ($rasterEntries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $pageNo = (int) ($entry['page_no'] ?? 0);
            $entryAssetId = (int) ($entry['asset_id'] ?? 0);
            $entrySha = strtolower((string) ($entry['sha256'] ?? ''));

            $rasterAsset = $candidateAssets->first(function (OmrTemplateAsset $a) use ($pageNo, $entrySha, $entryAssetId): bool {
                if ((int) ($a->page_no ?? 0) !== $pageNo) {
                    return false;
                }
                if (strtolower((string) $a->sha256) !== $entrySha) {
                    return false;
                }
                $pinnedId = (int) ($a->metadata['pinned_from_asset_id'] ?? 0);

                return (int) $a->id === $entryAssetId || $pinnedId === $entryAssetId;
            });

            if ($rasterAsset === null) {
                $rasterAsset = $candidateAssets->first(function (OmrTemplateAsset $a) use ($pageNo, $entrySha): bool {
                    return (int) ($a->page_no ?? 0) === $pageNo && strtolower((string) $a->sha256) === $entrySha;
                });
            }

            if ($rasterAsset === null) {
                continue;
            }

            $bubbles = [];
            $targetCount = 0;
            foreach ($fieldsByPage[$pageNo] ?? [] as $field) {
                $isShaded = ! empty($scheduled[$pageNo][(string) ($field['label'] ?? '')][(string) ($field['option'] ?? '')]);
                if ($isShaded) {
                    $targetCount++;
                }
                $bubbles[] = [
                    'label' => (string) ($field['label'] ?? ''),
                    'option' => (string) ($field['option'] ?? ''),
                    'x' => (int) ($field['pixel']['x'] ?? 0),
                    'y' => (int) ($field['pixel']['y'] ?? 0),
                    'w' => (int) ($field['pixel']['w'] ?? 35),
                    'h' => (int) ($field['pixel']['h'] ?? 35),
                    'shaded' => $isShaded,
                ];
            }

            $pageDims = $pageDimensions[$pageNo] ?? ['width' => 2480, 'height' => 3508];

            $result[] = [
                'page_no' => $pageNo,
                'url' => route('setup.omr.templates.canonical.verification_runs.assets.show', [
                    'run' => $run->id,
                    'asset' => $rasterAsset->id,
                    'sha256' => $rasterAsset->sha256,
                ]),
                'mime' => (string) ($rasterAsset->mime ?: 'image/png'),
                'sha256' => (string) $rasterAsset->sha256,
                'reference_only' => true,
                'width_px' => (int) ($pageDims['width'] ?? 2480),
                'height_px' => (int) ($pageDims['height'] ?? 3508),
                'target_count' => $targetCount,
                'bubbles' => $bubbles,
            ];
        }

        return $result;
    }

    /** @return array<string, mixed>|null */
    public function diagnosticReportSummary(OmrTemplateVerificationRun $run): ?array
    {
        try {
            $service = $this->diagnosticReportService ?? app(OmrDiagnosticReportService::class);
            $report = $service->buildReport($run);

            return [
                'manifest_hash' => $report['manifest_hash'],
                'manifest_version' => $report['manifest_version'],
                'plan' => $report['plan'],
                'lane_summaries' => $report['lane_summaries'],
                'qualification_status' => $report['qualification_status'],
                'coverage' => [
                    'is_complete' => (bool) ($report['coverage']['is_complete'] ?? false),
                    'gaps' => (array) ($report['coverage']['gaps'] ?? []),
                ],
                'report_url' => route('setup.omr.templates.canonical.verification_runs.diagnostic_report', ['run' => $run->id]),
                'export_url' => route('setup.omr.templates.canonical.verification_runs.diagnostic_report.export', ['run' => $run->id]),
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
