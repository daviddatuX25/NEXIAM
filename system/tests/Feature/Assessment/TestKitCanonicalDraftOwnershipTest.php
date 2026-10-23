<?php

use App\Assessment\Actions\Omr\Canonical\CreateOmrAuthoringDraft;
use App\Assessment\Actions\Omr\Canonical\SaveOmrCanonicalStudioState;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

function canonicalDraftOwnershipActor(Institution $institution, bool $authorized = true): User
{
    $actor = User::factory()->create();
    $actor->staffProfile()->create([
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);

    if ($authorized) {
        Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
        $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    return $actor;
}

function canonicalDraftOwnershipInstitution(string $code): Institution
{
    return Institution::query()->create([
        'code' => $code,
        'name' => $code.' University',
    ]);
}

test('canonical draft creation inserts its authorized owner and preserves authoring policies on the first row', function (): void {
    $institution = canonicalDraftOwnershipInstitution('CANONICAL-DRAFT-OWNER');
    $actor = canonicalDraftOwnershipActor($institution);
    $authoring = [
        'pages' => [
            ['page_no' => 1, 'asset_id' => 801, 'confirmed' => true],
            ['page_no' => 2, 'asset_id' => 802, 'confirmed' => false],
        ],
        'crop' => ['page_no' => 1, 'rotation' => 90, 'bounds' => [12, 18, 980, 1420]],
        'mapping' => ['field_blocks' => ['Question_1' => ['page_no' => 2, 'fieldType' => 'QTYPE_MCQ4']]],
        'studio' => ['current_stage' => 3, 'active_page_no' => 2, 'stage1Mode' => 'upload'],
    ];

    $draft = app(CreateOmrAuthoringDraft::class)->handle(
        actor: $actor,
        code: 'OWNED-DRAFT-01',
        name: 'Owned canonical draft',
        sourceKind: OmrTemplateAuthoringDraft::SOURCE_PDF,
        registrationMode: OmrRegistrationMode::DocumentBound,
        qrMode: OmrQrMode::Disabled,
        requestedPageCount: 2,
        authoring: $authoring,
    );

    expect($draft->institution_id)->toBe($institution->id)
        ->and($draft->owner_user_id)->toBe($actor->id)
        ->and($draft->template_id)->toBeNull()
        ->and($draft->is_synthetic)->toBeFalse()
        ->and($draft->source_kind)->toBe(OmrTemplateAuthoringDraft::SOURCE_PDF)
        ->and($draft->registration_mode)->toBe(OmrRegistrationMode::DocumentBound)
        ->and($draft->qr_mode)->toBe(OmrQrMode::Disabled)
        ->and($draft->requested_page_count)->toBe(2)
        ->and($draft->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_DRAFT)
        ->and($draft->authoring_json)->toBe($authoring)
        ->and($draft->fresh()->institution_id)->toBe($institution->id);

    $draft = app(SaveOmrCanonicalStudioState::class)->handle(
        $draft,
        $actor,
        ['boxes' => []],
        null,
        ['current_stage' => 3, 'active_page_no' => 1, 'stage1Mode' => 'upload'],
        (int) $draft->lock_version,
    );

    expect($draft->fresh()->authoring_json['studio']['active_page_no'])->toBe(1)
        ->and($draft->fresh()->owner_user_id)->toBe($actor->id)
        ->and($draft->fresh()->is_synthetic)->toBeFalse();
});

test('canonical draft template code lookup never links a template from another institution', function (): void {
    $institution = canonicalDraftOwnershipInstitution('CANONICAL-DRAFT-LOCAL');
    $otherInstitution = canonicalDraftOwnershipInstitution('CANONICAL-DRAFT-OTHER');
    $actor = canonicalDraftOwnershipActor($institution);
    $foreignTemplate = OmrAnswerSheetTemplate::query()->create([
        'institution_id' => $otherInstitution->id,
        'is_synthetic' => true,
        'code' => 'SHARED-TEMPLATE-CODE',
        'name' => 'Foreign synthetic template',
    ]);

    $draft = app(CreateOmrAuthoringDraft::class)->handle(
        actor: $actor,
        code: $foreignTemplate->code,
        name: 'Unbound local draft',
        sourceKind: OmrTemplateAuthoringDraft::SOURCE_HTML,
        registrationMode: OmrRegistrationMode::FiducialAnchored,
        qrMode: OmrQrMode::Signed,
        requestedPageCount: 1,
    );

    expect($draft->institution_id)->toBe($institution->id)
        ->and($draft->template_id)->toBeNull()
        ->and($draft->is_synthetic)->toBeFalse();
});

test('canonical draft creation takes synthetic provenance only from the exact institution-scoped template', function (): void {
    $institution = canonicalDraftOwnershipInstitution('CANONICAL-DRAFT-SYNTHETIC');
    $actor = canonicalDraftOwnershipActor($institution);
    $template = OmrAnswerSheetTemplate::query()->create([
        'institution_id' => $institution->id,
        'is_synthetic' => true,
        'code' => 'LOCAL-SYNTHETIC-TEMPLATE',
        'name' => 'Local synthetic template',
    ]);

    $draft = app(CreateOmrAuthoringDraft::class)->handle(
        actor: $actor,
        code: $template->code,
        name: 'Linked synthetic draft',
        sourceKind: OmrTemplateAuthoringDraft::SOURCE_IMAGE,
        registrationMode: OmrRegistrationMode::DocumentBound,
        qrMode: OmrQrMode::Disabled,
        requestedPageCount: 3,
        authoring: ['pages' => [['page_no' => 1, 'asset_id' => 810, 'crop' => ['x' => 4, 'y' => 9]]]],
    );

    expect($draft->institution_id)->toBe($institution->id)
        ->and($draft->template_id)->toBe($template->id)
        ->and($draft->is_synthetic)->toBeTrue()
        ->and($draft->requested_page_count)->toBe(3)
        ->and($draft->authoring_json['pages'][0]['crop'])->toBe(['x' => 4, 'y' => 9]);
});

test('canonical answer-sheet request cannot toggle a staff draft into synthetic provenance', function (): void {
    $institution = canonicalDraftOwnershipInstitution('CANONICAL-DRAFT-TOGGLE');
    $actor = canonicalDraftOwnershipActor($institution);

    $state = StaffAuthenticationState::query()->updateOrCreate(
        ['user_id' => $actor->id],
        [
            'activation_completed_at' => now(),
            'password_set_at' => now(),
            'session_version' => 1,
            'mfa_required' => false,
            'must_change_password' => false,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_generation' => (string) Str::uuid(),
        ],
    );
    $actor = $actor->fresh();
    $response = $this->actingAs($actor)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
        'auth.staff_inactivity_generation' => 'canonical-draft-test',
    ])->post(route('setup.exam-materials.answer-sheets.store'), [
        'code' => 'CLIENT-SYNTHETIC-TOGGLE',
        'name' => 'Client toggle attempt',
        'registration_mode' => OmrRegistrationMode::DocumentBound->value,
        'qr_mode' => OmrQrMode::Disabled->value,
        'requested_page_count' => 2,
        'is_synthetic' => true,
    ]);

    $response->assertSessionHasNoErrors();
    $draft = OmrTemplateAuthoringDraft::query()->where('code', 'CLIENT-SYNTHETIC-TOGGLE')->firstOrFail();
    $response->assertRedirect(route('setup.exam-materials.answer-sheets.edit', $draft));
    $this->assertAuthenticatedAs($actor);

    expect($draft->institution_id)->toBe($institution->id)
        ->and($draft->owner_user_id)->toBe($actor->id)
        ->and($draft->is_synthetic)->toBeFalse()
        ->and($draft->requested_page_count)->toBe(2);
});

test('canonical draft creation denies an actor without fresh OMR capability in an institution scope', function (): void {
    $institution = canonicalDraftOwnershipInstitution('CANONICAL-DRAFT-DENIED');
    $actor = canonicalDraftOwnershipActor($institution, authorized: false);

    expect(fn () => app(CreateOmrAuthoringDraft::class)->handle(
        actor: $actor,
        code: 'DENIED-DRAFT-01',
        name: 'Denied canonical draft',
        sourceKind: OmrTemplateAuthoringDraft::SOURCE_HTML,
        registrationMode: OmrRegistrationMode::FiducialAnchored,
        qrMode: OmrQrMode::Signed,
        requestedPageCount: 1,
    ))->toThrow(AuthorizationException::class);

    expect(OmrTemplateAuthoringDraft::query()->where('code', 'DENIED-DRAFT-01')->exists())->toBeFalse();
});
