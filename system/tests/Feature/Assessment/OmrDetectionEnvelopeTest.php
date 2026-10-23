<?php

use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Actions\Omr\RunOmrSheetAnalysis;
use App\Assessment\Omr\FakeOmrDetection;
use App\Assessment\Omr\OmrDetectedItem;
use App\Assessment\Omr\OmrDetectionIssue;
use App\Assessment\Omr\OmrDetectionOutcome;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrDetectionResultValidator;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrInvalidDetectionOutput;
use App\Assessment\Omr\OmrItemGeometry;
use App\Assessment\Omr\OmrOptionGeometry;
use App\Assessment\Omr\OmrOptionObservation;
use App\Assessment\Omr\OmrRegion;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\OmrSourceImage;
use App\Assessment\Omr\OmrSyntheticEvidenceCase;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrProcessingRun;
use App\Models\Foundation\AptitudeArea;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
    $inputHash = hash('sha256', $inputHash);

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
        configHash: hash('sha256', '{"fixture":true}'),
        bookletHash: str_repeat('d', 64),
        nativeTemplate: [],
        nativeConfigJson: '{"fixture":true}',
        nativeBundleMetadata: ['files' => ['config.json' => hash('sha256', '{"fixture":true}')]],
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
        registrationMode: OmrRegistrationMode::FiducialAnchored,
        templateVersionId: 1,
        candidateTargetId: null,
        sourceImage: new OmrSourceImage(1, 1, 1, $inputHash),
        pageNo: 1,
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
            $item->observedOptions,
            array_map(
                fn (OmrOptionObservation $observation): array => $observation->toArray(),
                $item->optionObservations,
            ),
            $item->issueCodes,
            $item->evidenceHash,
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
        runId: array_key_exists('runId', $overrides) ? $overrides['runId'] : $success->runId,
        correlationId: array_key_exists('correlationId', $overrides) ? $overrides['correlationId'] : $success->correlationId,
        bookletHash: array_key_exists('bookletHash', $overrides) ? $overrides['bookletHash'] : $success->bookletHash,
        nativeBundleHash: array_key_exists('nativeBundleHash', $overrides) ? $overrides['nativeBundleHash'] : $success->nativeBundleHash,
        manifestHash: array_key_exists('manifestHash', $overrides) ? $overrides['manifestHash'] : $success->manifestHash,
        patchIdentity: array_key_exists('patchIdentity', $overrides) ? $overrides['patchIdentity'] : $success->patchIdentity,
        calibrationStatus: $overrides['calibrationStatus'] ?? $success->calibrationStatus,
        providerDiagnostics: $overrides['providerDiagnostics'] ?? $success->providerDiagnostics,
        fieldBlockShifts: $overrides['fieldBlockShifts'] ?? $success->fieldBlockShifts,
        alignedImage: array_key_exists('alignedImage', $overrides) ? $overrides['alignedImage'] : $success->alignedImage,
        imageMime: array_key_exists('imageMime', $overrides) ? $overrides['imageMime'] : $success->imageMime,
        imageWidth: array_key_exists('imageWidth', $overrides) ? $overrides['imageWidth'] : $success->imageWidth,
        imageHeight: array_key_exists('imageHeight', $overrides) ? $overrides['imageHeight'] : $success->imageHeight,
        rectification: array_key_exists('rectification', $overrides) ? $overrides['rectification'] : $success->rectification,
        registrationMode: array_key_exists('registrationMode', $overrides) ? $overrides['registrationMode'] : $success->registrationMode,
        templateVersionId: array_key_exists('templateVersionId', $overrides) ? $overrides['templateVersionId'] : $success->templateVersionId,
        candidateTargetId: array_key_exists('candidateTargetId', $overrides) ? $overrides['candidateTargetId'] : $success->candidateTargetId,
        expectedPageNo: array_key_exists('expectedPageNo', $overrides) ? $overrides['expectedPageNo'] : $success->expectedPageNo,
        sourceImage: array_key_exists('sourceImage', $overrides) ? $overrides['sourceImage'] : $success->sourceImage,
    );
}

