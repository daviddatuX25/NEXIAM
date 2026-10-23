<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Assessment\Actions\AcceptAssessmentRawResults;
use App\Assessment\Actions\ApproveTestInstrumentVersion;
use App\Assessment\Queries\TestKitDetailQuery;
use App\Assessment\Support\TestKitEligibility;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\BindAssessmentAdministration;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Actions\RemoveApplicantFromExamSession;
use App\Examination\Actions\UnpublishExamSession;
use App\Examination\Actions\UpdateDraftExamSession;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentScoringRun;
use App\Models\Assessment\ScoreRecord;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

require_once __DIR__.'/../../Support/NormConversionFixtures.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedE2Foundation();

    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $capabilities = [
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
        CapabilityCatalog::GUIDANCE_BOOKLET_CONTENT,
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
    ];

    foreach ($capabilities as $capability) {
        Permission::findOrCreate($capability, 'web');
    }

    $this->guidance->givePermissionTo($capabilities);
});

/**
 * @return array<string, mixed>
 */
function administrationBindingProfilePayload(string $code): array
{
    return [
        'request_id' => (string) Str::uuid(),
        'profile' => [
            'code' => $code,
            'display_name' => 'Approved administration binding fixture',
            'publisher_name' => 'Isolated software test fixture',
            'form_code' => 'BIND-FORM',
            'edition' => 'Test-only source fixture',
            'language' => 'Test-only',
            'source_reference' => 'test-fixture://binding-source',
            'permitted_use_reference' => 'test-fixture://binding-permission',
            'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER,
            'administration_procedure_reference' => 'Synthetic direct manual binding test procedure',
        ],
        'subtests' => [[
            'code' => 'SECTION_A',
            'name' => 'Section A',
            'display_order' => 1,
            'response_mode' => 'keyed_items',
            'item_order_policy' => 'fixed',
            'items' => [[
                'item_code' => 'ITEM_A',
                'local_number' => '1',
                'display_order' => 1,
                'is_scored' => true,
                'option_tokens' => ['A', 'B'],
            ]],
        ]],
        'metrics' => [[
            'code' => 'RAW_A',
            'name' => 'Section A count',
            'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
            'subtest_code' => 'SECTION_A',
            'unit' => 'count',
            'min_raw_value' => null,
            'max_raw_value' => null,
            'precision_scale' => 0,
            'is_required' => true,
            'required_output_scales' => ['percentile_rank'],
            'procedure_reference' => null,
            'construct_aptitude_area_id' => null,
            'input_codes' => [],
            'input_contract_json' => [
                'allowed_sources' => ['import', 'manual'],
                'required_resources' => [],
                'source_requirements' => [
                    'import' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
                    'manual' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
                ],
            ],
        ]],
    ];
}

function administrationBindingApprovedProfile(TestCase $test, User $actor, string $code): TestInstrumentVersion
{
    return createTestApprovedProfileVersion($test, $actor, $code);
}

function administrationBindingAcceptedAttempt(User $actor, string $email): AdmissionAttempt
{
    $submission = submitSyntheticApplicant($email, 'Binding');

    return app(AcceptAdmissionAttempt::class)->handle($submission['attempt'], $actor, 1);
}

