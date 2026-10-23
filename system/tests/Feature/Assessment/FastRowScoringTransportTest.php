<?php

use App\Assessment\Actions\AssembleTestKitPreparation;
use App\Assessment\Actions\ClearScores;
use App\Assessment\Actions\RecordScores;
use App\Assessment\Support\IndividualResultMutationFence;
use App\Assessment\Support\ScoreBaseline;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\CreateDirectAssessmentCohort;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use Database\Seeders\FoundationalScoringSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->withoutVite();
    seedE2Foundation();

    $capabilities = [
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
    ];

    foreach ($capabilities as $capability) {
        Permission::findOrCreate($capability, 'web');
    }

    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->guidance->givePermissionTo($capabilities);
});

test('fast row scoring transport provides JSON individual projection with score_baseline and handles atomic row writes', function (): void {
    $fixture = externalFinalAdministrationFixture($this, $this->guidance);
    $attempt = $fixture['attempt'];
    $session = $fixture['gradingSession'];
    $entries = externalFinalScoreEntries($fixture['profile']);

    // 1. GET with Accept: application/json returns existing individual projection plus score_baseline
    $getRes = actingAsAdmissionSupportStaff($this, $this->guidance)
        ->getJson(route('scoring.sessions.attempts.scores', [
            'examSession' => $session->exam_session_id,
            'attempt' => $attempt->id,
        ]))
        ->assertOk();

    $data = $getRes->json();
    expect($data)->toHaveKeys([
        'domains',
        'metrics',
        'can_write',
        'individual_frozen',
        'is_final_only',
        'scoring_mode',
        'selected_test_kit_version_id',
        'norm_versions',
        'selected_norm_package_version_id',
        'urls',
        'attempt',
        'score_baseline',
    ])
        ->and($data['can_write'])->toBeTrue()
        ->and($data['individual_frozen'])->toBeFalse()
        ->and($data['is_final_only'])->toBeTrue()
        ->and($data['scoring_mode'])->toBe('manual_converted')
        ->and($data['score_baseline'])->toMatch('/^[a-f0-9]{64}$/');

    $initialBaseline = $data['score_baseline'];

    // 2. PUT without expected_baseline on JSON request returns 422
    $failRes = actingAsAdmissionSupportStaff($this, $this->guidance)
        ->putJson(route('scoring.sessions.attempts.scores.update', [
            'examSession' => $session->exam_session_id,
            'attempt' => $attempt->id,
        ]), [
            'scoring_mode' => 'manual_converted',
            'test_kit_version_id' => (int) $fixture['kitVersion']->id,
            'idempotency_key' => (string) Str::uuid(),
            'scores' => $entries,
        ]);
    $failRes->assertStatus(422)->assertJsonValidationErrors(['expected_baseline']);

    // 3. PUT with matching expected_baseline succeeds and returns new baseline
    $idempotencyKey = (string) Str::uuid();
    $putRes = actingAsAdmissionSupportStaff($this, $this->guidance)
        ->putJson(route('scoring.sessions.attempts.scores.update', [
            'examSession' => $session->exam_session_id,
            'attempt' => $attempt->id,
        ]), [
            'expected_baseline' => $initialBaseline,
            'scoring_mode' => 'manual_converted',
            'test_kit_version_id' => (int) $fixture['kitVersion']->id,
            'idempotency_key' => $idempotencyKey,
            'scores' => $entries,
        ])
        ->assertOk();

    $putData = $putRes->json();
    expect($putData['message'])->toBe('Scores saved.')
        ->and($putData['score_baseline'])->toMatch('/^[a-f0-9]{64}$/')
        ->and($putData['score_baseline'])->not->toBe($initialBaseline);

    $updatedBaseline = $putData['score_baseline'];

    // 4. Exact replay with same idempotency key and same payload succeeds despite changed baseline
    $replayRes = actingAsAdmissionSupportStaff($this, $this->guidance)
        ->putJson(route('scoring.sessions.attempts.scores.update', [
            'examSession' => $session->exam_session_id,
            'attempt' => $attempt->id,
        ]), [
            'expected_baseline' => $initialBaseline, // old baseline sent during retry
            'scoring_mode' => 'manual_converted',
            'test_kit_version_id' => (int) $fixture['kitVersion']->id,
            'idempotency_key' => $idempotencyKey,
            'scores' => $entries,
        ])
        ->assertOk();

    expect($replayRes->json('score_baseline'))->toBe($updatedBaseline);

    // 5. Replay with same idempotency key but conflicting payload is denied (exception thrown)
    $conflictEntries = $entries;
    $conflictEntries[0]['typed_outputs']['percentile_rank'] = '99.00';
    actingAsAdmissionSupportStaff($this, $this->guidance)
        ->putJson(route('scoring.sessions.attempts.scores.update', [
            'examSession' => $session->exam_session_id,
            'attempt' => $attempt->id,
        ]), [
            'expected_baseline' => $initialBaseline,
            'scoring_mode' => 'manual_converted',
            'test_kit_version_id' => (int) $fixture['kitVersion']->id,
            'idempotency_key' => $idempotencyKey,
            'scores' => $conflictEntries,
        ])
        ->assertStatus(500); // RuntimeException idempotency conflict bubbles to JSON handler

    // 6. Stale baseline write with new idempotency key returns 409 Conflict
    actingAsAdmissionSupportStaff($this, $this->guidance)
        ->putJson(route('scoring.sessions.attempts.scores.update', [
            'examSession' => $session->exam_session_id,
            'attempt' => $attempt->id,
        ]), [
            'expected_baseline' => $initialBaseline, // stale baseline
            'scoring_mode' => 'manual_converted',
            'test_kit_version_id' => (int) $fixture['kitVersion']->id,
            'idempotency_key' => (string) Str::uuid(),
            'scores' => $entries,
        ])
        ->assertStatus(409);
});

