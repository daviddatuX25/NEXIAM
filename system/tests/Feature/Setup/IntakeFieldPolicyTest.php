<?php

use App\Authorization\CapabilityCatalog;
use App\Http\Requests\Admission\SubmitAdmissionRequest;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeFieldKey;
use App\Models\Foundation\IntakeFieldPolicy;
use App\Models\Foundation\IntakeRequirementLevel;
use App\Models\User;
use App\Setup\Queries\ComputeSetupReadinessQuery;
use App\Setup\Services\IntakeFieldPolicyRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function createSetupActor(array $capabilities = [CapabilityCatalog::SETUP_INSTITUTION_MANAGE]): User
{
    $actor = User::factory()->create();
    foreach ($capabilities as $cap) {
        Permission::findOrCreate($cap, 'web');
        $actor->givePermissionTo($cap);
    }

    return $actor;
}

function createTestCycle(Institution $institution, bool $active = true): AcademicCycle
{
    return AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'name' => '2026-2027 1st Semester',
        'code' => 'AY-2026-1',
        'opens_on' => now()->subDay()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
        'is_active' => $active,
    ]);
}

test('intake field policy defaults seed non-negotiable floor on list', function (): void {
    $institution = Institution::query()->firstOrCreate(
        ['code' => 'SYN-INST-001'],
        ['name' => 'Synthetic Institution'],
    );

    $cycle = createTestCycle($institution);
    $actor = createSetupActor();

    $response = $this->actingAs($actor)->get('/setup/intake-fields');
    $response->assertOk();

    // Check that system mandatory fields are seeded as SystemMandatory
    $policies = IntakeFieldPolicy::query()->forCycle($institution->id, $cycle->id)->get();
    expect($policies)->not->toBeEmpty();
    $mandatoryKeys = [
        IntakeFieldKey::FirstName->value,
        IntakeFieldKey::LastName->value,
        IntakeFieldKey::Birthdate->value,
        IntakeFieldKey::Sex->value,
        IntakeFieldKey::CoursePreference1->value,
    ];

    foreach ($mandatoryKeys as $key) {
        $p = $policies->first(fn ($item) => $item->field_key->value === $key);
        expect($p)->not->toBeNull()
            ->and($p->requirement_level)->toBe(IntakeRequirementLevel::SystemMandatory);
    }
});

test('rejects modifying locked floor fields below mandatory level', function (): void {
    $institution = Institution::query()->firstOrCreate(
        ['code' => 'SYN-INST-001'],
        ['name' => 'Synthetic Institution'],
    );

    $cycle = createTestCycle($institution);
    $actor = createSetupActor();

    // Attempt to set birthdate to optional
    $response = $this->actingAs($actor)->put('/setup/intake-fields', [
        'academic_cycle_id' => $cycle->id,
        'policies' => [
            [
                'field_key' => IntakeFieldKey::Birthdate->value,
                'requirement_level' => IntakeRequirementLevel::Optional->value,
            ],
        ],
    ]);

    $response->assertSessionHasErrors(['policies.0.requirement_level']);
});

