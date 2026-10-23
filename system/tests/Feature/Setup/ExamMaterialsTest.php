<?php

use App\Assessment\Omr\Canonical\CanonicalOmrFixture;
use App\Assessment\Omr\Queries\ExamMaterialsQuery;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    $this->withoutVite();
    (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, 'web');
});

function attachCanonicalMaterialsTemplate(OmrTemplateAuthoringDraft $draft): OmrAnswerSheetTemplate
{
    $template = OmrAnswerSheetTemplate::query()->create([
        'institution_id' => $draft->institution_id,
        'is_synthetic' => $draft->is_synthetic,
        'code' => CanonicalOmrFixture::STABLE_CODE,
        'name' => $draft->name,
    ]);
    $draft->update(['template_id' => $template->id]);

    return $template;
}

it('projects the approved draft as one current answer-sheet lineage with truthful readiness', function (): void {
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
    $draft = OmrTemplateAuthoringDraft::query()
        ->where('code', CanonicalOmrFixture::STABLE_CODE)
        ->firstOrFail();

    $projection = app(ExamMaterialsQuery::class)->get($actor);

    expect($projection['answer_sheet']['status_label'])->toBe('Digitally verified')
        ->and($projection['answer_sheet']['active_count'])->toBe(0)
        ->and($projection['answer_sheet']['compiled_count'])->toBe(0)
        ->and($projection['answer_sheet']['physical_status'])->toBe('Physical qualification pending')
        ->and($projection['answer_sheet']['code'])->toBe(CanonicalOmrFixture::STABLE_CODE)
        ->and($projection['answer_sheet']['compiled_versions'])->toBe([])
        ->and($projection['booklets'])->toBe([])
        ->and($projection['omr_test']['status'])->toBe('needs_answer_sheet')
        ->and($projection['omr_test']['is_ready'])->toBeFalse()
        ->and($projection['omr_test']['open_url'])->toBe(route('setup.exam-materials.omr-test'));
    $this->actingAs($actor)
        ->get('/setup/booklets')
        ->assertRedirect('/setup/exam-materials/booklets');

    $this->actingAs($actor)
        ->get('/setup/test-kits')
        ->assertRedirect('/setup/exam-materials/test-kits');

});

it('projects legacy booklet lineage and fails closed on legacy physical qualification metadata', function (): void {
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
    $draft = OmrTemplateAuthoringDraft::query()->where('code', CanonicalOmrFixture::STABLE_CODE)->firstOrFail();
    $template = attachCanonicalMaterialsTemplate($draft);
    $baseVersion = [
        'page_width' => 2480,
        'page_height' => 3508,
        'identity_regions_json' => [],
        'provider_config_json' => [],
        'config_hash' => str_repeat('a', 64),
    ];
    $retired = $template->versions()->create([
        ...$baseVersion,
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_RETIRED,
    ]);
    $active = $template->versions()->create([
        ...$baseVersion,
        'version' => 2,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
        'authoring_manifest' => ['physical_qualification' => ['status' => 'physical_qualified']],
    ]);
    $booklet = OmrQuestionBooklet::query()->create([
        'institution_id' => $draft->institution_id,
        'is_synthetic' => $draft->is_synthetic,
        'code' => 'KEY-A',
        'name' => 'Entrance Key A',
    ]);
    $bookletVersion = OmrQuestionBookletVersion::query()->create([
        'booklet_id' => $booklet->id,
        'version' => 1,
        'status' => OmrQuestionBookletVersion::STATUS_ACTIVE,
        'answer_key_hash' => str_repeat('b', 64),
    ]);
    OmrTemplateBookletPair::query()->create([
        'template_version_id' => $active->id,
        'booklet_version_id' => $bookletVersion->id,
        'status' => OmrTemplateBookletPair::STATUS_ACTIVE,
    ]);

    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE);
    $this->actingAs($actor)
        ->get(route('setup.exam-materials.booklets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/BookletsIndex')
            ->where('booklets.0.status', 'draft')
            ->missing('booklets.0.code')
            ->missing('booklets.0.versions'));
    $projection = app(ExamMaterialsQuery::class)->get($actor, true);
    $activeProjection = collect($projection['answer_sheet']['compiled_versions'])->firstWhere('id', $active->id);

    expect($projection['answer_sheet']['physical_status'])->toBe('Physical qualification pending')
        ->and($activeProjection['physical_status'])->toBe('not_qualified');
});