test('scheduled publication keeps Setup-only logistics independent until exact Kit use is bound', function (): void {
    $profile = administrationBindingApprovedProfile($this, $this->guidance, 'BIND-SCHEDULED-PROFILE');
    $kitVersion = testKitSetupOnlyFixture($this->guidance, $profile)['version'];
    $kitVersion->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'activated_at' => now(),
        'activated_by_user_id' => $this->guidance->id,
        'revision' => (int) $kitVersion->revision + 1,
    ])->save();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $scoreCount = ScoreRecord::query()->count();
    $session = app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        'BIND-SCHEDULED-SESSION',
        now()->addDays(2)->toDateTimeString(),
        now()->addDays(2)->addHours(2)->toDateTimeString(),
        $this->guidance,
        ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        testKitVersionId: (int) $kitVersion->id,
    );
    $attempt = administrationBindingAcceptedAttempt($this->guidance, 'binding-scheduled@example.test');
    $assignment = app(AssignApplicantToExamSession::class)->handle($session, $attempt, $this->guidance);
    $published = app(PublishExamSession::class)->handle($session, $this->guidance, $session->version);

    expect($published->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and(AssessmentAdministration::query()->where('exam_assignment_id', $assignment->id)->exists())->toBeFalse()
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $published->id)->exists())->toBeFalse();

    $binding = app(BindAssessmentAdministration::class)->handle(
        $assignment,
        $kitVersion,
        $this->guidance,
        'manual',
    );
    expect($binding->binding_kind)->toBe(AssessmentAdministration::KIND_DIRECT_MANUAL)
        ->and($binding->test_instrument_version_id)->toBe($profile->id)
        ->and($binding->test_kit_version_id)->toBe($kitVersion->id)
        ->and($binding->manifest_sha256)->toBe($kitVersion->manifest_sha256)
        ->and($binding->material_snapshot_id)->toBeNull()
        ->and($binding->exam_session_seat_id)->toBeNull()
        ->and($binding->material_set_id)->toBeNull()
        ->and($binding->booklet_set_id)->toBeNull()
        ->and($binding->required_metric_set)->toHaveCount(1)
        ->and($binding->required_metric_set[0]['code'])->toBe('RAW_A')
        ->and(ScoreRecord::query()->count())->toBe($scoreCount);
});

test('direct assessment pins the explicitly selected Setup-only Kit without manufacturing paper evidence', function (): void {
    $profile = administrationBindingApprovedProfile($this, $this->guidance, 'BIND-DIRECT-PROFILE');
    $kitVersion = testKitSetupOnlyFixture($this->guidance, $profile)['version'];
    $kitVersion->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'activated_at' => now(),
        'activated_by_user_id' => $this->guidance->id,
        'revision' => (int) $kitVersion->revision + 1,
    ])->save();
    $attempt = administrationBindingAcceptedAttempt($this->guidance, 'binding-direct@example.test');
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);

    $this->actingAs($this->guidance)
        ->post(route('exams.direct-assessment.store'), [
            'academic_cycle_id' => $cycle->id,
            'applicant_ids' => [$attempt->id],
            'label' => 'Direct binding fixture',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'test_kit_version_id' => $kitVersion->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $assignment = ExamAssignment::query()
        ->where('admission_attempt_id', $attempt->id)
        ->firstOrFail();
    $binding = AssessmentAdministration::query()
        ->where('exam_assignment_id', $assignment->id)
        ->whereNull('superseded_at')
        ->firstOrFail();

    expect($binding->binding_kind)->toBe(AssessmentAdministration::KIND_DIRECT_MANUAL)
        ->and($binding->test_instrument_version_id)->toBe($profile->id)
        ->and($binding->test_kit_version_id)->toBe($kitVersion->id)
        ->and($binding->manifest_sha256)->toBe($kitVersion->manifest_sha256)
        ->and($binding->acceptance_receipt_id)->toBeNull()
        ->and($binding->material_snapshot_id)->toBeNull()
        ->and($binding->exam_session_seat_id)->toBeNull()
        ->and($binding->booklet_set_id)->toBeNull()
        ->and($binding->required_metric_set)->toHaveCount(1)
        ->and($binding->required_metric_set[0]['code'])->toBe('RAW_A')
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $assignment->exam_session_id)->exists())->toBeFalse();
});

