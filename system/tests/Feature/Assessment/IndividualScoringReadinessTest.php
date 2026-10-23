<?php

declare(strict_types=1);

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Assessment\Actions\AcceptAssessmentRawResults;
use App\Assessment\Actions\ClearScores;
use App\Assessment\Actions\ConvertAssessmentResults;
use App\Assessment\Support\IndividualScoringReadiness;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\BindAssessmentAdministration;
use App\Examination\Actions\CreateDirectAssessmentCohort;
use App\Models\Assessment\AssessmentResultSet;
use App\Models\Assessment\AssessmentScoringRun;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/../../Support/IndividualReleaseReadinessFixtures.php';

beforeEach(function (): void {
    seedE2Foundation();
    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    foreach ([
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
    ] as $capability) {
        Permission::findOrCreate($capability, 'web');
    }
    $this->guidance->givePermissionTo([
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
    ]);
});

test('individual readiness separates a complete external final from an incomplete peer in one open grading session', function (): void {
    $fixture = individualReleaseReadinessFixture($this, $this->guidance, 'OPEN-PAIR');
    $externalFinal = recordIndividualReleaseExternalFinal($fixture, 'complete', $this->guidance);

    $readiness = app(IndividualScoringReadiness::class)->forAttempts(collect([
        $fixture['attempts']['complete'],
        $fixture['attempts']['incomplete'],
    ]));
    $complete = $readiness[$fixture['attempts']['complete']->id];
    $incomplete = $readiness[$fixture['attempts']['incomplete']->id];

    expect($fixture['grading_session']->fresh()->status)->toBe(GradingSession::STATUS_OPEN)
        ->and($complete['ready'])->toBeTrue()
        ->and($complete['state'])->toBe('ready_to_finalize')
        ->and($complete['grading_session_id'])->toBe((int) $fixture['grading_session']->id)
        ->and($complete['exam_session_id'])->toBe((int) $fixture['session']->id)
        ->and($complete['administration_id'])->toBe((int) $fixture['administrations']['complete']->id)
        ->and($complete['conversion_revision_id'])->toBe((int) $externalFinal['conversion']->id)
        ->and($complete['result_set_id'])->toBeNull()
        ->and($complete['score_locked'])->toBeFalse()
        ->and($incomplete['ready'])->toBeFalse()
        ->and($incomplete['reason'])->not->toBeEmpty()
        ->and($externalFinal['conversion']->source)->toBe('manual_converted')
        ->and($externalFinal['conversion']->norm_package_version_id)->toBeNull()
        ->and($externalFinal['conversion']->typed_outputs_json['RAW_A']['percentile_rank'])->toBe('82')
        ->and($externalFinal['conversion']->typed_outputs_json['RAW_A']['stanine'])->toBe('7')
        ->and($externalFinal['conversion']->provenance_json['external_provenance']['RAW_A'])->toContain('Synthetic software-test');
});

test('raw-only current input is awaiting conversion and cannot become score-ready', function (): void {
    $fixture = individualReleaseReadinessFixture($this, $this->guidance, 'RAW-ONLY');
    app(AcceptAssessmentRawResults::class)->handle(
        administration: $fixture['administrations']['complete'],
        metricValues: ['RAW_A' => '1'],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: 'individual-raw-only-'.Str::uuid(),
    );

    $readiness = app(IndividualScoringReadiness::class)->forAttempt($fixture['attempts']['complete']);

    expect($readiness['ready'])->toBeFalse()
        ->and($readiness['state'])->toBe('awaiting_norm_conversion')
        ->and($readiness['administration_id'])->toBe((int) $fixture['administrations']['complete']->id)
        ->and($readiness['conversion_revision_id'])->toBeNull();
});

test('a conversion tied to superseded raw revisions is no longer individually ready', function (): void {
    $fixture = individualReleaseReadinessFixture($this, $this->guidance, 'STALE-RAW');
    $administration = $fixture['administrations']['complete'];
    app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['RAW_A' => '1'],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: 'individual-raw-before-conversion-'.Str::uuid(),
    );
    $normVersion = createTestApprovedNormVersion($this->guidance, 'INDIVIDUAL-STALE-NORM-'.Str::upper(Str::random(8)));
    app(ConvertAssessmentResults::class)->handle($administration, $normVersion, $this->guidance);

    expect(app(IndividualScoringReadiness::class)->forAttempt($fixture['attempts']['complete'])['ready'])->toBeTrue();

    app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['RAW_A' => '2'],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: 'individual-raw-after-conversion-'.Str::uuid(),
    );
    $readiness = app(IndividualScoringReadiness::class)->forAttempt($fixture['attempts']['complete']);

    expect($readiness['ready'])->toBeFalse()
        ->and($readiness['state'])->toBe('awaiting_norm_conversion')
        ->and($readiness['conversion_revision_id'])->toBeNull();
});

