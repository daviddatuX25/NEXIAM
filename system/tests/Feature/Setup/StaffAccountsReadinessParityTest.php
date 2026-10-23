<?php

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use App\Setup\Definitions\StaffAccountsDefinition;
use App\Setup\Queries\ComputeSetupReadinessQuery;
use App\Staffing\Actions\CreateStaffAccount;
use App\Staffing\Queries\StaffAccountReadiness;
use Illuminate\Support\Str;

function staffAccountsReadinessSurfaces(User $actor, Institution $institution): array
{
    $definition = app(StaffAccountsDefinition::class);
    $projection = app(StaffAccountReadiness::class)->handle(null, $institution);
    $groupReadiness = app(ComputeSetupReadinessQuery::class)->handle($actor);
    $category = collect($groupReadiness['groups'])
        ->firstWhere('key', 'institutional_setup')['categories'];
    $category = collect($category)->firstWhere('key', 'staff_accounts');
    $card = collect($groupReadiness['cards'])->firstWhere('key', 'staff_accounts');

    return [
        'projection' => $projection,
        'definition' => $definition,
        'category' => $category,
        'card' => $card,
    ];
}

it('shows no active cycle as attention with matching Setup card and checklist diagnostics', function (): void {
    seedE2Foundation();
    $institution = Institution::current();
    $actor = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    AcademicCycle::query()->where('institution_id', $institution->id)->update(['is_active' => false]);

    $surfaces = staffAccountsReadinessSurfaces($actor, $institution);
    $cycle = $surfaces['projection']['cycle_summary'];

    expect($cycle['status'])->toBe('no_active_cycle')
        ->and($surfaces['definition']->status($institution))->toBe('attention')
        ->and($surfaces['definition']->checks($institution)[0]['passed'])->toBeFalse()
        ->and($surfaces['definition']->checks($institution)[0]['detail'])->toBe($cycle['detail'])
        ->and($surfaces['card']['status'])->toBe('attention')
        ->and($surfaces['card']['summary_metric'])->toBe($cycle['summary_metric'])
        ->and($surfaces['card']['checks'][0]['passed'])->toBeFalse()
        ->and($surfaces['card']['checks'][0]['detail'])->toBe($cycle['detail'])
        ->and($surfaces['category']['checks'][0]['passed'])->toBeFalse()
        ->and($surfaces['category']['checks'][0]['detail'])->toBe($cycle['detail'])
        ->and($surfaces['card']['label'])->toBe($surfaces['definition']->label())
        ->and($surfaces['card']['contributes'])->toBe($surfaces['definition']->contributesToReadiness())
        ->and($surfaces['card']['in_navigation'])->toBeTrue()
        ->and($surfaces['card']['href'])->toBe('/staff/personnel')
        ->and($cycle['summary_metric'])->toContain('No active academic cycle');
});

it('keeps awaiting setup non-blocking when cycle coverage is current', function (): void {
    seedE2Foundation();
    $institution = Institution::current();
    $actor = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    AcademicCycle::query()->where('institution_id', $institution->id)->where('is_active', true)->firstOrFail();
    Department::query()->where('institution_id', $institution->id)->update(['is_active' => false]);
    Program::query()->where('institution_id', $institution->id)->update(['is_active' => false]);

    $department = Department::query()->create([
        'institution_id' => $institution->id,
        'code' => 'READINESS-DEPT-'.Str::upper(Str::random(6)),
        'name' => 'Readiness parity department',
        'is_active' => true,
    ]);
    $programCode = 'READINESS-'.Str::upper(Str::random(8));
    Program::query()->create([
        'institution_id' => $institution->id,
        'department_id' => $department->id,
        'code' => $programCode,
        'name' => 'Readiness parity program',
        'is_active' => true,
    ]);
    $createStaff = app(CreateStaffAccount::class);
    $head = $createStaff->handle(
        $actor,
        'Readiness Current Head',
        Str::lower(Str::random(16)).'@example.test',
        ['program_head'],
        'program',
        (int) $institution->id,
        [$programCode],
    );
    $dean = $createStaff->handle(
        $actor,
        'Readiness Current Dean',
        Str::lower(Str::random(16)).'@example.test',
        ['program_head'],
        'program',
        (int) $institution->id,
    );
    $department->update(['dean_user_id' => $dean->id]);

    $surfaces = staffAccountsReadinessSurfaces($actor, $institution);
    $cycle = $surfaces['projection']['cycle_summary'];

    expect($cycle['status'])->toBe('ready')
        ->and($surfaces['projection']['summary']['awaiting_setup'])->toBeGreaterThan(0)
        ->and($surfaces['definition']->status($institution))->toBe('ready')
        ->and($surfaces['card']['status'])->toBe('ready')
        ->and($surfaces['card']['checks'][0]['passed'])->toBeTrue()
        ->and($surfaces['card']['in_navigation'])->toBeTrue()
        ->and($surfaces['card']['href'])->toBe('/staff/personnel');
});

