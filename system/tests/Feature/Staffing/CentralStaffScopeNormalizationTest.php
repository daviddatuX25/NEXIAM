<?php

use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;

it('normalizes central staff assignments, preserves academic scopes, and audits each change', function (): void {
    seedE2Foundation();

    $institution = Institution::query()->firstOrFail();
    $department = Department::query()->firstOrFail();
    $centralProfiles = collect(['admin', 'registrar', 'guidance'])->map(function (string $office) use ($institution, $department): StaffProfile {
        $user = User::factory()->create();

        return StaffProfile::query()->create([
            'user_id' => $user->id,
            'institution_id' => $institution->id,
            'office' => $office,
            'department_id' => $department->id,
            'program_codes' => ['ENG'],
        ]);
    });

    foreach ($centralProfiles as $profile) {
        DB::table('staff_profiles')
            ->where('id', $profile->id)
            ->update([
                'department_id' => $department->id,
                'program_codes' => json_encode(['ENG']),
            ]);
    }

    $academicUser = User::factory()->create();
    $academicProfile = StaffProfile::query()->create([
        'user_id' => $academicUser->id,
        'institution_id' => $institution->id,
        'office' => 'program',
        'department_id' => $department->id,
        'program_codes' => ['ENG'],
    ]);

    $migration = require database_path('migrations/2026_10_15_120100_normalize_central_staff_scopes.php');
    $migration->up();

    foreach ($centralProfiles as $profile) {
        $profile->refresh();

        expect($profile->department_id)->toBeNull()
            ->and($profile->program_codes)->toBe([]);
    }

    $academicProfile->refresh();
    expect($academicProfile->department_id)->toBe($department->id)
        ->and($academicProfile->program_codes)->toBe(['ENG']);

    $events = AuditEvent::query()
        ->where('event_type', 'staff.central_scope_normalized')
        ->get();

    expect($events)->toHaveCount(3);
    foreach ($centralProfiles as $profile) {
        $event = $events->first(fn (AuditEvent $event): bool => $event->payload['before']['user_id'] === $profile->user_id);

        expect($event)->not->toBeNull()
            ->and($event->payload['before']['prior_department_id'])->toBe($department->id)
            ->and($event->payload['before']['prior_program_codes'])->toBe('["ENG"]')
            ->and($event->payload['after']['department_id'])->toBeNull()
            ->and($event->payload['after']['program_codes'])->toBe('[]');
    }
});
