<?php

use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('canonical routes require OMR capability and hide foreign drafts on mutation', function (): void {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $authorized = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $authorized->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $draft = OmrTemplateAuthoringDraft::query()->create([
        'code' => 'AUTHZ-DRAFT', 'name' => 'Authorization', 'source_kind' => 'image', 'stage' => 'draft', 'authoring_json' => [], 'lock_version' => 0, 'owner_user_id' => $owner->id,
    ]);

    $this->actingAs($outsider)->get(route('setup.omr.templates.canonical.drafts.show', $draft))->assertForbidden();
    $this->actingAs($authorized)->get(route('setup.omr.templates.canonical.diagnostics'))->assertOk();
    $this->actingAs($authorized)->delete(route('setup.omr.templates.canonical.drafts.destroy', $draft))->assertNotFound();
});
