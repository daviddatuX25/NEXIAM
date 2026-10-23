<?php

namespace App\Application\Capabilities\Handlers\Exams;

use App\Models\Examination\ExamSession;
use Illuminate\Contracts\Auth\Authenticatable;

final class ExamSessionsTodayHandler
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{today_count: int, sessions: list<array{id: int, code: string, status: string, starts_at: string|null, room_code: string, room_capacity: int, assigned_count: int}>, as_of: string}
     */
    public function handle(array $input = [], ?Authenticatable $actor = null): array
    {
        $actor ??= auth()->user();
        $institutionId = ($actor !== null && method_exists($actor, 'actorScope'))
            ? (int) $actor->actorScope()->institutionId
            : 0;
        $asOf = now();
        $todayStart = $asOf->copy()->startOfDay();
        $todayEnd = $asOf->copy()->endOfDay();

        if ($institutionId < 1) {
            return [
                'today_count' => 0,
                'sessions' => [],
                'as_of' => $asOf->toIso8601String(),
            ];
        }

        $baseQuery = ExamSession::query()
            ->whereHas('academicCycle', fn ($query) => $query->where('institution_id', $institutionId))
            ->where('status', '!=', ExamSession::STATUS_CANCELLED)
            ->with('room')
            ->withCount(['assignments as assigned_count' => fn ($query) => $query->where('status', 'assigned')]);
        $todaySessions = (clone $baseQuery)
            ->whereBetween('starts_at', [$todayStart, $todayEnd])
            ->orderBy('starts_at')
            ->get();
        $todayCount = $todaySessions->count();
        $sessions = $todayCount > 0
            ? $todaySessions
            : (clone $baseQuery)
                ->where('starts_at', '>=', $todayStart)
                ->orderBy('starts_at')
                ->limit(5)
                ->get();

        return [
            'today_count' => $todayCount,
            'sessions' => $sessions->map(fn (ExamSession $session): array => [
                'id' => $session->id,
                'code' => $session->code,
                'status' => $session->status,
                'starts_at' => $session->starts_at?->toIso8601String(),
                'room_code' => $session->room?->code ?? 'TBD',
                'room_capacity' => (int) ($session->room?->capacity ?? 0),
                'assigned_count' => (int) $session->assigned_count,
            ])->all(),
            'as_of' => $asOf->toIso8601String(),
        ];
    }
}
