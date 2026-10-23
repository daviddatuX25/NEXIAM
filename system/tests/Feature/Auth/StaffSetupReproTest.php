<?php

use App\Models\User;
use App\Staffing\Actions\IssueStaffActivationVoucher;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;

test('HR can issue voucher for draft staff and staff can complete setup without active attempt or 403 errors', function () {
    seedE2Foundation();
    Mail::fake();

    $hr = User::query()->where('email', 'hr.admin@nexiam.test')->firstOrFail();
    $staff = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    // The staff member is a draft account with @nexiam.test email
    expect($staff->email)->toBe('registrar.clerk@nexiam.test');

    // 1. HR can issue non-expiring activation voucher for draft staff
    $voucherData = app(IssueStaffActivationVoucher::class)->handle(
        actor: $hr,
        target: $staff,
        purpose: 'activation',
        mode: 'never_expires_single_use',
        replaceExisting: true,
    );
    expect($voucherData['expires_at'])->toBeNull();

    // 2. Staff redeems voucher
    $redeemResponse = $this->post('/staff/activate', [
        'reference' => $voucherData['public_reference'],
        'token' => $voucherData['token'],
    ]);
    $redeemResponse->assertRedirect('/staff/account-setup');

    // 3. Setup page loads and does NOT prefill draft nexiam.test email in email_address prop
    $setupResponse = $this->get('/staff/account-setup');
    $setupResponse->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Staff/AccountSetup')
            ->where('staff.email_address', null)
            ->where('staff.is_draft_email', true)
        );

    // 4. Submitting a nexiam.test email must be rejected
    $rejectTestDomainResponse = $this->post('/staff/account-setup/email', [
        'email' => 'new.clerk@nexiam.test',
    ]);
    $rejectTestDomainResponse->assertSessionHasErrors('email');

    // 5. Google setup redirect works without 403 error
    $googleResponse = $this->get('/staff/account-setup/google');
    $googleResponse->assertRedirect();

    // 6. Choosing a real personal email succeeds and advances phase
    $emailResponse = $this->post('/staff/account-setup/email', [
        'email' => 'clerk.personal@example.com',
    ]);
    $emailResponse->assertSessionHasNoErrors();
    $emailResponse->assertRedirect('/staff/account-setup');

    // Staff user now has the chosen real email
    $staff->refresh();
    expect($staff->email)->toBe('clerk.personal@example.com');

    // 7. Setting password succeeds
    $passwordResponse = $this->post('/staff/account-setup/password', [
        'password' => 'NewSecureStaffPass123!',
        'password_confirmation' => 'NewSecureStaffPass123!',
    ]);
    $passwordResponse->assertSessionHasNoErrors();
    $passwordResponse->assertRedirect('/staff/account-setup');

    $staff->refresh();
    expect(Hash::check('NewSecureStaffPass123!', $staff->password))->toBeTrue();
});