test('detected item hashes stay stable across all machine states', function (): void {
    $geometry = new OmrItemGeometry(
        1,
        'LOGIC',
        1,
        'LOGIC_Q_001',
        new OmrRegion(0.1, 0.2, 0.3, 0.4),
        [
            new OmrOptionGeometry('A', new OmrRegion(0.1, 0.2, 0.05, 0.05), 1),
            new OmrOptionGeometry('B', new OmrRegion(0.2, 0.2, 0.05, 0.05), 2),
        ],
    );

    $hashes = [
        'marked' => OmrDetectedItem::fromGeometry($geometry, 'A', OmrDetectedItem::STATE_MARKED, null)->evidenceHash,
        'blank' => OmrDetectedItem::fromGeometry($geometry, null, OmrDetectedItem::STATE_BLANK, null)->evidenceHash,
        'ambiguous' => OmrDetectedItem::fromGeometry($geometry, null, OmrDetectedItem::STATE_AMBIGUOUS, null, ['A', 'B'])->evidenceHash,
        'unreadable' => OmrDetectedItem::fromGeometry($geometry, null, OmrDetectedItem::STATE_UNREADABLE, null, [])->evidenceHash,
    ];

    expect($hashes)->toBe([
        'marked' => '34c2f8dac1a696501acade580723d38c5d14ec02fb45e37018182d2f2f64f8e3',
        'blank' => '6d0cfb852b2aefb5eece98ed02d470926fa18217ff723237a21f99b054bdaab8',
        'ambiguous' => 'c7be9f70e5e29ea4d981282111e27af0daaec52f46d2f4713023161b4f6ae497',
        'unreadable' => 'd3bc4c08efaf7284f907b5b678f6cf9aa70a7ba8c788697a44f07ca0ff964192',
    ]);
});

test('fake provider is deterministic and candidate-complete under v3', function (): void {
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
            ->and($item->region->isFiniteAndNormalized())->toBeTrue()
            ->and($item->isCandidateComplete())->toBeTrue()
            ->and($item->evidenceHash)->toMatch('/^[a-f0-9]{64}$/')
            ->and($item->optionObservations)->not->toBeEmpty();

        if ($item->state === OmrDetectedItem::STATE_MARKED) {
            expect($item->observedOptions)->toHaveCount(1)
                ->and($item->selectedOption)->toBe($item->observedOptions[0]);
        } elseif ($item->state === OmrDetectedItem::STATE_AMBIGUOUS) {
            expect($item->observedOptions)->toHaveCount(2)
                ->and($item->selectedOption)->toBeNull();
        } elseif ($item->state === OmrDetectedItem::STATE_BLANK) {
            expect($item->observedOptions)->toBe([])
                ->and($item->selectedOption)->toBeNull();
        } else {
            expect($item->selectedOption)->toBeNull();
        }
    }
});