test('stale baseline detects concurrent clearing of scores and rejects outdated write', function (): void {
    $fixture = externalFinalAdministrationFixture($this, $this->guidance);
    $attempt = $fixture['attempt'];
    $session = $fixture['gradingSession'];
    $entries = externalFinalScoreEntries($fixture['profile']);

    // Record initial scores
    app(RecordScores::class)->handle(
        gradingSession: $session,
        attempt: $attempt,
        scores: $entries,
        actor: $this->guidance,
        idempotencyKey: (string) Str::uuid(),
        scoringMode: 'manual_converted',
        testKitVersionId: (int) $fixture['kitVersion']->id,
    );

    // Fetch baseline while scored
    $baselineWhileScored = app(ScoreBaseline::class)->for($session, $attempt);

    // Clear scores concurrently
    app(ClearScores::class)->handle($session, $attempt, $this->guidance);

    // Baseline changed due to clear
    $baselineAfterClear = app(ScoreBaseline::class)->for($session, $attempt);
    expect($baselineAfterClear)->not->toBe($baselineWhileScored);

    // Attempting to write with stale baselineWhileScored is rejected with 409
    actingAsAdmissionSupportStaff($this, $this->guidance)
        ->putJson(route('scoring.sessions.attempts.scores.update', [
            'examSession' => $session->exam_session_id,
            'attempt' => $attempt->id,
        ]), [
            'expected_baseline' => $baselineWhileScored,
            'scoring_mode' => 'manual_converted',
            'test_kit_version_id' => (int) $fixture['kitVersion']->id,
            'idempotency_key' => (string) Str::uuid(),
            'scores' => $entries,
        ])
        ->assertStatus(409);
});

