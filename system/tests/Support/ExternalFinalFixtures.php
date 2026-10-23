<?php

declare(strict_types=1);

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Assessment\Actions\ActivateTestKitVersion;
use App\Assessment\Actions\ApproveTestInstrumentVersion;
use App\Assessment\Support\TestInstrumentDraftWriter;
use App\Assessment\Support\TestInstrumentProfileValidator;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** @return list<int> */
function externalFinalConstructIds(): array
{
    return array_map(
        fn (string $code): int => (int) AptitudeArea::query()->where('code', $code)->where('is_active', true)->firstOrFail()->id,
        ['VERBAL', 'QUANT', 'LOGIC', 'SPATIAL', 'MEMORY', 'LANGUAGE'],
    );
}

/** @param list<int> $constructIds @return array<string,mixed> */
function externalFinalProfilePayload(array $constructIds, string $code): array
{
    return [
        'profile' => [
            'code' => $code,
            'display_name' => 'Synthetic final aptitude definition '.$code,
            'publisher_name' => null,
            'form_code' => null,
            'edition' => null,
            'language' => null,
            'source_reference' => 'test-fixture://synthetic-external-percentile-report',
            'permitted_use_reference' => 'test-fixture://synthetic-software-verification-only',
            'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER,
            'administration_procedure_reference' => 'Synthetic externally supplied percentile-rank intake; no raw or institutional validity claim.',
        ],
        'subtests' => [],
        'metrics' => array_map(static fn (int $constructId, int $index): array => [
            'code' => 'FINAL_'.($index + 1),
            'name' => 'Supplied final aptitude '.($index + 1),
            'rule_kind' => TestInstrumentMetric::RULE_EXTERNAL_FINAL,
            'subtest_code' => null,
            'unit' => null,
            'min_raw_value' => null,
            'max_raw_value' => null,
            'precision_scale' => 2,
            'is_required' => true,
            'required_output_scales' => ['percentile_rank'],
            'procedure_reference' => null,
            'construct_aptitude_area_id' => $constructId,
            'input_codes' => [],
            'input_contract_json' => [
                'allowed_sources' => ['manual', 'import'],
                'required_resources' => [],
                'source_requirements' => [
                    'manual' => ['unit' => 'percentile_rank', 'identity_requirements' => ['administration', 'metric']],
                    'import' => ['unit' => 'percentile_rank', 'identity_requirements' => ['administration', 'metric']],
                ],
            ],
        ], $constructIds, array_keys($constructIds)),
    ];
}

/**
 * @param  list<int>  $constructIds
 *                                   A non-demo representation exercises institutional policy only inside the
 *                                   guarded software test database; it is not source-rights or qualification evidence.
 */
function createExternalFinalTestProfile(User $actor, array $constructIds, string $code, bool $isDemo = true): TestInstrumentVersion
{
    $connection = (string) config('database.default');
    $database = (string) config("database.connections.{$connection}.database");
    if (! app()->environment('testing')
        || ! (($connection === 'sqlite' && $database === ':memory:')
            || ($connection === 'pgsql' && str_starts_with($database, 'nexiam_test_kit_acceptance_')))) {
        throw new RuntimeException('External-final fixtures require a guarded isolated testing database.');
    }
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
    $instrument = new TestInstrument([
        'institution_id' => $actor->staffProfile()->firstOrFail()->institution_id,
        'code' => $code,
    ]);
    $instrument->forceFill(['is_synthetic' => $isDemo])->save();
    $version = new TestInstrumentVersion([
        'test_instrument_id' => $instrument->id,
        'version_number' => 1,
        'status' => TestInstrumentVersion::STATUS_DRAFT,
        'lock_version' => 1,
        'created_by_user_id' => $actor->id,
    ]);
    app(TestInstrumentDraftWriter::class)->save(
        $version,
        app(TestInstrumentProfileValidator::class)->normalize(externalFinalProfilePayload($constructIds, $code)),
    );

    return app(ApproveTestInstrumentVersion::class)->handle(
        $actor, (int) $instrument->id, (int) $version->id, (int) $version->lock_version,
        (string) Str::uuid(), sourceReviewAttestation: true, isDemo: $isDemo,
    );
}

/** @return array{attempt:AdmissionAttempt,gradingSession:GradingSession,administration:AssessmentAdministration,kitVersion:TestKitVersion,profile:TestInstrumentVersion} */
function externalFinalAdministrationFixture(TestCase $test, User $actor): array
{
    foreach ([CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, CapabilityCatalog::GUIDANCE_SCORING_MANUAL] as $capability) {
        Permission::findOrCreate($capability, 'web');
        $actor->givePermissionTo($capability);
    }
    $profile = createExternalFinalTestProfile($actor, externalFinalConstructIds(), 'FINAL-'.Str::upper(Str::random(8)), isDemo: false);
    $kitVersion = testKitSetupOnlyFixture($actor, $profile)['version'];
    $submitted = submitSyntheticApplicant('external-final-'.Str::random(10).'@example.test', 'Synthetic final intake')['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $attempt = app(AcceptAdmissionAttempt::class)->handle($submitted, $registrar, 1);
    $kitVersion = app(ActivateTestKitVersion::class)->handle(
        $actor, (int) $kitVersion->test_kit_id, (int) $kitVersion->id,
        ['expected_revision' => (int) $kitVersion->revision, 'idempotency_key' => (string) Str::uuid(), 'academic_cycle_id' => $attempt->academic_cycle_id],
    );
    $label = 'Synthetic final intake '.Str::random(10);
    actingAsAdmissionSupportStaff($test, $actor)->post(route('exams.direct-assessment.store'), [
        'academic_cycle_id' => $attempt->academic_cycle_id,
        'applicant_ids' => [$attempt->id],
        'label' => $label,
        'date' => now()->addDay()->toDateString(),
        'start_time' => '09:00',
        'end_time' => '10:00',
        'test_kit_version_id' => $kitVersion->id,
    ])->assertRedirect()->assertSessionHasNoErrors();
    $session = ExamSession::query()->where('label', $label)->firstOrFail();
    $gradingSession = GradingSession::query()->where('exam_session_id', $session->id)->firstOrFail();
    $assignment = ExamAssignment::query()->where('exam_session_id', $session->id)->firstOrFail();
    $administration = AssessmentAdministration::query()->where('exam_assignment_id', $assignment->id)->active()->firstOrFail();

    return compact('attempt', 'gradingSession', 'administration', 'kitVersion', 'profile');
}

/** @return list<array{metric_code:string,typed_outputs:array{percentile_rank:string},external_provenance:string}> */
function externalFinalScoreEntries(TestInstrumentVersion $profile): array
{
    return $profile->metrics()->get()->values()->map(
        fn (TestInstrumentMetric $metric, int $index): array => [
            'metric_code' => $metric->code,
            'typed_outputs' => ['percentile_rank' => $index === 0 ? '0.00' : '81.25'],
            'external_provenance' => 'Synthetic provider report '.($index + 1),
        ],
    )->all();
}
