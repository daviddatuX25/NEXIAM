<?php

use App\Assessment\Actions\Omr\Canonical\SaveOmrCanonicalOverlay;
use App\Assessment\Actions\Omr\Canonical\SaveOmrCanonicalStudioState;
use App\Assessment\Actions\Omr\Canonical\SelectOmrMarkerProfile;
use App\Assessment\Omr\Canonical\CanonicalSheetDefinition;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrCanonicalGeometryIdentity;
use App\Assessment\Omr\Canonical\OmrMappingValidationException;
use App\Assessment\Omr\Canonical\OmrOverlayException;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\Canonical\Queries\OmrCanonicalWorkflowProjection;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletQuestion;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Operation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(RefreshDatabase::class);

function omrActingAsStaff(TestCase $test, User $user): TestCase
{
    $state = StaffAuthenticationState::query()->firstOrCreate(
        ['user_id' => $user->id],
        [
            'activation_completed_at' => now(),
            'password_set_at' => now(),
            'session_version' => 1,
            'mfa_required' => false,
        ],
    );

    return $test->actingAs($user)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
    ]);
}

test('draft autosave rejects stale lock versions and preserves server state', function (): void {
    $actor = User::factory()->create();
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'AUTHORING-CONFLICT', 'Conflict', 'html');
    $saved = app(OmrAuthoringDraftService::class)->saveAuthoring($draft, $actor, ['source' => ['hash' => 'a']], 0);

    expect($saved->lock_version)->toBe(1)
        ->and(fn () => app(OmrAuthoringDraftService::class)->saveAuthoring($saved, $actor, ['source' => ['hash' => 'b']], 0))
        ->toThrow(OmrAuthoringDraftException::class, 'concurrency_conflict')
        ->and($saved->fresh()->authoring_json)->toBe(['source' => ['hash' => 'a']]);
});

test('studio save rejects malformed document field identity, page, and paper geometry', function (): void {
    $actor = User::factory()->create();
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'DOCUMENT-FIELD-VALIDATION', 'Document fields', 'html');
    $validField = [
        'id' => 'applicant-name',
        'page_no' => 1,
        'x_mm' => 10,
        'y_mm' => 10,
        'w_mm' => 40,
        'h_mm' => 10,
    ];
    $invalidCases = [
        [[[...$validField, 'id' => ' ']], 'document_field_id_required'],
        [[$validField, $validField], 'document_field_id_duplicate'],
        [[array_replace($validField, ['page_no' => 2])], 'document_field_page_out_of_range'],
        [[array_replace($validField, ['x_mm' => 180, 'w_mm' => 40])], 'document_field_out_of_bounds'],
    ];

    foreach ($invalidCases as [$fields, $errorCode]) {
        $exception = null;
        try {
            app(SaveOmrCanonicalStudioState::class)->handle(
                $draft->fresh(),
                $actor,
                ['boxes' => []],
                null,
                ['document_fields' => $fields],
                (int) $draft->lock_version,
            );
        } catch (OmrMappingValidationException $caught) {
            $exception = $caught;
        }

        expect($exception)->toBeInstanceOf(OmrMappingValidationException::class)
            ->and($exception->errorCode)->toBe($errorCode);
    }
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
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $profile = OmrMarkerProfile::query()->create([
        'code' => 'studio-atomic-marker',
        'version' => 1,
        'family' => 'omrchecker.bullseye',
        'asset_path' => 'app/Assessment/Omr/Fiducial/omr_marker.jpg',
        'asset_sha256' => hash_file('sha256', base_path('app/Assessment/Omr/Fiducial/omr_marker.jpg')),
        'matching_config' => [],
        'status' => OmrMarkerProfile::STATUS_CANDIDATE,
        'created_by_user_id' => $actor->id,
    ]);

    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'STUDIO-ATOMIC', 'Atomic Studio', 'preset');
    $draft->forceFill([
        'marker_profile_id' => $profile->id,
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

    $response = omrActingAsStaff($this, $actor)->putJson(
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
    $geometryIdentity = OmrCanonicalGeometryIdentity::forDraft($fresh);
    $authoring['canonical'] = [
        'artifact_set' => [
            'artifact_hash' => str_repeat('a', 64),
            'geometry_identity' => $geometryIdentity,
        ],
        'artifact_hash' => str_repeat('a', 64),
        'raster_assets' => [['page_no' => 1, 'asset_id' => 999]],
        'pdf_asset_id' => 998,
    ];
    $fresh->forceFill(['authoring_json' => $authoring])->save();
    $payload['lock_version'] = 1;
    $payload['idempotency_key'] = 'studio-save-navigation-only';
    $payload['studio']['current_stage'] = 3;
    omrActingAsStaff($this, $actor)
        ->putJson(route('setup.omr.templates.canonical.drafts.studio', $draft), $payload)
        ->assertOk()
        ->assertJson(['lock_version' => 2]);
    expect($draft->fresh()->authoring_json['studio']['current_stage'])->toBe(3)
        ->and($draft->fresh()->authoring_json['canonical']['artifact_set']['artifact_hash'])->toBe(str_repeat('a', 64));

    // Editor lock state persists without changing printable or native geometry.
    $payload['lock_version'] = 2;
    $payload['idempotency_key'] = 'studio-save-lock-only';
    $payload['studio']['sections'][0]['isLocked'] = true;
    omrActingAsStaff($this, $actor)
        ->putJson(route('setup.omr.templates.canonical.drafts.studio', $draft), $payload)
        ->assertOk()
        ->assertJson(['lock_version' => 3]);
    expect($draft->fresh()->authoring_json['canonical']['artifact_set']['artifact_hash'])->toBe(str_repeat('a', 64));

    // A content-margin constraint does not change the already placed component geometry.
    $payload['lock_version'] = 3;
    $payload['idempotency_key'] = 'studio-save-key-2';
    $payload['studio']['marginMm'] = 18;
    $secondRes = omrActingAsStaff($this, $actor)->putJson(
        route('setup.omr.templates.canonical.drafts.studio', $draft),
        $payload
    );
    $secondRes->assertOk()->assertJson(['status' => 'saved', 'lock_version' => 4]);
    expect($draft->fresh()->authoring_json['studio']['marginMm'])->toBe(18)
        ->and($draft->fresh()->authoring_json['canonical']['artifact_set']['artifact_hash'])->toBe(str_repeat('a', 64));

    $stalePayload = $payload;
    $stalePayload['lock_version'] = 3;
    $staleRes = omrActingAsStaff($this, $actor)->putJson(
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
    omrActingAsStaff($this, $actor)
        ->putJson(route('setup.omr.templates.canonical.drafts.studio', $draft), $overlapPayload)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'mapping_invalid')
        ->assertJsonPath('message', 'Block Section_2 overlaps Section_1.')
        ->assertJsonPath('errors.0.code', 'block_overlap')
        ->assertJsonPath('errors.0.block', 'Section_2');
});

test('studio endpoint persists placed Sheet Designer components for downstream canonical rendering', function (): void {
    $actor = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $profile = OmrMarkerProfile::query()->create([
        'code' => 'studio-components-marker',
        'version' => 1,
        'family' => 'omrchecker.bullseye',
        'asset_path' => 'app/Assessment/Omr/Fiducial/omr_marker.jpg',
        'asset_sha256' => hash_file('sha256', base_path('app/Assessment/Omr/Fiducial/omr_marker.jpg')),
        'matching_config' => [],
        'status' => OmrMarkerProfile::STATUS_CANDIDATE,
        'created_by_user_id' => $actor->id,
    ]);
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'STUDIO-COMPONENTS', 'Component Studio', 'html', ['pages' => [['page_no' => 1, 'confirmed' => true]]]);
    $draft->forceFill(['marker_profile_id' => $profile->id])->save();

    omrActingAsStaff($this, $actor)
        ->putJson(route('setup.omr.templates.canonical.drafts.studio', $draft), [
            'overlay' => ['boxes' => []],
            'mapping' => [
                'page_width' => 2480,
                'page_height' => 3508,
                'page_count' => 1,
                'blocks' => [[
                    'name' => 'Part_1',
                    'page_no' => 1,
                    'field_type' => 'QTYPE_MCQ4',
                    'label_prefix' => 'q',
                    'rows' => 1,
                    'options' => 4,
                    'origin_x' => 300,
                    'origin_y' => 1000,
                    'width' => 331,
                    'height' => 80,
                    'option_pitch_px' => 71,
                    'row_pitch_px' => 80,
                    'bubble_width_px' => 53,
                    'bubble_height_px' => 53,
                ]],
            ],
            'studio' => [
                'current_stage' => 1,
                'stage1Mode' => 'html',
                'baseType' => 'html',
                'components' => [
                    ['id' => 'Header_1', 'type' => 'header', 'title' => 'Entrance Examination', 'posX' => 20, 'posY' => 20, 'w_mm' => 170, 'h_mm' => 16],
                    ['id' => 'Name_1', 'type' => 'candidate_name', 'label' => 'Name', 'posX' => 20, 'posY' => 40, 'w_mm' => 100, 'h_mm' => 12],
                    ['id' => 'Room_1', 'type' => 'room_seat', 'label' => 'Room / Seat', 'posX' => 125, 'posY' => 40, 'w_mm' => 65, 'h_mm' => 12],
                    ['id' => 'Part_1', 'type' => 'mcq', 'subjectName' => 'General Ability', 'posX' => 25, 'posY' => 85, 'w_mm' => 30, 'h_mm' => 10, 'questionsCount' => 1, 'startQ' => 1, 'optionsCount' => 4],
                ],
                'sections' => [],
                'marginMm' => 15,
                'htmlCode' => '<main>Entrance Examination</main>',
            ],
            'lock_version' => 0,
            'source_kind' => 'html',
            'idempotency_key' => 'studio-components-1',
        ])
        ->assertOk();

    $studio = (array) $draft->fresh()->authoring_json['studio'];
    expect($studio['components'])->toHaveCount(4)
        ->and($studio['components'][0]['title'])->toBe('Entrance Examination')
        ->and($studio['components'][1]['type'])->toBe('candidate_name')
        ->and($studio['components'][2]['type'])->toBe('room_seat')
        ->and($studio['sections'])->toBe([])
        ->and($studio['htmlCode'])->toBe('<main>Entrance Examination</main>');

    $definition = CanonicalSheetDefinition::fromDraft($draft->fresh(), $profile);
    expect(array_column($definition->visualComponents, 'type'))->toBe([
        'header',
        'candidate_name',
        'room_seat',
        'mcq',
    ]);
});

