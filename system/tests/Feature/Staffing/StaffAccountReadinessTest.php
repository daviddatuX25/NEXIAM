<?php

use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Staffing\Actions\CreateStaffAccount;
use App\Staffing\Queries\StaffAccountReadiness;
use Illuminate\Support\Str;

it('counts one active program-head identity across every explicitly assigned program', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $department = Department::query()->where('institution_id', $hr->actorScope()->institutionId)->where('is_active', true)->firstOrFail();
    $codes = ['PREP-'.Str::upper(Str::random(6)), 'PREP-'.Str::upper(Str::random(6))];
    foreach ($codes as $code) {
        Program::query()->create([
            'institution_id' => $department->institution_id,
            'department_id' => $department->id,
            'code' => $code,
            'name' => $code.' review program',
            'is_active' => true,
        ]);
    }

    $head = app(CreateStaffAccount::class)->handle($hr, 'Readiness Test Head', Str::lower(Str::random(16)).'@example.test', ['program_head'], 'program', (int) $department->institution_id, $codes);
    $readiness = app(StaffAccountReadiness::class)->handle($hr);
    $rows = collect($readiness['rows'])->whereIn('scope_code', $codes)->keyBy('scope_code');

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('state')->unique()->all())->toBe(['awaiting_setup'])
        ->and($rows->pluck('user_id')->unique()->all())->toBe([$head->id]);
});

it('limits Dean coverage to active owned departments and does not select identity on the actor row', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $department = Department::query()->where('institution_id', $hr->actorScope()->institutionId)->where('is_active', true)->firstOrFail();
    $otherDepartment = Department::query()->create([
        'institution_id' => $department->institution_id,
        'code' => 'OUT-'.Str::upper(Str::random(5)),
        'name' => 'Other department',
        'is_active' => true,
    ]);
    Program::query()->create([
        'institution_id' => $otherDepartment->institution_id,
        'department_id' => $otherDepartment->id,
        'code' => 'OUTP-'.Str::upper(Str::random(5)),
        'name' => 'Other program',
        'is_active' => true,
    ]);
    $dean = app(CreateStaffAccount::class)->handle($hr, 'Readiness Test Dean', Str::lower(Str::random(16).'@example.test'), ['program_head'], 'program', (int) $department->institution_id);
    $department->update(['dean_user_id' => $dean->id]);

    $readiness = app(StaffAccountReadiness::class)->handle($dean);
    $row = collect($readiness['rows'])->firstWhere('key', 'department:'.$department->id);

    expect($row['state'])->toBe('awaiting_setup')
        ->and($row['user_id'])->toBeNull()
        ->and(collect($readiness['rows'])->pluck('department_id')->unique()->all())->toBe([(int) $department->id]);
});

it('returns a Setup aggregate without staff identity rows for a Setup-only caller', function (): void {
    seedE2Foundation();
    $summary = app(StaffAccountReadiness::class)->handle(null, Institution::current());

    expect($summary['summary'])->toHaveKeys(['covered', 'ready', 'missing', 'awaiting_setup', 'setup_in_progress', 'conflict', 'inactive_scope', 'protected'])
        ->and($summary['rows'])->toBe([]);
});

it('keeps central-office readiness scoped to a Guidance lead and excludes academic identity rows', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $readiness = app(StaffAccountReadiness::class)->handle($guidance);

    expect($readiness['rows'])->not->toBeEmpty()
        ->and(collect($readiness['rows'])->pluck('office')->unique()->all())->toBe(['guidance'])
        ->and(collect($readiness['rows'])->pluck('role')->contains('program_head'))->toBeFalse()
        ->and(collect($readiness['rows'])->pluck('email')->contains(config('seed.hr.email')))->toBeFalse();
});
