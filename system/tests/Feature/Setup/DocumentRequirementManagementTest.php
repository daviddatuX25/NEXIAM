<?php

use App\Admission\Actions\InitializeAttemptRequirementSet;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttemptRequirementItem;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeRequirement;
use App\Models\User;
use App\Setup\Actions\SetDocumentRequirementActive;
use App\Setup\Actions\UpdateDocumentRequirement;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function documentRequirementManagementStaff(bool $authorized = true): User
{
    $institution = Institution::query()->firstOrCreate(
        ['code' => 'SYN-INST-001'],
        ['name' => 'Synthetic Institution'],
    );

    $actor = User::factory()->create();
    if ($authorized) {
        Permission::findOrCreate(CapabilityCatalog::SETUP_INSTITUTION_MANAGE, 'web');
        $actor->givePermissionTo(CapabilityCatalog::SETUP_INSTITUTION_MANAGE);
    }

    return $actor;
}

/** @param array<string, mixed> $overrides */
function documentRequirementRecord(array $overrides = []): IntakeRequirement
{
    $institution = Institution::current();

    return IntakeRequirement::query()->create(array_merge([
        'institution_id' => $institution->id,
        'code' => 'requirement-'.uniqid(),
        'label' => 'Supporting document',
        'applicant_track' => IntakeRequirement::TRACK_ALL,
        'item_kind' => IntakeRequirement::KIND_DOCUMENT,
        'is_mandatory' => true,
        'is_active' => true,
        'sort_order' => 100,
        'version' => 1,
    ], $overrides));
}

test('document requirement index create and edit pages expose the catalog contract', function (): void {
    $actor = documentRequirementManagementStaff();
    $this->actingAs($actor);

    $this->get(route('setup.requirements'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Requirements/Index')
            ->where('page_state', 'empty')
            ->has('requirements.data', 0)
            ->where('filters.search', '')
            ->where('filters.track', '')
            ->where('filters.kind', '')
            ->where('filters.mandatory', '')
            ->where('filters.status', '')
            ->has('tracks')
            ->has('kinds')
            ->has('capabilities.setup')
        );

    $this->get(route('setup.requirements.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Requirements/Create')
            ->has('tracks')
            ->has('kinds')
            ->where('defaults.applicant_track', 'freshman')
            ->where('defaults.item_kind', 'document')
            ->where('defaults.sort_order', 100)
            ->where('defaults.is_mandatory', true)
            ->where('defaults.is_active', true)
            ->has('capabilities.setup')
        );

    $requirement = documentRequirementRecord(['code' => 'form-138']);
    $this->get(route('setup.requirements.edit', $requirement))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Requirements/Edit')
            ->where('requirement.id', $requirement->id)
            ->where('requirement.code', 'form-138')
            ->where('requirement.version', 1)
            ->where('requirement.label', 'Supporting document')
            ->where('requirement.applicant_track', 'all')
            ->where('requirement.item_kind', 'document')
            ->has('tracks')
            ->has('kinds')
            ->has('capabilities.setup')
        );
});

test('document requirement routes deny unauthorized users and hide foreign institution records', function (): void {
    documentRequirementManagementStaff();
    $requirement = documentRequirementRecord(['code' => 'foreign-scope']);
    $foreignInstitution = Institution::query()->create(['code' => 'OTHER-INST', 'name' => 'Other Institution']);
    $foreign = documentRequirementRecord([
        'institution_id' => $foreignInstitution->id,
        'code' => 'foreign-requirement',
    ]);

    $unauthorized = documentRequirementManagementStaff(false);
    $this->actingAs($unauthorized)
        ->get(route('setup.requirements'))
        ->assertForbidden();

    $this->actingAs(documentRequirementManagementStaff())
        ->get(route('setup.requirements.edit', $foreign))
        ->assertNotFound();

    $this->put(route('setup.requirements.update', $foreign), [
        'label' => 'Changed label',
        'applicant_track' => 'freshman',
        'item_kind' => 'document',
        'is_mandatory' => true,
        'sort_order' => 100,
        'expected_version' => 1,
    ])->assertNotFound();

    $this->patch(route('setup.requirements.toggle-active', $foreign), [
        'is_active' => false,
        'expected_version' => 1,
    ])->assertNotFound();

    expect($requirement->fresh()->label)->toBe('Supporting document')
        ->and($foreign->fresh()->is_active)->toBeTrue();
});

