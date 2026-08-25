<?php

use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Actions\Omr\ActivateOmrConfiguration;
use App\Assessment\Actions\Omr\RegisterNativeBundle;
use App\Assessment\Omr\OmrTemplateGeometryProjector;
use App\Assessment\Omr\OmrTemplateProjectionException;
use App\Assessment\Omr\Queries\OmrReviewGeometryQuery;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Assessment\Actions\Omr\RunOmrSheetAnalysis;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrProcessingRun;
use App\Support\SensitiveAssetStore;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateArea;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\Assessment\OmrTemplateQuestion;
use App\Models\Assessment\OmrTemplateQuestionOption;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * FEATURE (task 35.10): `OmrTemplateGeometryProjector` + 
 * `OmrReviewGeometryQuery` produce complete MCQ4/MCQ5/int option sets,
 * merge a compatible booklet's exact correct options by area code +
 * question position, and block pair activation/analysis with typed
 * failures instead of emitting placeholder regions.
 */

/**
 * Deterministic review manifest over the given area/question/option sets.
 * Options are laid out as one horizontal row per question at 300 PPI.
 *
 * @param  array<string, list<list<string>>>  $questionsByArea  area code => questions (each a list of option codes)
 */
function syntheticScannerReviewManifest(array $questionsByArea, string $code = 'SYNTH-GEOMETRY'): array
{
    $fields = [];
    $pageWidth = 2480;
    $pageHeight = 3508;

    foreach ($questionsByArea as $areaCode => $questions) {
        foreach ($questions as $questionNo => $options) {
            foreach ($options as $optionIndex => $optionCode) {
                $x = 200 + $optionIndex * 120;
                $y = 200 + $questionNo * 120;
                $fields[] = [
                    'page' => 1,
                    'block' => $areaCode,
                    'label' => sprintf('%s_Q_%03d', $areaCode, $questionNo + 1),
                    'option' => (string) $optionCode,
                    'pixel' => ['x' => $x, 'y' => $y, 'w' => 48, 'h' => 48],
                    'region' => [
                        'x' => $x / $pageWidth,
                        'y' => $y / $pageHeight,
                        'w' => 48 / $pageWidth,
                        'h' => 48 / $pageHeight,
                    ],
                ];
            }
        }
    }

    return [
        'manifest_version' => 'nexiam.omr.review-manifest.v1',
        'template_code' => $code,
        'template_version' => 1,
        'bundle_hash' => hash('sha256', $code),
        'file_hashes' => [],
        'engine' => ['name' => 'OMRChecker', 'commit' => 'test', 'patch_set' => 'test'],
        'page_count' => 1,
        'pages' => [['page_no' => 1, 'page_dimensions' => ['width' => $pageWidth, 'height' => $pageHeight], 'field_count' => count($fields)]],
        'identity' => ['mode' => 'none', 'labels' => [], 'symbols' => [], 'free_form_supported' => false, 'birthdate' => null],
        'fields' => $fields,
    ];
}

function syntheticProjectedVersion(User $actor, array $manifest): OmrAnswerSheetTemplateVersion
{
    $template = OmrAnswerSheetTemplate::query()->create(['code' => $manifest['template_code'], 'name' => 'Synthetic geometry']);
    $version = OmrAnswerSheetTemplateVersion::query()->create([
        'template_id' => $template->id,
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
        'page_width' => 2480,
        'page_height' => 3508,
        'identity_regions_json' => [],
        'provider_config_json' => ['driver' => 'fake'],
        'config_hash' => hash('sha256', 'config'),
        'template_json_hash' => hash('sha256', 'template'),
        'review_manifest_json' => $manifest,
        'review_manifest_hash' => hash('sha256', json_encode($manifest, JSON_UNESCAPED_SLASHES)),
        'native_bundle_hash' => hash('sha256', 'bundle'),
        'engine_name' => 'OMRChecker',
        'engine_commit' => 'test',
        'patch_identity' => 'test',
        'authoring_manifest' => [],
    ]);

    return $version;
}

