<?php

use App\Assessment\Actions\Omr\RunOmrSheetAnalysis;
use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Omr\FakeOmrDetection;
use App\Assessment\Omr\OmrDetectionIssue;
use App\Assessment\Omr\OmrDetectionOutcome;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrDetectionResultValidator;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrDetectedItem;
use App\Assessment\Omr\OmrInvalidDetectionOutput;
use App\Assessment\Omr\OmrItemGeometry;
use App\Assessment\Omr\OmrOptionGeometry;
use App\Assessment\Omr\OmrRegion;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrProcessingRun;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\Foundation\AptitudeArea;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** @return list<OmrOptionGeometry> */
function omrTestOptions(): array
{
    return [
        new OmrOptionGeometry('A', new OmrRegion(0.10, 0.20, 0.04, 0.02), 1),
        new OmrOptionGeometry('B', new OmrRegion(0.28, 0.20, 0.04, 0.02), 2),
        new OmrOptionGeometry('C', new OmrRegion(0.46, 0.20, 0.04, 0.02), 3),
        new OmrOptionGeometry('D', new OmrRegion(0.64, 0.20, 0.04, 0.02), 4),
    ];
}

function omrTestRequest(string $inputHash = 'a1b2c3d4'): OmrDetectionRequest
{
    return new OmrDetectionRequest(
        envelopeVersion: OmrDetectionRequest::ENVELOPE_VERSION,
        runId: 'test-run',
        correlationId: 'test-corr',
        imageBytes: str_repeat(chr(0), 64),
        inputHash: $inputHash,
        imageMime: 'image/png',
        imageWidth: null,
        imageHeight: null,
        areas: ['VER' => 2, 'NUM' => 2],
        items: [
            new OmrItemGeometry(1, 'VER', 1, 'VER_Q_001', new OmrRegion(0.08, 0.25, 0.84, 0.01), omrTestOptions()),
            new OmrItemGeometry(2, 'VER', 2, 'VER_Q_002', new OmrRegion(0.08, 0.28, 0.84, 0.01), omrTestOptions()),
            new OmrItemGeometry(3, 'NUM', 1, 'NUM_Q_001', new OmrRegion(0.08, 0.31, 0.84, 0.01), omrTestOptions()),
            new OmrItemGeometry(4, 'NUM', 2, 'NUM_Q_002', new OmrRegion(0.08, 0.34, 0.84, 0.01), omrTestOptions()),
        ],
        identityRegions: ['applicant_number' => new OmrRegion(0.08, 0.08, 0.24, 0.08)],
        nativeBundleHash: str_repeat('a', 64),
        manifestHash: str_repeat('b', 64),
        configHash: str_repeat('c', 64),
        bookletHash: str_repeat('d', 64),
        nativeTemplate: [],
        nativeConfig: [],
        nativeBundleMetadata: [],
        reviewManifest: [],
        engineName: 'nexiam.fake',
        engineCommit: null,
        patchIdentity: 'nexiam-headless-overlay-v1',
        expectedFieldLabels: ['APPLICANT_NUMBER_1', 'VER_Q_001', 'NUM_Q_001'],
        expectedOptionCodes: [
            'APPLICANT_NUMBER_1' => ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            'VER_Q_001' => ['A', 'B', 'C', 'D'],
            'NUM_Q_001' => ['A', 'B', 'C', 'D'],
        ],
        providerDeadline: 30.0,
    );
}

function omrSuccessArray(OmrDetectionSuccess $success): array
{
    return [
        'input_hash' => $success->inputHash,
        'config_hash' => $success->configHash,
        'proposal' => $success->applicantNumberProposal,
        'items' => array_map(fn (OmrDetectedItem $item): array => [
            $item->position,
            $item->areaCode,
            $item->selectedOption,
            $item->state,
            $item->confidence === null ? null : round($item->confidence, 4),
        ], $success->items),
        'issues' => array_map(fn (OmrDetectionIssue $issue): array => [
            $issue->code,
            $issue->severity,
            $issue->areaCode,
            $issue->questionNo,
        ], $success->issues),
    ];
}

