<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Assessment\Actions\AcceptAssessmentRawResults;
use App\Assessment\Actions\ApproveAssessmentNormVersion;
use App\Assessment\Actions\FinalizeGradingSession;
use App\Assessment\Actions\SaveAssessmentNormDraft;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentConversionRevision;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\AssessmentScoringRun;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

beforeEach(function (): void {
    $this->withoutVite();
    seedE2Foundation();
    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    foreach ([CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, CapabilityCatalog::GUIDANCE_SCORING_MANUAL] as $capability) {
        Permission::findOrCreate($capability, 'web');
    }
    $this->guidance->givePermissionTo([
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
    ]);
});

/**
 * @return array<string, mixed>
 */
function hybridScoringProfilePayload(string $code): array
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

    return [
        'request_id' => (string) Str::uuid(),
        'profile' => [
            'code' => $code,
            'display_name' => 'Hybrid scoring test fixture',
            'publisher_name' => 'Isolated software test fixture',
            'form_code' => 'RAW-ACCEPTANCE-FORM',
            'edition' => 'Test-only source fixture',
            'language' => 'Test-only',
            'source_reference' => "test-fixture://hybrid-scoring/{$code}",
            'permitted_use_reference' => 'test-fixture://hybrid-scoring-permission',
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
                'procedure_reference' => 'Test-only recorded-measure procedure',
                'construct_aptitude_area_id' => null,
                'input_codes' => [],
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
            ],
        ],
    ];
}