test('studio content margin persists without changing canonical marker insets', function (): void {
    $actor = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'STUDIO-CONTENT-MARGIN', 'Content Margin', 'html');
    $authoring = (array) $draft->authoring_json;
    $authoring['marker']['insets_mm'] = ['top' => 12, 'right' => 12, 'bottom' => 14, 'left' => 12];
    $draft->forceFill(['authoring_json' => $authoring])->save();

    omrActingAsStaff($this, $actor)
        ->putJson(route('setup.omr.templates.canonical.drafts.studio', $draft), [
            'overlay' => ['boxes' => []],
            'mapping' => null,
            'studio' => [
                'current_stage' => 1,
                'stage1Mode' => 'html',
                'marginMm' => 23,
            ],
            'lock_version' => 0,
            'idempotency_key' => 'studio-content-margin-1',
        ])
        ->assertOk();

    $fresh = $draft->fresh();
    expect($fresh->authoring_json['studio']['marginMm'])->toBe(23)
        ->and($fresh->authoring_json['marker']['insets_mm'])->toBe([
            'top' => 12,
            'right' => 12,
            'bottom' => 14,
            'left' => 12,
        ]);
});

test('studio endpoint persists the selected upload source kind', function (): void {
    $actor = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'STUDIO-SOURCE', 'Upload Source', 'html');

    $response = omrActingAsStaff($this, $actor)->putJson(
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
    Storage::fake('local');
    Queue::fake();

    $actor = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'INGEST-TEST', 'Ingest Test', 'image');

    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $file = UploadedFile::fake()->createWithContent('sheet.png', (string) $pngBytes);

    $response = omrActingAsStaff($this, $actor)->post(
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

test('staff-confirmed photo crop becomes the source page before mapping', function (): void {
    Storage::fake('local');
    seedE2Foundation();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'PHOTO-CROP', 'Photographed paper', 'image');

    $image = imagecreatetruecolor(600, 850);
    imagefill($image, 0, 0, imagecolorallocate($image, 45, 50, 55));
    imagefilledrectangle($image, 40, 40, 560, 810, imagecolorallocate($image, 250, 250, 250));
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);
    $payload = [
        'source' => UploadedFile::fake()->createWithContent('photograph.png', $bytes),
        'points' => [[40, 40], [560, 40], [560, 810], [40, 810]],
        'target_width' => 600,
        'target_height' => 850,
        'lock_version' => 0,
        'idempotency_key' => 'photo-crop-1',
    ];
    $url = route('setup.omr.templates.canonical.drafts.source.rectify', $draft);
    $response = omrActingAsStaff($this, $actor)->post($url, $payload)
        ->assertAccepted()
        ->assertJsonPath('status', 'succeeded');
    $saved = $draft->fresh();
    $page = $saved->authoring_json['pages'][0];
    $source = $saved->authoring_json['source'];
    expect($page['confirmed'])->toBeFalse()
        ->and($page['sha256'])->not->toBe(hash('sha256', $bytes))
        ->and($source['sha256'])->toBe(hash('sha256', $bytes))
        ->and($source['metadata']['rectified_sha256'])->toBe($page['sha256'])
        ->and($saved->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_DRAFT);
    omrActingAsStaff($this, $actor)->get($response->json('preview_url'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
    omrActingAsStaff($this, $actor)->postJson(
        route('setup.omr.templates.canonical.drafts.source.pages.confirm', $draft),
        ['pages' => [['page_no' => 1, 'decision' => 'confirmed']], 'lock_version' => $saved->lock_version],
    )->assertOk();
    expect($draft->fresh()->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_SOURCE_CONFIRMED);

    $payload['idempotency_key'] = 'photo-crop-stale';
    omrActingAsStaff($this, $actor)->post($url, $payload)
        ->assertConflict()
        ->assertJsonPath('code', 'concurrency_conflict');
    expect($draft->fresh()->authoring_json['pages'][0]['sha256'])->toBe($page['sha256']);
    $saved = $draft->fresh();
    $authoring = (array) $saved->authoring_json;
    $authoring['studio']['sections'] = [['id' => 'Question_1', 'page_no' => 1, 'posX' => 24, 'posY' => 72]];
    $authoring['mapping'] = ['input' => ['blocks' => [['name' => 'Question_1']]]];
    $saved->forceFill(['authoring_json' => $authoring])->save();
    $payload['lock_version'] = $saved->lock_version;
    $payload['idempotency_key'] = 'photo-crop-repair';
    $payload['points'] = [[45, 45], [555, 45], [555, 805], [45, 805]];
    omrActingAsStaff($this, $actor)->post($url, $payload)->assertAccepted();
    $repaired = $draft->fresh();
    expect($repaired->authoring_json['studio']['sections'])->toHaveCount(1)
        ->and($repaired->authoring_json['mapping'])->toBeNull()
        ->and($repaired->authoring_json['invalidations']['mapping_requires_review'])->toBeTrue()
        ->and($repaired->authoring_json['source']['settings_hash'])->not->toBe($source['settings_hash']);
});

test('multi-page image drafts support sequential page-by-page photo rectification and composition', function (): void {
    Storage::fake('local');
    seedE2Foundation();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $draft = app(OmrAuthoringDraftService::class)->create(
        actor: $actor,
        code: 'PHOTO-2P',
        name: 'Two Page Photo Paper',
        sourceKind: 'image',
        authoring: [],
        registrationMode: OmrRegistrationMode::DocumentBound,
        qrMode: OmrQrMode::Disabled,
        requestedPageCount: 2,
    );

    // Generate 2 test image byte payloads
    $image1 = imagecreatetruecolor(600, 850);
    imagefill($image1, 0, 0, imagecolorallocate($image1, 40, 40, 40));
    imagefilledrectangle($image1, 40, 40, 560, 810, imagecolorallocate($image1, 240, 240, 240));
    ob_start();
    imagepng($image1);
    $bytes1 = ob_get_clean();
    imagedestroy($image1);

    $image2 = imagecreatetruecolor(600, 850);
    imagefill($image2, 0, 0, imagecolorallocate($image2, 50, 50, 50));
    imagefilledrectangle($image2, 40, 40, 560, 810, imagecolorallocate($image2, 245, 245, 245));
    ob_start();
    imagepng($image2);
    $bytes2 = ob_get_clean();
    imagedestroy($image2);

    $url = route('setup.omr.templates.canonical.drafts.source.rectify', $draft);

    // 1. Rectify Page 1
    $res1 = omrActingAsStaff($this, $actor)->post($url, [
        'source' => UploadedFile::fake()->createWithContent('page1.png', $bytes1),
        'points' => [[40, 40], [560, 40], [560, 810], [40, 810]],
        'target_width' => 600,
        'target_height' => 850,
        'lock_version' => 0,
        'idempotency_key' => 'photo-p1',
        'page_no' => 1,
    ])->assertAccepted()
        ->assertJsonPath('status', 'succeeded')
        ->assertJsonPath('page_no', 1)
        ->assertJsonPath('pages_rectified', 1)
        ->assertJsonPath('all_rectified', false);

    $savedAfterP1 = $draft->fresh();
    expect($savedAfterP1->authoring_json['pages'])->toHaveCount(1)
        ->and($savedAfterP1->authoring_json['pages'][0]['page_no'])->toBe(1);

    // 2. Rectify Page 2
    $res2 = omrActingAsStaff($this, $actor)->post($url, [
        'source' => UploadedFile::fake()->createWithContent('page2.png', $bytes2),
        'points' => [[40, 40], [560, 40], [560, 810], [40, 810]],
        'target_width' => 600,
        'target_height' => 850,
        'lock_version' => $savedAfterP1->lock_version,
        'idempotency_key' => 'photo-p2',
        'page_no' => 2,
    ])->assertAccepted()
        ->assertJsonPath('status', 'succeeded')
        ->assertJsonPath('page_no', 2)
        ->assertJsonPath('pages_rectified', 2)
        ->assertJsonPath('all_rectified', true);

    $savedAfterP2 = $draft->fresh();
    expect($savedAfterP2->authoring_json['pages'])->toHaveCount(2)
        ->and($savedAfterP2->authoring_json['pages'][0]['page_no'])->toBe(1)
        ->and($savedAfterP2->authoring_json['pages'][1]['page_no'])->toBe(2);

    // Out-of-range page number is strictly rejected
    omrActingAsStaff($this, $actor)->post($url, [
        'source' => UploadedFile::fake()->createWithContent('page3.png', $bytes2),
        'points' => [[40, 40], [560, 40], [560, 810], [40, 810]],
        'target_width' => 600,
        'target_height' => 850,
        'lock_version' => $savedAfterP2->lock_version,
        'idempotency_key' => 'photo-p3-invalid',
        'page_no' => 3,
    ])->assertStatus(422)
        ->assertJsonPath('code', 'page_number_invalid');

    // 3. Confirm both pages
    omrActingAsStaff($this, $actor)->postJson(
        route('setup.omr.templates.canonical.drafts.source.pages.confirm', $draft),
        [
            'pages' => [
                ['page_no' => 1, 'decision' => 'confirmed'],
                ['page_no' => 2, 'decision' => 'confirmed'],
            ],
            'lock_version' => $savedAfterP2->lock_version,
        ],
    )->assertOk();
    $confirmedDraft = $draft->fresh();
    expect($confirmedDraft->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_SOURCE_CONFIRMED);

    // 4. Compose both pages
    omrActingAsStaff($this, $actor)->postJson(
        route('setup.omr.templates.canonical.drafts.compose', $draft),
        [
            'paper_width_mm' => 210,
            'paper_height_mm' => 297,
            'content_margin_mm' => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0],
            'idempotency_key' => 'compose-2p',
        ],
    )->assertOk();

    $composedDraft = $draft->fresh();
    expect($composedDraft->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED)
        ->and($composedDraft->authoring_json['canonical']['raster_assets'])->toHaveCount(2)
        ->and($composedDraft->authoring_json['canonical']['raster_assets'][0]['page_no'])->toBe(1)
        ->and($composedDraft->authoring_json['canonical']['raster_assets'][1]['page_no'])->toBe(2);
});

test('uploaded paper can save one recognition guide per printed question beyond one hundred', function (): void {
    $actor = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'MANY-GUIDES', 'Many guides', 'image');
    $draft->forceFill([
        'stage' => OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED,
        'authoring_json' => ['pages' => [['page_no' => 1, 'confirmed' => true, 'asset_id' => 1]]],
    ])->save();

    $sections = [];
    $blocks = [];
    foreach (range(1, 151) as $question) {
        $sections[] = [
            'id' => "Question_{$question}",
            'page_no' => 1,
            'name' => "Question {$question}",
            'type' => 'mcq',
            'questionsCount' => 1,
            'startQ' => $question,
            'posX' => 20,
            'posY' => 50,
        ];
        $blocks[] = [
            'name' => "Question_{$question}",
            'page_no' => 1,
            'field_type' => 'QTYPE_MCQ4',
            'rows' => 1,
            'options' => 4,
            'origin_x' => 100,
            'origin_y' => 500,
            'width' => 300,
            'height' => 60,
            'option_pitch_px' => 70,
            'row_pitch_px' => 80,
            'bubble_width_px' => 50,
            'bubble_height_px' => 50,
        ];
    }

    omrActingAsStaff($this, $actor)->putJson(route('setup.omr.templates.canonical.drafts.studio', $draft), [
        'overlay' => ['boxes' => []],
        'mapping' => ['page_width' => 2480, 'page_height' => 3508, 'page_count' => 1, 'blocks' => $blocks],
        'studio' => ['sections' => $sections, 'current_stage' => 3, 'stage1Mode' => 'upload', 'baseType' => 'image'],
        'source_kind' => 'image',
        'lock_version' => 0,
        'idempotency_key' => 'many-guides-save',
    ])->assertOk();

    expect($draft->fresh()->authoring_json['studio']['sections'])->toHaveCount(151);
});

