<?php

use App\Assessment\Actions\Omr\SaveAuthoredTemplateDraft;
use App\Assessment\Actions\Omr\SaveAuthoredBookletVersion;
use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\OmrPinnedSnapshot;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrCapturePolicy;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
 * Marker-asset bundle contract (invariant #5): the fiducial rides the
 * immutable bundle (staged by SaveAuthoredTemplateDraft, hash-covered by the
 * manifest, validated by NativeBundle::load), is carried to the worker via
 * OmrPinnedSnapshot/OmrDetectionRequest, and a declared-but-missing asset
 * fails validation here — never as an engine exit(31) in the child.
 */

function markerBundleSections(): array
{
    $areas = ['PROFILE_VERBAL', 'PROFILE_QUANT', 'LOGIC', 'SPATIAL', 'MEMORY', 'LANGUAGE'];
    $sections = [];
    foreach ($areas as $index => $area) {
        $sections[] = [
            'area_code' => $area,
            'question_from' => 1,
            'question_to' => 25,
            'option_count' => 4,
            'break_before' => $index === 0 ? null : 'next_column',
        ];
    }

    return $sections;
}

function markerBundleBlueprint(): array
{
    return [
        'schema' => 'nexiam.omr.layout-blueprint.v1',
        'paper' => 'long',
        'columns' => 6,
        'identity' => ['applicant_number' => true, 'birthdate' => false, 'repeat_on_every_page' => true],
        'markers' => ['enabled' => true],
        'sections' => markerBundleSections(),
    ];
}

test('marker-bearing save stages the fiducial asset into the bundle and manifest', function (): void {
    seedE2Foundation();

    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $version = app(SaveAuthoredTemplateDraft::class)->handle(
        'SYN-OMR-MARKER-TEST',
        'Marker bundle test',
        [],
        $actor,
        null,
        true,
        markerBundleBlueprint(),
    );

    expect($version->native_bundle_json['files'])->toHaveKey('omr_marker.jpg')
        ->and($version->native_bundle_json['template']['pages'][0]['markers']['asset_name'])->toBe('omr_marker.jpg');

    $bundle = NativeBundle::load('SYN-OMR-MARKER-TEST', (int) $version->version);
    expect($bundle->hasMarkerPages())->toBeTrue()
        ->and($bundle->markerAssetName())->toBe('omr_marker.jpg')
        ->and($bundle->fileHashes)->toHaveKey('omr_marker.jpg')
        ->and($bundle->assetBytes('omr_marker.jpg'))->toBe(\App\Assessment\Omr\Fiducial\OmrFiducial::assetBytes())
        ->and($bundle->manifest['files']['omr_marker.jpg'])->toBe($bundle->fileHashes['omr_marker.jpg']);
});

test('a marker-bearing bundle missing its fiducial fails validation, never exit(31)', function (): void {
    seedE2Foundation();

    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $version = app(SaveAuthoredTemplateDraft::class)->handle(
        'SYN-OMR-MARKER-TEST-2',
        'Marker bundle missing asset test',
        [],
        $actor,
        null,
        true,
        markerBundleBlueprint(),
    );

    Storage::disk('local')->delete('omr-bundles/SYN-OMR-MARKER-TEST-2/v'.$version->version.'/omr_marker.jpg');

    expect(fn () => NativeBundle::load('SYN-OMR-MARKER-TEST-2', (int) $version->version))
        ->toThrow(RuntimeException::class, 'missing')
        ->and(fn () => NativeBundle::load('SYN-OMR-MARKER-TEST-2', (int) $version->version))
        ->toThrow(RuntimeException::class, 'fiducial');
});

test('a marker-bearing bundle whose manifest omits the fiducial hash is rejected', function (): void {
    seedE2Foundation();

    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $version = app(SaveAuthoredTemplateDraft::class)->handle(
        'SYN-OMR-MARKER-TEST-3',
        'Marker bundle manifest tamper test',
        [],
        $actor,
        null,
        true,
        markerBundleBlueprint(),
    );

    // Tamper: drop the fiducial from the manifest files map while the file
    // remains — the load path must notice the declared asset is unhashed.
    $manifest = Storage::disk('local')->json('omr-bundles/SYN-OMR-MARKER-TEST-3/v'.$version->version.'/manifest.json');
    unset($manifest['files']['omr_marker.jpg']);
    Storage::disk('local')->put(
        'omr-bundles/SYN-OMR-MARKER-TEST-3/v'.$version->version.'/manifest.json',
        json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
    );

    expect(fn () => NativeBundle::load('SYN-OMR-MARKER-TEST-3', (int) $version->version))
        ->toThrow(RuntimeException::class, 'fiducial asset hashes');
});

test('the pinned snapshot carries the fiducial bytes to the worker', function (): void {
    seedE2Foundation();

    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $version = app(SaveAuthoredTemplateDraft::class)->handle(
        'SYN-OMR-MARKER-TEST-4',
        'Marker bundle pin test',
        [],
        $actor,
        null,
        true,
        markerBundleBlueprint(),
    );

    $questions = [];
    $position = 1;
    foreach (markerBundleSections() as $section) {
        for ($no = 1; $no <= 25; $no++) {
            $questions[] = [
                'area_id' => (int) AptitudeArea::query()->where('code', $section['area_code'])->value('id'),
                'question_no' => $no,
                'position' => $position,
                'correct_option_code' => ['A', 'B', 'C', 'D'][($position - 1) % 4],
            ];
            $position++;
        }
    }
    $booklet = app(SaveAuthoredBookletVersion::class)->handle('SYN-OMR-MARKER-KEY', 'Marker key', $questions, $actor);

    // Activation is the authority that pins: it re-validates the bundle
    // (fiducial included) and projects the marker-bearing geometry.
    $pair = app(\App\Assessment\Actions\Omr\ActivateOmrConfiguration::class)->handle($version->fresh(), $booklet->fresh(), $actor);
    $templateVersion = $pair->templateVersion;

    $snapshot = OmrPinnedSnapshot::pin(
        $templateVersion->load(['areas.aptitudeArea', 'areas.questions.options']),
        $pair->bookletVersion->fresh()->load('questions.templateQuestion.options'),
    );
    expect($snapshot->nativeAssets)->toHaveKey('omr_marker.jpg')
        ->and($snapshot->nativeAssets['omr_marker.jpg'])->toBe(
            NativeBundle::load('SYN-OMR-MARKER-TEST-4', (int) $templateVersion->version)->assetBytes('omr_marker.jpg'),
        )
        ->and($snapshot->pageTemplates[1]['preProcessors'][0]['name'])->toBe('CropOnMarkers')
        ->and($snapshot->pageTemplates[1]['pageDimensions'])->toBe(OmrCapturePolicy::frameBudget([2060, 3144])['frame']);

    // The capture policy for the activated marker version is served unqualified.
    $policy = OmrCapturePolicy::forVersion($templateVersion);
    expect($policy['contract'])->toBe('nexiam.omr.capture-policy.v1')
        ->and($policy['frame_dimensions'])->toBe(OmrCapturePolicy::frameBudget([2060, 3144])['frame'])
        ->and($policy['calibration_status'])->toBe('unqualified');
});

test('the worker envelope carries the bounded asset bytes as base64', function (): void {
    seedE2Foundation();

    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $version = app(SaveAuthoredTemplateDraft::class)->handle(
        'SYN-OMR-MARKER-TEST-5',
        'Marker envelope test',
        [],
        $actor,
        null,
        true,
        markerBundleBlueprint(),
    );

    $bundle = NativeBundle::load('SYN-OMR-MARKER-TEST-5', (int) $version->version);

    $request = new OmrDetectionRequest(
        envelopeVersion: OmrDetectionRequest::ENVELOPE_VERSION,
        runId: 'marker-envelope-run',
        correlationId: 'marker-envelope-corr',
        imageBytes: str_repeat(chr(0), 64),
        inputHash: str_repeat('1', 64),
        imageMime: 'image/png',
        imageWidth: 4000,
        imageHeight: 3000,
        areas: [],
        items: [],
        identityRegions: [],
        nativeBundleHash: $bundle->bundleHash,
        manifestHash: str_repeat('b', 64),
        configHash: str_repeat('c', 64),
        bookletHash: str_repeat('d', 64),
        nativeTemplate: $bundle->pageTemplate(1),
        nativeConfig: [],
        nativeBundleMetadata: [
            'files' => $bundle->fileHashes,
            'engine' => $bundle->manifest['engine'],
            'bundle_version' => $bundle->manifest['bundle_version'],
        ],
        reviewManifest: [
            'fields' => [[
                'label' => 'APPLICANT_NUMBER_1',
                'page' => 1,
                'option' => '0',
                'region' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.02, 'h' => 0.02],
            ]],
        ],
        engineName: 'OMRChecker',
        engineCommit: str_repeat('e', 40),
        patchIdentity: 'nexiam-headless-overlay-v1',
        expectedFieldLabels: [],
        expectedOptionCodes: [],
        providerDeadline: 30.0,
        pageNo: 1,
        nativeAssets: ['omr_marker.jpg' => $bundle->assetBytes('omr_marker.jpg')],
    );

    $manifestMethod = new ReflectionMethod(\App\Assessment\Omr\OmrCheckerDetection::class, 'manifest');
    $manifestMethod->setAccessible(true);
    $manifest = $manifestMethod->invoke(new \App\Assessment\Omr\OmrCheckerDetection, $request);

    expect($manifest['native_assets']['omr_marker.jpg'])->toBe(base64_encode($bundle->assetBytes('omr_marker.jpg')))
        ->and($manifest['native_template']['preProcessors'][0]['name'])->toBe('CropOnMarkers')
        ->and($manifest['native_bundle_hash'])->toBe($bundle->bundleHash);
});
