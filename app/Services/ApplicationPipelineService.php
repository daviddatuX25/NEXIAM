<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ExamSession;
use App\Models\ConsultationSummary;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ApplicationPipelineService
{
    /**
     * Ordered pipeline statuses. Higher index = further along in the lifecycle.
     * `dismissed` uses 99 as a sentinel — it is always allowed regardless of current position.
     *
     * @var array<string, int>
     */
    public const PIPELINE_ORDER = [
        'pending' => 0,
        'accepted' => 1,
        'draft_scheduled' => 2,
        'scheduled' => 3,
        'printed' => 4,
        'attended' => 5,
        'submitted' => 6,
        'scored' => 7,
        'graded' => 8,
        'released' => 9,
        'dismissed' => 99,
    ];

    /**
     * Transition an application forward in the pipeline.
     *
     * This is a forward-only guard: the application will only advance if `$newStatus`
     * is further along than the current `pipeline_status`. The only exception is
     * `dismissed`, which is always allowed from any state.
     *
     * Silently no-ops when the application is already at or past the requested status.
     *
     * @param  array<string, mixed>  $milestoneMeta  Extra data stored alongside the milestone timestamp.
     * @return bool True if the status was actually changed, false if it was a no-op.
     */
    public function transition(Application $app, string $newStatus, array $milestoneMeta = []): bool
    {
        if (! array_key_exists($newStatus, self::PIPELINE_ORDER)) {
            Log::warning('ApplicationPipelineService: unknown status attempted', [
                'app_id' => $app->id,
                'new_status' => $newStatus,
            ]);

            return false;
        }

        $currentOrder = self::PIPELINE_ORDER[$app->pipeline_status ?? 'pending'] ?? 0;
        $newOrder = self::PIPELINE_ORDER[$newStatus];

        // dismissed is always allowed; otherwise we only advance
        if ($newStatus !== 'dismissed' && $newOrder <= $currentOrder) {
            return false;
        }

        $prev = $app->pipeline_status;
        $app->updatePipelineStatus($newStatus, $milestoneMeta);

        Log::info('Pipeline status transitioned', [
            'app_id' => $app->id,
            'from' => $prev,
            'to' => $newStatus,
        ]);

        return true;
    }

    /**
     * Force-set a pipeline status, bypassing the forward-only guard.
     *
     * Reserved for the one-time backfill command (`pipeline:sync-statuses`).
     * Do NOT call this from application code — use `transition()` instead.
     *
     * @param  array<string, mixed>  $milestoneMeta
     */
    public function forceSet(Application $app, string $status, array $milestoneMeta = []): void
    {
        $app->updatePipelineStatus($status, $milestoneMeta);
    }

    /**
     * Re-calculate and set the correct pipeline status and milestones for an application.
     */
    public function syncStatus(Application $app): void
    {
        $app->loadMissing([
            'applicant.examSessions',
            'applicant.applicantScores',
            'applicant.consultationSummary',
        ]);

        [$status, $milestones] = $this->computeExpected($app);
        $this->forceSet($app, $status, $milestones);
    }

    /**
     * Compute expected status and milestones for an application.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function computeExpected(Application $app): array
    {
        $milestones = [];

        // ── Dismissed overrides everything ──────────────────────────────────
        if ($app->status === 'dismissed') {
            $milestones['dismissed'] = ['at' => $app->processed_at?->toIso8601String()];

            return ['dismissed', $milestones];
        }

        // ── Pending ─────────────────────────────────────────────────────────
        if ($app->status === 'pending') {
            return ['pending', []];
        }

        // ── Accepted milestone ───────────────────────────────────────────────
        $milestones['accepted'] = ['at' => $app->processed_at?->toIso8601String()];

        $applicant = $app->applicant;
        if (! $applicant) {
            return ['accepted', $milestones];
        }

        $examSessions = $applicant->relationLoaded('examSessions')
            ? $applicant->examSessions
            : $applicant->examSessions()->get();

        if ($examSessions->isEmpty()) {
            return ['accepted', $milestones];
        }

        // Filter out cancelled sessions
        $activeSessions = $examSessions->reject(
            fn (ExamSession $s) => $s->status === ExamSession::STATUS_CANCELLED
        );

        if ($activeSessions->isEmpty()) {
            return ['accepted', $milestones];
        }

        $statusPriority = [
            ExamSession::STATUS_COMPLETED => 4,
            ExamSession::STATUS_IN_PROGRESS => 3,
            ExamSession::STATUS_PUBLISHED => 2,
            ExamSession::STATUS_DRAFT => 1,
        ];

        $sortedSessions = $activeSessions->sortByDesc(
            fn (ExamSession $s) => $statusPriority[$s->status] ?? 0
        );

        $bestStatus = 'accepted';

        foreach ($sortedSessions as $session) {
            $pivot = $session->pivot;

            // ── Direct assessment ────────────────────────────────────────────
            if ($session->type === ExamSession::TYPE_DIRECT) {
                $hasScores = $applicant->relationLoaded('applicantScores')
                    ? $applicant->applicantScores->isNotEmpty()
                    : $applicant->applicantScores()->exists();

                if ($hasScores) {
                    $milestones['scored'] = ['at' => $session->created_at?->toIso8601String()];

                    $summary = $applicant->consultationSummary;
                    if ($summary && $summary->status === ConsultationSummary::STATUS_RELEASED) {
                        $milestones['released'] = ['at' => $summary->released_at?->toIso8601String()];
                        $milestones['graded'] = ['at' => null];

                        return ['released', $milestones];
                    }

                    $milestones['graded'] = ['at' => null];

                    return ['graded', $milestones];
                }

                if ($bestStatus === 'accepted') {
                    $bestStatus = 'scored';
                    $milestones['scored'] = ['at' => $session->created_at?->toIso8601String()];
                }

                continue;
            }

            // ── F2F / Scheduled session ──────────────────────────────────────
            if ($session->status === ExamSession::STATUS_DRAFT) {
                $milestones['draft_scheduled'] = ['at' => $session->created_at?->toIso8601String(), 'session_id' => $session->id];

                if ($app->admission_slip_printed_at) {
                    $milestones['printed'] = ['at' => $app->admission_slip_printed_at->toIso8601String()];

                    return ['printed', $milestones];
                }

                return ['draft_scheduled', $milestones];
            }

            if (in_array($session->status, [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS, ExamSession::STATUS_COMPLETED], true)) {
                $milestones['scheduled'] = [
                    'at' => $session->created_at?->toIso8601String(),
                    'session_id' => $session->id,
                ];

                if ($app->admission_slip_printed_at && $bestStatus === 'accepted') {
                    $bestStatus = 'printed';
                    $milestones['printed'] = ['at' => $app->admission_slip_printed_at->toIso8601String()];
                }

                if ($pivot && $pivot->attendance_status === 'present') {
                    $attendedAt = $pivot->attendance_marked_at
                        ? (is_string($pivot->attendance_marked_at) ? Carbon::parse($pivot->attendance_marked_at) : $pivot->attendance_marked_at)
                        : null;
                    $milestones['attended'] = ['at' => $attendedAt?->toIso8601String()];

                    if ($pivot->submission_status === 'submitted') {
                        $submittedAt = $pivot->submitted_at
                            ? (is_string($pivot->submitted_at) ? Carbon::parse($pivot->submitted_at) : $pivot->submitted_at)
                            : null;
                        $milestones['submitted'] = ['at' => $submittedAt?->toIso8601String()];

                        $hasScores = $applicant->relationLoaded('applicantScores')
                            ? $applicant->applicantScores->isNotEmpty()
                            : $applicant->applicantScores()->exists();

                        if ($hasScores) {
                            $milestones['graded'] = ['at' => null];

                            $summary = $applicant->consultationSummary;
                            if ($summary && $summary->status === ConsultationSummary::STATUS_RELEASED) {
                                $milestones['released'] = ['at' => $summary->released_at?->toIso8601String()];

                                return ['released', $milestones];
                            }

                            return ['graded', $milestones];
                        }

                        $bestStatus = 'submitted';

                        continue;
                    }

                    $bestStatus = 'attended';

                    continue;
                }

                if ($bestStatus === 'accepted') {
                    $bestStatus = 'scheduled';
                }
            }
        }

        return [$bestStatus, $milestones];
    }
}
