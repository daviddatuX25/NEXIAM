<?php

use App\Assessment\Actions\Omr\Canonical\SaveOmrCanonicalOverlay;
use App\Assessment\Actions\Omr\Canonical\SelectOmrMarkerProfile;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrOverlayException;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('draft autosave rejects stale lock versions and preserves server state', function (): void {
    $actor = User::factory()->create();
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'AUTHORING-CONFLICT', 'Conflict', 'html');
    $saved = app(OmrAuthoringDraftService::class)->saveAuthoring($draft, $actor, ['source' => ['hash' => 'a']], 0);

    expect($saved->lock_version)->toBe(1)
        ->and(fn () => app(OmrAuthoringDraftService::class)->saveAuthoring($saved, $actor, ['source' => ['hash' => 'b']], 0))
        ->toThrow(OmrAuthoringDraftException::class, 'concurrency_conflict')
        ->and($saved->fresh()->authoring_json)->toBe(['source' => ['hash' => 'a']]);
});

test('overlay rejects applicant tags and persists fixed scope boxes', function (): void {
    $actor = User::factory()->create();
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'AUTHORING-OVERLAY', 'Overlay', 'html');
    $draft->forceFill(['stage' => OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED])->save();
    $valid = ['boxes' => [[
        'tag' => 'assessment', 'scope' => 'template', 'x' => 10, 'y' => 10, 'width' => 100, 'height' => 30, 'overflow' => 'truncate',
    ]]];
    $saved = app(SaveOmrCanonicalOverlay::class)->handle($draft, $actor, $valid, 0);

    expect($saved->authoring_json['overlay']['boxes'][0]['tag'])->toBe('assessment')
        ->and(fn () => app(SaveOmrCanonicalOverlay::class)->handle($saved, $actor, ['boxes' => [[...$valid['boxes'][0], 'tag' => 'applicant_name']]], 1))
        ->toThrow(OmrOverlayException::class, 'tag_forbidden');
});

test('marker profile selection preserves uploaded pages and authoring state', function (): void {
    $actor = User::factory()->create();
    $profile = OmrMarkerProfile::query()->create([
        'code' => 'authoring-marker',
        'version' => 1,
        'family' => 'omrchecker.bullseye',
        'asset_path' => 'app/Assessment/Omr/Fiducial/omr_marker.jpg',
        'asset_sha256' => hash_file('sha256', base_path('app/Assessment/Omr/Fiducial/omr_marker.jpg')),
        'matching_config' => [],
        'status' => OmrMarkerProfile::STATUS_CANDIDATE,
        'created_by_user_id' => $actor->id,
    ]);
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'AUTHORING-MARKER', 'Marker', 'image');
    $draft->forceFill([
        'stage' => OmrTemplateAuthoringDraft::STAGE_SOURCE_CONFIRMED,
        'authoring_json' => [
            'pages' => [['page_no' => 1, 'asset_id' => 123, 'confirmed' => true]],
            'studio' => ['stage1Mode' => 'upload'],
            'mapping' => ['field_blocks' => ['Question' => ['fieldType' => 'QTYPE_MCQ4']]],
        ],
    ])->save();

    $saved = app(SelectOmrMarkerProfile::class)->handle($draft->fresh(), $actor, $profile, [
        'top' => 12, 'right' => 12, 'bottom' => 12, 'left' => 12,
    ], 0);

    expect($saved->authoring_json['pages'])->toHaveCount(1)
        ->and($saved->authoring_json['studio']['stage1Mode'])->toBe('upload')
        ->and($saved->authoring_json['mapping']['field_blocks'])->toHaveKey('Question')
        ->and($saved->marker_profile_id)->toBe($profile->id);
});

