<?php

namespace App\Application\Capabilities\Handlers\Exams;

use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ProctorDuty;
use App\Models\Foundation\AcademicCycle;

/**
 * Shared exam session projections for Kernel reads (mirrors controller payloads).
 */
final class ExamSessionProjection
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $status = null, ?int $academicCycleId = null, ?string $search = null): array
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
            ->latest('starts_at');

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        if ($academicCycleId) {
            $query->where('academic_cycle_id', $academicCycleId);
        } else {
            $active = AcademicCycle::active();
            if ($active !== null) {
                $query->where('academic_cycle_id', $active->id);
            }
        }

        if (is_string($search) && $search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('code', 'like', "%{$search}%")
                    ->orWhereHas('room', fn ($room) => $room->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"));
            });
        }

        return $query->limit(100)->get()->map(fn (ExamSession $session) => $this->listPayload($session))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function show(int $sessionId): array
    {
        $session = ExamSession::query()
            ->with([
                'room:id,code,name,capacity',
                'academicCycle:id,school_year,semester,name,is_active',
                'proctorDuties' => fn ($q) => $q->where('status', ProctorDuty::STATUS_ACTIVE)->with('user:id,name'),
                'assignments' => fn ($q) => $q->active()->with('admissionAttempt.identity'),
            ])
            ->withCount([
                'assignments as assigned_count' => fn ($q) => $q->active(),
            ])
            ->findOrFail($sessionId);

        $payload = $this->listPayload($session);
        $payload['version'] = $session->version;
        $payload['academic_cycle_id'] = $session->academic_cycle_id;
        $payload['room_id'] = $session->room_id;
        $payload['assignments'] = $session->assignments->map(function (ExamAssignment $assignment): array {
            $attempt = $assignment->admissionAttempt;

            return [
                'id' => $assignment->id,
                'admission_attempt_id' => $assignment->admission_attempt_id,
                'reference_code' => $attempt?->reference_code,
                'source' => $assignment->source,
                'status' => $assignment->status,
            ];
        })->values()->all();

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayload(ExamSession $session): array
    {
        $assigned = (int) ($session->assigned_count ?? 0);
        $block = null;
        if ($session->status === ExamSession::STATUS_DRAFT) {
            if ($session->room === null) {
                $block = 'Room is required before publish.';
            } elseif ($session->starts_at === null || $session->effective_end_at === null) {
                $block = 'Schedule date and time are required.';
            } elseif ($assigned < 1) {
                $block = 'Assign at least one applicant before publishing.';
            }
        }

        return [
            'id' => $session->id,
            'code' => $session->code,
            'status' => $session->status,
            'booking_mode' => $session->booking_mode ?? ExamSession::BOOKING_STAFF_ONLY,
            'reservation_opened_at' => $session->reservation_opened_at?->toIso8601String(),
            'reservation_closed_at' => $session->reservation_closed_at?->toIso8601String(),
            'starts_at' => $session->starts_at?->toIso8601String(),
            'effective_end_at' => $session->effective_end_at?->toIso8601String(),
            'assigned_count' => $assigned,
            'room' => $session->room ? [
                'id' => $session->room->id,
                'code' => $session->room->code,
                'name' => $session->room->name,
                'capacity' => $session->room->capacity,
            ] : null,
            'academic_cycle' => $session->academicCycle ? [
                'id' => $session->academicCycle->id,
                'label' => $session->academicCycle->staffLabel(),
            ] : null,
            'is_publishable' => $session->status === ExamSession::STATUS_DRAFT && $block === null,
            'publish_block_reason' => $block,
        ];
    }
}