it('derives OMR Test readiness from an active Answer Sheet and compatible finalized Booklet', function (): void {
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
    $draft = OmrTemplateAuthoringDraft::query()->where('code', CanonicalOmrFixture::STABLE_CODE)->firstOrFail();
    $template = attachCanonicalMaterialsTemplate($draft);
    $version = $template->versions()->create([
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_COMPILED,
        'page_width' => 2480,
        'page_height' => 3508,
        'identity_regions_json' => [],
        'provider_config_json' => [],
        'config_hash' => str_repeat('c', 64),
        'native_bundle_json' => ['pages' => []],
        'native_bundle_hash' => str_repeat('f', 64),
    ]);
    $booklet = OmrQuestionBooklet::query()->create([
        'institution_id' => $draft->institution_id,
        'is_synthetic' => $draft->is_synthetic,
        'code' => 'OMR-TEST-READY',
        'name' => 'OMR Test Ready',
    ]);
    $bookletVersion = OmrQuestionBookletVersion::query()->create([
        'booklet_id' => $booklet->id,
        'template_version_id' => $version->id,
        'version' => 1,
        'status' => OmrQuestionBookletVersion::STATUS_DRAFT,
        'answer_key_hash' => str_repeat('d', 64),
        'content_hash' => str_repeat('e', 64),
        'finalized_by_user_id' => $actor->id,
    ]);
    OmrBookletSet::query()->create([
        'booklet_version_id' => $bookletVersion->id,
        'set_code' => 'A',
        'status' => OmrBookletSet::STATUS_FINALIZED,
        'finalized_at' => now(),
    ]);
    $bookletVersion->update([
        'status' => OmrQuestionBookletVersion::STATUS_FINALIZED,
        'finalized_at' => now(),
    ]);

    $projection = app(ExamMaterialsQuery::class)->get($actor);

    expect($projection['omr_test']['status'])->toBe('ready')
        ->and($projection['omr_test']['is_ready'])->toBeTrue()
        ->and($projection['answer_sheet']['active_count'])->toBe(0)
        ->and($projection['answer_sheet']['compiled_count'])->toBe(1)
        ->and($projection['omr_test']['finalized_compatible_booklet_count'])->toBe(1);

    $this->actingAs($actor)
        ->get(route('setup.exam-materials.omr-test'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/OmrTest')
            ->where('page_state', 'ready')
            ->has('answer_sheets', 1)
            ->has('booklets', 1)
            ->where('acceptance_plans', [])
            ->where('booklets.0.sets.0.code', 'A'));
});

it('does not call a paired but unqualified lineage ready', function (): void {
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
    $draft = OmrTemplateAuthoringDraft::query()->where('code', CanonicalOmrFixture::STABLE_CODE)->firstOrFail();
    $template = attachCanonicalMaterialsTemplate($draft);
    $version = $template->versions()->create([
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
        'page_width' => 2480,
        'page_height' => 3508,
        'identity_regions_json' => [],
        'provider_config_json' => [],
        'config_hash' => str_repeat('c', 64),
    ]);
    $booklet = OmrQuestionBooklet::query()->create([
        'institution_id' => $draft->institution_id,
        'is_synthetic' => $draft->is_synthetic,
        'code' => 'KEY-PENDING',
        'name' => 'Pending Key',
    ]);
    $bookletVersion = OmrQuestionBookletVersion::query()->create([
        'booklet_id' => $booklet->id,
        'version' => 1,
        'status' => OmrQuestionBookletVersion::STATUS_ACTIVE,
        'answer_key_hash' => str_repeat('d', 64),
    ]);
    OmrTemplateBookletPair::query()->create([
        'template_version_id' => $version->id,
        'booklet_version_id' => $bookletVersion->id,
        'status' => OmrTemplateBookletPair::STATUS_ACTIVE,
    ]);

    $projection = app(ExamMaterialsQuery::class)->get($actor);

    expect($projection['omr_test']['status'])->not->toBe('ready')
        ->and($projection['candidate_readiness'])->not->toBeEmpty();
});

it('does not expose synthetic or unrelated answer-sheet rows from a dirty database', function (): void {
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
    OmrTemplateAuthoringDraft::query()->create([
        'institution_id' => $actor->actorScope()->institutionId,
        'is_synthetic' => true,
        'code' => 'SYN-DIAG-CONTRACT',
        'name' => 'Diagnostic',
        'source_kind' => 'html',
        'stage' => 'draft',
        'owner_user_id' => $actor->id,
    ]);
    $projection = app(ExamMaterialsQuery::class)->get($actor);

    expect($projection['answer_sheet']['display_name'])->toBe('NEXIAM answer sheet')
        ->and($projection['answer_sheet']['code'])->toBe(CanonicalOmrFixture::STABLE_CODE);
});

it('denies Test Kit catalog access without assessment authority', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('setup.exam-materials.test-kits.index'))
        ->assertForbidden();
});

it('opens Booklets with booklet-management authority alone', function (): void {
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE, 'web');
    $actor->revokePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE);
    OmrQuestionBooklet::query()->create([
        'institution_id' => $actor->actorScope()->institutionId,
        'is_synthetic' => true,
        'code' => 'LOW-VOLUME-INDEX',
        'name' => 'Entrance assessment',
    ]);
    $this->actingAs($actor)
        ->get(route('setup.exam-materials.booklets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/BookletsIndex')
            ->has('answer_sheets')
            ->has('booklets')
            ->has('booklets.0', fn (Assert $booklet) => $booklet
                ->hasAll([
                    'id',
                    'name',
                    'url',
                    'mode',
                    'status',
                    'question_count',
                    'required_count',
                    'aptitude_count',
                    'sets',
                    'next_action',
                    'version_id',
                    'version',
                    'lifecycle_status',
                    'can_discard',
                    'can_archive',
                    'can_restore',
                    'is_in_use',
                    'destroy_url',
                    'archive_url',
                    'restore_url',
                    'answer_sheet',
                    'has_paired_answer_sheet',
                    'thumbnail_url',
                    'read_only',
                ])
                ->where('read_only', false)
                ->missingAll(['aptitude_areas', 'question_content', 'answer_key']))
            ->where('selected_booklet_id', null));
});
