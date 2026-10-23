<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Assessment\Actions\AcceptAssessmentRawResults;
use App\Assessment\Actions\AssembleTestKitPreparation;
use App\Assessment\Actions\FinalizeGradingSession;
use App\Assessment\Actions\RecordScores;
use App\Assessment\Support\TestKitEligibility;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\CreateDirectAssessmentCohort;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentConversionRevision;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\AssessmentScoringRun;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use Database\Seeders\FoundationalScoringSeeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

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

function directManualSafeguardApprovedProfile(TestCase $test, User $actor, string $code, string $materialRequirement): TestInstrumentVersion
{
    $payload = [
        'request_id' => (string) Str::uuid(),
        'profile' => [
            'code' => $code,
            'display_name' => "Approved profile {$code}",
            'publisher_name' => 'Test Publisher',
            'form_code' => 'FORM-A',
            'edition' => '1st',
            'language' => 'English',
            'source_reference' => 'test-source-ref',
            'permitted_use_reference' => 'test-perm-ref',
            'material_requirement' => $materialRequirement,
            'administration_procedure_reference' => 'Direct scoring test procedure',
        ],
        'subtests' => [[
            'code' => 'SUB_A',
            'name' => 'Subtest A',
            'display_order' => 1,
            'response_mode' => 'keyed_items',
            'item_order_policy' => 'fixed',
            'items' => [[
                'item_code' => 'SUB_A_1',
                'local_number' => '1',
                'display_order' => 1,
                'is_scored' => true,
                'option_tokens' => ['A', 'B'],
            ]],
        ]],
        'metrics' => [[
            'code' => 'RAW_A',
            'name' => 'Raw A metric',
            'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
            'subtest_code' => 'SUB_A',
            'unit' => 'count',
            'min_raw_value' => null,
            'max_raw_value' => null,
            'precision_scale' => 0,
            'is_required' => true,
            'required_output_scales' => ['percentile_rank', 'stanine'],
            'procedure_reference' => null,
            'construct_aptitude_area_id' => null,
            'input_codes' => [],
            'input_contract_json' => [
                'allowed_sources' => ['manual', 'import'],
                'required_resources' => [],
                'source_requirements' => [
                    'manual' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
                    'import' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
                ],
            ],
        ]],
    ];

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
 * @return array{attempt: AdmissionAttempt, gradingSession: GradingSession, administration: AssessmentAdministration, kitVersion: TestKitVersion}
 */
function directManualSafeguardAdministration(
    TestCase $test,
    User $actor,
    TestInstrumentVersion $profileVersion,
    string $email,
    ?TestKitVersion $kitVersion = null,
): array {
    $kitVersion ??= testKitSetupOnlyFixture($actor, $profileVersion)['version'];
    if ($kitVersion->status === TestKitVersion::STATUS_CANDIDATE) {
        // This is a retained-use test boundary, not evidence of a successful activation.
        $kitVersion->forceFill([
            'status' => TestKitVersion::STATUS_ACTIVE,
            'activated_at' => now(),
            'activated_by_user_id' => $actor->id,
            'revision' => (int) $kitVersion->revision + 1,
        ])->save();
    }
    $submittedAttempt = submitSyntheticApplicant($email, 'Direct manual safeguard')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $attempt = app(AcceptAdmissionAttempt::class)->handle($submittedAttempt, $registrar, 1);
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $label = 'Direct safeguard '.Str::random(10);

    actingAsAdmissionSupportStaff($test, $actor)
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
    $assignment = ExamAssignment::query()->where('exam_session_id', $session->id)->firstOrFail();
    $gradingSession = GradingSession::query()->where('exam_session_id', $session->id)->firstOrFail();
    $administration = AssessmentAdministration::query()->where('exam_assignment_id', $assignment->id)->active()->firstOrFail();

    return compact('attempt', 'gradingSession', 'administration', 'kitVersion');
}

test('Setup-only direct manual scoring uses the exact Kit and accepts raw values without paper evidence', function (): void {
    $profileVersion = directManualSafeguardApprovedProfile(
        $this,
        $this->guidance,
        'PROF-DIRECT-NO-PAPER-'.Str::upper(Str::random(6)),
        TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER,
    );
    $fixture = directManualSafeguardAdministration(
        $this,
        $this->guidance,
        $profileVersion,
        'direct-no-paper-'.Str::random(8).'@example.test',
    );
    $administration = $fixture['administration'];

    expect($administration->binding_kind)->toBe(AssessmentAdministration::KIND_DIRECT_MANUAL)
        ->and((int) $administration->test_instrument_version_id)->toBe((int) $profileVersion->id)
        ->and((int) $administration->test_kit_version_id)->toBe((int) $fixture['kitVersion']->id)
        ->and($administration->manifest_sha256)->toBe($fixture['kitVersion']->manifest_sha256)
        ->and($administration->acceptance_receipt_id)->toBeNull()
        ->and($administration->material_snapshot_id)->toBeNull()
        ->and($administration->exam_session_seat_id)->toBeNull()
        ->and($administration->booklet_set_id)->toBeNull();

    app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['RAW_A' => 1],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: (string) Str::uuid(),
    );
    expect(AssessmentRawResultRevision::query()
        ->where('administration_id', $administration->id)
        ->where('is_current', true)
        ->exists())->toBeTrue();
});

