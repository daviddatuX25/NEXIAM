<?php

declare(strict_types=1);

namespace App\Assessment\Actions\Omr\Operational;

use App\Assessment\Actions\Omr\Camera\OmrCameraFrameFinalization;
use App\Assessment\Omr\Operational\OmrDiagnosticEffectiveAnswerProjector;
use App\Assessment\Support\TestKitCurrentEvidence;
use App\Assessment\Support\TestKitMockSession;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

use function count;
use function hash;
use function hash_equals;
use function is_array;
use function json_decode;
use function json_encode;
use function trim;

/**
 * Completes a diagnostic run after all current effective answers and
 * comparison projections pass their review gates. This action writes only the
 * diagnostic run and its diagnostic completion receipt/outcome.
 *
 * Diagnostic completion records execution state only; completion status alone
 * never implies test kit bundle acceptance or software acceptance receipts.
 */
final class CompleteOmrDiagnosticRun
{
    public const COMMAND_NAME = 'omr.diagnostic.complete';

    public const AUDIT_EVENT = 'omr.diagnostic.completed';

    private const RECEIPT_LEASE_MINUTES = 5;

    private const AGGREGATE_TYPE = 'omr_diagnostic_run';

    private const OUTCOME_SCHEMA = 'nexiam.omr.diagnostic-outcome.v1';

    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly OmrDiagnosticEffectiveAnswerProjector $projector,
        private readonly OmrCameraFrameFinalization $cameraFrameFinalization,
        private readonly ?TestKitCurrentEvidence $currentEvidence = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(
        OmrDiagnosticRun $run,
        User $actor,
        int $expectedRevision,
        string $idempotencyKey,
    ): array {
        $idempotencyKey = trim($idempotencyKey);
        if ($expectedRevision < 1) {
            throw new RuntimeException('omr_diagnostic_run_revision_invalid');
        }
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 255) {
            throw new RuntimeException('omr_diagnostic_complete_idempotency_key_invalid');
        }

