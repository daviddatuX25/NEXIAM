<?php

use App\Assessment\Actions\Omr\Canonical\SaveOmrCanonicalStudioState;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Authorization\CapabilityCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    seedE2Foundation();
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $this->actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
});

function physicalInventoryStudioPayload(array $sections): array
{
    return ['physicalInventory' => ['revision' => 1, 'sections' => $sections]];
}

function physicalInventorySection(string $uid, string $name, array $items, int $count = 1): array
{
    return [
        'printed_section_uid' => $uid,
        'name' => $name,
        'page_no' => 1,
        'numbering' => ['start' => 1, 'step' => 1],
        'expected_item_count' => $count,
        'items' => $items,
    ];
}

function physicalInventoryItem(string $uid, int $number, ?string $label, array $tokens, ?string $guide = null): array
{
    return [
        'physical_item_uid' => $uid,
        'native_label' => $label,
        'page_no' => 1,
        'printed_number' => $number,
        'option_tokens' => $tokens,
        'guide_id' => $guide,
    ];
}

test('physical identity and literal options survive a Studio save and reload without profile mapping', function (): void {
    $actor = $this->actor;
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'PHYSICAL-INVENTORY-SAVE', 'Physical inventory', 'html');
    $sections = [
        physicalInventorySection('section-vocabulary', 'Vocabulary', [physicalInventoryItem('physical-vocabulary-1', 1, 'VOCAB_Q_1', ['A-B, A-C, A-D, B-C, B-D, C-D'], 'guide-1')]),
        physicalInventorySection('section-computation', 'Computation', [physicalInventoryItem('physical-computation-1', 2, 'COMP_Q_1', ['A', 'B', 'C', 'D', 'E'], 'guide-2')]),
    ];

    $saved = app(SaveOmrCanonicalStudioState::class)->handle($draft, $actor, ['boxes' => []], null, physicalInventoryStudioPayload($sections), (int) $draft->lock_version);
    $reloaded = $saved->fresh();

    expect($reloaded->authoring_json['studio']['physicalInventory']['sections'])->toEqual($sections)
        ->and($reloaded->test_instrument_version_id)->toBeNull()
        ->and($reloaded->authoring_json['mapping']['test_instrument_version_id'] ?? null)->toBeNull();
});

test('190 physical guide option token sets remain literal in Studio state', function (): void {
    $actor = $this->actor;
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'PHYSICAL-INVENTORY-190', '190 guides', 'html');
    $vocabItems = [];
    for ($i = 1; $i <= 40; $i++) {
        $vocabItems[] = physicalInventoryItem('vocab-'.$i, $i, 'VOCABULARY_Q_'.$i, ['A-B', 'A-C', 'A-D', 'B-C', 'B-D', 'C-D'], 'guide-vocab-'.$i);
    }
    $compItems = [];
    for ($i = 1; $i <= 30; $i++) {
        $compItems[] = physicalInventoryItem('comp-'.$i, $i, 'COMPUTATION_Q_'.$i, ['A', 'B', 'C', 'D', 'E'], 'guide-comp-'.$i);
    }
    $spatialItems = [];
    for ($i = 1; $i <= 20; $i++) {
        $spatialItems[] = physicalInventoryItem('spatial-'.$i, $i, 'SPATIAL_Q_'.$i, ['A', 'B', 'C', 'D'], 'guide-spatial-'.$i);
    }
    $wordCompItems = [];
    for ($i = 1; $i <= 100; $i++) {
        $wordCompItems[] = physicalInventoryItem('word-'.$i, $i, 'WORD_COMP_Q_'.$i, ['S', 'D'], 'guide-word-'.$i);
    }
    $sections = [
        physicalInventorySection('sec-vocab', 'Vocabulary', $vocabItems, 40),
        physicalInventorySection('sec-comp', 'Computation', $compItems, 30),
        physicalInventorySection('sec-spatial', 'Spatial Relations', $spatialItems, 20),
        physicalInventorySection('sec-word-comp', 'Word Comparison', $wordCompItems, 100),
    ];

    $saved = app(SaveOmrCanonicalStudioState::class)->handle($draft, $actor, ['boxes' => []], null, physicalInventoryStudioPayload($sections), (int) $draft->lock_version);
    $loadedSections = $saved->fresh()->authoring_json['studio']['physicalInventory']['sections'];
    $totalLoadedItems = array_sum(array_map(static fn (array $sec): int => count($sec['items']), $loadedSections));

    expect($loadedSections)->toHaveCount(4)
        ->and($totalLoadedItems)->toBe(190)
        ->and($loadedSections[0]['items'][0]['option_tokens'])->toBe(['A-B', 'A-C', 'A-D', 'B-C', 'B-D', 'C-D'])
        ->and($loadedSections[3]['items'][99]['option_tokens'])->toBe(['S', 'D']);
});

