<?php

use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Room;
use App\Models\User;

function applicantSlotSwitchAcceptedAttempt(string $email): array
{
    $uniq = uniqid('', true);
    $result = submitSyntheticApplicant($email, 'Switch'.str_replace('.', '', $uniq));
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    test()->actingAs($registrar)
        ->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1])
        ->assertRedirect();

    return $result;
}

function applicantSlotSwitchLogin(array $result): void
{
    if (auth()->check()) {
        test()->post(route('logout'));
    }

    test()->post(route('applicant.login.store'), [
        'access_key' => $result['access_token'],
        'birthdate' => '2001-01-01',
    ])->assertRedirect(route('applicant.portal'));
}

function applicantSlotSwitchExamSession(
    AcademicCycle $cycle,
    Room $room,
    string $code,
    string $bookingMode = ExamSession::BOOKING_OPEN_FOR_RESERVATION,
    string $status = ExamSession::STATUS_DRAFT,
): ExamSession {
    return ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => $code,
        'status' => $status,
        'booking_mode' => $bookingMode,
        'starts_at' => now()->addDays(10)->setTime(9, 0),
        'effective_end_at' => now()->addDays(10)->setTime(11, 0),
        'version' => 1,
    ]);
}

function applicantSlotSwitchConsultationSession(
    AcademicCycle $cycle,
    Room $room,
    string $code,
    string $bookingMode = ConsultationSession::BOOKING_OPEN_FOR_RESERVATION,
): ConsultationSession {
    return ConsultationSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => $code,
        'status' => ConsultationSession::STATUS_DRAFT,
        'booking_mode' => $bookingMode,
        'scheduled_at' => now()->addDays(12)->setTime(10, 0),
        'version' => 1,
    ]);
}

function applicantSlotSwitchAttemptAndRoom(): array
{
    seedE2Foundation();

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $result = applicantSlotSwitchAcceptedAttempt('slot-switch-'.str_replace('.', '', uniqid('', true)).'@example.test');

    return [$cycle, $room, $result];
}

test('allows an applicant with a draft exam assignment to switch to another draft open slot', function (): void {
    [$cycle, $room, $result] = applicantSlotSwitchAttemptAndRoom();
    $attempt = $result['attempt'];
    $fromSession = applicantSlotSwitchExamSession($cycle, $room, 'EX-SWITCH-FROM-1', ExamSession::BOOKING_STAFF_ONLY);
    $toSession = applicantSlotSwitchExamSession($cycle, $room, 'EX-SWITCH-TO-1');
    $assignment = ExamAssignment::query()->create([
        'exam_session_id' => $fromSession->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
        'origin' => ExamAssignment::ORIGIN_MANUAL,
        'version' => 1,
    ]);

    applicantSlotSwitchLogin($result);
    $this->post(route('applicant.schedule.switch'), [
        'exam_session_id' => $toSession->id,
        'admission_attempt_id' => $attempt->id,
        'expected_assignment_id' => $assignment->id,
        'expected_assignment_version' => $assignment->version,
        'idempotency_key' => 'test-switch-1',
    ])->assertRedirect(route('applicant.schedule'))
        ->assertSessionHas('status', 'Appointment changed.');

    $replacement = ExamAssignment::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('exam_session_id', $toSession->id)
        ->firstOrFail();

    expect($assignment->fresh()->status)->toBe('removed')
        ->and($replacement->status)->toBe(ExamAssignment::STATUS_ASSIGNED)
        ->and($fromSession->fresh()->version)->toBe($fromSession->version + 1)
        ->and($toSession->fresh()->version)->toBe($toSession->version + 1)
        ->and(AuditEvent::query()->where('event_type', 'exam.slot_switched')->exists())->toBeTrue();
});