test('binding reuses the exact retired sitting, requires attributable unused replacement, and refuses replacement after raw use', function (): void {
    $profile = administrationBindingApprovedProfile($this, $this->guidance, 'BIND-REPLACEMENT-PROFILE');
    $firstKit = testKitSetupOnlyFixture($this->guidance, $profile)['version'];
    $secondKit = testKitSetupOnlyFixture($this->guidance, $profile)['version'];
    $thirdKit = testKitSetupOnlyFixture($this->guidance, $profile)['version'];
    foreach ([$firstKit, $secondKit, $thirdKit] as $kit) {
        $kit->forceFill([
            'status' => TestKitVersion::STATUS_ACTIVE,
            'activated_at' => now(),
            'activated_by_user_id' => $this->guidance->id,
            'revision' => (int) $kit->revision + 1,
        ])->save();
    }
    $attempt = administrationBindingAcceptedAttempt($this->guidance, 'binding-used-boundary@example.test');
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $this->actingAs($this->guidance)
        ->post(route('exams.direct-assessment.store'), [
            'academic_cycle_id' => $cycle->id,
            'applicant_ids' => [$attempt->id],
            'label' => 'Direct binding replacement fixture',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'test_kit_version_id' => $firstKit->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $assignment = ExamAssignment::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    $original = AssessmentAdministration::query()->where('exam_assignment_id', $assignment->id)->active()->firstOrFail();

    $firstKit->forceFill([
        'status' => TestKitVersion::STATUS_RETIRED,
        'retired_at' => now(),
        'retired_by_user_id' => $this->guidance->id,
        'revision' => (int) $firstKit->revision + 1,
    ])->save();
    expect(app(BindAssessmentAdministration::class)->handle(
        $assignment,
        $firstKit->fresh(),
        $this->guidance,
    )->id)->toBe($original->id);

    expect(fn () => app(BindAssessmentAdministration::class)->handle(
        $assignment,
        $secondKit,
        $this->guidance,
    ))->toThrow(ValidationException::class);
    $replacement = app(BindAssessmentAdministration::class)->handle(
        $assignment,
        $secondKit,
        $this->guidance,
        replacementReason: 'Corrected the explicitly selected Setup edition before accepting any input.',
    );
    expect($replacement->test_kit_version_id)->toBe($secondKit->id)
        ->and($original->fresh()->superseded_at)->not->toBeNull();

    app(AcceptAssessmentRawResults::class)->handle(
        administration: $replacement,
        metricValues: ['RAW_A' => 1],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: (string) Str::uuid(),
    );
    expect(fn () => app(BindAssessmentAdministration::class)->handle(
        $assignment,
        $thirdKit,
        $this->guidance,
        replacementReason: 'This replacement must be refused after accepted raw input.',
    ))->toThrow(ValidationException::class);
});

test('unpublishing fences an actual-use binding and a later exact Kit bind derives the next revision', function (): void {
    $profile = administrationBindingApprovedProfile($this, $this->guidance, 'BIND-REPUBLISH-PROFILE');
    $kitVersion = testKitSetupOnlyFixture($this->guidance, $profile)['version'];
    $kitVersion->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'activated_at' => now(),
        'activated_by_user_id' => $this->guidance->id,
        'revision' => (int) $kitVersion->revision + 1,
    ])->save();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $session = app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        'BIND-REPUBLISH-SESSION',
        now()->addDays(3)->toDateTimeString(),
        now()->addDays(3)->addHours(2)->toDateTimeString(),
        $this->guidance,
        ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        testKitVersionId: (int) $kitVersion->id,
    );
    $attempt = administrationBindingAcceptedAttempt($this->guidance, 'binding-republish@example.test');
    $assignment = app(AssignApplicantToExamSession::class)->handle($session, $attempt, $this->guidance);
    $published = app(PublishExamSession::class)->handle($session, $this->guidance, $session->version);
    $binder = app(BindAssessmentAdministration::class);
    $firstBinding = $binder->handle($assignment, $kitVersion, $this->guidance, 'manual');

    $unpublished = app(UnpublishExamSession::class)->handle(
        $published,
        $this->guidance,
        $published->version,
        'Test-only actual-use revision.',
    );
    expect($firstBinding->fresh()->superseded_at)->not->toBeNull();

    $republished = app(PublishExamSession::class)->handle(
        $unpublished->fresh(),
        $this->guidance,
        $unpublished->version,
    );
    $currentBinding = $binder->handle($assignment, $kitVersion, $this->guidance, 'manual');

    expect($currentBinding->binding_revision)->toBe(2)
        ->and($currentBinding->material_snapshot_id)->toBeNull()
        ->and($currentBinding->id)->not->toBe($firstBinding->id)
        ->and(AssessmentAdministration::query()->where('exam_assignment_id', $assignment->id)->count())->toBe(2)
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $republished->id)->exists())->toBeFalse();
});

