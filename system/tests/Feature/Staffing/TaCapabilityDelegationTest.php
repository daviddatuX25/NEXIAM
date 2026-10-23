<?php

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\AuditEvent;
use App\Models\User;
use App\Staffing\Actions\GrantTestAdministratorCapabilities;
use App\Staffing\Actions\RevokeTestAdministratorCapabilities;
use Database\Seeders\CapabilityCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function taGuidanceCounselor(): User
{
    return User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
}

function taGuidanceClerk(): User
{
    return User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
}

test('personnel page shows TA delegation and preserves exam capability when revoked', function (): void {
    seedE2Foundation();
    $counselor = taGuidanceCounselor();
    $clerk = taGuidanceClerk();

    $this->actingAs($counselor)
        ->get(route('staff.personnel.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Staff/Personnel/Index')
            ->where('can_delegate_ta', true)
            ->where('users.data', fn ($rows) => collect($rows)->contains(
                fn ($row) => (int) ($row['id'] ?? 0) === $clerk->id
                    && ($row['ta_status'] ?? '') === 'none'
                    && ($row['can_delegate_ta'] ?? false) === true
            ))
            ->where('users.data', fn ($rows) => collect($rows)->contains(
                fn ($row) => (int) ($row['id'] ?? 0) === $counselor->id
                    && ($row['ta_status'] ?? '') === 'inherent'
            ))
        );

    $this->actingAs($counselor)
        ->post(route('staff.personnel.grant-ta', $clerk))
        ->assertRedirect();

    $clerk->refresh();
    expect($clerk->getRoleNames()->contains('test_administrator'))->toBeFalse()
        ->and($clerk->hasPermissionTo(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE))->toBeTrue()
        ->and($clerk->hasPermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG))->toBeTrue()
        ->and($clerk->hasPermissionTo(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE))->toBeTrue()
        ->and($clerk->hasPermissionTo(CapabilityCatalog::GUIDANCE_BOOKLET_CONTENT))->toBeTrue()
        ->and($clerk->hasPermissionTo(CapabilityCatalog::PROGRAM_QUOTA_MANAGE))->toBeTrue()
        ->and($clerk->hasPermissionTo(CapabilityCatalog::GUIDANCE_SELECTION_MANAGE))->toBeTrue()
        ->and($clerk->hasPermissionTo(CapabilityCatalog::GUIDANCE_FOLLOW_UP_MANAGE))->toBeTrue()
        ->and($clerk->hasPermissionTo(CapabilityCatalog::GUIDANCE_TA_DELEGATE))->toBeFalse();

    $this->actingAs($clerk)
        ->get('/setup/exam-materials/test-kits')
        ->assertOk();

    $this->actingAs($clerk)
        ->get('/setup/exam-materials/booklets')
        ->assertOk();

    $this->actingAs($clerk)
        ->get('/setup/exam-materials/omr-test')
        ->assertOk();

    $this->actingAs($counselor)
        ->post(route('staff.personnel.revoke-ta', $clerk))
        ->assertRedirect();
    $clerk->refresh();

    expect($clerk->hasPermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG))->toBeFalse()
        ->and($clerk->hasPermissionTo(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE))->toBeFalse()
        ->and($clerk->hasPermissionTo(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE))->toBeTrue();

    $this->actingAs($clerk)
        ->get('/setup/exam-materials/test-kits')
        ->assertForbidden();

    $this->actingAs($clerk)
        ->get('/exams/monitoring')
        ->assertOk();
});

test('hr and registrar cannot grant ta; counselor cannot grant registrar', function (): void {
    seedE2Foundation();
    $counselor = taGuidanceCounselor();
    $clerk = taGuidanceClerk();
    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($hr)
        ->post(route('staff.personnel.grant-ta', $clerk))
        ->assertForbidden();

    $this->actingAs($registrar)
        ->post(route('staff.personnel.grant-ta', $clerk))
        ->assertForbidden();

    $this->actingAs($counselor)
        ->post(route('staff.personnel.grant-ta', $registrar))
        ->assertRedirect()
        ->assertSessionHasErrors('ta');
});

test('personnel page hides ta overlay column actions for hr without delegate cap', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();

    $this->actingAs($hr)
        ->get(route('staff.personnel.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can_delegate_ta', false)
            ->where('users.data', fn ($rows) => collect($rows)->every(
                fn ($row) => ($row['can_delegate_ta'] ?? false) === false
            ))
        );
});

test('guidance.ta.delegate stays outside the ta capability set', function (): void {
    expect(CapabilityCatalog::testAdministratorCapabilities())
        ->not->toContain(CapabilityCatalog::GUIDANCE_TA_DELEGATE)
        ->and(CapabilityCatalog::roleHelperGrants()['guidance_counselor'])
        ->toContain(CapabilityCatalog::GUIDANCE_TA_DELEGATE)
        ->and(CapabilityCatalog::roleHelperGrants()['guidance_clerk'])
        ->not->toContain(CapabilityCatalog::GUIDANCE_TA_DELEGATE);
});

