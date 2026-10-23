<?php

use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Operation;
use App\Models\User;
use App\Setup\Actions\ExtendApplicationWindow;
use App\Setup\Queries\AcademicCycleCalendarQuery;
use Carbon\CarbonImmutable;
use Database\Seeders\PublicIntakeConsentDemoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

function applicationWindowExtensionFixture(string $capability = CapabilityCatalog::SETUP_CYCLES_MANAGE): array
{
    seedE2Foundation();
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-23 12:00:00', 'UTC'));
    (new PublicIntakeConsentDemoSeeder)->run();

    $institution = Institution::current();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
    $cycle->update([
        'opens_on' => now()->subDays(2)->toDateString(),
        'closes_on' => now()->addDays(2)->toDateString(),
        'is_active' => true,
    ]);
    $window->update([
        'opens_at' => now()->subDays(2)->startOfDay()->toDateTimeString(),
        'closes_at' => now()->addDays(2)->endOfDay()->toDateTimeString(),
        'status' => 'published',
    ]);

    $actor = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    Permission::findOrCreate($capability, 'web');
    $actor->syncRoles([]);
    $actor->syncPermissions([$capability]);

    return [
        'actor' => $actor->fresh(),
        'cycle' => $cycle->fresh(),
        'window' => $window->fresh(),
        'baseline' => AcademicCycleCalendarQuery::baselineFor($cycle->fresh(), $window->fresh()),
        'institution' => $institution,
    ];
}

function applicationWindowExtensionAuditCount(): int
{
    return AuditEvent::query()->where('event_type', 'setup.application_window_extended')->count();
}

afterEach(function (): void {
    Carbon::setTestNow();
});

test('cycle setup authority extends only the linked current open primary window and records one atomic audit receipt', function (): void {
    $fixture = applicationWindowExtensionFixture();
    $auditCount = applicationWindowExtensionAuditCount();
    $key = (string) Str::uuid();

    $receipt = app(ExtendApplicationWindow::class)->handle(
        $fixture['actor'],
        $fixture['cycle'],
        (int) $fixture['window']->getKey(),
        3,
        $fixture['baseline'],
        $key,
        'Allow applicants three more days to complete intake.',
    );

    $newDeadline = CarbonImmutable::parse('2026-10-28 23:59:59', 'UTC');
    expect($receipt)
        ->toMatchArray([
            'cycle_id' => (int) $fixture['cycle']->getKey(),
            'window_id' => (int) $fixture['window']->getKey(),
            'days' => 3,
            'old_closes_at' => $fixture['window']->closes_at->copy()->utc()->format('Y-m-d H:i:s'),
            'new_closes_at' => $newDeadline->copy()->utc()->format('Y-m-d H:i:s'),
            'old_cycle_closes_on' => $fixture['cycle']->closes_on->toDateString(),
            'new_cycle_closes_on' => $newDeadline->toDateString(),
            'reason' => 'Allow applicants three more days to complete intake.',
        ])
        ->and($fixture['window']->fresh()->closes_at->equalTo($newDeadline))->toBeTrue()
        ->and($fixture['cycle']->fresh()->closes_on->toDateString())->toBe($newDeadline->toDateString())
        ->and(AuditEvent::query()->where('event_type', 'setup.application_window_extended')->count())->toBe(1)
        ->and(applicationWindowExtensionAuditCount())->toBe($auditCount + 1)
        ->and(Operation::query()->where('type', 'setup.application_window.extend')->count())->toBe(1);
});

test('institution setup authority independently permits a current application-window extension', function (): void {
    $fixture = applicationWindowExtensionFixture(CapabilityCatalog::SETUP_INSTITUTION_MANAGE);

    $receipt = app(ExtendApplicationWindow::class)->handle(
        $fixture['actor'],
        $fixture['cycle'],
        (int) $fixture['window']->getKey(),
        1,
        $fixture['baseline'],
        (string) Str::uuid(),
        'Keep the intake window open for one more day.',
    );

    expect($receipt['days'])->toBe(1)
        ->and($fixture['window']->fresh()->closes_at->toDateString())->toBe(now()->addDays(3)->toDateString());
});

