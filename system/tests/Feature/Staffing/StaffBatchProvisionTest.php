<?php

declare(strict_types=1);

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffActivationVoucher;
use App\Models\Auth\StaffVoucherBatchRowReceipt;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Staffing\StaffAccountOperationReceipt;
use App\Models\StaffProfile;
use App\Models\User;
use App\Staffing\Actions\BatchProvisionStaff;
use App\Staffing\Actions\IssueStaffActivationVoucher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('HR can batch-provision new personnel with instant activation vouchers and print hashes', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $institutionId = (int) $hr->staffProfile->institution_id;
    $cycle = AcademicCycle::query()->where('institution_id', $institutionId)->firstOrFail();
    StaffProfile::query()
        ->where('institution_id', $institutionId)
        ->where('office', 'program')
        ->whereJsonContains('program_codes', 'BSIT')
        ->whereHas('user.roles', fn ($query) => $query->where('name', 'program_head'))
        ->get()
        ->each(fn (StaffProfile $profile) => $profile->update([
            'program_codes' => array_values(array_diff((array) $profile->program_codes, ['BSIT'])),
        ]));
    $opId1 = (string) Str::uuid();
    $opId2 = (string) Str::uuid();
    $batchOpId = (string) Str::uuid();

    $action = app(BatchProvisionStaff::class);
    $result = $action->handle(
        actor: $hr,
        destinationCycleCode: $cycle->code,
        voucherMode: 'never_expires_single_use',
        operationId: $batchOpId,
        recipients: [
            [
                'operation_id' => $opId1,
                'name' => 'Elena Rostova',
                'office' => 'program',
                'roles' => ['program_head'],
                'program_codes' => ['BSIT'],
            ],
            [
                'operation_id' => $opId2,
                'name' => 'Marcus Vance',
                'office' => 'registrar',
                'roles' => ['registrar_clerk'],
                'program_codes' => [],
            ],
        ],
    );

    expect($result['rows'])->toHaveCount(2)
        ->and($result['rows'][0]['status'])->toBe('issued')
        ->and($result['rows'][0]['token'])->toBeString()
        ->and($result['rows'][0]['fallback'])->toBeString()
        ->and($result['rows'][0]['print_hash'])->toBeString()
        ->and($result['rows'][1]['status'])->toBe('issued');

    $user1 = User::query()->whereKey($result['rows'][0]['user_id'])->firstOrFail();
    expect($user1->name)->toBe('Elena Rostova')
        ->and($user1->email)->toBeNull()
        ->and($user1->staffProfile->active_cycle_code)->toBe($cycle->code)
        ->and($user1->staffProfile->status)->toBe(StaffProfile::STATUS_ACTIVE)
        ->and($user1->authenticationState->activation_completed_at)->toBeNull();

    expect(StaffAccountOperationReceipt::query()->where('operation_id', $opId1)->exists())->toBeTrue();
    $voucher = StaffActivationVoucher::query()->where('public_reference', $result['rows'][0]['public_reference'])->firstOrFail();
    $receipt = StaffAccountOperationReceipt::query()->where('operation_id', $opId1)->firstOrFail();
    $batchReceipt = StaffVoucherBatchRowReceipt::query()->where('operation_id', $batchOpId)->where('target_user_id', $user1->id)->firstOrFail();
    $audit = AuditEvent::query()->where('event_type', 'hr.personnel.fast_provisioned')->firstOrFail();
    $stored = json_encode([
        'voucher' => $voucher->getAttributes(),
        'receipt' => $receipt->getAttributes(),
        'batch_receipt' => $batchReceipt->getAttributes(),
        'audit' => $audit->payload,
    ], JSON_THROW_ON_ERROR);
    expect($voucher->token_digest)->toBe(hash('sha256', $result['rows'][0]['token']))
        ->and($voucher->fallback_hash)->not->toBe($result['rows'][0]['fallback'])
        ->and($stored)->not->toContain($result['rows'][0]['token'])
        ->and($stored)->not->toContain($result['rows'][0]['fallback']);
});

