<?php

namespace App\Assessment\Omr\Verification;

use App\Admission\Support\CommandIdempotency;
use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticEvidence;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTargetException;
use App\Assessment\Omr\Diagnostics\OmrPhysicalChallengeRenderer;
use App\Assessment\Omr\Diagnostics\OmrProofPlan;
use App\Assessment\Omr\OmrCaptureAnalysisPipeline;
use App\Assessment\Omr\OmrCaptureAnalysisRequest;
use App\Assessment\Omr\OmrDetectedItem;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrDocumentRectification;
use App\Assessment\Omr\OmrPreparedCaptureEvidence;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Assessment\AnalyzeOmrTemplateVerificationCaptureJob;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrItemReviewRevision;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationItemEvidence;
use App\Models\Assessment\OmrTemplateVerificationMethod;
use App\Models\Assessment\OmrTemplateVerificationMethodPage;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Foundation\Operation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

use function array_map;
use function array_values;
use function count;
use function hash;
use function hash_equals;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;
use function max;
use function now;
use function str_contains;
use function str_starts_with;
use function strtolower;

/**
 * Transaction owner for the method-bound physical assurance lifecycle.
 *
 * Rendering, recognition, and source preflight remain in their existing
 * modules. This coordinator is the only application boundary that mutates
 * method/page/attempt state, selected pointers, and physical run summaries.
 * Every mutating method locks rows in run -> method -> page -> attempt order.
 */
final class OmrPhysicalAssuranceCoordinator
{
    public const CROP_AUTO_DOCUMENT_QUAD = 'auto_document_quad';

    /** @var array<string, list<string>> */
    private const METHOD_TRANSITIONS = [
        OmrTemplateVerificationMethod::STATE_NOT_STARTED => [OmrTemplateVerificationMethod::STATE_CHALLENGE_READY],
        OmrTemplateVerificationMethod::STATE_CHALLENGE_READY => [OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE, OmrTemplateVerificationMethod::STATE_FAILED],
        OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE => [OmrTemplateVerificationMethod::STATE_ANALYZING, OmrTemplateVerificationMethod::STATE_PASSED, OmrTemplateVerificationMethod::STATE_FAILED],
        OmrTemplateVerificationMethod::STATE_ANALYZING => [OmrTemplateVerificationMethod::STATE_NEEDS_REVIEW, OmrTemplateVerificationMethod::STATE_PASSED, OmrTemplateVerificationMethod::STATE_FAILED, OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE],
        OmrTemplateVerificationMethod::STATE_NEEDS_REVIEW => [OmrTemplateVerificationMethod::STATE_PASSED, OmrTemplateVerificationMethod::STATE_FAILED, OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE],
        OmrTemplateVerificationMethod::STATE_PASSED => [OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE],
        OmrTemplateVerificationMethod::STATE_FAILED => [OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE],
    ];

    /** @var array<string, list<string>> */
    private const PAGE_TRANSITIONS = [
        OmrTemplateVerificationMethodPage::STATE_NOT_STARTED => [OmrTemplateVerificationMethodPage::STATE_AWAITING_CAPTURE],
        OmrTemplateVerificationMethodPage::STATE_AWAITING_CAPTURE => [OmrTemplateVerificationMethodPage::STATE_ANALYZING, OmrTemplateVerificationMethodPage::STATE_PASSED, OmrTemplateVerificationMethodPage::STATE_FAILED],
        OmrTemplateVerificationMethodPage::STATE_ANALYZING => [OmrTemplateVerificationMethodPage::STATE_NEEDS_REVIEW, OmrTemplateVerificationMethodPage::STATE_PASSED, OmrTemplateVerificationMethodPage::STATE_FAILED, OmrTemplateVerificationMethodPage::STATE_AWAITING_CAPTURE],
        OmrTemplateVerificationMethodPage::STATE_NEEDS_REVIEW => [OmrTemplateVerificationMethodPage::STATE_PASSED, OmrTemplateVerificationMethodPage::STATE_FAILED, OmrTemplateVerificationMethodPage::STATE_AWAITING_CAPTURE],
        OmrTemplateVerificationMethodPage::STATE_PASSED => [OmrTemplateVerificationMethodPage::STATE_AWAITING_CAPTURE],
        OmrTemplateVerificationMethodPage::STATE_FAILED => [OmrTemplateVerificationMethodPage::STATE_AWAITING_CAPTURE],
    ];

    /** @var list<string> */
    private const RUN_WIDE_FAILURE_CODES = [
        'stale_target',
        'target_changed_superseded',
        'worker_timeout',
        'verification_challenge_binding_invalid',
        'verification_method_binding_mismatch',
        'stale_plan',
        'stale_expected_vector',
    ];

    public function __construct(
        private readonly OmrVerificationRunService $runs,
        private readonly OmrCaptureAnalysisPipeline $analysis,
        private readonly OmrTemplateAssetStore $assets,
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly OmrVerificationTargetLoader $targets,
        private readonly CapabilityAuthorizer $authorizer,
    ) {}

