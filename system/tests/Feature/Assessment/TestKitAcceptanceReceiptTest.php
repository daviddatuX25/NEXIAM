<?php

declare(strict_types=1);

use App\Assessment\Actions\ActivateTestKitVersion;
use App\Assessment\Actions\AssembleTestKitPreparation;
use App\Assessment\Actions\Omr\Canonical\PrintOmrBlankInstances;
use App\Assessment\Actions\Omr\ConfirmOmrCaptureBatch;
use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Actions\Omr\Operational\CompareOmrDiagnosticRun;
use App\Assessment\Actions\Omr\Operational\CompleteOmrDiagnosticRun;
use App\Assessment\Actions\Omr\ReviewOmrCaptureSheet;
use App\Assessment\Actions\Omr\StartOmrPendingCaptureSet;
use App\Assessment\Actions\RecordTestKitAcceptanceReceipt as RecordReceipt;
use App\Assessment\Omr\Canonical\OmrBlankInstanceService;
use App\Assessment\Omr\Canonical\OmrBlankPrintOrchestrator;
use App\Assessment\Omr\Review\GradingOmrEffectiveAnswerProjector;
use App\Assessment\Support\KitCompositionContext;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Assessment\RenderOmrBlankInstanceBatchJob;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\AssessmentScoringRun;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrPendingCapturePageAnalysis;
use App\Models\Assessment\OmrPendingCapturePageEvidence;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Assessment\OmrQrDisabledIssuance;
use App\Models\Assessment\OmrQrDisabledPageClaim;
use App\Models\Assessment\OmrTemplateQuestion;
use App\Models\Assessment\TestKitAcceptanceReceipt;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Printing\PrintJob;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

require_once __DIR__.'/../../Support/TestKitHelpers.php';
require_once __DIR__.'/../../Support/TestKitSoftwareAcceptanceFixtures.php';
require_once __DIR__.'/../../Support/GradingHelpers.php';

beforeEach(function (): void {
    seedE2Foundation();
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->institution = Institution::current();
});

test('diagnostic start HTTP endpoint rejects client truth and scope fields', function (): void {
    $response = $this->actingAs($this->actor)->postJson(route('setup.exam-materials.omr-test.runs.start'), [
        'test_kit_version_id' => 15,
        'acceptance_plan_id' => 1,
        'expected_revision' => 0,
        'expected_vector' => ['Q1' => 'A'],
        'institution_id' => (int) $this->institution->id,
        'answer_key' => ['Q1' => 'A'],
    ]);
    $response->assertUnprocessable();
});

test('complete reviewed evidence records and selects an authentic passing receipt', function (): void {
    $fixture = testKitAcceptanceFixture($this->actor, $this->institution);
    $accepted = testKitAcceptedSoftwareFixture($fixture, $this->actor, $this->institution);
    expect($accepted['receipt']->result)->toBe(TestKitAcceptanceReceipt::RESULT_PASSED)
        ->and($accepted['run']->fresh()->status)->toBe(OmrDiagnosticRun::STATUS_COMPLETED)
        ->and($accepted['fixture']['version']->fresh()->selected_acceptance_receipt_id)->toBe($accepted['receipt']->id);
});

test('incomplete frozen case set cannot complete or produce a receipt', function (): void {
    $fixture = testKitAcceptanceFixture($this->actor, $this->institution);
    $prepared = testKitDiagnosticEvidenceFixture($fixture, $this->actor, $this->institution, ['correct', 'incorrect', 'blank']);
    $run = $prepared['run'];
    app(CompareOmrDiagnosticRun::class)->handle(run: $run, actor: $this->actor, expectedRevision: (int) $run->revision, idempotencyKey: (string) Str::uuid());
    $run = $run->fresh();

    expect(fn () => app(CompleteOmrDiagnosticRun::class)->handle(
        run: $run,
        actor: $this->actor,
        expectedRevision: (int) $run->revision,
        idempotencyKey: (string) Str::uuid(),
    ))->toThrow(RuntimeException::class, 'omr_diagnostic_run_evidence_incomplete')
        ->and($run->fresh()->status)->not->toBe(OmrDiagnosticRun::STATUS_COMPLETED)
        ->and(TestKitAcceptanceReceipt::query()->where('diagnostic_run_id', $run->id)->exists())->toBeFalse()
        ->and($fixture['version']->fresh()->selected_acceptance_receipt_id)->toBeNull();
});

