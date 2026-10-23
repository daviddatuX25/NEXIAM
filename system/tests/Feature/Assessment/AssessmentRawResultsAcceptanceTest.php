<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Assessment\Actions\AcceptAssessmentRawResults;
use App\Assessment\Support\GradingRoster;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\BindAssessmentAdministration;
use App\Examination\Actions\CreateExamSession;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\AssessmentScoringRun;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

beforeEach(function (): void {
    seedE2Foundation();

    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $capabilities = [
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
        CapabilityCatalog::GUIDANCE_SCORING_IMPORT,
        CapabilityCatalog::GUIDANCE_SCORING_OMR,
    ];

    foreach ($capabilities as $capability) {
        Permission::findOrCreate($capability, 'web');
    }

    $this->guidance->givePermissionTo($capabilities);
});

/**
 * @return array<string, mixed>
 */
function assessmentRawAcceptanceProfilePayload(string $code): array
{
    $items = static fn (string $prefix): array => array_map(
        static fn (int $number): array => [
            'item_code' => "{$prefix}_ITEM_{$number}",
            'local_number' => (string) $number,
            'display_order' => $number,
            'is_scored' => true,
            'option_tokens' => ['A', 'B'],
        ],
        range(1, 2),
    );
    $inputContract = static fn (string $unit): array => [
        'allowed_sources' => ['import', 'manual'],
        'required_resources' => [],
        'source_requirements' => [
            'import' => ['unit' => $unit, 'identity_requirements' => ['administration', 'metric']],
            'manual' => ['unit' => $unit, 'identity_requirements' => ['administration', 'metric']],
        ],
    ];

    return [
        'request_id' => (string) Str::uuid(),
        'profile' => [
            'code' => $code,
            'display_name' => 'Assessment raw-results acceptance fixture',
            'publisher_name' => 'Isolated software test fixture',
            'form_code' => 'RAW-ACCEPTANCE-FORM',
            'edition' => 'Test-only source fixture',
            'language' => 'Test-only',
            'source_reference' => "test-fixture://assessment-raw-results/{$code}",
            'permitted_use_reference' => 'test-fixture://assessment-raw-results-permission',
            'material_requirement' => 'no_paper',
            'administration_procedure_reference' => 'Direct manual intake test procedure',
        ],
        'subtests' => [
            [
                'code' => 'VOCABULARY',
                'name' => 'Vocabulary',
                'display_order' => 1,
                'response_mode' => 'keyed_items',
                'item_order_policy' => 'fixed',
                'items' => $items('VOCABULARY'),
            ],
            [
                'code' => 'COMPUTATION',
                'name' => 'Computation',
                'display_order' => 2,
                'response_mode' => 'keyed_items',
                'item_order_policy' => 'fixed',
                'items' => $items('COMPUTATION'),
            ],
            [
                'code' => 'DURATION_SECTION',
                'name' => 'Timed measurement',
                'display_order' => 3,
                'response_mode' => 'recorded_measure',
                'item_order_policy' => 'fixed',
                'items' => [],
            ],
        ],
        'metrics' => [
            [
                'code' => 'VOCABULARY',
                'name' => 'Vocabulary raw count',
                'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
                'subtest_code' => 'VOCABULARY',
                'unit' => 'count',
                'min_raw_value' => null,
                'max_raw_value' => null,
                'precision_scale' => 0,
                'is_required' => true,
                'required_output_scales' => ['percentile_rank'],
                'procedure_reference' => null,
                'construct_aptitude_area_id' => null,
                'input_codes' => [],
                'input_contract_json' => $inputContract('count'),
            ],
            [
                'code' => 'COMPUTATION',
                'name' => 'Computation raw count',
                'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
                'subtest_code' => 'COMPUTATION',
                'unit' => 'count',
                'min_raw_value' => null,
                'max_raw_value' => null,
                'precision_scale' => 0,
                'is_required' => true,
                'required_output_scales' => ['percentile_rank'],
                'procedure_reference' => null,
                'construct_aptitude_area_id' => null,
                'input_codes' => [],
                'input_contract_json' => $inputContract('count'),
            ],
            [
                'code' => 'DURATION',
                'name' => 'Timed measurement',
                'rule_kind' => TestInstrumentMetric::RULE_RECORDED_MEASURE,
                'subtest_code' => 'DURATION_SECTION',
                'unit' => 'seconds',
                'min_raw_value' => '0',
                'max_raw_value' => '250',
                'precision_scale' => 2,
                'is_required' => true,
                'required_output_scales' => ['percentile_rank'],
                'procedure_reference' => 'test-only recorded-measure procedure',
                'construct_aptitude_area_id' => null,
                'input_codes' => [],
                'input_contract_json' => $inputContract('seconds'),
            ],
            [
                'code' => 'TOTAL',
                'name' => 'Vocabulary and computation composite',
                'rule_kind' => TestInstrumentMetric::RULE_SUM_RAW_METRICS,
                'subtest_code' => null,
                'unit' => 'count',
                'min_raw_value' => null,
                'max_raw_value' => null,
                'precision_scale' => 0,
                'is_required' => true,
                'required_output_scales' => ['percentile_rank'],
                'procedure_reference' => null,
                'construct_aptitude_area_id' => null,
                'input_codes' => ['VOCABULARY', 'COMPUTATION'],
                'input_contract_json' => $inputContract('count'),
            ],
        ],
    ];
}