test('deleting sections with guides requires current impact confirmation', function (): void {
    $actor = $this->actor;
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'PHYSICAL-INVENTORY-IMPACT', 'Inventory impact', 'html');
    $originalInventory = ['revision' => 1, 'sections' => [
        physicalInventorySection('section-kept', 'Kept', [physicalInventoryItem('physical-kept', 1, 'ITEM_1', ['A', 'B'], 'guide-1')]),
        physicalInventorySection('section-removed', 'Removed', [physicalInventoryItem('physical-removed', 2, 'ITEM_2', ['A', 'B'], 'guide-2')]),
    ]];
    $saved = app(SaveOmrCanonicalStudioState::class)->handle($draft, $actor, ['boxes' => []], null, ['physicalInventory' => $originalInventory], (int) $draft->lock_version);
    $nextInventory = ['revision' => 2, 'sections' => [$originalInventory['sections'][0]]];
    $impact = [[
        'physical_item_uid' => 'physical-removed',
        'printed_section_uid' => 'section-removed',
        'section_name' => 'Removed',
        'native_label' => 'ITEM_2',
        'page_no' => 1,
        'guide_id' => 'guide-2',
    ]];
    $confirmation = [
        'revision' => 1,
        'digest' => hash('sha256', json_encode(['revision' => 1, 'impact' => $impact], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
    ];

    expect(fn () => app(SaveOmrCanonicalStudioState::class)->handle($saved->fresh(), $actor, ['boxes' => []], null, ['physicalInventory' => $nextInventory], (int) $saved->lock_version))
        ->toThrow(OmrAuthoringDraftException::class, 'physical_inventory_impact_stale')
        ->and(fn () => app(SaveOmrCanonicalStudioState::class)->handle(
            $saved->fresh(), $actor, ['boxes' => []], null,
            ['physicalInventory' => $nextInventory, 'physicalInventoryConfirmation' => ['revision' => 0, 'digest' => $confirmation['digest']]],
            (int) $saved->lock_version,
        ))->toThrow(OmrAuthoringDraftException::class, 'physical_inventory_impact_stale');

    $confirmed = app(SaveOmrCanonicalStudioState::class)->handle(
        $saved->fresh(), $actor, ['boxes' => []], null,
        ['physicalInventory' => $nextInventory, 'physicalInventoryConfirmation' => $confirmation],
        (int) $saved->lock_version,
    );
    expect($confirmed->authoring_json['studio']['physicalInventory']['sections'])->toHaveCount(1);
});

test('duplicate physical UIDs are rejected in a template inventory', function (): void {
    $actor = $this->actor;
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'PHYSICAL-INVENTORY-DUPLICATE', 'Duplicate inventory', 'html');
    $sections = [
        physicalInventorySection('section-one', 'One', [physicalInventoryItem('duplicate-uid', 1, 'ITEM_1', ['A', 'B'])]),
        physicalInventorySection('section-two', 'Two', [physicalInventoryItem('duplicate-uid', 2, 'ITEM_2', ['A', 'B'])]),
    ];

    expect(fn () => app(SaveOmrCanonicalStudioState::class)->handle($draft, $actor, ['boxes' => []], null, physicalInventoryStudioPayload($sections), (int) $draft->lock_version))
        ->toThrow(RuntimeException::class, 'physical_inventory_invalid');
});
