<?php

use App\Assessment\Actions\Omr\Canonical\ConfirmOmrSourcePages;
use App\Assessment\Actions\Omr\Canonical\UpdateOmrDraftPages;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function omrStudioActor(): User
{
    $actor = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    return $actor;
}

test('page commands reorder remove and add pages with optimistic locking before mapping', function (): void {
    $actor = omrStudioActor();
    $draft = app(OmrAuthoringDraftService::class)->create(
        $actor,
        'PAGE-COMMANDS',
        'Page Commands',
        OmrTemplateAuthoringDraft::SOURCE_HTML,
        [
            'pages' => [
                ['page_no' => 1, 'kind' => 'blank', 'confirmed' => true],
                ['page_no' => 2, 'kind' => 'blank', 'confirmed' => true],
            ],
            'studio' => [
                'components' => [
                    ['id' => 'A', 'page_no' => 1],
                    ['id' => 'B', 'page_no' => 2],
                ],
                'sections' => [],
            ],
            'mapping' => null,
        ],
        requestedPageCount: 2,
    );
    $action = app(UpdateOmrDraftPages::class);

    $reordered = $action->handle($draft, $actor, 'reorder', 0, order: [2, 1]);
    expect($reordered->requested_page_count)->toBe(2)
        ->and($reordered->authoring_json['studio']['components'])->toMatchArray([
            ['id' => 'A', 'page_no' => 2],
            ['id' => 'B', 'page_no' => 1],
        ]);

    $removed = $action->handle($reordered, $actor, 'remove', 1, pageNo: 2);
    expect($removed->requested_page_count)->toBe(1)
        ->and($removed->authoring_json['studio']['components'])->toBe([['id' => 'B', 'page_no' => 1]])
        ->and($removed->authoring_json['invalidations']['stale_mapping'])->toBeTrue();

    $added = $action->handle($removed, $actor, 'add', 2);
    expect($added->requested_page_count)->toBe(2)
        ->and($added->authoring_json['pages'])->toHaveCount(2)
        ->and($added->lock_version)->toBe(3)
        ->and(fn () => $action->handle($added, $actor, 'add', 2))
        ->toThrow(OmrAuthoringDraftException::class, 'concurrency_conflict');
});

test('uploaded pages cannot be confirmed when their count differs from the draft', function (): void {
    $actor = omrStudioActor();
    $draft = app(OmrAuthoringDraftService::class)->create(
        $actor,
        'UPLOAD-COUNT',
        'Upload Count',
        OmrTemplateAuthoringDraft::SOURCE_PDF,
        ['pages' => [['page_no' => 1, 'asset_id' => 10, 'confirmed' => false]]],
        requestedPageCount: 2,
    );

    expect(fn () => app(ConfirmOmrSourcePages::class)->handle(
        $draft,
        $actor,
        [['page_no' => 1, 'decision' => 'confirmed']],
        0,
    ))->toThrow(OmrAuthoringDraftException::class, 'uploaded_page_count_mismatch');
});

test('uploaded source stays confirmable and composable when Studio saves retained mapping intent', function (): void {
    $actor = omrStudioActor();
    $draft = app(OmrAuthoringDraftService::class)->create(
        $actor,
        'UPLOAD-STAGE-GATE',
        'Uploaded Stage Gate',
        OmrTemplateAuthoringDraft::SOURCE_PDF,
        ['pages' => [['page_no' => 1, 'asset_id' => 10, 'confirmed' => false]]],
        registrationMode: OmrRegistrationMode::DocumentBound,
        qrMode: OmrQrMode::Disabled,
    );
    $payload = [
        'overlay' => ['boxes' => []],
        'mapping' => [
            'page_width' => 2480,
            'page_height' => 3508,
            'page_count' => 1,
            'blocks' => [[
                'name' => 'Questions_1', 'page_no' => 1, 'field_type' => 'QTYPE_MCQ4', 'label_prefix' => 'q',
                'rows' => 1, 'options' => 4, 'labels' => ['q001'], 'origin_x' => 300, 'origin_y' => 900,
                'width' => 331, 'height' => 80, 'option_pitch_px' => 71, 'row_pitch_px' => 80,
                'bubble_width_px' => 53, 'bubble_height_px' => 53,
            ]],
        ],
        'studio' => ['current_stage' => 2, 'active_page_no' => 1, 'stage1Mode' => 'upload', 'components' => [], 'sections' => []],
        'source_kind' => 'pdf',
        'lock_version' => 0,
        'idempotency_key' => 'uploaded-stage-save-1',
    ];

    $this->actingAs($actor)->putJson(route('setup.omr.templates.canonical.drafts.studio', $draft), $payload)
        ->assertOk();
    expect($draft->fresh()->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_DRAFT);

    $confirmed = app(ConfirmOmrSourcePages::class)->handle(
        $draft->fresh(),
        $actor,
        [['page_no' => 1, 'decision' => 'confirmed']],
        (int) $draft->fresh()->lock_version,
    );
    expect($confirmed->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_SOURCE_CONFIRMED);

    $this->actingAs($actor)->putJson(route('setup.omr.templates.canonical.drafts.studio', $draft), [
        ...$payload,
        'lock_version' => $confirmed->lock_version,
        'idempotency_key' => 'uploaded-stage-save-2',
    ])->assertOk();
    expect($draft->fresh()->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_SOURCE_CONFIRMED);
});

