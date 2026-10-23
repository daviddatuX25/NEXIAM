<?php

use App\Authorization\CapabilityCatalog;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\ResultSheet\ResultSheetTemplateDraft;
use App\Models\User;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;
use Inertia\Testing\AssertableInertia as Assert;

function resultSheetDraftState(array $overrides = []): array
{
    return array_merge([
        'name' => 'Resumable Result Sheet',
        'mode' => 'html',
        'source_kind' => 'builder',
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'content' => '',
        'builder_json' => ResultSheetBuilderSchema::defaultDocument(),
        'builder_schema_version' => ResultSheetBuilderSchema::VERSION,
        'compiler_version' => ResultSheetBuilderSchema::COMPILER_VERSION,
        'watermark_text' => '',
        'is_active' => false,
    ], $overrides);
}

test('result sheet draft stage and builder state survive a fresh create page load', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $draftResponse = actingAsAdmissionSupportStaff($this, $guidance)->postJson(route('setup.result-templates.drafts.store'), [
        'stage' => 2,
        'state' => resultSheetDraftState(),
    ]);

    $draftResponse->assertCreated()
        ->assertJsonPath('draft.stage', 2)
        ->assertJsonPath('draft.lock_version', 1);

    $draft = ResultSheetTemplateDraft::query()->findOrFail($draftResponse->json('draft.id'));

    actingAsAdmissionSupportStaff($this, $guidance)
        ->get(route('setup.result-templates.create', ['draft' => $draft->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/ResultSheetTemplates/Create')
            ->where('draft.id', $draft->id)
            ->where('draft.stage', 2)
            ->where('draft.state.name', 'Resumable Result Sheet'));
});

test('result sheet draft rehydrates against its source template edit route', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $template = ResultSheetTemplate::query()->create([
        'name' => 'Existing Result Sheet',
        'mode' => ResultSheetTemplate::MODE_HTML,
        'source_kind' => ResultSheetTemplate::SOURCE_BUILDER,
        'paper_size' => ResultSheetTemplate::PAPER_A4,
        'orientation' => ResultSheetTemplate::ORIENTATION_PORTRAIT,
        'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
        'content' => '',
        'builder_json' => ResultSheetBuilderSchema::defaultDocument(),
        'builder_schema_version' => ResultSheetBuilderSchema::VERSION,
        'compiler_version' => ResultSheetBuilderSchema::COMPILER_VERSION,
        'is_active' => false,
    ]);

    $draft = actingAsAdmissionSupportStaff($this, $guidance)
        ->postJson(route('setup.result-templates.drafts.store'), [
            'source_template_id' => $template->id,
            'stage' => 2,
            'state' => resultSheetDraftState(['name' => 'Existing Result Sheet']),
        ])
        ->assertCreated()
        ->json('draft');

    actingAsAdmissionSupportStaff($this, $guidance)
        ->get(route('setup.result-templates.edit', [$template, 'draft' => $draft['id']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/ResultSheetTemplates/Edit')
            ->where('draft.id', $draft['id'])
            ->where('draft.source_template_id', $template->id)
            ->where('draft.stage', 2));
});

test('result sheet drafts use optimistic locking and reject stale stage updates', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $draft = actingAsAdmissionSupportStaff($this, $guidance)
        ->postJson(route('setup.result-templates.drafts.store'), [
            'stage' => 2,
            'state' => resultSheetDraftState(),
        ])
        ->assertCreated()
        ->json('draft');

    actingAsAdmissionSupportStaff($this, $guidance)
        ->putJson(route('setup.result-templates.drafts.update', $draft['id']), [
            'stage' => 3,
            'lock_version' => 1,
            'state' => resultSheetDraftState(['name' => 'Verify stage']),
        ])
        ->assertOk()
        ->assertJsonPath('draft.stage', 3)
        ->assertJsonPath('draft.lock_version', 2);

    actingAsAdmissionSupportStaff($this, $guidance)
        ->putJson(route('setup.result-templates.drafts.update', $draft['id']), [
            'stage' => 2,
            'lock_version' => 1,
            'state' => resultSheetDraftState(),
        ])
        ->assertStatus(409)
        ->assertJsonPath('error', 'draft_conflict');
});

test('final template save clears its completed authoring draft', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $draft = actingAsAdmissionSupportStaff($this, $guidance)
        ->postJson(route('setup.result-templates.drafts.store'), [
            'stage' => 3,
            'state' => resultSheetDraftState(),
        ])
        ->assertCreated()
        ->json('draft');

    actingAsAdmissionSupportStaff($this, $guidance)
        ->post(route('setup.result-templates.store'), [
            'draft_id' => $draft['id'],
            'name' => 'Completed Result Sheet',
            'mode' => 'html',
            'source_kind' => 'builder',
            'builder_json' => json_encode(resultSheetDraftState()['builder_json'], JSON_THROW_ON_ERROR),
            'content' => '',
            'paper_size' => 'a4',
            'orientation' => 'portrait',
            'logical_unit' => 'full',
            'is_active' => false,
        ])
        ->assertRedirect();

    expect(ResultSheetTemplateDraft::query()->whereKey($draft['id'])->exists())->toBeFalse();
});

test('owner can delete authoring draft and foreign or unauthorized users are refused', function (): void {
    seedE2Foundation();
    $owner = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $otherGuidance = User::query()->where('email', 'guidance.support@nexiam.test')->firstOrFail();
    $otherGuidance->givePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
    $unauthorized = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $draft = actingAsAdmissionSupportStaff($this, $owner)
        ->postJson(route('setup.result-templates.drafts.store'), [
            'stage' => 2,
            'state' => resultSheetDraftState(['name' => 'Draft To Discard']),
        ])
        ->assertCreated()
        ->json('draft');

    actingAsAdmissionSupportStaff($this, $unauthorized)
        ->deleteJson(route('setup.result-templates.drafts.destroy', $draft['id']))
        ->assertForbidden();

    expect(ResultSheetTemplateDraft::query()->whereKey($draft['id'])->exists())->toBeTrue();

    actingAsAdmissionSupportStaff($this, $otherGuidance)
        ->deleteJson(route('setup.result-templates.drafts.destroy', $draft['id']))
        ->assertNotFound();

    expect(ResultSheetTemplateDraft::query()->whereKey($draft['id'])->exists())->toBeTrue();

    actingAsAdmissionSupportStaff($this, $owner)
        ->deleteJson(route('setup.result-templates.drafts.destroy', $draft['id']))
        ->assertNoContent();

    expect(ResultSheetTemplateDraft::query()->whereKey($draft['id'])->exists())->toBeFalse();
});
