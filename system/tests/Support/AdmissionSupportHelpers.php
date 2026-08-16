<?php

const DEFAULT_PROGRAM = 'BSN';
const OTHER_PROGRAM = 'BSPSY';

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\RecordExamSubmission;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Release\Actions\PrepareResultDocument;
use App\Release\Actions\RecordConsultationDecision;
use App\Release\Actions\ReleaseResult;

/**
 * Drive an attempt to result-released + advised through the real HTTP/action
 * pipeline. Expensive (~6-8s per call) but exercises the domain truth.
 *
 * @return array{attempt: AdmissionAttempt, applicant: User, guidance: User, registrar: User}
 */
function buildReleasedAdvisedAttempt(object $test, string $email, int $roomVariant = 0, string $releaseMode = ReleaseResult::MODE_F2F): array
{
    seedE2Foundation();

    $result = submitSyntheticApplicant($email, 'ProgSel'.substr(md5($email), 0, 6));
    $attempt = $result['attempt'];

    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $result['access_token'],
        '2001-01-01',
    );

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', $roomVariant === 0 ? 'RM-101' : 'RM-LabA')->firstOrFail();

    $test->actingAs($registrar)->post(route('admissions.accept', $attempt), ['expected_version' => 1]);

    $test->actingAs($guidance)->post(route('exams.sessions.store'), [
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-PS-'.substr($email, 0, 8),
        'starts_at' => now()->subHours(2)->toDateTimeString(),
        'effective_end_at' => now()->addMinutes(30)->toDateTimeString(),
    ]);

    $session = ExamSession::query()->orderByDesc('id')->firstOrFail();
    $test->actingAs($guidance)->post(route('exams.sessions.assign', $session), [
        'admission_attempt_id' => $attempt->id,
    ]);
    $publishResponse = $test->actingAs($guidance)->post(route('exams.sessions.publish', $session), ['expected_version' => 1]);
    $errors = session('errors');
    if ($errors !== null && $errors->any()) {
        throw new RuntimeException('publish errors: '.implode(' | ', $errors->all()));
    }
    $session->refresh();
    if ($session->status !== 'published') {
        throw new RuntimeException('session status after publish: '.$session->status.' version '.$session->version);
    }

    $assignment = $session->assignments()->first();
    app(RecordExamAttendance::class)->handle($assignment, $guidance, 'present', 'att-'.substr($email, 0, 8));
    app(RecordExamSubmission::class)->handle($assignment, $guidance, 'sub-'.substr($email, 0, 8));

    $test->actingAs($guidance)->post(route('exams.sessions.close', $session), ['expected_version' => 2]);

    $grading = GradingSession::query()->orderByDesc('id')->firstOrFail();
    $areas = AptitudeArea::query()->where('is_active', true)->get();

    $test->actingAs($guidance)->put(route('grading.sessions.attempts.scores.update', [$grading, $attempt]), [
        'scores' => $areas->map(fn ($area) => [
            'aptitude_area_id' => $area->id,
            'score' => 85,
        ])->all(),
        'idempotency_key' => 'scores-'.substr($email, 0, 8),
    ]);

    $test->actingAs($guidance)->post(route('grading.finalize', $grading));

    app(RecordConsultationDecision::class)->handle($attempt, $guidance, 'approve', null, 'consult-'.substr($email, 0, 8));

    app(PrepareResultDocument::class)->handle($attempt, $guidance);
    app(ReleaseResult::class)->handle(
        $attempt,
        $attempt->resultDocuments()->where('status', 'ready')->latest()->firstOrFail(),
        $guidance,
        'rel-'.substr($email, 0, 8),
        $releaseMode,
    );

    return [$attempt, $applicant, $guidance, $registrar];
}