test('batch provisioning replay returns already_committed without creating duplicate accounts', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('institution_id', $hr->staffProfile->institution_id)->firstOrFail();

    $opId = (string) Str::uuid();
    $batchOpId = (string) Str::uuid();

    $action = app(BatchProvisionStaff::class);
    $firstRun = $action->handle(
        actor: $hr,
        destinationCycleCode: $cycle->code,
        voucherMode: 'standard_24h',
        operationId: $batchOpId,
        recipients: [
            [
                'operation_id' => $opId,
                'name' => 'Duplicate Safe Staff',
                'office' => 'guidance',
                'roles' => ['guidance_support_staff'],
            ],
        ],
    );
    expect($firstRun['rows'][0]['status'])->toBe('issued');
    $createdUserId = $firstRun['rows'][0]['user_id'];

    // Replay with identical row operation ID
    $secondRun = $action->handle(
        actor: $hr,
        destinationCycleCode: $cycle->code,
        voucherMode: 'standard_24h',
        operationId: $batchOpId,
        recipients: [
            [
                'operation_id' => $opId,
                'name' => 'Duplicate Safe Staff',
                'office' => 'guidance',
                'roles' => ['guidance_support_staff'],
            ],
        ],
    );

    expect($secondRun['rows'][0]['status'])->toBe('already_committed')
        ->and($secondRun['rows'][0]['user_id'])->toBe($createdUserId);

    expect(User::query()->where('name', 'Duplicate Safe Staff')->count())->toBe(1);
});