test('document bound and qr disabled drafts reject incompatible studio state', function (): void {
    $actor = omrStudioActor();
    $draft = app(OmrAuthoringDraftService::class)->create(
        $actor,
        'POLICY-GUARDS',
        'Policy Guards',
        OmrTemplateAuthoringDraft::SOURCE_HTML,
        registrationMode: OmrRegistrationMode::DocumentBound,
        qrMode: OmrQrMode::Disabled,
    );
    $basePayload = [
        'overlay' => ['boxes' => []],
        'mapping' => null,
        'studio' => [
            'current_stage' => 1,
            'active_page_no' => 1,
            'stage1Mode' => 'html',
            'components' => [],
            'sections' => [],
        ],
        'lock_version' => 0,
        'source_kind' => 'html',
        'idempotency_key' => 'policy-guard-1',
    ];

    $this->actingAs($actor)
        ->putJson(route('setup.omr.templates.canonical.drafts.studio', $draft), [
            ...$basePayload,
            'studio' => [...$basePayload['studio'], 'markerPattern' => 'target'],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'registration_mode_incompatible');

    $this->actingAs($actor)
        ->putJson(route('setup.omr.templates.canonical.drafts.studio', $draft), [
            ...$basePayload,
            'idempotency_key' => 'policy-guard-2',
            'studio' => [...$basePayload['studio'], 'showQrOverlay' => true],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'qr_mode_incompatible');
});

test('studio persists page identity and enforces globally contiguous question numbers', function (): void {
    $actor = omrStudioActor();
    $draft = app(OmrAuthoringDraftService::class)->create(
        $actor,
        'MULTI-PAGE-STUDIO',
        'Multi Page Studio',
        OmrTemplateAuthoringDraft::SOURCE_HTML,
        registrationMode: OmrRegistrationMode::DocumentBound,
        qrMode: OmrQrMode::Disabled,
        requestedPageCount: 2,
    );
    $payload = [
        'overlay' => ['boxes' => []],
        'mapping' => [
            'page_width' => 2480,
            'page_height' => 3508,
            'page_count' => 2,
            'blocks' => [
                [
                    'name' => 'Questions_1', 'page_no' => 1, 'field_type' => 'QTYPE_MCQ4', 'label_prefix' => 'q',
                    'rows' => 2, 'options' => 4, 'labels' => ['q001', 'q002'], 'origin_x' => 300, 'origin_y' => 900, 'width' => 331, 'height' => 160,
                    'option_pitch_px' => 71, 'row_pitch_px' => 80, 'bubble_width_px' => 53, 'bubble_height_px' => 53,
                ],
                [
                    'name' => 'Questions_2', 'page_no' => 2, 'field_type' => 'QTYPE_MCQ4', 'label_prefix' => 'q',
                    'rows' => 2, 'options' => 4, 'labels' => ['q003', 'q004'], 'origin_x' => 300, 'origin_y' => 900, 'width' => 331, 'height' => 160,
                    'option_pitch_px' => 71, 'row_pitch_px' => 80, 'bubble_width_px' => 53, 'bubble_height_px' => 53,
                ],
            ],
        ],
        'studio' => [
            'current_stage' => 2,
            'active_page_no' => 2,
            'stage1Mode' => 'html',
            'components' => [
                ['id' => 'Questions_1', 'type' => 'mcq', 'page_no' => 1],
                ['id' => 'Questions_2', 'type' => 'mcq', 'page_no' => 2],
            ],
            'sections' => [
                ['id' => 'Questions_1', 'type' => 'mcq', 'page_no' => 1, 'startQ' => 1, 'questionsCount' => 2],
                ['id' => 'Questions_2', 'type' => 'mcq', 'page_no' => 2, 'startQ' => 3, 'questionsCount' => 2],
            ],
        ],
        'lock_version' => 0,
        'source_kind' => 'html',
        'idempotency_key' => 'multi-page-studio-1',
    ];

    $this->actingAs($actor)
        ->putJson(route('setup.omr.templates.canonical.drafts.studio', $draft), $payload)
        ->assertOk();

    $fresh = $draft->fresh();
    expect($fresh->authoring_json['pages'])->toHaveCount(2)
        ->and($fresh->authoring_json['studio']['components'][1]['page_no'])->toBe(2)
        ->and($fresh->authoring_json['mapping']['field_blocks']['Questions_2']['page_no'])->toBe(2);

    $invalid = $payload;
    $invalid['lock_version'] = 1;
    $invalid['idempotency_key'] = 'multi-page-studio-2';
    $invalid['studio']['sections'][1]['startQ'] = 4;
    $this->actingAs($actor)
        ->putJson(route('setup.omr.templates.canonical.drafts.studio', $fresh), $invalid)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'question_numbering_invalid');
});
