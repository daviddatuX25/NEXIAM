<?php

use App\Models\User;
use App\Staffing\Actions\CreateStaffAccount;
use App\Staffing\Support\StaffTargetManagementScope;
use Illuminate\Support\Str;

it('reloads actor grants before allowing a target management decision', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $target = app(CreateStaffAccount::class)->handle(
        $hr,
        'Scope Target Support',
        Str::lower(Str::random(16)).'@example.test',
        ['guidance_support_staff'],
        'guidance',
        (int) $actor->actorScope()->institutionId,
    );

    $actor->syncRoles([]);

    expect(app(StaffTargetManagementScope::class)->allows($actor, $target))->toBeFalse();
});
