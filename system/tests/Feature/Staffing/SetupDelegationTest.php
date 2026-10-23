<?php

use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Staffing\InstitutionalSetupDelegation;
use App\Models\User;
use App\Staffing\Actions\DeactivateStaffAccount;
use App\Staffing\Actions\SyncStaffRoles;
use Database\Seeders\CapabilityCatalogSeeder;
use Spatie\Permission\PermissionRegistrar;

test('admin lead sees eligible cross-office staff without HR account mutation rights', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $this->actingAs($admin)->get('/staff/personnel')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Staff/Personnel/Index')
        ->where('can_manage_personnel', false)
        ->where('can_full_hr', false)
        ->where('can_delegate_setup', true)
        ->where('offices', [
            ['value' => 'admin', 'label' => 'Admin Office'],
            ['value' => 'registrar', 'label' => 'Registrar Office'],
            ['value' => 'guidance', 'label' => 'Guidance Office'],
            ['value' => 'program', 'label' => 'Academic Programs'],
        ])
        ->where('users.data', fn ($rows) => collect($rows)->contains(fn ($row) => (int) $row['id'] === $counselor->id
            && $row['can_delegate_setup'] === true
            && $row['can_manage_user'] === false
            && $row['can_issue_voucher'] === false))
        ->where('users.data', fn ($rows) => collect($rows)->contains(fn ($row) => (int) $row['id'] === $registrar->id
            && $row['can_delegate_setup'] === true
            && $row['can_manage_user'] === false
            && $row['can_issue_voucher'] === false)));

    $this->actingAs($admin)->post('/staff/personnel', [])->assertForbidden();
    $this->actingAs($admin)->post("/staff/personnel/{$counselor->id}/deactivate", [])->assertForbidden();
});

test('setup delegation grants idempotently, survives capability resync, and revokes cleanly', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $grant = fn () => $this->actingAs($admin)->post("/staff/personnel/{$counselor->id}/grant-setup", [
        'reason' => 'Cover institutional cycle setup.',
    ])->assertRedirect();
    $grant();
    $grant();

    $delegation = InstitutionalSetupDelegation::query()->where('recipient_id', $counselor->id)->whereNull('revoked_at')->firstOrFail();
    $counselor->refresh();
    expect(InstitutionalSetupDelegation::query()->where('recipient_id', $counselor->id)->whereNull('revoked_at')->count())->toBe(1)
        ->and($counselor->hasPermissionTo(CapabilityCatalog::SETUP_INSTITUTION_MANAGE))->toBeTrue()
        ->and($counselor->hasPermissionTo(CapabilityCatalog::SETUP_PROGRAMS_MANAGE))->toBeTrue()
        ->and($counselor->staffProfile->office)->toBe('guidance')
        ->and($counselor->hasPermissionTo(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE))->toBeTrue();

    expect(AuditEvent::query()->where('event_type', 'staff.setup_delegation_granted')->exists())->toBeTrue();
    app(CapabilityCatalogSeeder::class)->run();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $counselor->refresh();
    expect($counselor->hasPermissionTo(CapabilityCatalog::SETUP_INSTITUTION_MANAGE))->toBeTrue()
        ->and($counselor->hasPermissionTo(CapabilityCatalog::SETUP_PROGRAMS_MANAGE))->toBeTrue();

    $this->actingAs($counselor)->post(route('setup.cycles.store'), [
        'school_year' => '2027-2028',
        'semester' => '1',
        'application_start_date' => '2027-08-01',
        'application_end_date' => '2027-12-31',
    ])->assertRedirect(route('setup.cycles'));
    expect(AcademicCycle::query()->where('school_year', '2027-2028')->where('semester', '1')->exists())->toBeTrue();
    $this->actingAs($counselor)->get('/setup/rooms')->assertOk();

    $this->actingAs($admin)->post("/staff/personnel/{$counselor->id}/revoke-setup", [
        'reason' => 'Coverage ended.',
    ])->assertRedirect();
    $delegation->refresh();
    $counselor->refresh();
    expect($delegation->revoked_at)->not->toBeNull()
        ->and($counselor->hasPermissionTo(CapabilityCatalog::SETUP_INSTITUTION_MANAGE))->toBeFalse()
        ->and($counselor->hasPermissionTo(CapabilityCatalog::SETUP_PROGRAMS_MANAGE))->toBeFalse()
        ->and($counselor->hasPermissionTo(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE))->toBeTrue()
        ->and($counselor->hasRole('guidance_counselor'))->toBeTrue()
        ->and(AuditEvent::query()->where('event_type', 'staff.setup_delegation_revoked')->exists())->toBeTrue();
});

