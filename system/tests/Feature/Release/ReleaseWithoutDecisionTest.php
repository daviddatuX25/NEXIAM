<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Room;
use App\Models\Release\ResultRelease;
use App\Models\User;
use App\Notifications\NotificationDelivery;

function makeReleaseReadyAttempt(string $email, string $lastName = 'ReleaseNoDecision'): array
{
    $result = submitSyntheticApplicant($email, $lastName);
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    test()->actingAs($registrar)
        ->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1])
        ->assertRedirect();

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $examSession = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-REL-'.str_replace('.', '', uniqid('', true)),
        'status' => ExamSession::STATUS_COMPLETED,
        'starts_at' => now()->subDays(5),
        'effective_end_at' => now()->subDays(5)->addHours(2),
        'closed_at' => now()->subDays(5),
    ]);

    $assignment = ExamAssignment::query()->create([
        'exam_session_id' => $examSession->id,
        'admission_attempt_id' => $result['attempt']->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);

    $grading = GradingSession::query()->create([
        'exam_session_id' => $examSession->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subDays(4),
        'finalized_at' => now()->subDays(3),
    ]);
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    ExamAttendanceRecord::query()->create([
        'exam_assignment_id' => $assignment->id,
        'recorded_by_user_id' => $guidance->id,
        'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
        'idempotency_key' => 'release-no-decision-attendance-'.$assignment->id,
        'recorded_at' => now(),
    ]);
    ExamSubmissionRecord::query()->create([
        'exam_assignment_id' => $assignment->id,
        'recorded_by_user_id' => $guidance->id,
        'idempotency_key' => 'release-no-decision-submission-'.$assignment->id,
        'submitted_at' => now(),
    ]);
    foreach (AptitudeArea::query()->where('is_active', true)->get() as $area) {
        ScoreRecord::query()->create([
            'grading_session_id' => $grading->id,
            'admission_attempt_id' => $result['attempt']->id,
            'aptitude_area_id' => $area->id,
            'score' => min(20, (int) $area->max_items),
            'converted_score' => '85.00',
            'source' => ScoreRecord::SOURCE_MANUAL,
            'recorded_by_user_id' => $guidance->id,
            'idempotency_key' => 'release-no-decision-'.$assignment->id.'-'.$area->id,
        ]);
    }

    return $result;
}

test('release succeeds WITHOUT a consultation decision and records online mode + template', function (): void {
    seedE2Foundation();
    $result = makeReleaseReadyAttempt('release-no-decision@example.test', 'NoDecision');
    $attempt = $result['attempt'];
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    // NT-8 — portal account so deliveries have a recipient.
    app(AuthenticateApplicantWithAccessKey::class)->handle(
        $result['access_token'],
        '2001-01-01',
    );

    $this->actingAs($guidance)->post(route('release.prepare', $attempt));

    $this->actingAs($guidance)
        ->post(route('release.store', $attempt), [
            'idempotency_key' => 'release-no-decision-1',
            'mode' => 'online',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect(ResultRelease::query()->where('is_current', true)->first())
        ->mode->toBe('online');

    $applicant = User::query()->where('email', 'release-no-decision@example.test')->firstOrFail();
    $deliveries = NotificationDelivery::forUser($applicant)
        ->where('event_type', 'result.released.v1')
        ->get();

    expect($deliveries)->not->toBeEmpty()
        ->and($deliveries->every(fn (NotificationDelivery $d): bool => $d->template_key === 'result.released.online'))->toBeTrue();
});

test('release defaults to f2f template when no mode supplied', function (): void {
    seedE2Foundation();
    $result = makeReleaseReadyAttempt('release-f2f-default@example.test', 'F2fDefault');
    $attempt = $result['attempt'];
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    app(AuthenticateApplicantWithAccessKey::class)->handle(
        $result['access_token'],
        '2001-01-01',
    );

    $this->actingAs($guidance)->post(route('release.prepare', $attempt));

    $this->actingAs($guidance)
        ->post(route('release.store', $attempt), ['idempotency_key' => 'release-f2f-default-1'])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect(ResultRelease::query()->where('is_current', true)->first())
        ->mode->toBe('f2f');

    $applicant = User::query()->where('email', 'release-f2f-default@example.test')->firstOrFail();
    $deliveries = NotificationDelivery::forUser($applicant)
        ->where('event_type', 'result.released.v1')
        ->get();

    expect($deliveries)->not->toBeEmpty()
        ->and($deliveries->every(fn (NotificationDelivery $d): bool => $d->template_key === 'result.released.f2f'))->toBeTrue();
});