test('a protocol mismatch on genuine completed evidence records immutable failure without selection', function (): void {
    $fixture = testKitAcceptanceFixture($this->actor, $this->institution);
    $prepared = testKitDiagnosticEvidenceFixture($fixture, $this->actor, $this->institution);
    $run = $prepared['run'];
    app(CompareOmrDiagnosticRun::class)->handle(run: $run, actor: $this->actor, expectedRevision: (int) $run->revision, idempotencyKey: (string) Str::uuid());
    $run = $run->fresh();
    app(CompleteOmrDiagnosticRun::class)->handle(run: $run, actor: $this->actor, expectedRevision: (int) $run->revision, idempotencyKey: (string) Str::uuid());
    $run = $run->fresh();
    $result = app(RecordReceipt::class)->handle(actor: $this->actor, version: $fixture['version'], run: $run, expectedRunRevision: (int) $run->revision, protocolSchemaVersion: 'unsupported-test-protocol');
    $receipt = TestKitAcceptanceReceipt::query()->findOrFail($result['id']);
    expect($receipt->result)->toBe(TestKitAcceptanceReceipt::RESULT_FAILED)
        ->and($receipt->check_report_json['checks'])->toContain(['code' => 'protocol_schema_current', 'passed' => false])
        ->and($fixture['version']->fresh()->selected_acceptance_receipt_id)->toBeNull()
        ->and(fn () => $receipt->delete())->toThrow(RuntimeException::class);
});

test('a caller-supplied plan digest mismatch cannot certify genuine completed evidence', function (): void {
    $fixture = testKitAcceptanceFixture($this->actor, $this->institution);
    $prepared = testKitDiagnosticEvidenceFixture($fixture, $this->actor, $this->institution);
    $run = $prepared['run'];
    app(CompareOmrDiagnosticRun::class)->handle(run: $run, actor: $this->actor, expectedRevision: (int) $run->revision, idempotencyKey: (string) Str::uuid());
    $run = $run->fresh();
    app(CompleteOmrDiagnosticRun::class)->handle(run: $run, actor: $this->actor, expectedRevision: (int) $run->revision, idempotencyKey: (string) Str::uuid());
    $run = $run->fresh();
    $result = app(RecordReceipt::class)->handle(actor: $this->actor, version: $fixture['version'], run: $run, expectedRunRevision: (int) $run->revision, planSha256: str_repeat('0', 64));
    $receipt = TestKitAcceptanceReceipt::query()->findOrFail($result['id']);
    expect($receipt->result)->toBe(TestKitAcceptanceReceipt::RESULT_FAILED)
        ->and($receipt->check_report_json['checks'])->toContain(['code' => 'plan_schema_current', 'passed' => false]);
});

test('a genuine incorrect case fails independent response and raw scoring checks', function (): void {
    $fixture = testKitAcceptanceFixture($this->actor, $this->institution);
    $prepared = testKitDiagnosticEvidenceFixture($fixture, $this->actor, $this->institution, caseResponseOverrides: ['incorrect' => ['LOGIC_Q_1' => 'A']]);
    $run = $prepared['run'];
    app(CompareOmrDiagnosticRun::class)->handle(run: $run, actor: $this->actor, expectedRevision: (int) $run->revision, idempotencyKey: (string) Str::uuid());
    $run = $run->fresh();
    app(CompleteOmrDiagnosticRun::class)->handle(run: $run, actor: $this->actor, expectedRevision: (int) $run->revision, idempotencyKey: (string) Str::uuid());
    $run = $run->fresh();
    $result = app(RecordReceipt::class)->handle(actor: $this->actor, version: $fixture['version'], run: $run, expectedRunRevision: (int) $run->revision);
    $receipt = TestKitAcceptanceReceipt::query()->findOrFail($result['id']);
    expect($receipt->result)->toBe(TestKitAcceptanceReceipt::RESULT_FAILED)
        ->and($receipt->check_report_json['checks'])->toContain(['code' => 'independent_response_truth_match', 'passed' => false])
        ->and($receipt->check_report_json['checks'])->toContain(['code' => 'independent_raw_score_match', 'passed' => false]);
});