test('stale academic-cycle or window baseline conflicts without creating a receipt or extension audit', function (): void {
    $fixture = applicationWindowExtensionFixture();
    $beforeAuditCount = applicationWindowExtensionAuditCount();
    $beforeOperationCount = Operation::query()->count();
    $fixture['cycle']->update(['closes_on' => now()->addDays(3)->toDateString()]);
    $fixture['window']->update(['closes_at' => now()->addDays(3)->endOfDay()->toDateTimeString()]);

    expect(fn () => app(ExtendApplicationWindow::class)->handle(
        $fixture['actor'],
        $fixture['cycle'],
        (int) $fixture['window']->getKey(),
        1,
        $fixture['baseline'],
        (string) Str::uuid(),
        'The original date preview is now stale.',
    ))->toThrow(ValidationException::class);

    expect($fixture['cycle']->fresh()->closes_on->toDateString())->toBe(now()->addDays(3)->toDateString())
        ->and($fixture['window']->fresh()->closes_at->toDateString())->toBe(now()->addDays(3)->toDateString())
        ->and(applicationWindowExtensionAuditCount())->toBe($beforeAuditCount)
        ->and(Operation::query()->count())->toBe($beforeOperationCount);
});

test('an exact idempotency replay returns the stored old and new dates while changed payload conflicts', function (): void {
    $fixture = applicationWindowExtensionFixture();
    $key = (string) Str::uuid();
    $action = app(ExtendApplicationWindow::class);
    $arguments = [
        $fixture['actor'],
        $fixture['cycle'],
        (int) $fixture['window']->getKey(),
        1,
        $fixture['baseline'],
        $key,
        'Preserve access during final applicant submissions.',
    ];

    $first = $action->handle(...$arguments);
    $auditCount = applicationWindowExtensionAuditCount();
    $replay = $action->handle(...$arguments);

    expect($replay)->toBe($first)
        ->and($fixture['window']->fresh()->closes_at->format('Y-m-d H:i:s'))->toBe($first['new_closes_at'])
        ->and(applicationWindowExtensionAuditCount())->toBe($auditCount);

    $arguments[3] = 2;
    expect(fn () => $action->handle(...$arguments))->toThrow(ValidationException::class)
        ->and($fixture['window']->fresh()->closes_at->format('Y-m-d H:i:s'))->toBe($first['new_closes_at'])
        ->and(applicationWindowExtensionAuditCount())->toBe($auditCount);
});

test('missing or foreign institution setup capability is denied before a command receipt is created', function (): void {
    $fixture = applicationWindowExtensionFixture();
    $fixture['actor']->syncPermissions([]);
    $beforeOperationCount = Operation::query()->count();

    expect(fn () => app(ExtendApplicationWindow::class)->handle(
        $fixture['actor'],
        $fixture['cycle'],
        (int) $fixture['window']->getKey(),
        1,
        $fixture['baseline'],
        (string) Str::uuid(),
        'Attempt without a current setup capability.',
    ))->toThrow(AuthorizationException::class);
    expect(Operation::query()->count())->toBe($beforeOperationCount);

    $fixture = applicationWindowExtensionFixture();
    $otherInstitution = Institution::query()->create(['code' => 'SYN-OTHER-INST', 'name' => 'Other institution']);
    $fixture['actor']->staffProfile()->update(['institution_id' => $otherInstitution->getKey()]);
    $beforeOperationCount = Operation::query()->count();

    expect(fn () => app(ExtendApplicationWindow::class)->handle(
        $fixture['actor'],
        $fixture['cycle'],
        (int) $fixture['window']->getKey(),
        1,
        $fixture['baseline'],
        (string) Str::uuid(),
        'Attempt outside the current institution scope.',
    ))->toThrow(AuthorizationException::class)
        ->and(Operation::query()->count())->toBe($beforeOperationCount);
});

