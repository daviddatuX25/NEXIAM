<?php

namespace App\Assessment\Actions\Omr;

use App\Models\Assessment\AnswerSheetImportOperation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class RestoreAnswerSheetImport
{
    private const LEASE_SECONDS = 600;

    public function __construct(
        private readonly ImportAnswerSheetBundle $bundleImporter,
        private readonly AnswerSheetImportAuthorization $authorization,
        private readonly AnswerSheetImportReviewToken $reviewTokens,
    ) {}

    public function handle(string $operationId, int $generation): void
    {
        $claim = $this->claim($operationId, $generation);
        if ($claim === null) {
            return;
        }

        $operation = $claim['operation'];
        $leaseToken = $claim['lease_token'];

        try {
            $actor = $this->authorization->freshOwner($operation);
            $archivePath = Storage::disk($operation->archive_disk)->path($operation->archive_path);
            $progress = fn (int $done, int $total, string $member): bool => $this->recordProgress(
                $operationId,
                $generation,
                $leaseToken,
                $done,
                $total,
            );

            $this->bundleImporter->handle($archivePath, $actor, [
                'import_operation_id' => $operationId,
                'generation' => $generation,
                'lease_token' => $leaseToken,
                'confirmation_review_token' => (string) $operation->confirmation_review_token,
                'archive_sha256' => (string) $operation->archive_sha256,
                'progress_callback' => $progress,
            ]);
        } catch (InvalidArgumentException $exception) {
            if (str_starts_with($exception->getMessage(), 'import_operation_stale:')) {
                return;
            }

            $this->publishRestoreIssue($operationId, $generation, $leaseToken, 'The reviewed archive could not be restored safely.');
        } catch (AuthorizationException) {
            $this->finishFailure(
                $operationId,
                $generation,
                $leaseToken,
                'authorization_revoked',
                'Current Answer Sheet access is no longer available. No draft was restored.',
            );
        } catch (Throwable $exception) {
            $this->releaseForQueueRetry($operationId, $generation, $leaseToken);
            throw $exception;
        }
    }

    /** @return array{operation: AnswerSheetImportOperation, lease_token: string}|null */
    private function claim(string $operationId, int $generation): ?array
    {
        return DB::transaction(function () use ($operationId, $generation): ?array {
            $operation = AnswerSheetImportOperation::query()
                ->whereKey($operationId)
                ->lockForUpdate()
                ->first();
            if ($operation === null
                || (int) $operation->generation !== $generation
                || $operation->phase !== AnswerSheetImportOperation::PHASE_RESTORING
                || $operation->result_draft_id !== null) {
                return null;
            }

            if ($operation->lease_expires_at !== null && $operation->lease_expires_at->isFuture()) {
                return null;
            }
            if ((int) $operation->confirmed_review_revision !== (int) $operation->review_revision
                || $operation->confirmation_review_token === null
                || ! hash_equals($this->reviewTokens->forOperation($operation), (string) $operation->confirmation_review_token)) {
                $operation->forceFill([
                    'phase' => AnswerSheetImportOperation::PHASE_NEEDS_REVIEW,
                    'revision' => (int) $operation->revision + 1,
                    'review_revision' => (int) $operation->review_revision + 1,
                    'error_code' => 'confirmation_stale',
                    'safe_error' => 'The restore confirmation no longer matches the current review. Check the archive again before restoring.',
                    'lease_token' => null,
                    'lease_expires_at' => null,
                    'confirmation_review_token' => null,
                    'confirmed_by_user_id' => null,
                    'confirmed_review_revision' => null,
                    'confirmed_at' => null,
                ])->save();

                return null;
            }

            $leaseToken = (string) Str::uuid();
            $operation->forceFill([
                'revision' => (int) $operation->revision + 1,
                'lease_token' => $leaseToken,
                'lease_expires_at' => now()->addSeconds(self::LEASE_SECONDS),
                'last_heartbeat_at' => now(),
                'progress_done' => 0,
                'progress_total' => 0,
                'progress_unit' => 'archive_members',
                'safe_error' => null,
                'error_code' => null,
            ])->save();

            return ['operation' => $operation->fresh(), 'lease_token' => $leaseToken];
        }, attempts: 3);
    }

    private function recordProgress(string $operationId, int $generation, string $leaseToken, int $done, int $total): bool
    {
        return DB::transaction(function () use ($operationId, $generation, $leaseToken, $done, $total): bool {
            $updated = AnswerSheetImportOperation::query()
                ->whereKey($operationId)
                ->where('generation', $generation)
                ->where('phase', AnswerSheetImportOperation::PHASE_RESTORING)
                ->where('lease_token', $leaseToken)
                ->update([
                    'progress_done' => $done,
                    'progress_total' => $total,
                    'progress_unit' => 'archive_members',
                    'revision' => DB::raw('revision + 1'),
                    'lease_expires_at' => now()->addSeconds(self::LEASE_SECONDS),
                    'last_heartbeat_at' => now(),
                    'updated_at' => now(),
                ]);

            return $updated === 1;
        }, attempts: 3);
    }

    private function publishRestoreIssue(string $operationId, int $generation, string $leaseToken, string $cause): void
    {
        $issue = [
            'id' => hash('sha256', 'restore_rejected|'.$operationId.'|'.$generation),
            'code' => 'restore_rejected',
            'severity' => 'blocking',
            'artifact' => 'archive',
            'member' => null,
            'page_no' => null,
            'cause' => $cause,
            'evidence' => 'The archive passed checking, but the restore transaction did not publish a draft.',
            'remedy' => 'Review the latest report or select a linked replacement archive. No partial draft was published.',
            'allowed_actions' => ['replace_source', 'cancel'],
        ];
        $report = [
            'summary' => [],
            'issues' => [$issue],
            'notes' => [],
            'can_restore' => false,
        ];

        AnswerSheetImportOperation::query()
            ->whereKey($operationId)
            ->where('generation', $generation)
            ->where('phase', AnswerSheetImportOperation::PHASE_RESTORING)
            ->where('lease_token', $leaseToken)
            ->update([
                'phase' => AnswerSheetImportOperation::PHASE_NEEDS_REVIEW,
                'revision' => DB::raw('revision + 1'),
                'review_revision' => DB::raw('review_revision + 1'),
                'report_json' => json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'report_sha256' => hash('sha256', json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                'error_code' => 'restore_rejected',
                'safe_error' => $cause,
                'lease_token' => null,
                'lease_expires_at' => null,
                'confirmation_review_token' => null,
                'confirmed_by_user_id' => null,
                'confirmed_review_revision' => null,
                'confirmed_at' => null,
                'last_heartbeat_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function finishFailure(
        string $operationId,
        int $generation,
        string $leaseToken,
        string $errorCode,
        string $safeError,
    ): void {
        AnswerSheetImportOperation::query()
            ->whereKey($operationId)
            ->where('generation', $generation)
            ->where('phase', AnswerSheetImportOperation::PHASE_RESTORING)
            ->where('lease_token', $leaseToken)
            ->update([
                'phase' => AnswerSheetImportOperation::PHASE_FAILED,
                'revision' => DB::raw('revision + 1'),
                'error_code' => $errorCode,
                'safe_error' => $safeError,
                'lease_token' => null,
                'lease_expires_at' => null,
                'last_heartbeat_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function releaseForQueueRetry(string $operationId, int $generation, string $leaseToken): void
    {
        AnswerSheetImportOperation::query()
            ->whereKey($operationId)
            ->where('generation', $generation)
            ->where('phase', AnswerSheetImportOperation::PHASE_RESTORING)
            ->where('lease_token', $leaseToken)
            ->update([
                'revision' => DB::raw('revision + 1'),
                'error_code' => 'restore_worker_error',
                'safe_error' => 'Restoration stopped unexpectedly. It will be tried again or you can retry it later.',
                'lease_token' => null,
                'lease_expires_at' => null,
                'last_heartbeat_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
