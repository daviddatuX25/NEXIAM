<?php

namespace App\Consultation\Queries;

use App\Admission\Support\AttemptProgramFacts;
use App\Consultation\Support\AttemptResultReadiness;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Release\ConsultationDecision;

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
    public function for(?string $cycleCode = null): array
    {
        $cycle = $cycleCode === null ? null : AcademicCycle::query()
            ->where('code', $cycleCode)
            ->where('is_active', true)
            ->first();

        $attempts = AdmissionAttempt::query()
            ->when($cycle !== null, fn ($q) => $q->where('academic_cycle_id', $cycle->id))
            ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->get();

        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $sessionsToday = ConsultationSession::query()
            ->whereBetween('scheduled_at', [$todayStart, $todayEnd])
            ->where('status', '!=', ConsultationSession::STATUS_CANCELLED)
            ->orderBy('scheduled_at')
            ->with('room')
            ->get();

        $scheduledAttemptIds = ConsultationAssignment::query()
            ->whereIn('status', ConsultationAssignment::ACTIVE_STATUSES)
            ->whereIn('consultation_session_id', $sessionsToday->pluck('id'))
            ->distinct()
            ->pluck('admission_attempt_id')
            ->all();

        $anyScheduledIds = ConsultationAssignment::query()
            ->whereIn('status', ConsultationAssignment::ACTIVE_STATUSES)
            ->distinct()
            ->pluck('admission_attempt_id')
            ->all();

        $advisedIds = ConsultationDecision::query()
            ->whereIn('decision_type', ['approve', 'modify', 'override'])
            ->distinct()
            ->pluck('admission_attempt_id')
            ->all();

        $pendingAdvise = collect($anyScheduledIds)
            ->reject(fn ($id) => in_array($id, $advisedIds, true))
            ->values()
            ->all();

        $notYetScheduled = $attempts
            ->filter(fn (AdmissionAttempt $a) => AttemptResultReadiness::isReady($a))
            ->filter(fn (AdmissionAttempt $a) => ! in_array($a->id, $anyScheduledIds, true))
            ->filter(fn (AdmissionAttempt $a) => ! in_array($a->id, $advisedIds, true))
            ->values()
            ->map(fn (AdmissionAttempt $a) => $this->row($a))
            ->all();

        $schedule = $sessionsToday->map(function (ConsultationSession $session) use ($advisedIds) {
            $assignments = $session->assignments()
                ->whereIn('status', ConsultationAssignment::ACTIVE_STATUSES)
                ->with('admissionAttempt.identity')
                ->orderBy('id')
                ->get();

            return [
                'id' => $session->id,
                'code' => $session->code,
                'scheduled_at' => $session->scheduled_at?->toIso8601String(),
                'time' => $session->scheduled_at?->format('H:i'),
                'room' => $session->room?->name,
                'capacity' => $session->room?->capacity,
                'status' => $session->status,
                'assigned_count' => $assignments->count(),
                'applicants' => $assignments->map(function (ConsultationAssignment $assignment) use ($advisedIds) {
                    $attempt = $assignment->admissionAttempt;

                    return [
                        'attempt_id' => $attempt?->id,
                        'reference_code' => $attempt?->reference_code,
                        'name' => $attempt?->identity ? $attempt->identity->last_name.', '.$attempt->identity->first_name : null,
                        'advised' => $attempt !== null && in_array($attempt->id, $advisedIds, true),
                        'assignment_status' => $assignment->status,
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        $openSessions = ConsultationSession::query()
            ->when($cycle !== null, fn ($q) => $q->where('academic_cycle_id', $cycle->id))
            ->whereIn('status', [
                ConsultationSession::STATUS_PUBLISHED,
                ConsultationSession::STATUS_IN_PROGRESS,
            ])
            ->count();

        $closedSessions = ConsultationSession::query()
            ->when($cycle !== null, fn ($q) => $q->where('academic_cycle_id', $cycle->id))
            ->where('status', ConsultationSession::STATUS_COMPLETED)
            ->count();

        return [
            'stats' => [
                'today_scheduled' => count($scheduledAttemptIds),
                'pending_advise' => count($pendingAdvise),
                'not_yet_scheduled' => count($notYetScheduled),
                'sessions_open' => $openSessions,
                'sessions_closed' => $closedSessions,
            ],
            'today_schedule' => $schedule,
            'not_yet_scheduled' => $notYetScheduled,
            'as_of' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{attempt_id: int, reference_code: string, name: ?string, ready: bool}
     */
    private function row(AdmissionAttempt $attempt): array
    {
        return [
            'attempt_id' => $attempt->id,
            'reference_code' => $attempt->reference_code,
            'name' => $attempt->identity ? $attempt->identity->last_name.', '.$attempt->identity->first_name : null,
            'ready' => AttemptResultReadiness::isReady($attempt),
        ];
    }
}
