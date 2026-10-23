<?php

use App\Ai\Conversation\XiamConversationSnapshotQuery;
use App\Ai\Policy\UsageBudgetService;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Ai\XiamConversation;
use App\Models\Ai\XiamUsageReservation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['xiam.usage.period' => 'today']);
});

test('only the institutional AI governor receives the unlimited administrator quota tier', function (): void {
    seedE2Foundation();
    config([
        'xiam.usage.admin_limit' => -1,
        'xiam.usage.staff_limit' => 200,
        'xiam.usage.applicant_limit' => 15,
    ]);

    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $registrarClerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $budget = app(UsageBudgetService::class);

    $authorizer = app(CapabilityAuthorizer::class);

    expect($authorizer->allows($admin, CapabilityCatalog::AI_GOVERNANCE_INSTITUTION_MANAGE))->toBeTrue()
        ->and($budget->limit('staff', (string) $admin->id))->toBe(-1)
        ->and($authorizer->allows($guidance, CapabilityCatalog::AI_GOVERNANCE_OFFICE_MANAGE))->toBeTrue()
        ->and($budget->limit('staff', (string) $guidance->id))->toBe(200)
        ->and($authorizer->allows($registrar, CapabilityCatalog::AI_GOVERNANCE_OFFICE_MANAGE))->toBeTrue()
        ->and($budget->limit('staff', (string) $registrar->id))->toBe(200)
        ->and($authorizer->allows($registrarClerk, CapabilityCatalog::AI_GOVERNANCE_OFFICE_MANAGE))->toBeFalse()
        ->and($budget->limit('staff', (string) $registrarClerk->id))->toBe(200)
        ->and($budget->limit('staff', 'standard-staff-actor'))->toBe(200)
        ->and($budget->limit('applicant', 'applicant-actor'))->toBe(15);
});

test('office AI governors can grant same-office staff usage extensions', function (): void {
    seedE2Foundation();
    config(['xiam.usage.staff_limit' => 200]);
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $guidanceStaff = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $registrarStaff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $budget = app(UsageBudgetService::class);

    foreach ([[$guidance, $guidanceStaff], [$registrar, $registrarStaff]] as [$granter, $target]) {
        $extension = $budget->grantExtension(
            $granter,
            (string) $target->id,
            10,
            'Approved temporary workload increase',
        );

        expect($extension->actor_id)->toBe((string) $target->id)
            ->and($extension->amount)->toBe(10);
    }
});

test('registrar clerk keeps the standard quota and cannot grant usage extensions', function (): void {
    seedE2Foundation();
    config(['xiam.usage.staff_limit' => 200]);
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $target = User::query()->where('email', 'registrar.support@nexiam.test')->firstOrFail();

    expect($clerk->can(CapabilityCatalog::AI_GOVERNANCE_OFFICE_MANAGE))->toBeFalse()
        ->and(app(UsageBudgetService::class)->limit('staff', (string) $clerk->id))->toBe(200)
        ->and(fn () => app(UsageBudgetService::class)->grantExtension(
            $clerk,
            (string) $target->id,
            10,
            'Unauthorized extension attempt',
        ))->toThrow(AuthorizationException::class);
});

test('unauthorized users cannot grant usage extensions', function (): void {
    $unauthorized = User::factory()->create();
    $target = User::factory()->create();

    expect(fn () => app(UsageBudgetService::class)->grantExtension(
        $unauthorized,
        (string) $target->id,
        10,
        'Unauthorized extension attempt',
    ))->toThrow(AuthorizationException::class);
});

test('applicant usage view reports the enforced 15 of 15 daily allowance as exhausted', function (): void {
    config(['xiam.usage.applicant_limit' => 15]);

    XiamUsageReservation::query()->create([
        'id' => (string) Str::uuid(),
        'actor_type' => 'applicant',
        'actor_id' => 'applicant-at-limit',
        'institution_id' => null,
        'run_id' => null,
        'unit' => 'asks',
        'amount' => 15,
        'state' => XiamUsageReservation::STATE_SETTLED,
    ]);

    $view = app(UsageBudgetService::class)->view('applicant', 'applicant-at-limit');

    expect($view)->toMatchArray([
        'state' => 'exhausted',
        'used' => 15,
        'limit' => 15,
        'remaining' => 0,
        'credit_balance' => 0,
        'unit' => 'asks',
        'period' => 'today',
    ])->and($view['resets_at'])->toBe(today()->addDay()->startOfDay()->toIso8601String());
});

test('daily usage view counts reserved settled and unknown holds but excludes released and prior-day entries', function (): void {
    $actorId = 'daily-usage-accounting';
    $record = function (int $amount, string $state, mixed $createdAt = null) use ($actorId): void {
        $reservation = XiamUsageReservation::query()->create([
            'id' => (string) Str::uuid(),
            'actor_type' => 'applicant',
            'actor_id' => $actorId,
            'institution_id' => null,
            'run_id' => null,
            'unit' => 'asks',
            'amount' => $amount,
            'state' => $state,
        ]);
        if ($createdAt !== null) {
            $reservation->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }
    };

    $record(4, XiamUsageReservation::STATE_SETTLED);
    $record(3, XiamUsageReservation::STATE_UNKNOWN);
    $record(2, XiamUsageReservation::STATE_RESERVED);
    $record(7, XiamUsageReservation::STATE_RELEASED);
    $record(6, XiamUsageReservation::STATE_SETTLED, now()->subDay());

    $view = app(UsageBudgetService::class)->view('applicant', $actorId);

    expect($view['used'])->toBe(9)
        ->and($view['remaining'])->toBe(6)
        ->and($view['state'])->toBe('available');
});