test('updates configurable fields and increments policy revision', function (): void {
    $institution = Institution::query()->firstOrCreate(
        ['code' => 'SYN-INST-001'],
        ['name' => 'Synthetic Institution'],
    );

    $cycle = createTestCycle($institution);
    $actor = createSetupActor();

    // First list seeds revision v1
    $this->actingAs($actor)->get('/setup/intake-fields');
    $initial = IntakeFieldPolicy::query()->forCycle($institution->id, $cycle->id)->get();
    expect($initial->first()->version)->toBe(1);

    // Update middle_name to required (it defaults to optional)
    $response = $this->actingAs($actor)->put('/setup/intake-fields', [
        'academic_cycle_id' => $cycle->id,
        'policies' => [
            [
                'field_key' => IntakeFieldKey::MiddleName->value,
                'requirement_level' => IntakeRequirementLevel::InstitutionRequired->value,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    // Verification: new policies exist at version 2
    $revised = IntakeFieldPolicy::query()->forCycle($institution->id, $cycle->id, 2)->get();
    expect($revised)->not->toBeEmpty();

    $middlePolicy = $revised->first(fn ($p) => $p->field_key === IntakeFieldKey::MiddleName);
    expect($middlePolicy->requirement_level)->toBe(IntakeRequirementLevel::InstitutionRequired);

    // Old version 1 policies remain unchanged (optional)
    $oldPolicies = IntakeFieldPolicy::query()->forCycle($institution->id, $cycle->id, 1)->get();
    $oldMiddle = $oldPolicies->first(fn ($p) => $p->field_key === IntakeFieldKey::MiddleName);
    expect($oldMiddle->requirement_level)->toBe(IntakeRequirementLevel::Optional);
});

test('public admission validation applies every cycle field policy', function (): void {
    $institution = Institution::query()->firstOrCreate(
        ['code' => 'SYN-INST-001'],
        ['name' => 'Synthetic Institution'],
    );
    $cycle = createTestCycle($institution);
    $actor = createSetupActor();

    app(IntakeFieldPolicyRegistry::class)->updateRevision($institution, $cycle, [
        IntakeFieldKey::MiddleName->value => IntakeRequirementLevel::InstitutionRequired->value,
        IntakeFieldKey::Suffix->value => IntakeRequirementLevel::Disabled->value,
    ], $actor);

    $request = SubmitAdmissionRequest::create('/', 'POST', [
        'academic_cycle_id' => $cycle->id,
        'suffix' => 'Jr.',
    ]);
    $request->setContainer(app());

    $validator = Validator::make($request->all(), $request->rules());

    expect($validator->errors()->has('middle_name'))->toBeTrue()
        ->and($validator->errors()->has('suffix'))->toBeTrue();
});

test('denies access when actor lacks setup manage capability', function (): void {
    $actor = User::factory()->create(); // no capabilities

    $response = $this->actingAs($actor)->get('/setup/intake-fields');
    $response->assertForbidden();
});

test('compute setup readiness includes intake fields category', function (): void {
    $institution = Institution::query()->firstOrCreate(
        ['code' => 'SYN-INST-001'],
        ['name' => 'Synthetic Institution'],
    );

    createTestCycle($institution);

    $query = app(ComputeSetupReadinessQuery::class);
    $data = $query->handle();

    $allCats = collect($data['groups'])->pluck('categories')->flatten(1);
    $intakeCat = $allCats->firstWhere('key', 'intake_fields');

    expect($intakeCat)->not->toBeNull()
        ->and($intakeCat['href'])->toBe('/setup/intake-fields');
});
test('intake field policies are accessible and mutable by Registrar staff and institutional administrators', function (): void {
    seedE2Foundation();
    $cycle = AcademicCycle::query()->where('is_active', true)->firstOrFail();
    $actors = User::query()->whereIn('email', [
        'registrar.admin@nexiam.test',
        'registrar.clerk@nexiam.test',
        'admin.lead@nexiam.test',
    ])->get();
    expect($actors)->toHaveCount(3);
    foreach ($actors as $actor) {
        $this->actingAs($actor)->get('/setup/intake-fields')->assertOk();
        $this->actingAs($actor)->put('/setup/intake-fields', [
            'academic_cycle_id' => $cycle->id,
            'policies' => [[
                'field_key' => 'middle_name',
                'requirement_level' => IntakeRequirementLevel::Optional->value,
            ]],
        ])->assertRedirect();
    }

    expect(IntakeFieldPolicy::query()
        ->where('academic_cycle_id', $cycle->id)
        ->where('field_key', IntakeFieldKey::MiddleName->value)
        ->orderByDesc('version')
        ->firstOrFail()->requirement_level)->toBe(IntakeRequirementLevel::Optional);
});