    public function discardAttempt(
        OmrTemplateVerificationRun $run,
        OmrTemplateVerificationCapture $capture,
        User $actor,
        array $payload,
        string $idempotencyKey,
    ): array {
        $this->authorizeDiscard($run, $actor);
        $captureReference = OmrTemplateVerificationCapture::query()
            ->whereKey($capture->id)
            ->where('run_id', $run->id)
            ->first();
        if ($captureReference === null) {
            throw new ModelNotFoundException('Verification capture is outside this run.');
        }
        $operationPayload = [
            'run_id' => (int) $run->id,
            'capture_id' => (int) $captureReference->id,
            'method' => (string) $payload['method'],
            'page_no' => (int) $payload['page_no'],
            'target_hash' => (string) $payload['target_hash'],
            'plan_hash' => (string) $payload['plan_hash'],
            'expected_vector_hash' => (string) $payload['expected_vector_hash'],
            'page_version' => (int) $payload['page_version'],
            'capture_lock_version' => (int) $payload['capture_lock_version'],
        ];

        try {
            return DB::transaction(function () use ($run, $captureReference, $actor, $payload, $idempotencyKey, $operationPayload): array {
                $operation = $this->idempotency->claim(
                    $actor,
                    'omr.canonical.discard_physical_attempt',
                    'run:'.$run->id,
                    $operationPayload,
                    $idempotencyKey,
                );
                $receipt = is_array($operation->payload['result'] ?? null)
                    ? $operation->payload['result']
                    : null;
                if (! $operation->wasRecentlyCreated && $receipt !== null) {
                    return $receipt;
                }
                if (! $operation->wasRecentlyCreated && $operation->status === Operation::STATUS_PROCESSING) {
                    throw new RuntimeException('idempotency_in_progress');
                }
                $operation->forceFill([
                    'status' => Operation::STATUS_PROCESSING,
                    'attempts' => (int) $operation->attempts + 1,
                    'last_error' => null,
                    'finished_at' => null,
                ])->save();

                $lockedRun = OmrTemplateVerificationRun::query()->lockForUpdate()->findOrFail($run->id);
                $method = OmrTemplateVerificationMethod::query()
                    ->whereKey($captureReference->method_id)
                    ->where('run_id', $lockedRun->id)
                    ->lockForUpdate()
                    ->first();
                if ($method === null) {
                    throw new ModelNotFoundException('Verification method is outside this run.');
                }
                if ($method->method !== $payload['method']) {
                    throw new RuntimeException('discard_boundary_mismatch');
                }
                $page = OmrTemplateVerificationMethodPage::query()
                    ->where('method_id', $method->id)
                    ->where('page_no', (int) $payload['page_no'])
                    ->lockForUpdate()
                    ->first();
                if ($page === null) {
                    throw new ModelNotFoundException('Verification page is outside this method.');
                }
                $lockedCapture = OmrTemplateVerificationCapture::query()
                    ->whereKey($captureReference->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $lockedCapture->run_id !== (int) $lockedRun->id
                    || (int) $lockedCapture->method_id !== (int) $method->id
                    || (int) $lockedCapture->page_no !== (int) $page->page_no
                    || (string) $lockedCapture->method !== (string) $method->method
                    || $lockedCapture->challenge_id === null
                    || (int) $lockedCapture->challenge_id !== (int) $method->challenge_id
                    || (string) $lockedCapture->target_kind !== (string) $method->target_kind
                    || (int) $lockedCapture->target_id !== (int) $method->target_id
                    || (string) $lockedCapture->target_version !== (string) $method->target_version
                    || ! hash_equals((string) $lockedCapture->target_hash, (string) $method->target_hash)
                    || ! hash_equals((string) $lockedCapture->plan_hash, (string) $method->plan_hash)
                    || ! hash_equals((string) $lockedCapture->expected_vector_hash, (string) $method->expected_vector_hash)) {
                    throw new RuntimeException('discard_boundary_mismatch');
                }
                foreach ([
                    ['target_hash', $method->target_hash, 'stale_target'],
                    ['plan_hash', $method->plan_hash, 'stale_plan'],
                    ['expected_vector_hash', $method->expected_vector_hash, 'stale_expected_vector'],
                ] as [$field, $expected, $code]) {
                    if (! hash_equals((string) $expected, (string) $payload[$field])) {
                        throw new RuntimeException($code);
                    }
                }
                $selected = DB::table('omr_template_verification_method_pages')
                    ->where('selected_attempt_id', $lockedCapture->id)
                    ->exists();
                if ($selected) {
                    throw new RuntimeException('discard_selected_attempt');
                }
                if ($lockedCapture->isDiscarded()) {
                    throw new RuntimeException('discard_stale');
                }
                if ($lockedCapture->attempt_state === OmrTemplateVerificationCapture::ATTEMPT_ACTIVE) {
                    throw new RuntimeException('discard_attempt_active');
                }
                if (! in_array($lockedCapture->attempt_state, [
                    OmrTemplateVerificationCapture::ATTEMPT_FAILED,
                    OmrTemplateVerificationCapture::ATTEMPT_PASSED,
                    OmrTemplateVerificationCapture::ATTEMPT_SUPERSEDED,
                ], true)) {
                    throw new RuntimeException('discard_boundary_mismatch');
                }
                if ((int) $page->page_version !== (int) $payload['page_version']
                    || (int) $lockedCapture->lock_version !== (int) $payload['capture_lock_version']) {
                    throw new RuntimeException('discard_stale');
                }

                $discardedAt = now();
                $oldCaptureVersion = (int) $lockedCapture->lock_version;
                $oldPageVersion = (int) $page->page_version;
                $lockedCapture->forceFill([
                    'discarded_at' => $discardedAt,
                    'discarded_by_user_id' => $actor->id,
                    'discard_reason' => 'operator_discarded_unselected_attempt',
                    'lock_version' => $oldCaptureVersion + 1,
                ])->save();
                $page->forceFill(['page_version' => $oldPageVersion + 1])->save();
                $this->audit('omr.challenge.attempt_discarded', $actor, $lockedRun, $method->method, (int) $page->page_no, (int) $lockedCapture->attempt_no, 'operator_discarded_unselected_attempt', [
                    'capture_id' => (int) $lockedCapture->id,
                    'method_id' => (int) $method->id,
                    'attempt_state' => (string) $lockedCapture->attempt_state,
                    'target_hash' => (string) $lockedCapture->target_hash,
                    'plan_hash' => (string) $lockedCapture->plan_hash,
                    'expected_vector_hash' => (string) $lockedCapture->expected_vector_hash,
                    'evidence_hash' => $lockedCapture->evidence_hash,
                    'discarded_at' => $discardedAt->toIso8601String(),
                    'discard_reason' => 'operator_discarded_unselected_attempt',
                    'old_page_version' => $oldPageVersion,
                    'new_page_version' => (int) $page->page_version,
                    'old_capture_lock_version' => $oldCaptureVersion,
                    'new_capture_lock_version' => (int) $lockedCapture->lock_version,
                    'selected_attempt_id' => $page->selected_attempt_id,
                    'operation_id' => (string) $operation->id,
                ]);
                $receipt = [
                    'outcome' => 'succeeded',
                    'run_id' => (int) $lockedRun->id,
                    'capture_id' => (int) $lockedCapture->id,
                    'method' => (string) $method->method,
                    'page_no' => (int) $page->page_no,
                    'discarded' => true,
                    'discarded_at' => $discardedAt->toIso8601String(),
                    'capture_lock_version' => (int) $lockedCapture->lock_version,
                    'page_version' => (int) $page->page_version,
                    'selected_attempt_id' => $page->selected_attempt_id,
                    'status_url' => route('setup.omr.templates.canonical.verification_runs.status', ['run' => $lockedRun->id]),
                ];
                $operation->forceFill([
                    'status' => Operation::STATUS_SUCCEEDED,
                    'finished_at' => now(),
                    'payload' => [...(array) $operation->payload, 'result' => $receipt],
                ])->save();

                return $receipt;
            });
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'idempotency')) {
                throw new RuntimeException(str_starts_with($exception->getMessage(), 'idempotency_in_progress')
                    ? 'idempotency_in_progress'
                    : 'idempotency_key_conflict');
            }
            throw $exception;
        } catch (QueryException $exception) {
            $message = strtolower($exception->getMessage());
            if (! str_contains($message, 'operations.idempotency_key')
                && ! str_contains($message, 'operations_idempotency_key_unique')) {
                throw $exception;
            }

            try {
                $operation = $this->idempotency->claim(
                    $actor,
                    'omr.canonical.discard_physical_attempt',
                    'run:'.$run->id,
                    $operationPayload,
                    $idempotencyKey,
                );
            } catch (RuntimeException) {
                throw new RuntimeException('idempotency_key_conflict');
            }
            $receipt = $operation->payload['result'] ?? null;
            if (is_array($receipt)) {
                return $receipt;
            }
            if ($operation->status === Operation::STATUS_PROCESSING) {
                throw new RuntimeException('idempotency_in_progress');
            }

            throw new RuntimeException('idempotency_key_conflict');
        }
    }

    private function authorizeDiscard(OmrTemplateVerificationRun $run, User $actor): void
    {
        if (! $this->authorizer->decideFresh($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR)->allowed) {
            throw new AuthorizationException('OMR attempt disposition is not authorized.');
        }

        $run->loadMissing(['draft', 'startedBy.staffProfile']);
        if ($run->draft_id !== null) {
            if ((int) ($run->draft?->owner_user_id ?? 0) !== (int) $actor->id) {
                throw new ModelNotFoundException('Verification run is outside this actor scope.');
            }

            return;
        }

        $actorInstitution = $actor->actorScope()->institutionId;
        $starterInstitution = $run->startedBy?->actorScope()->institutionId;
        if ($actorInstitution !== null && $starterInstitution !== null && (int) $actorInstitution !== (int) $starterInstitution) {
            throw new ModelNotFoundException('Verification run is outside this actor scope.');
        }
    }

    /** Invoke the one shared, read-only analysis seam. */
    public function analyze(OmrCaptureAnalysisRequest $request): OmrPreparedCaptureEvidence
    {
        return $this->analysis->analyze($request);
    }

    /**
     * Materialize exactly one immutable method row and every required page for
     * each required method. Existing rows are accepted only when their entire
     * server-authored binding still matches.
     *
     * @param  list<OmrProofPlan>  $plans
     * @return Collection<int, OmrTemplateVerificationMethod>
     */
    public function ensureMethodRows(
        OmrTemplateVerificationRun $run,
        array $plans,
        string $targetKind,
        int $targetId,
        string $targetVersion,
        ?int $requiredPageCount = null,
    ): Collection {
        $byMethod = [];
        foreach ($plans as $plan) {
            if (! $plan instanceof OmrProofPlan || ! in_array($plan->method, OmrProofPlan::METHODS, true)) {
                throw new RuntimeException('verification_method_plan_required');
            }
            $byMethod[$plan->method] = $plan;
        }
        foreach (OmrProofPlan::METHODS as $method) {
            if (! isset($byMethod[$method])) {
                throw new RuntimeException('verification_methods_incomplete: both pre_shaded and hand_shaded plans are required.');
            }
        }
        if (! in_array($targetKind, [OmrTemplateVerificationMethod::TARGET_CANDIDATE, OmrTemplateVerificationMethod::TARGET_COMPILED_VERSION], true) || $targetId < 1) {
            throw new RuntimeException('verification_target_invalid');
        }

        return DB::transaction(function () use ($run, $byMethod, $targetKind, $targetId, $targetVersion, $requiredPageCount): Collection {
            $lockedRun = OmrTemplateVerificationRun::query()->lockForUpdate()->findOrFail($run->id);
            $expectedTargetKind = $lockedRun->draft_id !== null
                ? OmrTemplateVerificationMethod::TARGET_CANDIDATE
                : OmrTemplateVerificationMethod::TARGET_COMPILED_VERSION;
            $expectedTargetId = (int) ($lockedRun->draft_id ?? $lockedRun->template_version_id);
            if ($targetKind !== $expectedTargetKind || $targetId !== $expectedTargetId) {
                throw new RuntimeException('verification_target_binding_mismatch');
            }
            $rows = [];
            foreach (OmrProofPlan::METHODS as $methodName) {
                $plan = $byMethod[$methodName];
                $pageCount = $requiredPageCount ?? $this->planPageCount($plan);
                $targetHash = (string) ($plan->targetHashes['artifact'] ?? '');
                if ($targetHash === '' || $pageCount < 1) {
                    throw new RuntimeException('verification_method_binding_invalid');
                }

                $method = OmrTemplateVerificationMethod::query()
                    ->where('run_id', $lockedRun->id)
                    ->where('method', $methodName)
                    ->lockForUpdate()
                    ->first();
                $values = [
                    'run_id' => $lockedRun->id,
                    'method' => $methodName,
                    'target_kind' => $targetKind,
                    'target_id' => $targetId,
                    'target_version' => $targetVersion !== '' ? $targetVersion : $plan->planVersion,
                    'target_hash' => $targetHash,
                    'plan_version' => $plan->planVersion,
                    'plan_seed' => $plan->seed,
                    'plan_hash' => $plan->planHash(),
                    'expected_vector_hash' => $this->expectedVectorHash($plan),
                    'required_page_count' => $pageCount,
                ];
                if ($method === null) {
                    $method = OmrTemplateVerificationMethod::query()->create($values);
                } else {
                    foreach (['run_id', 'method', 'target_kind', 'target_id', 'target_version', 'target_hash', 'plan_version', 'plan_seed', 'plan_hash', 'expected_vector_hash', 'required_page_count'] as $field) {
                        if ((string) $method->{$field} !== (string) $values[$field]) {
                            throw new RuntimeException('verification_method_binding_mismatch');
                        }
                    }
                }

                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $page = OmrTemplateVerificationMethodPage::query()
                        ->where('method_id', $method->id)
                        ->where('page_no', $pageNo)
                        ->lockForUpdate()
                        ->first();
                    if ($page === null) {
                        OmrTemplateVerificationMethodPage::query()->create([
                            'method_id' => $method->id,
                            'page_no' => $pageNo,
                            'required' => true,
                            'page_state' => OmrTemplateVerificationMethodPage::STATE_NOT_STARTED,
                            'page_version' => 0,
                        ]);
                    } elseif (! $page->required) {
                        throw new RuntimeException('verification_method_page_binding_mismatch');
                    }
                }
                $rows[] = $method->fresh(['pages']);
            }

            return new Collection($rows);
        });
    }

    /**
     * Remove an unreferenced method materialization after challenge storage
     * fails. Once a challenge, capture, or selection exists this is a no-op;
     * submitted physical history is never deleted.
     */
    public function discardUnmaterializedRun(int $runId): void
    {
        DB::transaction(function () use ($runId): void {
            $run = OmrTemplateVerificationRun::query()->lockForUpdate()->find($runId);
            if ($run === null || $run->challenges()->exists() || $run->captures()->exists()) {
                return;
            }
            $methodIds = OmrTemplateVerificationMethod::query()
                ->where('run_id', $run->id)
                ->orderBy('method')
                ->lockForUpdate()
                ->pluck('id');
            if ($methodIds->isNotEmpty()) {
                OmrTemplateVerificationMethodPage::query()
                    ->whereIn('method_id', $methodIds)
                    ->delete();
                OmrTemplateVerificationMethod::query()
                    ->whereIn('id', $methodIds)
                    ->delete();
            }
            $run->delete();
        });
    }

    /**
     * Persist one method-bound challenge after its immutable assets have been
     * written. Challenge and method binding are committed together.
     *
     * @param  array<string,mixed>  $metadata
     * @param  array<string,mixed>  $assets
     * @return array{run:OmrTemplateVerificationRun,challenge:OmrPhysicalChallenge}
     */
    public function persistChallenge(
        OmrTemplateVerificationRun $run,
        User $actor,
        OmrProofPlan $plan,
        array $metadata,
        array $assets,
    ): array {
        $methodName = $plan->method;
        if (! in_array($methodName, OmrProofPlan::METHODS, true)) {
            throw new RuntimeException('verification_method_required');
        }
        $expectedVectorHash = $this->expectedVectorHash($plan);
        $targetKind = (string) ($metadata['target_kind'] ?? '');
        $targetId = (int) ($metadata['target_id'] ?? 0);
        $targetVersion = (string) ($metadata['target_version'] ?? '');
        if ($targetKind === '' || $targetId < 1 || $targetVersion === '') {
            throw new RuntimeException('verification_challenge_binding_invalid');
        }
        $pageManifest = is_array($metadata['page_manifest'] ?? null) ? array_values($metadata['page_manifest']) : [];
        $pageManifestHash = hash('sha256', json_encode($pageManifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return DB::transaction(function () use ($run, $actor, $plan, $methodName, $expectedVectorHash, $metadata, $assets, $pageManifest, $pageManifestHash, $targetKind, $targetId, $targetVersion): array {
            $lockedRun = OmrTemplateVerificationRun::query()->lockForUpdate()->findOrFail($run->id);
            $method = OmrTemplateVerificationMethod::query()
                ->where('run_id', $lockedRun->id)
                ->where('method', $methodName)
                ->lockForUpdate()
                ->first();
            if ($method === null) {
                throw new RuntimeException('verification_method_missing');
            }
            if ((string) $method->target_kind !== $targetKind
                || (int) $method->target_id !== $targetId
                || (string) $method->target_version !== $targetVersion) {
                throw new RuntimeException('challenge_method_binding_mismatch');
            }
            $this->assertMethodPlan($method, $plan);

            $challenge = OmrPhysicalChallenge::query()
                ->where('run_id', $lockedRun->id)
                ->where('method', $methodName)
                ->where('status', '!=', OmrPhysicalChallenge::STATUS_SUPERSEDED)
                ->lockForUpdate()
                ->latest('id')
                ->first();
            if ($challenge !== null) {
                if ((string) $challenge->plan_hash !== $plan->planHash()
                    || (string) $challenge->target_kind !== $targetKind
                    || (int) $challenge->target_id !== $targetId
                    || (string) $challenge->target_version !== $targetVersion
                    || (string) $challenge->target_hash !== (string) ($method->target_hash ?? '')) {
                    throw new RuntimeException('challenge_method_binding_mismatch');
                }

                return ['run' => $lockedRun->fresh(), 'challenge' => $challenge->fresh()];
            }

            $challenge = OmrPhysicalChallenge::query()->create([
                'run_id' => $lockedRun->id,
                'attempt' => (int) $lockedRun->attempt,
                'method' => $methodName,
                'mode' => (string) ($metadata['challenge_mode'] ?? ($lockedRun->mode === OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION ? OmrPhysicalChallenge::MODE_FULL : OmrPhysicalChallenge::MODE_QUICK)),
                'challenge_code' => (string) $plan->challengeCode,
                'target_kind' => $targetKind,
                'target_id' => $targetId,
                'target_version' => $targetVersion,
                'target_hash' => (string) ($plan->targetHashes['artifact'] ?? ''),
                'plan_version' => $plan->planVersion,
                'plan_seed' => $plan->seed,
                'plan_hash' => $plan->planHash(),
                'expected_vector_hash' => $expectedVectorHash,
                'renderer_version' => (string) ($metadata['renderer_version'] ?? OmrPhysicalChallengeRenderer::class),
                'renderer_hash' => (string) ($metadata['renderer_hash'] ?? hash('sha256', (string) ($metadata['renderer_version'] ?? ''))),
                'marker_profile_id' => $metadata['marker_profile_id'] ?? null,
                'marker_profile_version' => $metadata['marker_profile_version'] ?? null,
                'marker_profile_hash' => $metadata['marker_profile_hash'] ?? null,
                'page_manifest' => $pageManifest,
                'page_manifest_hash' => $pageManifestHash,
                'scenario_manifest' => $metadata['scenario_manifest'] ?? null,
                'scenario_manifest_hash' => $metadata['scenario_manifest_hash'] ?? null,
                'coverage_report' => $metadata['coverage_report'] ?? null,
                'coverage_hash' => $metadata['coverage_hash'] ?? null,
                'renderer_identity' => $metadata['renderer_identity'] ?? null,
                'profile_identity' => $metadata['profile_identity'] ?? null,
                'geometry_identity' => $metadata['geometry_identity'] ?? null,
                'source_artifact_hashes' => $metadata['source_artifact_hashes'] ?? null,
                'transformed_artifact_hashes' => $metadata['transformed_artifact_hashes'] ?? null,
                'scenario_outcomes' => $metadata['scenario_outcomes'] ?? null,
                'sheet_asset_id' => $assets['sheet_asset_id'] ?? null,
                'sheet_asset_hash' => $assets['sheet_asset_hash'] ?? null,
                'guide_asset_id' => $assets['guide_asset_id'] ?? null,
                'guide_asset_hash' => $assets['guide_asset_hash'] ?? null,
                'guide_json' => $metadata['guide_json'] ?? null,
                'qr_payload_hash' => $metadata['qr_payload_hash'] ?? null,
                'print_metadata' => $metadata['print_metadata'] ?? null,
                'status' => ($assets['sheet_asset_id'] ?? null) === null ? OmrPhysicalChallenge::STATUS_PRINTED : OmrPhysicalChallenge::STATUS_CREATED,
                'created_by_user_id' => $actor->id,
            ]);

            $isAssetless = ($assets['sheet_asset_id'] ?? null) === null;
            $this->transitionMethod($method, OmrTemplateVerificationMethod::STATE_CHALLENGE_READY);
            $method->forceFill([
                'challenge_id' => $challenge->id,
                'challenge_ready_at' => now(),
                'lock_version' => (int) $method->lock_version + 1,
            ])->save();
            if ($isAssetless || $lockedRun->status === OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE) {
                $this->transitionMethod($method, OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE);
                $this->transitionPages($method, OmrTemplateVerificationMethodPage::STATE_AWAITING_CAPTURE);
            }
            $lockedRun = $this->syncAggregateRunState($lockedRun);
            $this->audit('omr.challenge.created', $actor, $lockedRun, $methodName, null, (int) $lockedRun->attempt, null, [
                'challenge_id' => (int) $challenge->id,
                'challenge_code' => (string) $challenge->challenge_code,
                'plan_hash' => (string) $challenge->plan_hash,
            ]);

            return ['run' => $lockedRun->fresh(), 'challenge' => $challenge->fresh()];
        });
    }

    /** Update pre-capture printable assets without opening a second state path. */
    public function updateChallengeAssets(
        OmrTemplateVerificationRun $run,
        OmrPhysicalChallenge $challenge,
        User $actor,
        array $attributes,
        string $action = 'omr.challenge.assets_updated',
    ): OmrPhysicalChallenge {
        return DB::transaction(function () use ($run, $challenge, $actor, $attributes, $action): OmrPhysicalChallenge {
            $lockedRun = OmrTemplateVerificationRun::query()->lockForUpdate()->findOrFail($run->id);
            $lockedChallenge = OmrPhysicalChallenge::query()->lockForUpdate()->findOrFail($challenge->id);
            if ((int) $lockedChallenge->run_id !== (int) $lockedRun->id || $lockedChallenge->status === OmrPhysicalChallenge::STATUS_SUPERSEDED) {
                throw new RuntimeException('challenge_binding_mismatch');
            }
            if ($lockedRun->captures()->exists() || $lockedChallenge->captures()->exists()) {
                throw new RuntimeException('challenge_regeneration_evidence_exists');
            }
            $oldSheetAssetId = $lockedChallenge->sheet_asset_id;
            $oldGuideAssetId = $lockedChallenge->guide_asset_id;
            OmrPhysicalChallenge::allowPreCaptureRegeneration(static function () use ($lockedChallenge, $attributes): void {
                OmrPhysicalChallenge::allowDiagnosticPersistence(static function () use ($lockedChallenge, $attributes): void {
                    $lockedChallenge->forceFill($attributes)->save();
                });
            });
            $this->audit($action, $actor, $lockedRun, (string) ($lockedChallenge->method ?? OmrProofPlan::METHOD_PRE_SHADED), null, (int) $lockedRun->attempt, null, [
                'challenge_id' => (int) $lockedChallenge->id,
                'old_sheet_asset_id' => $oldSheetAssetId,
                'new_sheet_asset_id' => $lockedChallenge->sheet_asset_id,
                'old_guide_asset_id' => $oldGuideAssetId,
                'new_guide_asset_id' => $lockedChallenge->guide_asset_id,
                'new_renderer_version' => (string) ($attributes['print_metadata']['renderer_version'] ?? ''),
                'reason' => (string) ($attributes['print_metadata']['reason'] ?? ''),
            ]);

            return $lockedChallenge->fresh(['sheetAsset', 'guideAsset']);
        });
    }

    /** Row-locked print acknowledgement. */
    /**
     * Acknowledge one method-bound printed sheet. Methods are independent:
     * marking one method advances only its method/pages and moves the aggregate
     * run to awaiting_capture once, without blocking the other method.
     */
    public function markPrinted(
        OmrTemplateVerificationRun $run,
        User $actor,
        string $methodName,
        int $challengeId,
        ?string $targetHash = null,
        ?string $planHash = null,
        ?string $expectedVectorHash = null,
    ): OmrTemplateVerificationRun {
        if (! in_array($methodName, OmrProofPlan::METHODS, true)) {
            throw new RuntimeException('verification_method_invalid: A physical print acknowledgement must name a valid method.');
        }
        if ($challengeId < 1) {
            throw new RuntimeException('verification_challenge_required: A physical print acknowledgement must name a challenge.');
        }
        // Printing is an authoritative transition, not a presentation hint.
        // Reuse the same pinned-target and current-digital gate as capture.
        $this->targets->loadPhysical($run, $methodName);

        return DB::transaction(function () use ($run, $actor, $methodName, $challengeId, $targetHash, $planHash, $expectedVectorHash): OmrTemplateVerificationRun {
            $lockedRun = OmrTemplateVerificationRun::query()->lockForUpdate()->findOrFail($run->id);

            // Lock every method/page in canonical order before the selected
            // challenge row, matching capture and selection mutations.
            $methods = OmrTemplateVerificationMethod::query()
                ->where('run_id', $lockedRun->id)
                ->orderBy('method')
                ->lockForUpdate()
                ->get()
                ->keyBy('method');
            foreach ($methods as $method) {
                OmrTemplateVerificationMethodPage::query()
                    ->where('method_id', $method->id)
                    ->orderBy('page_no')
                    ->lockForUpdate()
                    ->get();
            }

            $method = $methods->get($methodName);
            if ($method === null) {
                throw new RuntimeException('verification_method_missing: The requested physical method is not part of this run.');
            }
            $challenge = OmrPhysicalChallenge::query()->whereKey($challengeId)->lockForUpdate()->first();
            if ($challenge === null || (int) $challenge->run_id !== (int) $lockedRun->id) {
                throw new RuntimeException('wrong_challenge: The challenge does not belong to this run.');
            }
            if ((string) $challenge->method !== $methodName
                || (int) ($method->challenge_id ?? 0) !== (int) $challenge->id) {
                throw new RuntimeException('challenge_method_mismatch: The challenge is not bound to the requested physical method.');
            }
            if ($challenge->status === OmrPhysicalChallenge::STATUS_SUPERSEDED) {
                throw new RuntimeException('challenge_superseded: The requested physical challenge is no longer active.');
            }

            $expectedTargetKind = $lockedRun->draft_id !== null
                ? OmrTemplateVerificationMethod::TARGET_CANDIDATE
                : OmrTemplateVerificationMethod::TARGET_COMPILED_VERSION;
            $expectedTargetId = (int) ($lockedRun->draft_id ?? $lockedRun->template_version_id);
            if ((string) $method->target_kind !== $expectedTargetKind
                || (int) $method->target_id !== $expectedTargetId
                || (string) $challenge->target_kind !== (string) $method->target_kind
                || (int) $challenge->target_id !== (int) $method->target_id
                || (string) $challenge->target_version !== (string) $method->target_version
                || ! hash_equals((string) $challenge->target_hash, (string) $method->target_hash)
                || ! hash_equals((string) $challenge->plan_hash, (string) $method->plan_hash)
                || ! hash_equals((string) $challenge->expected_vector_hash, (string) $method->expected_vector_hash)
                || ! hash_equals((string) $lockedRun->artifact_hash, (string) $method->target_hash)) {
                throw new RuntimeException('verification_print_binding_mismatch: The method, challenge, and run target bindings do not match.');
            }
            foreach ([
                'target_hash' => $targetHash,
                'plan_hash' => $planHash,
                'expected_vector_hash' => $expectedVectorHash,
            ] as $name => $provided) {
                if ($provided !== null && ($provided === '' || ! hash_equals((string) $method->{$name}, $provided))) {
                    throw new RuntimeException('stale_'.$name.': The print acknowledgement does not match the persisted method binding.');
                }
            }

            $alreadyPrinted = in_array($challenge->status, [
                OmrPhysicalChallenge::STATUS_PRINTED,
                OmrPhysicalChallenge::STATUS_AWAITING_CAPTURE,
                OmrPhysicalChallenge::STATUS_ANALYZING_CAPTURE,
                OmrPhysicalChallenge::STATUS_PASSED,
                OmrPhysicalChallenge::STATUS_FAILED,
            ], true);
            if (! $alreadyPrinted && $challenge->status !== OmrPhysicalChallenge::STATUS_CREATED) {
                throw new RuntimeException('verification_challenge_state_invalid: The challenge is not ready to mark printed.');
            }
            $runStatusBefore = (string) $lockedRun->status;
            $methodStateBefore = (string) $method->method_state;
            if ($method->method_state === OmrTemplateVerificationMethod::STATE_CHALLENGE_READY) {
                $this->transitionMethod($method, OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE);
                $this->transitionPages($method, OmrTemplateVerificationMethodPage::STATE_AWAITING_CAPTURE);
            } elseif (! in_array($method->method_state, [
                OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE,
                OmrTemplateVerificationMethod::STATE_ANALYZING,
                OmrTemplateVerificationMethod::STATE_NEEDS_REVIEW,
                OmrTemplateVerificationMethod::STATE_PASSED,
                OmrTemplateVerificationMethod::STATE_FAILED,
            ], true)) {
                throw new RuntimeException('verification_method_state_invalid: The method is not ready for print acknowledgement.');
            }

            if (! $alreadyPrinted) {
                $challenge->forceFill(['status' => OmrPhysicalChallenge::STATUS_PRINTED])->save();
            }

            $this->syncAggregateRunState($lockedRun, [
                'stage' => 'awaiting_capture',
                'current_variant' => null,
                'method' => $methodName,
                'challenge_id' => $challenge->id,
            ]);
            if (! $alreadyPrinted || $methodStateBefore !== (string) $method->method_state || $runStatusBefore !== (string) $lockedRun->status) {
                $this->audit('omr.challenge.printed', $actor, $lockedRun, $methodName, null, (int) $lockedRun->attempt, null, [
                    'challenge_id' => (int) $challenge->id,
                    'challenge_code' => (string) $challenge->challenge_code,
                    'target_hash' => (string) $method->target_hash,
                    'plan_hash' => (string) $method->plan_hash,
                    'expected_vector_hash' => (string) $method->expected_vector_hash,
                    'run_status_before' => $runStatusBefore,
                    'method_state_before' => $methodStateBefore,
                    'idempotent_replay' => $alreadyPrinted,
                ]);
            }

            return $lockedRun->fresh();
        });
    }

    /**
     * Claim and persist an active attempt. Raw bytes are stored as a private
     * immutable input asset; no bytes enter the operation or audit payload.
     * Analysis is dispatched only after this transaction commits.
     *
     * @return array{capture:OmrTemplateVerificationCapture,operation:Operation}
     */
    public function beginCapture(
        OmrTemplateVerificationRun $run,
        User $actor,
        string $methodName,
        int $challengeId,
        int $pageNo,
        string $bytes,
        string $mime,
        string $captureSource,
        ?string $medium,
        ?string $targetHash,
        ?string $planHash,
        ?string $expectedVectorHash,
        string $idempotencyKey,
        bool $autoSelect = false,
        ?Operation $operation = null,
        ?string $cropPolicy = 'auto_document_quad',
    ): array {
        if (! in_array($methodName, OmrProofPlan::METHODS, true)) {
            throw new RuntimeException('verification_method_invalid');
        }
        if ($methodName === OmrTemplateVerificationMethod::METHOD_HAND_SHADED && ($medium === null || trim($medium) === '')) {
            $medium = OmrTemplateVerificationCapture::MEDIUM_HB_2_PENCIL;
        }
        if ($challengeId < 1 || $pageNo < 1 || $bytes === '' || trim($idempotencyKey) === '') {
            throw new RuntimeException('verification_capture_input_invalid');
        }
        if (! in_array($captureSource, ['camera', 'upload'], true)) {
            throw new RuntimeException('verification_capture_source_invalid');
        }
        $inputHash = hash('sha256', $bytes);
        $providedOperation = $operation !== null;
        if (! $providedOperation) {
            try {
                $operation = $this->idempotency->claim($actor, 'omr.canonical.physical_capture', 'run:'.$run->id, [
                    'run_id' => (int) $run->id,
                    'method' => $methodName,
                    'challenge_id' => $challengeId,
                    'page_no' => $pageNo,
                    'input_hash' => $inputHash,
                    'capture_source' => $captureSource,
                    'medium' => $medium,
                    'target_hash' => $targetHash,
                    'plan_hash' => $planHash,
                    'expected_vector_hash' => $expectedVectorHash,
                    'crop_policy' => $cropPolicy ?? 'auto_document_quad',
                ], $idempotencyKey);
            } catch (RuntimeException $exception) {
                throw new RuntimeException('idempotency_key_conflict: '.$exception->getMessage());
            }
            $savedResult = is_array($operation->payload['result'] ?? null) ? $operation->payload['result'] : null;
            if (! $operation->wasRecentlyCreated && $savedResult !== null) {
                $capture = OmrTemplateVerificationCapture::query()->find((int) ($savedResult['capture_id'] ?? 0));
                if ($capture !== null) {
                    return ['capture' => $capture, 'operation' => $operation];
                }
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
        }
        if ($operation === null) {
            throw new RuntimeException('capture_operation_missing');
        }

        $createdInputAsset = null;
        try {
            $capture = DB::transaction(function () use ($run, $actor, $methodName, $challengeId, $pageNo, $bytes, $mime, $captureSource, $medium, $targetHash, $planHash, $expectedVectorHash, $inputHash, $autoSelect, $cropPolicy, $operation, &$createdInputAsset): OmrTemplateVerificationCapture {
                $lockedRun = OmrTemplateVerificationRun::query()->lockForUpdate()->findOrFail($run->id);
                if (OmrTemplateVerificationCapture::query()->where('run_id', $lockedRun->id)->where('attempt_state', OmrTemplateVerificationCapture::ATTEMPT_ACTIVE)->lockForUpdate()->exists()) {
                    throw new RuntimeException('verification_capture_active: Another capture is already being analyzed. Wait for it to finish before submitting another image.');
                }
                if ($lockedRun->isTerminal()) {
                    throw new RuntimeException('verification_run_not_awaiting_capture');
                }
                $method = OmrTemplateVerificationMethod::query()->where('run_id', $lockedRun->id)->where('method', $methodName)->lockForUpdate()->first();
                if ($method === null) {
                    throw new RuntimeException('verification_method_missing');
                }
                $page = OmrTemplateVerificationMethodPage::query()->where('method_id', $method->id)->where('page_no', $pageNo)->lockForUpdate()->first();
                if ($page === null || ! $page->required) {
                    throw new RuntimeException('verification_page_invalid');
                }
                $challenge = OmrPhysicalChallenge::query()->whereKey($challengeId)->lockForUpdate()->first();
                if ($challenge === null
                    || (int) $challenge->run_id !== (int) $lockedRun->id
                    || (string) $challenge->method !== $methodName
                    || ($method->challenge_id !== null && (int) $method->challenge_id !== (int) $challenge->id)
                    || $challenge->status === OmrPhysicalChallenge::STATUS_SUPERSEDED) {
                    throw new RuntimeException('wrong_challenge');
                }
                if ((string) $challenge->target_kind !== (string) $method->target_kind
                    || (int) $challenge->target_id !== (int) $method->target_id
                    || (string) $challenge->target_version !== (string) $method->target_version
                    || (string) $challenge->target_hash !== (string) $method->target_hash
                    || (string) $challenge->plan_hash !== (string) $method->plan_hash
                    || (string) $challenge->expected_vector_hash !== (string) $method->expected_vector_hash) {
                    throw new RuntimeException('challenge_method_binding_mismatch');
                }
                if ($targetHash !== null && $targetHash !== '' && ! hash_equals((string) $method->target_hash, $targetHash)) {
                    throw new RuntimeException('stale_target');
                }
                if ($planHash !== null && $planHash !== '' && ! hash_equals((string) $method->plan_hash, $planHash)) {
                    throw new RuntimeException('stale_plan');
                }
                if ($expectedVectorHash !== null && $expectedVectorHash !== '' && ! hash_equals((string) $method->expected_vector_hash, $expectedVectorHash)) {
                    throw new RuntimeException('stale_expected_vector');
                }
                if ($methodName === OmrTemplateVerificationMethod::METHOD_HAND_SHADED && ! in_array($medium, OmrTemplateVerificationCapture::HAND_SHADED_MEDIA, true)) {
                    throw new RuntimeException('verification_capture_hand_medium_required');
                }
                if ($methodName === OmrTemplateVerificationMethod::METHOD_PRE_SHADED && $medium !== null) {
                    throw new RuntimeException('verification_capture_pre_medium_forbidden');
                }
                if (in_array($method->method_state, [OmrTemplateVerificationMethod::STATE_NOT_STARTED, OmrTemplateVerificationMethod::STATE_CHALLENGE_READY], true)) {
                    throw new RuntimeException('verification_method_not_ready_for_capture');
                }
                if ($method->method_state === OmrTemplateVerificationMethod::STATE_ANALYZING) {
                    throw new RuntimeException('verification_capture_active: Another capture is already being analyzed. Wait for it to finish before submitting another image.');
                }
                if ($page->page_state !== OmrTemplateVerificationMethodPage::STATE_AWAITING_CAPTURE) {
                    throw new RuntimeException("verification_capture_retake_required: Page {$pageNo} already has analyzed evidence ({$page->page_state}). Start a retake for Page {$pageNo} before uploading a new image, or switch to an awaiting page.");
                }
                $challenge->forceFill(['status' => OmrPhysicalChallenge::STATUS_ANALYZING_CAPTURE])->save();

                // PostgreSQL rejects FOR UPDATE on an aggregate. Lock the latest
                // attempt row instead, while the run lock serializes reservations.
                $lastAttemptNo = OmrTemplateVerificationCapture::query()
                    ->where('run_id', $lockedRun->id)
                    ->where('method', $methodName)
                    ->where('page_no', $pageNo)
                    ->orderByDesc('attempt_no')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->value('attempt_no');
                $attemptNo = (int) $lastAttemptNo + 1;
                $owner = $this->assetOwner($lockedRun);
                $inputAsset = $this->putOwnedAsset($owner, OmrTemplateAsset::KIND_EVIDENCE, $bytes, $mime, $pageNo, [
                    'run_id' => $lockedRun->id,
                    'method' => $methodName,
                    'attempt_no' => $attemptNo,
                    'role' => 'capture_input',
                    'input_hash' => $inputHash,
                    'capture_source' => $captureSource,
                ]);
                if ($inputAsset->wasRecentlyCreated) {
                    $createdInputAsset = $inputAsset;
                }
                $challengeHash = hash('sha256', json_encode([
                    'challenge_id' => $challenge->id,
                    'challenge_code' => $challenge->challenge_code,
                    'plan_hash' => $challenge->plan_hash,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
                $capture = OmrTemplateVerificationCapture::query()->create([
                    'run_id' => $lockedRun->id,
                    'method_id' => $method->id,
                    'challenge_id' => $challenge->id,
                    'attempt' => (int) $lockedRun->attempt,
                    'attempt_no' => $attemptNo,
                    'page_no' => $pageNo,
                    'method' => $methodName,
                    'mode' => (string) $lockedRun->mode,
                    'variant' => 'physical_capture',
                    'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_ACTIVE,
                    'target_kind' => (string) $method->target_kind,
                    'target_id' => (int) $method->target_id,
                    'target_version' => (string) $method->target_version,
                    'target_hash' => (string) $method->target_hash,
                    'plan_version' => (string) $method->plan_version,
                    'plan_seed' => (string) $method->plan_seed,
                    'plan_hash' => (string) $method->plan_hash,
                    'challenge_hash' => $challengeHash,
                    'renderer_version' => $challenge->renderer_version,
                    'renderer_hash' => $challenge->renderer_hash,
                    'marker_profile_id' => $challenge->marker_profile_id,
                    'marker_profile_version' => $challenge->marker_profile_version,
                    'marker_profile_hash' => $challenge->marker_profile_hash,
                    'page_manifest_hash' => $challenge->page_manifest_hash,
                    'expected' => 'pass',
                    'actual' => 'failed',
                    'status' => OmrTemplateVerificationCapture::STATUS_FAIL,
                    'input_hash' => $inputHash,
                    'evidence_hash' => hash('sha256', 'active|'.$inputHash),
                    'input_asset_hash' => $inputAsset->sha256,
                    'input_asset_id' => $inputAsset->id,
                    'expected_vector_hash' => (string) $method->expected_vector_hash,
                    'medium' => $medium,
                    'captured_by_user_id' => $actor->id,
                    'summary' => [
                        'capture_source' => $captureSource,
                        'auto_select' => $autoSelect,
                        'crop_policy' => $cropPolicy ?? 'auto_document_quad',
                        'operation_id' => (string) $operation->id,
                    ],
                    'captured_at' => now(),
                    'lock_version' => 0,
                ]);
                $this->transitionMethod($method, OmrTemplateVerificationMethod::STATE_ANALYZING);
                $this->transitionPage($page, OmrTemplateVerificationMethodPage::STATE_ANALYZING);
                $this->syncAggregateRunState($lockedRun, [
                    'phase_code' => OmrTemplateVerificationRun::PHASE_QUEUED,
                    'stage' => OmrTemplateVerificationRun::PHASE_QUEUED,
                    'current_variant' => 'physical_capture',
                    'method' => $methodName,
                    'page_no' => $pageNo,
                    'capture_id' => $capture->id,
                    'crop_policy' => $cropPolicy ?? self::CROP_AUTO_DOCUMENT_QUAD,
                ]);

                return $capture->fresh();
            });
        } catch (\Throwable $exception) {
            if ($createdInputAsset?->wasRecentlyCreated) {
                Storage::disk($createdInputAsset->disk)->delete($createdInputAsset->path);
            }
            $operation->forceFill([
                'status' => Operation::STATUS_FAILED,
                'last_error' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();
            throw $exception;
        }

        $operation->forceFill([
            'payload' => [...(array) $operation->payload, 'result' => [
                'run_id' => (int) $run->id,
                'capture_id' => (int) $capture->id,
                'method' => $methodName,
                'page_no' => $pageNo,
                'attempt_no' => (int) $capture->attempt_no,
            ]],
        ])->save();
        if ($autoSelect) {
            AnalyzeOmrTemplateVerificationCaptureJob::dispatchSync((int) $capture->id, true);
            $capture = $capture->fresh() ?? $capture;
        } else {
            AnalyzeOmrTemplateVerificationCaptureJob::dispatch((int) $capture->id, false)
                ->onQueue('omr-verification');
        }

        return ['capture' => $capture, 'operation' => $operation];
    }

    /** Complete the active attempt and derive all workflow transitions. */
    public function completeCapture(
        OmrTemplateVerificationCapture $capture,
        OmrPreparedCaptureEvidence $prepared,
        OmrDiagnosticEvidence $evidence,
        User $actor,
        bool $vectorMatch,
        ?OmrTemplateAsset $alignedAsset = null,
        ?OmrTemplateAsset $overlayAsset = null,
        ?string $captureSource = null,
        bool $autoSelect = false,
    ): array {
        return DB::transaction(function () use ($capture, $prepared, $evidence, $actor, $vectorMatch, $alignedAsset, $overlayAsset, $captureSource, $autoSelect): array {
            // Keep the lock order run -> method -> page -> attempt for every
            // completion path. The attempt's foreign keys identify its parent
            // rows without taking an attempt lock first.
            $run = OmrTemplateVerificationRun::query()->lockForUpdate()->findOrFail($capture->run_id);
            $method = OmrTemplateVerificationMethod::query()
                ->whereKey($capture->method_id)
                ->where('run_id', $run->id)
                ->lockForUpdate()
                ->firstOrFail();
            $page = OmrTemplateVerificationMethodPage::query()
                ->where('method_id', $method->id)
                ->where('page_no', $capture->page_no)
                ->lockForUpdate()
                ->firstOrFail();
            $challenge = $capture->challenge_id !== null
                ? OmrPhysicalChallenge::query()->whereKey($capture->challenge_id)->lockForUpdate()->first()
                : null;
            $lockedCapture = OmrTemplateVerificationCapture::query()->lockForUpdate()->findOrFail($capture->id);
            if ((int) $lockedCapture->run_id !== (int) $run->id
                || (int) $lockedCapture->method_id !== (int) $method->id
                || (int) $lockedCapture->page_no !== (int) $page->page_no) {
                throw new RuntimeException('verification_capture_binding_mismatch');
            }
            if ($lockedCapture->attempt_state !== OmrTemplateVerificationCapture::ATTEMPT_ACTIVE) {
                return ['run' => $run->fresh(), 'capture' => $lockedCapture->fresh()];
            }
            if ($run->isTerminal()) {
                throw new RuntimeException('verification_run_terminal');
            }
            $registrationMode = $this->registrationModeForRun($run);
            $analysisFailed = ! $prepared->succeeded();
            $bindingFailure = $analysisFailed ? null : $this->gateFailure(
                $registrationMode,
                $prepared->gates ?? $evidence->gates,
                $prepared,
                $evidence,
                $run,
                $method,
                $page,
                $lockedCapture,
            );
            $gatesPass = ! $analysisFailed && $bindingFailure === null;
            $reviewCount = $this->observationReviewCount($evidence);
            $hardFailed = $analysisFailed || $bindingFailure !== null;
            $passed = ! $hardFailed && $vectorMatch && $reviewCount === 0;
            $needsReview = ! $hardFailed && ! $passed && $reviewCount > 0;
            if (! $passed && ! $needsReview && ! $hardFailed) {
                $hardFailed = true;
            }
            $failureCode = $analysisFailed
                ? ($prepared->code ?? 'capture_analysis_failed')
                : ($bindingFailure ?? ($needsReview ? 'capture_needs_review' : ($passed ? null : 'answer_vector_mismatch')));
            $failureDetail = $analysisFailed
                ? ($prepared->detail ?? $failureCode)
                : ($bindingFailure ?? ($needsReview ? 'Item observations require review.' : ($passed ? null : 'The observed answer vector did not match the test plan.')));
            $failedPhaseCode = $hardFailed
                ? ($analysisFailed ? $this->analysisFailurePhase($prepared) : OmrTemplateVerificationRun::PHASE_EVIDENCE_PERSISTENCE)
                : null;
            $attemptState = $passed ? OmrTemplateVerificationCapture::ATTEMPT_PASSED : OmrTemplateVerificationCapture::ATTEMPT_FAILED;
            $status = $passed ? OmrTemplateVerificationCapture::STATUS_PASS : OmrTemplateVerificationCapture::STATUS_FAIL;
            $actual = $passed ? 'pass' : ($bindingFailure !== null ? 'typed_rejection' : ($needsReview ? 'answer_vector_mismatch' : 'failed'));
            $documentRectificationEvidence = null;
            if ($registrationMode === OmrRegistrationMode::DocumentBound) {
                $documentRectificationEvidence = [
                    'layout' => $prepared->layout?->rectification,
                    'detection' => $prepared->providerResult?->success?->rectification,
                ];
                $documentRectificationEvidence['sha256'] = hash(
                    'sha256',
                    json_encode($documentRectificationEvidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                );
            }
            $summary = [
                'failed_phase_code' => $failedPhaseCode,
                'verification_result' => [
                    'gates_pass' => $gatesPass,
                    'failure_reason' => $failureCode,
                    'needs_review_count' => $needsReview ? $reviewCount : null,
                ],
                'execution' => 'omr_capture_analysis_pipeline',
                'registration_mode' => $registrationMode?->value,
                'document_rectification_evidence' => $documentRectificationEvidence,
                'capture_source' => $captureSource,
                'crop_policy' => is_array($lockedCapture->summary) ? ($lockedCapture->summary['crop_policy'] ?? 'auto_document_quad') : 'auto_document_quad',
                'gates' => $prepared->gates ?? $evidence->gates,
                'marker_matches' => $prepared->markerMatches ?? $evidence->markerMatches,
                'field_block_shifts' => $prepared->fieldBlockShifts ?? $evidence->fieldBlockShifts,
                'timings' => $prepared->timings,
                'issues' => $prepared->issues,
                'calibration_status' => $evidence->calibrationStatus,
                'rectification' => $prepared->layout?->rectification ?? $prepared->providerResult?->success?->rectification ?? null,
                'evidence' => $evidence->normalize(),
                'prepared_capture' => $prepared->normalize(),
            ];
            $lockedCapture->forceFill([
                'attempt_state' => $attemptState,
                'actual' => $actual,
                'status' => $status,
                'error_code' => $hardFailed ? $failureCode : null,
                'failure_code' => $failureCode,
                'failure_detail' => $failureDetail,
                'aligned_asset_id' => $alignedAsset?->id,
                'aligned_asset_hash' => $alignedAsset?->sha256,
                'overlay_asset_id' => $overlayAsset?->id,
                'overlay_asset_hash' => $overlayAsset?->sha256,
                'evidence_version' => 'v1',
                'evidence_hash' => $prepared->evidenceHash(),
                'detected_vector_hash' => $this->detectedVectorHash($evidence),
                'vector_match' => $vectorMatch,
                'orientation_tried' => array_map(static fn ($candidate): int => (int) $candidate['orientation'], $prepared->orientationDiagnostics->toArray()['candidates'] ?? []),
                'accepted_orientation' => $prepared->acceptedOrientation,
                'orientation_diagnostics' => $prepared->orientationDiagnostics->toArray(),
                'marker_result' => $prepared->markerMatches,
                'layout_result' => $prepared->layout === null ? null : [
                    'outcome' => $prepared->layout->outcome,
                    'code' => $prepared->layout->code,
                    'detail' => $prepared->layout->detail,
                    'aligned_image' => $prepared->layout->alignedImage,
                    'overlay_image' => $prepared->layout->overlayImage,
                    'marker_matches' => $prepared->layout->markerMatches,
                    'field_block_shifts' => $prepared->layout->fieldBlockShifts,
                    'gates' => $prepared->layout->gates,
                    'rectification' => $prepared->layout->rectification,
                ],
                'gate_result' => $prepared->gates,
                'summary' => $summary,
                'lock_version' => (int) $lockedCapture->lock_version + 1,
            ])->save();
            $this->persistItemEvidence($lockedCapture, $prepared);
            if ($challenge !== null) {
                $this->persistScenarioOutcomes($challenge, $lockedCapture, $prepared);
            }

            $nextPageState = $hardFailed
                ? OmrTemplateVerificationMethodPage::STATE_FAILED
                : ($needsReview
                    ? OmrTemplateVerificationMethodPage::STATE_NEEDS_REVIEW
                    : ($autoSelect
                        ? OmrTemplateVerificationMethodPage::STATE_PASSED
                        : OmrTemplateVerificationMethodPage::STATE_NEEDS_REVIEW));
            if ($autoSelect && $passed) {
                $lockedCapture->forceFill([
                    'selected_by_user_id' => $actor->id,
                    'selected_at' => now(),
                    'selection_reason' => 'exact analysis compatibility selection',
                    'lock_version' => (int) $lockedCapture->lock_version + 1,
                ])->save();
                $page->forceFill([
                    'selected_attempt_id' => $lockedCapture->id,
                    'page_state' => OmrTemplateVerificationMethodPage::STATE_PASSED,
                    'page_version' => (int) $page->page_version + 1,
                ])->save();
            }
            $this->transitionPage($page, $nextPageState);
            $methodPagesComplete = $this->allPagesSelected($method);
            $hasAwaitingPages = OmrTemplateVerificationMethodPage::query()
                ->where('method_id', $method->id)
                ->where('required', true)
                ->where('page_state', OmrTemplateVerificationMethodPage::STATE_AWAITING_CAPTURE)
                ->exists();
            $nextMethodState = $hardFailed
                ? OmrTemplateVerificationMethod::STATE_FAILED
                : ($methodPagesComplete
                    ? OmrTemplateVerificationMethod::STATE_PASSED
                    : ($hasAwaitingPages
                        ? OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE
                        : OmrTemplateVerificationMethod::STATE_NEEDS_REVIEW));
            $this->transitionMethod($method, $nextMethodState);

            if ($challenge !== null) {
                $challengeStatus = $hardFailed
                    ? OmrPhysicalChallenge::STATUS_FAILED
                    : ($methodPagesComplete
                        ? OmrPhysicalChallenge::STATUS_PASSED
                        : OmrPhysicalChallenge::STATUS_AWAITING_CAPTURE);
                $challenge->forceFill(['status' => $challengeStatus])->save();
            }

            $stage = $hardFailed
                ? 'failed'
                : ($passed ? ($autoSelect && $methodPagesComplete ? 'passed' : 'awaiting_review') : 'needs_review');

            $this->syncAggregateRunState($run, [
                'phase_code' => $hardFailed
                    ? OmrTemplateVerificationRun::PHASE_FAILED
                    : OmrTemplateVerificationRun::PHASE_AWAITING_REVIEW,
                'stage' => $stage,
                'current_variant' => null,
                'method' => $method->method,
                'page_no' => $page->page_no,
                'capture_id' => $lockedCapture->id,
                'failed_phase_code' => $failedPhaseCode,
            ]);
            $this->audit($passed ? 'omr.challenge.capture_passed' : ($needsReview ? 'omr.challenge.capture_needs_review' : 'omr.challenge.capture_failed'), $actor, $run, $method->method, $page->page_no, (int) $lockedCapture->attempt_no, $failureCode, [
                'capture_id' => (int) $lockedCapture->id,
                'vector_match' => $vectorMatch,
                'reason' => $failureDetail,
                'gates_pass' => $gatesPass,
                'needs_review_count' => $needsReview ? $reviewCount : null,
            ]);

            return ['run' => $run->fresh(), 'capture' => $lockedCapture->fresh()];
        });
    }

    /** Atomically terminalize a stale physical worker and every active child. */
    public function finalizeTimedOutRun(OmrTemplateVerificationRun $run, User $actor): bool
    {
        return DB::transaction(function () use ($run, $actor): bool {
            if (! $this->runs->finalizeTimedOut($run->id)) {
                return false;
            }

            OmrTemplateVerificationCapture::query()
                ->where('run_id', $run->id)
                ->where('attempt_state', OmrTemplateVerificationCapture::ATTEMPT_ACTIVE)
                ->with('capturedBy')
                ->get()
                ->each(function (OmrTemplateVerificationCapture $capture) use ($actor): void {
                    $this->failCapture(
                        $capture,
                        $capture->capturedBy ?? $actor,
                        'worker_timeout',
                        'The verification worker stopped before finishing; the retained capture can be retried.',
                    );
                });

            return true;
        });
    }

    /** Fail an active attempt while retaining its immutable input asset. */
    public function failCapture(OmrTemplateVerificationCapture $capture, User $actor, string $code, string $detail): array
    {
        return DB::transaction(function () use ($capture, $actor, $code, $detail): array {
            // Match completion and selection lock order so a worker failure
            // cannot deadlock with a concurrent retry or selection.
            $run = OmrTemplateVerificationRun::query()->lockForUpdate()->findOrFail($capture->run_id);
            $method = OmrTemplateVerificationMethod::query()
                ->whereKey($capture->method_id)
                ->where('run_id', $run->id)
                ->lockForUpdate()
                ->firstOrFail();
            $page = OmrTemplateVerificationMethodPage::query()
                ->where('method_id', $method->id)
                ->where('page_no', $capture->page_no)
                ->lockForUpdate()
                ->firstOrFail();
            $challenge = $capture->challenge_id !== null
                ? OmrPhysicalChallenge::query()->whereKey($capture->challenge_id)->lockForUpdate()->first()
                : null;
            $lockedCapture = OmrTemplateVerificationCapture::query()->lockForUpdate()->findOrFail($capture->id);
            if ((int) $lockedCapture->run_id !== (int) $run->id
                || (int) $lockedCapture->method_id !== (int) $method->id
                || (int) $lockedCapture->page_no !== (int) $page->page_no) {
                throw new RuntimeException('verification_capture_binding_mismatch');
            }
            $failedPhaseCode = $this->currentAnalysisPhase($run);
            if ($lockedCapture->attempt_state !== OmrTemplateVerificationCapture::ATTEMPT_ACTIVE) {
                return ['run' => $run->fresh(), 'capture' => $lockedCapture->fresh()];
            }
            if ($run->isTerminal() && $this->isRunWideFailure($run)) {
                $lockedCapture->forceFill([
                    'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_FAILED,
                    'actual' => OmrTemplateVerificationCapture::ACTUAL_FAILED,
                    'status' => OmrTemplateVerificationCapture::STATUS_FAIL,
                    'error_code' => $run->error_code,
                    'failure_code' => $run->error_code,
                    'failure_detail' => $run->safe_error,
                    'summary' => [
                        ...(is_array($lockedCapture->summary) ? $lockedCapture->summary : []),
                        'failed_phase_code' => $failedPhaseCode,
                    ],
                    'lock_version' => (int) $lockedCapture->lock_version + 1,
                ])->save();
                $this->transitionMethod($method, OmrTemplateVerificationMethod::STATE_FAILED);
                $this->transitionPage($page, OmrTemplateVerificationMethodPage::STATE_FAILED);
                if ($challenge !== null) {
                    $challenge->forceFill(['status' => OmrPhysicalChallenge::STATUS_FAILED])->save();
                }
                $this->audit('omr.challenge.capture_failed', $actor, $run, $method->method, $page->page_no, (int) $lockedCapture->attempt_no, (string) $run->error_code, [
                    'capture_id' => (int) $lockedCapture->id,
                    'reason' => (string) $run->safe_error,
                ]);

                return ['run' => $run->fresh(), 'capture' => $lockedCapture->fresh()];
            }
            $lockedCapture->forceFill([
                'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_FAILED,
                'actual' => 'failed',
                'status' => OmrTemplateVerificationCapture::STATUS_FAIL,
                'error_code' => $code,
                'failure_code' => $code,
                'failure_detail' => $detail,
                'summary' => [
                    ...(is_array($lockedCapture->summary) ? $lockedCapture->summary : []),
                    'failed_phase_code' => $failedPhaseCode,
                ],
                'lock_version' => (int) $lockedCapture->lock_version + 1,
            ])->save();
            $this->transitionMethod($method, OmrTemplateVerificationMethod::STATE_FAILED);
            $this->transitionPage($page, OmrTemplateVerificationMethodPage::STATE_FAILED);
            if ($challenge !== null) {
                $challenge->forceFill(['status' => OmrPhysicalChallenge::STATUS_FAILED])->save();
            }
            $this->syncAggregateRunState($run, [
                'stage' => 'failed',
                'method' => $method->method,
                'page_no' => $page->page_no,
                'capture_id' => $lockedCapture->id,
            ]);
            $this->audit('omr.challenge.capture_failed', $actor, $run, $method->method, $page->page_no, (int) $lockedCapture->attempt_no, $code, [
                'capture_id' => (int) $lockedCapture->id,
                'reason' => $detail,
            ]);

            return ['run' => $run->fresh(), 'capture' => $lockedCapture->fresh()];
        });
    }

    /** Fail a physical run before an attempt can be reserved (for stale targets). */
    public function failRun(
        OmrTemplateVerificationRun $run,
        User $actor,
        string $code,
        string $detail,
    ): OmrTemplateVerificationRun {
        return DB::transaction(function () use ($run, $actor, $code, $detail): OmrTemplateVerificationRun {
            $lockedRun = OmrTemplateVerificationRun::query()->lockForUpdate()->findOrFail($run->id);
            if (! $lockedRun->isTerminal()) {
                $changed = $this->runs->fail($lockedRun->id, $code, $detail, [
                    'phase_detail' => ['stage' => 'failed', 'current_variant' => null],
                ]);
                if (! $changed) {
                    throw new RuntimeException('verification_run_failure_claim_failed');
                }
                $challenge = $lockedRun->challenges()->latest('id')->lockForUpdate()->first();
                if ($challenge !== null && $challenge->status !== OmrPhysicalChallenge::STATUS_SUPERSEDED) {
                    $challenge->forceFill(['status' => OmrPhysicalChallenge::STATUS_FAILED])->save();
                }
                $this->audit('omr.challenge.run_failed', $actor, $lockedRun, null, null, (int) $lockedRun->attempt, $code, [
                    'reason' => $detail,
                ]);
            }

            return $lockedRun->fresh();
        });
    }

    /**
     * Supersede an evidence-free quick rehearsal before starting a fresh
     * digital verification for the drifted target. All physical state writes
     * stay in this coordinator so restart cannot bypass challenge guards.
     */
    public function supersedePhysicalRun(
        OmrTemplateVerificationRun $run,
        User $actor,
        ?string $newPlanHash = null,
    ): OmrTemplateVerificationRun {
        return DB::transaction(function () use ($run, $actor, $newPlanHash): OmrTemplateVerificationRun {
            $lockedRun = OmrTemplateVerificationRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($lockedRun->mode !== OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL) {
                throw new RuntimeException('challenge_restart_invalid_mode');
            }
            if (! in_array($lockedRun->status, [
                OmrTemplateVerificationRun::STATUS_AWAITING_PRINT,
                OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE,
                OmrTemplateVerificationRun::STATUS_FAILED,
            ], true)) {
                throw new RuntimeException('challenge_restart_invalid_state');
            }
            $methods = OmrTemplateVerificationMethod::query()
                ->where('run_id', $lockedRun->id)
                ->orderBy('method')
                ->lockForUpdate()
                ->get();
            foreach ($methods as $method) {
                OmrTemplateVerificationMethodPage::query()
                    ->where('method_id', $method->id)
                    ->orderBy('page_no')
                    ->lockForUpdate()
                    ->get();
            }
            $challenge = OmrPhysicalChallenge::query()
                ->where('run_id', $lockedRun->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            if ($challenge === null || $challenge->status === OmrPhysicalChallenge::STATUS_SUPERSEDED) {
                throw new RuntimeException('challenge_restart_missing');
            }
            if ($lockedRun->captures()->exists() || $challenge->captures()->exists()) {
                throw new RuntimeException('challenge_restart_evidence_exists');
            }
            $challenge->forceFill(['status' => OmrPhysicalChallenge::STATUS_SUPERSEDED])->save();
            if ($lockedRun->status === OmrTemplateVerificationRun::STATUS_FAILED) {
                $lockedRun->forceFill([
                    'error_code' => 'target_changed_superseded',
                    'safe_error' => 'Target geometry changed; run was superseded while the current target was re-verified.',
                    'phase_detail' => ['stage' => 'failed', 'current_variant' => null],
                    'optimistic_version' => (int) $lockedRun->optimistic_version + 1,
                ])->save();
            } else {
                $changed = $this->runs->fail($lockedRun->id, 'target_changed_superseded', 'Target geometry changed; run was superseded while the current target was re-verified.', [
                    'phase_detail' => ['stage' => 'failed', 'current_variant' => null],
                ]);
                if (! $changed) {
                    throw new RuntimeException('challenge_restart_claim_failed');
                }
            }
            $this->audit('omr.challenge.superseded_by_reverification', $actor, $lockedRun, null, null, (int) $lockedRun->attempt, 'target_changed_superseded', [
                'old_challenge_id' => (int) $challenge->id,
                'new_plan_hash' => $newPlanHash,
            ]);

            return $lockedRun->fresh();
        });
    }

    /** Human review resolves one attempt; evidence facts remain otherwise immutable. */
    public function reviewCapture(
        OmrTemplateVerificationRun $run,
        User $actor,
        int $captureId,
        bool $passed,
        ?string $reason = null,
    ): OmrTemplateVerificationRun {
        return DB::transaction(function () use ($run, $actor, $captureId, $passed, $reason): OmrTemplateVerificationRun {
            $lockedRun = OmrTemplateVerificationRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($lockedRun->status === OmrTemplateVerificationRun::STATUS_PASSED
                || ($lockedRun->status === OmrTemplateVerificationRun::STATUS_FAILED && $this->isRunWideFailure($lockedRun))) {
                throw new RuntimeException('verification_run_not_reviewable');
            }
            // Keep review in the same deterministic order as worker finalization:
            // run -> method -> page -> attempt.
            $captureReference = OmrTemplateVerificationCapture::query()->whereKey($captureId)->firstOrFail();
            if ($captureReference->method_id === null || $captureReference->challenge_id === null) {
                throw new RuntimeException('verification_capture_review_invalid');
            }
            $method = OmrTemplateVerificationMethod::query()
                ->whereKey($captureReference->method_id)
                ->where('run_id', $lockedRun->id)
                ->lockForUpdate()
                ->firstOrFail();
            $page = OmrTemplateVerificationMethodPage::query()
                ->where('method_id', $method->id)
                ->where('page_no', $captureReference->page_no)
                ->lockForUpdate()
                ->firstOrFail();
            $challenge = $captureReference->challenge_id !== null
                ? OmrPhysicalChallenge::query()->whereKey($captureReference->challenge_id)->lockForUpdate()->first()
                : null;
            $capture = OmrTemplateVerificationCapture::query()->lockForUpdate()->findOrFail($captureId);
            if ((int) $capture->run_id !== (int) $lockedRun->id
                || (int) $capture->method_id !== (int) $method->id
                || (int) $capture->page_no !== (int) $page->page_no
                || $capture->isDiscarded()
                || $capture->attempt_state !== OmrTemplateVerificationCapture::ATTEMPT_FAILED) {
                throw new RuntimeException('verification_capture_review_invalid');
            }
            if ($passed) {
                if (! $this->canAcceptReviewedCapture($lockedRun, $method, $page, $challenge, $capture)) {
                    throw new RuntimeException('verification_capture_exact_recovery_required');
                }
                $this->targets->loadPhysical($lockedRun, (string) $method->method);

                $effectiveReason = trim((string) $reason) !== ''
                    ? trim((string) $reason)
                    : 'Accepted after human evidence review.';
                $this->promoteReviewedCapture($capture, $actor, $effectiveReason);
                $previousCaptureId = null;
                if ($page->selected_attempt_id !== null) {
                    $previous = OmrTemplateVerificationCapture::query()
                        ->lockForUpdate()
                        ->find((int) $page->selected_attempt_id);
                    if (! $previous instanceof OmrTemplateVerificationCapture
                        || $previous->attempt_state !== OmrTemplateVerificationCapture::ATTEMPT_PASSED
                        || $previous->isDiscarded()) {
                        throw new RuntimeException('selection_existing_pointer_invalid');
                    }
                    $previousCaptureId = (int) $previous->getKey();
                    $page->forceFill(['selected_attempt_id' => null])->save();
                    $previous->forceFill([
                        'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_SUPERSEDED,
                        'superseded_by_attempt_id' => (int) $capture->getKey(),
                        'superseded_by_user_id' => (int) $actor->getKey(),
                        'superseded_at' => now(),
                        'supersede_reason' => $effectiveReason,
                        'lock_version' => (int) $previous->lock_version + 1,
                    ])->save();
                }
                $page->forceFill([
                    'selected_attempt_id' => (int) $capture->id,
                    'page_state' => OmrTemplateVerificationMethodPage::STATE_PASSED,
                    'page_version' => (int) $page->page_version + 1,
                ])->save();

                $methodPagesComplete = $this->allPagesSelected($method);
                $methodReviewPending = OmrTemplateVerificationMethodPage::query()
                    ->where('method_id', $method->id)
                    ->where('required', true)
                    ->where('page_state', OmrTemplateVerificationMethodPage::STATE_NEEDS_REVIEW)
                    ->whereNull('selected_attempt_id')
                    ->exists();
                $this->transitionMethod(
                    $method,
                    $methodPagesComplete
                        ? OmrTemplateVerificationMethod::STATE_PASSED
                        : ($methodReviewPending
                            ? OmrTemplateVerificationMethod::STATE_NEEDS_REVIEW
                            : OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE),
                );
                if ($challenge !== null) {
                    $challenge->forceFill([
                        'status' => $methodPagesComplete
                            ? OmrPhysicalChallenge::STATUS_PASSED
                            : OmrPhysicalChallenge::STATUS_AWAITING_CAPTURE,
                    ])->save();
                }
                $this->syncAggregateRunState($lockedRun, [
                    'stage' => $methodPagesComplete
                        ? 'passed'
                        : ($methodReviewPending ? 'needs_review' : 'awaiting_capture'),
                    'method' => $method->method,
                    'page_no' => $page->page_no,
                    'capture_id' => $capture->id,
                ]);
                $this->audit('omr.challenge.capture_reviewed', $actor, $lockedRun, $method->method, $page->page_no, (int) $capture->attempt_no, 'accepted', [
                    'capture_id' => (int) $capture->id,
                    'decision' => 'accepted',
                    'reason' => $effectiveReason,
                ]);
                $this->audit('omr.challenge.attempt_selected', $actor, $lockedRun, $method->method, $page->page_no, (int) $capture->attempt_no, null, [
                    'capture_id' => (int) $capture->id,
                    'previous_capture_id' => $previousCaptureId,
                    'reason' => $effectiveReason,
                ]);

                return $lockedRun->fresh();
            }
            $capture->forceFill([
                'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_FAILED,
                'status' => OmrTemplateVerificationCapture::STATUS_FAIL,
                'failure_code' => 'review_rejected',
                'failure_detail' => $reason ?: 'Staff rejected the reviewed capture evidence.',
                'lock_version' => (int) $capture->lock_version + 1,
            ])->save();

            $this->transitionMethod($method, OmrTemplateVerificationMethod::STATE_FAILED);
            $this->transitionPage($page, OmrTemplateVerificationMethodPage::STATE_FAILED);
            if ($challenge !== null) {
                $challenge->forceFill(['status' => OmrPhysicalChallenge::STATUS_FAILED])->save();
            }
            $this->syncAggregateRunState($lockedRun, [
                'stage' => 'failed',
                'method' => $method->method,
                'page_no' => $page->page_no,
                'capture_id' => $capture->id,
            ]);
            $this->audit('omr.challenge.capture_reviewed', $actor, $lockedRun, $method->method, $page->page_no, (int) $capture->attempt_no, 'review_rejected', [
                'capture_id' => (int) $capture->id,
                'decision' => 'rejected',
                'reason' => $reason,
            ]);

            return $lockedRun->fresh();
        });
    }

    /** Reset one failed or reviewable page before a new immutable capture attempt. */
    public function retakePage(
        OmrTemplateVerificationRun $run,
        User $actor,
        string $methodName,
        int $pageNo,
        int $expectedPageVersion,
        string $idempotencyKey,
    ): OmrTemplateVerificationMethodPage {
        try {
            $operation = $this->idempotency->claim($actor, 'omr.canonical.retake_physical_page', 'run:'.$run->id, [
                'run_id' => (int) $run->id,
                'method' => $methodName,
                'page_no' => $pageNo,
                'page_version' => $expectedPageVersion,
            ], $idempotencyKey);
        } catch (RuntimeException $exception) {
            throw new RuntimeException('idempotency_key_conflict: '.$exception->getMessage());
        }
        $saved = is_array($operation->payload['result'] ?? null) ? $operation->payload['result'] : null;
        if (! $operation->wasRecentlyCreated && $saved !== null) {
            return OmrTemplateVerificationMethodPage::query()->findOrFail((int) $saved['page_id']);
        }
        if (! $operation->wasRecentlyCreated && $operation->status === Operation::STATUS_PROCESSING) {
            throw new RuntimeException('idempotency_in_progress');
        }
        $operation->forceFill([
            'status' => Operation::STATUS_PROCESSING,
            'attempts' => (int) $operation->attempts + 1,
            'last_error' => null,
            'finished_at' => null,
        ])->save();

        try {
            $page = DB::transaction(function () use ($run, $actor, $methodName, $pageNo, $expectedPageVersion): OmrTemplateVerificationMethodPage {
                $lockedRun = OmrTemplateVerificationRun::query()->lockForUpdate()->findOrFail($run->id);
                if ($lockedRun->status === OmrTemplateVerificationRun::STATUS_PASSED
                    || ($lockedRun->status === OmrTemplateVerificationRun::STATUS_FAILED && $this->isRunWideFailure($lockedRun))) {
                    throw new RuntimeException('verification_run_not_retakeable');
                }
                if (OmrTemplateVerificationCapture::query()
                    ->where('run_id', $lockedRun->id)
                    ->where('attempt_state', OmrTemplateVerificationCapture::ATTEMPT_ACTIVE)
                    ->lockForUpdate()
                    ->exists()) {
                    throw new RuntimeException('verification_capture_active: Another capture is already being analyzed. Wait for it to finish before starting a retake.');
                }
                $method = OmrTemplateVerificationMethod::query()
                    ->where('run_id', $lockedRun->id)
                    ->where('method', $methodName)
                    ->lockForUpdate()
                    ->firstOrFail();
                $page = OmrTemplateVerificationMethodPage::query()
                    ->where('method_id', $method->id)
                    ->where('page_no', $pageNo)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ((int) $page->page_version !== $expectedPageVersion) {
                    throw new RuntimeException('retake_stale');
                }
                if (! in_array($method->method_state, [
                    OmrTemplateVerificationMethod::STATE_FAILED,
                    OmrTemplateVerificationMethod::STATE_NEEDS_REVIEW,
                    OmrTemplateVerificationMethod::STATE_PASSED,
                    OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE,
                ], true) || ! in_array($page->page_state, [
                    OmrTemplateVerificationMethodPage::STATE_FAILED,
                    OmrTemplateVerificationMethodPage::STATE_NEEDS_REVIEW,
                    OmrTemplateVerificationMethodPage::STATE_PASSED,
                ], true)) {
                    throw new RuntimeException('verification_page_not_retakeable');
                }
                $challenge = $method->challenge_id !== null
                    ? OmrPhysicalChallenge::query()->whereKey($method->challenge_id)->lockForUpdate()->first()
                    : null;
                if ($challenge === null || $challenge->status === OmrPhysicalChallenge::STATUS_SUPERSEDED) {
                    throw new RuntimeException('wrong_challenge');
                }
                if ($page->selected_attempt_id !== null) {
                    // A retake invalidates the effective selection while the
                    // immutable capture remains available as audit history.
                    $page->forceFill(['selected_attempt_id' => null])->save();
                }
                $this->transitionMethod($method, OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE);
                $this->transitionPage($page, OmrTemplateVerificationMethodPage::STATE_AWAITING_CAPTURE);
                $challenge->forceFill(['status' => OmrPhysicalChallenge::STATUS_AWAITING_CAPTURE])->save();
                $this->syncAggregateRunState($lockedRun, [
                    'stage' => 'awaiting_capture',
                    'current_variant' => null,
                    'method' => $methodName,
                    'page_no' => $pageNo,
                ]);
                $this->audit('omr.challenge.page_retake_started', $actor, $lockedRun, $methodName, $pageNo, null, null, [
                    'page_version' => (int) $page->page_version,
                ]);

                return $page->fresh();
            });
        } catch (\Throwable $exception) {
            $operation->forceFill([
                'status' => Operation::STATUS_FAILED,
                'last_error' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();
            throw $exception;
        }
        $operation->forceFill([
            'status' => Operation::STATUS_SUCCEEDED,
            'finished_at' => now(),
            'payload' => [
                ...(array) $operation->payload,
                'result' => ['page_id' => (int) $page->id],
            ],
        ])->save();

        return $page;
    }

    /** Retry the run while resetting only method/page cursors; captures remain. */
    public function retryRun(OmrTemplateVerificationRun $run, User $actor): OmrTemplateVerificationRun
    {
        return DB::transaction(function () use ($run, $actor): OmrTemplateVerificationRun {
            $retryError = (string) ($run->error_code ?: 'verification_retry');
            $retried = $this->runs->retry($run->id);

            return $this->restoreRetryBoundaries($retried, $actor, $retryError);
        });
    }

    /**
     * Repair a retry that already advanced the parent attempt but retained an
     * orphaned analyzing child from the previous attempt.
     */
    public function reconcileRetriedRun(OmrTemplateVerificationRun $run, User $actor): OmrTemplateVerificationRun
    {
        return $this->restoreRetryBoundaries(
            $run,
            $actor,
            'worker_timeout',
            onlyOrphanedAnalyzing: true,
        );
    }

    private function restoreRetryBoundaries(
        OmrTemplateVerificationRun $run,
        User $actor,
        string $retryError,
        bool $onlyOrphanedAnalyzing = false,
    ): OmrTemplateVerificationRun {
        return DB::transaction(function () use ($run, $actor, $retryError, $onlyOrphanedAnalyzing): OmrTemplateVerificationRun {
            $lockedRun = OmrTemplateVerificationRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($onlyOrphanedAnalyzing && ! in_array($lockedRun->status, [
                OmrTemplateVerificationRun::STATUS_AWAITING_PRINT,
                OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE,
            ], true)) {
                return $lockedRun->fresh();
            }

            $methods = OmrTemplateVerificationMethod::query()
                ->where('run_id', $lockedRun->id)
                ->orderBy('method')
                ->lockForUpdate()
                ->get();
            $pages = OmrTemplateVerificationMethodPage::query()
                ->whereIn('method_id', $methods->pluck('id'))
                ->orderBy('method_id')
                ->orderBy('page_no')
                ->lockForUpdate()
                ->get();
            $captures = OmrTemplateVerificationCapture::query()
                ->where('run_id', $lockedRun->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($onlyOrphanedAnalyzing && $captures->contains(
                static fn (OmrTemplateVerificationCapture $capture): bool => $capture->attempt_state === OmrTemplateVerificationCapture::ATTEMPT_ACTIVE,
            )) {
                return $lockedRun->fresh();
            }

            $orphanedMethodIds = $onlyOrphanedAnalyzing
                ? $methods
                    ->filter(function (OmrTemplateVerificationMethod $method) use ($captures, $lockedRun): bool {
                        if ($method->method_state !== OmrTemplateVerificationMethod::STATE_ANALYZING) {
                            return false;
                        }

                        return $captures
                            ->where('method_id', $method->id)
                            ->contains(static fn (OmrTemplateVerificationCapture $capture): bool => (int) $capture->attempt < (int) $lockedRun->attempt
                                && $capture->attempt_state !== OmrTemplateVerificationCapture::ATTEMPT_ACTIVE);
                    })
                    ->pluck('id')
                : $methods->pluck('id');

            if ($onlyOrphanedAnalyzing && $orphanedMethodIds->isEmpty()) {
                return $lockedRun->fresh();
            }

            foreach ($captures as $capture) {
                if ($capture->attempt_state !== OmrTemplateVerificationCapture::ATTEMPT_ACTIVE) {
                    continue;
                }
                $capture->forceFill([
                    'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_FAILED,
                    'actual' => OmrTemplateVerificationCapture::ACTUAL_FAILED,
                    'status' => OmrTemplateVerificationCapture::STATUS_FAIL,
                    'error_code' => $retryError,
                    'failure_code' => $retryError,
                    'failure_detail' => 'The previous worker attempt was closed before retry.',
                    'lock_version' => (int) $capture->lock_version + 1,
                ])->save();
            }

            if ($methods->isEmpty()) {
                OmrPhysicalChallenge::query()
                    ->where('run_id', $lockedRun->id)
                    ->where('status', OmrPhysicalChallenge::STATUS_PRINTED)
                    ->lockForUpdate()
                    ->update(['status' => OmrPhysicalChallenge::STATUS_AWAITING_CAPTURE]);
            }

            foreach ($methods as $method) {
                if (! $orphanedMethodIds->contains($method->id)) {
                    continue;
                }
                $methodPages = $pages->where('method_id', $method->id);
                $methodPagesComplete = $methodPages->isNotEmpty()
                    && $methodPages->every(
                        static fn (OmrTemplateVerificationMethodPage $page): bool => $page->selected_attempt_id !== null,
                    );
                $challenge = $method->challenge_id !== null
                    ? OmrPhysicalChallenge::query()->whereKey($method->challenge_id)->lockForUpdate()->first()
                    : null;
                $hasCapture = $captures->contains(
                    static fn (OmrTemplateVerificationCapture $capture): bool => (int) $capture->method_id === (int) $method->id,
                );
                $awaitingPrint = ! $methodPagesComplete
                    && $challenge !== null
                    && ! $hasCapture
                    && $challenge->status === OmrPhysicalChallenge::STATUS_CREATED;
                $methodState = $methodPagesComplete
                    ? OmrTemplateVerificationMethod::STATE_PASSED
                    : ($awaitingPrint
                        ? OmrTemplateVerificationMethod::STATE_CHALLENGE_READY
                        : ($challenge !== null
                            ? OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE
                            : OmrTemplateVerificationMethod::STATE_NOT_STARTED));
                $method->forceFill([
                    'method_state' => $methodState,
                    'last_attempt_at' => now(),
                    'lock_version' => (int) $method->lock_version + 1,
                ])->save();

                foreach ($methodPages as $page) {
                    $page->forceFill([
                        'page_state' => $page->selected_attempt_id !== null
                            ? OmrTemplateVerificationMethodPage::STATE_PASSED
                            : (in_array($methodState, [
                                OmrTemplateVerificationMethod::STATE_NOT_STARTED,
                                OmrTemplateVerificationMethod::STATE_CHALLENGE_READY,
                            ], true)
                                ? OmrTemplateVerificationMethodPage::STATE_NOT_STARTED
                                : OmrTemplateVerificationMethodPage::STATE_AWAITING_CAPTURE),
                        'page_version' => (int) $page->page_version + 1,
                    ])->save();
                }

                if ($challenge !== null) {
                    $challenge->forceFill([
                        'status' => $methodPagesComplete
                            ? OmrPhysicalChallenge::STATUS_PASSED
                            : ($awaitingPrint
                                ? OmrPhysicalChallenge::STATUS_CREATED
                                : OmrPhysicalChallenge::STATUS_AWAITING_CAPTURE),
                    ])->save();
                }
            }

            $synchronized = $this->syncAggregateRunState($lockedRun);
            $this->audit(
                $onlyOrphanedAnalyzing
                    ? 'omr.verification.interrupted_capture_reconciled'
                    : 'omr.verification.retried',
                $actor,
                $synchronized,
                null,
                null,
                (int) $synchronized->attempt,
                null,
                [
                    'plan_hash' => (string) $synchronized->plan_hash,
                    'mode' => (string) $synchronized->mode,
                ],
            );

            return $synchronized->fresh();
        });
    }

    /**
     * Select a reviewed pass and atomically supersede a prior selected pass.
     * The method/page pointer is locked before either capture transition.
     */
    public function selectAttempt(
        OmrTemplateVerificationRun $run,
        User $actor,
        string $methodName,
        int $pageNo,
        int $captureId,
        string $reason,
        ?string $targetHash = null,
        ?string $planHash = null,
        ?string $expectedVectorHash = null,
        ?int $expectedPageVersion = null,
        ?string $idempotencyKey = null,
    ): OmrTemplateVerificationMethodPage {
        $operation = null;
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            try {
                $operation = $this->idempotency->claim($actor, 'omr.canonical.select_physical_attempt', 'run:'.$run->id, [
                    'run_id' => (int) $run->id,
                    'method' => $methodName,
                    'page_no' => $pageNo,
                    'capture_id' => $captureId,
                    'reason' => trim($reason) !== '' ? trim($reason) : 'staff_confirmed_exact_pass',
                    'target_hash' => $targetHash,
                    'plan_hash' => $planHash,
                    'expected_vector_hash' => $expectedVectorHash,
                    'page_version' => $expectedPageVersion,
                ], $idempotencyKey);
            } catch (RuntimeException $exception) {
                throw new RuntimeException('idempotency_key_conflict: '.$exception->getMessage());
            }
            $saved = is_array($operation->payload['result'] ?? null) ? $operation->payload['result'] : null;
            if (! $operation->wasRecentlyCreated && $saved !== null) {
                return OmrTemplateVerificationMethodPage::query()->findOrFail((int) ($saved['page_id'] ?? 0));
            }
            if (! $operation->wasRecentlyCreated && $operation->status === Operation::STATUS_PROCESSING) {
                throw new RuntimeException('idempotency_in_progress');
            }
            $operation->forceFill(['status' => Operation::STATUS_PROCESSING, 'attempts' => (int) $operation->attempts + 1, 'finished_at' => null])->save();
        }
        try {

            $page = DB::transaction(function () use ($run, $actor, $methodName, $pageNo, $captureId, $reason, $targetHash, $planHash, $expectedVectorHash, $expectedPageVersion): OmrTemplateVerificationMethodPage {
                $lockedRun = OmrTemplateVerificationRun::query()->lockForUpdate()->findOrFail($run->id);
                $method = OmrTemplateVerificationMethod::query()->where('run_id', $lockedRun->id)->where('method', $methodName)->lockForUpdate()->first();
                if ($method === null) {
                    throw new RuntimeException('verification_method_missing');
                }
                $page = OmrTemplateVerificationMethodPage::query()->where('method_id', $method->id)->where('page_no', $pageNo)->lockForUpdate()->first();
                if ($page === null) {
                    throw new RuntimeException('verification_page_invalid');
                }
                if ($expectedPageVersion !== null && (int) $page->page_version !== $expectedPageVersion) {
                    throw new RuntimeException('selection_stale');
                }
                $selectedReference = OmrTemplateVerificationCapture::query()
                    ->whereKey($captureId)
                    ->first();
                if ($selectedReference === null) {
                    throw new RuntimeException('selection_boundary_mismatch');
                }
                $challenge = $selectedReference->challenge_id !== null
                    ? OmrPhysicalChallenge::query()->whereKey($selectedReference->challenge_id)->lockForUpdate()->first()
                    : null;
                $selected = OmrTemplateVerificationCapture::query()->whereKey($captureId)->lockForUpdate()->first();
                if ($selected === null
                    || (int) $selected->run_id !== (int) $lockedRun->id
                    || (int) $selected->method_id !== (int) $method->id
                    || (string) $selected->method !== $methodName
                    || (int) $selected->page_no !== $pageNo
                    || (string) $selected->target_kind !== (string) $method->target_kind
                    || (int) $selected->target_id !== (int) $method->target_id
                    || (string) $selected->target_version !== (string) $method->target_version
                    || (string) $selected->target_hash !== (string) $method->target_hash
                    || (string) $selected->plan_hash !== (string) $method->plan_hash
                    || (string) $selected->expected_vector_hash !== (string) $method->expected_vector_hash
                    || ($method->challenge_id !== null && (int) $selected->challenge_id !== (int) $method->challenge_id)) {
                    throw new RuntimeException('selection_boundary_mismatch');
                }
                if ($selected->isDiscarded() || $selected->isSuperseded()) {
                    throw new RuntimeException('selection_attempt_not_passed');
                }
                $selectedSummary = is_array($selected->summary) ? $selected->summary : [];
                $verificationResult = is_array($selectedSummary['verification_result'] ?? null)
                    ? $selectedSummary['verification_result']
                    : [];
                $exactPass = $selected->attempt_state === OmrTemplateVerificationCapture::ATTEMPT_PASSED
                    && $selected->status === OmrTemplateVerificationCapture::STATUS_PASS
                    && ($verificationResult['gates_pass'] ?? null) === true
                    && $selected->actual === OmrTemplateVerificationCapture::ACTUAL_PASS
                    && $selected->vector_match === true
                    && $selected->failure_code === null;
                $canAcceptReview = $this->canAcceptReviewedCapture($lockedRun, $method, $page, $challenge, $selected);
                $reviewedPass = $canAcceptReview
                    || ((int) $page->selected_attempt_id === (int) $selected->getKey()
                        && $this->isSelectedPassValid($method, $page));
                if (! $exactPass && ! $reviewedPass) {
                    throw new RuntimeException('verification_capture_exact_recovery_required');
                }
                if ($canAcceptReview) {
                    $this->targets->loadPhysical($lockedRun, $methodName);
                }
                foreach ([[$targetHash, $method->target_hash, 'stale_target'], [$planHash, $method->plan_hash, 'stale_plan'], [$expectedVectorHash, $method->expected_vector_hash, 'stale_expected_vector']] as [$provided, $expected, $error]) {
                    if ($provided !== null && $provided !== '' && ! hash_equals((string) $expected, (string) $provided)) {
                        throw new RuntimeException($error);
                    }
                }
                if ($page->selected_attempt_id !== null && (int) $page->selected_attempt_id === (int) $selected->id) {
                    return $page->fresh();
                }
                $old = $page->selected_attempt_id !== null
                    ? OmrTemplateVerificationCapture::query()->lockForUpdate()->find($page->selected_attempt_id)
                    : null;
                if ($old !== null && trim($reason) === '') {
                    throw new RuntimeException('replacement_reason_required');
                }
                $effectiveReason = $old !== null
                    ? trim($reason)
                    : (trim($reason) !== ''
                        ? trim($reason)
                        : ($reviewedPass ? 'Accepted after human evidence review.' : 'staff_confirmed_exact_pass'));
                $reviewedPromotionComplete = false;
                if ($reviewedPass
                    && $selected->attempt_state === OmrTemplateVerificationCapture::ATTEMPT_FAILED
                    && $old !== null) {
                    $this->promoteReviewedCapture($selected, $actor, $effectiveReason);
                    $reviewedPromotionComplete = true;
                }
                if ($old !== null) {
                    $page->forceFill(['selected_attempt_id' => null])->save();
                    if ($old->attempt_state !== OmrTemplateVerificationCapture::ATTEMPT_PASSED) {
                        throw new RuntimeException('selection_existing_pointer_invalid');
                    }
                    $old->forceFill([
                        'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_SUPERSEDED,
                        'superseded_by_attempt_id' => $selected->id,
                        'superseded_by_user_id' => $actor->id,
                        'superseded_at' => now(),
                        'supersede_reason' => $effectiveReason,
                        'lock_version' => (int) $old->lock_version + 1,
                    ])->save();
                }
                if ($reviewedPass) {
                    if (! $reviewedPromotionComplete) {
                        $this->promoteReviewedCapture($selected, $actor, $effectiveReason);
                    }
                } else {
                    $selected->forceFill([
                        'selected_by_user_id' => $actor->id,
                        'selected_at' => now(),
                        'selection_reason' => $effectiveReason,
                        'lock_version' => (int) $selected->lock_version + 1,
                    ])->save();
                }
                $page->forceFill([
                    'selected_attempt_id' => $selected->id,
                    'page_state' => OmrTemplateVerificationMethodPage::STATE_PASSED,
                    'page_version' => (int) $page->page_version + 1,
                ])->save();
                $methodPagesComplete = $this->allPagesSelected($method);
                $methodReviewPending = OmrTemplateVerificationMethodPage::query()
                    ->where('method_id', $method->id)
                    ->where('required', true)
                    ->where('page_state', OmrTemplateVerificationMethodPage::STATE_NEEDS_REVIEW)
                    ->whereNull('selected_attempt_id')
                    ->exists();
                $method->forceFill([
                    'method_state' => $methodPagesComplete
                        ? OmrTemplateVerificationMethod::STATE_PASSED
                        : ($methodReviewPending
                            ? OmrTemplateVerificationMethod::STATE_NEEDS_REVIEW
                            : OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE),
                    'completed_at' => $methodPagesComplete ? now() : $method->completed_at,
                    'lock_version' => (int) $method->lock_version + 1,
                ])->save();
                if ($challenge !== null) {
                    $challenge->forceFill([
                        'status' => $methodPagesComplete
                            ? OmrPhysicalChallenge::STATUS_PASSED
                            : OmrPhysicalChallenge::STATUS_AWAITING_CAPTURE,
                    ])->save();
                }
                $this->audit('omr.challenge.attempt_selected', $actor, $lockedRun, $methodName, $pageNo, (int) $selected->attempt_no, null, [
                    'capture_id' => (int) $selected->id,
                    'previous_capture_id' => $old?->id,

                    'reason' => $effectiveReason,
                ]);
                $this->syncAggregateRunState($lockedRun);

                return $page->fresh();
            });
        } catch (\Throwable $exception) {
            if ($operation !== null) {
                $operation->forceFill([
                    'status' => Operation::STATUS_FAILED,
                    'last_error' => $exception->getMessage(),
                    'finished_at' => now(),
                ])->save();
            }
            throw $exception;
        }
        if ($operation !== null) {
            $operation->forceFill([
                'status' => Operation::STATUS_SUCCEEDED,
                'finished_at' => now(),
                'payload' => [...(array) $operation->payload, 'result' => ['page_id' => (int) $page->id, 'capture_id' => $captureId]],
            ])->save();
        }

        return $page;
    }

    public function reconcileHistoricalFailure(OmrTemplateVerificationRun $run): bool
    {
        return DB::transaction(function () use ($run): bool {
            $lockedRun = OmrTemplateVerificationRun::query()->lockForUpdate()->findOrFail($run->id);
            if (! in_array($lockedRun->mode, [
                OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
                OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
            ], true)
                || $lockedRun->status !== OmrTemplateVerificationRun::STATUS_FAILED
                || $this->isRunWideFailure($lockedRun)
                || OmrTemplateVerificationCapture::query()
                    ->where('run_id', $lockedRun->id)
                    ->where('attempt_state', OmrTemplateVerificationCapture::ATTEMPT_ACTIVE)
                    ->exists()
                || ! $lockedRun->methods()->exists()) {
                return false;
            }

            // Historical child failures are recoverable only when every
            // method still resolves to the run's pinned target and plan.
            // The loader owns drift detection and its typed error/terminal
            // side effects; do not reopen a run that can no longer capture.
            foreach ($lockedRun->methods()->orderBy('method')->get() as $method) {
                try {
                    $this->targets->loadPhysical($lockedRun, (string) $method->method);
                } catch (OmrDiagnosticTargetException) {
                    return false;
                }
            }

            if (! $this->runs->recoverPhysicalFailure($lockedRun->id)) {
                return false;
            }
            $reconciled = $this->syncAggregateRunState($lockedRun->fresh());
            $this->recordAuditEvent->handle('omr.verification.historical_failure_reconciled', [
                'run_id' => (int) $reconciled->id,
                'status' => (string) $reconciled->status,
                'progress_done' => (int) $reconciled->progress_done,
                'progress_total' => (int) $reconciled->progress_total,
            ]);

            return true;
        });
    }

    private function assertMethodPlan(OmrTemplateVerificationMethod $method, OmrProofPlan $plan): void
    {
        if ((string) $method->method !== (string) $plan->method
            || ! hash_equals((string) $method->target_hash, (string) ($plan->targetHashes['artifact'] ?? ''))
            || (string) $method->plan_hash !== (string) $plan->planHash()
            || (string) $method->expected_vector_hash !== $this->expectedVectorHash($plan)) {
            throw new RuntimeException('verification_method_plan_mismatch');
        }
    }

    private function transitionMethod(OmrTemplateVerificationMethod $method, string $to): void
    {
        if ($method->method_state === $to) {
            return;
        }
        if (! in_array($to, self::METHOD_TRANSITIONS[$method->method_state] ?? [], true)) {
            throw new RuntimeException('verification_method_transition_invalid');
        }
        $fields = [
            'method_state' => $to,
            'lock_version' => (int) $method->lock_version + 1,
        ];
        if ($to === OmrTemplateVerificationMethod::STATE_PASSED) {
            $fields['completed_at'] = now();
        } elseif ($to === OmrTemplateVerificationMethod::STATE_FAILED) {
            $fields['failed_at'] = now();
        }
        $method->forceFill($fields)->save();
    }

    private function transitionPage(OmrTemplateVerificationMethodPage $page, string $to): void
    {
        if ($page->page_state === $to) {
            return;
        }
        if (! in_array($to, self::PAGE_TRANSITIONS[$page->page_state] ?? [], true)) {
            throw new RuntimeException('verification_page_transition_invalid');
        }
        $page->forceFill([
            'page_state' => $to,
            'page_version' => (int) $page->page_version + 1,
        ])->save();
    }

    private function transitionPages(OmrTemplateVerificationMethod $method, string $to): void
    {
        $pages = OmrTemplateVerificationMethodPage::query()->where('method_id', $method->id)->lockForUpdate()->get();
        foreach ($pages as $page) {
            $this->transitionPage($page, $to);
        }
    }

    private function planPageCount(OmrProofPlan $plan): int
    {
        $pages = array_map(static fn (array $instruction): int => (int) ($instruction['page_no'] ?? 0), $plan->pageInstructions);

        return max(1, ...$pages);
    }

    private function expectedVectorHash(OmrProofPlan $plan): string
    {
        return hash('sha256', json_encode($plan->expectedVector, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function requiredPageCountForRun(OmrTemplateVerificationRun $run): int
    {
        $methodIds = OmrTemplateVerificationMethod::query()->where('run_id', $run->id)->pluck('id');
        $count = (int) OmrTemplateVerificationMethodPage::query()->whereIn('method_id', $methodIds)->where('required', true)->count();

        return max(1, $count);
    }

    private function passedPageCount(OmrTemplateVerificationMethod $method): int
    {
        return (int) OmrTemplateVerificationMethodPage::query()->where('method_id', $method->id)->where('page_state', OmrTemplateVerificationMethodPage::STATE_PASSED)->count();
    }

    public function canAcceptReviewedCapture(
        OmrTemplateVerificationRun $run,
        OmrTemplateVerificationMethod $method,
        OmrTemplateVerificationMethodPage $page,
        ?OmrPhysicalChallenge $challenge,
        OmrTemplateVerificationCapture $capture,
    ): bool {
        if ($run->status === OmrTemplateVerificationRun::STATUS_PASSED
            || ($run->status === OmrTemplateVerificationRun::STATUS_FAILED && $this->isRunWideFailure($run))
            || $challenge === null
            || $challenge->status === OmrPhysicalChallenge::STATUS_SUPERSEDED) {
            return false;
        }
        $summary = is_array($capture->summary) ? $capture->summary : [];
        $verificationResult = is_array($summary['verification_result'] ?? null)
            ? $summary['verification_result']
            : [];
        $reviewCount = $verificationResult['needs_review_count']
            ?? ($summary['needs_review_count'] ?? null);
        $wasReviewFailure = $capture->attempt_state === OmrTemplateVerificationCapture::ATTEMPT_FAILED
            && $capture->status === OmrTemplateVerificationCapture::STATUS_FAIL
            && $capture->failure_code === 'capture_needs_review';
        $wasPromotedReview = $capture->attempt_state === OmrTemplateVerificationCapture::ATTEMPT_PASSED
            && $capture->status === OmrTemplateVerificationCapture::STATUS_PASS
            && $capture->failure_code === null;

        if ($capture->isDiscarded()
            || $capture->isSuperseded()
            || $capture->actual !== OmrTemplateVerificationCapture::ACTUAL_ANSWER_VECTOR_MISMATCH
            || $capture->vector_match !== false
            || (! $wasReviewFailure && ! $wasPromotedReview)
            || ($verificationResult['gates_pass'] ?? null) !== true
            || ! is_int($reviewCount)
            || $reviewCount < 1
            || ((int) ($page->selected_attempt_id ?? 0) === (int) $capture->getKey())
            || ($wasReviewFailure
                && (! in_array($method->method_state, [
                    OmrTemplateVerificationMethod::STATE_NEEDS_REVIEW,
                    OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE,
                ], true)
                    || $page->page_state !== OmrTemplateVerificationMethodPage::STATE_NEEDS_REVIEW))
            || ! $this->captureMatchesCurrentBinding($run, $method, $page, $challenge, $capture)) {
            return false;
        }

        $latestRetainedId = (int) OmrTemplateVerificationCapture::query()
            ->where('method_id', $method->id)
            ->where('page_no', $page->page_no)
            ->whereNull('discarded_at')
            ->orderByDesc('attempt_no')
            ->orderByDesc('id')
            ->value('id');
        if ($latestRetainedId !== (int) $capture->getKey()) {
            return false;
        }

        return $this->reviewedAnswersMatchPlan($capture);
    }

    private function captureMatchesCurrentBinding(
        OmrTemplateVerificationRun $run,
        OmrTemplateVerificationMethod $method,
        OmrTemplateVerificationMethodPage $page,
        ?OmrPhysicalChallenge $challenge,
        OmrTemplateVerificationCapture $capture,
    ): bool {
        return $challenge !== null
            && (int) $capture->run_id === (int) $run->getKey()
            && (int) $capture->method_id === (int) $method->getKey()
            && (int) $capture->challenge_id === (int) $challenge->getKey()
            && (int) $page->method_id === (int) $method->getKey()
            && (int) $capture->page_no === (int) $page->page_no
            && (string) $capture->method === (string) $method->method
            && (int) $method->run_id === (int) $run->getKey()
            && (int) $method->challenge_id === (int) $challenge->getKey()
            && (int) $challenge->run_id === (int) $run->getKey()
            && (string) $challenge->method === (string) $method->method
            && (string) ($run->draft_id !== null
                ? OmrTemplateVerificationMethod::TARGET_CANDIDATE
                : OmrTemplateVerificationMethod::TARGET_COMPILED_VERSION) === (string) $capture->target_kind
            && (int) ($run->draft_id ?? $run->template_version_id) === (int) $capture->target_id
            && (string) $run->artifact_hash === (string) $capture->target_hash
            && (string) $method->target_kind === (string) $capture->target_kind
            && (int) $method->target_id === (int) $capture->target_id
            && (string) $method->target_version === (string) $capture->target_version
            && (string) $method->target_hash === (string) $capture->target_hash
            && (string) $method->plan_version === (string) $capture->plan_version
            && (string) $method->plan_hash === (string) $capture->plan_hash
            && (string) $method->expected_vector_hash === (string) $capture->expected_vector_hash
            && (string) $challenge->target_kind === (string) $capture->target_kind
            && (int) $challenge->target_id === (int) $capture->target_id
            && (string) $challenge->target_version === (string) $capture->target_version
            && (string) $challenge->target_hash === (string) $capture->target_hash
            && (string) $challenge->plan_version === (string) $capture->plan_version
            && (string) $challenge->plan_hash === (string) $capture->plan_hash
            && (string) $challenge->expected_vector_hash === (string) $capture->expected_vector_hash;
    }

    private function reviewedAnswersMatchPlan(OmrTemplateVerificationCapture $capture): bool
    {
        $items = OmrTemplateVerificationItemEvidence::query()
            ->where('capture_id', $capture->getKey())
            ->where('group_type', OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA)
            ->orderBy('id')
            ->get();
        if ($items->isEmpty()) {
            return false;
        }
        $hasExpected = $items->contains(
            static fn (OmrTemplateVerificationItemEvidence $item): bool => trim((string) ($item->expected ?? '')) !== '',
        );

        $reviews = OmrItemReviewRevision::query()
            ->whereIn('verification_item_evidence_id', $items->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all())
            ->latestRevision()
            ->get()
            ->keyBy(static fn (OmrItemReviewRevision $review): int => (int) $review->verification_item_evidence_id);

        foreach ($items as $item) {
            $expected = trim((string) ($item->expected ?? ''));
            $blankNegative = $expected === '';
            if ($hasExpected && $blankNegative) {
                continue;
            }
            $review = $reviews->get((int) $item->getKey());
            $reviewDecision = $review?->decision instanceof OmrReviewDecision
                ? $review->decision->value
                : (string) ($review?->decision ?? '');
            if ($review !== null
                && ((string) $review->item_key !== (string) $item->item_id
                    || ! in_array($reviewDecision, [
                        OmrReviewDecision::Blank->value,
                        OmrReviewDecision::ConfirmMachine->value,
                        OmrReviewDecision::SetOption->value,
                    ], true)
                    || ! is_string($item->evidence_hash)
                    || ! hash_equals(
                        strtolower((string) $item->evidence_hash),
                        strtolower((string) $review->reviewed_machine_evidence_hash),
                    ))) {
                return false;
            }
            $hasCurrentReview = $review !== null;
            if ($hasCurrentReview) {
                if ($blankNegative) {
                    if ($reviewDecision !== OmrReviewDecision::Blank->value) {
                        return false;
                    }

                    continue;
                }

                if (! in_array($reviewDecision, [
                    OmrReviewDecision::ConfirmMachine->value,
                    OmrReviewDecision::SetOption->value,
                ], true)
                    || trim((string) $review->selected_option) !== $expected) {
                    return false;
                }

                continue;
            }

            $machineState = strtolower(trim((string) $item->state));
            $detected = trim((string) ($item->detected ?? ''));
            if ($blankNegative) {
                if (! in_array($machineState, [
                    OmrTemplateVerificationItemEvidence::STATE_MISSING,
                    OmrTemplateVerificationItemEvidence::STATE_MATCH,
                ], true)) {
                    return false;
                }

                continue;
            }

            if ($machineState !== OmrTemplateVerificationItemEvidence::STATE_MATCH
                || $detected !== $expected) {
                return false;
            }
        }

        return true;
    }

    private function promoteReviewedCapture(
        OmrTemplateVerificationCapture $capture,
        User $actor,
        string $reason,
    ): void {
        $wasFailed = $capture->attempt_state === OmrTemplateVerificationCapture::ATTEMPT_FAILED;
        $nextLockVersion = (int) $capture->lock_version + 1;
        if ($wasFailed) {
            $capture->forceFill([
                'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_PASSED,
                'status' => OmrTemplateVerificationCapture::STATUS_PASS,
                'failure_code' => null,
                'failure_detail' => null,
                'lock_version' => $nextLockVersion,
            ])->save();
        }

        $capture->forceFill([
            'selected_by_user_id' => (int) $actor->getKey(),
            'selected_at' => now(),
            'selection_reason' => $reason,
            'lock_version' => $wasFailed ? (int) $capture->lock_version : $nextLockVersion,
        ])->save();
    }

    private function isSelectedPassValid(
        OmrTemplateVerificationMethod $method,
        OmrTemplateVerificationMethodPage $page,
    ): bool {
        $capture = $page->selectedAttempt;
        if (! $capture instanceof OmrTemplateVerificationCapture
            || (int) $page->selected_attempt_id !== (int) $capture->getKey()
            || $capture->attempt_state !== OmrTemplateVerificationCapture::ATTEMPT_PASSED
            || $capture->status !== OmrTemplateVerificationCapture::STATUS_PASS
            || $capture->isDiscarded()
            || $capture->isSuperseded()
            || (int) $capture->method_id !== (int) $method->getKey()
            || (int) $capture->page_no !== (int) $page->page_no) {
            return false;
        }

        $run = $method->run;
        $challenge = $capture->challenge;
        if (! $run instanceof OmrTemplateVerificationRun
            || ! $this->captureMatchesCurrentBinding($run, $method, $page, $challenge, $capture)) {
            return false;
        }
        if ($page->page_state !== OmrTemplateVerificationMethodPage::STATE_PASSED) {
            return false;
        }
        $latestRetainedId = (int) OmrTemplateVerificationCapture::query()
            ->where('method_id', $method->id)
            ->where('page_no', $page->page_no)
            ->whereNull('discarded_at')
            ->orderByDesc('attempt_no')
            ->orderByDesc('id')
            ->value('id');
        if ($latestRetainedId !== (int) $capture->getKey()) {
            return false;
        }

        $summary = is_array($capture->summary) ? $capture->summary : [];
        $verificationResult = is_array($summary['verification_result'] ?? null)
            ? $summary['verification_result']
            : [];
        if (($verificationResult['gates_pass'] ?? null) !== true) {
            return false;
        }

        if ($capture->actual === OmrTemplateVerificationCapture::ACTUAL_PASS) {
            return $capture->vector_match === true && $capture->failure_code === null;
        }

        return $capture->actual === OmrTemplateVerificationCapture::ACTUAL_ANSWER_VECTOR_MISMATCH
            && $capture->vector_match === false
            && $capture->failure_code === null
            && $this->reviewedAnswersMatchPlan($capture);
    }

    private function allPagesSelected(OmrTemplateVerificationMethod $method): bool
    {
        $pages = OmrTemplateVerificationMethodPage::query()
            ->where('method_id', $method->id)
            ->where('required', true)
            ->with('selectedAttempt')
            ->orderBy('page_no')
            ->get();
        if ($pages->isEmpty()) {
            return false;
        }

        foreach ($pages as $page) {
            if (! $this->isSelectedPassValid($method, $page)) {
                return false;
            }
        }

        return true;
    }

    private function syncAggregateRunState(
        OmrTemplateVerificationRun $run,
        ?array $phaseDetail = null,
        ?string $errorCode = null,
        ?string $safeError = null,
    ): OmrTemplateVerificationRun {
        $lockedRun = OmrTemplateVerificationRun::query()->lockForUpdate()->findOrFail($run->id);

        $methods = OmrTemplateVerificationMethod::query()
            ->where('run_id', $lockedRun->id)
            ->orderBy('method')
            ->lockForUpdate()
            ->get();
        $methodIds = $methods->pluck('id');
        $allPages = OmrTemplateVerificationMethodPage::query()
            ->whereIn('method_id', $methodIds)
            ->orderBy('page_no')
            ->lockForUpdate()
            ->get();
        $requiredPages = $allPages->where('required', true);
        $total = max(1, $requiredPages->count());
        $done = $requiredPages->whereNotNull('selected_attempt_id')->count();
        $isRunWideFailure = $errorCode !== null && in_array($errorCode, self::RUN_WIDE_FAILURE_CODES, true);

        if ($isRunWideFailure) {
            $this->runs->syncPhysicalState($lockedRun->id, OmrTemplateVerificationRun::STATUS_FAILED, [
                'progress_done' => $done,
                'progress_total' => $total,
                'error_code' => $errorCode,
                'safe_error' => $safeError,
                'finished_at' => now(),
                'phase_detail' => [
                    ...($phaseDetail ?? []),
                    'phase_code' => OmrTemplateVerificationRun::PHASE_FAILED,
                    'stage' => OmrTemplateVerificationRun::PHASE_FAILED,
                    'current_variant' => null,
                ],
            ]);

            return $lockedRun->fresh();
        }

        if ($methods->isEmpty()) {
            $legacyChallenge = OmrPhysicalChallenge::query()
                ->where('run_id', $lockedRun->id)
                ->where('status', '!=', OmrPhysicalChallenge::STATUS_SUPERSEDED)
                ->latest('id')
                ->first();
            $status = $legacyChallenge?->status === OmrPhysicalChallenge::STATUS_CREATED
                ? OmrTemplateVerificationRun::STATUS_AWAITING_PRINT
                : OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE;
            $this->runs->syncPhysicalState($lockedRun->id, $status, [
                'progress_done' => $done,
                'progress_total' => $total,
                'error_code' => null,
                'safe_error' => null,
                'finished_at' => null,
                'phase_detail' => $phaseDetail ?? ['stage' => $status, 'current_variant' => null],
            ]);

            return $lockedRun->fresh();
        }

        $allMethodsPassed = $methods->isNotEmpty()
            && $methods->every(fn (OmrTemplateVerificationMethod $method): bool => $method->method_state === OmrTemplateVerificationMethod::STATE_PASSED);
        if ($done >= $total && $allMethodsPassed) {
            $this->runs->syncPhysicalState($lockedRun->id, OmrTemplateVerificationRun::STATUS_PASSED, [
                'progress_done' => $done,
                'progress_total' => $total,
                'error_code' => null,
                'safe_error' => null,
                'finished_at' => now(),
                'phase_detail' => [
                    ...($phaseDetail ?? []),
                    'phase_code' => OmrTemplateVerificationRun::PHASE_PASSED,
                    'stage' => OmrTemplateVerificationRun::PHASE_PASSED,
                    'current_variant' => null,
                ],
            ]);

            return $lockedRun->fresh();
        }

        // 3. Active capture in progress
        $hasActiveCapture = OmrTemplateVerificationCapture::query()
            ->where('run_id', $lockedRun->id)
            ->where('attempt_state', OmrTemplateVerificationCapture::ATTEMPT_ACTIVE)
            ->exists();

        if ($hasActiveCapture) {
            $this->runs->syncPhysicalState($lockedRun->id, OmrTemplateVerificationRun::STATUS_ANALYZING_CAPTURE, [
                'progress_done' => $done,
                'progress_total' => $total,
                'error_code' => null,
                'safe_error' => null,
                'last_heartbeat_at' => now(),
                'finished_at' => null,
                'phase_detail' => $phaseDetail ?? ['stage' => 'analyzing_capture', 'current_variant' => 'physical_capture'],
            ]);

            return $lockedRun->fresh();
        }

        // 4. Actionable method or page: awaiting capture or retryable failure
        $hasAwaitingCapture = $methods->contains(fn (OmrTemplateVerificationMethod $m): bool => in_array($m->method_state, [
            OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE,
            OmrTemplateVerificationMethod::STATE_FAILED,
        ], true)) || $allPages->contains(fn (OmrTemplateVerificationMethodPage $p): bool => in_array($p->page_state, [
            OmrTemplateVerificationMethodPage::STATE_AWAITING_CAPTURE,
            OmrTemplateVerificationMethodPage::STATE_FAILED,
        ], true));

        if ($hasAwaitingCapture) {
            $this->runs->syncPhysicalState($lockedRun->id, OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE, [
                'progress_done' => $done,
                'progress_total' => $total,
                'error_code' => null,
                'safe_error' => null,
                'finished_at' => null,
                'phase_detail' => $phaseDetail ?? ['stage' => 'awaiting_capture', 'current_variant' => null],
            ]);

            return $lockedRun->fresh();
        }

        // Unstarted or unprinted methods remain actionable before any review terminal.
        $hasChallengeReady = $methods->contains(fn (OmrTemplateVerificationMethod $m): bool => in_array($m->method_state, [
            OmrTemplateVerificationMethod::STATE_NOT_STARTED,
            OmrTemplateVerificationMethod::STATE_CHALLENGE_READY,
        ], true));
        if ($hasChallengeReady) {
            $isAssetless = $lockedRun->challenges()->where('renderer_version', 'supplier_original')->exists()
                || ($lockedRun->draft_id !== null && OmrSourcePolicy::sourceKindForDraft($lockedRun->draft) === OmrSourcePolicy::UPLOADED_ORIGINAL)
                || ($lockedRun->template_version_id !== null && (($lockedRun->templateVersion?->authoring_manifest['source_kind'] ?? null) === OmrSourcePolicy::UPLOADED_ORIGINAL));
            $status = $isAssetless ? OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE : OmrTemplateVerificationRun::STATUS_AWAITING_PRINT;
            $this->runs->syncPhysicalState($lockedRun->id, $status, [
                'progress_done' => $done,
                'progress_total' => $total,
                'error_code' => null,
                'safe_error' => null,
                'finished_at' => null,
                'phase_detail' => $phaseDetail ?? ['stage' => $status, 'current_variant' => null],
            ]);

            return $lockedRun->fresh();
        }

        // Review applies only after every sibling method has crossed the print boundary.
        $hasNeedsReview = $methods->contains(fn (OmrTemplateVerificationMethod $m): bool => $m->method_state === OmrTemplateVerificationMethod::STATE_NEEDS_REVIEW)
            || $allPages->contains(fn (OmrTemplateVerificationMethodPage $p): bool => $p->page_state === OmrTemplateVerificationMethodPage::STATE_NEEDS_REVIEW);
        if ($hasNeedsReview) {
            $this->runs->syncPhysicalState($lockedRun->id, OmrTemplateVerificationRun::STATUS_NEEDS_REVIEW, [
                'progress_done' => $done,
                'progress_total' => $total,
                'error_code' => null,
                'safe_error' => null,
                'finished_at' => null,
                'phase_detail' => [
                    ...($phaseDetail ?? []),
                    'phase_code' => OmrTemplateVerificationRun::PHASE_AWAITING_REVIEW,
                    'stage' => OmrTemplateVerificationRun::PHASE_AWAITING_REVIEW,
                    'current_variant' => null,
                ],
            ]);

            return $lockedRun->fresh();
        }

        // Default fallback: awaiting_capture
        $this->runs->syncPhysicalState($lockedRun->id, OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE, [
            'progress_done' => $done,
            'progress_total' => $total,
            'finished_at' => null,
            'phase_detail' => $phaseDetail ?? ['stage' => 'awaiting_capture', 'current_variant' => null],
        ]);

        return $lockedRun->fresh();
    }

    public function isRunWideFailure(OmrTemplateVerificationRun $run): bool
    {
        return $run->status === OmrTemplateVerificationRun::STATUS_FAILED
            && $run->error_code !== null
            && in_array($run->error_code, self::RUN_WIDE_FAILURE_CODES, true);
    }

    private function observationReviewCount(OmrDiagnosticEvidence $evidence): int
    {
        $hasExpected = false;
        foreach ($evidence->items as $item) {
            if (is_array($item)
                && is_scalar($item['expected'] ?? null)
                && trim((string) $item['expected']) !== '') {
                $hasExpected = true;
                break;
            }
        }

        $count = 0;
        foreach ($evidence->items as $item) {
            if ($hasExpected
                && (! is_array($item)
                    || ! is_scalar($item['expected'] ?? null)
                    || trim((string) $item['expected']) === '')) {
                continue;
            }
            if (! is_array($item) || ! is_array($item['detected'] ?? null)) {
                $count++;

                continue;
            }
            $state = (string) ($item['detected']['state'] ?? '');
            if (in_array($state, [OmrDetectedItem::STATE_AMBIGUOUS, OmrDetectedItem::STATE_UNREADABLE], true)
                || (($item['expected'] ?? null) === null && $state !== OmrDetectedItem::STATE_BLANK)
                || (($item['expected'] ?? null) !== null && ($item['exact'] ?? null) !== true)) {
                $count++;
            }
        }

        return $count;
    }

    private function gateFailure(
        ?OmrRegistrationMode $registrationMode,
        ?array $gates,
        OmrPreparedCaptureEvidence $prepared,
        OmrDiagnosticEvidence $evidence,
        OmrTemplateVerificationRun $run,
        OmrTemplateVerificationMethod $method,
        OmrTemplateVerificationMethodPage $page,
        OmrTemplateVerificationCapture $capture,
    ): ?string {
        if ($capture->isDiscarded()) {
            return 'capture_discarded';
        }
        if (! is_array($gates)) {
            return 'gate_evidence_missing';
        }
        if ($registrationMode === OmrRegistrationMode::DocumentBound) {
            $requiredGates = [
                'marker_count' => 'not_applicable',
                'registration' => 'pass',
                'document' => 'pass',
                'content' => 'pass',
                'dimensions' => 'pass',
                'shifts' => 'pass',
                'rectification_consistency' => 'pass',
            ];
            foreach ($requiredGates as $name => $requiredStatus) {
                if (($gates[$name] ?? null) !== $requiredStatus) {
                    return $name === 'dimensions' ? 'dimensions_mismatch' : $name.'_gate_failed';
                }
            }
            if (! array_key_exists('unsafe_blocks', $gates) || $gates['unsafe_blocks'] !== []) {
                return 'unsafe_blocks_gate_failed';
            }
            foreach (array_keys($gates) as $name) {
                if (! array_key_exists($name, $requiredGates) && $name !== 'unsafe_blocks') {
                    return 'unexpected_gate';
                }
            }

            return $this->documentBoundBindingFailure($prepared, $evidence, $run, $method, $page, $capture, $gates);
        }
        if ($registrationMode !== OmrRegistrationMode::FiducialAnchored) {
            return 'registration_mode_invalid';
        }
        foreach (['marker_count', 'registration', 'dimensions', 'shifts'] as $name) {
            if (($gates[$name] ?? null) !== 'pass') {
                return $name === 'dimensions' ? 'dimensions_mismatch' : $name.'_gate_failed';
            }
        }
        foreach ($gates as $name => $status) {
            if ($name === 'unsafe_blocks') {
                if ($status !== []) {
                    return 'unsafe_blocks_gate_failed';
                }

                continue;
            }
            if ($status !== 'pass') {
                return $name === 'dimensions' ? 'dimensions_mismatch' : $name.'_gate_failed';
            }
        }

        return null;
    }

    private function documentBoundBindingFailure(
        OmrPreparedCaptureEvidence $prepared,
        OmrDiagnosticEvidence $evidence,
        OmrTemplateVerificationRun $run,
        OmrTemplateVerificationMethod $method,
        OmrTemplateVerificationMethodPage $page,
        OmrTemplateVerificationCapture $capture,
        array $gates,
    ): ?string {
        $diagnostic = $prepared->diagnosticEvidence;
        $request = $prepared->detectionRequest;
        $success = $prepared->providerResult?->success;
        if ($diagnostic === null) {
            return 'diagnostic_evidence_missing';
        }
        if ($request === null) {
            return 'detection_request_missing';
        }
        if ($success === null) {
            return 'worker_success_missing';
        }
        if ($prepared->layout === null || ! $prepared->layout->succeeded()) {
            return 'alignment_gate_failed';
        }
        foreach ([
            'diagnostic_outcome_failed' => $diagnostic->succeeded(),
            'evidence_outcome_failed' => $evidence->succeeded(),
            'prepared_gate_evidence_mismatch' => $prepared->gates === $gates,
            'diagnostic_gate_evidence_mismatch' => $diagnostic->gates === $gates,
            'gate_evidence_mismatch' => $evidence->gates === $gates,
            'diagnostic_target_mismatch' => $diagnostic->target === $prepared->target,
            'evidence_target_mismatch' => $evidence->target === $prepared->target,
            'diagnostic_page_mismatch' => $diagnostic->pageNo === $prepared->pageNo,
            'evidence_page_mismatch' => $evidence->pageNo === $prepared->pageNo,
        ] as $code => $matches) {
            if (! $matches) {
                return $code;
            }
        }
        $pageNo = (int) $page->page_no;
        $targetKind = $run->draft_id !== null
            ? OmrTemplateVerificationMethod::TARGET_CANDIDATE
            : OmrTemplateVerificationMethod::TARGET_COMPILED_VERSION;
        $targetId = (int) ($run->draft_id ?? $run->template_version_id);
        $target = $prepared->target;
        $targetHashes = is_array($target['hashes'] ?? null) ? $target['hashes'] : [];
        $compiledPages = $target['compiled_pages'] ?? null;
        $planHash = $prepared->hashes['plan'] ?? null;
        foreach ([
            'target_id_invalid' => $targetId > 0,
            'method_target_kind_mismatch' => $method->target_kind === $targetKind,
            'method_target_id_mismatch' => (int) $method->target_id === $targetId,
            'prepared_page_mismatch' => $prepared->pageNo === $pageNo,
            'capture_page_mismatch' => (int) $capture->page_no === $pageNo,
            'capture_input_hash_mismatch' => $prepared->inputHash === (string) $capture->input_hash,
            'expected_vector_hash_mismatch' => $prepared->expectedVectorHash === (string) $method->expected_vector_hash,
            'plan_hash_mismatch' => is_string($planHash) && hash_equals((string) $method->plan_hash, $planHash),
            'target_hash_mismatch' => is_string($targetHashes['artifact'] ?? null) && hash_equals((string) $method->target_hash, $targetHashes['artifact']),
            'target_registration_mode_mismatch' => ($target['registration_mode'] ?? null) === OmrRegistrationMode::DocumentBound->value,
            'target_kind_mismatch' => ($target['kind'] ?? null) === $targetKind,
            'target_id_mismatch' => (string) ($target['id'] ?? '') === (string) $targetId,
            'target_page_mismatch' => (int) ($target['page_no'] ?? 0) === $pageNo,
            'compiled_page_missing' => is_array($compiledPages) && in_array(['page_no' => $pageNo], $compiledPages, true),
            'request_registration_mode_mismatch' => $request->registrationMode === OmrRegistrationMode::DocumentBound,
            'request_page_mismatch' => $request->pageNo === $pageNo,
            'worker_registration_mode_mismatch' => $success->registrationMode === OmrRegistrationMode::DocumentBound->value,
            'worker_page_mismatch' => $success->expectedPageNo === $pageNo,
        ] as $code => $matches) {
            if (! $matches) {
                return $code;
            }
        }
        $workerFailure = $this->workerBindingFailure($request, $success);
        if ($workerFailure !== null) {
            return $workerFailure;
        }
        if (! hash_equals((string) $evidence->planHash, (string) $method->plan_hash)) {
            return 'evidence_plan_hash_mismatch';
        }
        if ($targetKind === OmrTemplateVerificationMethod::TARGET_COMPILED_VERSION) {
            if ($request->templateVersionId !== $targetId
                || $request->candidateTargetId !== null
                || $success->templateVersionId !== $targetId
                || $success->candidateTargetId !== null) {
                return 'compiled_version_binding_mismatch';
            }
        } elseif ($request->templateVersionId !== null
            || ! is_string($request->candidateTargetId)
            || $success->templateVersionId !== null
            || $success->candidateTargetId !== $request->candidateTargetId) {
            return 'candidate_binding_mismatch';
        }
        if (! OmrDocumentRectification::sameSelectedWarp($prepared->layout->rectification, $success->rectification)) {
            return 'alignment_gate_failed';
        }

        return null;
    }

    private function workerBindingFailure(OmrDetectionRequest $request, OmrDetectionSuccess $success): ?string
    {
        $reqImage = $request->sourceImage->toArray();
        $sourceImage = $success->sourceImage;
        if (! is_array($sourceImage) || count($sourceImage) !== 4) {
            return 'source_image_shape_invalid';
        }
        foreach ([
            'dimensions_mismatch' => ($sourceImage['width'] ?? null) === $reqImage['width'] && ($sourceImage['height'] ?? null) === $reqImage['height'],
            'source_image_orientation_mismatch' => ($sourceImage['orientation'] ?? null) === $reqImage['orientation'],
            'source_image_hash_mismatch' => ($sourceImage['input_hash'] ?? null) === $reqImage['input_hash'],
            'response_envelope_version_mismatch' => $success->responseEnvelopeVersion === $request->envelopeVersion,
            'worker_input_hash_mismatch' => $success->inputHash === $request->inputHash,
            'config_hash_mismatch' => $success->configHash === $request->configHash,
            'worker_run_id_mismatch' => $success->runId === $request->runId,
            'correlation_id_mismatch' => $success->correlationId === $request->correlationId,
            'booklet_hash_mismatch' => $success->bookletHash === $request->bookletHash,
            'native_bundle_hash_mismatch' => $success->nativeBundleHash === $request->nativeBundleHash,
            'manifest_hash_mismatch' => $success->manifestHash === $request->manifestHash,
            'patch_identity_mismatch' => $success->patchIdentity === $request->patchIdentity,
            'engine_name_mismatch' => $success->engineName === $request->engineName,
            'engine_commit_mismatch' => $success->engineCommit === $request->engineCommit,
            'worker_registration_mode_mismatch' => $success->registrationMode === $request->registrationMode->value,
            'template_version_id_mismatch' => $success->templateVersionId === $request->templateVersionId,
            'candidate_target_id_mismatch' => $success->candidateTargetId === $request->candidateTargetId,
            'worker_page_mismatch' => $success->expectedPageNo === $request->pageNo,
        ] as $code => $matches) {
            if (! $matches) {
                return $code;
            }
        }

        return null;
    }

    private function registrationModeForRun(OmrTemplateVerificationRun $run): ?OmrRegistrationMode
    {
        if ($run->draft_id !== null) {
            $mode = OmrTemplateAuthoringDraft::query()->find($run->draft_id)?->registration_mode;
        } elseif ($run->template_version_id !== null) {
            $mode = OmrAnswerSheetTemplateVersion::query()->find($run->template_version_id)?->registration_mode;
        } else {
            return null;
        }

        return $mode instanceof OmrRegistrationMode ? $mode : null;
    }

    private function persistScenarioOutcomes(
        OmrPhysicalChallenge $challenge,
        OmrTemplateVerificationCapture $capture,
        OmrPreparedCaptureEvidence $prepared,
    ): void {
        $manifest = $challenge->scenario_manifest;
        if (! is_array($manifest) || ! is_array($manifest['scenarios'] ?? null)) {
            return;
        }

        $items = [];
        foreach ($prepared->items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $label = $item['native_label'] ?? $item['item_id'] ?? $item['label'] ?? null;
            if (is_scalar($label) && (string) $label !== '') {
                $items[(string) $label] = $item;
            }
        }

        $outcomes = is_array($challenge->scenario_outcomes) ? $challenge->scenario_outcomes : [];
        foreach ($manifest['scenarios'] as $scenario) {
            if (! is_array($scenario)) {
                continue;
            }
            $scenarioId = $scenario['id'] ?? null;
            $label = $scenario['item']['label'] ?? null;
            if (! is_string($scenarioId) || $scenarioId === '' || ! is_string($label) || ! isset($items[$label])) {
                continue;
            }
            $item = $items[$label];
            $detected = is_array($item['detected'] ?? null) ? $item['detected'] : [];
            $machineState = (string) ($detected['state'] ?? $item['state'] ?? 'unreadable');
            $exact = $item['exact'] ?? null;
            $outcomes[$scenarioId] = [
                'status' => in_array($machineState, ['ambiguous', 'unreadable'], true)
                    ? 'review_required'
                    : ($exact === false ? 'failed' : 'passed'),
                'machine_state' => $machineState,
                'selected_option' => $detected['selected_option'] ?? $item['selected_option'] ?? null,
                'observed_options' => array_values((array) ($detected['observed_options'] ?? $item['observed_options'] ?? [])),
                'capture_id' => (int) $capture->id,
                'input_hash' => (string) $capture->input_hash,
                'evidence_hash' => $prepared->evidenceHash(),
            ];
        }

        OmrPhysicalChallenge::allowDiagnosticPersistence(static function () use ($challenge, $outcomes): void {
            $challenge->forceFill(['scenario_outcomes' => $outcomes])->save();
        });
    }

    private function detectedVectorHash(OmrDiagnosticEvidence $evidence): string
    {
        $detected = [];
        foreach ($evidence->items as $item) {
            if (! is_array($item) || ! isset($item['native_label'])) {
                continue;
            }
            $detectedValue = $item['detected']['selected_option'] ?? $item['detected'] ?? '';
            $detected[(string) $item['native_label']] = is_string($detectedValue) ? $detectedValue : '';
        }
        ksort($detected);

        return hash('sha256', json_encode($detected, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function persistItemEvidence(OmrTemplateVerificationCapture $capture, OmrPreparedCaptureEvidence $prepared): void
    {
        $areaPositions = [];
        foreach ($prepared->items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $areaKey = $item['area_key'] ?? $item['area'] ?? $item['area_code'] ?? null;
            if (! is_scalar($areaKey) || (string) $areaKey === '') {
                continue;
            }
            $areaKey = strtolower((string) $areaKey);
            if (! isset($areaPositions[$areaKey])) {
                $areaPositions[$areaKey] = count($areaPositions) + 1;
            }
        }

        $rows = [];
        foreach ($prepared->items as $index => $item) {
            if (is_array($item)) {
                $rows[] = $this->evidenceRow($item, (int) $index, $areaPositions);
            }
        }
        foreach ($prepared->identity as $index => $identity) {
            if (! is_array($identity)) {
                continue;
            }
            $position = (int) ($identity['position'] ?? $index + 1);
            $identityItem = [
                ...$identity,
                'item_id' => 'APPLICANT_NUMBER_'.$position,
                'expected' => $identity['expected_digit'] ?? null,
                'detected' => $identity['detected_value'] ?? null,
                'exact' => $identity['exact'] ?? null,
                'options' => is_array($identity['options'] ?? null)
                    ? $identity['options']
                    : array_map(static fn (int $digit): array => ['code' => (string) $digit], range(0, 9)),
                'means' => ['confidence' => $identity['confidence'] ?? null],
            ];
            $rows[] = $this->evidenceRow($identityItem, (int) $index, $areaPositions, OmrTemplateVerificationItemEvidence::GROUP_IDENTITY);
        }

        $existing = $capture->evidenceItems()
            ->get(['item_id', 'evidence_version', 'evidence_hash'])
            ->keyBy('item_id');
        $inserts = [];
        $timestamp = now();
        foreach ($rows as $row) {
            $itemId = (string) $row['item_id'];
            $prior = $existing->get($itemId);
            if ($prior !== null) {
                if ((string) $prior->evidence_version !== (string) $row['evidence_version']
                    || ! hash_equals((string) $prior->evidence_hash, (string) $row['evidence_hash'])) {
                    throw new RuntimeException('verification_item_evidence_idempotency_conflict');
                }

                continue;
            }
            $insert = [
                ...$row,
                'capture_id' => (int) $capture->id,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
            foreach (['options', 'means', 'crop_rect'] as $jsonField) {
                if (is_array($insert[$jsonField] ?? null)) {
                    $insert[$jsonField] = json_encode($insert[$jsonField], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                }
            }
            $inserts[] = $insert;
            $existing->put($itemId, (object) [
                'evidence_version' => $row['evidence_version'],
                'evidence_hash' => $row['evidence_hash'],
            ]);
        }

        if ($inserts !== []) {
            DB::table('omr_template_verification_item_evidence')->insert($inserts);
        }
    }

    /**
     * @param  array<string,mixed>  $item
     * @param  array<string,int>  $areaPositions
     * @return array<string,mixed>
     */
    private function evidenceRow(
        array $item,
        int $index,
        array $areaPositions,
        ?string $groupOverride = null,
    ): array {
        $itemId = $item['item_id'] ?? $item['native_label'] ?? 'item-'.$index;
        $itemId = is_scalar($itemId) ? trim((string) $itemId) : '';
        if ($itemId === '') {
            $itemId = 'item-'.$index;
        }
        $group = $groupOverride ?? (str_starts_with($itemId, 'APPLICANT_NUMBER_')
            ? OmrTemplateVerificationItemEvidence::GROUP_IDENTITY
            : (str_starts_with($itemId, 'CALIBRATION_')
                ? OmrTemplateVerificationItemEvidence::GROUP_CALIBRATION
                : (string) ($item['group_type'] ?? OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA)));
        if (! in_array($group, OmrTemplateVerificationItemEvidence::GROUPS, true)) {
            $group = OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA;
        }
        $areaKey = $item['area_key'] ?? $item['area'] ?? $item['area_code'] ?? null;
        $areaKey = is_scalar($areaKey) && (string) $areaKey !== '' ? (string) $areaKey : null;
        $areaPosition = $item['area_position'] ?? null;
        if ($group === OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA && $areaPosition === null && $areaKey !== null) {
            $areaPosition = $areaPositions[strtolower($areaKey)] ?? null;
        }
        $areaPosition = is_numeric($areaPosition) && (int) $areaPosition > 0 ? (int) $areaPosition : null;
        $questionNo = $item['question_no'] ?? null;
        $questionNo = is_numeric($questionNo) && (int) $questionNo > 0 ? (int) $questionNo : null;
        $detected = $item['detected'] ?? $item['detected_answer'] ?? null;
        $detected = is_array($detected) ? ($detected['selected_option'] ?? $detected['value'] ?? null) : $detected;
        $detected = is_scalar($detected) && (string) $detected !== '' ? (string) $detected : null;
        $expected = $item['expected'] ?? $item['expected_digit'] ?? null;
        $expected = is_scalar($expected) && (string) $expected !== '' ? (string) $expected : null;
        $rawState = $item['state'] ?? (is_array($item['detected'] ?? null) ? ($item['detected']['state'] ?? null) : null);
        $rawState = strtolower(is_scalar($rawState) ? (string) $rawState : '');
        $state = match ($rawState) {
            'match' => OmrTemplateVerificationItemEvidence::STATE_MATCH,
            'mismatch', 'wrong' => OmrTemplateVerificationItemEvidence::STATE_MISMATCH,
            'ambiguous', 'unreadable' => OmrTemplateVerificationItemEvidence::STATE_AMBIGUOUS,
            'missing', 'blank' => OmrTemplateVerificationItemEvidence::STATE_MISSING,
            default => ($item['exact'] ?? false) === true
                ? OmrTemplateVerificationItemEvidence::STATE_MATCH
                : ($detected === null ? OmrTemplateVerificationItemEvidence::STATE_MISSING : OmrTemplateVerificationItemEvidence::STATE_MISMATCH),
        };
        if (($item['exact'] ?? null) === true) {
            $state = OmrTemplateVerificationItemEvidence::STATE_MATCH;
        }
        $options = is_array($item['options'] ?? null) ? $this->safeEvidenceValue($item['options']) : null;
        $means = is_array($item['means'] ?? null) ? $this->safeEvidenceValue($item['means']) : null;
        if ($means === null && is_array($options)) {
            $means = [];
            foreach ($options as $option) {
                if (is_array($option) && isset($option['code']) && is_numeric($option['mean'] ?? null)) {
                    $means[(string) $option['code']] = (float) $option['mean'];
                }
            }
            $means = $means === [] ? null : $means;
        }
        $row = [
            'group_type' => $group,
            'area_key' => $group === OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA ? $areaKey : null,
            'area_position' => $group === OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA ? $areaPosition : null,
            'question_no' => $questionNo,
            'item_id' => $itemId,
            'options' => $options,
            'expected' => $expected,
            'detected' => $detected,
            'state' => $state,
            'means' => $means,
            'delta' => is_numeric($item['delta'] ?? null) ? (float) $item['delta'] : (is_numeric($item['threshold_delta'] ?? null) ? (float) $item['threshold_delta'] : null),
            'status' => $state,
            'crop_asset_id' => is_numeric($item['crop_asset_id'] ?? null) && (int) $item['crop_asset_id'] > 0 ? (int) $item['crop_asset_id'] : null,
            'crop_asset_hash' => is_scalar($item['crop_asset_hash'] ?? null) ? (string) $item['crop_asset_hash'] : null,
            'crop_rect' => is_array($item['crop_rect'] ?? null) ? $this->safeEvidenceValue($item['crop_rect']) : (is_array($item['region'] ?? null) ? $this->safeEvidenceValue($item['region']) : null),
            'evidence_version' => 'v1',
            'row_version' => 0,
        ];
        $row['evidence_hash'] = hash('sha256', json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $row;
    }

    private function safeEvidenceValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return is_scalar($value) || $value === null ? $value : null;
        }
        $safe = [];
        foreach ($value as $key => $nested) {
            $name = strtolower((string) $key);
            if ($name === 'bytes'
                || str_contains($name, 'base64')
                || str_contains($name, 'bytes')
                || str_starts_with($name, 'provider')
                || str_contains($name, 'applicant')
                || str_contains($name, 'score')
                || str_contains($name, 'answer_key')
                || str_contains($name, 'correct_')
                || str_contains($name, 'secret')
                || str_contains($name, 'token')
                || $name === 'authorization') {
                continue;
            }
            $safe[$key] = is_array($nested) ? $this->safeEvidenceValue($nested) : (is_scalar($nested) || $nested === null ? $nested : null);
        }

        return $safe;
    }

    private function analysisFailurePhase(OmrPreparedCaptureEvidence $prepared): string
    {
        if ($prepared->layout === null || in_array($prepared->code, ['orientation_mismatch', 'rotation_failed'], true)) {
            return OmrTemplateVerificationRun::PHASE_ORIENTATION;
        }
        if (! $prepared->layout->succeeded() || in_array($prepared->code, [
            'dimension_mismatch',
            'marker_mismatch',
            'marker_missing',
            'registration_failed',
            'sheet_misaligned',
            'sheet_partial',
        ], true)) {
            return OmrTemplateVerificationRun::PHASE_LAYOUT_VERIFICATION;
        }

        return OmrTemplateVerificationRun::PHASE_RECOGNITION;
    }

    private function currentAnalysisPhase(OmrTemplateVerificationRun $run): ?string
    {
        $phaseCode = is_array($run->phase_detail) ? ($run->phase_detail['phase_code'] ?? null) : null;

        return in_array($phaseCode, [
            OmrTemplateVerificationRun::PHASE_QUEUED,
            OmrTemplateVerificationRun::PHASE_PREFLIGHT,
            OmrTemplateVerificationRun::PHASE_ORIENTATION,
            OmrTemplateVerificationRun::PHASE_LAYOUT_VERIFICATION,
            OmrTemplateVerificationRun::PHASE_RECOGNITION,
            OmrTemplateVerificationRun::PHASE_EVIDENCE_PERSISTENCE,
        ], true) ? $phaseCode : null;
    }

    /** @param array<string,mixed> $payload */
    private function audit(
        string $action,
        User $actor,
        OmrTemplateVerificationRun $run,
        ?string $method,
        ?int $page,
        ?int $attempt,
        ?string $reason,
        array $payload = [],
    ): void {
        $this->recordAuditEvent->handle($action, [
            'run_id' => (int) $run->id,
            'method' => $method,
            'page_no' => $page,
            'attempt' => $attempt,
            'reason' => $reason,
            ...$payload,
        ], (string) $actor->id);
    }

    /** @return array{draft:OmrTemplateAuthoringDraft|null,version:OmrAnswerSheetTemplateVersion|null} */
    private function assetOwner(OmrTemplateVerificationRun $run): array
    {
        if ($run->draft_id !== null) {
            return ['draft' => OmrTemplateAuthoringDraft::query()->findOrFail($run->draft_id), 'version' => null];
        }
        if ($run->template_version_id !== null) {
            return ['draft' => null, 'version' => OmrAnswerSheetTemplateVersion::query()->findOrFail($run->template_version_id)];
        }
        throw new RuntimeException('verification_target_missing');
    }

    /** @param array{draft:OmrTemplateAuthoringDraft|null,version:OmrAnswerSheetTemplateVersion|null} $owner */
    private function putOwnedAsset(array $owner, string $kind, string $bytes, string $mime, ?int $pageNo, array $metadata): OmrTemplateAsset
    {
        if ($owner['draft'] instanceof OmrTemplateAuthoringDraft) {
            return $this->assets->putDraftAsset($owner['draft'], $kind, $bytes, $mime, $pageNo, $metadata);
        }
        if ($owner['version'] instanceof OmrAnswerSheetTemplateVersion) {
            return $this->assets->putVersionAsset($owner['version'], $kind, $bytes, $mime, $pageNo, $metadata);
        }
        throw new RuntimeException('verification_asset_owner_missing');
    }
}