test('later catalog change does not alter a bound administration frozen required metric set', function (): void {
    $profile = administrationBindingApprovedProfile($this, $this->guidance, 'BIND-FROZEN-PROFILE');
    $kitVersion = testKitSetupOnlyFixture($this->guidance, $profile)['version'];
    $kitVersion->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'activated_at' => now(),
        'activated_by_user_id' => $this->guidance->id,
        'revision' => (int) $kitVersion->revision + 1,
    ])->save();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $session = app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        'BIND-FROZEN-SESSION',
        now()->addDays(4)->toDateTimeString(),
        now()->addDays(4)->addHours(2)->toDateTimeString(),
        $this->guidance,
        ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        testKitVersionId: (int) $kitVersion->id,
    );
    $attempt = administrationBindingAcceptedAttempt($this->guidance, 'binding-frozen@example.test');
    $assignment = app(AssignApplicantToExamSession::class)->handle($session, $attempt, $this->guidance);
    app(PublishExamSession::class)->handle($session, $this->guidance, $session->version);
    $binding = app(BindAssessmentAdministration::class)->handle(
        $assignment,
        $kitVersion,
        $this->guidance,
        'manual',
    );
    $frozenMetrics = $binding->required_metric_set;

    expect($frozenMetrics)->toHaveCount(1)
        ->and($frozenMetrics[0]['code'])->toBe('RAW_A');

    $instrument = TestInstrument::query()->findOrFail($profile->test_instrument_id);
    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.versions.successor', [
            'instrument' => $instrument->id,
            'version' => $profile->id,
        ]), [
            'request_id' => (string) Str::uuid(),
            'expected_revision' => $profile->lock_version,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $successor = $instrument->draftVersion()->firstOrFail();
    $payload = administrationBindingProfilePayload($instrument->code);
    $payload['profile']['display_name'] = 'Synthetic administration binding successor fixture';
    $payload['expected_revision'] = $successor->lock_version;
    $payload['metrics'][] = [
        ...$payload['metrics'][0],
        'code' => 'RAW_B',
        'name' => 'Additional required count',
    ];

    $this->actingAs($this->guidance)
        ->put(route('setup.test-instruments.versions.update', [
            'instrument' => $instrument->id,
            'version' => $successor->id,
        ]), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $successor->refresh();
    $successor = app(ApproveTestInstrumentVersion::class)->handle(
        $this->guidance,
        (int) $instrument->id,
        (int) $successor->id,
        (int) $successor->lock_version,
        (string) Str::uuid(),
        sourceReviewAttestation: true,
        isDemo: true,
    );

    expect($successor->fresh()->version_number)->toBe(2)
        ->and($successor->fresh()->status)->toBe(TestInstrumentVersion::STATUS_APPROVED)
        ->and($successor->metrics()->where('is_required', true)->pluck('code')->all())->toEqualCanonicalizing(['RAW_A', 'RAW_B'])
        ->and($instrument->fresh()->current_approved_version_id)->toBe($successor->id)
        ->and($binding->fresh()->test_instrument_version_id)->toBe($profile->id)
        ->and($binding->fresh()->required_metric_set)->toBe($frozenMetrics)
        ->and($binding->fresh()->required_metric_set)->toHaveCount(1)
        ->and($binding->fresh()->required_metric_set[0]['code'])->toBe('RAW_A');
});

