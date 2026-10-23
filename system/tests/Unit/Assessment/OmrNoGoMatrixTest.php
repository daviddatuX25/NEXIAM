<?php

use App\Assessment\Omr\Canonical\CanonicalArtifactSet;
use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use App\Assessment\Omr\OmrDetectedItem;
use App\Assessment\Omr\OmrDetectionOutcome;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Assessment\Omr\OmrLayoutVerificationResult;
use App\Assessment\Omr\OmrOptionObservation;
use App\Assessment\Omr\OmrRegion;

final class FakeNoGoLayoutVerification implements OmrLayoutVerificationPort
{
    private int $calls = 0;

    public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
    {
        $this->calls++;
        if ($this->calls <= 7) {
            return new OmrLayoutVerificationResult(
                outcome: OmrLayoutVerificationResult::OUTCOME_SUCCEEDED,
                alignedBytes: 'aligned',
                alignedImage: ['mime' => 'image/png', 'width' => 100, 'height' => 100],
                overlayBytes: 'overlay',
                overlayImage: ['mime' => 'image/png', 'width' => 100, 'height' => 100],
                markerMatches: ['quarter_1' => .9, 'quarter_2' => .9, 'quarter_3' => .9, 'quarter_4' => .9],
                fieldBlockShifts: [],
                gates: ['marker_count' => 'pass', 'dimensions' => 'pass', 'shifts' => 'pass'],
            );
        }

        return OmrLayoutVerificationResult::failed('marker_missing', 'fixture rejection');
    }
}

final class ExactNoGoDetectionFake implements OmrDetectionPort
{
    private int $calls = 0;

    public function detect(OmrDetectionRequest $request): OmrDetectionResult
    {
        $this->calls++;
        if ($this->calls > 7) {
            return OmrDetectionResult::failed(
                OmrDetectionOutcome::TerminalFailure,
                'marker_missing',
                'fixture rejection',
            );
        }

        $items = array_map(static function ($geometry): OmrDetectedItem {
            $selected = $geometry->options[0]->code ?? null;

            return OmrDetectedItem::fromGeometry(
                geometry: $geometry,
                selectedOption: $selected,
                state: OmrDetectedItem::STATE_MARKED,
                confidence: null,
            );
        }, $request->items);

        return OmrDetectionResult::succeeded(new OmrDetectionSuccess(
            engineName: $request->engineName,
            engineVersion: 'test',
            engineCommit: $request->engineCommit,
            inputHash: $request->inputHash,
            configHash: $request->configHash,
            applicantNumberProposal: null,
            applicantNumberConfidence: null,
            identityFields: [],
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
            fieldBlockShifts: [],
            imageMime: $request->imageMime,
            imageWidth: $request->imageWidth,
            imageHeight: $request->imageHeight,
        ));
    }
}