test('paper-manual eligibility keeps exact paper identity without requiring scanner receipt or physical qualification', function (): void {
    $fixture = testKitAcceptanceFixture($this->guidance, Institution::current());
    $kitVersion = $fixture['version'];
    $kitVersion->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'activated_at' => now(),
        'activated_by_user_id' => $this->guidance->id,
        'revision' => (int) $kitVersion->revision + 1,
    ])->save();
    $cycle = AcademicCycle::query()
        ->where('institution_id', $fixture['kit']->institution_id)
        ->firstOrFail();

    $decision = app(TestKitEligibility::class)->evaluate(
        $kitVersion->fresh(),
        $cycle,
        TestKitEligibility::PURPOSE_NEW_USE,
        operation: 'manual',
    );
    $blockerCodes = array_column($decision['blockers'], 'code');

    expect($kitVersion->test_instrument_version_id)->toBe($fixture['profile']->id)
        ->and($kitVersion->template_version_id)->not->toBeNull()
        ->and($kitVersion->booklet_version_id)->not->toBeNull()
        ->and($kitVersion->selected_acceptance_receipt_id)->toBeNull()
        ->and($decision['eligible'])->toBeFalse()
        ->and($blockerCodes)->toContain('source_evidence_unavailable')
        ->and($blockerCodes)->not->toContain('passing_receipt_required', 'physical_qualification_required');
});

test('manual raw scores are retained when norm tables are unconfigured while finalization remains blocked', function (): void {
    $profileVersion = directManualSafeguardApprovedProfile(
        $this,
        $this->guidance,
        'PROF-DIRECT-NORMS-'.Str::upper(Str::random(6)),
        TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER,
    );
    $fixture = directManualSafeguardAdministration($this, $this->guidance, $profileVersion, 'direct-norms-'.Str::random(8).'@example.test');
    $administration = $fixture['administration'];

    app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['RAW_A' => 1],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: (string) Str::uuid(),
    );

    expect(AssessmentRawResultRevision::query()
        ->where('administration_id', $administration->id)
        ->where('is_current', true)
        ->exists())->toBeTrue();

    expect(fn () => app(FinalizeGradingSession::class)->handle($fixture['gradingSession'], $this->guidance))
        ->toThrow(RuntimeException::class, 'norm conversion');

    expect(AssessmentRawResultRevision::query()
        ->where('administration_id', $administration->id)
        ->where('is_current', true)
        ->exists())->toBeTrue();
});

