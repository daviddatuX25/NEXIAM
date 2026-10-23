<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReconcileOverdueExamSessions
{
    public function __construct(
        private readonly CloseExamSession $closeAction,
        private readonly CancelExamSession $cancelAction,
        private readonly RevertStartedExamSession $revertAction,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function defaultGraceMinutes(): int
    {
        return max(0, (int) config('examination.thresholds.auto_close_grace_minutes', 15));
    }

    /**
     * Reconcile a single session on-demand if it is in progress and its
     * effective_end_at + grace window has lapsed.
     */
    public function reconcileSessionIfOverdue(ExamSession $session, ?int $graceMinutes = null): ExamSession
    {
        if (! in_array($session->status, [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS], true) || $session->effective_end_at === null) {
            return $session;
        }

        $grace = $graceMinutes !== null ? max(0, $graceMinutes) : $this->defaultGraceMinutes();
        if (now()->lt($session->effective_end_at->copy()->addMinutes($grace))) {
            return $session;
        }

        $this->handle($grace, (int) $session->id, true);

        return $session->fresh() ?? $session;
    }

    /**
     * Reconcile overdue exam sessions past their grace period per D-040 / D-075.
     *
     * @return array{closed: int, cancelled: int, errors: list<string>, scanned: int}
     */
    public function handle(
        ?int $graceMinutes = null,
        ?int $onlySessionId = null,
        bool $includePublished = true,
        ?int $academicCycleId = null,
    ): array {
        $grace = $graceMinutes !== null ? max(0, $graceMinutes) : $this->defaultGraceMinutes();
        $cutoff = now()->subMinutes($grace);

        $statuses = $includePublished
            ? [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS]
            : [ExamSession::STATUS_IN_PROGRESS];

        $query = ExamSession::query()
            ->forWorkspace()
            ->whereIn('status', $statuses)
            ->whereNotNull('effective_end_at')
            ->where('effective_end_at', '<=', $cutoff);

        if ($onlySessionId !== null) {
            $query->whereKey($onlySessionId);
        }

        if ($academicCycleId !== null) {
            $query->where('academic_cycle_id', $academicCycleId);
        }

        $sessionIds = $query->pluck('id');
        if ($sessionIds->isEmpty()) {
            return [
                'closed' => 0,
                'cancelled' => 0,
                'errors' => [],
                'scanned' => 0,
            ];
        }

        $systemActor = User::query()->where('email', 'guidance.counselor@nexiam.test')->first()
            ?? User::query()->first();

        if ($systemActor === null) {
            return [
                'closed' => 0,
                'cancelled' => 0,
                'errors' => ['No administrative user found for overdue session reconciliation.'],
                'scanned' => $sessionIds->count(),
            ];
        }

        $closedCount = 0;
        $cancelledCount = 0;
        $errors = [];

        foreach ($sessionIds as $sessionId) {
            try {
                DB::transaction(function () use (
                    $sessionId,
                    $systemActor,
                    $grace,
                    $cutoff,
                    $statuses,
                    &$closedCount,
                    &$cancelledCount,
                ): void {
                    /** @var ExamSession|null $session */
                    $session = ExamSession::query()
                        ->lockForUpdate()
                        ->with(['assignments.attendanceRecord'])
                        ->find($sessionId);

                    if (
                        $session === null
                        || ! in_array($session->status, $statuses, true)
                        || $session->effective_end_at === null
                        || $session->effective_end_at->gt($cutoff)
                    ) {
                        return;
                    }

                    $activeAssignments = $session->assignments()->active()->with('attendanceRecord')->get();
                    $recordedCount = $activeAssignments->filter(fn ($a) => $a->attendanceRecord !== null)->count();
                    $presentCount = $activeAssignments->filter(
                        fn ($a) => $a->attendanceRecord?->outcome === ExamAttendanceRecord::OUTCOME_PRESENT
                    )->count();

                    if (
                        $session->status === ExamSession::STATUS_IN_PROGRESS
                        && $recordedCount === 0
                        && $session->starts_at !== null
                        && now()->lt($session->starts_at)
                    ) {
                        $this->revertAction->handle($session->fresh(), $systemActor, (int) $session->version);
                        $closedCount++;

                        return;
                    }

                    // D-040: Unstarted published session with zero present attendance -> mark unrecorded absent and cancel
                    if ($session->status === ExamSession::STATUS_PUBLISHED && ($activeAssignments->isEmpty() || $presentCount === 0)) {
                        $unrecorded = $activeAssignments->filter(fn ($a) => $a->attendanceRecord === null);
                        foreach ($unrecorded as $assignment) {
                            ExamAttendanceRecord::query()->create([
                                'exam_assignment_id' => $assignment->id,
                                'recorded_by_user_id' => $systemActor->id,
                                'outcome' => ExamAttendanceRecord::OUTCOME_ABSENT,
                                'idempotency_key' => 'auto-close-noshow-'.$assignment->id,
                                'recorded_at' => now(),
                            ]);
                        }

                        $reason = "Auto-closed: Zero examinee attendance recorded within {$grace}m grace period.";
                        $this->cancelAction->handle($session->fresh(), $systemActor, (int) $session->version, $reason);
                        $cancelledCount++;

                        $this->recordAuditEvent->handle('exam.session_auto_cancelled', [
                            'session_id' => $session->id,
                            'code' => $session->code,
                            'reason' => $reason,
                            'no_show_count' => $unrecorded->count(),
                        ], 'scheduler');

                        return;
                    }

                    // Started (in_progress) session -> mark unrecorded absent and delegate to CloseExamSession
                    // (CloseExamSession transitions to cancelled if presentCount === 0, or completed if presentCount >= 1)
                    $unrecorded = $activeAssignments->filter(fn ($a) => $a->attendanceRecord === null);
                    foreach ($unrecorded as $assignment) {
                        ExamAttendanceRecord::query()->create([
                            'exam_assignment_id' => $assignment->id,
                            'recorded_by_user_id' => $systemActor->id,
                            'outcome' => ExamAttendanceRecord::OUTCOME_ABSENT,
                            'idempotency_key' => 'auto-close-noshow-'.$assignment->id,
                            'recorded_at' => now(),
                        ]);
                    }

                    $closedSession = $this->closeAction->handle($session->fresh(), $systemActor, (int) $session->version);

                    if ($closedSession->status === ExamSession::STATUS_CANCELLED) {
                        $cancelledCount++;
                        $reason = "Auto-closed: Zero examinee attendance present within {$grace}m grace period.";

                        $this->recordAuditEvent->handle('exam.session_auto_cancelled', [
                            'session_id' => $session->id,
                            'code' => $session->code,
                            'reason' => $reason,
                            'no_show_count' => $unrecorded->count(),
                        ], 'scheduler');
                    } else {
                        $closedCount++;

                        $this->recordAuditEvent->handle('exam.session_auto_closed', [
                            'session_id' => $session->id,
                            'code' => $session->code,
                            'attended_count' => $presentCount,
                            'no_show_count' => $unrecorded->count(),
                        ], 'scheduler');
                    }
                });
            } catch (Throwable $e) {
                $errors[] = "Session [{$sessionId}] failed: {$e->getMessage()}";
            }
        }

        return [
            'closed' => $closedCount,
            'cancelled' => $cancelledCount,
            'errors' => $errors,
            'scanned' => $sessionIds->count(),
        ];
    }
}