test('HTTP endpoint fast-provisions multiple staff rows sharing the same role helper', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('institution_id', $hr->staffProfile->institution_id)->firstOrFail();

    $response = $this->actingAs($hr)->postJson(route('staff.personnel.fast-provision'), [
        'destination_cycle_code' => $cycle->code,
        'voucher_mode' => 'standard_24h',
        'operation_id' => (string) Str::uuid(),
        'recipients' => [
            [
                'operation_id' => (string) Str::uuid(),
                'name' => 'Api Fast Staff',
                'office' => 'registrar',
                'roles' => ['registrar_clerk'],
            ],
            [
                'operation_id' => (string) Str::uuid(),
                'name' => 'Api Fast Staff Two',
                'office' => 'registrar',
                'roles' => ['registrar_clerk'],
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('rows.0.status', 'issued')
        ->assertJsonPath('rows.0.name', 'Api Fast Staff')
        ->assertJsonPath('rows.1.status', 'issued')
        ->assertJsonPath('rows.1.name', 'Api Fast Staff Two');
});
test('batch savepoints roll back voucher and audit failures while successful sibling rows commit', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('institution_id', $hr->staffProfile->institution_id)->firstOrFail();
    $realAudit = app(RecordAuditEvent::class);
    $realIssue = app(IssueStaffActivationVoucher::class);
    $failAuditOnce = true;

    $auditMock = Mockery::mock(RecordAuditEvent::class);
    $auditMock->shouldReceive('handle')->zeroOrMoreTimes()->andReturnUsing(
        function (string $eventType, array $payload, string $actor = 'system') use ($realAudit, &$failAuditOnce): AuditEvent {
            if ($eventType === 'hr.personnel.fast_provisioned' && $failAuditOnce) {
                $failAuditOnce = false;
                throw new RuntimeException('Synthetic audit persistence failure.');
            }

            return $realAudit->handle($eventType, $payload, $actor);
        },
    );

    $issueMock = Mockery::mock(IssueStaffActivationVoucher::class);
    $issueMock->shouldReceive('handle')->zeroOrMoreTimes()->andReturnUsing(
        function (
            User $actor,
            User $target,
            string $purpose = 'activation',
            string $mode = 'standard_24h',
            ?string $customExpiry = null,
            ?string $timezone = null,
            bool $replaceExisting = false,
        ) use ($realIssue): array {
            if ($target->name === 'Voucher Failure Row') {
                throw new RuntimeException('Synthetic voucher issuance failure.');
            }

            return $realIssue->handle($actor, $target, $purpose, $mode, $customExpiry, $timezone, $replaceExisting);
        },
    );

    app()->instance(RecordAuditEvent::class, $auditMock);
    app()->instance(IssueStaffActivationVoucher::class, $issueMock);

    $voucherFailureId = (string) Str::uuid();
    $auditFailureId = (string) Str::uuid();
    $successId = (string) Str::uuid();
    $batchId = (string) Str::uuid();
    $result = app(BatchProvisionStaff::class)->handle(
        actor: $hr,
        destinationCycleCode: $cycle->code,
        voucherMode: 'standard_24h',
        operationId: $batchId,
        recipients: [
            ['operation_id' => $voucherFailureId, 'name' => 'Voucher Failure Row', 'office' => 'guidance', 'roles' => ['guidance_support_staff']],
            ['operation_id' => $auditFailureId, 'name' => 'Audit Failure Row', 'office' => 'registrar', 'roles' => ['registrar_clerk']],
            ['operation_id' => $successId, 'name' => 'Successful Sibling Row', 'office' => 'guidance', 'roles' => ['guidance_support_staff']],
        ],
    );

    expect($result['rows'])->toHaveCount(3)
        ->and($result['rows'][0]['status'])->toBe('failed')
        ->and($result['rows'][1]['status'])->toBe('failed')
        ->and($result['rows'][2]['status'])->toBe('issued')
        ->and(User::query()->where('name', 'Voucher Failure Row')->exists())->toBeFalse()
        ->and(User::query()->where('name', 'Audit Failure Row')->exists())->toBeFalse()
        ->and(User::query()->where('name', 'Successful Sibling Row')->exists())->toBeTrue()
        ->and(StaffAccountOperationReceipt::query()->where('operation_id', $voucherFailureId)->exists())->toBeFalse()
        ->and(StaffAccountOperationReceipt::query()->where('operation_id', $auditFailureId)->exists())->toBeFalse()
        ->and(StaffAccountOperationReceipt::query()->where('operation_id', $successId)->exists())->toBeTrue()
        ->and(StaffVoucherBatchRowReceipt::query()->where('operation_id', $batchId)->count())->toBe(1)
        ->and(StaffActivationVoucher::query()->where('user_id', $result['rows'][2]['user_id'])->count())->toBe(1)
        ->and(AuditEvent::query()->where('event_type', 'hr.personnel.fast_provisioned')->count())->toBe(1)
        ->and(AuditEvent::query()->where('event_type', 'hr.staff.created')->count())->toBe(1)
        ->and(AuditEvent::query()->where('event_type', 'hr.voucher.issued')->count())->toBe(1);
});

test('batch replay binds actor, institution, purpose, cycle, mode, and recipient payload without exposing foreign targets', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $institutionId = (int) $hr->staffProfile->institution_id;
    $cycle = AcademicCycle::query()->where('institution_id', $institutionId)->firstOrFail();
    $alternateCycle = AcademicCycle::query()->create([
        'institution_id' => $institutionId,
        'code' => 'SYN-CYCLE-REPLAY-ALT',
        'name' => 'Alternate Rollover Cycle',
        'school_year' => '2026-2027',
        'semester' => '2',
        'opens_on' => '2026-11-01',
        'closes_on' => '2027-03-01',
        'is_active' => false,
    ]);
    $action = app(BatchProvisionStaff::class);
    $rowOperationId = (string) Str::uuid();
    $batchOperationId = (string) Str::uuid();
    $recipient = [
        'operation_id' => $rowOperationId,
        'name' => 'Replay Binding Recipient',
        'office' => 'guidance',
        'roles' => ['guidance_support_staff'],
    ];

    $created = $action->handle($hr, $cycle->code, 'standard_24h', $batchOperationId, [$recipient]);
    expect($created['rows'][0]['status'])->toBe('issued');

    $changes = [
        ['cycle' => $alternateCycle->code, 'mode' => 'standard_24h', 'name' => $recipient['name'], 'batch_id' => $batchOperationId],
        ['cycle' => $cycle->code, 'mode' => 'extended_7d', 'name' => $recipient['name'], 'batch_id' => (string) Str::uuid()],
        ['cycle' => $cycle->code, 'mode' => 'standard_24h', 'name' => 'Changed Recipient Payload', 'batch_id' => (string) Str::uuid()],
    ];

    foreach ($changes as $change) {
        $changedRecipient = [...$recipient, 'name' => $change['name']];
        $conflicted = $action->handle(
            $hr,
            $change['cycle'],
            $change['mode'],
            $change['batch_id'],
            [$changedRecipient],
        )['rows'][0];

        expect($conflicted['status'])->toBe('conflicted')
            ->and(array_key_exists('user_id', $conflicted))->toBeFalse()
            ->and(array_key_exists('target_user_id', $conflicted))->toBeFalse();
    }

    $foreignInstitution = Institution::query()->create([
        'code' => 'SYN-INST-FOREIGN-REPLAY',
        'name' => 'Foreign Replay Institution',
    ]);
    $foreignActor = User::factory()->create();
    $foreignTarget = User::factory()->create();
    $foreignContexts = [
        ['actor_id' => $foreignActor->id, 'institution_id' => $institutionId, 'purpose' => 'staff_provisioning'],
        ['actor_id' => $hr->id, 'institution_id' => $foreignInstitution->id, 'purpose' => 'staff_provisioning'],
        ['actor_id' => $hr->id, 'institution_id' => $institutionId, 'purpose' => 'prepare'],
    ];

    foreach ($foreignContexts as $context) {
        $operationId = (string) Str::uuid();
        StaffAccountOperationReceipt::query()->create([
            'operation_id' => $operationId,
            'actor_id' => $context['actor_id'],
            'institution_id' => $context['institution_id'],
            'purpose' => $context['purpose'],
            'payload_digest' => str_repeat('f', 64),
            'outcome' => 'committed',
            'target_user_id' => $foreignTarget->id,
        ]);

        $foreignReplay = $action->handle(
            $hr,
            $cycle->code,
            'standard_24h',
            (string) Str::uuid(),
            [[...$recipient, 'operation_id' => $operationId]],
        )['rows'][0];

        expect($foreignReplay['status'])->toBe('conflicted')
            ->and(array_key_exists('user_id', $foreignReplay))->toBeFalse()
            ->and(array_key_exists('target_user_id', $foreignReplay))->toBeFalse()
            ->and(json_encode($foreignReplay, JSON_THROW_ON_ERROR))->not->toContain((string) $foreignTarget->id);
    }

    expect(User::query()->where('name', 'Replay Binding Recipient')->count())->toBe(1);
});

test('batch provisioning validates catalog, office, assignment, and name boundaries per row', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('institution_id', $hr->staffProfile->institution_id)->firstOrFail();
    $result = app(BatchProvisionStaff::class)->handle(
        actor: $hr,
        destinationCycleCode: $cycle->code,
        voucherMode: 'standard_24h',
        operationId: (string) Str::uuid(),
        recipients: [
            ['operation_id' => (string) Str::uuid(), 'name' => 'Wrong Office Role', 'office' => 'guidance', 'roles' => ['registrar_clerk']],
            ['operation_id' => (string) Str::uuid(), 'name' => 'Invalid Catalog Role', 'office' => 'admin', 'roles' => ['dean']],
            ['operation_id' => (string) Str::uuid(), 'name' => 'Invalid Program Assignment', 'office' => 'program', 'roles' => ['program_head'], 'program_codes' => ['NOT-A-PROGRAM']],
            ['operation_id' => (string) Str::uuid(), 'name' => '  A  ', 'office' => 'guidance', 'roles' => ['guidance_support_staff']],
            ['operation_id' => (string) Str::uuid(), 'name' => 'Valid Mixed Row', 'office' => 'guidance', 'roles' => ['guidance_support_staff']],
        ],
    );

    expect(array_column($result['rows'], 'status'))->toBe(['failed', 'failed', 'failed', 'failed', 'issued'])
        ->and(User::query()->where('name', 'Valid Mixed Row')->count())->toBe(1)
        ->and(User::query()->whereIn('name', ['Wrong Office Role', 'Invalid Catalog Role', 'Invalid Program Assignment'])->count())->toBe(0);
});

