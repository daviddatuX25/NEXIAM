<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\ExamSession;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ExamSchedulingService
{
    public function __construct(
        protected AuditService $auditService,
        protected ApplicationPipelineService $pipelineService
    ) {}

    /**
     * Create a new draft exam session with conflict checking.
     *
     * @throws \RuntimeException on conflict or validation failure
     */
    public function createDraftSession(array $data, User $creator): ExamSession
    {
        $activeAcademicYear = AcademicYear::active();
        if (! $activeAcademicYear) {
            throw new \RuntimeException('No active season. Activate a season first.');
        }

        $roomId = (int) ($data['room_id'] ?? 0);
        $date = $data['date'] ?? null;
        $startTime = $data['start_time'] ?? null;
        $endTime = $data['end_time'] ?? null;

        if (! $roomId || ! $date || ! $startTime) {
            throw new \RuntimeException('New session must have room_id, date, and start_time.');
        }

        $room = Room::query()->where('is_active', true)->findOrFail($roomId);

        $applicantIds = array_values(array_unique(array_map('intval', $data['applicant_ids'] ?? [])));
        if (count($applicantIds) > ($room->capacity ?? 0)) {
            throw new \RuntimeException("Room {$roomId} capacity exceeded.");
        }

        if (ExamSession::hasRoomConflict($roomId, $date, $startTime, $endTime, null)) {
            throw new \RuntimeException("Room {$roomId} has a conflict on {$date} at {$startTime}.");
        }

        $session = ExamSession::create([
            'academic_year_id' => $activeAcademicYear->id,
            'room_id' => $roomId,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => ExamSession::STATUS_DRAFT,
            'created_by' => $creator->id,
            'type' => ExamSession::TYPE_SCHEDULED,
        ]);

        $this->auditService->log('exam_session.created', ExamSession::class, $session->id, [], [
            'room_id' => $session->room_id,
            'date' => $session->date?->format('Y-m-d'),
            'type' => $session->type,
        ]);

        if (! empty($applicantIds)) {
            $this->assignApplicants($session, $applicantIds);
        }

        return $session;
    }

    /**
     * Update an existing draft session (room, date, time) with conflict checking.
     *
     * @throws \RuntimeException on conflict or non-draft status
     */
    public function updateDraftSession(ExamSession $session, array $changes): ExamSession
    {
        if ($session->status !== ExamSession::STATUS_DRAFT) {
            throw new \RuntimeException("Exam session {$session->id} is not a draft session.");
        }

        $updateData = [];
        if (isset($changes['room_id'])) {
            $roomId = (int) $changes['room_id'];
            $room = Room::query()->where('is_active', true)->findOrFail($roomId);
            $updateData['room_id'] = $roomId;
        }

        if (isset($changes['date'])) {
            $updateData['date'] = $changes['date'];
        }
        if (isset($changes['start_time'])) {
            $updateData['start_time'] = $changes['start_time'];
        }
        if (isset($changes['end_time'])) {
            $updateData['end_time'] = $changes['end_time'];
        }

        if (! empty($updateData)) {
            $checkRoomId = $updateData['room_id'] ?? $session->room_id;
            $checkDate = $updateData['date'] ?? $session->date?->format('Y-m-d');
            $checkStartTime = $updateData['start_time'] ?? $session->start_time;
            $checkEndTime = array_key_exists('end_time', $updateData) ? $updateData['end_time'] : $session->end_time;

            if (ExamSession::hasRoomConflict($checkRoomId, $checkDate, $checkStartTime, $checkEndTime, $session->id)) {
                throw new \RuntimeException("Room {$checkRoomId} has a conflict on {$checkDate} at {$checkStartTime}.");
            }

            $session->update($updateData);

            $this->auditService->log('exam_session.updated', ExamSession::class, $session->id, [], $updateData);
        }

        if (isset($changes['applicant_ids'])) {
            $applicantIds = array_values(array_unique(array_map('intval', $changes['applicant_ids'] ?? [])));
            if (! empty($applicantIds)) {
                $this->assignApplicants($session, $applicantIds);
            }
        }

        return $session;
    }

    /**
     * Delete a draft session, detach applicants, revert their pipeline status.
     *
     * @throws \RuntimeException if session is not draft
     */
    public function deleteDraftSession(ExamSession $session): void
    {
        if ($session->status !== ExamSession::STATUS_DRAFT) {
            throw new \RuntimeException("Exam session {$session->id} is not a draft session.");
        }

        $applicants = $session->applicants()->with('application')->get();

        $applicants->each(function (Applicant $applicant) {
            if ($applicant->application && $applicant->application->pipeline_status === 'draft_scheduled') {
                $this->pipelineService->forceSet($applicant->application, 'accepted');
            }
        });

        $this->auditService->log('exam_session.deleted', ExamSession::class, $session->id, [
            'room_id' => $session->room_id,
            'date' => $session->date?->format('Y-m-d'),
        ]);

        $session->applicants()->detach();
        $session->delete();
    }

    /**
     * Assign applicants to a session with capacity and eligibility checks.
     *
     * @throws \RuntimeException on capacity exceeded or ineligible applicant
     */
    public function assignApplicants(ExamSession $session, array $applicantIds): void
    {
        $applicantIds = array_values(array_unique(array_map('intval', $applicantIds)));
        if (empty($applicantIds)) {
            return;
        }

        $assignableIds = Applicant::query()
            ->whereHas('application', fn ($q) => $q->where('status', 'accepted'))
            ->whereDoesntHave('examSessions', fn ($q) => $q->whereNotIn('status', [ExamSession::STATUS_CANCELLED]))
            ->pluck('id')
            ->all();

        $alreadyAssignedToThisSession = DB::table('exam_session_applicant')
            ->where('exam_session_id', $session->id)
            ->pluck('applicant_id')
            ->all();

        foreach ($applicantIds as $id) {
            if (! in_array($id, $assignableIds, true) && ! in_array($id, $alreadyAssignedToThisSession, true)) {
                throw new \RuntimeException("Applicant {$id} is not assignable (must be accepted and not yet in any active session).");
            }
        }

        $alreadyAttached = $session->applicants()->pluck('applicants.id')->all();
        $toAttach = array_diff($applicantIds, $alreadyAttached);

        $currentCount = $session->applicants()->count();
        $capacity = $session->room?->capacity ?? 0;
        if ($currentCount + count($toAttach) > $capacity) {
            throw new \RuntimeException("Session {$session->id} would exceed room capacity.");
        }

        if (! empty($toAttach)) {
            $session->applicants()->attach($toAttach);

            $newApplicants = Applicant::whereIn('id', $toAttach)
                ->with('application')
                ->get();

            $newApplicants->each(function (Applicant $applicant) use ($session) {
                if ($applicant->application) {
                    $this->pipelineService->transition($applicant->application, 'draft_scheduled', [
                        'session_id' => $session->id,
                    ]);
                }
            });

            $this->auditService->log('exam_session.applicants_assigned', ExamSession::class, $session->id, [], [
                'applicant_count' => count($toAttach),
            ]);
        }
    }
}
