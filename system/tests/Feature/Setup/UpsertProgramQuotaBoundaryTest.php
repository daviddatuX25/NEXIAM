<?php

use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\ProgramQuotaChange;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntentDispatcher;
use App\Setup\Actions\QuotaCommandConflict;
use App\Setup\Actions\UpsertProgramQuota;
use App\Setup\Actions\UpsertProgramQuotas;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function quotaTestCommand(User $actor, Program $program, AcademicCycle $cycle, int $slots, int $expectedVersion, ?string $key = null): ProgramQuotaChange
{
    return app(UpsertProgramQuota::class)->handle(
        $actor,
        $program,
        $cycle,
        $slots,
        'Synthetic test capacity reason',
        $expectedVersion,
        $key ?? (string) Str::uuid(),
    );
}

test('quota writes remain disabled unless the isolated rollout opt-in is set', function (): void {
    seedE2Foundation();
    config()->set('admission.quota_writes_enabled', false);
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    expect(fn () => quotaTestCommand($guidance, $program, $cycle, 48, 0))
        ->toThrow(AuthorizationException::class);
    expect(ProgramQuota::query()->where('program_id', $program->id)->where('academic_cycle_id', $cycle->id)->exists())->toBeFalse();
});

test('quota command replay returns the original receipt after later edits and binds payload', function (): void {
    seedE2Foundation();
    config()->set('admission.quota_writes_enabled', true);
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $firstKey = (string) Str::uuid();

    $first = quotaTestCommand($guidance, $program, $cycle, 48, 0, $firstKey);
    $second = quotaTestCommand($guidance, $program, $cycle, 42, 1);
    $replay = quotaTestCommand($guidance, $program, $cycle, 48, 0, $firstKey);

    expect($replay->getKey())->toBe($first->getKey())
        ->and($replay->to_slots)->toBe(48)
        ->and($second->version)->toBe(2)
        ->and(ProgramQuota::query()->whereKey($first->program_quota_id)->value('slots'))->toBe(42)
        ->and(ProgramQuotaChange::query()->where('program_quota_id', $first->program_quota_id)->count())->toBe(2);

    expect(fn () => app(UpsertProgramQuota::class)->handle(
        $guidance,
        $program,
        $cycle,
        43,
        'Changed payload with reused key',
        0,
        $firstKey,
    ))->toThrow(QuotaCommandConflict::class);
});

test('quota stale edit exposes the current version without mutation', function (): void {
    seedE2Foundation();
    config()->set('admission.quota_writes_enabled', true);
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $first = quotaTestCommand($guidance, $program, $cycle, 48, 0);

    try {
        quotaTestCommand($guidance, $program, $cycle, 40, 0);
        test()->fail('Expected a stale quota command conflict.');
    } catch (QuotaCommandConflict $exception) {
        expect($exception->current)->toBe(['program_id' => $program->id, 'slots' => 48, 'version' => 1]);
    }

    expect(ProgramQuotaChange::query()->where('program_quota_id', $first->program_quota_id)->count())->toBe(1);
});

test('quota audit failure rolls back the capacity and its change receipt', function (): void {
    seedE2Foundation();
    config()->set('admission.quota_writes_enabled', true);
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $audit = Mockery::mock(RecordAuditEvent::class);
    $audit->shouldReceive('handle')->once()->andThrow(new RuntimeException('audit unavailable'));
    app()->instance(RecordAuditEvent::class, $audit);

    expect(fn () => quotaTestCommand($guidance, $program, $cycle, 48, 0))
        ->toThrow(RuntimeException::class, 'audit unavailable');
    expect(ProgramQuota::query()->where('program_id', $program->id)->where('academic_cycle_id', $cycle->id)->exists())->toBeFalse()
        ->and(ProgramQuotaChange::query()->exists())->toBeFalse();
});

test('revoked quota capability denies receipt replay and a closed cycle denies edits', function (): void {
    seedE2Foundation();
    config()->set('admission.quota_writes_enabled', true);
    $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $programHead->givePermissionTo(CapabilityCatalog::PROGRAM_QUOTA_MANAGE);
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $key = (string) Str::uuid();
    $first = quotaTestCommand($programHead, $program, $cycle, 48, 0, $key);
    $programHead->syncRoles([]);
    $programHead->revokePermissionTo(CapabilityCatalog::PROGRAM_QUOTA_MANAGE);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect(fn () => quotaTestCommand($programHead, $program, $cycle, 48, 0, $key))
        ->toThrow(AuthorizationException::class);
    expect(ProgramQuotaChange::query()->whereKey($first->getKey())->exists())->toBeTrue();

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle->forceFill(['is_active' => false, 'closes_on' => now()->subDay()->toDateString()])->save();
    expect(fn () => quotaTestCommand($guidance, $program, $cycle, 30, 1))
        ->toThrow(AuthorizationException::class);
});

test('guidance counselor can upsert program quotas', function (): void {
    seedE2Foundation();
    config()->set('admission.quota_writes_enabled', true);

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    $change = quotaTestCommand($guidance, $program, $cycle, 48, 0);

    expect($change->programQuota->program_id)->toBe($program->id)
        ->and($change->programQuota->academic_cycle_id)->toBe($cycle->id)
        ->and($change->to_slots)->toBe(48)
        ->and($change->reason)->toBe('Synthetic test capacity reason')
        ->and($change->actor_user_id)->toBe($guidance->id)
        ->and($change->version)->toBe(1)
        ->and(ProgramQuota::query()->whereKey($change->program_quota_id)->exists())->toBeTrue();
});

