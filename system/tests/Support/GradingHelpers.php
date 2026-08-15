<?php

use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\RecordExamSubmission;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Room;
use App\Models\User;
use Tests\TestCase;

/**
 * Build an exam session with N accepted, assigned applicants and return
 * [guidance, registrar, session, attempts, grading (when closed)].
 *
 * @return array{guidance: User, registrar: User, session: ExamSession, attempts: array<int, AdmissionAttempt>, grading: ?GradingSession}
 */
function buildGradingFixture(TestCase $test, int $applicantCount = 1, bool $close = true): array
{
    seedE2Foundation();

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    $attempts = [];
    for ($i = 0; $i < $applicantCount; $i++) {
        $result = submitSyntheticApplicant('grading.'.uniqid().'@example.test', 'Grading '.$i);
        $attempt = $result['attempt'];

        $test->actingAs($registrar)
            ->post(route('admissions.accept', $attempt), ['expected_version' => 1])
            ->assertRedirect();

        $attempts[] = $attempt;
    }

    $test->actingAs($guidance)->post(route('exams.sessions.store'), [
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-GRAD-'.uniqid(),
        'starts_at' => now()->subHours(2)->toDateTimeString(),
        'effective_end_at' => now()->addMinutes(30)->toDateTimeString(),
    ])->assertRedirect();

    $session = ExamSession::query()->orderByDesc('id')->firstOrFail();

    foreach ($attempts as $attempt) {
        $test->actingAs($guidance)
            ->post(route('exams.sessions.assign', $session), ['admission_attempt_id' => $attempt->id])
            ->assertRedirect();
    }

    $test->actingAs($guidance)
        ->post(route('exams.sessions.publish', $session), ['expected_version' => 1])
        ->assertRedirect();

    // GRE-1/3 — attendance + submission are recorded before close so the
    // grading roster can be derived. Domain actions directly (the proctor
    // duty gate is covered by ExamProctorDutyAssignTest, not this fixture).
    // Only pre-record when the fixture will close; callers using close:false
    // record their own attendance outcomes.
    $grading = null;

    if ($close) {
        $attendanceAction = app(RecordExamAttendance::class);
        $submissionAction = app(RecordExamSubmission::class);

        foreach ($session->assignments as $assignment) {
            $attendanceAction->handle($assignment, $guidance, ExamAttendanceRecord::OUTCOME_PRESENT, 'att-grading-'.$assignment->id);
            $submissionAction->handle($assignment, $guidance, 'sub-grading-'.$assignment->id);
        }

        $test->actingAs($guidance)
            ->post(route('exams.sessions.close', $session), ['expected_version' => 2])
            ->assertRedirect();

        $grading = GradingSession::query()->where('exam_session_id', $session->id)->firstOrFail();
    }

    return [
        'guidance' => $guidance,
        'registrar' => $registrar,
        'session' => $session,
        'attempts' => $attempts,
        'grading' => $grading,
    ];
}

/**
 * @return array<int, int>
 */
function activeAreas(): array
{
    return AptitudeArea::query()->where('is_active', true)->orderBy('id')->pluck('id')->all();
}
