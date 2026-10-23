<?php

use App\Assessment\Actions\Omr\CreateBookletDraft;
use App\Assessment\Omr\Queries\BookletWorkspaceQuery;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE, 'web');
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actor->givePermissionTo(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE);
});

it('keeps Booklet content and key workspace independent from Answer Sheet preview pairing', function (): void {
    $version = app(CreateBookletDraft::class)->handle(
        'Independent Booklet preview fixture',
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $this->actor,
        null,
        'INDEPENDENT-PREVIEW-'.strtoupper(substr(hash('sha256', uniqid('', true)), 0, 8)),
        false,
    );
    $workspace = app(BookletWorkspaceQuery::class)->forBooklet(
        $version->booklet,
        $this->actor,
        false,
        (int) $version->id,
    );

    expect($workspace['template_version_id'])->toBeNull()
        ->and(array_key_exists('answer_sheet_preview', $workspace))->toBeFalse();

    $index = $this->actingAs($this->actor)
        ->get(route('setup.exam-materials.booklets.index'))
        ->assertOk();
    $bookletCard = collect($index->viewData('page')['props']['booklets'] ?? [])
        ->firstWhere('id', $version->booklet_id);

    expect($bookletCard)->not->toBeNull()
        ->and($bookletCard['thumbnail_url'])->toBeNull();
});
