<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\Room;

/**
 * Applicant schedule payload and QR check-in lifecycle projection.
 */
test('schedule exposes a structured venue and check-in is not projected as exam completion', function (): void {
    seedE2Foundation();

    $foreignFixture = submitSyntheticApplicant('schedule-foreign@example.test', 'Foreign');
    $foreignFixture['attempt']->update(['status' => AdmissionAttempt::STATUS_ACCEPTED]);

    $applicantFixture = submitSyntheticApplicant('schedule-wayfinding@example.test', 'Wayfinding');
    $attempt = $applicantFixture['attempt'];
    $attempt->update(['status' => AdmissionAttempt::STATUS_ACCEPTED]);

    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $applicantFixture['access_token'],
        '2001-01-01',
    );

    $room = Room::query()->where('is_active', true)->firstOrFail();
    $room->update([
        'building_name' => 'Academic Building',
        'floor_level' => '1st Floor',
        'directions_hint' => 'Enter the Academic Building main lobby; Room PL-DEF is along the right wing corridor.',
        'photo_path' => '/assets/wayfinding/demo/rm-101.svg',
        'latitude' => 16.0471,
        'longitude' => 120.3408,
    ]);
    $foreignSession = ExamSession::factory()->create([
        'room_id' => $room->id,
        'status' => ExamSession::STATUS_IN_PROGRESS,
        'starts_at' => now()->addHour(),
        'effective_end_at' => now()->addHours(3),
    ]);
    $foreignAssignment = ExamAssignment::factory()->create([
        'exam_session_id' => $foreignSession->id,
        'admission_attempt_id' => $foreignFixture['attempt']->id,
    ]);

    $session = ExamSession::factory()->create([
        'room_id' => $room->id,
        'status' => ExamSession::STATUS_IN_PROGRESS,
        'starts_at' => now()->addDays(1)->setTime(8, 0),
        'effective_end_at' => now()->addDays(1)->setTime(10, 0),
        'started_at' => now()->setTime(7, 30),
    ]);
    $assignment = ExamAssignment::factory()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
    ]);
    expect($foreignAssignment->id)->toBeLessThan($assignment->id);
    ExamAttendanceRecord::query()->create([
        'exam_assignment_id' => $assignment->id,
        'recorded_by_user_id' => $applicant->id,
        'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
        'idempotency_key' => 'schedule-check-in-'.$assignment->id,
        'recorded_at' => now(),
    ]);

    $this->actingAs($applicant)
        ->get(route('applicant.schedule'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Schedule')
            ->where('assignment.id', $assignment->id)
            ->where('assignment.session.code', $session->code)
            ->where('assignment.session.status', ExamSession::STATUS_IN_PROGRESS)
            ->where('assignment.session.date', $session->starts_at->toDateString())
            ->where('assignment.session.start_time', '08:00')
            ->where('assignment.session.end_date', $session->effective_end_at->toDateString())
            ->where('assignment.session.started_at', $session->started_at->toIso8601String())
            ->where('assignment.session.closed_at', null)
            ->where('assignment.exam_completed', false)
            ->where('assignment.attendance_outcome', ExamAttendanceRecord::OUTCOME_PRESENT)
            ->where('assignment.session.room', $room->code.' — '.$room->name)
            ->where('assignment.session.venue.id', $room->id)
            ->where('assignment.session.venue.code', $room->code)
            ->where('assignment.session.venue.name', $room->name)
            ->where('assignment.session.venue.building_name', 'Academic Building')
            ->where('assignment.session.venue.floor_level', '1st Floor')
            ->where('assignment.session.venue.directions_hint', 'Enter the Academic Building main lobby; Room PL-DEF is along the right wing corridor.')
            ->where('assignment.session.venue.photo_url', '/assets/wayfinding/demo/rm-101.svg')
            ->where('assignment.session.venue.latitude', 16.0471)
            ->where('assignment.session.venue.longitude', 120.3408));

    $this->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stages.2.status', 'current')
            ->where('stages.2.exam_completed', false)
            ->where('stages.2.checked_in', true)
            ->where('stages.2.summary', fn ($summary) => str_contains($summary, 'attendance recorded')));
});

test('completed sessions do not complete applicants recorded absent or void without a submission', function (string $outcome): void {
    seedE2Foundation();

    $applicantFixture = submitSyntheticApplicant('schedule-'.$outcome.'@example.test', 'Non-attendee');
    $attempt = $applicantFixture['attempt'];
    $attempt->update(['status' => AdmissionAttempt::STATUS_ACCEPTED]);
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $applicantFixture['access_token'],
        '2001-01-01',
    );
    $room = Room::query()->where('is_active', true)->firstOrFail();
    $startedAt = now()->subHours(3);
    $closedAt = now()->subHour();
    $session = ExamSession::factory()->create([
        'room_id' => $room->id,
        'status' => ExamSession::STATUS_COMPLETED,
        'starts_at' => $startedAt,
        'effective_end_at' => $closedAt,
        'started_at' => $startedAt,
        'closed_at' => $closedAt,
    ]);
    $assignment = ExamAssignment::factory()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
    ]);
    ExamAttendanceRecord::query()->create([
        'exam_assignment_id' => $assignment->id,
        'recorded_by_user_id' => $applicant->id,
        'outcome' => $outcome,
        'idempotency_key' => 'schedule-'.$outcome.'-'.$assignment->id,
        'recorded_at' => now()->subHours(2),
    ]);

    $this->actingAs($applicant)
        ->get(route('applicant.schedule'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('assignment.id', $assignment->id)
            ->where('assignment.exam_completed', false)
            ->where('assignment.session.status', ExamSession::STATUS_COMPLETED)
            ->where('assignment.session.started_at', $startedAt->toIso8601String())
            ->where('assignment.session.closed_at', $closedAt->toIso8601String())
            ->where('assignment.attendance_outcome', $outcome));

    $this->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stages.2.exam_completed', false)
            ->where('stages.2.checked_in', false));
})->with([
    'absent' => [ExamAttendanceRecord::OUTCOME_ABSENT],
    'void' => [ExamAttendanceRecord::OUTCOME_VOID],
]);

test('schedule publication state follows the assigned session including unpublication', function (): void {
    seedE2Foundation();
    $fixture = submitSyntheticApplicant('schedule-publication@example.test', 'Publication');
    $attempt = $fixture['attempt'];
    $attempt->update(['status' => AdmissionAttempt::STATUS_ACCEPTED]);
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle($fixture['access_token'], '2001-01-01');
    $session = ExamSession::factory()->create([
        'academic_cycle_id' => $attempt->academic_cycle_id,
        'status' => ExamSession::STATUS_DRAFT,
        'starts_at' => now()->addDays(1)->setTime(8, 0),
        'effective_end_at' => now()->addDays(1)->setTime(10, 0),
    ]);
    $assignment = ExamAssignment::factory()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
    ]);

    foreach ([ExamSession::STATUS_DRAFT, ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_DRAFT] as $status) {
        $published = $status === ExamSession::STATUS_PUBLISHED;
        $session->update(['status' => $status]);
        $this->actingAs($applicant)
            ->get(route('applicant.schedule'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('page_state', $published ? 'assigned_published' : 'assigned_tentative')
                ->where('assignment.id', $assignment->id)
                ->where('assignment.session.status', $status)
                ->where('assignment.session.published', $published)
                ->where('pipeline.1.status', 'done')
                ->where('pipeline.3.status', $published ? 'done' : 'current'));
    }
});
