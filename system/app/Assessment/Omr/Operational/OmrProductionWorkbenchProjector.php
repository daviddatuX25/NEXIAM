<?php

declare(strict_types=1);

namespace App\Assessment\Omr\Operational;

use App\Assessment\Omr\Queries\OmrRunStatusSnapshot;
use App\Models\Assessment\OmrCaptureBatch;
use InvalidArgumentException;

use function ctype_digit;
use function is_array;
use function is_string;
use function trim;

/**
 * Adapts the incumbent production run snapshot to the shared workbench shape.
 *
 * The snapshot remains the production status authority. This class only
 * projects its safe, answer-key-blind fields and a closed command list; it
 * never resolves or mutates a production aggregate.
 */
final readonly class OmrProductionWorkbenchProjector
{
    public function __construct(private OmrRunStatusSnapshot $statusSnapshot) {}

    public function project(string $workbenchId, OmrCaptureBatch $batch): OmrWorkbenchProjection
    {
        return $this->fromSnapshot($workbenchId, $this->statusSnapshot->forBatch($batch));
    }

    /**
     * Project a status response without widening the production status
     * contract. This is public so status transport tests and adapters can use
     * the same normalization path.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function fromSnapshot(string $workbenchId, array $snapshot): OmrWorkbenchProjection
    {
        $mode = $snapshot['mode'] ?? null;
        $executionKind = $snapshot['execution_kind'] ?? null;
        if (($mode !== null && $mode !== OmrExecutionKind::Production->value)
            || ($executionKind !== null && $executionKind !== OmrExecutionKind::Production->value)
            || ($mode === null && $executionKind === null)) {
            throw new InvalidArgumentException('A production workbench requires a production run snapshot.');
        }

        $sessionId = $this->positiveInt($snapshot['session_id'] ?? null, 'session_id');
        $runId = $this->positiveInt($snapshot['run_id'] ?? null, 'run_id');
        [$identitySessionId, $identityBatchId] = $this->identity($workbenchId);
        if ($identitySessionId !== $sessionId || $identityBatchId !== $runId) {
            throw new InvalidArgumentException('The production workbench identity does not match the run snapshot.');
        }
        $revision = $this->positiveInt($snapshot['revision'] ?? null, 'revision');
        $stage = is_string($snapshot['stage'] ?? null) && trim($snapshot['stage']) !== ''
            ? trim((string) $snapshot['stage'])
            : 'open';
        $rawStatus = is_string($snapshot['raw_status'] ?? null)
            ? (string) $snapshot['raw_status']
            : $stage;
        $terminal = (bool) ($snapshot['terminal'] ?? false);
        $progress = is_array($snapshot['progress'] ?? null)
            ? OmrBrowserPayloadRedactor::redact($snapshot['progress'])
            : ['completed' => 0, 'total' => 0, 'known' => false];
        $counts = is_array($snapshot['counts'] ?? null)
            ? OmrBrowserPayloadRedactor::redact($snapshot['counts'])
            : [];
        $sheets = is_array($snapshot['sheets'] ?? null)
            ? array_values(OmrBrowserPayloadRedactor::redact($snapshot['sheets']))
            : [];
        $attention = is_array($snapshot['needs_attention'] ?? null)
            ? array_values(OmrBrowserPayloadRedactor::redact($snapshot['needs_attention']))
            : [];
        $observedAt = is_string($snapshot['observed_at'] ?? null)
            ? (string) $snapshot['observed_at']
            : null;
        $staleAfterMs = $this->nonNegativeInt($snapshot['stale_after_ms'] ?? 0);

        foreach ($sheets as $sheet) {
            if (! is_array($sheet)) {
                throw new InvalidArgumentException('Production OMR sheet status is malformed.');
            }
        }

        $progress = [
            'completed' => $this->nonNegativeInt($progress['completed'] ?? 0),
            'total' => $this->nonNegativeInt($progress['total'] ?? 0),
            'known' => (bool) ($progress['known'] ?? false),
        ];

        $context = [
            'execution_kind' => OmrExecutionKind::Production->value,
            'session_id' => $sessionId,
            'run_id' => $runId,
            'session' => [
                'id' => $sessionId,
            ],
            'run' => [
                'id' => $runId,
                'status' => $rawStatus,
                'terminal' => $terminal,
            ],
            'revision' => $revision,
            'stage' => $stage,
            'progress' => $progress,
            'counts' => $counts,
            'attention' => $attention,
            'raw_status' => $rawStatus,
            'terminal' => $terminal,
            'stale_after_ms' => $staleAfterMs,
        ];

        if ($observedAt !== null) {
            $context['observed_at'] = $observedAt;
        }

        $references = $this->evidenceReferences($sheets);
        $evidence = [
            'references' => $references,
            'count' => count($references),
            'redacted' => true,
        ];

        $completion = $terminal
            ? [
                'status' => $rawStatus,
                'session_id' => $sessionId,
                'run_id' => $runId,
                'immutable' => true,
            ]
            : null;

        return new OmrWorkbenchProjection(
            workbenchId: $workbenchId,
            executionKind: OmrExecutionKind::Production,
            revision: $revision,
            phase: $this->phase($stage, $rawStatus, $terminal),
            context: $context,
            sheets: $sheets,
            evidence: $evidence,
            recovery: ['needs_attention' => $attention],
            completion: $completion,
            commands: $this->commands($snapshot, $sheets, $terminal),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $sheets
     * @return list<array{sheet_id:int,page_no:int,status:string,captured:bool}>
     */
    private function evidenceReferences(array $sheets): array
    {
        $references = [];

        foreach ($sheets as $sheet) {
            $sheetId = $this->positiveInt($sheet['id'] ?? null, 'sheet_id');
            $pages = is_array($sheet['pages'] ?? null) ? $sheet['pages'] : [];

            foreach ($pages as $page) {
                if (! is_array($page)) {
                    throw new InvalidArgumentException('Production OMR page status is malformed.');
                }

                $references[] = [
                    'sheet_id' => $sheetId,
                    'page_no' => $this->positiveInt($page['page_no'] ?? null, 'page_no'),
                    'status' => is_string($page['status'] ?? null) ? (string) $page['status'] : 'unknown',
                    'captured' => (bool) ($page['captured'] ?? false),
                ];
            }
        }

        return $references;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  list<array<string, mixed>>  $sheets
     * @return list<OmrWorkbenchCommand>
     */
    private function commands(array $snapshot, array $sheets, bool $terminal): array
    {
        if ($terminal) {
            return [];
        }

        $actions = is_array($snapshot['actions'] ?? null) ? $snapshot['actions'] : [];
        $captureOpen = (bool) ($actions['capture'] ?? false);
        $commands = [];

        if ($captureOpen) {
            $commands[] = OmrWorkbenchCommand::CaptureStart;
        }

        if ($captureOpen && $this->hasRetryableSheet($sheets)) {
            $commands[] = OmrWorkbenchCommand::CaptureRetry;
        }

        if ($this->hasSheetStatus($sheets, 'needs_review')) {
            $commands[] = OmrWorkbenchCommand::ReviewSave;
        }

        if ($this->hasRecoveryAction($sheets, 'match_applicant')) {
            $commands[] = OmrWorkbenchCommand::ResolveApplicant;
        }

        if ($this->hasRecoveryAction($sheets, 'compare_duplicates')) {
            $commands[] = OmrWorkbenchCommand::ResolveDuplicate;
        }

        if ((bool) ($actions['confirm'] ?? false)) {
            $commands[] = OmrWorkbenchCommand::ProductionComplete;
        }

        return $commands;
    }

    /**
     * @param  list<array<string, mixed>>  $sheets
     */
    private function hasRetryableSheet(array $sheets): bool
    {
        foreach ($sheets as $sheet) {
            if ($this->hasRecoveryAction([$sheet], 'retry_scan')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $sheets
     */
    private function hasSheetStatus(array $sheets, string $status): bool
    {
        foreach ($sheets as $sheet) {
            if (($sheet['status'] ?? null) === $status) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $sheets
     */
    private function hasRecoveryAction(array $sheets, string $action): bool
    {
        foreach ($sheets as $sheet) {
            $recovery = is_array($sheet['recovery'] ?? null) ? $sheet['recovery'] : [];
            if (($recovery['primary_action'] ?? null) === $action
                || ($recovery['secondary_action'] ?? null) === $action) {
                return true;
            }
        }

        return false;
    }

    private function phase(string $stage, string $rawStatus, bool $terminal): string
    {
        if ($terminal && $rawStatus === 'confirmed') {
            return OmrWorkbenchProjection::PHASE_COMPLETE;
        }

        if ($terminal) {
            return OmrWorkbenchProjection::PHASE_TERMINAL;
        }

        return match ($stage) {
            'captured', 'open', 'empty' => OmrWorkbenchProjection::PHASE_CAPTURE,
            'queued', 'recognizing', 'processing', 'preparing' => OmrWorkbenchProjection::PHASE_PROCESSING,
            'resolving', 'context' => OmrWorkbenchProjection::PHASE_CONTEXT,
            'needs_review', 'ready', 'review' => OmrWorkbenchProjection::PHASE_REVIEW,
            'recovery', 'failed' => OmrWorkbenchProjection::PHASE_RECOVERY,
            'completion' => OmrWorkbenchProjection::PHASE_COMPLETION,
            'complete', 'confirmed' => OmrWorkbenchProjection::PHASE_COMPLETE,
            default => OmrWorkbenchProjection::PHASE_CAPTURE,
        };
    }

    /**
     * @return array{0:int,1:int}
     */
    private function identity(string $workbenchId): array
    {
        $workbenchId = trim($workbenchId);
        if (preg_match('/\Aproduction:([1-9][0-9]*):([1-9][0-9]*)\z/D', $workbenchId, $matches) !== 1) {
            throw new InvalidArgumentException('The production workbench identity is invalid.');
        }

        return [(int) $matches[1], (int) $matches[2]];
    }

    private function positiveInt(mixed $value, string $field): int
    {
        $value = is_int($value)
            ? $value
            : (is_string($value) && ctype_digit($value) ? (int) $value : 0);
        if ($value < 1) {
            throw new InvalidArgumentException("Production OMR {$field} must be positive.");
        }

        return $value;
    }

    private function nonNegativeInt(mixed $value): int
    {
        if (is_int($value)) {
            return max(0, $value);
        }

        return is_string($value) && ctype_digit($value)
            ? (int) $value
            : 0;
    }
}