function assessmentRawAcceptanceApprovedProfile(TestCase $test, User $actor, string $code): TestInstrumentVersion
{
    $payload = assessmentRawAcceptanceProfilePayload($code);

    actingAsAdmissionSupportStaff($test, $actor)
        ->post(route('setup.test-instruments.store'), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $instrument = TestInstrument::query()->where('code', $code)->firstOrFail();
    $draft = $instrument->draftVersion()->firstOrFail();

    actingAsAdmissionSupportStaff($test, $actor)
        ->post(route('setup.test-instruments.versions.approve', [
            'instrument' => $instrument->id,
            'version' => $draft->id,
        ]), [
            'request_id' => (string) Str::uuid(),
            'expected_revision' => $draft->lock_version,
            'source_review_attestation' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    return $draft->fresh();
}

/**
 * @return array{
 *     attempt: AdmissionAttempt,
 *     grading: GradingSession,
 *     administration: AssessmentAdministration,
 *     assignment: ExamAssignment,
 *     kitVersion: TestKitVersion
 * }
 */
function assessmentRawAcceptanceEligibleAdministration(
    TestCase $test,
    User $scorer,
    TestInstrumentVersion $profile,
    string $email,
    string $labelSuffix,
): array {
    $kitVersion = testKitSetupOnlyFixture($scorer, $profile)['version'];
    $kitVersion->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'activated_at' => now(),
        'activated_by_user_id' => $scorer->id,
        'revision' => (int) $kitVersion->revision + 1,
    ])->save();
    $submittedAttempt = submitSyntheticApplicant($email, 'Raw Results '.$labelSuffix)['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $attempt = app(AcceptAdmissionAttempt::class)->handle($submittedAttempt, $registrar, 1);
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $label = 'Raw results '.$labelSuffix;

    actingAsAdmissionSupportStaff($test, $scorer)
        ->post(route('exams.direct-assessment.store'), [
            'academic_cycle_id' => $cycle->id,
            'applicant_ids' => [$attempt->id],
            'label' => $label,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'test_kit_version_id' => $kitVersion->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $session = ExamSession::query()->where('label', $label)->firstOrFail();
    $assignment = ExamAssignment::query()
        ->where('exam_session_id', $session->id)
        ->where('admission_attempt_id', $attempt->id)
        ->firstOrFail()
        ->load(['attendanceRecord', 'submissionRecord']);
    $grading = GradingSession::query()->where('exam_session_id', $session->id)->firstOrFail();
    $administration = AssessmentAdministration::query()
        ->where('exam_assignment_id', $assignment->id)
        ->active()
        ->firstOrFail();

    expect(GradingRoster::isEligible($assignment))->toBeTrue()
        ->and(GradingRoster::includesAttempt($grading, (int) $attempt->id))->toBeTrue()
        ->and($administration->admission_attempt_id)->toBe($attempt->id)
        ->and($administration->test_instrument_version_id)->toBe($profile->id)
        ->and($administration->test_kit_version_id)->toBe($kitVersion->id);

    return [
        'attempt' => $attempt,
        'grading' => $grading,
        'administration' => $administration,
        'assignment' => $assignment,
        'kitVersion' => $kitVersion,
    ];
}

/**
 * @param  array<string, int|float|string>  $metricValues
 */
function submitAssessmentRawMetrics(
    TestCase $test,
    User $actor,
    GradingSession $grading,
    AdmissionAttempt $attempt,
    array $metricValues,
    string $idempotencyKey,
): TestResponse {
    $scores = [];
    foreach ($metricValues as $metricCode => $value) {
        $scores[] = ['metric_code' => $metricCode, 'score' => $value];
    }

    return actingAsAdmissionSupportStaff($test, $actor)->put(
        route('grading.sessions.attempts.scores.update', [$grading, $attempt]),
        [
            'scores' => $scores,
            'idempotency_key' => $idempotencyKey,
        ],
    );
}

test('Setup-only exact Kit binding is reused and manual raw zero remains distinct from missing or disallowed source', function (): void {
    $profile = assessmentRawAcceptanceApprovedProfile($this, $this->guidance, 'RAW-SETUP-ONLY-CURRENT-ACTION');
    $kitVersion = testKitSetupOnlyFixture($this->guidance, $profile)['version'];
    // A retained Active state here exercises use only; it is not activation or evidence.
    $kitVersion->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'activated_at' => now(),
        'activated_by_user_id' => $this->guidance->id,
        'revision' => (int) $kitVersion->revision + 1,
    ])->save();
    $cycle = AcademicCycle::query()->where('institution_id', $kitVersion->institution_id)->firstOrFail();
    $room = Room::query()->where('institution_id', $kitVersion->institution_id)->firstOrFail();
    $startsAt = now()->addDays(5)->setTime(9, 0);
    $session = app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        'RAW-SETUP-ONLY-'.Str::upper(Str::random(8)),
        $startsAt->toDateTimeString(),
        $startsAt->copy()->addHours(2)->toDateTimeString(),
        $this->guidance,
        ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        testKitVersionId: (int) $kitVersion->id,
    );
    $attempt = app(AcceptAdmissionAttempt::class)->handle(
        submitSyntheticApplicant('raw-setup-only-'.Str::random(8).'@example.test', 'Setup-only raw')['attempt'],
        User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail(),
        1,
    );
    $assignment = app(AssignApplicantToExamSession::class)->handle($session, $attempt, $this->guidance);
    $binder = app(BindAssessmentAdministration::class);
    $administration = $binder->handle($assignment, $kitVersion, $this->guidance, 'manual');
    $reused = $binder->handle($assignment, $kitVersion, $this->guidance, 'manual');

    expect($reused->id)->toBe($administration->id)
        ->and(AssessmentAdministration::query()->where('exam_assignment_id', $assignment->id)->count())->toBe(1)
        ->and($administration->test_kit_version_id)->toBe($kitVersion->id)
        ->and($administration->test_instrument_version_id)->toBe($profile->id)
        ->and($administration->manifest_sha256)->toBe($kitVersion->manifest_sha256)
        ->and($administration->acceptance_receipt_id)->toBeNull()
        ->and($administration->material_snapshot_id)->toBeNull()
        ->and($administration->exam_session_seat_id)->toBeNull();

    expect(fn () => app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['VOCABULARY' => null],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: 'setup-only-missing-value',
    ))->toThrow(ValidationException::class);

    expect(fn () => app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['VOCABULARY' => 1],
        source: AssessmentScoringRun::SOURCE_OMR,
        actor: $this->guidance,
        idempotencyKey: 'setup-only-disallowed-source',
    ))->toThrow(ValidationException::class);

    app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['VOCABULARY' => 0],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: 'setup-only-explicit-zero',
    );
    expect(AssessmentRawResultRevision::query()
        ->where('administration_id', $administration->id)
        ->where('metric_code', 'VOCABULARY')
        ->where('is_current', true)
        ->value('raw_value'))->toBe('0.000000');
});

