<?php

namespace App\Consultation\Queries;

use App\Assessment\Support\IndividualScoringReadiness;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Release\ResultDocument;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use Illuminate\Database\Eloquent\Builder;

final class ReleaseDaySessionMonitoringQuery
{
    public function __construct(private readonly IndividualScoringReadiness $scoringReadiness) {}

    /**
     * @return array{
     *     total_count: int,
     *     items: list<array{
     *         id: int,
     *         code: string,
     *         scheduled_at: string,
     *         time_range: string,
     *         room: ?string,
     *         status: string,
     *         version: int,
     *         assigned_count: int,
     *         href: string,
     *         applicants: list<array{
     *             attempt_id: int,
     *             reference_code: string,
     *             name: string,
     *             release_day_readiness: string,
     *             scoring_blocker: ?string,
     *             url: string
     *         }>
     *     }>
     * }
     */
    public function for(int $institutionId, ?string $activeCycleCode): array
    {
        if ($activeCycleCode === null) {
            return ['total_count' => 0, 'items' => []];
        }

        $cycleId = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->where('code', $activeCycleCode)
            ->where('is_active', true)
            ->value('id');

        if ($cycleId === null) {
            return ['total_count' => 0, 'items' => []];
        }

        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $query = ConsultationSession::query()
            ->where('academic_cycle_id', $cycleId)
            ->where('status', ConsultationSession::STATUS_PUBLISHED)
            ->whereNotNull('scheduled_at')
            ->where(function (Builder $query) use ($todayStart, $todayEnd): void {
                $query->where('scheduled_at', '<', $todayStart)
                    ->orWhere('scheduled_at', '>', $todayEnd);
            });

        $totalCount = (clone $query)->count();

        $sessions = (clone $query)
            ->with([
                'room:id,code,name',
                'assignments' => fn ($assignments) => $assignments
                    ->active()
                    ->whereHas('admissionAttempt', fn (Builder $attempts): Builder => $attempts->forWorkspace())
                    ->with(['admissionAttempt:id,applicant_identity_id,reference_code,current_result_document_id', 'admissionAttempt.identity:id,first_name,last_name'])
                    ->orderBy('id'),
            ])
            ->withCount([
                'assignments as assigned_count' => fn (Builder $assignments): Builder => $assignments
                    ->active()
                    ->whereHas('admissionAttempt', fn (Builder $attempts): Builder => $attempts->forWorkspace()),
            ])
            ->orderByRaw('CASE WHEN consultation_sessions.scheduled_at < ? THEN 1 ELSE 0 END', [$todayStart])
            ->orderBy('scheduled_at')
            ->get(['id', 'code', 'status', 'version', 'scheduled_at', 'room_id']);

        $allAttempts = $sessions
            ->flatMap(fn (ConsultationSession $session) => $session->assignments->pluck('admissionAttempt')->filter())
            ->values();
        $attemptIds = $allAttempts->pluck('id')->unique()->all();
        $readinessByAttempt = $this->scoringReadiness->forAttempts($allAttempts);
        $currentDocumentIds = $allAttempts->pluck('current_result_document_id')->filter()->unique()->all();

        $readyDocumentIds = $currentDocumentIds === []
            ? []
            : ResultDocument::query()
                ->whereIn('id', $currentDocumentIds)
                ->where('status', ResultDocument::STATUS_READY)
                ->pluck('id')
                ->all();

        $printedAttemptIds = ($attemptIds === [] || $readyDocumentIds === [])
            ? []
            : ResultDocumentPrintReceipt::query()
                ->whereIn('admission_attempt_id', $attemptIds)
                ->whereIn('result_document_id', $readyDocumentIds)
                ->where('operation', 'confirmed')
                ->where('is_current', true)
                ->pluck('admission_attempt_id')
                ->flip()
                ->all();

        $items = $sessions
            ->map(function (ConsultationSession $session) use ($printedAttemptIds, $readinessByAttempt): array {
                $timeRange = $session->scheduled_at->format('h:i A').' – '.$session->scheduled_at->copy()->addMinutes(90)->format('h:i A');

                $applicants = $session->assignments
                    ->map(function (ConsultationAssignment $assignment) use ($session, $printedAttemptIds, $readinessByAttempt): ?array {
                        $attempt = $assignment->admissionAttempt;
                        if ($attempt === null) {
                            return null;
                        }

                        return [
                            'attempt_id' => (int) $attempt->id,
                            'reference_code' => $attempt->reference_code,
                            'name' => $attempt->identity
                                ? $attempt->identity->last_name.', '.$attempt->identity->first_name
                                : $attempt->reference_code,
                            'release_day_readiness' => ! $readinessByAttempt[$attempt->id]['ready']
                                ? 'scoring_incomplete'
                                : (isset($printedAttemptIds[$attempt->id]) ? 'ready' : 'needs_print'),
                            'scoring_blocker' => $readinessByAttempt[$attempt->id]['ready']
                                ? null
                                : $readinessByAttempt[$attempt->id]['reason'],
                            'url' => route('admission-support.show', [
                                'attempt' => $attempt->id,
                                'session_id' => $session->id,
                            ]),
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'id' => (int) $session->id,
                    'code' => $session->code,
                    'scheduled_at' => $session->scheduled_at->toIso8601String(),
                    'time_range' => $timeRange,
                    'room' => $session->room?->name ?: $session->room?->code,
                    'status' => $session->status,
                    'version' => (int) $session->version,
                    'assigned_count' => (int) $session->assigned_count,
                    'href' => route('consultation.sessions.show', $session),
                    'applicants' => $applicants,
                ];
            })
            ->values()
            ->all();

        return [
            'total_count' => $totalCount,
            'items' => $items,
        ];
    }
}
