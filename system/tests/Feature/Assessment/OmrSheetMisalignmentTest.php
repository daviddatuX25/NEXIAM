<?php

use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Actions\Omr\RunOmrSheetAnalysis;
use App\Assessment\Omr\FakeOmrDetection;
use App\Assessment\Omr\OmrDetectedItem;
use App\Assessment\Omr\OmrDetectionIssue;
use App\Assessment\Omr\OmrDetectionOutcome;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrDetectionResultValidator;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrIdentityField;
use App\Assessment\Omr\OmrInvalidDetectionOutput;
use App\Assessment\Omr\OmrItemGeometry;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Assessment\Omr\OmrOptionGeometry;
use App\Assessment\Omr\OmrRegion;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\OmrSourceImage;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrProcessingRun;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Support\SensitiveAssetStore;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** A valid solid-color RGB PNG of arbitrary size (decodes via getimagesize). */
function omrSolidPng(int $width, int $height): string
{
    $ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
    $raw = str_repeat("\x00".str_repeat("\xFF\xFF\xFF", $width), $height);
    $idat = gzcompress($raw, 6);

    return "\x89PNG\r\n\x1a\n"
        .pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr))
        .pack('N', strlen($idat)).'IDAT'.$idat.pack('N', crc32('IDAT'.$idat))
        .pack('N', 0).'IEND'.pack('N', crc32('IEND'));
}

/** @return list<OmrOptionGeometry> */
function omrSheetMisalignmentOptions(): array
{
    return [
        new OmrOptionGeometry('A', new OmrRegion(0.10, 0.20, 0.04, 0.02), 1),
        new OmrOptionGeometry('B', new OmrRegion(0.28, 0.20, 0.04, 0.02), 2),
        new OmrOptionGeometry('C', new OmrRegion(0.46, 0.20, 0.04, 0.02), 3),
        new OmrOptionGeometry('D', new OmrRegion(0.64, 0.20, 0.04, 0.02), 4),
    ];
}

function omrSheetMisalignmentBaseRequest(): OmrDetectionRequest
{
    $bytes = omrSolidPng(1, 1);
    $sourceImage = OmrSourceImage::fromBytes($bytes);

    return new OmrDetectionRequest(
        envelopeVersion: OmrDetectionRequest::ENVELOPE_VERSION,
        runId: 'test-run',
        correlationId: 'test-corr',
        imageBytes: $bytes,
        registrationMode: OmrRegistrationMode::FiducialAnchored,
        templateVersionId: 1,
        candidateTargetId: null,
        sourceImage: $sourceImage,
        inputHash: $sourceImage->inputHash,
        imageMime: 'image/png',
        imageWidth: $sourceImage->width,
        imageHeight: $sourceImage->height,
        areas: ['VER' => 2, 'NUM' => 2],
        items: [
            new OmrItemGeometry(1, 'VER', 1, 'VER_Q_001', new OmrRegion(0.08, 0.25, 0.84, 0.01), omrSheetMisalignmentOptions()),
            new OmrItemGeometry(2, 'VER', 2, 'VER_Q_002', new OmrRegion(0.08, 0.28, 0.84, 0.01), omrSheetMisalignmentOptions()),
            new OmrItemGeometry(3, 'NUM', 1, 'NUM_Q_001', new OmrRegion(0.08, 0.31, 0.84, 0.01), omrSheetMisalignmentOptions()),
            new OmrItemGeometry(4, 'NUM', 2, 'NUM_Q_002', new OmrRegion(0.08, 0.34, 0.84, 0.01), omrSheetMisalignmentOptions()),
        ],
        identityRegions: ['applicant_number' => new OmrRegion(0.08, 0.08, 0.24, 0.08)],
        nativeBundleHash: str_repeat('a', 64),
        manifestHash: str_repeat('b', 64),
        configHash: hash('sha256', '{"fixture":true}'),
        bookletHash: str_repeat('d', 64),
        nativeTemplate: [],
        nativeConfigJson: '{"fixture":true}',
        nativeBundleMetadata: [
            'files' => ['config.json' => hash('sha256', '{"fixture":true}')],
            'registration_mode' => OmrRegistrationMode::FiducialAnchored->value,
            'qr_mode' => 'qr_signed',
            'compiled_pages' => [['page_no' => 1]],
        ],
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
        pageNo: 1,
    );
}