test('ordinary guidance clerks and support staff cannot score or prepare results but retain scheduling', function (): void {
    $fixture = buildGradingFixture($this);
    $session = $fixture['session'];
    $attempt = $fixture['attempts'][0];
    $authorizer = app(CapabilityAuthorizer::class);

    foreach ([
        'guidance_clerk' => 'guidance.clerk@nexiam.test',
        'guidance_support_staff' => 'guidance.support@nexiam.test',
    ] as $role => $email) {
        $target = User::query()->where('email', $email)->firstOrFail();
        expect($target->hasRole($role))->toBeTrue();

        expect($authorizer->decideFresh($target, CapabilityCatalog::GUIDANCE_SCORING_MANUAL)->allowed)->toBeFalse()
            ->and($authorizer->decideFresh($target, CapabilityCatalog::GUIDANCE_RESULTS_PREPARE)->allowed)->toBeFalse()
            ->and($authorizer->decideFresh($target, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)->allowed)->toBeTrue();

        $this->actingAs($target)->get('/scoring')->assertForbidden();
        $this->actingAs($target)->put("/scoring/sessions/{$session->id}/attempts/{$attempt->id}/scores", [
            'scores' => [],
            'idempotency_key' => 'unauthorized-score-'.$role,
        ])->assertForbidden();
        $this->actingAs($target)->post("/release/{$attempt->id}/prepare", [])->assertForbidden();

        $delegator = taGuidanceCounselor();
        app(GrantTestAdministratorCapabilities::class)->handle($delegator, $target);

        expect($authorizer->decideFresh($target->fresh(), CapabilityCatalog::GUIDANCE_SCORING_MANUAL)->allowed)->toBeTrue()
            ->and($authorizer->decideFresh($target->fresh(), CapabilityCatalog::GUIDANCE_RESULTS_PREPARE)->allowed)->toBeTrue();
        $this->actingAs($target->fresh())->get('/scoring')->assertOk();

        app(RevokeTestAdministratorCapabilities::class)->handle($delegator, $target->fresh());
        expect($authorizer->decideFresh($target->fresh(), CapabilityCatalog::GUIDANCE_SCORING_MANUAL)->allowed)->toBeFalse()
            ->and($authorizer->decideFresh($target->fresh(), CapabilityCatalog::GUIDANCE_RESULTS_PREPARE)->allowed)->toBeFalse();
        $this->actingAs($target->fresh())->get('/scoring')->assertForbidden();
    }
});

test('capability catalog resync preserves explicit TA delegation but cannot restore role-helper scoring grants', function (): void {
    seedE2Foundation();
    $clerk = taGuidanceClerk();
    $support = User::query()->where('email', 'guidance.support@nexiam.test')->firstOrFail();
    $delegator = taGuidanceCounselor();
    app(GrantTestAdministratorCapabilities::class)->handle($delegator, $clerk);
    app(CapabilityCatalogSeeder::class)->run();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($clerk->fresh()->hasDirectPermission(CapabilityCatalog::GUIDANCE_SCORING_MANUAL))->toBeTrue()
        ->and($clerk->fresh()->hasDirectPermission(CapabilityCatalog::GUIDANCE_RESULTS_PREPARE))->toBeTrue()
        ->and($clerk->fresh()->hasPermissionTo(CapabilityCatalog::GUIDANCE_SCORING_MANUAL))->toBeTrue()
        ->and($clerk->fresh()->hasPermissionTo(CapabilityCatalog::GUIDANCE_RESULTS_PREPARE))->toBeTrue();

    expect($support->fresh()->hasPermissionTo(CapabilityCatalog::GUIDANCE_SCORING_MANUAL))->toBeFalse()
        ->and($support->fresh()->hasPermissionTo(CapabilityCatalog::GUIDANCE_RESULTS_PREPARE))->toBeFalse();
    app(RevokeTestAdministratorCapabilities::class)->handle($delegator, $clerk->fresh());
    expect($clerk->fresh()->hasPermissionTo(CapabilityCatalog::GUIDANCE_SCORING_MANUAL))->toBeFalse()
        ->and($clerk->fresh()->hasPermissionTo(CapabilityCatalog::GUIDANCE_RESULTS_PREPARE))->toBeFalse();
});