test('Studio saves Applicant Number method and declared inventory for reload', function (): void {
    $actor = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'IDENTITY-RELOAD', 'Identity reload', 'image');
    $draft->forceFill([
        'stage' => OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED,
        'authoring_json' => ['pages' => [['page_no' => 1, 'confirmed' => true, 'asset_id' => 1]]],
    ])->save();

    omrActingAsStaff($this, $actor)->putJson(route('setup.omr.templates.canonical.drafts.studio', $draft), [
        'overlay' => ['boxes' => []],
        'studio' => [
            'current_stage' => 3,
            'stage1Mode' => 'upload',
            'baseType' => 'image',
            'sections' => [],
            'identity' => ['method' => 'shaded_grid', 'digit_count' => 6],
            'declaredInventory' => [[
                'id' => 'inventory-1',
                'page_no' => 1,
                'area_code' => 'LOGIC',
                'question_count' => 20,
                'option_labels' => ['A', 'B', 'C', 'D'],
            ]],
        ],
        'source_kind' => 'image',
        'lock_version' => 0,
        'idempotency_key' => 'identity-reload-save',
    ])->assertOk();

    $savedStudio = $draft->fresh()->authoring_json['studio'];
    expect($savedStudio['identity'])->toBe(['method' => 'shaded_grid', 'digit_count' => 6])
        ->and($savedStudio['declaredInventory'][0]['question_count'])->toBe(20);
});