test('readiness follows the exact current sitting instead of reusing scores from a prior sitting', function (): void {
    $fixture = individualReleaseReadinessFixture($this, $this->guidance, 'WRONG-SITTING');
    recordIndividualReleaseExternalFinal($fixture, 'complete', $this->guidance);
    $oldAssignment = $fixture['assignments']['complete'];
    $oldAssignment->forceFill([
        'status' => ExamAssignment::STATUS_REMOVED,
        'ended_at' => now(),
        'end_reason' => 'Synthetic test successor sitting.',
    ])->save();
    $cycle = AcademicCycle::query()->findOrFail($fixture['attempts']['complete']->academic_cycle_id);
    $nextSitting = app(CreateDirectAssessmentCohort::class)->handle(
        cycle: $cycle,
        attemptIds: [$fixture['attempts']['complete']->id],
        actor: $this->guidance,
        label: 'Current sitting after scored history '.Str::upper(Str::random(6)),
        room: Room::query()->where('code', 'RM-101')->firstOrFail(),
        kitVersion: $fixture['kit_version'],
    );

    $readiness = app(IndividualScoringReadiness::class)->forAttempt($fixture['attempts']['complete']);

    expect($readiness['ready'])->toBeFalse()
        ->and($readiness['exam_session_id'])->toBe((int) $nextSitting['session']->id)
        ->and($readiness['grading_session_id'])->toBe((int) $nextSitting['grading_session']->id)
        ->and($readiness['administration_id'])->not->toBe((int) $fixture['administrations']['complete']->id);
});

test('an exact frozen individual snapshot remains ready in an open batch and fences all lower-level mutations', function (): void {
    $fixture = individualReleaseReadinessFixture($this, $this->guidance, 'FROZEN-OPEN');
    $externalFinal = recordIndividualReleaseExternalFinal($fixture, 'complete', $this->guidance);
    $administration = $fixture['administrations']['complete'];
    $resultSet = AssessmentResultSet::query()->create([
        'administration_id' => $administration->id,
        'grading_session_id' => $fixture['grading_session']->id,
        'exam_session_id' => $fixture['session']->id,
        'raw_revision_vector_json' => [],
        'conversion_revision_vector_json' => [
            'conversion_revision_id' => $externalFinal['conversion']->id,
            'norm_package_version_id' => null,
            'revision_no' => $externalFinal['conversion']->revision_no,
            'status' => $externalFinal['conversion']->status,
            'typed_outputs' => $externalFinal['conversion']->typed_outputs_json,
            'source' => $externalFinal['conversion']->source,
            'provenance' => $externalFinal['conversion']->provenance_json,
        ],
        'status' => AssessmentResultSet::STATUS_FINALIZED,
        'finalized_by_user_id' => $this->guidance->id,
        'finalized_at' => now(),
    ]);

    $readiness = app(IndividualScoringReadiness::class)->forAttempt($fixture['attempts']['complete']);

    expect($fixture['grading_session']->fresh()->status)->toBe(GradingSession::STATUS_OPEN)
        ->and($readiness['ready'])->toBeTrue()
        ->and($readiness['state'])->toBe('frozen')
        ->and($readiness['score_locked'])->toBeTrue()
        ->and($readiness['result_set_id'])->toBe((int) $resultSet->id)
        ->and($readiness['finalized_at'])->toBe($resultSet->finalized_at->toIso8601String())
        ->and($readiness['score_locked_at'])->toBe($resultSet->finalized_at->toIso8601String());

    expect(fn () => app(ClearScores::class)->handle(
        $fixture['grading_session'],
        $fixture['attempts']['complete'],
        $this->guidance,
    ))->toThrow(RuntimeException::class, 'assessment_post_finalization_correction_blocked');
    expect(fn () => recordIndividualReleaseExternalFinal(
        $fixture,
        'complete',
        $this->guidance,
        idempotencyKey: 'frozen-individual-score-edit',
    ))->toThrow(RuntimeException::class, 'assessment_post_finalization_correction_blocked');

    expect(fn () => app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['RAW_A' => '1'],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: 'frozen-individual-raw-edit',
    ))->toThrow(RuntimeException::class, 'assessment_post_finalization_correction_blocked');

    $normVersion = createTestApprovedNormVersion(
        $this->guidance,
        'FROZEN-INDIVIDUAL-NORM-'.Str::upper(Str::random(6)),
    );
    expect(fn () => app(ConvertAssessmentResults::class)->handle(
        $administration,
        $normVersion,
        $this->guidance,
    ))->toThrow(RuntimeException::class, 'assessment_post_finalization_correction_blocked');

    expect(fn () => app(BindAssessmentAdministration::class)->handle(
        $fixture['assignments']['complete'],
        $fixture['kit_version'],
        $this->guidance,
    ))->toThrow(RuntimeException::class, 'assessment_post_finalization_correction_blocked');
    expect($fixture['grading_session']->fresh()->status)->toBe(GradingSession::STATUS_OPEN);
});

