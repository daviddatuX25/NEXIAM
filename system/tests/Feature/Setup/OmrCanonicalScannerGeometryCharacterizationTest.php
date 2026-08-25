<?php

use App\Assessment\Actions\Omr\Canonical\CompileOmrCanonicalDraft;
use App\Assessment\Actions\Omr\Canonical\MaterializeOmrCanonicalDraft;
use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\OmrTemplateGeometryProjector;
use App\Models\Assessment\OmrAnswerSheetPage;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateArea;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateQuestion;
use App\Models\Assessment\OmrTemplateQuestionOption;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * SCANNER GEOMETRY REPAIR (tasks 35.4–35.10): canonical compile now projects
 * the version-owned page/area/question/option rows through
 * `OmrTemplateGeometryProjector`, and grading reads per-item geometry through
 * `OmrReviewGeometryQuery` (area code + question position merge, never
 * `template_question_id` links). These tests assert the REPAIRED behavior —
 * the documented flip of the pre-cut `[0,0,1,1]` characterization.
 */

/**
 * Builds a real canonical compiled version through the domain pipeline:
 * qualified profile → draft → materialize → digital-verified → compile.
 */
function canonicalScannerGapCompiledVersion(User $actor): OmrAnswerSheetTemplateVersion
{
    $profile = OmrMarkerProfile::query()->create([
        'code' => 'scanner-gap.marker',
        'version' => 1,
        'family' => 'nexiam.fiducial',
        'asset_path' => 'app/Assessment/Omr/Fiducial/omr_marker.jpg',
        'asset_sha256' => hash_file('sha256', base_path('app/Assessment/Omr/Fiducial/omr_marker.jpg')),
        'size_mm' => 12,
        'quiet_zone_mm' => 3,
        'matching_config' => [],
        'status' => OmrMarkerProfile::STATUS_QUALIFIED,
        'qualification_evidence_hash' => hash('sha256', 'calibration'),
        'created_by_user_id' => $actor->id,
        'qualified_by_user_id' => $actor->id,
        'qualified_at' => now(),
    ]);

    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'CANONICAL-SCANNER-GAP', 'Scanner gap', 'html');
    $assets = app(OmrTemplateAssetStore::class);
    $source = $assets->putDraftAsset($draft, OmrTemplateAsset::KIND_SOURCE, 'source', 'text/plain');
    $raster = $assets->putDraftAsset(
        $draft,
        OmrTemplateAsset::KIND_RASTER_PAGE,
        (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        'image/png',
        1,
    );
    $draft->forceFill([
        'marker_profile_id' => $profile->id,
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
        'authoring_json' => [
            'source' => ['sha256' => $source->sha256, 'converter_version' => 'test', 'settings_hash' => hash('sha256', 'settings')],
            'pages' => [['page_no' => 1, 'asset_id' => $raster->id, 'confirmed' => true]],
            'marker' => ['insets_mm' => ['top' => 12, 'right' => 12, 'bottom' => 14, 'left' => 12]],
            'canonical' => ['settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297], 'composition_hash' => hash('sha256', 'composition'), 'raster_assets' => [['page_no' => 1, 'asset_id' => $raster->id]]],
            'mapping' => ['mapping_hash' => hash('sha256', 'mapping'), 'field_blocks' => ['Questions' => ['fieldType' => 'QTYPE_MCQ4', 'fieldLabels' => ['LOGIC_Q_001', 'LOGIC_Q_002'], 'bubblesGap' => 80, 'labelsGap' => 80, 'origin' => [200, 300], 'bubbleDimensions' => [50, 50]]]],
            'verification' => [],
        ],
    ])->save();

    $materialized = app(MaterializeOmrCanonicalDraft::class)->handle($draft->fresh(['markerProfile']), $actor, 0);
    $authoring = (array) $materialized->authoring_json;
    $authoring['verification'] = [
        'status' => 'passed',
        'artifact_hash' => $authoring['canonical']['artifact_set']['artifact_hash'],
        'hashes' => ['digital_verification' => hash('sha256', 'verification')],
    ];
    $materialized->forceFill(['stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED, 'authoring_json' => $authoring])->save();

    $compiled = app(CompileOmrCanonicalDraft::class)->handle($materialized->fresh(), $actor);

    return $compiled->compiledVersion->fresh();
}

test('canonical compile projects one idempotent version-owned geometry set', function (): void {
    seedE2Foundation();
    $actor = User::factory()->create();
    $version = canonicalScannerGapCompiledVersion($actor);

    // The manifest carries per-bubble geometry and the version-owned
    // projection rows now exist exactly once.
    $manifest = $version->review_manifest_json;
    expect($manifest)->not->toBeNull()
        ->and($manifest['fields'])->not->toBeEmpty()
        ->and(collect($manifest['fields'])->pluck('label')->unique()->count())->toBe(2)
        ->and(collect($manifest['fields'])->every(fn (array $field): bool => (float) ($field['region']['w'] ?? 0) > 0 && (float) ($field['region']['h'] ?? 0) > 0))->toBeTrue();

    $areas = OmrTemplateArea::query()->where('template_version_id', $version->id)->with(['aptitudeArea', 'questions.options'])->get();
    $questions = OmrTemplateQuestion::query()->whereIn('template_area_id', $areas->pluck('id'))->get();
    $options = OmrTemplateQuestionOption::query()->whereIn('template_question_id', $questions->pluck('id'))->get();

    expect($areas)->toHaveCount(1)
        ->and(OmrAnswerSheetPage::query()->where('template_version_id', $version->id)->count())->toBe(1)
        ->and($questions)->toHaveCount(2)
        ->and($options)->toHaveCount(8)
        ->and($areas->first()->aptitudeArea->code)->toBe('LOGIC')
        ->and($areas->first()->max_items)->toBe(2)
        ->and($areas->first()->projection_source)->toBe(OmrTemplateGeometryProjector::PROJECTION_SOURCE)
        ->and($version->authoring_manifest)->not->toBeNull();

    // Idempotent identical retry: same projection hash, no duplicate rows.
    $projector = app(OmrTemplateGeometryProjector::class);
    $retry = $projector->project($version, $version->review_manifest_json);
    expect($retry->reused)->toBeTrue()
        ->and($retry->projectionHash)->toBe($areas->first()->projection_manifest_hash)
        ->and(OmrTemplateArea::query()->where('template_version_id', $version->id)->count())->toBe(1)
        ->and(OmrTemplateQuestion::query()->whereIn('template_area_id', $areas->pluck('id'))->count())->toBe(2)
        ->and(OmrTemplateQuestionOption::query()->whereIn('template_question_id', $questions->pluck('id'))->count())->toBe(8);
});

test('grading Capture template items carry exact projected regions and declared options without template_question_id links', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];

    $version = canonicalScannerGapCompiledVersion($guidance);
    // Activation is gated on physical qualification (blocked); the version
    // rows are identical either way, so the pair is created directly.
    $version->forceFill([
        'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
        'activated_by_user_id' => $guidance->id,
        'activated_at' => now(),
    ])->save();

    $booklet = OmrQuestionBooklet::query()->create([
        'code' => 'SCANNER-GAP-BOOKLET',
        'name' => 'Scanner gap booklet',
    ]);
    $bookletVersion = OmrQuestionBookletVersion::query()->create([
        'booklet_id' => $booklet->id,
        'version' => 1,
        'status' => OmrQuestionBookletVersion::STATUS_ACTIVE,
        'answer_key_hash' => hash('sha256', 'scanner-gap-key'),
        'activated_by_user_id' => $guidance->id,
        'activated_at' => now(),
    ]);
    $area = AptitudeArea::query()->where('code', 'LOGIC')->firstOrFail();
    // Booklet questions are authored independently — no `template_question_id`
    // link exists; the geometry merge uses area code + question position.
    $bookletVersion->questions()->create([
        'aptitude_area_id' => $area->id,
        'question_position' => 1,
        'correct_option_code' => 'A',
    ]);
    $bookletVersion->questions()->create([
        'aptitude_area_id' => $area->id,
        'question_position' => 2,
        'correct_option_code' => 'B',
    ]);

    OmrTemplateBookletPair::query()->create([
        'template_version_id' => $version->id,
        'booklet_version_id' => $bookletVersion->id,
        'status' => OmrTemplateBookletPair::STATUS_ACTIVE,
        'approved_by_user_id' => $guidance->id,
        'approved_at' => now(),
    ]);

    app(CreateOmrCaptureBatch::class)->handle(
        $grading,
        $guidance,
        (int) $version->id,
        (int) $bookletVersion->id,
        'scanner-gap-'.uniqid(),
    );

    $projectedQuestion = OmrTemplateQuestion::query()
        ->whereHas('area', fn ($query) => $query->where('template_version_id', $version->id))
        ->where('question_no', 1)
        ->firstOrFail();
    $projectedQuestionTwo = OmrTemplateQuestion::query()
        ->whereHas('area', fn ($query) => $query->where('template_version_id', $version->id))
        ->where('question_no', 2)
        ->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('grading.sessions.omr', $grading))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assessment/Omr/Capture')
            ->has('batch.template.items', 2)
            // Exact projected regions — never the pre-cut [0,0,1,1] fallback.
            ->where('batch.template.items.0.region', [
                'x' => (float) $projectedQuestion->region_x,
                'y' => (float) $projectedQuestion->region_y,
                'w' => (float) $projectedQuestion->region_w,
                'h' => (float) $projectedQuestion->region_h,
            ])
            ->where('batch.template.items.0.question_no', 1)
            ->where('batch.template.items.0.area_code', 'LOGIC')
            ->where('batch.template.items.0.correct_option_code', 'A')
            ->where('batch.template.items.0.page_no', 1)
            ->where('batch.template.items.0.options', [
                ['code' => 'A', 'region' => ['x' => $projectedQuestion->options[0]->region_x, 'y' => $projectedQuestion->options[0]->region_y, 'w' => $projectedQuestion->options[0]->region_w, 'h' => $projectedQuestion->options[0]->region_h]],
                ['code' => 'B', 'region' => ['x' => $projectedQuestion->options[1]->region_x, 'y' => $projectedQuestion->options[1]->region_y, 'w' => $projectedQuestion->options[1]->region_w, 'h' => $projectedQuestion->options[1]->region_h]],
                ['code' => 'C', 'region' => ['x' => $projectedQuestion->options[2]->region_x, 'y' => $projectedQuestion->options[2]->region_y, 'w' => $projectedQuestion->options[2]->region_w, 'h' => $projectedQuestion->options[2]->region_h]],
                ['code' => 'D', 'region' => ['x' => $projectedQuestion->options[3]->region_x, 'y' => $projectedQuestion->options[3]->region_y, 'w' => $projectedQuestion->options[3]->region_w, 'h' => $projectedQuestion->options[3]->region_h]],
            ])
            ->where('batch.template.items.1.question_no', 2)
            ->where('batch.template.items.1.correct_option_code', 'B')
            ->where('batch.template.items.1.page_no', 1)
            ->where('batch.template.items.1.region', [
                'x' => (float) $projectedQuestionTwo->region_x,
                'y' => (float) $projectedQuestionTwo->region_y,
                'w' => (float) $projectedQuestionTwo->region_w,
                'h' => (float) $projectedQuestionTwo->region_h,
            ])
            ->where('batch.template.items.1.options', [
                ['code' => 'A', 'region' => ['x' => $projectedQuestionTwo->options[0]->region_x, 'y' => $projectedQuestionTwo->options[0]->region_y, 'w' => $projectedQuestionTwo->options[0]->region_w, 'h' => $projectedQuestionTwo->options[0]->region_h]],
                ['code' => 'B', 'region' => ['x' => $projectedQuestionTwo->options[1]->region_x, 'y' => $projectedQuestionTwo->options[1]->region_y, 'w' => $projectedQuestionTwo->options[1]->region_w, 'h' => $projectedQuestionTwo->options[1]->region_h]],
                ['code' => 'C', 'region' => ['x' => $projectedQuestionTwo->options[2]->region_x, 'y' => $projectedQuestionTwo->options[2]->region_y, 'w' => $projectedQuestionTwo->options[2]->region_w, 'h' => $projectedQuestionTwo->options[2]->region_h]],
                ['code' => 'D', 'region' => ['x' => $projectedQuestionTwo->options[3]->region_x, 'y' => $projectedQuestionTwo->options[3]->region_y, 'w' => $projectedQuestionTwo->options[3]->region_w, 'h' => $projectedQuestionTwo->options[3]->region_h]],
            ])
        );
});
