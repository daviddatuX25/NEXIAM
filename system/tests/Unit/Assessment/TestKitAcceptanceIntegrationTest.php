<?php

declare(strict_types=1);

use App\Assessment\Actions\AcceptAssessmentRawResults;
use App\Assessment\Actions\Omr\ConfirmOmrCaptureBatch;
use App\Assessment\Actions\Omr\Operational\CompareOmrDiagnosticRun;
use App\Assessment\Actions\Omr\Operational\CompleteOmrDiagnosticRun;
use App\Assessment\Actions\Omr\Operational\RetakeOmrDiagnosticPage;
use App\Assessment\Actions\Omr\Operational\StartOmrDiagnosticRun;
use App\Assessment\Actions\RecordTestKitAcceptanceReceipt;
use App\Assessment\Actions\SelectTestKitAcceptanceReceipt;
use App\Assessment\Queries\KitPairedAnswerKeyPreviewQuery;
use App\Assessment\Support\KitCompositionContext;
use App\Assessment\Support\TestKitAcceptancePlan;
use App\Assessment\Support\TestKitCurrentAcceptanceStatus;
use App\Assessment\Support\TestKitCurrentEvidence;
use App\Assessment\Support\TestKitEligibility;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\AssessmentScoringRun;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\TestKitAcceptanceReceipt;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/../../Support/TestKitSoftwareAcceptanceFixtures.php';