it('keeps current and stale cycle summaries in parity and reports every diagnostic count', function (): void {
    seedE2Foundation();
    $institution = Institution::current();
    $actor = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $cycle = AcademicCycle::query()
        ->where('institution_id', $institution->id)
        ->where('is_active', true)
        ->firstOrFail();

    $current = staffAccountsReadinessSurfaces($actor, $institution);
    $currentSummary = $current['projection']['cycle_summary'];
    Department::query()->where('institution_id', $institution->id)->update(['is_active' => false]);
    Program::query()->where('institution_id', $institution->id)->update(['is_active' => false]);
    $department = Department::query()->create([
        'institution_id' => $institution->id,
        'code' => 'READINESS-DEPT-'.Str::upper(Str::random(6)),
        'name' => 'Readiness diagnostic department',
        'is_active' => true,
    ]);
    $codes = [
        'stale' => 'READINESS-STALE-'.Str::upper(Str::random(6)),
        'missing' => 'READINESS-MISSING-'.Str::upper(Str::random(6)),
        'conflict' => 'READINESS-CONFLICT-'.Str::upper(Str::random(6)),
    ];
    foreach ($codes as $code) {
        Program::query()->create([
            'institution_id' => $institution->id,
            'department_id' => $department->id,
            'code' => $code,
            'name' => $code,
            'is_active' => true,
        ]);
    }
    $createStaff = app(CreateStaffAccount::class);
    $staleHead = $createStaff->handle(
        $actor,
        'Readiness Stale Head',
        Str::lower(Str::random(16)).'@example.test',
        ['program_head'],
        'program',
        (int) $institution->id,
        [$codes['stale']],
    );
    StaffProfile::query()->where('user_id', $staleHead->id)->update(['active_cycle_code' => 'STALE-READINESS-CYCLE']);
    foreach (['Readiness Conflict Head One', 'Readiness Conflict Head Two'] as $name) {
        $createStaff->handle(
            $actor,
            $name,
            Str::lower(Str::random(16)).'@example.test',
            ['program_head'],
            'program',
            (int) $institution->id,
            [$codes['conflict']],
        );
    }

    $stale = staffAccountsReadinessSurfaces($actor, $institution);
    $staleSummary = $stale['projection']['cycle_summary'];

    expect($staleSummary['missing'])->toBeGreaterThan(0)
        ->and($staleSummary['conflict'])->toBeGreaterThan(0)
        ->and($staleSummary['needs_rollover'])->toBeGreaterThan(0)
        ->and($staleSummary['awaiting_setup'])->toBeGreaterThan(0)
        ->and($stale['definition']->status($institution))->toBe($stale['card']['status'])
        ->and($stale['definition']->status($institution))->toBe('attention')
        ->and($stale['definition']->checks($institution)[0]['passed'])->toBeFalse()
        ->and($stale['definition']->checks($institution)[0]['detail'])->toBe($staleSummary['detail'])
        ->and($stale['card']['checks'][0]['detail'])->toBe($staleSummary['detail'])
        ->and($staleSummary['detail'])->toContain($staleSummary['needs_rollover'].' assignments need rollover');

    foreach ([
        'missing' => 'missing',
        'conflict' => 'conflicts',
        'needs_rollover' => 'assignments need rollover',
        'awaiting_setup' => 'awaiting setup',
    ] as $key => $label) {
        expect($staleSummary['detail'])->toContain($staleSummary[$key].' '.$label);
    }
});