test('usage view reports extension credits and preserves their remaining allowance above the base cap', function (): void {
    config(['xiam.usage.applicant_limit' => 5]);
    $actorId = 'credited-applicant';
    $record = function (int $amount, string $unit, string $state) use ($actorId): void {
        XiamUsageReservation::query()->create([
            'id' => (string) Str::uuid(),
            'actor_type' => 'applicant',
            'actor_id' => $actorId,
            'institution_id' => null,
            'run_id' => null,
            'unit' => $unit,
            'amount' => $amount,
            'state' => $state,
        ]);
    };

    $record(10, 'asks_extension', XiamUsageReservation::STATE_SETTLED);
    $view = app(UsageBudgetService::class)->view('applicant', $actorId);
    expect($view['used'])->toBe(0)
        ->and($view['limit'])->toBe(5)
        ->and($view['remaining'])->toBe(15)
        ->and($view['credit_balance'])->toBe(10)
        ->and($view['state'])->toBe('available');

    $record(12, 'asks', XiamUsageReservation::STATE_SETTLED);
    $view = app(UsageBudgetService::class)->view('applicant', $actorId);
    expect($view['used'])->toBe(2)
        ->and($view['remaining'])->toBe(3)
        ->and($view['credit_balance'])->toBe(0);
});

test('usage view represents zero and unlimited caps and omits reset for non-daily periods', function (): void {
    $actorId = 'period-usage-account';
    XiamUsageReservation::query()->create([
        'id' => (string) Str::uuid(),
        'actor_type' => 'staff',
        'actor_id' => $actorId,
        'institution_id' => null,
        'run_id' => null,
        'unit' => 'asks',
        'amount' => 3,
        'state' => XiamUsageReservation::STATE_SETTLED,
    ])->forceFill(['created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)])->save();

    config(['xiam.usage.limit' => 0]);
    $zero = app(UsageBudgetService::class)->view('staff', $actorId);
    expect($zero)->toMatchArray([
        'state' => 'exhausted',
        'used' => 0,
        'limit' => 0,
        'remaining' => 0,
        'resets_at' => today()->addDay()->startOfDay()->toIso8601String(),
    ]);

    config(['xiam.usage.limit' => -1, 'xiam.usage.period' => 'lifetime']);
    $unlimited = app(UsageBudgetService::class)->view('staff', $actorId);
    expect($unlimited)->toMatchArray([
        'state' => 'available',
        'used' => 3,
        'limit' => -1,
        'remaining' => null,
        'credit_balance' => 0,
        'period' => 'lifetime',
        'resets_at' => null,
    ]);
});

test('owned conversation snapshots refresh budget after release and reject foreign snapshots without usage metadata', function (): void {
    config(['xiam.usage.applicant_limit' => 15]);
    $actor = User::factory()->create();
    $otherActor = User::factory()->create();
    $conversation = XiamConversation::query()->create([
        'id' => (string) Str::uuid(),
        'actor_type' => 'applicant',
        'actor_id' => (string) $actor->id,
        'institution_id' => null,
        'surface_key' => 'applicant',
        'partition_key' => 'applicant:'.$actor->id.':applicant',
        'context_epoch' => (string) Str::uuid(),
        'epoch_version' => 1,
        'status' => 'open',
    ]);
    $reservation = XiamUsageReservation::query()->create([
        'id' => (string) Str::uuid(),
        'actor_type' => 'applicant',
        'actor_id' => (string) $actor->id,
        'institution_id' => null,
        'run_id' => (string) Str::uuid(),
        'unit' => 'asks',
        'amount' => 1,
        'state' => XiamUsageReservation::STATE_RESERVED,
    ]);

    $snapshots = app(XiamConversationSnapshotQuery::class);
    expect($snapshots->handle($actor, $conversation->id)['usage'])->toMatchArray([
        'used' => 1,
        'remaining' => 14,
    ]);

    app(UsageBudgetService::class)->release((string) $reservation->run_id);
    expect($snapshots->handle($actor, $conversation->id)['usage'])->toMatchArray([
        'used' => 0,
        'remaining' => 15,
    ])->and($snapshots->handle($otherActor, $conversation->id))->toBeNull();
});
test('usage view identifies a configured near-limit tier', function (): void {
    config(['xiam.usage.applicant_limit' => 15]);
    XiamUsageReservation::query()->create([
        'id' => (string) Str::uuid(),
        'actor_type' => 'applicant',
        'actor_id' => 'near-limit-applicant',
        'institution_id' => null,
        'run_id' => null,
        'unit' => 'asks',
        'amount' => 12,
        'state' => XiamUsageReservation::STATE_SETTLED,
    ]);

    expect(app(UsageBudgetService::class)->view('applicant', 'near-limit-applicant')['state'])->toBe('near_limit');
});