test('batched readiness uses a bounded number of database queries as roster size grows', function (): void {
    $fixture = individualReleaseReadinessFixture($this, $this->guidance, 'BATCH-QUERY');
    recordIndividualReleaseExternalFinal($fixture, 'complete', $this->guidance);
    $queryCount = 0;
    DB::listen(function (QueryExecuted $query) use (&$queryCount): void {
        $queryCount++;
    });

    app(IndividualScoringReadiness::class)->forAttempts(collect([
        $fixture['attempts']['complete'],
        $fixture['attempts']['incomplete'],
    ]));
    $twoAttemptQueries = $queryCount;
    $queryCount = 0;
    app(IndividualScoringReadiness::class)->forAttempt($fixture['attempts']['complete']);
    $oneAttemptQueries = $queryCount;

    expect($twoAttemptQueries)->toBe($oneAttemptQueries);
});
test('profile-bound readiness does not accept an untyped area row or ready state as a complete output set', function (): void {
    $fixture = individualReleaseReadinessFixture($this, $this->guidance, 'UNTYPED-FALLBACK');
    $administration = $fixture['administrations']['incomplete'];
    $area = AptitudeArea::query()->where('is_active', true)->firstOrFail();
    ScoreRecord::query()->create([
        'grading_session_id' => $fixture['grading_session']->id,
        'admission_attempt_id' => $fixture['attempts']['incomplete']->id,
        'aptitude_area_id' => $area->id,
        'score' => '80',
        'converted_score' => '80',
        'idempotency_key' => 'individual-untyped-'.Str::uuid(),
        'source' => ScoreRecord::SOURCE_MANUAL,
        'recorded_by_user_id' => $this->guidance->id,
    ]);
    $administration->update([
        'binding_state' => AssessmentAdministration::STATE_READY_TO_FINALIZE,
    ]);

    $readiness = app(IndividualScoringReadiness::class)->forAttempt($fixture['attempts']['incomplete']);

    expect($readiness['ready'])->toBeFalse()
        ->and($readiness['state'])->toBe('raw_incomplete')
        ->and($readiness['administration_id'])->toBe((int) $administration->id)
        ->and($readiness['result_set_id'])->toBeNull();
});
test('legacy untyped readiness preserves exact ledger and required-area completeness', function (): void {
    $submission = submitSyntheticApplicant('individual-legacy-'.Str::lower(Str::random(10)).'@example.test', 'Legacy');
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $attempt = app(AcceptAdmissionAttempt::class)->handle($submission['attempt'], $registrar, 1);
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'IND-LEGACY-'.Str::upper(Str::random(8)),
        'label' => 'Legacy untyped readiness',
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'status' => ExamSession::STATUS_COMPLETED,
        'test_instrument_version_id' => null,
        'test_kit_version_id' => null,
        'starts_at' => now()->subHours(2),
        'effective_end_at' => now()->subHour(),
        'closed_at' => now(),
        'version' => 1,
    ]);
    $assignment = ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
        'origin' => ExamAssignment::ORIGIN_MANUAL,
        'version' => 1,
    ]);
    ExamAttendanceRecord::query()->create([
        'exam_assignment_id' => $assignment->id,
        'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
        'recorded_by_user_id' => $this->guidance->id,
        'recorded_at' => now(),
        'idempotency_key' => 'individual-legacy-attendance-'.$assignment->id,
    ]);
    ExamSubmissionRecord::query()->create([
        'exam_assignment_id' => $assignment->id,
        'recorded_by_user_id' => $this->guidance->id,
        'submitted_at' => now(),
        'idempotency_key' => 'individual-legacy-submission-'.$assignment->id,
    ]);
    $gradingSession = GradingSession::query()->create([
        'exam_session_id' => $session->id,
        'status' => GradingSession::STATUS_OPEN,
        'opened_at' => now(),
        'opened_by_user_id' => $this->guidance->id,
        'version' => 1,
    ]);

    foreach (AptitudeArea::query()->where('is_active', true)->with('conversionRows')->get() as $area) {
        $score = (int) floor((int) $area->max_items * 0.8);
        ScoreRecord::query()->create([
            'grading_session_id' => $gradingSession->id,
            'admission_attempt_id' => $attempt->id,
            'aptitude_area_id' => $area->id,
            'score' => $score,
            'converted_score' => $area->conversionRows->firstWhere('min_score', '<=', $score)?->output_value,
            'idempotency_key' => 'individual-legacy-score-'.$assignment->id.'-'.$area->id,
            'source' => ScoreRecord::SOURCE_MANUAL,
            'recorded_by_user_id' => $this->guidance->id,
        ]);
    }

    $readiness = app(IndividualScoringReadiness::class)->forAttempt($attempt);

    expect($readiness['ready'])->toBeTrue()
        ->and($readiness['state'])->toBe('ready_to_finalize')
        ->and($readiness['exam_session_id'])->toBe((int) $session->id)
        ->and($readiness['grading_session_id'])->toBe((int) $gradingSession->id)
        ->and($readiness['administration_id'])->toBeNull()
        ->and($readiness['result_set_id'])->toBeNull()
        ->and($readiness['score_locked'])->toBeFalse()
        ->and($readiness['score_locked_at'])->toBeNull()
        ->and($readiness['current_fingerprint'])->toMatch('/^[a-f0-9]{64}$/');
});