test('independent applicant rows have isolated baselines and writes to one row do not invalidate another', function (): void {
    $this->seed(FoundationalScoringSeeder::class);
    $kit = TestKit::query()->where('code', 'TK-ICAT-2026')->firstOrFail();
    $preparation = $kit->preparations()->where('state', 'preparing')->firstOrFail();
    $normId = (int) $preparation->norm_package_version_id;

    $version = app(AssembleTestKitPreparation::class)->handle($this->guidance, (int) $kit->id, [
        'preparation_id' => (int) $preparation->id,
        'expected_revision' => (int) $preparation->revision,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $version->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'activated_at' => now(),
        'activated_by_user_id' => $this->guidance->id,
        'revision' => (int) $version->revision + 1,
    ])->save();

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $created = app(CreateDirectAssessmentCohort::class)->handle(
        cycle: $cycle,
        attemptIds: AdmissionAttempt::query()->where('academic_cycle_id', $cycle->id)
            ->where('reference_code', 'like', 'APP-ICAT-2026-%')->take(2)->pluck('id')->all(),
        actor: $this->guidance,
        label: 'Independent baseline test cohort',
        kitVersion: $version,
    );
    $session = $created['session']->load('assignments.admissionAttempt');
    $gradingSession = $session->gradingSession;

    $attemptA = $session->assignments[0]->admissionAttempt;
    $attemptB = $session->assignments[1]->admissionAttempt;

    // Both get their initial baselines
    $baselineA = app(ScoreBaseline::class)->for($gradingSession, $attemptA);
    $baselineB = app(ScoreBaseline::class)->for($gradingSession, $attemptB);

    expect($baselineA)->toMatch('/^[a-f0-9]{64}$/')
        ->and($baselineB)->toMatch('/^[a-f0-9]{64}$/');

    // Write scores for Attempt A
    $putA = actingAsAdmissionSupportStaff($this, $this->guidance)
        ->putJson(route('scoring.sessions.attempts.scores.update', [
            'examSession' => $session->id,
            'attempt' => $attemptA->id,
        ]), [
            'expected_baseline' => $baselineA,
            'scoring_mode' => 'manual_raw',
            'test_kit_version_id' => (int) $version->id,
            'norm_package_version_id' => $normId,
            'idempotency_key' => (string) Str::uuid(),
            'scores' => [
                ['metric_code' => 'VOCABULARY_RAW', 'score' => 30],
                ['metric_code' => 'COMPUTATION_RAW', 'score' => 25],
                ['metric_code' => 'SPATIAL_RAW', 'score' => 15],
                ['metric_code' => 'WORD_COMP_RAW', 'score' => 85],
            ],
        ])
        ->assertOk();

    // Baseline for A has changed
    $newBaselineA = $putA->json('score_baseline');
    expect($newBaselineA)->not->toBe($baselineA);

    // Baseline for B remains unchanged and valid!
    $currentBaselineB = app(ScoreBaseline::class)->for($gradingSession, $attemptB);
    expect($currentBaselineB)->toBe($baselineB);

    // Writing Attempt B with its original baseline succeeds without conflict
    actingAsAdmissionSupportStaff($this, $this->guidance)
        ->putJson(route('scoring.sessions.attempts.scores.update', [
            'examSession' => $session->id,
            'attempt' => $attemptB->id,
        ]), [
            'expected_baseline' => $baselineB,
            'scoring_mode' => 'manual_raw',
            'test_kit_version_id' => (int) $version->id,
            'norm_package_version_id' => $normId,
            'idempotency_key' => (string) Str::uuid(),
            'scores' => [
                ['metric_code' => 'VOCABULARY_RAW', 'score' => 28],
                ['metric_code' => 'COMPUTATION_RAW', 'score' => 20],
                ['metric_code' => 'SPATIAL_RAW', 'score' => 12],
                ['metric_code' => 'WORD_COMP_RAW', 'score' => 78],
            ],
        ])
        ->assertOk();
});