test('fast provision rechecks active actor after the institution lock', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->with(['roles', 'staffProfile'])->firstOrFail();
    $cycle = AcademicCycle::query()->where('institution_id', $hr->staffProfile->institution_id)->firstOrFail();
    $actorProfileId = $hr->staffProfile->id;
    $institutionId = $hr->staffProfile->institution_id;
    $archiveAfterInstitutionLock = false;

    DB::listen(function (QueryExecuted $query) use (&$archiveAfterInstitutionLock, $actorProfileId): void {
        if (! $archiveAfterInstitutionLock && str_contains(strtolower($query->sql), 'institutions')) {
            DB::table('staff_profiles')->where('id', $actorProfileId)->update(['status' => StaffProfile::STATUS_INACTIVE]);
            $archiveAfterInstitutionLock = true;
        }
    });

    expect(fn () => app(BatchProvisionStaff::class)->handle(
        actor: $hr,
        destinationCycleCode: $cycle->code,
        voucherMode: 'standard_24h',
        operationId: (string) Str::uuid(),
        recipients: [[
            'operation_id' => (string) Str::uuid(),
            'name' => 'Stale Actor Must Not Provision',
            'office' => 'guidance',
            'roles' => ['guidance_support_staff'],
        ]],
    ))->toThrow(AuthorizationException::class);

    expect($archiveAfterInstitutionLock)->toBeTrue()
        ->and(StaffProfile::query()->whereKey($actorProfileId)->value('status'))->toBe(StaffProfile::STATUS_ACTIVE)
        ->and(User::query()->where('name', 'Stale Actor Must Not Provision')->exists())->toBeFalse()
        ->and($institutionId)->toBe((int) $hr->staffProfile->institution_id);
});