test('fake provider deterministically emits all eight selectable synthetic evidence cases', function (): void {
    $inputHash = hash('sha256', 'hash-synthetic-8-cases');
    $request = new OmrDetectionRequest(
        envelopeVersion: OmrDetectionRequest::ENVELOPE_VERSION,
        runId: 'synthetic-run-8',
        correlationId: 'synthetic-corr-8',
        imageBytes: str_repeat(chr(0), 64),
        inputHash: $inputHash,
        imageMime: 'image/png',
        imageWidth: null,
        imageHeight: null,
        areas: ['VER' => 4, 'NUM' => 4],
        items: [
            new OmrItemGeometry(1, 'VER', 1, 'VER_Q_001', new OmrRegion(0.08, 0.10, 0.84, 0.01), omrTestOptions()),
            new OmrItemGeometry(2, 'VER', 2, 'VER_Q_002', new OmrRegion(0.08, 0.15, 0.84, 0.01), omrTestOptions()),
            new OmrItemGeometry(3, 'VER', 3, 'VER_Q_003', new OmrRegion(0.08, 0.20, 0.84, 0.01), omrTestOptions()),
            new OmrItemGeometry(4, 'VER', 4, 'VER_Q_004', new OmrRegion(0.08, 0.25, 0.84, 0.01), omrTestOptions()),
            new OmrItemGeometry(5, 'NUM', 1, 'NUM_Q_001', new OmrRegion(0.08, 0.30, 0.84, 0.01), omrTestOptions()),
            new OmrItemGeometry(6, 'NUM', 2, 'NUM_Q_002', new OmrRegion(0.08, 0.35, 0.84, 0.01), omrTestOptions()),
            new OmrItemGeometry(7, 'NUM', 3, 'NUM_Q_003', new OmrRegion(0.08, 0.40, 0.84, 0.01), omrTestOptions()),
            new OmrItemGeometry(8, 'NUM', 4, 'NUM_Q_004', new OmrRegion(0.08, 0.45, 0.84, 0.01), omrTestOptions()),
        ],
        identityRegions: ['applicant_number' => new OmrRegion(0.08, 0.08, 0.24, 0.08)],
        nativeBundleHash: str_repeat('a', 64),
        manifestHash: str_repeat('b', 64),
        configHash: hash('sha256', '{"fixture":true}'),
        bookletHash: str_repeat('d', 64),
        nativeTemplate: [],
        nativeConfigJson: '{"fixture":true}',
        nativeBundleMetadata: ['files' => ['config.json' => hash('sha256', '{"fixture":true}')]],
        reviewManifest: [],
        engineName: 'nexiam.fake',
        engineCommit: null,
        patchIdentity: 'nexiam-headless-overlay-v1',
        expectedFieldLabels: ['VER_Q_001', 'VER_Q_002', 'VER_Q_003', 'VER_Q_004', 'NUM_Q_001', 'NUM_Q_002', 'NUM_Q_003', 'NUM_Q_004'],
        expectedOptionCodes: [
            'VER_Q_001' => ['A', 'B', 'C', 'D'],
            'VER_Q_002' => ['A', 'B', 'C', 'D'],
            'VER_Q_003' => ['A', 'B', 'C', 'D'],
            'VER_Q_004' => ['A', 'B', 'C', 'D'],
            'NUM_Q_001' => ['A', 'B', 'C', 'D'],
            'NUM_Q_002' => ['A', 'B', 'C', 'D'],
            'NUM_Q_003' => ['A', 'B', 'C', 'D'],
            'NUM_Q_004' => ['A', 'B', 'C', 'D'],
        ],
        registrationMode: OmrRegistrationMode::FiducialAnchored,
        templateVersionId: 1,
        candidateTargetId: null,
        sourceImage: new OmrSourceImage(1, 1, 1, $inputHash),
        pageNo: 1,
        providerDeadline: 30.0,
    );

    config([
        'omr.fake.evidence_cases' => [
            $request->inputHash => [
                'VER_Q_001' => OmrSyntheticEvidenceCase::CASE_DEFINITE,
                'VER_Q_002' => OmrSyntheticEvidenceCase::CASE_BLANK,
                'VER_Q_003' => OmrSyntheticEvidenceCase::CASE_AMBIGUOUS,
                'VER_Q_004' => OmrSyntheticEvidenceCase::CASE_UNREADABLE,
                'NUM_Q_001' => OmrSyntheticEvidenceCase::CASE_ERASED,
                'NUM_Q_002' => OmrSyntheticEvidenceCase::CASE_CROSSED,
                'NUM_Q_003' => OmrSyntheticEvidenceCase::CASE_PARTIAL,
                'NUM_Q_004' => OmrSyntheticEvidenceCase::CASE_OUT_OF_REGION,
            ],
        ],
    ]);

    $detector = new FakeOmrDetection;
    $result = $detector->detect($request);

    expect($result->outcome)->toBe(OmrDetectionOutcome::Succeeded)
        ->and($result->success)->not->toBeNull();

    $validated = app(OmrDetectionResultValidator::class)->validate($request, $result);
    expect($validated)->not->toBeNull();

    $itemsByKey = [];
    foreach ($result->success->items as $item) {
        $itemsByKey[sprintf('%s_Q_%03d', $item->areaCode, $item->questionNo)] = $item;
    }

    // 1. Definite case
    $definite = $itemsByKey['VER_Q_001'];
    expect($definite->state)->toBe(OmrDetectedItem::STATE_MARKED)
        ->and($definite->selectedOption)->toBe('A')
        ->and($definite->observedOptions)->toBe(['A'])
        ->and($definite->isCandidateComplete())->toBeTrue()
        ->and($definite->issueCodes)->toBe([]);
    $obsDef = array_column(array_map(fn ($o) => $o->toArray(), $definite->optionObservations), 'classification', 'code');
    expect($obsDef['A'])->toBe('marked')
        ->and($obsDef['B'])->toBe('unmarked')
        ->and($obsDef['C'])->toBe('unmarked')
        ->and($obsDef['D'])->toBe('unmarked');

    // 2. Blank case
    $blank = $itemsByKey['VER_Q_002'];
    expect($blank->state)->toBe(OmrDetectedItem::STATE_BLANK)
        ->and($blank->selectedOption)->toBeNull()
        ->and($blank->observedOptions)->toBe([])
        ->and($blank->isCandidateComplete())->toBeTrue()
        ->and($blank->issueCodes)->toBe([]);
    $obsBlank = array_column(array_map(fn ($o) => $o->toArray(), $blank->optionObservations), 'classification', 'code');
    expect($obsBlank)->toBe([
        'A' => 'unmarked',
        'B' => 'unmarked',
        'C' => 'unmarked',
        'D' => 'unmarked',
    ]);

    // 3. Ambiguous case
    $ambiguous = $itemsByKey['VER_Q_003'];
    expect($ambiguous->state)->toBe(OmrDetectedItem::STATE_AMBIGUOUS)
        ->and($ambiguous->selectedOption)->toBeNull()
        ->and($ambiguous->observedOptions)->toBe(['A', 'B'])
        ->and($ambiguous->isCandidateComplete())->toBeTrue()
        ->and($ambiguous->issueCodes)->toContain(OmrDetectionIssue::CODE_AMBIGUOUS_MARK);
    $obsAmb = array_column(array_map(fn ($o) => $o->toArray(), $ambiguous->optionObservations), 'classification', 'code');
    expect($obsAmb['A'])->toBe('ambiguous_candidate')
        ->and($obsAmb['B'])->toBe('ambiguous_candidate')
        ->and($obsAmb['C'])->toBe('unmarked')
        ->and($obsAmb['D'])->toBe('unmarked');

    // 4. Unreadable case
    $unreadable = $itemsByKey['VER_Q_004'];
    expect($unreadable->state)->toBe(OmrDetectedItem::STATE_UNREADABLE)
        ->and($unreadable->selectedOption)->toBeNull()
        ->and($unreadable->isCandidateComplete())->toBeTrue()
        ->and($unreadable->issueCodes)->toContain(OmrDetectionIssue::CODE_UNREADABLE);
    $obsUnr = array_column(array_map(fn ($o) => $o->toArray(), $unreadable->optionObservations), 'classification', 'code');
    expect($obsUnr['A'])->toBe('unreadable');

    // 5. Erased case
    $erased = $itemsByKey['NUM_Q_001'];
    expect($erased->state)->toBe(OmrDetectedItem::STATE_MARKED)
        ->and($erased->selectedOption)->toBe('B')
        ->and($erased->observedOptions)->toBe(['B'])
        ->and($erased->isCandidateComplete())->toBeTrue();
    $obsEra = array_column(array_map(fn ($o) => $o->toArray(), $erased->optionObservations), 'classification', 'code');
    expect($obsEra['A'])->toBe('erased')
        ->and($obsEra['B'])->toBe('marked')
        ->and($obsEra['C'])->toBe('unmarked');

    // 6. Crossed case
    $crossed = $itemsByKey['NUM_Q_002'];
    expect($crossed->state)->toBe(OmrDetectedItem::STATE_MARKED)
        ->and($crossed->selectedOption)->toBe('A')
        ->and($crossed->observedOptions)->toBe(['A'])
        ->and($crossed->isCandidateComplete())->toBeTrue()
        ->and($crossed->issueCodes)->toContain(OmrDetectionIssue::CODE_LOW_CONFIDENCE);
    $obsCro = array_column(array_map(fn ($o) => $o->toArray(), $crossed->optionObservations), 'classification', 'code');
    expect($obsCro['A'])->toBe('crossed')
        ->and($obsCro['B'])->toBe('unmarked');

    // 7. Partial case
    $partial = $itemsByKey['NUM_Q_003'];
    expect($partial->state)->toBe(OmrDetectedItem::STATE_MARKED)
        ->and($partial->selectedOption)->toBe('A')
        ->and($partial->observedOptions)->toBe(['A'])
        ->and($partial->isCandidateComplete())->toBeTrue()
        ->and($partial->issueCodes)->toContain(OmrDetectionIssue::CODE_LOW_CONFIDENCE);
    $obsPar = array_column(array_map(fn ($o) => $o->toArray(), $partial->optionObservations), 'classification', 'code');
    expect($obsPar['A'])->toBe('partial')
        ->and($obsPar['B'])->toBe('unmarked');

    // 8. Out of region case
    $oor = $itemsByKey['NUM_Q_004'];
    expect($oor->state)->toBe(OmrDetectedItem::STATE_MARKED)
        ->and($oor->selectedOption)->toBe('A')
        ->and($oor->observedOptions)->toBe(['A'])
        ->and($oor->isCandidateComplete())->toBeTrue()
        ->and($oor->issueCodes)->toContain(OmrDetectionIssue::CODE_NO_GEOMETRY);
    $obsOor = array_column(array_map(fn ($o) => $o->toArray(), $oor->optionObservations), 'classification', 'code');
    expect($obsOor['A'])->toBe('out_of_region')
        ->and($obsOor['B'])->toBe('unmarked');

    // Cardinality invariants on all eight items:
    foreach ($result->success->items as $item) {
        expect($item->evidenceHash)->toMatch('/^[a-f0-9]{64}$/')
            ->and($item->optionObservations)->toHaveCount(4);

        if ($item->state === OmrDetectedItem::STATE_MARKED) {
            expect($item->observedOptions)->toHaveCount(1)
                ->and($item->selectedOption)->toBe($item->observedOptions[0]);
        } elseif ($item->state === OmrDetectedItem::STATE_AMBIGUOUS) {
            expect(count($item->observedOptions))->toBeGreaterThanOrEqual(2)
                ->and($item->selectedOption)->toBeNull();
        } elseif ($item->state === OmrDetectedItem::STATE_BLANK) {
            expect($item->observedOptions)->toBe([])
                ->and($item->selectedOption)->toBeNull();
        } elseif ($item->state === OmrDetectedItem::STATE_UNREADABLE) {
            expect($item->selectedOption)->toBeNull();
        }

        foreach ($item->issueCodes as $code) {
            expect(OmrDetectionIssue::isKnownCode($code))->toBeTrue();
        }
    }
});