function omrSheetMisalignmentCopySuccess(OmrDetectionSuccess $success, array $overrides = []): OmrDetectionSuccess
{
    return new OmrDetectionSuccess(
        engineName: $overrides['engineName'] ?? $success->engineName,
        engineVersion: $overrides['engineVersion'] ?? $success->engineVersion,
        engineCommit: $overrides['engineCommit'] ?? $success->engineCommit,
        inputHash: $overrides['inputHash'] ?? $success->inputHash,
        configHash: $overrides['configHash'] ?? $success->configHash,
        applicantNumberProposal: $overrides['applicantNumberProposal'] ?? $success->applicantNumberProposal,
        applicantNumberConfidence: $overrides['applicantNumberConfidence'] ?? $success->applicantNumberConfidence,
        identityFields: $overrides['identityFields'] ?? $success->identityFields,
        items: $overrides['items'] ?? $success->items,
        issues: $overrides['issues'] ?? $success->issues,
        sheetConfidence: $overrides['sheetConfidence'] ?? $success->sheetConfidence,
        responseEnvelopeVersion: $overrides['responseEnvelopeVersion'] ?? $success->responseEnvelopeVersion,
        runId: $overrides['runId'] ?? $success->runId,
        correlationId: $overrides['correlationId'] ?? $success->correlationId,
        bookletHash: $overrides['bookletHash'] ?? $success->bookletHash,
        nativeBundleHash: $overrides['nativeBundleHash'] ?? $success->nativeBundleHash,
        manifestHash: $overrides['manifestHash'] ?? $success->manifestHash,
        patchIdentity: $overrides['patchIdentity'] ?? $success->patchIdentity,
        registrationMode: $overrides['registrationMode'] ?? $success->registrationMode,
        templateVersionId: $overrides['templateVersionId'] ?? $success->templateVersionId,
        candidateTargetId: $overrides['candidateTargetId'] ?? $success->candidateTargetId,
        expectedPageNo: $overrides['expectedPageNo'] ?? $success->expectedPageNo,
        sourceImage: $overrides['sourceImage'] ?? $success->sourceImage,
        calibrationStatus: $overrides['calibrationStatus'] ?? $success->calibrationStatus,
        fieldBlockShifts: $overrides['fieldBlockShifts'] ?? $success->fieldBlockShifts,
        alignedImage: $overrides['alignedImage'] ?? $success->alignedImage,
        imageMime: $overrides['imageMime'] ?? $success->imageMime,
        imageWidth: $overrides['imageWidth'] ?? $success->imageWidth,
        imageHeight: $overrides['imageHeight'] ?? $success->imageHeight,
        birthdateProposal: $overrides['birthdateProposal'] ?? $success->birthdateProposal,
        providerDiagnostics: $overrides['providerDiagnostics'] ?? $success->providerDiagnostics,
        rectification: $overrides['rectification'] ?? $success->rectification,
    );
}

/**
 * The compact profile block geometry solved by the layout solver: 40px
 * bubbles, 52px option pitch, 80px row pitch, and a compact applicant grid.
 */
