<?php

declare(strict_types=1);

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Assessment\Actions\ApproveAssessmentNormVersion;
use App\Assessment\Actions\ApproveTestInstrumentVersion;
use App\Assessment\Actions\SaveAssessmentNormDraft;
use App\Assessment\Support\TestInstrumentDraftWriter;
use App\Assessment\Support\TestInstrumentProfileValidator;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKitVersion;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

function createTestActiveKitVersionFor(
    TestCase $test,
    User $actor,
    Institution $institution,
    AcademicCycle $cycle,
    string $code,
): TestKitVersion {
    $profile = $actor->staffProfile()->first();
    if ($profile === null) {
        $profile = StaffProfile::query()->create([
            'user_id' => $actor->id,
            'institution_id' => $institution->id,
            'office' => 'guidance',
            'program_codes' => [],
            'active_cycle_code' => $cycle->code,
            'status' => StaffProfile::STATUS_ACTIVE,
            'deactivated_at' => null,
        ]);
    }
    if ((int) $profile->institution_id !== (int) $institution->id) {
        throw new RuntimeException('The test Kit actor must belong to the fixture institution.');
    }
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, 'web');
    if (! $actor->can(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)) {
        $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
    }
    StaffAuthenticationState::query()->firstOrCreate(['user_id' => $actor->id], [
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'must_change_password' => false,
        'session_version' => 1,
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
        'two_factor_generation' => (string) Str::uuid(),
    ]);
    $profileVersion = createTestApprovedProfileVersion($test, $actor, $code);

    return createTestActiveKitVersion($actor, $profileVersion);
}

/**
 * Establishes the isolated software-test Given Active boundary from a real locked Setup-only Kit.
 * This does not claim activation, source qualification, or institutional readiness.
 */
function createTestActiveKitVersion(User $actor, TestInstrumentVersion $profileVersion): TestKitVersion
{
    $fixture = testKitSetupOnlyFixture($actor, $profileVersion);
    $version = $fixture['version'];
    $version->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'activated_at' => now(),
        'activated_by_user_id' => $actor->id,
        'revision' => (int) $version->revision + 1,
    ])->save();

    return $version->fresh(['testInstrumentVersion']);
}

function createTestApprovedProfileVersion(TestCase $test, User $actor, string $code): TestInstrumentVersion
{
    $payload = [
        'profile' => [
            'code' => $code,
            'display_name' => "Synthetic test profile {$code}",
            'publisher_name' => 'Synthetic software fixture',
            'form_code' => 'FORM-A',
            'edition' => 'Synthetic test fixture',
            'language' => 'Test-only',
            'source_reference' => 'test-fixture://synthetic-profile-source',
            'permitted_use_reference' => 'test-fixture://synthetic-profile-permitted-use',
            'material_requirement' => 'no_paper',
            'administration_procedure_reference' => 'Synthetic direct manual intake test procedure',
        ],
        'subtests' => [[
            'code' => 'SUB_A', 'name' => 'Subtest A', 'display_order' => 1,
            'response_mode' => 'keyed_items', 'item_order_policy' => 'fixed',
            'items' => [
                ['item_code' => 'SUB_A_1', 'local_number' => '1', 'display_order' => 1, 'is_scored' => true, 'option_tokens' => ['A', 'B']],
                ['item_code' => 'SUB_A_2', 'local_number' => '2', 'display_order' => 2, 'is_scored' => true, 'option_tokens' => ['A', 'B']],
            ],
        ]],
        'metrics' => [[
            'code' => 'RAW_A', 'name' => 'Raw A metric', 'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
            'subtest_code' => 'SUB_A', 'unit' => 'count', 'min_raw_value' => null, 'max_raw_value' => null,
            'precision_scale' => 0, 'is_required' => true, 'required_output_scales' => ['percentile_rank', 'stanine'],
            'procedure_reference' => null, 'construct_aptitude_area_id' => null, 'input_codes' => [],
            'input_contract_json' => [
                'allowed_sources' => ['import', 'manual'], 'required_resources' => [],
                'source_requirements' => [
                    'import' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
                    'manual' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
                ],
            ],
        ]],
    ];

    $instrument = new TestInstrument(['institution_id' => $actor->staffProfile->institution_id, 'code' => $code]);
    $instrument->forceFill(['is_synthetic' => true])->save();
    $draft = new TestInstrumentVersion([
        'test_instrument_id' => $instrument->id, 'version_number' => 1,
        'status' => TestInstrumentVersion::STATUS_DRAFT, 'lock_version' => 1, 'created_by_user_id' => $actor->id,
    ]);
    app(TestInstrumentDraftWriter::class)->save($draft, app(TestInstrumentProfileValidator::class)->normalize($payload));

    return app(ApproveTestInstrumentVersion::class)->handle(
        $actor, (int) $instrument->id, (int) $draft->id, (int) $draft->lock_version,
        (string) Str::uuid(), sourceReviewAttestation: true, isDemo: true,
    );
}

