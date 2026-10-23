<?php

namespace App\Consultation\Support;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;

/**
 * Shared Inertia payload shapes and option catalogs for the consultation
 * session surfaces. publishBlockReason is a UX pre-check only — the
 * PublishConsultationSession action is the authority on publishability.
 */
class ConsultationSessionPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function listPayload(ConsultationSession $session): array
    {
        return [
            'id' => $session->id,
            'code' => $session->code,
            'status' => $session->status,
            'booking_mode' => $session->booking_mode ?? ConsultationSession::BOOKING_STAFF_ONLY,
            'auto_assign_enabled' => (bool) $session->auto_assign_enabled,
            'reservation_opened_at' => $session->reservation_opened_at?->toIso8601String(),
            'reservation_closed_at' => $session->reservation_closed_at?->toIso8601String(),
            'scheduled_at' => $session->scheduled_at?->toIso8601String(),
            'date' => $session->scheduled_at?->toDateString(),
            'time' => $session->scheduled_at?->format('H:i'),
            'published_at' => $session->published_at?->toIso8601String(),
            'completed_at' => $session->completed_at?->toIso8601String(),
            'cancelled_at' => $session->cancelled_at?->toIso8601String(),
            'assigned_count' => (int) ($session->assigned_count ?? 0),
            'room' => $session->room ? [
                'id' => $session->room->id,
                'code' => $session->room->code,
                'name' => $session->room->name,
                'capacity' => $session->room->capacity,
            ] : null,
            'academic_cycle_id' => (int) $session->academic_cycle_id,
            'academic_cycle' => $session->academicCycle ? [
                'id' => $session->academicCycle->id,
                'label' => $session->academicCycle->staffLabel(),
                'name' => $session->academicCycle->name,
                'school_year' => $session->academicCycle->school_year,
                'semester' => $session->academicCycle->semester,
                'is_active' => (bool) $session->academicCycle->is_active,
            ] : null,
            'is_publishable' => $session->status === ConsultationSession::STATUS_DRAFT
                && $this->publishBlockReason($session) === null,
            'publish_block_reason' => $session->status === ConsultationSession::STATUS_DRAFT
                ? $this->publishBlockReason($session)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detailPayload(ConsultationSession $session): array
    {
        return [
            ...$this->listPayload($session),
            'auto_assign_enabled' => (bool) $session->auto_assign_enabled,
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
            ['value' => ConsultationSession::STATUS_DRAFT, 'label' => 'Draft'],
            ['value' => ConsultationSession::STATUS_PUBLISHED, 'label' => 'Published'],
            ['value' => ConsultationSession::STATUS_IN_PROGRESS, 'label' => 'In progress'],
            ['value' => ConsultationSession::STATUS_COMPLETED, 'label' => 'Completed'],
            ['value' => ConsultationSession::STATUS_CANCELLED, 'label' => 'Cancelled'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function bookingModeOptions(): array
    {
        return [
            ['value' => ConsultationSession::BOOKING_STAFF_ONLY, 'label' => 'Staff-only (office assigns)'],
            ['value' => ConsultationSession::BOOKING_OPEN_FOR_RESERVATION, 'label' => 'Self-booking (students reserve)'],
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

    public function publishBlockReason(ConsultationSession $session): ?string
    {
        if ($session->status !== ConsultationSession::STATUS_DRAFT) {
            return 'Only draft sessions may be published.';
        }

        if ($session->room === null) {
            return 'Room is required before publish.';
        }

        if ($session->scheduled_at === null) {
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