test('digital no-go matrix executes image variants and records lifecycle gates', function (): void {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $template = [
        'bubbleDimensions' => [4, 4],
        'pageDimensions' => [100, 100],
        'pages' => [[
            'page_no' => 1,
            'pageDimensions' => [100, 100],
            'fieldBlocks' => ['Part_1' => [
                'fieldType' => 'QTYPE_MCQ4',
                'direction' => 'horizontal',
                'fieldLabels' => ['LOGIC_Q_1'],
                'bubblesGap' => 10,
                'labelsGap' => 10,
                'origin' => [20, 20],
                'bubbleDimensions' => [4, 4],
            ]],
            'preProcessors' => [['name' => 'CropOnMarkers', 'options' => ['relativePath' => 'marker.jpg']]],
            'markers' => ['asset_name' => 'marker.jpg', 'size' => 8, 'quiet_zone' => 2, 'centers' => [[0, 0]], 'insets_px' => ['top' => 0, 'left' => 0]],
        ]],
    ];
    $reviewManifest = [
        'fields' => [[
            'page' => 1,
            'label' => 'LOGIC_Q_1',
            'option' => 'A',
            'region' => ['x' => .2, 'y' => .2, 'w' => .04, 'h' => .04],
        ]],
    ];
    $artifact = new CanonicalArtifactSet(
        definition: ['source_hash' => str_repeat('d', 64), 'insets_px' => ['top' => 0, 'left' => 0]],
        template: $template,
        config: ['dimensions' => ['display_width' => 100, 'display_height' => 100]],
        manifest: ['files' => ['template.json' => str_repeat('t', 64)], 'engine' => ['name' => 'OMRChecker', 'commit' => str_repeat('c', 40), 'patch_set' => 'nexiam-headless-overlay-v1'], 'bundle_version' => 'nexiam.omr.native-bundle.v1'],
        reviewManifest: $reviewManifest,
        reviewManifestHash: str_repeat('m', 64),
        files: ['marker.jpg' => $png],
        pages: [['page_no' => 1, 'bytes' => $png, 'mime' => 'image/png', 'width' => 1, 'height' => 1, 'sha256' => hash('sha256', $png), 'metadata' => []]],
        pdfBytes: 'pdf',
        artifactHash: str_repeat('a', 64),
        templateHash: str_repeat('t', 64),
        configHash: str_repeat('g', 64),
        pdfHash: str_repeat('p', 64),
        referenceHash: str_repeat('r', 64),
        markerHash: hash('sha256', $png),
    );
    app()->instance(OmrDetectionPort::class, new ExactNoGoDetectionFake);
    app()->instance(OmrLayoutVerificationPort::class, new FakeNoGoLayoutVerification);
    $evidence = [];
    $proof = ['proof_hash' => hash('sha256', 'proof'), 'answer_vector' => ['LOGIC_Q_1' => 'A'], 'pages' => [['bytes' => $png]]];
    $result = app(OmrNoGoMatrixRunner::class)->run($artifact, $proof, function (string $variant, string $role, string $bytes) use (&$evidence): array {
        $evidence[] = [$variant, $role, $bytes];

        return ['role' => $role, 'asset_id' => count($evidence), 'sha256' => hash('sha256', $bytes)];
    });

    foreach (['clean', 'perspective', 'rotation', 'uneven_light', 'blur', 'glare', 'low_resolution'] as $variant) {
        expect($result['variants'][$variant]['actual'])->toBe('pass')
            ->and($result['variants'][$variant]['answer_vector_match'])->toBeTrue();
    }
    foreach (['partial_page', 'missing_marker', 'wrong_marker'] as $variant) {
        expect($result['variants'][$variant]['actual'])->toBe('typed_rejection');
    }
    expect($result['variants'])->toHaveCount(count(OmrNoGoMatrixRunner::VARIANTS))
        ->and($result['variants']['wrong_instance']['execution'])->toBe('nexiam_capture_lifecycle_gate')
        ->and($result['matrix_hash'])->toHaveLength(64);
    $inputEvidence = array_values(array_filter($evidence, static fn (array $entry): bool => $entry[1] === 'input.png'));
    expect($evidence)->toHaveCount(28)
        ->and($inputEvidence)->toHaveCount(count(OmrNoGoMatrixRunner::VARIANTS))
        ->and($inputEvidence)->each->toBeArray()
        ->and($result['variants']['uneven_light']['item_summary'])->toHaveKeys(['expected', 'exact', 'ambiguous', 'missing', 'vector_match'])
        ->and($result['variants']['clean'])->not->toHaveKey('aligned_bytes');

    foreach ($inputEvidence as [$variant, $_role, $bytes]) {
        expect(hash('sha256', $bytes))->toBe($result['variants'][$variant]['input_hash']);
    }
});