test('synthetic evidence cases can be selected by canonical key, flat config, or area and question coordinates', function (): void {
    $request = omrTestRequest('hash-coord-selection');

    // Configure with flat keys, area+question nesting, and kebab-case alias
    config([
        'omr.fake.evidence_cases' => [
            'VER_Q_001' => 'erased',
            'VER_Q_002' => 'crossed',
            'NUM' => [
                1 => 'partial',
                2 => 'out-of-region',
            ],
        ],
    ]);

    $result = (new FakeOmrDetection)->detect($request);
    expect($result->outcome)->toBe(OmrDetectionOutcome::Succeeded);

    $validated = app(OmrDetectionResultValidator::class)->validate($request, $result);
    expect($validated)->not->toBeNull();

    $itemsByKey = [];
    foreach ($result->success->items as $item) {
        $itemsByKey[sprintf('%s_Q_%03d', $item->areaCode, $item->questionNo)] = $item;
    }

    $erased = $itemsByKey['VER_Q_001'];
    $obsEra = array_column(array_map(fn ($o) => $o->toArray(), $erased->optionObservations), 'classification', 'code');
    expect($obsEra['A'])->toBe('erased');

    $crossed = $itemsByKey['VER_Q_002'];
    $obsCro = array_column(array_map(fn ($o) => $o->toArray(), $crossed->optionObservations), 'classification', 'code');
    expect($obsCro['A'])->toBe('crossed');

    $partial = $itemsByKey['NUM_Q_001'];
    $obsPar = array_column(array_map(fn ($o) => $o->toArray(), $partial->optionObservations), 'classification', 'code');
    expect($obsPar['A'])->toBe('partial');

    $oor = $itemsByKey['NUM_Q_002'];
    $obsOor = array_column(array_map(fn ($o) => $o->toArray(), $oor->optionObservations), 'classification', 'code');
    expect($obsOor['A'])->toBe('out_of_region');
});