function omrCopySuccess(OmrDetectionSuccess $success, array $overrides = []): OmrDetectionSuccess
{
    return new OmrDetectionSuccess(
        engineName: $overrides['engineName'] ?? $success->engineName,
        engineVersion: $overrides['engineVersion'] ?? $success->engineVersion,
        engineCommit: $overrides['engineCommit'] ?? $success->engineCommit,
        inputHash: $overrides['inputHash'] ?? $success->inputHash,
        configHash: $overrides['configHash'] ?? $success->configHash,
        applicantNumberProposal: $overrides['applicantNumberProposal'] ?? $success->applicantNumberProposal,
        applicantNumberConfidence: array_key_exists('applicantNumberConfidence', $overrides) ? $overrides['applicantNumberConfidence'] : $success->applicantNumberConfidence,
        identityFields: $overrides['identityFields'] ?? $success->identityFields,
        items: $overrides['items'] ?? $success->items,
        issues: $overrides['issues'] ?? $success->issues,
        sheetConfidence: array_key_exists('sheetConfidence', $overrides) ? $overrides['sheetConfidence'] : $success->sheetConfidence,
        responseEnvelopeVersion: $overrides['responseEnvelopeVersion'] ?? $success->responseEnvelopeVersion,
        nativeBundleHash: array_key_exists('nativeBundleHash', $overrides) ? $overrides['nativeBundleHash'] : $success->nativeBundleHash,
        manifestHash: array_key_exists('manifestHash', $overrides) ? $overrides['manifestHash'] : $success->manifestHash,
        patchIdentity: array_key_exists('patchIdentity', $overrides) ? $overrides['patchIdentity'] : $success->patchIdentity,
        calibrationStatus: $overrides['calibrationStatus'] ?? $success->calibrationStatus,
        fieldBlockShifts: $overrides['fieldBlockShifts'] ?? $success->fieldBlockShifts,
        alignedImage: array_key_exists('alignedImage', $overrides) ? $overrides['alignedImage'] : $success->alignedImage,
        imageMime: array_key_exists('imageMime', $overrides) ? $overrides['imageMime'] : $success->imageMime,
        imageWidth: array_key_exists('imageWidth', $overrides) ? $overrides['imageWidth'] : $success->imageWidth,
        imageHeight: array_key_exists('imageHeight', $overrides) ? $overrides['imageHeight'] : $success->imageHeight,
    );
}

test('fake provider is deterministic and observation-only under v2', function (): void {
    $request = omrTestRequest();
    $fake = new FakeOmrDetection;
    $first = $fake->detect($request);
    $second = $fake->detect($request);

    expect($first->outcome)->toBe(OmrDetectionOutcome::Succeeded)
        ->and($first->success)->not->toBeNull()
        ->and(omrSuccessArray($first->success))->toBe(omrSuccessArray($second->success))
        ->and(property_exists($request->items[0], 'correctOptionCode'))->toBeFalse()
        ->and($first->success->responseEnvelopeVersion)->toBe(OmrDetectionRequest::ENVELOPE_VERSION)
        ->and($first->success->calibrationStatus)->toBe('synthetic');
});

test('fake provider keys output by stable area codes with geometry', function (): void {
    $result = (new FakeOmrDetection)->detect(omrTestRequest());
    expect($result->success)->not->toBeNull();

    foreach ($result->success->items as $item) {
        expect($item->areaCode)->toBeIn(['VER', 'NUM'])
            ->and($item->state)->toBeIn(OmrDetectedItem::STATES)
            ->and($item->selectedOption)->toBeIn(['A', 'B', 'C', 'D', null])
            ->and($item->confidence)->toBeBetween(0.0, 1.0)
            ->and($item->region->isFiniteAndNormalized())->toBeTrue();
    }
});

test('provider cannot receive a roster identity hint', function (): void {
    config(['omr.fake.applicant_number' => null]);
    $result = (new FakeOmrDetection)->detect(omrTestRequest())->success;

    expect($result->applicantNumberProposal)->toBeNull()
        ->and($result->applicantNumberConfidence)->toBe(0.0);

    $issueCodes = array_map(fn (OmrDetectionIssue $issue) => $issue->code, $result->issues);
    expect($issueCodes)->toContain(OmrDetectionIssue::CODE_IDENTITY_UNRESOLVED);
});