test('creating requirements normalizes codes defaults flags and scopes uniqueness by institution', function (): void {
    $actor = documentRequirementManagementStaff();
    $this->actingAs($actor);

    $this->post(route('setup.requirements.store'), [
        'code' => '  PSA_Birth  ',
        'label' => 'PSA Birth Certificate',
        'applicant_track' => 'freshman',
        'item_kind' => 'document',
        'sort_order' => 20,
    ])->assertRedirect();

    $requirement = IntakeRequirement::query()->where('institution_id', Institution::current()->id)
        ->where('code', 'psa_birth')->firstOrFail();
    expect($requirement->version)->toBe(1)
        ->and($requirement->is_mandatory)->toBeTrue()
        ->and($requirement->is_active)->toBeTrue()
        ->and(AuditEvent::query()->where('event_type', 'setup.intake_requirement_upserted')
            ->where('payload->requirement_id', $requirement->id)->exists())->toBeTrue();

    $this->post(route('setup.requirements.store'), [
        'code' => ' PSA_BIRTH ',
        'label' => 'Duplicate requirement',
        'applicant_track' => 'freshman',
        'item_kind' => 'document',
        'sort_order' => 30,
    ])->assertSessionHasErrors('code');

    $otherInstitution = Institution::query()->create(['code' => 'OTHER-UNIQUE', 'name' => 'Other Institution']);
    $sameCodeElsewhere = documentRequirementRecord([
        'institution_id' => $otherInstitution->id,
        'code' => 'psa_birth',
    ]);
    expect($sameCodeElsewhere->code)->toBe('psa_birth');
});

test('updates editable requirement fields with optimistic version checks and prohibits code changes', function (): void {
    $actor = documentRequirementManagementStaff();
    $requirement = documentRequirementRecord(['code' => 'tor']);
    $this->actingAs($actor)
        ->put(route('setup.requirements.update', $requirement), [
            'label' => 'Official Transcript',
            'applicant_track' => 'transferee',
            'item_kind' => 'container',
            'is_mandatory' => false,
            'is_active' => false,
            'sort_order' => 25,
            'expected_version' => 1,
        ])
        ->assertRedirect();

    expect($requirement->fresh()->label)->toBe('Official Transcript')
        ->and($requirement->fresh()->applicant_track)->toBe('transferee')
        ->and($requirement->fresh()->item_kind)->toBe('container')
        ->and($requirement->fresh()->is_mandatory)->toBeFalse()
        ->and($requirement->fresh()->is_active)->toBeFalse()
        ->and($requirement->fresh()->sort_order)->toBe(25)
        ->and($requirement->fresh()->version)->toBe(2);

    $this->put(route('setup.requirements.update', $requirement), [
        'code' => 'renamed-code',
        'label' => 'Changed label',
        'applicant_track' => 'transferee',
        'item_kind' => 'container',
        'is_mandatory' => false,
        'sort_order' => 25,
        'expected_version' => 2,
    ])->assertSessionHasErrors('code');

    $this->put(route('setup.requirements.update', $requirement), [
        'label' => 'Stale update',
        'applicant_track' => 'transferee',
        'item_kind' => 'container',
        'is_mandatory' => false,
        'sort_order' => 25,
        'expected_version' => 1,
    ])->assertSessionHasErrors('expected_version');
});

test('active toggle only changes state once and rejects smuggled fields and stale versions', function (): void {
    $actor = documentRequirementManagementStaff();
    $requirement = documentRequirementRecord(['code' => 'toggle-target']);
    $url = route('setup.requirements.toggle-active', $requirement);
    $editableFields = $requirement->only(['code', 'label', 'applicant_track', 'item_kind', 'is_mandatory', 'sort_order']);

    $this->actingAs($actor)->patch($url, [
        'is_active' => false,
        'expected_version' => 1,
    ])->assertRedirect();
    expect($requirement->fresh()->is_active)->toBeFalse()
        ->and($requirement->fresh()->version)->toBe(2)
        ->and($requirement->fresh()->only(['code', 'label', 'applicant_track', 'item_kind', 'is_mandatory', 'sort_order']))
        ->toBe($editableFields);

    $this->patch($url, [
        'is_active' => false,
        'expected_version' => 2,
    ])->assertRedirect();
    expect($requirement->fresh()->version)->toBe(2)
        ->and($requirement->fresh()->only(['code', 'label', 'applicant_track', 'item_kind', 'is_mandatory', 'sort_order']))
        ->toBe($editableFields);

    foreach (['label', 'code', 'applicant_track'] as $field) {
        $payload = ['is_active' => true, 'expected_version' => 2, $field => 'smuggled'];
        $this->patch($url, $payload)->assertSessionHasErrors($field);
    }

    $this->patch($url, [
        'is_active' => true,
        'expected_version' => 1,
    ])->assertSessionHasErrors('expected_version');
    expect($requirement->fresh()->is_active)->toBeFalse()
        ->and($requirement->fresh()->version)->toBe(2);
});