test('external typed finals use declared scales and replaying an older idempotency key does not rewind current final', function (): void {
    $profileVersion = directManualSafeguardApprovedProfile(
        $this,
        $this->guidance,
        'PROF-DIRECT-EXTERNAL-FINAL-'.Str::upper(Str::random(6)),
        TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER,
    );
    $fixture = directManualSafeguardAdministration(
        $this,
        $this->guidance,
        $profileVersion,
        'direct-external-final-'.Str::random(8).'@example.test',
    );
    $recordExternalFinal = function (string $percentile, string $stanine, string $idempotencyKey) use ($fixture): array {
        return app(RecordScores::class)->handle(
            gradingSession: $fixture['gradingSession'],
            attempt: $fixture['attempt'],
            scores: [[
                'metric_code' => 'RAW_A',
                'typed_outputs' => [
                    'percentile_rank' => $percentile,
                    'stanine' => $stanine,
                ],
                'external_provenance' => 'Test-only external scoring source; no institutional result claim.',
            ]],
            actor: $this->guidance,
            idempotencyKey: $idempotencyKey,
            scoringMode: 'manual_converted',
            testKitVersionId: (int) $fixture['kitVersion']->id,
        );
    };

    $recordExternalFinal('77', '6', 'external-final-A');
    $recordExternalFinal('82', '7', 'external-final-B');

    expect(fn () => app(RecordScores::class)->handle(
        gradingSession: $fixture['gradingSession'],
        attempt: $fixture['attempt'],
        scores: [[
            'metric_code' => 'RAW_A',
            'typed_outputs' => ['converted_score' => '80'],
            'external_provenance' => 'Ambiguous numeric scale must be rejected.',
        ]],
        actor: $this->guidance,
        idempotencyKey: 'external-final-ambiguous-scale',
        scoringMode: 'manual_converted',
        testKitVersionId: (int) $fixture['kitVersion']->id,
    ))->toThrow(ValidationException::class);
    $currentB = AssessmentConversionRevision::query()
        ->where('administration_id', $fixture['administration']->id)
        ->where('is_current', true)
        ->firstOrFail();
    $recordExternalFinal('77', '6', 'external-final-A');
    $currentAfterReplay = AssessmentConversionRevision::query()
        ->where('administration_id', $fixture['administration']->id)
        ->where('is_current', true)
        ->firstOrFail();

    expect($currentAfterReplay->id)->toBe($currentB->id)
        ->and($currentAfterReplay->source)->toBe(AssessmentConversionRevision::SOURCE_MANUAL_CONVERTED)
        ->and($currentAfterReplay->norm_package_version_id)->toBeNull()
        ->and($currentAfterReplay->typed_outputs_json['RAW_A']['percentile_rank'])->toBe('82')
        ->and($currentAfterReplay->typed_outputs_json['RAW_A']['stanine'])->toBe('7')
        ->and(AssessmentRawResultRevision::query()->where('administration_id', $fixture['administration']->id)->exists())->toBeFalse();
});

