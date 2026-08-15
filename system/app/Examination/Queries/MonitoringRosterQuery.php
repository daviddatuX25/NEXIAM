<?php

namespace App\Examination\Queries;

use App\Examination\Support\ExamSessionPresenter;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ProctorDuty;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Exam Monitoring (conduct phase, D-015/D-016): duty-scoped session list and
 * the per-session roster projection with attendance + submission state.
 */
class MonitoringRosterQuery
{
    public function __construct(private readonly ExamSessionPresenter $presenter)
    {
    }

    public function hasAnyDuty(User $user): bool
    {
        return ProctorDuty::query()
            ->where('user_id', $user->id)
            ->where('status', ProctorDuty::STATUS_ACTIVE)
            ->exists();
    }

    /**
     * Conduct-phase sessions. Test Administrators see all; duty-holders see
     * only the sessions where they carry an active duty (D-015).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function sessionsFor(User $user, bool $canManage): Collection
    {
        $query = ExamSession::query()
            ->with([
                'room:id,code,name,capacity',
                'academicCycle:id,school_year,semester,name,is_active',
                'proctorDuties' => fn ($q) => $q->where('status', ProctorDuty::STATUS_ACTIVE)->with('user:id,name'),
            ])
            ->withCount([
                'assignments as assigned_count' => fn ($q) => $q->active(),
            ])
            ->whereIn('status', [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS])
            ->latest('starts_at');

        if (! $canManage) {
            $query->whereHas('proctorDuties', fn ($q) => $q
                ->where('user_id', $user->id)
                ->where('status', ProctorDuty::STATUS_ACTIVE));
        }

        return $query->limit(100)->get()->map(
            fn (ExamSession $session) => $this->presenter->listPayload($session)
        );
    }

    /**
     * @return array{session: array<string, mixed>, roster: Collection<int, array<string, mixed>>}
     */
    public function forSession(ExamSession $session): array
    {
        $session->load([
            'room:id,code,name,capacity',
            'academicCycle:id,school_year,semester,name,is_active',
            'proctorDuties' => fn ($q) => $q->where('status', ProctorDuty::STATUS_ACTIVE)->with('user:id,name'),
            'assignments' => fn ($q) => $q->active()->with([
                'admissionAttempt.identity',
                'attendanceRecord',
                'submissionRecord',
            ]),
        ]);

        $roster = $session->assignments->map(fn (ExamAssignment $assignment) => [
            'id' => $assignment->id,
            'reference_number' => $assignment->admissionAttempt?->reference_code,
            'name' => $this->presenter->attemptName($assignment->admissionAttempt),
            'source' => $assignment->source,
            'attendance' => $assignment->attendanceRecord?->outcome ?? null,
            'attendance_recorded_at' => $assignment->attendanceRecord?->recorded_at?->toIso8601String(),
            'submitted' => $assignment->submissionRecord !== null,
            'submitted_at' => $assignment->submissionRecord?->submitted_at?->toIso8601String(),
        ])->values();

        return [
            'session' => $this->presenter->detailPayload($session),
            'roster' => $roster,
        ];
    }
}