test('validator accepts a valid fake output', function (): void {
    $request = omrTestRequest();
    $result = (new FakeOmrDetection)->detect($request);
    app(OmrDetectionResultValidator::class)->validate($request, $result);
    expect(true)->toBeTrue();
});

test('validator rejects a mismatched input hash', function (): void {
    $request = omrTestRequest();
    $result = (new FakeOmrDetection)->detect($request);
    $tampered = OmrDetectionResult::succeeded(omrCopySuccess($result->success, ['inputHash' => 'tampered']));

    expect(fn () => app(OmrDetectionResultValidator::class)->validate($request, $tampered))
        ->toThrow(OmrInvalidDetectionOutput::class);
});

test('validator rejects a missing item', function (): void {
    $request = omrTestRequest();
    $result = (new FakeOmrDetection)->detect($request);
    $incomplete = OmrDetectionResult::succeeded(omrCopySuccess($result->success, [
        'items' => array_slice($result->success->items, 0, 3),
    ]));

    expect(fn () => app(OmrDetectionResultValidator::class)->validate($request, $incomplete))
        ->toThrow(OmrInvalidDetectionOutput::class);
});

test('validator rejects an unknown issue code', function (): void {
    $request = omrTestRequest();
    $result = (new FakeOmrDetection)->detect($request);
    $badIssue = OmrDetectionResult::succeeded(omrCopySuccess($result->success, [
        'issues' => [new OmrDetectionIssue('made_up_code', 'required', 'VER', 1)],
    ]));

    expect(fn () => app(OmrDetectionResultValidator::class)->validate($request, $badIssue))
        ->toThrow(OmrInvalidDetectionOutput::class);
});

test('validator rejects an out-of-range option selection', function (): void {
    $request = omrTestRequest();
    $result = (new FakeOmrDetection)->detect($request);
    $items = $result->success->items;
    $items[0] = new OmrDetectedItem(
        position: $items[0]->position,
        areaCode: $items[0]->areaCode,
        questionNo: $items[0]->questionNo,
        selectedOption: 'X',
        state: OmrDetectedItem::STATE_MARKED,
        confidence: 0.99,
        region: $items[0]->region,
    );
    $badSelection = OmrDetectionResult::succeeded(omrCopySuccess($result->success, ['items' => $items]));

    expect(fn () => app(OmrDetectionResultValidator::class)->validate($request, $badSelection))
        ->toThrow(OmrInvalidDetectionOutput::class);
});

test('validator rejects confidence from an unqualified provider', function (): void {
    $request = omrTestRequest();
    $result = (new FakeOmrDetection)->detect($request);
    $unqualified = OmrDetectionResult::succeeded(omrCopySuccess($result->success, [
        'calibrationStatus' => 'unqualified',
    ]));

    expect(fn () => app(OmrDetectionResultValidator::class)->validate($request, $unqualified))
        ->toThrow(OmrInvalidDetectionOutput::class);
});

test('validator lets typed failure outcomes through for the caller to handle', function (): void {
    $request = omrTestRequest();
    $failed = OmrDetectionResult::failed(OmrDetectionOutcome::TerminalFailure, 'fake.unexpected_error');
    app(OmrDetectionResultValidator::class)->validate($request, $failed);
    expect(true)->toBeTrue();
});

