<?php

declare(strict_types=1);

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Assessment\Actions\RecordScores;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\CreateDirectAssessmentCohort;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentConversionRevision;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

require_once __DIR__.'/E2Helpers.php';

require_once __DIR__.'/TestKitHelpers.php';
require_once __DIR__.'/NormConversionFixtures.php';

/**
 * Build two accepted, profile-bound applicants in one completed exam sitting
 * with an open grading ledger. No applicant is scored by this fixture.
 * This is a synthetic software-test Given, not institutional assessment evidence.
 *
 * @return array{
 *     profile_version: TestInstrumentVersion,
 *     kit_version: TestKitVersion,
 *     session: ExamSession,
 *     grading_session: GradingSession,
 *     attempts: array{complete: AdmissionAttempt, incomplete: AdmissionAttempt},
 *     access_tokens: array{complete: string, incomplete: string},
 *     assignments: array{complete: ExamAssignment, incomplete: ExamAssignment},
 *     administrations: array{complete: AssessmentAdministration, incomplete: AssessmentAdministration}
 * }
 */
function individualReleaseReadinessFixture(TestCase $test, User $actor, ?string $suffix = null): array
{
    $suffix = strtoupper($suffix ?? Str::random(10));
    $capabilities = [
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
    ];
    foreach ($capabilities as $capability) {
        Permission::findOrCreate($capability, 'web');
    }
    $actor->givePermissionTo($capabilities);

    $profileVersion = createTestApprovedProfileVersion($test, $actor, 'INDIVIDUAL-READINESS-'.$suffix);
    $kitVersion = createTestActiveKitVersion($actor, $profileVersion);
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $submittedComplete = submitSyntheticApplicant('individual-ready-'.$suffix.'@example.test', 'Ready '.$suffix);
    $submittedIncomplete = submitSyntheticApplicant('individual-incomplete-'.$suffix.'@example.test', 'Incomplete '.$suffix);
    $attempts = [
        'complete' => app(AcceptAdmissionAttempt::class)->handle($submittedComplete['attempt'], $registrar, 1),
        'incomplete' => app(AcceptAdmissionAttempt::class)->handle($submittedIncomplete['attempt'], $registrar, 1),
    ];
    $cycle = AcademicCycle::query()->findOrFail($attempts['complete']->academic_cycle_id);
    if ((int) $attempts['incomplete']->academic_cycle_id !== (int) $cycle->id) {
        throw new RuntimeException('The synthetic scoring candidates must share one academic cycle.');
    }

    $room = Room::query()
        ->where('institution_id', $cycle->institution_id)
        ->where('capacity', '>', 1)
        ->orderBy('id')
        ->firstOrFail();
    $assessment = app(CreateDirectAssessmentCohort::class)->handle(
        cycle: $cycle,
        attemptIds: [$attempts['complete']->id, $attempts['incomplete']->id],
        actor: $actor,
        label: 'Individual scoring readiness '.$suffix,
        room: $room,
        kitVersion: $kitVersion,
    );
    /** @var ExamSession $session */
    $session = $assessment['session'];
    /** @var GradingSession $gradingSession */
    $gradingSession = $assessment['grading_session'];
    $assignmentsByAttempt = ExamAssignment::query()
        ->where('exam_session_id', $session->id)
        ->whereIn('admission_attempt_id', array_map(static fn (AdmissionAttempt $attempt): int => (int) $attempt->id, $attempts))
        ->get()
        ->keyBy('admission_attempt_id');
    $administrationsByAttempt = AssessmentAdministration::query()
        ->whereIn('exam_assignment_id', $assignmentsByAttempt->pluck('id'))
        ->whereNull('superseded_at')
        ->get()
        ->keyBy('admission_attempt_id');

    return [
        'profile_version' => $profileVersion,
        'kit_version' => $kitVersion,
        'session' => $session,
        'grading_session' => $gradingSession,
        'attempts' => $attempts,
        'access_tokens' => [
            'complete' => $submittedComplete['access_token'],
            'incomplete' => $submittedIncomplete['access_token'],
        ],
        'assignments' => [
            'complete' => $assignmentsByAttempt->get($attempts['complete']->id),
            'incomplete' => $assignmentsByAttempt->get($attempts['incomplete']->id),
        ],
        'administrations' => [
            'complete' => $administrationsByAttempt->get($attempts['complete']->id),
            'incomplete' => $administrationsByAttempt->get($attempts['incomplete']->id),
        ],
    ];
}

/**
 * Record one valid profile-bound external final through the ordinary scoring Action.
 * All labels/provenance are synthetic and only suitable for software tests.
 *
 * @param  array{
 *     profile_version: TestInstrumentVersion,
 *     kit_version: TestKitVersion,
 *     session: ExamSession,
 *     grading_session: GradingSession,
 *     attempts: array{complete: AdmissionAttempt, incomplete: AdmissionAttempt},
 *     assignments: array{complete: ExamAssignment, incomplete: ExamAssignment},
 *     administrations: array{complete: AssessmentAdministration, incomplete: AssessmentAdministration}
 * }  $fixture
 * @return array{score_records: list<ScoreRecord>, conversion: AssessmentConversionRevision}
 */
function recordIndividualReleaseExternalFinal(
    array $fixture,
    string $candidateKey,
    User $actor,
    string $percentile = '82',
    string $stanine = '7',
    ?string $idempotencyKey = null,
): array {
    if (! array_key_exists($candidateKey, $fixture['attempts'])) {
        throw new InvalidArgumentException('The candidate key must be complete or incomplete.');
    }

    $attempt = $fixture['attempts'][$candidateKey];
    $scoreRecords = app(RecordScores::class)->handle(
        gradingSession: $fixture['grading_session'],
        attempt: $attempt,
        scores: [[
            'metric_code' => 'RAW_A',
            'typed_outputs' => [
                'percentile_rank' => $percentile,
                'stanine' => $stanine,
            ],
            'external_provenance' => 'Synthetic software-test external final; no institutional result claim.',
        ]],
        actor: $actor,
        idempotencyKey: $idempotencyKey ?? 'individual-release-final-'.$attempt->id.'-'.Str::uuid(),
        scoringMode: 'manual_converted',
        testKitVersionId: (int) $fixture['kit_version']->id,
    );
    $conversion = AssessmentConversionRevision::query()
        ->where('administration_id', $fixture['administrations'][$candidateKey]->id)
        ->where('is_current', true)
        ->firstOrFail();

    return [
        'score_records' => $scoreRecords,
        'conversion' => $conversion,
    ];
}
