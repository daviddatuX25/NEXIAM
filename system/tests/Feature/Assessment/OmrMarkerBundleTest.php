<?php

use App\Assessment\Actions\Omr\ActivateOmrConfiguration;
use App\Assessment\Actions\Omr\SaveAuthoredBookletVersion;
use App\Assessment\Actions\Omr\SaveAuthoredTemplateDraft;
use App\Assessment\Omr\Fiducial\OmrFiducial;
use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\OmrCapturePolicy;
use App\Assessment\Omr\OmrCheckerDetection;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrPinnedSnapshot;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\OmrSourceImage;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

beforeEach(function (): void {
    Storage::fake('local');
});

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

    $bundle = NativeBundle::loadForVersion($version);
    expect($bundle->hasMarkerPages())->toBeTrue()
        ->and($bundle->markerAssetName())->toBe('omr_marker.jpg')
        ->and($bundle->fileHashes)->toHaveKey('omr_marker.jpg')
        ->and($bundle->assetBytes('omr_marker.jpg'))->toBe(OmrFiducial::assetBytes())
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

    Storage::disk('local')->delete($version->native_bundle_json['relative_dir'].'/omr_marker.jpg');

    expect(fn () => NativeBundle::loadForVersion($version))
        ->toThrow(RuntimeException::class);
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
    $manifest = Storage::disk('local')->json($version->native_bundle_json['relative_dir'].'/manifest.json');
    unset($manifest['files']['omr_marker.jpg']);
    Storage::disk('local')->put(
        $version->native_bundle_json['relative_dir'].'/manifest.json',
        json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
    );

    expect(fn () => NativeBundle::loadForVersion($version))
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
    $disk = Storage::disk('local');
    $relativeDir = (string) $version->native_bundle_json['relative_dir'];
    $configPath = $relativeDir.'/config.json';
    $config = json_decode($disk->get($configPath), true, 512, JSON_THROW_ON_ERROR);
    $config['threshold_params']['GAMMA_LOW'] = 1.0;
    $configBytes = json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION);
    $disk->put($configPath, $configBytes);
    $manifest = json_decode($disk->get($relativeDir.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $manifest['files']['config.json'] = hash('sha256', $configBytes);
    $disk->put($relativeDir.'/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    $bundle = NativeBundle::loadFromDirectory($disk->path($relativeDir), (string) $version->template->code, (int) $version->version);
    $persistedBundle = $version->native_bundle_json;
    $persistedBundle['config']['threshold_params']['GAMMA_LOW'] = 1;
    $persistedBundle['files'] = $bundle->fileHashes;
    $persistedBundle['manifest'] = $bundle->manifest;
    $physicalSections = [];
    foreach ($bundle->pages as $pageIndex => $page) {
        foreach ($page['fieldBlocks'] as $block) {
            foreach (array_merge(...array_map(NativeBundle::expandLabel(...), $block['fieldLabels'])) as $label) {
                if (! preg_match('/^(.+)_Q_(\d+)$/', $label, $parts)) {
                    continue;
                }
                $sectionKey = $pageIndex.':'.$parts[1];
                $physicalSections[$sectionKey] ??= [
                    'printed_section_uid' => (string) Str::uuid(),
                    'name' => $parts[1],
                    'page_no' => $pageIndex + 1,
                    'numbering' => ['start' => 1, 'step' => 1],
                    'expected_item_count' => 0,
                    'items' => [],
                ];
                $physicalSections[$sectionKey]['items'][] = [
                    'physical_item_uid' => (string) Str::uuid(),
                    'native_label' => $label,
                    'page_no' => $pageIndex + 1,
                    'printed_number' => (int) $parts[2],
                    'option_tokens' => ['A', 'B', 'C', 'D'],
                ];
                $physicalSections[$sectionKey]['expected_item_count']++;
            }
        }
    }
    $authoringManifest = $version->authoring_manifest;
    $authoringManifest['physical_inventory'] = ['revision' => 1, 'sections' => array_values($physicalSections)];
    $version->forceFill([
        'config_hash' => hash('sha256', $configBytes),
        'native_bundle_hash' => $bundle->bundleHash,
        'native_bundle_json' => $persistedBundle,
        'authoring_manifest' => $authoringManifest,
    ])->save();

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
    $pair = app(ActivateOmrConfiguration::class)->handle($version->fresh(), $booklet->fresh(), $actor);
    $templateVersion = $pair->templateVersion;

    $snapshot = OmrPinnedSnapshot::pin(
        $templateVersion->load(['areas.aptitudeArea', 'areas.questions.options']),
        $pair->bookletVersion->fresh()->load('questions.templateQuestion.options'),
    );
    $imageBytes = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $sourceImage = OmrSourceImage::fromBytes($imageBytes);
    $request = new OmrDetectionRequest(
        envelopeVersion: OmrDetectionRequest::ENVELOPE_VERSION,
        runId: 'lossy-config-production-request',
        correlationId: 'omr:lossy-config-production-request',
        imageBytes: $imageBytes,
        inputHash: $sourceImage->inputHash,
        imageMime: 'image/png',
        imageWidth: $sourceImage->width,
        imageHeight: $sourceImage->height,
        areas: $snapshot->areas,
        items: $snapshot->items,
        identityRegions: $snapshot->identityRegions,
        nativeBundleHash: $snapshot->nativeBundleHash,
        manifestHash: $snapshot->manifestHash,
        configHash: $snapshot->configHash,
        bookletHash: $snapshot->bookletHash,
        nativeTemplate: $snapshot->pageTemplates[1],
        nativeConfigJson: $snapshot->nativeConfigJson,
        nativeBundleMetadata: $snapshot->nativeBundleMetadata,
        reviewManifest: $snapshot->reviewManifest,
        engineName: $snapshot->engineName,
        engineCommit: $snapshot->engineCommit,
        patchIdentity: $snapshot->patchIdentity,
        expectedFieldLabels: $snapshot->expectedFieldLabels,
        expectedOptionCodes: $snapshot->expectedOptionCodes,
        registrationMode: $templateVersion->registration_mode,
        templateVersionId: (int) $templateVersion->id,
        candidateTargetId: null,
        sourceImage: $sourceImage,
        pageNo: 1,
        providerDeadline: 30.0,
    );
    config(['omr.omrchecker.signing_secret' => str_repeat('s', 40)]);
    Http::preventStrayRequests();
    Http::fake(['*/detections' => Http::response('Worker unavailable', 503)]);
    $result = (new OmrCheckerDetection)->detect($request);

    expect($snapshot->nativeAssets)->toHaveKey('omr_marker.jpg')
        ->and($snapshot->nativeAssets['omr_marker.jpg'])->toBe(
            NativeBundle::loadForVersion($templateVersion)->assetBytes('omr_marker.jpg'),
        )
        ->and($snapshot->pageTemplates[1]['preProcessors'][0]['name'])->toBe('CropOnMarkers')
        ->and($snapshot->pageTemplates[1]['pageDimensions'])->toBe(OmrCapturePolicy::frameBudget([2060, 3144])['frame']);
    expect($snapshot->nativeConfig['threshold_params']['GAMMA_LOW'])->toBe(1.0)
        ->and($request->nativeConfig['threshold_params']['GAMMA_LOW'])->toBe(1.0)
        ->and($result->safeDiagnosticCode)->toBe('worker_http_503');
    Http::assertSent(static fn ($outgoing): bool => str_contains(
        $outgoing->body(),
        json_encode($configBytes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
    ) && ! str_contains($outgoing->body(), '"native_config":'));

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

    $bundle = NativeBundle::loadForVersion($version);

    $imageBytes = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

    $request = new OmrDetectionRequest(
        envelopeVersion: OmrDetectionRequest::ENVELOPE_VERSION,
        runId: 'marker-envelope-run',
        correlationId: 'marker-envelope-corr',
        imageBytes: $imageBytes,
        inputHash: hash('sha256', $imageBytes),
        imageMime: 'image/png',
        imageWidth: 1,
        imageHeight: 1,
        areas: [],
        items: [],
        identityRegions: [],
        nativeBundleHash: $bundle->bundleHash,
        manifestHash: str_repeat('b', 64),
        configHash: (string) $bundle->fileHashes['config.json'],
        bookletHash: str_repeat('d', 64),
        nativeTemplate: $bundle->pageTemplate(1),
        nativeConfigJson: $bundle->assetBytes('config.json'),
        nativeBundleMetadata: [
            'files' => $bundle->fileHashes,
            'engine' => $bundle->manifest['engine'],
            'bundle_version' => $bundle->manifest['bundle_version'],
            'registration_mode' => OmrRegistrationMode::FiducialAnchored->value,
            'qr_mode' => 'qr_signed',
            'compiled_pages' => [['page_no' => 1]],
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
        registrationMode: OmrRegistrationMode::FiducialAnchored,
        templateVersionId: (int) $version->id,
        candidateTargetId: null,
        sourceImage: OmrSourceImage::fromBytes($imageBytes),
        pageNo: 1,
        nativeAssets: ['omr_marker.jpg' => $bundle->assetBytes('omr_marker.jpg')],
    );

    $manifestMethod = new ReflectionMethod(OmrCheckerDetection::class, 'manifest');
    $manifestMethod->setAccessible(true);
    $manifest = $manifestMethod->invoke(new OmrCheckerDetection, $request);

    expect($manifest['native_assets']['omr_marker.jpg'])->toBe(base64_encode($bundle->assetBytes('omr_marker.jpg')))
        ->and($manifest['native_template']['preProcessors'][0]['name'])->toBe('CropOnMarkers')
        ->and($manifest['native_bundle_hash'])->toBe($bundle->bundleHash);
});
