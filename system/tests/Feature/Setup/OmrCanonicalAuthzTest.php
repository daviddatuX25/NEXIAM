<?php

use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Foundation\AuditEvent;
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

    $this->actingAs($outsider)->get(route('setup.exam-materials.answer-sheets.edit', $draft))->assertForbidden();
    $this->actingAs($authorized)->get(route('setup.omr.templates.canonical.diagnostics'))->assertOk();
    $this->actingAs($authorized)->delete(route('setup.exam-materials.answer-sheets.destroy', $draft))->assertNotFound();
});

test('answer-sheet creation pins the physical paper policy before opening Studio', function (): void {
    $actor = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    $response = $this->actingAs($actor)->post(
        route('setup.exam-materials.answer-sheets.store'),
        [
            'code' => 'PAPER-POLICY',
            'name' => 'Two-page document-bound sheet',
            'registration_mode' => 'document_bound',
            'qr_mode' => 'qr_disabled',
            'requested_page_count' => 2,
        ],
    );

    $draft = OmrTemplateAuthoringDraft::query()->where('code', 'PAPER-POLICY')->firstOrFail();

    $response->assertRedirect(route('setup.exam-materials.answer-sheets.edit', $draft));
    expect($draft->source_kind)->toBe(OmrTemplateAuthoringDraft::SOURCE_HTML)
        ->and($draft->registration_mode->value)->toBe('document_bound')
        ->and($draft->qr_mode->value)->toBe('qr_disabled')
        ->and($draft->requested_page_count)->toBe(2)
        ->and(data_get($draft->authoring_json, 'studio.stage1Mode'))->toBeNull();
    $audit = AuditEvent::query()
        ->where('event_type', 'omr.canonical_draft_created')
        ->latest('created_at')
        ->firstOrFail();
    expect($audit->payload)->toMatchArray([
        'draft_id' => $draft->id,
        'registration_mode' => 'document_bound',
        'qr_mode' => 'qr_disabled',
        'requested_page_count' => 2,
    ]);
});

test('answer-sheet creation rejects missing policy and page counts outside one through four', function (array $payload, array $errors): void {
    $actor = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    $this->actingAs($actor)
        ->from(route('setup.exam-materials.answer-sheets.index'))
        ->post(route('setup.exam-materials.answer-sheets.store'), [
            'code' => 'INVALID-POLICY',
            'name' => 'Invalid policy',
            ...$payload,
        ])
        ->assertRedirect(route('setup.exam-materials.answer-sheets.index'))
        ->assertSessionHasErrors($errors);

    expect(OmrTemplateAuthoringDraft::query()->where('code', 'INVALID-POLICY')->exists())->toBeFalse();
})->with([
    'missing policy' => [[], ['registration_mode', 'qr_mode', 'requested_page_count']],
    'zero pages' => [[
        'registration_mode' => 'document_bound',
        'qr_mode' => 'qr_disabled',
        'requested_page_count' => 0,
    ], ['requested_page_count']],
    'five pages' => [[
        'registration_mode' => 'fiducial_anchored',
        'qr_mode' => 'qr_signed',
        'requested_page_count' => 5,
    ], ['requested_page_count']],
]);