test('a corrected crop retains guides without silently reauthorizing their old coordinates', function (): void {
    $actor = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'CROP-REVIEW', 'Crop review', 'image');
    $guide = ['id' => 'Question_1', 'page_no' => 1, 'name' => 'Question 1', 'questionsCount' => 1, 'posX' => 20, 'posY' => 50];
    $draft->forceFill([
        'stage' => OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED,
        'authoring_json' => [
            'pages' => [['page_no' => 1, 'confirmed' => true, 'asset_id' => 5]],
            'studio' => ['sections' => [$guide]],
            'invalidations' => ['stale_mapping' => true, 'mapping_requires_review' => true],
        ],
    ])->save();
    $payload = [
        'overlay' => ['boxes' => []],
        'mapping' => [
            'page_width' => 2480,
            'page_height' => 3508,
            'page_count' => 1,
            'blocks' => [[
                'name' => 'Question_1', 'page_no' => 1, 'field_type' => 'QTYPE_MCQ4',
                'rows' => 1, 'options' => 4, 'origin_x' => 100, 'origin_y' => 500,
                'width' => 300, 'height' => 60, 'option_pitch_px' => 70,
                'row_pitch_px' => 80, 'bubble_width_px' => 50, 'bubble_height_px' => 50,
            ]],
        ],
        'studio' => ['sections' => [$guide], 'current_stage' => 3, 'stage1Mode' => 'upload', 'baseType' => 'image'],
        'source_kind' => 'image',
        'lock_version' => 0,
        'idempotency_key' => 'crop-navigation-save',
    ];
    omrActingAsStaff($this, $actor)->putJson(route('setup.omr.templates.canonical.drafts.studio', $draft), $payload)->assertOk();
    $saved = $draft->fresh();
    expect($saved->authoring_json['studio']['sections'])->toEqual([$guide])
        ->and($saved->authoring_json['mapping'] ?? null)->toBeNull()
        ->and($saved->authoring_json['invalidations']['mapping_requires_review'])->toBeTrue();

    $payload['studio']['reviewed_crop_asset_id'] = 4;
    $payload['lock_version'] = $saved->lock_version;
    omrActingAsStaff($this, $actor)->putJson(route('setup.omr.templates.canonical.drafts.studio', $draft), $payload)
        ->assertUnprocessable();
    $payload['studio']['reviewed_crop_asset_id'] = 5;
    omrActingAsStaff($this, $actor)->putJson(route('setup.omr.templates.canonical.drafts.studio', $draft), $payload)
        ->assertOk();
    expect($draft->fresh()->authoring_json['mapping']['input']['blocks'])->toHaveCount(1)
        ->and($draft->fresh()->authoring_json['invalidations']['mapping_requires_review'])->toBeFalse();
});

test('canonical draft and asset reads are owner scoped', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $other->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $draft = app(OmrAuthoringDraftService::class)->create($owner, 'OWNER-SCOPED', 'Owner scoped', 'html');
    $asset = app(OmrTemplateAssetStore::class)->putDraftAsset(
        $draft,
        OmrTemplateAsset::KIND_SOURCE,
        '<html></html>',
        'text/html',
    );

    omrActingAsStaff($this, $other)
        ->get(route('setup.exam-materials.answer-sheets.edit', $draft))
        ->assertNotFound();
    omrActingAsStaff($this, $other)
        ->get(route('setup.omr.templates.canonical.drafts.assets.show', [$draft, $asset]))
        ->assertNotFound();
});

test('canonical booklet options expose question metadata but never answer keys', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $other->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $owner->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $area = AptitudeArea::query()->create([
        'code' => 'META-AREA',
        'name' => 'Metadata area',
        'max_items' => 2,
        'is_active' => true,
    ]);
    $draft = app(OmrAuthoringDraftService::class)->create($owner, 'BOOKLET-METADATA', 'Booklet metadata', 'html');
    $booklet = OmrQuestionBooklet::query()->create([
        'code' => 'META-'.strtoupper(substr(uniqid(), -8)),
        'name' => 'Metadata only booklet',
    ]);
    $version = OmrQuestionBookletVersion::query()->create([
        'booklet_id' => $booklet->id,
        'version' => 3,
        'status' => OmrQuestionBookletVersion::STATUS_DRAFT,
        'answer_key_hash' => hash('sha256', 'metadata-only-answer-key'),
    ]);
    foreach ([[2, 'D'], [1, 'A']] as [$position, $answer]) {
        OmrQuestionBookletQuestion::query()->create([
            'booklet_version_id' => $version->id,
            'aptitude_area_id' => $area->id,
            'question_position' => $position,
            'correct_option_code' => $answer,
        ]);
    }

    $response = omrActingAsStaff($this, $owner)
        ->getJson(route('setup.omr.templates.canonical.drafts.booklet_options', $draft))
        ->assertOk()
        ->assertExactJson([
            'contract' => 'omr-booklet-options.v1',
            'booklets' => [[
                'id' => $version->id,
                'booklet_id' => $booklet->id,
                'code' => $booklet->code,
                'name' => $booklet->name,
                'version' => 3,
                'status' => OmrQuestionBookletVersion::STATUS_DRAFT,
                'questions_count' => 2,
                'questions' => [
                    ['position' => 1, 'label' => 'q1'],
                    ['position' => 2, 'label' => 'q2'],
                ],
            ]],
        ]);
    expect($response->headers->getCacheControlDirective('private'))->toBeTrue()
        ->and($response->headers->getCacheControlDirective('no-store'))->toBeTrue();

    expect($response->getContent())->not->toContain('correct_option_code')
        ->and($response->getContent())->not->toContain('"A"')
        ->and($response->getContent())->not->toContain('"D"');

    omrActingAsStaff($this, $other)
        ->getJson(route('setup.omr.templates.canonical.drafts.booklet_options', $draft))
        ->assertNotFound();
    $unauthorized = User::factory()->create();
    omrActingAsStaff($this, $unauthorized)
        ->getJson(route('setup.omr.templates.canonical.drafts.booklet_options', $draft))
        ->assertForbidden();
});

test('failed digital verification idempotency keys retry instead of replaying success', function (): void {
    $actor = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'VERIFY-RETRY', 'Verify retry', 'html');
    $url = route('setup.omr.templates.canonical.drafts.verification_runs.start', $draft);
    $payload = ['idempotency_key' => 'verification-retry-key'];

    omrActingAsStaff($this, $actor)->postJson($url, $payload)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'digital_verification_stage_invalid');
    omrActingAsStaff($this, $actor)->postJson($url, $payload)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'digital_verification_stage_invalid');

    $operation = Operation::query()
        ->where('type', 'omr.canonical.verification_run')
        ->firstOrFail();
    expect($operation->status)->toBe(Operation::STATUS_FAILED)
        ->and($operation->attempts)->toBe(2);
});

test('workflow projection exposes the canonical printable PDF for physical testing', function (): void {
    $actor = User::factory()->create();
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'PRINT-CANDIDATE', 'Print candidate', 'html');
    $asset = app(OmrTemplateAssetStore::class)->putDraftAsset(
        $draft,
        OmrTemplateAsset::KIND_PRINT_PDF,
        '%PDF-1.4 test',
        'application/pdf',
    );
    $draft->forceFill(['authoring_json' => ['canonical' => ['pdf_asset_id' => $asset->id]]])->save();

    $props = app(OmrCanonicalWorkflowProjection::class)
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

    $props = app(OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draft->fresh(), $actor);

    expect(collect($props['marker_profiles'])->pluck('code')->all())
        ->toBe(['omrchecker.sample']);
});