test('raw metrics are retained incrementally and composites derive only from current inputs in the same administration', function (): void {
    $profile = assessmentRawAcceptanceApprovedProfile($this, $this->guidance, 'RAW-ADMINISTRATION-ISOLATION');
    $first = assessmentRawAcceptanceEligibleAdministration(
        $this,
        $this->guidance,
        $profile,
        'raw-results-first@example.test',
        'first administration',
    );
    $second = assessmentRawAcceptanceEligibleAdministration(
        $this,
        $this->guidance,
        $profile,
        'raw-results-second@example.test',
        'second administration',
    );
    $legacyScoreCount = ScoreRecord::query()->count();

    submitAssessmentRawMetrics($this, $this->guidance, $first['grading'], $first['attempt'], [
        'VOCABULARY' => 2,
    ], 'raw-first-vocabulary')
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    submitAssessmentRawMetrics($this, $this->guidance, $second['grading'], $second['attempt'], [
        'COMPUTATION' => 1,
    ], 'raw-second-computation')
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $firstVocabulary = AssessmentRawResultRevision::query()
        ->where('administration_id', $first['administration']->id)
        ->where('metric_code', 'VOCABULARY')
        ->where('is_current', true)
        ->firstOrFail();
    $secondComputation = AssessmentRawResultRevision::query()
        ->where('administration_id', $second['administration']->id)
        ->where('metric_code', 'COMPUTATION')
        ->where('is_current', true)
        ->firstOrFail();

    expect(AssessmentRawResultRevision::query()
        ->where('administration_id', $first['administration']->id)
        ->current()
        ->pluck('metric_code')
        ->all())->toBe(['VOCABULARY'])
        ->and($first['administration']->fresh()->binding_state)->toBe(AssessmentAdministration::STATE_RAW_INCOMPLETE)
        ->and(AssessmentRawResultRevision::query()
            ->where('administration_id', $first['administration']->id)
            ->where('metric_code', 'TOTAL')
            ->where('is_current', true)
            ->exists())->toBeFalse()
        ->and(AssessmentRawResultRevision::query()
            ->where('administration_id', $second['administration']->id)
            ->current()
            ->pluck('metric_code')
            ->all())->toBe(['COMPUTATION'])
        ->and(AssessmentRawResultRevision::query()
            ->where('administration_id', $second['administration']->id)
            ->where('metric_code', 'TOTAL')
            ->where('is_current', true)
            ->exists())->toBeFalse();

    submitAssessmentRawMetrics($this, $this->guidance, $first['grading'], $first['attempt'], [
        'COMPUTATION' => 1,
        'DURATION' => '125.75',
    ], 'raw-first-remaining-metrics')
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $firstCurrent = AssessmentRawResultRevision::query()
        ->where('administration_id', $first['administration']->id)
        ->current()
        ->get()
        ->keyBy('metric_code');
    $total = $firstCurrent->get('TOTAL');
    $finalRun = AssessmentScoringRun::query()
        ->where('administration_id', $first['administration']->id)
        ->where('idempotency_key', 'raw-first-remaining-metrics')
        ->firstOrFail();

    expect($firstCurrent->keys()->all())->toEqualCanonicalizing([
        'VOCABULARY',
        'COMPUTATION',
        'DURATION',
        'TOTAL',
    ])
        ->and($firstCurrent->get('VOCABULARY')->id)->toBe($firstVocabulary->id)
        ->and($firstCurrent->get('COMPUTATION')->raw_value)->toBe('1.000000')
        ->and($firstCurrent->get('DURATION')->raw_value)->toBe('125.750000')
        ->and($total->raw_value)->toBe('3.000000')
        ->and($total->source)->toBe(AssessmentRawResultRevision::SOURCE_DERIVED_COMPOSITE)
        ->and($total->provenance_json['component_revision_ids'])->toBe([
            $firstVocabulary->id,
            $firstCurrent->get('COMPUTATION')->id,
        ])
        ->and($first['administration']->fresh()->binding_state)->toBe(AssessmentAdministration::STATE_AWAITING_NORM_CONVERSION)
        ->and($secondComputation->administration_id)->toBe($second['administration']->id)
        ->and(AssessmentRawResultRevision::query()
            ->where('administration_id', $second['administration']->id)
            ->where('metric_code', 'TOTAL')
            ->where('is_current', true)
            ->exists())->toBeFalse()
        ->and($finalRun->actor_user_id)->toBe($this->guidance->id)
        ->and(ScoreRecord::query()->count())->toBe($legacyScoreCount);
});