test('analysis persists a v2 observation proposal with NEXIAM-derived scores', function (): void {
    Storage::fake('sensitive');
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $pair = OmrTemplateBookletPair::query()->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)->firstOrFail();

    $batch = app(CreateOmrCaptureBatch::class)->handle(
        $grading,
        $guidance,
        (int) $pair->template_version_id,
        (int) $pair->booklet_version_id,
        'test-omr-batch-key',
    );

    $assetStore = app(SensitiveAssetStore::class);
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $path = $assetStore->store($bytes, 'omr/test/'.uniqid().'.img');
    $sheet = OmrCaptureSheet::query()->create([
        'batch_id' => $batch->id,
        'status' => OmrCaptureSheet::STATUS_QUEUED,
        'capture_mode' => OmrCaptureSheet::MODE_AUTO,
        'version' => 0,
        'client_capture_id' => 'test-capture-'.uniqid(),
        'asset_path' => $path,
        'asset_hmac' => $assetStore->hmac($bytes),
        'asset_format_version' => 'v1',
        'asset_state' => OmrCaptureSheet::ASSET_LIVE,
        'original_mime' => 'image/png',
        'original_bytes' => strlen($bytes),
        'captured_by_user_id' => $guidance->id,
        'captured_at' => now(),
    ]);

    $run = $sheet->processingRuns()->create([
        'actor_id' => $guidance->id,
        'run_no' => 1,
        'run_uuid' => (string) Illuminate\Support\Str::uuid(),
        'status' => OmrProcessingRun::STATUS_PENDING,
        'provider_name' => 'nexiam.fake',
        'provider_version' => '1',
        'request_version' => OmrDetectionRequest::ENVELOPE_VERSION,
        'expected_sheet_version' => 0,
        'input_hash' => hash('sha256', $bytes),
        'template_hash' => $batch->templateVersion->native_bundle_hash,
        'booklet_hash' => $batch->bookletVersion->answer_key_hash,
        'native_bundle_hash' => $batch->templateVersion->native_bundle_hash,
        'manifest_hash' => $batch->templateVersion->review_manifest_hash,
        'config_hash' => $batch->templateVersion->config_hash,
        'patch_identity' => $batch->templateVersion->patch_identity,
    ]);
    $sheet->forceFill(['current_processing_run_id' => $run->id])->save();

    app(RunOmrSheetAnalysis::class)->handle($run->id);
    $sheet->refresh();

    expect($sheet->status)->toBe(OmrCaptureSheet::STATUS_NEEDS_REVIEW)
        ->and($sheet->proposal_json['envelope'])->toBe(OmrDetectionRequest::ENVELOPE_VERSION)
        ->and($sheet->proposal_json['provider']['name'])->toBe('nexiam.fake')
        ->and($sheet->proposal_json['provider']['calibration_status'])->toBe('synthetic')
        ->and($sheet->proposal_json['input_hash'])->toBe(hash('sha256', $bytes))
        ->and($sheet->proposal_json)->not->toHaveKey('template_hash')
        ->and($sheet->proposal_json)->not->toHaveKey('booklet_hash')
        ->and($sheet->detected_applicant_number)->toBeNull();

    $areas = AptitudeArea::query()->where('is_active', true)->get();
    foreach ($sheet->proposal_json['per_area_scores'] as $areaId => $entry) {
        $area = $areas->firstWhere('id', (int) $areaId);
        expect($area)->not->toBeNull()
            ->and($entry['score'])->toBeBetween(0, (int) $area->max_items)
            ->and($entry['area_code'])->toBe($area->code);
    }

    expect(count($sheet->proposal_json['items']))->toBe($batch->bookletVersion->questions()->count());
    foreach ($sheet->proposal_json['items'] as $item) {
        expect($item['region'])->toHaveKeys(['x', 'y', 'w', 'h'])
            ->and($item['score'])->toBeIn([0, 1])
            ->and($item['correct_option_code'])->not->toBeNull();
    }

    $byArea = [];
    foreach ($sheet->proposal_json['items'] as $item) {
        $byArea[(string) $item['area_id']][] = $item['score'];
    }
    foreach ($byArea as $areaId => $scores) {
        expect(array_sum($scores))->toBe((int) $sheet->proposal_json['per_area_scores'][$areaId]['score']);
    }

    $issueCodes = $sheet->issues->pluck('issue_code')->unique();
    expect($issueCodes)->toContain(OmrDetectionIssue::CODE_IDENTITY_UNRESOLVED);
    foreach ($sheet->issues as $issue) {
        expect(OmrDetectionIssue::isKnownCode($issue->issue_code))->toBeTrue()
            ->and($issue->state)->toBe('open');
    }

    expect(DB::table('audit_events')->where('event_type', 'grading.omr_sheet_analyzed')->exists())->toBeTrue();
});
