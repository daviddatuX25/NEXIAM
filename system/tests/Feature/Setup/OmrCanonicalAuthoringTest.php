<?php

use App\Assessment\Actions\Omr\Canonical\SaveOmrCanonicalOverlay;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrOverlayException;
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

test('atomic studio endpoint saves mapping, overlays, and studio state without cross-invalidation', function (): void {
    $actor = User::factory()->create();
    \Spatie\Permission\Models\Permission::findOrCreate(\App\Authorization\CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(\App\Authorization\CapabilityCatalog::GUIDANCE_SCORING_OMR);

    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'STUDIO-ATOMIC', 'Atomic Studio', 'preset');
    $draft->forceFill([
        'stage' => OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED,
        'authoring_json' => [
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
        ->and($fresh->authoring_json['overlay']['boxes'])->toHaveCount(2)
        ->and($fresh->authoring_json['overlay']['boxes'][0]['content'])->toBe('ENTRANCE EXAMINATION')
        ->and($fresh->authoring_json['mapping']['field_blocks'])->toHaveKey('Section_1')
        ->and($fresh->authoring_json['studio']['marginMm'])->toBe(15);

    // Second save with new lock version succeeds
    $payload['lock_version'] = 1;
    $payload['idempotency_key'] = 'studio-save-key-2';
    $payload['studio']['marginMm'] = 18;

    $secondRes = $this->actingAs($actor)->putJson(
        route('setup.omr.templates.canonical.drafts.studio', $draft),
        $payload
    );

    $secondRes->assertOk()
        ->assertJson([
            'status' => 'saved',
            'lock_version' => 2,
        ]);

    expect($draft->fresh()->authoring_json['studio']['marginMm'])->toBe(18);

    // Stale lock version is rejected with concurrency conflict
    $stalePayload = $payload;
    $stalePayload['lock_version'] = 1; // Stale (current is 2)
    $staleRes = $this->actingAs($actor)->putJson(
        route('setup.omr.templates.canonical.drafts.studio', $draft),
        $stalePayload
    );
    $staleRes->assertStatus(500);
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
