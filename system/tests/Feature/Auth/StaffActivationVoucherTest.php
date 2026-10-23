<?php

use App\Models\Auth\StaffActivationVoucher;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AuditEvent;
use App\Models\User;
use App\Staffing\Actions\IssueStaffActivationVoucher;
use App\Staffing\Actions\RequireStaffMfa;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use PragmaRX\Google2FA\Google2FA;

function markStaffUnactivated(User $staff): void
{
    StaffAuthenticationState::query()->updateOrCreate(
        ['user_id' => $staff->id],
        ['activation_completed_at' => null, 'password_set_at' => null, 'session_version' => 1, 'mfa_required' => false],
    );
}

test('HR admin can issue an activation voucher for an unactivated staff member', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    markStaffUnactivated($staff);

    $response = $this->actingAs($hr)->postJson(route('staff.personnel.vouchers.store', $staff));

    $response->assertOk()
        ->assertJsonStructure(['public_reference', 'token', 'fallback', 'expires_at']);

    $voucherData = $response->json();
    expect($voucherData['token'])->toMatch('/^[a-f0-9]{32}$/')
        ->and($voucherData['fallback'])->toMatch('/^[A-Z2-7]{4}-[A-Z2-7]{4}$/')
        ->and($voucherData['expires_at'])->toBeString();

    $voucher = StaffActivationVoucher::query()
        ->where('public_reference', $voucherData['public_reference'])
        ->firstOrFail();
    expect($voucher->token_digest)->toHaveLength(64)
        ->and($voucher->token_digest)->toBe(hash('sha256', $voucherData['token']))
        ->and($voucher->fallback_hash)->not->toBe($voucherData['fallback']);
    expect(Schema::getColumnListing('staff_activation_vouchers'))->not->toContain('token');
    expect(DB::table('staff_activation_vouchers')->where('id', $voucher->id)->value('token_digest'))
        ->not->toBe($voucherData['token']);
    expect(AuditEvent::query()->where('event_type', 'hr.voucher.issued')->exists())->toBeTrue();
});

test('staff can redeem a voucher via its full QR token', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    markStaffUnactivated($staff);
    $voucherData = app(IssueStaffActivationVoucher::class)->handle($hr, $staff);

    $this->post('/staff/activate', [
        'public_reference' => $voucherData['public_reference'],
        'token' => $voucherData['token'],
    ])->assertRedirect('/staff/account-setup');

    expect(StaffActivationVoucher::query()->where('public_reference', $voucherData['public_reference'])->value('redeemed_at'))
        ->not->toBeNull();
    $this->assertNotEmpty(session('staff.pending_attempt_id'));
});

test('staff can redeem a voucher via its eight-character Base32 fallback code', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    markStaffUnactivated($staff);
    $voucherData = app(IssueStaffActivationVoucher::class)->handle($hr, $staff);

    $this->post('/staff/activate', [
        'public_reference' => $voucherData['public_reference'],
        'fallback' => $voucherData['fallback'],
    ])->assertRedirect('/staff/account-setup');

    expect(StaffActivationVoucher::query()->where('public_reference', $voucherData['public_reference'])->value('redeemed_at'))
        ->not->toBeNull();
});

test('redeeming an already redeemed voucher fails', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    markStaffUnactivated($staff);
    $voucherData = app(IssueStaffActivationVoucher::class)->handle($hr, $staff);
    $this->post('/staff/activate', [
        'public_reference' => $voucherData['public_reference'],
        'token' => $voucherData['token'],
    ])->assertRedirect('/staff/account-setup');

    $this->post('/staff/activate', [
        'public_reference' => $voucherData['public_reference'],
        'token' => $voucherData['token'],
    ])->assertSessionHasErrors();
});

test('redeeming an expired voucher fails', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    markStaffUnactivated($staff);
    $voucherData = app(IssueStaffActivationVoucher::class)->handle($hr, $staff);
    StaffActivationVoucher::query()->where('public_reference', $voucherData['public_reference'])->update([
        'expires_at' => now()->subMinute(),
    ]);

    $this->post('/staff/activate', [
        'public_reference' => $voucherData['public_reference'],
        'token' => $voucherData['token'],
    ])->assertSessionHasErrors();
});

test('five consecutive wrong fallback attempts lock out the voucher', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    markStaffUnactivated($staff);
    $voucherData = app(IssueStaffActivationVoucher::class)->handle($hr, $staff);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/staff/activate', [
            'public_reference' => $voucherData['public_reference'],
            'fallback' => 'AAAA-AAAA',
        ])->assertSessionHasErrors();
    }

    $voucher = StaffActivationVoucher::query()->where('public_reference', $voucherData['public_reference'])->firstOrFail();
    expect($voucher->failed_attempts)->toBe(5)
        ->and($voucher->blocked_until)->not->toBeNull();
});