function omrMisalignedRequest(): OmrDetectionRequest
{
    $request = omrSheetMisalignmentBaseRequest();

    return new OmrDetectionRequest(
        envelopeVersion: $request->envelopeVersion,
        runId: $request->runId,
        correlationId: $request->correlationId,
        imageBytes: $request->imageBytes,
        inputHash: $request->inputHash,
        imageMime: $request->imageMime,
        imageWidth: $request->imageWidth,
        imageHeight: $request->imageHeight,
        areas: $request->areas,
        items: $request->items,
        identityRegions: $request->identityRegions,
        registrationMode: $request->registrationMode,
        templateVersionId: $request->templateVersionId,
        candidateTargetId: $request->candidateTargetId,
        sourceImage: $request->sourceImage,
        nativeBundleHash: $request->nativeBundleHash,
        manifestHash: $request->manifestHash,
        configHash: $request->configHash,
        bookletHash: $request->bookletHash,
        nativeTemplate: [
            'fieldBlocks' => [
                'ApplicantNumber' => [
                    'fieldType' => 'QTYPE_INT',
                    'fieldLabels' => ['APPLICANT_NUMBER_1..10'],
                    'bubblesGap' => 52,
                    'labelsGap' => 66,
                    'origin' => [200, 300],
                ],
                'PROFILE_VERBALPartA' => [
                    'fieldType' => 'QTYPE_MCQ4',
                    'direction' => 'horizontal',
                    'fieldLabels' => ['PROFILE_VERBAL_Q_001..025'],
                    'bubblesGap' => 52,
                    'labelsGap' => 80,
                    'origin' => [120, 940],
                ],
                'LOGICPartC' => [
                    'fieldType' => 'QTYPE_MCQ4',
                    'direction' => 'horizontal',
                    'fieldLabels' => ['LOGIC_Q_001..025'],
                    'bubblesGap' => 52,
                    'labelsGap' => 80,
                    'origin' => [784, 940],
                ],
            ],
        ],
        nativeConfigJson: $request->nativeConfigJson,
        nativeBundleMetadata: $request->nativeBundleMetadata,
        reviewManifest: $request->reviewManifest,
        engineName: $request->engineName,
        engineCommit: $request->engineCommit,
        patchIdentity: $request->patchIdentity,
        expectedFieldLabels: $request->expectedFieldLabels,
        expectedOptionCodes: $request->expectedOptionCodes,
        providerDeadline: $request->providerDeadline,
        pageNo: $request->pageNo,
    );
}

/** @return list<array{block: string, x: int, y: int}> */
function omrShifts(array $blocks, string $target, int $x): array
{
    $shifts = [];
    foreach (array_keys($blocks) as $name) {
        $shifts[] = ['block' => $name, 'x' => $name === $target ? $x : 0, 'y' => 0];
    }

    return $shifts;
}

/** @return list<string> */
function omrIssueCodes(OmrDetectionSuccess $success): array
{
    return array_map(fn (OmrDetectionIssue $issue): string => $issue->code, $success->issues);
}

/** @return list<OmrDetectionIssue> */
function omrMisalignedIssues(OmrDetectionSuccess $success): array
{
    return array_values(array_filter(
        $success->issues,
        static fn (OmrDetectionIssue $issue): bool => $issue->code === OmrDetectionIssue::CODE_SHEET_MISALIGNED,
    ));
}

/**
 * Deterministic unqualified worker-shaped detector: echoes request identity,
 * reports five false marked answers plus 145 blanks (the observed real-photo
 * failure shape), and reports one option-crossing field-block shift.
 */
final class ShiftedOmrDetection implements OmrDetectionPort
{
    public function __construct(private readonly int $unsafeX = 128) {}

    public function detect(OmrDetectionRequest $request): OmrDetectionResult
    {
        $blocks = (array) ($request->nativeTemplate['fieldBlocks'] ?? []);
        $mcq = array_keys(array_filter(
            $blocks,
            static fn (array $block): bool => ($block['fieldType'] ?? '') === 'QTYPE_MCQ4',
        ));
        $target = in_array('LOGICPartC', $mcq, true) ? 'LOGICPartC' : (string) ($mcq[0] ?? '');

        $falseAnswers = ['B', 'D', 'B', 'A', 'C'];
        $items = [];
        $position = 0;
        foreach ($request->items as $geometry) {
            $position++;
            $selected = $position <= 5 ? $falseAnswers[$position - 1] : null;
            $items[] = OmrDetectedItem::fromGeometry(
                geometry: $geometry,
                selectedOption: $selected,
                state: $selected !== null ? OmrDetectedItem::STATE_MARKED : OmrDetectedItem::STATE_BLANK,
                confidence: null,
            );
        }

        $identityRegion = $request->identityRegions[OmrIdentityField::CODE_APPLICANT_NUMBER] ?? new OmrRegion(0.05, 0.05, 0.2, 0.2);
        $alignedBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        return OmrDetectionResult::succeeded(new OmrDetectionSuccess(
            engineName: $request->engineName,
            engineVersion: '1',
            engineCommit: $request->engineCommit,
            inputHash: $request->inputHash,
            configHash: $request->configHash,
            applicantNumberProposal: '2000600002',
            applicantNumberConfidence: null,
            identityFields: [
                new OmrIdentityField(OmrIdentityField::CODE_APPLICANT_NUMBER, $identityRegion, null, '2000600002'),
            ],
            items: $items,
            issues: [],
            sheetConfidence: null,
            responseEnvelopeVersion: $request->envelopeVersion,
            runId: $request->runId,
            correlationId: $request->correlationId,
            bookletHash: $request->bookletHash,
            nativeBundleHash: $request->nativeBundleHash,
            manifestHash: $request->manifestHash,
            patchIdentity: $request->patchIdentity,
            calibrationStatus: 'unqualified',
            registrationMode: $request->registrationMode->value,
            templateVersionId: $request->templateVersionId,
            candidateTargetId: $request->candidateTargetId,
            expectedPageNo: $request->pageNo,
            sourceImage: $request->sourceImage->toArray(),
            fieldBlockShifts: omrShifts($blocks, $target, $this->unsafeX),
            alignedImage: [
                'mime' => 'image/png',
                'width' => 1,
                'height' => 1,
                'bytes' => $alignedBytes,
                'hash' => hash('sha256', $alignedBytes),
            ],
            imageMime: $request->imageMime,
            imageWidth: $request->imageWidth,
            imageHeight: $request->imageHeight,
        ));
    }
}

