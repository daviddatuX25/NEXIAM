<?php

namespace App\Consultation\Support;

use App\Models\Consultation\ConsultationSession;

final class ConsultationReservationEvaluator
{
    public function evaluate(ConsultationSession $session, int $expectedVersion): PreflightResult
    {
        $checks = [
            [
                'key' => 'version_matches',
                'label' => 'Session version',
                'status' => $session->version === $expectedVersion ? 'pass' : 'fail',
                'detail' => (string) $session->version,
            ],
            [
                'key' => 'session_draft',
                'label' => 'Draft session',
                'status' => $session->status === ConsultationSession::STATUS_DRAFT ? 'pass' : 'fail',
                'detail' => $session->status,
            ],
            [
                'key' => 'room_assigned',
                'label' => 'Room',
                'status' => $session->room_id !== null ? 'pass' : 'fail',
                'detail' => $session->room?->name ?? 'Room required',
            ],
            [
                'key' => 'scheduled_at_valid',
                'label' => 'Appointment time',
                'status' => $session->scheduled_at !== null ? 'pass' : 'fail',
                'detail' => $session->scheduled_at?->toIso8601String() ?? 'Schedule time required',
            ],
        ];

        if ($session->booking_mode === ConsultationSession::BOOKING_OPEN_FOR_RESERVATION) {
            $checks[] = [
                'key' => 'booking_mode_open',
                'label' => 'Reservation status',
                'status' => 'pass',
                'note' => 'Already accepting reservations',
            ];

            $outcome = 'noop';
        } else {
            $checks[] = [
                'key' => 'can_open_reservation',
                'label' => 'Booking mode transition',
                'status' => $session->booking_mode === ConsultationSession::BOOKING_STAFF_ONLY ? 'pass' : 'fail',
                'detail' => $session->booking_mode,
            ];

            $outcome = 'pass';
        }

        if (array_filter($checks, static fn (array $check): bool => $check['status'] === 'fail') !== []) {
            $outcome = 'fail';
        }

        return new PreflightResult(
            outcome: $outcome,
            checks: $checks,
            diff: $outcome === 'pass' ? [[
                'key' => 'booking_mode',
                'label' => 'Booking mode',
                'before' => $session->booking_mode,
                'after' => ConsultationSession::BOOKING_OPEN_FOR_RESERVATION,
            ]] : [],
        );
    }
}
