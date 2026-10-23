<?php

use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedE2Foundation();

    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $this->room = Room::query()->where('code', 'RM-101')->firstOrFail();
});

function createBulkConsultationSession(
    AcademicCycle $cycle,
    Room $room,
    string $code,
    string $status = ConsultationSession::STATUS_DRAFT,
    string $bookingMode = ConsultationSession::BOOKING_STAFF_ONLY,
): ConsultationSession {
    return ConsultationSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => $code,
        'status' => $status,
        'booking_mode' => $bookingMode,
        'scheduled_at' => now()->addDays(5)->setTime(10, 0),
        'version' => 1,
    ]);
}

function addBulkConsultationAssignment(ConsultationSession $session, AdmissionAttempt $attempt): ConsultationAssignment
{
    return ConsultationAssignment::query()->create([
        'consultation_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ConsultationAssignment::STATUS_ASSIGNED,
        'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
    ]);
}

test('authorized staff can bulk publish draft consultation sessions', function (): void {
    $session = createBulkConsultationSession($this->cycle, $this->room, 'CS-BULK-PUBLISH');
    $attempt = submitSyntheticApplicant('bulk.consult.publish@example.test')['attempt'];
    addBulkConsultationAssignment($session, $attempt);

    $this->actingAs($this->guidance)
        ->post(route('consultation.sessions.bulk-publish'), [
            'session_ids' => [$session->id],
        ])
        ->assertRedirect();

    expect($session->fresh()->status)->toBe(ConsultationSession::STATUS_PUBLISHED)
        ->and($session->fresh()->version)->toBe(2);
});

test('authorized staff can bulk update consultation booking mode in both directions', function (): void {
    $session = createBulkConsultationSession($this->cycle, $this->room, 'CS-BULK-BOOKING');

    $this->actingAs($this->guidance)
        ->post(route('consultation.sessions.bulk-booking-mode'), [
            'session_ids' => [$session->id],
            'booking_mode' => ConsultationSession::BOOKING_OPEN_FOR_RESERVATION,
        ])
        ->assertRedirect();

    expect($session->fresh()->booking_mode)->toBe(ConsultationSession::BOOKING_OPEN_FOR_RESERVATION);

    $this->actingAs($this->guidance)
        ->post(route('consultation.sessions.bulk-booking-mode'), [
            'session_ids' => [$session->id],
            'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        ])
        ->assertRedirect();

    expect($session->fresh()->booking_mode)->toBe(ConsultationSession::BOOKING_STAFF_ONLY);
});

test('authorized staff can bulk cancel draft and published sessions with audited reason', function (): void {
    $draft = createBulkConsultationSession($this->cycle, $this->room, 'CS-BULK-CANCEL-DRAFT');
    $published = createBulkConsultationSession(
        $this->cycle,
        $this->room,
        'CS-BULK-CANCEL-PUBLISHED',
        ConsultationSession::STATUS_PUBLISHED,
    );
    $attempt = submitSyntheticApplicant('bulk.cons.cancel@example.test')['attempt'];
    addBulkConsultationAssignment($published, $attempt);
    $reason = 'Staffing emergency';

    $this->actingAs($this->guidance)
        ->post(route('consultation.sessions.bulk-cancel'), [
            'session_ids' => [$draft->id, $published->id],
            'reason' => $reason,
        ])
        ->assertRedirect();

    expect($draft->fresh()->status)->toBe(ConsultationSession::STATUS_CANCELLED)
        ->and($published->fresh()->status)->toBe(ConsultationSession::STATUS_CANCELLED);

    $audit = AuditEvent::query()
        ->where('event_type', 'consultation.session_cancelled')
        ->where('payload->consultation_session_id', $published->id)
        ->firstOrFail();

    expect($audit->payload['reason'])->toBe($reason);
});

test('staff without consultation planning capability cannot invoke bulk actions', function (): void {
    $unauthorized = User::factory()->create();
    $session = createBulkConsultationSession($this->cycle, $this->room, 'CS-BULK-UNAUTHORIZED');

    $this->actingAs($unauthorized)
        ->post(route('consultation.sessions.bulk-publish'), ['session_ids' => [$session->id]])
        ->assertForbidden();

    $this->actingAs($unauthorized)
        ->post(route('consultation.sessions.bulk-booking-mode'), [
            'session_ids' => [$session->id],
            'booking_mode' => ConsultationSession::BOOKING_OPEN_FOR_RESERVATION,
        ])
        ->assertForbidden();

    $this->actingAs($unauthorized)
        ->post(route('consultation.sessions.bulk-cancel'), [
            'session_ids' => [$session->id],
            'reason' => 'Unauthorized attempt',
        ])
        ->assertForbidden();
});
