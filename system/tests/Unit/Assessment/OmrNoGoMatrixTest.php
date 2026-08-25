<?php

use App\Assessment\Omr\Canonical\CanonicalArtifactSet;
use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Assessment\Omr\FakeOmrDetection;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrLayoutVerificationResult;

final class FakeNoGoLayoutVerification implements OmrLayoutVerificationPort
{
    private int $calls = 0;

    public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
    {
        $this->calls++;
        if ($this->calls === 1) {
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
    config(['omr.fake.ground_truth' => [hash('sha256', $png) => ['LOGIC' => 1]]]);
    app()->instance(OmrDetectionPort::class, new FakeOmrDetection);
    app()->instance(OmrLayoutVerificationPort::class, new FakeNoGoLayoutVerification);
    $evidence = [];
    $proof = ['proof_hash' => hash('sha256', 'proof'), 'answer_vector' => ['LOGIC_Q_1' => 'A'], 'pages' => [['bytes' => $png]]];
    $result = app(OmrNoGoMatrixRunner::class)->run($artifact, $proof, function (string $variant, string $role, string $bytes) use (&$evidence): array {
        $evidence[] = [$variant, $role, $bytes];

        return ['role' => $role, 'asset_id' => count($evidence), 'sha256' => hash('sha256', $bytes)];
    });

    expect($result['variants'])->toHaveCount(count(OmrNoGoMatrixRunner::VARIANTS))
        ->and($result['variants']['clean']['actual'])->toBe('pass')
        ->and($result['variants']['missing_marker']['actual'])->toBe('typed_rejection')
        ->and($result['variants']['wrong_instance']['execution'])->toBe('nexiam_capture_lifecycle_gate')
        ->and($result['matrix_hash'])->toHaveLength(64);
    expect($evidence)->toHaveCount(2)
        ->and($result['variants']['clean'])->not->toHaveKey('aligned_bytes');
});
