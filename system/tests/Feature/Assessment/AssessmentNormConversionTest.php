<?php

use App\Assessment\Actions\AcceptAssessmentRawResults;
use App\Assessment\Actions\ApproveAssessmentNormVersion;
use App\Assessment\Actions\ConvertAssessmentResults;
use App\Assessment\Actions\FinalizeGradingSession;
use App\Assessment\Actions\RecordScores;
use App\Assessment\Actions\SaveAssessmentNormDraft;
use App\Assessment\Support\AssessmentAdministrationFinalizationPredicate;
use App\Assessment\Support\ScoreConverter;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\AssessmentConversionRevision;
use App\Models\Assessment\AssessmentNormTable;
use App\Models\Assessment\AssessmentScoringRun;
use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/../../Support/NormConversionFixtures.php';
beforeEach(function (): void {
    $this->withoutVite();
    seedE2Foundation();
    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $capabilities = [
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
    ];

    foreach ($capabilities as $capability) {
        Permission::findOrCreate($capability, 'web');
    }

    $this->guidance->givePermissionTo($capabilities);
});

test('SaveAssessmentNormDraft and ApproveAssessmentNormVersion create and approve an immutable norm package version with tables', function (): void {
    $intervals = [
        ['min' => 0, 'max' => 1, 'percentile_rank' => 30, 'stanine' => 4],
        ['min' => 2, 'max' => 3, 'percentile_rank' => 75, 'stanine' => 6],
    ];

    $draft = app(SaveAssessmentNormDraft::class)->handle(
        actor: $this->guidance,
        package: null,
        version: null,
        expectedRevision: null,
        requestId: (string) Str::uuid(),
        input: [
            'code' => 'NORM-TEST-IMMUTABLE',
            'display_name' => 'Synthetic Norm Immutable Test Package',
            'publisher_name' => 'Synthetic assessment test fixture',
            'form_code' => 'FORM-TEST-A',
            'edition' => 'Synthetic 2026 test',
            'language' => 'Test-only',
            'applicable_population' => 'Synthetic college entrance test examinees',
            'source_reference' => 'test-fixture://synthetic-norm-source',
            'permitted_use_reference' => 'test-fixture://synthetic-norm-permitted-use',
            'is_synthetic' => true,
            'tables' => [
                [
                    'metric_code' => 'RAW_A',
                    'output_scale' => 'percentile_rank',
                    'intervals' => $intervals,
                ],
                [
                    'metric_code' => 'RAW_A',
                    'output_scale' => 'stanine',
                    'intervals' => $intervals,
                ],
            ],
        ],
    );

    expect($draft->status)->toBe('draft')
        ->and($draft->tables)->toHaveCount(2);

    $initialLockVersion = (int) $draft->lock_version;

    $approved = app(ApproveAssessmentNormVersion::class)->handle(
        actor: $this->guidance,
        package: $draft->norm_package_id,
        version: $draft->id,
        expectedRevision: $draft->lock_version,
        requestId: (string) Str::uuid(),
        attestationOrPayload: true,
    );

    expect($approved->status)->toBe('approved')
        ->and($approved->lock_version)->toBe($initialLockVersion + 1)
        ->and($approved->content_sha256)->not->toBeEmpty();
});

test('decimal norm intervals preserve exact six-place boundaries through approval and lookup', function (): void {
    $draft = app(SaveAssessmentNormDraft::class)->handle(
        actor: $this->guidance,
        package: null,
        version: null,
        expectedRevision: null,
        requestId: (string) Str::uuid(),
        input: [
            'code' => 'NORM-DECIMAL-EXACT',
            'display_name' => 'Synthetic Decimal Boundary Norm',
            'publisher_name' => 'Test fixture',
            'form_code' => 'DECIMAL-1',
            'edition' => 'Test',
            'language' => 'Test',
            'applicable_population' => 'Synthetic',
            'source_reference' => 'test-fixture://norm/decimal',
            'permitted_use_reference' => 'test-fixture://norm/decimal-use',
            'is_synthetic' => true,
            'tables' => [[
                'metric_code' => 'RAW_DECIMAL',
                'output_scale' => 'percentile_rank',
                'intervals' => [
                    ['min_score' => '999999999999.000000', 'max_score' => '999999999999.000000', 'output_value' => '40'],
                    ['min_score' => '999999999999.000001', 'max_score' => '999999999999.000002', 'output_value' => '60'],
                ],
            ]],
        ],
    );
    $approved = app(ApproveAssessmentNormVersion::class)->handle(
        actor: $this->guidance,
        package: $draft->norm_package_id,
        version: $draft->id,
        expectedRevision: $draft->lock_version,
        requestId: (string) Str::uuid(),
        attestationOrPayload: true,
    );
    $table = $approved->tables()->where('metric_code', 'RAW_DECIMAL')->firstOrFail();

    expect(app(ScoreConverter::class)
        ->convertViaNormTable($table, '999999999999.000001'))->toBe('60');
});