test('seeded scoring rules use an explicit locked Kit and selected norm without seeding acceptance evidence', function (): void {
    $this->seed(FoundationalScoringSeeder::class);
    $kit = TestKit::query()->where('code', 'TK-ICAT-2026')->firstOrFail();
    $preparation = $kit->preparations()->where('state', 'preparing')->firstOrFail();
    $normId = (int) $preparation->norm_package_version_id;
    $version = app(AssembleTestKitPreparation::class)->handle($this->guidance, (int) $kit->id, [
        'preparation_id' => (int) $preparation->id,
        'expected_revision' => (int) $preparation->revision,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    expect($version->selected_acceptance_receipt_id)->toBeNull();
    // Operational lifecycle is a test Given, never a seeder qualification claim.
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
            ->where('reference_code', 'like', 'APP-ICAT-2026-%')->pluck('id')->all(),
        actor: $this->guidance,
        label: 'Explicit seeded rule acceptance',
        kitVersion: $version,
    );
    $session = $created['session']->load('assignments.admissionAttempt');

    $gradingSession = $session->gradingSession;
    expect($gradingSession)->not->toBeNull()
        ->and($gradingSession->status)->toBe(GradingSession::STATUS_OPEN);

    $testScores = [
        ['vocab' => 34, 'comp' => 28, 'spatial' => 16, 'word_comp' => 91, 'expected_ga' => 62],
        ['vocab' => 30, 'comp' => 22, 'spatial' => 14, 'word_comp' => 80, 'expected_ga' => 52],
        ['vocab' => 38, 'comp' => 29, 'spatial' => 19, 'word_comp' => 95, 'expected_ga' => 67],
    ];

    actingAsAdmissionSupportStaff($this, $this->guidance)
        ->get(route('scoring.sessions.show', ['examSession' => $session->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assessment/GradingSession')
            ->where('session.exam_session_id', (int) $session->id)
            ->where('session.grading_status', GradingSession::STATUS_OPEN)
            ->where('session.grading_version', (int) $gradingSession->version));

    foreach ($session->assignments as $index => $assignment) {
        $attempt = $assignment->admissionAttempt;
        $scores = $testScores[$index];

        actingAsAdmissionSupportStaff($this, $this->guidance)
            ->get(route('scoring.sessions.attempts.scores', [
                'examSession' => $session->id,
                'attempt' => $attempt->id,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Assessment/GradingScoreInput')
                ->where('selected_test_kit_version_id', (int) $version->id)
                ->where('selected_norm_package_version_id', $normId)
                ->where('has_scoring_profile', true));

        $response = actingAsAdmissionSupportStaff($this, $this->guidance)->put(
            route('scoring.sessions.attempts.scores.update', [
                'examSession' => $session->id,
                'attempt' => $attempt->id,
            ]),
            [
                'scoring_mode' => 'manual_raw',
                'test_kit_version_id' => (int) $version->id,
                'norm_package_version_id' => $normId,
                'idempotency_key' => (string) Str::uuid(),
                'scores' => [
                    ['metric_code' => 'VOCABULARY_RAW', 'score' => $scores['vocab']],
                    ['metric_code' => 'COMPUTATION_RAW', 'score' => $scores['comp']],
                    ['metric_code' => 'SPATIAL_RAW', 'score' => $scores['spatial']],
                    ['metric_code' => 'WORD_COMP_RAW', 'score' => $scores['word_comp']],
                ],
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $admin = AssessmentAdministration::query()
            ->where('exam_assignment_id', $assignment->id)
            ->whereNull('superseded_at')
            ->firstOrFail();

        expect($admin->currentRawResultRevisions()->count())->toBe(6);

        $gaRevision = $admin->currentRawResultRevisions()
            ->whereHas('metric', fn ($q) => $q->where('code', 'GENERAL_ABILITY_RAW'))
            ->firstOrFail();
        expect((int) $gaRevision->raw_value)->toBe($scores['expected_ga']);

        $mdRevision = $admin->currentRawResultRevisions()
            ->whereHas('metric', fn ($q) => $q->where('code', 'MANUAL_DEXTERITY_RAW'))
            ->firstOrFail();
        expect((int) $mdRevision->raw_value)->toBe($scores['spatial'] + $scores['word_comp']);

        $conversion = $admin->currentConversionRevision;
        expect($conversion)->not->toBeNull();
    }

    $gradingSession->refresh();
    $finalizeResponse = actingAsAdmissionSupportStaff($this, $this->guidance)->post(
        route('scoring.sessions.finalize', ['examSession' => $session->id]),
        [
            'expected_version' => $gradingSession->version,
            'idempotency_key' => (string) Str::uuid(),
        ],
    );

    $finalizeResponse->assertRedirect();
    $finalizeResponse->assertSessionHasNoErrors();

    $gradingSession->refresh();
    expect($gradingSession->status)->toBe(GradingSession::STATUS_FINALIZED);
});
