<?php

namespace App\Assessment\Actions\Omr;

use App\Models\Assessment\AnswerSheetImportOperation;

final class AnswerSheetImportStatus
{
    public function __construct(private readonly AnswerSheetImportReviewToken $reviewToken) {}

    /** @return array<string, mixed> */
    public function project(AnswerSheetImportOperation $operation): array
    {
        $phase = (string) $operation->phase;
        $leaseExpired = $operation->lease_expires_at !== null
            && $operation->lease_expires_at->isPast();
        $missingLeaseTooLong = $operation->lease_expires_at === null
            && in_array($phase, [
                AnswerSheetImportOperation::PHASE_CHECKING,
                AnswerSheetImportOperation::PHASE_RESTORING,
            ], true)
            && $operation->updated_at !== null
            && $operation->updated_at->addMinutes(15)->isPast();
        $queuedTooLong = $phase === AnswerSheetImportOperation::PHASE_QUEUED
            && $operation->created_at !== null
            && $operation->created_at->addMinutes(15)->isPast();
        $canRetry = $phase === AnswerSheetImportOperation::PHASE_FAILED
            || ($phase === AnswerSheetImportOperation::PHASE_QUEUED && $queuedTooLong)
            || (in_array($phase, [
                AnswerSheetImportOperation::PHASE_CHECKING,
                AnswerSheetImportOperation::PHASE_RESTORING,
            ], true) && ($leaseExpired || $missingLeaseTooLong));
        $canCancel = in_array($phase, [
            AnswerSheetImportOperation::PHASE_QUEUED,
            AnswerSheetImportOperation::PHASE_CHECKING,
            AnswerSheetImportOperation::PHASE_NEEDS_REVIEW,
            AnswerSheetImportOperation::PHASE_READY_TO_RESTORE,
            AnswerSheetImportOperation::PHASE_RESTORING,
        ], true);
        $canReplace = in_array($phase, [
            AnswerSheetImportOperation::PHASE_NEEDS_REVIEW,
            AnswerSheetImportOperation::PHASE_FAILED,
            AnswerSheetImportOperation::PHASE_CANCELLED,
        ], true);
        $report = (array) ($operation->report_json ?? []);
        $resultSummary = (array) ($operation->result_summary ?? []);
        $result = null;

        if ($phase === AnswerSheetImportOperation::PHASE_COMPLETED && $operation->result_draft_id !== null) {
            $result = $resultSummary + [
                'draft_id' => (int) $operation->result_draft_id,
                'edit_url' => route('setup.exam-materials.answer-sheets.edit', [
                    'draft' => (int) $operation->result_draft_id,
                ], absolute: false),
            ];
        }

        $projection = [
            'id' => (string) $operation->id,
            'phase' => $phase,
            'revision' => (int) $operation->revision,
            'generation' => (int) $operation->generation,
            'review_revision' => (int) $operation->review_revision,
            'filename' => (string) $operation->original_filename,
            'upload' => [
                'bytes' => (int) $operation->archive_bytes,
                'mime' => (string) $operation->archive_mime,
            ],
            'progress' => [
                'done' => (int) $operation->progress_done,
                'total' => (int) $operation->progress_total,
                'unit' => $operation->progress_unit,
                'percent' => (int) $operation->progress_total > 0
                    ? (int) floor(((int) $operation->progress_done / (int) $operation->progress_total) * 100)
                    : null,
            ],
            'updated_at' => $operation->updated_at?->toIso8601String(),
            'last_heartbeat_at' => $operation->last_heartbeat_at?->toIso8601String(),
            'report' => [
                'issues' => array_values((array) ($report['issues'] ?? [])),
                'notes' => array_map(
                    static fn (array $note): array => array_intersect_key($note, [
                        'code' => true,
                        'severity' => true,
                        'cause' => true,
                        'action' => true,
                    ]),
                    array_values((array) ($report['notes'] ?? [])),
                ),
                'summary' => (array) ($report['summary'] ?? []),
                'can_restore' => (bool) ($report['can_restore'] ?? false),
            ],
            'safe_error' => $operation->safe_error,
            'error_code' => $operation->error_code,
            'result' => $result,
            'can_confirm' => $phase === AnswerSheetImportOperation::PHASE_READY_TO_RESTORE
                && (bool) ($report['can_restore'] ?? false),
            'review_token' => $phase === AnswerSheetImportOperation::PHASE_READY_TO_RESTORE
                && (bool) ($report['can_restore'] ?? false)
                    ? $this->reviewToken->forOperation($operation)
                    : null,
            'can_retry' => $canRetry,
            'can_cancel' => $canCancel,
            'can_replace' => $canReplace,
            'status_url' => route('setup.exam-materials.answer-sheets.imports.status', [
                'operation' => $operation->id,
            ], absolute: false),
            'retry_url' => route('setup.exam-materials.answer-sheets.imports.retry', [
                'operation' => $operation->id,
            ], absolute: false),
            'confirm_url' => route('setup.exam-materials.answer-sheets.imports.confirm', [
                'operation' => $operation->id,
            ], absolute: false),
            'cancel_url' => route('setup.exam-materials.answer-sheets.imports.cancel', [
                'operation' => $operation->id,
            ], absolute: false),
            'replacement_url' => route('setup.exam-materials.answer-sheets.imports.replacement', [
                'operation' => $operation->id,
            ], absolute: false),
        ];

        return $projection;
    }
}