test('norm approval rejects holes on its declared decimal grid', function (): void {
    $draft = app(SaveAssessmentNormDraft::class)->handle(
        actor: $this->guidance,
        package: null,
        version: null,
        expectedRevision: null,
        requestId: (string) Str::uuid(),
        input: [
            'code' => 'NORM-DECIMAL-GRID-GAP',
            'display_name' => 'Synthetic Decimal Grid Gap',
            'publisher_name' => 'Test fixture',
            'form_code' => 'DECIMAL-GAP',
            'edition' => 'Test',
            'language' => 'Test',
            'applicable_population' => 'Synthetic',
            'source_reference' => 'test-fixture://norm/grid-gap',
            'permitted_use_reference' => 'test-fixture://norm/grid-gap-use',
            'is_synthetic' => true,
            'tables' => [[
                'metric_code' => 'RAW_DECIMAL',
                'output_scale' => 'percentile_rank',
                'intervals' => [
                    ['min_score' => '0.00', 'max_score' => '0.49', 'output_value' => '40'],
                    ['min_score' => '0.51', 'max_score' => '1.00', 'output_value' => '60'],
                ],
            ]],
        ],
    );

    expect(fn () => app(ApproveAssessmentNormVersion::class)->handle(
        actor: $this->guidance,
        package: $draft->norm_package_id,
        version: $draft->id,
        expectedRevision: $draft->lock_version,
        requestId: (string) Str::uuid(),
        attestationOrPayload: true,
    ))->toThrow(ValidationException::class);
});

test('exact raw-value norm rows do not fall through to an unbounded interval', function (): void {
    $table = new AssessmentNormTable;
    $table->intervals_json = [
        ['raw_value' => '1', 'output_value' => '10'],
        ['raw_value' => '2', 'output_value' => '20'],
    ];
    $converter = app(ScoreConverter::class);

    expect($converter->convertViaNormTable($table, '1'))->toBe('10')
        ->and($converter->convertViaNormTable($table, '2'))->toBe('20')
        ->and($converter->convertViaNormTable($table, '3'))->toBeNull();
});

test('ConvertAssessmentResults freezes typed outputs and transitions administration state to ready_to_finalize', function (): void {
    $profileVersion = createTestApprovedProfileVersion($this, $this->guidance, 'PROF-NORM-CONV-2');
    $fixture = createTestDirectAdministration($this, $this->guidance, $profileVersion, 'norm-conv-2@example.test');
    $administration = $fixture['administration'];

    app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['RAW_A' => 2],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: (string) Str::uuid(),
    );

    expect($administration->fresh()->binding_state)->toBe(AssessmentAdministration::STATE_AWAITING_NORM_CONVERSION);

    $normVersion = createTestApprovedNormVersion($this->guidance, 'NORM-PKG-2');

    $conversionRevision = app(ConvertAssessmentResults::class)->handle(
        $administration,
        $normVersion,
        $this->guidance,
    );

    expect($conversionRevision)->toBeInstanceOf(AssessmentConversionRevision::class)
        ->and($conversionRevision->typed_outputs_json)->toHaveKey('RAW_A')
        ->and($conversionRevision->typed_outputs_json['RAW_A'])->toHaveKey('percentile_rank')
        ->and($conversionRevision->typed_outputs_json['RAW_A'])->toHaveKey('stanine')
        ->and($conversionRevision->typed_outputs_json['RAW_A']['percentile_rank'])->toBe('75')
        ->and($conversionRevision->typed_outputs_json['RAW_A']['stanine'])->toBe('6')
        ->and($conversionRevision->norm_package_version_id)->toBe($normVersion->id)
        ->and($conversionRevision->raw_dependency_sha256)->toMatch('/^[a-f0-9]{64}$/')
        ->and($conversionRevision->rawDependencies)->toHaveCount(1)
        ->and((int) $conversionRevision->rawDependencies->first()->pivot->administration_id)->toBe((int) $administration->id);

    expect(AssessmentConversionRevision::query()
        ->where('administration_id', $administration->id)
        ->where('is_current', true)
        ->where('status', 'valid')
        ->exists()
    )->toBeTrue();

    expect($administration->fresh()->binding_state)->toBe('ready_to_finalize');
});

