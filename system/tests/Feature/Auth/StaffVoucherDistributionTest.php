<?php

use App\Models\Auth\StaffActivationVoucher;
use App\Models\Auth\StaffVoucherBatchRowReceipt;
use App\Models\Foundation\Institution;
use App\Models\User;
use App\Staffing\Mail\StaffActivationInviteMail;
use App\Staffing\Support\StaffVoucherLifetime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

beforeEach(function (): void {
    seedE2Foundation();
});

test('activation lifetimes include extended presets and institution-local custom expiry', function (): void {
    $base = CarbonImmutable::parse('2026-02-01T12:00:00Z');
    CarbonImmutable::setTestNow($base);
    $policy = app(StaffVoucherLifetime::class);

    expect($policy->expiry('activation', 'standard_24h'))->toEqual($base->addHours(24))
        ->and($policy->expiry('activation', 'extended_7d'))->toEqual($base->addDays(7))
        ->and($policy->expiry('activation', 'extended_30d'))->toEqual($base->addDays(30))
        ->and($policy->expiry('activation', 'extended_90d'))->toEqual($base->addDays(90))
        ->and($policy->expiry('activation', 'extended_180d'))->toEqual($base->addDays(180))
        ->and($policy->expiry('activation', 'never_expires_single_use', null, null, true))->toBeNull()
        ->and($policy->expiry('activation', 'custom', '2026-02-03T09:30', 'Asia/Manila'))->toEqual(
            CarbonImmutable::parse('2026-02-03T09:30:00', 'Asia/Manila')->utc(),
        );

    expect(fn () => $policy->expiry('password_reset', 'extended_180d'))
        ->toThrow(RuntimeException::class);
    expect(fn () => $policy->expiry('activation', 'custom', '2026-01-31T23:00', 'Asia/Manila'))
        ->toThrow(RuntimeException::class);
    expect(fn () => $policy->expiry('activation', 'never_expires_single_use', null, null, false))
        ->toThrow(RuntimeException::class);

    CarbonImmutable::setTestNow();
});

test('batch voucher issuance returns secrets once and a retry returns only the safe row receipt', function (): void {
    $actor = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $operationId = (string) Str::uuid();
    $payload = [
        'operation_id' => $operationId,
        'targets' => [['id' => $staff->id]],
        'purpose' => 'activation',
        'mode' => 'extended_180d',
    ];

    $first = $this->actingAs($actor)->postJson(route('staff.personnel.vouchers.batch.store'), $payload);
    $first->assertOk()->assertJsonPath('rows.0.status', 'issued');
    expect($first->json('rows.0.token'))->toMatch('/^[a-f0-9]{32}$/')
        ->and($first->json('rows.0.fallback'))->toMatch('/^[A-Z2-7]{4}-[A-Z2-7]{4}$/')
        ->and(StaffVoucherBatchRowReceipt::query()->count())->toBe(1)
        ->and(StaffVoucherBatchRowReceipt::query()->firstOrFail()->institution_id)->toBe((int) $staff->staffProfile->institution_id);

    $firstVoucher = StaffActivationVoucher::query()->where('user_id', $staff->id)->latest('created_at')->firstOrFail();
    expect($firstVoucher->token_digest)->toBe(hash('sha256', $first->json('rows.0.token')));

    $retry = $this->postJson(route('staff.personnel.vouchers.batch.store'), $payload);
    $retry->assertOk()->assertJsonPath('rows.0.status', 'already_issued');
    expect($retry->json('rows.0.token'))->toBeNull()
        ->and(StaffActivationVoucher::query()->where('user_id', $staff->id)->count())->toBe(1);
    $card = [
        'user_id' => $staff->id,
        'reference' => $first->json('rows.0.public_reference'),
        'token' => $first->json('rows.0.token'),
        'fallback' => $first->json('rows.0.fallback'),
        'hash' => $first->json('rows.0.print_hash'),
    ];
    $printed = $this->actingAs($actor)->post(route('staff.personnel.vouchers.batch.print'), [
        'format' => 'html',
        'cards' => [$card],
    ]);
    $institution = $staff->staffProfile->institution ?? Institution::query()->find($staff->staffProfile->institution_id);
    $expectedInstitutionLabel = trim(($institution?->short_name ?? $institution?->name ?? 'ISPSC').' · '.($institution?->campus_name ?? 'Tagudin Campus'));
    $printed->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee($staff->name)
        ->assertSee($expectedInstitutionLabel)
        ->assertSee($first->json('rows.0.public_reference'))
        ->assertSee($first->json('rows.0.fallback'))
        ->assertSee('voucher-card')
        ->assertSee('voucher-qr')
        ->assertSee('data:image/png;base64')
        ->assertSee('<table class="voucher-grid"', false)
        ->assertSee('<td><div class="voucher-card"', false);
    $individual = $this->post(route('staff.personnel.vouchers.batch.print'), [
        'format' => 'html',
        'layout' => 'individual',
        'cards' => [$card],
    ]);
    $individual->assertOk()->assertSee($staff->name)->assertSee('Official Staff Access Voucher');
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actingAs($guidance)->post(route('staff.personnel.vouchers.batch.print'), [
        'format' => 'html',
        'cards' => [$card],
    ])->assertForbidden();
    $this->actingAs($actor);
    $badTicket = [...$card, 'hash' => str_repeat('0', 64)];
    $this->post(route('staff.personnel.vouchers.batch.print'), [
        'format' => 'html',
        'cards' => [$badTicket],
    ])->assertForbidden();

    $mismatch = $this->postJson(route('staff.personnel.vouchers.batch.store'), [
        ...$payload,
        'mode' => 'extended_7d',
    ]);
    $mismatch->assertOk()->assertJsonPath('rows.0.status', 'conflict');

    $replacement = $this->postJson(route('staff.personnel.vouchers.batch.store'), [
        ...$payload,
        'operation_id' => (string) Str::uuid(),
        'replace' => true,
    ]);
    $replacement->assertOk()->assertJsonPath('rows.0.status', 'issued');
    $this->post(route('staff.personnel.vouchers.batch.print'), [
        'format' => 'html',
        'cards' => [$card],
    ])->assertForbidden();
    expect($replacement->json('rows.0.public_reference'))->not->toBe($first->json('rows.0.public_reference'))
        ->and($firstVoucher->refresh()->revoked_at)->not->toBeNull()
        ->and(StaffVoucherBatchRowReceipt::query()->count())->toBe(2);
});
test('Dean voucher issuance cannot fall back to same-office program staff outside owned departments', function (): void {
    $dean = User::query()->where('email', 'cas.dean@nexiam.test')->firstOrFail();
    $otherDepartmentHead = User::query()->where('email', 'bsba.head@nexiam.test')->firstOrFail();

    $this->actingAs($dean)->postJson(route('staff.personnel.vouchers.batch.store'), [
        'operation_id' => (string) Str::uuid(),
        'targets' => [['id' => $otherDepartmentHead->id]],
        'purpose' => 'activation',
        'mode' => 'extended_180d',
    ])->assertForbidden();

    expect(StaffActivationVoucher::query()->where('user_id', $otherDepartmentHead->id)->count())->toBe(0);
});

