<?php

namespace App\Assessment\Queries;

use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class UnscoredExamineesQuery
{
    /**
     * @return array{
     *     unscored_examinees_count: int,
     *     completed_sessions_count: int,
     *     sessions_awaiting_scores: list<array{
     *         session_id: int,
     *         session_code: string,
     *         exam_date: string,
     *         room_name: string,
     *         unscored_count: int,
     *     }>,
     *     as_of: string,
     * }
     */
    public function handle(int $institutionId, ?int $cycleId = null): array
    {
        $sessionsQuery = ExamSession::query()->forWorkspace()
            ->where('status', ExamSession::STATUS_COMPLETED)
            ->whereHas('academicCycle', function (Builder $query) use ($institutionId, $cycleId): void {
                $query->where('institution_id', $institutionId);

                if ($cycleId === null) {
                    $query->where('is_active', true);
                } else {
                    $query->whereKey($cycleId);
                }
            });

        $completedSessionsCount = (int) (clone $sessionsQuery)->count();
        $sessions = $sessionsQuery
            ->with('room:id,name')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get(['id', 'code', 'starts_at', 'room_id']);

        $sessionIds = $sessions->modelKeys();
        $unscoredBySession = $sessionIds === []
            ? collect()
            : ExamAssignment::query()
                ->select('exam_session_id')
                ->selectRaw('COUNT(DISTINCT exam_assignments.admission_attempt_id) AS unscored_count')
                ->whereIn('exam_session_id', $sessionIds)
                ->active()
                ->whereHas(
                    'attendanceRecord',
                    fn (Builder $query): Builder => $query->where('outcome', ExamAttendanceRecord::OUTCOME_PRESENT),
                )
                ->whereNotIn('admission_attempt_id', ScoreRecord::query()
                    ->select('admission_attempt_id')
                    ->whereNotNull('admission_attempt_id'))
                ->whereNotIn('id', AssessmentAdministration::query()
                    ->active()
                    ->where(function (Builder $query): void {
                        $query->whereHas('currentRawResultRevisions')
                            ->orWhereHas('currentConversionRevision');
                    })
                    ->select('exam_assignment_id'))
                ->groupBy('exam_session_id')
                ->pluck('unscored_count', 'exam_session_id');

        $awaitingScores = [];
        $unscoredCount = 0;

        foreach ($sessions as $session) {
            $sessionUnscoredCount = (int) $unscoredBySession->get($session->id, 0);
            if ($sessionUnscoredCount === 0) {
                continue;
            }

            $unscoredCount += $sessionUnscoredCount;
            $awaitingScores[] = [
                'session_id' => (int) $session->id,
                'session_code' => $session->code,
                'exam_date' => $session->starts_at?->toDateString() ?? '',
                'room_name' => (string) ($session->room?->name ?? ''),
                'unscored_count' => $sessionUnscoredCount,
            ];
        }

        return [
            'unscored_examinees_count' => $unscoredCount,
            'completed_sessions_count' => $completedSessionsCount,
            'sessions_awaiting_scores' => $awaitingScores,
            'as_of' => Carbon::now()->toIso8601String(),
        ];
    }
}