test('RecordScores converts only the explicitly selected norm and otherwise leaves raw results awaiting conversion', function (): void {
    $profile = createTestApprovedProfileVersion($this, $this->guidance, 'PROF-RECORD-EXPLICIT-NORM');
    $norm = createTestApprovedNormVersion($this->guidance, 'NORM-RECORD-EXPLICIT-NORM');
    $selectedFixture = createTestDirectAdministration(
        $this,
        $this->guidance,
        $profile,
        'record-explicit-norm@example.test',
    );
    app(RecordScores::class)->handle(
        gradingSession: $selectedFixture['gradingSession'],
        attempt: $selectedFixture['attempt'],
        scores: [['metric_code' => 'RAW_A', 'score' => 2]],
        actor: $this->guidance,
        idempotencyKey: 'record-explicit-norm-'.Str::uuid(),
        source: ScoreRecord::SOURCE_MANUAL,
        testKitVersionId: (int) $selectedFixture['administration']->test_kit_version_id,
        normPackageVersionId: (int) $norm->id,
    );
    $selectedConversion = AssessmentConversionRevision::query()
        ->where('administration_id', $selectedFixture['administration']->id)
        ->where('is_current', true)
        ->firstOrFail();

    $unselectedFixture = createTestDirectAdministration(
        $this,
        $this->guidance,
        $profile,
        'record-no-norm@example.test',
    );
    app(RecordScores::class)->handle(
        gradingSession: $unselectedFixture['gradingSession'],
        attempt: $unselectedFixture['attempt'],
        scores: [['metric_code' => 'RAW_A', 'score' => 2]],
        actor: $this->guidance,
        idempotencyKey: 'record-no-norm-'.Str::uuid(),
        source: ScoreRecord::SOURCE_MANUAL,
        testKitVersionId: (int) $unselectedFixture['administration']->test_kit_version_id,
    );

    expect($selectedConversion->norm_package_version_id)->toBe($norm->id)
        ->and($selectedConversion->typed_outputs_json['RAW_A']['percentile_rank'])->toBe('75')
        ->and(AssessmentConversionRevision::query()
            ->where('administration_id', $unselectedFixture['administration']->id)
            ->where('is_current', true)
            ->exists())->toBeFalse()
        ->and($unselectedFixture['administration']->fresh()->binding_state)->toBe(AssessmentAdministration::STATE_AWAITING_NORM_CONVERSION);
});
test('a norm-only successor pins the new approved norm and preserves the original raw lineage', function (): void {
    $profileVersion = createTestApprovedProfileVersion($this, $this->guidance, 'PROF-NORM-SUCCESSOR');
    $fixture = createTestDirectAdministration($this, $this->guidance, $profileVersion, 'norm-successor@example.test');
    $administration = $fixture['administration'];

    app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['RAW_A' => 2],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: (string) Str::uuid(),
    );

    $rawRevisionId = (int) $administration->rawRevisions()
        ->where('metric_code', 'RAW_A')
        ->where('is_current', true)
        ->value('id');
    $firstNorm = createTestApprovedNormVersion($this->guidance, 'NORM-PKG-SUCCESSOR');
    $firstConversion = app(ConvertAssessmentResults::class)->handle($administration, $firstNorm, $this->guidance);
    $successorNorm = createTestApprovedNormVersion($this->guidance, 'NORM-PKG-SUCCESSOR', $firstNorm, alternateNorm: true);
    $successorConversion = app(ConvertAssessmentResults::class)->handle($administration, $successorNorm, $this->guidance);

    expect($firstConversion->fresh()->status)->toBe(AssessmentConversionRevision::STATUS_SUPERSEDED)
        ->and($firstConversion->fresh()->rawDependencies->first()->id)->toBe($rawRevisionId)
        ->and($firstConversion->typed_outputs_json['RAW_A']['percentile_rank'])->toBe('75')
        ->and($successorConversion->norm_package_version_id)->toBe($successorNorm->id)
        ->and($successorConversion->typed_outputs_json['RAW_A']['percentile_rank'])->toBe('60')
        ->and($successorConversion->rawDependencies->first()->id)->toBe($rawRevisionId)
        ->and($successorConversion->raw_dependency_sha256)->toBe($firstConversion->raw_dependency_sha256);
    app(FinalizeGradingSession::class)->handle($fixture['gradingSession'], $this->guidance);
    $thirdNorm = createTestApprovedNormVersion($this->guidance, 'NORM-PKG-SUCCESSOR', $successorNorm);

    expect(fn () => app(ConvertAssessmentResults::class)->handle($administration, $thirdNorm, $this->guidance))
        ->toThrow(RuntimeException::class, 'assessment_post_finalization_correction_blocked')
        ->and($administration->fresh()->binding_state)->toBe(AssessmentAdministration::STATE_FINALIZED)
        ->and(AssessmentConversionRevision::query()
            ->where('administration_id', $administration->id)
            ->where('is_current', true)
            ->value('id')
        )->toBe($successorConversion->id);
});