test('rejects switching when the target session is full', function (): void {
    [$cycle, $room, $result] = applicantSlotSwitchAttemptAndRoom();
    $room->update(['capacity' => 1]);
    $attempt = $result['attempt'];
    $fromSession = applicantSlotSwitchExamSession($cycle, $room, 'EX-SWITCH-FULL-FROM');
    $toSession = applicantSlotSwitchExamSession($cycle, $room, 'EX-SWITCH-FULL-TO');
    $assignment = ExamAssignment::query()->create([
        'exam_session_id' => $fromSession->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
        'version' => 1,
    ]);
    $other = applicantSlotSwitchAcceptedAttempt('slot-switch-full-other-'.str_replace('.', '', uniqid('', true)).'@example.test');
    ExamAssignment::query()->create([
        'exam_session_id' => $toSession->id,
        'admission_attempt_id' => $other['attempt']->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);

    applicantSlotSwitchLogin($result);
    $this->post(route('applicant.schedule.switch'), [
        'exam_session_id' => $toSession->id,
        'admission_attempt_id' => $attempt->id,
        'expected_assignment_id' => $assignment->id,
        'expected_assignment_version' => $assignment->version,
        'idempotency_key' => 'test-switch-full',
    ])->assertSessionHasErrors('switch');

    expect($assignment->fresh()->status)->toBe(ExamAssignment::STATUS_ASSIGNED);
});

test('rejects switching when the source session is already published', function (): void {
    [$cycle, $room, $result] = applicantSlotSwitchAttemptAndRoom();
    $attempt = $result['attempt'];
    $fromSession = applicantSlotSwitchExamSession(
        $cycle,
        $room,
        'EX-SWITCH-PUBLISHED-FROM',
        status: ExamSession::STATUS_PUBLISHED,
    );
    $toSession = applicantSlotSwitchExamSession($cycle, $room, 'EX-SWITCH-PUBLISHED-TO');
    $assignment = ExamAssignment::query()->create([
        'exam_session_id' => $fromSession->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
        'version' => 1,
    ]);

    applicantSlotSwitchLogin($result);
    $this->post(route('applicant.schedule.switch'), [
        'exam_session_id' => $toSession->id,
        'admission_attempt_id' => $attempt->id,
        'expected_assignment_id' => $assignment->id,
        'expected_assignment_version' => $assignment->version,
        'idempotency_key' => 'test-switch-published',
    ])->assertSessionHasErrors('switch');

    expect(session('errors')->getBag('default')->first('switch'))->toContain('published');

    expect($assignment->fresh()->status)->toBe(ExamAssignment::STATUS_ASSIGNED);
});

test('allows switching consultation release day slots when draft', function (): void {
    [$cycle, $room, $result] = applicantSlotSwitchAttemptAndRoom();
    $attempt = $result['attempt'];
    $fromConsultation = applicantSlotSwitchConsultationSession($cycle, $room, 'CS-SWITCH-FROM');
    $toConsultation = applicantSlotSwitchConsultationSession($cycle, $room, 'CS-SWITCH-TO');
    $assignment = ConsultationAssignment::query()->create([
        'consultation_session_id' => $fromConsultation->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ConsultationAssignment::STATUS_ASSIGNED,
        'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
        'origin' => ConsultationAssignment::ORIGIN_MANUAL,
        'version' => 1,
    ]);

    applicantSlotSwitchLogin($result);
    $this->post(route('applicant.consultation.switch'), [
        'consultation_session_id' => $toConsultation->id,
        'admission_attempt_id' => $attempt->id,
        'expected_assignment_id' => $assignment->id,
        'expected_assignment_version' => $assignment->version,
        'idempotency_key' => 'test-switch-consult-1',
    ])->assertRedirect(route('applicant.consultation'))
        ->assertSessionHas('status', 'Release Day appointment changed.');

    $replacement = ConsultationAssignment::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('consultation_session_id', $toConsultation->id)
        ->firstOrFail();

    expect($assignment->fresh()->status)->toBe('cancelled')
        ->and($replacement->status)->toBe(ConsultationAssignment::STATUS_ASSIGNED)
        ->and(AuditEvent::query()->where('event_type', 'consultation.slot_switched')->exists())->toBeTrue();
});
