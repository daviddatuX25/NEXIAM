<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\User;
use App\Release\Actions\ReleaseResult;
use Tests\TestCase;

/**
 * Establishes only the authenticated surface supported by the actor's existing
 * account records. Staff fixtures must already have a legitimate active
 * profile and authentication state; this helper never provisions either.
 */
function actingAsMessagingActor(TestCase $test, User $actor): TestCase
{
    $actor = User::query()
        ->with(['staffProfile', 'authenticationState', 'applicantAccount'])
        ->findOrFail($actor->id);

    if ($actor->staffProfile !== null) {
        if ($actor->authenticationState === null) {
            throw new RuntimeException(
                'Messaging staff actor is missing its staff authentication state; use a fully onboarded staff fixture.'
            );
        }

        return actingAsAdmissionSupportStaff($test, $actor);
    }

    if ($actor->authenticationState !== null) {
        throw new RuntimeException(
            'Messaging actor has staff authentication state but no staff profile; use a valid staff fixture or an applicant account.'
        );
    }

    return $test->actingAs($actor)->withSession([
        'auth.surface' => $actor->applicantAccount !== null ? 'applicant' : 'neutral',
        'auth.session_version' => null,
    ]);
}

/**
 * Creates only the synthetic applicant and source-backed prerequisite rows used
 * to exercise case-messaging eligibility without running the expensive OMR
 * pipeline. It deliberately does not create a selection, follow-up, pool, or
 * message root, and does not prove any exam, result, release, or consultation
 * workflow. A real accepted public submission and access-key authentication
 * are retained; subsequent rows are labeled synthetic prerequisites.
 *
 * @return array{AdmissionAttempt, User, User, User}
 */
function buildMessagingReadyAttempt(TestCase $test, string $email): array
{
    seedE2Foundation();

    $digest = substr(hash('sha256', $email), 0, 12);
    $submitted = submitSyntheticApplicant($email, 'Messaging'.$digest);
    $attempt = $submitted['attempt'];
    $attempt->forceFill(['status' => AdmissionAttempt::STATUS_ACCEPTED])->save();

    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $submitted['access_token'],
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

    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $session = ExamSession::query()->create([
        'code' => 'MSG-'.$digest,
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'starts_at' => now()->subHours(3),
        'effective_end_at' => now()->subHours(1),
        'status' => 'closed',
    ]);
    ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
    ]);
    GradingSession::query()->create([
        'exam_session_id' => $session->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subHours(2),
        'finalized_at' => now()->subHour(),
    ]);

    $document = ResultDocument::query()->create([
        'admission_attempt_id' => $attempt->id,
        'template_version' => 1,
        'checksum' => 'synthetic-messaging-'.$digest,
        'status' => ResultDocument::STATUS_READY,
        'version' => 1,
        'snapshot_payload_json' => ['synthetic_messaging_prerequisite' => true],
    ]);
    $attempt->forceFill(['current_result_document_id' => $document->id])->save();
    ResultRelease::query()->create([
        'admission_attempt_id' => $attempt->id,
        'result_document_id' => $document->id,
        'released_by_user_id' => $guidance->id,
        'release_version' => 1,
        'is_current' => true,
        'released_at' => now(),
        'mode' => ReleaseResult::MODE_F2F,
        'channel' => 'physical',
        'idempotency_key' => 'msg-release-'.$digest,
    ]);
    ConsultationDecision::query()->create([
        'admission_attempt_id' => $attempt->id,
        'decision_type' => 'approve',
        'counselor_user_id' => $guidance->id,
        'decided_at' => now(),
        'idempotency_key' => 'msg-consult-'.$digest,
    ]);

    actingAsMessagingActor($test, $guidance);

    return [$attempt, $applicant, $guidance, $registrar];
}
