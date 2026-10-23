<?php

use App\Models\Auth\StaffActivationVoucher;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\User;
use App\Staffing\Actions\IssueStaffActivationVoucher;
use App\Staffing\Mail\StaffActivationInviteMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

test('issuing a voucher with email delivery queues an activation invitation', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    Mail::fake();

    $response = $this->actingAs($hr)->postJson(route('staff.personnel.vouchers.store', $staff), [
        'send_email' => true,
    ]);

    $response->assertOk()->assertJsonPath('email_queued', true)->assertJsonMissingPath('email_sent');
    Mail::assertQueued(StaffActivationInviteMail::class, function (StaffActivationInviteMail $mail) use ($staff): bool {
        return $mail->recipient->is($staff)
            && str_contains($mail->activationUrl, '#v=')
            && str_contains($mail->activationUrl, '&t=');
    });
});

test('reissuing a voucher with email delivery queues the replacement activation invitation', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    Mail::fake();

    $response = $this->actingAs($hr)->postJson(route('staff.personnel.vouchers.reissue', $staff), [
        'send_email' => true,
        'confirm_replace' => true,
    ]);

    $response->assertOk()->assertJsonPath('email_queued', true)->assertJsonMissingPath('email_sent');
    Mail::assertQueued(StaffActivationInviteMail::class, function (StaffActivationInviteMail $mail) use ($staff): bool {
        return $mail->recipient->is($staff)
            && str_contains($mail->activationUrl, '#v=')
            && str_contains($mail->activationUrl, '&t=');
    });
});

test('reissuing a voucher revokes pending authentication attempts', function () {
    seedE2Foundation();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    StaffAuthenticationState::query()->updateOrCreate(
        ['user_id' => $staff->id],
        ['activation_completed_at' => null, 'password_set_at' => null, 'session_version' => 1, 'mfa_required' => false],
    );
    $issuedVoucher = app(IssueStaffActivationVoucher::class)->handle($hr, $staff);
    $voucher = StaffActivationVoucher::query()
        ->where('public_reference', $issuedVoucher['public_reference'])
        ->firstOrFail();
    $voucher->update(['redeemed_at' => now()]);
    $attempt = StaffAuthenticationAttempt::query()->create([
        'id' => (string) Str::uuid(),
        'user_id' => $staff->id,
        'purpose' => 'activation',
        'primary_method' => 'voucher',
        'phase' => 'setup',
        'voucher_id' => $voucher->id,
        'expected_session_version' => 1,
        'session_binding_digest' => hash('sha256', Str::random(40)),
        'primary_verified_at' => now(),
        'expires_at' => now()->addHour(),
    ]);

    $this->actingAs($hr)->postJson(route('staff.personnel.vouchers.reissue', $staff), ['confirm_replace' => true])->assertOk();

    $attempt->refresh();
    expect($attempt->revoked_at)->not->toBeNull()
        ->and($attempt->revoke_reason)->toBe('voucher_reissued')
        ->and($voucher->refresh()->revoked_at)->not->toBeNull()
        ->and($voucher->revoke_reason)->toBe('voucher_reissued');
});