test('projector persists complete MCQ4, MCQ5, and int option sets with declared order', function (): void {
    seedE2Foundation();
    $actor = User::factory()->create();
    $manifest = syntheticScannerReviewManifest([
        'LOGIC' => [['A', 'B', 'C', 'D'], ['A', 'B', 'C', 'D']],
        'SPATIAL' => [['A', 'B', 'C', 'D', 'E']],
        'MEMORY' => [['0', '1', '2', '3', '4', '5', '6', '7', '8', '9']],
    ]);
    $version = syntheticProjectedVersion($actor, $manifest);

    $result = app(OmrTemplateGeometryProjector::class)->project($version, $manifest);

    expect($result->pageCount)->toBe(1)
        ->and($result->areaCount)->toBe(3)
        ->and($result->questionCount)->toBe(4)
        ->and($result->optionCount)->toBe(4 + 4 + 5 + 10);

    $options = OmrTemplateQuestionOption::query()
        ->whereIn('template_question_id', OmrTemplateQuestion::query()->pluck('id'))
        ->orderBy('template_question_id')
        ->orderBy('display_order')
        ->get();

    $mcq4 = OmrTemplateQuestion::query()->whereHas('area.aptitudeArea', fn ($query) => $query->where('code', 'LOGIC'))->where('question_no', 1)->firstOrFail();
    expect($mcq4->options->sortBy('display_order')->pluck('code')->all())->toBe(['A', 'B', 'C', 'D'])
        ->and($mcq4->options->pluck('display_order')->sort()->values()->all())->toBe([1, 2, 3, 4]);

    $mcq5 = OmrTemplateQuestion::query()->whereHas('area.aptitudeArea', fn ($query) => $query->where('code', 'SPATIAL'))->firstOrFail();
    expect($mcq5->options->sortBy('display_order')->pluck('code')->all())->toBe(['A', 'B', 'C', 'D', 'E']);

    $int = OmrTemplateQuestion::query()->whereHas('area.aptitudeArea', fn ($query) => $query->where('code', 'MEMORY'))->firstOrFail();
    expect($int->options->sortBy('display_order')->pluck('code')->all())->toBe(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'])
        ->and($int->options->sortBy('display_order')->pluck('display_order')->all())->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

    // All option regions are exact normalized bubbles, never placeholders.
    expect($options->every(fn ($option): bool => (float) $option->region_w > 0 && (float) $option->region_h > 0
        && (float) $option->region_x + (float) $option->region_w <= 1.0
        && (float) $option->region_y + (float) $option->region_h <= 1.0))->toBeTrue();
});

test('projector replays identical manifests idempotently and rejects different manifests as stale', function (): void {
    seedE2Foundation();
    $actor = User::factory()->create();
    $manifest = syntheticScannerReviewManifest(['LOGIC' => [['A', 'B', 'C', 'D'], ['A', 'B', 'C', 'D']]]);
    $version = syntheticProjectedVersion($actor, $manifest);

    $projector = app(OmrTemplateGeometryProjector::class);
    $first = $projector->project($version, $manifest);
    $retry = $projector->project($version, $manifest);

    expect($retry->reused)->toBeTrue()
        ->and($retry->projectionHash)->toBe($first->projectionHash)
        ->and(OmrTemplateQuestion::query()->count())->toBe(2)
        ->and(OmrTemplateQuestionOption::query()->count())->toBe(8);

    // A different manifest against the already-projected version is rejected.
    $moved = $manifest;
    $moved['fields'][0]['region']['x'] = 0.01;
    expect(fn () => $projector->project($version, $moved))
        ->toThrow(OmrTemplateProjectionException::class, 'geometry_projection_stale');
});

test('currentFor blocks missing and stale projections with typed failures', function (): void {
    seedE2Foundation();
    $actor = User::factory()->create();
    $manifest = syntheticScannerReviewManifest(['LOGIC' => [['A', 'B', 'C', 'D']]]);
    $version = syntheticProjectedVersion($actor, $manifest);
    $projector = app(OmrTemplateGeometryProjector::class);

    expect(fn () => $projector->currentFor($version))
        ->toThrow(OmrTemplateProjectionException::class, 'geometry_projection_missing');

    $projector->project($version, $manifest);
    expect($projector->currentFor($version)->questionCount)->toBe(1);

    // Mutating the version manifest after projection makes it stale.
    $version->update(['review_manifest_json' => syntheticScannerReviewManifest(['LOGIC' => [['A', 'B', 'C', 'D'], ['A', 'B', 'C', 'D']]])]);
    expect(fn () => $projector->currentFor($version->fresh()))
        ->toThrow(OmrTemplateProjectionException::class, 'geometry_projection_stale');
});

test('compatible booklet merge returns exact correct options by area code and position', function (): void {
    seedE2Foundation();
    $actor = User::factory()->create();
    $manifest = syntheticScannerReviewManifest(['LOGIC' => [['A', 'B', 'C', 'D'], ['A', 'B', 'C', 'D']]]);
    $version = syntheticProjectedVersion($actor, $manifest);
    app(OmrTemplateGeometryProjector::class)->project($version, $manifest);

    $booklet = OmrQuestionBooklet::query()->create(['code' => 'SYNTH-BOOKLET', 'name' => 'Synthetic booklet']);
    $bookletVersion = OmrQuestionBookletVersion::query()->create([
        'booklet_id' => $booklet->id,
        'version' => 1,
        'status' => OmrQuestionBookletVersion::STATUS_DRAFT,
        'answer_key_hash' => hash('sha256', 'key'),
    ]);
    $area = AptitudeArea::query()->where('code', 'LOGIC')->firstOrFail();
    $bookletVersion->questions()->create(['aptitude_area_id' => $area->id, 'question_position' => 1, 'correct_option_code' => 'B']);
    $bookletVersion->questions()->create(['aptitude_area_id' => $area->id, 'question_position' => 2, 'correct_option_code' => 'C']);

    $items = app(OmrReviewGeometryQuery::class)->itemsFor($version, $bookletVersion->load('questions.aptitudeArea'));

    expect($items)->toHaveCount(2)
        ->and($items[0]['position'])->toBe(1)
        ->and($items[0]['area_code'])->toBe('LOGIC')
        ->and($items[0]['question_no'])->toBe(1)
        ->and($items[0]['correct_option_code'])->toBe('B')
        ->and($items[0]['page_no'])->toBe(1)
        ->and(array_map(static fn (array $option): array => [
            'code' => $option['code'],
            'region' => array_map(static fn (float $value): float => round($value, 12), $option['region']),
        ], $items[0]['options']))->toBe([
            ['code' => 'A', 'region' => ['x' => round(200 / 2480, 12), 'y' => round(200 / 3508, 12), 'w' => round(48 / 2480, 12), 'h' => round(48 / 3508, 12)]],
            ['code' => 'B', 'region' => ['x' => round(320 / 2480, 12), 'y' => round(200 / 3508, 12), 'w' => round(48 / 2480, 12), 'h' => round(48 / 3508, 12)]],
            ['code' => 'C', 'region' => ['x' => round(440 / 2480, 12), 'y' => round(200 / 3508, 12), 'w' => round(48 / 2480, 12), 'h' => round(48 / 3508, 12)]],
            ['code' => 'D', 'region' => ['x' => round(560 / 2480, 12), 'y' => round(200 / 3508, 12), 'w' => round(48 / 2480, 12), 'h' => round(48 / 3508, 12)]],
        ])
        ->and($items[1]['correct_option_code'])->toBe('C')
        ->and($items[1]['region']['w'])->toBeGreaterThan(0);

    // Coverage mismatch: booklet drops the second question.
    $bookletVersion->questions()->where('question_position', 2)->delete();
    expect(fn () => app(OmrReviewGeometryQuery::class)->itemsFor($version, $bookletVersion->load('questions.aptitudeArea')))
        ->toThrow(OmrTemplateProjectionException::class, 'pair_geometry_coverage_mismatch');

    // Option-set mismatch: booklet answers with an undeclared option.
    $bookletVersion->questions()->create(['aptitude_area_id' => $area->id, 'question_position' => 2, 'correct_option_code' => 'Z']);
    expect(fn () => app(OmrReviewGeometryQuery::class)->itemsFor($version, $bookletVersion->load('questions.aptitudeArea')))
        ->toThrow(OmrTemplateProjectionException::class, 'pair_option_set_mismatch');
});

test('pair activation blocks a booklet option outside the declared set', function (): void {
    seedE2Foundation();
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];

    $code = 'TYPED-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));
    $bundleDir = base_path('app/Assessment/Omr/NativeBundles/'.$code);

    try {
        $base = base_path('app/Assessment/Omr/NativeBundles/SYN-OMR-ONE-PAGE/v3');
        $files = [
            'template.json' => file_get_contents($base.'/template.json'),
            'config.json' => file_get_contents($base.'/config.json'),
            'manifest.json' => file_get_contents($base.'/manifest.json'),
            'reference-page-1.svg' => file_get_contents($base.'/reference-page-1.svg'),
            'reference-page-2.svg' => file_get_contents($base.'/reference-page-2.svg'),
        ];
        $manifest = json_decode($files['manifest.json'], true);
        $manifest['template_code'] = $code;
        $files['manifest.json'] = json_encode($manifest, JSON_UNESCAPED_SLASHES);

        $templateVersion = app(RegisterNativeBundle::class)->handle($code, $files, $guidance);

        $booklet = OmrQuestionBooklet::query()->create(['code' => 'TYPED-BOOKLET-'.substr($code, -6), 'name' => 'Typed booklet']);
        $bookletVersion = OmrQuestionBookletVersion::query()->create([
            'booklet_id' => $booklet->id,
            'version' => 1,
            'status' => 'draft',
            'answer_key_hash' => hash('sha256', 'typed-key'),
        ]);

        $areas = AptitudeArea::query()->where('is_active', true)->orderBy('code')->get();
        $position = 1;
        foreach ($areas as $area) {
            for ($i = 1; $i <= (int) $area->max_items; $i++) {
                $bookletVersion->questions()->create([
                    'aptitude_area_id' => $area->id,
                    'question_position' => $position++,
                    'correct_option_code' => $i === 1 ? 'Z' : ['A', 'B', 'C', 'D'][($i - 1) % 4],
                ]);
            }
        }

        expect(fn () => app(ActivateOmrConfiguration::class)->handle($templateVersion, $bookletVersion, $guidance))
            ->toThrow(OmrTemplateProjectionException::class, 'pair_option_set_mismatch');

        // The failed activation leaves no projection rows and no pair.
        expect(OmrTemplateArea::query()->where('template_version_id', $templateVersion->id)->count())->toBe(0)
            ->and($templateVersion->fresh()->status)->toBe('draft')
            ->and($templateVersion->fresh()->pairs()->count())->toBe(0);
    } finally {
        Storage::disk('local')->deleteDirectory('omr-bundles/'.$code);
    }
});
test('analysis blocks with geometry_projection_missing when the pair template has no projection', function (): void {
    seedE2Foundation();
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];

    // A pair whose template version never received a projection (e.g. a
    // legacy activated version) must block analysis with the typed failure
    // instead of degrading to placeholder regions.
    $template = OmrAnswerSheetTemplate::query()->create(['code' => 'UNPROJECTED', 'name' => 'Unprojected']);
    $version = OmrAnswerSheetTemplateVersion::query()->create([
        'template_id' => $template->id,
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
        'page_width' => 2060,
        'page_height' => 3144,
        'identity_regions_json' => [],
        'provider_config_json' => ['driver' => 'fake'],
        'config_hash' => hash('sha256', 'config'),
        'template_json_hash' => hash('sha256', 'template'),
        'review_manifest_json' => [],
        'review_manifest_hash' => hash('sha256', 'review'),
        'native_bundle_json' => ['template' => ['pageDimensions' => [2060, 3144], 'bubbleDimensions' => [48, 48]], 'config' => [], 'manifest' => [], 'files' => []],
        'native_bundle_hash' => hash('sha256', 'bundle'),
        'engine_name' => 'OMRChecker',
        'engine_commit' => 'test',
        'patch_identity' => 'test',
        'activated_by_user_id' => $guidance->id,
        'activated_at' => now(),
    ]);
    $booklet = OmrQuestionBooklet::query()->create(['code' => 'UNPROJECTED-BOOKLET', 'name' => 'Unprojected booklet']);
    $bookletVersion = OmrQuestionBookletVersion::query()->create([
        'booklet_id' => $booklet->id,
        'version' => 1,
        'status' => OmrQuestionBookletVersion::STATUS_ACTIVE,
        'answer_key_hash' => hash('sha256', 'key'),
        'activated_by_user_id' => $guidance->id,
        'activated_at' => now(),
    ]);
    OmrTemplateBookletPair::query()->create([
        'template_version_id' => $version->id,
        'booklet_version_id' => $bookletVersion->id,
        'status' => OmrTemplateBookletPair::STATUS_ACTIVE,
        'approved_by_user_id' => $guidance->id,
        'approved_at' => now(),
    ]);

    $batch = app(CreateOmrCaptureBatch::class)->handle(
        $grading,
        $guidance,
        (int) $version->id,
        (int) $bookletVersion->id,
        'unprojected-'.uniqid(),
    );

    $assetStore = app(SensitiveAssetStore::class);
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
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
        'provider_name' => 'nexiam.fake',
        'provider_version' => '1',
        'request_version' => OmrDetectionRequest::ENVELOPE_VERSION,
        'expected_sheet_version' => 0,
        'input_hash' => hash('sha256', $bytes),
        'template_hash' => $version->native_bundle_hash,
        'booklet_hash' => $bookletVersion->answer_key_hash,
        'native_bundle_hash' => $version->native_bundle_hash,
        'manifest_hash' => $version->review_manifest_hash,
        'config_hash' => $version->config_hash,
        'patch_identity' => $version->patch_identity,
    ]);
    $sheet->forceFill(['current_processing_run_id' => $run->id])->save();

    app(RunOmrSheetAnalysis::class)->handle($run->id);

    expect($run->fresh()->diagnostic_code)->toBe('geometry_projection_missing')
        ->and($run->fresh()->status)->toBe(OmrProcessingRun::STATUS_RETRYABLE_FAILED);
});