test('guidance counselor can issue voucher for clerk in own office even if auth state was not pre-populated', function () {
    seedE2Foundation();

    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();

    // Delete auth state to simulate legacy or unpopulated record
    StaffAuthenticationState::query()->where('user_id', $clerk->id)->delete();

    $response = $this->actingAs($counselor)->postJson(route('staff.personnel.vouchers.store', $clerk));

    $response->assertOk()
        ->assertJsonStructure(['public_reference', 'token', 'fallback', 'expires_at']);

    expect(StaffAuthenticationState::query()->where('user_id', $clerk->id)->exists())->toBeTrue();
});

test('guidance counselor cannot issue voucher for clerk in a different office', function () {
    seedE2Foundation();

    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $registrarClerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $response = $this->actingAs($counselor)->postJson(route('staff.personnel.vouchers.store', $registrarClerk));

    $response->assertForbidden();
});

test('HR can issue a non-expiring activation voucher with null expires_at and redeem it', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    markStaffUnactivated($staff);

    $response = $this->actingAs($hr)->postJson(route('staff.personnel.vouchers.store', $staff), [
        'purpose' => 'activation',
        'mode' => 'never_expires_single_use',
    ]);

    $response->assertOk()
        ->assertJsonPath('mode', 'never_expires_single_use')
        ->assertJsonPath('expires_at', null)
        ->assertJsonStructure(['public_reference', 'token', 'fallback', 'print_url']);

    $voucher = StaffActivationVoucher::query()->where('public_reference', $response->json('public_reference'))->firstOrFail();
    expect($voucher->expires_at)->toBeNull()
        ->and($voucher->mode)->toBe('never_expires_single_use')
        ->and($voucher->isValid())->toBeTrue();

    // Candidate can redeem the non-expiring voucher via QR token
    $this->post('/staff/activate', [
        'public_reference' => $response->json('public_reference'),
        'token' => $response->json('token'),
    ])->assertRedirect('/staff/account-setup');

    expect($voucher->fresh()->isRedeemed())->toBeTrue();
});

test('HR can issue an extended 7-day voucher', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    markStaffUnactivated($staff);

    $response = $this->actingAs($hr)->postJson(route('staff.personnel.vouchers.store', $staff), [
        'purpose' => 'activation',
        'mode' => 'extended_7d',
    ]);

    $response->assertOk()
        ->assertJsonPath('mode', 'extended_7d');

    $voucher = StaffActivationVoucher::query()->where('public_reference', $response->json('public_reference'))->firstOrFail();
    expect($voucher->expires_at)->not->toBeNull()
        ->and($voucher->expires_at->gt(now()->addDays(6)))->toBeTrue();
});

test('non-expiring mode is strictly rejected for password_reset and mfa_reset', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($hr)->postJson(route('staff.personnel.vouchers.store', $staff), [
        'purpose' => 'password_reset',
        'mode' => 'never_expires_single_use',
    ])->assertForbidden();

    $this->actingAs($hr)->postJson(route('staff.personnel.vouchers.store', $staff), [
        'purpose' => 'mfa_reset',
        'mode' => 'never_expires_single_use',
    ])->assertForbidden();
});

test('ten cumulative wrong fallback attempts permanently disables fallback PIN while QR remains redeemable', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    markStaffUnactivated($staff);
    $voucherData = app(IssueStaffActivationVoucher::class)->handle($hr, $staff, 'activation', 'never_expires_single_use');
    $voucher = StaffActivationVoucher::query()->where('public_reference', $voucherData['public_reference'])->firstOrFail();

    for ($attempt = 1; $attempt <= 10; $attempt++) {
        if ($attempt > 5) {
            $this->travel(16)->minutes();
        }

        $this->post('/staff/activate', [
            'public_reference' => $voucherData['public_reference'],
            'fallback' => 'AAAA-AAAA',
        ])->assertSessionHasErrors('reference');
    }

    $voucher->refresh();
    expect($voucher->lifetime_fallback_attempts)->toBe(10)
        ->and($voucher->fallback_disabled)->toBeTrue();
    $this->travel(16)->minutes();
    // But the high-entropy 128-bit QR token remains redeemable
    $this->post('/staff/activate', [
        'public_reference' => $voucherData['public_reference'],
        'token' => $voucherData['token'],
    ])->assertRedirect('/staff/account-setup');

    expect($voucher->fresh()->isRedeemed())->toBeTrue();
});