function hybridScoringApprovedProfile(TestCase $test, User $actor, string $code): TestInstrumentVersion
{
    actingAsAdmissionSupportStaff($test, $actor)
        ->post(route('setup.test-instruments.store'), hybridScoringProfilePayload($code))
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
 * @return array{attempt: AdmissionAttempt, grading: GradingSession, administration: AssessmentAdministration}
 */
function hybridScoringEligibleAdministration(TestCase $test, User $scorer, TestInstrumentVersion $profile, string $email, string $labelSuffix): array
{
    $submittedAttempt = submitSyntheticApplicant($email, 'Hybrid scoring '.$labelSuffix)['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $attempt = app(AcceptAdmissionAttempt::class)->handle($submittedAttempt, $registrar, 1);
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $label = 'Hybrid scoring '.$labelSuffix;
    actingAsAdmissionSupportStaff($test, $scorer)
        ->post(route('exams.direct-assessment.store'), [
            'academic_cycle_id' => $cycle->id,
            'applicant_ids' => [$attempt->id],
            'label' => $label,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'test_instrument_version_id' => $profile->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $session = ExamSession::query()->where('label', $label)->firstOrFail();
    $assignment = ExamAssignment::query()
        ->where('exam_session_id', $session->id)
        ->where('admission_attempt_id', $attempt->id)
        ->firstOrFail()
        ->load(['attendanceRecord', 'submissionRecord']);

    return [
        'attempt' => $attempt,
        'grading' => GradingSession::query()->where('exam_session_id', $session->id)->firstOrFail(),
        'administration' => AssessmentAdministration::query()->where('exam_assignment_id', $assignment->id)->active()->firstOrFail(),
    ];
}

function hybridScoringApprovedNormVersion(User $actor, string $code): AssessmentNormPackageVersion
{
    $intervals = [['min' => 0, 'max' => 250, 'percentile_rank' => 75]];
    $tables = [];
    foreach (['VOCABULARY', 'COMPUTATION', 'DURATION', 'TOTAL'] as $metricCode) {
        $tables[] = [
            'metric_code' => $metricCode,
            'output_scale' => 'percentile_rank',
            'intervals' => $intervals,
        ];
    }

    $draft = app(SaveAssessmentNormDraft::class)->handle(
        actor: $actor,
        package: null,
        version: null,
        expectedRevision: null,
        requestId: (string) Str::uuid(),
        input: [
            'code' => $code,
            'display_name' => "Hybrid scoring norm {$code}",
            'publisher_name' => 'Test norm publisher',
            'form_code' => 'RAW-ACCEPTANCE-FORM',
            'edition' => 'Test-only',
            'language' => 'Test-only',
            'applicable_population' => 'Test population',
            'source_reference' => 'test-fixture://hybrid-norm',
            'permitted_use_reference' => 'test-fixture://hybrid-norm-permission',
            'is_synthetic' => false,
            'tables' => $tables,
        ],
    );

    return app(ApproveAssessmentNormVersion::class)->handle(
        actor: $actor,
        package: $draft->norm_package_id,
        version: $draft->id,
        expectedRevision: $draft->lock_version,
        requestId: (string) Str::uuid(),
        attestationOrPayload: true,
    );
}

test('manual raw scoring retains revisions, derives composites, and converts current metrics', function (): void {
    $profile = hybridScoringApprovedProfile($this, $this->guidance, 'HYBRID-MODE-ONE');
    $fixture = hybridScoringEligibleAdministration($this, $this->guidance, $profile, 'hybrid-mode-one@example.test', 'mode one');
    $norm = hybridScoringApprovedNormVersion($this->guidance, 'HYBRID-NORM-ONE');

    actingAsAdmissionSupportStaff($this, $this->guidance)->put(route('grading.sessions.attempts.scores.update', [$fixture['grading'], $fixture['attempt']]), [
        'scoring_mode' => 'manual_raw',
        'scores' => [['metric_code' => 'VOCABULARY', 'score' => 2], ['metric_code' => 'COMPUTATION', 'score' => 1], ['metric_code' => 'DURATION', 'score' => '125.75']],
        'norm_package_version_id' => $norm->id,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $current = AssessmentRawResultRevision::query()->where('administration_id', $fixture['administration']->id)->current()->get()->keyBy('metric_code');
    expect($current->keys()->all())->toEqualCanonicalizing(['VOCABULARY', 'COMPUTATION', 'DURATION', 'TOTAL'])
        ->and($current->get('TOTAL')->raw_value)->toBe('3.000000')
        ->and(AssessmentConversionRevision::query()->where('administration_id', $fixture['administration']->id)->current()->exists())->toBeTrue()
        ->and($fixture['administration']->fresh()->binding_state)->toBe(AssessmentAdministration::STATE_READY_TO_FINALIZE);
});

test('manual converted scoring creates current direct conversion and finalizes without raw revisions', function (): void {
    $profile = hybridScoringApprovedProfile($this, $this->guidance, 'HYBRID-MODE-TWO');
    $fixture = hybridScoringEligibleAdministration($this, $this->guidance, $profile, 'hybrid-mode-two@example.test', 'mode two');
    actingAsAdmissionSupportStaff($this, $this->guidance)->put(route('grading.sessions.attempts.scores.update', [$fixture['grading'], $fixture['attempt']]), [
        'scoring_mode' => 'manual_converted',
        'scores' => [
            ['metric_code' => 'VOCABULARY', 'score' => 82],
            ['metric_code' => 'COMPUTATION', 'score' => 78],
            ['metric_code' => 'DURATION', 'score' => 85],
            ['metric_code' => 'TOTAL', 'score' => 80],
        ],
        'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $revision = AssessmentConversionRevision::query()->where('administration_id', $fixture['administration']->id)->current()->firstOrFail();
    expect($revision->source)->toBe('manual_converted')
        ->and($revision->norm_package_version_id)->toBeNull()
        ->and($fixture['administration']->fresh()->binding_state)->toBe(AssessmentAdministration::STATE_READY_TO_FINALIZE)
        ->and(AssessmentRawResultRevision::query()->where('administration_id', $fixture['administration']->id)->current()->exists())->toBeFalse();
    app(FinalizeGradingSession::class)->handle($fixture['grading'], $this->guidance);
});

test('hybrid manual raw input preserves OMR revisions and requires reasoned override to replace OMR', function (): void {
    $profile = hybridScoringApprovedProfile($this, $this->guidance, 'HYBRID-OMR-MANUAL');
    $fixture = hybridScoringEligibleAdministration($this, $this->guidance, $profile, 'hybrid-omr@example.test', 'hybrid OMR');
    $metrics = TestInstrumentMetric::query()->where('test_instrument_version_id', $profile->id)->whereIn('code', ['VOCABULARY', 'COMPUTATION'])->get()->keyBy('code');
    $omrRun = AssessmentScoringRun::query()->create([
        'administration_id' => $fixture['administration']->id,
        'run_uuid' => (string) Str::uuid(),
        'source' => AssessmentScoringRun::SOURCE_OMR,
        'actor_user_id' => $this->guidance->id,
        'idempotency_key' => (string) Str::uuid(),
        'created_at' => now(),
    ]);
    foreach (['VOCABULARY' => 2, 'COMPUTATION' => 1] as $code => $score) {
        $metric = $metrics->get($code);
        AssessmentRawResultRevision::query()->create([
            'administration_id' => $fixture['administration']->id,
            'scoring_run_id' => $omrRun->id,
            'metric_id' => $metric->id,
            'metric_code' => $metric->code,
            'revision_no' => 1,
            'raw_value' => (string) $score,
            'source' => AssessmentRawResultRevision::SOURCE_OMR,
            'status' => AssessmentRawResultRevision::STATUS_VALID,
            'provenance_json' => ['source' => AssessmentRawResultRevision::SOURCE_OMR],
            'is_current' => true,
            'superseded_at' => null,
            'created_at' => now(),
        ]);
    }
    actingAsAdmissionSupportStaff($this, $this->guidance)->put(route('grading.sessions.attempts.scores.update', [$fixture['grading'], $fixture['attempt']]), [
        'scoring_mode' => 'manual_raw', 'scores' => [['metric_code' => 'DURATION', 'score' => 125]], 'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $preservedOmrSources = AssessmentRawResultRevision::query()
        ->where('administration_id', $fixture['administration']->id)
        ->whereIn('metric_code', ['VOCABULARY', 'COMPUTATION'])
        ->orderBy('metric_code')
        ->current()
        ->pluck('source', 'metric_code')
        ->all();
    expect($preservedOmrSources)->toBe([
        'COMPUTATION' => AssessmentRawResultRevision::SOURCE_OMR,
        'VOCABULARY' => AssessmentRawResultRevision::SOURCE_OMR,
    ]);

    $unauthorizedOverride = actingAsAdmissionSupportStaff($this, $this->guidance)->put(route('grading.sessions.attempts.scores.update', [$fixture['grading'], $fixture['attempt']]), [
        'scoring_mode' => 'manual_raw', 'scores' => [['metric_code' => 'VOCABULARY', 'score' => 1]], 'idempotency_key' => (string) Str::uuid(),
    ]);
    $unauthorizedOverride->assertRedirect()->assertSessionHasErrors('scores');
    actingAsAdmissionSupportStaff($this, $this->guidance)->put(route('grading.sessions.attempts.scores.update', [$fixture['grading'], $fixture['attempt']]), [
        'scoring_mode' => 'manual_raw', 'scores' => [['metric_code' => 'VOCABULARY', 'score' => 1, 'override_omr' => true, 'override_reason' => 'Smudged bubble #14']], 'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect(AssessmentRawResultRevision::query()->where('administration_id', $fixture['administration']->id)->where('metric_code', 'VOCABULARY')->current()->firstOrFail()->provenance_json['overridden_from_source'])->toBe('omr');
});

test('clearing scores supersedes current raw and conversion revisions and resets binding', function (): void {
    $profile = hybridScoringApprovedProfile($this, $this->guidance, 'HYBRID-CLEAR');
    $fixture = hybridScoringEligibleAdministration($this, $this->guidance, $profile, 'hybrid-clear@example.test', 'clear');
    app(AcceptAssessmentRawResults::class)->handle($fixture['administration'], ['VOCABULARY' => 1, 'COMPUTATION' => 2, 'DURATION' => 100], AssessmentScoringRun::SOURCE_MANUAL, $this->guidance, (string) Str::uuid());
    actingAsAdmissionSupportStaff($this, $this->guidance)->delete(route('grading.sessions.attempts.scores.destroy', [$fixture['grading'], $fixture['attempt']]))->assertRedirect()->assertSessionHasNoErrors();
    expect(AssessmentRawResultRevision::query()->where('administration_id', $fixture['administration']->id)->where('is_current', true)->exists())->toBeFalse()
        ->and(AssessmentConversionRevision::query()->where('administration_id', $fixture['administration']->id)->where('is_current', true)->exists())->toBeFalse()
        ->and($fixture['administration']->fresh()->binding_state)->toBe(AssessmentAdministration::STATE_RAW_INCOMPLETE);
});

test('grading hub search and roster filters preserve aggregate progress statistics', function (): void {
    $profile = hybridScoringApprovedProfile($this, $this->guidance, 'HYBRID-SEARCH');
    $fixture = hybridScoringEligibleAdministration($this, $this->guidance, $profile, 'hybrid-search@example.test', 'search');
    actingAsAdmissionSupportStaff($this, $this->guidance)->get(route('grading.index', ['search' => 'search']))->assertOk()->assertSee($fixture['grading']->id);
    $response = actingAsAdmissionSupportStaff($this, $this->guidance)->get(route('grading.sessions.show', $fixture['grading']).'?progress=complete&source_mode=direct_converted')->assertOk();
    expect($response->viewData('page')['props']['progress_stats'] ?? $response->viewData('progress_stats'))->not->toBeEmpty();
});
