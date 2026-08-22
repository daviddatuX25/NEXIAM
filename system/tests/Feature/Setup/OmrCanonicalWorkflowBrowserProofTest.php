<?php

use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('canonical authoring workflow page renders nine stages with correct gate locks', function (): void {
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $user = User::factory()->create();
    $user->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    $draft = OmrTemplateAuthoringDraft::query()->create([
        'code' => 'WORKFLOW-STAGE-TEST',
        'name' => 'Workflow stage test',
        'source_kind' => 'preset',
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
        'authoring_json' => ['pages' => [], 'mapping' => ['input' => ['blocks' => []]]],
        'lock_version' => 0,
        'owner_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('setup.omr.templates.canonical.drafts.show', $draft))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/OmrConfiguration/Canonical/CanonicalWorkflow')
            ->where('draft.id', $draft->id)
            ->where('draft.stage', 'mapping_complete')
            ->where('draft.can_edit', true)
            ->has('stages', 9)
            ->where('stages.0', 'draft')
            ->where('stages.5', 'digital_verify')
            ->where('stages.6', 'compile')
            ->where('stages.7', 'physical_activate')
            ->where('stages.8', 'blank_print')
            ->has('marker_profiles')
            ->has('urls.compile')
            ->has('urls.digital_verification')
        );
});

test('canonical authoring workflow rejects unauthenticated and unauthorized staff', function (): void {
    $owner = User::factory()->create();
    $draft = OmrTemplateAuthoringDraft::query()->create([
        'code' => 'AUTHZ-CHECK',
        'name' => 'Authz check',
        'source_kind' => 'image',
        'stage' => 'draft',
        'authoring_json' => [],
        'lock_version' => 0,
        'owner_user_id' => $owner->id,
    ]);

    $this->get(route('setup.omr.templates.canonical.drafts.show', $draft))->assertRedirect(route('login'));

    $unauthorized = User::factory()->create();
    $this->actingAs($unauthorized)
        ->get(route('setup.omr.templates.canonical.drafts.show', $draft))
        ->assertForbidden();
});