test('exact recovery rejects an extra marked field as a false accept', function (): void {
    $region = new OmrRegion(0.0, 0.0, 1.0, 1.0);
    $items = [
        new OmrDetectedItem(1, 'LOGIC', 1, 'A', OmrDetectedItem::STATE_MARKED, null, $region, ['A'], [new OmrOptionObservation('A', null, 'observed')], []),
        new OmrDetectedItem(2, 'LOGIC', 2, 'B', OmrDetectedItem::STATE_MARKED, null, $region, ['B'], [new OmrOptionObservation('B', null, 'observed')], []),
    ];

    expect(OmrNoGoMatrixRunner::answerVectorMatches(['LOGIC_Q_1' => 'A'], $items))->toBeFalse();
});

test('marker-sensitive no-go variants fail closed on weak registration scores', function (): void {
    $runner = app(OmrNoGoMatrixRunner::class);
    $gate = new ReflectionMethod($runner, 'markerGatePasses');
    $gate->setAccessible(true);

    expect($gate->invoke($runner, [
        'quarter_1' => 0.351,
        'quarter_2' => 0.493,
        'quarter_3' => 0.49,
        'quarter_4' => 0.49,
    ]))->toBeFalse()
        ->and($gate->invoke($runner, [
            'quarter_1' => 0.883,
            'quarter_2' => 0.882,
            'quarter_3' => 0.884,
            'quarter_4' => 0.883,
        ]))->toBeTrue();
});

