<?php

namespace App\Assessment\Actions\Omr;

use App\Admission\Support\CommandIdempotency;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrPaperReproductionAuthorization;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\Operation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class AuthorizeOmrPaperReproduction
{
    public function __construct(
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        GradingSession $gradingSession,
        User $actor,
        string $displayCode,
        int $pageNo,
        int $quantity,
        string $reason,
        string $idempotencyKey,
        ?CarbonInterface $requestedExpiry = null,
    ): OmrPaperReproductionAuthorization {
        $operation = $this->idempotency->claim(
            $actor,
            'grading.omr.authorize_paper_reproduction',
            'grading-session:'.$gradingSession->id,
            [
                'display_code' => strtoupper(trim($displayCode)),
                'page_no' => $pageNo,
                'quantity' => $quantity,
                'reason' => trim($reason),
                'expires_at' => $requestedExpiry?->toIso8601String(),
            ],
            $idempotencyKey,
        );
        $saved = is_array($operation->payload['result'] ?? null) ? $operation->payload['result'] : null;
        if (! $operation->wasRecentlyCreated && $saved !== null) {
            return OmrPaperReproductionAuthorization::query()->findOrFail((int) $saved['authorization_id']);
        }
        if (! $operation->wasRecentlyCreated) {
            throw new RuntimeException('idempotency_in_progress');
        }
        $operation->forceFill([
            'status' => Operation::STATUS_PROCESSING,
            'attempts' => (int) $operation->attempts + 1,
            'last_error' => null,
            'finished_at' => null,
        ])->save();

        try {
            $authorization = DB::transaction(function () use (
                $gradingSession,
                $actor,
                $displayCode,
                $pageNo,
                $quantity,
                $reason,
                $requestedExpiry,
            ): OmrPaperReproductionAuthorization {
                $session = GradingSession::query()->lockForUpdate()->findOrFail($gradingSession->id);
                if ($session->status === GradingSession::STATUS_FINALIZED) {
                    throw new RuntimeException('Grading session is finalized; paper reproduction is closed.');
                }

                $examSession = ExamSession::query()
                    ->with('currentMaterialSnapshot')
                    ->lockForUpdate()
                    ->findOrFail($session->exam_session_id);
                if (! in_array($examSession->status, [
                    ExamSession::STATUS_IN_PROGRESS,
                    ExamSession::STATUS_COMPLETED,
                ], true)) {
                    throw new RuntimeException('Paper reproduction is available only for an in-progress or completed exam session.');
                }

                $instance = OmrBlankInstance::query()
                    ->where('display_code', strtoupper(trim($displayCode)))
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($instance->copy_policy !== OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE) {
                    throw new RuntimeException('Reusable masters do not require emergency reproduction authorization.');
                }
                if (in_array($instance->status, [
                    OmrBlankInstance::STATUS_VOID,
                    OmrBlankInstance::STATUS_SUPERSEDED,
                ], true)) {
                    throw new RuntimeException('The source Answer Sheet is no longer valid.');
                }

                $snapshot = $examSession->currentMaterialSnapshot;
                if ($snapshot === null || (int) $snapshot->template_version_id !== (int) $instance->template_version_id) {
                    throw new RuntimeException('The source Answer Sheet does not match this exam session.');
                }

                $page = $instance->pages()->where('page_no', $pageNo)->lockForUpdate()->first();
                if ($page === null) {
                    throw new RuntimeException('The source Answer Sheet page does not exist.');
                }

                OmrPaperReproductionAuthorization::query()
                    ->where('exam_session_id', $examSession->id)
                    ->where('blank_instance_page_id', $page->id)
                    ->where('status', OmrPaperReproductionAuthorization::STATUS_OPEN)
                    ->where('expires_at', '<=', now())
                    ->update([
                        'status' => OmrPaperReproductionAuthorization::STATUS_VOID,
                        'reconciled_by_user_id' => $actor->id,
                        'reconciled_at' => now(),
                        'updated_at' => now(),
                    ]);
                if (OmrPaperReproductionAuthorization::query()
                    ->where('exam_session_id', $examSession->id)
                    ->where('blank_instance_page_id', $page->id)
                    ->where('status', OmrPaperReproductionAuthorization::STATUS_OPEN)
                    ->exists()) {
                    throw new RuntimeException('An active reproduction authorization already exists for this page.');
                }

                $maximumExpiry = now()->addHours(4);
                if ($examSession->effective_end_at?->isFuture()) {
                    $maximumExpiry = $maximumExpiry->min($examSession->effective_end_at);
                }
                $expiresAt = $requestedExpiry ?? now()->addMinutes(30);
                if ($expiresAt->isPast()) {
                    throw new RuntimeException('The reproduction authorization expiry must be in the future.');
                }
                if ($expiresAt->greaterThan($maximumExpiry)) {
                    $expiresAt = $maximumExpiry;
                }

                $created = OmrPaperReproductionAuthorization::query()->create([
                    'exam_session_id' => $examSession->id,
                    'blank_instance_page_id' => $page->id,
                    'maximum_claims' => $quantity,
                    'used_claims' => 0,
                    'status' => OmrPaperReproductionAuthorization::STATUS_OPEN,
                    'reason' => trim($reason),
                    'expires_at' => $expiresAt,
                    'lock_version' => 0,
                    'authorized_by_user_id' => $actor->id,
                ]);
                $this->recordAuditEvent->handle('grading.omr.paper_reproduction_authorized', [
                    'authorization_id' => $created->id,
                    'grading_session_id' => $session->id,
                    'exam_session_id' => $examSession->id,
                    'blank_instance_id' => $instance->id,
                    'blank_instance_page_id' => $page->id,
                    'display_code' => $instance->display_code,
                    'page_no' => $pageNo,
                    'quantity' => $quantity,
                    'expires_at' => $expiresAt->toIso8601String(),
                    'reason' => trim($reason),
                ], (string) $actor->id);

                return $created;
            });
            $operation->forceFill([
                'status' => Operation::STATUS_SUCCEEDED,
                'finished_at' => now(),
                'payload' => [...(array) $operation->payload, 'result' => [
                    'authorization_id' => (int) $authorization->id,
                ]],
            ])->save();

            return $authorization;
        } catch (Throwable $exception) {
            $operation->forceFill([
                'status' => Operation::STATUS_FAILED,
                'last_error' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();
            throw $exception;
        }
    }
}