test('workflow projection exposes persisted_preview with exact contract when canonical artifact_set and raster exist', function (): void {
    $actor = User::factory()->create();
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'PREVIEW-VALID', 'Preview valid', 'html');
    $raster = app(OmrTemplateAssetStore::class)->putDraftAsset(
        $draft,
        OmrTemplateAsset::KIND_RASTER_PAGE,
        (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        'image/png',
        1,
    );

    $artifactHash = hash('sha256', 'preview-test-artifact');
    $draft->forceFill([
        'authoring_json' => [
            'studio' => ['stage1Mode' => 'html'],
            'marker' => ['insets_mm' => ['top' => 12.0, 'right' => 12.0, 'bottom' => 14.0, 'left' => 12.0]],
            'canonical' => [
                'settings' => ['paper_width_mm' => 210.0, 'paper_height_mm' => 297.0],
                'raster_assets' => [['page_no' => 1, 'asset_id' => $raster->id]],
                'artifact_hash' => $artifactHash,
                'artifact_set' => [
                    'schema' => 'nexiam.omr.canonical-artifact-set.v1',
                    'artifact_hash' => $artifactHash,
                    'definition' => [
                        'paper' => ['width_mm' => 210.0, 'height_mm' => 297.0],
                        'insets_mm' => ['top' => 12.0, 'right' => 12.0, 'bottom' => 14.0, 'left' => 12.0],
                    ],
                    'raster_assets' => [['page_no' => 1, 'asset_id' => $raster->id]],
                ],
            ],
        ],
    ])->save();

    $props = app(OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draft->fresh(), $actor);

    expect($props['draft']['persisted_preview'])->not->toBeNull()
        ->and($props['draft']['persisted_preview']['source'])->toBe('persisted')
        ->and($props['draft']['persisted_preview']['aligned_url'])->toBeNull()
        ->and($props['draft']['persisted_preview']['overlay_url'])->toBeNull()
        ->and($props['draft']['persisted_preview']['mapping_guides'])->toBe([])
        ->and($props['draft']['persisted_preview']['diagnostics_available'])->toBeFalse()
        ->and($props['draft']['persisted_preview']['artifact'])->toEqual([
            'canonical_url' => route('setup.omr.templates.canonical.drafts.assets.show', [$draft, $raster->id]),
            'pages' => [
                [
                    'page_no' => 1,
                    'asset_id' => $raster->id,
                    'canonical_url' => route('setup.omr.templates.canonical.drafts.assets.show', [$draft, $raster->id]),
                    'sha256' => '',
                ],
            ],
            'artifact_hash' => $artifactHash,
            'paper' => ['width_mm' => 210.0, 'height_mm' => 297.0],
            'insets_mm' => ['top' => 12.0, 'right' => 12.0, 'bottom' => 14.0, 'left' => 12.0],
        ]);

    $indexDraft = collect(app(OmrCanonicalWorkflowProjection::class)->indexProps($actor)['drafts'])
        ->firstWhere('id', $draft->id);

    expect($indexDraft)->not->toBeNull()
        ->and($indexDraft['preview_url'])->toBe(route('setup.omr.templates.canonical.drafts.assets.show', [$draft, $raster->id]))
        ->and($indexDraft['preview_page_count'])->toBe(1)
        ->and($indexDraft['authoring_method'])->toBe('Sheet Designer')
        ->and($indexDraft)->not->toHaveKey('source_kind');
});