beforeEach(function (): void {
    $this->testKitPostgresTransactionStarted = false;

    if (DB::connection()->getDriverName() !== 'pgsql') {
        test()->markTestSkipped('Test-kit acceptance integration requires the isolated PostgreSQL test-kit configuration.');
    }

    expect((string) DB::scalar('SELECT current_database()'))
        ->toMatch('/^nexiam_test_kit_acceptance_[a-z0-9_]+$/');

    DB::beginTransaction();
    $this->testKitPostgresTransactionStarted = true;

    $this->institution = Institution::query()->where('code', 'SYN-INST-001')->first()
        ?? Institution::query()->create([
            'code' => 'SYN-INST-001',
            'name' => 'Synthetic Acceptance Integration Test Institution',
        ]);

    $this->actor = User::factory()->create();

    $cycle = AcademicCycle::query()->create([
        'institution_id' => $this->institution->id,
        'code' => 'CYCLE-'.Str::upper(Str::random(6)),
        'name' => 'Synthetic Acceptance Cycle',
        'opens_on' => now()->subYear()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);

    StaffProfile::query()->create([
        'user_id' => $this->actor->id,
        'institution_id' => $this->institution->id,
        'office' => 'guidance',
        'active_cycle_code' => $cycle->code,
        'is_active' => true,
    ]);
    foreach ([
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_SCORING_OMR,
        CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
        CapabilityCatalog::GUIDANCE_BOOKLET_CONTENT,
    ] as $capability) {
        Permission::findOrCreate($capability);
        $this->actor->givePermissionTo($capability);
    }
    $this->fixture = testKitAcceptanceFixture($this->actor, $this->institution);
});

afterEach(function (): void {
    if (! $this->testKitPostgresTransactionStarted) {
        return;
    }

    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

test('two-item native plan acceptance succeeds across all frozen cases, seals run, records passing receipt, and selects under ordered locks', function (): void {
    $prepared = testKitDiagnosticEvidenceFixture(
        $this->fixture,
        $this->actor,
        $this->institution,
    );
    $run = $prepared['run'];

    // 1. Compare diagnostic run: compares all cases against Set A keys
    $compareResult = app(CompareOmrDiagnosticRun::class)->handle(
        run: $run,
        actor: $this->actor,
        expectedRevision: (int) $run->revision,
        idempotencyKey: (string) Str::uuid(),
    );
    expect($compareResult['outcome'])->toBe('succeeded')
        ->and($compareResult['comparison']['passed'])->toBeTrue();

    // 2. Complete diagnostic run: seals evidence vector via same current selector
    $runAfterCompare = $run->fresh();
    $completeResult = app(CompleteOmrDiagnosticRun::class)->handle(
        run: $runAfterCompare,
        actor: $this->actor,
        expectedRevision: (int) $runAfterCompare->revision,
        idempotencyKey: (string) Str::uuid(),
    );
    expect($completeResult['outcome'])->toBe('succeeded');

    $runCompleted = $run->fresh();
    expect($runCompleted->status)->toBe(OmrDiagnosticRun::STATUS_COMPLETED)
        ->and($runCompleted->phase)->toBe(OmrDiagnosticRun::PHASE_COMPLETE)
        ->and($runCompleted->sealed_at)->not->toBeNull()
        ->and((int) $runCompleted->sealed_evidence_revision)->toBe((int) $runCompleted->revision)
        ->and($runCompleted->sealed_evidence_vector_sha256)->toMatch('/^[a-f0-9]{64}$/');

    // 3. Current evidence selector verifies completeness and seal matching
    $evidence = app(TestKitCurrentEvidence::class)->forRun($runCompleted, true);
    expect($evidence['complete'])->toBeTrue()
        ->and($evidence['errors'])->toBe([])
        ->and($evidence['evidence_vector_sha256'])->toBe($runCompleted->sealed_evidence_vector_sha256);

    // 4. Record acceptance receipt from frozen plan: caller passes no truth or raw metrics
    $receiptResult = app(RecordTestKitAcceptanceReceipt::class)->handle(
        actor: $this->actor,
        version: $this->fixture['version'],
        run: $runCompleted,
        expectedRunRevision: (int) $runCompleted->revision,
    );

    $this->assertSame(
        TestKitAcceptanceReceipt::RESULT_PASSED,
        $receiptResult['result'],
        json_encode($receiptResult['check_report_json'], JSON_THROW_ON_ERROR),
    );
    expect($receiptResult['check_report_json']['checks'])->toBeArray();

    // Confirm all four cases passed in the check report
    $caseResults = $receiptResult['check_report_json']['case_results'];
    expect($caseResults)->toHaveKeys(['correct', 'incorrect', 'blank', 'mixed']);
    foreach ($caseResults as $caseResult) {
        expect($caseResult['response_truth_match'])->toBeTrue()
            ->and($caseResult['raw_score_match'])->toBeTrue()
            ->and($caseResult['passed'])->toBeTrue();
    }

    $status = app(TestKitCurrentAcceptanceStatus::class);
    $receiptModel = TestKitAcceptanceReceipt::query()->findOrFail((int) $receiptResult['id']);
    expect($status->forReceipt($this->fixture['version'], $receiptModel)['current'])->toBeTrue();
    $incompleteReport = $receiptResult['check_report_json'];
    array_pop($incompleteReport['checks']);
    expect($status->completeCheckReport($incompleteReport, $runCompleted->acceptance_plan_json))->toBeFalse();

    // 5. Select acceptance receipt on candidate version
    $selector = app(SelectTestKitAcceptanceReceipt::class);
    $versionBefore = $this->fixture['version']->fresh();
    expect($versionBefore->selected_acceptance_receipt_id)->toBeNull();

    $versionSelected = $selector->handle(
        actor: $this->actor,
        testKitId: (int) $this->fixture['kit']->id,
        versionId: (int) $versionBefore->id,
        receiptIdOrInput: (int) $receiptResult['id'],
        expectedRevision: (int) $versionBefore->revision,
        idempotencyKey: (string) Str::uuid(),
    );

    expect($versionSelected->selected_acceptance_receipt_id)->toBe((int) $receiptResult['id'])
        ->and((int) $versionSelected->revision)->toBe((int) $versionBefore->revision + 1);
    expect($status->forReceipt(
        $versionSelected,
        TestKitAcceptanceReceipt::query()->findOrFail((int) $receiptResult['id']),
    )['current'])->toBeTrue();

    // Retained immutable history check: earlier receipts remain in database and unmutated
    expect(TestKitAcceptanceReceipt::query()->whereKey((int) $receiptResult['id'])->exists())->toBeTrue();

    $firstReceipt = TestKitAcceptanceReceipt::query()->findOrFail((int) $receiptResult['id']);
    // Synthetic historical lifecycle metadata only; this does not claim institutional activation.
    $activeVersion = $versionSelected->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'activated_at' => now(),
        'activated_by_user_id' => $this->actor->id,
    ]);
    $activeVersion->save();
    $this->fixture['version'] = $activeVersion->fresh();
    $renewal = testKitDiagnosticEvidenceFixture(
        $this->fixture,
        $this->actor,
        $this->institution,
    );
    $renewalRun = $renewal['run'];
    app(CompareOmrDiagnosticRun::class)->handle(
        run: $renewalRun,
        actor: $this->actor,
        expectedRevision: (int) $renewalRun->revision,
        idempotencyKey: (string) Str::uuid(),
    );
    $renewalRun = $renewalRun->fresh();
    app(CompleteOmrDiagnosticRun::class)->handle(
        run: $renewalRun,
        actor: $this->actor,
        expectedRevision: (int) $renewalRun->revision,
        idempotencyKey: (string) Str::uuid(),
    );
    $renewedReceipt = app(RecordTestKitAcceptanceReceipt::class)->handle(
        actor: $this->actor,
        version: $this->fixture['version'],
        run: $renewalRun->fresh(),
        expectedRunRevision: (int) $renewalRun->fresh()->revision,
    );
    foreach ($renewedReceipt['check_report_json']['case_results'] as $caseResult) {
        expect($caseResult['response_truth_match'])->toBeTrue()
            ->and($caseResult['raw_score_match'])->toBeTrue()
            ->and($caseResult['passed'])->toBeTrue();
    }
    $activeBeforeSelection = $this->fixture['version']->fresh();
    $renewedSelection = app(SelectTestKitAcceptanceReceipt::class)->handle(
        actor: $this->actor,
        testKitId: (int) $this->fixture['kit']->id,
        versionId: (int) $activeBeforeSelection->id,
        receiptIdOrInput: (int) $renewedReceipt['id'],
        expectedRevision: (int) $activeBeforeSelection->revision,
        idempotencyKey: (string) Str::uuid(),
    );

    expect($renewedReceipt['result'])->toBe(TestKitAcceptanceReceipt::RESULT_PASSED)
        ->and($renewedSelection->status)->toBe(TestKitVersion::STATUS_ACTIVE)
        ->and((int) $renewedSelection->selected_acceptance_receipt_id)->toBe((int) $renewedReceipt['id'])
        ->and(TestKitAcceptanceReceipt::query()->findOrFail((int) $firstReceipt->id)->result)
        ->toBe(TestKitAcceptanceReceipt::RESULT_PASSED);
    expect($status->forReceipt(
        $renewedSelection,
        TestKitAcceptanceReceipt::query()->findOrFail((int) $renewedReceipt['id']),
    )['current'])->toBeTrue();

    $unrecordedRenewal = testKitDiagnosticEvidenceFixture(
        $this->fixture,
        $this->actor,
        $this->institution,
    );
    $unrecordedRun = $unrecordedRenewal['run'];
    app(CompareOmrDiagnosticRun::class)->handle(
        run: $unrecordedRun,
        actor: $this->actor,
        expectedRevision: (int) $unrecordedRun->revision,
        idempotencyKey: (string) Str::uuid(),
    );
    $unrecordedRun = $unrecordedRun->fresh();
    app(CompleteOmrDiagnosticRun::class)->handle(
        run: $unrecordedRun,
        actor: $this->actor,
        expectedRevision: (int) $unrecordedRun->revision,
        idempotencyKey: (string) Str::uuid(),
    );

    // Synthetic historical retirement metadata is not evidence of a real operational pin.
    $retiredVersion = $renewedSelection->forceFill([
        'status' => TestKitVersion::STATUS_RETIRED,
        'retired_at' => now(),
        'retired_by_user_id' => $this->actor->id,
    ]);
    $retiredVersion->save();
    $this->fixture['version'] = $retiredVersion->fresh();
    $cycle = AcademicCycle::query()->where('institution_id', $this->institution->id)->firstOrFail();
    expect(app(TestKitEligibility::class)->evaluate(
        $this->fixture['version'],
        $cycle,
        TestKitEligibility::PURPOSE_REHEARSAL,
    )['eligible'])->toBeFalse();
    expect(fn () => app(SelectTestKitAcceptanceReceipt::class)->handle(
        actor: $this->actor,
        testKitId: (int) $this->fixture['kit']->id,
        versionId: (int) $this->fixture['version']->id,
        receiptIdOrInput: (int) $renewedReceipt['id'],
        expectedRevision: (int) $this->fixture['version']->revision,
        idempotencyKey: (string) Str::uuid(),
    ))->toThrow(ValidationException::class);
    $replayed = app(RecordTestKitAcceptanceReceipt::class)->handle(
        actor: $this->actor,
        version: $this->fixture['version'],
        run: $renewalRun->fresh(),
        expectedRunRevision: (int) $renewalRun->fresh()->revision,
    );
    expect((int) $replayed['id'])->toBe((int) $renewedReceipt['id']);
    expect(fn () => app(RecordTestKitAcceptanceReceipt::class)->handle(
        actor: $this->actor,
        version: $this->fixture['version'],
        run: $unrecordedRun->fresh(),
        expectedRunRevision: (int) $unrecordedRun->fresh()->revision,
    ))->toThrow(RuntimeException::class);
    expect(fn () => app(StartOmrDiagnosticRun::class)->handle(
        templateVersion: null,
        bookletVersion: null,
        declaredSetCode: null,
        actor: $this->actor,
        institutionId: (int) $this->institution->id,
        academicCycleId: (int) $cycle->id,
        testKitVersion: $this->fixture['version'],
        acceptancePlan: $renewal['planRecord'],
    ))->toThrow(RuntimeException::class);
});

test('retained operation pins permit canonical renewal while the independent source hold still blocks new paper scores', function (): void {
    $original = testKitAcceptedSoftwareFixture($this->fixture, $this->actor, $this->institution);
    $this->fixture = $original['fixture'];
    $originalReceipt = $original['receipt'];
    // The only direct setup below is an explicitly historical use tuple; acceptance receipts remain real Action-produced records.
    $version = $this->fixture['version']->fresh(['templateVersion', 'bookletVersion', 'bookletSet', 'testInstrumentVersion']);
    $version->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'activated_at' => now(),
        'activated_by_user_id' => $this->actor->id,
    ])->save();
    $cycle = AcademicCycle::query()->where('institution_id', $this->institution->id)->firstOrFail();
    $now = now();
    $room = Room::factory()->create([
        'institution_id' => (int) $this->institution->id,
        'code' => 'SYN-RENEWAL-'.Str::upper(Str::random(6)),
    ]);
    $identityId = (int) DB::table('applicant_identities')->insertGetId([
        'institution_id' => $this->institution->id,
        'normalized_key' => 'renewal-'.Str::lower((string) Str::uuid()),
        'first_name' => 'Synthetic',
        'last_name' => 'Renewal',
        'birthdate' => '2001-01-01',
        'sex' => 'unspecified',
        'email' => 'renewal-'.Str::lower((string) Str::uuid()).'@example.test',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $attemptId = (int) DB::table('admission_attempts')->insertGetId([
        'applicant_identity_id' => $identityId,
        'institution_id' => $this->institution->id,
        'academic_cycle_id' => $cycle->id,
        'reference_code' => 'SYN-RENEWAL-'.Str::upper(Str::random(8)),
        'status' => 'accepted',
        'accepted_at' => $now,
        'payload' => json_encode(['first_name' => 'Synthetic', 'last_name' => 'Renewal'], JSON_THROW_ON_ERROR),
        'version' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $sessionId = (int) DB::table('exam_sessions')->insertGetId([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'SYN-RENEWAL-'.Str::upper(Str::random(8)),
        'label' => 'Synthetic historical used-kit administration',
        'status' => 'in_progress',
        'booking_mode' => 'staff_only',
        'starts_at' => $now,
        'effective_end_at' => $now->copy()->addHours(2),
        'published_at' => $now,
        'version' => 1,
        'test_instrument_version_id' => $version->test_instrument_version_id,
        'booklet_version_id' => $version->booklet_version_id,
        'test_kit_version_id' => $version->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $snapshotId = (int) DB::table('exam_session_material_snapshots')->insertGetId([
        'exam_session_id' => $sessionId,
        'revision' => 1,
        'is_current' => true,
        'template_version_id' => $version->template_version_id,
        'booklet_version_id' => $version->booklet_version_id,
        'test_instrument_version_id' => $version->test_instrument_version_id,
        'test_kit_version_id' => $version->id,
        'manifest_sha256' => $version->manifest_sha256,
        'acceptance_receipt_id' => $originalReceipt->id,
        'scoring_policy_identity' => 'synthetic-historical-score-policy',
        'material_hash' => hash('sha256', 'synthetic-historical-material'),
        // The attributed administration ledger is the operational pin in this case.
        'published_by_user_id' => null,
        'published_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $materialSetId = (int) DB::table('exam_session_material_sets')->insertGetId([
        'snapshot_id' => $snapshotId,
        'booklet_set_id' => $version->booklet_set_id,
        'set_code' => 'A',
        'order_hash' => hash('sha256', 'synthetic-order'),
        'answer_key_hash' => hash('sha256', 'synthetic-key'),
        'answer_key_json' => json_encode([], JSON_THROW_ON_ERROR),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $seatId = (int) DB::table('exam_session_seats')->insertGetId([
        'material_snapshot_id' => $snapshotId,
        'ordinal' => 1,
        'row_number' => 1,
        'column_number' => 1,
        'seat_label' => 'A-01',
        'booklet_set_id' => $version->booklet_set_id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $assignmentId = (int) DB::table('exam_assignments')->insertGetId([
        'exam_session_id' => $sessionId,
        'admission_attempt_id' => $attemptId,
        'status' => 'assigned',
        'version' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $allocationId = (int) DB::table('exam_assignment_seat_allocations')->insertGetId([
        'exam_assignment_id' => $assignmentId,
        'exam_session_seat_id' => $seatId,
        'effective_from' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('exam_attendance_records')->insert([
        'exam_assignment_id' => $assignmentId,
        'recorded_by_user_id' => $this->actor->id,
        'outcome' => 'present',
        'idempotency_key' => 'synthetic-historical-attendance-'.Str::uuid(),
        'recorded_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('exam_submission_records')->insert([
        'exam_assignment_id' => $assignmentId,
        'recorded_by_user_id' => $this->actor->id,
        'seat_allocation_id' => $allocationId,
        'idempotency_key' => 'synthetic-historical-submission-'.Str::uuid(),
        'submitted_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $gradingSessionId = (int) DB::table('grading_sessions')->insertGetId([
        'exam_session_id' => $sessionId,
        'status' => 'open',
        'version' => 1,
        'opened_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $administrationId = (int) DB::table('assessment_administrations')->insertGetId([
        'exam_assignment_id' => $assignmentId,
        'admission_attempt_id' => $attemptId,
        'binding_kind' => AssessmentAdministration::KIND_SCHEDULED_MATERIAL,
        'binding_revision' => 1,
        'test_instrument_version_id' => $version->test_instrument_version_id,
        'material_snapshot_id' => $snapshotId,
        'exam_session_seat_id' => $seatId,
        'material_set_id' => $materialSetId,
        'booklet_set_id' => $version->booklet_set_id,
        'test_kit_version_id' => $version->id,
        'manifest_sha256' => $version->manifest_sha256,
        'acceptance_receipt_id' => $originalReceipt->id,
        'procedure_snapshot_json' => json_encode([
            'material_requirement' => $version->testInstrumentVersion->material_requirement,
            'administration_procedure_reference' => $version->testInstrumentVersion->administration_procedure_reference,
            'content_sha256' => $version->testInstrumentVersion->content_sha256,
            'manifest_schema_version' => (int) $version->manifest_schema_version,
            'kit_manifest_sha256' => (string) $version->manifest_sha256,
            'operation' => 'omr',
        ], JSON_THROW_ON_ERROR),
        'required_metric_set' => json_encode($version->testInstrumentVersion->metrics()
            ->where('is_required', true)->orderBy('code')->get()->map(static fn ($metric): array => [
                'code' => $metric->code, 'name' => $metric->name, 'rule_kind' => $metric->rule_kind,
                'unit' => $metric->unit, 'min_raw_value' => $metric->min_raw_value,
                'max_raw_value' => $metric->max_raw_value, 'precision_scale' => $metric->precision_scale,
                'required_output_scales' => $metric->required_output_scales, 'input_contract_json' => $metric->input_contract_json,
            ])->all(), JSON_THROW_ON_ERROR),
        'binding_state' => AssessmentAdministration::STATE_RAW_INCOMPLETE,
        'bound_by_user_id' => $this->actor->id,
        'bound_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $administration = AssessmentAdministration::query()->findOrFail($administrationId);
    $administrationPins = $administration->only([
        'test_instrument_version_id',
        'material_snapshot_id',
        'exam_session_seat_id',
        'material_set_id',
        'booklet_set_id',
        'test_kit_version_id',
        'manifest_sha256',
        'acceptance_receipt_id',
    ]);
    $snapshotBefore = (array) DB::table('exam_session_material_snapshots')->where('id', $snapshotId)->first();
    $seatBefore = (array) DB::table('exam_session_seats')->where('id', $seatId)->first();
    $materialSetBefore = (array) DB::table('exam_session_material_sets')->where('id', $materialSetId)->first();
    // Retained operation is an explicit historical Given, not newly qualified paper use.
    $originalScoringRun = AssessmentScoringRun::query()->create([
        'administration_id' => $administration->id,
        'run_uuid' => (string) Str::uuid(),
        'source' => AssessmentScoringRun::SOURCE_OMR,
        'actor_user_id' => $this->actor->id,
        'idempotency_key' => 'synthetic-retained-operation',
        'test_kit_version_id' => $version->id,
        'test_kit_version_revision' => $version->fresh()->revision,
        'manifest_sha256' => $version->manifest_sha256,
        'acceptance_receipt_id' => $originalReceipt->id,
    ]);
    $originalRawRevision = AssessmentRawResultRevision::query()->create([
        'administration_id' => $administration->id,
        'scoring_run_id' => $originalScoringRun->id,
        'metric_id' => $this->fixture['metric']->id,
        'metric_code' => $this->fixture['metric']->code,
        'revision_no' => 1, 'raw_value' => '1',
        'source' => AssessmentScoringRun::SOURCE_OMR,
        'status' => 'valid',
        'provenance_json' => ['fixture' => 'retained historical operation; no new paper qualification'],
        'is_current' => true,
    ]);

    TestKitAcceptancePlan::$protocolOverride = [
        'schema_version' => 'test-kit-acceptance-protocol-v2',
        'sha256' => hash('sha256', 'test-kit-acceptance-protocol-v2:stale-protocol-hold'),
    ];
    try {
        expect(app(TestKitCurrentAcceptanceStatus::class)->forReceipt($version->fresh(), $originalReceipt)['current'])->toBeFalse();
        expect(fn () => app(AcceptAssessmentRawResults::class)->handle(
            administration: $administration,
            metricValues: [$this->fixture['metric']->code => 1],
            source: AssessmentScoringRun::SOURCE_OMR,
            actor: $this->actor,
            idempotencyKey: 'renewal-stale-protocol-denied',
            operationAcceptanceReceiptId: (int) $originalReceipt->id,
        ))->toThrow(RuntimeException::class);
        expect($originalScoringRun->fresh()->acceptance_receipt_id)->toBe((int) $originalReceipt->id)
            ->and($originalRawRevision->fresh()->raw_value)->toBe($originalRawRevision->raw_value);
    } finally {
        TestKitAcceptancePlan::$protocolOverride = null;
    }
    $renewal = testKitAcceptedSoftwareFixture($this->fixture, $this->actor, $this->institution);
    $this->fixture = $renewal['fixture'];
    $renewedReceipt = $renewal['receipt'];
    $kitVersion = $this->fixture['version']->fresh();
    $selected = $kitVersion;
    expect((int) $selected->selected_acceptance_receipt_id)->toBe((int) $renewedReceipt->id)
        ->and((int) $selected->id)->toBe((int) $kitVersion->id)
        ->and(TestKitAcceptanceReceipt::query()->findOrFail((int) $originalReceipt->id)->getAttributes())
        ->toEqual($originalReceipt->getAttributes());
    expect(fn () => app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration->fresh(),
        metricValues: [$this->fixture['metric']->code => 2],
        source: AssessmentScoringRun::SOURCE_OMR,
        actor: $this->actor,
        idempotencyKey: 'renewal-new-omr-operation',
        operationAcceptanceReceiptId: (int) $renewedReceipt->id,
    ))->toThrow(RuntimeException::class, 'source_evidence_unavailable');
    expect(AssessmentScoringRun::query()->where('administration_id', $administration->id)->count())->toBe(1)
        ->and($originalScoringRun->fresh()->acceptance_receipt_id)->toBe((int) $originalReceipt->id)
        ->and($originalRawRevision->fresh()->raw_value)->toBe('1.000000')
        ->and($administration->fresh()->acceptance_receipt_id)->toBe((int) $originalReceipt->id)
        ->and($administration->fresh()->material_snapshot_id)->toBe($snapshotId)
        ->and($administration->fresh()->manifest_sha256)->toBe($version->manifest_sha256);
    expect($administration->fresh()->only(array_keys($administrationPins)))->toBe($administrationPins)
        ->and((array) DB::table('exam_session_material_snapshots')->where('id', $snapshotId)->first())->toBe($snapshotBefore)
        ->and((array) DB::table('exam_session_seats')->where('id', $seatId)->first())->toBe($seatBefore)
        ->and((array) DB::table('exam_session_material_sets')->where('id', $materialSetId)->first())->toBe($materialSetBefore);

    $eligibility = app(TestKitEligibility::class);
    $cycle = $cycle->fresh();
    $selectedVersion = $selected->fresh();
    expect(array_column($eligibility->evaluate($selectedVersion->fresh(), $cycle, TestKitEligibility::PURPOSE_PINNED_USE, $originalReceipt, $renewedReceipt)['blockers'], 'code'))->toBe(['source_evidence_unavailable']);
    $selectedVersion->forceFill([
        'status' => TestKitVersion::STATUS_RETIRED,
        'retired_at' => now(),
        'retired_by_user_id' => $this->actor->id,
    ])->save();
    expect($eligibility->allowsReacceptance($selectedVersion->fresh()))->toBeTrue();
    expect(fn () => app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration->fresh(),
        metricValues: [$this->fixture['metric']->code => 2],
        source: AssessmentScoringRun::SOURCE_OMR,
        actor: $this->actor,
        idempotencyKey: 'renewal-retired-pinned-omr-operation',
        operationAcceptanceReceiptId: (int) $renewedReceipt->id,
    ))->toThrow(RuntimeException::class, 'source_evidence_unavailable');
    expect($eligibility->allowsReacceptance($selectedVersion->fresh()))->toBeTrue()
        ->and($originalScoringRun->fresh()->acceptance_receipt_id)->toBe((int) $originalReceipt->id);
    $selectedVersion->forceFill([
        'revoked_at' => now(),
        'revoked_by_user_id' => $this->actor->id,
        'revocation_reason' => 'Synthetic test revocation for pinned-use denial.',
    ])->save();
    expect($eligibility->evaluate($selectedVersion->fresh(), $cycle, TestKitEligibility::PURPOSE_PINNED_USE, $originalReceipt, $renewedReceipt)['eligible'])->toBeFalse()
        ->and($eligibility->allowsReacceptance($selectedVersion->fresh()))->toBeFalse();
    expect(fn () => app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration->fresh(),
        metricValues: [$this->fixture['metric']->code => 2],
        source: AssessmentScoringRun::SOURCE_OMR,
        actor: $this->actor,
        idempotencyKey: 'renewal-revoked-pinned-omr-denied',
        operationAcceptanceReceiptId: (int) $renewedReceipt->id,
    ))->toThrow(RuntimeException::class);
});

test('missing or corrupt non-correct case records failed receipt and rejects selection', function (): void {
    // Corrupt the 'incorrect' case so that LOGIC_Q_1 has 'A' instead of 'B', scoring 1 instead of 0
    $prepared = testKitDiagnosticEvidenceFixture(
        $this->fixture,
        $this->actor,
        $this->institution,
        includedCaseIds: ['correct', 'incorrect', 'blank', 'mixed'],
        caseResponseOverrides: [
            'incorrect' => ['LOGIC_Q_1' => 'A', 'LOGIC_Q_2' => 'A'], // Scores 1 instead of expected 0!
        ],
    );
    $run = $prepared['run'];

    app(CompareOmrDiagnosticRun::class)->handle(
        run: $run,
        actor: $this->actor,
        expectedRevision: (int) $run->revision,
        idempotencyKey: (string) Str::uuid(),
    );
    app(CompleteOmrDiagnosticRun::class)->handle(
        run: $run->fresh(),
        actor: $this->actor,
        expectedRevision: (int) $run->fresh()->revision,
        idempotencyKey: (string) Str::uuid(),
    );

    $runCompleted = $run->fresh();

    // Record acceptance receipt: must append a FAILED receipt
    $receiptResult = app(RecordTestKitAcceptanceReceipt::class)->handle(
        actor: $this->actor,
        version: $this->fixture['version'],
        run: $runCompleted,
        expectedRunRevision: (int) $runCompleted->revision,
    );

    expect($receiptResult['result'])->toBe(TestKitAcceptanceReceipt::RESULT_FAILED)
        ->and($receiptResult['check_report_json']['case_results']['incorrect']['passed'])->toBeFalse();

    // Selecting a failed receipt MUST be rejected by SelectTestKitAcceptanceReceipt
    $selector = app(SelectTestKitAcceptanceReceipt::class);
    $version = $this->fixture['version']->fresh();

    expect(fn () => $selector->handle(
        actor: $this->actor,
        testKitId: (int) $this->fixture['kit']->id,
        versionId: (int) $version->id,
        receiptIdOrInput: (int) $receiptResult['id'],
        expectedRevision: (int) $version->revision,
        idempotencyKey: (string) Str::uuid(),
    ))->toThrow(ValidationException::class);
});

test('exact receipt replay returns original receipt without duplicate and changed payload conflicts', function (): void {
    $prepared = testKitDiagnosticEvidenceFixture(
        $this->fixture,
        $this->actor,
        $this->institution,
    );
    $run = $prepared['run'];

    app(CompareOmrDiagnosticRun::class)->handle(
        run: $run,
        actor: $this->actor,
        expectedRevision: (int) $run->revision,
        idempotencyKey: (string) Str::uuid(),
    );
    app(CompleteOmrDiagnosticRun::class)->handle(
        run: $run->fresh(),
        actor: $this->actor,
        expectedRevision: (int) $run->fresh()->revision,
        idempotencyKey: (string) Str::uuid(),
    );

    $runCompleted = $run->fresh();

    // First call: appends receipt
    $first = app(RecordTestKitAcceptanceReceipt::class)->handle(
        actor: $this->actor,
        version: $this->fixture['version'],
        run: $runCompleted,
        expectedRunRevision: (int) $runCompleted->revision,
    );

    // Exact replay: returns existing receipt
    $second = app(RecordTestKitAcceptanceReceipt::class)->handle(
        actor: $this->actor,
        version: $this->fixture['version'],
        run: $runCompleted,
        expectedRunRevision: (int) $runCompleted->revision,
    );

    expect($second['id'])->toBe($first['id'])
        ->and(TestKitAcceptanceReceipt::query()->where('diagnostic_run_id', $runCompleted->id)->count())->toBe(1);

    // Conflicting payload: changing protocol hash throws conflict
    expect(fn () => app(RecordTestKitAcceptanceReceipt::class)->handle(
        actor: $this->actor,
        version: $this->fixture['version'],
        run: $runCompleted,
        expectedRunRevision: (int) $runCompleted->revision,
        protocolSha256: str_repeat('f', 64),
    ))->toThrow(RuntimeException::class);
});

test('page retake advances run revision and invalidates prior proof selection while preserving immutable history', function (): void {
    $prepared = testKitDiagnosticEvidenceFixture(
        $this->fixture,
        $this->actor,
        $this->institution,
    );
    $run = $prepared['run'];
    $set = $prepared['set'];
    // 1. Complete run and record valid receipt at initial revision
    app(CompareOmrDiagnosticRun::class)->handle(
        run: $run,
        actor: $this->actor,
        expectedRevision: (int) $run->revision,
        idempotencyKey: (string) Str::uuid(),
    );
    app(CompleteOmrDiagnosticRun::class)->handle(
        run: $run->fresh(),
        actor: $this->actor,
        expectedRevision: (int) $run->fresh()->revision,
        idempotencyKey: (string) Str::uuid(),
    );
    $runCompleted = $run->fresh();
    $initialRevision = (int) $runCompleted->revision;

    $receiptResult = app(RecordTestKitAcceptanceReceipt::class)->handle(
        actor: $this->actor,
        version: $this->fixture['version'],
        run: $runCompleted,
        expectedRunRevision: $initialRevision,
    );

    // 2. Sealed run cannot be retaken: throws RuntimeException
    expect(fn () => app(RetakeOmrDiagnosticPage::class)->handle(
        set: $set,
        actor: $this->actor,
        expectedRunRevision: $initialRevision,
        expectedSetRevision: 1,
        bytes: (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='),
        pageNo: 1,
        clientCaptureId: (string) Str::uuid(),
        mime: 'image/png',
        replacementReason: 'Post-seal invalid retake attempt',
        caseId: 'correct',
    ))->toThrow(RuntimeException::class);

    // 3. Create a second active run to verify that retake advances revision on an unsealed run
    $preparedActive = testKitDiagnosticEvidenceFixture(
        $this->fixture,
        $this->actor,
        $this->institution,
    );
    $activeRun = $preparedActive['run'];
    $activeSet = $preparedActive['set'];
    $activeInitialRevision = (int) $activeRun->revision;

    $retakeResult = app(RetakeOmrDiagnosticPage::class)->handle(
        set: $activeSet,
        actor: $this->actor,
        expectedRunRevision: $activeInitialRevision,
        expectedSetRevision: 1,
        bytes: (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='),
        pageNo: 1,
        clientCaptureId: (string) Str::uuid(),
        mime: 'image/png',
        replacementReason: 'Blurry capture replacement',
        caseId: 'correct',
    );
    $activeRunAfterRetake = $activeRun->fresh();
    expect((int) $activeRunAfterRetake->revision)->toBeGreaterThan($activeInitialRevision);

    // 4. Stale receipt selection fails when run revision has advanced beyond the receipt's revision
    $receiptModel = TestKitAcceptanceReceipt::query()->findOrFail((int) $receiptResult['id']);
    $staleAttributes = $receiptModel->getAttributes();
    unset($staleAttributes['id'], $staleAttributes['public_id'], $staleAttributes['created_at'], $staleAttributes['updated_at']);
    $staleAttributes['public_id'] = (string) Str::uuid();
    $staleAttributes['run_revision'] = $initialRevision - 1;
    $staleReceipt = TestKitAcceptanceReceipt::query()->create($staleAttributes);
    $selector = app(SelectTestKitAcceptanceReceipt::class);
    $version = $this->fixture['version']->fresh();

    expect(fn () => $selector->handle(
        actor: $this->actor,
        testKitId: (int) $this->fixture['kit']->id,
        versionId: (int) $version->id,
        receiptIdOrInput: (int) $staleReceipt->id,
        expectedRevision: (int) $version->revision,
        idempotencyKey: (string) Str::uuid(),
    ))->toThrow(ValidationException::class);

    // 5. History is preserved: both receipts still exist in the database
    expect(TestKitAcceptanceReceipt::query()->whereKey((int) $receiptResult['id'])->exists())->toBeTrue();
    expect(TestKitAcceptanceReceipt::query()->whereKey((int) $staleReceipt->id)->exists())->toBeTrue();
});

test('protocol-only upgrade and reacceptance restores eligibility under stable kit manifest without successor kit', function (): void {
    $selector = app(SelectTestKitAcceptanceReceipt::class);
    $version = $this->fixture['version'];
    $originalManifest = (string) $version->manifest_sha256;

    $recordPassingReceipt = function () use ($version): array {
        $prepared = testKitDiagnosticEvidenceFixture(
            $this->fixture,
            $this->actor,
            $this->institution,
        );
        $run = $prepared['run'];
        app(CompareOmrDiagnosticRun::class)->handle(
            run: $run,
            actor: $this->actor,
            expectedRevision: (int) $run->revision,
            idempotencyKey: (string) Str::uuid(),
        );
        app(CompleteOmrDiagnosticRun::class)->handle(
            run: $run->fresh(),
            actor: $this->actor,
            expectedRevision: (int) $run->fresh()->revision,
            idempotencyKey: (string) Str::uuid(),
        );

        return app(RecordTestKitAcceptanceReceipt::class)->handle(
            actor: $this->actor,
            version: $version,
            run: $run->fresh(),
            expectedRunRevision: (int) $run->fresh()->revision,
        );
    };

    $receiptResult = $recordPassingReceipt();
    $selector->handle(
        actor: $this->actor,
        testKitId: (int) $this->fixture['kit']->id,
        versionId: (int) $version->id,
        receiptIdOrInput: (int) $receiptResult['id'],
        expectedRevision: (int) $version->fresh()->revision,
        idempotencyKey: (string) Str::uuid(),
    );
    expect($version->fresh()->selected_acceptance_receipt_id)->toBe((int) $receiptResult['id']);
    // Simulate protocol upgrade: canonical protocol identity updates to v2
    TestKitAcceptancePlan::$protocolOverride = [
        'schema_version' => 'test-kit-acceptance-protocol-v2',
        'sha256' => hash('sha256', 'test-kit-acceptance-protocol-v2:upgraded'),
    ];

    // Old receipt recorded under prior protocol remains immutable but cannot be selected under upgraded protocol
    expect(fn () => $selector->handle(
        actor: $this->actor,
        testKitId: (int) $this->fixture['kit']->id,
        versionId: (int) $version->id,
        receiptIdOrInput: (int) $receiptResult['id'],
        expectedRevision: (int) $version->fresh()->revision,
        idempotencyKey: (string) Str::uuid(),
    ))->toThrow(ValidationException::class);

    expect(TestKitAcceptanceReceipt::query()->whereKey((int) $receiptResult['id'])->exists())->toBeTrue()
        ->and($version->fresh()->manifest_sha256)->toBe($originalManifest);

    // Restore canonical protocol for reacceptance
    TestKitAcceptancePlan::$protocolOverride = null;
    $newReceipt = $recordPassingReceipt();
    $selector->handle(
        actor: $this->actor,
        testKitId: (int) $this->fixture['kit']->id,
        versionId: (int) $version->id,
        receiptIdOrInput: (int) $newReceipt['id'],
        expectedRevision: (int) $version->fresh()->revision,
        idempotencyKey: (string) Str::uuid(),
    );
    expect($version->fresh()->selected_acceptance_receipt_id)->toBe((int) $newReceipt['id'])
        ->and($version->fresh()->manifest_sha256)->toBe($originalManifest)
        ->and(TestKitAcceptanceReceipt::query()->whereKey((int) $receiptResult['id'])->exists())->toBeTrue()
        ->and(TestKitAcceptanceReceipt::query()->whereKey((int) $newReceipt['id'])->exists())->toBeTrue();
});

test('worker engine currency invalidates old proof and same-kit acceptance renews against the configured worker', function (): void {
    $selector = app(SelectTestKitAcceptanceReceipt::class);
    $status = app(TestKitCurrentAcceptanceStatus::class);
    $version = $this->fixture['version'];
    $manifest = (string) $version->manifest_sha256;
    $oldEngineCommit = (string) config('omr.omrchecker.engine_commit');
    $old = testKitAcceptedSoftwareFixture($this->fixture, $this->actor, $this->institution);

    expect($status->forReceipt($version->fresh(), $old['receipt'])['current'])->toBeTrue();

    $currentEngineCommit = str_repeat('c', 40);
    config(['omr.omrchecker.engine_commit' => $currentEngineCommit]);
    $replayedReceipt = app(RecordTestKitAcceptanceReceipt::class)->handle(
        actor: $this->actor,
        version: $version,
        run: $old['run'],
        expectedRunRevision: (int) $old['run']->revision,
    );

    expect((int) $replayedReceipt['id'])->toBe((int) $old['receipt']->id)
        ->and($replayedReceipt['result'])->toBe($old['receipt']->result)
        ->and($status->forReceipt($version->fresh(), $old['receipt'])['current'])->toBeFalse();

    expect($status->forReceipt($version->fresh(), $old['receipt'])['current'])->toBeFalse()
        ->and($old['receipt']->fresh()->worker_commit)->toBe($oldEngineCommit)
        ->and($version->fresh()->manifest_sha256)->toBe($manifest);

    $renewed = testKitAcceptedSoftwareFixture($this->fixture, $this->actor, $this->institution);
    $plan = $renewed['run']->acceptance_plan_json;

    expect($plan['expected_worker_identity']['engine_commit'])->toBe($currentEngineCommit)
        ->and($renewed['run']->worker_commit)->toBe($currentEngineCommit)
        ->and($renewed['receipt']->worker_commit)->toBe($currentEngineCommit)
        ->and($status->forReceipt($version->fresh(), $renewed['receipt'])['current'])->toBeTrue()
        ->and($version->fresh()->selected_acceptance_receipt_id)->toBe((int) $renewed['receipt']->id)
        ->and($version->fresh()->manifest_sha256)->toBe($manifest)
        ->and(TestKitAcceptanceReceipt::query()->whereKey((int) $old['receipt']->id)->exists())->toBeTrue();

    $wrongWorkerRun = $renewed['run']->fresh();
    $wrongWorkerRun->worker_commit = $oldEngineCommit;
    expect($status->workerIdentityMatchesRun($wrongWorkerRun, $plan))->toBeFalse();
});

test('diagnostic acceptance runs and receipts remain completely isolated from production sessions and claims', function (): void {
    $prepared = testKitDiagnosticEvidenceFixture(
        $this->fixture,
        $this->actor,
        $this->institution,
    );
    $run = $prepared['run'];
    $beforeAssignments = DB::table('exam_assignments')->count();
    $beforeAdministrations = DB::table('assessment_administrations')->count();

    expect(fn () => app(ConfirmOmrCaptureBatch::class)->handle($run, $this->actor))
        ->toThrow(TypeError::class);

    $diagnosticRunId = (int) $run->id;
    app(CompareOmrDiagnosticRun::class)->handle(
        run: $run,
        actor: $this->actor,
        expectedRevision: (int) $run->revision,
        idempotencyKey: (string) Str::uuid(),
    );
    app(CompleteOmrDiagnosticRun::class)->handle(
        run: $run->fresh(),
        actor: $this->actor,
        expectedRevision: (int) $run->fresh()->revision,
        idempotencyKey: (string) Str::uuid(),
    );
    $receipt = app(RecordTestKitAcceptanceReceipt::class)->handle(
        actor: $this->actor,
        version: $this->fixture['version'],
        run: $run->fresh(),
        expectedRunRevision: (int) $run->fresh()->revision,
    );

    expect((int) TestKitAcceptanceReceipt::query()->findOrFail((int) $receipt['id'])->diagnostic_run_id)
        ->toBe($diagnosticRunId)
        ->and(DB::table('exam_assignments')->count())->toBe($beforeAssignments)
        ->and(DB::table('assessment_administrations')->count())->toBe($beforeAdministrations);
});

test('opaque swapped saved composition survives paired preview and reviewed acceptance evidence', function (): void {
    $fixture = testKitAcceptanceFixture($this->actor, $this->institution, scenario: 'opaque-swapped');
    $resolved = app(KitCompositionContext::class)->resolveVersion($fixture['version']);
    $validatedKeys = app(KitCompositionContext::class)->readValidatedKeys($this->actor, $resolved);
    $preview = app(KitPairedAnswerKeyPreviewQuery::class)->forKit(
        $fixture['sheet'],
        $resolved['rows'],
        array_map(static fn (array $key): array => [
            'physical_item_uid' => $key['physical_item_uid'],
            'correct_option_code' => $key['correct_option_code'],
        ], $validatedKeys),
        [
            'map_complete' => true,
            'preparation_revision' => (int) $fixture['preparation']->revision,
            'composition_revision' => (int) $fixture['preparation']->composition_revision,
            'component_vector' => $resolved['component_vector'],
        ],
        static fn (int $pageNo): string => '/synthetic-opaque-preview/'.$pageNo,
    );
    $itemsByLocalNumber = $fixture['profile']->load('subtests.items')->subtests->firstOrFail()->items->keyBy('local_number');
    $physicalUidByLabel = collect($resolved['physical_inventory']['items'])->keyBy('native_label');
    $rowByPhysicalUid = collect($resolved['rows'])->keyBy('physical_item_uid');
    $intrinsicBookletItemIds = $fixture['set']->items()->orderBy('global_position')->pluck('booklet_item_id')->all();

    expect($preview['state'])->toBe('ready')
        ->and($fixture['profile']->subtests->firstOrFail()->item_order_policy)->toBe('within_subtest_permutable')
        ->and($physicalUidByLabel->get('LOGIC_Q_2')['global_position'])->toBe(1)
        ->and($physicalUidByLabel->get('LOGIC_Q_1')['global_position'])->toBe(2)
        ->and($rowByPhysicalUid->get($physicalUidByLabel->get('LOGIC_Q_2')['physical_item_uid'])['formal_item_id'])
        ->toBe((int) $itemsByLocalNumber->get('2')->id)
        ->and($rowByPhysicalUid->get($physicalUidByLabel->get('LOGIC_Q_1')['physical_item_uid'])['formal_item_id'])
        ->toBe((int) $itemsByLocalNumber->get('1')->id)
        ->and($rowByPhysicalUid->get($physicalUidByLabel->get('LOGIC_Q_2')['physical_item_uid'])['booklet_item_id'])
        ->toBe($intrinsicBookletItemIds[0])
        ->and($rowByPhysicalUid->get($physicalUidByLabel->get('LOGIC_Q_1')['physical_item_uid'])['booklet_item_id'])
        ->toBe($intrinsicBookletItemIds[1])
        ->and(collect($preview['answers'])->keyBy('physical_item_uid')->all())
        ->toMatchArray([
            $physicalUidByLabel->get('LOGIC_Q_2')['physical_item_uid'] => [
                'physical_item_uid' => $physicalUidByLabel->get('LOGIC_Q_2')['physical_item_uid'],
                'correct_option_code' => 'token::cobalt',
            ],
            $physicalUidByLabel->get('LOGIC_Q_1')['physical_item_uid'] => [
                'physical_item_uid' => $physicalUidByLabel->get('LOGIC_Q_1')['physical_item_uid'],
                'correct_option_code' => 'token::amber',
            ],
        ]);

    $accepted = testKitAcceptedSoftwareFixture($fixture, $this->actor, $this->institution);
    $planBindingsByIdentity = collect($accepted['run']->acceptance_plan_json['item_bindings'])
        ->keyBy(static fn (array $binding): string => $binding['page_no'].':'.$binding['native_label']);
    $caseResults = (array) $accepted['receipt']->check_report_json['case_results'];
    $caseIds = array_keys($caseResults);
    sort($caseIds);
    expect($accepted['receipt']->result)->toBe(TestKitAcceptanceReceipt::RESULT_PASSED)
        ->and($accepted['fixture']['acceptance_source_key'])->toBe('two-item-count-opaque-swapped-v1')
        ->and($planBindingsByIdentity->get('1:LOGIC_Q_2')['item_id'])->toBe((int) $itemsByLocalNumber->get('2')->id)
        ->and($planBindingsByIdentity->get('1:LOGIC_Q_1')['item_id'])->toBe((int) $itemsByLocalNumber->get('1')->id)
        ->and($caseIds)->toBe(['blank', 'correct', 'incorrect', 'mixed']);
    foreach (['correct', 'incorrect', 'blank', 'mixed'] as $caseId) {
        expect($caseResults[$caseId]['response_truth_match'])->toBeTrue()
            ->and($caseResults[$caseId]['raw_score_match'])->toBeTrue()
            ->and($caseResults[$caseId]['passed'])->toBeTrue();
    }
});
