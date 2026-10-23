<?php

use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffProviderIdentity;
use App\Models\User;
use App\Staffing\Mail\StaffEmailChangeVerificationMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

function profileStaff(): User
{
    return User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
}

test('staff can view their profile and security overview', function (): void {
    seedE2Foundation();
    $staff = profileStaff();

    $this->actingAs($staff)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $staff->authenticationState->session_version,
    ])
        ->get('/staff/profile')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Staff/Profile/Show')
            ->where('page_state', 'ready')
            ->where('user.id', $staff->id)
            ->where('user.name', $staff->name)
            ->where('user.email', $staff->email)
            ->where('user.office', $staff->staffProfile->office)
            ->where('user.status', $staff->staffProfile->status)
            ->has('user.roles')
            ->where('security.has_local_password', true)
            ->where('security.mfa_required', true)
            ->where('security.mfa_enrolled', false)
            ->where('security.google_linked', false)
            ->where('security.recovery_codes_remaining', 0)
            ->where('security.pending_email', null)
        );
});

test('staff can update their display name', function (): void {
    seedE2Foundation();
    $staff = profileStaff();

    $this->actingAs($staff)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $staff->authenticationState->session_version,
    ])
        ->put('/staff/profile/name', ['name' => 'Updated Staff Name'])
        ->assertRedirect('/staff/profile');

    expect($staff->fresh()->name)->toBe('Updated Staff Name');
});

test('staff can update their password with valid current password', function (): void {
    seedE2Foundation();
    $staff = profileStaff();
    $state = StaffAuthenticationState::query()->where('user_id', $staff->id)->firstOrFail();
    $initialVersion = (int) $state->session_version;

    $this->actingAs($staff)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
    ])
        ->put('/staff/profile/password', [
            'current_password' => 'password',
            'password' => 'New-strong-password-456',
            'password_confirmation' => 'New-strong-password-456',
        ])
        ->assertRedirect('/staff/profile');

    expect(Hash::check('New-strong-password-456', $staff->fresh()->password))->toBeTrue()
        ->and((int) $state->fresh()->session_version)->toBe($initialVersion + 1);
});

test('updating password fails with invalid current password', function (): void {
    seedE2Foundation();
    $staff = profileStaff();
    $originalHash = $staff->password;
    $state = StaffAuthenticationState::query()->where('user_id', $staff->id)->firstOrFail();
    $initialVersion = (int) $state->session_version;

    $this->actingAs($staff)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
    ])
        ->put('/staff/profile/password', [
            'current_password' => 'incorrect-password',
            'password' => 'New-strong-password-456',
            'password_confirmation' => 'New-strong-password-456',
        ])
        ->assertSessionHasErrors('current_password');

    expect($staff->fresh()->password)->toBe($originalHash)
        ->and((int) $state->fresh()->session_version)->toBe($initialVersion);
});

test('staff can request an email change which sends verification mail and leaves the old email active', function (): void {
    seedE2Foundation();
    $staff = profileStaff();
    Mail::fake();
    $this->actingAs($staff)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $staff->authenticationState->session_version,
    ])
        ->post('/staff/profile/email/request', [
            'new_email' => 'new.clerk@example.test',
            'current_password' => 'password',
        ])
        ->assertRedirect('/staff/profile');

    expect($staff->fresh()->email)->toBe('registrar.clerk@nexiam.test');
    $this->actingAs($staff)
        ->get('/staff/profile')
        ->assertInertia(fn ($page) => $page
            ->where('user.email', 'registrar.clerk@nexiam.test')
            ->where('security.pending_email.new_email', 'new.clerk@example.test')
        );
    Mail::assertQueued(fn (StaffEmailChangeVerificationMail $mail): bool => $mail->hasTo('new.clerk@example.test'));
});

test('confirming an email change updates email, revokes the Google link, and increments the session version', function (): void {
    seedE2Foundation();
    $staff = profileStaff();
    $state = StaffAuthenticationState::query()->where('user_id', $staff->id)->firstOrFail();
    $initialVersion = (int) $state->session_version;
    StaffProviderIdentity::query()->create([
        'user_id' => $staff->id,
        'provider' => 'google',
        'subject' => 'google-subject-for-profile-test',
        'email_at_link' => $staff->email,
        'email_verified_at_link' => true,
        'linked_at' => now(),
    ]);
    Mail::fake();
    $this->actingAs($staff)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $state->session_version,
    ])
        ->post('/staff/profile/email/request', [
            'new_email' => 'verified.clerk@example.test',
            'current_password' => 'password',
        ])
        ->assertRedirect('/staff/profile');

    $token = null;
    Mail::assertQueued(function (StaffEmailChangeVerificationMail $mail) use (&$token): bool {
        if (! $mail->hasTo('verified.clerk@example.test')) {
            return false;
        }

        $token = $mail->plainToken;

        return true;
    });
    expect($token)->not->toBeNull();
    $this->actingAs($staff)
        ->get('/staff/profile/email/confirm/'.$token)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Staff/Profile/Show')
            ->where('page_state', 'confirm_email')
            ->where('email_confirmation.new_email', 'verified.clerk@example.test')
        );
    $this->actingAs($staff)
        ->post('/staff/profile/email/confirm/'.$token, ['current_password' => 'password'])
        ->assertRedirect('/staff/profile');
    expect($staff->fresh()->email)->toBe('verified.clerk@example.test')
        ->and((int) $state->fresh()->session_version)->toBe($initialVersion + 1)
        ->and(StaffProviderIdentity::query()->where('user_id', $staff->id)->firstOrFail()->fresh()->revoked_at)->not->toBeNull();
});

test('staff can cancel a pending email change', function (): void {
    seedE2Foundation();
    $staff = profileStaff();
    $this->actingAs($staff)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $staff->authenticationState->session_version,
    ])
        ->post('/staff/profile/email/request', [
            'new_email' => 'cancelled.clerk@example.test',
            'current_password' => 'password',
        ])
        ->assertRedirect('/staff/profile');

    $this->actingAs($staff)
        ->delete('/staff/profile/email/cancel')
        ->assertRedirect('/staff/profile');

    expect($staff->fresh()->email)->toBe('registrar.clerk@nexiam.test');
    $this->actingAs($staff)
        ->get('/staff/profile')
        ->assertInertia(fn ($page) => $page->where('security.pending_email', null));
});