test('voucher print route renders official document template with signed ticket', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    markStaffUnactivated($staff);

    $response = $this->actingAs($hr)->postJson(route('staff.personnel.vouchers.store', $staff), [
        'purpose' => 'activation',
        'mode' => 'never_expires_single_use',
    ]);
    $printUrl = $response->json('print_url');
    $printPayload = [
        'reference' => $response->json('public_reference'),
        'token' => $response->json('token'),
        'fallback' => $response->json('fallback'),
        'hash' => $response->json('print_hash'),
        'embedded' => true,
    ];
    expect($printUrl)->toBeString()->toContain('/staff/personnel/'.$staff->id.'/vouchers/print');
    expect($printUrl)->not->toContain($response->json('token'))
        ->and($printUrl)->not->toContain($response->json('fallback'));

    $htmlResponse = $this->actingAs($hr)->postJson($printUrl, $printPayload);
    $htmlResponse->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertHeader('Cache-Control', 'no-store, private');
    expect($htmlResponse->getContent())
        ->toContain('Official Staff Access Voucher')
        ->toContain('No calendar expiry')
        ->toContain($response->json('public_reference'));

    $pdfResponse = $this->actingAs($hr)->postJson($printUrl, [...$printPayload, 'format' => 'pdf']);
    $pdfResponse->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Cache-Control', 'no-store, private');
    expect($pdfResponse->getContent())->toStartWith('%PDF-');

    $this->actingAs($hr)->postJson($printUrl, [...$printPayload, 'hash' => str_repeat('0', 64)])->assertForbidden();
});

test('requiring MFA for an unenrolled voucher recipient leads to first authenticator setup', function (): void {
    seedE2Foundation();
    $this->withoutVite();
    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'bsit.head@nexiam.test')->firstOrFail();
    $state = $staff->authenticationState;
    $state->forceFill([
        'activation_completed_at' => null,
        'password_set_at' => null,
        'mfa_required' => false,
        'mfa_exempt' => true,
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ])->save();

    app(RequireStaffMfa::class)->handle($hr, $staff);
    $voucher = app(IssueStaffActivationVoucher::class)->handle($hr, $staff);
    expect($state->fresh()->mfa_required)->toBeTrue()
        ->and($state->fresh()->hasConfirmedTwoFactor())->toBeFalse()
        ->and($state->fresh()->two_factor_secret)->toBeNull();

    Mail::fake();
    $this->post('/staff/activate', [
        'reference' => $voucher['public_reference'],
        'token' => $voucher['token'],
    ])->assertRedirect('/staff/account-setup');
    $this->post('/staff/account-setup/email', ['email' => 'bsit.head.personal@example.com'])->assertRedirect('/staff/account-setup');
    $this->post('/staff/account-setup/password', [
        'password' => 'RecipientSetup!2026',
        'password_confirmation' => 'RecipientSetup!2026',
    ])->assertRedirect('/staff/account-setup');
    $this->get('/staff/account-setup')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Staff/AccountSetup')
            ->where('can_enroll_factor', true)
            ->where('requires_existing_mfa', false)
            ->where('two_factor_confirmed', false)
            ->where('ready_to_complete', false));

    $enrollment = $this->postJson('/staff/account-setup/two-factor');
    $enrollment->assertOk()->assertJsonStructure(['secret', 'qr_svg']);
    expect($state->fresh()->hasConfirmedTwoFactor())->toBeFalse();
    $this->postJson('/staff/account-setup/two-factor/confirm', [
        'code' => (new Google2FA)->getCurrentOtp($enrollment->json('secret')),
    ])->assertOk()->assertJsonCount(8, 'recovery_codes');
    expect($state->fresh()->hasConfirmedTwoFactor())->toBeTrue();
});

test('a password voucher preserves a confirmed factor and requires its challenge instead of replacement enrollment', function (): void {
    seedE2Foundation();
    $this->withoutVite();
    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'bsit.head@nexiam.test')->firstOrFail();
    $staff->authenticationState->forceFill([
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
    ])->save();
    $voucher = app(IssueStaffActivationVoucher::class)->handle($hr, $staff, 'password_reset');
    Mail::fake();
    $this->post('/staff/activate', [
        'reference' => $voucher['public_reference'],
        'token' => $voucher['token'],
    ])->assertRedirect('/staff/account-setup');
    $this->post('/staff/account-setup/password', [
        'password' => 'RecipientReset!2026',
        'password_confirmation' => 'RecipientReset!2026',
    ])->assertRedirect('/staff/account-setup');

    $this->get('/staff/account-setup')->assertRedirect(route('two-factor.login'));
    $this->postJson('/staff/account-setup/two-factor')->assertForbidden();
    expect($staff->authenticationState->fresh()->two_factor_secret)->toBe('JBSWY3DPEHPK3PXP');
});