function createTestApprovedNormVersion(
    User $actor,
    string $code,
    ?AssessmentNormPackageVersion $successorOf = null,
    bool $alternateNorm = false,
): AssessmentNormPackageVersion {
    $intervals = $alternateNorm
        ? [['min' => 0, 'max' => 1, 'percentile_rank' => 20, 'stanine' => 3], ['min' => 2, 'max' => 3, 'percentile_rank' => 60, 'stanine' => 5]]
        : [['min' => 0, 'max' => 1, 'percentile_rank' => 30, 'stanine' => 4], ['min' => 2, 'max' => 3, 'percentile_rank' => 75, 'stanine' => 6]];
    $draft = app(SaveAssessmentNormDraft::class)->handle(
        actor: $actor, package: $successorOf?->norm_package_id, version: null, expectedRevision: null,
        requestId: (string) Str::uuid(),
        input: [
            'code' => $code, 'display_name' => "Synthetic Norm Package {$code}",
            'publisher_name' => 'Synthetic test norm fixture', 'form_code' => 'FORM-A',
            'edition' => 'Synthetic test fixture', 'language' => 'Test-only',
            'applicable_population' => 'Synthetic grade 12 test applicants',
            'source_reference' => 'test-fixture://synthetic-norm-source',
            'permitted_use_reference' => 'test-fixture://synthetic-norm-permitted-use', 'is_synthetic' => true,
            'tables' => [
                ['metric_code' => 'RAW_A', 'output_scale' => 'percentile_rank', 'intervals' => $intervals],
                ['metric_code' => 'RAW_A', 'output_scale' => 'stanine', 'intervals' => $intervals],
            ],
        ],
    );

    return app(ApproveAssessmentNormVersion::class)->handle(
        actor: $actor, package: $draft->norm_package_id, version: $draft->id,
        expectedRevision: $draft->lock_version, requestId: (string) Str::uuid(), attestationOrPayload: true,
    );
}

/** @return array{attempt: AdmissionAttempt, gradingSession: GradingSession, administration: AssessmentAdministration, assignment: ExamAssignment} */
function createTestDirectAdministration(TestCase $test, User $actor, TestInstrumentVersion $profileVersion, string $email): array
{
    $submittedAttempt = submitSyntheticApplicant($email, 'Norm '.substr(hash('sha256', $email), 0, 12))['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $attempt = app(AcceptAdmissionAttempt::class)->handle($submittedAttempt, $registrar, 1);
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $kitVersion = createTestActiveKitVersion($actor, $profileVersion);
    $label = 'Direct Norm Assessment '.Str::random(8);
    actingAsAdmissionSupportStaff($test, $actor)->post(route('exams.direct-assessment.store'), [
        'academic_cycle_id' => $cycle->id, 'applicant_ids' => [$attempt->id], 'label' => $label,
        'date' => now()->addDay()->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00',
        'test_kit_version_id' => $kitVersion->id,
    ])->assertRedirect()->assertSessionHasNoErrors();
    $session = ExamSession::query()->where('label', $label)->firstOrFail();
    $assignment = ExamAssignment::query()->where('exam_session_id', $session->id)->firstOrFail();

    return [
        'attempt' => $attempt, 'assignment' => $assignment,
        'gradingSession' => GradingSession::query()->where('exam_session_id', $session->id)->firstOrFail(),
        'administration' => AssessmentAdministration::query()->where('exam_assignment_id', $assignment->id)->active()->firstOrFail(),
    ];
}