test('raw result replay is idempotent and a changed payload with the same key conflicts', function (): void {
    $profile = assessmentRawAcceptanceApprovedProfile($this, $this->guidance, 'RAW-REPLAY-CONFLICT');
    $fixture = assessmentRawAcceptanceEligibleAdministration(
        $this,
        $this->guidance,
        $profile,
        'raw-results-replay@example.test',
        'replay',
    );

    submitAssessmentRawMetrics($this, $this->guidance, $fixture['grading'], $fixture['attempt'], [
        'VOCABULARY' => 1,
    ], 'raw-replay-key')
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    submitAssessmentRawMetrics($this, $this->guidance, $fixture['grading'], $fixture['attempt'], [
        'VOCABULARY' => 1,
    ], 'raw-replay-key')
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $conflictingReplay = submitAssessmentRawMetrics(
        $this,
        $this->guidance,
        $fixture['grading'],
        $fixture['attempt'],
        ['VOCABULARY' => 2],
        'raw-replay-key',
    );
    $conflictingReplay->assertRedirect()->assertSessionHasErrors([
        'scores' => 'assessment_raw_results_idempotency_conflict',
    ]);

    expect(AssessmentScoringRun::query()->where('administration_id', $fixture['administration']->id)->count())->toBe(1)
        ->and(AssessmentRawResultRevision::query()->where('administration_id', $fixture['administration']->id)->count())->toBe(1)
        ->and(AssessmentRawResultRevision::query()
            ->where('administration_id', $fixture['administration']->id)
            ->where('metric_code', 'VOCABULARY')
            ->where('is_current', true)
            ->value('raw_value'))->toBe('1.000000')
        ->and(ScoreRecord::query()->where('admission_attempt_id', $fixture['attempt']->id)->exists())->toBeFalse();
});