test('scoring grant removal migration removes only the historical role grants and audits once', function (): void {
    seedE2Foundation();
    $clerk = taGuidanceClerk();
    $support = User::query()->where('email', 'guidance.support@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $support->givePermissionTo(CapabilityCatalog::legacyTestAdministratorCapabilities());
    $delegator = taGuidanceCounselor();
    app(GrantTestAdministratorCapabilities::class)->handle($delegator, $clerk);

    $roleIds = Role::query()
        ->whereIn('name', ['guidance_clerk', 'guidance_support_staff'])
        ->pluck('id');
    $permissions = Permission::query()
        ->whereIn('name', [CapabilityCatalog::GUIDANCE_SCORING_MANUAL, CapabilityCatalog::GUIDANCE_RESULTS_PREPARE])
        ->pluck('id', 'name');
    foreach ($roleIds as $roleId) {
        foreach ($permissions as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }
    $registrar->assignRole('registrar_clerk');
    $registrarRole = $registrar->roles()->firstOrFail();
    DB::table('role_has_permissions')->insert([
        'permission_id' => $permissions[CapabilityCatalog::GUIDANCE_SCORING_MANUAL],
        'role_id' => $registrarRole->id,
    ]);

    $migration = require database_path('migrations/2026_10_19_120000_remove_default_guidance_scoring_grants.php');
    $migration->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $eventCount = AuditEvent::query()
        ->where('event_type', 'staff.guidance_default_scoring_grants_removed')
        ->count();
    expect($eventCount)->toBe(2)
        ->and($clerk->fresh()->hasDirectPermission(CapabilityCatalog::GUIDANCE_SCORING_MANUAL))->toBeTrue()
        ->and($clerk->fresh()->hasDirectPermission(CapabilityCatalog::GUIDANCE_RESULTS_PREPARE))->toBeTrue()
        ->and($clerk->fresh()->hasPermissionTo(CapabilityCatalog::GUIDANCE_SCORING_MANUAL))->toBeTrue()
        ->and($clerk->fresh()->hasPermissionTo(CapabilityCatalog::GUIDANCE_RESULTS_PREPARE))->toBeTrue()
        ->and($clerk->fresh()->hasPermissionTo(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE))->toBeTrue()
        ->and($support->fresh()->hasDirectPermission(CapabilityCatalog::GUIDANCE_SCORING_MANUAL))->toBeTrue()
        ->and($support->fresh()->hasPermissionTo(CapabilityCatalog::GUIDANCE_SCORING_MANUAL))->toBeTrue()
        ->and($support->fresh()->hasDirectPermission(CapabilityCatalog::GUIDANCE_RESULTS_PREPARE))->toBeTrue()
        ->and($support->fresh()->hasPermissionTo(CapabilityCatalog::GUIDANCE_RESULTS_PREPARE))->toBeTrue();
    foreach (CapabilityCatalog::legacyTestAdministratorCapabilities() as $capability) {
        expect($support->fresh()->hasDirectPermission($capability))->toBeTrue();
    }
    expect(DB::table('role_has_permissions')
        ->whereIn('role_id', $roleIds)
        ->whereIn('permission_id', $permissions->values())
        ->count())->toBe(0);

    expect(DB::table('role_has_permissions')
        ->where('role_id', $registrarRole->id)
        ->where('permission_id', $permissions[CapabilityCatalog::GUIDANCE_SCORING_MANUAL])
        ->exists())->toBeTrue();

    $clerkRole = Role::query()->where('name', 'guidance_clerk')->firstOrFail();
    $schedulePermissionId = Permission::query()
        ->where('name', CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
        ->value('id');
    expect(DB::table('role_has_permissions')
        ->where('role_id', $clerkRole->id)
        ->where('permission_id', $schedulePermissionId)
        ->exists())->toBeTrue();
    $migration->up();
    expect(AuditEvent::query()
        ->where('event_type', 'staff.guidance_default_scoring_grants_removed')
        ->count())->toBe($eventCount);
});

test('scoring grant migration rolls back role edges when audit recording fails', function (): void {
    seedE2Foundation();
    $roleIds = Role::query()
        ->whereIn('name', ['guidance_clerk', 'guidance_support_staff'])
        ->pluck('id');
    $permissionIds = Permission::query()
        ->whereIn('name', [CapabilityCatalog::GUIDANCE_SCORING_MANUAL, CapabilityCatalog::GUIDANCE_RESULTS_PREPARE])
        ->pluck('id');
    foreach ($roleIds as $roleId) {
        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    $this->app->instance(
        RecordAuditEvent::class,
        Mockery::mock(RecordAuditEvent::class)
            ->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException('audit-failure'))
            ->getMock(),
    );
    $migration = require database_path('migrations/2026_10_19_120000_remove_default_guidance_scoring_grants.php');

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'audit-failure')
        ->and(DB::table('role_has_permissions')
            ->whereIn('role_id', $roleIds)
            ->whereIn('permission_id', $permissionIds)
            ->count())->toBe($roleIds->count() * $permissionIds->count());
});
