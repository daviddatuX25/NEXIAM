<?php

const DEFAULT_PROGRAM = 'BSN';
const OTHER_PROGRAM = 'BSPSY';

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\RecordExamSubmission;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Release\Actions\PrepareResultDocument;
use App\Release\Actions\RecordConsultationDecision;
use App\Release\Actions\ReleaseResult;
use Tests\TestCase;

function actingAsAdmissionSupportStaff(TestCase $test, User $staff): TestCase
{
    $staff = User::query()->with('authenticationState')->findOrFail($staff->id);
    if ($staff->authenticationState === null) {
        throw new RuntimeException('Seeded staff authentication state is required for an admission-support test session.');
    }

    app('session')->invalidate();

    return $test->actingAs($staff);
}

/**
 * Drive an attempt to result-released + advised through the real HTTP/action
 * pipeline. Expensive (~6-8s per call) but exercises the domain truth.
 *
 * @return array{attempt: AdmissionAttempt, applicant: User, guidance: User, registrar: User}
 */
function buildReleasedAdvisedAttempt(object $test, string $email, int $roomVariant = 0, string $releaseMode = ReleaseResult::MODE_F2F): array
{
    seedE2Foundation();
    seedE2OmrFoundation();
    $result = submitSyntheticApplicant($email, 'ProgSel'.substr(md5($email), 0, 6));
    $attempt = $result['attempt'];

    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $result['access_token'],
        '2001-01-01',
    );

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    foreach ([DEFAULT_PROGRAM, OTHER_PROGRAM] as $programCode) {
        $program = Program::query()->where('code', $programCode)->firstOrFail();
        ProgramQuota::query()->updateOrCreate(
            ['program_id' => $program->id, 'academic_cycle_id' => $cycle->id],
            ['slots' => 5],
        );
    }
    actingAsAdmissionSupportStaff($test, $registrar)->post(route('admissions.accept', $attempt), ['expected_version' => 1]);
    $attempt->refresh();
    $room = Room::query()->where('code', $roomVariant === 0 ? 'RM-101' : 'RM-LabA')->firstOrFail();
    enableE2AdvancedExamFeaturePolicy($guidance);
    $booklet = ensurePublishReadyExamMaterials($guidance, $room)['booklet'];
    $sessionCode = 'EX-PS-'.substr($email, 0, 8);
    actingAsAdmissionSupportStaff($test, $guidance)->post(route('exams.sessions.store'), [
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'booklet_version_id' => $booklet->id,
        'code' => $sessionCode,
        'starts_at' => now()->subHours(2)->toDateTimeString(),
        'effective_end_at' => now()->addMinutes(30)->toDateTimeString(),
    ]);
    $createErrors = session('errors');
    if ($createErrors !== null && $createErrors->any()) {
        throw new RuntimeException('exam session create errors: '.implode(' | ', $createErrors->all()));
    }

    $session = ExamSession::query()->where('code', $sessionCode)->firstOrFail();

    actingAsAdmissionSupportStaff($test, $guidance)->post(route('exams.sessions.assign', $session), [
        'admission_attempt_id' => $attempt->id,
    ]);
    $assignmentErrors = session('errors');
    if ($assignmentErrors !== null && $assignmentErrors->any()) {
        throw new RuntimeException('exam assignment errors: '.implode(' | ', $assignmentErrors->all()));
    }
    $publishResponse = actingAsAdmissionSupportStaff($test, $guidance)->post(route('exams.sessions.publish', $session), ['expected_version' => 1]);
    $errors = session('errors');
    if ($errors !== null && $errors->any()) {
        $bookletSets = OmrBookletSet::query()
            ->where('booklet_version_id', $session->booklet_version_id)
            ->get(['set_code', 'status', 'finalized_at'])
            ->map(fn (OmrBookletSet $set): array => [
                'code' => $set->set_code,
                'status' => $set->status,
                'finalized_at' => $set->finalized_at?->toIso8601String(),
            ])
            ->all();
        throw new RuntimeException('publish errors: '.implode(' | ', $errors->all()).'; session booklet #'.$session->booklet_version_id.' sets '.json_encode($bookletSets, JSON_THROW_ON_ERROR));
    }
    $session->refresh();
    if ($session->status !== 'published') {
        throw new RuntimeException('session status after publish: '.$session->status.' version '.$session->version);
    }

    $assignment = $session->assignments()->first();
    app(RecordExamAttendance::class)->handle($assignment, $guidance, 'present', 'att-'.substr($email, 0, 8));
    app(RecordExamSubmission::class)->handle($assignment, $guidance, 'sub-'.substr($email, 0, 8));

    actingAsAdmissionSupportStaff($test, $guidance)->post(route('exams.sessions.close', $session), ['expected_version' => 2]);

    $grading = GradingSession::query()->orderByDesc('id')->firstOrFail();
    $areas = AptitudeArea::query()->where('is_active', true)->get();

    actingAsAdmissionSupportStaff($test, $guidance)->put(route('grading.sessions.attempts.scores.update', [$grading, $attempt]), [
        'scores' => $areas->map(fn ($area) => [
            'aptitude_area_id' => $area->id,
            'score' => min(20, (int) $area->max_items),
        ])->all(),
        'idempotency_key' => 'scores-'.substr($email, 0, 8),
    ]);

    actingAsAdmissionSupportStaff($test, $guidance)->post(route('grading.finalize', $grading));

    $consSession = ConsultationSession::query()->firstOrCreate(
        ['code' => 'CONS-SEED-'.substr(md5($email), 0, 8)],
        [
            'academic_cycle_id' => $cycle->id,
            'room_id' => $room->id,
            'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
            'status' => ConsultationSession::STATUS_PUBLISHED,
            'scheduled_at' => now()->addDays(2),
            'version' => 1,
        ]
    );

    ConsultationAssignment::query()->firstOrCreate(
        ['admission_attempt_id' => $attempt->id],
        [
            'consultation_session_id' => $consSession->id,
            'status' => ConsultationAssignment::STATUS_ASSIGNED,
            'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
        ]
    );

    app(PrepareResultDocument::class)->handle($attempt, $guidance);
    $release = app(ReleaseResult::class)->handle(
        $attempt,
        $attempt->resultDocuments()->where('status', 'ready')->latest()->firstOrFail(),
        $guidance,
        'rel-'.substr($email, 0, 8),
        $releaseMode,
    );

    if ($releaseMode === ReleaseResult::MODE_F2F) {
        $release->forceFill(['result_printed_at' => now()])->save();
    }

    config(['admission.release_mode' => $releaseMode]);
    app(RecordConsultationDecision::class)->handle($attempt, $guidance, 'approve', null, 'consult-'.substr($email, 0, 8));

    return [$attempt, $applicant, $guidance, $registrar];
}