test('raw replay rejects an actor or source change under the same idempotency key', function (): void {
    $profile = assessmentRawAcceptanceApprovedProfile($this, $this->guidance, 'RAW-REPLAY-ACTOR-SOURCE');
    $fixture = assessmentRawAcceptanceEligibleAdministration(
        $this,
        $this->guidance,
        $profile,
        'raw-results-replay-scope@example.test',
        'replay-scope',
    );
    $key = 'raw-replay-scope-key';
    app(AcceptAssessmentRawResults::class)->handle(
        administration: $fixture['administration'],
        metricValues: ['VOCABULARY' => 1],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: $key,
    );

    $anotherGuidance = User::factory()->create(['email' => 'guidance.replay-scope@nexiam.test']);
    $staffProfile = $this->guidance->staffProfile;
    $staffAttributes = $staffProfile->getAttributes();
    unset($staffAttributes['id'], $staffAttributes['user_id'], $staffAttributes['created_at'], $staffAttributes['updated_at']);
    $anotherGuidance->staffProfile()->create($staffAttributes);
    $anotherGuidance->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_MANUAL);

    expect(fn () => app(AcceptAssessmentRawResults::class)->handle(
        administration: $fixture['administration'],
        metricValues: ['VOCABULARY' => 1],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $anotherGuidance,
        idempotencyKey: $key,
    ))->toThrow(RuntimeException::class, 'assessment_raw_results_idempotency_conflict');

    expect(fn () => app(AcceptAssessmentRawResults::class)->handle(
        administration: $fixture['administration'],
        metricValues: ['VOCABULARY' => 1],
        source: AssessmentScoringRun::SOURCE_IMPORT,
        actor: $this->guidance,
        idempotencyKey: $key,
    ))->toThrow(RuntimeException::class, 'assessment_raw_results_idempotency_conflict');

    expect(AssessmentScoringRun::query()->where('administration_id', $fixture['administration']->id)->count())->toBe(1)
        ->and(AssessmentRawResultRevision::query()->where('administration_id', $fixture['administration']->id)->count())->toBe(1);
});