test('program head can upsert quota only for the assigned program', function (): void {
    seedE2Foundation();
    config()->set('admission.quota_writes_enabled', true);

    $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $programHead->givePermissionTo(CapabilityCatalog::PROGRAM_QUOTA_MANAGE);
    $assignedProgram = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    $change = quotaTestCommand($programHead, $assignedProgram, $cycle, 36, 0);

    expect($change->programQuota->program_id)->toBe($assignedProgram->id)
        ->and($change->to_slots)->toBe(36)
        ->and($change->actor_user_id)->toBe($programHead->id)
        ->and(ProgramQuota::query()
            ->where('program_id', $assignedProgram->id)
            ->where('academic_cycle_id', $cycle->id)
            ->exists())->toBeTrue();
});

test('program head is forbidden from upserting quota for an unassigned program', function (): void {
    seedE2Foundation();
    config()->set('admission.quota_writes_enabled', true);

    $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $programHead->givePermissionTo(CapabilityCatalog::PROGRAM_QUOTA_MANAGE);
    $unassignedProgram = Program::query()->where('code', 'BSPSY')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    expect(fn () => quotaTestCommand($programHead, $unassignedProgram, $cycle, 20, 0))
        ->toThrow(AuthorizationException::class);

    expect(ProgramQuota::query()
        ->where('program_id', $unassignedProgram->id)
        ->where('academic_cycle_id', $cycle->id)
        ->exists())->toBeFalse();
});

test('ta-originated quota change creates aftercommit notice for assigned program heads and excludes self', function (): void {
    seedE2Foundation();
    config()->set('admission.quota_writes_enabled', true);
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $ph = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $ph->givePermissionTo(CapabilityCatalog::PROGRAM_QUOTA_MANAGE);

    $change = quotaTestCommand($guidance, $program, $cycle, 52, 0);

    $delivery = NotificationDelivery::query()
        ->where('event_type', 'admission.program_quota_changed.v1')
        ->where('recipient_type', User::class)
        ->where('recipient_id', $ph->id)
        ->first();
    expect($delivery)->not->toBeNull()
        ->and($delivery->channel)->toBe(NotificationDelivery::CHANNEL_IN_APP);

    $selfDelivery = NotificationDelivery::query()
        ->where('event_type', 'admission.program_quota_changed.v1')
        ->where('recipient_type', User::class)
        ->where('recipient_id', $guidance->id)
        ->first();
    expect($selfDelivery)->toBeNull();
});

test('notification dispatch failure does not undo an already committed quota receipt', function (): void {
    seedE2Foundation();
    config()->set('admission.quota_writes_enabled', true);
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $dispatcher = Mockery::mock(NotificationIntentDispatcher::class);
    $dispatcher->shouldReceive('dispatch')->andThrow(new RuntimeException('Delivery domain failure'));
    app()->instance(NotificationIntentDispatcher::class, $dispatcher);

    $change = quotaTestCommand($guidance, $program, $cycle, 55, 0);

    expect($change->to_slots)->toBe(55)
        ->and(ProgramQuota::query()->whereKey($change->program_quota_id)->value('slots'))->toBe(55)
        ->and(ProgramQuotaChange::query()->whereKey($change->id)->exists())->toBeTrue();
});

test('bulk quota command is atomic and all-or-nothing with deterministic member receipts', function (): void {
    seedE2Foundation();
    config()->set('admission.quota_writes_enabled', true);
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $bsn = Program::query()->where('code', 'BSN')->firstOrFail();
    $bsit = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $bulkKey = (string) Str::uuid();

    $members = [
        ['program_id' => $bsn->id, 'slots' => 45, 'reason' => 'BSN expansion', 'expected_version' => 0],
        ['program_id' => $bsit->id, 'slots' => 50, 'reason' => 'BSIT capacity', 'expected_version' => 0],
    ];

    $changes = app(UpsertProgramQuotas::class)->handle($guidance, $cycle, $members, $bulkKey);
    expect($changes)->toHaveCount(2)
        ->and(collect($changes)->firstWhere('program_quota_id', ProgramQuota::query()->where('program_id', $bsn->id)->where('academic_cycle_id', $cycle->id)->value('id'))->to_slots)->toBe(45)
        ->and(collect($changes)->firstWhere('program_quota_id', ProgramQuota::query()->where('program_id', $bsit->id)->where('academic_cycle_id', $cycle->id)->value('id'))->to_slots)->toBe(50);

    // Replaying identical bulk command returns original receipts
    $replayed = app(UpsertProgramQuotas::class)->handle($guidance, $cycle, $members, $bulkKey);
    expect($replayed[0]->getKey())->toBe($changes[0]->getKey())
        ->and($replayed[1]->getKey())->toBe($changes[1]->getKey());

    // Stale member in bulk causes complete rollback
    $staleMembers = [
        ['program_id' => $bsn->id, 'slots' => 46, 'reason' => 'BSN second update', 'expected_version' => 1],
        ['program_id' => $bsit->id, 'slots' => 55, 'reason' => 'BSIT stale version', 'expected_version' => 0],
    ];
    expect(fn () => app(UpsertProgramQuotas::class)->handle($guidance, $cycle, $staleMembers, (string) Str::uuid()))
        ->toThrow(QuotaCommandConflict::class);

    // Verify rollback: BSN stays at 45 (not 46)
    expect(ProgramQuota::query()->where('program_id', $bsn->id)->where('academic_cycle_id', $cycle->id)->value('slots'))->toBe(45);
});
