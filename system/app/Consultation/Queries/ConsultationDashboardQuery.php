<?php

namespace App\Consultation\Queries;

use App\Assessment\Support\IndividualScoringReadiness;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;

/**
 * Founder 2026-08-15: `/consultation` is a DASHBOARD first — consultation
 * progress from existing session/assignment data (today's scheduled, pending
 * advise, not-yet-scheduled, sessions open/closed), grouped by session with
 * assigned applicants. The per-applicant admission-support workspace moved to
 * an inner page.
 */
final class ConsultationDashboardQuery
{
    /**
     * @return array<string, mixed>
     */
    public function for(int $institutionId, ?string $cycleCode): array
    {
        $cycle = $cycleCode === null
            ? null
            : AcademicCycle::query()
                ->where('institution_id', $institutionId)
                ->where('code', $cycleCode)
                ->where('is_active', true)
                ->first();
        if ($cycle === null) {
            return $this->emptyDashboard();
        }

        $attempts = AdmissionAttempt::query()->forWorkspace()
            ->with('identity')
            ->where('academic_cycle_id', $cycle->id)
            ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->get();
        $attemptIds = $attempts->pluck('id')->all();
        $scoringReadinessByAttempt = app(IndividualScoringReadiness::class)->forAttempts($attempts);

        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $sessionsToday = ConsultationSession::query()
            ->where('academic_cycle_id', $cycle->id)
            ->whereNotIn('status', [
                ConsultationSession::STATUS_DRAFT,
                ConsultationSession::STATUS_CANCELLED,
            ])
            ->where(function ($q) use ($todayStart, $todayEnd): void {
                $q->whereBetween('scheduled_at', [$todayStart, $todayEnd])
                    ->orWhere('status', ConsultationSession::STATUS_IN_PROGRESS);
            })
            ->orderBy('scheduled_at')
            ->with('room')
            ->get();

        $scheduledAttemptIds = ConsultationAssignment::query()
            ->whereIn('admission_attempt_id', $attemptIds)
            ->whereIn('status', [
                ConsultationAssignment::STATUS_ASSIGNED,
                ConsultationAssignment::STATUS_CONSULTED,
            ])
            ->whereIn('consultation_session_id', $sessionsToday->pluck('id'))
            ->distinct()
            ->pluck('admission_attempt_id')
            ->all();

        $activeAssignments = ConsultationAssignment::query()
            ->whereIn('admission_attempt_id', $attemptIds)
            ->whereIn('status', ConsultationAssignment::ACTIVE_STATUSES);
        $anyScheduledIds = (clone $activeAssignments)
            ->distinct()
            ->pluck('admission_attempt_id')
            ->all();
        $publishedScheduledIds = (clone $activeAssignments)
            ->whereHas('consultationSession', fn ($query) => $query->whereIn('status', [
                ConsultationSession::STATUS_PUBLISHED,
                ConsultationSession::STATUS_IN_PROGRESS,
            ]))
            ->distinct()
            ->pluck('admission_attempt_id')
            ->all();

        $decisionAttemptIds = array_values(array_unique([...$attemptIds, ...$scheduledAttemptIds]));
        $latestDecisions = ConsultationDecision::query()
            ->whereIn('admission_attempt_id', $decisionAttemptIds)
            ->orderByDesc('id')
            ->get(['admission_attempt_id', 'decision_type', 'created_at'])
            ->unique('admission_attempt_id')
            ->keyBy('admission_attempt_id');

        $advisedIds = $latestDecisions
            ->filter(fn (ConsultationDecision $decision) => in_array($decision->decision_type, ['approve', 'modify', 'override'], true))
            ->keys()
            ->all();

        $deferredIds = $latestDecisions
            ->filter(fn (ConsultationDecision $decision) => $decision->decision_type === 'defer')
            ->keys()
            ->all();

        $openProgramFollowUps = ProgramFollowUp::query()
            ->whereIn('admission_attempt_id', $attemptIds)
            ->whereIn('status', ProgramFollowUp::OPEN_STATUSES)
            ->get(['id', 'admission_attempt_id', 'program_id', 'program_pool_id', 'status', 'reason', 'created_at', 'updated_at']);
        $declinedFollowUps = ProgramFollowUp::query()
            ->whereIn('admission_attempt_id', $attemptIds)
            ->where('status', ProgramFollowUp::STATUS_DECLINED_BY_PH)
            ->orderByDesc('id')
            ->get(['id', 'admission_attempt_id', 'program_id', 'program_pool_id', 'status', 'reason', 'resolved_at', 'created_at', 'updated_at']);
        $unresolvedDeclines = $declinedFollowUps
            ->filter(function (ProgramFollowUp $followUp) use ($latestDecisions): bool {
                $decision = $latestDecisions->get($followUp->admission_attempt_id);
                if ($decision === null || ! in_array($decision->decision_type, ['approve', 'modify', 'override'], true)) {
                    return true;
                }

                $declinedAt = $followUp->resolved_at ?? $followUp->updated_at ?? $followUp->created_at;

                return $decision->created_at === null
                    || $declinedAt === null
                    || $decision->created_at->lessThanOrEqualTo($declinedAt);
            })
            ->values();
        $outstandingProgramFollowUps = $openProgramFollowUps->concat($unresolvedDeclines);
        $openProgramFollowUpIds = $openProgramFollowUps->pluck('admission_attempt_id')->unique()->all();
        $declinedByPhIds = $unresolvedDeclines->pluck('admission_attempt_id')->unique()->all();
        $programFollowUpAttemptIds = $outstandingProgramFollowUps->pluck('admission_attempt_id')->unique()->all();
        $followUpReasonByAttempt = $outstandingProgramFollowUps
            ->groupBy('admission_attempt_id')
            ->map(fn ($followUps) => $followUps->pluck('reason')->filter()->unique()->implode('; '));

        $reAdvisingIds = array_values(array_unique([...$deferredIds, ...$programFollowUpAttemptIds]));

        $currentReadyDocuments = ResultDocument::query()
            ->whereIn('id', $attempts->pluck('current_result_document_id')->filter()->all())
            ->where('status', ResultDocument::STATUS_READY)
            ->get(['id', 'admission_attempt_id']);
        $currentReadyDocumentIds = $currentReadyDocuments->pluck('id')->all();
        $printedAttemptIds = ResultDocumentPrintReceipt::query()
            ->whereIn('admission_attempt_id', $attemptIds)
            ->whereIn('result_document_id', $currentReadyDocumentIds)
            ->where('operation', 'confirmed')
            ->where('is_current', true)
            ->pluck('admission_attempt_id')
            ->flip()
            ->all();
        $preparedAttemptIds = $currentReadyDocuments->pluck('admission_attempt_id')->flip()->all();
        $releasedAttemptIds = ResultRelease::query()
            ->whereIn('admission_attempt_id', $attemptIds)
            ->where('is_current', true)
            ->whereNull('unreleased_at')
            ->distinct()
            ->pluck('admission_attempt_id')
            ->flip()
            ->all();

        $isAttemptReady = static fn (int $attemptId): bool => (bool) ($scoringReadinessByAttempt[$attemptId]['ready'] ?? false);
        $attemptWorkspaceStatus = fn (AdmissionAttempt $attempt): array => [
            'appointment_status' => in_array($attempt->id, $publishedScheduledIds, true)
                ? 'scheduled'
                : (in_array($attempt->id, $anyScheduledIds, true) ? 'tentative' : 'unscheduled'),
            'document_status' => isset($preparedAttemptIds[$attempt->id]) ? 'prepared' : 'not_prepared',
            'print_status' => isset($printedAttemptIds[$attempt->id]) ? 'printed' : 'not_printed',
            'scoring_ready' => $isAttemptReady($attempt->id),
            'scoring_blocker' => $scoringReadinessByAttempt[$attempt->id]['reason'] ?? null,
            'release_day_readiness' => isset($printedAttemptIds[$attempt->id]) ? 'ready' : 'needs_print',
        ];

        $reAdvising = $attempts
            ->filter(function (AdmissionAttempt $attempt) use ($reAdvisingIds, $declinedByPhIds, $openProgramFollowUpIds, $advisedIds): bool {
                if (! in_array($attempt->id, $reAdvisingIds, true)) {
                    return false;
                }
                if (in_array($attempt->id, $declinedByPhIds, true) || in_array($attempt->id, $openProgramFollowUpIds, true)) {
                    return true;
                }

                return ! in_array($attempt->id, $advisedIds, true);
            })
            ->values()
            ->map(function (AdmissionAttempt $attempt) use ($declinedByPhIds, $deferredIds, $followUpReasonByAttempt, $attemptWorkspaceStatus): array {
                $reason = in_array($attempt->id, $declinedByPhIds, true)
                    ? 'program_declined_by_department'
                    : (in_array($attempt->id, $deferredIds, true) ? 'deferred' : 'program_follow_up');

                return [
                    ...$this->row($attempt),
                    ...$attemptWorkspaceStatus($attempt),
                    'reason' => $reason,
                    'follow_up_reason' => $followUpReasonByAttempt->get($attempt->id),
                ];
            })
            ->all();
        $pendingReleaseApplicants = $attempts
            ->filter(fn (AdmissionAttempt $attempt): bool => $isAttemptReady($attempt->id) && ! isset($releasedAttemptIds[$attempt->id]))
            ->map(function (AdmissionAttempt $attempt) use ($attemptWorkspaceStatus, $advisedIds, $latestDecisions): array {
                return [
                    ...$this->row($attempt),
                    ...$attemptWorkspaceStatus($attempt),
                    'advising_status' => in_array($attempt->id, $advisedIds, true) ? 'advised_awaiting_release' : 'awaiting_advising',
                    'decision_type' => $latestDecisions->get($attempt->id)?->decision_type,
                ];
            })->values();
        $releasedApplicants = $attempts
            ->filter(fn (AdmissionAttempt $attempt): bool => isset($releasedAttemptIds[$attempt->id]))
            ->map(fn (AdmissionAttempt $attempt): array => [
                ...$this->row($attempt),
                ...$attemptWorkspaceStatus($attempt),
            ])
            ->values();
        $scheduledApplicants = $pendingReleaseApplicants->filter(fn (array $row): bool => $row['appointment_status'] === 'scheduled')->values();
        $unscheduledApplicants = $pendingReleaseApplicants->reject(fn (array $row): bool => $row['appointment_status'] === 'scheduled')->values();

        $schedule = $sessionsToday->map(function (ConsultationSession $session) use ($latestDecisions, $printedAttemptIds, $todayStart, $todayEnd) {
            $assignments = $session->assignments()
                ->whereIn('status', [
                    ConsultationAssignment::STATUS_ASSIGNED,
                    ConsultationAssignment::STATUS_CONSULTED,
                ])
                ->with('admissionAttempt.identity')
                ->orderBy('id')
                ->get();

            $timeRange = $session->scheduled_at !== null
                ? $session->scheduled_at->format('h:i A').' – '.$session->scheduled_at->copy()->addMinutes(90)->format('h:i A')
                : null;
            $isOpenedEarly = $session->status === ConsultationSession::STATUS_IN_PROGRESS
                && ($session->scheduled_at === null || ! $session->scheduled_at->betweenIncluded($todayStart, $todayEnd));

            return [
                'id' => $session->id,
                'code' => $session->code,
                'scheduled_at' => $session->scheduled_at?->toIso8601String(),
                'time' => $session->scheduled_at?->format('H:i'),
                'time_range' => $timeRange,
                'room' => $session->room?->name,
                'capacity' => $session->room?->capacity,
                'status' => $session->status,
                'version' => (int) $session->version,
                'is_opened_early' => $isOpenedEarly,
                'assigned_count' => $assignments->count(),
                'applicants' => $assignments->map(function (ConsultationAssignment $assignment) use ($latestDecisions, $printedAttemptIds): array {
                    $attempt = $assignment->admissionAttempt;
                    $decision = $attempt === null ? null : $latestDecisions->get($attempt->id);
                    $outcome = match ($decision?->decision_type) {
                        'approve', 'modify', 'override' => 'advised',
                        'defer' => 'deferred',
                        'refer' => 'referred',
                        default => $assignment->status === ConsultationAssignment::STATUS_CONSULTED ? 'advised' : 'pending',
                    };

                    return [
                        'attempt_id' => $attempt?->id,
                        'reference_code' => $attempt?->reference_code,
                        'name' => $attempt?->identity ? $attempt->identity->last_name.', '.$attempt->identity->first_name : null,
                        'outcome' => $outcome,
                        'advised' => $outcome === 'advised',
                        'status_label' => match ($outcome) {
                            'advised' => 'Advised',
                            'deferred' => 'Deferred',
                            'referred' => 'Referred to Department',
                            default => $assignment->status === ConsultationAssignment::STATUS_CONSULTED ? 'In Progress' : 'Scheduled',
                        },
                        'assignment_status' => $assignment->status,
                        'release_day_readiness' => $attempt !== null && isset($printedAttemptIds[$attempt->id]) ? 'ready' : 'needs_print',
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return [
            'stats' => [
                'today_scheduled' => count($scheduledAttemptIds),
                'pending_release' => $pendingReleaseApplicants->count(),
                'released' => $releasedApplicants->count(),
                'followups' => count($reAdvising),
            ],
            'today_schedule' => $schedule,
            'pending_release_applicants' => $pendingReleaseApplicants->all(),
            'scheduled_applicants' => $scheduledApplicants->all(),
            'unscheduled_applicants' => $unscheduledApplicants->all(),
            'released_applicants' => $releasedApplicants->all(),
            'followup_applicants' => $reAdvising,
            'as_of' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDashboard(): array
    {
        return [
            'stats' => [
                'today_scheduled' => 0,
                'pending_release' => 0,
                'released' => 0,
                'followups' => 0,
            ],
            'today_schedule' => [],
            'pending_release_applicants' => [],
            'scheduled_applicants' => [],
            'unscheduled_applicants' => [],
            'released_applicants' => [],
            'followup_applicants' => [],
            'as_of' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{attempt_id: int, reference_code: string, name: ?string, url: string}
     */
    private function row(AdmissionAttempt $attempt): array
    {
        return [
            'attempt_id' => $attempt->id,
            'reference_code' => $attempt->reference_code,
            'name' => $attempt->identity ? $attempt->identity->last_name.', '.$attempt->identity->first_name : null,
            'url' => route('admission-support.show', $attempt->id),
        ];
    }
}