test('synthetic evidence cases maintain answer-key isolation and candidate completeness', function (): void {
    $request = omrTestRequest('hash-key-isolation');

    config([
        'omr.fake.evidence_cases' => [
            'hash-key-isolation' => [
                'VER_Q_001' => 'definite',
                'VER_Q_002' => 'ambiguous',
                'NUM_Q_001' => 'erased',
                'NUM_Q_002' => 'blank',
            ],
        ],
    ]);

    // Confirm provider request does NOT carry answer keys
    expect(property_exists($request, 'answerKey'))->toBeFalse()
        ->and(property_exists($request, 'correctAnswers'))->toBeFalse();

    $firstRun = (new FakeOmrDetection)->detect($request);
    $secondRun = (new FakeOmrDetection)->detect($request);

    expect(omrSuccessArray($firstRun->success))->toBe(omrSuccessArray($secondRun->success));

    foreach ($firstRun->success->items as $item) {
        expect($item->isCandidateComplete())->toBeTrue()
            ->and($item->legacyEvidenceLimited)->toBeFalse()
            ->and($item->toArray())->not->toHaveKeys([
                'answer_key',
                'correct_option',
                'correct_option_code',
                'expected',
                'expected_answer',
                'expected_option',
                'correctness',
                'score',
                'scores',
            ]);
    }
});

