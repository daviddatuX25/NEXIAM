<?php

use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use Database\Seeders\DepartmentOfficeLocationSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('authorized staff can view colleges and departments setup workspace', function (): void {
    seedE2Foundation();

    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('setup.departments'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Departments')
            ->has('departments')
            ->has('eligible_deans')
        );
});

test('unauthorized users are forbidden from viewing departments setup', function (): void {
    seedE2Foundation();

    $applicant = User::factory()->create();

    $this->actingAs($applicant)
        ->get(route('setup.departments'))
        ->assertForbidden();
});

test('undelegated registrar clerk is denied department setup', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('setup.departments'))
        ->assertForbidden();
});

test('admin can create a new college or department with audit logging', function (): void {
    seedE2Foundation();

    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

    $this->actingAs($admin)
        ->post(route('setup.departments.store'), [
            'code' => 'COL',
            'name' => 'College of Law',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $dept = Department::query()->where('institution_id', $institution->id)->where('code', 'COL')->firstOrFail();
    expect($dept->name)->toBe('College of Law')
        ->and($dept->is_active)->toBeTrue();

    $event = AuditEvent::query()->where('event_type', 'setup.department_upserted')->latest('id')->first();
    expect($event)->not->toBeNull()
        ->and($event->payload['code'])->toBe('COL');
});

test('admin can update college details and toggle active status', function (): void {
    seedE2Foundation();

    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

    $dept = Department::query()->create([
        'institution_id' => $institution->id,
        'code' => 'TEST_DEPT',
        'name' => 'Test Department Name',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->put(route('setup.departments.update', $dept->id), [
            'name' => 'Updated College of Advanced Studies',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($dept->fresh()->name)->toBe('Updated College of Advanced Studies');

    $this->actingAs($admin)
        ->post(route('setup.departments.toggle', $dept->id))
        ->assertRedirect();

    expect($dept->fresh()->is_active)->toBeFalse();
});
test('department office can be configured from the institution room catalog', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $department = Department::query()->create([
        'institution_id' => $institution->id,
        'code' => 'OFFICE-MAP',
        'name' => 'Office Mapping Department',
        'is_active' => true,
    ]);
    $room = Room::factory()->create([
        'institution_id' => $institution->id,
        'name' => 'Department Service Point',
        'building_name' => 'Academic Building',
    ]);

    $this->actingAs($admin)
        ->get(route('setup.departments'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('office_rooms'));

    $this->actingAs($admin)
        ->put(route('setup.departments.update', $department->id), [
            'name' => $department->name,
            'dean_user_id' => null,
            'office_room_id' => $room->id,
            'is_active' => true,
        ])
        ->assertRedirect();

    $event = AuditEvent::query()->where('event_type', 'setup.department_upserted')->latest('id')->firstOrFail();
    expect($department->fresh()->office_room_id)->toBe($room->id)
        ->and($event->payload['office_room_id'])->toBe($room->id);
});
test('verified office locations seed idempotently without replacing a configured room', function (): void {
    seedE2Foundation();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cas = Department::query()->where('institution_id', $institution->id)->where('code', 'CAS')->firstOrFail();
    $curatedRoom = Room::factory()->create([
        'institution_id' => $institution->id,
        'name' => 'Staff-curated CAS office',
    ]);
    $cas->update(['office_room_id' => $curatedRoom->id]);
    $cbme = Department::query()->where('institution_id', $institution->id)->where('code', 'CBME')->firstOrFail();
    $existingVerifiedRoom = Room::factory()->create([
        'institution_id' => $institution->id,
        'code' => 'DEPT-CBME-OFFICE',
        'name' => 'CBME Department Office Service Point',
        'space_kind' => 'service_point',
        'latitude' => '16.9375143',
        'longitude' => '120.4439792',
    ]);

    $seeder = new DepartmentOfficeLocationSeeder;
    $seeder->run();

    $cte = Department::query()->where('institution_id', $institution->id)->where('code', 'CTE')->firstOrFail();
    $office = Room::query()->where('institution_id', $institution->id)->where('code', 'DEPT-CTE-OFFICE')->firstOrFail();
    expect($cas->fresh()->office_room_id)->toBe($curatedRoom->id)
        ->and($cte->office_room_id)->toBe($office->id)
        ->and(number_format((float) $office->latitude, 7, '.', ''))->toBe('16.9377908')
        ->and(number_format((float) $office->longitude, 7, '.', ''))->toBe('120.4437501')
        ->and($cbme->fresh()->office_room_id)->toBe($existingVerifiedRoom->id);
    $seeder->run();

    expect(Room::query()->where('institution_id', $institution->id)->where('code', 'DEPT-CTE-OFFICE')->count())->toBe(1)
        ->and($cas->fresh()->office_room_id)->toBe($curatedRoom->id);
});

test('department office updates reject foreign and inactive rooms without replacing the current mapping', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $department = Department::query()->where('institution_id', $institution->id)->where('code', 'CAS')->firstOrFail();
    $current = Room::factory()->create(['institution_id' => $institution->id, 'is_active' => true]);
    $department->update(['office_room_id' => $current->id]);
    $foreignInstitution = Institution::query()->create(['code' => 'FOREIGN-OFFICE-SCOPE', 'name' => 'Other institution']);
    $foreign = Room::factory()->create(['institution_id' => $foreignInstitution->id, 'is_active' => true]);
    $inactive = Room::factory()->create(['institution_id' => $institution->id, 'is_active' => false]);

    foreach ([$foreign, $inactive] as $invalidOffice) {
        $this->actingAs($admin)
            ->put(route('setup.departments.update', $department->id), [
                'name' => $department->name,
                'is_active' => true,
                'office_room_id' => $invalidOffice->id,
            ])
            ->assertSessionHasErrors('office_room_id');
        expect($department->fresh()->office_room_id)->toBe($current->id);
    }
});