/**
 * Real profile pair: open batch, queued sheet with a 512px-wide asset (so the
 * observed 96/128px shifts stay inside image bounds), and a pending run.
 *
 * @return array{0: OmrCaptureSheet, 1: OmrProcessingRun}
 */
function misalignmentOmrFixture(TestCase $test): array
{
    Storage::fake('sensitive');
    // The production layout port shares the detector adapter. This fixture
    // overrides detection with a detection-only fake; marker-less grading
    // bypasses layout, so provide an inert layout adapter explicitly.
    app()->instance(
        OmrLayoutVerificationPort::class,
        new FakeOmrDetection,
    );
    $fixture = buildGradingFixture($test);
    seedE2OmrFoundation();
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->whereHas('templateVersion.template', fn ($query) => $query->where('code', 'SYN-OMR-PROFILE-150'))
        ->firstOrFail();

    $batch = app(CreateOmrCaptureBatch::class)->handle($grading, $guidance, 'misalignment-'.uniqid());

    $assetStore = app(SensitiveAssetStore::class);
    $bytes = omrSolidPng(512, 512);
    $path = $assetStore->store($bytes, 'omr/test/'.uniqid().'.img');

    $sheet = OmrCaptureSheet::query()->create([
        'batch_id' => $batch->id,
        'status' => OmrCaptureSheet::STATUS_QUEUED,
        'capture_mode' => OmrCaptureSheet::MODE_AUTO,
        'version' => 0,
        'client_capture_id' => (string) Str::uuid(),
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
        'run_uuid' => (string) Str::uuid(),
        'status' => OmrProcessingRun::STATUS_PENDING,
        'page_no' => 1,
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

    return [$sheet, $run];
}

test('an option-crossing shift derives a required sheet_misaligned issue', function (): void {
    $request = omrMisalignedRequest();
    $success = (new FakeOmrDetection)->detect($request)->success;
    $success = omrSheetMisalignmentCopySuccess($success, [
        'fieldBlockShifts' => omrShifts($request->nativeTemplate['fieldBlocks'], 'PROFILE_VERBALPartA', 96),
    ]);

    $effective = app(OmrDetectionResultValidator::class)->validate($request, OmrDetectionResult::succeeded($success));

    expect($effective)->not->toBeNull()
        ->and(omrIssueCodes($effective))->toContain(OmrDetectionIssue::CODE_SHEET_MISALIGNED);
    $misaligned = omrMisalignedIssues($effective);
    expect($misaligned)->toHaveCount(1)
        ->and($misaligned[0]->severity)->toBe(OmrDetectionIssue::SEVERITY_REQUIRED)
        ->and($misaligned[0]->areaCode)->toBeNull()
        ->and($misaligned[0]->questionNo)->toBeNull()
        ->and($effective->items)->toHaveCount(count($success->items));
});

test('the observed 128px shift is unsafe and 0px stays valid', function (): void {
    $request = omrMisalignedRequest();
    $validator = app(OmrDetectionResultValidator::class);
    $base = (new FakeOmrDetection)->detect($request)->success;

    $unsafe = $validator->validate($request, OmrDetectionResult::succeeded(omrSheetMisalignmentCopySuccess($base, [
        'fieldBlockShifts' => omrShifts($request->nativeTemplate['fieldBlocks'], 'LOGICPartC', 128),
    ])));
    expect(omrIssueCodes($unsafe))->toContain(OmrDetectionIssue::CODE_SHEET_MISALIGNED);

    $zero = omrSheetMisalignmentCopySuccess($base, [
        'fieldBlockShifts' => omrShifts($request->nativeTemplate['fieldBlocks'], 'LOGICPartC', 0),
    ]);
    $validated = $validator->validate($request, OmrDetectionResult::succeeded($zero));
    expect($validated)->toBe($zero)
        ->and(omrIssueCodes($validated))->not->toContain(OmrDetectionIssue::CODE_SHEET_MISALIGNED);
});

test('sub-pitch shifts and missing geometry stay valid', function (): void {
    $request = omrMisalignedRequest();
    $validator = app(OmrDetectionResultValidator::class);
    $base = (new FakeOmrDetection)->detect($request)->success;

    $small = $validator->validate($request, OmrDetectionResult::succeeded(omrSheetMisalignmentCopySuccess($base, [
        'fieldBlockShifts' => omrShifts($request->nativeTemplate['fieldBlocks'], 'PROFILE_VERBALPartA', 10),
    ])));
    expect(omrIssueCodes($small))->not->toContain(OmrDetectionIssue::CODE_SHEET_MISALIGNED);

    $noGeometry = omrSheetMisalignmentCopySuccess($base, [
        'fieldBlockShifts' => omrShifts([], 'PROFILE_VERBALPartA', 128),
    ]);
    $validated = $validator->validate(omrSheetMisalignmentBaseRequest(), OmrDetectionResult::succeeded($noGeometry));
    expect(omrIssueCodes($validated))->not->toContain(OmrDetectionIssue::CODE_SHEET_MISALIGNED);
});

test('a provider-emitted required issue is retained without duplication', function (): void {
    $request = omrMisalignedRequest();
    $success = (new FakeOmrDetection)->detect($request)->success;
    $success = omrSheetMisalignmentCopySuccess($success, [
        'issues' => [
            new OmrDetectionIssue(OmrDetectionIssue::CODE_SHEET_MISALIGNED, OmrDetectionIssue::SEVERITY_REQUIRED),
        ],
        'fieldBlockShifts' => omrShifts($request->nativeTemplate['fieldBlocks'], 'LOGICPartC', 128),
    ]);

    $effective = app(OmrDetectionResultValidator::class)->validate($request, OmrDetectionResult::succeeded($success));

    expect(omrMisalignedIssues($effective))->toHaveCount(1)
        ->and($effective->issues)->toBe($success->issues);
});

test('a provider warning is upgraded to a required sheet_misaligned issue', function (): void {
    $request = omrMisalignedRequest();
    $success = (new FakeOmrDetection)->detect($request)->success;
    $success = omrSheetMisalignmentCopySuccess($success, [
        'issues' => [
            new OmrDetectionIssue(OmrDetectionIssue::CODE_SHEET_MISALIGNED, OmrDetectionIssue::SEVERITY_WARNING),
        ],
        'fieldBlockShifts' => omrShifts($request->nativeTemplate['fieldBlocks'], 'LOGICPartC', 128),
    ]);

    $effective = app(OmrDetectionResultValidator::class)->validate($request, OmrDetectionResult::succeeded($success));

    $required = array_values(array_filter(
        omrMisalignedIssues($effective),
        static fn (OmrDetectionIssue $issue): bool => $issue->severity === OmrDetectionIssue::SEVERITY_REQUIRED,
    ));
    expect($required)->toHaveCount(1);
});

test('vertical digit grids gate on the digit and column pitch', function (): void {
    $request = omrMisalignedRequest();
    $validator = app(OmrDetectionResultValidator::class);
    $base = (new FakeOmrDetection)->detect($request)->success;

    $verticalUnsafe = $validator->validate($request, OmrDetectionResult::succeeded(omrSheetMisalignmentCopySuccess($base, [
        'fieldBlockShifts' => [
            ['block' => 'ApplicantNumber', 'x' => 0, 'y' => 30],
            ['block' => 'PROFILE_VERBALPartA', 'x' => 0, 'y' => 0],
            ['block' => 'LOGICPartC', 'x' => 0, 'y' => 0],
        ],
    ])));
    expect(omrIssueCodes($verticalUnsafe))->toContain(OmrDetectionIssue::CODE_SHEET_MISALIGNED);

    $verticalSafe = $validator->validate($request, OmrDetectionResult::succeeded(omrSheetMisalignmentCopySuccess($base, [
        'fieldBlockShifts' => [
            ['block' => 'ApplicantNumber', 'x' => 10, 'y' => 10],
            ['block' => 'PROFILE_VERBALPartA', 'x' => 0, 'y' => 0],
            ['block' => 'LOGICPartC', 'x' => 0, 'y' => 0],
        ],
    ])));
    expect(omrIssueCodes($verticalSafe))->not->toContain(OmrDetectionIssue::CODE_SHEET_MISALIGNED);
});

test('unknown block orientation degrades to the smaller gap on both axes', function (): void {
    $request = omrSheetMisalignmentBaseRequest();
    $request = new OmrDetectionRequest(
        envelopeVersion: $request->envelopeVersion,
        runId: $request->runId,
        correlationId: $request->correlationId,
        imageBytes: $request->imageBytes,
        inputHash: $request->inputHash,
        imageMime: $request->imageMime,
        imageWidth: $request->imageWidth,
        imageHeight: $request->imageHeight,
        areas: $request->areas,
        items: $request->items,
        identityRegions: $request->identityRegions,
        nativeBundleHash: $request->nativeBundleHash,
        manifestHash: $request->manifestHash,
        configHash: $request->configHash,
        bookletHash: $request->bookletHash,
        nativeTemplate: [
            'fieldBlocks' => [
                'MysteryBlock' => ['fieldType' => 'QTYPE_MCQ8', 'bubblesGap' => 60, 'origin' => [200, 300]],
            ],
        ],
        nativeBundleMetadata: $request->nativeBundleMetadata,
        reviewManifest: $request->reviewManifest,
        nativeConfigJson: $request->nativeConfigJson,
        engineName: $request->engineName,
        registrationMode: $request->registrationMode,
        templateVersionId: $request->templateVersionId,
        candidateTargetId: $request->candidateTargetId,
        sourceImage: $request->sourceImage,
        engineCommit: $request->engineCommit,
        patchIdentity: $request->patchIdentity,
        expectedFieldLabels: $request->expectedFieldLabels,
        expectedOptionCodes: $request->expectedOptionCodes,
        providerDeadline: $request->providerDeadline,
        pageNo: $request->pageNo,
    );
    $validator = app(OmrDetectionResultValidator::class);
    $base = (new FakeOmrDetection)->detect($request)->success;

    $unsafe = $validator->validate($request, OmrDetectionResult::succeeded(omrSheetMisalignmentCopySuccess($base, [
        'fieldBlockShifts' => [['block' => 'MysteryBlock', 'x' => 35, 'y' => 0]],
    ])));
    expect(omrIssueCodes($unsafe))->toContain(OmrDetectionIssue::CODE_SHEET_MISALIGNED);

    $safe = $validator->validate($request, OmrDetectionResult::succeeded(omrSheetMisalignmentCopySuccess($base, [
        'fieldBlockShifts' => [['block' => 'MysteryBlock', 'x' => 20, 'y' => 20]],
    ])));
    expect(omrIssueCodes($safe))->not->toContain(OmrDetectionIssue::CODE_SHEET_MISALIGNED);
});

test('malformed shifts still fail validation', function (): void {
    config(['omr.fake.with_aligned_image' => true]);
    $request = omrMisalignedRequest();
    $success = (new FakeOmrDetection)->detect($request)->success;
    $success = omrSheetMisalignmentCopySuccess($success, [
        'calibrationStatus' => 'unqualified',
        'fieldBlockShifts' => [['block' => 'LOGICPartC', 'x' => 128]],
    ]);

    expect(fn () => app(OmrDetectionResultValidator::class)->validate($request, OmrDetectionResult::succeeded($success)))
        ->toThrow(OmrInvalidDetectionOutput::class);
});

test('non-succeeded outcomes still pass through as null', function (): void {
    $failed = OmrDetectionResult::failed(OmrDetectionOutcome::TerminalFailure, 'fake.unexpected_error');
    expect(app(OmrDetectionResultValidator::class)->validate(omrMisalignedRequest(), $failed))->toBeNull();
});

test('an unsafe field-block shift fails closed end to end without persisting marks', function (): void {
    app()->instance(OmrDetectionPort::class, new ShiftedOmrDetection(128));
    [$sheet, $run] = misalignmentOmrFixture($this);
    app(RunOmrSheetAnalysis::class)->handle($run->id);
    $sheet->refresh();

    expect($sheet->status)->toBe(OmrCaptureSheet::STATUS_NEEDS_REVIEW)
        ->and($sheet->proposal_json['abstained'])->toBeTrue()
        ->and($sheet->proposal_json['reason'])->toBe('sheet_misaligned')
        ->and($sheet->proposal_json['items'])->toBe([])
        ->and($sheet->proposal_json['per_area_scores'])->toBe([])
        ->and($sheet->proposal_json['applicant_number_proposal'])->toBeNull()
        ->and($sheet->proposal_json['applicant_number_confidence'])->toBeNull()
        ->and($sheet->proposal_json['birthdate_proposal'])->toBeNull()
        ->and($sheet->proposal_json['sheet_confidence'])->toBeNull()
        ->and($sheet->proposal_json['provider']['calibration_status'])->toBe('unqualified')
        ->and($sheet->detected_applicant_number)->toBeNull()
        ->and($sheet->sheet_confidence)->toBeNull();

    $issues = $sheet->issues;
    $misaligned = $issues->first(fn ($issue): bool => $issue->issue_code === OmrDetectionIssue::CODE_SHEET_MISALIGNED);
    expect($misaligned)->not->toBeNull()
        ->and($misaligned->severity)->toBe(OmrDetectionIssue::SEVERITY_REQUIRED)
        ->and($misaligned->state)->toBe('open');

    $run->refresh();
    expect($run->status)->toBe(OmrProcessingRun::STATUS_SUCCEEDED)
        ->and($run->output_json['issue_count'])->toBe(1);

    // The false marks must not surface anywhere in the persisted proposal.
    foreach ((array) $sheet->proposal_json['items'] as $item) {
        expect($item['detected_answer'])->toBeNull();
    }
});

test('a zero-shift unqualified envelope still projects answers and identity', function (): void {
    app()->instance(OmrDetectionPort::class, new ShiftedOmrDetection(0));
    [$sheet, $run] = misalignmentOmrFixture($this);

    app(RunOmrSheetAnalysis::class)->handle($run->id);
    $sheet->refresh();

    expect($sheet->status)->toBe(OmrCaptureSheet::STATUS_NEEDS_REVIEW)
        ->and($sheet->proposal_json['abstained'] ?? false)->toBeFalse()
        ->and($sheet->proposal_json['items'])->toHaveCount((int) $sheet->batch->bookletVersion->questions()->count())
        ->and($sheet->proposal_json['per_area_scores'])->toHaveCount(6)
        ->and($sheet->proposal_json['applicant_number_proposal'])->toBe('2000600002')
        ->and($sheet->proposal_json['sheet_confidence'])->toBeNull()
        ->and($sheet->detected_applicant_number)->toBe('2000600002')
        ->and($sheet->detected_applicant_confidence)->toBeNull()
        ->and($sheet->issues->pluck('issue_code'))->not->toContain(OmrDetectionIssue::CODE_SHEET_MISALIGNED);

    $marked = collect($sheet->proposal_json['items'])->filter(fn (array $item): bool => $item['state'] === 'marked');
    expect($marked)->toHaveCount(5);
});