test('exact receipt replay returns original and changed caller identity conflicts without overwriting', function (): void {
    $fixture = testKitAcceptanceFixture($this->actor, $this->institution);
    $accepted = testKitAcceptedSoftwareFixture($fixture, $this->actor, $this->institution);
    $version = $accepted['fixture']['version'];
    $run = $accepted['run'];
    $first = app(RecordReceipt::class)->handle(actor: $this->actor, version: $version, run: $run, expectedRunRevision: (int) $run->revision);
    expect($first['id'])->toBe($accepted['receipt']->id)
        ->and(fn () => app(RecordReceipt::class)->handle(actor: $this->actor, version: $version, run: $run, expectedRunRevision: (int) $run->revision, protocolSha256: str_repeat('a', 64)))->toThrow(RuntimeException::class, 'test_kit_acceptance_receipt_conflict')
        ->and(TestKitAcceptanceReceipt::query()->where('diagnostic_run_id', $run->id)->count())->toBe(1);
    expect(fn () => $accepted['receipt']->delete())->toThrow(RuntimeException::class);
});

test('opaque saved map drives exact production observations and raw scoring while activation holds stay explicit', function (): void {
    $fixture = testKitAcceptanceFixture(
        $this->actor,
        $this->institution,
        assemble: false,
        scenario: 'opaque-swapped',
    );
    $preparation = $fixture['preparation']->fresh();
    $fixture['version'] = app(AssembleTestKitPreparation::class)->handle(
        $this->actor,
        (int) $fixture['kit']->id,
        [
            'preparation_id' => (int) $preparation->id,
            'expected_revision' => (int) $preparation->revision,
            'idempotency_key' => (string) Str::uuid(),
        ],
    )->fresh(['templateVersion', 'bookletVersion', 'bookletSet', 'testInstrumentVersion']);
    $fixture['sheet'] = $fixture['version']->templateVersion;
    $accepted = testKitAcceptedSoftwareFixture($fixture, $this->actor, $this->institution);
    $version = $accepted['fixture']['version'];

    try {
        app(ActivateTestKitVersion::class)->handle(
            $this->actor,
            (int) $accepted['fixture']['kit']->id,
            (int) $version->id,
            ['expected_revision' => (int) $version->revision, 'idempotency_key' => (string) Str::uuid()],
        );
        test()->fail('Synthetic source evidence must not pass institutional activation.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['eligibility'])
            ->toContain('Lawful institutional source, rights, and independent expected-truth evidence are not currently verifiable.');
    }
    expect($version->fresh()->status)->toBe(TestKitVersion::STATUS_CANDIDATE);

    // Prior-generation Test Kit lifecycle metadata is only for frozen capture;
    // it is not an activation or physical-qualification claim.
    $historicalVersion = $version->fresh()->forceFill([
        'status' => TestKitVersion::STATUS_ACTIVE,
        'activated_at' => now()->subDay(),
        'activated_by_user_id' => $this->actor->id,
        'revision' => (int) $version->revision + 1,
    ]);
    $historicalVersion->save();
    $accepted['fixture']['version'] = $historicalVersion->fresh([
        'templateVersion', 'bookletVersion', 'bookletSet', 'testInstrumentVersion',
    ]);
    $historical = buildPaperGradingFixtureInternal(
        $this,
        1,
        true,
        requireCurrentOmrProof: true,
        kitFixture: $accepted,
    );
    $attempt = $historical['attempts'][0];
    $assignment = $attempt->examAssignments()->firstOrFail();
    $administration = $historical['historicalAdministrationsByAssignment'][$assignment->id];
    $area = AptitudeArea::query()->where('code', 'LOGIC')->firstOrFail();
    $productionComposition = app(KitCompositionContext::class)->resolveVersion($historical['kitVersion']);
    $itemMap = $productionComposition['item_map'];
    $rowsByPageAndLabel = [];
    foreach ($itemMap['composition_rows'] as $row) {
        if (($row['disposition'] ?? null) !== 'physical') {
            continue;
        }
        $pageNo = (int) ($row['page_no'] ?? 0);
        $nativeLabel = $row['native_label'] ?? null;
        if ($pageNo < 1 || ! is_string($nativeLabel) || $nativeLabel === '') {
            throw new RuntimeException('The production composition has an incomplete exact physical identity.');
        }
        $identity = $pageNo.':'.$nativeLabel;
        if (isset($rowsByPageAndLabel[$identity])) {
            throw new RuntimeException('The production composition has an ambiguous page/native-label identity.');
        }
        $rowsByPageAndLabel[$identity] = $row;
    }
    $bindingsByPageAndLabel = [];
    foreach ($itemMap['bindings'] as $binding) {
        if (! is_array($binding)) {
            throw new RuntimeException('The production Test Kit item map contains an invalid binding.');
        }
        $pageNo = (int) ($binding['page_no'] ?? 0);
        $nativeLabel = $binding['native_label'] ?? null;
        if ($pageNo < 1 || ! is_string($nativeLabel) || $nativeLabel === '') {
            throw new RuntimeException('The production Test Kit binding has an incomplete page/native-label identity.');
        }
        $identity = $pageNo.':'.$nativeLabel;
        if (isset($bindingsByPageAndLabel[$identity])) {
            throw new RuntimeException('The production Test Kit item map has an ambiguous page/native-label binding.');
        }
        $bindingsByPageAndLabel[$identity] = $binding;
    }
    $savedRowsByPhysicalUid = collect($historical['compositionRows'])->keyBy('physical_item_uid');
    $literalObservations = [
        'LOGIC_Q_2' => 'token::cobalt',
        'LOGIC_Q_1' => 'token::amber',
    ];
    $proposalItems = [];

    foreach ($literalObservations as $nativeLabel => $observedToken) {
        $identity = '1:'.$nativeLabel;
        $row = $rowsByPageAndLabel[$identity] ?? null;
        $binding = $bindingsByPageAndLabel[$identity] ?? null;
        if (! is_array($row) || ! is_array($binding)) {
            throw new RuntimeException('A literal production observation has no exact page/native-label composition binding.');
        }
        $physicalUid = (string) ($row['physical_item_uid'] ?? '');
        $savedRow = $savedRowsByPhysicalUid->get($physicalUid);
        $optionTokens = array_values((array) ($binding['option_tokens'] ?? []));
        $physicalTokens = array_values((array) ($row['physical_option_tokens'] ?? []));
        if ($physicalUid === '' || $savedRow === null
            || (int) $binding['global_position'] !== (int) $row['global_position']
            || (int) $binding['sheet_item_id'] !== (int) $row['sheet_item_id']
            || (int) $binding['item_id'] !== (int) $row['formal_item_id']
            || (int) $binding['subtest_id'] !== (int) $row['formal_subtest_id']
            || $optionTokens === [] || $optionTokens !== $physicalTokens
            || ! in_array($observedToken, $optionTokens, true)
            || (int) $savedRow['test_instrument_item_id'] !== (int) $row['formal_item_id']
            || (int) $savedRow['booklet_set_item_id'] !== (int) $row['booklet_set_item_id']) {
            throw new RuntimeException('The production binding differs from the exact saved UID/formal/Set association.');
        }
        $physicalQuestion = OmrTemplateQuestion::query()
            ->where('template_version_id', $historical['sheet']->id)
            ->where('physical_item_uid', $physicalUid)
            ->firstOrFail();
        if ((string) $physicalQuestion->native_label !== $nativeLabel
            || (int) $physicalQuestion->page_no !== (int) $binding['page_no']
            || (int) $physicalQuestion->global_position !== (int) $binding['global_position']) {
            throw new RuntimeException('The production capture location differs from its exact compiled physical identity.');
        }
        $proposalItems[] = [
            'position' => (int) $binding['global_position'],
            'area_code' => $area->code,
            'area_id' => (int) $area->id,
            'question_no' => (int) $physicalQuestion->question_no,
            'options' => $optionTokens,
            'evidence_hash' => hash('sha256', 'opaque-production-observation:'.$nativeLabel),
            'machine' => [
                'state' => 'marked',
                'selected_option' => $observedToken,
                'observed_options' => [$observedToken],
                'option_observations' => [],
                'confidence' => 0.99,
                'issue_codes' => [],
            ],
        ];
    }

    // Synthetic prior-generation custody records use the same QR-disabled claim
    // contract as the historical evidence reader; they are not physical proof.
    $templateVersion = $historical['sheet'];
    $printOperation = app(PrintOmrBlankInstances::class)->handle(
        $templateVersion,
        $historical['guidance'],
        1,
        'opaque-saved-map-print-'.Str::uuid(),
    );
    $printPayload = is_array($printOperation->payload) ? $printOperation->payload : [];
    $printJobId = (int) ($printPayload['print_job_id'] ?? 0);
    if ($printJobId < 1) {
        throw new RuntimeException('The exact synthetic QR-disabled print operation has no persisted Print Job identity.');
    }
    $printJob = PrintJob::query()->findOrFail($printJobId);
    (new RenderOmrBlankInstanceBatchJob($printJobId, (int) $historical['guidance']->id))->handle(
        app(OmrBlankInstanceService::class),
        app(OmrBlankPrintOrchestrator::class),
        app(RecordAuditEvent::class),
    );
    $issuance = OmrQrDisabledIssuance::query()
        ->where('print_job_id', $printJob->id)
        ->where('template_version_id', $templateVersion->id)
        ->firstOrFail();
    $batch = app(CreateOmrCaptureBatch::class)->handle(
        $historical['grading'],
        $historical['guidance'],
        'opaque-saved-map-production-'.Str::uuid(),
    );
    $captureSet = app(StartOmrPendingCaptureSet::class)->forProduction(
        $historical['grading'],
        $batch,
        $issuance,
        $historical['guidance'],
        (string) Str::uuid(),
    );
    $expectedPages = (array) $captureSet->expected_pages_json;
    if (count($expectedPages) !== (int) $captureSet->page_count || $expectedPages === []) {
        throw new RuntimeException('The production capture set has no exact immutable page list.');
    }
    $sheet = $batch->sheets()->create([
        'admission_attempt_id' => null,
        'booklet_set_id' => (int) $historical['bookletSet']->id,
        'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
        'expected_page_count' => (int) $captureSet->page_count,
        'capture_mode' => OmrCaptureSheet::MODE_MANUAL,
        'version' => 0,
        'client_capture_id' => (string) $captureSet->public_id,
        'detected_applicant_number' => $attempt->reference_code,
        'asset_state' => OmrCaptureSheet::ASSET_LIVE,
        'captured_by_user_id' => (int) $historical['guidance']->id,
        'captured_at' => now(),
        'proposal_json' => ['items' => $proposalItems],
    ]);
    $captureSet->update([
        'status' => OmrPendingCaptureSet::STATUS_READY,
        'revision' => (int) $captureSet->revision + 1,
    ]);
    $captureSet->update([
        'adopted_sheet_id' => (int) $sheet->id,
        'revision' => (int) $captureSet->revision + 1,
    ]);
    foreach ($expectedPages as $expectedPage) {
        $pageNo = (int) ($expectedPage['page_no'] ?? 0);
        if ($pageNo < 1) {
            throw new RuntimeException('The printed QR-disabled page identity is invalid.');
        }
        $inputHash = hash('sha256', 'opaque-synthetic-capture:'.$captureSet->public_id.':page:'.$pageNo);
        $assetPath = 'omr/evidence/'.$captureSet->public_id.'_page_'.$pageNo.'.png';
        $evidence = OmrPendingCapturePageEvidence::query()->create([
            'set_id' => (int) $captureSet->id,
            'page_no' => $pageNo,
            'client_capture_id' => (string) Str::uuid(),
            'input_hash' => $inputHash,
            'asset_path' => $assetPath,
            'mime_type' => 'image/png',
            'byte_count' => 1,
            'captured_by_user_id' => (int) $historical['guidance']->id,
        ]);
        OmrPendingCapturePageAnalysis::query()->create([
            'evidence_id' => (int) $evidence->id,
            'detected_applicant_number' => $attempt->reference_code,
            'evidence_json' => ['input_hash' => $inputHash, 'page_no' => $pageNo],
        ]);
        $capturePage = $sheet->pages()->create([
            'page_no' => $pageNo,
            'status' => OmrCaptureSheetPage::STATUS_SUCCEEDED,
            'asset_path' => $assetPath,
            'proposal_json' => ['input_hash' => $inputHash, 'page_no' => $pageNo],
        ]);
        OmrQrDisabledPageClaim::query()->create([
            'issuance_id' => (int) $issuance->id,
            'set_id' => (int) $captureSet->id,
            'evidence_id' => (int) $evidence->id,
            'capture_sheet_page_id' => (int) $capturePage->id,
            'page_no' => $pageNo,
            'input_hash' => $inputHash,
            'created_by_user_id' => (int) $historical['guidance']->id,
        ]);
    }
    app(ReviewOmrCaptureSheet::class)->handle($sheet, $historical['guidance'], null, (int) $attempt->id);
    $projection = app(GradingOmrEffectiveAnswerProjector::class)->project($sheet->fresh(), $productionComposition);
    $projectedByLabel = collect($projection['items'])->keyBy('native_label');
    expect($projection['has_unresolved'])->toBeFalse();
    foreach ($literalObservations as $nativeLabel => $observedToken) {
        $identity = '1:'.$nativeLabel;
        $row = $rowsByPageAndLabel[$identity];
        $savedRow = $savedRowsByPhysicalUid->get((string) $row['physical_item_uid']);
        expect($projectedByLabel->get($nativeLabel)['physical_item_uid'])->toBe($row['physical_item_uid'])
            ->and((int) $projectedByLabel->get($nativeLabel)['test_instrument_item_id'])
            ->toBe((int) $savedRow['test_instrument_item_id'])
            ->and($projectedByLabel->get($nativeLabel)['effective_option'])->toBe($observedToken);
    }
    $confirmed = app(ConfirmOmrCaptureBatch::class)->handle($batch, $historical['guidance']);
    $raw = AssessmentRawResultRevision::query()
        ->where('administration_id', $administration->id)
        ->where('metric_code', 'RAW_COUNT')
        ->where('is_current', true)
        ->firstOrFail();
    $scoringRun = AssessmentScoringRun::query()->findOrFail((int) $raw->scoring_run_id);
    expect($accepted['fixture']['version']->status)->toBe(TestKitVersion::STATUS_ACTIVE)
        ->and($confirmed->status)->toBe(OmrCaptureBatch::STATUS_CONFIRMED)
        ->and($sheet->fresh()->status)->toBe(OmrCaptureSheet::STATUS_COMMITTED)
        ->and($raw->raw_value)->toBe('2.000000')
        ->and($raw->source)->toBe(AssessmentRawResultRevision::SOURCE_OMR)
        ->and((int) $scoringRun->acceptance_receipt_id)->toBe((int) $accepted['receipt']->id)
        ->and((int) $scoringRun->test_kit_version_id)->toBe((int) $accepted['fixture']['version']->id)
        ->and((int) $administration->fresh()->acceptance_receipt_id)->toBe((int) $accepted['receipt']->id)
        ->and((int) $historical['historicalSnapshot']->acceptance_receipt_id)->toBe((int) $accepted['receipt']->id)
        ->and((int) $administration->fresh()->material_snapshot_id)->toBe((int) $historical['historicalSnapshot']->id);
});