test('binding and raw acceptance reject an assignment removed from the current sitting', function (): void {
    $profile = administrationBindingApprovedProfile($this, $this->guidance, 'BIND-STALE-OWNER-PROFILE');
    $kitVersion = testKitSetupOnlyFixture($this->guidance, $profile)['version'];
    $kitVersion->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'activated_at' => now(),
        'activated_by_user_id' => $this->guidance->id,
        'revision' => (int) $kitVersion->revision + 1,
    ])->save();
    $attempt = administrationBindingAcceptedAttempt($this->guidance, 'binding-stale-owner@example.test');
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $room = Room::query()->where('institution_id', $cycle->institution_id)->firstOrFail();
    $startsAt = now()->addDays(6)->setTime(9, 0);
    $session = app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        'BIND-STALE-OWNER-'.Str::upper(Str::random(8)),
        $startsAt->toDateTimeString(),
        $startsAt->copy()->addHours(2)->toDateTimeString(),
        $this->guidance,
        ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        testKitVersionId: (int) $kitVersion->id,
    );
    $assignment = app(AssignApplicantToExamSession::class)->handle($session, $attempt, $this->guidance);
    $administration = app(BindAssessmentAdministration::class)->handle(
        $assignment,
        $kitVersion,
        $this->guidance,
    );
    app(RemoveApplicantFromExamSession::class)->handle(
        $session,
        $assignment,
        $this->guidance,
        'Stale-owner boundary fixture.',
    );

    expect(fn () => app(BindAssessmentAdministration::class)->handle(
        $assignment,
        $kitVersion,
        $this->guidance,
    ))->toThrow(ValidationException::class);
    expect(fn () => app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['RAW_A' => 1],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: (string) Str::uuid(),
    ))->toThrow(ValidationException::class);
    expect($administration->fresh()->superseded_at)->toBeNull()
        ->and(AssessmentScoringRun::query()->where('administration_id', $administration->id)->exists())->toBeFalse();
});
test('locked final-only Test Setup requires the external-final operation without material pins', function (): void {
    $profile = createExternalFinalTestProfile(
        $this->guidance,
        externalFinalConstructIds(),
        'BIND-FINAL-'.Str::upper(Str::random(8)),
    );
    $kit = testKitSetupOnlyFixture($this->guidance, $profile);
    $version = $kit['version']->fresh();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $startsAt = now()->addDays(5)->setTime(9, 0);
    $effectiveEndAt = $startsAt->copy()->addHours(2);
    $session = app(CreateExamSession::class)->handle(
        cycle: $cycle,
        room: $room,
        code: 'BIND-FINAL-SESSION-'.Str::upper(Str::random(6)),
        startsAt: $startsAt->toDateTimeString(),
        effectiveEndAt: $effectiveEndAt->toDateTimeString(),
        actor: $this->guidance,
        seatingMode: ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        testKitVersionId: (int) $version->id,
    );
    $session = app(UpdateDraftExamSession::class)->handle(
        session: $session,
        cycle: $cycle,
        room: $room,
        code: $session->code.'-EDIT',
        startsAt: $startsAt->copy()->addDay()->toDateTimeString(),
        effectiveEndAt: $effectiveEndAt->copy()->addDay()->toDateTimeString(),
        actor: $this->guidance,
        expectedVersion: (int) $session->version,
        seatingMode: ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        testKitVersionId: (int) $version->id,
    );
    $eligibility = app(TestKitEligibility::class);

    $manual = $eligibility->evaluate(
        $version,
        $cycle,
        TestKitEligibility::PURPOSE_NEW_USE,
        operation: 'manual',
    );
    $externalFinal = $eligibility->evaluate(
        $version,
        $cycle,
        TestKitEligibility::PURPOSE_NEW_USE,
        operation: 'external_final',
    );
    $detail = app(TestKitDetailQuery::class)->query(
        $this->guidance,
        (int) $kit['kit']->id,
        (int) $version->id,
    );
    expect((int) $session->test_kit_version_id)->toBe((int) $version->id)
        ->and((int) $session->test_instrument_version_id)->toBe((int) $profile->id)
        ->and($session->booklet_version_id)->toBeNull()
        ->and($version->testInstrumentVersion?->isFinalOnly())->toBeTrue()
        ->and($version->template_version_id)->toBeNull()
        ->and($version->booklet_version_id)->toBeNull()
        ->and($version->booklet_set_id)->toBeNull()
        ->and($version->selected_acceptance_receipt_id)->toBeNull()
        ->and($version->manifest_json['requirements']['required_resources'])->toBe([])
        ->and($detail['test_kit_version']['scoring_method'])->toBe('final_scores_only')
        ->and($detail['test_profile']['required_final_metric_count'])->toBe(6)
        ->and($detail['readiness']['omr_readiness'])->toBe('not_applicable')
        ->and($detail['trial']['verification_status'])->toBe('not_applicable')
        ->and($detail['norms']['status'])->toBe('not_applicable')
        ->and($detail['actions']['answer_sheet'])->toBeNull()
        ->and($detail['actions']['booklet'])->toBeNull()
        ->and(array_column($manual['blockers'], 'code'))->toContain('external_final_operation_required')
        ->and(array_column($externalFinal['blockers'], 'code'))->not->toContain('external_final_operation_required');
});
