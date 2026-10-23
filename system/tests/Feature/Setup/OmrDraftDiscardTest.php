<?php

use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Foundation\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('owner can discard an uncompiled draft with assets and files removed cleanly', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $owner->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    $draft = OmrTemplateAuthoringDraft::query()->create([
        'code' => 'DISCARD-ME',
        'name' => 'To Be Discarded',
        'source_kind' => 'image',
        'stage' => 'draft',
        'authoring_json' => ['test' => true],
        'lock_version' => 0,
        'owner_user_id' => $owner->id,
    ]);

    $filePath = "omr-canonical/drafts/{$draft->id}/raster.png";
    Storage::disk('local')->put($filePath, 'fake image data');

    OmrTemplateAsset::query()->create([
        'authoring_draft_id' => $draft->id,
        'template_version_id' => null,
        'kind' => OmrTemplateAsset::KIND_SOURCE,
        'mime' => 'image/png',
        'bytes' => 15,
        'disk' => 'local',
        'path' => $filePath,
        'sha256' => hash('sha256', 'fake image data'),
        'metadata' => [],
    ]);

    expect(Storage::disk('local')->exists($filePath))->toBeTrue();

    $response = $this->actingAs($owner)->delete(
        route('setup.exam-materials.answer-sheets.destroy', $draft)
    );

    $response->assertRedirect(route('setup.exam-materials.answer-sheets.index'))
        ->assertSessionHas('status', 'Answer-sheet draft discarded.');

    expect(OmrTemplateAuthoringDraft::query()->find($draft->id))->toBeNull()
        ->and(OmrTemplateAsset::query()->where('authoring_draft_id', $draft->id)->count())->toBe(0)
        ->and(Storage::disk('local')->exists($filePath))->toBeFalse();

    $audit = AuditEvent::query()
        ->where('event_type', 'omr.canonical_draft_abandoned')
        ->latest('created_at')
        ->firstOrFail();

    expect($audit->payload)->toMatchArray(['draft_id' => $draft->id]);
});

test('owner can discard an uncompiled draft with verification runs and evidence purged cleanly', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $owner->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    $draft = OmrTemplateAuthoringDraft::query()->create([
        'code' => 'VERIFIED-DRAFT',
        'name' => 'Verified Draft',
        'source_kind' => 'image',
        'stage' => 'digital_verified',
        'authoring_json' => [],
        'lock_version' => 5,
        'owner_user_id' => $owner->id,
    ]);

    // Create a verification run pointing to this draft
    $run = OmrTemplateVerificationRun::query()->create([
        'draft_id' => $draft->id,
        'started_by_user_id' => $owner->id,
        'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
        'status' => OmrTemplateVerificationRun::STATUS_PASSED,
        'attempt' => 1,
        'plan_version' => 'v1',
        'plan_hash' => hash('sha256', 'plan:test'),
        'expected_vector_hash' => hash('sha256', 'expected_vector:test'),
        'clean_vector_hash' => hash('sha256', 'clean_vector:test'),
        'mapping_hash' => hash('sha256', 'mapping:test'),
        'proof_hash' => hash('sha256', 'proof:test'),
        'artifact_hash' => hash('sha256', 'artifact:test'),
        'native_bundle_hash' => hash('sha256', 'native_bundle:test'),
        'manifest_hash' => hash('sha256', 'manifest:test'),
        'config_hash' => hash('sha256', 'config:test'),
        'verification_hash' => hash('sha256', 'verification:test'),
        'optimistic_version' => 0,
        'payload' => [],
    ]);

    $response = $this->actingAs($owner)->delete(
        route('setup.exam-materials.answer-sheets.destroy', $draft)
    );

    $response->assertRedirect(route('setup.exam-materials.answer-sheets.index'))
        ->assertSessionHas('status', 'Answer-sheet draft discarded.');

    expect(OmrTemplateAuthoringDraft::query()->find($draft->id))->toBeNull()
        ->and(OmrTemplateVerificationRun::query()->where('draft_id', $draft->id)->count())->toBe(0);
});

test('owner can discard an unused compiled draft and purge its draft version cleanly', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $owner->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    $version = OmrAnswerSheetTemplateVersion::factory()->create([
        'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
    ]);

    $draft = OmrTemplateAuthoringDraft::query()->create([
        'code' => 'COMPILED-DRAFT',
        'name' => 'Compiled Draft',
        'source_kind' => 'html',
        'stage' => 'compiled',
        'compiled_version_id' => $version->id,
        'authoring_json' => [],
        'lock_version' => 1,
        'owner_user_id' => $owner->id,
    ]);

    $response = $this->actingAs($owner)->delete(
        route('setup.exam-materials.answer-sheets.destroy', $draft)
    );

    $response->assertRedirect(route('setup.exam-materials.answer-sheets.index'))
        ->assertSessionHas('status', 'Answer-sheet draft discarded.');

    expect(OmrTemplateAuthoringDraft::query()->find($draft->id))->toBeNull()
        ->and(OmrAnswerSheetTemplateVersion::query()->find($version->id))->toBeNull();
});

test('discarding a compiled draft bound to a Question Booklet version is rejected with 409 conflict', function (): void {
    $owner = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $owner->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    $version = OmrAnswerSheetTemplateVersion::factory()->create([
        'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
    ]);

    $booklet = OmrQuestionBooklet::query()->create([
        'code' => 'TEST-BOOKLET',
        'name' => 'Test Booklet',
        'is_synthetic' => false,
    ]);

    OmrQuestionBookletVersion::query()->create([
        'booklet_id' => $booklet->id,
        'version' => 1,
        'template_version_id' => $version->id,
        'status' => OmrQuestionBookletVersion::STATUS_DRAFT,
        'authoring_mode' => OmrQuestionBookletVersion::AUTHORING_MODE_STORED_QUESTIONS,
        'answer_key_hash' => hash('sha256', 'key'),
        'total_questions' => 50,
        'choices_per_question' => 4,
    ]);

    $draft = OmrTemplateAuthoringDraft::query()->create([
        'code' => 'COMPILED-BOUND-DRAFT',
        'name' => 'Bound Draft',
        'source_kind' => 'html',
        'stage' => 'compiled',
        'compiled_version_id' => $version->id,
        'authoring_json' => [],
        'lock_version' => 1,
        'owner_user_id' => $owner->id,
    ]);

    $response = $this->actingAs($owner)->delete(
        route('setup.exam-materials.answer-sheets.destroy', $draft)
    );

    $response->assertStatus(409);
    expect(OmrTemplateAuthoringDraft::query()->find($draft->id))->not->toBeNull()
        ->and(OmrAnswerSheetTemplateVersion::query()->find($version->id))->not->toBeNull();
});

test('non-owner cannot discard another users draft', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $other->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    $draft = OmrTemplateAuthoringDraft::query()->create([
        'code' => 'OTHER-DRAFT',
        'name' => 'Other Draft',
        'source_kind' => 'html',
        'stage' => 'draft',
        'authoring_json' => [],
        'lock_version' => 0,
        'owner_user_id' => $owner->id,
    ]);

    $response = $this->actingAs($other)->delete(
        route('setup.exam-materials.answer-sheets.destroy', $draft)
    );

    $response->assertStatus(404);
    expect(OmrTemplateAuthoringDraft::query()->find($draft->id))->not->toBeNull();
});