test('conversion dependencies reject a foreign raw owner and remain immutable', function (): void {
    $profileVersion = createTestApprovedProfileVersion($this, $this->guidance, 'PROF-NORM-DEPENDENCY-GUARD');
    $firstFixture = createTestDirectAdministration($this, $this->guidance, $profileVersion, 'norm-dependency-one@example.test');
    $secondFixture = createTestDirectAdministration($this, $this->guidance, $profileVersion, 'norm-dependency-two@example.test');
    $firstAdministration = $firstFixture['administration'];
    $secondAdministration = $secondFixture['administration'];

    foreach ([$firstAdministration, $secondAdministration] as $administration) {
        app(AcceptAssessmentRawResults::class)->handle(
            administration: $administration,
            metricValues: ['RAW_A' => 2],
            source: AssessmentScoringRun::SOURCE_MANUAL,
            actor: $this->guidance,
            idempotencyKey: (string) Str::uuid(),
        );
    }

    $normVersion = createTestApprovedNormVersion($this->guidance, 'NORM-PKG-DEPENDENCY-GUARD');
    $conversion = app(ConvertAssessmentResults::class)->handle($firstAdministration, $normVersion, $this->guidance);
    $firstRaw = $firstAdministration->rawRevisions()->where('is_current', true)->firstOrFail();
    $foreignRaw = $secondAdministration->rawRevisions()->where('is_current', true)->firstOrFail();

    expect(fn () => DB::transaction(function () use ($firstAdministration, $normVersion, $foreignRaw): void {
        $pending = (new AssessmentConversionRevision)->forceFill([
            'administration_id' => $firstAdministration->id,
            'norm_package_version_id' => $normVersion->id,
            'source' => AssessmentConversionRevision::SOURCE_NORM_CONVERTED,
            'revision_no' => 2,
            'typed_outputs_json' => [],
            'raw_dependency_sha256' => str_repeat('a', 64),
            'status' => AssessmentConversionRevision::STATUS_PENDING,
            'converted_by_user_id' => $this->guidance->id,
            'converted_at' => now(),
            'is_current' => false,
            'superseded_at' => null,
        ]);
        $pending->save();
        DB::table('assessment_conversion_raw_dependencies')->insert([
            'conversion_revision_id' => $pending->id,
            'administration_id' => $firstAdministration->id,
            'metric_id' => $foreignRaw->metric_id,
            'raw_result_revision_id' => $foreignRaw->id,
        ]);
    }))->toThrow(QueryException::class);
    expect(AssessmentConversionRevision::query()->where('administration_id', $firstAdministration->id)->count())
        ->toBe(1);

    expect(fn () => DB::table('assessment_conversion_raw_dependencies')
        ->where('conversion_revision_id', $conversion->id)
        ->where('raw_result_revision_id', $firstRaw->id)
        ->update(['raw_result_revision_id' => $foreignRaw->id])
    )->toThrow(QueryException::class);
});