test('raw replay reauthorizes the persisted actor before returning the original idempotent run', function (): void {
    $profile = assessmentRawAcceptanceApprovedProfile($this, $this->guidance, 'RAW-REPLAY-REVOKED-ACTOR');
    $fixture = assessmentRawAcceptanceEligibleAdministration(
        $this,
        $this->guidance,
        $profile,
        'raw-results-replay-revoked@example.test',
        'replay-revoked',
    );
    $key = 'raw-replay-revoked-key';
    app(AcceptAssessmentRawResults::class)->handle(
        administration: $fixture['administration'],
        metricValues: ['VOCABULARY' => 1],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: $key,
    );
    $this->guidance->syncRoles([]);
    $this->guidance->syncPermissions([]);

    expect(fn () => app(AcceptAssessmentRawResults::class)->handle(
        administration: $fixture['administration'],
        metricValues: ['VOCABULARY' => 1],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: $key,
    ))->toThrow(AuthorizationException::class);

    expect(AssessmentScoringRun::query()->where('administration_id', $fixture['administration']->id)->count())->toBe(1)
        ->and(AssessmentRawResultRevision::query()
            ->where('administration_id', $fixture['administration']->id)
            ->where('metric_code', 'VOCABULARY')
            ->where('is_current', true)
            ->value('raw_value'))->toBe('1.000000');
});

test('profile precision and raw bounds allow an above-one-hundred boundary value and reject values outside it', function (): void {
    $profile = assessmentRawAcceptanceApprovedProfile($this, $this->guidance, 'RAW-PRECISION-BOUNDARY');
    $fixture = assessmentRawAcceptanceEligibleAdministration(
        $this,
        $this->guidance,
        $profile,
        'raw-results-boundary@example.test',
        'boundary',
    );

    submitAssessmentRawMetrics($this, $this->guidance, $fixture['grading'], $fixture['attempt'], [
        'DURATION' => '250.00',
    ], 'raw-boundary-valid')
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    submitAssessmentRawMetrics($this, $this->guidance, $fixture['grading'], $fixture['attempt'], [
        'DURATION' => '250.01',
    ], 'raw-boundary-over-max')
        ->assertRedirect()
        ->assertSessionHasErrors('scores');
    submitAssessmentRawMetrics($this, $this->guidance, $fixture['grading'], $fixture['attempt'], [
        'DURATION' => '100.001',
    ], 'raw-boundary-over-precision')
        ->assertRedirect()
        ->assertSessionHasErrors('scores');

    expect(AssessmentRawResultRevision::query()
        ->where('administration_id', $fixture['administration']->id)
        ->where('metric_code', 'DURATION')
        ->where('is_current', true)
        ->value('raw_value'))->toBe('250.000000')
        ->and(AssessmentScoringRun::query()->where('administration_id', $fixture['administration']->id)->count())->toBe(1)
        ->and(ScoreRecord::query()->where('admission_attempt_id', $fixture['attempt']->id)->exists())->toBeFalse();
});

test('an invalid submitted metric rolls back every other value in that vector', function (): void {
    $profile = assessmentRawAcceptanceApprovedProfile($this, $this->guidance, 'RAW-VECTOR-ROLLBACK');
    $fixture = assessmentRawAcceptanceEligibleAdministration(
        $this,
        $this->guidance,
        $profile,
        'raw-results-rollback@example.test',
        'rollback',
    );

    submitAssessmentRawMetrics($this, $this->guidance, $fixture['grading'], $fixture['attempt'], [
        'VOCABULARY' => 1,
    ], 'raw-rollback-baseline')
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    submitAssessmentRawMetrics($this, $this->guidance, $fixture['grading'], $fixture['attempt'], [
        'VOCABULARY' => 2,
        'COMPUTATION' => 1,
        'DURATION' => '250.01',
    ], 'raw-rollback-invalid-vector')
        ->assertRedirect()
        ->assertSessionHasErrors('scores');

    expect(AssessmentScoringRun::query()->where('administration_id', $fixture['administration']->id)->count())->toBe(1)
        ->and(AssessmentRawResultRevision::query()->where('administration_id', $fixture['administration']->id)->count())->toBe(1)
        ->and(AssessmentRawResultRevision::query()
            ->where('administration_id', $fixture['administration']->id)
            ->where('metric_code', 'VOCABULARY')
            ->where('is_current', true)
            ->value('raw_value'))->toBe('1.000000')
        ->and(AssessmentRawResultRevision::query()
            ->where('administration_id', $fixture['administration']->id)
            ->whereIn('metric_code', ['COMPUTATION', 'DURATION', 'TOTAL'])
            ->exists())->toBeFalse()
        ->and($fixture['administration']->fresh()->binding_state)->toBe(AssessmentAdministration::STATE_RAW_INCOMPLETE)
        ->and(ScoreRecord::query()->where('admission_attempt_id', $fixture['attempt']->id)->exists())->toBeFalse();
});