test('batch invitation delivery does not send to a recipient with no email', function (): void {
    Mail::fake();
    $actor = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $staff->forceFill(['email' => null])->save();

    $response = $this->actingAs($actor)->postJson(route('staff.personnel.vouchers.batch.store'), [
        'operation_id' => (string) Str::uuid(),
        'targets' => [['id' => $staff->id]],
        'purpose' => 'activation',
        'mode' => 'extended_180d',
        'send_email' => true,
    ]);

    $response->assertOk()
        ->assertJsonPath('rows.0.status', 'issued')
        ->assertJsonPath('rows.0.email_queued', false)
        ->assertJsonPath('rows.0.email_blocked', true)
        ->assertJsonMissingPath('rows.0.email_sent');
    Mail::assertNothingQueued();
});

test('batch queue failure keeps the committed voucher reveal in its issued row', function (): void {
    $actor = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    Mail::shouldReceive('to')->once()->with($staff->email)->andThrow(new RuntimeException('queue unavailable'));

    $response = $this->actingAs($actor)->postJson(route('staff.personnel.vouchers.batch.store'), [
        'operation_id' => (string) Str::uuid(),
        'targets' => [['id' => $staff->id]],
        'purpose' => 'activation',
        'mode' => 'extended_180d',
        'send_email' => true,
    ]);

    $response->assertOk()
        ->assertJsonPath('rows.0.status', 'issued')
        ->assertJsonPath('rows.0.email_queued', false)
        ->assertJsonPath('rows.0.email_queue_failed', true)
        ->assertJsonMissingPath('rows.0.email_sent');
    expect($response->json('rows.0.token'))->toMatch('/^[a-f0-9]{32}$/')
        ->and($response->json('rows.0.fallback'))->toMatch('/^[A-Z2-7]{4}-[A-Z2-7]{4}$/')
        ->and($response->json('rows.0.message'))->toContain('could not be queued');
});

test('single queue failure still returns the committed issued voucher', function (): void {
    $actor = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    Mail::shouldReceive('to')->once()->with($staff->email)->andThrow(new RuntimeException('queue unavailable'));

    $response = $this->actingAs($actor)->postJson(route('staff.personnel.vouchers.store', $staff), [
        'send_email' => true,
    ]);

    $response->assertOk()
        ->assertJsonPath('email_queued', false)
        ->assertJsonPath('email_queue_failed', true)
        ->assertJsonMissingPath('email_sent');
    expect($response->json('token'))->toMatch('/^[a-f0-9]{32}$/');
});

test('activation invitation includes the exact expiry and timezone', function (): void {
    $recipient = User::factory()->create(['email' => 'recipient@example.test']);
    $mail = new StaffActivationInviteMail(
        $recipient,
        'https://nexiam.example.test/staff/activate#v=ABCDEFGH23&t=0123456789abcdef0123456789abcdef',
        'ABCDEFGH23',
        'ABCD-EFGH',
        '2026-02-03T17:30:00+00:00',
        'Asia/Manila',
    );

    $html = $mail->render();
    expect($html)->toContain('February 4, 2026 1:30 AM')
        ->toContain('(Asia/Manila)')
        ->not->toContain('expires in 24 hours');
});