        $this->assertDiagnosticKind($run);
        $this->assertAuthorized($actor, $run);
        $payloadFingerprint = hash('sha256', json_encode([
            'run_id' => (int) $run->getKey(),
            'expected_revision' => $expectedRevision,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return DB::transaction(function () use (
            $run,
            $actor,
            $expectedRevision,
            $idempotencyKey,
            $payloadFingerprint,
        ): array {
            $lockedRun = OmrDiagnosticRun::query()->lockForUpdate()->findOrFail((int) $run->getKey());
            $this->assertDiagnosticKind($lockedRun);
            $this->assertAuthorized($actor, $lockedRun);
            if ($lockedRun->status === OmrDiagnosticRun::STATUS_CANCELLED) {
                throw new RuntimeException('omr_diagnostic_run_not_completable');
            }

            $receipt = $this->lockReceipt($actor, (int) $lockedRun->getKey(), $idempotencyKey);
            if ($receipt !== null) {
                $this->assertReceiptFingerprint($receipt, $payloadFingerprint);
                if ((string) $receipt->status === 'succeeded') {
                    return $this->resultFromReceipt($receipt, $lockedRun);
                }
                if ((string) $receipt->status === 'started'
                    && $receipt->lease_expires_at !== null
                    && strtotime((string) $receipt->lease_expires_at) > time()) {
                    throw new RuntimeException('omr_diagnostic_complete_idempotency_in_progress');
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
                    'aggregate_id' => (string) $lockedRun->getKey(),
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

            if ($lockedRun->isTerminal()) {
                throw new RuntimeException('omr_diagnostic_run_not_completable');
            }
            if ((int) $lockedRun->revision !== $expectedRevision) {
                throw new RuntimeException('omr_diagnostic_run_revision_stale');
            }
            if ($this->cameraFrameFinalization->hasUnresolvedCaptureOperation(
                $actor,
                'diagnostic:'.$lockedRun->public_id,
            )) {
                throw new RuntimeException('omr_diagnostic_capture_outcome_unknown');
            }
            $hasInFlightCapture = OmrDiagnosticCapture::query()
                ->where('diagnostic_run_id', (int) $lockedRun->getKey())
                ->whereIn('status', [
                    OmrDiagnosticCapture::STATUS_RECEIVED,
                    OmrDiagnosticCapture::STATUS_QUEUED,
                    OmrDiagnosticCapture::STATUS_PROCESSING,
                ])
                ->exists();
            if ($hasInFlightCapture) {
                throw new RuntimeException('omr_diagnostic_capture_active');
            }
            $isMockRehearsal = (string) $lockedRun->diagnostic_purpose === 'mock_rehearsal';
            if ($isMockRehearsal) {
                $summary = app(TestKitMockSession::class)->summarize($lockedRun);
                if ((int) ($summary['completed_instance_count'] ?? 0) !== (int) ($summary['expected_instance_count'] ?? -1)
                    || (int) ($summary['captured_page_count'] ?? 0) !== (int) ($summary['expected_page_count'] ?? -1)) {
                    throw new RuntimeException('omr_diagnostic_run_evidence_incomplete');
                }
                foreach ((array) ($summary['instances'] ?? []) as $instance) {
                    if (! is_array($instance) || ($instance['complete'] ?? false) !== true) {
                        throw new RuntimeException('omr_diagnostic_run_evidence_incomplete');
                    }
                }
                $selectedEvidence = null;
                $captures = collect();
            } else {
                $currentEvidenceService = $this->currentEvidence ?? app(TestKitCurrentEvidence::class);
                $selectedEvidence = null;
                if ($lockedRun->test_kit_version_id !== null) {
                    $selectedEvidence = $currentEvidenceService->forRun($lockedRun, true);
                    if (! ($selectedEvidence['complete'] ?? false) || ($selectedEvidence['errors'] ?? []) !== []) {
                        throw new RuntimeException('omr_diagnostic_run_evidence_incomplete');
                    }
                    $captures = $selectedEvidence['captures'];
                } else {
                    $captures = OmrDiagnosticCapture::query()
                        ->where('diagnostic_run_id', (int) $lockedRun->getKey())
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get()
                        ->groupBy('input_asset_id')
                        ->map->last()
                        ->sortBy('id')
                        ->values();
                }
            }
            $effectiveByCapture = [];
            $comparisonByCapture = [];
            if (! $isMockRehearsal) {
                if (count($captures) === 0) {
                    throw new RuntimeException('omr_diagnostic_capture_missing');
                }
                if ($captures->contains(static fn (OmrDiagnosticCapture $capture): bool => in_array(
                    $capture->status,
                    [
                        OmrDiagnosticCapture::STATUS_RECEIVED,
                        OmrDiagnosticCapture::STATUS_QUEUED,
                        OmrDiagnosticCapture::STATUS_PROCESSING,
                    ],
                    true,
                ))) {
                    throw new RuntimeException('omr_diagnostic_capture_active');
                }
                if ($captures->contains(static fn (OmrDiagnosticCapture $capture): bool => ! in_array(
                    $capture->status,
                    [
                        OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
                        OmrDiagnosticCapture::STATUS_REVIEWED,
                        OmrDiagnosticCapture::STATUS_COMPLETED,
                    ],
                    true,
                ))) {
                    throw new RuntimeException('omr_diagnostic_capture_not_completable');
                }
                foreach ($captures as $capture) {
                    $effective = $this->projector->project($capture);
                    $capture->refresh();
                    $this->assertReviewGate($capture, $effective, (int) $lockedRun->revision);
                    $effectiveByCapture[(int) $capture->getKey()] = $effective;
                    $storedScore = is_array($capture->projected_score_json) ? $capture->projected_score_json : [];
                    $comparisonByCapture[(int) $capture->getKey()] = is_array($storedScore['comparison'] ?? null)
                        ? $storedScore['comparison']
                        : [];
                }
            }
            $passed = $isMockRehearsal || ! in_array(false, array_map(
                static fn (array $comparison): bool => ($comparison['passed'] ?? false) === true,
                $comparisonByCapture,
            ), true);
            $nextRevision = (int) $lockedRun->revision + 1;
            $diagnosticOutcome = [
                'schema_version' => self::OUTCOME_SCHEMA,
                'diagnostic_purpose' => (string) $lockedRun->diagnostic_purpose,
                'official' => false,
                'run_id' => (int) $lockedRun->getKey(),
                'run_revision' => $nextRevision,
                'review_complete' => true,
                'comparison_passed' => $passed,
                'capture_count' => $isMockRehearsal ? (int) ($summary['captured_page_count'] ?? 0) : count($captures),
                'captures' => $isMockRehearsal ? [] : array_map(
                    static fn (int $captureId): array => [
                        'capture_id' => $captureId,
                        'effective' => $effectiveByCapture[$captureId],
                        'comparison' => $comparisonByCapture[$captureId],
                    ],
                    array_keys($effectiveByCapture),
                ),
                'mock_summary' => $isMockRehearsal ? $summary : null,
            ];

            $runAttributes = [
                'status' => OmrDiagnosticRun::STATUS_COMPLETED,
                'phase' => OmrDiagnosticRun::PHASE_COMPLETE,
                'revision' => $nextRevision,
                'completed_at' => now(),
            ];

            if ($lockedRun->test_kit_version_id !== null && $selectedEvidence !== null) {
                $vector = $selectedEvidence['evidence_vector'];
                $vector['run_revision'] = $nextRevision;
                $sealedHash = $currentEvidenceService->computeVectorHash($vector);

                $runAttributes['sealed_evidence_revision'] = $nextRevision;
                $runAttributes['sealed_evidence_vector_sha256'] = $sealedHash;
                $runAttributes['sealed_at'] = now();
            }

            $lockedRun->forceFill($runAttributes)->saveOrFail();

            $result = [
                'outcome' => 'succeeded',
                'run_id' => (int) $lockedRun->getKey(),
                'run_revision' => $nextRevision,
                'status' => OmrDiagnosticRun::STATUS_COMPLETED,
                'phase' => OmrDiagnosticRun::PHASE_COMPLETE,
                'diagnostic_purpose' => (string) $lockedRun->diagnostic_purpose,
                'official' => false,
                'safe_outcome' => $isMockRehearsal ? $summary : null,
                'passed' => $passed,
                'captures' => $isMockRehearsal ? [] : $comparisonByCapture,
                'diagnostic_outcome' => $diagnosticOutcome,
                'receipt' => [
                    'command_name' => self::COMMAND_NAME,
                    'aggregate_type' => self::AGGREGATE_TYPE,
                    'aggregate_id' => (string) $lockedRun->getKey(),
                    'run_revision' => $nextRevision,
                    'correlation_id' => $correlationId,
                ],
            ];

            $this->recordAuditEvent->handle(
                self::AUDIT_EVENT,
                [
                    'run_id' => (int) $lockedRun->getKey(),
                    'capture_count' => count($captures),
                    'comparison_passed' => $passed,
                    'prior_run_revision' => $expectedRevision,
                    'run_revision' => $nextRevision,
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

    /**
     * @param  array<string, mixed>  $effective
     */
    private function assertReviewGate(
        OmrDiagnosticCapture $capture,
        array $effective,
        int $runRevision,
    ): void {
        if (($effective['is_complete'] ?? false) !== true
            || ($effective['has_unresolved'] ?? true) === true
            || ($effective['has_stale'] ?? true) === true) {
            throw new RuntimeException('omr_diagnostic_review_gate_blocked');
        }

        $projected = is_array($capture->projected_score_json)
            ? $capture->projected_score_json
            : [];
        $comparison = is_array($projected['comparison'] ?? null)
            ? $projected['comparison']
            : null;
        if ($comparison === null) {
            throw new RuntimeException('omr_diagnostic_comparison_required');
        }
        if ((int) ($projected['run_revision'] ?? -1) !== $runRevision) {
            throw new RuntimeException('omr_diagnostic_comparison_stale');
        }
    }

    private function assertDiagnosticKind(OmrDiagnosticRun $run): void
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
        if ($run->academicCycle !== null && trim((string) $run->academicCycle->code) !== '') {
            $scope['cycle'] = (string) $run->academicCycle->code;
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
            throw new RuntimeException('omr_diagnostic_complete_denied');
        }
    }

    private function lockReceipt(User $actor, int $runId, string $idempotencyKey): ?object
    {
        return DB::table('omr_command_receipts')
            ->where('actor_id', (int) $actor->getKey())
            ->where('command_name', self::COMMAND_NAME)
            ->where('aggregate_type', self::AGGREGATE_TYPE)
            ->where('aggregate_id', (string) $runId)
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();
    }

    private function assertReceiptFingerprint(object $receipt, string $payloadFingerprint): void
    {
        if (! hash_equals((string) $receipt->payload_fingerprint, $payloadFingerprint)) {
            throw new RuntimeException('omr_diagnostic_complete_idempotency_conflict');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resultFromReceipt(object $receipt, OmrDiagnosticRun $run): array
    {
        $result = json_decode((string) $receipt->safe_result_json, true);
        if (! is_array($result)
            || (int) ($result['run_id'] ?? 0) !== (int) $run->getKey()
            || ! is_array($result['diagnostic_outcome'] ?? null)) {
            throw new RuntimeException('omr_diagnostic_complete_receipt_invalid');
        }

        return $result;
    }
}