test('synthetic evidence cases support structured configuration overrides and normalized aliases', function (): void {
    $request = omrTestRequest('hash-structured-overrides');

    config([
        'omr.fake.evidence_cases' => [
            $request->inputHash => [
                'VER_Q_001' => [
                    'case' => 'erased',
                    'state' => OmrDetectedItem::STATE_BLANK,
                    'erased_option' => 'A',
                ],
                'VER_Q_002' => [
                    'case' => 'crossed',
                    'state' => OmrDetectedItem::STATE_AMBIGUOUS,
                    'observed_options' => ['A', 'C'],
                ],
                'NUM_Q_001' => [
                    'case' => 'partial',
                    'state' => OmrDetectedItem::STATE_BLANK,
                    'partial_option' => 'B',
                ],
                'NUM_Q_002' => [
                    'case' => 'out-of-region',
                    'state' => OmrDetectedItem::STATE_UNREADABLE,
                    'out_of_region_option' => 'D',
                ],
            ],
        ],
    ]);

    $result = (new FakeOmrDetection)->detect($request);
    expect($result->outcome)->toBe(OmrDetectionOutcome::Succeeded);

    $validated = app(OmrDetectionResultValidator::class)->validate($request, $result);
    expect($validated)->not->toBeNull();

    $items = [];
    foreach ($result->success->items as $item) {
        $items[sprintf('%s_Q_%03d', $item->areaCode, $item->questionNo)] = $item;
    }

    // Erased in blank state
    expect($items['VER_Q_001']->state)->toBe(OmrDetectedItem::STATE_BLANK)
        ->and($items['VER_Q_001']->selectedOption)->toBeNull()
        ->and($items['VER_Q_001']->observedOptions)->toBe([]);
    $obs1 = array_column(array_map(fn ($o) => $o->toArray(), $items['VER_Q_001']->optionObservations), 'classification', 'code');
    expect($obs1['A'])->toBe('erased');

    // Crossed in ambiguous state
    expect($items['VER_Q_002']->state)->toBe(OmrDetectedItem::STATE_AMBIGUOUS)
        ->and($items['VER_Q_002']->selectedOption)->toBeNull()
        ->and($items['VER_Q_002']->observedOptions)->toBe(['A', 'C'])
        ->and($items['VER_Q_002']->issueCodes)->toContain(OmrDetectionIssue::CODE_AMBIGUOUS_MARK);
    $obs2 = array_column(array_map(fn ($o) => $o->toArray(), $items['VER_Q_002']->optionObservations), 'classification', 'code');
    expect($obs2['A'])->toBe('crossed')
        ->and($obs2['C'])->toBe('ambiguous_candidate');

    // Partial in blank state
    expect($items['NUM_Q_001']->state)->toBe(OmrDetectedItem::STATE_BLANK)
        ->and($items['NUM_Q_001']->selectedOption)->toBeNull()
        ->and($items['NUM_Q_001']->observedOptions)->toBe([]);
    $obs3 = array_column(array_map(fn ($o) => $o->toArray(), $items['NUM_Q_001']->optionObservations), 'classification', 'code');
    expect($obs3['B'])->toBe('partial');

    // Out of region in unreadable state
    expect($items['NUM_Q_002']->state)->toBe(OmrDetectedItem::STATE_UNREADABLE)
        ->and($items['NUM_Q_002']->selectedOption)->toBeNull()
        ->and($items['NUM_Q_002']->issueCodes)->toContain(OmrDetectionIssue::CODE_NO_GEOMETRY);
    $obs4 = array_column(array_map(fn ($o) => $o->toArray(), $items['NUM_Q_002']->optionObservations), 'classification', 'code');
    expect($obs4['D'])->toBe('out_of_region');
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
        observedOptions: ['A'],
        optionObservations: $items[0]->optionObservations,
        issueCodes: [],
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

test('analysis persists a v3 candidate-complete proposal with NEXIAM-derived scores', function (): void {
    Storage::fake('sensitive');
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];

    $batch = app(CreateOmrCaptureBatch::class)->handle($grading, $guidance, 'test-omr-batch-key');

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
        'run_uuid' => (string) Str::uuid(),
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
            ->and($item)->toHaveKeys(['observed_options', 'option_observations', 'issue_codes', 'evidence_hash'])
            ->and($item['evidence_hash'])->toMatch('/^[a-f0-9]{64}$/')
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
