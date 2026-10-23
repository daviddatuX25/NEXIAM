<?php

namespace App\Jobs\Assessment;

use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTargetException;
use App\Assessment\Omr\Verification\OmrVerificationEvidenceService;
use App\Assessment\Omr\Verification\OmrVerificationRunService;
use App\Assessment\Omr\Verification\OmrVerificationTargetLoader;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateVerificationRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

use function file_put_contents;
use function hash;
use function implode;
use function is_string;
use function json_encode;
use function preg_replace;
use function strtolower;
use function sys_get_temp_dir;
use function tempnam;

/**
 * One real no-go variant per short-lived OMR queue worker process.
 *
 * Only scalar run identity/cursor values are serialized. The run row owns the
 * current variant, attempt, optimistic version, and progress; duplicate or
 * out-of-order deliveries therefore become harmless no-ops.
 */
final class RunOmrTemplateVerificationVariantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 75;

    public function __construct(
        public readonly int $runId,
        public readonly int $attempt,
        public readonly string $variant,
        public readonly ?int $expectedVersion = null,
    ) {
        $this->onQueue('omr-verification');
    }

    public function handle(
        OmrTemplateAssetStore $assetStore,
        OmrNoGoMatrixRunner $matrix,
        OmrVerificationEvidenceService $evidence,
        OmrVerificationRunService $runs,
        OmrVerificationTargetLoader $targets,
        RecordAuditEvent $recordAuditEvent,
    ): void {
        $run = $runs->claimVariant($this->runId, $this->attempt, $this->variant, $this->expectedVersion);
        if ($run === null) {
            return;
        }

        $cleanPath = null;
        $createdAssets = [];
        try {
            $target = $targets->load($run);
            $plan = $target['plan'];
            $variantSpec = OmrNoGoMatrixRunner::parseVariantKey($this->variant, $plan->registrationMode);
            $baseVariant = $variantSpec['variant'];
            $pageNo = $variantSpec['page_no'];
            $proof = $this->loadProof($run, $pageNo, $plan->registrationMode->value, $plan->qrMode->value);
            $cleanPath = tempnam(sys_get_temp_dir(), 'nexiam-omr-variant-');
            if ($cleanPath === false) {
                throw new RuntimeException('verification_clean_source_write_failed');
            }
            $cleanBytes = Storage::disk($proof['disk'])->get($proof['path']);
            if (! is_string($cleanBytes) || hash('sha256', $cleanBytes) !== $proof['sha256']
                || file_put_contents($cleanPath, $cleanBytes) === false) {
                throw new RuntimeException('verification_clean_source_integrity_failed');
            }
            unset($cleanBytes);

            $draft = $target['draft'];
            $artifact = $target['artifact'];
            $plan = $target['plan'];
            $persistEvidence = $evidence->evidencePersister(
                $run,
                $draft,
                $assetStore,
                $run->proof_hash,
                $createdAssets,
                $pageNo,
                $this->variant,
            );
            $expectedPageVector = $plan->expectedVectorForPage($pageNo);
            $entry = $matrix->runVariant(
                $baseVariant,
                $cleanPath,
                $artifact,
                [
                    'answer_vector' => $expectedPageVector,
                    'registration_mode' => $proof['registration_mode'],
                    'qr_mode' => $proof['qr_mode'],
                    'paper_bounds' => $proof['paper_bounds'],
                    'pages' => [['paper_bounds' => $proof['paper_bounds']]],
                ],
                $persistEvidence,
                $pageNo,
                $plan->registrationMode,
            );
            $variantItems = (array) ($entry['detected_items'] ?? []);
            $variantVectorHash = hash('sha256', json_encode(
                OmrNoGoMatrixRunner::detectedVector($expectedPageVector, $variantItems),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            ));
            $variantVectorMatch = $entry['expected'] === 'pass'
                ? (($entry['answer_vector_match'] ?? false) === true)
                : null;
            $proofAssets = $proof['refs'];

            $completed = $runs->completeVariant(
                $run->id,
                $this->attempt,
                $this->variant,
                (int) $run->optimistic_version,
                function (OmrTemplateVerificationRun $lockedRun) use ($evidence, $entry, $proofAssets, $variantVectorHash, $variantVectorMatch, $pageNo): void {
                    $evidence->storeCapture($lockedRun, $this->variant, $pageNo, $entry, $proofAssets, $variantVectorHash, $variantVectorMatch);
                },
                $baseVariant === 'clean' && $pageNo === $plan->requiredPageCount
                    ? ['clean_vector_hash' => $plan->expectedVectorHash]
                    : [],
            );
            if ($completed === null) {
                $evidence->cleanupUnreferenced($createdAssets);

                return;
            }

            // Clean recovery has a distinct failure contract; preserve that
            // capture before closing the run and leave later variants absent.
            if ($baseVariant === 'clean' && ($entry['status'] ?? null) !== 'pass') {
                $code = (string) ($entry['error_code'] ?? 'clean_recovery_failed');
                $runs->fail($run->id, $code, 'The clean answer vector was not recovered exactly; completed evidence is preserved.', [
                    'phase_detail' => ['stage' => 'failed', 'current_variant' => 'clean', 'state' => 'terminal'],
                ]);
                $this->auditFailure($recordAuditEvent, $run, $code);

                return;
            }

            $phase = (array) $completed->phase_detail;
            if (($phase['state'] ?? null) === 'ready' && is_string($phase['current_variant'] ?? null)) {
                $nextVariant = (string) $phase['current_variant'];
                $next = $completed->fresh();
                unset($target, $proof, $draft, $artifact, $plan, $entry, $persistEvidence, $completed);
                RunOmrTemplateVerificationVariantJob::dispatch(
                    $next->id,
                    (int) $next->attempt,
                    $nextVariant,
                    (int) $next->optimistic_version,
                )->onQueue('omr-verification')->afterCommit();

                return;
            }
            if (($phase['state'] ?? null) !== 'finalizing') {
                return;
            }

            $latest = $completed->fresh();
            $summary = $evidence->resultSummary($latest, [
                'proof_hash' => $latest->proof_hash,
            ], (string) $latest->clean_vector_hash);
            $failedVariants = $latest->captures()
                ->where('attempt', $this->attempt)
                ->where('status', '!=', 'pass')
                ->pluck('variant')
                ->filter(static fn ($variant): bool => is_string($variant) && $variant !== '')
                ->values()
                ->all();
            if ($failedVariants !== []) {
                $runs->fail($latest->id, 'no_go_variant_failed', 'Variants failed the declared outcome: '.implode(', ', $failedVariants).'. Completed evidence is preserved.', [
                    'result_summary' => $summary,
                    'phase_detail' => ['stage' => 'failed', 'current_variant' => null, 'state' => 'terminal'],
                ]);
                $this->auditFailure($recordAuditEvent, $latest, 'no_go_variant_failed', ['failed_variants' => $failedVariants]);

                return;
            }

            $verificationProof = [
                'proof_hash' => $latest->proof_hash,
                'registration_mode' => $proof['registration_mode'],
                'qr_mode' => $proof['qr_mode'],
                'answer_vector' => $plan->expectedVector,
                'pages' => array_map(static function (array $page): array {
                    $proofPage = ['page_no' => $page['page_no'], 'sha256' => $page['sha256']];
                    if (is_array($page['paper_bounds'] ?? null)) {
                        $proofPage['paper_bounds'] = $page['paper_bounds'];
                    }

                    return $proofPage;
                }, $proof['pages']),
            ];
            if (! $evidence->promoteDraft($draft, $latest, $verificationProof, $summary, (string) $latest->clean_vector_hash)) {
                $runs->fail($latest->id, 'stale_target', 'The draft changed while verification was running; completed evidence is preserved and the run can be retried or a fresh run started.', [
                    'result_summary' => $summary,
                    'phase_detail' => ['stage' => 'failed', 'current_variant' => null, 'state' => 'terminal'],
                ]);
                $this->auditFailure($recordAuditEvent, $latest, 'stale_target');

                return;
            }

            if (! $runs->transition($latest->id, OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX, OmrTemplateVerificationRun::STATUS_PASSED, [
                'progress_done' => $latest->progress_total,
                'result_summary' => $summary,
                'phase_detail' => ['stage' => 'passed', 'current_variant' => null, 'state' => 'terminal'],
                'finished_at' => now(),
                'last_heartbeat_at' => now(),
            ])) {
                return;
            }
            $recordAuditEvent->handle('omr.verification.passed', [
                'run_id' => $latest->id,
                'attempt' => $latest->attempt,
                'plan_hash' => $latest->plan_hash,
                'matrix_hash' => $summary['matrix_hash'],
                'proof_hash' => $latest->proof_hash,
                'draft_id' => $draft->id,
            ], (string) $latest->started_by_user_id);
        } catch (OmrDiagnosticTargetException $exception) {
            $evidence->cleanupUnreferenced($createdAssets);
            $runs->fail($run->id, $exception->errorCode, $exception->getMessage(), [
                'phase_detail' => ['stage' => 'failed', 'current_variant' => $this->variant, 'state' => 'terminal'],
            ]);
            $this->auditFailure($recordAuditEvent, $run, $exception->errorCode);
        } catch (Throwable $exception) {
            $evidence->cleanupUnreferenced($createdAssets);
            $code = self::safeErrorCode($exception);
            $runs->fail($run->id, $code, 'The verification worker failed before finishing; completed evidence is preserved and the run can be retried.', [
                'phase_detail' => ['stage' => 'failed', 'current_variant' => $this->variant, 'state' => 'terminal'],
            ]);
            $this->auditFailure($recordAuditEvent, $run, $code);
            throw $exception;
        } finally {
            if ($cleanPath !== null) {
                @unlink($cleanPath);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        try {
            $run = OmrTemplateVerificationRun::query()->find($this->runId);
            if ($run === null || $run->isTerminal() || (int) $run->attempt !== $this->attempt) {
                return;
            }
            $code = self::safeErrorCode($exception);
            $detail = $code === 'worker_timeout'
                ? 'The verification worker timed out; completed evidence is preserved and the run can be retried.'
                : 'The verification worker failed before finishing; completed evidence is preserved and the run can be retried.';
            OmrVerificationRunService::make()->fail($run->id, $code, $detail, [
                'phase_detail' => ['stage' => 'failed', 'current_variant' => $this->variant, 'state' => 'terminal'],
            ]);
        } catch (Throwable) {
            // A failed finalizer must not mask the original worker failure.
        }
    }

    /**
     * @return array{
     *   disk:string,
     *   path:string,
     *   sha256:string,
     *   registration_mode:string,
     *   qr_mode:string,
     *   paper_bounds:?array{x:int,y:int,width:int,height:int},
     *   refs:list<array{page_no:int,asset_id:int,sha256:string}>,
     *   pages:list<array{page_no:int,sha256:string,paper_bounds?:array{x:int,y:int,width:int,height:int}}>
     * }
     */
    private function loadProof(OmrTemplateVerificationRun $run, int $pageNo, string $registrationMode, string $qrMode): array
    {
        if ($run->draft_id === null || ! is_string($run->proof_hash) || $run->proof_hash === '') {
            throw new RuntimeException('verification_proof_missing');
        }
        $assets = OmrTemplateAsset::query()
            ->where('authoring_draft_id', $run->draft_id)
            ->where('kind', OmrTemplateAsset::KIND_DIGITAL_PROOF)
            ->orderBy('page_no')
            ->get();
        $pages = [];
        foreach ($assets as $asset) {
            $metadata = (array) $asset->metadata;
            if ((int) ($metadata['run_id'] ?? 0) !== $run->id
                || (string) ($metadata['proof_hash'] ?? '') !== $run->proof_hash
                || $asset->page_no === null) {
                continue;
            }
            if (($metadata['registration_mode'] ?? null) !== $registrationMode
                || ($metadata['qr_mode'] ?? null) !== $qrMode) {
                throw new RuntimeException('verification_proof_policy_mismatch');
            }
            $paperBounds = is_array($metadata['paper_bounds'] ?? null) ? $metadata['paper_bounds'] : null;
            if ($registrationMode === 'document_bound'
                && ($paperBounds === null
                    || ! is_int($paperBounds['x'] ?? null)
                    || ! is_int($paperBounds['y'] ?? null)
                    || ! is_int($paperBounds['width'] ?? null)
                    || ! is_int($paperBounds['height'] ?? null)
                    || $paperBounds['x'] < 0
                    || $paperBounds['y'] < 0
                    || $paperBounds['width'] <= 0
                    || $paperBounds['height'] <= 0)) {
                throw new RuntimeException('verification_proof_paper_bounds_invalid');
            }
            $pages[(int) $asset->page_no] = [
                'page_no' => (int) $asset->page_no,
                'asset_id' => (int) $asset->id,
                'sha256' => (string) $asset->sha256,
                'disk' => (string) $asset->disk,
                'path' => (string) $asset->path,
                'paper_bounds' => $paperBounds,
            ];
        }
        if (! isset($pages[$pageNo])) {
            throw new RuntimeException('verification_proof_asset_missing');
        }
        $source = $pages[$pageNo];
        $refs = array_map(static fn (array $page): array => [
            'page_no' => $page['page_no'],
            'asset_id' => $page['asset_id'],
            'sha256' => $page['sha256'],
        ], array_values($pages));
        $pageRefs = array_map(static function (array $page): array {
            $reference = ['page_no' => $page['page_no'], 'sha256' => $page['sha256']];
            if (is_array($page['paper_bounds'] ?? null)) {
                $reference['paper_bounds'] = $page['paper_bounds'];
            }

            return $reference;
        }, array_values($pages));

        return [
            'disk' => $source['disk'],
            'path' => $source['path'],
            'sha256' => $source['sha256'],
            'registration_mode' => $registrationMode,
            'qr_mode' => $qrMode,
            'paper_bounds' => $source['paper_bounds'],
            'refs' => $refs,
            'pages' => $pageRefs,
        ];
    }

    /** @param array<string,mixed> $extra */
    private function auditFailure(RecordAuditEvent $audit, OmrTemplateVerificationRun $run, string $code, array $extra = []): void
    {
        $audit->handle('omr.verification.failed', [
            'run_id' => $run->id,
            'attempt' => $run->attempt,
            'plan_hash' => $run->plan_hash,
            'error_code' => $code,
            ...$extra,
        ], (string) $run->started_by_user_id);
    }

    private static function safeErrorCode(Throwable $exception): string
    {
        if ($exception instanceof TimeoutExceededException
            || $exception instanceof MaxAttemptsExceededException
            || stripos($exception->getMessage(), 'timed out') !== false) {
            return 'worker_timeout';
        }
        [$code] = explode(':', $exception->getMessage(), 2);
        $code = preg_replace('/[^a-z0-9_]+/i', '_', strtolower(trim($code)));

        return $code !== '' ? $code : 'worker_unavailable';
    }
}