test('requirement listing applies exact filters and escapes literal search wildcards', function (): void {
    $actor = documentRequirementManagementStaff();
    documentRequirementRecord(['code' => 'scope-all', 'label' => 'All-track record', 'applicant_track' => 'all']);
    documentRequirementRecord(['code' => 'scope-fresh', 'label' => 'Freshman record', 'applicant_track' => 'freshman']);
    documentRequirementRecord(['code' => 'scope-transfer', 'label' => 'Transferee record', 'applicant_track' => 'transferee']);
    documentRequirementRecord(['code' => 'scope-als', 'label' => 'ALS record', 'applicant_track' => 'als']);
    documentRequirementRecord(['code' => 'percent_100', 'label' => 'Grant 100% Complete', 'item_kind' => 'container', 'is_mandatory' => false, 'is_active' => false]);
    documentRequirementRecord(['code' => 'percentX100', 'label' => 'Grant 1000 Decoy', 'applicant_track' => 'transferee', 'is_mandatory' => false, 'is_active' => false]);
    $this->actingAs($actor);

    $codesFor = function (array $query): array {
        $page = $this->get(route('setup.requirements', $query));
        $page->assertOk()->assertInertia(fn (Assert $inertia) => $inertia->where('page_state', 'ready'));
        $rows = data_get($page->viewData('page'), 'props.requirements.data', []);

        return collect($rows)->pluck('code')->all();
    };

    expect($codesFor(['track' => 'all']))->toBe(['scope-all', 'percent_100'])
        ->and($codesFor(['track' => 'freshman']))->toBe(['scope-fresh'])
        ->and($codesFor(['track' => 'transferee']))->toBe(['scope-transfer', 'percentX100'])
        ->and($codesFor(['track' => 'als']))->toBe(['scope-als'])
        ->and($codesFor(['kind' => 'container']))->toBe(['percent_100'])
        ->and($codesFor(['mandatory' => 'optional']))->toBe(['percent_100', 'percentX100'])
        ->and($codesFor(['mandatory' => 'mandatory']))->toBe(['scope-all', 'scope-fresh', 'scope-transfer', 'scope-als'])
        ->and($codesFor(['status' => 'inactive']))->toBe(['percent_100', 'percentX100'])
        ->and($codesFor(['status' => 'active']))->toBe(['scope-all', 'scope-fresh', 'scope-transfer', 'scope-als'])
        ->and($codesFor(['search' => 'Grant 100%']))->toBe(['percent_100'])
        ->and($codesFor(['search' => 'percent_100']))->toBe(['percent_100']);

    $this->get(route('setup.requirements', ['search' => 'Freshman']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', 'Freshman')
            ->where('requirements.data.0.code', 'scope-fresh'));
});

test('editing and deactivating a catalog requirement leaves initialized attempt snapshots unchanged', function (): void {
    seedE2Foundation();
    $submission = submitSyntheticApplicant('requirement-catalog-snapshot@example.test', 'Catalog Snapshot');
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->first() ?? User::query()->where('email', 'like', '%registrar%')->firstOrFail();
    $attempt = $submission['attempt'];
    $set = app(InitializeAttemptRequirementSet::class)->handle(
        $attempt,
        $registrar,
        (int) $attempt->version,
        'requirement-catalog-snapshot-init',
    );
    /** @var AdmissionAttemptRequirementItem $item */
    $item = $set->items()->firstOrFail();
    $snapshot = $item->only(['code', 'label', 'item_kind', 'is_mandatory', 'sort_order']);
    $catalog = IntakeRequirement::query()
        ->where('institution_id', $attempt->institution_id)
        ->where('code', $item->code)
        ->firstOrFail();

    $setupActor = documentRequirementManagementStaff();
    app(UpdateDocumentRequirement::class)->handle($setupActor, $catalog, [
        'label' => 'Prospective catalog label',
        'applicant_track' => 'all',
        'item_kind' => 'container',
        'is_mandatory' => ! (bool) $catalog->is_mandatory,
        'sort_order' => ((int) $catalog->sort_order) + 10,
        'expected_version' => (int) $catalog->version,
    ]);
    $catalog->refresh();
    app(SetDocumentRequirementActive::class)->handle(
        $setupActor,
        $catalog,
        false,
        (int) $catalog->version,
    );

    expect($item->fresh()->only(['code', 'label', 'item_kind', 'is_mandatory', 'sort_order']))
        ->toBe($snapshot)
        ->and($catalog->fresh()->is_active)->toBeFalse()
        ->and($catalog->fresh()->label)->toBe('Prospective catalog label');
});
test('requirements management is available to registrar staff and institutional administrators', function (): void {
    seedE2Foundation();
    $actors = User::query()->whereIn('email', [
        'registrar.admin@nexiam.test',
        'registrar.clerk@nexiam.test',
        'admin.lead@nexiam.test',
    ])->get()->keyBy('email');

    expect($actors)->toHaveCount(3);
    foreach ($actors as $email => $actor) {
        $this->actingAs($actor)->get(route('setup.requirements.create'))->assertOk();

        $code = 'access-'.str_replace(['.', '@'], '-', $email);
        $this->actingAs($actor)->post(route('setup.requirements.store'), [
            'code' => $code,
            'label' => 'Access verified for '.$email,
            'applicant_track' => 'freshman',
            'item_kind' => 'document',
            'sort_order' => 200,
        ])->assertRedirect();

        expect(IntakeRequirement::query()->where('code', $code)->exists())->toBeTrue();
    }
});