test('extension rejects deadlines beyond the supported calendar range without changing either bound', function (): void {
    $fixture = applicationWindowExtensionFixture();
    $fixture['cycle']->update(['closes_on' => '9999-12-31']);
    $fixture['window']->update(['closes_at' => '9999-12-31 23:59:59']);
    $fixture['cycle'] = $fixture['cycle']->fresh();
    $fixture['window'] = $fixture['window']->fresh();
    $fixture['baseline'] = AcademicCycleCalendarQuery::baselineFor($fixture['cycle'], $fixture['window']);
    $beforeAuditCount = applicationWindowExtensionAuditCount();
    $beforeOperationCount = Operation::query()->count();

    expect(fn () => app(ExtendApplicationWindow::class)->handle(
        $fixture['actor'],
        $fixture['cycle'],
        (int) $fixture['window']->getKey(),
        1,
        $fixture['baseline'],
        (string) Str::uuid(),
        'Do not exceed the supported date range.',
    ))->toThrow(ValidationException::class);

    expect($fixture['cycle']->fresh()->closes_on->toDateString())->toBe('9999-12-31')
        ->and($fixture['window']->fresh()->closes_at->format('Y-m-d H:i:s'))->toBe('9999-12-31 23:59:59')
        ->and(applicationWindowExtensionAuditCount())->toBe($beforeAuditCount)
        ->and(Operation::query()->count())->toBe($beforeOperationCount);
});

test('calendar projection exposes the exact UTC shape and the effective current-intake state', function (): void {
    $fixture = applicationWindowExtensionFixture();
    $calendar = app(AcademicCycleCalendarQuery::class)->forInstitution($fixture['institution']);

    expect(array_keys($calendar))->toBe([
        'cycle_id', 'label', 'window_id', 'opens_at', 'closes_at', 'cycle_closes_on',
        'status', 'availability', 'can_extend', 'baseline', 'timezone', 'effective_window',
    ])->and($calendar)->toMatchArray([
        'cycle_id' => (int) $fixture['cycle']->getKey(),
        'label' => $fixture['cycle']->staffLabel(),
        'window_id' => (int) $fixture['window']->getKey(),
        'status' => 'published',
        'availability' => 'open',
        'can_extend' => true,
        'timezone' => 'UTC',
    ]);
});

test('older published open intake remains effective when a newer primary window is an unpublished draft', function (): void {
    $fixture = applicationWindowExtensionFixture();
    ApplicationWindow::query()
        ->where('academic_cycle_id', $fixture['cycle']->getKey())
        ->where('id', '!=', $fixture['window']->getKey())
        ->update(['status' => 'closed']);

    $cycleDatesBefore = [
        $fixture['cycle']->opens_on->toDateString(),
        $fixture['cycle']->closes_on->toDateString(),
    ];
    $effectiveDatesBefore = [
        $fixture['window']->opens_at->format('Y-m-d H:i:s'),
        $fixture['window']->closes_at->format('Y-m-d H:i:s'),
    ];
    $primary = ApplicationWindow::query()->create([
        'academic_cycle_id' => $fixture['cycle']->getKey(),
        'code' => 'SYN-WINDOW-2026-PRIMARY-DRAFT',
        'name' => 'Newer draft window',
        'opens_at' => now()->addDays(4)->startOfDay()->toDateTimeString(),
        'closes_at' => now()->addDays(12)->endOfDay()->toDateTimeString(),
        'status' => 'draft',
    ]);
    $primary = $primary->fresh();
    $primaryDatesBefore = [
        $primary->opens_at->format('Y-m-d H:i:s'),
        $primary->closes_at->format('Y-m-d H:i:s'),
    ];

    $calendar = app(AcademicCycleCalendarQuery::class)->forInstitution($fixture['institution']);

    expect($calendar)->toMatchArray([
        'window_id' => (int) $primary->getKey(),
        'status' => 'draft',
        'availability' => 'another_window_open',
        'can_extend' => false,
        'effective_window' => [
            'id' => (int) $fixture['window']->getKey(),
            'name' => $fixture['window']->name,
            'opens_at' => $fixture['window']->opens_at->copy()->utc()->toIso8601String(),
            'closes_at' => $fixture['window']->closes_at->copy()->utc()->toIso8601String(),
        ],
    ]);

    expect([
        $fixture['cycle']->fresh()->opens_on->toDateString(),
        $fixture['cycle']->fresh()->closes_on->toDateString(),
    ])->toBe($cycleDatesBefore)
        ->and([
            $fixture['window']->fresh()->opens_at->format('Y-m-d H:i:s'),
            $fixture['window']->fresh()->closes_at->format('Y-m-d H:i:s'),
        ])->toBe($effectiveDatesBefore)
        ->and([
            $primary->fresh()->opens_at->format('Y-m-d H:i:s'),
            $primary->fresh()->closes_at->format('Y-m-d H:i:s'),
        ])->toBe($primaryDatesBefore);
});