test('atomic studio endpoint saves mapping, overlays, and studio state without cross-invalidation', function (): void {
    $actor = User::factory()->create();
    \Spatie\Permission\Models\Permission::findOrCreate(\App\Authorization\CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(\App\Authorization\CapabilityCatalog::GUIDANCE_SCORING_OMR);

    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'STUDIO-ATOMIC', 'Atomic Studio', 'preset');
    $draft->forceFill([
        'stage' => OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED,
        'authoring_json' => [
            'pages' => [[
                'page_no' => 1,
                'asset_id' => 987,
                'confirmed' => true,
            ]],
            'canonical' => ['content_zones' => [], 'marker_zones' => []],
        ],
    ])->save();

    $payload = [
        'overlay' => [
            'boxes' => [
                [
                    'tag' => 'header_title',
                    'scope' => 'template',
                    'x' => 200,
                    'y' => 200,
                    'width' => 1500,
                    'height' => 150,
                    'overflow' => 'shrink',
                    'font_size' => 14.0,
                    'content' => 'ENTRANCE EXAMINATION',
                ],
                [
                    'tag' => 'signature_box',
                    'scope' => 'template',
                    'x' => 1200,
                    'y' => 2500,
                    'width' => 600,
                    'height' => 200,
                    'overflow' => 'truncate',
                    'content' => 'EXAMINEE SIGNATURE',
                ],
            ],
        ],
        'mapping' => [
            'page_width' => 2480,
            'page_height' => 3508,
            'page_count' => 1,
            'blocks' => [
                [
                    'name' => 'Section_1',
                    'page_no' => 1,
                    'field_type' => 'QTYPE_MCQ4',
                    'label_prefix' => 'q',
                    'rows' => 10,
                    'options' => 4,
                    'origin_x' => 200,
                    'origin_y' => 500,
                    'width' => 600,
                    'height' => 800,
                    'option_pitch_px' => 100,
                    'row_pitch_px' => 80,
                    'bubble_width_px' => 50,
                    'bubble_height_px' => 50,
                ],
            ],
        ],
        'studio' => [
            'sections' => [
                [
                    'id' => 'Section_1',
                    'name' => 'Section 1',
                    'posX' => 20,
                    'posY' => 50,
                    'questionsCount' => 10,
                    'startQ' => 1,
                ],
            ],
            'marginMm' => 15,
            'markerPattern' => 'square',
        ],
        'lock_version' => 0,
        'idempotency_key' => 'studio-save-key-1',
    ];

    $response = $this->actingAs($actor)->putJson(
        route('setup.omr.templates.canonical.drafts.studio', $draft),
        $payload
    );

    $response->assertOk()
        ->assertJson([
            'status' => 'saved',
            'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
            'lock_version' => 1,
        ]);

    $fresh = $draft->fresh();
    expect($fresh->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE)
        ->and($fresh->lock_version)->toBe(1)
        ->and($fresh->authoring_json['pages'])->toHaveCount(1)
        ->and($fresh->authoring_json['overlay']['boxes'])->toHaveCount(2)
        ->and($fresh->authoring_json['overlay']['boxes'][0]['content'])->toBe('ENTRANCE EXAMINATION')
        ->and($fresh->authoring_json['mapping']['field_blocks'])->toHaveKey('Section_1')
        ->and($fresh->authoring_json['studio']['marginMm'])->toBe(15);
    // Navigation-only saves preserve an already materialized printable artifact.
    $authoring = (array) $fresh->authoring_json;
    $authoring['canonical'] = [
        'artifact_set' => ['artifact_hash' => str_repeat('a', 64)],
        'artifact_hash' => str_repeat('a', 64),
        'raster_assets' => [['page_no' => 1, 'asset_id' => 999]],
        'pdf_asset_id' => 998,
    ];
    $fresh->forceFill(['authoring_json' => $authoring])->save();
    $payload['lock_version'] = 1;
    $payload['idempotency_key'] = 'studio-save-navigation-only';
    $payload['studio']['current_stage'] = 3;
    $this->actingAs($actor)
        ->putJson(route('setup.omr.templates.canonical.drafts.studio', $draft), $payload)
        ->assertOk()
        ->assertJson(['lock_version' => 2]);
    expect($draft->fresh()->authoring_json['canonical']['artifact_set']['artifact_hash'])->toBe(str_repeat('a', 64));

    // Editor lock state persists without changing printable or native geometry.
    $payload['lock_version'] = 2;
    $payload['idempotency_key'] = 'studio-save-lock-only';
    $payload['studio']['sections'][0]['isLocked'] = true;
    $this->actingAs($actor)
        ->putJson(route('setup.omr.templates.canonical.drafts.studio', $draft), $payload)
        ->assertOk()
        ->assertJson(['lock_version' => 3]);
    expect($draft->fresh()->authoring_json['canonical']['artifact_set']['artifact_hash'])->toBe(str_repeat('a', 64));

    // A real printable-definition change invalidates the candidate.
    $payload['lock_version'] = 3;
    $payload['idempotency_key'] = 'studio-save-key-2';
    $payload['studio']['marginMm'] = 18;
    $secondRes = $this->actingAs($actor)->putJson(
        route('setup.omr.templates.canonical.drafts.studio', $draft),
        $payload
    );
    $secondRes->assertOk()->assertJson(['status' => 'saved', 'lock_version' => 4]);
    expect($draft->fresh()->authoring_json['studio']['marginMm'])->toBe(18)
        ->and($draft->fresh()->authoring_json['canonical']['artifact_set'])->toBeNull();

    $stalePayload = $payload;
    $stalePayload['lock_version'] = 3;
    $staleRes = $this->actingAs($actor)->putJson(
        route('setup.omr.templates.canonical.drafts.studio', $draft),
        $stalePayload
    );
    $staleRes->assertUnprocessable()->assertJsonPath('code', 'concurrency_conflict');

    $overlapPayload = $payload;
    $overlapPayload['lock_version'] = 4;
    $overlapPayload['mapping']['blocks'][] = [
        ...$overlapPayload['mapping']['blocks'][0],
        'name' => 'Section_2',
    ];
    $this->actingAs($actor)
        ->putJson(route('setup.omr.templates.canonical.drafts.studio', $draft), $overlapPayload)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'mapping_invalid')
        ->assertJsonPath('message', 'Block Section_2 overlaps Section_1.')
        ->assertJsonPath('errors.0.code', 'block_overlap')
        ->assertJsonPath('errors.0.block', 'Section_2');
});

