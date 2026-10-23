<?php

use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;

beforeEach(function (): void {
    seedE2Foundation();
    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $this->room = Room::query()->where('code', 'RM-101')->firstOrFail();
});

function createBulkExamSession(AcademicCycle $cycle, Room $room, string $code, string $bookingMode = ExamSession::BOOKING_STAFF_ONLY): ExamSession
{
    return ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => $code,
        'status' => ExamSession::STATUS_DRAFT,
        'booking_mode' => $bookingMode,
        'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        'test_kit_version_id' => null,
        'test_instrument_version_id' => null,
        'booklet_version_id' => null,
        'starts_at' => now()->addDays(5)->setTime(9, 0),
        'effective_end_at' => now()->addDays(5)->setTime(11, 0),
        'version' => 1,
    ]);
}

test('authorized staff delegate bulk booking mode changes and skip non-draft sessions', function (): void {
    $session1 = createBulkExamSession($this->cycle, $this->room, 'EX-BLK-BK1', ExamSession::BOOKING_STAFF_ONLY);
    $session2 = createBulkExamSession($this->cycle, $this->room, 'EX-BLK-BK2', ExamSession::BOOKING_STAFF_ONLY);
    $publishedSession = createBulkExamSession($this->cycle, $this->room, 'EX-BLK-BK3', ExamSession::BOOKING_STAFF_ONLY);
    $session1->update(['auto_assign_enabled' => true]);
    $session2->update(['auto_assign_enabled' => true]);
    $publishedSession->update(['status' => ExamSession::STATUS_PUBLISHED, 'auto_assign_enabled' => true]);

    $this->actingAs($this->guidance)
        ->post(route('exams.sessions.bulk-booking-mode'), [
            'session_ids' => [$session1->id, $session2->id, $publishedSession->id],
            'booking_mode' => 'open_for_reservation',
        ])
        ->assertRedirect()
        ->assertSessionHas('warning', fn (?string $message): bool => $message !== null
            && str_contains($message, '2 sessions updated')
            && str_contains($message, '1 skipped')
            && str_contains($message, $publishedSession->code));

    expect($session1->fresh()->booking_mode)->toBe(ExamSession::BOOKING_OPEN_FOR_RESERVATION)
        ->and($session1->fresh()->version)->toBe(2)
        ->and($session1->fresh()->auto_assign_enabled)->toBeFalse()
        ->and($session2->fresh()->booking_mode)->toBe(ExamSession::BOOKING_OPEN_FOR_RESERVATION)
        ->and($session2->fresh()->version)->toBe(2)
        ->and($session2->fresh()->auto_assign_enabled)->toBeFalse()
        ->and($publishedSession->fresh()->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($publishedSession->fresh()->booking_mode)->toBe(ExamSession::BOOKING_STAFF_ONLY)
        ->and($publishedSession->fresh()->version)->toBe(1)
        ->and($publishedSession->fresh()->auto_assign_enabled)->toBeTrue();

    $this->actingAs($this->guidance)
        ->post(route('exams.sessions.bulk-booking-mode'), [
            'session_ids' => [$session1->id, $session2->id, $publishedSession->id],
            'booking_mode' => 'staff_only',
        ])
        ->assertRedirect();

    expect($session1->fresh()->booking_mode)->toBe(ExamSession::BOOKING_STAFF_ONLY)
        ->and($session1->fresh()->version)->toBe(3)
        ->and($session1->fresh()->auto_assign_enabled)->toBeFalse()
        ->and($session2->fresh()->booking_mode)->toBe(ExamSession::BOOKING_STAFF_ONLY)
        ->and($session2->fresh()->version)->toBe(3)
        ->and($session2->fresh()->auto_assign_enabled)->toBeFalse()
        ->and($publishedSession->fresh()->version)->toBe(1)
        ->and($publishedSession->fresh()->auto_assign_enabled)->toBeTrue();
});

test('authorized staff can bulk cancel selected sessions with reason', function (): void {
    $session1 = createBulkExamSession($this->cycle, $this->room, 'EX-BLK-CN1');
    $session2 = createBulkExamSession($this->cycle, $this->room, 'EX-BLK-CN2');

    $response = $this->actingAs($this->guidance)
        ->post(route('exams.sessions.bulk-cancel'), [
            'session_ids' => [$session1->id, $session2->id],
            'reason' => 'Severe weather emergency postponement',
        ]);

    $response->assertRedirect();
    expect($session1->fresh()->status)->toBe(ExamSession::STATUS_CANCELLED)
        ->and($session2->fresh()->status)->toBe(ExamSession::STATUS_CANCELLED);
});

test('bulk cancellation reports rejected sessions and preserves independent successes', function (): void {
    $draft = createBulkExamSession($this->cycle, $this->room, 'EX-BLK-CANCEL-OK');
    $completed = createBulkExamSession($this->cycle, $this->room, 'EX-BLK-CANCEL-DENIED');
    $completed->update(['status' => ExamSession::STATUS_COMPLETED]);

    $this->actingAs($this->guidance)
        ->post(route('exams.sessions.bulk-cancel'), [
            'session_ids' => [$draft->id, $completed->id],
            'reason' => 'Weather closure',
        ])
        ->assertRedirect()
        ->assertSessionHas('warning', fn (?string $message): bool => $message !== null && str_contains($message, '1 sessions cancelled; 1 skipped')
            && str_contains($message, $completed->code)
            && str_contains($message, 'Session is already terminal.'));

    expect($draft->fresh()->status)->toBe(ExamSession::STATUS_CANCELLED)
        ->and($completed->fresh()->status)->toBe(ExamSession::STATUS_COMPLETED)
        ->and($completed->fresh()->version)->toBe(1);
});

test('bulk cancellation reports an all-rejected selection as an error', function (): void {
    $completed = createBulkExamSession($this->cycle, $this->room, 'EX-BLK-CANCEL-ALL-DENIED');
    $completed->update(['status' => ExamSession::STATUS_COMPLETED]);

    $this->actingAs($this->guidance)
        ->post(route('exams.sessions.bulk-cancel'), [
            'session_ids' => [$completed->id],
            'reason' => 'Weather closure',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', fn (?string $message): bool => $message !== null && str_contains($message, '0 sessions cancelled; 1 skipped')
            && str_contains($message, 'Session is already terminal.'))
        ->assertSessionMissing('status');

    expect($completed->fresh()->status)->toBe(ExamSession::STATUS_COMPLETED)
        ->and($completed->fresh()->version)->toBe(1);
});

test('bulk publish handles preflight and reports published and skipped sessions', function (): void {
    $sessionEmpty = createBulkExamSession($this->cycle, $this->room, 'EX-BLK-EMPTY');

    $response = $this->actingAs($this->guidance)
        ->post(route('exams.sessions.bulk-publish'), [
            'session_ids' => [$sessionEmpty->id],
        ]);

    $response->assertRedirect()
        ->assertSessionHas('error', fn (?string $message): bool => $message !== null
            && str_contains($message, '0 sessions published; 1 skipped')
            && str_contains($message, 'EX-BLK-EMPTY')
            && str_contains($message, 'Assign at least one applicant before publishing.')
            && ! str_contains($message, '(1)'))
        ->assertSessionMissing('status');
    expect($sessionEmpty->fresh()->status)->toBe(ExamSession::STATUS_DRAFT);
});

test('unauthorized staff cannot invoke bulk session actions', function (): void {
    $unauthorized = User::factory()->create();
    $session = createBulkExamSession($this->cycle, $this->room, 'EX-BLK-UNAUTH');

    $this->actingAs($unauthorized)
        ->post(route('exams.sessions.bulk-booking-mode'), [
            'session_ids' => [$session->id],
            'booking_mode' => 'open_for_reservation',
        ])
        ->assertForbidden();

    $this->actingAs($unauthorized)
        ->post(route('exams.sessions.bulk-cancel'), [
            'session_ids' => [$session->id],
            'reason' => 'Unauthorized attempt',
        ])
        ->assertForbidden();

    $this->actingAs($unauthorized)
        ->post(route('exams.sessions.bulk-publish'), [
            'session_ids' => [$session->id],
        ])
        ->assertForbidden();
});