test('AssessmentAdministrationFinalizationPredicate returns ok true and ready_to_finalize after norm conversion', function (): void {
    $profileVersion = createTestApprovedProfileVersion($this, $this->guidance, 'PROF-NORM-CONV-3');
    $fixture = createTestDirectAdministration($this, $this->guidance, $profileVersion, 'norm-conv-3@example.test');
    $administration = $fixture['administration'];
    $gradingSession = $fixture['gradingSession'];

    app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['RAW_A' => 2],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: (string) Str::uuid(),
    );

    $normVersion = createTestApprovedNormVersion($this->guidance, 'NORM-PKG-3');

    app(ConvertAssessmentResults::class)->handle(
        $administration,
        $normVersion,
        $this->guidance,
    );

    $predicate = app(AssessmentAdministrationFinalizationPredicate::class);
    $result = $predicate->evaluate($gradingSession);

    expect($result['ok'])->toBeTrue()
        ->and($result['state'])->toBe('ready_to_finalize');
});

test('an incomplete current conversion and raw score row do not bypass required typed norm conversion', function (): void {
    $profileVersion = createTestApprovedProfileVersion($this, $this->guidance, 'PROF-NORM-CONV-INCOMPLETE');
    $fixture = createTestDirectAdministration($this, $this->guidance, $profileVersion, 'norm-conv-incomplete@example.test');
    $administration = $fixture['administration'];
    $gradingSession = $fixture['gradingSession'];
    $attempt = $fixture['attempt'];
    $area = AptitudeArea::query()->where('is_active', true)->firstOrFail();

    ScoreRecord::query()->create([
        'grading_session_id' => $gradingSession->id,
        'admission_attempt_id' => $attempt->id,
        'aptitude_area_id' => $area->id,
        'score' => 20,
        'idempotency_key' => 'norm-conv-incomplete-'.$administration->id,
        'source' => ScoreRecord::SOURCE_MANUAL,
        'recorded_by_user_id' => $this->guidance->id,
    ]);
    app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['RAW_A' => 2],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: (string) Str::uuid(),
    );

    $normVersion = createTestApprovedNormVersion($this->guidance, 'NORM-PKG-INCOMPLETE-CURRENT');
    $rawRevision = $administration->rawRevisions()
        ->where('metric_code', 'RAW_A')
        ->where('is_current', true)
        ->firstOrFail();
    $rawDependency = [
        'administration_id' => (int) $administration->id,
        'metric_id' => (int) $rawRevision->metric_id,
        'raw_result_revision_id' => (int) $rawRevision->id,
    ];
    $incompleteConversion = (new AssessmentConversionRevision)->forceFill([
        'administration_id' => $administration->id,
        'norm_package_version_id' => $normVersion->id,
        'source' => AssessmentConversionRevision::SOURCE_NORM_CONVERTED,
        'revision_no' => 1,
        'typed_outputs_json' => [],
        'raw_dependency_sha256' => hash('sha256', json_encode([$rawDependency], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        'status' => AssessmentConversionRevision::STATUS_PENDING,
        'converted_by_user_id' => $this->guidance->id,
        'converted_at' => now(),
        'is_current' => false,
        'superseded_at' => null,
    ]);
    $incompleteConversion->save();
    DB::table('assessment_conversion_raw_dependencies')->insert([
        'conversion_revision_id' => $incompleteConversion->id,
        ...$rawDependency,
    ]);
    $incompleteConversion->status = AssessmentConversionRevision::STATUS_VALID;
    $incompleteConversion->is_current = true;
    $incompleteConversion->save();
    $administration->update([
        'binding_state' => AssessmentAdministration::STATE_AWAITING_NORM_CONVERSION,
    ]);

    expect($administration->currentConversionRevision)->not->toBeNull();

    $result = app(AssessmentAdministrationFinalizationPredicate::class)->evaluate($gradingSession);

    expect($result['ok'])->toBeFalse()
        ->and($result['state'])->toBe('awaiting_norm_conversion')
        ->and($result['reason'])->toContain('norm conversion');

    expect(fn () => app(FinalizeGradingSession::class)->handle($gradingSession, $this->guidance))
        ->toThrow(RuntimeException::class, 'norm conversion');
});

test('Norm package routes enforce fresh guidance catalog capability', function (): void {
    $unauthorized = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    actingAsAdmissionSupportStaff($this, $unauthorized)
        ->get(route('setup.norm-packages.index'))
        ->assertForbidden();
    actingAsAdmissionSupportStaff($this, $this->guidance)
        ->get(route('setup.norm-packages.index'))
        ->assertOk();
});
