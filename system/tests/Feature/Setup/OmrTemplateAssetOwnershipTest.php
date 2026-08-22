<?php

use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('draft assets are immutable and pin to a version-owned copy', function (): void {
    Storage::fake('local');
    $actor = User::factory()->create();
    $draft = OmrTemplateAuthoringDraft::query()->create([
        'code' => 'ASSET-OWNERSHIP', 'name' => 'Assets', 'source_kind' => 'image', 'stage' => 'draft', 'authoring_json' => [], 'lock_version' => 0, 'owner_user_id' => $actor->id,
    ]);
    $asset = app(OmrTemplateAssetStore::class)->putDraftAsset($draft, OmrTemplateAsset::KIND_SOURCE, 'immutable', 'text/plain');
    expect(fn () => $asset->forceFill(['sha256' => hash('sha256', 'changed')])->save())->toThrow(RuntimeException::class, 'asset_immutable');
    $asset = $asset->fresh();

    $template = OmrAnswerSheetTemplate::query()->create(['code' => 'ASSET-VERSION', 'name' => 'Version']);
    $version = OmrAnswerSheetTemplateVersion::query()->create([
        'template_id' => $template->id, 'version' => 1, 'status' => 'draft', 'page_width' => 1, 'page_height' => 1, 'identity_regions_json' => [], 'provider_config_json' => [], 'config_hash' => hash('sha256', 'c'), 'template_json_hash' => hash('sha256', 't'),
    ]);
    $pinned = app(OmrTemplateAssetStore::class)->pinToVersion($asset, $version);

    expect($pinned->template_version_id)->toBe($version->id)
        ->and(fn () => $pinned->delete())->toThrow(RuntimeException::class, 'version_asset_immutable');
});