test('workflow projection derives nonempty native mapping guides from persisted artifact with 80px row spacing preserved', function (): void {
    $actor = User::factory()->create();
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'PREVIEW-NATIVE-PITCH', 'Preview native pitch', 'html');
    $raster = app(OmrTemplateAssetStore::class)->putDraftAsset(
        $draft,
        OmrTemplateAsset::KIND_RASTER_PAGE,
        (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        'image/png',
        1,
    );

    $artifactHash = hash('sha256', 'preview-native-pitch-artifact');
    $draft->forceFill([
        'authoring_json' => [
            'canonical' => [
                'raster_assets' => [['page_no' => 1, 'asset_id' => $raster->id]],
                'artifact_hash' => $artifactHash,
                'artifact_set' => [
                    'schema' => 'nexiam.omr.canonical-artifact-set.v1',
                    'artifact_hash' => $artifactHash,
                    'insets_px' => ['top' => 142, 'right' => 142, 'bottom' => 165, 'left' => 142],
                    'definition' => [
                        'paper' => ['width_mm' => 210.0, 'height_mm' => 297.0],
                        'insets_mm' => ['top' => 12.0, 'right' => 12.0, 'bottom' => 14.0, 'left' => 12.0],
                    ],
                    'template' => [
                        'pages' => [
                            [
                                'fieldBlocks' => [
                                    'section_1' => [
                                        'fieldType' => 'QTYPE_MCQ4',
                                        'origin' => [100, 200],
                                        'bubbleDimensions' => [53, 53],
                                        'direction' => 'horizontal',
                                        'bubblesGap' => 72,
                                        'labelsGap' => 80,
                                        'fieldLabels' => ['q1..q3'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'raster_assets' => [['page_no' => 1, 'asset_id' => $raster->id]],
                ],
            ],
        ],
    ])->save();

    $props = app(OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draft->fresh(), $actor);

    $persisted = $props['draft']['persisted_preview'];
    expect($persisted)->not->toBeNull()
        ->and($persisted['mapping_guides'])->toHaveCount(1);

    $guide = $persisted['mapping_guides'][0];
    expect($guide['id'])->toBe('section_1')
        ->and($guide['x_mm'])->toBe(round((100 + 142) * 25.4 / 300, 3))
        ->and($guide['y_mm'])->toBe(round((200 + 142) * 25.4 / 300, 3))
        ->and($guide['bubbles'])->toHaveCount(12);

    $row0Y = $guide['bubbles'][0]['y_mm'];
    $row1Y = $guide['bubbles'][4]['y_mm'];
    $row2Y = $guide['bubbles'][8]['y_mm'];

    $expectedRow0Y = round((200 + 142) * 25.4 / 300, 3);
    $expectedRow1Y = round((200 + 142 + 80) * 25.4 / 300, 3);
    $expectedRow2Y = round((200 + 142 + 160) * 25.4 / 300, 3);

    expect($row0Y)->toBe($expectedRow0Y)
        ->and($row1Y)->toBe($expectedRow1Y)
        ->and($row2Y)->toBe($expectedRow2Y);
});

test('workflow projection returns null persisted_preview when artifact_set is missing or definition was invalidated', function (): void {
    $actor = User::factory()->create();
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'PREVIEW-INVALID', 'Preview invalid', 'html');

    // Case 1: no artifact_set
    $props = app(OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draft->fresh(), $actor);
    expect($props['draft']['persisted_preview'])->toBeNull();

    // Case 2: artifact_set explicitly invalidated (null)
    $draft->forceFill([
        'authoring_json' => [
            'canonical' => [
                'artifact_set' => null,
                'artifact_hash' => null,
                'raster_assets' => [],
            ],
        ],
    ])->save();
    $props = app(OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draft->fresh(), $actor);
    expect($props['draft']['persisted_preview'])->toBeNull();
});

test('workflow projection returns null persisted_preview when raster asset is missing or belongs to another draft', function (): void {
    $actor = User::factory()->create();
    $draftA = app(OmrAuthoringDraftService::class)->create($actor, 'PREVIEW-MISMATCH-A', 'Draft A', 'html');
    $draftB = app(OmrAuthoringDraftService::class)->create($actor, 'PREVIEW-MISMATCH-B', 'Draft B', 'html');

    $otherAsset = app(OmrTemplateAssetStore::class)->putDraftAsset(
        $draftB,
        OmrTemplateAsset::KIND_RASTER_PAGE,
        (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        'image/png',
        1,
    );

    $artifactHash = hash('sha256', 'mismatch-test');

    // Draft A references Draft B's asset (belongs elsewhere)
    $draftA->forceFill([
        'authoring_json' => [
            'canonical' => [
                'artifact_hash' => $artifactHash,
                'raster_assets' => [['page_no' => 1, 'asset_id' => $otherAsset->id]],
                'artifact_set' => [
                    'schema' => 'nexiam.omr.canonical-artifact-set.v1',
                    'artifact_hash' => $artifactHash,
                    'raster_assets' => [['page_no' => 1, 'asset_id' => $otherAsset->id]],
                ],
            ],
        ],
    ])->save();

    $props = app(OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draftA->fresh(), $actor);
    expect($props['draft']['persisted_preview'])->toBeNull();

    // Draft A references non-existent asset ID
    $draftA->forceFill([
        'authoring_json' => [
            'canonical' => [
                'artifact_hash' => $artifactHash,
                'raster_assets' => [['page_no' => 1, 'asset_id' => 9999999]],
                'artifact_set' => [
                    'schema' => 'nexiam.omr.canonical-artifact-set.v1',
                    'artifact_hash' => $artifactHash,
                    'raster_assets' => [['page_no' => 1, 'asset_id' => 9999999]],
                ],
            ],
        ],
    ])->save();

    $props = app(OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draftA->fresh(), $actor);
    expect($props['draft']['persisted_preview'])->toBeNull();
});

test('test_compiled_draft_projects_can_edit_false', function (): void {
    $actor = User::factory()->create();
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'COMPILED-READ-ONLY', 'Compiled read-only', 'html');

    $template = OmrAnswerSheetTemplate::query()->create([
        'code' => 'TPL-COMPILED-READ-ONLY',
        'name' => 'Compiled read-only template',
    ]);
    $version = OmrAnswerSheetTemplateVersion::query()->create([
        'template_id' => $template->id,
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
        'page_width' => 2480,
        'page_height' => 3508,
        'identity_regions_json' => [],
        'provider_config_json' => [],
        'config_hash' => hash('sha256', 'compiled-read-only-config'),
        'template_json_hash' => hash('sha256', 'compiled-read-only-template'),
        'engine_name' => 'OMRChecker',
    ]);
    $draft->forceFill([
        'stage' => OmrTemplateAuthoringDraft::STAGE_COMPILED,
        'compiled_version_id' => $version->id,
    ])->save();

    $projection = app(OmrCanonicalWorkflowProjection::class);
    $indexDraft = collect($projection->indexProps($actor)['drafts'])
        ->firstWhere('id', $draft->id);
    $workflowDraft = $projection->workflowProps($draft->fresh(), $actor)['draft'];

    expect($indexDraft['can_edit'])->toBeFalse()
        ->and($workflowDraft['can_edit'])->toBeFalse();
});
test('canonical draft show route is read-only and leaves lock version, stage, and assets unchanged', function (): void {
    $actor = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'SHOW-READONLY', 'Show Readonly', 'html');
    $raster = app(OmrTemplateAssetStore::class)->putDraftAsset(
        $draft,
        OmrTemplateAsset::KIND_RASTER_PAGE,
        (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        'image/png',
        1,
    );

    $initialLockVersion = (int) $draft->lock_version;
    $initialStage = (string) $draft->stage;
    $initialAssetCount = $draft->assets()->count();

    $response = omrActingAsStaff($this, $actor)->get(route('setup.exam-materials.answer-sheets.edit', $draft));

    $response->assertOk();

    $fresh = $draft->fresh();
    expect($fresh->lock_version)->toBe($initialLockVersion)
        ->and($fresh->stage)->toBe($initialStage)
        ->and($fresh->assets()->count())->toBe($initialAssetCount);
});

function createTestDraftWithArtifact(User $actor, string $code = 'PROJ-TEST', string $artifactHash = 'artifact_hash_test', string $mappingHash = 'mapping_hash_test'): OmrTemplateAuthoringDraft
{
    $draft = app(OmrAuthoringDraftService::class)->create($actor, $code, $code, 'html');
    $raster = app(OmrTemplateAssetStore::class)->putDraftAsset(
        $draft,
        OmrTemplateAsset::KIND_RASTER_PAGE,
        (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        'image/png',
        1,
    );

    $draft->forceFill([
        'authoring_json' => [
            'canonical' => [
                'artifact_hash' => $artifactHash,
                'mapping_hash' => $mappingHash,
                'raster_assets' => [['page_no' => 1, 'asset_id' => $raster->id]],
                'artifact_set' => [
                    'schema' => 'nexiam.omr.canonical-artifact-set.v1',
                    'artifact_hash' => $artifactHash,
                    'mapping_hash' => $mappingHash,
                    'definition' => [
                        'paper' => ['width_mm' => 210.0, 'height_mm' => 297.0],
                        'insets_mm' => ['top' => 12.0, 'right' => 12.0, 'bottom' => 14.0, 'left' => 12.0],
                    ],
                    'raster_assets' => [['page_no' => 1, 'asset_id' => $raster->id]],
                ],
            ],
            'mapping' => [
                'mapping_hash' => $mappingHash,
            ],
        ],
    ])->save();

    return $draft->fresh();
}

function createTestVerificationRun(User $actor, array $attributes): OmrTemplateVerificationRun
{
    return OmrTemplateVerificationRun::query()->create([
        'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
        'attempt' => 1,
        'plan_version' => 'v1',
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('v', 64),
        'artifact_hash' => str_repeat('a', 64),
        'native_bundle_hash' => str_repeat('n', 64),
        'manifest_hash' => str_repeat('m', 64),
        'config_hash' => str_repeat('c', 64),
        'mapping_hash' => str_repeat('x', 64),
        'progress_done' => 0,
        'progress_total' => 14,
        'started_by_user_id' => $actor->id,
        'started_at' => now(),
        'last_heartbeat_at' => now(),
        ...$attributes,
    ]);
}

test('workflow projection verification_run prefers active run over terminal run on current target', function (): void {
    $actor = User::factory()->create();
    $artifactHash = hash('sha256', 'artifact-active-wins');
    $mappingHash = hash('sha256', 'mapping-active-wins');
    $draft = createTestDraftWithArtifact($actor, 'ACTIVE-WINS', $artifactHash, $mappingHash);

    // Active run is older than the terminal run but still wins by phase.
    $activeRun = createTestVerificationRun($actor, [
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX,
        'attempt' => 2,
        'artifact_hash' => $artifactHash,
        'mapping_hash' => $mappingHash,
        'progress_done' => 3,
        'progress_total' => 14,
        'phase_detail' => ['stage' => 'variant_matrix', 'current_variant' => 'skew_1deg'],
        'optimistic_version' => 2,
    ]);

    $terminalRun = createTestVerificationRun($actor, [
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_PASSED,
        'artifact_hash' => $artifactHash,
        'mapping_hash' => $mappingHash,
        'progress_done' => 14,
        'progress_total' => 14,
        'optimistic_version' => 1,
    ]);

    $props = app(OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draft->fresh(), $actor);

    expect($props['verification_run'])->not->toBeNull()
        ->and($props['verification_run']['projection'])->toBe('summary')
        ->and($props['verification_run']['run_id'])->toBe($activeRun->id)
        ->and($props['verification_run']['status'])->toBe(OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX)
        ->and($props['verification_run']['mode'])->toBe('digital')
        ->and($props['verification_run']['attempt'])->toBe(2)
        ->and($props['verification_run']['progress'])->toEqual([
            'done' => 3,
            'total' => 14,
            'percent' => 21.4,
        ])
        ->and($props['verification_run']['current_variant'])->toBe('skew_1deg')
        ->and($props['verification_run']['run_version'])->toBe(2)
        ->and($props['verification_run']['status_url'])->toBe(route('setup.omr.templates.canonical.verification_runs.status', ['run' => $activeRun->id]));
});

test('workflow projection selects newer failed quick run22 over passed digital run21', function (): void {
    $actor = User::factory()->create();
    $artifactHash = hash('sha256', 'artifact-run21-run22');
    $mappingHash = hash('sha256', 'mapping-run21-run22');
    $draft = createTestDraftWithArtifact($actor, 'RUN21-RUN22', $artifactHash, $mappingHash);

    $run21 = createTestVerificationRun($actor, [
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_PASSED,
        'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
        'artifact_hash' => $artifactHash,
        'mapping_hash' => $mappingHash,
        'progress_done' => 14,
        'progress_total' => 14,
        'finished_at' => now(),
    ]);
    $run22 = createTestVerificationRun($actor, [
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_FAILED,
        'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        'artifact_hash' => $artifactHash,
        'mapping_hash' => $mappingHash,
        'challenge_code' => 'RUN22-QUICK',
        'progress_done' => 0,
        'progress_total' => 1,
        'error_code' => 'capture_rejected',
        'safe_error' => 'The physical challenge capture was rejected.',
        'finished_at' => now(),
    ]);

    expect($run22->id)->toBeGreaterThan($run21->id);

    $props = app(OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draft->fresh(), $actor);

    expect($props['verification_run'])->not->toBeNull()
        ->and($props['verification_run']['run_id'])->toBe($run22->id)
        ->and($props['verification_run']['mode'])->toBe(OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL)
        ->and($props['verification_run']['status'])->toBe(OmrTemplateVerificationRun::STATUS_FAILED)
        ->and($props['verification_run']['safe_issue'])->toEqual([
            'code' => 'capture_rejected',
            'detail' => 'The physical challenge capture was rejected.',
        ]);
});

test('workflow projection persists latest terminal failed run with retry fields', function (): void {
    $actor = User::factory()->create();
    $artifactHash = hash('sha256', 'artifact-failed-persists');
    $mappingHash = hash('sha256', 'mapping-failed-persists');
    $draft = createTestDraftWithArtifact($actor, 'FAILED-PERSISTS', $artifactHash, $mappingHash);

    $passedRun = createTestVerificationRun($actor, [
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_PASSED,
        'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
        'artifact_hash' => $artifactHash,
        'mapping_hash' => $mappingHash,
        'progress_done' => 14,
        'progress_total' => 14,
    ]);
    $failedRun = createTestVerificationRun($actor, [
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_FAILED,
        'attempt' => 1,
        'artifact_hash' => $artifactHash,
        'mapping_hash' => $mappingHash,
        'progress_done' => 2,
        'progress_total' => 14,
        'phase_detail' => ['stage' => 'variant_matrix', 'current_variant' => 'shadow'],
        'error_code' => 'no_go_variant_failed',
        'safe_error' => 'Variants failed the declared outcome.',
        'result_summary' => ['failure_variant' => 'shadow'],
        'optimistic_version' => 3,
    ]);

    expect($failedRun->id)->toBeGreaterThan($passedRun->id);

    $props = app(OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draft->fresh(), $actor);

    expect($props['verification_run'])->not->toBeNull()
        ->and($props['verification_run']['projection'])->toBe('summary')
        ->and($props['verification_run']['run_id'])->toBe($failedRun->id)
        ->and($props['verification_run']['status'])->toBe('failed')
        ->and($props['verification_run']['mode'])->toBe('digital')
        ->and($props['verification_run']['attempt'])->toBe(1)
        ->and($props['verification_run']['safe_issue'])->toEqual([
            'code' => 'no_go_variant_failed',
            'detail' => 'Variants failed the declared outcome.',
        ])
        ->and($props['verification_run']['result_summary'])->toEqual(['failure_variant' => 'shadow'])
        ->and($props['verification_run']['current_variant'])->toBe('shadow')
        ->and($props['verification_run']['run_version'])->toBe(3);
});

test('workflow projection persists passed verification run', function (): void {
    $actor = User::factory()->create();
    $artifactHash = hash('sha256', 'artifact-passed-persists');
    $mappingHash = hash('sha256', 'mapping-passed-persists');
    $draft = createTestDraftWithArtifact($actor, 'PASSED-PERSISTS', $artifactHash, $mappingHash);

    $passedRun = createTestVerificationRun($actor, [
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_PASSED,
        'attempt' => 1,
        'artifact_hash' => $artifactHash,
        'mapping_hash' => $mappingHash,
        'progress_done' => 14,
        'progress_total' => 14,
        'phase_detail' => ['stage' => 'passed', 'current_variant' => null],
        'result_summary' => ['ok' => true, 'variants_tested' => 14],
        'optimistic_version' => 5,
    ]);

    $props = app(OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draft->fresh(), $actor);

    expect($props['verification_run'])->not->toBeNull()
        ->and($props['verification_run']['projection'])->toBe('summary')
        ->and($props['verification_run']['run_id'])->toBe($passedRun->id)
        ->and($props['verification_run']['status'])->toBe('passed')
        ->and($props['verification_run']['progress'])->toEqual([
            'done' => 14,
            'total' => 14,
            'percent' => 100.0,
        ])
        ->and($props['verification_run']['result_summary'])->toEqual(['ok' => true, 'variants_tested' => 14])
        ->and($props['verification_run']['current_variant'])->toBeNull();
});

test('workflow projection excludes stale run after artifact hash change or definition invalidation', function (): void {
    $actor = User::factory()->create();
    $oldArtifactHash = hash('sha256', 'old-artifact');
    $mappingHash = hash('sha256', 'mapping-hash');
    $draft = createTestDraftWithArtifact($actor, 'STALE-EXCLUDED', $oldArtifactHash, $mappingHash);

    $oldRun = createTestVerificationRun($actor, [
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_PASSED,
        'artifact_hash' => $oldArtifactHash,
        'mapping_hash' => $mappingHash,
        'progress_done' => 14,
        'progress_total' => 14,
    ]);

    // Case 1: artifact_hash changed on draft
    $newArtifactHash = hash('sha256', 'new-artifact');
    $authoring = (array) $draft->authoring_json;
    $authoring['canonical']['artifact_hash'] = $newArtifactHash;
    $authoring['canonical']['artifact_set']['artifact_hash'] = $newArtifactHash;
    $draft->forceFill(['authoring_json' => $authoring])->save();

    $props = app(OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draft->fresh(), $actor);
    expect($props['verification_run'])->toBeNull();

    // Case 2: artifact_set invalidated (null)
    $authoring['canonical']['artifact_set'] = null;
    $authoring['canonical']['artifact_hash'] = null;
    $draft->forceFill(['authoring_json' => $authoring])->save();

    $props = app(OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draft->fresh(), $actor);
    expect($props['verification_run'])->toBeNull();

    // Case 3: mapping_hash changed
    $authoring['canonical']['artifact_hash'] = $oldArtifactHash;
    $authoring['canonical']['artifact_set'] = [
        'schema' => 'nexiam.omr.canonical-artifact-set.v1',
        'artifact_hash' => $oldArtifactHash,
        'mapping_hash' => hash('sha256', 'new-mapping'),
        'raster_assets' => [['page_no' => 1, 'asset_id' => 1]],
    ];
    $authoring['mapping']['mapping_hash'] = hash('sha256', 'new-mapping');
    $draft->forceFill(['authoring_json' => $authoring])->save();

    $props = app(OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draft->fresh(), $actor);
    expect($props['verification_run'])->toBeNull();
});

test('workflow projection excludes runs belonging to other drafts or versions', function (): void {
    $actor = User::factory()->create();
    $artifactHash = hash('sha256', 'isolation-artifact');
    $mappingHash = hash('sha256', 'isolation-mapping');
    $draftA = createTestDraftWithArtifact($actor, 'ISOLATION-A', $artifactHash, $mappingHash);
    $draftB = createTestDraftWithArtifact($actor, 'ISOLATION-B', $artifactHash, $mappingHash);

    // Run created for draft B
    createTestVerificationRun($actor, [
        'draft_id' => $draftB->id,
        'status' => OmrTemplateVerificationRun::STATUS_PASSED,
        'artifact_hash' => $artifactHash,
        'mapping_hash' => $mappingHash,
        'progress_done' => 14,
        'progress_total' => 14,
    ]);

    $propsA = app(OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draftA->fresh(), $actor);
    expect($propsA['verification_run'])->toBeNull();

    $propsB = app(OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draftB->fresh(), $actor);
    expect($propsB['verification_run'])->not->toBeNull()
        ->and($propsB['verification_run']['run_id'])->toBeGreaterThan(0);
});

test('workflow projection isolates physical terminal runs by draft artifact and mapping target', function (): void {
    $actor = User::factory()->create();
    $artifactHash = hash('sha256', 'isolation-physical-artifact');
    $mappingHash = hash('sha256', 'isolation-physical-mapping');
    $staleArtifactHash = hash('sha256', 'isolation-stale-artifact');
    $staleMappingHash = hash('sha256', 'isolation-stale-mapping');
    $draftA = createTestDraftWithArtifact($actor, 'ISOLATION-PHYSICAL-A', $artifactHash, $mappingHash);
    $draftB = createTestDraftWithArtifact($actor, 'ISOLATION-PHYSICAL-B', $artifactHash, $mappingHash);

    createTestVerificationRun($actor, [
        'draft_id' => $draftA->id,
        'status' => OmrTemplateVerificationRun::STATUS_FAILED,
        'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        'artifact_hash' => $staleArtifactHash,
        'mapping_hash' => $mappingHash,
    ]);
    createTestVerificationRun($actor, [
        'draft_id' => $draftA->id,
        'status' => OmrTemplateVerificationRun::STATUS_FAILED,
        'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        'artifact_hash' => $artifactHash,
        'mapping_hash' => $staleMappingHash,
    ]);
    createTestVerificationRun($actor, [
        'draft_id' => $draftB->id,
        'status' => OmrTemplateVerificationRun::STATUS_FAILED,
        'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        'artifact_hash' => $artifactHash,
        'mapping_hash' => $mappingHash,
    ]);

    $propsA = app(OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draftA->fresh(), $actor);
    expect($propsA['verification_run'])->toBeNull();

    $matchingRun = createTestVerificationRun($actor, [
        'draft_id' => $draftA->id,
        'status' => OmrTemplateVerificationRun::STATUS_FAILED,
        'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        'artifact_hash' => $artifactHash,
        'mapping_hash' => $mappingHash,
    ]);

    $propsA = app(OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draftA->fresh(), $actor);
    expect($propsA['verification_run'])->not->toBeNull()
        ->and($propsA['verification_run']['run_id'])->toBe($matchingRun->id)
        ->and($propsA['verification_run']['mode'])->toBe(OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL);
});

test('workflow projection selects compiled physical qualification run and physical_run_id', function (): void {
    $actor = User::factory()->create();
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'COMPILED-PHYSICAL', 'Compiled physical', 'html');

    $template = OmrAnswerSheetTemplate::query()->create(['code' => 'TPL-PHYSICAL-MAIN', 'name' => 'Physical main template']);
    $version = OmrAnswerSheetTemplateVersion::query()->create([
        'template_id' => $template->id,
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
        'page_width' => 2480,
        'page_height' => 3508,
        'identity_regions_json' => [],
        'provider_config_json' => [],
        'config_hash' => hash('sha256', 'c'),
        'template_json_hash' => hash('sha256', 't'),
        'engine_name' => 'OMRChecker',
        'authoring_manifest' => [
            'physical_qualification' => ['status' => 'physical_qualified'],
        ],
    ]);
    $artifactHash = hash('sha256', 'compiled-terminal-artifact');
    $mappingHash = hash('sha256', 'compiled-terminal-mapping');
    $draft->forceFill([
        'compiled_version_id' => $version->id,
        'stage' => OmrTemplateAuthoringDraft::STAGE_COMPILED,
        'authoring_json' => [
            'canonical' => [
                'artifact_set' => [
                    'artifact_hash' => $artifactHash,
                    'mapping_hash' => $mappingHash,
                ],
            ],
        ],
    ])->save();

    $physicalRun = createTestVerificationRun($actor, [
        'draft_id' => null,
        'template_version_id' => $version->id,
        'status' => OmrTemplateVerificationRun::STATUS_PASSED,
        'mode' => OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
        'progress_done' => 1,
        'progress_total' => 1,
        'result_summary' => ['qualified' => true],
        'optimistic_version' => 1,
    ]);
    createTestVerificationRun($actor, [
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_FAILED,
        'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        'artifact_hash' => $artifactHash,
        'mapping_hash' => $mappingHash,
        'error_code' => 'capture_rejected',
    ]);
    $newerPhysicalRun = createTestVerificationRun($actor, [
        'draft_id' => null,
        'template_version_id' => $version->id,
        'status' => OmrTemplateVerificationRun::STATUS_FAILED,
        'mode' => OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
        'progress_done' => 0,
        'progress_total' => 1,
        'error_code' => 'capture_rejected',
        'safe_error' => 'The latest physical qualification capture was rejected.',
        'optimistic_version' => 2,
    ]);

    $props = app(OmrCanonicalWorkflowProjection::class)
        ->workflowProps($draft->fresh(), $actor);

    expect($props['physical_run_id'])->toBe($physicalRun->id)
        ->and($props['verification_run'])->not->toBeNull()
        ->and($props['verification_run']['projection'])->toBe('summary')
        ->and($props['verification_run']['run_id'])->toBe($newerPhysicalRun->id)
        ->and($props['verification_run']['mode'])->toBe('full_qualification')
        ->and($props['verification_run']['status'])->toBe('failed');
});

test('canonical draft show route is strictly read-only and executes reasonable query count with active verification run', function (): void {
    $actor = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    $artifactHash = hash('sha256', 'read-only-check');
    $mappingHash = hash('sha256', 'read-only-mapping');
    $draft = createTestDraftWithArtifact($actor, 'SHOW-QUERY-COUNT', $artifactHash, $mappingHash);

    $run = createTestVerificationRun($actor, [
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX,
        'artifact_hash' => $artifactHash,
        'mapping_hash' => $mappingHash,
        'progress_done' => 2,
        'progress_total' => 14,
        'phase_detail' => ['stage' => 'variant_matrix', 'current_variant' => 'contrast_low'],
        'optimistic_version' => 1,
    ]);

    $runVersionBefore = (int) $run->optimistic_version;
    $draftLockBefore = (int) $draft->lock_version;
    $draftStageBefore = (string) $draft->stage;

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = omrActingAsStaff($this, $actor)->get(route('setup.exam-materials.answer-sheets.edit', $draft));
    $response->assertOk();

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Assert no INSERT / UPDATE / DELETE queries occurred on authoring/run domain models during show
    $domainMutationQueries = array_filter($queries, function (array $query): bool {
        $sql = strtolower(trim((string) $query['query']));
        if (! (str_starts_with($sql, 'insert') || str_starts_with($sql, 'update') || str_starts_with($sql, 'delete'))) {
            return false;
        }

        return str_contains($sql, 'omr_') || str_contains($sql, 'draft') || str_contains($sql, 'verification');
    });
    expect($domainMutationQueries)->toBeEmpty();

    // Assert state remained unchanged
    $freshDraft = $draft->fresh();
    $freshRun = $run->fresh();
    expect($freshDraft->lock_version)->toBe($draftLockBefore)
        ->and($freshDraft->stage)->toBe($draftStageBefore)
        ->and($freshRun->optimistic_version)->toBe($runVersionBefore);
});

test('workflow projection and preview endpoint project page-indexed preview urls for multi-page drafts', function (): void {
    $actor = User::factory()->create();
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'MULTI-PAGE-PREVIEW-TEST', 'Multi-Page Preview Test', 'image');
    $store = app(OmrTemplateAssetStore::class);
    $raster1 = $store->putDraftAsset(
        $draft,
        OmrTemplateAsset::KIND_RASTER_PAGE,
        (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        'image/png',
        1,
    );
    $raster2 = $store->putDraftAsset(
        $draft,
        OmrTemplateAsset::KIND_RASTER_PAGE,
        (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        'image/png',
        2,
    );

    $artifactHash = hash('sha256', 'multi-page-preview-test-hash');
    $draft->forceFill([
        'requested_page_count' => 2,
        'authoring_json' => [
            'pages' => [
                ['page_no' => 1, 'asset_id' => $raster1->id, 'confirmed' => true, 'sha256' => 'hash-p1'],
                ['page_no' => 2, 'asset_id' => $raster2->id, 'confirmed' => true, 'sha256' => 'hash-p2'],
            ],
            'canonical' => [
                'raster_assets' => [
                    ['page_no' => 1, 'asset_id' => $raster1->id, 'sha256' => 'hash-p1'],
                    ['page_no' => 2, 'asset_id' => $raster2->id, 'sha256' => 'hash-p2'],
                ],
                'artifact_hash' => $artifactHash,
                'artifact_set' => [
                    'schema' => 'nexiam.omr.canonical-artifact-set.v1',
                    'artifact_hash' => $artifactHash,
                    'raster_assets' => [
                        ['page_no' => 1, 'asset_id' => $raster1->id, 'sha256' => 'hash-p1'],
                        ['page_no' => 2, 'asset_id' => $raster2->id, 'sha256' => 'hash-p2'],
                    ],
                    'definition' => [
                        'paper' => ['width_mm' => 210.0, 'height_mm' => 297.0],
                        'insets_mm' => ['top' => 12.0, 'right' => 12.0, 'bottom' => 14.0, 'left' => 12.0],
                    ],
                ],
            ],
        ],
    ])->save();

    $props = app(OmrCanonicalWorkflowProjection::class)->workflowProps($draft->fresh(), $actor);
    $persisted = $props['draft']['persisted_preview'];

    expect($persisted)->not->toBeNull()
        ->and($persisted['artifact']['canonical_url'])->toBe(route('setup.omr.templates.canonical.drafts.assets.show', [$draft, $raster1->id]))
        ->and($persisted['artifact']['pages'])->toHaveCount(2)
        ->and($persisted['artifact']['pages'][0]['page_no'])->toBe(1)
        ->and($persisted['artifact']['pages'][0]['canonical_url'])->toBe(route('setup.omr.templates.canonical.drafts.assets.show', [$draft, $raster1->id]))
        ->and($persisted['artifact']['pages'][1]['page_no'])->toBe(2)
        ->and($persisted['artifact']['pages'][1]['canonical_url'])->toBe(route('setup.omr.templates.canonical.drafts.assets.show', [$draft, $raster2->id]));

    expect($props['draft']['ordered_pages'])->toHaveCount(2)
        ->and($props['draft']['ordered_pages'][0]['raster_url'])->toBe(route('setup.omr.templates.canonical.drafts.assets.show', [$draft, $raster1->id]))
        ->and($props['draft']['ordered_pages'][1]['raster_url'])->toBe(route('setup.omr.templates.canonical.drafts.assets.show', [$draft, $raster2->id]));
});
