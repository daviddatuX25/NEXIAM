<?php

use App\Assessment\Actions\Omr\Canonical\UpdateOmrDraftPages;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('deleting a draft page prunes its authoring state and invalidates mapping caches', function (): void {
    $actor = User::factory()->create();
    $pageOneSection = ['id' => 'section-page-one', 'page_no' => 1, 'area_code' => 'GENERAL'];
    $pageTwoSection = ['id' => 'section-page-two', 'page_no' => 2, 'area_code' => 'VERBAL'];
    $pageOneComponent = ['id' => 'component-page-one', 'page_no' => 1, 'type' => 'question'];
    $pageTwoComponent = ['id' => 'component-page-two', 'page_no' => 2, 'type' => 'question'];

    $draft = app(OmrAuthoringDraftService::class)->create(
        actor: $actor,
        code: 'OMR-PAGE-DELETE-CASCADE',
        name: 'Page deletion cascade fixture',
        sourceKind: OmrTemplateAuthoringDraft::SOURCE_HTML,
        authoring: [
            'pages' => [
                ['page_no' => 1, 'kind' => 'blank', 'confirmed' => true],
                ['page_no' => 2, 'kind' => 'blank', 'confirmed' => true],
            ],
            'studio' => [
                'active_page_no' => 2,
                'components' => [$pageOneComponent, $pageTwoComponent],
                'sections' => [$pageOneSection, $pageTwoSection],
            ],
            'mapping' => [
                'field_blocks' => [
                    'Questions_1' => ['page_no' => 1, 'fieldType' => 'QTYPE_MCQ4'],
                    'Questions_2' => ['page_no' => 2, 'fieldType' => 'QTYPE_MCQ4'],
                ],
            ],
            'verification' => ['status' => 'passed', 'page_no' => 2],
            'canonical' => ['artifact_set' => ['artifact_hash' => str_repeat('a', 64)]],
        ],
        requestedPageCount: 2,
    );

    $updated = app(UpdateOmrDraftPages::class)->handle(
        draft: $draft,
        actor: $actor,
        operation: 'remove',
        expectedLockVersion: 0,
        pageNo: 2,
    );
    $authoring = $updated->authoring_json;

    expect($updated->requested_page_count)->toBe(1)
        ->and($authoring['pages'])->toBe([
            ['page_no' => 1, 'kind' => 'blank', 'confirmed' => true],
        ])
        ->and($authoring['studio']['sections'])->toBe([$pageOneSection])
        ->and($authoring['studio']['components'])->toBe([$pageOneComponent])
        ->and($authoring['studio']['active_page_no'])->toBe(1)
        ->and($authoring['mapping'])->toBeNull()
        ->and($authoring['verification'])->toBeNull()
        ->and($authoring['canonical'])->toBeNull();
});