test('extension rejects independently bounded dates and ended periods without reopening or changing deadlines', function (): void {
    $fixture = applicationWindowExtensionFixture();
    $fixture['cycle']->update(['closes_on' => now()->addDay()->toDateString()]);
    $fixture['cycle'] = $fixture['cycle']->fresh();
    $fixture['baseline'] = AcademicCycleCalendarQuery::baselineFor($fixture['cycle'], $fixture['window']);
    $beforeWindowClose = $fixture['window']->closes_at->format('Y-m-d H:i:s');
    $beforeAuditCount = applicationWindowExtensionAuditCount();
    $beforeOperationCount = Operation::query()->count();

    expect(fn () => app(ExtendApplicationWindow::class)->handle(
        $fixture['actor'],
        $fixture['cycle'],
        (int) $fixture['window']->getKey(),
        1,
        $fixture['baseline'],
        (string) Str::uuid(),
        'The deadlines are independently configured.',
    ))->toThrow(ValidationException::class);

    expect($fixture['cycle']->fresh()->closes_on->toDateString())->toBe(now()->addDay()->toDateString())
        ->and($fixture['window']->fresh()->closes_at->format('Y-m-d H:i:s'))->toBe($beforeWindowClose)
        ->and(applicationWindowExtensionAuditCount())->toBe($beforeAuditCount)
        ->and(Operation::query()->count())->toBe($beforeOperationCount);

    $fixture['cycle']->update(['closes_on' => now()->subDay()->toDateString()]);
    $fixture['window']->update(['closes_at' => now()->subDay()->endOfDay()->toDateTimeString()]);
    $fixture['cycle'] = $fixture['cycle']->fresh();
    $fixture['window'] = $fixture['window']->fresh();
    $fixture['baseline'] = AcademicCycleCalendarQuery::baselineFor($fixture['cycle'], $fixture['window']);

    expect(fn () => app(ExtendApplicationWindow::class)->handle(
        $fixture['actor'],
        $fixture['cycle'],
        (int) $fixture['window']->getKey(),
        1,
        $fixture['baseline'],
        (string) Str::uuid(),
        'An ended window must not be reopened by a quick extension.',
    ))->toThrow(ValidationException::class);

    expect($fixture['cycle']->fresh()->closes_on->toDateString())->toBe(now()->subDay()->toDateString())
        ->and($fixture['window']->fresh()->closes_at->toDateString())->toBe(now()->subDay()->toDateString())
        ->and(applicationWindowExtensionAuditCount())->toBe($beforeAuditCount)
        ->and(Operation::query()->count())->toBe($beforeOperationCount);
});
