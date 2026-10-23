<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\User;
use App\Staffing\Mail\StaffEmailChangeVerificationMail;
use Illuminate\Support\Facades\Mail;

function staffUser(): User
{
    return User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
}

test('authenticated applicant cannot view staff profile and receives 403', function (): void {
    seedE2Foundation();
    $submission = submitSyntheticApplicant('applicant.profile.block@example.test');
    $applicantUser = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $submission['access_token'],
        '2001-01-01',
    );

    $this->actingAs($applicantUser)
        ->withSession(['auth.surface' => 'applicant'])
        ->get('/staff/profile')
        ->assertForbidden();
});

test('authenticated applicant cannot mutate staff name and receives 403', function (): void {
    seedE2Foundation();
    $submission = submitSyntheticApplicant('applicant.name.block@example.test');
    $applicantUser = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $submission['access_token'],
        '2001-01-01',
    );

    $this->actingAs($applicantUser)
        ->withSession(['auth.surface' => 'applicant'])
        ->put('/staff/profile/name', ['name' => 'Hacked Name'])
        ->assertForbidden();

    expect($applicantUser->fresh()->name)->not->toBe('Hacked Name');
});

test('authenticated staff with active profile and staff surface can view profile and update name', function (): void {
    seedE2Foundation();
    $staff = staffUser();
    $sessionVersion = (int) StaffAuthenticationState::query()
        ->where('user_id', $staff->id)
        ->firstOrFail()
        ->session_version;

    $this->actingAs($staff)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => $sessionVersion,
    ])
        ->get('/staff/profile')
        ->assertOk();

    $this->actingAs($staff)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => $sessionVersion,
    ])
        ->put('/staff/profile/name', ['name' => 'Valid Staff Name'])
        ->assertRedirect('/staff/profile');

    expect($staff->fresh()->name)->toBe('Valid Staff Name');
});

test('guest can access public email confirmation GET and HEAD with valid token but POST requires auth', function (): void {
    seedE2Foundation();
    $staff = staffUser();
    $sessionVersion = (int) StaffAuthenticationState::query()
        ->where('user_id', $staff->id)
        ->firstOrFail()
        ->session_version;
    Mail::fake();

    $this->actingAs($staff)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => $sessionVersion,
    ])
        ->post('/staff/profile/email/request', [
            'new_email' => 'guest.confirmation@example.test',
            'current_password' => 'password',
        ])
        ->assertRedirect('/staff/profile');

    $token = null;
    Mail::assertQueued(function (StaffEmailChangeVerificationMail $mail) use (&$token): bool {
        if (! $mail->hasTo('guest.confirmation@example.test')) {
            return false;
        }

        $token = $mail->plainToken;

        return true;
    });
    expect($token)->not->toBeNull();

    $this->actingAsGuest();
    $this->get('/staff/profile/email/confirm/'.$token)->assertOk();
    $this->head('/staff/profile/email/confirm/'.$token)->assertOk();
    $this->post('/staff/profile/email/confirm/'.$token)->assertRedirect(route('login'));
});