test('HTTP batch provisioning rejects foreign cycles and unsupported custom expiry before creating staff', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('institution_id', $hr->staffProfile->institution_id)->firstOrFail();
    $foreignInstitution = Institution::query()->create([
        'code' => 'SYN-INST-FOREIGN-CYCLE',
        'name' => 'Foreign Cycle Institution',
    ]);
    $foreignCycle = AcademicCycle::query()->create([
        'institution_id' => $foreignInstitution->id,
        'code' => 'SYN-CYCLE-FOREIGN',
        'name' => 'Foreign Institution Cycle',
        'opens_on' => '2026-11-01',
        'closes_on' => '2027-03-01',
        'is_active' => false,
    ]);
    $recipient = [
        'operation_id' => (string) Str::uuid(),
        'name' => 'Rejected HTTP Recipient',
        'office' => 'guidance',
        'roles' => ['guidance_support_staff'],
    ];
    $payload = [
        'destination_cycle_code' => $foreignCycle->code,
        'voucher_mode' => 'standard_24h',
        'operation_id' => (string) Str::uuid(),
        'recipients' => [$recipient],
    ];

    $this->actingAs($hr)
        ->postJson(route('staff.personnel.fast-provision'), $payload)
        ->assertUnprocessable();

    $payload['destination_cycle_code'] = $cycle->code;
    $payload['voucher_mode'] = 'custom';
    $this->postJson(route('staff.personnel.fast-provision'), $payload)->assertUnprocessable();

    $payload['voucher_mode'] = 'standard_24h';
    $payload['recipients'][0]['office'] = 'guidance';
    $payload['recipients'][0]['roles'] = ['registrar_clerk'];
    $this->postJson(route('staff.personnel.fast-provision'), $payload)->assertUnprocessable();

    expect(User::query()->where('name', 'Rejected HTTP Recipient')->exists())->toBeFalse();
});