test('layout verification projects corrected option rects into normalized item evidence and preserves pinned geometry on invalid rects', function (): void {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $template = [
        'bubbleDimensions' => [4, 4],
        'pageDimensions' => [100, 100],
        'pages' => [[
            'page_no' => 1,
            'pageDimensions' => [100, 100],
            'fieldBlocks' => ['Part_1' => [
                'fieldType' => 'QTYPE_MCQ4',
                'direction' => 'horizontal',
                'fieldLabels' => ['LOGIC_Q_1', 'LOGIC_Q_2', 'LOGIC_Q_3', 'LOGIC_Q_4'],
                'bubblesGap' => 10,
                'labelsGap' => 10,
                'origin' => [20, 20],
                'bubbleDimensions' => [4, 4],
            ]],
            'preProcessors' => [['name' => 'CropOnMarkers', 'options' => ['relativePath' => 'marker.jpg']]],
            'markers' => ['asset_name' => 'marker.jpg', 'size' => 8, 'quiet_zone' => 2, 'centers' => [[0, 0]], 'insets_px' => ['top' => 0, 'left' => 0]],
        ]],
    ];
    $reviewManifest = [
        'fields' => [
            ['page' => 1, 'label' => 'LOGIC_Q_1', 'option' => 'A', 'region' => ['x' => 0.20, 'y' => 0.20, 'w' => 0.04, 'h' => 0.04]],
            ['page' => 1, 'label' => 'LOGIC_Q_1', 'option' => 'B', 'region' => ['x' => 0.30, 'y' => 0.20, 'w' => 0.04, 'h' => 0.04]],
            ['page' => 1, 'label' => 'LOGIC_Q_2', 'option' => 'A', 'region' => ['x' => 0.20, 'y' => 0.40, 'w' => 0.04, 'h' => 0.04]],
            ['page' => 1, 'label' => 'LOGIC_Q_3', 'option' => 'A', 'region' => ['x' => 0.20, 'y' => 0.60, 'w' => 0.04, 'h' => 0.04]],
            ['page' => 1, 'label' => 'LOGIC_Q_4', 'option' => 'A', 'region' => ['x' => 0.20, 'y' => 0.80, 'w' => 0.04, 'h' => 0.04]],
        ],
    ];
    $artifact = new CanonicalArtifactSet(
        definition: ['source_hash' => str_repeat('d', 64), 'insets_px' => ['top' => 0, 'left' => 0]],
        template: $template,
        config: ['dimensions' => ['display_width' => 100, 'display_height' => 100]],
        manifest: ['files' => ['template.json' => str_repeat('t', 64)], 'engine' => ['name' => 'OMRChecker', 'commit' => str_repeat('c', 40), 'patch_set' => 'nexiam-headless-overlay-v1'], 'bundle_version' => 'nexiam.omr.native-bundle.v1'],
        reviewManifest: $reviewManifest,
        reviewManifestHash: str_repeat('m', 64),
        files: ['marker.jpg' => $png],
        pages: [['page_no' => 1, 'bytes' => $png, 'mime' => 'image/png', 'width' => 1, 'height' => 1, 'sha256' => hash('sha256', $png), 'metadata' => []]],
        pdfBytes: 'pdf',
        artifactHash: str_repeat('a', 64),
        templateHash: str_repeat('t', 64),
        configHash: str_repeat('g', 64),
        pdfHash: str_repeat('p', 64),
        referenceHash: str_repeat('r', 64),
        markerHash: hash('sha256', $png),
    );

    $layoutResult = new OmrLayoutVerificationResult(
        outcome: OmrLayoutVerificationResult::OUTCOME_SUCCEEDED,
        alignedBytes: 'aligned',
        alignedImage: ['mime' => 'image/png', 'width' => 200, 'height' => 200],
        overlayBytes: 'overlay',
        overlayImage: ['mime' => 'image/png', 'width' => 200, 'height' => 200],
        markerMatches: ['quarter_1' => .9, 'quarter_2' => .9, 'quarter_3' => .9, 'quarter_4' => .9],
        fieldBlockShifts: [],
        gates: ['marker_count' => 'pass', 'dimensions' => 'pass', 'shifts' => 'pass'],
        itemEvidence: [
            [
                'label' => 'LOGIC_Q_1',
                'options' => [
                    ['option' => 'A', 'rect' => ['x' => 44, 'y' => 42, 'w' => 10, 'h' => 10]],
                    ['option' => 'B', 'rect' => ['x' => 64, 'y' => 42, 'w' => 10, 'h' => 10]],
                    ['option' => 'UNKNOWN_OPTION', 'rect' => ['x' => 10, 'y' => 10, 'w' => 10, 'h' => 10]],
                ],
            ],
            [
                'label' => 'LOGIC_Q_2',
                'options' => [
                    ['option' => 'A', 'rect' => ['x' => 195, 'y' => 195, 'w' => 20, 'h' => 20]],
                ],
            ],
            [
                'label' => 'LOGIC_Q_3',
                'options' => [
                    ['option' => 'A', 'rect' => ['x' => -5, 'y' => 20, 'w' => 10, 'h' => 10]],
                ],
            ],
            [
                'label' => 'UNRECOGNIZED_Q_99',
                'options' => [
                    ['option' => 'A', 'rect' => ['x' => 50, 'y' => 50, 'w' => 10, 'h' => 10]],
                ],
            ],
        ],
    );

    app()->instance(OmrDetectionPort::class, new ExactNoGoDetectionFake);
    app()->instance(OmrLayoutVerificationPort::class, new class($layoutResult) implements OmrLayoutVerificationPort
    {
        public function __construct(private OmrLayoutVerificationResult $result) {}

        public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
        {
            return $this->result;
        }
    });

    $cleanPath = tempnam(sys_get_temp_dir(), 'omr-clean-');
    expect($cleanPath)->not->toBeFalse();
    file_put_contents($cleanPath, $png);

    try {
        $entry = app(OmrNoGoMatrixRunner::class)->runVariant(
            'clean',
            $cleanPath,
            $artifact,
            ['answer_vector' => ['LOGIC_Q_1' => 'A', 'LOGIC_Q_2' => 'A', 'LOGIC_Q_3' => 'A', 'LOGIC_Q_4' => 'A']],
        );

        $items = collect($entry['item_evidence'])->keyBy('native_label');

        expect($items)->toHaveKeys(['LOGIC_Q_1', 'LOGIC_Q_2', 'LOGIC_Q_3', 'LOGIC_Q_4'])
            ->and($items)->not->toHaveKey('UNRECOGNIZED_Q_99');

        // Known label and options: projected into normalized coordinates using verified aligned dimensions (200x200)
        $q1Options = collect($items['LOGIC_Q_1']['options'])->keyBy('code');
        expect($q1Options)->toHaveKeys(['A', 'B'])
            ->and($q1Options)->not->toHaveKey('UNKNOWN_OPTION');

        expect($q1Options['A']['region']['x'])->toEqualWithDelta(0.22, 1e-8)
            ->and($q1Options['A']['region']['y'])->toEqualWithDelta(0.21, 1e-8)
            ->and($q1Options['A']['region']['w'])->toEqualWithDelta(0.05, 1e-8)
            ->and($q1Options['A']['region']['h'])->toEqualWithDelta(0.05, 1e-8)
            ->and($q1Options['B']['region']['x'])->toEqualWithDelta(0.32, 1e-8)
            ->and($q1Options['B']['region']['y'])->toEqualWithDelta(0.21, 1e-8)
            ->and($q1Options['B']['region']['w'])->toEqualWithDelta(0.05, 1e-8)
            ->and($q1Options['B']['region']['h'])->toEqualWithDelta(0.05, 1e-8)
            ->and($items['LOGIC_Q_1']['region']['x'])->toEqualWithDelta(0.22, 1e-8)
            ->and($items['LOGIC_Q_1']['region']['y'])->toEqualWithDelta(0.21, 1e-8)
            ->and($items['LOGIC_Q_1']['region']['w'])->toEqualWithDelta(0.15, 1e-8)
            ->and($items['LOGIC_Q_1']['region']['h'])->toEqualWithDelta(0.05, 1e-8);

        // Out-of-bounds rect: falls back to pinned request geometry
        $q2Options = collect($items['LOGIC_Q_2']['options'])->keyBy('code');
        expect($q2Options['A']['region'])->toEqual(['x' => 0.2, 'y' => 0.4, 'w' => 0.04, 'h' => 0.04]);
        expect($items['LOGIC_Q_2']['region']['x'])->toEqualWithDelta(0.2, 1e-8)
            ->and($items['LOGIC_Q_2']['region']['y'])->toEqualWithDelta(0.4, 1e-8)
            ->and($items['LOGIC_Q_2']['region']['w'])->toEqualWithDelta(0.04, 1e-8)
            ->and($items['LOGIC_Q_2']['region']['h'])->toEqualWithDelta(0.04, 1e-8);

        // Invalid rect (negative coordinate): falls back to pinned request geometry
        $q3Options = collect($items['LOGIC_Q_3']['options'])->keyBy('code');
        expect($q3Options['A']['region'])->toEqual(['x' => 0.2, 'y' => 0.6, 'w' => 0.04, 'h' => 0.04]);
        expect($items['LOGIC_Q_3']['region']['x'])->toEqualWithDelta(0.2, 1e-8)
            ->and($items['LOGIC_Q_3']['region']['y'])->toEqualWithDelta(0.6, 1e-8)
            ->and($items['LOGIC_Q_3']['region']['w'])->toEqualWithDelta(0.04, 1e-8)
            ->and($items['LOGIC_Q_3']['region']['h'])->toEqualWithDelta(0.04, 1e-8);

        // Missing rect (not in layout evidence): falls back to pinned request geometry
        $q4Options = collect($items['LOGIC_Q_4']['options'])->keyBy('code');
        expect($q4Options['A']['region'])->toEqual(['x' => 0.2, 'y' => 0.8, 'w' => 0.04, 'h' => 0.04]);
        expect($items['LOGIC_Q_4']['region']['x'])->toEqualWithDelta(0.2, 1e-8)
            ->and($items['LOGIC_Q_4']['region']['y'])->toEqualWithDelta(0.8, 1e-8)
            ->and($items['LOGIC_Q_4']['region']['w'])->toEqualWithDelta(0.04, 1e-8)
            ->and($items['LOGIC_Q_4']['region']['h'])->toEqualWithDelta(0.04, 1e-8);
    } finally {
        @unlink($cleanPath);
        app()->forgetInstance(OmrDetectionPort::class);
        app()->forgetInstance(OmrLayoutVerificationPort::class);
    }
});