test('studio endpoint persists the selected upload source kind', function (): void {
    $actor = User::factory()->create();
    \Spatie\Permission\Models\Permission::findOrCreate(\App\Authorization\CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(\App\Authorization\CapabilityCatalog::GUIDANCE_SCORING_OMR);

    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'STUDIO-SOURCE', 'Upload Source', 'html');

    $response = $this->actingAs($actor)->putJson(
        route('setup.omr.templates.canonical.drafts.studio', $draft),
        [
            'source_kind' => 'pdf',
            'overlay' => ['boxes' => []],
            'mapping' => null,
            'studio' => [
                'current_stage' => 1,
                'stage1Mode' => 'upload',
                'baseType' => 'pdf',
                'palette_filter' => [
                    'path' => 'upload_document',
                    'includeTextComponents' => true,
                    'includeQuestionComponents' => false,
                    'includeOverlayControls' => true,
                ],
            ],
            'lock_version' => 0,
            'idempotency_key' => 'studio-source-kind-1',
        ],
    );

    $response->assertOk();
    expect($draft->fresh()->source_kind)->toBe('pdf');
});

test('source ingestion endpoint accepts image and html documents with audit and operations', function (): void {
    \Illuminate\Support\Facades\Storage::fake('local');
    \Illuminate\Support\Facades\Queue::fake();

    $actor = User::factory()->create();
    \Spatie\Permission\Models\Permission::findOrCreate(\App\Authorization\CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(\App\Authorization\CapabilityCatalog::GUIDANCE_SCORING_OMR);

    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'INGEST-TEST', 'Ingest Test', 'image');

    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('sheet.png', (string) $pngBytes);

    $response = $this->actingAs($actor)->post(
        route('setup.omr.templates.canonical.drafts.source.ingest', $draft),
        [
            'source_kind' => 'image',
            'source' => $file,
            'idempotency_key' => 'ingest-key-1',
        ]
    );

    $response->assertStatus(202)
        ->assertJson([
            'draft_id' => $draft->id,
        ]);

    expect($draft->fresh()->authoring_json['source_run']['status'])->toBe('queued');
});

