<?php

use App\Assessment\Actions\Omr\Canonical\CompileOmrCanonicalDraft;
use App\Assessment\Actions\Omr\Canonical\MaterializeOmrCanonicalDraft;
use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Models\Assessment\OmrAnswerSheetPage;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateArea;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * CHARACTERIZATION (task 35.2): canonical compile persists `review_manifest_json`
 * on the immutable version but never materializes the version-owned
 * page/area/question/option rows (`omr_template_areas`,
 * `omr_template_questions`, `omr_template_question_options`,
 * `omr_answer_sheet_pages`) that the legacy `ActivateOmrConfiguration`
 * projection creates. The grading `Capture.svelte` item projection therefore
 * degrades to `[0,0,1,1]` fallback regions and empty option lists.
 *
 * These tests pin the CURRENT (gap) behavior honestly. They are expected to
 * flip when tasks 35.4–35.10 replace the booklet-question→template-question
 * geometry projection with `OmrTemplateGeometryProjector` +
 * `OmrReviewGeometryQuery`.
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
            'mapping' => ['mapping_hash' => hash('sha256', 'mapping'), 'field_blocks' => ['Questions' => ['fieldType' => 'QTYPE_MCQ4', 'fieldLabels' => ['Q_001', 'Q_002'], 'bubblesGap' => 80, 'labelsGap' => 80, 'origin' => [200, 300], 'bubbleDimensions' => [50, 50]]]],
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

test('CHARACTERIZATION: canonical compile stores review_manifest_json but no version-owned per-item geometry rows', function (): void {
    $actor = User::factory()->create();
    $version = canonicalScannerGapCompiledVersion($actor);

    // The manifest exists and carries per-bubble geometry the grading
    // projection could consume…
    $manifest = $version->review_manifest_json;
    expect($manifest)->not->toBeNull()
        ->and($manifest['fields'])->not->toBeEmpty()
        ->and(collect($manifest['fields'])->pluck('label')->unique()->count())->toBe(2)
        ->and(collect($manifest['fields'])->every(fn (array $field): bool => (float) ($field['region']['w'] ?? 0) > 0 && (float) ($field['region']['h'] ?? 0) > 0))->toBeTrue();

    // …but the version-owned projection rows never exist.
    expect(OmrTemplateArea::query()->where('template_version_id', $version->id)->count())->toBe(0)
        ->and(OmrAnswerSheetPage::query()->where('template_version_id', $version->id)->count())->toBe(0)
        ->and($version->authoring_manifest)->not->toBeNull();
});

test('CHARACTERIZATION: grading Capture template items degrade to [0,0,1,1] fallback regions and empty options without booklet templateQuestion links', function (): void {
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
    $area = AptitudeArea::query()->where('is_active', true)->firstOrFail();
    // Booklet questions are authored independently — no `template_question_id`
    // link exists because canonical compile never creates template questions.
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

    $this->actingAs($guidance)
        ->get(route('grading.sessions.omr', $grading))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assessment/Omr/Capture')
            ->has('batch.template.items', 2)
            // Fallback region `[0,0,1,1]` — the known gap; per-item regions
            // stay usable only after OmrTemplateGeometryProjector lands (35.4).
            ->where('batch.template.items.0.region', ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1])
            ->where('batch.template.items.0.options', [])
            ->where('batch.template.items.0.page_no', 1)
            ->where('batch.template.items.1.region', ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1])
            ->where('batch.template.items.1.options', [])
            ->where('batch.template.items.1.page_no', 1)
        );
});
