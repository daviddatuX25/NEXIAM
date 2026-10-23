<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccount;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;

function examDayFixture(string $email): array
{
    seedE2Foundation();
    $submitted = submitSyntheticApplicant($email, 'Exam'.substr(md5($email), 0, 6));
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle($submitted['access_token'], '2001-01-01');
    $attempt = $submitted['attempt'];
    $attempt->update(['status' => AdmissionAttempt::STATUS_ACCEPTED]);

    return [$applicant, $attempt];
}

function examDayAssignment(AdmissionAttempt $attempt, string $status, string $code): ExamAssignment
{
    $session = ExamSession::factory()->create([
        'academic_cycle_id' => AcademicCycle::query()->firstOrFail()->id,
        'room_id' => Room::query()->where('is_active', true)->value('id'),
        'code' => $code,
        'status' => $status,
        'starts_at' => now()->subMinutes(30),
        'effective_end_at' => now()->addMinutes(30),
        'started_at' => $status === ExamSession::STATUS_IN_PROGRESS ? now()->subMinutes(10) : null,
        'closed_at' => $status === ExamSession::STATUS_COMPLETED ? now()->subMinute() : null,
    ]);

    return ExamAssignment::factory()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
    ]);
}

test('Exam Day resolves only the authenticated applicant latest attempt and active assignment', function (): void {
    [$applicant, $attempt] = examDayFixture('exam-day-scope@example.test');
    $foreign = submitSyntheticApplicant('exam-day-foreign@example.test', 'Foreign');
    $foreignSession = ExamSession::factory()->create([
        'academic_cycle_id' => AcademicCycle::query()->firstOrFail()->id,
        'room_id' => Room::query()->where('is_active', true)->value('id'),
        'code' => 'EX-FOREIGN',
        'status' => ExamSession::STATUS_IN_PROGRESS,
        'starts_at' => now(),
        'effective_end_at' => now()->addHours(2),
    ]);
    ExamAssignment::factory()->create([
        'exam_session_id' => $foreignSession->id,
        'admission_attempt_id' => $foreign['attempt']->id,
    ]);
    $assignment = examDayAssignment($attempt, ExamSession::STATUS_IN_PROGRESS, 'EX-OWN');

    $this->actingAs($applicant)
        ->get(route('applicant.exam-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/ExamDay')
            ->where('exam_day.page_state', 'ready')
            ->where('exam_day.session.code', 'EX-OWN')
            ->where('exam_day.session.status', ExamSession::STATUS_IN_PROGRESS)
            ->where('exam_day.session.scheduled_starts_at', $assignment->examSession->starts_at->toIso8601String())
            ->where('exam_day.session.scheduled_date', $assignment->examSession->starts_at->toDateString())
            ->where('exam_day.session.scheduled_start_time', $assignment->examSession->starts_at->format('H:i'))
            ->where('exam_day.session.scheduled_end_time', $assignment->examSession->effective_end_at->format('H:i'))
            ->where('exam_day.session.started_at', $assignment->examSession->started_at?->toIso8601String())
            ->where('exam_day.session.closed_at', null)
            ->where('exam_day.attendance', null)
            ->has('exam_day.session.version')
            ->missing('exam_day.submission_status')
            ->missing('exam_day.submitted_at')
            ->missing('exam_day.score')
            ->missing('exam_day.answers'));
});

test('Exam Day reports a missing record as unknown and serializes recorded attendance without internal keys', function (): void {
    [$applicant, $attempt] = examDayFixture('exam-day-attendance@example.test');
    $assignment = examDayAssignment($attempt, ExamSession::STATUS_COMPLETED, 'EX-ATTEND');
    $recorder = User::factory()->create(['name' => 'Approved Recorder']);
    $recordedAt = now()->subHour()->startOfSecond();
    ExamAttendanceRecord::query()->create([
        'exam_assignment_id' => $assignment->id,
        'recorded_by_user_id' => $recorder->id,
        'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
        'idempotency_key' => 'exam-day-record-'.$assignment->id,
        'recorded_at' => $recordedAt,
    ]);

    $this->actingAs($applicant)
        ->get(route('applicant.exam-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('exam_day.attendance.outcome', 'present')
            ->where('exam_day.attendance.recorded_at', $recordedAt->toIso8601String())
            ->where('exam_day.session.status', ExamSession::STATUS_COMPLETED)
            ->where('exam_day.session.started_at', null)
            ->where('exam_day.session.closed_at', $assignment->examSession->closed_at?->toIso8601String())
            ->missing('exam_day.attendance.recorded_by_user_id')
            ->missing('exam_day.session.submission_status'));
});

test('guest is redirected and an applicant account without an attempt receives an empty projection', function (): void {
    seedE2Foundation();
    $this->get(route('applicant.exam-day'))->assertRedirect(route('applicant.login'));

    $identity = ApplicantIdentity::query()->create([
        'institution_id' => Institution::query()->value('id'),
        'normalized_key' => 'EXAM-DAY-NO-ATTEMPT',
        'first_name' => 'No',
        'last_name' => 'Attempt',
        'sex' => 'female',
        'birthdate' => '2001-01-01',
        'email' => 'exam-day-no-attempt@example.test',
    ]);
    $applicant = User::factory()->create(['email' => $identity->email]);
    ApplicantAccount::query()->create([
        'applicant_identity_id' => $identity->id,
        'user_id' => $applicant->id,
        'setup_completed_at' => now(),
    ]);

    $this->actingAs($applicant)
        ->get(route('applicant.exam-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/ExamDay')
            ->where('exam_day.page_state', 'empty')
            ->where('exam_day.attendance', null)
            ->where('exam_day.session', null));
});

test('snapshot JSON is applicant scoped, uncached, and never returns timer for completed direct assessment', function (): void {
    [$applicant, $attempt] = examDayFixture('exam-day-direct@example.test');
    $assignment = examDayAssignment($attempt, ExamSession::STATUS_COMPLETED, 'EX-DIRECT');

    $response = $this->actingAs($applicant)
        ->withHeader('X-Applicant-Snapshot', 'true')
        ->getJson(route('applicant.exam-day'));

    expect($response->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store');

    $response->assertOk()
        ->assertJsonPath('exam_day.session.status', ExamSession::STATUS_COMPLETED)
        ->assertJsonPath('exam_day.session.started_at', null)
        ->assertJsonPath('exam_day.session.closed_at', $assignment->examSession->closed_at?->toIso8601String())
        ->assertJsonMissingPath('exam_day.session.submission_status')
        ->assertJsonMissingPath('exam_day.submission_timestamp');
});