test('canonical draft and asset reads are owner scoped', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    \Spatie\Permission\Models\Permission::findOrCreate(\App\Authorization\CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $other->givePermissionTo(\App\Authorization\CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $draft = app(OmrAuthoringDraftService::class)->create($owner, 'OWNER-SCOPED', 'Owner scoped', 'html');
    $asset = app(\App\Assessment\Omr\Canonical\OmrTemplateAssetStore::class)->putDraftAsset(
        $draft,
        \App\Models\Assessment\OmrTemplateAsset::KIND_SOURCE,
        '<html></html>',
        'text/html',
    );

    $this->actingAs($other)
        ->get(route('setup.omr.templates.canonical.drafts.show', $draft))
        ->assertNotFound();
    $this->actingAs($other)
        ->get(route('setup.omr.templates.canonical.drafts.assets.show', [$draft, $asset]))
        ->assertNotFound();
});

test('failed digital verification idempotency keys retry instead of replaying success', function (): void {
    $actor = User::factory()->create();
    \Spatie\Permission\Models\Permission::findOrCreate(\App\Authorization\CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(\App\Authorization\CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'VERIFY-RETRY', 'Verify retry', 'html');
    $url = route('setup.omr.templates.canonical.drafts.verify.digital', $draft);
    $payload = ['idempotency_key' => 'verification-retry-key'];

    $this->actingAs($actor)->postJson($url, $payload)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'digital_verification_stage_invalid');
    $this->actingAs($actor)->postJson($url, $payload)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'digital_verification_stage_invalid');

    $operation = \App\Models\Foundation\Operation::query()
        ->where('type', 'omr.canonical.digital_verification')
        ->firstOrFail();
    expect($operation->status)->toBe(\App\Models\Foundation\Operation::STATUS_FAILED)
        ->and($operation->attempts)->toBe(2);
});

test('workflow projection exposes the canonical printable PDF for physical testing', function (): void {
    $actor = User::factory()->create();
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'PRINT-CANDIDATE', 'Print candidate', 'html');
    $asset = app(\App\Assessment\Omr\Canonical\OmrTemplateAssetStore::class)->putDraftAsset(
        $draft,
        \App\Models\Assessment\OmrTemplateAsset::KIND_PRINT_PDF,
        '%PDF-1.4 test',
        'application/pdf',
    );
    $draft->forceFill(['authoring_json' => ['canonical' => ['pdf_asset_id' => $asset->id]]])->save();

    $props = app(\App\Assessment\Omr\Canonical\Queries\OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draft->fresh(), $actor);

    expect($props['draft']['print_pdf_url'])
        ->toBe(route('setup.omr.templates.canonical.drafts.assets.show', [$draft, $asset]));
});

test('normal authoring hides comparison-only marker profiles', function (): void {
    $actor = User::factory()->create();
    $primary = OmrMarkerProfile::query()->create([
        'code' => 'omrchecker.sample',
        'version' => 1,
        'family' => 'omrchecker.bullseye',
        'asset_path' => 'app/Assessment/Omr/Fiducial/omr_marker.jpg',
        'asset_sha256' => hash_file('sha256', base_path('app/Assessment/Omr/Fiducial/omr_marker.jpg')),
        'matching_config' => [],
        'status' => OmrMarkerProfile::STATUS_CANDIDATE,
        'created_by_user_id' => $actor->id,
    ]);
    OmrMarkerProfile::query()->create([
        'code' => 'nexiam.custom',
        'version' => 1,
        'family' => 'nexiam.fiducial',
        'asset_path' => 'app/Assessment/Omr/Fiducial/omr_marker.jpg',
        'asset_sha256' => $primary->asset_sha256,
        'matching_config' => ['candidate_role' => 'comparison'],
        'status' => OmrMarkerProfile::STATUS_CANDIDATE,
        'created_by_user_id' => $actor->id,
    ]);
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'PROFILE-FILTER', 'Profile filter', 'html');
    $draft->forceFill(['marker_profile_id' => $primary->id])->save();

    $props = app(\App\Assessment\Omr\Canonical\Queries\OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draft->fresh(), $actor);

    expect(collect($props['marker_profiles'])->pluck('code')->all())
        ->toBe(['omrchecker.sample']);
});
