<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Notifications\NotificationDelivery;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    seedE2Foundation();
});

test('notifications:send-exam-reminders dispatches 24h reminder to assigned examinee', function (): void {
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $applicant = submitSyntheticApplicant('examinee.remind24@example.test', 'Remind24');
    /** @var AdmissionAttempt $attempt */
    $attempt = $applicant['attempt'];
    $user = app(AuthenticateApplicantWithAccessKey::class)->handle($applicant['access_token'], '2001-01-01');

    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-REMIND-24H',
        'status' => ExamSession::STATUS_PUBLISHED,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->addHours(24),
        'effective_end_at' => now()->addHours(26),
        'version' => 1,
    ]);

    ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);

    $exit = Artisan::call('notifications:send-exam-reminders', ['--window' => '24h']);
    expect($exit)->toBe(0);

    $deliveries = NotificationDelivery::forUser($user)
        ->where('event_type', 'exam.reminder.v1')
        ->get();

    $firstPayload = $deliveries->first()->payload;
    $code = $firstPayload['code'] ?? $firstPayload['context']['values']['session_code'] ?? null;
    expect($deliveries->count())->toBeGreaterThanOrEqual(1)
        ->and($code)->toBe('EX-REMIND-24H');
    // Idempotent re-run does not duplicate deliveries
    $countBefore = NotificationDelivery::query()->count();
    $secondExit = Artisan::call('notifications:send-exam-reminders', ['--window' => '24h']);
    expect($secondExit)->toBe(0)
        ->and(NotificationDelivery::query()->count())->toBe($countBefore);
});

test('notifications:send-exam-reminders dispatches 1h reminder for soon sessions', function (): void {
    $this->freezeSecond();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $applicant = submitSyntheticApplicant('examinee.remind1h@example.test', 'Remind1h');
    /** @var AdmissionAttempt $attempt */
    $attempt = $applicant['attempt'];
    $user = app(AuthenticateApplicantWithAccessKey::class)->handle($applicant['access_token'], '2001-01-01');

    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-REMIND-1H',
        'status' => ExamSession::STATUS_PUBLISHED,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->addMinutes(45),
        'notification_policy' => [
            'schema_version' => 1,
            'publication_email_enabled' => true,
            'reminder_offsets_minutes' => [60],
            'reminder_email_enabled' => true,
        ],
        'notification_policy_snapshot' => [
            'schema_version' => 1,
            'cycle_policy_version' => 1,
            'session_version' => 1,
            'publication_email_enabled' => true,
            'reminder_offsets_minutes' => [60],
            'reminder_email_enabled' => true,
        ],
        'effective_end_at' => now()->addMinutes(165),
    ]);

    ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);

    $exit = Artisan::call('notifications:send-exam-reminders', ['--window' => '1h']);
    expect($exit)->toBe(0);

    $deliveries = NotificationDelivery::forUser($user)
        ->where('event_type', 'exam.reminder.v1')
        ->get();

    $window = $deliveries->first()->payload['practice_reminder_window']
        ?? $deliveries->first()->payload['window']
        ?? $deliveries->first()->payload['event_resource']['window']
        ?? null;
    expect($deliveries->count())->toBeGreaterThanOrEqual(1)
        ->and($window)->toBe('1h');
});