test('HR lifecycle changes revoke active setup delegations without delegation authority', function (): void {
    foreach (['deactivate', 'demote'] as $change) {
        seedE2Foundation();
        $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
        $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
        $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

        $this->actingAs($admin)->post("/staff/personnel/{$counselor->id}/grant-setup", [
            'reason' => 'Temporary setup coverage.',
        ])->assertRedirect();
        expect($hr->hasPermissionTo(CapabilityCatalog::SETUP_INSTITUTION_DELEGATE))->toBeFalse();

        if ($change === 'deactivate') {
            app(DeactivateStaffAccount::class)->handle($hr, $counselor);
        } else {
            app(SyncStaffRoles::class)->handle($hr, $counselor, ['guidance_support_staff']);
        }

        expect(InstitutionalSetupDelegation::query()->where('recipient_id', $counselor->id)
            ->whereNotNull('revoked_at')->exists())->toBeTrue();
    }
});
test('setup delegation grants and updates granular setup scopes', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $recipient = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $grant = fn (array $scopes) => $this->actingAs($admin)->post("/staff/personnel/{$recipient->id}/grant-setup", [
        'reason' => 'Temporary room setup coverage.',
        'scopes' => $scopes,
    ])->assertRedirect();

    $grant(['rooms']);
    $delegation = InstitutionalSetupDelegation::query()
        ->where('recipient_id', $recipient->id)
        ->whereNull('revoked_at')
        ->firstOrFail();
    $recipient->refresh();

    expect($recipient->hasPermissionTo(CapabilityCatalog::SETUP_ROOMS_MANAGE))->toBeTrue()
        ->and($recipient->hasPermissionTo(CapabilityCatalog::SETUP_CYCLES_MANAGE))->toBeFalse()
        ->and($recipient->hasPermissionTo(CapabilityCatalog::SETUP_PROGRAMS_MANAGE))->toBeFalse()
        ->and($delegation->scopes)->toBe(['rooms']);

    $this->actingAs($recipient)->get('/setup/rooms')->assertOk();
    $this->actingAs($recipient)->get('/setup/cycles')->assertForbidden();
    $this->actingAs($recipient)->get('/setup/programs')->assertForbidden();

    $grant(['cycles', 'rooms']);
    $delegation = InstitutionalSetupDelegation::query()
        ->where('recipient_id', $recipient->id)
        ->whereNull('revoked_at')
        ->firstOrFail();
    $recipient->refresh();

    expect($delegation->scopes)->toBe(['cycles', 'rooms'])
        ->and($recipient->hasPermissionTo(CapabilityCatalog::SETUP_CYCLES_MANAGE))->toBeTrue()
        ->and($recipient->hasPermissionTo(CapabilityCatalog::SETUP_ROOMS_MANAGE))->toBeTrue()
        ->and($recipient->hasPermissionTo(CapabilityCatalog::SETUP_PROGRAMS_MANAGE))->toBeFalse();

    $this->actingAs($recipient)->get('/setup/cycles')->assertOk();
    $this->actingAs($recipient)->get('/setup/rooms')->assertOk();
    $this->actingAs($recipient)->get('/setup/programs')->assertForbidden();
    $this->actingAs($admin)->get('/staff/personnel')->assertOk()->assertInertia(fn ($page) => $page
        ->where('users.data', fn ($rows) => collect($rows)->contains(
            fn ($row) => (int) $row['id'] === $recipient->id
                && $row['setup_scopes'] === ['cycles', 'rooms'],
        )));
});
