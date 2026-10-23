<?php

declare(strict_types=1);

use App\Assessment\Omr\Diagnostics\OmrDiagnosticCaptureTransformPipeline;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticScenarioManifest;

function createTestPatternPng(int $width = 200, int $height = 200): string
{
    $image = imagecreatetruecolor($width, $height);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    $gray = imagecolorallocate($image, 128, 128, 128);

    imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $white);
    imagefilledrectangle($image, 20, 20, 80, 80, $black);
    imagefilledrectangle($image, 100, 100, 160, 160, $gray);
    imageellipse($image, 100, 100, 60, 60, $black);

    ob_start();
    imagepng($image);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

test('capture transform pipeline preserves clean identity raster untouched', function (): void {
    $pipeline = new OmrDiagnosticCaptureTransformPipeline;
    $sourceBytes = createTestPatternPng(150, 150);
    $sourceHash = hash('sha256', $sourceBytes);

    $result = $pipeline->transform($sourceBytes, [
        'capture' => [
            'condition' => OmrDiagnosticScenarioManifest::CAPTURE_CLEAN_SCANNER,
            'transform' => ['kind' => 'identity'],
        ],
    ]);

    expect($result['bytes'])->toBe($sourceBytes)
        ->and($result['input_hash'])->toBe($sourceHash)
        ->and($result['source_hash'])->toBe($sourceHash)
        ->and($result['condition'])->toBe(OmrDiagnosticScenarioManifest::CAPTURE_CLEAN_SCANNER)
        ->and($result['width'])->toBe(150)
        ->and($result['height'])->toBe(150)
        ->and($result['generator']['name'])->toBe('nexiam.omr.variant');
});

test('capture transform pipeline executes every taxonomy condition deterministically', function (): void {
    $pipeline = new OmrDiagnosticCaptureTransformPipeline;
    $sourceBytes = createTestPatternPng(240, 240);
    $sourceHash = hash('sha256', $sourceBytes);

    $conditions = [
        OmrDiagnosticScenarioManifest::CAPTURE_PHONE => ['kind' => 'phone', 'downsample_ratio' => 0.88],
        OmrDiagnosticScenarioManifest::CAPTURE_ROTATION => ['kind' => 'rotation', 'degrees' => 1.25],
        OmrDiagnosticScenarioManifest::CAPTURE_PERSPECTIVE => ['kind' => 'perspective', 'x' => 0.012, 'y' => -0.009],
        OmrDiagnosticScenarioManifest::CAPTURE_UNEVEN_LIGHTING => ['kind' => 'uneven_lighting', 'gradient' => 0.18],
        OmrDiagnosticScenarioManifest::CAPTURE_LOW_CONTRAST => ['kind' => 'low_contrast', 'contrast' => 0.78],
        OmrDiagnosticScenarioManifest::CAPTURE_BLUR => ['kind' => 'blur', 'radius' => 1.2],
        OmrDiagnosticScenarioManifest::CAPTURE_SHADOW => ['kind' => 'shadow', 'opacity' => 0.16],
        OmrDiagnosticScenarioManifest::CAPTURE_PARTIAL_PAGE => ['kind' => 'partial_page', 'visible_fraction' => 0.72],
    ];

    foreach ($conditions as $condition => $transform) {
        $first = $pipeline->transform($sourceBytes, [
            'capture' => [
                'condition' => $condition,
                'transform' => $transform,
            ],
        ]);
        $second = $pipeline->transform($sourceBytes, [
            'capture' => [
                'condition' => $condition,
                'transform' => $transform,
            ],
        ]);

        expect($first['source_hash'])->toBe($sourceHash)
            ->and($first['condition'])->toBe($condition)
            ->and($first['input_hash'])->toBe($second['input_hash'])
            ->and($first['bytes'])->toBe($second['bytes'])
            ->and($first['width'])->toBeGreaterThan(0)
            ->and($first['height'])->toBeGreaterThan(0);
    }
});

test('capture transform pipeline executes quick rehearsal composite variants', function (): void {
    $pipeline = new OmrDiagnosticCaptureTransformPipeline;
    $sourceBytes = createTestPatternPng(200, 200);

    $composites = [
        'rotation_perspective' => ['kind' => 'rotation_perspective', 'degrees' => 1.25],
        'lighting_contrast' => ['kind' => 'lighting_contrast', 'gradient' => 0.18, 'contrast' => 0.78],
        'blur_shadow' => ['kind' => 'blur_shadow', 'radius' => 1.2, 'opacity' => 0.16],
    ];

    foreach ($composites as $kind => $transform) {
        $result = $pipeline->transform($sourceBytes, [
            'transform' => $transform,
        ]);

        expect($result['input_hash'])->toHaveLength(64)
            ->and($result['bytes'])->not->toBeEmpty()
            ->and($result['width'])->toBeGreaterThan(0);
    }
});

test('capture transform pipeline refuses empty source bytes', function (): void {
    $pipeline = new OmrDiagnosticCaptureTransformPipeline;

    expect(fn () => $pipeline->transform('', ['condition' => 'blur']))
        ->toThrow(RuntimeException::class, 'capture_pipeline_source_empty');
});
