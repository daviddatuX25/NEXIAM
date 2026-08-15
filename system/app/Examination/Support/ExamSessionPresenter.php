<?php

namespace App\Examination\Support;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ProctorDuty;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;

/**
 * Shared Inertia payload shapes and option catalogs for the exam-session
 * scheduling and monitoring surfaces. Keeps controllers and queries free of
 * presentation assembly; publishBlockReason is a UX pre-check only — the
 * PublishExamSession action is the authority on publishability.
 */
class ExamSessionPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function listPayload(ExamSession $session): array
    {
        return [
            'id' => $session->id,
            'code' => $session->code,
            'status' => $session->status,
            'booking_mode' => $session->booking_mode ?? ExamSession::BOOKING_STAFF_ONLY,
            'reservation_opened_at' => $session->reservation_opened_at?->toIso8601String(),
            'reservation_closed_at' => $session->reservation_closed_at?->toIso8601String(),
            'starts_at' => $session->starts_at?->toIso8601String(),
            'effective_end_at' => $session->effective_end_at?->toIso8601String(),
            'date' => $session->starts_at?->toDateString(),
            'start_time' => $session->starts_at?->format('H:i'),
            'end_time' => $session->effective_end_at?->format('H:i'),
            'assigned_count' => (int) ($session->assigned_count ?? 0),
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
            'proctors' => $session->proctorDuties
                ->map(fn (ProctorDuty $duty) => [
                    'duty_id' => $duty->id,
                    'id' => $duty->user?->id,
                    'name' => $duty->user?->name,
                    'email' => $duty->user?->email,
                ])
                ->filter(fn (array $row) => filled($row['name']))
                ->values()
                ->all(),
            'is_publishable' => $session->status === ExamSession::STATUS_DRAFT
                && $this->publishBlockReason($session) === null,
            'publish_block_reason' => $session->status === ExamSession::STATUS_DRAFT
                ? $this->publishBlockReason($session)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detailPayload(ExamSession $session): array
    {
        return [
            ...$this->listPayload($session),
            'version' => $session->version,
            'academic_cycle_id' => $session->academic_cycle_id,
            'room_id' => $session->room_id,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function statusOptions(): array
    {
        return [
            ['value' => ExamSession::STATUS_DRAFT, 'label' => 'Draft'],
            ['value' => ExamSession::STATUS_PUBLISHED, 'label' => 'Published'],
            ['value' => ExamSession::STATUS_IN_PROGRESS, 'label' => 'In progress'],
            ['value' => ExamSession::STATUS_COMPLETED, 'label' => 'Completed'],
            ['value' => ExamSession::STATUS_CANCELLED, 'label' => 'Cancelled'],
        ];
    }

    /**
     * @return list<array{id: int, label: string, is_active: bool}>
     */
    public function cycleOptions(): array
    {
        return AcademicCycle::query()
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AcademicCycle $cycle) => [
                'id' => $cycle->id,
                'label' => $cycle->staffLabel(),
                'is_active' => (bool) $cycle->is_active,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, label: string, capacity: int}>
     */
    public function roomOptions(): array
    {
        return Room::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'label' => trim(($room->name ?: $room->code).' (cap. '.$room->capacity.')'),
                'capacity' => $room->capacity,
            ])
            ->all();
    }

    public function publishBlockReason(ExamSession $session): ?string
    {
        if ($session->status !== ExamSession::STATUS_DRAFT) {
            return 'Only draft sessions may be published.';
        }

        if ($session->room === null) {
            return 'Room is required before publish.';
        }

        if ($session->starts_at === null || $session->effective_end_at === null) {
            return 'Schedule date and time are required.';
        }

        $assigned = (int) ($session->assigned_count
            ?? $session->assignments()->active()->count());

        if ($assigned < 1) {
            return 'Assign at least one applicant before publishing.';
        }

        return null;
    }

    public function attemptName(?AdmissionAttempt $attempt): string
    {
        if ($attempt === null) {
            return '—';
        }

        $identity = $attempt->identity;
        if ($identity === null) {
            return $attempt->reference_code;
        }

        return trim(implode(' ', array_filter([
            $identity->first_name,
            $identity->middle_name,
            $identity->last_name,
            $identity->suffix,
        ]))) ?: $attempt->reference_code;
    }
}
