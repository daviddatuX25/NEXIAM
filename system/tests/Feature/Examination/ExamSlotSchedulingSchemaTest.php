<?php

use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('option A booking schema: session booking_mode and assignment source without slot plans', function (): void {
    seedE2Foundation();

    expect(Schema::hasTable('exam_slot_plans'))->toBeFalse()
        ->and(Schema::hasTable('exam_slot_reservations'))->toBeFalse()
        ->and(Schema::hasColumns('exam_sessions', [
            'booking_mode',
            'reservation_opened_at',
            'reservation_closed_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('exam_assignments', [
            'source',
            'ended_at',
            'end_reason',
        ]))->toBeTrue();
});

test('active assignment uniqueness allows history after end', function (): void {
    seedE2Foundation();

    $result = submitSyntheticApplicant('schema-unique@example.test', 'Schema');
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $this->actingAs($registrar)
        ->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1])
        ->assertRedirect();

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $this->actingAs($guidance)
        ->post(route('exams.sessions.store'), [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'code' => 'EX-SCHEMA-1',
            'date' => now()->addDays(10)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
        ])
        ->assertRedirect();

    $session = ExamSession::query()->where('code', 'EX-SCHEMA-1')->firstOrFail();

    $first = ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $result['attempt']->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);

    $duplicateBlocked = false;
    try {
        ExamAssignment::query()->create([
            'exam_session_id' => $session->id,
            'admission_attempt_id' => $result['attempt']->id,
            'status' => ExamAssignment::STATUS_ASSIGNED,
            'source' => ExamAssignment::SOURCE_APPLICANT_RESERVED,
        ]);
    } catch (Throwable) {
        $duplicateBlocked = true;
    }

    expect($duplicateBlocked)->toBeTrue();

    $first->update([
        'status' => ExamAssignment::STATUS_REMOVED,
        'ended_at' => now(),
        'end_reason' => 'moved',
    ]);

    $second = ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $result['attempt']->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);

    expect($second->status)->toBe(ExamAssignment::STATUS_ASSIGNED)
        ->and(ExamAssignment::query()->where('admission_attempt_id', $result['attempt']->id)->count())->toBe(2);
});