test('frozen attempt is read-only and write returns 409 mutation fence error', function (): void {
    $fixture = externalFinalAdministrationFixture($this, $this->guidance);
    $attempt = $fixture['attempt'];
    $session = $fixture['gradingSession'];
    $admin = $fixture['administration'];
    $entries = externalFinalScoreEntries($fixture['profile']);

    // Freeze the administration
    $admin->update(['binding_state' => AssessmentAdministration::STATE_FINALIZED]);

    // GET reflects frozen state
    $getRes = actingAsAdmissionSupportStaff($this, $this->guidance)
        ->getJson(route('scoring.sessions.attempts.scores', [
            'examSession' => $session->exam_session_id,
            'attempt' => $attempt->id,
        ]))
        ->assertOk();

    expect($getRes->json('can_write'))->toBeFalse()
        ->and($getRes->json('individual_frozen'))->toBeTrue();

    $baseline = $getRes->json('score_baseline');

    // PUT write is blocked
    actingAsAdmissionSupportStaff($this, $this->guidance)
        ->putJson(route('scoring.sessions.attempts.scores.update', [
            'examSession' => $session->exam_session_id,
            'attempt' => $attempt->id,
        ]), [
            'expected_baseline' => $baseline,
            'scoring_mode' => 'manual_converted',
            'test_kit_version_id' => (int) $fixture['kitVersion']->id,
            'idempotency_key' => (string) Str::uuid(),
            'scores' => $entries,
        ])
        ->assertStatus(409); // IndividualResultMutationFence throws 409
});

test('session roster progress stats remain unfiltered when page filter is applied', function (): void {
    $this->seed(FoundationalScoringSeeder::class);
    $kit = TestKit::query()->where('code', 'TK-ICAT-2026')->firstOrFail();
    $preparation = $kit->preparations()->where('state', 'preparing')->firstOrFail();
    $normId = (int) $preparation->norm_package_version_id;

    $version = app(AssembleTestKitPreparation::class)->handle($this->guidance, (int) $kit->id, [
        'preparation_id' => (int) $preparation->id,
        'expected_revision' => (int) $preparation->revision,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $version->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'activated_at' => now(),
        'activated_by_user_id' => $this->guidance->id,
        'revision' => (int) $version->revision + 1,
    ])->save();

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $attempts = AdmissionAttempt::query()->where('academic_cycle_id', $cycle->id)
        ->where('reference_code', 'like', 'APP-ICAT-2026-%')->take(3)->get();

    $created = app(CreateDirectAssessmentCohort::class)->handle(
        cycle: $cycle,
        attemptIds: $attempts->pluck('id')->all(),
        actor: $this->guidance,
        label: 'Progress stats test cohort',
        kitVersion: $version,
    );
    $session = $created['session'];
    $gradingSession = $session->gradingSession;

    // Score only the first attempt
    $attemptA = $attempts[0];
    app(RecordScores::class)->handle(
        gradingSession: $gradingSession,
        attempt: $attemptA,
        scores: [
            ['metric_code' => 'VOCABULARY_RAW', 'score' => 30],
            ['metric_code' => 'COMPUTATION_RAW', 'score' => 25],
            ['metric_code' => 'SPATIAL_RAW', 'score' => 15],
            ['metric_code' => 'WORD_COMP_RAW', 'score' => 85],
        ],
        actor: $this->guidance,
        idempotencyKey: (string) Str::uuid(),
        scoringMode: 'manual_raw',
        testKitVersionId: (int) $version->id,
        normPackageVersionId: $normId,
    );

    // Filter roster by search term matching only Attempt B (which is pending)
    $attemptB = $attempts[1];
    $response = actingAsAdmissionSupportStaff($this, $this->guidance)
        ->get(route('scoring.sessions.show', [
            'examSession' => $session->id,
            'search' => $attemptB->reference_code,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Assessment/GradingSession')
            ->has('applicants', 1)
            ->where('applicants.0.attempt_id', $attemptB->id)
            // progress_stats must retain total cohort statistics (total: 3, scored: 1, pending: 2)
            ->where('progress_stats.total', 3)
            ->where('progress_stats.scored', 1)
            ->where('progress_stats.pending', 2));

    actingAsAdmissionSupportStaff($this, $this->guidance)
        ->get(route('grading.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Assessment/Grading')
            ->where('sessions.0.applicants_total', 3)
            ->where('sessions.0.applicants_scored', 1)
            ->where('sessions.0.progress_percent', 33));
});
